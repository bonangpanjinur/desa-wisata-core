<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. USER & PUBLIC HANDLERS
// ==========================================

/**
 * Handle image upload via AJAX
 */
function dw_handle_image_upload() {
    check_ajax_referer( 'dw_upload_nonce', 'nonce' );

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => 'Anda tidak memiliki izin.' ) );
    }

    if ( ! isset( $_FILES['file'] ) ) {
        wp_send_json_error( array( 'message' => 'Tidak ada file yang diunggah.' ) );
    }

    $file = $_FILES['file'];
    $upload_overrides = array( 'test_form' => false );
    $movefile = wp_handle_upload( $file, $upload_overrides );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        $file_type = wp_check_filetype( $movefile['file'] );
        if( ! in_array( $file_type['type'], ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'] ) ) {
             wp_delete_file( $movefile['file'] );
             wp_send_json_error( array( 'message' => 'Hanya file gambar (JPG, PNG, WEBP) yang diperbolehkan.' ) );
        }
        wp_send_json_success( array( 'url' => $movefile['url'], 'id' => 0 ) );
    } else {
        wp_send_json_error( array( 'message' => $movefile['error'] ) );
    }
}
add_action( 'wp_ajax_dw_upload_image', 'dw_handle_image_upload' );

/**
 * Handler Toggle Wishlist
 */
function dw_ajax_toggle_wishlist() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Silakan login terlebih dahulu', 'code' => 'not_logged_in']);
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $item_type = isset($_POST['item_type']) ? sanitize_text_field($_POST['item_type']) : 'wisata';

    if (!$item_id) wp_send_json_error(['message' => 'Data tidak valid']);

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

    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Login required.' ) );

    global $wpdb;
    $order_id = intval( $_POST['order_id'] ?? 0 );
    $message  = sanitize_textarea_field( $_POST['message'] ?? '' );
    $sender   = get_current_user_id();

    if ( !$order_id || empty($message) ) wp_send_json_error( array( 'message' => 'Data invalid.' ) );

    $order = $wpdb->get_row( $wpdb->prepare( "SELECT pembeli_id, pedagang_id FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id ) );

    if ( ! $order ) wp_send_json_error( array( 'message' => 'Pesanan tidak ditemukan.' ) );

    if ( $sender != $order->pembeli_id && $sender != $order->pedagang_id && ! current_user_can('manage_options') ) {
        wp_send_json_error( array( 'message' => 'Akses ditolak.' ) );
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
        wp_send_json_error( array( 'message' => 'Database error' ) );
    }
}
add_action( 'wp_ajax_dw_send_message', 'dw_handle_send_message' );

/**
 * Cek Kecocokan Desa
 */
function dw_check_desa_match_from_address() {
    check_ajax_referer( 'dw_admin_nonce', 'nonce' ); 
    global $wpdb;

    $kel_id = sanitize_text_field($_POST['kel_id'] ?? '');
    
    if ( empty($kel_id) ) wp_send_json_error( ['message' => 'Data wilayah tidak lengkap'] );

    $desa = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE api_kelurahan_id = %s AND status = 'aktif' LIMIT 1",
        $kel_id
    ));

    if ( $desa ) {
        wp_send_json_success( [ 'matched' => true, 'desa_id' => $desa->id, 'nama_desa' => $desa->nama_desa ] );
    } else {
        wp_send_json_success( [ 'matched' => false ] );
    }
}
add_action( 'wp_ajax_dw_check_desa_match_from_address', 'dw_check_desa_match_from_address' );

/**
 * Handler Wilayah
 */
function dw_get_wilayah_handler() {
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $id   = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    $data = [];

    // Pastikan fungsi API tersedia
    if (!function_exists('dw_get_api_provinsi')) {
        if (defined('DW_CORE_PLUGIN_DIR')) {
            require_once DW_CORE_PLUGIN_DIR . 'includes/address-api.php';
        }
    }

    if (function_exists('dw_get_api_provinsi')) {
        if ($type === 'provinsi') $data = dw_get_api_provinsi();
        elseif ($type === 'kabupaten') $data = dw_get_api_kabupaten($id);
        elseif ($type === 'kecamatan') $data = dw_get_api_kecamatan($id);
        elseif ($type === 'kelurahan') $data = dw_get_api_desa($id);
    }

    if (!empty($data)) wp_send_json_success($data);
    else wp_send_json_error(['message' => 'Data kosong']);
}
add_action('wp_ajax_dw_get_wilayah', 'dw_get_wilayah_handler');
add_action('wp_ajax_nopriv_dw_get_wilayah', 'dw_get_wilayah_handler');

// ==========================================
// 2. ADMIN HANDLERS (ADMIN PANEL)
// ==========================================

/**
 * Handle Verifikasi Paket Transaksi
 * Triggered by: .dw-verify-paket-btn
 */
