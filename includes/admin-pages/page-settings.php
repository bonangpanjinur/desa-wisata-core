<?php
/**
 * File Name:   page-settings.php
 * Description: Pengaturan Plugin + Upload QRIS Admin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_settings_save_handler() {
    if ( ! isset( $_POST['dw_settings_submit'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['dw_save_settings_nonce_field'], 'dw_save_settings_action' ) ) return;

    $tab = $_POST['active_tab'] ?? 'general';

    if ( $tab === 'general' ) {
        update_option( 'dw_app_name', sanitize_text_field( $_POST['dw_app_name'] ) );
        update_option( 'dw_admin_phone', sanitize_text_field( $_POST['dw_admin_phone'] ) );
        update_option( 'dw_company_address', sanitize_textarea_field( $_POST['dw_company_address'] ) );
    } elseif ( $tab === 'payment' ) {
        update_option( 'dw_bank_name', sanitize_text_field( $_POST['dw_bank_name'] ) );
        update_option( 'dw_bank_account', sanitize_text_field( $_POST['dw_bank_account'] ) );
        update_option( 'dw_bank_holder', sanitize_text_field( $_POST['dw_bank_holder'] ) );
        
        // --- BARU: SIMPAN QRIS ---
        update_option( 'dw_qris_image_url', esc_url_raw( $_POST['dw_qris_image_url'] ) );
        
        // Midtrans settings... (tetap sama)
    } elseif ( $tab === 'whatsapp' ) {
        update_option( 'dw_wa_api_url', esc_url_raw( $_POST['dw_wa_api_url'] ) );
        update_option( 'dw_wa_api_key', sanitize_text_field( $_POST['dw_wa_api_key'] ) );
        update_option( 'dw_wa_sender', sanitize_text_field( $_POST['dw_wa_sender'] ) );
    }

    add_settings_error( 'dw_settings_notices', 'saved', 'Pengaturan disimpan.', 'success' );
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect( admin_url( 'admin.php?page=dw-settings&tab=' . $tab ) ); exit;
}
add_action( 'admin_init', 'dw_settings_save_handler' );

// ... (Fungsi handler utama tetap sama) ...
function dw_admin_settings_page_handler() {
    dw_settings_page_render();
}

function dw_settings_page_render() {
    $active_tab = $_GET['tab'] ?? 'general';
    $errors = get_transient('settings_errors'); if($errors) { settings_errors('dw_settings_notices'); delete_transient('settings_errors'); }
    ?>
    <div class="wrap dw-wrap">
        <h1>Pengaturan Desa Wisata</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=dw-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Umum</a>
            <a href="?page=dw-settings&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">Pembayaran</a>
            <a href="?page=dw-settings&tab=whatsapp" class="nav-tab <?php echo $active_tab == 'whatsapp' ? 'nav-tab-active' : ''; ?>">WhatsApp</a>
        </h2>
        <div class="card" style="padding:20px; margin-top:20px;">
            <?php 
            if ($active_tab == 'general') dw_render_general_tab();
            elseif ($active_tab == 'payment') dw_render_payment_tab();
            elseif ($active_tab == 'whatsapp') dw_render_whatsapp_tab();
            ?>
        </div>
    </div>
    <?php
}

function dw_render_general_tab() { /* ... kode lama ... */ ?>
    <form method="post">
        <input type="hidden" name="active_tab" value="general">
        <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>
        <table class="form-table">
            <tr><th>Nama Aplikasi</th><td><input name="dw_app_name" value="<?php echo esc_attr(get_option('dw_app_name')); ?>" class="regular-text"></td></tr>
            <tr><th>No. HP Admin</th><td><input name="dw_admin_phone" value="<?php echo esc_attr(get_option('dw_admin_phone')); ?>" class="regular-text"></td></tr>
            <tr><th>Alamat</th><td><textarea name="dw_company_address" class="large-text"><?php echo esc_textarea(get_option('dw_company_address')); ?></textarea></td></tr>
        </table>
        <?php submit_button('Simpan', 'primary', 'dw_settings_submit'); ?>
    </form>
<?php }

function dw_render_payment_tab() { ?>
    <form method="post">
        <input type="hidden" name="active_tab" value="payment">
        <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>
        <h3>Rekening Manual</h3>
        <table class="form-table">
            <tr><th>Nama Bank</th><td><input name="dw_bank_name" value="<?php echo esc_attr(get_option('dw_bank_name')); ?>" class="regular-text"></td></tr>
            <tr><th>No. Rekening</th><td><input name="dw_bank_account" value="<?php echo esc_attr(get_option('dw_bank_account')); ?>" class="regular-text"></td></tr>
            <tr><th>Atas Nama</th><td><input name="dw_bank_holder" value="<?php echo esc_attr(get_option('dw_bank_holder')); ?>" class="regular-text"></td></tr>
            
            <!-- NEW: QRIS UPLOAD -->
            <tr><th>QRIS Platform</th><td>
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="text" name="dw_qris_image_url" id="dw_qris_field" value="<?php echo esc_attr(get_option('dw_qris_image_url')); ?>" class="regular-text">
                    <button type="button" class="button" id="btn_upl_qris_admin">Upload QRIS</button>
                </div>
                <img id="prev_qris_admin" src="<?php echo esc_url(get_option('dw_qris_image_url')); ?>" style="max-width:150px; margin-top:10px; border:1px solid #ddd; padding:5px;">
                <p class="description">QRIS ini akan muncul saat pedagang melakukan pembayaran paket/setoran.</p>
            </td></tr>
        </table>
        <script>
        jQuery('#btn_upl_qris_admin').click(function(e){
            e.preventDefault(); var frame = wp.media({title:'QRIS Admin', multiple:false});
            frame.on('select', function(){ 
                var url = frame.state().get('selection').first().toJSON().url; 
                jQuery('#dw_qris_field').val(url); jQuery('#prev_qris_admin').attr('src', url); 
            }); frame.open();
        });
        </script>
        <?php submit_button('Simpan', 'primary', 'dw_settings_submit'); ?>
    </form>
<?php }

function dw_render_whatsapp_tab() { /* ... kode lama ... */ ?>
    <form method="post">
        <input type="hidden" name="active_tab" value="whatsapp">
        <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>
        <table class="form-table">
            <tr><th>API URL</th><td><input name="dw_wa_api_url" value="<?php echo esc_attr(get_option('dw_wa_api_url')); ?>" class="large-text"></td></tr>
            <tr><th>API Key</th><td><input name="dw_wa_api_key" type="password" value="<?php echo esc_attr(get_option('dw_wa_api_key')); ?>" class="large-text"></td></tr>
        </table>
        <?php submit_button('Simpan', 'primary', 'dw_settings_submit'); ?>
    </form>
<?php } 
?>