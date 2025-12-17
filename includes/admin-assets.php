<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fungsi untuk memuat CSS dan JS Admin
 */
function dw_enqueue_admin_scripts( $hook ) {
    // Muat script ini di halaman admin saja.
    // Jika ingin spesifik hanya di halaman plugin Anda, gunakan:
    // if ( strpos($hook, 'dw-verifikasi') === false && strpos($hook, 'dw-komisi') === false ) return;

    // 1. Register & Enqueue Script JS
    wp_enqueue_script( 
        'dw-admin-js', // Handle ID
        plugin_dir_url( __DIR__ ) . 'assets/js/dw-admin-script.js', // Path ke file JS
        array( 'jquery' ), // Wajib butuh jQuery
        time(), // Gunakan time() saat dev agar tidak cache
        true // Load di footer
    );

    // 2. Kirim Variabel PHP ke JS (Localize Script)
    // Ini PENTING agar JS tahu URL ajax dan Nonce keamanan
    wp_localize_script( 'dw-admin-js', 'dw_admin_vars', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'dw_admin_nonce' ) // Harus cocok dengan check_ajax_referer di PHP
    ));
}
add_action( 'admin_enqueue_scripts', 'dw_enqueue_admin_scripts' );
?>