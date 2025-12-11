<?php
/**
 * File Name:   api-auth.php
 * File Folder: includes/rest-api/
 * File Path:   includes/rest-api/api-auth.php
 *
 * --- PERBAIKAN (KRITIS v3.2.4) ---
 * - Mengganti `dw_api_login` agar menggunakan helper JWT terpusat (`dw_encode_jwt`
 * dan `dw_create_refresh_token`) dari `helpers.php`.
 * - Mengganti `dw_api_permission_check_auth_user` (yang tidak ada)
 * menjadi `dw_permission_check_logged_in` pada endpoint `/auth/validate-token`.
 * - MENAMBAHKAN endpoint `/auth/refresh` untuk memperbarui access token.
 * - MENAMBAHKAN endpoint `/auth/logout` untuk menghapus token.
 *
 * --- PENAMBAHAN (LENGKAP) ---
 * - Menambahkan endpoint validasi token (/auth/validate-token).
 * - Menambahkan endpoint lupa password (/auth/forgot-password).
 * - Menambahkan endpoint reset password (/auth/reset-password).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Pastikan variabel $namespace sudah didefinisikan di file pemanggil (rest-api.php)
if ( ! isset( $namespace ) ) {
    return;
}
// Pastikan Firebase JWT library sudah di-load
if ( ! class_exists( 'Firebase\\JWT\\JWT' ) ) {
    return;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// =========================================================================
// ENDPOINT OTENTIKASI (PUBLIK)
// =========================================================================

register_rest_route($namespace, '/auth/register', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_register_user',
    'permission_callback' => '__return_true', // Siapapun bisa mendaftar
    'args' => [
        'username' => ['required' => true, 'sanitize_callback' => 'sanitize_user'],
        'email' => ['required' => true, 'sanitize_callback' => 'sanitize_email', 'validate_callback' => 'is_email'],
        'password' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
        'nama_lengkap' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
]);

register_rest_route($namespace, '/auth/login', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_login',
    'permission_callback' => '__return_true', // Siapapun bisa coba login
    'args' => [
        'username' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'], // Bisa username atau email
        'password' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
]);

// --- BARU: REFRESH TOKEN ---
register_rest_route($namespace, '/auth/refresh', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_refresh_token',
    'permission_callback' => '__return_true', // Publik, tapi butuh refresh token valid
    'args' => [
        'refresh_token' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
]);

// --- BARU: LOGOUT ---
register_rest_route($namespace, '/auth/logout', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_logout',
    'permission_callback' => 'dw_permission_check_logged_in', // Harus login untuk logout
    'args' => [
        'refresh_token' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
]);


// --- VALIDASI TOKEN ---
register_rest_route($namespace, '/auth/validate-token', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'dw_api_validate_token',
    // --- PERBAIKAN: Menggunakan permission callback yang benar ---
    'permission_callback' => 'dw_permission_check_logged_in', 
]);

// --- LUPA PASSWORD ---
register_rest_route($namespace, '/auth/forgot-password', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_forgot_password',
    'permission_callback' => '__return_true', // Publik
    'args' => [
        'email' => ['required' => true, 'sanitize_callback' => 'sanitize_email', 'validate_callback' => 'is_email'],
    ],
]);

// --- RESET PASSWORD ---
register_rest_route($namespace, '/auth/reset-password', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'dw_api_reset_password',
    'permission_callback' => '__return_true', // Publik
    'args' => [
        'key' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
        'login' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
        'new_password' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
]);


// =========================================================================
// IMPLEMENTASI CALLBACK (OTENTIKASI)
// =========================================================================

/**
 * Callback untuk registrasi pengguna baru.
 */
