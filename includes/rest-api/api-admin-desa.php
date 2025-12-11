<?php
/**
 * File Name:   api-admin-desa.php
 * File Folder: includes/rest-api/
 * File Path:   includes/rest-api/api-admin-desa.php
 *
 * Berisi semua endpoint REST API khusus ADMIN DESA (Manajemen Wisata, Persetujuan Pedagang).
 * Dipanggil oleh `rest-api.php`.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Pastikan variabel $namespace sudah didefinisikan di file pemanggil (rest-api.php)
if ( ! isset( $namespace ) ) {
    return;
}

// =========================================================================
// ENDPOINT ADMIN DESA: PROFIL DESA
// =========================================================================
register_rest_route($namespace, '/admin-desa/profile/me', [
    [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'dw_api_admin_desa_get_profile',
        'permission_callback' => 'dw_permission_check_admin_desa',
    ],
    [
        'methods' => WP_REST_Server::CREATABLE, // POST untuk update
        'callback' => 'dw_api_admin_desa_update_profile',
        'permission_callback' => 'dw_permission_check_admin_desa',
        'args' => [
            'deskripsi' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'foto' => ['type' => 'string', 'format' => 'url', 'sanitize_callback' => 'esc_url_raw'],
            'no_rekening_desa' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'nama_bank_desa' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'atas_nama_rekening_desa' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'qris_image_url_desa' => ['type' => 'string', 'format' => 'url', 'sanitize_callback' => 'esc_url_raw'],
        ],
    ]
]);

// =========================================================================
// ENDPOINT ADMIN DESA: MANAJEMEN PEDAGANG
// =========================================================================
register_rest_route($namespace, '/admin-desa/pedagang/pending', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'dw_api_admin_desa_get_pending_pedagang',
    'permission_callback' => 'dw_permission_check_admin_desa',
]);
register_rest_route($namespace, '/admin-desa/pedagang/(?P<id>\d+)/approve', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_admin_desa_approve_pedagang',
    'permission_callback' => 'dw_permission_check_admin_desa',
]);
register_rest_route($namespace, '/admin-desa/pedagang/(?P<id>\d+)/reject', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_admin_desa_reject_pedagang',
    'permission_callback' => 'dw_permission_check_admin_desa',
    'args' => [
        'reason' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => 'Ditolak oleh Admin Desa.'],
    ],
]);

// =========================================================================
// ENDPOINT ADMIN DESA: MANAJEMEN WISATA
// =========================================================================
register_rest_route($namespace, '/admin-desa/wisata', [
    [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'dw_api_admin_desa_get_my_wisata',
        'permission_callback' => 'dw_permission_check_admin_desa',
    ],
    [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'dw_api_admin_desa_create_wisata',
        'permission_callback' => 'dw_permission_check_admin_desa',
    ],
]);
register_rest_route($namespace, '/admin-desa/wisata/(?P<id>\d+)', [
    [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'dw_api_admin_desa_get_single_wisata',
        'permission_callback' => 'dw_permission_check_wisata_owner_desa', // Perlu helper baru
    ],
    [
        'methods' => WP_REST_Server::CREATABLE, // POST untuk update
        'callback' => 'dw_api_admin_desa_update_wisata',
        'permission_callback' => 'dw_permission_check_wisata_owner_desa',
    ],
    [
        'methods' => WP_REST_Server::DELETABLE,
        'callback' => 'dw_api_admin_desa_delete_wisata',
        'permission_callback' => 'dw_permission_check_wisata_owner_desa',
    ],
]);


// =========================================================================
// IMPLEMENTASI CALLBACK (PROFIL DESA)
// =========================================================================

/**
 * Helper untuk mendapatkan ID Desa yang dikelola oleh Admin Desa saat ini.
 */
function dw_get_managed_desa_id($user_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id));
}

/**
 * Helper permission check baru untuk memastikan Admin Desa hanya mengedit wisatanya.
 */
function dw_permission_check_wisata_owner_desa(WP_REST_Request $request) {
    $permission = dw_permission_check_admin_desa($request);
    if (is_wp_error($permission)) return $permission;
    
    $user_id = dw_get_user_id_from_request($request);
    $managed_desa_id = dw_get_managed_desa_id($user_id);
    $post_id = $request['id'];
    
    $wisata_desa_id = get_post_meta($post_id, '_dw_id_desa', true);
    
    if ($managed_desa_id && $wisata_desa_id == $managed_desa_id) {
        return true;
    }
    
    return new WP_Error('rest_forbidden_ownership', __('Anda tidak memiliki izin untuk mengelola wisata ini.', 'desa-wisata-core'), ['status' => 403]);
}


