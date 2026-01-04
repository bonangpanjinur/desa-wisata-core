<?php
/**
 * File Path: includes/rest-api/api-pembeli.php
 *
 * Mendaftarkan semua endpoint API yang memerlukan otentikasi
 * sebagai 'pembeli' atau 'customer'.
 *
 * --- PERBAIKAN (KEAMANAN v3.2.7) ---
 * - `dw_api_upload_media`: MENAMBAHKAN validasi ekstensi file
 * untuk (jpg, jpeg, png, pdf) sebagai lapisan keamanan tambahan.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mendaftarkan semua endpoint API pembeli.
 */
function dw_api_register_pembeli_routes() {
    $namespace = 'dw/v1';

    // Endpoint daftar alamat pembeli
    register_rest_route( $namespace, '/pembeli/addresses', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_my_addresses',
        'permission_callback' => 'dw_permission_check_logged_in', 
    ] );

    // Endpoint tambah alamat baru
    register_rest_route( $namespace, '/pembeli/addresses', [
        'methods'  => 'POST',
        'callback' => 'dw_api_add_my_address',
        'permission_callback' => 'dw_permission_check_logged_in', 
        'args'     => [
            'nama_penerima' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'no_hp' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'alamat_lengkap' => ['required' => true, 'sanitize_callback' => 'sanitize_textarea_field'],
            'provinsi' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'kabupaten' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'kecamatan' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'kelurahan' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'kode_pos' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'api_provinsi_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'api_kabupaten_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'api_kecamatan_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'api_kelurahan_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'is_default' => ['sanitize_callback' => 'absint'],
        ],
    ] );

    // Endpoint daftar pesanan pembeli
    register_rest_route( $namespace, '/pembeli/orders', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_my_orders',
        'permission_callback' => 'dw_permission_check_logged_in', 
    ] );

    // Endpoint buat pesanan baru (checkout)
    register_rest_route( $namespace, '/pembeli/orders', [
        'methods'  => 'POST',
        'callback' => 'dw_api_create_order',
        'permission_callback' => 'dw_permission_check_logged_in', 
        // Validasi argumen dilakukan di dalam fungsi callback
    ] );

    // Endpoint detail pesanan pembeli
    register_rest_route( $namespace, '/pembeli/orders/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_my_order_detail',
        'permission_callback' => 'dw_permission_check_order_owner', 
        'args'     => [
            'id' => [
                'validate_callback' => 'dw_rest_validate_numeric', 
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );

    // Endpoint konfirmasi pembayaran
    register_rest_route( $namespace, '/pembeli/orders/confirm-payment', [
        'methods'  => 'POST',
        'callback' => 'dw_api_confirm_payment',
        'permission_callback' => 'dw_permission_check_order_owner_by_order_id', 
        'args'     => [
            'order_id' => ['required' => true, 'sanitize_callback' => 'absint'],
            'payment_proof_url' => ['required' => true, 'sanitize_callback' => 'esc_url_raw'],
            'notes' => ['sanitize_callback' => 'sanitize_textarea_field'],
        ],
    ] );

    // Endpoint upload media (bukti bayar)
    register_rest_route( $namespace, '/pembeli/upload-media', [
        'methods'  => 'POST',
        'callback' => 'dw_api_upload_media',
        'permission_callback' => 'dw_permission_check_logged_in', 
    ] );

    // Endpoint keranjang pembeli (GET)
    register_rest_route( $namespace, '/pembeli/cart', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_my_cart',
        'permission_callback' => 'dw_permission_check_logged_in', 
    ] );

    // Endpoint keranjang pembeli (SYNC)
    register_rest_route( $namespace, '/pembeli/cart/sync', [
        'methods'  => 'POST',
        'callback' => 'dw_api_sync_my_cart',
        'permission_callback' => 'dw_permission_check_logged_in', 
    ] );

    // Endpoint keranjang pembeli (DELETE)
    register_rest_route( $namespace, '/pembeli/cart', [
        'methods'  => 'DELETE',
        'callback' => 'dw_api_clear_my_cart',
        'permission_callback' => 'dw_permission_check_logged_in', 
    ] );

}

/**
 * Helper permission check by order_id in params
 */
function dw_permission_check_order_owner_by_order_id(WP_REST_Request $request) {
    $order_id = absint($request['order_id']);
    if ($order_id <= 0) return false;
    
    // Create a temporary request to use existing helper
    $temp_request = new WP_REST_Request();
    $temp_request->set_header('Authorization', $request->get_header('Authorization'));
    $temp_request['id'] = $order_id;
    
    return dw_permission_check_order_owner($temp_request);
}

/**
 * Mengambil daftar alamat yang disimpan oleh pembeli.
 */
