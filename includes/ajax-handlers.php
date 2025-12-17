<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. USER & PUBLIC HANDLERS
// ==========================================

/**
 * Handle Image Upload
 */
function dw_handle_image_upload() {
    check_ajax_referer( 'dw_upload_nonce', 'nonce' );
    if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( array( 'message' => 'Izin ditolak.' ) );
    if ( ! isset( $_FILES['file'] ) ) wp_send_json_error( array( 'message' => 'Tidak ada file.' ) );

    $file = $_FILES['file'];
    $movefile = wp_handle_upload( $file, ['test_form' => false] );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        $type = wp_check_filetype( $movefile['file'] );
        if(!in_array($type['type'], ['image/jpeg','image/png','image/jpg','image/webp'])) {
             wp_delete_file($movefile['file']);
             wp_send_json_error(['message'=>'Hanya gambar (JPG/PNG/WEBP).']);
        }
        wp_send_json_success(['url' => $movefile['url']]);
    } else {
        wp_send_json_error(['message' => $movefile['error']]);
    }
}
add_action( 'wp_ajax_dw_upload_image', 'dw_handle_image_upload' );

/**
 * Handle Wishlist Toggle
 */
function dw_ajax_toggle_wishlist() {
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Silakan login.']);
    global $wpdb;
    $user_id = get_current_user_id();
    $item_id = intval($_POST['item_id']);
    $item_type = sanitize_text_field($_POST['item_type']);
    
    $table = $wpdb->prefix . 'dw_wishlist';
    $exists = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE user_id=%d AND item_id=%d AND item_type=%s", $user_id, $item_id, $item_type));

    if ($exists) {
        $wpdb->delete($table, ['id' => $exists->id]);
        wp_send_json_success(['status'=>'removed', 'message'=>'Dihapus dari favorit']);
    } else {
        $wpdb->insert($table, ['user_id'=>$user_id, 'item_id'=>$item_id, 'item_type'=>$item_type, 'created_at'=>current_time('mysql')]);
        wp_send_json_success(['status'=>'added', 'message'=>'Ditambahkan ke favorit']);
    }
}
add_action('wp_ajax_dw_toggle_wishlist', 'dw_ajax_toggle_wishlist');

/**
 * Handle Chat Message
 */
function dw_handle_send_message() {
    check_ajax_referer('dw_chat_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Login required']);
    
    global $wpdb;
    $order_id = intval($_POST['order_id']);
    $msg = sanitize_textarea_field($_POST['message']);
    $sender = get_current_user_id();

    if(!$order_id || empty($msg)) wp_send_json_error(['message'=>'Data invalid']);

    $order = $wpdb->get_row($wpdb->prepare("SELECT pembeli_id, pedagang_id FROM {$wpdb->prefix}dw_transaksi WHERE id=%d", $order_id));
    if(!$order) wp_send_json_error(['message'=>'Order not found']);
    
    if($sender != $order->pembeli_id && $sender != $order->pedagang_id && !current_user_can('manage_options')) {
        wp_send_json_error(['message'=>'Akses ditolak']);
    }

    $wpdb->insert($wpdb->prefix.'dw_chat_message', [
        'order_id'=>$order_id, 'sender_id'=>$sender, 'message'=>$msg, 'created_at'=>current_time('mysql')
    ]);
    wp_send_json_success(['message'=>'Terkirim']);
}
add_action('wp_ajax_dw_send_message', 'dw_handle_send_message');

/**
 * Handle Cek Desa Match
 */
function dw_check_desa_match_from_address() {
    check_ajax_referer('dw_admin_nonce', 'nonce');
    global $wpdb;
    $kel_id = sanitize_text_field($_POST['kel_id']);
    $desa = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE api_kelurahan_id=%s AND status='aktif'", $kel_id));
    
    if($desa) wp_send_json_success(['matched'=>true, 'desa_id'=>$desa->id, 'nama_desa'=>$desa->nama_desa]);
    else wp_send_json_success(['matched'=>false]);
}
add_action('wp_ajax_dw_check_desa_match_from_address', 'dw_check_desa_match_from_address');

/**
 * Handle Get Wilayah API
 */
function dw_get_wilayah_handler() {
    $type = sanitize_text_field($_GET['type']);
    $id = sanitize_text_field($_GET['id']);
    // Pastikan path ini benar sesuai struktur plugin Anda
    if(!function_exists('dw_get_api_provinsi') && defined('DW_CORE_PLUGIN_DIR')) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/address-api.php';
    }
    
    $data = [];
    if(function_exists('dw_get_api_provinsi')) {
        if($type=='provinsi') $data=dw_get_api_provinsi();
        elseif($type=='kabupaten') $data=dw_get_api_kabupaten($id);
        elseif($type=='kecamatan') $data=dw_get_api_kecamatan($id);
        elseif($type=='kelurahan') $data=dw_get_api_desa($id);
    }
    if($data) wp_send_json_success($data);
    else wp_send_json_error(['message'=>'Empty']);
}
add_action('wp_ajax_dw_get_wilayah', 'dw_get_wilayah_handler');
add_action('wp_ajax_nopriv_dw_get_wilayah', 'dw_get_wilayah_handler');