function dw_api_register_user(WP_REST_Request $request) {
    $username = $request['username'];
    $email = $request['email'];
    $password = $request['password'];
    $nama_lengkap = $request['nama_lengkap']; // Ambil nama lengkap

    if (username_exists($username)) {
        return new WP_Error('rest_username_exists', __('Username sudah terdaftar.', 'desa-wisata-core'), ['status' => 400]);
    }
    if (email_exists($email)) {
        return new WP_Error('rest_email_exists', __('Email sudah terdaftar.', 'desa-wisata-core'), ['status' => 400]);
    }

    // Buat pengguna baru
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return new WP_Error('rest_registration_failed', __('Gagal membuat pengguna.', 'desa-wisata-core'), ['status' => 500]);
    }

    // Set peran default sebagai 'pembeli'
    $user = new WP_User($user_id);
    $user->set_role('pembeli');

    // Simpan nama lengkap
    $name_parts = explode(' ', $nama_lengkap, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? ''; // Jika tidak ada nama belakang

    wp_update_user([
        'ID' => $user_id,
        'display_name' => $nama_lengkap,
        'first_name' => $first_name,
        'last_name' => $last_name,
    ]);

    wp_send_new_user_notifications($user_id, 'admin');

    return new WP_REST_Response(['message' => __('Registrasi berhasil. Silakan login.', 'desa-wisata-core')], 201);
}

/**
 * Callback untuk login pengguna.
 * --- PERBAIKAN: Menggunakan helper JWT terpusat ---
 */
function dw_api_login(WP_REST_Request $request) {
    $username = $request['username']; // Bisa username atau email
    $password = $request['password'];

    $creds = [
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => false,
    ];

    if (is_email($username)) {
        $user = get_user_by('email', $username);
        if ($user) {
            $creds['user_login'] = $user->user_login; 
        }
    }

    $user_signon = wp_signon($creds, false);

    if (is_wp_error($user_signon)) {
        return new WP_Error('rest_login_failed', __('Username atau password salah.', 'desa-wisata-core'), ['status' => 403]);
    }

    $user_id = $user_signon->ID;
    $user_data = dw_internal_get_user_data_for_token($user_signon);

    // Dapatkan data pedagang/admin desa
    $pedagang_data = dw_get_pedagang_data_by_user_id($user_id);
    $user_data['is_pedagang'] = (bool) $pedagang_data;
    if ($pedagang_data) $user_data['pedagang_id'] = $pedagang_data->id;

    $desa_data = dw_get_desa_admin_data_by_user_id($user_id);
    $user_data['is_admin_desa'] = (bool) $desa_data;
    if ($desa_data) $user_data['admin_desa_id'] = $desa_data->id;


    // --- PERBAIKAN: Gunakan helper terpusat ---
    // 1. Buat Access Token
    $access_token = dw_encode_jwt(['user_id' => $user_id], DW_JWT_ACCESS_TOKEN_EXPIRATION);
    if (is_wp_error($access_token)) {
        return $access_token; // Kembalikan error jika gagal (misal: key tidak diset di production)
    }

    // 2. Buat Refresh Token
    $refresh_token = dw_create_refresh_token($user_id);
    if ($refresh_token === false) {
        return new WP_Error('rest_refresh_token_failed', __('Gagal membuat sesi login.', 'desa-wisata-core'), ['status' => 500]);
    }
    // --- AKHIR PERBAIKAN ---

    return new WP_REST_Response([
        'access_token' => $access_token,
        'refresh_token' => $refresh_token,
        'user_data' => $user_data,
        'expires_in' => DW_JWT_ACCESS_TOKEN_EXPIRATION,
    ], 200);
}

/**
 * [BARU] Callback untuk refresh token.
 */
function dw_api_refresh_token(WP_REST_Request $request) {
    $refresh_token = $request['refresh_token'];
    
    // Validasi refresh token
    $user_id = dw_validate_refresh_token($refresh_token);
    if (!$user_id) {
        return new WP_Error('rest_invalid_refresh_token', __('Refresh token tidak valid atau telah kedaluwarsa.', 'desa-wisata-core'), ['status' => 401]);
    }
    
    // Buat access token baru
    $access_token = dw_encode_jwt(['user_id' => $user_id], DW_JWT_ACCESS_TOKEN_EXPIRATION);
    if (is_wp_error($access_token)) {
        return $access_token;
    }

    return new WP_REST_Response([
        'access_token' => $access_token,
        'expires_in' => DW_JWT_ACCESS_TOKEN_EXPIRATION,
    ], 200);
}

