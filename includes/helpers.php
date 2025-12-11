<?php
/**
 * File Name:   helpers.php
 * File Folder: includes/
 * File Path:   includes/helpers.php
 *
 * --- PERBAIKAN (CODE CLEANUP v3.2.6) ---
 * - `dw_update_sub_order_status`: Menghapus komentar TODO yang tidak diperlukan
 * karena fungsi sinkronisasi total order utama (`dw_sync_main_order_totals`)
 * sudah diimplementasikan dan dipanggil dengan benar.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// HELPER INTERNAL: PEMBEKUAN KUOTA (DIPINDAHKAN DARI API-PEDAGANG.PHP)
// =============================================================================

/**
 * Helper untuk memeriksa sisa kuota transaksi pedagang.
 * Ini adalah "kunci" pembekuan otomatis.
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
            ['status' => 403] // 403 Forbidden
        );
    }
    
    // Cek jika akun dinonaktifkan manual oleh Admin (bukan karena kuota)
    if ($pedagang->status_akun === 'nonaktif') {
         return new WP_Error(
            'rest_akun_nonaktif',
            'Akun Anda saat ini dinonaktifkan oleh Administrator.',
            ['status' => 403] // 403 Forbidden
        );
    }

    return $pedagang; // Kembalikan data pedagang jika sukses
}


// =============================================================================
// KONSTANTA JWT
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
 * Mendapatkan Secret Key JWT dari konstanta, wp-config.php, atau fallback.
 * @return string|WP_Error Secret key JWT atau WP_Error jika gagal.
 */
function dw_get_jwt_secret_key() {
    static $secret_key = null; 
    if ($secret_key !== null) {
        return $secret_key;
    }
    if (defined('DW_JWT_SECRET_KEY') && !empty(DW_JWT_SECRET_KEY)) {
        $secret_key = DW_JWT_SECRET_KEY;
    }
    elseif (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
        $secret_key = AUTH_KEY;
    }
    else {
        // Cek jika ini environment produksi
        if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') {
            // JANGAN gunakan kunci fallback di produksi. Hentikan.
            error_log('!!! FATAL SECURITY RISK: JWT Fallback key used in production. Halting. Define DW_JWT_SECRET_KEY in wp-config.php. !!!');
            // Mengembalikan WP_Error akan menghentikan endpoint API yang memanggilnya.
            return new WP_Error('jwt_production_unsafe', 'Konfigurasi keamanan server tidak lengkap. Kunci JWT tidak diatur.', ['status' => 503]); // 503 Service Unavailable
        }

        $secret_key = 'dw-headless-default-auth-key-0123456789abcdefghijklmnopqrstuvwxyza-secure-token'; 
        if (is_admin() && current_user_can('manage_options') && !wp_doing_ajax()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>Risiko Keamanan Tinggi:</strong> Kunci JWT default sedang digunakan. Harap segera definisikan konstanta <code>DW_JWT_SECRET_KEY</code> atau <code>AUTH_KEY</code> di file <code>wp-config.php</code> Anda dengan string acak yang kuat dan unik.</p></div>';
            });
        }
    }
    return $secret_key;
}


// =============================================================================
// FUNGSI JWT (Menggunakan firebase/php-jwt)
// =============================================================================
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

/**
 * Membuat token JWT dari payload menggunakan firebase/php-jwt.
 *
 * @param array $payload Data yang akan dienkode (harus berisi 'user_id').
 * @param int $expiration Durasi token dalam detik (default dari konstanta).
 * @return string|WP_Error Token JWT atau WP_Error jika gagal.
 */
