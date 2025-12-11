<?php
/**
 * File Name:   template-loader.php
 * File Folder: includes/
 * File Path:   includes/template-loader.php
 *
 * Mengelola pemuatan file template frontend dari plugin.
 *
 * PERUBAHAN KRITIS (MODE HEADLESS):
 * - Template loader diubah untuk SECARA PAKSA memuat template plugin
 * untuk CPT 'dw_produk' dan 'dw_wisata' untuk memastikan outputnya adalah JSON,
 * mengabaikan template tema.
 *
 * @package DesaWisataCore
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Memuat template CPT 'dw_produk' dan 'dw_wisata' dari plugin.
 *
 * @param string $template Path ke file template yang akan digunakan.
 * @return string Path ke file template yang baru.
 */
function dw_template_loader( $template ) {
    // Tentukan tipe post yang dikelola oleh loader ini
    $post_types = [ 'dw_produk', 'dw_wisata' ];

    // Dapatkan ID post global jika tersedia
    $post_id = get_the_ID();

    // **Pemeriksaan Kompatibilitas Elementor/Headless**
    // Karena fokus kita Headless, kita akan memaksakan template plugin untuk CPT tertentu.
    
    // Cek jika ini adalah halaman single untuk CPT yang kita kelola
    if ( is_singular( $post_types ) ) {
        $post_type = get_post_type();
        $plugin_template = DW_CORE_PLUGIN_DIR . "templates/single-{$post_type}.php";
        
        // **Paksa gunakan template plugin (yang kini mengeluarkan JSON)**
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }
    }
    
    // Cek jika ini adalah halaman arsip untuk CPT yang kita kelola
    if ( is_post_type_archive( $post_types ) ) {
        $post_type = get_query_var('post_type');
        $plugin_template = DW_CORE_PLUGIN_DIR . "templates/archive-{$post_type}.php";
        
        // Archive juga harus mengeluarkan JSON (jika ada template archive-*.php yang diubah ke JSON)
        // Jika tidak, kita biarkan default WordPress, kecuali archive-produk.php diubah ke JSON.
        // Karena template archive-produk.php Anda masih HTML, kita biarkan default WordPress.
        // Jika frontend membutuhkan daftar produk, mereka HARUS menggunakan REST API /dw/v1/produk.
        // Kita hanya fokus pada single post JSON output.
    }

    // Kembalikan template default jika bukan halaman yang kita kelola atau jika kita ingin
    // membiarkan WordPress menangani archive dengan REST API.
    return $template;
}
// Hook ini sudah terdaftar di includes/init.php
// add_action( 'template_include', 'dw_template_loader' );
