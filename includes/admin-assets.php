<?php
/**
 * Register Admin Assets
 * Path: includes/admin-assets.php
 * * Mengatur loading aset Admin CSS/JS dan mengirim variabel Nonce ke JavaScript.
 * @package DesaWisataCore
 * @version 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function dw_admin_enqueue_scripts($hook) {
    // Hanya load di halaman plugin desa wisata
    if (strpos($hook, 'desa-wisata') === false && strpos($hook, 'dw-') === false) {
        return;
    }

    $version = '2.5.0'; // Updated version

    // CSS
    wp_enqueue_style('dw-admin-style', DW_PLUGIN_URL . 'assets/css/admin-style.css', [], $version);
    
    // Vendor: Select2 (Jika dibutuhkan untuk dropdown user)
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

    // SweetAlert2
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);

    // Main Admin Script
    wp_enqueue_script('dw-admin-js', DW_PLUGIN_URL . 'assets/js/admin-scripts.js', ['jquery'], $version, true);

    // Localization: Kirim data dari PHP ke JS (PENTING UNTUK SECURITY)
    wp_localize_script('dw-admin-js', 'dw_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('dw_nonce_action'), // Security Token
        'strings'  => [
            'confirm_delete' => 'Apakah Anda yakin ingin menghapus data ini?',
            'confirm_verify' => 'Verifikasi pedagang ini?',
            'confirm_reject' => 'Tolak pedagang ini?',
            'success'        => 'Berhasil!',
            'error'          => 'Terjadi kesalahan.',
        ]
    ]);
}
add_action('admin_enqueue_scripts', 'dw_admin_enqueue_scripts');