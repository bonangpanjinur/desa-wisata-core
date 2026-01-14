<?php
/**
 * AJAX Handlers
 * Path: includes/ajax-handlers.php
 * * Menangani semua request AJAX dari dashboard admin dengan validasi Nonce & Capability.
 * * UPDATE: Merged fitur CRUD Pedagang (Lama) + Hitung Ongkir & Lokasi (Baru) + Dashboard Stats (Fase 5.3).
 * @package DesaWisataCore
 * @version 2.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle simpan/update pedagang via AJAX
 */
function dw_ajax_save_pedagang() {
    // 1. Security Check: Validasi Nonce
    check_ajax_referer('dw_nonce_action', 'security');

    // 2. Security Check: Validasi User Capability
    if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Anda tidak memiliki izin untuk melakukan aksi ini.']);
    }

    global $wpdb;

    // 3. Sanitasi Input
    $id             = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nama_pemilik   = isset($_POST['nama_pemilik']) ? sanitize_text_field($_POST['nama_pemilik']) : '';
    $nama_toko      = isset($_POST['nama_toko']) ? sanitize_text_field($_POST['nama_toko']) : '';
    $user_id        = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $status         = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
    
    // Validasi data wajib
    if (empty($nama_pemilik) || empty($nama_toko) || empty($user_id)) {
        wp_send_json_error(['message' => 'Data wajib (Nama Pemilik, Nama Toko, User) harus diisi.']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    $data = [
        'user_id'       => $user_id,
        'nama_pemilik'  => $nama_pemilik,
        'nama_toko'     => $nama_toko,
        'status'        => $status,
    ];

    $format = ['%d', '%s', '%s', '%s'];

    // Cek apakah Insert atau Update
    if ($id > 0) {
        // Update
        $updated = $wpdb->update($table_name, $data, ['id' => $id], $format, ['%d']);
        if ($updated !== false) {
            do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
            wp_send_json_success(['message' => 'Data pedagang berhasil diperbarui.']);
        } else {
            wp_send_json_error(['message' => 'Gagal memperbarui data pedagang.']);
        }
    } else {
        // Insert
        $inserted = $wpdb->insert($table_name, $data, $format);
        if ($inserted) {
            do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
            wp_send_json_success(['message' => 'Pedagang baru berhasil ditambahkan.']);
        } else {
            wp_send_json_error(['message' => 'Gagal menambahkan pedagang baru.']);
        }
    }
}
add_action('wp_ajax_dw_save_pedagang', 'dw_ajax_save_pedagang');

/**
 * Handle pencarian user untuk dropdown Select2
 */
function dw_ajax_search_user() {
    // 1. Security Check: Validasi Nonce
    check_ajax_referer('dw_nonce_action', 'security');
    
    // 2. Security Check: Capability
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Gunakan prepare untuk LIKE query yang aman dari SQL Injection
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, display_name, user_email 
         FROM {$wpdb->users} 
         WHERE display_name LIKE %s OR user_email LIKE %s 
         LIMIT %d OFFSET %d",
        $search_like,
        $search_like,
        $limit,
        $offset
    ));

    $results = [];
    foreach ($users as $user) {
        $results[] = [
            'id' => $user->ID,
            'text' => $user->display_name . ' (' . $user->user_email . ')'
        ];
    }

    // Cek apakah masih ada data lagi (pagination)
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->users} WHERE display_name LIKE %s OR user_email LIKE %s",
        $search_like,
        $search_like
    ));
    
    $more = ($offset + $limit) < $count;

    wp_send_json_success([
        'results' => $results,
        'pagination' => ['more' => $more]
    ]);
}
add_action('wp_ajax_dw_search_user', 'dw_ajax_search_user');

/**
 * Get detail pedagang untuk edit modal
 */
function dw_ajax_get_pedagang_detail() {
    // 1. Security Check
    check_ajax_referer('dw_nonce_action', 'security');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID tidak valid.']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    
    // Gunakan prepare untuk keamanan ID
    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);

    if ($data) {
        // Ambil data user terkait
        $user = get_userdata($data['user_id']);
        $data['user_name'] = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'User tidak ditemukan';
        
        wp_send_json_success($data);
    } else {
        wp_send_json_error(['message' => 'Data tidak ditemukan.']);
    }
}
add_action('wp_ajax_dw_get_pedagang_detail', 'dw_ajax_get_pedagang_detail');

/**
 * Verifikasi pedagang
 */
