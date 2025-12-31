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
 * Fungsi inisialisasi plugin.
 * Dijalankan saat WordPress selesai memuat core functions.
 */
function dw_core_init_main() {
    
    // Cek apakah fungsi user sudah tersedia (Safety Check)
    if ( ! function_exists( 'is_user_logged_in' ) ) {
        return;
    }

    // --- AREA LOGIKA GLOBAL ---
    // Di sini tempat untuk registrasi Post Type, Taxonomy, atau logika global lainnya.
    // JANGAN menaruh kode tampilan Dashboard (HTML) di sini.
    
    // Contoh penggunaan yang benar:
    /*
    if ( is_user_logged_in() ) {
        // Logika backend background process
    }
    */
}

// Hook ke 'init' agar aman dari error undefined function
add_action( 'init', 'dw_core_init_main' );