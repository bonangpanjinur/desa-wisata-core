<?php
/**
 * File Path: includes/ajax-handlers.php
 *
 * PERUBAHAN KRITIS (HEADLESS MURNI):
 * - Hapus semua handler AJAX frontend (Keranjang, Chat, Upload Bukti Bayar)
 * karena fungsionalitas tersebut sudah dipindahkan ke REST API murni (`includes/rest-api.php`).
 * - Hanya sisakan handler AJAX Admin yang diperlukan untuk operasional dashboard WordPress.
 *
 * PERBAIKAN FATAL ERROR (v3.1.3):
 * - Memastikan semua `add_action` di file ini mendaftarkan hook AJAX
 * untuk fungsi yang didefinisikan di file ini.
 *
 * PERBAIKAN FATAL ERROR (v3.2.2):
 * - Menghapus satu kurung kurawal penutup `}` yang tersesat di akhir file
 * yang menyebabkan PHP Parse Error (500 Internal Server Error).
 *
 * --- PERUBAHAN (RELASI ALAMAT) ---
 * - Menambahkan fungsi `dw_check_desa_match_from_address_ajax`
 * untuk mendukung UI di form admin pedagang.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// HANDLER ADMIN (Internal WP Dashboard)
// =========================================================================

/**
 * Handler AJAX untuk mengambil data kabupaten.
 * Dipertahankan untuk penggunaan di form admin (dw-desa).
 */
function dw_get_kabupaten_ajax() {
    check_ajax_referer('dw_admin_nonce', 'nonce'); // Mengubah nonce ke admin
    
    $provinsi_id = isset($_POST['provinsi_id']) ? sanitize_text_field($_POST['provinsi_id']) : '';
    $kabupaten = dw_get_api_kabupaten($provinsi_id);
    wp_send_json_success($kabupaten);
}
add_action('wp_ajax_dw_get_kabupaten', 'dw_get_kabupaten_ajax');
// Hapus add_action('wp_ajax_nopriv_dw_get_kabupaten')

/**
 * Handler AJAX untuk mengambil data kecamatan.
 * Dipertahankan untuk penggunaan di form admin (dw-desa).
 */
function dw_get_kecamatan_ajax() {
    check_ajax_referer('dw_admin_nonce', 'nonce'); // Mengubah nonce ke admin
    
    $kabupaten_id = isset($_POST['kabupaten_id']) ? sanitize_text_field($_POST['kabupaten_id']) : '';
    $kecamatan = dw_get_api_kecamatan($kabupaten_id);
    wp_send_json_success($kecamatan);
}
add_action('wp_ajax_dw_get_kecamatan', 'dw_get_kecamatan_ajax');
// Hapus add_action('wp_ajax_nopriv_dw_get_kecamatan')

/**
 * Handler AJAX untuk mengambil data desa/kelurahan.
 * Dipertahankan untuk penggunaan di form admin (dw-desa).
 */
function dw_get_desa_ajax() {
    check_ajax_referer('dw_admin_nonce', 'nonce'); // Mengubah nonce ke admin
    
    $kecamatan_id = isset($_POST['kecamatan_id']) ? sanitize_text_field($_POST['kecamatan_id']) : '';
    $desa = dw_get_api_desa($kecamatan_id);
    wp_send_json_success($desa);
}
add_action('wp_ajax_dw_get_desa', 'dw_get_desa_ajax'); // Hook ini dipanggil oleh admin-scripts.js
// Hapus add_action('wp_ajax_nopriv_dw_get_desa')


/**
 * Handler AJAX untuk mengambil detail alamat sebuah desa dari DB.
 * Digunakan untuk autofill alamat di form Pedagang.
 */
function dw_get_desa_address_ajax() {
    check_ajax_referer('dw_admin_nonce', 'nonce');
    // Kapabilitas disesuaikan agar bisa diakses oleh role yang relevan.
    if ( ! current_user_can('dw_manage_pedagang') && !current_user_can('dw_manage_desa') ) {
        wp_send_json_error(['message' => 'Akses ditolak.']);
    }

    $desa_id = isset($_POST['desa_id']) ? intval($_POST['desa_id']) : 0;
    if ($desa_id === 0) {
        wp_send_json_error(['message' => 'ID Desa tidak valid.']);
    }

    global $wpdb;
    // Ambil semua field alamat yang relevan dari tabel dw_desa
    $desa = $wpdb->get_row($wpdb->prepare("SELECT kelurahan, kecamatan, kabupaten, provinsi FROM {$wpdb->prefix}dw_desa WHERE id = %d", $desa_id));

    if ($desa) {
        $alamat_parts = array_filter([
            $desa->kelurahan,
            $desa->kecamatan,
            $desa->kabupaten,
            $desa->provinsi,
        ]);
        $alamat_string = implode(', ', $alamat_parts);
        wp_send_json_success(['alamat' => esc_html($alamat_string)]);
    } else {
        wp_send_json_error(['message' => 'Desa tidak ditemukan.']);
    }
}
add_action('wp_ajax_dw_get_desa_address', 'dw_get_desa_address_ajax');


