<?php
/**
 * Class DW_Ojek_Handler
 * Path: includes/classes/class-dw-ojek-handler.php
 * * "Otak" Logika Ojek Desa:
 * 1. Manajemen Profil & Status Driver (Online/Offline)
 * 2. Sistem Kuota & Bonus Pendaftaran
 * 3. Alur Transaksi Lengkap (Request -> Bid -> Nego -> Deal -> Trip -> Selesai)
 * 4. Algoritma Hitung Jarak & Biaya
 * * @version 2.8.1 (Added Map Coordinate Support)
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Ojek_Handler {

    /**
     * Inisialisasi Hook & Action
     */
    public static function init() {
        // --- 1. AJAX Actions untuk Alur Transaksi & Nego (Real-time Flow) ---
        add_action('wp_ajax_dw_ojek_update_status', [__CLASS__, 'ajax_update_work_status']); // Driver ganti status kerja
        add_action('wp_ajax_dw_ojek_submit_bid', [__CLASS__, 'ajax_driver_submit_bid']); // Driver tawar harga
        add_action('wp_ajax_dw_passenger_nego', [__CLASS__, 'ajax_passenger_nego']); // Penumpang nego balik
        add_action('wp_ajax_dw_passenger_accept', [__CLASS__, 'ajax_passenger_accept']); // Penumpang setuju harga
        add_action('wp_ajax_dw_ojek_pickup', [__CLASS__, 'ajax_driver_pickup']); // Driver menjemput
        add_action('wp_ajax_dw_ojek_complete', [__CLASS__, 'ajax_driver_complete']); // Pesanan selesai

        // --- 2. Manajemen Profil Ojek ---
        // Sinkronisasi data user WP ke tabel khusus `wp_dw_ojek`
        add_action('personal_options_update', [__CLASS__, 'save_ojek_profile_data']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_ojek_profile_data']);

        // --- 3. Bonus & Settings ---
        // Berikan kuota gratis saat user baru diverifikasi jadi Ojek
        add_action('set_user_role', [__CLASS__, 'check_new_ojek_bonus'], 10, 3);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * =========================================================================
     * BAGIAN 1: SETTINGS & SISTEM KUOTA (MONETISASI)
     * =========================================================================
     */

    public static function register_settings() {
        register_setting('dw_general_options', 'dw_ojek_free_quota_amount', ['type' => 'integer', 'default' => 10, 'sanitize_callback' => 'absint']);
        
        add_settings_section('dw_ojek_settings', 'Pengaturan Ojek Desa', null, 'general');
        add_settings_field('dw_ojek_free_quota_amount', 'Bonus Kuota Ojek Baru', function() {
            $val = get_option('dw_ojek_free_quota_amount', 10);
            echo '<input type="number" name="dw_ojek_free_quota_amount" value="' . esc_attr($val) . '" class="small-text"> Transaksi';
            echo '<p class="description">Jumlah kuota gratis otomatis yang diberikan saat pendaftaran driver disetujui.</p>';
        }, 'general', 'dw_ojek_settings');
    }

    /**
     * Cek bonus saat role berubah jadi 'dw_ojek'
     */
    public static function check_new_ojek_bonus($user_id, $role, $old_roles) {
        if ($role === 'dw_ojek') {
            $has_received = get_user_meta($user_id, '_dw_ojek_welcome_bonus_received', true);
            if (!$has_received) {
                $free_quota = get_option('dw_ojek_free_quota_amount', 10);
                self::add_quota($user_id, $free_quota, 'bonus', 'Bonus Pendaftaran Ojek Baru');
                update_user_meta($user_id, '_dw_ojek_welcome_bonus_received', 1);
            }
        }
    }

    /**
     * Tambah Kuota (Topup / Bonus)
     */
    public static function add_quota($user_id, $amount, $type = 'topup', $description = '') {
        global $wpdb;
        $amount = absint($amount);
        $current = (int) get_user_meta($user_id, 'dw_ojek_quota', true);
        update_user_meta($user_id, 'dw_ojek_quota', $current + $amount);

        // Catat Log
        $wpdb->insert($wpdb->prefix . 'dw_quota_logs', [
            'user_id' => absint($user_id),
            'quota_change' => $amount,
            'type' => sanitize_text_field($type),
            'description' => sanitize_text_field($description),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Kurangi Kuota (Saat ambil order)
     */
    public static function deduct_quota($user_id, $amount = 1, $description = 'Ambil Order') {
        global $wpdb;
        $amount = absint($amount);
        $current = (int) get_user_meta($user_id, 'dw_ojek_quota', true);
        
        if ($current < $amount) return false; // Gagal jika kuota kurang

        $new = $current - $amount;
        update_user_meta($user_id, 'dw_ojek_quota', $new);

        $wpdb->insert($wpdb->prefix . 'dw_quota_logs', [
            'user_id' => absint($user_id),
            'quota_change' => -1 * $amount,
            'type' => 'usage_ojek',
            'description' => sanitize_text_field($description),
            'created_at' => current_time('mysql')
        ]);

        // Jika habis, paksa offline
        if ($new <= 0) {
            $wpdb->update($wpdb->prefix . 'dw_ojek', ['status_kerja' => 'offline'], ['id_user' => absint($user_id)]);
            update_user_meta($user_id, 'dw_ojek_status_kerja', 'offline');
        }

        return true;
    }

    /**
     * =========================================================================
     * BAGIAN 2: MANAJEMEN DATA DRIVER (SYNC PROFIL + LOKASI MAPS)
     * =========================================================================
     */

    public static function save_ojek_profile_data($user_id) {
        if (!current_user_can('edit_user', $user_id)) return;
        
        $user = get_userdata($user_id);
        if (!in_array('dw_ojek', (array) $user->roles)) return;

        global $wpdb;
        $table = $wpdb->prefix . 'dw_ojek';

        // Mapping Data dari Form Profil (termasuk Koordinat Maps)
        $data = [
            'nama_lengkap' => sanitize_text_field($_POST['first_name'] . ' ' . $_POST['last_name']),
            'no_hp'        => sanitize_text_field($_POST['dw_no_hp'] ?? ''),
            'plat_nomor'   => sanitize_text_field($_POST['dw_motor_plat'] ?? ''),
            'merk_motor'   => sanitize_text_field($_POST['dw_motor_merk'] ?? ''),
            
            // Lokasi Wilayah (Penting untuk filter order)
            'api_provinsi_id'  => sanitize_text_field($_POST['dw_alamat_provinsi_id'] ?? ''),
            'api_kabupaten_id' => sanitize_text_field($_POST['dw_alamat_kota_id'] ?? ''),
            'api_kecamatan_id' => sanitize_text_field($_POST['dw_alamat_kecamatan_id'] ?? ''),
            'alamat_domisili'  => sanitize_textarea_field($_POST['dw_alamat_lengkap'] ?? ''),
            
            // [BARU] Koordinat Peta (Latitude & Longitude)
            // Input ini diisi otomatis oleh JS Map Picker (Leaflet)
            'latitude'         => sanitize_text_field($_POST['dw_latitude'] ?? ''),
            'longitude'        => sanitize_text_field($_POST['dw_longitude'] ?? ''),
            
            'updated_at'       => current_time('mysql')
        ];

        // Cek Insert atau Update
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id_user = %d", $user_id));

        if ($exists) {
            $wpdb->update($table, $data, ['id_user' => absint($user_id)]);
        } else {
            $data['id_user'] = absint($user_id);
            $data['status_pendaftaran'] = 'menunggu'; // Default butuh verifikasi admin
            $data['status_kerja'] = 'offline';
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }

        // Simpan meta status kerja jika ada post
        if (isset($_POST['dw_ojek_status_kerja'])) {
            update_user_meta($user_id, 'dw_ojek_status_kerja', sanitize_text_field($_POST['dw_ojek_status_kerja']));
        }
        
        // Simpan Lat/Long ke User Meta juga (untuk backup/akses cepat)
        if (!empty($data['latitude'])) update_user_meta($user_id, 'latitude', $data['latitude']);
        if (!empty($data['longitude'])) update_user_meta($user_id, 'longitude', $data['longitude']);
    }

    /**
     * AJAX: Driver Ubah Status Online/Offline
     */
    public static function ajax_update_work_status() {
        check_ajax_referer('dw_nonce', 'nonce');
        $user_id = get_current_user_id();
        $status = sanitize_text_field($_POST['status']); // 'online' / 'offline'

        // Validasi: Tidak bisa online jika kuota 0
        if ($status === 'online') {
            $quota = (int) get_user_meta($user_id, 'dw_ojek_quota', true);
            if ($quota <= 0) {
                wp_send_json_error(['message' => 'Kuota habis. Silakan top up paket ojek untuk mulai bekerja.']);
            }
        }

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'dw_ojek', 
            ['status_kerja' => $status], 
            ['id_user' => absint($user_id)]
        );
        update_user_meta($user_id, 'dw_ojek_status_kerja', $status);

        wp_send_json_success(['status' => $status, 'message' => 'Status kerja diperbarui: ' . strtoupper($status)]);
    }

    /**
     * =========================================================================
     * BAGIAN 3: CORE TRANSAKSI & NEGO (COMPLEX FLOW)
     * =========================================================================
     */

    /**
     * 1. CREATE REQUEST: User membuat pesanan baru
     * Dipanggil oleh Frontend Form atau Checkout
     */
    public static function create_ride_request($user_id, $data) {
        global $wpdb;
        
        // Struktur JSON data teknis
        $ojek_data = [
            'pickup' => $data['pickup'],     // {lat, lng, address, note}
            'dropoff' => $data['dropoff'],   // {lat, lng, address, note}
            'distance_km' => $data['distance'],
            'nego_history' => []             // Log tawar menawar
        ];

        $insert_data = [
            'kode_unik' => 'OJK-' . strtoupper(wp_generate_password(6, false)),
            'id_pembeli' => absint($user_id),
            'status_transaksi' => 'menunggu_driver', // Status awal
            'ojek_data' => json_encode($ojek_data),
            'alamat_lengkap' => sanitize_textarea_field($data['pickup']['address']),
            'kecamatan' => sanitize_text_field($data['pickup']['kecamatan_id'] ?? ''),
            'total_transaksi' => 0, // Belum ada harga deal
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . 'dw_transaksi', $insert_data);
        return $wpdb->insert_id;
    }

    /**
     * 2. DRIVER BID: Driver mengajukan harga
     */
    public static function ajax_driver_submit_bid() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        
        $driver_id = get_current_user_id();
        $trx_id = absint($_POST['transaction_id']);
        $price = absint($_POST['price']); 

        // Ambil data transaksi
        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        if (!$trx) wp_send_json_error(['message' => 'Order tidak ditemukan']);

        // Cek apakah order masih available
        if ($trx->status_transaksi !== 'menunggu_driver' && $trx->status_transaksi !== 'nego') {
            wp_send_json_error(['message' => 'Maaf, status order sudah berubah.']);
        }

        $ojek_data = json_decode($trx->ojek_data, true);
        
        // Log Nego
        $ojek_data['nego_history'][] = [
            'actor' => 'driver',
            'driver_id' => $driver_id,
            'driver_name' => wp_get_current_user()->display_name,
            'price' => $price,
            'time' => current_time('mysql')
        ];
        
        // Set kandidat driver
        $ojek_data['driver_candidate_id'] = $driver_id;

        // Update DB
        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'penawaran_driver', // Status berubah -> User dapat notif
            'ojek_data' => json_encode($ojek_data),
            'total_transaksi' => $price
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Penawaran Rp ' . number_format($price) . ' dikirim ke penumpang.']);
    }

    /**
     * 3. PASSENGER NEGO: Penumpang menawar balik
     */
    public static function ajax_passenger_nego() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;

        $trx_id = absint($_POST['transaction_id']);
        $price = absint($_POST['price']);

        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        if (!$trx) wp_send_json_error(['message' => 'Order error']);
        
        $ojek_data = json_decode($trx->ojek_data, true);

        $ojek_data['nego_history'][] = [
            'actor' => 'passenger',
            'price' => $price,
            'time' => current_time('mysql')
        ];

        // Kembalikan status ke 'nego' agar driver tahu ada tawaran baru
        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'nego',
            'ojek_data' => json_encode($ojek_data),
            'total_transaksi' => $price
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Tawaran dikirim balik ke driver.']);
    }

    /**
     * 4. PASSENGER ACCEPT: Penumpang setuju -> DEAL
     */
    public static function ajax_passenger_accept() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;

        $trx_id = absint($_POST['transaction_id']);
        
        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        $ojek_data = json_decode($trx->ojek_data, true);
        
        $driver_id = $ojek_data['driver_candidate_id'] ?? 0;
        if (!$driver_id) wp_send_json_error(['message' => 'Driver tidak valid']);

        // Kunci Transaksi dengan Driver ID
        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'id_ojek' => absint($driver_id),
            'status_transaksi' => 'menunggu_penjemputan',
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        // Notifikasi ke Driver bisa ditambahkan di sini (FCM/WA)

        wp_send_json_success(['message' => 'Deal! Driver sedang menuju lokasi Anda.']);
    }

    /**
     * 5. DRIVER PICKUP: Mulai Perjalanan
     */
    public static function ajax_driver_pickup() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        $trx_id = absint($_POST['transaction_id']);

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'dalam_perjalanan',
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Perjalanan dimulai. Hati-hati di jalan!']);
    }

    /**
     * 6. DRIVER COMPLETE: Pesanan Selesai
     */
    public static function ajax_driver_complete() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        $trx_id = absint($_POST['transaction_id']);
        $driver_id = get_current_user_id();

        // Potong Kuota Driver (Biaya Platform)
        $deducted = self::deduct_quota($driver_id, 1, 'Komisi Order #' . $trx_id);
        
        if (!$deducted) {
            // Edge case: Selesaikan saja tapi catat hutang (opsional)
        }

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'selesai',
            'status_pembayaran' => 'lunas', // Asumsi Cash on Ride
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Order selesai. Kuota Anda berkurang 1.']);
    }

    /**
     * Helper: Ambil Order Aktif di Sekitar
     * Digunakan oleh halaman dashboard driver untuk mencari orderan
     */
    public static function get_nearby_requests($kecamatan_id) {
        global $wpdb;
        // Cari order yang statusnya masih 'menunggu_driver' atau 'nego' di kecamatan yang sama
        $sql = "SELECT * FROM {$wpdb->prefix}dw_transaksi 
                WHERE (status_transaksi = 'menunggu_driver' OR status_transaksi = 'nego') 
                AND kecamatan = %s 
                AND id_ojek = 0 
                ORDER BY created_at DESC LIMIT 20";
        
        return $wpdb->get_results($wpdb->prepare($sql, sanitize_text_field($kecamatan_id)));
    }

    /**
     * =========================================================================
     * BAGIAN 4: HELPER MATEMATIKA & LOGIKA BIAYA (FASE 4 INTEGRATION)
     * =========================================================================
     */

    /**
     * Hitung Jarak (Haversine Formula)
     * @param float $lat1, $lon1 (Asal)
     * @param float $lat2, $lon2 (Tujuan)
     * @return float Jarak dalam Kilometer (2 desimal)
     */
    public static function calculate_distance( $lat1, $lon1, $lat2, $lon2 ) {
        if ( empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2) ) {
            return 0;
        }

        $earth_radius = 6371; // Radius bumi KM

        $dLat = deg2rad( $lat2 - $lat1 );
        $dLon = deg2rad( $lon2 - $lon1 );

        $a = sin( $dLat / 2 ) * sin( $dLat / 2 ) +
             cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
             sin( $dLon / 2 ) * sin( $dLon / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
        $distance = $earth_radius * $c;

        return round( $distance, 2 );
    }

    /**
     * Hitung Estimasi Ongkir
     * Logika ini digunakan sebagai harga acuan awal sebelum nego.
     */
    public static function calculate_shipping_cost( $distance ) {
        // TODO: Ambil tarif dari database settings (dw_settings_general)
        // Saat ini hardcode untuk stabilitas
        $base_fare = 7000;    // 0 - 2 KM
        $rate_per_km = 2500;  // per KM berikutnya
        $min_distance = 2; 

        if ( $distance <= 0 ) return 0;

        if ( $distance <= $min_distance ) {
            return $base_fare;
        } else {
            $extra_km = $distance - $min_distance;
            $cost = $base_fare + ( ceil($extra_km) * $rate_per_km );
            return (int) $cost;
        }
    }
}