function dw_encode_jwt($payload, $expiration = DW_JWT_ACCESS_TOKEN_EXPIRATION) {
     if ( ! class_exists('\Firebase\JWT\JWT') ) {
        return new WP_Error('jwt_library_missing', __('Library JWT tidak tersedia.', 'desa-wisata-core'));
    }
    $secret_key = dw_get_jwt_secret_key();
    if (empty($secret_key)) {
        return new WP_Error('jwt_secret_missing', __('Kunci rahasia JWT tidak dikonfigurasi.', 'desa-wisata-core'));
    }
    if (is_wp_error($secret_key)) {
        return $secret_key; // Kembalikan error (misal: 'jwt_production_unsafe')
    }
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

/**
 * Memvalidasi dan mendecode token JWT menggunakan firebase/php-jwt.
 * Juga memeriksa blacklist token.
 *
 * @param string $jwt Token JWT.
 * @return object|WP_Error Payload token (sebagai objek stdClass) atau WP_Error jika tidak valid.
 */
function dw_decode_jwt($jwt) {
     if ( ! class_exists('\Firebase\JWT\JWT') ) {
        return new WP_Error('jwt_library_missing', __('Library JWT tidak tersedia.', 'desa-wisata-core'), ['status' => 500]);
    }
    if (empty($jwt)) {
        return new WP_Error('jwt_empty', __('Token JWT tidak ditemukan.', 'desa-wisata-core'), ['status' => 401]);
    }

    $secret_key = dw_get_jwt_secret_key();
    if (empty($secret_key)) {
         return new WP_Error('jwt_secret_missing', __('Kunci rahasia JWT tidak dikonfigurasi.', 'desa-wisata-core'), ['status' => 500]);
    }
    if (is_wp_error($secret_key)) {
        return $secret_key; // Kembalikan error (misal: 'jwt_production_unsafe')
    }

    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, DW_JWT_ALGORITHM));

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
            return new WP_Error('jwt_payload_invalid', __('Payload token tidak valid (data->user_id).', 'desa-wisata-core'), ['status' => 400]);
        }

        return $decoded; 

    } catch (ExpiredException $e) {
        return new WP_Error('jwt_expired', __('Token JWT telah kedaluwarsa.', 'desa-wisata-core'), ['status' => 401]);
    } catch (SignatureInvalidException $e) {
         return new WP_Error('jwt_signature_invalid', __('Tanda tangan token JWT tidak valid.', 'desa-wisata-core'), ['status' => 403]);
    } catch (BeforeValidException $e) { 
         return new WP_Error('jwt_not_yet_valid', __('Token JWT belum valid untuk digunakan.', 'desa-wisata-core'), ['status' => 401]);
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
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        error_log("Tabel $table_name tidak ditemukan saat membuat refresh token.");
        return false;
     }
    $wpdb->delete($table_name, ['user_id' => $user_id], ['%d']);
    try {
        $token = bin2hex(random_bytes(32)); 
    } catch (\Exception $e) {
        error_log("Gagal generate random bytes untuk refresh token: " . $e->getMessage());
        return false; 
    }
    $expires_at = gmdate('Y-m-d H:i:s', time() + DW_JWT_REFRESH_TOKEN_EXPIRATION); 
    $inserted = $wpdb->insert(
        $table_name,
        ['token' => $token, 'user_id' => $user_id, 'expires_at' => $expires_at, 'created_at' => current_time('mysql', 1)],
        ['%s', '%d', '%s', '%s']
    );
    if ($inserted === false) {
        error_log("Gagal insert refresh token untuk user #{$user_id}: " . $wpdb->last_error);
        return false;
    }
    return $token;
}
function dw_validate_refresh_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_refresh_tokens';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        error_log("Tabel $table_name tidak ditemukan saat validasi refresh token.");
        return false;
    }
    if (empty($token)) return false; 
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id, expires_at FROM $table_name WHERE token = %s",
        $token
    ));
    if (!$result) {
        return false; 
    }
    if (strtotime($result->expires_at . ' GMT') < time()) {
        $wpdb->delete($table_name, ['token' => $token], ['%s']);
        return false;
    }
    return absint($result->user_id);
}
function dw_revoke_refresh_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_refresh_tokens';
     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        error_log("Tabel $table_name tidak ditemukan saat mencabut refresh token.");
        return false;
     }
     if (empty($token)) return true; 
    $deleted = $wpdb->delete($table_name, ['token' => $token], ['%s']);
    if ($deleted === false) {
         error_log("Gagal menghapus refresh token: " . $wpdb->last_error);
         return false;
    }
    return true; 
}
function dw_add_token_to_blacklist($jwt, $user_id, $expires_at) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_revoked_tokens';
     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        error_log("Tabel $table_name tidak ditemukan saat menambahkan token ke blacklist.");
        return false;
     }
     if (empty($jwt) || empty($user_id) || empty($expires_at)) return false;
    $token_hash = hash('sha256', $jwt);
    $expires_at_mysql = gmdate('Y-m-d H:i:s', $expires_at); 
    $inserted = $wpdb->insert(
        $table_name,
        ['token_hash' => $token_hash, 'user_id' => $user_id, 'revoked_at' => current_time('mysql', 1), 'expires_at' => $expires_at_mysql],
        ['%s', '%d', '%s', '%s']
    );
    if ($inserted === false) {
         error_log("Gagal menambahkan token ke blacklist untuk user #{$user_id}: " . $wpdb->last_error);
         return false;
    }
    return true;
}


