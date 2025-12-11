<?php
/**
 * File Name:   api-permissions.php
 * File Folder: includes/rest-api/
 * File Path:   includes/rest-api/api-permissions.php
 *
 * Berisi semua fungsi `permission_callback` untuk REST API.
 * Dipanggil oleh `rest-api.php`.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// Implementasi Permission Callbacks
// =============================================================================

/**
 * Cek jika user sudah login (berdasarkan JWT valid).
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_logged_in(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request);
    if ($user_id instanceof WP_Error) {
        return $user_id; // Kembalikan error jika token tidak valid/kadaluwarsa
    }
    return $user_id > 0;
}

/**
 * Cek jika user adalah Pedagang (role 'pedagang').
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_pedagang(WP_REST_Request $request) {
    $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request); // Ambil user ID lagi (sudah divalidasi)
    if (user_can($user_id, 'pedagang')) {
        return true;
    }
    return new WP_Error('rest_forbidden_role', __('Hanya Pedagang yang diizinkan.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user adalah Admin Desa (role 'admin_desa') dan TIDAK diblokir.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_admin_desa(WP_REST_Request $request) {
     $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'admin_desa')) {
        // Cek status blokir dari helper
        if (function_exists('dw_is_admin_desa_blocked') && dw_is_admin_desa_blocked()) {
             return new WP_Error('rest_account_blocked', __('Akun Admin Desa Anda diblokir.', 'desa-wisata-core'), ['status' => 403]);
        }
        return true;
    }
    return new WP_Error('rest_forbidden_role', __('Hanya Admin Desa yang diizinkan.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user adalah Admin Kabupaten atau Super Admin.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_admin_kabupaten_or_super(WP_REST_Request $request) {
     $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'administrator') || user_can($user_id, 'admin_kabupaten')) {
        return true;
    }
     return new WP_Error('rest_forbidden_role', __('Hanya Administrator atau Admin Kabupaten yang diizinkan.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user adalah Administrator (Super Admin).
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_administrator(WP_REST_Request $request) {
     $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'administrator')) {
        return true;
    }
    return new WP_Error('rest_forbidden_role', __('Hanya Administrator yang diizinkan.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user memiliki kapabilitas 'manage_categories'.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_manage_categories(WP_REST_Request $request) {
    $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;
    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'manage_categories')) {
        return true;
    }
    return new WP_Error('rest_forbidden_capability', __('Anda tidak memiliki izin mengelola kategori.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user memiliki kapabilitas 'moderate_comments'.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_moderate_comments(WP_REST_Request $request) {
    $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;
    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'moderate_comments')) {
        return true;
    }
     return new WP_Error('rest_forbidden_capability', __('Anda tidak memiliki izin memoderasi ulasan.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user memiliki kapabilitas kustom 'dw_manage_promosi'.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_manage_promosi(WP_REST_Request $request) {
    $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;
    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'dw_manage_promosi')) {
        return true;
    }
    return new WP_Error('rest_forbidden_capability', __('Anda tidak memiliki izin mengelola promosi.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user memiliki kapabilitas kustom 'dw_manage_banners'.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_manage_banners(WP_REST_Request $request) {
     $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;
    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'dw_manage_banners')) {
        return true;
    }
     return new WP_Error('rest_forbidden_capability', __('Anda tidak memiliki izin mengelola banner.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user memiliki kapabilitas kustom 'dw_manage_settings'.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_manage_settings(WP_REST_Request $request) {
     $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;
    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'dw_manage_settings')) {
        return true;
    }
    return new WP_Error('rest_forbidden_capability', __('Anda tidak memiliki izin mengelola pengaturan.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user memiliki kapabilitas kustom 'dw_view_logs'.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_view_logs(WP_REST_Request $request) {
    $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;
    $user_id = dw_get_user_id_from_request($request);
    if (user_can($user_id, 'dw_view_logs')) {
        return true;
    }
     return new WP_Error('rest_forbidden_capability', __('Anda tidak memiliki izin melihat log.', 'desa-wisata-core'), ['status' => 403]);
}


/**
 * Cek jika user adalah pemilik order (pembeli).
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_order_owner(WP_REST_Request $request) {
    $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request);
    $order_id = absint($request['id']);
    if ($order_id <= 0) return false; // ID tidak valid

    global $wpdb;
    $order_pembeli_id = $wpdb->get_var($wpdb->prepare("SELECT id_pembeli FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));

    if ($order_pembeli_id && $order_pembeli_id == $user_id) {
        return true;
    }
    return new WP_Error('rest_forbidden_ownership', __('Anda bukan pemilik pesanan ini.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user adalah pemilik order ATAU admin/admin kab.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_order_owner_or_admin(WP_REST_Request $request) {
    $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission)) return $permission;
    if (!$permission) return false;

    $user_id = dw_get_user_id_from_request($request);
     // Admin/Admin Kab selalu boleh lihat
     if (user_can($user_id, 'administrator') || user_can($user_id, 'admin_kabupaten')) {
        return true;
    }

    $order_id = absint($request['id']);
    if ($order_id <= 0) return false;

    global $wpdb;
    $order_pembeli_id = $wpdb->get_var($wpdb->prepare("SELECT id_pembeli FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));

    if ($order_pembeli_id && $order_pembeli_id == $user_id) {
        return true;
    }
    return new WP_Error('rest_forbidden_view', __('Anda tidak diizinkan melihat pesanan ini.', 'desa-wisata-core'), ['status' => 403]);
}


/**
 * Cek jika user adalah pedagang pemilik order.
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_order_pedagang(WP_REST_Request $request) {
    $permission = dw_permission_check_pedagang($request); // Cek login & role pedagang
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request);
    $order_id = absint($request['id']);
    if ($order_id <= 0) return false;

    global $wpdb;
    // Dapatkan ID Pedagang dari User ID
    $pedagang_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id));
    if (!$pedagang_id) return false; // Bukan pedagang

    // Dapatkan ID Pedagang pemilik order
    $order_pedagang_id = $wpdb->get_var($wpdb->prepare("SELECT id_pedagang FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));

    if ($order_pedagang_id && $order_pedagang_id == $pedagang_id) {
        return true;
    }
    return new WP_Error('rest_forbidden_ownership', __('Anda bukan pemilik pesanan ini.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user adalah pemilik produk (pedagang).
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_product_owner(WP_REST_Request $request) {
     $permission = dw_permission_check_pedagang($request); // Cek login & role pedagang
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request);
    $product_id = absint($request['id'] ?? $request['product_id'] ?? 0); // Handle 'id' atau 'product_id'
    if ($product_id <= 0) return false;

    $product_author_id = get_post_field('post_author', $product_id);

    if ($product_author_id && $product_author_id == $user_id) {
        return true;
    }
    return new WP_Error('rest_forbidden_ownership', __('Anda bukan pemilik produk ini.', 'desa-wisata-core'), ['status' => 403]);
}

/**
 * Cek jika user adalah partisipan chat (pembeli atau penjual produk).
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_chat_participant(WP_REST_Request $request) {
    $permission = dw_permission_check_logged_in($request);
    if (is_wp_error($permission) || !$permission) return $permission;

    $user_id = dw_get_user_id_from_request($request);
    $product_id = absint($request['product_id']);
    if ($product_id <= 0) return false;

    global $wpdb;
    // Cek apakah user adalah penjual produk
    $product_author_id = get_post_field('post_author', $product_id);
    if ($product_author_id && $product_author_id == $user_id) {
        return true;
    }

    // Cek apakah user pernah mengirim atau menerima pesan untuk produk ini
    $is_participant = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}dw_chat_message WHERE produk_id = %d AND (sender_id = %d OR receiver_id = %d)",
        $product_id, $user_id, $user_id
    ));

    if ($is_participant) {
        return true;
    }

    return new WP_Error('rest_forbidden_chat', __('Anda tidak diizinkan melihat percakapan ini.', 'desa-wisata-core'), ['status' => 403]);
}


/**
 * Cek akses ke keranjang (Login atau punya Guest ID valid).
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function dw_permission_check_cart_access(WP_REST_Request $request) {
    $user_id = dw_get_user_id_from_request($request, false); // Jangan return error jika token invalid, cek guest
    if ($user_id > 0) return true; // User login boleh akses

    // Jika tidak login, cek header X-Guest-ID
    $guest_id = $request->get_header('X-Guest-ID');
    // TODO: Tambahkan validasi format Guest ID jika diperlukan (misal: UUID)
    if (!empty($guest_id)) {
        return true;
    }

    // Jika tidak login dan tidak ada Guest ID
     return new WP_Error('rest_cart_unidentified', __('Akses keranjang ditolak. Harap login atau sediakan Guest ID.', 'desa-wisata-core'), ['status' => 401]);
}
?>
