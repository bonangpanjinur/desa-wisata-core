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

    if ($order_pedagang_id && $order_pedagang_id == $user_id) {
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
        if ($request->has_param($field)) {
            $data_to_update[$field] = $request[$field];
        }
    }

    if ($request->has_param('shipping_ojek_lokal_zona')) {
        $zona_data = $request['shipping_ojek_lokal_zona'];
        if (is_array($zona_data)) {
            $sanitized_zona = [];
            foreach ($zona_data as $zona) {
                if (!empty($zona['id']) && isset($zona['harga'])) {
                    $sanitized_zona[] = [
                        'id'   => sanitize_text_field($zona['id']), 
                        'nama' => sanitize_text_field($zona['nama']), 
                        'harga'=> floatval($zona['harga'])
                    ];
                }
            }
            $data_to_update['shipping_ojek_lokal_zona'] = json_encode($sanitized_zona);
        } else {
            $data_to_update['shipping_ojek_lokal_zona'] = '[]'; 
        }
    }
    
    if ($request->has_param('shipping_profiles')) {
        $profiles_data = $request['shipping_profiles'];
        $sanitized_profiles = [];
        if (is_array($profiles_data) || is_object($profiles_data)) {
            foreach ($profiles_data as $key => $profile) {
                if (!empty($profile['nama']) && isset($profile['harga'])) {
                    $sanitized_key = sanitize_key($key); 
                    $sanitized_profiles[$sanitized_key] = [
                        'nama'  => sanitize_text_field($profile['nama']),
                        'harga' => floatval($profile['harga']),
                    ];
                }
            }
        }
        $data_to_update['shipping_profiles'] = json_encode($sanitized_profiles);
    }

    if ($request->has_param('shipping_ojek_lokal_aktif')) {
        $data_to_update['shipping_ojek_lokal_aktif'] = $request['shipping_ojek_lokal_aktif'] ? 1 : 0;
    }
    if ($request->has_param('shipping_nasional_aktif')) {
        $data_to_update['shipping_nasional_aktif'] = $request['shipping_nasional_aktif'] ? 1 : 0;
    }
    if ($request->has_param('shipping_nasional_harga')) {
        $data_to_update['shipping_nasional_harga'] = floatval($request['shipping_nasional_harga']);
    }
 
    if ($request->has_param('allow_pesan_di_tempat')) {
        $data_to_update['allow_pesan_di_tempat'] = $request['allow_pesan_di_tempat'] ? 1 : 0;
    }
   
    if (empty($data_to_update)) {
        return new WP_Error('rest_no_data', __('Tidak ada data untuk diupdate.', 'desa-wisata-core'), ['status' => 400]);
    }

    $updated = $wpdb->update($table, $data_to_update, ['id_user' => $user_id]);
    
    if ($updated === false) {
        return new WP_Error('rest_db_error', __('Gagal mengupdate profil pedagang.', 'desa-wisata-core'), ['status' => 500]);
    }
    
    // --- LOGIKA AUTO-MATCH DESA ---
    $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id, id_desa FROM $table WHERE id_user = %d", $user_id));
    
    if ($pedagang && empty($pedagang->id_desa) && isset($data_to_update['api_kelurahan_id'])) {
        $desa_table = $wpdb->prefix . 'dw_desa';
        $matched_desa_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $desa_table 
             WHERE api_kelurahan_id = %s 
             AND api_kecamatan_id = %s 
             AND api_kabupaten_id = %s",
            $data_to_update['api_kelurahan_id'],
            $data_to_update['api_kecamatan_id'],
            $data_to_update['api_kabupaten_id']
        ));
        if ($matched_desa_id) {
            $wpdb->update(
                $table,
                ['id_desa' => $matched_desa_id],
                ['id' => $pedagang->id]
            );
        }
    }
    
    return dw_api_pedagang_get_profile($request); 
}


// =========================================================================
// IMPLEMENTASI CALLBACK (DASHBOARD)
// =========================================================================

