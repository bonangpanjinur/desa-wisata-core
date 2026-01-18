<?php
/**
 * Initialization File
 * Path: includes/init.php
 * Description: Memuat semua komponen utama plugin.
 * Fixes: Menu Admin Hilang & Fatal Error pada Shortcode Init.
 * Updates: Support Fase 2 (Transaction), Fase 3 (Ojek Maps), & Fase 4 (Wallet UI).
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants if not already defined (Fallback)
if ( ! defined( 'DW_PATH' ) ) {
    define( 'DW_PATH', plugin_dir_path( dirname( __FILE__ ) ) );
}
if ( ! defined( 'DW_URL' ) ) {
    define( 'DW_URL', plugin_dir_url( dirname( __FILE__ ) ) );
}
if ( ! defined( 'DW_VERSION' ) ) {
    define( 'DW_VERSION', '2.9.4' );
}
// Ensure DW_PLUGIN_DIR exists for legacy requires compatibility
if ( ! defined( 'DW_PLUGIN_DIR' ) ) {
    define( 'DW_PLUGIN_DIR', DW_PATH );
}

/**
 * 1. Enqueue Scripts & Styles (Frontend)
 */
function dw_enqueue_scripts() {
    // CSS Utama
    wp_enqueue_style('dw-frontend-style', DW_URL . 'assets/css/dw-frontend.css', array(), DW_VERSION);
    // Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
    // JS Utama
    wp_enqueue_script('dw-frontend-script', DW_URL . 'assets/js/dw-frontend.js', array('jquery'), DW_VERSION, true);

    // Localize Script
    wp_localize_script('dw-frontend-script', 'dw_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('dw_nonce'),
        'is_logged_in' => is_user_logged_in(),
        'user_id' => get_current_user_id()
    ));

    // Ojek Maps Assets (Conditional - Fase 3)
    global $post;
    if ( (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dw_ojek_order')) || 
         (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dw_ojek_driver')) ) {
        
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
        wp_enqueue_script('dw-ojek-maps', DW_URL . 'assets/js/dw-ojek-maps.js', array('jquery', 'leaflet-js'), DW_VERSION, true);
        
        wp_localize_script('dw-ojek-maps', 'dw_ojek_settings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dw_ojek_nonce'),
            'icon_origin' => DW_URL . 'assets/img/marker-origin.png',
            'icon_dest' => DW_URL . 'assets/img/marker-dest.png',
            'icon_driver' => DW_URL . 'assets/img/marker-driver.png'
        ));
    }
}
add_action('wp_enqueue_scripts', 'dw_enqueue_scripts');

/**
 * 2. Main Init Function (Classes & Logic)
 */
function dw_core_init() {
    // Load Helper Functions
    if (file_exists(DW_PATH . 'includes/helpers.php')) {
        require_once DW_PATH . 'includes/helpers.php';
    }

    // Load All Necessary Classes
    $includes = array(
        'includes/post-types.php',
        'includes/taxonomies.php',
        'includes/roles-capabilities.php',
        'includes/user-profiles.php',
        'includes/meta-boxes.php',
        'includes/admin-menus.php', // Menu Loaded here for capabilities check
        'includes/admin-assets.php',
        'includes/ajax-handlers.php',
        'includes/address-api.php',
        'includes/cart.php',
        // Classes Logic
        'includes/classes/class-dw-wallet.php',
        'includes/classes/class-dw-pos-handler.php',
        'includes/classes/class-dw-ojek-handler.php',
        'includes/classes/class-dw-transaction-handler.php', 
        // Handlers & Jobs
        'includes/commission-handler.php',
        'includes/cron-jobs.php',
        'includes/whatsapp-templates.php',
        'includes/reviews.php',
        'includes/promotions.php',
        'includes/logs.php',
        'includes/rest-api.php',
        // Shortcodes (Load individual files if needed for class check)
        'includes/shortcodes/class-dw-shortcode-wallet.php', // NEW: Fase 4
    );

    foreach ($includes as $file) {
        if (file_exists(DW_PATH . $file)) {
            require_once DW_PATH . $file;
        }
    }

    // --- SAFETY INIT: LOGIC CLASSES ---
    if ( class_exists( 'DW_Ojek_Handler' ) && method_exists( 'DW_Ojek_Handler', 'init' ) ) {
        DW_Ojek_Handler::init();
    }
    
    if ( class_exists( 'DW_Wallet' ) && method_exists( 'DW_Wallet', 'init' ) ) {
        DW_Wallet::init();
    }

    if ( class_exists( 'DW_Transaction_Handler' ) && method_exists( 'DW_Transaction_Handler', 'init' ) ) {
        DW_Transaction_Handler::init();
    }

    // --- SAFETY INIT: SHORTCODES ---
    if (file_exists(DW_PATH . 'includes/shortcodes/class-dw-shortcodes.php')) {
        require_once DW_PATH . 'includes/shortcodes/class-dw-shortcodes.php';
        
        if ( class_exists( 'DW_Shortcodes' ) ) {
            if ( method_exists( 'DW_Shortcodes', 'init' ) ) {
                DW_Shortcodes::init();
            } else {
                new DW_Shortcodes();
            }
        }
    }

    // --- SAFETY INIT: CRON JOBS ---
    if (function_exists('dw_schedule_cron_jobs')) {
        dw_schedule_cron_jobs();
    }
}
add_action('plugins_loaded', 'dw_core_init');

/**
 * 3. Admin Interfaces 
 */
if ( is_admin() ) {
    if ( file_exists( DW_PATH . 'includes/admin-menus.php' ) ) {
        require_once DW_PATH . 'includes/admin-menus.php';
    }
    if ( file_exists( DW_PATH . 'includes/admin-assets.php' ) ) {
        require_once DW_PATH . 'includes/admin-assets.php';
    }
    if ( file_exists( DW_PATH . 'includes/meta-boxes.php' ) ) {
        require_once DW_PATH . 'includes/meta-boxes.php';
    }
    if ( file_exists( DW_PATH . 'includes/ajax-handlers.php' ) ) {
        require_once DW_PATH . 'includes/ajax-handlers.php';
    }
    // Load Pages Logic
    if ( file_exists( DW_PATH . 'includes/admin-pages/page-settings.php' ) ) {
        require_once DW_PATH . 'includes/admin-pages/page-settings.php';
    }
    if ( file_exists( DW_PATH . 'includes/admin-pages/index.php' ) ) {
        require_once DW_PATH . 'includes/admin-pages/index.php';
    }
} else {
    // Load AJAX handlers on frontend
    if ( file_exists( DW_PATH . 'includes/ajax-handlers.php' ) ) {
        require_once DW_PATH . 'includes/ajax-handlers.php';
    }
}