<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. USER & PUBLIC HANDLERS (KODE LAMA ANDA)
// ==========================================

/**
 * Handle image upload via AJAX
 */
function dw_handle_image_upload() {
    check_ajax_referer( 'dw_upload_nonce', 'nonce' );

    if ( ! current_user_can( 'upload_files' ) ) {
        error_log('DW Upload: User not permitted');
        wp_send_json_error( array( 'message' => 'Anda tidak memiliki izin.' ) );
        wp_die();
    }

    if ( ! isset( $_FILES['file'] ) ) {
        wp_send_json_error( array( 'message' => 'No file uploaded' ) );
        wp_die();
    }

    $file = $_FILES['file'];
    $upload_overrides = array( 'test_form' => false );
    $movefile = wp_handle_upload( $file, $upload_overrides );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        $file_type = wp_check_filetype( $movefile['file'] );
        if( ! in_array( $file_type['type'], ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'] ) ) {
             wp_delete_file( $movefile['file'] );
             wp_send_json_error( array( 'message' => 'Hanya file gambar (JPG, PNG, WEBP) yang diperbolehkan.' ) );
             wp_die();
        }

        wp_send_json_success( array( 'url' => $movefile['url'], 'id' => 0 ) );
    } else {
        error_log('DW Upload Error: ' . $movefile['error']);
        wp_send_json_error( array( 'message' => $movefile['error'] ) );
    }
    wp_die();
}
add_action( 'wp_ajax_dw_upload_image', 'dw_handle_image_upload' );

/**
 * Handler Toggle Wishlist (Like/Unlike)
 */
function dw_ajax_toggle_wishlist() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Silakan login terlebih dahulu', 'code' => 'not_logged_in']);
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $item_type = isset($_POST['item_type']) ? sanitize_text_field($_POST['item_type']) : 'wisata';

    if (!$item_id) {
        wp_send_json_error(['message' => 'Data tidak valid']);
    }

    $table = $wpdb->prefix . 'dw_wishlist';

    $exists = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND item_id = %d AND item_type = %s",
        $user_id, $item_id, $item_type
    ));

    if ($exists) {
        $wpdb->delete($table, ['id' => $exists->id]);
        wp_send_json_success(['status' => 'removed', 'message' => 'Dihapus dari favorit']);
    } else {
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'item_id' => $item_id,
            'item_type' => $item_type,
            'created_at' => current_time('mysql')
        ]);
        wp_send_json_success(['status' => 'added', 'message' => 'Ditambahkan ke favorit']);
    }
}
add_action('wp_ajax_dw_toggle_wishlist', 'dw_ajax_toggle_wishlist');

/**
 * Handle chat message sending
 */
function dw_handle_send_message() {
    check_ajax_referer( 'dw_chat_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Login required.' ) );
        wp_die();
    }

    $order_id = intval( $_POST['order_id'] ?? 0 );
    $message  = sanitize_textarea_field( $_POST['message'] ?? '' );
    $sender   = get_current_user_id();

    if ( !$order_id || empty($message) ) {
        wp_send_json_error( array( 'message' => 'Data invalid.' ) );
        wp_die();
    }

    global $wpdb;
    $order = $wpdb->get_row( $wpdb->prepare( "SELECT pembeli_id, pedagang_id FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id ) );

    if ( ! $order ) {
        wp_send_json_error( array( 'message' => 'Pesanan tidak ditemukan.' ) );
        wp_die();
    }

    if ( $sender != $order->pembeli_id && $sender != $order->pedagang_id && ! current_user_can('manage_options') ) {
        wp_send_json_error( array( 'message' => 'Akses ditolak.' ) );
        wp_die();
    }

    $table_chat = $wpdb->prefix . 'dw_chat_message'; 
    $inserted = $wpdb->insert(
        $table_chat,
        array( 'order_id' => $order_id, 'sender_id' => $sender, 'message' => $message, 'created_at' => current_time('mysql') ), 
        array( '%d', '%d', '%s', '%s' )
    );

    if ( $inserted ) {
        wp_send_json_success( array( 'message' => 'Terkirim' ) );
    } else {
        error_log('DW Chat Error: ' . $wpdb->last_error);
        wp_send_json_error( array( 'message' => 'Database error' ) );
    }
    wp_die();
}
add_action( 'wp_ajax_dw_send_message', 'dw_handle_send_message' );

/**
 * Cek Kecocokan Desa (Untuk Auto-Verify Pedagang)
 */
function dw_check_desa_match_from_address() {
    check_ajax_referer( 'dw_admin_nonce', 'nonce' );
    global $wpdb;

    $kel_id = sanitize_text_field($_POST['kel_id'] ?? '');
    
    if ( empty($kel_id) ) {
        wp_send_json_error( ['message' => 'Data wilayah tidak lengkap'] );
        wp_die();
    }

    $desa = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE api_kelurahan_id = %s AND status = 'aktif' LIMIT 1",
        $kel_id
    ));

    if ( $desa ) {
        wp_send_json_success( [ 'matched' => true, 'desa_id' => $desa->id, 'nama_desa' => $desa->nama_desa ] );
    } else {
        wp_send_json_success( [ 'matched' => false ] );
    }
    wp_die();
}
add_action( 'wp_ajax_dw_check_desa_match_from_address', 'dw_check_desa_match_from_address' );