/**
 * [BARU] Callback untuk logout.
 */
function dw_api_logout(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    $refresh_token = $request['refresh_token'];
    
    // 1. Cabut (hapus) refresh token dari database
    dw_revoke_refresh_token($refresh_token);
    
    // 2. Tambahkan access token ke blacklist (JTI - Token ID)
    // Kita perlu mendapatkan token mentah dari helper
    $auth_header = $request->get_header('Authorization');
    preg_match('/^Bearer\s+(.*)$/i', $auth_header, $matches);
    $access_token = $matches[1] ?? '';
    
    if (!empty($access_token)) {
        // Decode untuk mendapatkan 'exp'
        $decoded = dw_decode_jwt($access_token);
        if (!is_wp_error($decoded) && isset($decoded->exp)) {
            dw_add_token_to_blacklist($access_token, $user_id, $decoded->exp);
        }
    }

    return new WP_REST_Response(['message' => __('Logout berhasil.', 'desa-wisata-core')], 200);
}


/**
 * [BARU] Callback untuk validasi token.
 * Mengembalikan data pengguna jika token valid.
 */
function dw_api_validate_token(WP_REST_Request $request) {
    // Permission callback 'dw_permission_check_logged_in' sudah memvalidasi token
    // dan `dw_get_user_id_from_request` sudah mengembalikan ID
    $user_id = dw_get_user_id_from_request($request); 
    
    if (is_wp_error($user_id)) {
        return $user_id; // Kembalikan error (misal: 401 expired)
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('rest_user_not_found', __('Pengguna tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }

    // Ambil data terbaru pengguna
    $user_data = dw_internal_get_user_data_for_token($user);

    // Dapatkan data pedagang jika ada
    $pedagang_data = dw_get_pedagang_data_by_user_id($user_id);
    $user_data['is_pedagang'] = (bool) $pedagang_data;
    if ($pedagang_data) $user_data['pedagang_id'] = $pedagang_data->id;
    
    // Dapatkan data admin desa jika ada
    $desa_data = dw_get_desa_admin_data_by_user_id($user_id);
    $user_data['is_admin_desa'] = (bool) $desa_data;
    if ($desa_data) $user_data['admin_desa_id'] = $desa_data->id;

    return new WP_REST_Response([
        'user_data' => $user_data,
    ], 200);
}

/**
 * [BARU] Callback untuk mengirim email lupa password.
 */
function dw_api_forgot_password(WP_REST_Request $request) {
    $email = $request['email'];
    
    $user_data = get_user_by('email', $email);
    if (!$user_data) {
        return new WP_Error('rest_email_not_found', __('Tidak ada pengguna dengan alamat email tersebut.', 'desa-wisata-core'), ['status' => 404]);
    }

    // --- PERBAIKAN: Ganti filter subject/message bawaan WP ---
    // WordPress
    add_filter( 'retrieve_password_title', 'dw_custom_retrieve_password_title', 10, 3 );
    add_filter( 'retrieve_password_message', 'dw_custom_retrieve_password_message', 10, 4 );

    $result = retrieve_password($user_data->user_login); 

    // Hapus filter agar tidak memengaruhi email WP lainnya
    remove_filter( 'retrieve_password_title', 'dw_custom_retrieve_password_title' );
    remove_filter( 'retrieve_password_message', 'dw_custom_retrieve_password_message' );
    // --- AKHIR PERBAIKAN ---

    if (is_wp_error($result)) {
        return new WP_Error('rest_reset_failed', $result->get_error_message(), ['status' => 500]);
    }

    return new WP_REST_Response(['message' => __('Email untuk reset password telah dikirim. Silakan periksa kotak masuk Anda.', 'desa-wisata-core')], 200);
}

/**
 * [BARU] Callback untuk me-reset password menggunakan key.
 */
function dw_api_reset_password(WP_REST_Request $request) {
    $key = $request['key'];
    $login = $request['login'];
    $new_password = $request['new_password'];

    $user = check_password_reset_key($key, $login);

    if (is_wp_error($user)) {
        $error_code = $user->get_error_code();
        $message = '';
        if ($error_code === 'expired_key') {
            $message = __('Link reset password Anda telah kedaluwarsa.', 'desa-wisata-core');
        } elseif ($error_code === 'invalid_key') {
            $message = __('Link reset password Anda tidak valid.', 'desa-wisata-core');
        } else {
            $message = $user->get_error_message();
        }
        return new WP_Error('rest_reset_key_invalid', $message, ['status' => 400]);
    }

    reset_password($user, $new_password);

    return new WP_REST_Response(['message' => __('Password Anda telah berhasil direset. Silakan login.', 'desa-wisata-core')], 200);
}


// =========================================================================
// HELPER INTERNAL (AUTH)
// =========================================================================

/**
 * Helper untuk mengambil data pengguna yang akan disimpan di frontend.
 */
function dw_internal_get_user_data_for_token(WP_User $user) {
    // Ambil alamat utama jika ada
    $default_address_id = (int) get_user_meta($user->ID, 'default_address_id', true);
    $alamat_utama = null;
    if ($default_address_id > 0) {
        global $wpdb;
        $alamat_utama = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dw_user_alamat WHERE id = %d AND user_id = %d",
            $default_address_id, $user->ID
        ), 'ARRAY_A');
    }
    
    return [
        'id' => $user->ID,
        'email' => $user->user_email,
        'username' => $user->user_login,
        'display_name' => $user->display_name,
        'roles' => $user->roles,
        'alamat_utama' => $alamat_utama,
    ];
}

/**
 * Helper untuk mengambil data pedagang.
 */
function dw_get_pedagang_data_by_user_id($user_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT id, status_akun FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id
    ));
}

