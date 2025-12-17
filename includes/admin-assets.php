<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enqueue Admin Scripts & Styles
 */
function dw_enqueue_admin_assets( $hook ) {
    // Opsional: Batasi hanya di halaman plugin Anda agar tidak bentrok
    // if ( strpos($hook, 'page_dw_') === false ) return; 

    // Tentukan path file utama plugin untuk mendapatkan URL root yang benar
    // dirname(__FILE__) = includes/
    // dirname(dirname(__FILE__)) = root plugin/
    $plugin_root = dirname( dirname( __FILE__ ) ) . '/desa-wisata-core.php';

    // 1. CSS Admin (Arahkan ke root/assets/css/...)
    wp_enqueue_style( 
        'dw-admin-style', 
        plugins_url( 'assets/css/admin-styles.css', $plugin_root ), 
        array(), 
        '1.0.0' 
    );

    // 2. JS Admin (Arahkan ke root/assets/js/...)
    wp_enqueue_script( 
        'dw-admin-script', 
        plugins_url( 'assets/js/admin-scripts.js', $plugin_root ), 
        array( 'jquery' ), // Wajib load jQuery
        '1.0.0', 
        true 
    );

    // 3. PENTING: Kirim variabel PHP ke JS (Localization)
    // Pastikan handler AJAX di PHP memverifikasi nonce 'dw_admin_nonce'
    wp_localize_script( 'dw-admin-script', 'dw_admin_vars', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'dw_admin_nonce' ), // Kunci keamanan global admin
    ));
}
add_action( 'admin_enqueue_scripts', 'dw_enqueue_admin_assets' );