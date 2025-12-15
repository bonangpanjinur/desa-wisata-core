<?php
/**
 * File Name:   data-integrity.php
 * File Folder: includes/
 * File Path:   includes/data-integrity.php
 * * Description: 
 * Wrapper functions untuk berinteraksi dengan Custom Tables (Wisata & Produk).
 * Menggantikan fungsi meta WordPress standar (get_post_meta) dengan query langsung ke tabel custom.
 * * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Simpan Data Atribut Produk ke Custom Table
 */
function dw_save_produk_data( $post_id, $data ) {
    global $wpdb;
    $table = $wpdb->prefix . 'dw_produk'; // Gunakan nama tabel yg sesuai dengan activation.php

    // Catatan: Logic ini untuk sync jika masih pakai CPT, 
    // tapi karena kita sudah FULL Custom Table, fungsi ini lebih baik
    // digunakan sebagai Model/Controller untuk operasi insert/update ke tabel dw_produk.
    
    // ... Implementasi CRUD spesifik ...
}

/**
 * Helper: Ambil Data Produk
 */
function dw_get_produk_by_id( $id ) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_produk WHERE id = %d", $id));
}

/**
 * Helper: Ambil Data Wisata
 */
function dw_get_wisata_by_id( $id ) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_wisata WHERE id = %d", $id));
}
?>