/**
 * Handler AJAX untuk mengambil data wilayah (Provinsi/Kota/Kec/Kel)
 */
function dw_get_wilayah_handler() {
    check_ajax_referer( 'dw_admin_nonce', 'nonce' );

    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $id   = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    $data = [];

    if (!function_exists('dw_get_api_provinsi')) {
        // Sesuaikan path jika perlu, menggunakan konstanta plugin lebih aman
        if (defined('DW_CORE_PLUGIN_DIR')) {
            require_once DW_CORE_PLUGIN_DIR . 'includes/address-api.php';
        } else {
             // Fallback manual path
             $api_path = plugin_dir_path(dirname(__FILE__)) . 'includes/address-api.php';
             if (file_exists($api_path)) require_once $api_path;
        }
    }

    if (function_exists('dw_get_api_provinsi')) {
        if ($type === 'provinsi') {
            $data = dw_get_api_provinsi();
        } 
        elseif ($type === 'kabupaten' && !empty($id)) {
            $data = dw_get_api_kabupaten($id);
        } 
        elseif ($type === 'kecamatan' && !empty($id)) {
            $data = dw_get_api_kecamatan($id);
        } 
        elseif ($type === 'kelurahan' && !empty($id)) {
            $data = dw_get_api_desa($id);
        }
    }

    if (empty($data)) {
        wp_send_json_error(['message' => 'Gagal mengambil data atau data kosong.']);
    } else {
        wp_send_json_success($data);
    }
    wp_die();
}
add_action('wp_ajax_dw_get_wilayah', 'dw_get_wilayah_handler');

// ==========================================
// 2. ADMIN HANDLERS (PERBAIKAN & PENAMBAHAN)
// ==========================================

/**
 * Handle Verifikasi Paket Transaksi (FIXED)
 * Memperbaiki masalah verifikasi paket tidak berfungsi.
 */
