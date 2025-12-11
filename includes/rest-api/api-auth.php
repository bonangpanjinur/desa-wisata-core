<?php
/**
 * File Name:   api-auth.php
 * File Folder: includes/rest-api/
 * File Path:   includes/rest-api/api-auth.php
 *
 * Endpoint API untuk Login, Register, dan Refresh Token.
 * --- PENINGKATAN KEAMANAN ---
 * 1. Menggunakan helper JWT & Refresh Token baru.
 * 2. Rate Limiting sederhana (berbasis transient) untuk mencegah Brute Force.
 * 3. Sanitasi input yang ketat.
 * 4. Error message yang tidak membocorkan informasi sensitif (misal: "User tidak ditemukan" vs "Password salah").
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Inisialisasi Route
add_action('rest_api_init', function () {
    // POST /dw/v1/auth/login
    register_rest_route('dw/v1', '/auth/login', [
        'methods'  => 'POST',
        'callback' => 'dw_rest_login',
        'permission_callback' => '__return_true', // Login terbuka untuk publik
    ]);

    // POST /dw/v1/auth/register
    register_rest_route('dw/v1', '/auth/register', [
        'methods'  => 'POST',
        'callback' => 'dw_rest_register',
        'permission_callback' => '__return_true',
    ]);

    // POST /dw/v1/auth/refresh
    register_rest_route('dw/v1', '/auth/refresh', [
        'methods'  => 'POST',
        'callback' => 'dw_rest_refresh_token',
        'permission_callback' => '__return_true',
    ]);
    
    // POST /dw/v1/auth/logout
    register_rest_route('dw/v1', '/auth/logout', [
        'methods'  => 'POST',
        'callback' => 'dw_rest_logout',
        'permission_callback' => function($request) {
            // Validasi token dulu sebelum logout
            $token = $request->get_header('Authorization');
            if (!$token) return false;
            $token = str_replace('Bearer ', '', $token);
            $decoded = dw_validate_access_token($token);
            return !is_wp_error($decoded);
        },
    ]);
});

/**
 * Endpoint Login.
 */
function dw_rest_login($request) {
    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username'] ?? '');
    $password = $params['password'] ?? ''; // Password jangan disanitasi text_field (karakter khusus boleh)

    if (empty($username) || empty($password)) {
        return new WP_Error('missing_credentials', 'Username dan password wajib diisi.', ['status' => 400]);
    }

    // 1. Rate Limiting (Cegah Brute Force)
    // Batasi: 5 percobaan gagal per IP per 10 menit
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit_key = 'dw_login_attempt_' . md5($ip);
    $attempts = get_transient($rate_limit_key) ?: 0;

    if ($attempts >= 5) {
        return new WP_Error('too_many_attempts', 'Terlalu banyak percobaan login gagal. Silakan coba lagi dalam 10 menit.', ['status' => 429]);
    }

    // 2. Autentikasi User
    $user = wp_authenticate($username, $password);

    if (is_wp_error($user)) {
        // Increment rate limit counter
        set_transient($rate_limit_key, $attempts + 1, 10 * MINUTE_IN_SECONDS);
        
        // Gunakan pesan error umum untuk keamanan (User Enumeration Protection)
        // Kecuali jika Anda ingin UX lebih baik, bisa gunakan $user->get_error_message()
        return new WP_Error('invalid_credentials', 'Username atau password salah.', ['status' => 401]);
    }

    // Reset rate limit jika berhasil
    delete_transient($rate_limit_key);

    // 3. Generate Token
    $access_token = dw_encode_jwt(['user_id' => $user->ID]);
    if (is_wp_error($access_token)) {
        return $access_token;
    }

    $refresh_token = dw_create_refresh_token($user->ID);
    if (!$refresh_token) {
        return new WP_Error('token_error', 'Gagal membuat refresh token.', ['status' => 500]);
    }

    // 4. Ambil Data Profil Singkat
    $user_data = [
        'id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'roles' => $user->roles,
    ];
    
    // Cek apakah user adalah pedagang
    global $wpdb;
    $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id, status_akun, nama_toko FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user->ID));
    
    if ($pedagang) {
        $user_data['is_pedagang'] = true;
        $user_data['pedagang_id'] = $pedagang->id;
        $user_data['toko_status'] = $pedagang->status_akun;
        $user_data['nama_toko'] = $pedagang->nama_toko;
    } else {
        $user_data['is_pedagang'] = false;
    }

    return dw_json_response([
        'token' => $access_token,
        'refresh_token' => $refresh_token,
        'user' => $user_data
    ]);
}

