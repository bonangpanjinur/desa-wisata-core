<?php
/**
 * Initialization File
 * Path: includes/init.php
 * Description: Memuat semua komponen utama plugin.
 * Fixes: Menu Admin Hilang & Fatal Error pada Shortcode Init.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants if not already defined
if ( ! defined( 'DW_CORE_PATH' ) ) {
    define( 'DW_CORE_PATH', plugin_dir_path( dirname( __FILE__ ) ) );
}
if ( ! defined( 'DW_CORE_URL' ) ) {
    define( 'DW_CORE_URL', plugin_dir_url( dirname( __FILE__ ) ) );
}
if ( ! defined( 'DW_CORE_VERSION' ) ) {
    define( 'DW_CORE_VERSION', '2.9.2' );
}
// Ensure DW_PLUGIN_DIR exists for legacy requires
if ( ! defined( 'DW_PLUGIN_DIR' ) ) {
    define( 'DW_PLUGIN_DIR', DW_CORE_PATH );
}

/**
 * 1. Enqueue Scripts & Styles (Frontend)
 */
function dw_enqueue_scripts() {
    // CSS Utama
    wp_enqueue_style('dw-frontend-style', DW_CORE_URL . 'assets/css/dw-frontend.css', array(), DW_CORE_VERSION);

    // Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');

    // JS Utama
    wp_enqueue_script('dw-frontend-script', DW_CORE_URL . 'assets/js/dw-frontend.js', array('jquery'), DW_CORE_VERSION, true);

    // Localize Script
    wp_localize_script('dw-frontend-script', 'dw_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('dw_nonce'),
        'is_logged_in' => is_user_logged_in(),
        'user_id' => get_current_user_id()
    ));

    // Ojek Maps Assets (Conditional)
    global $post;
    if ( (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dw_ojek_order')) || 
         (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dw_ojek_driver')) ) {
        
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
        wp_enqueue_script('dw-ojek-maps', DW_CORE_URL . 'assets/js/dw-ojek-maps.js', array('jquery', 'leaflet-js'), DW_CORE_VERSION, true);
        
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

/**
 * 2. Main Init Function (Classes & Logic)
 */
function dw_core_init() {
    // Load Helper Functions
    if (file_exists(DW_CORE_PATH . 'includes/helpers.php')) {
        require_once DW_CORE_PATH . 'includes/helpers.php';
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
        'includes/classes/class-dw-wallet.php',
        'includes/classes/class-dw-pos-handler.php',
        'includes/classes/class-dw-ojek-handler.php',
        'includes/commission-handler.php',
        'includes/cron-jobs.php',
        'includes/whatsapp-templates.php',
        'includes/reviews.php',
        'includes/promotions.php',
        'includes/logs.php',
        'includes/rest-api.php',
    );

    foreach ($includes as $file) {
        if (file_exists(DW_CORE_PATH . $file)) {
            require_once DW_CORE_PATH . $file;
        }
    }

    // --- SAFETY INIT: OJEK HANDLER ---
    if ( class_exists( 'DW_Ojek_Handler' ) ) {
        if ( method_exists( 'DW_Ojek_Handler', 'init' ) ) {
            DW_Ojek_Handler::init();
        } elseif ( method_exists( 'DW_Ojek_Handler', 'instance' ) ) {
            DW_Ojek_Handler::instance();
        } else {
            // Fallback Constructor
            new DW_Ojek_Handler();
        }
    }

    // --- SAFETY INIT: WALLET ---
    if ( class_exists( 'DW_Wallet' ) ) {
        if ( method_exists( 'DW_Wallet', 'init' ) ) {
            DW_Wallet::init();
        } else {
            new DW_Wallet();
        }
    }

    // --- SAFETY INIT: SHORTCODES ---
    // Fix Fatal Error: Check method existence first
    if (file_exists(DW_CORE_PATH . 'includes/shortcodes/class-dw-shortcodes.php')) {
        require_once DW_CORE_PATH . 'includes/shortcodes/class-dw-shortcodes.php';
        
        if ( class_exists( 'DW_Shortcodes' ) ) {
            if ( method_exists( 'DW_Shortcodes', 'init' ) ) {
                DW_Shortcodes::init();
            } else {
                // If init() doesn't exist, assume constructor handles hooks
                new DW_Shortcodes();
            }
        }
    }
}
add_action('plugins_loaded', 'dw_core_init');

/**
 * 3. Admin Interfaces (Loaded Globally to Ensure Menus Appear)
 * Penting: Diletakkan di luar fungsi agar tereksekusi langsung saat mode admin.
 */
if ( is_admin() ) {
    // Muat ulang menu & assets admin untuk memastikan hooks terdaftar
    if ( file_exists( DW_PLUGIN_DIR . 'includes/admin-menus.php' ) ) {
        require_once DW_PLUGIN_DIR . 'includes/admin-menus.php';
    }
    if ( file_exists( DW_PLUGIN_DIR . 'includes/admin-assets.php' ) ) {
        require_once DW_PLUGIN_DIR . 'includes/admin-assets.php';
    }
    if ( file_exists( DW_PLUGIN_DIR . 'includes/meta-boxes.php' ) ) {
        require_once DW_PLUGIN_DIR . 'includes/meta-boxes.php';
    }
    if ( file_exists( DW_PLUGIN_DIR . 'includes/ajax-handlers.php' ) ) {
        require_once DW_PLUGIN_DIR . 'includes/ajax-handlers.php';
    }
    
    // Load Settings Page Logic (Agar Action Hooks di dalamnya terbaca)
    if ( file_exists( DW_PLUGIN_DIR . 'includes/admin-pages/page-settings.php' ) ) {
        require_once DW_PLUGIN_DIR . 'includes/admin-pages/page-settings.php';
    }
} else {
    // Load AJAX handlers on frontend for non-logged in users if needed
    if ( file_exists( DW_PLUGIN_DIR . 'includes/ajax-handlers.php' ) ) {
        require_once DW_PLUGIN_DIR . 'includes/ajax-handlers.php';
    }
}