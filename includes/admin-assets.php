<?php
/**
 * Register Admin Assets
 * File: includes/admin-assets.php
 * Description: Mengatur loading aset Admin CSS/JS, Vendor libraries (Select2, SweetAlert, Leaflet), 
 * dan mengirim variabel Nonce/Data ke JavaScript.
 * @package DesaWisataCore
 * @version 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dw_admin_enqueue_scripts( $hook ) {
    // Hanya load di halaman plugin desa wisata atau yang terkait
    if ( strpos( $hook, 'desa-wisata' ) === false && strpos( $hook, 'dw-' ) === false && strpos( $hook, 'page_dw_' ) === false ) {
        return;
    }

    $version = '2.8.0'; // Updated version for Phase 4

    // 1. Core Admin CSS
    wp_enqueue_style( 'dw-admin-style', DW_PLUGIN_URL . 'assets/css/admin-style.css', [], $version );
    
    // 2. Vendor Libraries
    
    // Select2 (Untuk dropdown user/pencarian)
    wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css' );
    wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true );

    // SweetAlert2 (Untuk notifikasi cantik)
    wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true );

    // Leaflet JS (Untuk Peta / Fase 4.2)
    // Diload di semua halaman plugin agar tidak error jika ada fitur lokasi mendadak, 
    // atau bisa dikondisikan spesifik jika ingin hemat resource.
    wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
    wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );

    // 3. Main Admin Script
    wp_enqueue_script( 'dw-admin-js', DW_PLUGIN_URL . 'assets/js/admin-scripts.js', ['jquery', 'select2-js', 'sweetalert2'], $version, true );

    // 4. Custom Location Picker Script (Fase 4.2)
    wp_enqueue_script( 'dw-location-picker', DW_PLUGIN_URL . 'assets/js/dw-location-picker.js', ['jquery', 'leaflet-js'], $version, true );

    // 5. Localization: Kirim data dari PHP ke JS
    $localize_data = [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'dw_nonce_action' ), // Security Token Utama
        'strings'  => [
            'confirm_delete' => 'Apakah Anda yakin ingin menghapus data ini?',
            'confirm_verify' => 'Verifikasi pedagang ini?',
            'confirm_reject' => 'Tolak pedagang ini?',
            'success'        => 'Berhasil!',
            'error'          => 'Terjadi kesalahan.',
        ]
    ];

    wp_localize_script( 'dw-admin-js', 'dw_vars', $localize_data );

    // Localize untuk script peta juga (jika butuh data spesifik map)
    wp_localize_script( 'dw-location-picker', 'dw_maps', [
        'default_lat' => -7.5, // Default Center (bisa diubah via settings di masa depan)
        'default_lng' => 110.5,
        'icon_url'    => DW_PLUGIN_URL . 'assets/img/marker-icon.png', // Pastikan icon tersedia
        'nonce'       => wp_create_nonce( 'dw_nonce_frontend' ), // Token untuk akses frontend/public AJAX
        'ajax_url'    => admin_url( 'admin-ajax.php' )
    ]);
}
add_action( 'admin_enqueue_scripts', 'dw_admin_enqueue_scripts' );

/**
 * Enqueue untuk Frontend (Shortcode halaman dashboard/checkout/profil)
 * Digunakan saat shortcode plugin terdeteksi di halaman.
 */
function dw_enqueue_frontend_scripts() {
    global $post;
    
    // Cek apakah halaman mengandung shortcode plugin
    if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'dw_checkout' ) || has_shortcode( $post->post_content, 'dw_dashboard_toko' ) || has_shortcode( $post->post_content, 'dw_topup_paket' ) ) ) {
        
        // Load dependencies yang diperlukan di frontend
        wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
        wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );
        wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true );

        // Script Location Picker khusus frontend
        wp_enqueue_script( 'dw-location-picker', DW_PLUGIN_URL . 'assets/js/dw-location-picker.js', ['jquery', 'leaflet-js'], time(), true );
        
        wp_localize_script( 'dw-location-picker', 'dw_maps', [
            'nonce'       => wp_create_nonce( 'dw_nonce_frontend' ),
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'default_lat' => -7.5,
            'default_lng' => 110.5
        ]);
    }
}
add_action( 'wp_enqueue_scripts', 'dw_enqueue_frontend_scripts' );