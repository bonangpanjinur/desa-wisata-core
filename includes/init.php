<?php
/**
 * File Path: includes/init.php
 * Description: Mendaftarkan aset dan variabel global untuk JavaScript.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Cek Update DB
function dw_plugin_update_check() {
    $current_version = get_option( 'dw_core_db_version', '1.0.0' );
    if ( version_compare( $current_version, DW_CORE_VERSION, '<' ) ) {
        if ( ! function_exists( 'dw_core_activate_plugin' ) ) {
            require_once DW_CORE_PLUGIN_DIR . 'includes/activation.php';
        }
        dw_core_activate_plugin();
    }
}
add_action( 'plugins_loaded', 'dw_plugin_update_check' );

// 2. Load Assets Admin
function dw_core_load_admin_assets($hook) {
    // Identifikasi Halaman Plugin
    $is_dw_page = (strpos($hook, 'dw-') !== false);
    
    // Identifikasi Post Type (Produk/Wisata)
    $screen = get_current_screen();
    $is_dw_cpt = ($screen && in_array($screen->post_type, ['dw_wisata', 'dw_produk']));

    if ( $is_dw_page || $is_dw_cpt ) {
        // Load CSS
        wp_enqueue_style( 'dw-admin-styles', DW_CORE_PLUGIN_URL . 'assets/css/admin-styles.css', [], DW_CORE_VERSION );
        
        // Load JS
        wp_enqueue_script( 'dw-admin-scripts', DW_CORE_PLUGIN_URL . 'assets/js/admin-scripts.js', ['jquery'], DW_CORE_VERSION, true );
        wp_enqueue_media();

        // [PENTING] Kirim Data ke JS (AJAX URL & REST API URL)
        wp_localize_script('dw-admin-scripts', 'dw_admin_vars', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('dw_admin_nonce'),
            'rest_url'   => esc_url_raw(rest_url('dw/v1/')), // URL API Internal
            'rest_nonce' => wp_create_nonce('wp_rest')       // Nonce untuk API
        ]);

        // Library tambahan untuk halaman tertentu
        if ($screen && $screen->id === 'desa-wisata_page_dw-pedagang') {
            wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], '4.0.13', true);
        }
        
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
?>