<?php
/**
 * File Name:   page-settings.php
 * File Folder: includes/admin-pages/
 * Description: Halaman Pengaturan Plugin yang disempurnakan.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler untuk menyimpan pengaturan ketika form disubmit.
 * Dijalankan pada hook 'admin_init'.
 */
function dw_settings_save_handler() {
    // Cek apakah user sedang submit form settings
    if ( ! isset( $_POST['dw_settings_submit'] ) ) return;
    
    // Verifikasi Nonce
    if ( ! isset($_POST['dw_save_settings_nonce_field']) || ! wp_verify_nonce( $_POST['dw_save_settings_nonce_field'], 'dw_save_settings_action' ) ) {
        return;
    }

    // Cek Capability
    if ( ! current_user_can( 'manage_options' ) ) return;

    $tab = isset( $_POST['active_tab'] ) ? $_POST['active_tab'] : 'general';

    if ( $tab === 'general' ) {
        update_option( 'dw_app_name', sanitize_text_field( $_POST['dw_app_name'] ) );
        update_option( 'dw_admin_phone', sanitize_text_field( $_POST['dw_admin_phone'] ) );
        update_option( 'dw_company_address', sanitize_textarea_field( $_POST['dw_company_address'] ) );
        
        // Simpan setting
        add_settings_error( 'dw_settings_notices', 'dw_settings_saved', 'Pengaturan Umum berhasil disimpan.', 'success' );
    
    } elseif ( $tab === 'payment' ) {
        // Transfer Manual
        update_option( 'dw_bank_name', sanitize_text_field( $_POST['dw_bank_name'] ) );
        update_option( 'dw_bank_account', sanitize_text_field( $_POST['dw_bank_account'] ) );
        update_option( 'dw_bank_holder', sanitize_text_field( $_POST['dw_bank_holder'] ) );
        
        // Midtrans
        update_option( 'dw_midtrans_server_key', sanitize_text_field( $_POST['dw_midtrans_server_key'] ) );
        update_option( 'dw_midtrans_client_key', sanitize_text_field( $_POST['dw_midtrans_client_key'] ) );
        update_option( 'dw_midtrans_is_production', isset( $_POST['dw_midtrans_is_production'] ) ? 1 : 0 );
        
        add_settings_error( 'dw_settings_notices', 'dw_settings_saved', 'Konfigurasi Pembayaran berhasil disimpan.', 'success' );
    
    } elseif ( $tab === 'whatsapp' ) {
        update_option( 'dw_wa_api_url', esc_url_raw( $_POST['dw_wa_api_url'] ) );
        update_option( 'dw_wa_api_key', sanitize_text_field( $_POST['dw_wa_api_key'] ) );
        update_option( 'dw_wa_sender', sanitize_text_field( $_POST['dw_wa_sender'] ) );
        
        add_settings_error( 'dw_settings_notices', 'dw_settings_saved', 'Konfigurasi WhatsApp berhasil disimpan.', 'success' );
    }

    // Simpan error/success message ke transient agar muncul setelah redirect (PRG Pattern)
    set_transient('settings_errors', get_settings_errors(), 30);

    // Redirect kembali ke tab yang sama
    wp_redirect( admin_url( 'admin.php?page=dw-settings&tab=' . $tab ) );
    exit;
}
add_action( 'admin_init', 'dw_settings_save_handler' );


/**
 * Handler utama halaman settings (Controller).
 */
function dw_admin_settings_page_handler() {
    // Handle Actions di Tab Tools (Seeder & Cache)
    if ( isset($_POST['dw_action']) ) {
        if ( ! current_user_can('manage_options') ) return;

        // Action: Run Seeder
        if ( $_POST['dw_action'] === 'run_seeder' ) {
            check_admin_referer('dw_run_seeder_action');
            
            if (class_exists('DW_Seeder')) {
                DW_Seeder::run();
                add_settings_error( 'dw_settings_notices', 'seeder_success', 'Data Dummy berhasil ditambahkan!', 'success' );
            } elseif (file_exists(DW_CORE_PLUGIN_DIR . 'includes/class-dw-seeder.php')) {
                require_once DW_CORE_PLUGIN_DIR . 'includes/class-dw-seeder.php';
                DW_Seeder::run();
                add_settings_error( 'dw_settings_notices', 'seeder_success', 'Data Dummy berhasil ditambahkan!', 'success' );
            } else {
                add_settings_error( 'dw_settings_notices', 'seeder_failed', 'Gagal: Class Seeder tidak ditemukan.', 'error' );
            }
        }
        
        // Action: Clear Cache
        if ( $_POST['dw_action'] === 'clear_cache' ) {
            check_admin_referer('dw_clear_cache_action');
            
            // Hapus semua transient yang relevan
            delete_transient('dw_api_banners_cache');
            delete_transient('dw_api_products_cache'); 
            // Tambahkan transient key lain di sini jika ada

            add_settings_error( 'dw_settings_notices', 'cache_cleared', 'Cache API berhasil dibersihkan.', 'success' );
        }
    }

    dw_settings_page_render();
}