/**
 * BARU: Handler AJAX untuk mengecek apakah alamat API cocok dengan desa terdaftar.
 * Digunakan di form admin pedagang untuk memberi feedback ke admin.
 */
function dw_check_desa_match_from_address_ajax() {
    check_ajax_referer('dw_admin_nonce', 'nonce');
    if ( ! current_user_can('dw_manage_pedagang') ) {
        wp_send_json_error(['message' => 'Akses ditolak.']);
    }

    $kel_id = isset($_POST['kel_id']) ? sanitize_text_field($_POST['kel_id']) : '';
    $kec_id = isset($_POST['kec_id']) ? sanitize_text_field($_POST['kec_id']) : '';
    $kab_id = isset($_POST['kab_id']) ? sanitize_text_field($_POST['kab_id']) : '';

    if (empty($kel_id) || empty($kec_id) || empty($kab_id)) {
        wp_send_json_error(['message' => 'Data alamat tidak lengkap.']);
    }

    global $wpdb;
    $desa = $wpdb->get_row($wpdb->prepare(
        "SELECT id, nama_desa 
         FROM {$wpdb->prefix}dw_desa 
         WHERE api_kelurahan_id = %s 
           AND api_kecamatan_id = %s 
           AND api_kabupaten_id = %s",
        $kel_id, $kec_id, $kab_id
    ));

    if ($desa) {
        wp_send_json_success([
            'matched' => true,
            'desa_id' => $desa->id,
            'nama_desa' => $desa->nama_desa
        ]);
    } else {
        wp_send_json_success([
            'matched' => false
        ]);
    }
}
add_action('wp_ajax_dw_check_desa_match_from_address', 'dw_check_desa_match_from_address_ajax');


/**
 * Handler AJAX untuk mengirim balasan chat dari Pedagang (Admin Backend).
 */
function dw_send_admin_reply_message_ajax() {
    // Hanya Pedagang yang bisa membalas
    if (!current_user_can('pedagang')) {
        wp_send_json_error(['message' => 'Akses ditolak.']);
    }
    check_ajax_referer('dw_admin_nonce', 'nonce'); // Menggunakan nonce admin

    global $wpdb;
    $table_chat = $wpdb->prefix . 'dw_chat_message';
    $product_id = absint($_POST['product_id'] ?? 0);
    $receiver_id = absint($_POST['receiver_id'] ?? 0); // Penerima pesan adalah Pembeli
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    $sender_id = get_current_user_id(); // Pengirim adalah Pedagang

    // Verifikasi kepemilikan produk dan penerima
    if (!$product_id || empty($message) || !$receiver_id || get_post_field('post_author', $product_id) !== $sender_id) {
        wp_send_json_error(['message' => 'Data tidak valid, pesan kosong, atau Anda bukan pemilik produk.']);
    }
    
    $wpdb->insert(
        $table_chat,
        [
            'produk_id' => $product_id,
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'message' => $message,
            'is_read' => 0, // Tandai belum dibaca oleh Pembeli
        ],
        ['%d', '%d', '%d', '%s', '%d']
    );

    if ($wpdb->insert_id) {
        dw_log_activity('CHAT_REPLY_SENT', "Balasan chat dikirim oleh Pedagang #{$sender_id} ke Pembeli #{$receiver_id} tentang produk #{$product_id}.");
        wp_send_json_success(['message' => 'Pesan balasan berhasil dikirim.']);
    } else {
        wp_send_json_error(['message' => 'Gagal menyimpan pesan balasan.']);
    }
}
add_action('wp_ajax_dw_send_admin_reply_message', 'dw_send_admin_reply_message_ajax');

// =========================================================================
// HANDLER FRONTEND (Headless Murni) - DIHAPUS DARI FILE INI
// =========================================================================
// dw_add_to_cart_ajax() - DIPINDAHKAN KE REST API
// dw_upload_payment_proof_ajax() - DIPINDAHKAN KE REST API
// dw_send_inquiry_message_ajax() - DIPINDAHKAN KE REST API
// dw_get_inquiry_messages_ajax() - DIPINDAHKAN KE REST API
?>