function dw_api_get_my_addresses(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    if ( is_wp_error($user_id) ) return $user_id;

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_user_alamat';
    $addresses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d", $user_id
    ));

    $default_address_id = (int) get_user_meta($user_id, 'default_address_id', true);

    $formatted_addresses = array_map(function($addr) {
        return [
            'id' => (int) $addr->id,
            'nama_penerima' => $addr->nama_penerima,
            'no_hp' => $addr->no_hp,
            'alamat_lengkap' => $addr->alamat_lengkap,
            'provinsi' => $addr->provinsi,
            'kabupaten' => $addr->kabupaten,
            'kecamatan' => $addr->kecamatan,
            'kelurahan' => $addr->kelurahan,
            'kode_pos' => $addr->kode_pos,
            'api_provinsi_id' => $addr->api_provinsi_id,
            'api_kabupaten_id' => $addr->api_kabupaten_id,
            'api_kecamatan_id' => $addr->api_kecamatan_id,
            'api_kelurahan_id' => $addr->api_kelurahan_id,
        ];
    }, $addresses);

    return new WP_REST_Response([
        'addresses' => $formatted_addresses,
        'default_address_id' => $default_address_id,
    ], 200);
}

/**
 * Menambah alamat baru untuk pembeli.
 */
function dw_api_add_my_address(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    if ( is_wp_error($user_id) ) return $user_id;

    $params = $request->get_params();

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_user_alamat';

    $data = [
        'user_id' => $user_id,
        'nama_penerima' => $params['nama_penerima'],
        'no_hp' => $params['no_hp'],
        'alamat_lengkap' => $params['alamat_lengkap'],
        'provinsi' => $params['provinsi'],
        'kabupaten' => $params['kabupaten'],
        'kecamatan' => $params['kecamatan'],
        'kelurahan' => $params['kelurahan'],
        'kode_pos' => $params['kode_pos'],
        'api_provinsi_id' => $params['api_provinsi_id'],
        'api_kabupaten_id' => $params['api_kabupaten_id'],
        'api_kecamatan_id' => $params['api_kecamatan_id'],
        'api_kelurahan_id' => $params['api_kelurahan_id'],
    ];

    $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

    $result = $wpdb->insert($table_name, $data, $format);

    if ($result === false) {
        dw_log_activity('ADD_ADDRESS_FAIL', "Gagal DB insert alamat untuk user #{$user_id}. Error: " . $wpdb->last_error, $user_id);
        return new WP_Error('rest_db_error', __('Gagal menyimpan alamat.', 'desa-wisata-core'), ['status' => 500]);
    }

    $new_address_id = $wpdb->insert_id;

    // Jika 'is_default' di-centang atau jika ini alamat pertama
    $address_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE user_id = %d", $user_id));
    if (!empty($params['is_default']) || $address_count == 1) { // Perbandingan
        update_user_meta($user_id, 'default_address_id', $new_address_id);
    }

    dw_log_activity('ADD_ADDRESS_SUCCESS', "Alamat baru #{$new_address_id} ditambahkan untuk user #{$user_id}", $user_id);
    return new WP_REST_Response(['message' => 'Alamat berhasil ditambahkan.', 'new_address_id' => $new_address_id], 201);
}

/**
 * Mengambil daftar pesanan milik pembeli.
 */
function dw_api_get_my_orders(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    if ( is_wp_error($user_id) ) return $user_id;

    // Fungsi ini ada di 'includes/cart.php'
    $orders = dw_get_orders_by_customer_id($user_id);

    return new WP_REST_Response($orders, 200);
}

/**
 * Mengambil detail spesifik pesanan milik pembeli.
 */
function dw_api_get_my_order_detail(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    if ( is_wp_error($user_id) ) return $user_id;
    
    $order_id = $request['id'];

    // Fungsi ini ada di 'includes/cart.php'
    $order_data = dw_get_order_detail($order_id);

    if (!$order_data) {
        return new WP_Error('rest_not_found', __('Pesanan tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }

    return new WP_REST_Response($order_data, 200);
}

/**
 * Membuat pesanan baru (checkout).
 */
function dw_api_create_order(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    if ( is_wp_error($user_id) ) return $user_id;

    $params = $request->get_json_params();
    
    // Fungsi ini ada di 'includes/cart.php'
    $result = dw_process_order($user_id, $params);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['message' => $result->get_error_message()], 400);
    }

    return new WP_REST_Response($result, 201); // 201 Created
}

/**
 * Mengonfirmasi pembayaran.
 */
function dw_api_confirm_payment(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    if ( is_wp_error($user_id) ) return $user_id;

    $params = $request->get_params();
    $order_id = $params['order_id'];
    $payment_proof_url = $params['payment_proof_url'];
    $notes = $params['notes'] ?? ''; // Default ke string kosong

    // ... (sisanya tetap sama)
