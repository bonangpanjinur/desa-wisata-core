<?php
/**
 * File Name:   includes/ajax-handlers.php
 * Description: Semua penanganan permintaan AJAX untuk Admin dan Frontend.
 * * Fitur Utama:
 * 1. Konsolidasi API Wilayah (Emsifa & Wilayah.id).
 * 2. Verifikasi UMKM Modern (Pending, Active, Rejected).
 * 3. Manajemen Paket Transaksi & Kuota.
 * 4. Kontrol Status Produk Pedagang.
 */

if (!defined('ABSPATH')) exit;

/**
 * =============================================================================
 * 1. PENANGANAN WILAYAH (API WILAYAH INDONESIA)
 * =============================================================================
 */

/**
 * Mengambil data wilayah secara dinamis (Provinsi -> Kelurahan)
 * Menggunakan Transient API untuk cache selama 24 jam guna efisiensi bandwidth.
 */
add_action('wp_ajax_dw_get_wilayah', 'dw_ajax_get_wilayah');
add_action('wp_ajax_nopriv_dw_get_wilayah', 'dw_ajax_get_wilayah');
function dw_ajax_get_wilayah() {
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $id   = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

    if (empty($type)) {
        wp_send_json_error(['message' => 'Parameter type diperlukan.']);
    }

    $api_base = 'https://www.emsifa.com/api-wilayah-indonesia/api/';
    $url = '';

    // Routing API berdasarkan tipe permintaan
    switch ($type) {
        case 'provinsi':  $url = $api_base . 'provinces.json'; break;
        case 'kabupaten': $url = $api_base . 'regencies/' . $id . '.json'; break;
        case 'kecamatan': $url = $api_base . 'districts/' . $id . '.json'; break;
        case 'kelurahan': $url = $api_base . 'villages/' . $id . '.json'; break;
        default: wp_send_json_error(['message' => 'Tipe wilayah tidak valid.']);
    }

    // Cek cache untuk menghindari request berulang ke server luar
    $cache_key   = 'dw_wilayah_' . $type . '_' . $id;
    $cached_data = get_transient($cache_key);

    if (false !== $cached_data) {
        wp_send_json_success($cached_data);
    }

    // Eksekusi request ke API eksternal
    $response = wp_remote_get($url, ['timeout' => 15]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Server wilayah tidak merespon.']);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if ($data) {
        set_transient($cache_key, $data, DAY_IN_SECONDS);
        wp_send_json_success($data);
    } else {
        wp_send_json_error(['message' => 'Data wilayah tidak ditemukan.']);
    }
}

/**
 * =============================================================================
 * 2. MODERN UMKM VERIFICATION (UI/UX ENHANCED)
 * =============================================================================
 */

/**
 * Mengambil daftar UMKM untuk tabel dashboard verifikator.
 */
add_action('wp_ajax_dw_get_umkm_list', 'dw_ajax_get_umkm_list');
function dw_ajax_get_umkm_list() {
    check_ajax_referer('dw_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error('Akses ditolak. Anda tidak memiliki izin verifikasi.');
    }

    $status_map = [
        'pending'  => 'pending',
        'approved' => 'active',
        'rejected' => 'rejected'
    ];

    $req_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
    $meta_value = isset($status_map[$req_status]) ? $status_map[$req_status] : 'pending';

    $args = array(
        'role'       => 'pedagang',
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key'     => '_status_akun',
                'value'   => $meta_value,
                'compare' => ($meta_value === 'pending') ? 'NOT EXISTS' : '=' 
            )
        ),
        'number' => 50,
        'orderby' => 'user_registered',
        'order'   => 'DESC'
    );

    // Tambahan filter jika status eksplisit diset 'pending'
    if ($meta_value === 'pending') {
        $args['meta_query'][] = array('key' => '_status_akun', 'value' => 'pending', 'compare' => '=');
    }

    $users = get_users($args);
    $data  = array();

    foreach ($users as $user) {
        $data[] = array(
            'id'       => $user->ID,
            'name'     => get_user_meta($user->ID, 'dw_nama_usaha', true) ?: $user->display_name,
            'owner'    => $user->display_name,
            'category' => get_user_meta($user->ID, 'dw_kategori_usaha', true) ?: 'Belum diatur',
            'date'     => date('d/m/Y H:i', strtotime($user->user_registered)),
            'logo'     => "https://ui-avatars.com/api/?name=" . urlencode($user->display_name) . "&background=random",
            'status'   => $req_status
        );
    }

    wp_send_json_success($data);
}

