<?php
/**
 * File Name:   helpers.php
 * File Folder: includes/
 * File Path:   includes/helpers.php
 *
 * Fungsi bantuan umum untuk plugin Desa Wisata Core.
 * Termasuk fungsi JWT, formatting, dan utilitas lainnya.
 * * --- UPDATE v3.3 (FITUR PENGEMBALIAN STOK) ---
 * 1. Added: dw_restore_sub_order_stock() untuk mengembalikan stok.
 * 2. Modified: dw_update_sub_order_status() memanggil restore stock saat batal.
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
 *
 * @param int $user_id ID User Pedagang
 * @return true|WP_Error True jika kuota aman, WP_Error jika habis.
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

    // Cek jika akun dibekukan KARENA KUOTA HABIS
    if ($pedagang->status_akun === 'nonaktif_habis_kuota' || $pedagang->sisa_transaksi <= 0) {
        
        // Update status jika sisa_transaksi <= 0 tapi status belum terganti
        if ($pedagang->status_akun !== 'nonaktif_habis_kuota') {
             $wpdb->update($wpdb->prefix . 'dw_pedagang', ['status_akun' => 'nonaktif_habis_kuota'], ['id' => $pedagang->id]);
        }
        
        return new WP_Error(
            'rest_kuota_habis',
            'Kuota transaksi Anda telah habis. Akun Anda dibekukan sementara. Harap segera beli paket transaksi baru untuk dapat melanjutkan penjualan.',
            ['status' => 403]
        );
    }
    
    // Cek jika akun dinonaktifkan manual oleh Admin
    if ($pedagang->status_akun === 'nonaktif') {
         return new WP_Error(
            'rest_akun_nonaktif',
            'Akun Anda saat ini dinonaktifkan oleh Administrator.',
            ['status' => 403]
        );
    }

    return $pedagang; 
}

/**
 * Mengurangi kuota transaksi pedagang sebanyak 1.
 *
 * @param int $user_id ID User pedagang.
 * @return bool True jika berhasil update, False jika gagal.
 */
