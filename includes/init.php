<?php
/**
 * File Path: includes/init.php
 * Description: Mendaftarkan aset, handler, dan variabel global.
 * * UPDATE (MERGED):
 * - Mengembalikan logika Dynamic Host Detection & Admin Assets dari versi user.
 * - Menambahkan require untuk Ojek Handler dan komponen inti lainnya.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Konstanta Fallback (Jaga-jaga jika ada perbedaan penamaan di main file)
if (!defined('DW_PLUGIN_DIR') && defined('DW_CORE_PLUGIN_DIR')) {
    define('DW_PLUGIN_DIR', DW_CORE_PLUGIN_DIR);
}
if (!defined('DW_PLUGIN_URL') && defined('DW_CORE_PLUGIN_URL')) {
    define('DW_PLUGIN_URL', DW_CORE_PLUGIN_URL);
}

// ==========================================================================
// 1. LOAD CORE COMPONENTS (Wajib untuk Fitur Ojek & Lainnya)
// ==========================================================================

// Helper Functions
require_once DW_PLUGIN_DIR . 'includes/helpers.php';

// Post Types & Taxonomies
require_once DW_PLUGIN_DIR . 'includes/post-types.php';
require_once DW_PLUGIN_DIR . 'includes/taxonomies.php';

// Access Control & Roles
require_once DW_PLUGIN_DIR . 'includes/roles-capabilities.php';
require_once DW_PLUGIN_DIR . 'includes/access-control.php';

// Logic Handlers
require_once DW_PLUGIN_DIR . 'includes/user-profiles.php';
require_once DW_PLUGIN_DIR . 'includes/cart.php';
require_once DW_PLUGIN_DIR . 'includes/reviews.php';

// OJEK HANDLER (NEW FEATURE)
require_once DW_PLUGIN_DIR . 'includes/class-dw-ojek-handler.php';

// REST API
require_once DW_PLUGIN_DIR . 'includes/rest-api/index.php';

// Admin Menu & Meta Boxes
if (is_admin()) {
    require_once DW_PLUGIN_DIR . 'includes/admin-menus.php';
    // require_once DW_PLUGIN_DIR . 'includes/admin-assets.php'; // Kita gunakan fungsi inline di bawah agar fitur Dynamic Host tetap jalan
    require_once DW_PLUGIN_DIR . 'includes/meta-boxes.php';
}

// Initialize Classes
function dw_init_classes() {
    // Init Ojek Handler
    if (class_exists('DW_Ojek_Handler')) {
        DW_Ojek_Handler::init();
    }
}
add_action('plugins_loaded', 'dw_init_classes');


// ==========================================================================
// 2. LOGIKA INIT SEBELUMNYA (RESTORED)
// ==========================================================================

// 1. Cek Update DB
function dw_plugin_update_check() {
    $current_version = get_option( 'dw_core_db_version', '1.0.0' );
    $plugin_version = defined('DW_CORE_VERSION') ? DW_CORE_VERSION : '1.0.0';

    if ( version_compare( $current_version, $plugin_version, '<' ) ) {
        if ( ! function_exists( 'dw_core_activate_plugin' ) ) {
            require_once DW_PLUGIN_DIR . 'includes/activation.php';
        }
        dw_core_activate_plugin();
    }
}
add_action( 'plugins_loaded', 'dw_plugin_update_check' );

// 2. Load Assets Admin (Dengan Dynamic Host Detection)
function dw_core_load_admin_assets($hook) {
    // Identifikasi Halaman Plugin
    $is_dw_page = (strpos($hook, 'dw-') !== false);
    
    // Identifikasi Post Type (Produk/Wisata)
    $screen = get_current_screen();
    $is_dw_cpt = ($screen && in_array($screen->post_type, ['dw_wisata', 'dw_produk']));

    // Gunakan URL Plugin Constant
    $plugin_url = defined('DW_CORE_PLUGIN_URL') ? DW_CORE_PLUGIN_URL : plugin_dir_url(dirname(__FILE__));
    $version = defined('DW_CORE_VERSION') ? DW_CORE_VERSION : '1.0.0';

    if ( $is_dw_page || $is_dw_cpt ) {
        // Load CSS Utama
        wp_enqueue_style( 'dw-admin-styles', $plugin_url . 'assets/css/admin-styles.css', [], $version );
        
        // Load Library Select2
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], '4.0.13', true);

        // Load JS Utama
        wp_enqueue_script( 'dw-admin-scripts', $plugin_url . 'assets/js/admin-scripts.js', ['jquery', 'select2'], $version, true );
        wp_enqueue_media();

        // --- PERBAIKAN URL API (DYNAMIC ABSOLUTE HOST) ---
        // Kita hitung URL secara manual berdasarkan Host yang sedang diakses.
        
        // 1. Tentukan Protokol
        $protocol = is_ssl() ? 'https://' : 'http://';
        
        // 2. Ambil Host saat ini
        $current_host = $_SERVER['HTTP_HOST']; 
        
        // 3. Ambil Path Relatif WordPress
        $ajax_relative_path = admin_url('admin-ajax.php', 'relative'); 
        $site_path_relative = str_replace('wp-admin/admin-ajax.php', '', $ajax_relative_path);
        
        // 4. Susun URL Absolute Dinamis
        $dynamic_site_url = $protocol . $current_host . $site_path_relative;
        
        // URL Hasil Akhir
        $final_rest_url = $dynamic_site_url . rest_get_url_prefix() . '/dw/v1/';
        $final_ajax_url = $dynamic_site_url . 'wp-admin/admin-ajax.php';

        // Kirim Data ke JS
        wp_localize_script('dw-admin-scripts', 'dw_admin_vars', [
            'ajax_url'   => $final_ajax_url, 
            'nonce'      => wp_create_nonce('dw_admin_nonce'),
            'rest_url'   => $final_rest_url, 
            'rest_nonce' => wp_create_nonce('wp_rest')
        ]);

        // Library tambahan khusus halaman settings (Color Picker)
        if ($screen && $screen->id === 'desa-wisata_page_dw-settings') {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'dw-admin-scripts' ); 
        }
    }
}
add_action( 'admin_enqueue_scripts', 'dw_core_load_admin_assets' );

// 3. Keamanan Media Library (Isolasi User)
function dw_filter_media_library_by_author($query) {
    if (!is_user_logged_in()) return $query;
    if (current_user_can('manage_options') || current_user_can('dw_manage_desa')) return $query;

    $user_id = get_current_user_id();
    if ($user_id > 0) $query['author'] = $user_id;
    
    return $query;
}
add_filter('ajax_query_attachments_args', 'dw_filter_media_library_by_author');

// 4. Fix CSP Header
function dw_remove_admin_csp_header() {
    if (is_admin() && !headers_sent()) {
        header_remove('Content-Security-Policy');
    }
}
add_action('admin_init', 'dw_remove_admin_csp_header', 999);

// 5. Support Thumbnail
function dw_force_add_thumbnail_support() {
    add_post_type_support( 'dw_produk', 'thumbnail' );
    add_post_type_support( 'dw_wisata', 'thumbnail' );
}
add_action( 'init', 'dw_force_add_thumbnail_support', 99 );