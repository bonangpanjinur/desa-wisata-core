<?php
/**
 * File Path: includes/init.php
 *
 * PERBAIKAN (FATAL ERROR v3.1.3):
 * - File ini sekarang HANYA berisi definisi fungsi dan hook yang spesifik
 * untuk file ini (filter keamanan, CSP, support thumbnail).
 * - Hook untuk memuat aset (CSS/JS) dipindahkan ke file utama `desa-wisata-core.php`
 * untuk memastikan fungsi `dw_core_load_admin_assets` sudah ada saat dipanggil.
 *
 * PERBAIKAN (FATAL ERROR v3.2.2):
 * - Menghapus satu kurung kurawal penutup `}` yang tersesat di akhir file
 * yang menyebabkan PHP Parse Error (500 Internal Server Error).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- PERBAIKAN: Hapus definisi fungsi yang dipanggil oleh file utama ---
// --- Fungsi-fungsi ini sekarang dipanggil langsung dari file utama ---

function dw_plugin_update_check() {
    $current_version = get_option( 'dw_core_db_version', '1.0.0' );
    if ( version_compare( $current_version, DW_CORE_VERSION, '<' ) ) {
        // Pastikan fungsi aktivasi sudah dimuat jika belum
        if ( ! function_exists( 'dw_core_activate_plugin' ) ) {
            require_once DW_CORE_PLUGIN_DIR . 'includes/activation.php';
        }
        dw_core_activate_plugin();
    }
}
// Daftarkan hook ini
add_action( 'plugins_loaded', 'dw_plugin_update_check' );


function dw_core_load_frontend_assets() {
    // wp_enqueue_style( 'dw-frontend-styles', DW_CORE_PLUGIN_URL . 'assets/css/frontend-styles.css', [], DW_CORE_VERSION ); // <-- PERBAIKAN: File ini dihapus
    // wp_enqueue_script( 'dw-frontend-scripts', DW_CORE_PLUGIN_URL . 'assets/js/frontend-scripts.js', ['jquery'], DW_CORE_VERSION, true ); // <-- PERBAIKAN: File ini dihapus
    // wp_localize_script('dw-frontend-scripts', 'dw_frontend_vars', [
    //     'ajax_url' => admin_url('admin-ajax.php'),
    //     'nonce'    => wp_create_nonce('dw_frontend_nonce')
    // ]);
}
// Daftarkan hook ini
// add_action( 'wp_enqueue_scripts', 'dw_core_load_frontend_assets' ); // <-- PERBAIKAN: Hook ini dinonaktifkan


function dw_core_load_admin_assets($hook) {
    $screen = get_current_screen();
    
    $is_pedagang_page = ($screen && $screen->id === 'desa-wisata_page_dw-pedagang');
    
    $is_dw_page = (strpos($hook, 'dw-') !== false);
    $is_dw_cpt = ($screen && in_array($screen->post_type, ['dw_wisata', 'dw_produk']));

    $is_settings_page = ($screen && $screen->id === 'desa-wisata_page_dw-settings');


    if ( $is_dw_page || $is_dw_cpt ) {
        wp_enqueue_style( 'dw-admin-styles', DW_CORE_PLUGIN_URL . 'assets/css/admin-styles.css', [], DW_CORE_VERSION );
        wp_enqueue_script( 'dw-admin-scripts', DW_CORE_PLUGIN_URL . 'assets/js/admin-scripts.js', ['jquery'], DW_CORE_VERSION, true );
        wp_enqueue_media();

        wp_localize_script('dw-admin-scripts', 'dw_admin_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('dw_admin_nonce')
        ]);

        if ($is_pedagang_page) {
            wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], '4.0.13', true);
        }

        if ($is_settings_page) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'dw-admin-scripts' ); 
        }
    }
}
// Daftarkan hook ini
add_action( 'admin_enqueue_scripts', 'dw_core_load_admin_assets' );


// --- PERBAIKAN (SARAN PENINGKATAN KEAMANAN) ---
/**
 * 1. (Keamanan) Membatasi Media Library agar Pedagang/Admin Desa hanya melihat file mereka.
 */
function dw_filter_media_library_by_author($query) {
    if (!is_user_logged_in()) {
        return $query; // Harusnya tidak terjadi di admin
    }
    
    if (current_user_can('manage_options') || current_user_can('dw_manage_desa')) {
        return $query;
    }

    $user_id = get_current_user_id();
    if ($user_id > 0) {
        $query['author'] = $user_id;
    }
    return $query;
}
add_filter('ajax_query_attachments_args', 'dw_filter_media_library_by_author');


/**
 * [BARU] Perbaikan Paksa untuk Konflik CSP (Content Security Policy).
 */
function dw_remove_admin_csp_header() {
    if (is_admin() && !headers_sent()) {
        header_remove('Content-Security-Policy');
    }
}
add_action('admin_init', 'dw_remove_admin_csp_header', 999);


/**
 * [BARU] Perbaikan Paksa untuk Konflik Tema/Plugin.
 */
function dw_force_add_thumbnail_support() {
    add_post_type_support( 'dw_produk', 'thumbnail' );
    add_post_type_support( 'dw_wisata', 'thumbnail' );
}
add_action( 'init', 'dw_force_add_thumbnail_support', 99 );

// PERBAIKAN: Kurung kurawal `}` yang tersesat di sini telah dihapus.
?>