function dw_ajax_verifikasi_pedagang() {
    check_ajax_referer('dw_nonce_action', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Anda tidak memiliki akses.']);
    }

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    $updated = $wpdb->update(
        $table_name,
        ['status' => 'verified'],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        // Kirim notifikasi email atau sistem (Future Phase)
        do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
        wp_send_json_success(['message' => 'Pedagang berhasil diverifikasi.']);
    } else {
        wp_send_json_error(['message' => 'Gagal verifikasi pedagang.']);
    }
}
add_action('wp_ajax_dw_verifikasi_pedagang', 'dw_ajax_verifikasi_pedagang');

/**
 * Tolak pedagang
 */
function dw_ajax_reject_pedagang() {
    check_ajax_referer('dw_nonce_action', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Anda tidak memiliki akses.']);
    }

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    $updated = $wpdb->update(
        $table_name,
        ['status' => 'rejected'],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
        wp_send_json_success(['message' => 'Pengajuan pedagang ditolak.']);
    } else {
        wp_send_json_error(['message' => 'Gagal menolak pedagang.']);
    }
}
add_action('wp_ajax_dw_reject_pedagang', 'dw_ajax_reject_pedagang');

/**
 * Hapus pedagang
 */
function dw_ajax_delete_pedagang() {
    check_ajax_referer('dw_nonce_action', 'security');

    if (!current_user_can('delete_users')) { // Level akses lebih tinggi untuk hapus
        wp_send_json_error(['message' => 'Anda tidak memiliki akses untuk menghapus data.']);
    }

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }

    $table_name = $wpdb->prefix . 'dw_pedagang';
    $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);

    if ($deleted) {
        do_action('dw_data_pedagang_updated'); // TRIGGER CACHE CLEAR
        wp_send_json_success(['message' => 'Data pedagang dihapus.']);
    } else {
        wp_send_json_error(['message' => 'Gagal menghapus data.']);
    }
}
add_action('wp_ajax_dw_delete_pedagang', 'dw_ajax_delete_pedagang');


/**
 * ----------------------------------------------------------------------
 * FASE 4.2 ADDITIONS: Ojek & Location Handlers
 * ----------------------------------------------------------------------
 */

/**
 * AJAX: Hitung Ongkos Kirim Ojek
 * Action: dw_calculate_ongkir
 */
add_action( 'wp_ajax_dw_calculate_ongkir', 'dw_ajax_calculate_ongkir' );
add_action( 'wp_ajax_nopriv_dw_calculate_ongkir', 'dw_ajax_calculate_ongkir' );

function dw_ajax_calculate_ongkir() {
    // Menggunakan dw_nonce_frontend untuk public access
    check_ajax_referer( 'dw_nonce_frontend', 'nonce' );

    $lat_user = isset( $_POST['lat'] ) ? floatval( $_POST['lat'] ) : 0;
    $lon_user = isset( $_POST['lng'] ) ? floatval( $_POST['lng'] ) : 0;
    $pedagang_id = isset( $_POST['pedagang_id'] ) ? intval( $_POST['pedagang_id'] ) : 0;

    if ( empty( $lat_user ) || empty( $lon_user ) || empty( $pedagang_id ) ) {
        wp_send_json_error( [ 'message' => 'Data lokasi tidak lengkap.' ] );
    }

    // Ambil Lokasi Pedagang
    $lat_pedagang = get_user_meta( $pedagang_id, 'latitude', true );
    $lon_pedagang = get_user_meta( $pedagang_id, 'longitude', true );

    if ( ! $lat_pedagang || ! $lon_pedagang ) {
        wp_send_json_error( [ 'message' => 'Lokasi pedagang belum diatur.' ] );
    }

    // Hitung Jarak
    if ( ! class_exists( 'DW_Ojek_Handler' ) ) {
        require_once DW_PLUGIN_DIR . 'includes/classes/class-dw-ojek-handler.php';
    }

    // Menggunakan static methods dari class Ojek Handler yang sudah diupdate
    $distance = DW_Ojek_Handler::calculate_distance( $lat_user, $lon_user, $lat_pedagang, $lon_pedagang );
    
    // Hitung Biaya
    $cost = DW_Ojek_Handler::calculate_shipping_cost( $distance );

    // Cari Ojek Terdekat (Opsional, untuk info ketersediaan)
    // Asumsi get_nearest_ojek sudah diimplementasikan di class DW_Ojek_Handler jika ingin dipakai
    // $available_ojek = DW_Ojek_Handler::get_nearest_ojek( $lat_pedagang, $lon_pedagang, 10 ); 
    // $ojek_found = count( $available_ojek ) > 0;
    $ojek_found = true; // Placeholder sementara

    wp_send_json_success( [
        'distance' => $distance . ' KM',
        'cost' => $cost,
        'cost_formatted' => 'Rp ' . number_format( $cost, 0, ',', '.' ),
        'ojek_available' => $ojek_found,
        'message' => $ojek_found ? 'Ojek tersedia' : 'Tidak ada ojek di sekitar pedagang saat ini'
    ] );
}

