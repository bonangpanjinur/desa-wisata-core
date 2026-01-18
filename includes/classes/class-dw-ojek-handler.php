<?php
if (!defined('ABSPATH')) {
    exit;
}

class DW_Ojek_Handler {

    /**
     * Instance Singleton
     */
    protected static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Init Method - Dipanggil oleh includes/init.php
     * Fix untuk error: Call to undefined method DW_Ojek_Handler::init()
     */
    public static function init() {
        self::instance();
    }

    public function __construct() {
        add_action('wp_ajax_dw_toggle_ojek_status', array($this, 'toggle_driver_status'));
        add_action('wp_ajax_nopriv_dw_calculate_ojek_fare', array($this, 'calculate_fare'));
        add_action('wp_ajax_dw_calculate_ojek_fare', array($this, 'calculate_fare'));
    }

    /**
     * CRUD: Simpan Tarif (Static)
     */
    public static function save_rate($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek_rates';
        
        if (empty($data['api_kabupaten_id']) || empty($data['nama_kabupaten'])) return false;

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
     * CRUD: Hapus Tarif (Static)
     */
    public static function delete_rate($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek_rates';
        return $wpdb->delete($table_name, ['id' => $id]);
    }

    // --- LOGIC OJEK ---

    public function toggle_driver_status() {
        check_ajax_referer('dw_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Login required.'));

        $user_id = get_current_user_id();
        $is_driver = get_user_meta($user_id, 'is_ojek_driver', true); // Pastikan meta key ini benar

        // Fallback check role jika meta belum ada
        if (!$is_driver) {
            $user = get_userdata($user_id);
            if (in_array('dw_ojek_driver', (array) $user->roles)) {
                $is_driver = true;
            }
        }

        if (!$is_driver) wp_send_json_error(array('message' => 'Bukan akun driver.'));

        $current_status = get_user_meta($user_id, 'ojek_status', true);
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';

        if ($new_status === 'active') {
            if (!$this->can_driver_activate($user_id)) {
                $min_bal = get_option('dw_ojek_min_balance', 20000);
                wp_send_json_error(array('message' => "Saldo kurang. Min: Rp " . number_format($min_bal)));
            }
        }

        update_user_meta($user_id, 'ojek_status', $new_status);
        if ($new_status === 'active') update_user_meta($user_id, 'ojek_last_active', current_time('mysql'));

        wp_send_json_success(array(
            'status' => $new_status,
            'message' => ($new_status === 'active') ? 'Online' : 'Offline'
        ));
    }

    private function can_driver_activate($user_id) {
        $min_balance = (int) get_option('dw_ojek_min_balance', 20000);
        // Cek tabel wallet jika ada, atau fallback ke user meta
        global $wpdb;
        $table_wallet = $wpdb->prefix . 'dw_wallet'; // Asumsi tabel wallet ada
        
        // Cek apakah tabel wallet ada
        if($wpdb->get_var("SHOW TABLES LIKE '$table_wallet'") == $table_wallet) {
            $balance = $wpdb->get_var($wpdb->prepare("SELECT balance FROM $table_wallet WHERE user_id = %d", $user_id));
        } else {
            $balance = get_user_meta($user_id, '_dw_wallet_balance', true);
        }
        
        return (int)$balance >= $min_balance;
    }

    public function calculate_fare() {
        if (!isset($_POST['distance_km']) || !isset($_POST['kabupaten_id'])) wp_send_json_error(['message' => 'Param incomplete']);

        $distance = floatval($_POST['distance_km']);
        $kab_id = sanitize_text_field($_POST['kabupaten_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek_rates';
        
        $rate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE api_kabupaten_id = %s AND is_active = 1", $kab_id));

        if ($rate) {
            $base = floatval($rate->base_fare);
            $per_km = floatval($rate->price_per_km);
            $min_km = floatval($rate->min_distance_km);
        } else {
            $base = floatval(get_option('dw_ojek_global_base_fare', 7000));
            $per_km = floatval(get_option('dw_ojek_global_price_km', 2500));
            $min_km = floatval(get_option('dw_ojek_global_min_km', 2));
        }

        $calc_dist = max($distance, $min_km);
        $total = $base + ($calc_dist * $per_km);

        wp_send_json_success([
            'price' => $total,
            'formatted' => 'Rp ' . number_format($total, 0, ',', '.')
        ]);
    }
}