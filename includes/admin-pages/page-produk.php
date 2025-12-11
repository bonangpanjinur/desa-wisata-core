<?php
/**
 * File Name:   page-produk.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-produk.php
 *
 * Halaman admin untuk mengelola Produk.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Catatan Pengembang:
 * -------------------
 * Halaman ini sengaja dikosongkan dari tabel CRUD manual.
 *
 * Sesuai keputusan pada file todolist.md ("Decision: For developer friendliness, use CPT for wisata & produk..."), 
 * manajemen data Produk (tambah, edit, hapus) ditangani melalui antarmuka Custom Post Type (CPT) bawaan WordPress.
 *
 * Ini memberikan pengalaman pengguna yang lebih baik dan konsisten, serta memudahkan pengelolaan media, kategori, dan meta data.
 *
 * Anda bisa mengakses halaman pengelolaan Produk langsung dari menu admin:
 * "Desa Wisata" -> "Produk".
 */
?>
<div class="wrap">
    <h1>Pengelolaan Produk</h1>
    <div class="notice notice-info inline">
        <p>
            <strong>Catatan:</strong> Pengelolaan data Produk (tambah, edit, hapus) dilakukan melalui menu 
            <a href="<?php echo admin_url('edit.php?post_type=dw_produk'); ?>">Produk</a> 
            yang menggunakan antarmuka standar WordPress untuk kemudahan penggunaan.
        </p>
    </div>
</div>

