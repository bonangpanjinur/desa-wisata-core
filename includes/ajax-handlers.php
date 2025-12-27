<?php
/**
 * File Name:   includes/ajax-handlers.php
 * Description: Semua penanganan permintaan AJAX terpadu v3.6 utuh dan lengkap.
 * Mencakup: Wilayah, Akun (Pedagang, Desa, Pembeli), Verifikator, Paket, & Produk.
 */

if (!defined('ABSPATH')) exit;

/**
 * =============================================================================
 * 1. PENANGANAN WILAYAH (EMSIFA API & LEGACY SUPPORT)
 * =============================================================================
 */

// Legacy Hooks (Untuk form pendaftaran lama yang memanggil fungsi ini secara spesifik)
add_action('wp_ajax_dw_get_cities', 'dw_handle_get_cities');
add_action('wp_ajax_dw_get_districts', 'dw_handle_get_districts');
add_action('wp_ajax_dw_get_villages', 'dw_handle_get_villages');

function dw_handle_get_cities() {
    $prov_id = sanitize_text_field($_POST['prov_id'] ?? '');
    if (empty($prov_id)) wp_send_json_error('Provinsi ID kosong');
    $response = wp_remote_get("https://wilayah.id/api/kabupaten/{$prov_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

function dw_handle_get_districts() {
    $city_id = sanitize_text_field($_POST['city_id'] ?? '');
    $response = wp_remote_get("https://wilayah.id/api/kecamatan/{$city_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

function dw_handle_get_villages() {
    $dist_id = sanitize_text_field($_POST['dist_id'] ?? '');
    $response = wp_remote_get("https://wilayah.id/api/kelurahan/{$dist_id}.json");
    if (is_wp_error($response)) wp_send_json_error('Gagal menghubungi API Wilayah');
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
}

// Unified Hook (API Emsifa dengan caching Transient WP)
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
    if (is_wp_error($response)) wp_send_json_error(['message' => 'Gagal mengambil data']);

    $data = json_decode(wp_remote_retrieve_body($response));
    if ($data) {
        set_transient($cache_key, $data, DAY_IN_SECONDS);
        wp_send_json_success($data);
    }
    wp_send_json_error(['message' => 'Data kosong']);
}

/**
 * =============================================================================
 * 2. MANAJEMEN VERIFIKATOR UMKM (ADMIN PUSAT)
 * =============================================================================
 */

// Mendapatkan Daftar Verifikator
add_action('wp_ajax_dw_get_verifikator_list', 'dw_get_verifikator_list');
function dw_get_verifikator_list() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');

    $table = $wpdb->prefix . 'dw_verifikator';
    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

    $data = array();
    foreach ($results as $row) {
        $data[] = array(
            'id'       => $row->id,
            'name'     => $row->nama_lengkap,
            'location' => ($row->kecamatan ? $row->kecamatan . ', ' : '') . $row->kabupaten,
            'total'    => $row->total_verifikasi_sukses,
            'income'   => 'Rp ' . number_format($row->total_pendapatan_komisi, 0, ',', '.'),
            'balance'  => 'Rp ' . number_format($row->saldo_saat_ini, 0, ',', '.'),
            'status'   => $row->status,
            'wa'       => $row->nomor_wa
        );
    }
    wp_send_json_success($data);
}

// Menambahkan Verifikator Baru
add_action('wp_ajax_dw_add_verifikator', 'dw_ajax_add_verifikator');
function dw_ajax_add_verifikator() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) wp_send_json_error('Akses ditolak.');

    $user_id = intval($_POST['user_id']);
    $nama    = sanitize_text_field($_POST['nama']);
    $nik     = sanitize_text_field($_POST['nik']);
    $wa      = sanitize_text_field($_POST['wa']);
    
    if (!$user_id || empty($nama)) wp_send_json_error('Data tidak lengkap.');

    $table = $wpdb->prefix . 'dw_verifikator';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id_user = %d", $user_id));
    if ($exists) wp_send_json_error('User sudah terdaftar sebagai verifikator.');

    $inserted = $wpdb->insert($table, array(
        'id_user'      => $user_id,
        'nama_lengkap' => $nama,
        'nik'          => $nik,
        'nomor_wa'     => $wa,
        'kabupaten'    => sanitize_text_field($_POST['kota'] ?? ''),
        'status'       => 'aktif'
    ));

    if ($inserted) wp_send_json_success('Verifikator berhasil ditambahkan.');
    wp_send_json_error('Gagal menyimpan data.');
}

/**
 * =============================================================================
 * 3. MANAJEMEN PEMBELI / CUSTOMER
 * =============================================================================
 */

add_action('wp_ajax_dw_get_buyer_list', 'dw_get_buyer_list');
function dw_get_buyer_list() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');

    $args = array(
        'role__in' => array('subscriber', 'customer', 'pembeli'),
        'number'   => 100,
        'orderby'  => 'user_registered',
        'order'    => 'DESC'
    );
    $users = get_users($args);

    $data = array();
    foreach ($users as $user) {
        $total_order = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dw_transaksi WHERE id_pembeli = %d", $user->ID));
        
        $data[] = array(
            'id'       => $user->ID,
            'name'     => $user->display_name,
            'email'    => $user->user_email,
            'orders'   => $total_order ?: 0,
            'date'     => date('d/m/Y', strtotime($user->user_registered)),
            'status'   => 'aktif',
            'logo'     => "https://ui-avatars.com/api/?name=" . urlencode($user->display_name) . "&background=random"
        );
    }
    wp_send_json_success($data);
}

