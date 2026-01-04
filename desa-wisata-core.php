<?php
/**
 * File Name:   desa-wisata-core.php
 * Plugin Name: Desa Wisata Core
 * Version:     3.3.1 (CORS & Fixes)
 *
 * --- PERBAIKAN V3.3.1 (ERROR 403 & DNS) ---
 * - Memperbaiki `dw_central_cors_handler` agar tidak memblokir request OPTIONS
 * secara agresif yang menyebabkan error 403 pada admin-ajax.php.
 * - Menambahkan validasi keamanan tambahan untuk akses file langsung.
 */

// Mencegah akses langsung ke file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DW_CORE_VERSION', '3.3.1' ); 
define( 'DW_CORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DW_CORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DW_CORE_PLUGIN_FILE', __FILE__ );

// [Compatibility Fix] Alias Constants 
// Menambahkan alias agar kompatibel dengan init.php yang menggunakan DW_CORE_PATH
define( 'DW_CORE_PATH', DW_CORE_PLUGIN_DIR );
define( 'DW_CORE_URL', DW_CORE_PLUGIN_URL );

// ** 1. Memuat Composer Autoload **
if ( file_exists( DW_CORE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once DW_CORE_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * ** 2. Memuat semua file dependensi **
 */
function dw_core_load_dependencies() {
    
    // -----------------------------------------------------------------
    // TAHAP 1: Helper Inti & API Eksternal
    // -----------------------------------------------------------------
    require_once DW_CORE_PLUGIN_DIR . 'includes/helpers.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/logs.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/address-api.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/relasi-handler.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/commission-handler.php';

    // -----------------------------------------------------------------
    // TAHAP 2: Logika Bisnis & Penanganan Data
    // -----------------------------------------------------------------
    require_once DW_CORE_PLUGIN_DIR . 'includes/cart.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/data-integrity.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/reviews.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/promotions.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/whatsapp-templates.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/cron-jobs.php'; 

    // -----------------------------------------------------------------
    // TAHAP 3: Inisialisasi UI, Route, dan Role
    // -----------------------------------------------------------------
    require_once DW_CORE_PLUGIN_DIR . 'includes/post-types.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/taxonomies.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-menus.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/meta-boxes.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/roles-capabilities.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/user-profiles.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/ajax-handlers.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-ui-tweaks.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/init.php'; 
    
    // --- Access Control ---
    require_once DW_CORE_PLUGIN_DIR . 'includes/access-control.php';
}
dw_core_load_dependencies();


/**
 * ** 3. Mendaftarkan hook aktivasi & deaktivasi **
 */
function dw_core_activate() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/activation.php';
    dw_core_activate_plugin();
}
register_activation_hook( __FILE__, 'dw_core_activate' );

function dw_core_deactivate() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/deactivation.php';
    dw_core_deactivate_plugin();
}
register_deactivation_hook( __FILE__, 'dw_core_deactivate' );

// Load ulang address-api jika diperlukan di global scope (sesuai kode asli)
require_once plugin_dir_path(__FILE__) . 'includes/address-api.php';

// 2. Pastikan file script di-enqueue (jika belum ada di admin-assets.php)
function dw_core_enqueue_admin_scripts($hook) {
    // Hanya load di halaman plugin kita untuk optimasi
    wp_enqueue_script(
        'dw-admin-script',
        plugin_dir_url(__FILE__) . 'assets/js/dw-admin-script.js',
        array('jquery'),
        '1.0.0',
        true
    );
}
add_action('admin_enqueue_scripts', 'dw_core_enqueue_admin_scripts');
// =========================================================================
// PERBAIKAN CORS (v3.3.1)
// =========================================================================

function dw_central_cors_handler($value = null) {
    
    // 1. Ambil pengaturan origins dari database
    $options = get_option('dw_settings');
    $allowed_origins_string = $options['allowed_cors_origins'] ?? '';
    $allowed_origins = array_filter(array_map('trim', explode("\n", $allowed_origins_string)));

    // Default origins jika kosong
    if (empty($allowed_origins)) {
        $allowed_origins = [
            'https://sadesa.site',
            'http://localhost:3000',
            'http://localhost:8000',
            site_url() // Selalu izinkan domain sendiri
        ];
    }

    $origin = get_http_origin();
    
    // 2. Set Header jika Origin cocok
    if (!empty($origin) && in_array($origin, $allowed_origins)) {
        if (!headers_sent()) {
            header("Access-Control-Allow-Origin: " . $origin);
            header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Guest-ID");
            header("Access-Control-Allow-Credentials: true");
        }
    }

    // 3. Handle Preflight Request (OPTIONS)
    // PERBAIKAN: Jangan memblokir (403) jika ini adalah request internal Admin Panel 
    // (biasanya same-origin, atau origin header tidak dikirim browser untuk same-origin simple requests)
    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
        if (!empty($origin) && in_array($origin, $allowed_origins)) {
            status_header(200);
            exit(); 
        } 
        // JANGAN return 403 di sini secara default, biarkan WordPress yang menangani.
        // Memblokir di sini menyebabkan admin-ajax.php error jika header Origin tidak sesuai ekspektasi.
    }

    return $value;
}
add_action('init', 'dw_central_cors_handler', 5);
add_filter('rest_pre_serve_request', 'dw_central_cors_handler', 5);