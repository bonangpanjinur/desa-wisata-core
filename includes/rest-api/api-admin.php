<?php
/**
 * File Name:   api-admin.php
 * File Folder: includes/rest-api/
 * File Path:   includes/rest-api/api-admin.php
 *
 * Berisi semua endpoint REST API khusus SUPER ADMIN / ADMIN KABUPATEN.
 * (Manajemen Desa, Pedagang, Banner, Promosi, Ulasan, Pengaturan, Log)
 * Dipanggil oleh `rest-api.php`.
 *
 * --- PERUBAHAN (USER REQUEST: BRANDING) ---
 * - Memperbarui `dw_api_admin_update_settings` untuk menerima field branding baru.
 * - Memperbarui `dw_api_admin_update_settings` untuk menggunakan key 'kuota_gratis_default'.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Pastikan variabel $namespace sudah didefinisikan di file pemanggil (rest-api.php)
if ( ! isset( $namespace ) ) {
    return;
}
// Namespace khusus admin untuk endpoint level tinggi
$admin_namespace = $namespace . '/admin';

// =========================================================================
// ENDPOINT ADMIN: MANAJEMEN DESA
// =========================================================================
register_rest_route($admin_namespace, '/desa', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_admin_create_desa',
    'permission_callback' => 'dw_permission_check_admin_kabupaten_or_super',
    // Args divalidasi di dalam callback
]);
register_rest_route($admin_namespace, '/desa/(?P<id>\d+)', [
    'methods' => WP_REST_Server::CREATABLE, // POST untuk update
    'callback' => 'dw_api_admin_update_desa',
    'permission_callback' => 'dw_permission_check_admin_kabupaten_or_super',
]);
register_rest_route($admin_namespace, '/desa/(?P<id>\d+)', [
    'methods' => WP_REST_Server::DELETABLE,
    'callback' => 'dw_api_admin_delete_desa',
    'permission_callback' => 'dw_permission_check_administrator', // Hanya Super Admin
]);

// =========================================================================
// ENDPOINT ADMIN: MANAJEMEN PEDAGANG
// =========================================================================
register_rest_route($admin_namespace, '/pedagang', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'dw_api_admin_get_all_pedagang',
    'permission_callback' => 'dw_permission_check_admin_kabupaten_or_super',
]);
register_rest_route($admin_namespace, '/pedagang/(?P<id>\d+)', [
    'methods' => WP_REST_Server::CREATABLE, // POST untuk update
    'callback' => 'dw_api_admin_update_pedagang',
    'permission_callback' => 'dw_permission_check_admin_kabupaten_or_super',
]);
register_rest_route($admin_namespace, '/pedagang/(?P<id>\d+)', [
    'methods' => WP_REST_Server::DELETABLE,
    'callback' => 'dw_api_admin_delete_pedagang',
    'permission_callback' => 'dw_permission_check_administrator', // Hanya Super Admin
]);

// =========================================================================
// ENDPOINT ADMIN: PENGATURAN, LOG, ULASAN
// =========================================================================
register_rest_route($admin_namespace, '/settings', [
    [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'dw_api_admin_get_settings',
        'permission_callback' => 'dw_permission_check_administrator',
    ],
    [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'dw_api_admin_update_settings',
        'permission_callback' => 'dw_permission_check_administrator',
    ]
]);
register_rest_route($admin_namespace, '/logs', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'dw_api_admin_get_logs',
    'permission_callback' => 'dw_permission_check_admin_kabupaten_or_super',
]);
register_rest_route($admin_namespace, '/reviews/pending', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'dw_api_admin_get_pending_reviews',
    'permission_callback' => 'dw_permission_check_admin_kabupaten_or_super',
]);
register_rest_route($admin_namespace, '/reviews/(?P<id>\d+)/moderate', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_admin_moderate_review',
    'permission_callback' => 'dw_permission_check_admin_kabupaten_or_super',
    'args' => [
        'status' => ['required' => true, 'validate_callback' => function($param) { return in_array($param, ['disetujui', 'ditolak', 'hapus']); }]
    ],
]);

// =========================================================================
// IMPLEMENTASI CALLBACK (MANAJEMEN DESA)
// =========================================================================

function dw_api_admin_create_desa(WP_REST_Request $request) {
    global $wpdb;
    $params = $request->get_json_params();
    
    // Validasi
    if (empty($params['nama_desa']) || empty($params['id_user_desa']) || empty($params['id_kabupaten'])) {
        return new WP_Error('rest_missing_fields', __('Nama Desa, User Pengelola, dan ID Kabupaten wajib diisi.', 'desa-wisata-core'), ['status' => 400]);
    }
    
    // Cek duplikat user
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $params['id_user_desa']));
    if ($existing) {
        return new WP_Error('rest_user_assigned', __('User ini sudah mengelola desa lain.', 'desa-wisata-core'), ['status' => 400]);
    }
    
    // Ambil data dari helper `dw_desa_form_handler` di page-desa.php
    $data = [
        'id_user_desa' => absint($params['id_user_desa']),
        'nama_desa' => sanitize_text_field($params['nama_desa']),
        'deskripsi' => wp_kses_post($params['deskripsi'] ?? ''),
        'status' => sanitize_key($params['status'] ?? 'aktif'),
        'foto' => esc_url_raw($params['foto'] ?? ''),
        'persentase_komisi_penjualan' => 0.00, // Diatur global
        'id_provinsi' => sanitize_text_field($params['id_provinsi'] ?? ''),
        'id_kabupaten' => sanitize_text_field($params['id_kabupaten']),
        'id_kecamatan' => sanitize_text_field($params['id_kecamatan'] ?? ''),
        'id_kelurahan' => sanitize_text_field($params['id_kelurahan'] ?? ''),
        'provinsi' => sanitize_text_field($params['provinsi'] ?? ''),
        'kabupaten' => sanitize_text_field($params['kabupaten'] ?? ''),
        'kecamatan' => sanitize_text_field($params['kecamatan'] ?? ''),
        'kelurahan' => sanitize_text_field($params['kelurahan'] ?? ''),
    ];
    
    $result = $wpdb->insert($wpdb->prefix . 'dw_desa', $data);
    if (!$result) {
        return new WP_Error('rest_db_error', __('Gagal menyimpan desa baru.', 'desa-wisata-core'), ['status' => 500]);
    }
    $new_id = $wpdb->insert_id;
    
    return dw_api_get_single_desa(new WP_REST_Request(['id' => $new_id]));
}

function dw_api_admin_update_desa(WP_REST_Request $request) {
    global $wpdb;
    $id = $request['id'];
    $params = $request->get_json_params();
    
    $data_to_update = [];
    $allowed_fields = [
        'id_user_desa', 'nama_desa', 'deskripsi', 'status', 'foto', 
        'no_rekening_desa', 'nama_bank_desa', 'atas_nama_rekening_desa', 'qris_image_url_desa',
        'id_provinsi', 'id_kabupaten', 'id_kecamatan', 'id_kelurahan',
        'provinsi', 'kabupaten', 'kecamatan', 'kelurahan'
    ];

    foreach ($allowed_fields as $field) {
        if (isset($params[$field])) {
            // Lakukan sanitasi dasar
            if (in_array($field, ['foto', 'qris_image_url_desa'])) $data_to_update[$field] = esc_url_raw($params[$field]);
            elseif ($field === 'deskripsi') $data_to_update[$field] = wp_kses_post($params[$field]);
            elseif ($field === 'id_user_desa') $data_to_update[$field] = absint($params[$field]);
            else $data_to_update[$field] = sanitize_text_field($params[$field]);
        }
    }
    
    if (empty($data_to_update)) {
         return new WP_Error('rest_no_data', __('Tidak ada data untuk diupdate.', 'desa-wisata-core'), ['status' => 400]);
    }

    $updated = $wpdb->update($wpdb->prefix . 'dw_desa', $data_to_update, ['id' => $id]);
    if ($updated === false) {
         return new WP_Error('rest_db_error', __('Gagal mengupdate desa.', 'desa-wisata-core'), ['status' => 500]);
    }

    do_action('dw_desa_updated', $id, $data_to_update); // Trigger sinkronisasi alamat wisata
    
    return dw_api_get_single_desa(new WP_REST_Request(['id' => $id]));
}

function dw_api_admin_delete_desa(WP_REST_Request $request) {
    $id = $request['id'];
    
    // Panggil handler yang sudah ada (dari page-desa.php)
    if (function_exists('dw_handle_desa_deletion')) {
        dw_handle_desa_deletion($id); // Lepaskan relasi
    }
    
    global $wpdb;
    $deleted = $wpdb->delete($wpdb->prefix . 'dw_desa', ['id' => $id]);
    
    if (!$deleted) {
        return new WP_Error('rest_delete_failed', __('Gagal menghapus desa.', 'desa-wisata-core'), ['status' => 500]);
    }
    
    return new WP_REST_Response(['message' => 'Desa berhasil dihapus.', 'id' => $id], 200);
}


// =========================================================================
// IMPLEMENTASI CALLBACK (MANAJEMEN PEDAGANG)
// =========================================================================

function dw_api_admin_get_all_pedagang(WP_REST_Request $request) {
    global $wpdb;
    // TODO: Tambahkan paginasi jika data sudah banyak
    $pedagang_list = $wpdb->get_results(
        "SELECT p.*, d.nama_desa 
         FROM {$wpdb->prefix}dw_pedagang p
         LEFT JOIN {$wpdb->prefix}dw_desa d ON p.id_desa = d.id
         ORDER BY p.created_at DESC",
        ARRAY_A
    );
    return new WP_REST_Response($pedagang_list, 200);
}

function dw_api_admin_update_pedagang(WP_REST_Request $request) {
    // Gunakan callback yang sama dengan pedagang, tapi dengan permission admin
    // Pastikan fungsi ini sudah ada di file `api-pedagang.php`
    if (function_exists('dw_api_pedagang_update_profile')) {
        return dw_api_pedagang_update_profile($request);
    }
    return new WP_Error('rest_handler_missing', __('Fungsi update pedagang tidak ditemukan.', 'desa-wisata-core'), ['status' => 500]);
}

function dw_api_admin_delete_pedagang(WP_REST_Request $request) {
    $id = $request['id'];
    
    // Panggil handler yang sudah ada (dari page-pedagang.php)
    // Handler ini sudah SANGAT lengkap (hapus user, produk, transaksi, dll)
    if (function_exists('dw_pedagang_delete_handler')) {
        // Hati-hati: dw_pedagang_delete_handler() mungkin bergantung pada $_GET
        // Kita tiru logikanya di sini
        global $wpdb;
        $pedagang_id = absint($id);
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id_user, nama_toko FROM $table_pedagang WHERE id = %d", $pedagang_id));
        if (!$pedagang) return new WP_Error('rest_not_found', __('Pedagang tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
        
        $user_id = absint($pedagang->id_user);

        // 1. Hapus produk CPT
        $produk_posts = get_posts(['post_type' => 'dw_produk', 'author' => $user_id, 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any']);
        foreach ($produk_posts as $post_id) { wp_delete_post($post_id, true); }

        // 2. Hapus transaksi
        $table_transaksi = $wpdb->prefix . 'dw_transaksi';
        $table_transaksi_item = $wpdb->prefix . 'dw_transaksi_item';
        $transaksi_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_transaksi WHERE id_pedagang = %d", $pedagang_id));
        if (!empty($transaksi_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($transaksi_ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM $table_transaksi_item WHERE id_transaksi IN ($ids_placeholder)", $transaksi_ids));
            $wpdb->delete($table_transaksi, ['id_pedagang' => $pedagang_id], ['%d']);
        }
        
        // 3. Hapus data pedagang
        $wpdb->delete($table_pedagang, ['id' => $pedagang_id], ['%d']);

        // 4. Hapus user WP (atau ubah role)
        // Sesuai update di page-pedagang.php, kita ubah role saja
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if ($user) {
                $user->remove_role('pedagang');
                if (empty($user->roles)) {
                    $user->add_role('subscriber');
                }
            }
        }
        
        dw_log_activity('PEDAGANG_DELETED_API', "Pedagang '{$pedagang->nama_toko}' (#{$pedagang_id}) dihapus via API oleh Admin #".get_current_user_id().". Role user diubah ke subscriber.", get_current_user_id());
        return new WP_REST_Response(['message' => 'Pedagang dan semua data terkait berhasil dihapus permanen. Akun pengguna diubah menjadi subscriber.', 'id' => $id], 200);

    } else {
        return new WP_Error('rest_handler_missing', __('Fungsi handler penghapusan tidak ditemukan.', 'desa-wisata-core'), ['status' => 500]);
    }
}


// =========================================================================
// IMPLEMENTASI CALLBACK (PENGATURAN, LOG, ULASAN)
// =========================================================================

function dw_api_admin_get_settings(WP_REST_Request $request) {
    $settings = get_option('dw_settings');
    return new WP_REST_Response($settings, 200);
}

/**
 * [PERUBAHAN] Fungsi ini diperbarui untuk menyertakan
 * 'kuota_gratis_default' dan field branding.
 */