/**
 * Endpoint Register (Pembeli).
 */
function dw_rest_register($request) {
    $params = $request->get_json_params();
    
    $username = sanitize_user($params['username'] ?? '');
    $email    = sanitize_email($params['email'] ?? '');
    $password = $params['password'] ?? '';
    $fullname = sanitize_text_field($params['fullname'] ?? '');
    $no_hp    = dw_sanitize_phone($params['no_hp'] ?? '');

    // 1. Validasi Input
    if (empty($username) || empty($email) || empty($password) || empty($fullname)) {
        return new WP_Error('missing_fields', 'Semua field wajib diisi.', ['status' => 400]);
    }
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'Format email tidak valid.', ['status' => 400]);
    }
    if (username_exists($username)) {
        return new WP_Error('username_exists', 'Username sudah digunakan.', ['status' => 400]);
    }
    if (email_exists($email)) {
        return new WP_Error('email_exists', 'Email sudah terdaftar.', ['status' => 400]);
    }

    // 2. Buat User WordPress
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return new WP_Error('registration_failed', $user_id->get_error_message(), ['status' => 500]);
    }

    // 3. Update Data User
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $fullname,
        'first_name' => $fullname
    ]);
    
    // Simpan No HP di meta
    update_user_meta($user_id, 'billing_phone', $no_hp); // Kompatibilitas WooCommerce/Standar
    update_user_meta($user_id, 'dw_phone', $no_hp);

    // Set Role default (Customer/Subscriber)
    $user = new WP_User($user_id);
    $user->set_role('subscriber'); // Atau 'customer' jika ada

    // 4. Auto Login (Generate Token)
    $access_token = dw_encode_jwt(['user_id' => $user_id]);
    $refresh_token = dw_create_refresh_token($user_id);

    return dw_json_response([
        'message' => 'Registrasi berhasil.',
        'token' => $access_token,
        'refresh_token' => $refresh_token,
        'user' => [
            'id' => $user_id,
            'username' => $username,
            'email' => $email,
            'display_name' => $fullname,
            'roles' => ['subscriber']
        ]
    ], 201);
}

/**
 * Endpoint Refresh Token.
 * Digunakan saat Access Token expired.
 */
function dw_rest_refresh_token($request) {
    $params = $request->get_json_params();
    $token_client = $params['refresh_token'] ?? '';

    if (empty($token_client)) {
        return new WP_Error('missing_token', 'Refresh token wajib dikirim.', ['status' => 400]);
    }

    // 1. Validasi Token di DB
    $user_id = dw_validate_refresh_token($token_client);

    if (!$user_id) {
        return new WP_Error('invalid_token', 'Refresh token tidak valid atau kadaluarsa. Silakan login ulang.', ['status' => 403]);
    }

    // 2. Rotate Refresh Token (Optional Security Best Practice: Ganti refresh token lama dengan baru)
    // Jika ingin rotate, uncomment baris bawah dan kirim token baru ke client
    // $new_refresh_token = dw_create_refresh_token($user_id); 
    
    // 3. Generate Access Token Baru
    $new_access_token = dw_encode_jwt(['user_id' => $user_id]);

    if (is_wp_error($new_access_token)) {
        return $new_access_token;
    }

    return dw_json_response([
        'token' => $new_access_token,
        // 'refresh_token' => $new_refresh_token // Kirim jika rotasi aktif
    ]);
}

/**
 * Endpoint Logout.
 * Mencabut refresh token dan mem-blacklist access token (jika perlu).
 */
function dw_rest_logout($request) {
    $params = $request->get_json_params();
    $refresh_token = $params['refresh_token'] ?? '';
    
    // Ambil Access Token dari Header untuk diblacklist
    $auth_header = $request->get_header('Authorization');
    $access_token = str_replace('Bearer ', '', $auth_header);

    // 1. Cabut Refresh Token
    if (!empty($refresh_token)) {
        dw_revoke_refresh_token($refresh_token);
    }

    // 2. Blacklist Access Token (Opsional tapi bagus)
    if (!empty($access_token)) {
        $decoded = dw_validate_access_token($access_token); // Decode untuk dapat user_id & exp
        if (!is_wp_error($decoded)) {
            dw_add_token_to_blacklist($access_token, $decoded->data->user_id, $decoded->exp);
        }
    }

    return dw_json_response(['message' => 'Berhasil logout.'], 200);
}
?>