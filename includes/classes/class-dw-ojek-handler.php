<?php
if (!defined('ABSPATH')) {
    exit;
}

class DW_Ojek_Handler {

    /**
     * The single instance of the class.
     *
     * @var DW_Ojek_Handler
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Main DW_Ojek_Handler Instance.
     *
     * Ensures only one instance of DW_Ojek_Handler is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return DW_Ojek_Handler - Main instance.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Initialize the Ojek Handler.
     * This method is called from includes/init.php to start the class.
     */
    public static function init() {
        self::instance();
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Hook for driver status toggle (via AJAX)
        add_action('wp_ajax_dw_toggle_ojek_status', array($this, 'toggle_driver_status'));
        
        // Hook to calculate fare (AJAX) - Logic Baru Phase 3
        add_action('wp_ajax_nopriv_dw_calculate_ojek_fare', array($this, 'calculate_fare'));
        add_action('wp_ajax_dw_calculate_ojek_fare', array($this, 'calculate_fare'));
    }

    /**
     * CRUD: Simpan Tarif (Static Method untuk dipanggil dari Page Settings)
     */
    public static function save_rate($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek_rates';
        
        // Validasi minimal
        if (empty($data['api_kabupaten_id']) || empty($data['nama_kabupaten'])) {
            return false;
        }

        // Cek existing
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_name WHERE api_kabupaten_id = %s", $data['api_kabupaten_id']));

        if ($existing) {
            $data['updated_at'] = current_time('mysql');
            return $wpdb->update($table_name, $data, ['id' => $existing->id]);
        } else {
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert($table_name, $data);
        }
    }

    /**
     * CRUD: Hapus Tarif (Static Method)
     */
    public static function delete_rate($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek_rates';
        return $wpdb->delete($table_name, ['id' => $id]);
    }

    /**
     * Mengubah status driver (Online/Offline)
     * Validasi: Saldo minimum.
     */
    public function toggle_driver_status() {
        check_ajax_referer('dw_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Anda harus login.'));
        }

        $user_id = get_current_user_id();
        
        // Cek apakah user adalah driver (bisa via role atau meta)
        $is_driver = get_user_meta($user_id, 'is_ojek_driver', true);
        if (!$is_driver) {
            // Fallback check role
            $user = get_userdata($user_id);
            if ( in_array( 'dw_ojek_driver', (array) $user->roles ) ) {
                $is_driver = true;
            }
        }

        if (!$is_driver) {
            wp_send_json_error(array('message' => 'Anda bukan driver terdaftar.'));
        }

        // Get current status
        $current_status = get_user_meta($user_id, 'ojek_status', true); // 'active' or 'inactive'
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';

        // VALIDASI SALDO (Hanya jika ingin mengaktifkan)
        if ($new_status === 'active') {
            if (!$this->can_driver_activate($user_id)) {
                $min_balance_val = get_option('dw_ojek_min_balance', 20000);
                $min_balance = function_exists('dw_format_price') ? dw_format_price($min_balance_val) : 'Rp ' . number_format($min_balance_val,0,',','.');
                
                wp_send_json_error(array(
                    'message' => "Saldo tidak cukup. Minimal saldo untuk narik adalah $min_balance. Silakan Top Up dompet Anda."
                ));
            }
        }

        // Update status
        update_user_meta($user_id, 'ojek_status', $new_status);

        // Jika status aktif, update last location timestamp agar muncul di map (opsional)
        if ($new_status === 'active') {
            update_user_meta($user_id, 'ojek_last_active', current_time('mysql'));
        }

        wp_send_json_success(array(
            'status' => $new_status,
            'message' => ($new_status === 'active') ? 'Status Online. Siap menerima order.' : 'Status Offline.'
        ));
    }

    /**
     * Cek apakah saldo driver cukup untuk aktif
     */
    private function can_driver_activate($user_id) {
        $min_balance = (int) get_option('dw_ojek_min_balance', 20000);
        
        // Get driver wallet balance
        // Cek tabel wallet jika ada, atau fallback ke user meta
        global $wpdb;
        $table_wallet = $wpdb->prefix . 'dw_wallet';
        
        // Cek apakah tabel wallet ada
        if($wpdb->get_var("SHOW TABLES LIKE '$table_wallet'") == $table_wallet) {
            $balance = $wpdb->get_var($wpdb->prepare("SELECT balance FROM $table_wallet WHERE user_id = %d", $user_id));
        } else {
            $balance = get_user_meta($user_id, '_dw_wallet_balance', true);
        }

        return (int)$balance >= $min_balance;
    }

    /**
     * Hitung Estimasi Harga Ojek
     * Rumus: (Jarak KM * Tarif Kabupaten) + Biaya Admin/Platform
     * Jika tarif kabupaten tidak ada, gunakan Fallback Global.
     */
    public function calculate_fare() {
        // Validasi Request
        if (!isset($_POST['distance_km']) || !isset($_POST['kabupaten_id'])) {
            wp_send_json_error(['message' => 'Parameter tidak lengkap']);
        }

        $distance = floatval($_POST['distance_km']);
        $kab_id = sanitize_text_field($_POST['kabupaten_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek_rates';

        // 1. Cek Tarif Spesifik Kabupaten
        $rate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE api_kabupaten_id = %s AND is_active = 1", $kab_id));

        if ($rate) {
            $base_fare = floatval($rate->base_fare);
            $price_per_km = floatval($rate->price_per_km);
            $min_km = floatval($rate->min_distance_km);
            $commission_percent = floatval($rate->commission_percent);
        } else {
            // 2. Gunakan Fallback Global (Setting Admin)
            $base_fare = floatval(get_option('dw_ojek_global_base_fare', 7000));
            $price_per_km = floatval(get_option('dw_ojek_global_price_km', 2500));
            $min_km = floatval(get_option('dw_ojek_global_min_km', 2));
            $commission_percent = floatval(get_option('dw_ojek_global_commission', 20));
        }

        // Logika Perhitungan
        $calc_distance = max($distance, $min_km); // Jarak minimal
        $travel_cost = $calc_distance * $price_per_km;
        
        $total_price = $base_fare + $travel_cost;

        // Helper format price
        $formatted = function_exists('dw_format_price') ? dw_format_price($total_price) : 'Rp ' . number_format($total_price,0,',','.');

        wp_send_json_success([
            'distance_km' => $distance,
            'total_price' => $total_price,
            'formatted_price' => $formatted,
            'breakdown' => [
                'base_fare' => $base_fare,
                'travel_cost' => $travel_cost,
                'min_km_applied' => $distance < $min_km
            ]
        ]);
    }
}