// =============================================================================
// FUNGSI HELPER UMUM & CACHING
// =============================================================================

/**
 * Fungsi helper untuk mengambil satu nilai pengaturan dari 'dw_settings'.
 *
 * @param string $key Kunci pengaturan yang ingin diambil (misal: 'gmaps_api_key').
 * @param mixed $default Nilai default jika kunci tidak ditemukan.
 * @return mixed Nilai pengaturan.
 */
function dw_get_setting($key, $default = null) {
    static $dw_settings = null;
    
    // Muat pengaturan dari database sekali saja
    if ($dw_settings === null) {
        $dw_settings = get_option('dw_settings', []);
    }
    
    // Kembalikan nilai jika ada, atau kembalikan default
    return isset($dw_settings[$key]) ? $dw_settings[$key] : $default;
}

function dw_get_desa_name_by_id($desa_id) {
    if (empty($desa_id) || !is_numeric($desa_id)) return 'N/A';
    $desa_id = absint($desa_id);
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_desa';
     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        error_log("Tabel $table_name tidak ditemukan saat mengambil nama desa.");
        return 'N/A';
     }
    $cache_key = 'dw_desa_name_' . $desa_id;
    $cache_group = 'desa_wisata_core'; 
    $desa_name = wp_cache_get($cache_key, $cache_group);
    if (false === $desa_name) {
        $desa_name_from_db = $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM $table_name WHERE id = %d", $desa_id));
        $desa_name = $desa_name_from_db ? $desa_name_from_db : 'N/A';
        wp_cache_set($cache_key, $desa_name, $cache_group, HOUR_IN_SECONDS);
    }
    return ($desa_name !== 'N/A') ? esc_html($desa_name) : 'N/A';
}

/**
 * Mendapatkan label status pesanan yang ramah pengguna.
 * @param string $status Status internal pesanan.
 * @return string Label status yang mudah dibaca.
 */
function dw_get_order_status_label($status) {
    $labels = [
        // Status Transaksi Utama (dw_transaksi)
        'menunggu_pembayaran'   => __('Menunggu Pembayaran', 'desa-wisata-core'),
        'pembayaran_dikonfirmasi' => __('Pembayaran Dikonfirmasi', 'desa-wisata-core'),
        'pembayaran_gagal'    => __('Pembayaran Gagal', 'desa-wisata-core'),
        'refunded'            => __('Refunded', 'desa-wisata-core'),
        
        // Status Pesanan Pedagang (dw_transaksi_sub)
        'menunggu_konfirmasi' => __('Menunggu Konfirmasi', 'desa-wisata-core'), 
        'lunas'               => __('Pembayaran Lunas', 'desa-wisata-core'), // Alias untuk 'pembayaran_dikonfirmasi'
        'diproses'            => __('Diproses Penjual', 'desa-wisata-core'),
        'diantar_ojek'        => __('Dikirim (Ojek Lokal)', 'desa-wisata-core'),
        'dikirim_ekspedisi'   => __('Dikirim (Ekspedisi)', 'desa-wisata-core'),
        'selesai'             => __('Pesanan Selesai', 'desa-wisata-core'),
        'dibatalkan'          => __('Dibatalkan', 'desa-wisata-core'),
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
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

        $log_status = $sent ? 'berhasil' : 'GAGAL';
        $log_message = sprintf( __("Notifikasi '%s' %s dikirim ke User #%d (%s).", 'desa-wisata-core'), $subject, $log_status, $user_id, $user_info->user_email );
        if (function_exists('dw_log_activity')) {
             dw_log_activity('NOTIFICATION_SENT', $log_message, 0); 
        }
        if (!$sent) {
             error_log("[DW Plugin] Gagal mengirim email notifikasi '{$subject}' ke {$user_info->user_email} (User #{$user_id}). Periksa konfigurasi SMTP WordPress.");
        }
        return $sent;
    } else {
         $error_message = sprintf( __("Gagal mengirim notifikasi '%s' ke User #%d: Email tidak valid atau user tidak ditemukan.", 'desa-wisata-core'), $subject, $user_id );
         error_log("[DW Plugin] " . $error_message);
         if (function_exists('dw_log_activity')) { 
              dw_log_activity('NOTIFICATION_FAILED', $error_message, 0);
         }
         return false;
    }
}
function dw_send_pedagang_notification($user_id, $subject, $message) {
    return dw_send_user_notification($user_id, $subject, $message);
}


