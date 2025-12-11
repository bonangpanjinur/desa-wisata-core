<?php
/**
 * File Path: includes/post-types.php
 *
 * PERBAIKAN (KRITIS):
 * - Menambahkan kembali 'title' dan 'editor' ke dalam array 'supports' untuk CPT 'dw_wisata' dan 'dw_produk'.
 * - Ini akan memunculkan kembali kolom input Judul/Nama dan editor teks utama di halaman editor.
 *
 * --- PERBAIKAN (FATAL ERROR 500) ---
 * - MENAMBAHKAN hook `add_action('init', 'dw_register_post_types');`
 * - Fungsi ini sebelumnya hanya didefinisikan tapi tidak pernah dijalankan.
 * - Ini adalah salah satu penyebab utama API 404 (endpoint tidak ditemukan).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fungsi untuk mendaftarkan semua CPT.
 */
function dw_register_post_types() {
    
    // CPT untuk Wisata
    $labels_wisata = [
        'name'                  => _x( 'Wisata', 'Post type general name', 'desa-wisata-core' ),
        'singular_name'         => _x( 'Wisata', 'Post type singular name', 'desa-wisata-core' ),
        'menu_name'             => _x( 'Wisata', 'Admin Menu text', 'desa-wisata-core' ),
        'name_admin_bar'        => _x( 'Wisata', 'Add New on Toolbar', 'desa-wisata-core' ),
        'add_new'               => __( 'Tambah Baru', 'desa-wisata-core' ),
        'add_new_item'          => __( 'Tambah Wisata Baru', 'desa-wisata-core' ),
        'new_item'              => __( 'Wisata Baru', 'desa-wisata-core' ),
        'edit_item'             => __( 'Edit Wisata', 'desa-wisata-core' ),
        'view_item'             => __( 'Lihat Wisata', 'desa-wisata-core' ),
        'all_items'             => __( 'Semua Wisata', 'desa-wisata-core' ),
    ];
    $args_wisata = [
        'labels'             => $labels_wisata,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => false, // Akan ditampilkan via submenu
        'query_var'          => true,
        'rewrite'            => ['slug' => 'wisata'],
        'capability_type'    => 'post', // Menggunakan kapabilitas post standar
        'map_meta_cap'       => true, // Penting untuk filter `map_meta_cap`
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => ['title', 'editor', 'thumbnail', 'author', 'custom-fields'], // PERBAIKAN: 'title' dan 'editor' ditambahkan
        'menu_icon'          => 'dashicons-location-alt',
        'show_in_rest'       => true, // Pastikan ini true untuk API
    ];
    register_post_type( 'dw_wisata', $args_wisata );

    // CPT untuk Produk
    $labels_produk = [
        'name'                  => _x( 'Produk', 'Post type general name', 'desa-wisata-core' ),
        'singular_name'         => _x( 'Produk', 'Post type singular name', 'desa-wisata-core' ),
        'menu_name'             => _x( 'Produk', 'Admin Menu text', 'desa-wisata-core' ),
        'name_admin_bar'        => _x( 'Produk', 'Add New on Toolbar', 'desa-wisata-core' ),
        'add_new'               => __( 'Tambah Baru', 'desa-wisata-core' ),
        'add_new_item'          => __( 'Tambah Produk Baru', 'desa-wisata-core' ),
    ];
    $args_produk = [
        'labels'             => $labels_produk,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => false, // Akan ditampilkan via submenu
        'query_var'          => true,
        'rewrite'            => ['slug' => 'produk'],
        'capability_type'    => 'post', // Menggunakan kapabilitas post standar
        'map_meta_cap'       => true, // Penting untuk filter `map_meta_cap`
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => ['title', 'editor', 'thumbnail', 'author', 'custom-fields'], // PERBAIKAN: 'title' dan 'editor' ditambahkan
        'menu_icon'          => 'dashicons-products',
        'show_in_rest'       => true, // Pastikan ini true untuk API
    ];
    register_post_type( 'dw_produk', $args_produk );
}
// --- PERBAIKAN FATAL ERROR 500 ---
// Mendaftarkan fungsi di atas agar dijalankan oleh WordPress.
add_action( 'init', 'dw_register_post_types' );
?>