add_action('wp_ajax_dw_process_verifikasi_paket', 'dw_process_verifikasi_paket');
function dw_process_verifikasi_paket() {
    check_ajax_referer('dw_admin_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Anda tidak memiliki izin.');
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

    if (!$post_id) {
        wp_send_json_error('Data transaksi tidak valid.');
    }

    // Ambil User ID (Coba meta key 'user_id' dulu, fallback ke 'id_pedagang')
    $user_id = get_post_meta($post_id, 'user_id', true);
    if (!$user_id) {
        $user_id = get_post_meta($post_id, 'id_pedagang', true);
    }

    if (!$user_id) {
        wp_send_json_error('User ID tidak ditemukan dalam transaksi.');
    }

    if ($action_type === 'approve') {
        // 1. Update status transaksi
        update_post_meta($post_id, 'status_verifikasi', 'terverifikasi');
        
        // 2. Berikan role 'pedagang' ke user
        $user = get_user_by('ID', $user_id);
        if ($user && !in_array('pedagang', (array) $user->roles)) {
            $user->add_role('pedagang');
        }

        // 3. Set masa aktif paket
        $paket_id = get_post_meta($post_id, 'paket_id', true);
        $duration = get_post_meta($paket_id, 'durasi', true); // Durasi dalam hari
        
        if ($duration) {
            $start_date = current_time('mysql');
            $end_date = date('Y-m-d H:i:s', strtotime("+$duration days", strtotime($start_date)));
            
            // Update di transaksi
            update_post_meta($post_id, 'tanggal_mulai', $start_date);
            update_post_meta($post_id, 'tanggal_berakhir', $end_date);
            
            // Update di user meta agar mudah dicek
            update_user_meta($user_id, 'dw_paket_status', 'active');
            update_user_meta($user_id, 'dw_paket_end_date', $end_date);
        }

        wp_send_json_success('Paket disetujui dan diaktifkan.');

    } elseif ($action_type === 'reject') {
        update_post_meta($post_id, 'status_verifikasi', 'ditolak');
        wp_send_json_success('Paket ditolak.');
    }

    wp_send_json_error('Aksi tidak dikenali.');
}

/**
 * Handle Payout Komisi (FIXED)
 * Memperbaiki masalah tombol payout tidak merespon.
 */
add_action('wp_ajax_dw_process_payout_komisi', 'dw_process_payout_komisi');
function dw_process_payout_komisi() {
    check_ajax_referer('dw_admin_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Akses ditolak.');
    }

    $komisi_id = isset($_POST['komisi_id']) ? intval($_POST['komisi_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if (!$komisi_id) {
        wp_send_json_error('ID Komisi tidak valid.');
    }

    if ($status === 'paid') {
        update_post_meta($komisi_id, 'status_komisi', 'paid');
        update_post_meta($komisi_id, 'tanggal_payout', current_time('mysql'));
        
        // Tambahan: Jika mau update saldo wallet user bisa ditambahkan disini
        
        wp_send_json_success('Komisi berhasil dibayarkan.');

    } elseif ($status === 'rejected') {
        update_post_meta($komisi_id, 'status_komisi', 'rejected');
        wp_send_json_success('Komisi ditolak.');
    }

    wp_send_json_error('Gagal memproses.');
}

/**
 * Handle Verifikasi Pedagang (Dokumen)
 */
add_action('wp_ajax_dw_verify_pedagang', 'dw_process_verify_pedagang');
function dw_process_verify_pedagang() {
    check_ajax_referer('dw_admin_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Izin ditolak.');
    }

    $pedagang_id = intval($_POST['pedagang_id']);
    $action_type = sanitize_text_field($_POST['action_type']);

    if (!$pedagang_id) wp_send_json_error('ID Pedagang invalid.');

    $user_id = get_post_meta($pedagang_id, 'user_id', true);

    if ($action_type === 'approve') {
        update_post_meta($pedagang_id, 'status_verifikasi', 'terverifikasi');
        if($user_id) update_user_meta($user_id, 'dw_is_verified_pedagang', 'yes');
        wp_send_json_success('Pedagang diverifikasi.');
    } elseif ($action_type === 'reject') {
        update_post_meta($pedagang_id, 'status_verifikasi', 'ditolak');
        if($user_id) update_user_meta($user_id, 'dw_is_verified_pedagang', 'no');
        wp_send_json_success('Pedagang ditolak.');
    }
    wp_send_json_error('Error handling request.');
}

/**
 * Handle Delete Log
 */
add_action('wp_ajax_dw_delete_log', 'dw_process_delete_log');
function dw_process_delete_log() {
    check_ajax_referer('dw_admin_nonce', 'security');
    if (!current_user_can('manage_options')) wp_send_json_error('Ditolak.');

    $log_id = intval($_POST['log_id']);
    if ($log_id) {
        wp_delete_post($log_id, true);
        wp_send_json_success('Log dihapus.');
    }
    wp_send_json_error('Gagal hapus.');
}
?>