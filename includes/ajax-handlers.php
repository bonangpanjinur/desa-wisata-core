<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle image upload via AJAX
 * Security: Added Nonce Verification & Capability Check
 */
function dw_handle_image_upload() {
    // 1. Security Check: Verifikasi Nonce
    // Pastikan di frontend JS Anda mengirimkan parameter 'nonce' dengan value wp_create_nonce('dw_upload_nonce')
    check_ajax_referer( 'dw_upload_nonce', 'nonce' );

    // 2. Security Check: Pastikan user berhak upload
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => 'Anda tidak memiliki izin untuk mengunggah file.' ) );
    }

    if ( ! isset( $_FILES['file'] ) ) {
        wp_send_json_error( array( 'message' => 'No file uploaded' ) );
    }

    $file = $_FILES['file'];
    
    // Gunakan media_handle_sideload atau handle_upload agar masuk ke Media Library dengan aman
    $upload_overrides = array( 'test_form' => false );
    $movefile = wp_handle_upload( $file, $upload_overrides );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        // Opsional: Batasi tipe file hanya gambar
        $file_type = wp_check_filetype( $movefile['file'] );
        if( ! in_array( $file_type['type'], ['image/jpeg', 'image/png', 'image/jpg'] ) ) {
             wp_delete_file( $movefile['file'] );
             wp_send_json_error( array( 'message' => 'Hanya file gambar yang diperbolehkan.' ) );
        }

        wp_send_json_success( array(
            'url' => $movefile['url'],
            'id'  => 0 // Jika tidak dimasukkan ke DB media library, ID 0.
        ) );
    } else {
        wp_send_json_error( array( 'message' => $movefile['error'] ) );
    }
}
add_action( 'wp_ajax_dw_upload_image', 'dw_handle_image_upload' );
// add_action( 'wp_ajax_nopriv_dw_upload_image', 'dw_handle_image_upload' ); // DIBUANG: Tamu tidak boleh upload sembarangan!

/**
 * Handle chat message sending
 */
function dw_handle_send_message() {
    // 1. Security Check
    check_ajax_referer( 'dw_chat_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Silakan login terlebih dahulu.' ) );
    }

    // Input Sanitization
    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    $message  = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : ''; // Gunakan sanitize_textarea_field untuk chat
    $sender   = get_current_user_id();

    if ( ! $order_id || empty( $message ) ) {
        wp_send_json_error( array( 'message' => 'Data tidak lengkap.' ) );
    }

    // Validasi Kepemilikan Order (Penting!)
    // Pastikan pengirim adalah Pembeli ATAU Penjual di order tersebut
    global $wpdb;
    $order = $wpdb->get_row( $wpdb->prepare( "SELECT pembeli_id, pedagang_id FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id ) );

    if ( ! $order ) {
        wp_send_json_error( array( 'message' => 'Pesanan tidak ditemukan.' ) );
    }

    if ( $sender != $order->pembeli_id && $sender != $order->pedagang_id && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Anda tidak memiliki akses ke percakapan ini.' ) );
    }

    $table_name = $wpdb->prefix . 'dw_chat';
    $result = $wpdb->insert(
        $table_name,
        array(
            'order_id' => $order_id,
            'sender_id' => $sender,
            'message' => $message,
            'sent_at' => current_time( 'mysql' )
        ),
        array( '%d', '%d', '%s', '%s' )
    );

    if ( $result ) {
        wp_send_json_success( array( 'message' => 'Pesan terkirim' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Gagal mengirim pesan' ) );
    }
}
add_action( 'wp_ajax_dw_send_message', 'dw_handle_send_message' );