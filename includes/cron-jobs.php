<?php
/**
 * File: includes/cron-jobs.php
 * Menangani tugas-tugas terjadwal (Cron Jobs) otomatis.
 * * CAKUPAN TUGAS (Sesuai Roadmap):
 * 1. FASE 2: Auto-Complete Order (Ojek & Ekspedisi) -> Hook: dw_cron_auto_complete_orders
 * 2. FASE 5: Sync Payment Status (Xendit/Safety Net) -> Hook: dw_cron_sync_payments
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 1. Daftarkan Interval Waktu Kustom
 */
function dw_add_cron_intervals( $schedules ) {
    // Interval 30 Menit untuk Sync Payment yang lebih agresif
    $schedules['every_30_mins'] = array(
        'interval' => 1800,
        'display'  => __( 'Setiap 30 Menit' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'dw_add_cron_intervals' );

/**
 * 2. Registrasi Hook Jadwal (Scheduler)
 * Fungsi ini harus dipanggil saat aktivasi plugin atau di init.
 */
function dw_schedule_cron_jobs() {
    // A. Jadwal Auto-Complete Order (Fase 2) - Jalan setiap jam
    if ( ! wp_next_scheduled( 'dw_cron_auto_complete_orders' ) ) {
        wp_schedule_event( time(), 'hourly', 'dw_cron_auto_complete_orders' );
    }

    // B. Jadwal Sync Payment Status (Fase 5) - Jalan setiap 30 menit
    if ( ! wp_next_scheduled( 'dw_cron_sync_payments' ) ) {
        wp_schedule_event( time(), 'every_30_mins', 'dw_cron_sync_payments' );
    }
}
// Hook ke init untuk memastikan cron tetap terjadwal jika terhapus tidak sengaja
add_action( 'init', 'dw_schedule_cron_jobs' );

/**
 * 3. Bersihkan Jadwal (Cleanup)
 * Dipanggil saat plugin dinonaktifkan.
 */
function dw_clear_cron_jobs() {
    $jobs = [ 'dw_cron_auto_complete_orders', 'dw_cron_sync_payments' ];
    foreach ( $jobs as $job ) {
        $timestamp = wp_next_scheduled( $job );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $job );
        }
    }
}
register_deactivation_hook( DW_FILE, 'dw_clear_cron_jobs' );

/**
 * ==============================================================================
 * IMPLEMENTASI LOGIKA TUGAS (HANDLERS)
 * ==============================================================================
 */

/**
 * A. LOGIKA SYNC PAYMENT (Fase 5 - Safety Net)
 * Mengecek transaksi PENDING (Xendit/QRIS) yang mungkin statusnya miss-sync.
 */
function dw_execute_sync_payments() {
    global $wpdb;
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';

    // Cek apakah tabel transaksi ada (mencegah error fatal jika belum install DB)
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_transaksi'" ) != $table_transaksi ) {
        return;
    }

    // Ambil transaksi 'menunggu_pembayaran' (Pending)
    // Kriteria: Dibuat > 20 menit lalu & < 24 jam lalu
    $orders = $wpdb->get_results( "
        SELECT * FROM $table_transaksi 
        WHERE status_transaksi = 'menunggu_pembayaran' 
        AND metode_pembayaran IN ('xendit', 'qris', 'va', 'ewallet')
        AND created_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        LIMIT 20
    " );

    if ( empty( $orders ) ) {
        return;
    }

    // Pastikan dependensi tersedia
    if ( ! class_exists( 'DW_Xendit_Gateway' ) ) {
        // Coba load manual jika path sesuai standar plugin
        $gateway_path = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/classes/gateways/class-dw-xendit-gateway.php';
        if ( file_exists( $gateway_path ) ) {
            require_once $gateway_path;
        } else {
            return; // Nyerah, file gak ada
        }
    }

    // Load Commission Handler jika perlu
    if ( ! function_exists( 'dw_distribute_order_commissions' ) ) {
        $comm_path = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/commission-handler.php';
        if ( file_exists( $comm_path ) ) require_once $comm_path;
    }

    $gateway = new DW_Xendit_Gateway();

    foreach ( $orders as $order ) {
        // Logika External ID / Invoice ID
        // Prioritas 1: Ambil dari Meta Post (jika tersimpan)
        $xendit_invoice_id = get_post_meta( $order->id, '_xendit_invoice_id', true );
        
        // Prioritas 2: Jika kosong, coba reconstruct dari pola (Fallback)
        // Format umum: TRX-{ID}-{TIMESTAMP}. 
        // Note: Ini berisiko jika timestamp beda detik. Lebih aman pakai Priority 1.
        if ( ! $xendit_invoice_id ) {
             continue; // Skip jika tidak ada ID referensi yang valid
        }

        // Call API Xendit
        $invoice = $gateway->get_invoice( $xendit_invoice_id );

        if ( is_wp_error( $invoice ) ) {
            error_log( "[DW Cron] Error Order #{$order->id}: " . $invoice->get_error_message() );
            continue;
        }

        $status_xendit = isset( $invoice['status'] ) ? $invoice['status'] : '';

        // 1. KASUS SUKSES (PAID/SETTLED)
        if ( $status_xendit === 'PAID' || $status_xendit === 'SETTLED' ) {
            
            // Update Status DB Custom
            $wpdb->update( 
                $table_transaksi, 
                [ 
                    'status_transaksi'   => 'pembayaran_dikonfirmasi',
                    'tanggal_pembayaran' => current_time( 'mysql' )
                ], 
                [ 'id' => $order->id ] 
            );

            // Update Status WooCommerce / Sub Transaction
            // Asumsi tabel sub transaksi ada
            $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_sub'" ) == $table_sub ) {
                $wpdb->update(
                    $table_sub,
                    [ 'status_pesanan' => 'dikemas' ],
                    [ 'id_transaksi' => $order->id ]
                );
            }

            // Update Status WooCommerce Order (jika pakai WC Orders)
            $wc_order = wc_get_order( $order->id );
            if ( $wc_order ) {
                $wc_order->payment_complete();
                $wc_order->add_order_note( 'Pembayaran terverifikasi otomatis via Cron Job (Safety Net).' );
            }

            // Distribusi Komisi
            if ( function_exists( 'dw_distribute_order_commissions' ) ) {
                dw_distribute_order_commissions( $order->id );
            }

        // 2. KASUS EXPIRED
        } elseif ( $status_xendit === 'EXPIRED' ) {
            $wpdb->update( 
                $table_transaksi, 
                [ 'status_transaksi' => 'dibatalkan' ], 
                [ 'id' => $order->id ] 
            );
            
            $wc_order = wc_get_order( $order->id );
            if ( $wc_order ) {
                $wc_order->update_status( 'cancelled', 'Invoice Xendit Expired (dicek via Cron).' );
            }
        }
    }
}
add_action( 'dw_cron_sync_payments', 'dw_execute_sync_payments' );

/**
 * B. LOGIKA AUTO-COMPLETE (Fase 2)
 * Note: Logika detailnya didelegasikan ke Class Handler agar file ini tidak terlalu panjang.
 * Pastikan class 'DW_Transaction_Handler' sudah dimuat di init.php.
 */
/* * Hook 'dw_cron_auto_complete_orders' akan ditangkap oleh:
 * DW_Transaction_Handler::run_auto_complete_logic() 
 * (Lihat file: includes/classes/class-dw-transaction-handler.php)
 */