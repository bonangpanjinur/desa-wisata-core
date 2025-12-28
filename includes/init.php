<?php
/**
 * File Name:    includes/init.php
 * Description:  Inisialisasi plugin, load dependencies, dan setup hooks utama v3.6.
 * UPDATE (BUG FIX):
 * - Memperbaiki Fatal error: Cannot redeclare dw_core_load_dependencies().
 * - Mengganti nama fungsi loading dependensi agar tidak bentrok dengan main file.
 * - Memastikan semua komponen inti (UMKM, Verifikator, Pembeli) termuat.
 * - Terintegrasi dengan Sistem Referral Baru.
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. Definisikan Konstanta Global
 */
if (!defined('DW_CORE_VERSION')) define('DW_CORE_VERSION', '3.6');
if (!defined('DW_CORE_PLUGIN_DIR')) define('DW_CORE_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
if (!defined('DW_CORE_PLUGIN_URL')) define('DW_CORE_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));

// Fallback legacy constants agar kompatibel dengan kode lama yang pakai DW_PATH
if (!defined('DW_PLUGIN_DIR')) define('DW_PLUGIN_DIR', DW_CORE_PLUGIN_DIR);
if (!defined('DW_PLUGIN_URL')) define('DW_PLUGIN_URL', DW_CORE_PLUGIN_URL);
if (!defined('DW_PATH')) define('DW_PATH', DW_CORE_PLUGIN_DIR);

/**
 * 2. Load Dependencies secara sistematis
 * Nama fungsi diubah menjadi dw_core_initialize_all_components agar tidak bentrok.
 */
function dw_core_initialize_all_components() {
    // Core Helpers & Logic
    require_once DW_CORE_PLUGIN_DIR . 'includes/helpers.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/data-integrity.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/access-control.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/roles-capabilities.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/user-profiles.php';

    // Post Types, Taxonomies & Referral
    require_once DW_CORE_PLUGIN_DIR . 'includes/post-types.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/taxonomies.php';
    
    // --> SISTEM REFERRAL UTAMA (Diintegrasikan dari versi sebelumnya) <--
    require_once DW_CORE_PLUGIN_DIR . 'includes/class-dw-referral-logic.php'; // Logika Database Referral
    require_once DW_CORE_PLUGIN_DIR . 'includes/class-dw-referral-hooks.php'; // Hooks Pendaftaran
    require_once DW_CORE_PLUGIN_DIR . 'includes/class-dw-referral-handler.php'; // Handler AJAX/Legacy
    // ---------------------------------------------------------------------

    // UI/UX & Admin
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-assets.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-menus.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/meta-boxes.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-ui-tweaks.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/index.php'; // Loader List Table

    // Handlers & Features
    require_once DW_CORE_PLUGIN_DIR . 'includes/ajax-handlers.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/index.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/commission-handler.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/relasi-handler.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/class-dw-ojek-handler.php';

    // Logic Updates & Seeder (Diintegrasikan dari versi sebelumnya)
    require_once DW_CORE_PLUGIN_DIR . 'includes/class-dw-seeder.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/dw-logic-updates.php';

    // Integrasi Eksternal & Fitur Tambahan
    require_once DW_CORE_PLUGIN_DIR . 'includes/whatsapp-templates.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/address-api.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/cart.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/reviews.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/promotions.php';

    // Cron & Automation
    require_once DW_CORE_PLUGIN_DIR . 'includes/cron-jobs.php';
}
// Gunakan nama fungsi baru pada hook plugins_loaded
add_action('plugins_loaded', 'dw_core_initialize_all_components', 10);

/**
 * 3. Inisialisasi Class Utama
 */
function dw_core_init_classes() {
    // Init Ojek Handler
    if (class_exists('DW_Ojek_Handler')) {
        DW_Ojek_Handler::init();
    }
    
    // Init Referral Hooks (Agar logika pendaftaran Desa/Verifikator/Pedagang jalan)
    if (class_exists('DW_Referral_Hooks')) {
        new DW_Referral_Hooks();
    }
}
add_action('plugins_loaded', 'dw_core_init_classes', 20);

/**
 * 4. Cek Update Database (Auto-Migration)
 */
function dw_plugin_update_check() {
    $current_version = get_option('dw_core_db_version', '1.0.0');
    if (version_compare($current_version, DW_CORE_VERSION, '<')) {
        if (!function_exists('dw_core_activate_plugin')) {
            require_once DW_CORE_PLUGIN_DIR . 'includes/activation.php';
        }
        dw_core_activate_plugin();
    }
}
add_action('plugins_loaded', 'dw_plugin_update_check', 30);

/**
 * 5. Load Assets Admin (Dengan Dynamic Host Detection)
 */
function dw_core_load_admin_assets_handler($hook) {
    // Tentukan Halaman Plugin
    $is_dw_page = (strpos($hook, 'dw-') !== false);
    $screen = get_current_screen();
    $is_dw_cpt = ($screen && in_array($screen->post_type, ['dw_wisata', 'dw_produk']));

    if ($is_dw_page || $is_dw_cpt) {
        $version = DW_CORE_VERSION;
        $plugin_url = DW_CORE_PLUGIN_URL;

        wp_enqueue_style('dw-admin-styles', $plugin_url . 'assets/css/admin-styles.css', [], $version);
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], '4.0.13', true);
        wp_enqueue_script('dw-admin-scripts', $plugin_url . 'assets/js/admin-scripts.js', ['jquery', 'select2'], $version, true);
        wp_enqueue_media();

        // --- DYNAMIC ABSOLUTE HOST DETECTION ---
        $protocol = is_ssl() ? 'https://' : 'http://';
        $current_host = $_SERVER['HTTP_HOST'];
        $ajax_relative_path = admin_url('admin-ajax.php', 'relative');
        $site_path_relative = str_replace('wp-admin/admin-ajax.php', '', $ajax_relative_path);
        $dynamic_site_url = $protocol . $current_host . $site_path_relative;

        $final_rest_url = $dynamic_site_url . rest_get_url_prefix() . '/dw/v1/';
        $final_ajax_url = $dynamic_site_url . 'wp-admin/admin-ajax.php';

        wp_localize_script('dw-admin-scripts', 'dw_admin_vars', [
            'ajax_url'   => $final_ajax_url,
            'nonce'      => wp_create_nonce('dw_admin_nonce'),
            'rest_url'   => $final_rest_url,
            'rest_nonce' => wp_create_nonce('wp_rest')
        ]);

        if ($screen && $screen->id === 'desa-wisata_page_dw-settings') {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
    }
}
add_action('admin_enqueue_scripts', 'dw_core_load_admin_assets_handler');

/**
 * 6. Fitur Pendukung & Keamanan
 */

// Filter Media Library agar User hanya melihat miliknya (kecuali admin)
add_filter('ajax_query_attachments_args', function($query) {
    if (!is_user_logged_in()) return $query;
    if (current_user_can('manage_options') || current_user_can('dw_manage_desa')) return $query;
    $user_id = get_current_user_id();
    if ($user_id > 0) $query['author'] = $user_id;
    return $query;
});

// Hapus CSP Header agar iframe/resource eksternal lancar di dashboard
add_action('admin_init', function() {
    if (!headers_sent()) header_remove('Content-Security-Policy');
}, 999);

// Paksa Support Thumbnail untuk CPT
add_action('init', function() {
    add_post_type_support('dw_produk', 'thumbnail');
    add_post_type_support('dw_wisata', 'thumbnail');
}, 99);