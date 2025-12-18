<?php
// includes/admin-assets.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue scripts and styles for admin
 */
function dw_enqueue_admin_assets( $hook ) {
    // Definisi URL Plugin yang aman
    $plugin_url = defined('DW_PLUGIN_URL') ? DW_PLUGIN_URL : plugin_dir_url( dirname( __FILE__ ) );
    $version = (defined('WP_DEBUG') && WP_DEBUG) ? time() : '1.0.1';

    // 1. CSS Admin
    wp_enqueue_style( 'dw-admin-style', $plugin_url . 'assets/css/admin-styles.css', array(), $version );

    // 2. JS: dw-admin-script.js (Script Baru/Utama kita)
    wp_enqueue_script( 'dw-admin-script', $plugin_url . 'assets/js/dw-admin-script.js', array( 'jquery' ), $version, true );

    // Localize untuk dw-admin-script.js (menggunakan 'dw_ajax')
    wp_localize_script( 'dw-admin-script', 'dw_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'dw_admin_nonce' ),
        'site_url' => site_url()
    ));

    // 3. JS: admin-scripts.js (Script Lama/Legacy yang menyebabkan error)
    // Kita enqueue ulang atau pastikan jika sudah ter-enqueue oleh file lain, dia punya variabelnya.
    // Jika file ini ada di folder assets/js/admin-scripts.js:
    if ( file_exists( dirname( dirname( __FILE__ ) ) . '/assets/js/admin-scripts.js' ) ) {
        wp_enqueue_script( 'dw-legacy-admin-script', $plugin_url . 'assets/js/admin-scripts.js', array('jquery'), $version, true );
        
        // FIX ERROR: "dw_admin_vars tidak ditemukan"
        // Kita suntikkan variabel yang dibutuhkan oleh script lama ini.
        wp_localize_script( 'dw-legacy-admin-script', 'dw_admin_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'dw_admin_nonce' ),
            'root'     => esc_url_raw( rest_url() )
        ));
    }
}
add_action( 'admin_enqueue_scripts', 'dw_enqueue_admin_assets' );