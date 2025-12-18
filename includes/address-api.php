<?php
/**
 * Lokasi: includes/address-api.php
 * Deskripsi: Handler API Wilayah.id dengan Caching dan Fallback.
 */

if (!defined('ABSPATH')) exit;

class DW_Address_API {

    private static function get_base_url() {
        return 'https://wilayah.id/api';
    }

    public static function init() {
        add_action('wp_ajax_dw_fetch_provinces', [__CLASS__, 'fetch_provinces_ajax']);
        add_action('wp_ajax_dw_fetch_regencies', [__CLASS__, 'fetch_regencies_ajax']);
        add_action('wp_ajax_dw_fetch_districts', [__CLASS__, 'fetch_districts_ajax']);
        add_action('wp_ajax_dw_fetch_villages', [__CLASS__, 'fetch_villages_ajax']);
    }

    // --- AJAX WRAPPERS ---
    public static function fetch_provinces_ajax() {
        wp_send_json_success(self::get_provinces());
    }

    public static function fetch_regencies_ajax() {
        $id = sanitize_text_field($_GET['province_id'] ?? '');
        wp_send_json_success(self::get_cities($id));
    }

    public static function fetch_districts_ajax() {
        $id = sanitize_text_field($_GET['regency_id'] ?? '');
        wp_send_json_success(self::get_subdistricts($id));
    }

    public static function fetch_villages_ajax() {
        $id = sanitize_text_field($_GET['district_id'] ?? '');
        wp_send_json_success(self::get_villages($id));
    }

    // --- CORE LOGIC WITH CACHING ---
    public static function get_provinces() {
        $cache = get_transient('dw_prov_v4');
        if ($cache) return $cache;

        $response = wp_remote_get(self::get_base_url() . '/provinces.json');
        if (is_wp_error($response)) return self::get_fallback_provinces();

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $res = [];
        foreach (($data['data'] ?? []) as $v) {
            $res[] = ['id' => $v['code'], 'name' => ucwords(strtolower($v['name']))];
        }
        set_transient('dw_prov_v4', $res, 30 * DAY_IN_SECONDS);
        return $res;
    }

    public static function get_cities($prov_id) {
        if (!$prov_id) return [];
        $key = 'dw_city_v4_' . $prov_id;
        $cache = get_transient($key);
        if ($cache) return $cache;

        $response = wp_remote_get(self::get_base_url() . "/regencies/{$prov_id}.json");
        if (is_wp_error($response)) return [];

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $res = [];
        foreach (($data['data'] ?? []) as $v) {
            $res[] = ['id' => $v['code'], 'name' => ucwords(strtolower($v['name']))];
        }
        set_transient($key, $res, 30 * DAY_IN_SECONDS);
        return $res;
    }

    public static function get_subdistricts($city_id) {
        if (!$city_id) return [];
        $key = 'dw_dist_v4_' . $city_id;
        $cache = get_transient($key);
        if ($cache) return $cache;

        $response = wp_remote_get(self::get_base_url() . "/districts/{$city_id}.json");
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $res = [];
        foreach (($data['data'] ?? []) as $v) {
            $res[] = ['id' => $v['code'], 'name' => ucwords(strtolower($v['name']))];
        }
        set_transient($key, $res, 30 * DAY_IN_SECONDS);
        return $res;
    }

    public static function get_villages($dist_id) {
        if (!$dist_id) return [];
        $key = 'dw_vil_v4_' . $dist_id;
        $cache = get_transient($key);
        if ($cache) return $cache;

        $response = wp_remote_get(self::get_base_url() . "/villages/{$dist_id}.json");
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $res = [];
        foreach (($data['data'] ?? []) as $v) {
            $res[] = ['id' => $v['code'], 'name' => ucwords(strtolower($v['name']))];
        }
        set_transient($key, $res, 30 * DAY_IN_SECONDS);
        return $res;
    }

    public static function get_fallback_provinces() {
        return [
            ["id" => "32", "name" => "Jawa Barat"],
            ["id" => "33", "name" => "Jawa Tengah"],
            ["id" => "35", "name" => "Jawa Timur"],
            ["id" => "51", "name" => "Bali"]
        ];
    }

    public static function get_cost($origin, $destination, $weight, $courier) {
        return [['service'=>'STD', 'cost'=>[['value'=>15000 * ceil($weight/1000)]]]];
    }
}
DW_Address_API::init();