/**
 * Render Tampilan Halaman Settings (View).
 */
function dw_settings_page_render() {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
    
    // Tampilkan notifikasi
    $errors = get_transient('settings_errors');
    if($errors) {
        settings_errors('dw_settings_notices');
        delete_transient('settings_errors');
    }
    settings_errors('dw_settings_notices'); 
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Pengaturan Plugin Desa Wisata</h1>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="?page=dw-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-generic" style="vertical-align:text-top;"></span> Umum
            </a>
            <a href="?page=dw-settings&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-money-alt" style="vertical-align:text-top;"></span> Pembayaran
            </a>
            <a href="?page=dw-settings&tab=whatsapp" class="nav-tab <?php echo $active_tab == 'whatsapp' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-whatsapp" style="vertical-align:text-top;"></span> WhatsApp
            </a>
            <a href="?page=dw-settings&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-hammer" style="vertical-align:text-top;"></span> Tools
            </a>
        </h2>

        <div class="dw-settings-content" style="margin-top: 20px; background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
            <?php 
            if ($active_tab == 'general') {
                dw_render_general_tab();
            } elseif ($active_tab == 'payment') {
                dw_render_payment_tab();
            } elseif ($active_tab == 'whatsapp') {
                dw_render_whatsapp_tab();
            } elseif ($active_tab == 'tools') {
                dw_render_tools_tab();
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Tab Content: General
 */
function dw_render_general_tab() {
    ?>
    <form method="post" action="">
        <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>
        <input type="hidden" name="active_tab" value="general">
        
        <h3>Informasi Aplikasi</h3>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="dw_app_name">Nama Aplikasi</label></th>
                <td>
                    <input name="dw_app_name" type="text" id="dw_app_name" value="<?php echo esc_attr( get_option('dw_app_name', get_bloginfo('name')) ); ?>" class="regular-text">
                    <p class="description">Nama yang akan muncul di header aplikasi mobile atau invoice.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="dw_admin_phone">Nomor Telepon Admin</label></th>
                <td>
                    <input name="dw_admin_phone" type="text" id="dw_admin_phone" value="<?php echo esc_attr( get_option('dw_admin_phone') ); ?>" class="regular-text">
                    <p class="description">Format: 628xxxxxxxx (Gunakan kode negara). Digunakan untuk fallback kontak.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="dw_company_address">Alamat Kantor/Desa</label></th>
                <td>
                    <textarea name="dw_company_address" id="dw_company_address" rows="3" class="large-text"><?php echo esc_textarea( get_option('dw_company_address') ); ?></textarea>
                    <p class="description">Alamat lengkap sekretariat desa wisata.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" name="dw_settings_submit" class="button button-primary">Simpan Perubahan</button>
        </p>
    </form>
    <?php
}

/**
 * Tab Content: Payment & Midtrans
 */
function dw_render_payment_tab() {
    ?>
    <form method="post" action="">
        <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>
        <input type="hidden" name="active_tab" value="payment">

        <h3>Transfer Bank Manual</h3>
        <p>Rekening ini akan ditampilkan jika user memilih metode transfer manual.</p>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="dw_bank_name">Nama Bank</label></th>
                <td><input name="dw_bank_name" type="text" id="dw_bank_name" value="<?php echo esc_attr( get_option('dw_bank_name', 'BCA') ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="dw_bank_account">Nomor Rekening</label></th>
                <td><input name="dw_bank_account" type="text" id="dw_bank_account" value="<?php echo esc_attr( get_option('dw_bank_account') ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="dw_bank_holder">Atas Nama</label></th>
                <td><input name="dw_bank_holder" type="text" id="dw_bank_holder" value="<?php echo esc_attr( get_option('dw_bank_holder') ); ?>" class="regular-text"></td>
            </tr>
        </table>
        
        <hr>

        <h3>Konfigurasi Midtrans Payment Gateway</h3>
        <p>Pengaturan untuk pembayaran otomatis menggunakan Midtrans (QRIS, VA, E-Wallet).</p>
        <table class="form-table">
            <tr>
                <th scope="row">Mode Production</th>
                <td>
                    <label for="dw_midtrans_is_production">
                        <input name="dw_midtrans_is_production" type="checkbox" id="dw_midtrans_is_production" value="1" <?php checked( 1, get_option( 'dw_midtrans_is_production' ), true ); ?>>
                        Aktifkan Mode Produksi (Live)
                    </label>
                    <p class="description">Jika tidak dicentang, sistem berjalan dalam mode Sandbox (Test).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="dw_midtrans_server_key">Server Key</label></th>
                <td>
                    <input name="dw_midtrans_server_key" type="text" id="dw_midtrans_server_key" value="<?php echo esc_attr( get_option('dw_midtrans_server_key') ); ?>" class="large-text">
                    <p class="description">Dapatkan dari dashboard Midtrans > Settings > Access Keys.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="dw_midtrans_client_key">Client Key</label></th>
                <td>
                    <input name="dw_midtrans_client_key" type="text" id="dw_midtrans_client_key" value="<?php echo esc_attr( get_option('dw_midtrans_client_key') ); ?>" class="large-text">
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="dw_settings_submit" class="button button-primary">Simpan Pengaturan Pembayaran</button>
        </p>
    </form>
    <?php
}

/**
 * Tab Content: WhatsApp
 */
function dw_render_whatsapp_tab() {
    ?>
    <form method="post" action="">
        <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>
        <input type="hidden" name="active_tab" value="whatsapp">

        <h3>WhatsApp Gateway API</h3>
        <p>Konfigurasi untuk mengirim notifikasi WA otomatis (OTP, Invoice, Status Pesanan).</p>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="dw_wa_api_url">API URL Endpoint</label></th>
                <td>
                    <input name="dw_wa_api_url" type="url" id="dw_wa_api_url" value="<?php echo esc_attr( get_option('dw_wa_api_url') ); ?>" class="large-text" placeholder="https://api.fonnte.com/send">
                    <p class="description">Contoh endpoint dari provider (Wado, Fonnte, Wablas, dll).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="dw_wa_api_key">API Key / Token</label></th>
                <td>
                    <input name="dw_wa_api_key" type="password" id="dw_wa_api_key" value="<?php echo esc_attr( get_option('dw_wa_api_key') ); ?>" class="large-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="dw_wa_sender">Nomor Pengirim (Opsional)</label></th>
                <td>
                    <input name="dw_wa_sender" type="text" id="dw_wa_sender" value="<?php echo esc_attr( get_option('dw_wa_sender') ); ?>" class="regular-text">
                    <p class="description">Beberapa provider memerlukan parameter nomor pengirim.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="dw_settings_submit" class="button button-primary">Simpan Konfigurasi WA</button>
        </p>
    </form>
    <?php
}

/**
 * Tab Content: Tools
 */
function dw_render_tools_tab() {
    ?>
    <h3>System Tools</h3>
    
    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <!-- Card Seeder -->
        <div class="dw-card" style="border:1px solid #ddd; padding:20px; border-radius:5px; flex: 1; min-width: 300px;">
            <h4><span class="dashicons dashicons-database"></span> Generator Data Dummy</h4>
            <p>Isi database dengan data contoh untuk keperluan testing (Desa, Paket, Pedagang, Produk).</p>
            <form method="post" action="" onsubmit="return confirm('Apakah Anda yakin? Ini akan menambahkan data dummy ke database.');">
                <?php wp_nonce_field('dw_run_seeder_action'); ?>
                <input type="hidden" name="dw_action" value="run_seeder">
                <button type="submit" class="button button-primary">Jalankan Seeder</button>
            </form>
        </div>

        <!-- Card Cache -->
        <div class="dw-card" style="border:1px solid #ddd; padding:20px; border-radius:5px; flex: 1; min-width: 300px;">
            <h4><span class="dashicons dashicons-update"></span> Bersihkan Cache API</h4>
            <p>Hapus cache data API (Banner, Produk, Desa) agar perubahan di admin langsung terlihat di aplikasi mobile/frontend.</p>
            <form method="post" action="">
                <?php wp_nonce_field('dw_clear_cache_action'); ?>
                <input type="hidden" name="dw_action" value="clear_cache">
                <button type="submit" class="button button-secondary">Bersihkan Cache</button>
            </form>
        </div>
    </div>
    
    <hr>
    <h3>Informasi Server</h3>
    <table class="widefat striped">
        <tbody>
            <tr><td>PHP Version</td><td><?php echo phpversion(); ?></td></tr>
            <tr><td>WordPress Version</td><td><?php echo get_bloginfo('version'); ?></td></tr>
            <tr><td>Plugin Version</td><td><?php echo defined('DW_CORE_VERSION') ? DW_CORE_VERSION : '1.0.0'; ?></td></tr>
        </tbody>
    </table>
    <?php
}
?>