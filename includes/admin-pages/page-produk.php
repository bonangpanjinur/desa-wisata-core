<?php
/**
 * File Name:   page-produk.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-produk.php
 *
 * Halaman ini hanya placeholder karena manajemen produk menggunakan CPT.
 * HTML dibungkus dalam fungsi agar tidak bocor.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Fungsi ini mungkin tidak dipanggil jika menu langsung ke edit.php, 
// tapi bagus untuk fallback jika ada yang mengakses via parameter page.
function dw_produk_page_info_render() {
    ?>
    <div class="wrap">
        <h1>Pengelolaan Produk</h1>
        <div class="notice notice-info inline">
            <p>
                <strong>Info:</strong> Silakan kelola produk melalui menu 
                <a href="<?php echo admin_url('edit.php?post_type=dw_produk'); ?>">Daftar Produk</a>.
            </p>
        </div>
    </div>
    <?php
}
?>