<?php
/**
 * File: includes/cron-jobs.php
 * Menangani tugas-tugas terjadwal (Cron Jobs) otomatis.
 * UPDATE FASE 5: Menambahkan Job Sync Payment Status (Safety Net).
 * * Tugas Utama:
 * 1. dw_sync_payment_status: Cek status Xendit untuk order pending > 30 menit.
 * 2. dw_daily_cleanup: (Opsional) Membersihkan keranjang lama/log.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 1. Daftarkan Jadwal Kustom (Jika interval bawaan WP kurang)
 */
function dw_add_cron_intervals( $schedules ) {
    // Interval 30 Menit
    $schedules['every_30_mins'] = array(
        'interval' => 1800,
        'display'  => __( 'Every 30 Minutes' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'dw_add_cron_intervals' );

/**
 * 2. Registrasi Hook saat Plugin Aktif
 * Dipanggil di includes/activation.php sebenarnya, tapi kita definisikan hooknya di sini.
 */
if ( ! wp_next_scheduled( 'dw_cron_sync_payments' ) ) {
    wp_schedule_event( time(), 'hourly', 'dw_cron_sync_payments' );
}

/**
 * 3. LOGIKA UTAMA: Sinkronisasi Status Pembayaran
 * Mengecek transaksi PENDING yang dibuat lebih dari 20 menit lalu.
 */
function dw_execute_sync_payments() {
    global $wpdb;
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';

    // Ambil transaksi yang masih 'menunggu_pembayaran' dan dibuat > 20 menit lalu
    // Kita batasi 20 order per run agar server tidak berat
    $orders = $wpdb->get_results( "
        SELECT * FROM $table_transaksi 
        WHERE status_transaksi = 'menunggu_pembayaran' 
        AND metode_pembayaran IN ('xendit', 'qris', 'va')
        AND created_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        LIMIT 20
    " );

    if ( empty( $orders ) ) {
        return;
    }

    // Pastikan gateway class tersedia
    if ( ! class_exists( 'DW_Xendit_Gateway' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'classes/gateways/class-dw-xendit-gateway.php';
    }
    
    // Pastikan commission handler tersedia
    if ( ! function_exists( 'dw_distribute_order_commissions' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'commission-handler.php';
    }

    $gateway = new DW_Xendit_Gateway();

    foreach ( $orders as $order ) {
        // Coba cari External ID (Format: TRX-{ID}-{TIMESTAMP})
        // Kita perlu mencari External ID yang valid. 
        // Jika kode_unik disimpan sebagai external_id di DB, gunakan itu. 
        // Jika tidak, kita mungkin perlu reconstruct atau simpan external_id di meta saat create invoice.
        // Asumsi: Kode Unik di DB = External ID (atau mirip).
        // Tapi create_invoice pakai format: 'TRX-' . $transaction_id . '-' . time()
        // Masalah: Kita tidak tahu timestamp pastinya.
        // SOLUSI TERBAIK: Simpan 'external_id' Xendit di tabel transaksi saat create invoice.
        // Jika belum ada kolom itu, kita coba cari Invoice ID dari meta post (jika disimpan).
        
        // Untuk Safety Net ini bekerja efektif, 'includes/classes/gateways/class-dw-xendit-gateway.php' 
        // idealnya menyimpan Invoice ID Xendit ke post_meta '_xendit_invoice_id'.
        
        $xendit_invoice_id = get_post_meta( $order->id, '_xendit_invoice_id', true );
        
        // Jika tidak punya ID Xendit, kita tidak bisa cek status pastinya. Skip.
        if ( ! $xendit_invoice_id ) {
            continue; 
        }

        // Panggil API Xendit Get Invoice
        $invoice = $gateway->get_invoice( $xendit_invoice_id );

        if ( is_wp_error( $invoice ) ) {
            // Log error koneksi, tapi jangan stop proses order lain
            error_log( "DW Cron Error Order #{$order->id}: " . $invoice->get_error_message() );
            continue;
        }

        // Cek Status Invoice di Xendit
        $status_xendit = isset($invoice['status']) ? $invoice['status'] : '';

        if ( $status_xendit === 'PAID' || $status_xendit === 'SETTLED' ) {
            // ALHAMDULILLAH, SUDAH BAYAR TAPI STATUS LOKAL BELUM UPDATE
            // Lakukan Update Otomatis
            
            // 1. Update Status DB Utama
            $wpdb->update( 
                $table_transaksi, 
                [ 
                    'status_transaksi'   => 'pembayaran_dikonfirmasi',
                    'tanggal_pembayaran' => current_time( 'mysql' )
                ], 
                [ 'id' => $order->id ] 
            );

            // 2. Update Sub-Order jadi 'dikemas'
            $wpdb->update(
                $wpdb->prefix . 'dw_transaksi_sub',
                [ 'status_pesanan' => 'dikemas' ],
                [ 'id_transaksi' => $order->id ]
            );

            // 3. Trigger Distribusi Komisi (PENTING)
            dw_distribute_order_commissions( $order->id );

            // 4. Log Aktivitas
            if ( function_exists( 'dw_log_activity' ) ) {
                dw_log_activity( 'CRON_AUTO_SYNC', "Order #{$order->id} otomatis di-update ke PAID via Cron Job.", 0 );
            }

        } elseif ( $status_xendit === 'EXPIRED' ) {
            // Invoice sudah kadaluarsa
            $wpdb->update( 
                $table_transaksi, 
                [ 'status_transaksi' => 'dibatalkan' ], 
                [ 'id' => $order->id ] 
            );
        }
    }
}
add_action( 'dw_cron_sync_payments', 'dw_execute_sync_payments' );

/**
 * 4. Helper: Unschedule saat Deaktivasi Plugin
 * (Biasanya dipanggil di file deactivation.php)
 */
function dw_clear_cron_jobs() {
    $timestamp = wp_next_scheduled( 'dw_cron_sync_payments' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'dw_cron_sync_payments' );
    }
}