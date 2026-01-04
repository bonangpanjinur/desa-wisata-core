<?php
/**
 * File Name:   includes/init.php
 * Description: Inisialisasi logika utama plugin.
 * Fix:         File ini diperbaiki untuk mencegah Fatal Error.
 * Logika pengecekan user dipindahkan ke hook 'init'.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. LOAD CLASS DEFINITIONS
 * Load file class di level root agar bisa diakses kapan saja setelah plugin aktif.
 */
// Load Logic Favorit
if ( file_exists( DW_CORE_PATH . 'includes/class-dw-favorites.php' ) ) {
    require_once DW_CORE_PATH . 'includes/class-dw-favorites.php';
}

/**
 * 2. MAIN INIT FUNCTION
 * Dijalankan saat WordPress selesai memuat core functions (hook 'init').
 */
function dw_core_init_main() {
    
    // Cek apakah fungsi user sudah tersedia (Safety Check)
    if ( ! function_exists( 'is_user_logged_in' ) ) {
        return;
    }

    // --- AREA LOGIKA GLOBAL ---
    // Di sini tempat untuk registrasi Post Type, Taxonomy, atau logika global lainnya.
    
    // (Opsional) Jika ada logika inisialisasi otomatis untuk favorit, bisa ditaruh sini.
    // Saat ini logika favorit berjalan by request (API), jadi aman dibiarkan kosong
    // atau diisi logika background process lainnya.
}

// Hook ke 'init' agar aman dari error undefined function
add_action( 'init', 'dw_core_init_main' );

/**
 * 3. REST API INITIALIZATION
 * Hook khusus untuk mendaftarkan endpoint API.
 */
add_action( 'rest_api_init', function() {
    
    // Register API Favorit
    if ( file_exists( DW_CORE_PATH . 'includes/rest-api/api-favorites.php' ) ) {
        require_once DW_CORE_PATH . 'includes/rest-api/api-favorites.php';
        
        if ( class_exists( 'DW_API_Favorites' ) ) {
            $favorites_api = new DW_API_Favorites();
            $favorites_api->register_routes();
        }
    }

});