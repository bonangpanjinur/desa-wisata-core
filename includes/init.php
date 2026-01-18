<?php
/**
 * Initialization File
 * Path: includes/init.php
 * Description: Memuat semua komponen utama plugin saat WordPress 'plugins_loaded'.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants if not already defined (Fixes PHP Warnings)
if ( ! defined( 'DW_CORE_PATH' ) ) {
    define( 'DW_CORE_PATH', plugin_dir_path( dirname( __FILE__ ) ) );
}
if ( ! defined( 'DW_CORE_URL' ) ) {
    define( 'DW_CORE_URL', plugin_dir_url( dirname( __FILE__ ) ) );
}
if ( ! defined( 'DW_CORE_VERSION' ) ) {
    define( 'DW_CORE_VERSION', '2.9.1' );
}
// Ensure DW_PLUGIN_DIR exists for legacy requires
if ( ! defined( 'DW_PLUGIN_DIR' ) ) {
    define( 'DW_PLUGIN_DIR', DW_CORE_PATH );
}

/**
 * Enqueue scripts and styles
 */
function dw_enqueue_scripts() {
    // CSS Utama
    wp_enqueue_style('dw-frontend-style', DW_CORE_URL . 'assets/css/dw-frontend.css', array(), DW_CORE_VERSION);

    // Font Awesome (jika belum ada di tema)
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');

    // JS Utama
    wp_enqueue_script('dw-frontend-script', DW_CORE_URL . 'assets/js/dw-frontend.js', array('jquery'), DW_CORE_VERSION, true);

    // Localize Script (Data dari PHP ke JS)
    wp_localize_script('dw-frontend-script', 'dw_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('dw_nonce'),
        'is_logged_in' => is_user_logged_in(),
        'user_id' => get_current_user_id()
    ));

    global $post;
    if ( (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dw_ojek_order')) || 
         (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dw_ojek_driver')) ) {
        
        // Load Leaflet CSS & JS (CDN Gratis)
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

        // Load Ojek Maps Handler
        wp_enqueue_script('dw-ojek-maps', DW_CORE_URL . 'assets/js/dw-ojek-maps.js', array('jquery', 'leaflet-js'), DW_CORE_VERSION, true);
        
        // Kirim data setting ke JS Maps
        wp_localize_script('dw-ojek-maps', 'dw_ojek_settings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dw_ojek_nonce'),
            'icon_origin' => DW_CORE_URL . 'assets/img/marker-origin.png',
            'icon_dest' => DW_CORE_URL . 'assets/img/marker-dest.png',
            'icon_driver' => DW_CORE_URL . 'assets/img/marker-driver.png'
        ));
    }
}
add_action('wp_enqueue_scripts', 'dw_enqueue_scripts');

function dw_core_init() {
    // 1. Load Helper Functions (Paling Awal)
    if (file_exists(DW_CORE_PATH . 'includes/helpers.php')) {
        require_once DW_CORE_PATH . 'includes/helpers.php';
    }

    // 2. Load Classes & Handlers
    $includes = array(
        'includes/post-types.php',           // Register CPT
        'includes/taxonomies.php',           // Register Taxonomies
        'includes/roles-capabilities.php',   // Role Management
        'includes/user-profiles.php',        // User Meta Fields
        'includes/meta-boxes.php',           // Custom Meta Boxes
        'includes/admin-menus.php',          // Admin Menu Structure
        'includes/admin-assets.php',         // Enqueue Scripts/Styles
        'includes/ajax-handlers.php',        // General AJAX
        'includes/address-api.php',          // Wilayah API
        
        // Core Logic Classes
        'includes/cart.php',
        'includes/classes/class-dw-wallet.php',
        'includes/classes/class-dw-pos-handler.php',
        'includes/classes/class-dw-ojek-handler.php', // New Ojek Handler
        'includes/commission-handler.php',
        'includes/cron-jobs.php',
        'includes/whatsapp-templates.php',
        'includes/reviews.php',
        'includes/promotions.php',
        'includes/logs.php',
        
        // REST API
        'includes/rest-api.php',
    );

    foreach ($includes as $file) {
        if (file_exists(DW_CORE_PATH . $file)) {
            require_once DW_CORE_PATH . $file;
        }
    }

    // 3. Initialize Ojek Handler (Singleton)
    // FIX: Check if class exists before calling init() to prevent fatal error
    if ( class_exists( 'DW_Ojek_Handler' ) && method_exists( 'DW_Ojek_Handler', 'init' ) ) {
        DW_Ojek_Handler::init();
    } elseif ( class_exists( 'DW_Ojek_Handler' ) && method_exists( 'DW_Ojek_Handler', 'instance' ) ) {
        // Fallback if init() missing but instance() exists
        DW_Ojek_Handler::instance();
    }

    // 4. Initialize Wallet (Singleton if applicable)
    if ( class_exists( 'DW_Wallet' ) && method_exists( 'DW_Wallet', 'init' ) ) {
        DW_Wallet::init();
    }

    // 5. Initialize Shortcodes
    if (file_exists(DW_CORE_PATH . 'includes/shortcodes/class-dw-shortcodes.php')) {
        require_once DW_CORE_PATH . 'includes/shortcodes/class-dw-shortcodes.php';
        DW_Shortcodes::init();
    }
}
add_action('plugins_loaded', 'dw_core_init');

// 4. Admin Interfaces (Loaded separately to ensure hook availability)
if ( is_admin() ) {
    // These are already loaded in dw_core_init via $includes array, 
    // but explicit check for page-settings logic inside admin_init context if needed
    // However, dw_core_init runs on plugins_loaded which is early enough.
    
    // [FIX] Load Settings Page Logic Here (agar hooks admin_init & save settings jalan)
    // Double check if not already included via dw_core_init list
    // admin-menus.php handles the menu creation.
}