function dw_reduce_pedagang_kuota($user_id) {
    global $wpdb;
    $pedagang_table = $wpdb->prefix . 'dw_pedagang';

    // Cek dulu apakah punya kuota
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

/**
 * Mendapatkan Secret Key JWT dengan aman.
 */
function dw_get_jwt_secret_key() {
    // 1. Cek Konstanta di wp-config.php (Prioritas Utama)
    if (defined('DW_JWT_SECRET_KEY') && !empty(DW_JWT_SECRET_KEY)) {
        return DW_JWT_SECRET_KEY;
    }
    
    // 2. Cek AUTH_KEY bawaan WP (Alternatif Aman)
    if (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
        return AUTH_KEY;
    }

    // 3. Fallback Aman: Auto-generate dan simpan di database
    $stored_key = get_option('dw_generated_jwt_secret');
    
    if ($stored_key) {
        return $stored_key;
    }

    // Generate kunci baru yang kuat
    if (function_exists('openssl_random_pseudo_bytes')) {
        $new_key = bin2hex(openssl_random_pseudo_bytes(32));
    } else {
        $new_key = sha1(uniqid(rand(), true) . wp_generate_password(32, true, true));
    }

    update_option('dw_generated_jwt_secret', $new_key, false); // false = jangan autoload
    
    return $new_key;
}


// =============================================================================
// FUNGSI JWT (Encode/Decode)
// =============================================================================

function dw_encode_jwt($payload, $expiration = DW_JWT_ACCESS_TOKEN_EXPIRATION) {
     if ( ! class_exists('\Firebase\JWT\JWT') ) {
        return new WP_Error('jwt_library_missing', __('Library JWT tidak tersedia.', 'desa-wisata-core'));
    }
    
    $secret_key = dw_get_jwt_secret_key();
    
    if (!isset($payload['user_id']) || !is_numeric($payload['user_id']) || $payload['user_id'] <= 0) {
         return new WP_Error('jwt_payload_invalid', __('Payload harus berisi user_id yang valid.', 'desa-wisata-core'));
    }

    $issued_at = time();
    $expire = $issued_at + $expiration;

    $jwt_payload = [
        'iss' => get_site_url(), 
        'iat' => $issued_at,     
        'nbf' => $issued_at,     
        'exp' => $expire,        
        'data' => [             
            'user_id' => (int) $payload['user_id'],
        ]
    ];

    try {
        $jwt = JWT::encode($jwt_payload, $secret_key, DW_JWT_ALGORITHM);
        return $jwt;
    } catch (\Exception $e) {
        error_log('JWT Encode Error: ' . $e->getMessage());
        return new WP_Error('jwt_encode_failed', __('Gagal membuat token JWT:', 'desa-wisata-core') . ' ' . $e->getMessage());
    }
}

function dw_decode_jwt($jwt) {
     if ( ! class_exists('\Firebase\JWT\JWT') ) {
        return new WP_Error('jwt_library_missing', __('Library JWT tidak tersedia.', 'desa-wisata-core'), ['status' => 500]);
    }
    if (empty($jwt)) {
        return new WP_Error('jwt_empty', __('Token JWT tidak ditemukan.', 'desa-wisata-core'), ['status' => 401]);
    }

    $secret_key = dw_get_jwt_secret_key();

    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, DW_JWT_ALGORITHM));

        // Cek Blacklist (Logout)
        global $wpdb;
        $revoked_table = $wpdb->prefix . 'dw_revoked_tokens';
        if ($wpdb->get_var("SHOW TABLES LIKE '$revoked_table'") == $revoked_table) {
            $token_hash = hash('sha256', $jwt);
            $is_revoked = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $revoked_table WHERE token_hash = %s AND expires_at > %s",
                $token_hash,
                current_time('mysql', 1) 
            ));
            if ($is_revoked) {
                return new WP_Error('jwt_revoked', __('Token JWT telah dicabut (logout).', 'desa-wisata-core'), ['status' => 401]);
            }
        }

        if (!isset($decoded->data->user_id) || !is_numeric($decoded->data->user_id) || $decoded->data->user_id <= 0) {
            return new WP_Error('jwt_payload_invalid', __('Payload token tidak valid.', 'desa-wisata-core'), ['status' => 400]);
        }

        return $decoded; 

    } catch (ExpiredException $e) {
        return new WP_Error('jwt_expired', __('Token JWT telah kedaluwarsa.', 'desa-wisata-core'), ['status' => 401]);
    } catch (SignatureInvalidException $e) {
         return new WP_Error('jwt_signature_invalid', __('Tanda tangan token JWT tidak valid.', 'desa-wisata-core'), ['status' => 403]);
    } catch (BeforeValidException $e) { 
         return new WP_Error('jwt_not_yet_valid', __('Token JWT belum valid.', 'desa-wisata-core'), ['status' => 401]);
    } catch (\Exception $e) { 
        error_log("JWT Decode Failed: " . $e->getMessage()); 
        return new WP_Error('jwt_decode_failed', __('Token JWT tidak valid.', 'desa-wisata-core'), ['status' => 401]);
    }
}

// =============================================================================
// FUNGSI HELPER REFRESH TOKEN
// =============================================================================
function dw_create_refresh_token($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_refresh_tokens';
    
    // Hapus token lama user ini agar single session (opsional)
    $wpdb->delete($table_name, ['user_id' => $user_id], ['%d']);
    
    try {
        $token = bin2hex(random_bytes(32)); 
    } catch (\Exception $e) {
        return false; 
    }
    
    $expires_at = gmdate('Y-m-d H:i:s', time() + DW_JWT_REFRESH_TOKEN_EXPIRATION); 
    
    $inserted = $wpdb->insert(
        $table_name,
        ['token' => $token, 'user_id' => $user_id, 'expires_at' => $expires_at, 'created_at' => current_time('mysql', 1)],
        ['%s', '%d', '%s', '%s']
    );
    
    return $inserted ? $token : false;
}

function dw_validate_refresh_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_refresh_tokens';
    
    if (empty($token)) return false; 
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id, expires_at FROM $table_name WHERE token = %s",
        $token
    ));
    
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
    
    return $wpdb->insert(
        $table_name,
        ['token_hash' => $token_hash, 'user_id' => $user_id, 'revoked_at' => current_time('mysql', 1), 'expires_at' => $expires_at_mysql],
        ['%s', '%d', '%s', '%s']
    );
}


// =============================================================================
// FUNGSI HELPER UTILS (Format, Sanitize, dll)
// =============================================================================

