<?php
/**
 * Core Initialization
 * Path: includes/init.php
 */

if (!defined('ABSPATH')) {
    exit;
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

    // --- LEAFLET JS / OPENSTREETMAP (Untuk Fitur Ojek) ---
    // Hanya load jika ada shortcode ojek atau di halaman tertentu untuk performa
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
            'icon_origin' => DW_CORE_URL . 'assets/img/marker-origin.png', // Pastikan icon ada atau gunakan default leaflet
            'icon_dest' => DW_CORE_URL . 'assets/img/marker-dest.png',
            'icon_driver' => DW_CORE_URL . 'assets/img/marker-driver.png'
        ));
    }
}
add_action('wp_enqueue_scripts', 'dw_enqueue_scripts');

/**
 * Enqueue Admin Scripts
 */
function dw_admin_enqueue_scripts($hook) {
    wp_enqueue_style('dw-admin-style', DW_CORE_URL . 'assets/css/admin-style.css', array(), DW_CORE_VERSION);
    wp_enqueue_script('dw-admin-script', DW_CORE_URL . 'assets/js/admin-scripts.js', array('jquery'), DW_CORE_VERSION, true);

    // Jika di halaman Ojek Management, mungkin butuh maps juga
    if (strpos($hook, 'page_dw-ojek') !== false) {
         // Load Leaflet di Admin jika perlu tracking posisi driver
    }
}
add_action('admin_enqueue_scripts', 'dw_admin_enqueue_scripts');

/**
 * Initialize Ojek Handler
 * Ensure class is loaded if available
 */
function dw_init_ojek_handler() {
    if ( file_exists( DW_CORE_PATH . 'includes/classes/class-dw-ojek-handler.php' ) ) {
        require_once DW_CORE_PATH . 'includes/classes/class-dw-ojek-handler.php';
    } elseif ( file_exists( DW_CORE_PATH . 'includes/class-dw-ojek-handler.php' ) ) {
        require_once DW_CORE_PATH . 'includes/class-dw-ojek-handler.php';
    }

    if ( class_exists( 'DW_Ojek_Handler' ) ) {
        DW_Ojek_Handler::init();
    }
}
add_action( 'plugins_loaded', 'dw_init_ojek_handler' );