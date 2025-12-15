<?php
/**
 * File Name:   page-wisata.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-wisata.php
 * * Description: 
 * Halaman instruksi untuk mengarahkan pengguna ke menu CPT Wisata yang benar.
 * Mencegah penggunaan form manual yang usang.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

?>
<div class="wrap dw-wrap">
    <div class="dw-header">
        <h1>Manajemen Objek Wisata</h1>
    </div>

    <div class="dw-card" style="max-width: 800px; text-align: center; padding: 40px;">
        <span class="dashicons dashicons-palmtree" style="font-size: 64px; width: 64px; height: 64px; color: #2271b1; margin-bottom: 20px;"></span>
        
        <h2>Pengelolaan Data Wisata</h2>
        <p>Silakan gunakan menu "Wisata" di sidebar kiri untuk menambah atau mengedit objek wisata.</p>

        <div style="display: flex; gap: 20px; justify-content: center;">
            <a href="<?php echo admin_url('edit.php?post_type=dw_wisata'); ?>" class="button button-primary button-hero">
                Lihat Daftar Wisata
            </a>
        </div>
    </div>
</div>
<?php
?>