/**
 * Format Rupiah (Safe Declaration)
 * Mencegah error "Cannot redeclare" jika fungsi sudah ada di theme functions.php
 */
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
    $desa_id = absint($desa_id);
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
        'menunggu_pembayaran'   => __('Menunggu Pembayaran', 'desa-wisata-core'),
        'pembayaran_dikonfirmasi' => __('Pembayaran Dikonfirmasi', 'desa-wisata-core'),
        'pembayaran_gagal'      => __('Pembayaran Gagal', 'desa-wisata-core'),
        'refunded'              => __('Refunded', 'desa-wisata-core'),
        'menunggu_konfirmasi'   => __('Menunggu Konfirmasi', 'desa-wisata-core'), 
        'lunas'                 => __('Lunas', 'desa-wisata-core'),
        'diproses'              => __('Diproses Penjual', 'desa-wisata-core'),
        'diantar_ojek'          => __('Dikirim (Ojek Lokal)', 'desa-wisata-core'),
        'dikirim_ekspedisi'     => __('Dikirim (Ekspedisi)', 'desa-wisata-core'),
        'selesai'               => __('Pesanan Selesai', 'desa-wisata-core'),
        'dibatalkan'            => __('Dibatalkan', 'desa-wisata-core'),
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function dw_get_order_status_badge($status) {
    $label = dw_get_order_status_label($status);
    $status_class = sanitize_html_class($status); 
    return '<span class="dw-badge ' . $status_class . '">' . esc_html($label) . '</span>';
}

function dw_get_embed_video_url_helper($url) { 
    if (empty($url)) return null;
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $url, $match)) {
        return "https://www.youtube.com/embed/" . $match[1];
    }
    if (preg_match('/vimeo\.com\/(\d+)/i', $url, $match)) {
        return "https://player.vimeo.com/video/" . $match[1];
    }
    return null; 
}

// =============================================================================
// FUNGSI TAMBAHAN (ADMIN UI & LOGS)
// =============================================================================

/**
 * Get Pending Reviews Count
 * Menghitung jumlah ulasan yang statusnya pending.
 * Dilengkapi pengecekan tabel untuk menghindari error jika tabel belum dibuat.
 */
function dw_get_pending_reviews_count() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_ulasan';
    
    // Cek apakah tabel ada sebelum query untuk mencegah error database
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return 0; // Return 0 jika tabel tidak ada
    }

    // Ambil jumlah ulasan pending
    return (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE status_moderasi = 'pending'");
}

/**
 * Helper untuk mendapatkan status label (Desa/Produk)
 * Memberikan badge visual HTML berdasarkan status.
 * (Safe Declaration)
 */
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

/**
 * Helper log aktivitas (Sederhana)
 * Mencatat aktivitas user ke database.
 * Dilengkapi pengecekan tabel log dan function_exists check.
 */
if ( ! function_exists( 'dw_add_log' ) ) {
    function dw_add_log($user_id, $activity, $type = 'info') {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'dw_logs';
        
        // Cek tabel logs sebelum insert
        if($wpdb->get_var("SHOW TABLES LIKE '$table_logs'") != $table_logs) return;

        $wpdb->insert($table_logs, [
            'user_id' => $user_id,
            'activity' => $activity,
            'type' => $type,
            'created_at' => current_time('mysql')
        ]);
    }
}


// =============================================================================
// FUNGSI HELPER UPDATE PESANAN (CORE LOGIC)
// =============================================================================