/**
 * Memproses verifikasi (Setuju/Tolak) pendaftaran UMKM.
 */
add_action('wp_ajax_dw_process_umkm_verification', 'dw_ajax_process_umkm_verification');
function dw_ajax_process_umkm_verification() {
    check_ajax_referer('dw_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error('Anda tidak memiliki wewenang untuk melakukan aksi ini.');
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $type    = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : ''; 
    $reason  = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
    
    if (!$user_id) wp_send_json_error('ID Pedagang tidak ditemukan.');

    $current_admin = wp_get_current_user();
    $verifier_role = in_array('administrator', (array) $current_admin->roles) ? 'admin_pusat' : 'admin_desa';

    if ($type === 'approve') {
        // Update Status & Metadata Verifikasi
        update_user_meta($user_id, '_status_akun', 'active');
        update_user_meta($user_id, '_approved_by_role', $verifier_role); 
        update_user_meta($user_id, '_approved_by_user_id', $current_admin->ID);
        update_user_meta($user_id, '_approved_date', current_time('mysql'));

        // Pencatatan Log Sistem
        if (function_exists('dw_add_log')) {
            dw_add_log($current_admin->ID, "Menyetujui pendaftaran UMKM ID: {$user_id} ({$verifier_role})", 'success');
        }

        wp_send_json_success('UMKM telah berhasil diverifikasi dan diaktifkan.');
    } 
    elseif ($type === 'reject') {
        update_user_meta($user_id, '_status_akun', 'rejected');
        update_user_meta($user_id, '_rejection_reason', $reason);

        if (function_exists('dw_add_log')) {
            dw_add_log($current_admin->ID, "Menolak pendaftaran UMKM ID: {$user_id}. Alasan: {$reason}", 'warning');
        }

        wp_send_json_success('Pendaftaran telah ditolak dan alasan telah disimpan.');
    }

    wp_send_json_error('Aksi verifikasi tidak valid.');
}

/**
 * =============================================================================
 * 3. MANAJEMEN PAKET & PRODUK
 * =============================================================================
 */

/**
 * Konfirmasi pembayaran paket transaksi pedagang.
 */
add_action('wp_ajax_dw_verify_package_payment', 'dw_handle_package_verification');
function dw_handle_package_verification() {
    check_ajax_referer('dw_admin_nonce', 'security');

    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error(['message' => 'Akses ditolak.']);
    }

    $order_id = intval($_POST['order_id']);
    $status   = sanitize_text_field($_POST['status']); 

    if ($status === 'confirm') {
        update_post_meta($order_id, '_payment_status', 'completed');
        $pedagang_id = get_post_meta($order_id, '_pedagang_id', true);
        $kuota_paket = get_post_meta($order_id, '_quota_amount', true);
        
        if ($pedagang_id && $kuota_paket) {
            $current_quota = (int) get_post_meta($pedagang_id, '_sisa_kuota', true);
            update_post_meta($pedagang_id, '_sisa_kuota', $current_quota + $kuota_paket);
            update_post_meta($pedagang_id, '_status_akun', 'active');
            
            dw_add_log(get_current_user_id(), "Konfirmasi paket Order ID {$order_id} untuk Pedagang {$pedagang_id}", 'info');
        }
        wp_send_json_success(['message' => 'Pembayaran paket dikonfirmasi dan kuota ditambahkan.']);
    }
    wp_send_json_error(['message' => 'Gagal memproses verifikasi paket.']);
}

/**
 * Toggle status publish/private produk pedagang.
 */
add_action('wp_ajax_dw_toggle_product_status', 'dw_handle_toggle_product');
function dw_handle_toggle_product() {
    check_ajax_referer('dw_nonce', 'security');
    
    $product_id = intval($_POST['product_id']);
    $new_status = sanitize_text_field($_POST['status']);
    
    $updated = wp_update_post([
        'ID'          => $product_id,
        'post_status' => ($new_status === 'aktif') ? 'publish' : 'private'
    ]);

    if ($updated) {
        wp_send_json_success(['message' => 'Status ketersediaan produk diperbarui.']);
    }
    wp_send_json_error(['message' => 'Gagal memperbarui status produk.']);
}