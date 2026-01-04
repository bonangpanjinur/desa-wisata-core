<?php
/**
 * Class DW_Ojek_Handler
 * Menangani logika backend untuk fitur Ojek Desa
 * * UPDATE:
 * - Integrasi Tabel Custom dw_ojek & dw_transaksi
 * - Implementasi Alur Nego (Bid, Counter, Accept)
 * - Sistem Kuota & Bonus Pendaftaran
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Ojek_Handler {

    /**
     * Inisialisasi Hooks
     */
    public static function init() {
        // --- 1. AJAX Actions untuk Alur Transaksi & Nego ---
        add_action('wp_ajax_dw_ojek_update_status', [__CLASS__, 'ajax_update_work_status']); // Driver ON/OFF Bid
        add_action('wp_ajax_dw_ojek_submit_bid', [__CLASS__, 'ajax_driver_submit_bid']); // Driver Ajukan Harga
        add_action('wp_ajax_dw_passenger_nego', [__CLASS__, 'ajax_passenger_nego']); // Penumpang Nego
        add_action('wp_ajax_dw_passenger_accept', [__CLASS__, 'ajax_passenger_accept']); // Penumpang Setuju
        add_action('wp_ajax_dw_ojek_pickup', [__CLASS__, 'ajax_driver_pickup']); // Driver Jemput (Start Trip)
        add_action('wp_ajax_dw_ojek_complete', [__CLASS__, 'ajax_driver_complete']); // Selesai

        // --- 2. Manajemen Profil Ojek ---
        // Simpan data ojek ke tabel `wp_dw_ojek` saat profil diupdate
        add_action('personal_options_update', [__CLASS__, 'save_ojek_profile_data']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_ojek_profile_data']);

        // --- 3. Bonus & Settings ---
        // Hook saat user baru jadi ojek -> Kasih Kuota Gratis
        add_action('set_user_role', [__CLASS__, 'check_new_ojek_bonus'], 10, 3);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * ---------------------------------------------------------
     * SECTION A: SETTINGS & BONUS (KUOTA)
     * ---------------------------------------------------------
     */

    public static function register_settings() {
        register_setting('dw_general_options', 'dw_ojek_free_quota_amount', ['type' => 'integer', 'default' => 10, 'sanitize_callback' => 'absint']);
        
        add_settings_section('dw_ojek_settings', 'Pengaturan Ojek Desa', null, 'general');
        add_settings_field('dw_ojek_free_quota_amount', 'Bonus Kuota Ojek Baru', function() {
            $val = get_option('dw_ojek_free_quota_amount', 10);
            echo '<input type="number" name="dw_ojek_free_quota_amount" value="' . esc_attr($val) . '" class="small-text"> Transaksi';
            echo '<p class="description">Jumlah kuota gratis otomatis saat pendaftaran ojek disetujui.</p>';
        }, 'general', 'dw_ojek_settings');
    }

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

    public static function add_quota($user_id, $amount, $type = 'topup', $description = '') {
        global $wpdb;
        $amount = absint($amount);
        $current = (int) get_user_meta($user_id, 'dw_ojek_quota', true);
        update_user_meta($user_id, 'dw_ojek_quota', $current + $amount);

        $wpdb->insert($wpdb->prefix . 'dw_quota_logs', [
            'user_id' => absint($user_id),
            'quota_change' => $amount,
            'type' => sanitize_text_field($type),
            'description' => sanitize_text_field($description),
            'created_at' => current_time('mysql')
        ]);
    }

    public static function deduct_quota($user_id, $amount = 1, $description = 'Ambil Order') {
        global $wpdb;
        $amount = absint($amount);
        $current = (int) get_user_meta($user_id, 'dw_ojek_quota', true);
        
        if ($current < $amount) return false;

        $new = $current - $amount;
        update_user_meta($user_id, 'dw_ojek_quota', $new);

        $wpdb->insert($wpdb->prefix . 'dw_quota_logs', [
            'user_id' => absint($user_id),
            'quota_change' => -1 * $amount,
            'type' => 'usage_ojek',
            'description' => sanitize_text_field($description),
            'created_at' => current_time('mysql')
        ]);

        if ($new <= 0) {
            // Update status di tabel ojek juga
            $wpdb->update($wpdb->prefix . 'dw_ojek', ['status_kerja' => 'offline'], ['id_user' => absint($user_id)]);
            update_user_meta($user_id, 'dw_ojek_status_kerja', 'offline');
        }

        return true;
    }

    /**
     * ---------------------------------------------------------
     * SECTION B: PROFIL & STATUS KERJA (TABEL dw_ojek)
     * ---------------------------------------------------------
     */

    public static function save_ojek_profile_data($user_id) {
        if (!current_user_can('edit_user', $user_id)) return;
        
        $user = get_userdata($user_id);
        if (!in_array('dw_ojek', (array) $user->roles)) return;

        global $wpdb;
        $table = $wpdb->prefix . 'dw_ojek';

        // Data mapping dari form profil WordPress ke tabel dw_ojek
        $data = [
            'nama_lengkap' => sanitize_text_field($_POST['first_name'] . ' ' . $_POST['last_name']),
            'no_hp'        => sanitize_text_field($_POST['dw_no_hp'] ?? ''),
            'plat_nomor'   => sanitize_text_field($_POST['dw_motor_plat'] ?? ''),
            'merk_motor'   => sanitize_text_field($_POST['dw_motor_merk'] ?? ''),
            // Simpan ID Wilayah untuk kemudahan filter
            'api_provinsi_id'  => sanitize_text_field($_POST['dw_alamat_provinsi_id'] ?? ''),
            'api_kabupaten_id' => sanitize_text_field($_POST['dw_alamat_kota_id'] ?? ''),
            'api_kecamatan_id' => sanitize_text_field($_POST['dw_alamat_kecamatan_id'] ?? ''),
            'alamat_domisili'  => sanitize_textarea_field($_POST['dw_alamat_lengkap'] ?? ''),
            'updated_at'       => current_time('mysql')
        ];

        // Cek apakah data ojek sudah ada
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id_user = %d", $user_id));

        if ($exists) {
            $wpdb->update($table, $data, ['id_user' => absint($user_id)]);
        } else {
            $data['id_user'] = absint($user_id);
            $data['status_pendaftaran'] = 'menunggu'; // Default pending
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }

        // Simpan meta juga untuk compatibilitas
        if (isset($_POST['dw_ojek_status_kerja'])) {
            update_user_meta($user_id, 'dw_ojek_status_kerja', sanitize_text_field($_POST['dw_ojek_status_kerja']));
        }
    }

    public static function ajax_update_work_status() {
        check_ajax_referer('dw_nonce', 'nonce');
        $user_id = get_current_user_id();
        $status = sanitize_text_field($_POST['status']); // 'online' / 'offline'

        // Cek Kuota sebelum Online
        if ($status === 'online') {
            $quota = (int) get_user_meta($user_id, 'dw_ojek_quota', true);
            if ($quota <= 0) {
                wp_send_json_error(['message' => 'Kuota habis. Beli paket dulu.']);
            }
        }

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'dw_ojek', 
            ['status_kerja' => $status], 
            ['id_user' => absint($user_id)]
        );
        update_user_meta($user_id, 'dw_ojek_status_kerja', $status);

        wp_send_json_success(['status' => $status]);
    }

    /**
     * ---------------------------------------------------------
     * SECTION C: TRANSAKSI & NEGO (TABEL dw_transaksi)
     * ---------------------------------------------------------
     */

    // 1. User Membuat Request (Status: menunggu_driver)
    public static function create_ride_request($user_id, $data) {
        global $wpdb;
        
        $ojek_data = [
            'pickup' => $data['pickup'], // {lat, lng, address, note}
            'dropoff' => $data['dropoff'],
            'nego_history' => [] // Array log nego
        ];

        $insert_data = [
            'kode_unik' => 'OJK-' . strtoupper(wp_generate_password(6, false)),
            'id_pembeli' => absint($user_id),
            'status_transaksi' => 'menunggu_driver',
            'ojek_data' => json_encode($ojek_data),
            'alamat_lengkap' => sanitize_textarea_field($data['pickup']['address']), // Untuk display simple
            'kecamatan' => sanitize_text_field($data['pickup']['kecamatan_id'] ?? ''), // Penting untuk broadcast driver
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . 'dw_transaksi', $insert_data);
        return $wpdb->insert_id;
    }

    // 2. Driver Mengajukan Ongkos (Status: penawaran_driver)
    public static function ajax_driver_submit_bid() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        
        $driver_id = get_current_user_id();
        $trx_id = absint($_POST['transaction_id']);
        $price = absint($_POST['price']); // Nominal yang diajukan driver

        // Ambil data transaksi
        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        if (!$trx) wp_send_json_error(['message' => 'Order tidak ditemukan']);

        $ojek_data = json_decode($trx->ojek_data, true);
        
        // Tambahkan ke history nego
        $ojek_data['nego_history'][] = [
            'actor' => 'driver',
            'price' => $price,
            'time' => current_time('mysql')
        ];
        // Set assigned driver sementara (belum deal)
        $ojek_data['driver_candidate_id'] = $driver_id;

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'penawaran_driver', // Status berubah, User harus respon
            'ojek_data' => json_encode($ojek_data),
            'total_transaksi' => $price // Update nominal sementara
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Penawaran dikirim ke penumpang']);
    }

    // 3. Penumpang Nego Balik (Status: nego)
    public static function ajax_passenger_nego() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;

        $trx_id = absint($_POST['transaction_id']);
        $price = absint($_POST['price']);

        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        if (!$trx) wp_send_json_error(['message' => 'Order tidak ditemukan']);
        $ojek_data = json_decode($trx->ojek_data, true);

        $ojek_data['nego_history'][] = [
            'actor' => 'passenger',
            'price' => $price,
            'time' => current_time('mysql')
        ];

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'nego', // Balik ke driver untuk konfirmasi/nego lagi
            'ojek_data' => json_encode($ojek_data),
            'total_transaksi' => $price
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Nego dikirim ke driver']);
    }

    // 4. Penumpang Setuju (Status: menunggu_penjemputan)
    public static function ajax_passenger_accept() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;

        $trx_id = absint($_POST['transaction_id']);
        
        $trx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $trx_id));
        if (!$trx) wp_send_json_error(['message' => 'Order tidak ditemukan']);
        $ojek_data = json_decode($trx->ojek_data, true);
        
        // Driver yang terakhir bidding/deal
        $driver_id = $ojek_data['driver_candidate_id'] ?? 0;
        if (!$driver_id) wp_send_json_error(['message' => 'Driver tidak valid']);

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'id_ojek' => absint($driver_id),
            'status_transaksi' => 'menunggu_penjemputan',
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Deal! Driver akan segera menjemput']);
    }

    // 5. Driver Pickup (Status: dalam_perjalanan)
    public static function ajax_driver_pickup() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        $trx_id = absint($_POST['transaction_id']);

        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'dalam_perjalanan',
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Trip dimulai']);
    }

    // 6. Driver Selesai (Status: selesai)
    public static function ajax_driver_complete() {
        check_ajax_referer('dw_nonce', 'nonce');
        global $wpdb;
        $trx_id = absint($_POST['transaction_id']);
        $driver_id = get_current_user_id();

        // Potong Kuota Driver
        $deducted = self::deduct_quota($driver_id, 1, 'Selesaikan Order Ojek');
        
        $wpdb->update($wpdb->prefix . 'dw_transaksi', [
            'status_transaksi' => 'selesai',
            'status_pembayaran' => 'lunas', // Asumsi cash/berhasil
            'updated_at' => current_time('mysql')
        ], ['id' => absint($trx_id)]);

        wp_send_json_success(['message' => 'Trip selesai. Kuota Anda terpotong 1.']);
    }

    /**
     * Helper: Ambil Order Aktif di Sekitar (Kecamatan)
     */
    public static function get_nearby_requests($kecamatan_id) {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}dw_transaksi 
                WHERE status_transaksi = 'menunggu_driver' 
                AND kecamatan = %s 
                ORDER BY created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, sanitize_text_field($kecamatan_id)));
    }
}