function dw_api_pedagang_get_dashboard_summary(WP_REST_Request $request) {
    global $wpdb;
    $user_id = dw_get_user_id_from_request($request);
    
    if (!$user_id) {
        return new WP_Error('rest_not_pedagang', __('Profil pedagang tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $table_variasi = $wpdb->prefix . 'dw_produk_variasi';
    $table_chat = $wpdb->prefix . 'dw_chat_message';
    $table_posts = $wpdb->posts;
    $table_postmeta = $wpdb->postmeta;

    $penjualan_bulan_ini = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(total_pesanan_toko) 
         FROM $table_sub 
         WHERE id_pedagang = %d 
         AND status_pesanan = 'selesai' 
         AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
        $user_id 
    ));

    $pesanan_baru = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) 
         FROM $table_sub 
         WHERE id_pedagang = %d 
         AND status_pesanan IN ('lunas', 'menunggu_konfirmasi')",
        $user_id 
    ));
    
    $produk_habis_variasi = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT v.id_produk) 
         FROM $table_variasi v
         JOIN $table_posts p ON v.id_produk = p.ID
         WHERE p.post_author = %d 
         AND p.post_status = 'publish' 
         AND v.stok_variasi = 0",
         $user_id
    ));
    
     $produk_habis_meta = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(p.ID)
         FROM $table_posts p
         JOIN $table_postmeta pm ON p.ID = pm.post_id
         WHERE p.post_author = %d
         AND p.post_status = 'publish'
         AND pm.meta_key = '_dw_stok'
         AND pm.meta_value = '0'
         AND NOT EXISTS (SELECT 1 FROM $table_variasi v WHERE v.id_produk = p.ID)", 
         $user_id
    ));
    
    $produk_habis = $produk_habis_variasi + $produk_habis_meta;

    $chat_baru = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT produk_id) 
         FROM $table_chat 
         WHERE receiver_id = %d AND is_read = 0",
        $user_id
    ));

    $response_data = [
        'penjualan_bulan_ini' => $penjualan_bulan_ini,
        'pesanan_baru'        => $pesanan_baru,
        'produk_habis'        => $produk_habis,
        'chat_baru'           => $chat_baru,
    ];
    
    return new WP_REST_Response($response_data, 200);
}


// =========================================================================
// IMPLEMENTASI CALLBACK (MANAJEMEN PRODUK)
// =========================================================================

function dw_api_pedagang_get_my_products(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $args = [
        'post_type' => 'dw_produk',
        'post_status' => ['publish', 'draft', 'pending'],
        'author' => $user_id,
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);
    $produk_data = [];
    foreach ($query->posts as $post) {
        $produk_data[] = dw_internal_format_produk_data($post); 
    }
    return new WP_REST_Response($produk_data, 200);
}