add_action('wp_ajax_dw_process_verifikasi_paket', 'dw_process_verifikasi_paket');
function dw_process_verifikasi_paket() {
    check_ajax_referer('dw_admin_nonce', 'security');
    if (!current_user_can('manage_options')) wp_send_json_error('Akses ditolak.');

    global $wpdb;
    $pembelian_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $action_type  = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : ''; // 'approve' atau 'reject'

    if (!$pembelian_id) wp_send_json_error('ID Transaksi tidak valid.');

    // Ambil data pembelian dari tabel custom
    $table_pembelian = $wpdb->prefix . 'dw_pembelian_paket';
    $transaksi = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pembelian WHERE id = %d", $pembelian_id));

    if (!$transaksi) wp_send_json_error('Data transaksi tidak ditemukan.');
    if ($transaksi->status != 'pending') wp_send_json_error('Transaksi ini sudah diproses sebelumnya.');

    if ($action_type === 'approve') {
        // 1. Update status di tabel pembelian
        $wpdb->update(
            $table_pembelian,
            array('status' => 'disetujui', 'updated_at' => current_time('mysql')),
            array('id' => $pembelian_id)
        );

        // 2. Update Data Pedagang (Kuota & Masa Aktif)
        $id_pedagang = $transaksi->id_pedagang;
        $kuota_tambahan = intval($transaksi->jumlah_transaksi);
        
        // Cek tabel pedagang
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        $pedagang = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id = %d", $id_pedagang));
        
        if ($pedagang) {
            $kuota_sekarang = isset($pedagang->sisa_kuota) ? intval($pedagang->sisa_kuota) : 0;
            $kuota_baru = $kuota_sekarang + $kuota_tambahan;

            $wpdb->update(
                $table_pedagang,
                array(
                    'sisa_kuota' => $kuota_baru, 
                    'status_paket' => 'active',
                    'paket_expired' => date('Y-m-d H:i:s', strtotime('+30 days')) // Contoh default 30 hari
                ),
                array('id' => $id_pedagang)
            );
            
            // Opsional: Jika user WP terhubung, berikan role 'pedagang'
            if (!empty($pedagang->user_id)) {
                $user = get_user_by('ID', $pedagang->user_id);
                if ($user && !in_array('pedagang', (array)$user->roles)) {
                    $user->add_role('pedagang');
                }
            }
        }

        wp_send_json_success('Paket berhasil disetujui. Kuota ditambahkan.');

    } elseif ($action_type === 'reject') {
        // Update status ditolak
        $wpdb->update(
            $table_pembelian,
            array('status' => 'ditolak', 'updated_at' => current_time('mysql')),
            array('id' => $pembelian_id)
        );
        wp_send_json_success('Permintaan paket ditolak.');
    }

    wp_send_json_error('Aksi tidak dikenali.');
}

/**
 * Handle Verifikasi Pedagang (Dokumen)
 * Triggered by: .dw-verify-pedagang-btn
 */
add_action('wp_ajax_dw_verify_pedagang', 'dw_process_verify_pedagang');
function dw_process_verify_pedagang() {
    check_ajax_referer('dw_admin_nonce', 'security');
    if (!current_user_can('manage_options')) wp_send_json_error('Akses ditolak.');

    global $wpdb;
    $pedagang_id = intval($_POST['pedagang_id']);
    $action_type = sanitize_text_field($_POST['action_type']);
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';

    if (!$pedagang_id) wp_send_json_error('ID Pedagang invalid.');

    if ($action_type === 'approve') {
        $wpdb->update(
            $table_pedagang,
            array('status_verifikasi' => 'terverifikasi'),
            array('id' => $pedagang_id)
        );
        wp_send_json_success('Pedagang diverifikasi.');
    } elseif ($action_type === 'reject') {
        $wpdb->update(
            $table_pedagang,
            array('status_verifikasi' => 'ditolak'),
            array('id' => $pedagang_id)
        );
        wp_send_json_success('Pedagang ditolak.');
    }
    wp_send_json_error('Error handling request.');
}

/**
 * Handle Payout Komisi
 * Triggered by: .dw-payout-btn
 */
add_action('wp_ajax_dw_process_payout_komisi', 'dw_process_payout_komisi');
function dw_process_payout_komisi() {
    check_ajax_referer('dw_admin_nonce', 'security');
    if (!current_user_can('manage_options')) wp_send_json_error('Akses ditolak.');

    global $wpdb;
    $komisi_id = intval($_POST['komisi_id']);
    $status = sanitize_text_field($_POST['status']);
    $table_komisi = $wpdb->prefix . 'dw_komisi';

    if ($status === 'paid') {
        $wpdb->update($table_komisi, 
            ['status_komisi' => 'paid', 'tanggal_payout' => current_time('mysql')],
            ['id' => $komisi_id]
        );
        wp_send_json_success('Komisi DIBAYAR.');
    } elseif ($status === 'rejected') {
        $wpdb->update($table_komisi, 
            ['status_komisi' => 'rejected'], 
            ['id' => $komisi_id]
        );
        wp_send_json_success('Komisi DITOLAK.');
    }
    wp_send_json_error('Gagal memproses.');
}

/**
 * Handle Delete Log
 * Triggered by JS for log cleanup if applicable
 */
add_action('wp_ajax_dw_delete_log', 'dw_process_delete_log');
function dw_process_delete_log() {
    check_ajax_referer('dw_admin_nonce', 'security');
    if (!current_user_can('manage_options')) wp_send_json_error('Ditolak.');

    $log_id = intval($_POST['log_id']);
    if ($log_id) {
        // Asumsi Log disimpan sebagai Post Type 'dw_log' atau serupa.
        // Jika Log menggunakan tabel custom, ganti dengan logic $wpdb->delete
        wp_delete_post($log_id, true);
        wp_send_json_success('Log dihapus.');
    }
    wp_send_json_error('Gagal hapus.');
}
?>