// =============================================================================
// [MODIFIKASI] FUNGSI HELPER UPDATE PESANAN
// =============================================================================

/**
 * --- PERBAIKAN: Fungsi ini sekarang memperbarui SUB-ORDER (dw_transaksi_sub) ---
 * Fungsi terpusat untuk memperbarui status pesanan.
 * Mengurangi kuota jika perlu, mencatat log, dan mengirim notifikasi.
 *
 * @param int $sub_order_id ID Pesanan dari tabel `dw_transaksi_sub`.
 * @param string $new_status Status baru.
 * @param string $notes Catatan (opsional).
 * @param string|null $nomor_resi Nomor resi (opsional).
 * @param float|null $ongkir_final Ongkir final (opsional, untuk 'dikirim_ekspedisi').
 * @param int|null $actor_id User ID yang melakukan aksi (opsional, default 0 = Sistem).
 * @return bool|WP_Error True jika berhasil update, WP_Error jika gagal.
 */
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
        error_log("[DW_ERROR] Gagal update status: Sub-Pesanan #{$sub_order_id} tidak ditemukan.");
        return new WP_Error('rest_not_found', __('Sub-Pesanan tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }

    $old_status = $sub_order->status_pesanan;
    $id_pedagang_user = $sub_order->id_pedagang; // Ini adalah User ID pedagang
    $main_order_id = $sub_order->id_transaksi;

    // Ambil data order utama untuk log dan notifikasi
    $main_order_data = $wpdb->get_row($wpdb->prepare("SELECT kode_unik, id_pembeli FROM $table_main WHERE id = %d", $main_order_id));
    $id_pembeli = $main_order_data->id_pembeli ?? 0;
    $kode_unik = $main_order_data->kode_unik ?? $main_order_id;


    // Jika status tidak berubah, jangan lakukan apa-apa (kecuali update resi/ongkir)
    $data_to_update = [];
    if ($old_status !== $new_status) {
        $data_to_update['status_pesanan'] = $new_status;
    }
    
    $log_message_extra = '';

    // Catatan Pedagang disimpan di `dw_transaksi_sub`
    if (!empty($notes)) {
        $data_to_update['catatan_penjual'] = $notes; 
    }
    if ($nomor_resi !== null) {
        $data_to_update['no_resi'] = $nomor_resi;
    }
    
    // Jika ongkir final diisi, update di sub-order
    if ($ongkir_final !== null) {
        $data_to_update['ongkir'] = $ongkir_final; // Update ongkir di sub-order
        // Hitung ulang total di sub-order
        $data_to_update['total_pesanan_toko'] = (float) $sub_order->sub_total + (float) $ongkir_final;
        $log_message_extra .= " Ongkir final di-set ke Rp " . number_format($ongkir_final) . ". Total Sub-Order: Rp " . number_format($data_to_update['total_pesanan_toko']) . ".";
        
        // --- PERBAIKAN CLEANUP v3.2.6: Komentar TODO dihapus ---
        // Komentar TODO tidak lagi diperlukan karena fungsi di bawah ini sudah melakukannya.
        dw_sync_main_order_totals($main_order_id);
    }

    // Jika tidak ada yang diupdate, keluar
    if (empty($data_to_update)) {
        return true;
    }

    // --- LOGIKA PENGURANGAN KUOTA ---
    $is_quota_transition = !in_array($old_status, ['lunas', 'selesai', 'dibatalkan']) && in_array($new_status, ['lunas', 'selesai']);

    if ($is_quota_transition) {
        // Cek kuota (fungsi ini sudah ada di file ini)
        $pedagang = dw_check_pedagang_kuota($id_pedagang_user); 
        
        if (is_wp_error($pedagang)) {
             error_log("[DW_ERROR] Gagal update status sub-pesanan #{$sub_order_id} ke '{$new_status}': " . $pedagang->get_error_message());
             return $pedagang; // Kembalikan error 403 (Kuota Habis)
        }

        // Kuota aman, kurangi 1
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_pedagang SET sisa_transaksi = sisa_transaksi - 1 WHERE id = %d",
            $pedagang->id 
        ));
        $log_message_extra .= " Kuota pedagang dikurangi 1.";
    }
    // --- AKHIR LOGIKA KUOTA ---

    // Update database sub-transaksi
    $updated = $wpdb->update($table_sub, $data_to_update, ['id' => $sub_order_id]);

    if ($updated === false) {
         error_log("[DW_ERROR] Gagal update DB untuk sub-pesanan #{$sub_order_id}: " . $wpdb->last_error);
        return new WP_Error('db_update_failed', 'Gagal memperbarui database pesanan.', ['status' => 500]);
    }

    // Panggil helper sinkronisasi total (lagi, jika ongkir tidak diubah tapi status berubah)
    dw_sync_main_order_totals($main_order_id);
    // Panggil helper sinkronisasi status utama
    dw_sync_main_order_status($main_order_id);

    // Kirim Log (hanya jika status berubah)
    if ($old_status !== $new_status) {
        $log_message = "Status Sub-Pesanan #{$sub_order_id} (Order: {$kode_unik}) diubah dari '{$old_status}' menjadi '{$new_status}' oleh User #{$actor_id}." . $log_message_extra;
        dw_log_activity('SUB_ORDER_STATUS_UPDATED', $log_message, $actor_id);

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

        // Trigger hook untuk hapus cache notifikasi
        do_action('dw_order_status_changed', $sub_order_id, $new_status, $old_status, $id_pedagang_user);
    }

    return true; // Sukses
}

