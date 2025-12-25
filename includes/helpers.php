<?php
/**
 * File Name:   helpers.php
 * File Folder: includes/
 * File Path:   includes/helpers.php
 *
 * Fungsi bantuan umum untuk plugin Desa Wisata Core.
 * Termasuk fungsi JWT, formatting, utilitas, Relasi Wilayah, dan Komisi.
 * * --- UPDATE v3.4 ---
 * 1. Added: Relasi otomatis Pedagang - Desa berdasarkan Kelurahan.
 * 2. Added: Logika komisi berjenjang (Admin vs Desa).
 * 3. Integrated: Semua fungsi original JWT & Stock v3.3 tetap utuh.
 * 4. Added: Logika Monetisasi Desa (Freemium).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

// =============================================================================
// HELPER INTERNAL: KUOTA PEDAGANG
// =============================================================================

/**
 * Helper untuk memeriksa sisa kuota transaksi pedagang.
 */
function dw_check_pedagang_kuota($user_id) {
    global $wpdb;
    
    $pedagang = $wpdb->get_row($wpdb->prepare(
        "SELECT id, sisa_transaksi, status_akun FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", 
        $user_id
    ));

    if (!$pedagang) {
        return new WP_Error('rest_not_pedagang', 'Profil pedagang tidak ditemukan.', ['status' => 404]);
    }

    if ($pedagang->status_akun === 'nonaktif_habis_kuota' || $pedagang->sisa_transaksi <= 0) {
        if ($pedagang->status_akun !== 'nonaktif_habis_kuota') {
             $wpdb->update($wpdb->prefix . 'dw_pedagang', ['status_akun' => 'nonaktif_habis_kuota'], ['id' => $pedagang->id]);
        }
        return new WP_Error(
            'rest_kuota_habis',
            'Kuota transaksi Anda telah habis. Harap segera beli paket transaksi baru.',
            ['status' => 403]
        );
    }
    
    if ($pedagang->status_akun === 'nonaktif') {
         return new WP_Error('rest_akun_nonaktif', 'Akun Anda dinonaktifkan oleh Administrator.', ['status' => 403]);
    }

    return $pedagang; 
}

/**
 * Mengurangi kuota transaksi pedagang sebanyak 1.
 */
function dw_reduce_pedagang_kuota($user_id) {
    global $wpdb;
    $pedagang_table = $wpdb->prefix . 'dw_pedagang';

    if (is_wp_error(dw_check_pedagang_kuota($user_id))) {
        return false;
    }

    $result = $wpdb->query($wpdb->prepare(
        "UPDATE $pedagang_table SET sisa_transaksi = sisa_transaksi - 1 WHERE id_user = %d AND sisa_transaksi > 0",
        $user_id
    ));

    return $result !== false;
}


// =============================================================================
// KONSTANTA JWT & SECURITY
// =============================================================================
if (!defined('DW_JWT_ALGORITHM')) {
    define('DW_JWT_ALGORITHM', 'HS256'); 
}
if (!defined('DW_JWT_ACCESS_TOKEN_EXPIRATION')) {
    define('DW_JWT_ACCESS_TOKEN_EXPIRATION', HOUR_IN_SECONDS); 
}
if (!defined('DW_JWT_REFRESH_TOKEN_EXPIRATION')) {
    define('DW_JWT_REFRESH_TOKEN_EXPIRATION', MONTH_IN_SECONDS); 
}

function dw_get_jwt_secret_key() {
    if (defined('DW_JWT_SECRET_KEY') && !empty(DW_JWT_SECRET_KEY)) {
        return DW_JWT_SECRET_KEY;
    }
    if (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
        return AUTH_KEY;
    }
    $stored_key = get_option('dw_generated_jwt_secret');
    if ($stored_key) {
        return $stored_key;
    }
    $new_key = bin2hex(random_bytes(32));
    update_option('dw_generated_jwt_secret', $new_key, false);
    return $new_key;
}


// =============================================================================
// FUNGSI JWT (Encode/Decode)
// =============================================================================