function dw_api_pedagang_get_single_product(WP_REST_Request $request) {
    $data = dw_internal_format_produk_data($request['id']);
    if (!$data) {
        return new WP_Error('rest_not_found', __('Produk tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }
    return new WP_REST_Response($data, 200);
}

function dw_api_pedagang_create_product(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    
    // --- LOGIKA KUNCI: HANYA SAAT CREATE ---
    $kuota_check = dw_check_pedagang_kuota($user_id);
    if (is_wp_error($kuota_check)) {
        return $kuota_check; // Kembalikan error 403 jika kuota habis
    }
    
    $params = $request->get_json_params();
    if (empty($params['nama_produk'])) {
         return new WP_Error('rest_missing_title', __('Nama produk wajib diisi.', 'desa-wisata-core'), ['status' => 400]);
    }

    $post_data = [
        'post_title' => sanitize_text_field($params['nama_produk']),
        'post_content' => wp_kses_post($params['deskripsi'] ?? ''),
        'post_status' => 'publish', 
        'post_type' => 'dw_produk',
        'post_author' => $user_id,
    ];
    $post_id = wp_insert_post($post_data, true);
    
    if (is_wp_error($post_id)) {
        return $post_id;
    }
    
    $result = dw_api_save_produk_data($post_id, $params, $user_id);
    if (is_wp_error($result)) {
        wp_delete_post($post_id, true); 
        return $result;
    }

    $data = dw_internal_format_produk_data($post_id);
    return new WP_REST_Response($data, 201);
}

function dw_api_pedagang_update_product(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $post_id = $request['id'];
    
    // --- PERBAIKAN UX (v3.2.6): Pengecekan kuota DIHAPUS ---
    // Pedagang tetap bisa mengedit produk (misal: ganti harga, stok, deskripsi)
    // meskipun kuota transaksi mereka 0.
    // $kuota_check = dw_check_pedagang_kuota($user_id); 
    // if (is_wp_error($kuota_check)) { return $kuota_check; }
    // --- AKHIR PERBAIKAN ---

    $params = $request->get_json_params();
    
    $post_data = ['ID' => $post_id];
    if (isset($params['nama_produk'])) {
        $post_data['post_title'] = sanitize_text_field($params['nama_produk']);
    }
    if (isset($params['deskripsi'])) {
        $post_data['post_content'] = wp_kses_post($params['deskripsi']);
    }
    if (isset($params['status'])) { 
        $post_data['post_status'] = sanitize_key($params['status']);
    }
    
    if (count($post_data) > 1) {
        wp_update_post($post_data, true);
    }
    
    $result = dw_api_save_produk_data($post_id, $params, $user_id);
    if (is_wp_error($result)) {
        return $result;
    }

    $data = dw_internal_format_produk_data($post_id);
    return new WP_REST_Response($data, 200);
}

function dw_api_pedagang_delete_product(WP_REST_Request $request) {
    $post_id = $request['id'];
    $deleted = wp_delete_post($post_id, false); 
    
    if (!$deleted) {
        return new WP_Error('rest_delete_failed', __('Gagal menghapus produk.', 'desa-wisata-core'), ['status' => 500]);
    }
    
    return new WP_REST_Response(['message' => 'Produk berhasil dipindahkan ke trash.', 'id' => $post_id], 200);
}


// =========================================================================
// IMPLEMENTASI CALLBACK (MANAJEMEN PESANAN)
// =========================================================================

function dw_api_pedagang_get_my_orders(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    global $wpdb;
    
    if (!$user_id) {
         return new WP_Error('rest_not_pedagang', __('Profil pedagang tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    $where_status = '';
    $params = [$user_id];
    if ($request->has_param('status')) {
        $where_status = " AND sub.status_pesanan = %s";
        $params[] = $request['status'];
    }

    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            sub.id as sub_order_id, 
            sub.status_pesanan, 
            sub.total_pesanan_toko,
            main.id as order_id, 
            main.kode_unik, 
            main.tanggal_transaksi, 
            main.status_transaksi,
            u.display_name as nama_pembeli 
         FROM {$wpdb->prefix}dw_transaksi_sub sub
         JOIN {$wpdb->prefix}dw_transaksi main ON sub.id_transaksi = main.id
         JOIN {$wpdb->users} u ON main.id_pembeli = u.ID
         WHERE sub.id_pedagang = %d $where_status
         ORDER BY main.tanggal_transaksi DESC",
        $params
    ), ARRAY_A);
    
    $formatted_orders = [];
    foreach($orders as $order) {
        $order['status_label'] = dw_get_order_status_label($order['status_pesanan']);
        $formatted_orders[] = $order;
    }

    return new WP_REST_Response($formatted_orders, 200);
}

function dw_api_pedagang_get_single_order(WP_REST_Request $request) {
    $sub_order_id = $request['sub_order_id'];
    
    global $wpdb;
    $sub_order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dw_transaksi_sub WHERE id = %d", $sub_order_id
    ), ARRAY_A);
    
    if (!$sub_order) {
         return new WP_Error('rest_not_found', __('Pesanan tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    $main_order_id = $sub_order['id_transaksi'];
    
    $order_data = dw_get_order_detail($main_order_id);

    if (!$order_data) {
        return new WP_Error('rest_not_found', __('Pesanan utama tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    $found_sub_order = null;
    foreach ($order_data['sub_pesanan'] as $sub) {
        if ($sub['id'] == $sub_order_id) {
            $found_sub_order = $sub;
            break;
        }
    }
    
    if (!$found_sub_order) {
         return new WP_Error('rest_not_found', __('Sub-pesanan tidak ditemukan dalam pesanan utama.', 'desa-wisata-core'), ['status' => 404]);
    }

    $response = [
        'order_utama' => [
            'id' => $order_data['id'],
            'kode_unik' => $order_data['kode_unik'],
            'tanggal_transaksi' => $order_data['tanggal_transaksi'],
            'status_transaksi' => $order_data['status_transaksi'],
            'bukti_pembayaran' => $order_data['bukti_pembayaran'],
            'catatan_pembeli' => $order_data['catatan_pembeli'],
            'alamat_pengiriman' => $order_data['alamat_pengiriman'],
        ],
        'sub_pesanan' => $found_sub_order,
    ];

    return new WP_REST_Response($response, 200);
}

function dw_api_pedagang_update_order_status(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request); 
    $sub_order_id = $request['sub_order_id'];
    $new_status = $request['status'];
    $nomor_resi = $request['nomor_resi'] ?? null;
    $ongkir_final = $request['ongkir_final'] ?? null;
    $catatan = $request['catatan'] ?? '';

    if ($new_status === 'dikirim_ekspedisi') {
        if (empty($nomor_resi)) {
            return new WP_Error('rest_missing_resi', __('Nomor Resi wajib diisi.', 'desa-wisata-core'), ['status' => 400]);
        }
    }

    $updated_result = dw_update_sub_order_status($sub_order_id, $new_status, $catatan, $nomor_resi, $ongkir_final, $user_id);
    
    if (is_wp_error($updated_result)) {
        return $updated_result;
    }
    
    if (!$updated_result) {
        return new WP_Error('rest_update_failed', __('Gagal mengupdate status pesanan.', 'desa-wisata-core'), ['status' => 500]);
    }
    
    $new_request = new WP_REST_Request('GET', $namespace . '/pedagang/orders/sub/' . $sub_order_id);
    $new_request->set_url_params(['sub_order_id' => $sub_order_id]);
    return dw_api_pedagang_get_single_order($new_request);
}


// =========================================================================
// IMPLEMENTASI CALLBACK (PAKET KUOTA BARU)
// =========================================================================

function dw_api_pedagang_get_daftar_paket(WP_REST_Request $request) {
    global $wpdb;
    $paket = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, nama_paket, deskripsi, harga, jumlah_transaksi 
             FROM {$wpdb->prefix}dw_paket_transaksi 
             WHERE status = %s 
             ORDER BY harga ASC",
            'aktif'
        ), ARRAY_A
    );
    return new WP_REST_Response($paket, 200);
}

function dw_api_pedagang_beli_paket(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $id_paket = absint($request['id_paket']);
    $files = $request->get_file_params();

    global $wpdb;
    
    $paket = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dw_paket_transaksi WHERE id = %d AND status = 'aktif'", $id_paket
    ));
    if (!$paket) {
        return new WP_Error('rest_paket_tidak_valid', 'Paket yang dipilih tidak valid atau tidak aktif.', ['status' => 404]);
    }
    
    $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id));
    if (!$pedagang) {
         return new WP_Error('rest_not_pedagang', 'Profil pedagang tidak ditemukan.', ['status' => 404]);
    }

    if (empty($files) || !isset($files['file'])) {
        return new WP_Error('rest_missing_file', 'File bukti pembayaran tidak ditemukan.', ['status' => 400]);
    }
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $attachment_id = media_handle_upload('file', 0); 
    if (is_wp_error($attachment_id)) {
        return new WP_Error('rest_upload_failed', 'Gagal mengunggah file: ' . $attachment_id->get_error_message(), ['status' => 500]);
    }
    $file_url = wp_get_attachment_url($attachment_id);

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'dw_pembelian_paket',
        [
            'id_pedagang' => $pedagang->id,
            'id_paket' => $paket->id,
            'nama_paket_snapshot' => $paket->nama_paket,
            'harga_paket' => $paket->harga,
            'jumlah_transaksi' => $paket->jumlah_transaksi,
            'persentase_komisi_desa' => $paket->persentase_komisi_desa,
            'url_bukti_bayar' => $file_url,
            'status' => 'pending',
            'created_at' => current_time('mysql', 1),
        ],
        ['%d', '%d', '%s', '%f', '%d', '%f', '%s', '%s', '%s']
    );

    if (!$inserted) {
         wp_delete_attachment($attachment_id, true); 
         return new WP_Error('rest_db_error', 'Gagal menyimpan data pembelian paket.', ['status' => 500]);
    }
    
    return new WP_REST_Response([
        'message' => 'Pembelian paket berhasil dikirim. Harap tunggu verifikasi dari Super Admin.'
    ], 201); 
}
?>