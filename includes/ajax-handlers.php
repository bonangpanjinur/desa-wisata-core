<?php
/**
 * File Name:   includes/ajax-handlers.php
 * Description: Semua penanganan permintaan AJAX terpadu v3.7.
 * Mencakup: Wilayah, Verifikasi Akun (Pedagang & Desa), Manajemen Verifikator, Paket, & Produk.
 * Logika: Mendukung verifikasi multi-role (Admin, Desa, Verifikator UMKM) untuk pembagian komisi.
 */

if (!defined('ABSPATH')) exit;

/**
 * =============================================================================
 * 1. PENANGANAN WILAYAH (EMSIFA API)
 * =============================================================================
 */

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
 * 2. MANAJEMEN VERIFIKATOR (UNTUK ADMIN PUSAT)
 * =============================================================================
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
            'id'       => $row->id,
            'name'     => $row->nama_lengkap,
            'location' => ($row->kecamatan ? $row->kecamatan . ', ' : '') . $row->kabupaten,
            'total'    => $row->total_verifikasi_sukses,
            'income'   => 'Rp ' . number_format($row->total_pendapatan_komisi, 0, ',', '.'),
            'balance'  => 'Rp ' . number_format($row->saldo_saat_ini, 0, ',', '.'),
            'status'   => $row->status,
            'wa'       => $row->nomor_wa,
            'kode'     => $row->kode_referal
        );
    }
    wp_send_json_success($data);
}

/**
 * =============================================================================
 * 3. VERIFIKASI AKUN (PEDAGANG & DESA)
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

    // 1. Ambil Data Pedagang
    if (empty($req_role) || $req_role === 'pedagang') {
        $st = ($req_status === 'pending') ? 'menunggu_desa' : (($req_status === 'approved') ? 'disetujui' : 'ditolak');
        $pedagang = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nama_toko as name, nama_pemilik as owner, kabupaten_nama as location, created_at, 'Pedagang' as role_type, kode_referal_digunakan
             FROM $table_pedagang WHERE status_pendaftaran = %s", $st
        ));
        if ($pedagang) $results = array_merge($results, $pedagang);
    }

    // 2. Ambil Data Desa
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
            'referral' => isset($row->kode_referal_digunakan) ? $row->kode_referal_digunakan : '-',
            'logo'     => "https://ui-avatars.com/api/?name=" . urlencode($row->name) . "&background=random",
            'status'   => $req_status
        );
    }
    wp_send_json_success($data);
}

/**
 * Proses Eksekusi Verifikasi & Alokasi Komisi (Terintegrasi v3.7)
 */
add_action('wp_ajax_dw_process_umkm_verification', 'dw_ajax_process_umkm_verification');
function dw_ajax_process_umkm_verification() {
    global $wpdb;
    check_ajax_referer('dw_admin_nonce', 'nonce');

    $id   = intval($_POST['user_id']);
    $role = sanitize_text_field($_POST['role']); // 'Desa' atau 'Pedagang'
    $type = sanitize_text_field($_POST['type']); // 'approve' atau 'reject'
    $current_user_id = get_current_user_id();

    // 1. Identifikasi Role Verifikator (Siapa yang sedang klik tombol approve?)
    $verifier_role = 'admin'; // Default
    if (current_user_can('admin_desa')) {
        $verifier_role = 'desa';
    } 
    
    // Cek apakah user ini terdaftar sebagai verifikator UMKM
    $verifikator_data = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}dw_verifikator WHERE id_user = %d AND status = 'aktif'",
        $current_user_id
    ));
    if ($verifikator_data) {
        $verifier_role = 'verifikator_umkm';
    }

    $table = ($role === 'Desa') ? $wpdb->prefix . 'dw_desa' : $wpdb->prefix . 'dw_pedagang';
    
    if ($type === 'approve') {
        if ($role === 'Desa') {
            // Verifikasi Desa Wisata (Hanya oleh Admin Pusat)
            $wpdb->update($table, array('status' => 'aktif'), array('id' => $id));
            wp_send_json_success('Akun Desa berhasil diaktifkan.');
        } else {
            // Verifikasi Pedagang
            $wpdb->update($table, array(
                'status_pendaftaran' => 'disetujui', 
                'status_akun'        => 'aktif', 
                'verified_by_id'     => $current_user_id,
                'verifier_role'      => $verifier_role,
                'is_verified'        => 1,
                'verified_at'        => current_time('mysql')
            ), array('id' => $id));
            
            // Jika yang verifikasi adalah Verifikator UMKM, update statistiknya
            if ($verifier_role === 'verifikator_umkm') {
                // Komisi instan pendaftaran (jika ada kebijakan komisi pendaftaran)
                $komisi_daftar = 0; // Set sesuai kebijakan, misal 5000
                
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}dw_verifikator 
                     SET total_verifikasi_sukses = total_verifikasi_sukses + 1, 
                         total_pendapatan_komisi = total_pendapatan_komisi + %d, 
                         saldo_saat_ini = saldo_saat_ini + %d 
                     WHERE id_user = %d", 
                    $komisi_daftar, $komisi_daftar, $current_user_id
                ));
            }
            
            wp_send_json_success('Akun Pedagang berhasil diaktifkan oleh ' . strtoupper($verifier_role));
        }
    } else {
        // Logika Reject
        if ($role === 'Desa') {
            $wpdb->update($table, array('status' => 'pending'), array('id' => $id));
        } else {
            $wpdb->update($table, array('status_pendaftaran' => 'ditolak'), array('id' => $id));
        }
        wp_send_json_success('Pendaftaran telah ditolak.');
    }
}