// ==========================================
// 2. ADMIN HANDLERS (SISI SERVER)
// ==========================================

/**
 * A. Verifikasi Paket (Terima/Tolak Topup)
 */
add_action('wp_ajax_dw_process_verifikasi_paket', 'dw_process_verifikasi_paket');
function dw_process_verifikasi_paket() {
    // 1. Cek Keamanan (Nonce harus cocok dengan yang dikirim JS)
    check_ajax_referer('dw_admin_nonce', 'security');
    
    if (!current_user_can('manage_options')) wp_send_json_error('Akses ditolak.');

    global $wpdb;
    $pembelian_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $action_type  = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
    
    $table_pembelian = $wpdb->prefix . 'dw_pembelian_paket';

    if (!$pembelian_id) wp_send_json_error('ID Transaksi tidak valid.');
    
    // Ambil data transaksi
    $transaksi = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pembelian WHERE id = %d", $pembelian_id));
    
    if (!$transaksi) wp_send_json_error('Data tidak ditemukan.');
    if ($transaksi->status != 'pending') wp_send_json_error('Transaksi sudah diproses sebelumnya.');

    if ($action_type === 'approve') {
        // Update status pembelian jadi disetujui
        $wpdb->update($table_pembelian, 
            ['status' => 'disetujui', 'updated_at' => current_time('mysql')], 
            ['id' => $pembelian_id]
        );
        
        // Tambahkan Kuota ke Pedagang
        $id_pedagang = $transaksi->id_pedagang;
        $kuota_plus = intval($transaksi->jumlah_transaksi);
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        
        $pedagang = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id = %d", $id_pedagang));
        if ($pedagang) {
            $kuota_new = intval($pedagang->sisa_kuota) + $kuota_plus;
            
            // Update tabel pedagang
            $wpdb->update($table_pedagang, 
                [
                    'sisa_kuota' => $kuota_new, 
                    'status_paket' => 'active', 
                    'paket_expired' => date('Y-m-d H:i:s', strtotime('+30 days')) // Durasi contoh 30 hari
                ],
                ['id' => $id_pedagang]
            );
            
            // Berikan role ke user WP jika ada
            if (!empty($pedagang->user_id)) {
                $u = get_user_by('ID', $pedagang->user_id);
                if ($u && !in_array('pedagang', (array)$u->roles)) {
                    $u->add_role('pedagang');
                }
            }
        }
        wp_send_json_success('Berhasil! Paket disetujui & aktif.');

    } elseif ($action_type === 'reject') {
        // Update status ditolak
        $wpdb->update($table_pembelian, 
            ['status' => 'ditolak', 'updated_at' => current_time('mysql')], 
            ['id' => $pembelian_id]
        );
        wp_send_json_success('Paket berhasil ditolak.');
    }
    
    wp_send_json_error('Aksi tidak dikenali.');
}

/**
 * B. Verifikasi Pedagang (Dokumen KTP/Selfie)
 */
add_action('wp_ajax_dw_verify_pedagang', 'dw_process_verify_pedagang');
function dw_process_verify_pedagang() {
    check_ajax_referer('dw_admin_nonce', 'security');
    if (!current_user_can('manage_options')) wp_send_json_error('Akses ditolak.');

    global $wpdb;
    $pedagang_id = intval($_POST['pedagang_id']);
    $action_type = sanitize_text_field($_POST['action_type']);
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';

    if ($action_type === 'approve') {
        $wpdb->update($table_pedagang, ['status_verifikasi' => 'terverifikasi'], ['id' => $pedagang_id]);
        wp_send_json_success('Pedagang diverifikasi.');
    } elseif ($action_type === 'reject') {
        $wpdb->update($table_pedagang, ['status_verifikasi' => 'ditolak'], ['id' => $pedagang_id]);
        wp_send_json_success('Pedagang ditolak.');
    }
    wp_send_json_error('Error handling request.');
}

/**
 * C. Bulk Payout Desa
 */
add_action('wp_ajax_dw_process_bulk_payout_desa', 'dw_process_bulk_payout_desa');
function dw_process_bulk_payout_desa() {
    check_ajax_referer('dw_admin_nonce', 'security');
    if (!current_user_can('manage_options')) wp_send_json_error('Akses ditolak.');
    
    global $wpdb;
    $desa_id = intval($_POST['desa_id']);
    
    if (!$desa_id) wp_send_json_error('ID Desa invalid.');
    
    $updated = $wpdb->update(
        "{$wpdb->prefix}dw_payout_ledger", 
        array('status' => 'paid', 'paid_at' => current_time('mysql')), 
        array('payable_to_type' => 'desa', 'payable_to_id' => $desa_id, 'status' => 'unpaid')
    );
    
    if ($updated !== false) {
        wp_send_json_success('Payout berhasil ditandai LUNAS.');
    } else {
        wp_send_json_error('Gagal update atau data sudah lunas.');
    }
}
?>