/**
 * AJAX: Simpan Lokasi (User/Pedagang)
 * Action: dw_save_location
 */
add_action( 'wp_ajax_dw_save_location', 'dw_ajax_save_location' );

function dw_ajax_save_location() {
    check_ajax_referer( 'dw_vars_nonce', 'nonce' ); // Menggunakan dw_vars dari admin-scripts

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    $user_id = get_current_user_id();
    $lat = isset( $_POST['lat'] ) ? sanitize_text_field( $_POST['lat'] ) : '';
    $lng = isset( $_POST['lng'] ) ? sanitize_text_field( $_POST['lng'] ) : '';

    if ( $lat && $lng ) {
        update_user_meta( $user_id, 'latitude', $lat );
        update_user_meta( $user_id, 'longitude', $lng );
        wp_send_json_success( [ 'message' => 'Lokasi berhasil disimpan.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Koordinat tidak valid.' ] );
    }
}

/**
 * ----------------------------------------------------------------------
 * FASE 5: NOTIFIKASI REAL-TIME (AJAX POLLING)
 * ----------------------------------------------------------------------
 */

/**
 * AJAX: Cek Notifikasi Baru (Polling)
 * Action: dw_check_notifications
 */
add_action( 'wp_ajax_dw_check_notifications', 'dw_ajax_check_notifications' );

function dw_ajax_check_notifications() {
    check_ajax_referer( 'dw_vars_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error();
    }

    $user_id = get_current_user_id();
    $role = '';
    
    // Tentukan Role (Pedagang atau Ojek)
    $user = wp_get_current_user();
    if ( in_array( 'pedagang', (array) $user->roles ) ) {
        $role = 'pedagang';
    } elseif ( in_array( 'dw_ojek', (array) $user->roles ) ) {
        $role = 'ojek';
    } else {
        wp_send_json_success( [ 'has_notification' => false ] ); // User biasa tidak perlu polling berat
    }

    global $wpdb;
    $has_notification = false;
    $message = '';
    $type = ''; // 'order', 'chat', 'system'

    if ( $role === 'pedagang' ) {
        // Cek Order Baru (Status: pending / menunggu_konfirmasi) dalam 1 menit terakhir
        // Atau gunakan flag 'is_read' di tabel notifikasi jika ada. 
        // Sederhananya, kita cek order pending yang created_at nya baru saja.
        
        // Strategi Polling Efisien: Client kirim 'last_check_time'.
        $last_check = isset( $_POST['last_check'] ) ? sanitize_text_field( $_POST['last_check'] ) : date( 'Y-m-d H:i:s', strtotime( '-1 minute' ) );
        
        // Query Pesanan Pedagang Baru
        // Asumsi tabel: dw_transaksi_produk (Belum ada di snippet sebelumnya, pakai logic umum dulu)
        // Kita pakai contoh sederhana: Cek tabel dw_transaksi (ojek) atau log notifikasi
        
        // TODO: Sesuaikan dengan tabel pesanan produk Anda nanti.
        // Simulasi: Cek apakah ada notifikasi di tabel logs yang belum dibaca (jika ada fitur unread)
        // Untuk demo, kita return false dulu agar tidak error SQL.
        $has_notification = false; 

    } elseif ( $role === 'ojek' ) {
        // Cek Order Ojek Baru (Status: menunggu_driver / nego)
        // Ojek perlu tahu jika ada order di kecamatannya
        
        $driver_profile = $wpdb->get_row( $wpdb->prepare( "SELECT api_kecamatan_id, status_kerja FROM {$wpdb->prefix}dw_ojek WHERE id_user = %d", $user_id ) );
        
        if ( $driver_profile && $driver_profile->status_kerja === 'online' ) {
            $last_check = isset( $_POST['last_check'] ) ? sanitize_text_field( $_POST['last_check'] ) : date( 'Y-m-d H:i:s', strtotime( '-30 seconds' ) );
            
            // Cari transaksi yang dibuat SETELAH last_check di kecamatan driver
            $sql = "SELECT COUNT(id) FROM {$wpdb->prefix}dw_transaksi 
                    WHERE status_transaksi IN ('menunggu_driver', 'nego') 
                    AND kecamatan = %s 
                    AND updated_at > %s";
            
            $count = $wpdb->get_var( $wpdb->prepare( $sql, $driver_profile->api_kecamatan_id, $last_check ) );
            
            if ( $count > 0 ) {
                $has_notification = true;
                $message = "Ada $count order ojek baru di sekitarmu!";
                $type = 'order_ojek';
            }
        }
    }

    wp_send_json_success( [
        'has_notification' => $has_notification,
        'message' => $message,
        'type' => $type,
        'server_time' => current_time( 'mysql' )
    ] );
}

