<?php
/**
 * AJAX Handlers
 * Path: includes/ajax-handlers.php
 * * Menangani semua request AJAX dari dashboard admin dengan validasi Nonce & Capability.
 * @package DesaWisataCore
 * @version 2.5.0 (Security Hardened)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle simpan/update pedagang via AJAX
 */
function dw_ajax_save_pedagang() {
    // 1. Security Check: Validasi Nonce
    check_ajax_referer('dw_nonce_action', 'security');

    // 2. Security Check: Validasi User Capability
    if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Anda tidak memiliki izin untuk melakukan aksi ini.']);
    }

    global $wpdb;

    // 3. Sanitasi Input
    $id             = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nama_pemilik   = isset($_POST['nama_pemilik']) ? sanitize_text_field($_POST['nama_pemilik']) : '';
    $nama_toko      = isset($_POST['nama_toko']) ? sanitize_text_field($_POST['nama_toko']) : '';
    $user_id        = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $status         = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
    
    // Validasi data wajib
    if (empty($nama_pemilik) || empty($nama_toko) || empty($user_id)) {
        wp_send_json_error(['message' => 'Data wajib (Nama Pemilik, Nama Toko, User) harus diisi.']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    $data = [
        'user_id'       => $user_id,
        'nama_pemilik'  => $nama_pemilik,
        'nama_toko'     => $nama_toko,
        'status'        => $status,
    ];

    $format = ['%d', '%s', '%s', '%s'];

    // Cek apakah Insert atau Update
    if ($id > 0) {
        // Update
        $updated = $wpdb->update($table_name, $data, ['id' => $id], $format, ['%d']);
        if ($updated !== false) {
            do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
            wp_send_json_success(['message' => 'Data pedagang berhasil diperbarui.']);
        } else {
            wp_send_json_error(['message' => 'Gagal memperbarui data pedagang.']);
        }
    } else {
        // Insert
        $inserted = $wpdb->insert($table_name, $data, $format);
        if ($inserted) {
            do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
            wp_send_json_success(['message' => 'Pedagang baru berhasil ditambahkan.']);
        } else {
            wp_send_json_error(['message' => 'Gagal menambahkan pedagang baru.']);
        }
    }
}
add_action('wp_ajax_dw_save_pedagang', 'dw_ajax_save_pedagang');

/**
 * Handle pencarian user untuk dropdown Select2
 */
function dw_ajax_search_user() {
    // 1. Security Check: Validasi Nonce
    check_ajax_referer('dw_nonce_action', 'security');
    
    // 2. Security Check: Capability
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Gunakan prepare untuk LIKE query yang aman dari SQL Injection
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, display_name, user_email 
         FROM {$wpdb->users} 
         WHERE display_name LIKE %s OR user_email LIKE %s 
         LIMIT %d OFFSET %d",
        $search_like,
        $search_like,
        $limit,
        $offset
    ));

    $results = [];
    foreach ($users as $user) {
        $results[] = [
            'id' => $user->ID,
            'text' => $user->display_name . ' (' . $user->user_email . ')'
        ];
    }

    // Cek apakah masih ada data lagi (pagination)
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->users} WHERE display_name LIKE %s OR user_email LIKE %s",
        $search_like,
        $search_like
    ));
    
    $more = ($offset + $limit) < $count;

    wp_send_json_success([
        'results' => $results,
        'pagination' => ['more' => $more]
    ]);
}
add_action('wp_ajax_dw_search_user', 'dw_ajax_search_user');

/**
 * Get detail pedagang untuk edit modal
 */
function dw_ajax_get_pedagang_detail() {
    // 1. Security Check
    check_ajax_referer('dw_nonce_action', 'security');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID tidak valid.']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    
    // Gunakan prepare untuk keamanan ID
    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);

    if ($data) {
        // Ambil data user terkait
        $user = get_userdata($data['user_id']);
        $data['user_name'] = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'User tidak ditemukan';
        
        wp_send_json_success($data);
    } else {
        wp_send_json_error(['message' => 'Data tidak ditemukan.']);
    }
}
add_action('wp_ajax_dw_get_pedagang_detail', 'dw_ajax_get_pedagang_detail');

/**
 * Verifikasi pedagang
 */
function dw_ajax_verifikasi_pedagang() {
    check_ajax_referer('dw_nonce_action', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Anda tidak memiliki akses.']);
    }

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    $updated = $wpdb->update(
        $table_name,
        ['status' => 'verified'],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        // Kirim notifikasi email atau sistem (Future Phase)
        do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
        wp_send_json_success(['message' => 'Pedagang berhasil diverifikasi.']);
    } else {
        wp_send_json_error(['message' => 'Gagal verifikasi pedagang.']);
    }
}
add_action('wp_ajax_dw_verifikasi_pedagang', 'dw_ajax_verifikasi_pedagang');

/**
 * Tolak pedagang
 */
function dw_ajax_reject_pedagang() {
    check_ajax_referer('dw_nonce_action', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Anda tidak memiliki akses.']);
    }

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    $updated = $wpdb->update(
        $table_name,
        ['status' => 'rejected'],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
        wp_send_json_success(['message' => 'Pengajuan pedagang ditolak.']);
    } else {
        wp_send_json_error(['message' => 'Gagal menolak pedagang.']);
    }
}
add_action('wp_ajax_dw_reject_pedagang', 'dw_ajax_reject_pedagang');

/**
 * Hapus pedagang
 */
function dw_ajax_delete_pedagang() {
    check_ajax_referer('dw_nonce_action', 'security');

    if (!current_user_can('delete_users')) { // Level akses lebih tinggi untuk hapus
        wp_send_json_error(['message' => 'Anda tidak memiliki akses untuk menghapus data.']);
    }

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);

    if ($deleted) {
        do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
        wp_send_json_success(['message' => 'Data pedagang dihapus.']);
    } else {
        wp_send_json_error(['message' => 'Gagal menghapus data.']);
    }
}
add_action('wp_ajax_dw_delete_pedagang', 'dw_ajax_delete_pedagang');