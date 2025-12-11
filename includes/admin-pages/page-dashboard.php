<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-dashboard.php
 *
 * PERBAIKAN:
 * - Mengubah query dari `{$table_prefix}penjual` menjadi `{$table_prefix}pedagang`
 * untuk mengatasi error 'Table doesn't exist'.
 */
function dw_dashboard_page_render() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . 'dw_';

    $desa_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}desa");
    // PERBAIKAN: Menggunakan nama tabel yang benar 'pedagang'.
    $penjual_pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}pedagang WHERE status_pendaftaran = 'menunggu'");
    $produk_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'dw_produk' AND post_status = 'publish'");
    $wisata_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'dw_wisata' AND post_status = 'publish'");

    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Dashboard Desa Wisata</h1>
        </div>
        <p>Selamat datang di panel admin Desa Wisata Core. Di sini Anda dapat melihat ringkasan statistik dan informasi penting.</p>
        
        <div class="dw-dashboard-cards">

            <div class="dw-card">
                <div class="dw-card-icon"><span class="dashicons dashicons-location"></span></div>
                <div class="dw-card-content">
                    <h3>Total Desa</h3>
                    <p class="dw-card-number"><?php echo esc_html($desa_count); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=dw-desa'); ?>">Kelola Desa</a>
                </div>
            </div>

            <div class="dw-card">
                <div class="dw-card-icon"><span class="dashicons dashicons-store"></span></div>
                <div class="dw-card-content">
                    <h3>Total Produk</h3>
                    <p class="dw-card-number"><?php echo esc_html($produk_count); ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=dw_produk'); ?>">Kelola Produk</a>
                </div>
            </div>

             <div class="dw-card">
                <div class="dw-card-icon"><span class="dashicons dashicons-palmtree"></span></div>
                <div class="dw-card-content">
                    <h3>Total Wisata</h3>
                    <p class="dw-card-number"><?php echo esc_html($wisata_count); ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=dw_wisata'); ?>">Kelola Wisata</a>
                </div>
            </div>

            <div class="dw-card <?php echo $penjual_pending_count > 0 ? 'warning' : ''; ?>">
                <div class="dw-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
                <div class="dw-card-content">
                    <h3>Persetujuan Penjual</h3>
                    <p class="dw-card-number"><?php echo esc_html($penjual_pending_count); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=dw-pedagang'); ?>">Lihat Pedagang</a>
                </div>
            </div>

        </div>

    </div>
    <?php
}