/**
 * Helper untuk mengambil data admin desa.
 */
function dw_get_desa_admin_data_by_user_id($user_id) {
     global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT id, status FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id
    ));
}

// --- BARU: Kustomisasi Email Reset Password ---

/**
 * Mengganti judul email reset password.
 */
function dw_custom_retrieve_password_title( $title, $user_login, $user_data ) {
    $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    return "[$site_name] Reset Password Anda";
}

/**
 * Mengganti isi pesan email reset password.
 */
function dw_custom_retrieve_password_message( $message, $key, $user_login, $user_data ) {
    // Ambil URL frontend dari pengaturan
    $options = get_option('dw_settings');
    $frontend_url = $options['frontend_url'] ?? home_url(); // Fallback ke home_url jika tidak diset
    
    // Pastikan URL frontend memiliki trailing slash
    $frontend_url = rtrim($frontend_url, '/') . '/';
    
    // Buat link reset kustom yang mengarah ke frontend
    // Frontend Anda harus memiliki halaman di /reset-password
    $reset_link = $frontend_url . 'reset-password?key=' . $key . '&login=' . rawurlencode( $user_login );

    $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    
    $custom_message = "Halo " . $user_data->display_name . ",\n\n";
    $custom_message .= "Seseorang telah meminta reset password untuk akun Anda di $site_name.\n\n";
    $custom_message .= "Jika ini adalah Anda, klik link berikut untuk membuat password baru:\n";
    $custom_message .= $reset_link . "\n\n";
    $custom_message .= "Jika Anda tidak meminta ini, abaikan saja email ini.\n\n";
    $custom_message .= "Terima kasih,\n";
    $custom_message .= "Tim $site_name";

    return $custom_message;
}
?>