function dw_update_sub_order_status($sub_order_id, $new_status, $notes = '', $nomor_resi = null, $ongkir_final = null, $actor_id = 0) {
    global $wpdb;
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $table_main = $wpdb->prefix . 'dw_transaksi';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    
    if ($actor_id === 0) {
        $actor_id = get_current_user_id();
    }

    $sub_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_sub WHERE id = %d", $sub_order_id));
    if (!$sub_order) {
        return new WP_Error('rest_not_found', __('Sub-Pesanan tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }

    $old_status = $sub_order->status_pesanan;
    $id_pedagang_user = $sub_order->id_pedagang; 
    $main_order_id = $sub_order->id_transaksi;

    $main_order_data = $wpdb->get_row($wpdb->prepare("SELECT kode_unik, id_pembeli FROM $table_main WHERE id = %d", $main_order_id));
    $id_pembeli = $main_order_data->id_pembeli ?? 0;
    $kode_unik = $main_order_data->kode_unik ?? $main_order_id;

    $data_to_update = [];
    if ($old_status !== $new_status) {
        $data_to_update['status_pesanan'] = $new_status;
    }
    
    $log_message_extra = '';

    if (!empty($notes)) $data_to_update['catatan_penjual'] = $notes; 
    if ($nomor_resi !== null) $data_to_update['no_resi'] = $nomor_resi;
    
    if ($ongkir_final !== null) {
        $data_to_update['ongkir'] = $ongkir_final;
        $data_to_update['total_pesanan_toko'] = (float) $sub_order->sub_total + (float) $ongkir_final;
        $log_message_extra .= " Ongkir final di-set ke " . dw_format_rupiah($ongkir_final);
    }

    if (empty($data_to_update)) return true;

    // --- [BARU] LOGIKA PENGEMBALIAN STOK (RESTOCK) ---
    // Jika pesanan DIBATALKAN atau REFUNDED atau GAGAL BAYAR, kembalikan stok.
    // Tapi hanya jika status sebelumnya BUKAN status gagal tersebut (agar tidak double restore).
    $cancelled_statuses = ['dibatalkan', 'refunded', 'pembayaran_gagal'];
    if (in_array($new_status, $cancelled_statuses) && !in_array($old_status, $cancelled_statuses)) {
        
        $restock_success = dw_restore_sub_order_stock($sub_order_id);
        
        if ($restock_success) {
            $log_message_extra .= " [Stok Produk Dikembalikan]";
        } else {
            $log_message_extra .= " [PERINGATAN: Gagal mengembalikan stok]";
        }
    }
    // -------------------------------------------------

    // --- LOGIKA PENGURANGAN KUOTA PEDAGANG ---
    // Hanya kurangi kuota saat status berubah jadi 'lunas' atau 'selesai' pertama kali
    $is_quota_transition = !in_array($old_status, ['lunas', 'selesai', 'dibatalkan']) && in_array($new_status, ['lunas', 'selesai']);

    if ($is_quota_transition) {
        $pedagang_check = dw_check_pedagang_kuota($id_pedagang_user); 
        
        if (is_wp_error($pedagang_check)) {
             return $pedagang_check; // Kembalikan error Kuota Habis
        }

        // Kuota aman, kurangi 1
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_pedagang SET sisa_transaksi = sisa_transaksi - 1 WHERE id = %d",
            $pedagang_check->id 
        ));
        $log_message_extra .= " Kuota pedagang dikurangi 1.";
    }

    // Update DB
    $updated = $wpdb->update($table_sub, $data_to_update, ['id' => $sub_order_id]);

    if ($updated === false) {
        return new WP_Error('db_update_failed', 'Gagal memperbarui database pesanan.', ['status' => 500]);
    }

    // Sync Total & Status Utama
    dw_sync_main_order_totals($main_order_id);
    dw_sync_main_order_status($main_order_id);

    // Kirim Log & Notifikasi
    if ($old_status !== $new_status) {
        $log_message = "Status Sub-Pesanan #{$sub_order_id} ({$kode_unik}) diubah: '{$old_status}' -> '{$new_status}'." . $log_message_extra;
        
        if (function_exists('dw_log_activity')) {
            dw_log_activity('SUB_ORDER_STATUS_UPDATED', $log_message, $actor_id);
        }

        // Kirim Notifikasi ke Pembeli
        $notif_subject = "Update Pesanan Anda #" . $kode_unik;
        $notif_message = "Status pesanan Anda #{$kode_unik} (dari Toko {$sub_order->nama_toko}) telah diperbarui menjadi: *" . dw_get_order_status_label($new_status) . "*.";
        if ($new_status === 'dikirim_ekspedisi' && $nomor_resi) {
            $notif_message .= "\n\nNomor Resi: " . $nomor_resi;
        }
        if ($notes) {
            $notif_message .= "\n\nCatatan dari Penjual: " . $notes;
        }
        
        if ($id_pembeli > 0) {
            dw_send_user_notification($id_pembeli, $notif_subject, $notif_message);
        }

        do_action('dw_order_status_changed', $sub_order_id, $new_status, $old_status, $id_pedagang_user);
    }

    return true; 
}

function dw_sync_main_order_totals($main_order_id) {
    global $wpdb;
    $totals = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(sub_total) as total_produk, SUM(ongkir) as total_ongkir 
         FROM {$wpdb->prefix}dw_transaksi_sub 
         WHERE id_transaksi = %d",
        $main_order_id
    ));

    if ($totals) {
        $total_transaksi = (float) $totals->total_produk + (float) $totals->total_ongkir;
        $wpdb->update(
            $wpdb->prefix . 'dw_transaksi',
            [
                'total_produk' => (float) $totals->total_produk,
                'total_ongkir' => (float) $totals->total_ongkir,
                'total_transaksi' => $total_transaksi,
            ],
            ['id' => $main_order_id]
        );
    }
}

