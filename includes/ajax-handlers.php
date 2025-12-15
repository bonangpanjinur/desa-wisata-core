<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handle image upload via AJAX
 */
function dw_handle_image_upload() {
    check_ajax_referer( 'dw_upload_nonce', 'nonce' );

    if ( ! current_user_can( 'upload_files' ) ) {
        error_log('DW Upload: User not permitted');
        wp_send_json_error( array( 'message' => 'Anda tidak memiliki izin.' ) );
        wp_die(); // Pastikan stop
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
    
    // Validasi sederhana
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
 * [BARU] Handler AJAX untuk mengambil data wilayah (Provinsi/Kota/Kec/Kel)
 * Memperbaiki masalah dropdown tidak loading di halaman admin.
 */
function dw_get_wilayah_handler() {
    // Cek nonce untuk keamanan (dikirim dari admin-scripts.js)
    check_ajax_referer( 'dw_admin_nonce', 'nonce' );

    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $id   = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    $data = [];

    // Pastikan file API helper dimuat
    if (!function_exists('dw_get_api_provinsi')) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/address-api.php';
    }

    // Routing berdasarkan tipe wilayah
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
        $data = dw_get_api_desa($id); // API Helper menamakannya 'desa', tapi ini level kelurahan
    }

    if (empty($data)) {
        wp_send_json_error(['message' => 'Gagal mengambil data atau data kosong.']);
    } else {
        wp_send_json_success($data);
    }
    wp_die();
}
add_action('wp_ajax_dw_get_wilayah', 'dw_get_wilayah_handler');
?>