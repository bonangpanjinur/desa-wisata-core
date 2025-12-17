<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enqueue scripts khusus untuk Halaman Admin
 */
function dw_enqueue_admin_scripts( $hook ) {
    // Muat script ini di semua halaman admin, atau batasi dengan `if ( 'toplevel_page_nama-menu' !== $hook ) return;`
    
    // Pastikan path file JS sesuai dengan lokasi Anda menyimpan file JS di atas
    wp_enqueue_script( 
        'dw-admin-script', 
        plugin_dir_url( __DIR__ ) . 'assets/js/dw-admin-script.js', // Asumsi file ada di folder assets/js/
        array( 'jquery' ), 
        time(), // Gunakan time() saat dev agar tidak cache
        true 
    );

    // Kirim variabel dari PHP ke JS (PENTING: Nonce ada di sini)
    wp_localize_script( 'dw-admin-script', 'dw_admin_vars', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'dw_admin_nonce' ) // Kunci keamanan yang dicek di ajax-handlers.php
    ));
}
add_action( 'admin_enqueue_scripts', 'dw_enqueue_admin_scripts' );
?>