/**
 * =============================================================================
 * 4. VERIFIKASI AKUN (PEDAGANG & DESA DARI TABEL KUSTOM)
 * =============================================================================
 */

add_action('wp_ajax_dw_get_umkm_list', 'dw_ajax_get_umkm_list');
function dw_ajax_get_umkm_list() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');
    
    $req_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
    $req_role   = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : ''; 

    $results = array();
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';

    // Query Pedagang UMKM
    if (empty($req_role) || $req_role === 'pedagang') {
        $st = ($req_status === 'pending') ? 'menunggu_desa' : (($req_status === 'approved') ? 'disetujui' : 'ditolak');
        $pedagang = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nama_toko as name, nama_pemilik as owner, kabupaten_nama as location, created_at, 'Pedagang' as role_type 
             FROM $table_pedagang WHERE status_pendaftaran = %s", $st
        ));
        if ($pedagang) $results = array_merge($results, $pedagang);
    }

    // Query Desa
    if (empty($req_role) || $req_role === 'admin_desa') {
        $st = ($req_status === 'approved') ? 'aktif' : 'pending';
        $desa = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nama_desa as name, 'Admin Desa' as owner, kabupaten as location, created_at, 'Desa' as role_type 
             FROM $table_desa WHERE status = %s", $st
        ));
        if ($desa) $results = array_merge($results, $desa);
    }

    $data = array();
    foreach ($results as $row) {
        $data[] = array(
            'id'       => $row->id,
            'name'     => $row->name,
            'owner'    => $row->owner,
            'role'     => $row->role_type,
            'location' => $row->location ?: 'Lokasi N/A',
            'date'     => date('d/m/Y', strtotime($row->created_at)),
            'logo'     => "https://ui-avatars.com/api/?name=" . urlencode($row->name) . "&background=random",
            'status'   => $req_status
        );
    }
    wp_send_json_success($data);
}

add_action('wp_ajax_dw_process_umkm_verification', 'dw_ajax_process_umkm_verification');
function dw_ajax_process_umkm_verification() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');

    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error('Akses ditolak.');
    }

    $id      = intval($_POST['user_id']);
    $role    = sanitize_text_field($_POST['role']);
    $type    = sanitize_text_field($_POST['type']);
    $current_user_id = get_current_user_id();

    $table = ($role === 'Desa') ? $wpdb->prefix . 'dw_desa' : $wpdb->prefix . 'dw_pedagang';
    
    if ($type === 'approve') {
        if ($role === 'Desa') {
            $wpdb->update($table, array('status' => 'aktif'), array('id' => $id));
        } else {
            $wpdb->update($table, array(
                'status_pendaftaran' => 'disetujui', 
                'status_akun' => 'aktif', 
                'id_verifikator' => $current_user_id,
                'is_verified' => 1,
                'verified_at' => current_time('mysql')
            ), array('id' => $id));
            
            // Komisi Verifikator (Contoh: Rp 10.000 per verifikasi sukses)
            $komisi = 10000;
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}dw_verifikator 
                 SET total_verifikasi_sukses = total_verifikasi_sukses + 1, 
                     total_pendapatan_komisi = total_pendapatan_komisi + %d, 
                     saldo_saat_ini = saldo_saat_ini + %d 
                 WHERE id_user = %d", 
                $komisi, $komisi, $current_user_id
            ));
        }
        wp_send_json_success('Verifikasi berhasil. Akun telah diaktifkan.');
    } else {
        $wpdb->update($table, ($role === 'Desa' ? array('status' => 'pending') : array('status_pendaftaran' => 'ditolak')), array('id' => $id));
        wp_send_json_success('Pendaftaran telah ditolak.');
    }
}

/**
 * =============================================================================
 * 5. OPERASIONAL (PAKET & PRODUK)
 * =============================================================================
 */

// Verifikasi Pembayaran Paket Transaksi
add_action('wp_ajax_dw_verify_package_payment', 'dw_handle_package_verification');
function dw_handle_package_verification() {
    check_ajax_referer('dw_admin_nonce', 'security');
    
    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error(['message' => 'Akses ditolak.']);
    }

    $order_id = intval($_POST['order_id']);
    $status   = sanitize_text_field($_POST['status']); // 'confirm' or 'reject'

    if ($status === 'confirm') {
        update_post_meta($order_id, '_payment_status', 'completed');
        
        // Tambahkan kuota ke akun pedagang (logika internal)
        $pedagang_id = get_post_meta($order_id, '_pedagang_id', true);
        $kuota_paket = get_post_meta($order_id, '_quota_amount', true);
        
        if ($pedagang_id && $kuota_paket) {
            $current_quota = (int) get_post_meta($pedagang_id, '_sisa_kuota', true);
            update_post_meta($pedagang_id, '_sisa_kuota', $current_quota + $kuota_paket);
        }

        wp_send_json_success(['message' => 'Pembayaran paket dikonfirmasi. Kuota telah ditambahkan.']);
    }

    wp_send_json_error(['message' => 'Gagal memproses verifikasi paket.']);
}

// Toggle Status Produk (Aktif/Nonaktif)
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