function dw_encode_jwt($payload, $expiration = DW_JWT_ACCESS_TOKEN_EXPIRATION) {
    if ( ! class_exists('\Firebase\JWT\JWT') ) {
        return new WP_Error('jwt_library_missing', 'Library JWT tidak tersedia.');
    }
    $secret_key = dw_get_jwt_secret_key();
    if (!isset($payload['user_id']) || !is_numeric($payload['user_id']) || $payload['user_id'] <= 0) {
         return new WP_Error('jwt_payload_invalid', 'Payload harus berisi user_id.');
    }

    $issued_at = time();
    $expire = $issued_at + $expiration;

    $jwt_payload = [
        'iss' => get_site_url(), 
        'iat' => $issued_at, 'nbf' => $issued_at, 'exp' => $expire,
        'data' => [ 'user_id' => (int) $payload['user_id'] ]
    ];

    try {
        return JWT::encode($jwt_payload, $secret_key, DW_JWT_ALGORITHM);
    } catch (\Exception $e) {
        return new WP_Error('jwt_encode_failed', 'Gagal membuat token: ' . $e->getMessage());
    }
}

function dw_decode_jwt($jwt) {
    if ( ! class_exists('\Firebase\JWT\JWT') ) {
        return new WP_Error('jwt_library_missing', 'Library JWT tidak tersedia.', ['status' => 500]);
    }
    if (empty($jwt)) {
        return new WP_Error('jwt_empty', 'Token JWT tidak ditemukan.', ['status' => 401]);
    }

    $secret_key = dw_get_jwt_secret_key();

    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, DW_JWT_ALGORITHM));

        global $wpdb;
        $revoked_table = $wpdb->prefix . 'dw_revoked_tokens';
        if ($wpdb->get_var("SHOW TABLES LIKE '$revoked_table'") == $revoked_table) {
            $token_hash = hash('sha256', $jwt);
            $is_revoked = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $revoked_table WHERE token_hash = %s AND expires_at > %s",
                $token_hash, current_time('mysql', 1) 
            ));
            if ($is_revoked) {
                return new WP_Error('jwt_revoked', 'Token telah dicabut (logout).', ['status' => 401]);
            }
        }

        if (!isset($decoded->data->user_id) || !is_numeric($decoded->data->user_id) || $decoded->data->user_id <= 0) {
            return new WP_Error('jwt_payload_invalid', 'Payload token tidak valid.', ['status' => 400]);
        }

        return $decoded; 

    } catch (ExpiredException $e) {
        return new WP_Error('jwt_expired', 'Token telah kedaluwarsa.', ['status' => 401]);
    } catch (\Exception $e) { 
        return new WP_Error('jwt_decode_failed', 'Token tidak valid.', ['status' => 401]);
    }
}

// =============================================================================
// FUNGSI HELPER REFRESH TOKEN
// =============================================================================

function dw_create_refresh_token($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_refresh_tokens';
    $wpdb->delete($table_name, ['user_id' => $user_id], ['%d']);
    $token = bin2hex(random_bytes(32)); 
    $expires_at = gmdate('Y-m-d H:i:s', time() + DW_JWT_REFRESH_TOKEN_EXPIRATION); 
    $inserted = $wpdb->insert($table_name,
        ['token' => $token, 'user_id' => $user_id, 'expires_at' => $expires_at, 'created_at' => current_time('mysql', 1)],
        ['%s', '%d', '%s', '%s']
    );
    return $inserted ? $token : false;
}

function dw_validate_refresh_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_refresh_tokens';
    if (empty($token)) return false; 
    $result = $wpdb->get_row($wpdb->prepare("SELECT user_id, expires_at FROM $table_name WHERE token = %s", $token));
    if (!$result) return false; 
    if (strtotime($result->expires_at . ' GMT') < time()) {
        $wpdb->delete($table_name, ['token' => $token], ['%s']);
        return false;
    }
    return absint($result->user_id);
}

function dw_revoke_refresh_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_refresh_tokens';
    if (empty($token)) return true; 
    return $wpdb->delete($table_name, ['token' => $token], ['%s']);
}

function dw_add_token_to_blacklist($jwt, $user_id, $expires_at) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_revoked_tokens';
    if (empty($jwt) || empty($user_id) || empty($expires_at)) return false;
    $token_hash = hash('sha256', $jwt);
    $expires_at_mysql = gmdate('Y-m-d H:i:s', $expires_at); 
    return $wpdb->insert($table_name,
        ['token_hash' => $token_hash, 'user_id' => $user_id, 'revoked_at' => current_time('mysql', 1), 'expires_at' => $expires_at_mysql],
        ['%s', '%d', '%s', '%s']
    );
}


