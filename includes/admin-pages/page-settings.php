<?php
/**
 * File Name:   page-settings.php
 * File Folder: includes/admin-pages/
 * Description: Halaman Pengaturan Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_admin_settings_page_handler() {
    // Handle Seeder
    if ( isset($_POST['dw_action']) && $_POST['dw_action'] === 'run_seeder' ) {
        if ( ! current_user_can('manage_options') ) return;
        check_admin_referer('dw_run_seeder_action');
        
        if (class_exists('DW_Seeder')) {
            DW_Seeder::run();
            echo '<div class="notice notice-success is-dismissible"><p>Data Dummy berhasil ditambahkan!</p></div>';
        } else {
            // Coba load manual jika class belum ada
             if (file_exists(DW_CORE_PLUGIN_DIR . 'includes/class-dw-seeder.php')) {
                require_once DW_CORE_PLUGIN_DIR . 'includes/class-dw-seeder.php';
                DW_Seeder::run();
                echo '<div class="notice notice-success is-dismissible"><p>Data Dummy berhasil ditambahkan!</p></div>';
             } else {
                 echo '<div class="notice notice-error"><p>Gagal: Class Seeder tidak ditemukan.</p></div>';
             }
        }
    }

    dw_settings_page_render();
}

function dw_settings_page_render() {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Pengaturan Plugin</h1>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="?page=dw-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Umum</a>
            <a href="?page=dw-settings&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">Pembayaran</a>
            <a href="?page=dw-settings&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">Tools</a>
        </h2>

        <div class="dw-card" style="margin-top: 20px;">
            <?php if ($active_tab == 'general'): ?>
                <p>Pengaturan umum plugin.</p>
            <?php elseif ($active_tab == 'payment'): ?>
                <p>Konfigurasi pembayaran.</p>
            <?php elseif ($active_tab == 'tools'): ?>
                <h3>Generator Data Dummy</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('dw_run_seeder_action'); ?>
                    <input type="hidden" name="dw_action" value="run_seeder">
                    <button type="submit" class="button button-primary">Generate Data Dummy</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>