<?php
/**
 * File Name:   page-settings.php
 * Description: Halaman Pengaturan Plugin dengan UI Modern & Upload QRIS.
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
        update_option( 'dw_qris_image_url', esc_url_raw( $_POST['dw_qris_image_url'] ) );
    } elseif ( $tab === 'whatsapp' ) {
        update_option( 'dw_wa_api_url', esc_url_raw( $_POST['dw_wa_api_url'] ) );
        update_option( 'dw_wa_api_key', sanitize_text_field( $_POST['dw_wa_api_key'] ) );
        update_option( 'dw_wa_sender', sanitize_text_field( $_POST['dw_wa_sender'] ) );
    }

    add_settings_error( 'dw_settings_notices', 'saved', 'Pengaturan berhasil disimpan.', 'success' );
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect( admin_url( 'admin.php?page=dw-settings&tab=' . $tab ) ); exit;
}
add_action( 'admin_init', 'dw_settings_save_handler' );

function dw_admin_settings_page_handler() {
    $active_tab = $_GET['tab'] ?? 'general';
    $errors = get_transient('settings_errors'); 
    if($errors) { settings_errors('dw_settings_notices'); delete_transient('settings_errors'); }
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Pengaturan Sistem</h1>
        <hr class="wp-header-end">

        <style>
            .dw-settings-container { display: flex; gap: 20px; margin-top: 20px; }
            .dw-sidebar-nav { width: 200px; flex-shrink: 0; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; }
            .dw-nav-item { display: flex; align-items: center; padding: 12px 15px; color: #3c434a; text-decoration: none; border-bottom: 1px solid #f0f0f1; transition: 0.2s; }
            .dw-nav-item:hover { background: #f6f7f7; color: #2271b1; }
            .dw-nav-item.active { background: #2271b1; color: #fff; border-color: #2271b1; }
            .dw-nav-item .dashicons { margin-right: 10px; }
            
            .dw-settings-content { flex-grow: 1; background: #fff; padding: 30px; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            .dw-form-section h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1e293b; }
            .dw-input-group { margin-bottom: 20px; }
            .dw-input-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
            .dw-input-group input[type="text"], .dw-input-group input[type="password"], .dw-input-group textarea { width: 100%; max-width: 600px; padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px; }
            .dw-help-text { font-size: 12px; color: #64748b; margin-top: 5px; }
            
            .dw-qris-preview { margin-top: 15px; border: 1px dashed #ccc; padding: 10px; display: inline-block; border-radius: 4px; background: #f9f9f9; }
            .dw-btn-save { background: #2271b1; color: #fff; border: none; padding: 10px 25px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; margin-top: 20px; }
            .dw-btn-save:hover { background: #135e96; }
        </style>

        <div class="dw-settings-container">
            <!-- Sidebar Nav -->
            <div class="dw-sidebar-nav">
                <a href="?page=dw-settings&tab=general" class="dw-nav-item <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> Umum
                </a>
                <a href="?page=dw-settings&tab=payment" class="dw-nav-item <?php echo $active_tab == 'payment' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-money-alt"></span> Pembayaran
                </a>
                <a href="?page=dw-settings&tab=whatsapp" class="dw-nav-item <?php echo $active_tab == 'whatsapp' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-whatsapp"></span> Notifikasi WA
                </a>
            </div>

            <!-- Content Area -->
            <div class="dw-settings-content">
                <form method="post">
                    <?php 
                    if ($active_tab == 'general') {
                        echo '<input type="hidden" name="active_tab" value="general">';
                        wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' );
                        ?>
                        <div class="dw-form-section">
                            <h3>Identitas Aplikasi</h3>
                            <div class="dw-input-group">
                                <label>Nama Aplikasi / Platform</label>
                                <input type="text" name="dw_app_name" value="<?php echo esc_attr(get_option('dw_app_name', 'Desa Wisata')); ?>" placeholder="Contoh: Wisata Desa Nusantara">
                            </div>
                            <div class="dw-input-group">
                                <label>Nomor WhatsApp Admin Utama</label>
                                <input type="text" name="dw_admin_phone" value="<?php echo esc_attr(get_option('dw_admin_phone')); ?>" placeholder="0812xxxx">
                                <p class="dw-help-text">Nomor ini akan menerima notifikasi pendaftaran toko baru.</p>
                            </div>
                            <div class="dw-input-group">
                                <label>Alamat Kantor / Sekretariat</label>
                                <textarea name="dw_company_address" rows="3"><?php echo esc_textarea(get_option('dw_company_address')); ?></textarea>
                            </div>
                        </div>
                        <?php
                    } elseif ($active_tab == 'payment') {
                        echo '<input type="hidden" name="active_tab" value="payment">';
                        wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' );
                        ?>
                        <div class="dw-form-section">
                            <h3>Rekening Tujuan Transfer (Admin)</h3>
                            <p class="dw-help-text" style="margin-bottom: 20px;">Data ini akan ditampilkan kepada pedagang saat membeli paket atau menyetor komisi manual.</p>
                            
                            <div class="dw-input-group">
                                <label>Nama Bank</label>
                                <input type="text" name="dw_bank_name" value="<?php echo esc_attr(get_option('dw_bank_name')); ?>" placeholder="Contoh: BCA / BRI">
                            </div>
                            <div class="dw-input-group">
                                <label>Nomor Rekening</label>
                                <input type="text" name="dw_bank_account" value="<?php echo esc_attr(get_option('dw_bank_account')); ?>">
                            </div>
                            <div class="dw-input-group">
                                <label>Atas Nama Pemilik</label>
                                <input type="text" name="dw_bank_holder" value="<?php echo esc_attr(get_option('dw_bank_holder')); ?>">
                            </div>
                            
                            <div class="dw-input-group" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                                <label style="font-size: 16px;">QRIS Platform (Scan)</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="text" name="dw_qris_image_url" id="dw_qris_field" value="<?php echo esc_attr(get_option('dw_qris_image_url')); ?>" class="regular-text" placeholder="URL Gambar QRIS">
                                    <button type="button" class="button button-secondary" id="btn_upl_qris_admin"><span class="dashicons dashicons-upload"></span> Upload QRIS</button>
                                </div>
                                
                                <div class="dw-qris-preview">
                                    <img id="prev_qris_admin" src="<?php echo esc_url(get_option('dw_qris_image_url') ?: 'https://placehold.co/200x200?text=No+QRIS'); ?>" style="max-width:200px; height:auto; display:block;">
                                    <p class="dw-help-text" style="text-align:center;">Preview QRIS</p>
                                </div>
                            </div>
                        </div>
                        <script>
                        jQuery(document).ready(function($){
                            $('#btn_upl_qris_admin').click(function(e){
                                e.preventDefault();
                                var frame = wp.media({title:'Upload QRIS Admin', multiple:false, library:{type:'image'}});
                                frame.on('select', function(){ 
                                    var url = frame.state().get('selection').first().toJSON().url; 
                                    $('#dw_qris_field').val(url); $('#prev_qris_admin').attr('src', url); 
                                });
                                frame.open();
                            });
                        });
                        </script>
                        <?php
                    } elseif ($active_tab == 'whatsapp') {
                        echo '<input type="hidden" name="active_tab" value="whatsapp">';
                        wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' );
                        ?>
                        <div class="dw-form-section">
                            <h3>Integrasi WhatsApp Gateway</h3>
                            <p class="dw-help-text">Konfigurasi untuk pengiriman notifikasi otomatis (misal: Fonnte, Watzap, dll).</p>
                            
                            <div class="dw-input-group">
                                <label>API URL Endpoint</label>
                                <input type="text" name="dw_wa_api_url" value="<?php echo esc_attr(get_option('dw_wa_api_url')); ?>" placeholder="https://api.fonnte.com/send">
                            </div>
                            <div class="dw-input-group">
                                <label>API Key / Token</label>
                                <input type="password" name="dw_wa_api_key" value="<?php echo esc_attr(get_option('dw_wa_api_key')); ?>">
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    
                    <button type="submit" name="dw_settings_submit" class="dw-btn-save">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>
    <?php
}
?>