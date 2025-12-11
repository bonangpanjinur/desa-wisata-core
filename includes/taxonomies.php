<?php
/**
 * File Name:   taxonomies.php
 * File Folder: includes/
 * File Path:   includes/taxonomies.php
 *
 * Mendaftarkan taksonomi kustom.
 *
 * --- PERBAIKAN (FATAL ERROR 500) ---
 * - MENAMBAHKAN hook `add_action('init', 'dw_register_taxonomies');`
 * - Fungsi ini sebelumnya hanya didefinisikan tapi tidak pernah dijalankan.
 * - Ini juga salah satu penyebab utama API 404.
 *
 * --- PERBAIKAN (ANALISIS API) ---
 * - Menambahkan hook `add_action` untuk `created_term`, `edited_term`,
 * dan `delete_term` untuk menghapus cache transient API publik
 * setiap kali kategori diperbarui.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fungsi untuk mendaftarkan taksonomi.
 */
function dw_register_taxonomies() {
    // Taksonomi Kategori Wisata
    $labels_cat_wisata = [
        'name'              => _x( 'Kategori Wisata', 'taxonomy general name', 'desa-wisata-core' ),
        'singular_name'     => _x( 'Kategori Wisata', 'taxonomy singular name', 'desa-wisata-core' ),
        'search_items'      => __( 'Cari Kategori', 'desa-wisata-core' ),
        'all_items'         => __( 'Semua Kategori', 'desa-wisata-core' ),
        'parent_item'       => __( 'Induk Kategori', 'desa-wisata-core' ),
        'parent_item_colon' => __( 'Induk Kategori:', 'desa-wisata-core' ),
        'edit_item'         => __( 'Edit Kategori', 'desa-wisata-core' ),
        'update_item'       => __( 'Perbarui Kategori', 'desa-wisata-core' ),
        'add_new_item'      => __( 'Tambah Kategori Baru', 'desa-wisata-core' ),
        'new_item_name'     => __( 'Nama Kategori Baru', 'desa-wisata-core' ),
        'menu_name'         => __( 'Kategori Wisata', 'desa-wisata-core' ),
    ];
    $args_cat_wisata = [
        'hierarchical'      => true,
        'labels'            => $labels_cat_wisata,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'kategori-wisata'],
        'show_in_rest'      => true, // Pastikan ini true untuk API
    ];
    register_taxonomy( 'kategori_wisata', ['dw_wisata'], $args_cat_wisata );

    // Taksonomi Kategori Produk
    $labels_cat_produk = [
        'name'              => _x( 'Kategori Produk', 'taxonomy general name', 'desa-wisata-core' ),
        'singular_name'     => _x( 'Kategori Produk', 'taxonomy singular name', 'desa-wisata-core' ),
        'menu_name'         => __( 'Kategori Produk', 'desa-wisata-core' ),
    ];
    $args_cat_produk = [
        'hierarchical'      => true,
        'labels'            => $labels_cat_produk,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'kategori-produk'],
        'show_in_rest'      => true, // Pastikan ini true untuk API
    ];
    register_taxonomy( 'kategori_produk', ['dw_produk'], $args_cat_produk );
}
// --- PERBAIKAN FATAL ERROR 500 ---
// Mendaftarkan fungsi di atas agar dijalankan oleh WordPress.
add_action( 'init', 'dw_register_taxonomies' );

// --- PERBAIKAN PERFORMA (Sesuai Analisis Poin 3.2.2) ---
/**
 * Hapus cache transient saat taksonomi diperbarui.
 */
function dw_clear_kategori_cache_on_edit($term_id, $tt_id, $taxonomy) {
    if ($taxonomy === 'kategori_produk') {
        delete_transient('dw_api_kategori_produk_cache');
    }
    if ($taxonomy === 'kategori_wisata') {
        delete_transient('dw_api_kategori_wisata_cache');
    }
}
add_action('created_term', 'dw_clear_kategori_cache_on_edit', 10, 3);
add_action('edited_term', 'dw_clear_kategori_cache_on_edit', 10, 3);
add_action('delete_term', 'dw_clear_kategori_cache_on_edit', 10, 3);
// --- AKHIR PERBAIKAN ---
?>