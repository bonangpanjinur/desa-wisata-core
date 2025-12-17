<?php
/**
 * TAMBAHAN: HANDLER VERIFIKASI PAKET
 * Silakan salin kode di bawah ini dan tempelkan (append) di bagian paling bawah
 * file includes/ajax-handlers.php Anda yang asli (sebelum penutup ?> jika ada).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_dw_proses_verifikasi_paket', 'dw_ajax_proses_verifikasi_paket');

function dw_ajax_proses_verifikasi_paket() {
    global $wpdb;

    // 1. Verifikasi Nonce & Permission
    $transaksi_id = isset($_POST['transaksi_id']) ? intval($_POST['transaksi_id']) : 0;
    
    if (!check_ajax_referer('dw_verify_paket_' . $transaksi_id, 'security', false)) {
        wp_send_json_error(['message' => 'Keamanan token tidak valid. Silakan refresh halaman.']);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Anda tidak memiliki izin untuk melakukan ini.']);
    }

    $tipe_aksi = sanitize_text_field($_POST['tipe_aksi']); // 'approve' atau 'reject'

    // 2. Ambil Data Transaksi
    $table_transaksi = $wpdb->prefix . 'dw_transaksi_paket';
    $table_paket = $wpdb->prefix . 'dw_paket_transaksi';

    $transaksi = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_transaksi WHERE id = %d", $transaksi_id));

    if (!$transaksi) {
        wp_send_json_error(['message' => 'Data transaksi tidak ditemukan.']);
    }

    if ($transaksi->status !== 'pending') {
        wp_send_json_error(['message' => 'Transaksi ini sudah diproses sebelumnya.']);
    }

    // 3. Logika TERIMA (Approve)
    if ($tipe_aksi === 'approve') {
        
        // Ambil detail paket yang dibeli
        $paket = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_paket WHERE id = %d", $transaksi->paket_id));
        
        if (!$paket) {
            wp_send_json_error(['message' => 'Data paket referensi tidak ditemukan.']);
        }

        // Mulai Transaksi Database
        $wpdb->query('START TRANSACTION');

        try {
            // A. Update Status Transaksi jadi 'completed'
            $updated_transaksi = $wpdb->update(
                $table_transaksi,
                [
                    'status' => 'completed',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $transaksi_id]
            );

            if ($updated_transaksi === false) {
                throw new Exception('Gagal mengupdate status transaksi.');
            }

            // B. Hitung Tanggal Expired Baru untuk User
            $user_id = $transaksi->user_id;
            $durasi_hari = intval($paket->durasi_hari);
            
            // Cek apakah user sudah punya paket aktif sebelumnya?
            $current_expired = get_user_meta($user_id, 'dw_paket_expired_date', true);
            $now = time();
            
            if ($current_expired && strtotime($current_expired) > $now) {
                // Jika masih aktif, tambahkan hari ke tanggal expired yang ada
                $new_expired_timestamp = strtotime($current_expired . " + $durasi_hari days");
            } else {
                // Jika sudah mati atau baru, hitung dari hari ini
                $new_expired_timestamp = strtotime("+$durasi_hari days");
            }
            
            $new_expired_date = date('Y-m-d H:i:s', $new_expired_timestamp);

            // C. Update User Meta (Aktifkan Fitur Pedagang)
            update_user_meta($user_id, 'dw_paket_id', $paket->id);
            update_user_meta($user_id, 'dw_paket_nama', $paket->nama_paket);
            update_user_meta($user_id, 'dw_paket_status', 'active');
            update_user_meta($user_id, 'dw_paket_expired_date', $new_expired_date);
            
            $wpdb->query('COMMIT');
            
            wp_send_json_success(['message' => 'Paket berhasil disetujui. Pedagang kini aktif hingga ' . date('d M Y', $new_expired_timestamp)]);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }

    } 
    // 4. Logika TOLAK (Reject)
    elseif ($tipe_aksi === 'reject') {
        
        $updated = $wpdb->update(
            $table_transaksi,
            [
                'status' => 'rejected',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $transaksi_id]
        );

        if ($updated !== false) {
            wp_send_json_success(['message' => 'Permintaan paket berhasil ditolak.']);
        } else {
            wp_send_json_error(['message' => 'Gagal mengupdate database.']);
        }

    } else {
        wp_send_json_error(['message' => 'Aksi tidak dikenal.']);
    }
}