<?php
/**
 * File Name:   page-wisata.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-wisata.php
 *
 * Halaman admin untuk mengelola Wisata.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Catatan Pengembang:
 * -------------------
 * Halaman ini sengaja dikosongkan.
 *
 * Sesuai keputusan pada file todolist.md ("Decision: For developer friendliness, use CPT for wisata & produk..."), 
 * manajemen data Wisata (tambah, edit, hapus) ditangani melalui antarmuka Custom Post Type (CPT) bawaan WordPress.
 *
 * Ini memberikan pengalaman pengguna yang lebih baik dan konsisten, serta memudahkan pengelolaan media (gambar).
 *
 * Anda bisa mengakses halaman pengelolaan Wisata langsung dari menu admin:
 * "Desa Wisata" -> "Wisata".
 */
?>
<div class="wrap">
    <h1>Pengelolaan Wisata</h1>
    <div class="notice notice-info inline">
        <p>
            <strong>Catatan:</strong> Pengelolaan data Wisata (tambah, edit, hapus) dilakukan melalui menu 
            <a href="<?php echo admin_url('edit.php?post_type=dw_wisata'); ?>">Wisata</a> 
            yang menggunakan antarmuka standar WordPress untuk kemudahan penggunaan.
        </p>
    </div>
</div>

