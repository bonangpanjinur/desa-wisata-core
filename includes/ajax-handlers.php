<?php
/**
 * File Name:   includes/ajax-handlers.php
 * Description: Semua penanganan permintaan AJAX untuk Admin dan Frontend.
 * * Fitur Utama:
 * 1. Integrasi API Wilayah (Emsifa & Wilayah.id Legacy).
 * 2. Verifikasi Akun Terpadu (Pedagang & Admin Desa).
 * 3. Manajemen Paket Transaksi & Kuota.
 * 4. Kontrol Status Produk Pedagang.
 */

if (!defined('ABSPATH')) exit;

/**
 * =============================================================================
 * 1. PENANGANAN WILAYAH (API WILAYAH INDONESIA)
 * =============================================================================
 */

// Hook untuk kompatibilitas skrip lama (wilayah.id)
add_action('wp_ajax_dw_get_cities', 'dw_handle_get_cities');
add_action('wp_ajax_dw_get_districts', 'dw_handle_get_districts');
add_action('wp_ajax_dw_get_villages', 'dw_handle_get_villages');

function dw_handle_get_cities() {
    $prov_id = sanitize_text_field($_POST['prov_id']);
    if (empty($prov_id)) wp_send_json_error('Provinsi ID kosong');
    $response = wp_remote_get("https://wilayah.id/api/kabupaten/{$prov_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

function dw_handle_get_districts() {
    $city_id = sanitize_text_field($_POST['city_id']);
    $response = wp_remote_get("https://wilayah.id/api/kecamatan/{$city_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

function dw_handle_get_villages() {
    $dist_id = sanitize_text_field($_POST['dist_id']);
    $response = wp_remote_get("https://wilayah.id/api/kelurahan/{$dist_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

// Hook terpadu (Emsifa API dengan Caching)
add_action('wp_ajax_dw_get_wilayah', 'dw_ajax_get_wilayah');
add_action('wp_ajax_nopriv_dw_get_wilayah', 'dw_ajax_get_wilayah');
function dw_ajax_get_wilayah() {
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $id   = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

    $api_base = 'https://www.emsifa.com/api-wilayah-indonesia/api/';
    $url = '';

    switch ($type) {
        case 'provinsi':  $url = $api_base . 'provinces.json'; break;
        case 'kabupaten': $url = $api_base . 'regencies/' . $id . '.json'; break;
        case 'kecamatan': $url = $api_base . 'districts/' . $id . '.json'; break;
        case 'kelurahan': $url = $api_base . 'villages/' . $id . '.json'; break;
        default: wp_send_json_error(['message' => 'Invalid Request']);
    }

    $cache_key   = 'dw_wilayah_' . $type . '_' . $id;
    $cached_data = get_transient($cache_key);
    if (false !== $cached_data) wp_send_json_success($cached_data);

    $response = wp_remote_get($url);
    if (is_wp_error($response)) wp_send_json_error(['message' => 'Gagal mengambil data wilayah']);

    $data = json_decode(wp_remote_retrieve_body($response));
    if ($data) {
        set_transient($cache_key, $data, DAY_IN_SECONDS);
        wp_send_json_success($data);
    }
    wp_send_json_error(['message' => 'Data kosong']);
}

/**
 * =============================================================================
 * 2. MODERN ACCOUNT VERIFICATION (PEDAGANG & DESA)
 * =============================================================================
 */

/**
 * Mengambil daftar pendaftaran akun (Pedagang & Admin Desa)
 */
add_action('wp_ajax_dw_get_umkm_list', 'dw_ajax_get_umkm_list');
function dw_ajax_get_umkm_list() {
    check_ajax_referer('dw_admin_nonce', 'nonce');
    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error('Akses ditolak.');
    }

    $req_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
    $req_role   = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : ''; 

    // Mapping UI status ke Database value (Approved = Active)
    $status_db = ($req_status === 'approved') ? 'active' : $req_status;

    $args = array(
        'number'  => 100,
        'orderby' => 'user_registered',
        'order'   => 'DESC'
    );

    // Filter Role
    if (!empty($req_role)) {
        $args['role'] = $req_role;
    } else {
        $args['role__in'] = array('pedagang', 'admin_desa');
    }

    // Filter Status Akun (Handling jika meta belum ada)
    $args['meta_query'] = array('relation' => 'OR');
    if ($status_db === 'pending') {
        $args['meta_query'][] = array('key' => '_status_akun', 'compare' => 'NOT EXISTS');
        $args['meta_query'][] = array('key' => '_status_akun', 'value' => 'pending', 'compare' => '=');
        $args['meta_query'][] = array('key' => '_status_akun', 'value' => '', 'compare' => '=');
    } else {
        $args['meta_query'][] = array('key' => '_status_akun', 'value' => $status_db, 'compare' => '=');
    }

    $users = get_users($args);
    $data  = array();

    foreach ($users as $user) {
        // Ambil data lokasi dari meta
        $prov = get_user_meta($user->ID, 'dw_provinsi_name', true) ?: '-';
        $kota = get_user_meta($user->ID, 'dw_kota_name', true) ?: '-';
        $location = ($prov !== '-') ? $kota . ', ' . $prov : 'Lokasi belum diset';

        $data[] = array(
            'id'       => $user->ID,
            'name'     => get_user_meta($user->ID, 'dw_nama_usaha', true) ?: (get_user_meta($user->ID, 'dw_nama_desa', true) ?: $user->display_name),
            'owner'    => $user->display_name,
            'role'     => in_array('admin_desa', (array)$user->roles) ? 'Desa' : 'Pedagang',
            'location' => $location,
            'category' => get_user_meta($user->ID, 'dw_kategori_usaha', true) ?: '-',
            'date'     => date('d/m/Y', strtotime($user->user_registered)),
            'logo'     => "https://ui-avatars.com/api/?name=" . urlencode($user->display_name) . "&background=random",
            'status'   => $req_status
        );
    }
    wp_send_json_success($data);
}

/**
 * Memproses Verifikasi Akun (Setuju/Tolak)
 */
add_action('wp_ajax_dw_process_umkm_verification', 'dw_ajax_process_umkm_verification');
add_action('wp_ajax_dw_verify_merchant', 'dw_ajax_process_umkm_verification'); // Alias kompatibilitas
function dw_ajax_process_umkm_verification() {
    // Mendukung nonce lama dan baru
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_POST['security']) ? $_POST['security'] : '');
    if (!wp_verify_nonce($nonce, 'dw_admin_nonce') && !wp_verify_nonce($nonce, 'dw_nonce')) {
        wp_send_json_error('Sesi keamanan kedaluwarsa.');
    }

    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error('Akses ditolak.');
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($_POST['merchant_id']) ? intval($_POST['merchant_id']) : 0);
    $type    = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : (isset($_POST['verification_action']) ? sanitize_text_field($_POST['verification_action']) : '');
    $reason  = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
    
    if (!$user_id) wp_send_json_error('ID akun tidak valid.');

    $current_admin = wp_get_current_user();
    $verifier_role = in_array('administrator', (array)$current_admin->roles) ? 'admin_pusat' : 'admin_desa';

    if ($type === 'approve' || $type === 'confirm') {
        update_user_meta($user_id, '_status_akun', 'active');
        update_user_meta($user_id, '_approved_by_role', $verifier_role); 
        update_user_meta($user_id, '_approved_by_user_id', $current_admin->ID);
        update_user_meta($user_id, '_approved_date', current_time('mysql'));

        if (function_exists('dw_add_log')) {
            dw_add_log($current_admin->ID, "Menyetujui pendaftaran ID {$user_id} sebagai {$verifier_role}", 'info');
        }
        wp_send_json_success('Akun telah berhasil diaktifkan.');
    } else {
        update_user_meta($user_id, '_status_akun', 'rejected');
        update_user_meta($user_id, '_rejection_reason', $reason);
        wp_send_json_success('Pendaftaran akun ditolak.');
    }
}

/**
 * =============================================================================
 * 3. VERIFIKASI PAKET TRANSAKSI & KUOTA
 * =============================================================================
 */

add_action('wp_ajax_dw_verify_package_payment', 'dw_handle_package_verification');
function dw_handle_package_verification() {
    check_ajax_referer('dw_admin_nonce', 'security');
    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) wp_send_json_error('Akses ditolak.');

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
        }
        wp_send_json_success(['message' => 'Pembayaran paket dikonfirmasi.']);
    }
    wp_send_json_error(['message' => 'Gagal memproses verifikasi paket.']);
}

/**
 * =============================================================================
 * 4. DASHBOARD PRODUK
 * =============================================================================
 */

add_action('wp_ajax_dw_toggle_product_status', 'dw_handle_toggle_product');
function dw_handle_toggle_product() {
    check_ajax_referer('dw_nonce', 'security');
    $product_id = intval($_POST['product_id']);
    $new_status = sanitize_text_field($_POST['status']);
    
    $updated = wp_update_post([
        'ID' => $product_id,
        'post_status' => ($new_status === 'aktif') ? 'publish' : 'private'
    ]);

    if ($updated) wp_send_json_success(['message' => 'Status produk diperbarui.']);
    wp_send_json_error(['message' => 'Gagal memperbarui produk.']);
}