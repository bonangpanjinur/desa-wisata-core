<?php
/**
 * Class DW_Ojek_Handler
 * Path: includes/classes/class-dw-ojek-handler.php
 * Description: Menangani logika bisnis Ojek: Tarif, Saldo (Wallet), Transaksi, dan Profil Driver.
 * Updated: Menghapus sistem 'Kuota' dan menggantinya full dengan 'Saldo Wallet'.
 * @package DesaWisataCore
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Ojek_Handler {

    /**
     * Inisialisasi Hook & Action
     */
    public static function init() {
        // --- 1. AJAX Actions untuk Alur Transaksi & Nego ---
        add_action('wp_ajax_dw_ojek_update_status', [__CLASS__, 'ajax_update_work_status']); // Driver ganti status kerja
        add_action('wp_ajax_dw_ojek_submit_bid', [__CLASS__, 'ajax_driver_submit_bid']); // Driver tawar harga
        add_action('wp_ajax_dw_passenger_nego', [__CLASS__, 'ajax_passenger_nego']); // Penumpang nego balik
        add_action('wp_ajax_dw_passenger_accept', [__CLASS__, 'ajax_passenger_accept']); // Penumpang setuju harga
        add_action('wp_ajax_dw_ojek_pickup', [__CLASS__, 'ajax_driver_pickup']); // Driver menjemput
        add_action('wp_ajax_dw_ojek_complete', [__CLASS__, 'ajax_driver_complete']); // Pesanan selesai

        // --- 2. Manajemen Profil Ojek ---
        add_action('personal_options_update', [__CLASS__, 'save_ojek_profile_data']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_ojek_profile_data']);

        // --- 3. Bonus & Settings (Saldo Awal) ---
        add_action('set_user_role', [__CLASS__, 'check_new_ojek_bonus'], 10, 3);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * =========================================================================
     * BAGIAN 1: RATE MANAGEMENT (TARIF WILAYAH)
     * =========================================================================
     */

    public static function get_rate_by_location($kabupaten_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek_rates';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return null;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE api_kabupaten_id = %s AND is_active = 1",
            $kabupaten_id
        ));
    }

    public static function save_rate($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek_rates';

        $defaults = [
            'base_fare' => 5000,
            'price_per_km' => 2000,
            'min_distance_km' => 1,
            'commission_percent' => 10,
            'is_active' => 1
        ];
        $data = wp_parse_args($data, $defaults);
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE api_kabupaten_id = %s", $data['api_kabupaten_id']));

        if ($exists) {
            return $wpdb->update($table_name, $data, ['id' => $exists]);
        } else {
            return $wpdb->insert($table_name, $data);
        }
    }

    public static function delete_rate($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'dw_ojek_rates', ['id' => $id]);
    }

    /**
     * Menghitung estimasi biaya perjalanan.
     */
    public static function calculate_fare($distance_km, $kabupaten_id) {
        $rate = self::get_rate_by_location($kabupaten_id);

        if (!$rate) {
            $base_fare = 7000;
            $price_per_km = 2500;
            $min_dist = 2;
        } else {
            $base_fare = floatval($rate->base_fare);
            $price_per_km = floatval($rate->price_per_km);
            $min_dist = floatval($rate->min_distance_km);
        }

        if ($distance_km <= $min_dist) {
            $fare_distance = 0;
            $total = $base_fare;
        } else {
            $fare_distance = ($distance_km - $min_dist) * $price_per_km;
            $total = $base_fare + $fare_distance;
        }

        $total = ceil($total / 500) * 500; // Pembulatan

        return [
            'base_fare' => $base_fare,
            'distance_cost' => $fare_distance,
            'total_fare' => $total,
            'currency' => 'IDR',
            'rate_info' => $rate
        ];
    }

    /**
     * Hitung komisi aplikasi (potongan saldo driver).
     */
    public static function calculate_commission($order_amount, $kabupaten_id = null) {
        $commission_percent = get_option('dw_ojek_global_commission', 10); // Default 10%

        if ($kabupaten_id) {
            $rate = self::get_rate_by_location($kabupaten_id);
            if ($rate && isset($rate->commission_percent) && $rate->commission_percent > 0) {
                $commission_percent = floatval($rate->commission_percent);
            }
        }

        return ($order_amount * $commission_percent) / 100;
    }

    /**
     * Cek Saldo Driver untuk syarat On-Bid
     */
    public static function is_driver_eligible_to_bid($driver_user_id) {
        // 1. Ambil setting minimum saldo
        $min_balance = (int) get_option('dw_ojek_min_balance', 20000);

        // 2. Cek Saldo via DW_Wallet
        if (!class_exists('DW_Wallet')) {
            // Jika modul wallet belum aktif/error, blokir sementara
            return new WP_Error('wallet_error', 'Sistem dompet belum aktif. Hubungi admin.');
        }

        $wallet = new DW_Wallet($driver_user_id);
        $current_balance = $wallet->get_balance();

        if ($current_balance < $min_balance) {
            return new WP_Error('insufficient_balance', 'Saldo dompet kurang dari Rp ' . number_format($min_balance, 0, ',', '.') . '. Topup untuk mulai bekerja.');
        }

        return true;
    }

    /**
     * =========================================================================
     * BAGIAN 2: MANAJEMEN DATA DRIVER
     * =========================================================================
     */

    public static function save_ojek_profile_data($user_id) {
        if (!current_user_can('edit_user', $user_id)) return;
        $user = get_userdata($user_id);
        if (!in_array('dw_ojek', (array) $user->roles)) return;

        global $wpdb;
        $table = $wpdb->prefix . 'dw_ojek';

        $data = [
            'nama_lengkap' => sanitize_text_field(($_POST['first_name'] ?? '') . ' ' . ($_POST['last_name'] ?? '')),
            'no_hp'        => sanitize_text_field($_POST['dw_no_hp'] ?? ''),
            'plat_nomor'   => sanitize_text_field($_POST['dw_motor_plat'] ?? ''),
            'merk_motor'   => sanitize_text_field($_POST['dw_motor_merk'] ?? ''),
            'api_provinsi_id'  => sanitize_text_field($_POST['dw_alamat_provinsi_id'] ?? ''),
            'api_kabupaten_id' => sanitize_text_field($_POST['dw_alamat_kota_id'] ?? ''),
            'api_kecamatan_id' => sanitize_text_field($_POST['dw_alamat_kecamatan_id'] ?? ''),
            'alamat_domisili'  => sanitize_textarea_field($_POST['dw_alamat_lengkap'] ?? ''),
            'latitude'         => sanitize_text_field($_POST['dw_latitude'] ?? ''),
            'longitude'        => sanitize_text_field($_POST['dw_longitude'] ?? ''),
            'updated_at'       => current_time('mysql')
        ];

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id_user = %d", $user_id));

        if ($exists) {
            $wpdb->update($table, $data, ['id_user' => absint($user_id)]);
        } else {
            $data['id_user'] = absint($user_id);
            $data['status_pendaftaran'] = 'menunggu';
            $data['status_kerja'] = 'offline';
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }

        if (isset($_POST['dw_ojek_status_kerja'])) {
            update_user_meta($user_id, 'dw_ojek_status_kerja', sanitize_text_field($_POST['dw_ojek_status_kerja']));
        }
        
        if (!empty($data['latitude'])) update_user_meta($user_id, 'latitude', $data['latitude']);
        if (!empty($data['longitude'])) update_user_meta($user_id, 'longitude', $data['longitude']);
        if (!empty($data['api_kecamatan_id'])) update_user_meta($user_id, 'api_kecamatan_id', $data['api_kecamatan_id']);
    }

    /**
     * AJAX: Driver Ubah Status Online/Offline
     */
    public static function ajax_update_work_status() {
        check_ajax_referer('dw_nonce', 'nonce');
        $user_id = get_current_user_id();
        $status = sanitize_text_field($_POST['status']); // 'online' / 'offline'

        // Validasi: Cek Saldo jika ingin Online
        if ($status === 'online') {
            $eligible = self::is_driver_eligible_to_bid($user_id);
            if (is_wp_error($eligible)) {
                wp_send_json_error(['message' => $eligible->get_error_message()]);
            }
        }

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'dw_ojek', 
            ['status_kerja' => $status], 
            ['id_user' => absint($user_id)]
        );
        update_user_meta($user_id, 'dw_ojek_status_kerja', $status);

        wp_send_json_success(['status' => $status, 'message' => 'Status: ' . strtoupper($status)]);
    }

    /**
     * =========================================================================
     * BAGIAN 3: CORE TRANSAKSI & NEGO
     * =========================================================================
     */

    public static function create_ride_request($user_id, $data) {
        global $wpdb;
        
        $kabupaten_id = $data['kabupaten_id'] ?? '';
        $fare_info = self::calculate_fare($data['distance'], $kabupaten_id);

        $ojek_data = [
            'pickup' => $data['pickup'],
            'dropoff' => $data['dropoff'],
            'distance_km' => $data['distance'],
            'estimated_fare' => $fare_info['total_fare'],
            'nego_history' => []
        ];

        $insert_data = [
            'kode_unik' => 'OJK-' . strtoupper(wp_generate_password(6, false)),
            'id_pembeli' => absint($user_id),
            'status_transaksi' => 'menunggu_driver',
            'ojek_data' => json_encode($ojek_data),
            'alamat_lengkap' => sanitize_textarea_field($data['pickup']['address']),
            'kecamatan' => sanitize_text_field($data['pickup']['kecamatan_id'] ?? ''),
            'kabupaten' => sanitize_text_field($kabupaten_id),
            'total_transaksi' => 0, 
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . 'dw_transaksi', $insert_data);
        return $wpdb->insert_id;
    }

    public static function ajax_driver_submit_bid() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        
        $driver_id = get_current_user_id();
        
        // Cek Saldo sebelum nge-bid (Safety Check)
        $eligible = self::is_driver_eligible_to_bid($driver_id);
        if (is_wp_error($eligible)) wp_send_json_error(['message' => $eligible->get_error_message()]);

        $trx_id = absint($_POST['transaction_id']);
        $price = absint($_POST['price']); 

        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        if (!$trx) wp_send_json_error(['message' => 'Order tidak ditemukan']);

        if ($trx->status_transaksi !== 'menunggu_driver' && $trx->status_transaksi !== 'nego') {
            wp_send_json_error(['message' => 'Order sudah diambil atau dibatalkan.']);
        }

        $ojek_data = json_decode($trx->ojek_data, true);
        
        $ojek_data['nego_history'][] = [
            'actor' => 'driver',
            'driver_id' => $driver_id,
            'driver_name' => wp_get_current_user()->display_name,
            'price' => $price,
            'time' => current_time('mysql')
        ];
        
        $ojek_data['driver_candidate_id'] = $driver_id;

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'penawaran_driver',
            'ojek_data' => json_encode($ojek_data),
            'total_transaksi' => $price
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Penawaran dikirim.']);
    }

    public static function ajax_passenger_nego() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;

        $trx_id = absint($_POST['transaction_id']);
        $price = absint($_POST['price']);

        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        
        $ojek_data = json_decode($trx->ojek_data, true);
        $ojek_data['nego_history'][] = [
            'actor' => 'passenger',
            'price' => $price,
            'time' => current_time('mysql')
        ];

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'nego',
            'ojek_data' => json_encode($ojek_data),
            'total_transaksi' => $price
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Tawaran dikirim balik.']);
    }

    public static function ajax_passenger_accept() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;

        $trx_id = absint($_POST['transaction_id']);
        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        $ojek_data = json_decode($trx->ojek_data, true);
        
        $driver_id = $ojek_data['driver_candidate_id'] ?? 0;
        if (!$driver_id) wp_send_json_error(['message' => 'Driver tidak valid']);

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'id_ojek' => absint($driver_id),
            'status_transaksi' => 'menunggu_penjemputan',
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Deal!']);
    }

    public static function ajax_driver_pickup() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        $trx_id = absint($_POST['transaction_id']);

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'dalam_perjalanan',
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Perjalanan dimulai.']);
    }

    /**
     * 6. DRIVER COMPLETE (Potong Saldo)
     */
    public static function ajax_driver_complete() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        
        $trx_id = absint($_POST['transaction_id']);
        $driver_id = get_current_user_id();

        // 1. Ambil Data Transaksi
        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        if (!$trx) wp_send_json_error(['message' => 'Order tidak valid']);

        $total_deal = floatval($trx->total_transaksi);
        $kabupaten_id = $trx->kabupaten ?? ''; 

        // 2. Hitung Komisi (Potongan)
        $commission_amount = self::calculate_commission($total_deal, $kabupaten_id);

        // 3. Potong Saldo Wallet Driver
        if (class_exists('DW_Wallet')) {
            $wallet = new DW_Wallet($driver_id);
            // Deduct balance: (Jumlah, Tipe, Catatan)
            $deducted = $wallet->deduct_balance($commission_amount, 'commission', "Komisi Ojek #" . $trx->kode_unik);
            
            if (is_wp_error($deducted)) {
                // Log error tapi jangan gagalkan transaksi user (biar tidak stuck)
                // Atau bisa dibuat strict: wp_send_json_error(...)
                error_log("Gagal potong komisi ojek ID $driver_id: " . $deducted->get_error_message());
            }
        }

        // 4. Update Status Transaksi
        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'selesai',
            'status_pembayaran' => 'lunas',
            'platform_fee' => $commission_amount, 
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        wp_send_json_success([
            'message' => 'Order selesai.', 
            'commission' => $commission_amount
        ]);
    }

    /**
     * =========================================================================
     * BAGIAN 4: SETTINGS & BONUS AWAL (SALDO)
     * =========================================================================
     */

    public static function register_settings() {
        // Ganti setting 'free_quota' jadi 'welcome_balance'
        register_setting('dw_general_options', 'dw_ojek_welcome_balance', ['type' => 'integer', 'default' => 10000, 'sanitize_callback' => 'absint']);
        
        add_settings_section('dw_ojek_settings', 'Pengaturan Ojek Desa', null, 'general');
        add_settings_field('dw_ojek_welcome_balance', 'Bonus Saldo Awal Ojek', function() {
            $val = get_option('dw_ojek_welcome_balance', 10000);
            echo 'Rp <input type="number" name="dw_ojek_welcome_balance" value="' . esc_attr($val) . '" class="regular-text">';
            echo '<p class="description">Saldo gratis yang diberikan ke dompet driver saat akun disetujui.</p>';
        }, 'general', 'dw_ojek_settings');
    }

    /**
     * Berikan Saldo Bonus ke Driver Baru
     */
    public static function check_new_ojek_bonus($user_id, $role, $old_roles) {
        if ($role === 'dw_ojek') {
            $has_received = get_user_meta($user_id, '_dw_ojek_welcome_bonus_received', true);
            
            if (!$has_received && class_exists('DW_Wallet')) {
                $bonus_amount = get_option('dw_ojek_welcome_balance', 10000);
                if ($bonus_amount > 0) {
                    $wallet = new DW_Wallet($user_id);
                    // Topup/Credit: (Jumlah, Tipe, Catatan)
                    // Asumsi method credit_balance atau topup ada di Class Wallet.
                    // Jika methodnya 'top_up', sesuaikan. Di sini pakai generic 'credit_balance'
                    // Kalau di class-dw-wallet.php pakai method lain, harap sesuaikan.
                    $wallet->credit_balance($bonus_amount, 'bonus', 'Bonus Pendaftaran Ojek');
                    
                    update_user_meta($user_id, '_dw_ojek_welcome_bonus_received', 1);
                }
            }
        }
    }

    /**
     * =========================================================================
     * BAGIAN 5: HELPER
     * =========================================================================
     */

    public static function get_nearby_requests($kecamatan_id) {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}dw_transaksi 
                WHERE (status_transaksi = 'menunggu_driver' OR status_transaksi = 'nego') 
                AND kecamatan = %s 
                AND (id_ojek IS NULL OR id_ojek = 0)
                ORDER BY created_at DESC LIMIT 20";
        
        return $wpdb->get_results($wpdb->prepare($sql, sanitize_text_field($kecamatan_id)));
    }
}