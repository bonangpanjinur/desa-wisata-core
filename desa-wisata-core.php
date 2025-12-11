<?php
/**
 * File Name:   desa-wisata-core.php
 * Plugin Name:       Desa Wisata Core
 * Version:           3.2.5 (Perbaikan Urutan Pemuatan)
 *
 * --- PERBAIKAN V3.2.5 (FIX FATAL ERROR) ---
 * - Mengubah urutan `require_once` di `dw_core_load_dependencies` secara drastis.
 * - Memindahkan file logika bisnis (cart.php, data-integrity.php, reviews.php)
 * ke atas agar dimuat SEBELUM file UI (admin-menus.php, rest-api.php)
 * yang memanggil fungsi-fungsi dari file tersebut.
 * - Ini memperbaiki fatal error "Call to undefined function" untuk
 * dw_get_pedagang_orders() dan dw_pedagang_delete_handler().
 */

// Mencegah akses langsung ke file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DW_CORE_VERSION', '3.2.5' ); 
define( 'DW_CORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DW_CORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DW_CORE_PLUGIN_FILE', __FILE__ );

// ** 1. Memuat Composer Autoload **
if ( file_exists( DW_CORE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once DW_CORE_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * ** 2. Memuat semua file dependensi **
 * --- PERBAIKAN: Urutan file diubah total ---
 */
function dw_core_load_dependencies() {
    
    // -----------------------------------------------------------------
    // TAHAP 1: Helper Inti & API Eksternal
    // (File-file ini tidak memiliki dependensi internal)
    // -----------------------------------------------------------------
	require_once DW_CORE_PLUGIN_DIR . 'includes/helpers.php'; 
	require_once DW_CORE_PLUGIN_DIR . 'includes/logs.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/address-api.php';

    // -----------------------------------------------------------------
    // TAHAP 2: Logika Bisnis & Penanganan Data
    // (File-file ini berisi fungsi yang akan dipanggil oleh TAHAP 3)
    // -----------------------------------------------------------------
	require_once DW_CORE_PLUGIN_DIR . 'includes/cart.php'; // DIPINDAHKAN KE ATAS
	require_once DW_CORE_PLUGIN_DIR . 'includes/data-integrity.php'; // DIPINDAHKAN KE ATAS
	require_once DW_CORE_PLUGIN_DIR . 'includes/reviews.php';
	require_once DW_CORE_PLUGIN_DIR . 'includes/promotions.php'; 
	require_once DW_CORE_PLUGIN_DIR . 'includes/whatsapp-templates.php';
	require_once DW_CORE_PLUGIN_DIR . 'includes/cron-jobs.php'; 

    // -----------------------------------------------------------------
    // TAHAP 3: Inisialisasi UI, Route, dan Role
    // (File-file ini memanggil fungsi dari TAHAP 1 & 2)
    // -----------------------------------------------------------------
	require_once DW_CORE_PLUGIN_DIR . 'includes/post-types.php'; 
	require_once DW_CORE_PLUGIN_DIR . 'includes/taxonomies.php'; 
	require_once DW_CORE_PLUGIN_DIR . 'includes/admin-menus.php'; // (Membutuhkan cart.php & data-integrity.php)
	require_once DW_CORE_PLUGIN_DIR . 'includes/meta-boxes.php'; 
	require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api.php'; // (Membutuhkan data-integrity.php)
	require_once DW_CORE_PLUGIN_DIR . 'includes/roles-capabilities.php'; 
	require_once DW_CORE_PLUGIN_DIR . 'includes/user-profiles.php'; 
	require_once DW_CORE_PLUGIN_DIR . 'includes/ajax-handlers.php'; 
	require_once DW_CORE_PLUGIN_DIR . 'includes/admin-ui-tweaks.php'; 
    require_once DW_CORE_PLUGIN_DIR . 'includes/init.php'; 
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


// =========================================================================
// PERBAIKAN CORS (v3.2.0)
// =========================================================================

function dw_central_cors_handler($value = null) {
    
    // --- PERBAIKAN #2: Pindahkan CORS ke Database ---
    $options = get_option('dw_settings');
    $allowed_origins_string = $options['allowed_cors_origins'] ?? '';
    
    $allowed_origins = array_filter(array_map('trim', explode("\n", $allowed_origins_string)));

    if (empty($allowed_origins)) {
        $allowed_origins = [
            'https://sadesa.site', // DOMAIN FRONTEND ANDA
            'https://www.sadesa.site', 
            'http://localhost:3000', // Untuk development lokal
        ];
    }
    // --- AKHIR PERBAIKAN #2 ---

    $origin = get_http_origin();
    
    if (!empty($origin) && in_array($origin, $allowed_origins)) {
        if (!headers_sent()) {
            header("Access-Control-Allow-Origin: " . $origin);
            header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Guest-ID");
            header("Access-Control-Allow-Credentials: true");
        }
    }

    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
        if (!empty($origin) && in_array($origin, $allowed_origins)) {
            status_header(200);
            exit(); 
        } else {
            status_header(403); 
            exit();
        }
    }

    return $value;
}
add_action('init', 'dw_central_cors_handler', 5);
add_filter('rest_pre_serve_request', 'dw_central_cors_handler', 5);
?>