function dw_sync_main_order_status($main_order_id) {
    global $wpdb;
    $table_main = $wpdb->prefix . 'dw_transaksi';
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    
    $main_status = $wpdb->get_var($wpdb->prepare("SELECT status_transaksi FROM $table_main WHERE id = %d", $main_order_id));
    if (in_array($main_status, ['selesai', 'dibatalkan'])) {
        return;
    }

    $sub_statuses = $wpdb->get_col($wpdb->prepare("SELECT status_pesanan FROM $table_sub WHERE id_transaksi = %d", $main_order_id));

    if (empty($sub_statuses)) return;

    $new_main_status = $main_status;

    // Logika Sinkronisasi Status
    $count_selesai = count(array_filter($sub_statuses, function($s) { return $s === 'selesai'; }));
    $count_batal = count(array_filter($sub_statuses, function($s) { return $s === 'dibatalkan'; }));
    $total_sub = count($sub_statuses);

    if ($count_selesai === $total_sub) {
        $new_main_status = 'selesai';
    } elseif ($count_batal === $total_sub) {
         $new_main_status = 'dibatalkan';
    } elseif (count(array_filter($sub_statuses, function($s) { return in_array($s, ['diproses', 'diantar_ojek', 'dikirim_ekspedisi']); })) > 0) {
        $new_main_status = 'diproses';
    }
    
    if ($new_main_status !== $main_status) {
        $wpdb->update($table_main, ['status_transaksi' => $new_main_status], ['id' => $main_order_id]);
    }
}

// =============================================================================
// FUNGSI NOTIFIKASI
// =============================================================================
function dw_send_user_notification($user_id, $subject, $message) { 
    $user_info = get_userdata($user_id);
    if ($user_info && !empty($user_info->user_email)) {
        $full_message = sprintf( __("Halo %s,", 'desa-wisata-core'), $user_info->display_name ) . "\n\n"
                      . $message . "\n\n"
                      . sprintf( __("Terima kasih,\nSistem %s", 'desa-wisata-core'), get_bloginfo('name') );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $sent = wp_mail($user_info->user_email, wp_specialchars_decode($subject), $full_message, $headers); 
        return $sent;
    } 
    return false;
}
function dw_send_pedagang_notification($user_id, $subject, $message) {
    return dw_send_user_notification($user_id, $subject, $message);
}

// =============================================================================
// FUNGSI HELPER BARU: RESTOCK (Update v3.3)
// =============================================================================

/**
 * Mengembalikan stok produk saat pesanan dibatalkan/refund.
 *
 * @param int $sub_order_id ID Sub Transaksi
 * @return bool True jika berhasil, False jika gagal.
 */
function dw_restore_sub_order_stock($sub_order_id) {
    global $wpdb;
    $table_items = $wpdb->prefix . 'dw_transaksi_items';
    $table_variasi = $wpdb->prefix . 'dw_produk_variasi';
    $table_postmeta = $wpdb->postmeta;

    // Ambil item dari pesanan ini
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT id_produk, id_variasi, jumlah FROM $table_items WHERE id_sub_transaksi = %d",
        $sub_order_id
    ));

    if (empty($items)) {
        return false;
    }

    foreach ($items as $item) {
        $qty = (int) $item->jumlah;
        $product_id = (int) $item->id_produk;
        $variation_id = (int) $item->id_variasi;

        if ($qty <= 0) continue;

        if ($variation_id > 0) {
            // A. Kembalikan stok VARIASI
            // Query: UPDATE dw_produk_variasi SET stok_variasi = stok_variasi + qty
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_variasi SET stok_variasi = stok_variasi + %d WHERE id = %d",
                $qty, $variation_id
            ));
        } else {
            // B. Kembalikan stok PRODUK UTAMA (Simple Product)
            // Menggunakan SQL langsung ke postmeta agar atomik dan cepat
            // Query: UPDATE wp_postmeta SET meta_value = meta_value + qty WHERE ...
            
            // Cek dulu apakah key _dw_stok ada dan berupa angka
            $current_val = get_post_meta($product_id, '_dw_stok', true);
            
            if ($current_val !== '' && is_numeric($current_val)) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_postmeta SET meta_value = meta_value + %d 
                     WHERE post_id = %d AND meta_key = '_dw_stok'",
                    $qty, $product_id
                ));
            }
        }
    }

    return true;
}
?>