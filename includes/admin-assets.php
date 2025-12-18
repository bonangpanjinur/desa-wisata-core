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
    // Kita cek path absolut file untuk memastikan keberadaannya
    $legacy_js_path = dirname( dirname( __FILE__ ) ) . '/assets/js/admin-scripts.js';
    $legacy_js_url  = $plugin_url . 'assets/js/admin-scripts.js';

    if ( file_exists( $legacy_js_path ) ) {
        // Enqueue script lama dengan handle berbeda agar tidak bentrok
        wp_enqueue_script( 'dw-legacy-admin-script', $legacy_js_url, array('jquery'), $version, true );
        
        // FIX ERROR CRITICAL: "dw_admin_vars tidak ditemukan"
        // Kita suntikkan objek 'dw_admin_vars' yang dicari oleh admin-scripts.js
        wp_localize_script( 'dw-legacy-admin-script', 'dw_admin_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'dw_admin_nonce' ),
            'root'     => esc_url_raw( rest_url() ),
            'site_url' => site_url() // Tambahan jika dibutuhkan
        ));
    }
}
add_action( 'admin_enqueue_scripts', 'dw_enqueue_admin_assets' );