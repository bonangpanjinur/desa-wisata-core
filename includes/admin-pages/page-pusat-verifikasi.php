<?php
/**
 * File Name:   page-pusat-verifikasi.php
 * Description: Pusat Validasi (Unified Verification Center) untuk Admin.
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin-ui-components.php';

function dw_pusat_verifikasi_render() {
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pedagang';
    ?>
    <div class="wrap dw-wrap">
        <h1>Pusat Verifikasi</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=dw-pusat-verifikasi&tab=pedagang" class="nav-tab <?php echo $active_tab == 'pedagang' ? 'nav-tab-active' : ''; ?>">Verifikasi Pedagang/UMKM</a>
            <a href="?page=dw-pusat-verifikasi&tab=paket" class="nav-tab <?php echo $active_tab == 'paket' ? 'nav-tab-active' : ''; ?>">Verifikasi Paket/Produk</a>
            <a href="?page=dw-pusat-verifikasi&tab=desa" class="nav-tab <?php echo $active_tab == 'desa' ? 'nav-tab-active' : ''; ?>">Verifikasi Akun Desa</a>
        </h2>

        <div class="tab-content" style="margin-top: 20px;">
            <?php
            switch ($active_tab) {
                case 'pedagang':
                    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikator-umkm.php';
                    if (function_exists('dw_render_verifikator_umkm_page')) dw_render_verifikator_umkm_page();
                    break;
                case 'paket':
                    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikasi-paket.php';
                    if (function_exists('dw_render_page_verifikasi_paket')) dw_render_page_verifikasi_paket();
                    break;
                case 'desa':
                    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa-verifikasi-pedagang.php';
                    if (function_exists('dw_admin_desa_verifikasi_page_render')) dw_admin_desa_verifikasi_page_render();
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