// =============================================================================
// FUNGSI RELASI OTOMATIS & KOMISI (FITUR UPDATE v3.4)
// =============================================================================

/**
 * Mencari Desa Wisata berdasarkan Kelurahan dan menghubungkan Pedagang secara otomatis.
 * Fungsi ini memastikan statistik pedagang tercatat di wilayah wisata tersebut.
 */
function dw_auto_relate_pedagang_to_village($pedagang_id, $kelurahan_id) {
    global $wpdb;
    if (empty($kelurahan_id)) return;

    // Cari Desa Wisata dengan kelurahan ID (dari API Wilayah) yang sama
    $desa_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}dw_desa WHERE api_kelurahan_id = %s LIMIT 1",
        $kelurahan_id
    ));

    if ($desa_id) {
        $wpdb->update("{$wpdb->prefix}dw_pedagang", 
            ['id_desa' => $desa_id, 'is_independent' => 0], 
            ['id' => $pedagang_id]
        );
    } else {
        // Jika tidak ada desa, status menjadi Independen
        $wpdb->update("{$wpdb->prefix}dw_pedagang", 
            ['id_desa' => 0, 'is_independent' => 1], 
            ['id' => $pedagang_id]
        );
    }
}

/**
 * Fungsi untuk menghubungkan pedagang-pedagang independen ke Desa yang baru mendaftar.
 */
function dw_sync_independent_merchants_to_new_village($desa_id, $kelurahan_id) {
    global $wpdb;
    if (empty($kelurahan_id)) return;

    // Update semua pedagang yang independen di kelurahan yang sama
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}dw_pedagang SET id_desa = %d, is_independent = 0 
         WHERE api_kelurahan_id = %s AND id_desa = 0",
        $desa_id, $kelurahan_id
    ));
}

/**
 * Menghitung komisi Desa dari paket transaksi.
 * Desa dapat komisi HANYA JIKA verifikator pedagang adalah 'desa'.
 */
function dw_get_calculated_commission($pedagang_id, $paket_price) {
    global $wpdb;
    
    // Ambil data relasi desa dan siapa yang meng-approve pendaftaran pedagang
    $pedagang = $wpdb->get_row($wpdb->prepare(
        "SELECT id_desa, approved_by FROM {$wpdb->prefix}dw_pedagang WHERE id = %d",
        $pedagang_id
    ));

    // Ketentuan: Desa tidak dapat apa-apa jika tidak ada relasi ATAU admin pusat yang ACC.
    if (!$pedagang || empty($pedagang->id_desa) || $pedagang->approved_by !== 'desa') {
        return 0;
    }

    // Ambil persentase komisi yang diatur di data Desa Wisata tersebut
    $persentase = $wpdb->get_var($wpdb->prepare(
        "SELECT persentase_komisi_penjualan FROM {$wpdb->prefix}dw_desa WHERE id = %d",
        $pedagang->id_desa
    ));

    if (!$persentase || $persentase <= 0) return 0;

    return ($persentase / 100) * $paket_price;
}


// =============================================================================
// FUNGSI HELPER UTILS (Format, Sanitize, dll)
// =============================================================================

if ( ! function_exists( 'dw_format_rupiah' ) ) {
    function dw_format_rupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

function dw_sanitize_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) !== '62') {
        $phone = '62' . $phone;
    }
    return $phone;
}

