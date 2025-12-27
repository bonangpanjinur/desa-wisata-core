<?php
/**
 * File Name:   includes/ajax-handlers.php
 * Description: Semua penanganan permintaan AJAX terpadu untuk Desa Wisata Core v3.6.
 * * Fitur Utama:
 * 1. Integrasi API Wilayah (Emsifa & Wilayah.id Legacy).
 * 2. Verifikasi Akun Terpadu (Pedagang & Admin Desa) dari Tabel Kustom.
 * 3. Manajemen Verifikator UMKM (Statistik & Komisi).
 * 4. Manajemen Paket Transaksi & Status Produk.
 */

if (!defined('ABSPATH')) exit;

/**
 * =============================================================================
 * 1. PENANGANAN WILAYAH (API WILAYAH INDONESIA)
 * =============================================================================
 */

// Legacy Hooks (wilayah.id)
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

// Unified Hook (Emsifa API)
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
 * 2. MANAJEMEN VERIFIKATOR UMKM (UNTUK ADMIN PUSAT)
 * =============================================================================
 */

/**
 * Mengambil Daftar Verifikator (Untuk Admin Pusat)
 */
add_action('wp_ajax_dw_get_verifikator_list', 'dw_get_verifikator_list');
function dw_get_verifikator_list() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');

    $table = $wpdb->prefix . 'dw_verifikator';
    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

    $data = array();
    foreach ($results as $row) {
        $data[] = array(
            'id'         => $row->id,
            'name'       => $row->nama_lengkap,
            'location'   => $row->kecamatan . ', ' . $row->kabupaten,
            'total_docs' => $row->total_verifikasi_sukses,
            'income'     => 'Rp ' . number_format($row->total_pendapatan_komisi, 0, ',', '.'),
            'balance'    => 'Rp ' . number_format($row->saldo_saat_ini, 0, ',', '.'),
            'status'     => $row->status,
            'wa'         => $row->nomor_wa
        );
    }
    wp_send_json_success($data);
}

/**
 * =============================================================================
 * 3. VERIFIKASI AKUN (UNTUK VERIFIKATOR / ADMIN DESA)
 * =============================================================================
 */

/**
 * Mengambil Daftar Akun dari Tabel dw_pedagang & dw_desa
 */
add_action('wp_ajax_dw_get_umkm_list', 'dw_ajax_get_umkm_list');
function dw_ajax_get_umkm_list() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error('Akses ditolak.');
    }

    $req_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
    $req_role   = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : ''; 

    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';
    
    $results = array();

    // 1. Ambil Data Pedagang (UMKM)
    if (empty($req_role) || $req_role === 'pedagang') {
        $status_query = ($req_status === 'pending') ? 'menunggu_desa' : (($req_status === 'approved') ? 'disetujui' : 'ditolak');
        
        $pedagang = $wpdb->get_results($wpdb->prepare(
            "SELECT id, id_user, nama_toko as name, nama_pemilik as owner, kabupaten_nama, provinsi_nama, status_pendaftaran, created_at, 'Pedagang' as role_type 
             FROM $table_pedagang WHERE status_pendaftaran = %s",
            $status_query
        ));
        
        if ($pedagang) $results = array_merge($results, $pedagang);
    }

    // 2. Ambil Data Desa
    if (empty($req_role) || $req_role === 'admin_desa') {
        $status_query = ($req_status === 'approved') ? 'aktif' : 'pending';
        
        $desa = $wpdb->get_results($wpdb->prepare(
            "SELECT id, id_user_desa as id_user, nama_desa as name, nama_desa as owner, kabupaten as kabupaten_nama, provinsi as provinsi_nama, status, created_at, 'Desa' as role_type 
             FROM $table_desa WHERE status = %s",
            $status_query
        ));
        
        if ($desa) $results = array_merge($results, $desa);
    }

    $data = array();
    foreach ($results as $row) {
        $location = ($row->kabupaten_nama) ? $row->kabupaten_nama . ', ' . $row->provinsi_nama : 'Lokasi tidak diset';
        
        $data[] = array(
            'id'       => $row->id,
            'user_id'  => $row->id_user,
            'name'     => $row->name,
            'owner'    => $row->owner,
            'role'     => $row->role_type,
            'location' => $location,
            'date'     => date('d/m/Y', strtotime($row->created_at)),
            'logo'     => "https://ui-avatars.com/api/?name=" . urlencode($row->name) . "&background=random",
            'status'   => $req_status
        );
    }

    wp_send_json_success($data);
}

/**
 * Memproses Verifikasi UMKM / Desa (Approve & Reject)
 * Mencatat Komisi jika yang memproses adalah verifikator terdaftar.
 */
add_action('wp_ajax_dw_process_umkm_verification', 'dw_ajax_process_umkm_verification');
function dw_ajax_process_umkm_verification() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');

    if (!current_user_can('manage_options') && !current_user_can('admin_desa')) {
        wp_send_json_error('Akses ditolak.');
    }

    $id      = intval($_POST['user_id']); // Row ID di tabel kustom
    $role    = sanitize_text_field($_POST['role']); // 'Desa' atau 'Pedagang'
    $type    = sanitize_text_field($_POST['type']); // 'approve' atau 'reject'
    $reason  = sanitize_textarea_field($_POST['reason']);
    $current_user_id = get_current_user_id();

    $table_name = ($role === 'Desa') ? $wpdb->prefix . 'dw_desa' : $wpdb->prefix . 'dw_pedagang';
    
    if ($type === 'approve') {
        if ($role === 'Desa') {
            $update = $wpdb->update($table_name, array('status' => 'aktif'), array('id' => $id));
        } else {
            $update = $wpdb->update($table_name, 
                array(
                    'status_pendaftaran' => 'disetujui',
                    'status_akun' => 'aktif',
                    'is_verified' => 1,
                    'verified_at' => current_time('mysql'),
                    'id_verifikator' => $current_user_id
                ), 
                array('id' => $id)
            );

            // LOGIKA KOMISI VERIFIKATOR UMKM
            $komisi = 10000; // Contoh: Rp 10.000 per verifikasi sukses
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}dw_verifikator 
                 SET total_verifikasi_sukses = total_verifikasi_sukses + 1, 
                     total_pendapatan_komisi = total_pendapatan_komisi + %d,
                     saldo_saat_ini = saldo_saat_ini + %d
                 WHERE id_user = %d",
                $komisi, $komisi, $current_user_id
            ));
        }
        
        if ($update !== false) {
            wp_send_json_success('Akun berhasil diaktifkan dan diverifikasi.');
        }
    } else {
        $update = ($role === 'Desa') ? 
            $wpdb->update($table_name, array('status' => 'pending', 'alasan_penolakan' => $reason), array('id' => $id)) :
            $wpdb->update($table_name, array('status_pendaftaran' => 'ditolak'), array('id' => $id));
            
        if ($update !== false) {
            wp_send_json_success('Pendaftaran ditolak.');
        }
    }

    wp_send_json_error('Gagal memperbarui database.');
}

/**
 * =============================================================================
 * 4. MANAJEMEN PAKET & PRODUK (LAMA)
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