function dw_api_admin_desa_get_profile(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $desa_id = dw_get_managed_desa_id($user_id);
    
    if (!$desa_id) {
        return new WP_Error('rest_desa_not_found', __('Profil desa tidak terhubung dengan akun Anda.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    return dw_api_get_single_desa(new WP_REST_Request(['id' => $desa_id]));
}

function dw_api_admin_desa_update_profile(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $desa_id = dw_get_managed_desa_id($user_id);
    if (!$desa_id) {
         return new WP_Error('rest_desa_not_found', __('Profil desa tidak terhubung.', 'desa-wisata-core'), ['status' => 404]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dw_desa';
    
    $data_to_update = [];
    $allowed_fields = ['deskripsi', 'foto', 'no_rekening_desa', 'nama_bank_desa', 'atas_nama_rekening_desa', 'qris_image_url_desa'];
    
    foreach ($allowed_fields as $field) {
        if ($request->has_param($field)) {
            $data_to_update[$field] = $request[$field];
        }
    }
    
    if (empty($data_to_update)) {
        return new WP_Error('rest_no_data', __('Tidak ada data untuk diupdate.', 'desa-wisata-core'), ['status' => 400]);
    }

    $updated = $wpdb->update($table, $data_to_update, ['id' => $desa_id]);
    
    if ($updated === false) {
        return new WP_Error('rest_db_error', __('Gagal mengupdate profil desa.', 'desa-wisata-core'), ['status' => 500]);
    }
    
    return dw_api_get_single_desa(new WP_REST_Request(['id' => $desa_id]));
}


// =========================================================================
// IMPLEMENTASI CALLBACK (MANAJEMEN PEDAGANG)
// =========================================================================

function dw_api_admin_desa_get_pending_pedagang(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $desa_id = dw_get_managed_desa_id($user_id);
    if (!$desa_id) {
         return new WP_Error('rest_desa_not_found', __('Profil desa tidak terhubung.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    global $wpdb;
    $pedagang_list = $wpdb->get_results($wpdb->prepare(
        "SELECT id, nama_toko, nama_pemilik, nomor_wa, created_at 
         FROM {$wpdb->prefix}dw_pedagang
         WHERE id_desa = %d AND status_pendaftaran = 'menunggu_desa'
         ORDER BY created_at ASC",
        $desa_id
    ), ARRAY_A);
    
    return new WP_REST_Response($pedagang_list, 200);
}

function dw_api_admin_desa_approve_pedagang(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $desa_id = dw_get_managed_desa_id($user_id);
    $pedagang_id = $request['id'];
    
    global $wpdb;
    $pedagang = $wpdb->get_row($wpdb->prepare(
        "SELECT id, id_user, nama_toko, id_desa, status_pendaftaran 
         FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $pedagang_id
    ));
    
    if (!$pedagang || $pedagang->id_desa != $desa_id) {
        return new WP_Error('rest_forbidden_ownership', __('Pedagang ini bukan bagian dari desa Anda.', 'desa-wisata-core'), ['status' => 403]);
    }
    if ($pedagang->status_pendaftaran !== 'menunggu_desa') {
         return new WP_Error('rest_invalid_status', __('Pedagang tidak dalam status menunggu verifikasi desa.', 'desa-wisata-core'), ['status' => 400]);
    }
    
    // --- Logika dari page-desa-verifikasi-pedagang.php ---
    // 1. Update status pendaftaran
    $wpdb->update(
        $wpdb->prefix . 'dw_pedagang',
        ['status_pendaftaran' => 'disetujui', 'status_akun' => 'aktif'],
        ['id' => $pedagang_id]
    );
    
    // 2. Update role user WP
    $user = new WP_User($pedagang->id_user);
    if ($user->exists()) {
        $user->remove_role('subscriber');
        $user->add_role('pedagang');
    }
    // 3. Notifikasi
    dw_send_pedagang_notification($pedagang->id_user, "Pendaftaran Disetujui & Akun Aktif: {$pedagang->nama_toko}", "Selamat! Kelayakan pendaftaran toko Anda, '{$pedagang->nama_toko}', telah disetujui oleh Admin Desa dan akun Anda sekarang *Aktif*. Anda bisa mulai mengunggah produk.");
    // 4. Log
    dw_log_activity('PEDAGANG_APPROVED', "Admin Desa #{$user_id} menyetujui pedagang #{$pedagang_id} ({$pedagang->nama_toko}).", $user_id);

    return new WP_REST_Response(['message' => 'Pedagang berhasil disetujui dan diaktifkan.'], 200);
}

function dw_api_admin_desa_reject_pedagang(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $desa_id = dw_get_managed_desa_id($user_id);
    $pedagang_id = $request['id'];
    $reason = $request['reason'];
    
    global $wpdb;
    $pedagang = $wpdb->get_row($wpdb->prepare(
        "SELECT id, id_user, nama_toko, id_desa, status_pendaftaran 
         FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $pedagang_id
    ));
    
    if (!$pedagang || $pedagang->id_desa != $desa_id) {
        return new WP_Error('rest_forbidden_ownership', __('Pedagang ini bukan bagian dari desa Anda.', 'desa-wisata-core'), ['status' => 403]);
    }
     if ($pedagang->status_pendaftaran !== 'menunggu_desa') {
         return new WP_Error('rest_invalid_status', __('Pedagang tidak dalam status menunggu verifikasi desa.', 'desa-wisata-core'), ['status' => 400]);
    }

    // Update status
    $wpdb->update(
        $wpdb->prefix . 'dw_pedagang',
        ['status_pendaftaran' => 'ditolak'],
        ['id' => $pedagang_id]
    );
    
    // Notifikasi
    dw_send_pedagang_notification($pedagang->id_user, "Pendaftaran Ditolak: {$pedagang->nama_toko}", "Mohon maaf, pendaftaran toko Anda, '{$pedagang->nama_toko}', telah ditolak oleh Admin Desa. Alasan: " . $reason);
    // Log
    dw_log_activity('PEDAGANG_REJECTED', "Admin Desa #{$user_id} menolak pedagang #{$pedagang_id} ({$pedagang->nama_toko}). Alasan: $reason", $user_id);

    return new WP_REST_Response(['message' => 'Pedagang berhasil ditolak.'], 200);
}

// =========================================================================
// IMPLEMENTASI CALLBACK (MANAJEMEN WISATA)
// =========================================================================

function dw_api_admin_desa_get_my_wisata(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $desa_id = dw_get_managed_desa_id($user_id);
    if (!$desa_id) {
         return new WP_Error('rest_desa_not_found', __('Profil desa tidak terhubung.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    $args = [
        'post_type' => 'dw_wisata',
        'post_status' => ['publish', 'draft', 'pending'],
        'posts_per_page' => -1,
        'meta_query' => [[
            'key' => '_dw_id_desa',
            'value' => $desa_id
        ]]
    ];
    $query = new WP_Query($args);
    $wisata_data = [];
    foreach ($query->posts as $post) {
        $wisata_data[] = dw_internal_format_wisata_data($post);
    }
    return new WP_REST_Response($wisata_data, 200);
}

function dw_api_admin_desa_get_single_wisata(WP_REST_Request $request) {
    // Permission check 'dw_permission_check_wisata_owner_desa' sudah memastikan ini wisata milik desa
    $data = dw_internal_format_wisata_data($request['id']);
    if (!$data) {
        return new WP_Error('rest_not_found', __('Wisata tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }
    return new WP_REST_Response($data, 200);
}

function dw_api_admin_desa_create_wisata(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $desa_id = dw_get_managed_desa_id($user_id);
    if (!$desa_id) {
         return new WP_Error('rest_desa_not_found', __('Profil desa tidak terhubung.', 'desa-wisata-core'), ['status' => 404]);
    }

    $params = $request->get_json_params();
    if (empty($params['nama_wisata'])) {
         return new WP_Error('rest_missing_title', __('Nama wisata wajib diisi.', 'desa-wisata-core'), ['status' => 400]);
    }

    $post_data = [
        'post_title' => sanitize_text_field($params['nama_wisata']),
        'post_content' => wp_kses_post($params['deskripsi'] ?? ''),
        'post_status' => 'publish', // Atau 'draft'
        'post_type' => 'dw_wisata',
        'post_author' => $user_id, // Author adalah Admin Desa
    ];
    $post_id = wp_insert_post($post_data, true);
    
    if (is_wp_error($post_id)) return $post_id;
    
    // PENTING: Set ID Desa
    $params['_dw_id_desa'] = $desa_id; 
    
    $result = dw_api_save_wisata_data($post_id, $params, $user_id);
    if (is_wp_error($result)) {
        wp_delete_post($post_id, true);
        return $result;
    }

    $data = dw_internal_format_wisata_data($post_id);
    return new WP_REST_Response($data, 201);
}

function dw_api_admin_desa_update_wisata(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $post_id = $request['id'];
    // Permission check sudah memastikan kepemilikan
    
    $params = $request->get_json_params();
    
    $post_data = ['ID' => $post_id];
    if (isset($params['nama_wisata'])) $post_data['post_title'] = sanitize_text_field($params['nama_wisata']);
    if (isset($params['deskripsi'])) $post_data['post_content'] = wp_kses_post($params['deskripsi']);
    if (isset($params['status'])) $post_data['post_status'] = sanitize_key($params['status']);
    
    if (count($post_data) > 1) {
        wp_update_post($post_data, true);
    }
    
    // Panggil helper untuk update meta & taksonomi
    $result = dw_api_save_wisata_data($post_id, $params, $user_id);
    if (is_wp_error($result)) return $result;

    $data = dw_internal_format_wisata_data($post_id);
    return new WP_REST_Response($data, 200);
}

function dw_api_admin_desa_delete_wisata(WP_REST_Request $request) {
    $post_id = $request['id'];
    $deleted = wp_delete_post($post_id, false); // false = trash
    
    if (!$deleted) {
        return new WP_Error('rest_delete_failed', __('Gagal menghapus wisata.', 'desa-wisata-core'), ['status' => 500]);
    }
    
    return new WP_REST_Response(['message' => 'Wisata berhasil dipindahkan ke trash.', 'id' => $post_id], 200);
}
?>