function dw_json_response($data, $status = 200) {
    status_header($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function dw_get_setting($key, $default = null) {
    static $dw_settings = null;
    if ($dw_settings === null) {
        $dw_settings = get_option('dw_settings', []);
    }
    return isset($dw_settings[$key]) ? $dw_settings[$key] : $default;
}

function dw_get_desa_name_by_id($desa_id) {
    if (empty($desa_id) || !is_numeric($desa_id)) return 'N/A';
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_desa';
    $cache_key = 'dw_desa_name_' . $desa_id;
    $desa_name = wp_cache_get($cache_key, 'desa_wisata_core');
    if (false === $desa_name) {
        $desa_name = $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM $table_name WHERE id = %d", $desa_id));
        $desa_name = $desa_name ? $desa_name : 'N/A';
        wp_cache_set($cache_key, $desa_name, 'desa_wisata_core', HOUR_IN_SECONDS);
    }
    return esc_html($desa_name);
}

function dw_get_order_status_label($status) {
    $labels = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'pembayaran_dikonfirmasi' => 'Pembayaran Dikonfirmasi',
        'pembayaran_gagal' => 'Pembayaran Gagal',
        'refunded' => 'Refunded',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi', 
        'lunas' => 'Lunas',
        'diproses' => 'Diproses Penjual',
        'diantar_ojek' => 'Dikirim (Ojek Lokal)',
        'dikirim_ekspedisi' => 'Dikirim (Ekspedisi)',
        'selesai' => 'Pesanan Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function dw_get_order_status_badge($status) {
    $label = dw_get_order_status_label($status);
    return '<span class="dw-badge ' . sanitize_html_class($status) . '">' . esc_html($label) . '</span>';
}

function dw_get_embed_video_url_helper($url) { 
    if (empty($url)) return null;
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $url, $match)) {
        return "https://www.youtube.com/embed/" . $match[1];
    }
    return null; 
}

// =============================================================================
// FUNGSI ADMIN UI & LOGS
// =============================================================================

if ( ! function_exists( 'dw_get_pending_reviews_count' ) ) {
    function dw_get_pending_reviews_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ulasan';
        $count = wp_cache_get('dw_pending_reviews_count', 'desa_wisata_core');
        if (false === $count) {
            if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return 0;
            $count = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE status_moderasi = 'pending'");
            wp_cache_set('dw_pending_reviews_count', $count, 'desa_wisata_core', MINUTE_IN_SECONDS * 5);
        }
        return $count;
    }
}

if ( ! function_exists( 'dw_get_status_label' ) ) {
    function dw_get_status_label($status) {
        switch ($status) {
            case 'aktif': return '<span class="dw-badge dw-badge-success">Aktif</span>';
            case 'nonaktif': return '<span class="dw-badge dw-badge-danger">Nonaktif</span>';
            case 'pending': return '<span class="dw-badge dw-badge-warning">Menunggu</span>';
            default: return '<span class="dw-badge">' . esc_html($status) . '</span>';
        }
    }
}

if ( ! function_exists( 'dw_add_log' ) ) {
    function dw_add_log($user_id, $activity, $type = 'info') {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'dw_logs';
        if($wpdb->get_var("SHOW TABLES LIKE '$table_logs'") != $table_logs) return;
        $wpdb->insert($table_logs, [
            'user_id' => $user_id, 'activity' => $activity, 'type' => $type, 'created_at' => current_time('mysql')
        ]);
    }
}


// =============================================================================
// FUNGSI UPDATE PESANAN & RESTORE STOK (v3.3)
// =============================================================================

function dw_update_sub_order_status($sub_order_id, $new_status, $notes = '', $resi = null, $ongkir = null, $actor_id = 0) {
    global $wpdb;
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $table_main = $wpdb->prefix . 'dw_transaksi';
    
    if ($actor_id === 0) $actor_id = get_current_user_id();

    $sub_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sub WHERE id = %d", $sub_order_id));
    if (!$sub_order) return new WP_Error('rest_not_found', 'Pesanan tidak ditemukan.');

    $old_status = $sub_order->status_pesanan;
    $data_to_update = [];
    if ($old_status !== $new_status) $data_to_update['status_pesanan'] = $new_status;
    if (!empty($notes)) $data_to_update['catatan_penjual'] = $notes; 
    if ($resi !== null) $data_to_update['no_resi'] = $resi;

    // --- LOGIKA RESTORE STOK v3.3 ---
    $cancelled_statuses = ['dibatalkan', 'refunded', 'pembayaran_gagal'];
    if (in_array($new_status, $cancelled_statuses) && !in_array($old_status, $cancelled_statuses)) {
        dw_restore_sub_order_stock($sub_order_id);
    }

    if (empty($data_to_update)) return true;

    $updated = $wpdb->update($table_sub, $data_to_update, ['id' => $sub_order_id]);
    if ($updated !== false) {
        dw_sync_main_order_totals($sub_order->id_transaksi);
        dw_sync_main_order_status($sub_order->id_transaksi);
    }

    return true; 
}

