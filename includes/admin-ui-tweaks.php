<?php
/**
 * File Path: includes/admin-ui-tweaks.php
 *
 * PERBAIKAN (KRITIS):
 * - Mengembalikan file ini ke versi yang stabil dan sederhana.
 * - Hanya mendaftarkan meta box kustom dan menghapus beberapa meta box WordPress
 * yang tidak relevan, tanpa mengganggu layout utama editor (judul dan konten).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DW_Admin_UI_Tweaks {

    public function __construct() {
        // Menggunakan hook standar untuk menambah dan mengatur meta box
        add_action( 'add_meta_boxes', [ $this, 'setup_meta_boxes' ], 10, 2 );
    }

    public function setup_meta_boxes( $post_type, $post ) {
        // Hanya jalankan pada CPT yang kita inginkan
        if ( !in_array($post_type, ['dw_wisata', 'dw_produk']) ) {
            return;
        }

        // Hapus meta box yang tidak relevan untuk UI yang lebih bersih
        remove_meta_box( 'slugdiv', $post_type, 'normal' );
        remove_meta_box( 'postcustom', $post_type, 'normal' ); 
        remove_meta_box( 'commentstatusdiv', $post_type, 'normal' );
        remove_meta_box( 'commentsdiv', $post_type, 'normal' );
        remove_meta_box( 'authordiv', 'dw_wisata', 'normal' ); // Sembunyikan author khusus untuk Wisata

        // Daftarkan meta box kustom kita dengan cara standar WordPress
        if ($post_type === 'dw_produk') {
            add_meta_box(
                'dw_produk_details_meta_box', 
                'Detail Informasi Produk', 
                'dw_produk_details_meta_box_html', 
                'dw_produk', 
                'normal', // Tampilkan di kolom utama, di bawah editor
                'high'
            );
        }

        if ($post_type === 'dw_wisata') {
            add_meta_box(
                'dw_wisata_details_meta_box', 
                'Detail Informasi Wisata', 
                'dw_wisata_details_meta_box_html', 
                'dw_wisata', 
                'normal', // Tampilkan di kolom utama, di bawah editor
                'high'
            );
        }
    }
}

new DW_Admin_UI_Tweaks();