/**
 * [BARU] Helper untuk menghitung ulang total di tabel transaksi utama.
 */
function dw_sync_main_order_totals($main_order_id) {
    global $wpdb;
    
    // Hitung ulang total dari semua sub-order
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

/**
 * [BARU] Helper untuk menyinkronkan status transaksi utama berdasarkan sub-transaksi.
 */
function dw_sync_main_order_status($main_order_id) {
    global $wpdb;
    $table_main = $wpdb->prefix . 'dw_transaksi';
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    
    // 1. Cek status order utama saat ini
    $main_status = $wpdb->get_var($wpdb->prepare("SELECT status_transaksi FROM $table_main WHERE id = %d", $main_order_id));
    // Jika sudah 'selesai' atau 'dibatalkan', jangan diubah lagi
    if (in_array($main_status, ['selesai', 'dibatalkan'])) {
        return;
    }

    // 2. Dapatkan semua status sub-order
    $sub_statuses = $wpdb->get_col($wpdb->prepare("SELECT status_pesanan FROM $table_sub WHERE id_transaksi = %d", $main_order_id));

    if (empty($sub_statuses)) {
        return; // Tidak ada sub-order?
    }

    // 3. Tentukan status utama baru
    $new_main_status = $main_status;

    // Logika 1: Jika SEMUA sub-order 'selesai'
    $all_selesai = count(array_filter($sub_statuses, function($s) { return $s === 'selesai'; })) === count($sub_statuses);
    if ($all_selesai) {
        $new_main_status = 'selesai';
    }
    // Logika 2: Jika SEMUA sub-order 'dibatalkan'
    elseif (count(array_filter($sub_statuses, function($s) { return $s === 'dibatalkan'; })) === count($sub_statuses)) {
         $new_main_status = 'dibatalkan';
    }
    // Logika 3: Jika setidaknya satu 'diproses' atau 'dikirim' (dan belum 'selesai' semua)
    elseif (count(array_filter($sub_statuses, function($s) { return in_array($s, ['diproses', 'diantar_ojek', 'dikirim_ekspedisi']); })) > 0) {
        $new_main_status = 'diproses'; // Status utama menjadi "Diproses"
    }
    
    // 4. Update jika status utama berubah
    if ($new_main_status !== $main_status) {
        $wpdb->update($table_main, ['status_transaksi' => $new_main_status], ['id' => $main_order_id]);
        dw_log_activity('MAIN_ORDER_STATUS_SYNC', "Status Transaksi Utama #{$main_order_id} otomatis diupdate menjadi '{$new_main_status}' berdasarkan sub-order.", 0);
    }
}


/**
 * [BARU] Mengambil badge HTML untuk status pesanan.
 */
function dw_get_order_status_badge($status) {
    $label = dw_get_order_status_label($status);
    $status_class = sanitize_html_class($status); 
    return '<span class="' . $status_class . '">' . esc_html($label) . '</span>';
}


/**
 * Mengambil URL embed video dari URL asli (YouTube/Vimeo).
 */
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

?>