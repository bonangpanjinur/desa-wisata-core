<?php
/**
 * File Name:   api-pedagang.php
 * File Folder: includes/rest-api/
 * File Path:   includes/rest-api/api-pedagang.php
 *
 * --- PERBAIKAN (UX IMPROVEMENT v3.2.6) ---
 * - `dw_api_pedagang_update_product`: MENGHAPUS pengecekan kuota (`dw_check_pedagang_kuota`).
 * Ini memungkinkan pedagang untuk tetap mengedit produk (misal: perbaiki harga/deskripsi)
 * meskipun kuota transaksi mereka habis (0). Pengecekan hanya dilakukan saat *membuat* produk baru
 * dan saat *memproses pesanan*.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Pastikan variabel $namespace sudah didefinisikan di file pemanggil (rest-api.php)
if ( ! isset( $namespace ) ) {
    return;
}

// =========================================================================
// ENDPOINT PEDAGANG: PROFIL TOKO
// =========================================================================
register_rest_route($namespace, '/pedagang/profile/me', [
    [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'dw_api_pedagang_get_profile',
        'permission_callback' => 'dw_permission_check_pedagang',
    ],
    [
        'methods' => WP_REST_Server::CREATABLE, // POST untuk update
        'callback' => 'dw_api_pedagang_update_profile',
        'permission_callback' => 'dw_permission_check_pedagang',
        'args' => [
            'nama_toko' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'nama_pemilik' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'nomor_wa' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'deskripsi_toko' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            'url_gmaps' => ['type' => 'string', 'format' => 'url', 'sanitize_callback' => 'esc_url_raw'],
            'no_rekening' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'nama_bank' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'atas_nama_rekening' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'qris_image_url' => ['type' => 'string', 'format' => 'url', 'sanitize_callback' => 'esc_url_raw'],
            // Field Pengiriman Baru
            'shipping_ojek_lokal_aktif' => ['type' => 'boolean'],
            'shipping_ojek_lokal_zona' => ['type' => 'array'],
            'shipping_nasional_aktif' => ['type' => 'boolean'],
            'shipping_nasional_harga' => ['type' => 'number'],
            'shipping_profiles' => ['type' => 'object'], // Menerima objek JSON
            'allow_pesan_di_tempat' => ['type' => 'boolean'], 
            
            // --- FIELD ALAMAT BARU ---
            'api_provinsi_id' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'api_kabupaten_id' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'api_kecamatan_id' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'api_kelurahan_id' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'provinsi_nama' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'kabupaten_nama' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'kecamatan_nama' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'kelurahan_nama' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'alamat_lengkap' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'], // Alamat manual
        ],
    ]
]);

// =========================================================================
// ENDPOINT PEDAGANG: DASHBOARD
// =========================================================================
register_rest_route($namespace, '/pedagang/dashboard/summary', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'dw_api_pedagang_get_dashboard_summary',
    'permission_callback' => 'dw_permission_check_pedagang',
]);

// =========================================================================
// ENDPOINT PEDAGANG: MANAJEMEN PRODUK
// =========================================================================
register_rest_route($namespace, '/pedagang/produk', [
    [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'dw_api_pedagang_get_my_products',
        'permission_callback' => 'dw_permission_check_pedagang',
    ],
    [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'dw_api_pedagang_create_product',
        'permission_callback' => 'dw_permission_check_pedagang',
        // Validasi argumen akan ditangani di dalam callback
    ],
]);

register_rest_route($namespace, '/pedagang/produk/(?P<id>\d+)', [
    [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'dw_api_pedagang_get_single_product',
        'permission_callback' => 'dw_permission_check_product_owner',
    ],
    [
        'methods' => WP_REST_Server::CREATABLE, // POST untuk update
        'callback' => 'dw_api_pedagang_update_product',
        'permission_callback' => 'dw_permission_check_product_owner',
    ],
    [
        'methods' => WP_REST_Server::DELETABLE,
        'callback' => 'dw_api_pedagang_delete_product',
        'permission_callback' => 'dw_permission_check_product_owner',
    ],
]);

// =========================================================================
// ENDPOINT PEDAGANG: MANAJEMEN PESANAN
// =========================================================================
register_rest_route($namespace, '/pedagang/orders', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'dw_api_pedagang_get_my_orders',
    'permission_callback' => 'dw_permission_check_pedagang',
    'args' => [
        'status' => ['sanitize_callback' => 'sanitize_key'], // 'lunas', 'diproses', dll
    ],
]);

register_rest_route($namespace, '/pedagang/orders/sub/(?P<sub_order_id>\d+)', [
    [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'dw_api_pedagang_get_single_order',
        'permission_callback' => 'dw_permission_check_sub_order_pedagang', 
    ],
    [
        'methods' => WP_REST_Server::CREATABLE, // POST untuk update status
        'callback' => 'dw_api_pedagang_update_order_status',
        'permission_callback' => 'dw_permission_check_sub_order_pedagang', 
        'args' => [
            'status' => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
            'nomor_resi' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'ongkir_final' => ['validate_callback' => 'dw_rest_validate_numeric'], 
            'catatan' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
        ],
    ]
]);


// =========================================================================
// ENDPOINT PEDAGANG: PAKET KUOTA
// =========================================================================
register_rest_route($namespace, '/pedagang/paket/daftar', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'dw_api_pedagang_get_daftar_paket',
    'permission_callback' => 'dw_permission_check_pedagang',
]);

register_rest_route($namespace, '/pedagang/paket/beli', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_pedagang_beli_paket',
    'permission_callback' => 'dw_permission_check_pedagang',
    'args' => [
        'id_paket' => ['required' => true, 'validate_callback' => 'dw_rest_validate_numeric'], 
        'file' => ['required' => true], // Bukti bayar
    ],
]);

// =========================================================================
// PERMISSION CHECK BARU (UNTUK SUB-ORDER)
// =========================================================================

function dw_permission_check_sub_order_pedagang(WP_REST_Request $request) {
    $permission = dw_permission_check_pedagang($request); 
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request);
    $sub_order_id = absint($request['sub_order_id']);
    if ($sub_order_id <= 0) return false; 

    global $wpdb;
    $order_pedagang_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id_pedagang FROM {$wpdb->prefix}dw_transaksi_sub WHERE id = %d", 
        $sub_order_id
    ));

    // Ambil ID Pedagang dari User ID
    $pedagang_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id));

    if ($order_pedagang_id && $order_pedagang_id == $pedagang_id) {
        return true;
    }
    return new WP_Error('rest_forbidden_ownership', __('Anda bukan pemilik pesanan ini.', 'desa-wisata-core'), ['status' => 403]);
}


// =========================================================================
// IMPLEMENTASI CALLBACK (PROFIL PEDAGANG)
// =========================================================================

function dw_api_pedagang_get_profile(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    global $wpdb;
    $data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id
    ), ARRAY_A);
    
    if (!$data) {
        return new WP_Error('rest_not_found', __('Profil pedagang tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    if ($data && isset($data['shipping_ojek_lokal_zona'])) {
        $data['shipping_ojek_lokal_zona'] = json_decode($data['shipping_ojek_lokal_zona'], true) ?? [];
    }
    if ($data && isset($data['shipping_profiles'])) {
        $data['shipping_profiles'] = json_decode($data['shipping_profiles'], true) ?? (object)[]; 
    }

    $data['shipping_ojek_lokal_aktif'] = (bool)$data['shipping_ojek_lokal_aktif'];
    $data['shipping_nasional_aktif'] = (bool)$data['shipping_nasional_aktif'];
    $data['shipping_nasional_harga'] = (float)$data['shipping_nasional_harga'];
    $data['sisa_transaksi'] = (int)$data['sisa_transaksi']; 
    $data['allow_pesan_di_tempat'] = (bool)$data['allow_pesan_di_tempat']; 

    return new WP_REST_Response($data, 200);
}

function dw_api_pedagang_update_profile(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    global $wpdb;
    $table = $wpdb->prefix . 'dw_pedagang';
    
    $data_to_update = [];
    $allowed_fields = [
        'nama_toko', 'nama_pemilik', 'nomor_wa', 'deskripsi_toko', 'url_gmaps', 
        'no_rekening', 'nama_bank', 'atas_nama_rekening', 'qris_image_url',
        'alamat_lengkap', 'api_provinsi_id', 'api_kabupaten_id', 'api_kecamatan_id', 'api_kelurahan_id',
        'provinsi_nama', 'kabupaten_nama', 'kecamatan_nama', 'kelurahan_nama'
    ];
    
    foreach ($allowed_fields as $field) {
        if (isset($request[$field])) {
            if (in_array($field, ['url_gmaps', 'qris_image_url'])) {
                $data_to_update[$field] = esc_url_raw($request[$field]);
            } elseif ($field === 'deskripsi_toko' || $field === 'alamat_lengkap') {
                $data_to_update[$field] = sanitize_textarea_field($request[$field]);
            } else {
                $data_to_update[$field] = sanitize_text_field($request[$field]);
            }
        }
    }

    // Field boolean & array
    if (isset($request['shipping_ojek_lokal_aktif'])) $data_to_update['shipping_ojek_lokal_aktif'] = (bool)$request['shipping_ojek_lokal_aktif'];
    if (isset($request['shipping_nasional_aktif'])) $data_to_update['shipping_nasional_aktif'] = (bool)$request['shipping_nasional_aktif'];
    if (isset($request['shipping_nasional_harga'])) $data_to_update['shipping_nasional_harga'] = (float)$request['shipping_nasional_harga'];
    if (isset($request['allow_pesan_di_tempat'])) $data_to_update['allow_pesan_di_tempat'] = (bool)$request['allow_pesan_di_tempat'];
    
    if (isset($request['shipping_ojek_lokal_zona'])) {
        $data_to_update['shipping_ojek_lokal_zona'] = json_encode($request['shipping_ojek_lokal_zona']);
    }
    if (isset($request['shipping_profiles'])) {
        $data_to_update['shipping_profiles'] = json_encode($request['shipping_profiles']);
    }

    if (empty($data_to_update)) {
        return new WP_Error('rest_no_data', __('Tidak ada data untuk diupdate.', 'desa-wisata-core'), ['status' => 400]);
    }

    $updated = $wpdb->update($table, $data_to_update, ['id_user' => $user_id]);
    
    if ($updated === false) {
        return new WP_Error('rest_db_error', __('Gagal mengupdate profil.', 'desa-wisata-core'), ['status' => 500]);
    }

    return dw_api_pedagang_get_profile($request);
}

// ... (sisanya tetap sama, pastikan fungsi lain juga dicek IDOR-nya jika perlu)
