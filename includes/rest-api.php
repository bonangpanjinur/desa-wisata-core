<?php
/**
 * File Name:   rest-api.php
 * PERBAIKAN (KRITIS v3.2.4):
 * - Memanggil fungsi `dw_api_register_public_routes()` dan `dw_api_register_pembeli_routes()`
 * secara langsung.
 * - Sebelumnya, file-file tersebut mencoba meng-hook diri mereka sendiri ke `rest_api_init`,
 * tapi itu sudah terlambat (race condition), sehingga route mereka (termasuk /settings)
 * tidak pernah terdaftar dan menyebabkan error 404.
 *
 * PERBAIKAN (PERFORMA):
 * - Memindahkan semua `require_once` ke dalam fungsi `dw_register_rest_routes`
 * agar hanya dimuat saat hook `rest_api_init` berjalan.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Mendaftarkan semua REST route dengan memuat file modular.
 */
function dw_register_rest_routes() {
    
    // --- PERBAIKAN: Hapus 'require_once' untuk file yang sudah dimuat ---
    // File-file ini sudah dimuat oleh desa-wisata-core.php (file utama).
    // Memuatnya lagi di sini (meskipun 'once') menyebabkan konflik urutan
    // yang memicu Fatal Error "Cannot redeclare dw_check_pedagang_kuota()".
    /*
    require_once DW_CORE_PLUGIN_DIR . 'includes/helpers.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/cart.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/reviews.php';
    */
    // --- AKHIR PERBAIKAN ---
    
    $namespace = 'dw/v1'; 
    $admin_namespace = $namespace . '/admin'; 

    // --- Memuat File Modular Endpoint ---
    
    // 1. Helpers & Permissions (Internal)
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/api-helpers.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/api-permissions.php';

    // 2. Endpoint Otentikasi
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/api-auth.php';
    
    // 3. Endpoint Publik
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/api-public.php';

    // 4. Endpoint Pembeli
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/api-pembeli.php';
    
    // 5. Endpoint Pedagang
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/api-pedagang.php';

    // 6. Endpoint Admin Desa
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/api-admin-desa.php';

    // 7. Endpoint Administratif
    require_once DW_CORE_PLUGIN_DIR . 'includes/rest-api/api-admin.php';

    // --- PERBAIKAN (KRITIS v3.2.4): Panggil fungsi pendaftaran ---
    // File-file ini berisi fungsi wrapper, jadi kita panggil di sini.
    dw_api_register_public_routes();
    dw_api_register_pembeli_routes();
    // File lain (auth, pedagang, admin) mendaftarkan route secara langsung
    // jadi tidak perlu dipanggil.
    // --- AKHIR PERBAIKAN ---
}
add_action('rest_api_init', 'dw_register_rest_routes');
?>