function dw_api_admin_update_settings(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $current_settings = get_option('dw_settings', []);
    
    // Sanitasi
    $new_settings = $current_settings;
    if (isset($params['biaya_promosi_produk'])) $new_settings['biaya_promosi_produk'] = absint($params['biaya_promosi_produk']);
    
    // --- PERBAIKAN: Ganti nama key kuota ---
    if (isset($params['kuota_gratis_default'])) $new_settings['kuota_gratis_default'] = absint($params['kuota_gratis_default']);
    
    // --- KOMISI DIHAPUS ---
    // if (isset($params['persentase_komisi_platform'])) $new_settings['persentase_komisi_platform'] = floatval($params['persentase_komisi_platform']);
    // if (isset($params['persentase_komisi_desa_global'])) $new_settings['persentase_komisi_desa_global'] = floatval($params['persentase_komisi_desa_global']);

    // --- BARU: Sanitasi Data Branding ---
    if (isset($params['nama_website'])) {
        $new_settings['nama_website'] = sanitize_text_field($params['nama_website']);
    }
    if (isset($params['logo_frontend'])) {
        $new_settings['logo_frontend'] = esc_url_raw($params['logo_frontend']);
    }
    if (isset($params['warna_utama']) && sanitize_hex_color($params['warna_utama'])) {
        $new_settings['warna_utama'] = sanitize_hex_color($params['warna_utama']);
    }
    // --- AKHIR PERUBAHAN ---

    update_option('dw_settings', $new_settings);
    
    return new WP_REST_Response($new_settings, 200);
}