/**
 * ----------------------------------------------------------------------
 * FASE 5.3: DASHBOARD DATA HANDLERS
 * ----------------------------------------------------------------------
 */

/**
 * Get Dashboard Stats (Pedagang)
 */
add_action('wp_ajax_dw_get_dashboard_stats', 'dw_ajax_get_dashboard_stats');
function dw_ajax_get_dashboard_stats() {
    check_ajax_referer('dw_nonce_frontend', 'nonce'); // Menggunakan nonce frontend

    if (!is_user_logged_in()) {
        wp_send_json_error();
    }

    $user_id = get_current_user_id();
    global $wpdb;

    // Default values
    $stats = [
        'sales' => 'Rp 0',
        'orders' => 0,
        'products' => 0
    ];

    if (dw_is_pedagang()) {
        // 1. Total Penjualan (Pesanan Selesai)
        // Sesuaikan nama tabel dengan skema Anda (misal: dw_transaksi_produk)
        // Untuk contoh ini kita gunakan dummy query atau tabel ojek jika pedagang merangkap ojek
        // $total_sales = $wpdb->get_var(...) 
        $stats['sales'] = 'Rp ' . number_format(0, 0, ',', '.'); 

        // 2. Pesanan Baru (Pending)
        // $new_orders = $wpdb->get_var(...)
        $stats['orders'] = 0;

        // 3. Produk Aktif (Post Type: produk_desa)
        $product_count = count_user_posts($user_id, 'produk_desa', true); // Hanya yang publish
        $stats['products'] = $product_count;

    } elseif (in_array('dw_ojek', (array) wp_get_current_user()->roles)) {
        // Stats untuk Ojek
        $ojek_id = $user_id;
        
        // Hitung Trip Selesai
        $completed_trips = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM {$wpdb->prefix}dw_transaksi WHERE id_ojek = %d AND status_transaksi = 'selesai'", 
            $ojek_id
        ));
        
        // Hitung Pendapatan (Total Transaksi)
        $earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_transaksi) FROM {$wpdb->prefix}dw_transaksi WHERE id_ojek = %d AND status_transaksi = 'selesai'", 
            $ojek_id
        ));

        // Kuota Sisa
        $quota = get_user_meta($user_id, 'dw_ojek_quota', true);

        $stats['sales'] = 'Rp ' . number_format((int)$earnings, 0, ',', '.'); // Label Sales jadi Pendapatan
        $stats['orders'] = (int)$completed_trips; // Label Orders jadi Trip Selesai
        $stats['products'] = (int)$quota; // Label Products jadi Sisa Kuota
    }

    wp_send_json_success($stats);
}

/**
 * Get Recent Transactions HTML
 */
add_action('wp_ajax_dw_get_recent_transactions', 'dw_ajax_get_recent_transactions');
function dw_ajax_get_recent_transactions() {
    check_ajax_referer('dw_nonce_frontend', 'nonce');
    
    if (!is_user_logged_in()) wp_send_json_error();

    $user_id = get_current_user_id();
    global $wpdb;
    
    // Query Transaksi Terakhir (Limit 5)
    // Sesuaikan query dengan role user (Pembeli lihat belanjanya, Pedagang lihat jualannya)
    
    // Contoh Query Generik ke tabel dw_transaksi (Ojek/Paket)
    $table = $wpdb->prefix . 'dw_transaksi';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE id_pembeli = %d OR id_ojek = %d ORDER BY created_at DESC LIMIT 5",
        $user_id, $user_id
    ));

    if (empty($results)) {
        wp_send_json_success(['html' => '']);
        return;
    }

    ob_start();
    foreach ($results as $row) {
        // Status Badge logic
        $status_class = 'dw-badge-info';
        if ($row->status_transaksi == 'selesai') $status_class = 'dw-badge-success';
        if ($row->status_transaksi == 'batal') $status_class = 'dw-badge-danger';
        
        echo '<tr>';
        echo '<td>#' . esc_html($row->kode_unik) . '</td>';
        echo '<td>' . date_i18n('d M Y, H:i', strtotime($row->created_at)) . '</td>';
        echo '<td>Rp ' . number_format($row->total_transaksi, 0, ',', '.') . '</td>';
        echo '<td><span class="dw-badge ' . $status_class . '">' . esc_html(strtoupper($row->status_transaksi)) . '</span></td>';
        echo '<td><a href="#" class="dw-btn dw-btn-outline" style="padding: 4px 8px; font-size: 12px;">Detail</a></td>';
        echo '</tr>';
    }
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}