function dw_restore_sub_order_stock($sub_order_id) {
    global $wpdb;
    $items = $wpdb->get_results($wpdb->prepare("SELECT id_produk, id_variasi, jumlah FROM {$wpdb->prefix}dw_transaksi_items WHERE id_sub_transaksi = %d", $sub_order_id));
    if (empty($items)) return false;

    foreach ($items as $item) {
        $qty = (int) $item->jumlah;
        if ($item->id_variasi > 0) {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}dw_produk_variasi SET stok_variasi = stok_variasi + %d WHERE id = %d", $qty, $item->id_variasi));
        } else {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = meta_value + %d WHERE post_id = %d AND meta_key = '_dw_stok'", $qty, $item->id_produk));
        }
    }
    return true;
}

function dw_sync_main_order_totals($main_order_id) {
    global $wpdb;
    $totals = $wpdb->get_row($wpdb->prepare("SELECT SUM(sub_total) as prod, SUM(ongkir) as ong FROM {$wpdb->prefix}dw_transaksi_sub WHERE id_transaksi = %d", $main_order_id));
    if ($totals) {
        $wpdb->update($wpdb->prefix . 'dw_transaksi', ['total_transaksi' => (float)$totals->prod + (float)$totals->ong], ['id' => $main_order_id]);
    }
}

function dw_sync_main_order_status($main_order_id) {
    global $wpdb;
    $sub_statuses = $wpdb->get_col($wpdb->prepare("SELECT status_pesanan FROM {$wpdb->prefix}dw_transaksi_sub WHERE id_transaksi = %d", $main_order_id));
    if (empty($sub_statuses)) return;

    $total = count($sub_statuses);
    $selesai = count(array_filter($sub_statuses, function($s) { return $s === 'selesai'; }));
    $batal = count(array_filter($sub_statuses, function($s) { return $s === 'dibatalkan'; }));

    $new_status = 'diproses';
    if ($selesai === $total) $new_status = 'selesai';
    elseif ($batal === $total) $new_status = 'dibatalkan';

    $wpdb->update($wpdb->prefix . 'dw_transaksi', ['status_transaksi' => $new_status], ['id' => $main_order_id]);
}

// =============================================================================
// NOTIFIKASI & WISHLIST
// =============================================================================

function dw_send_user_notification($user_id, $subject, $message) { 
    $user = get_userdata($user_id);
    if ($user && !empty($user->user_email)) {
        return wp_mail($user->user_email, wp_specialchars_decode($subject), $message);
    } 
    return false;
}

function dw_is_wishlisted($item_id, $item_type = 'wisata', $user_id = null) {
    if (!$user_id) $user_id = get_current_user_id();
    if (!$user_id) return false;
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_wishlist WHERE user_id = %d AND item_id = %d AND item_type = %s", $user_id, $item_id, $item_type));
}

// =============================================================================
// MONETISASI DESA (FREEMIUM)
// =============================================================================

/**
 * Check if Desa is Premium (Verified/Paid)
 * * @param int $user_id Optional. User ID to check. Defaults to current user.
 * @return boolean
 */
function dw_is_desa_premium($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    // Admin selalu premium
    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    // Cek status paket dari user meta
    // Nilai 'active' diset ketika pembayaran paket terverifikasi
    $status = get_user_meta($user_id, 'dw_paket_status', true);
    
    // Cek juga tanggal kadaluarsa jika ada
    $expiry = get_user_meta($user_id, 'dw_paket_expiry', true);
    
    if ($status === 'active') {
        if (!empty($expiry)) {
            $today = date('Y-m-d');
            if ($today > $expiry) {
                return false; // Paket sudah expired
            }
        }
        return true;
    }

    return false;
}

/**
 * Check if Desa can upload more Wisata
 * Limit: 2 for Free, Unlimited for Premium
 * * @param int $user_id
 * @return boolean
 */
function dw_can_add_wisata($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    // Jika premium, bebas upload
    if (dw_is_desa_premium($user_id)) {
        return true;
    }

    // Hitung jumlah post type 'wisata' milik user ini
    $args = array(
        'author'    => $user_id,
        'post_type' => 'wisata',
        'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
        'fields'    => 'ids',
        'posts_per_page' => -1
    );
    
    $query = new WP_Query($args);
    $count = $query->found_posts;

    // Batas untuk akun gratis adalah 2
    if ($count >= 2) {
        return false;
    }

    return true;
}