function dw_api_admin_get_logs(WP_REST_Request $request) {
    global $wpdb;
    $logs = $wpdb->get_results(
        "SELECT l.*, u.display_name 
         FROM {$wpdb->prefix}dw_logs l
         LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
         ORDER BY l.created_at DESC LIMIT 100", // Ambil 100 log terbaru
        ARRAY_A
    );
    return new WP_REST_Response($logs, 200);
}

function dw_api_admin_get_pending_reviews(WP_REST_Request $request) {
    global $wpdb;
    $reviews = $wpdb->get_results(
        "SELECT r.*, u.display_name
         FROM {$wpdb->prefix}dw_ulasan r
         LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
         WHERE r.status_moderasi = 'pending'
         ORDER BY r.created_at DESC",
        ARRAY_A
    );
    return new WP_REST_Response($reviews, 200);
}

function dw_api_admin_moderate_review(WP_REST_Request $request) {
    $id = $request['id'];
    $status = $request['status'];
    global $wpdb;
    $table = $wpdb->prefix . 'dw_ulasan';
    $message = '';

    if ($status === 'hapus') {
        $wpdb->delete($table, ['id' => $id]);
        $message = 'Ulasan dihapus permanen.';
    } else {
        $wpdb->update($table, ['status_moderasi' => $status], ['id' => $id]);
        $message = "Ulasan ditandai sebagai '{$status}'.";
    }
    
    do_action('dw_review_status_updated'); // Hapus cache
    return new WP_REST_Response(['message' => $message], 200);
}

?>

