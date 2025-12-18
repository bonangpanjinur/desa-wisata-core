<?php
/**
 * File Name:   includes/ajax-handlers.php
 * Description: Semua penanganan permintaan AJAX untuk Admin dan Frontend.
 * * Fitur Utama:
 * 1. Verifikasi Pedagang (dengan pencatatan role verifikator).
 * 2. Integrasi API Wilayah (Provinsi, Kota, Kec, Kel).
 * 3. Verifikasi Paket Transaksi.
 * 4. Manajemen Produk & Banner via AJAX.
 */

if (!defined('ABSPATH')) exit;

/**
 * =============================================================================
 * 1. PENANGANAN WILAYAH (API WILAYAH.ID)
 * =============================================================================
 */

// Load Kabupaten/Kota berdasarkan Provinsi
add_action('wp_ajax_dw_get_cities', 'dw_handle_get_cities');
function dw_handle_get_cities() {
    $prov_id = sanitize_text_field($_POST['prov_id']);
    if (empty($prov_id)) wp_send_json_error('Provinsi ID kosong');

    $response = wp_remote_get("https://wilayah.id/api/kabupaten/{$prov_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');

    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

// Load Kecamatan berdasarkan Kota
add_action('wp_ajax_dw_get_districts', 'dw_handle_get_districts');
function dw_handle_get_districts() {
    $city_id = sanitize_text_field($_POST['city_id']);
    $response = wp_remote_get("https://wilayah.id/api/kecamatan/{$city_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

// Load Kelurahan berdasarkan Kecamatan
add_action('wp_ajax_dw_get_villages', 'dw_handle_get_villages');
function dw_handle_get_villages() {
    $dist_id = sanitize_text_field($_POST['dist_id']);
    $response = wp_remote_get("https://wilayah.id/api/kelurahan/{$dist_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

/**
 * =============================================================================
 * 2. VERIFIKASI PEDAGANG & TOKO (DENGAN LOGIKA KOMISI)
 * =============================================================================
 */

add_action('wp_ajax_dw_verify_merchant', 'dw_handle_merchant_verification');
/**
 * Menangani persetujuan pendaftaran pedagang.
 * Mencatat siapa yang menyetujui (Admin Pusat atau Admin Desa).
 */
function dw_handle_merchant_verification() {
    // Validasi Keamanan
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'dw_nonce')) {
        wp_send_json_error(['message' => 'Sesi kedaluwarsa, silakan refresh halaman.']);
    }

    $merchant_id = intval($_POST['merchant_id']);
    $action      = sanitize_text_field($_POST['verification_action']); // 'approve' atau 'reject'
    
    // Identifikasi Role Verifikator
    $current_user = wp_get_current_user();
    $is_admin_pusat = in_array('administrator', (array) $current_user->roles);
    $verifier_role  = $is_admin_pusat ? 'admin_pusat' : 'admin_desa';

    if ($action === 'approve') {
        // Update Status Akun di Tabel/Meta
        update_post_meta($merchant_id, '_status_akun', 'active');
        
        // PENTING: Simpan siapa yang melakukan verifikasi
        // Jika Admin Desa yang ACC, maka Desa berhak mendapat komisi persentase paket
        update_post_meta($merchant_id, '_approved_by_role', $verifier_role); 
        update_post_meta($merchant_id, '_approved_by_user_id', $current_user->ID);
        update_post_meta($merchant_id, '_approved_date', current_time('mysql'));

        // Log Aktivitas
        if (function_exists('dw_add_log')) {
            dw_add_log($current_user->ID, "Menyetujui pedagang ID: {$merchant_id} sebagai {$verifier_role}", 'info');
        }
        
        wp_send_json_success(['message' => 'Pedagang berhasil diaktifkan oleh ' . str_replace('_', ' ', $verifier_role)]);

    } elseif ($action === 'reject') {
        update_post_meta($merchant_id, '_status_akun', 'rejected');
        update_post_meta($merchant_id, '_rejection_reason', sanitize_textarea_field($_POST['reason']));
        
        wp_send_json_success(['message' => 'Pendaftaran pedagang telah ditolak.']);
    }
    
    wp_send_json_error(['message' => 'Aksi tidak dikenal.']);
}

/**
 * =============================================================================
 * 3. VERIFIKASI PAKET TRANSAKSI
 * =============================================================================
 */

add_action('wp_ajax_dw_verify_package_payment', 'dw_handle_package_verification');
function dw_handle_package_verification() {
    check_ajax_referer('dw_admin_nonce', 'security');

    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error(['message' => 'Akses ditolak.']);
    }

    $order_id = intval($_POST['order_id']);
    $status   = sanitize_text_field($_POST['status']); // 'confirm' or 'reject'

    if ($status === 'confirm') {
        // 1. Update status transaksi paket jadi Lunas
        update_post_meta($order_id, '_payment_status', 'completed');
        
        // 2. Tambahkan kuota transaksi ke akun pedagang
        $pedagang_id = get_post_meta($order_id, '_pedagang_id', true);
        $kuota_paket = get_post_meta($order_id, '_quota_amount', true);
        
        if ($pedagang_id && $kuota_paket) {
            $current_quota = (int) get_post_meta($pedagang_id, '_sisa_kuota', true);
            update_post_meta($pedagang_id, '_sisa_kuota', $current_quota + $kuota_paket);
            update_post_meta($pedagang_id, '_status_akun', 'active'); // Re-aktifkan jika suspend kuota
        }

        wp_send_json_success(['message' => 'Pembayaran paket dikonfirmasi. Kuota telah ditambahkan.']);
    }

    wp_send_json_error(['message' => 'Gagal memproses verifikasi paket.']);
}

/**
 * =============================================================================
 * 4. DASHBOARD & LAIN-LAIN
 * =============================================================================
 */

// Toggle Status Produk (Aktif/Nonaktif) via AJAX
add_action('wp_ajax_dw_toggle_product_status', 'dw_handle_toggle_product');
function dw_handle_toggle_product() {
    check_ajax_referer('dw_nonce', 'security');
    
    $product_id = intval($_POST['product_id']);
    $new_status = sanitize_text_field($_POST['status']);
    
    $updated = wp_update_post([
        'ID' => $product_id,
        'post_status' => ($new_status === 'aktif') ? 'publish' : 'private'
    ]);

    if ($updated) {
        wp_send_json_success(['message' => 'Status produk diperbarui.']);
    }
    wp_send_json_error(['message' => 'Gagal memperbarui produk.']);
}