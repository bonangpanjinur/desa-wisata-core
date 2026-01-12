<?php
/**
 * File Name:   page-settings.php
 * Description: Halaman Pengaturan Plugin Terintegrasi v3.6.
 * Fitur: Identitas, Pembayaran (QRIS), WhatsApp, dan Sistem Referral & Reward.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin-ui-components.php';

/**
 * Handler Simpan Pengaturan
 */
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
        update_option( 'dw_order_notification_youtube', esc_url_raw( $_POST['dw_order_notification_youtube'] ) );
    } elseif ( $tab === 'referral' ) {
        update_option( 'dw_bonus_quota_referral', absint( $_POST['dw_bonus_quota_referral'] ) );
        update_option( 'dw_prefix_referral_pedagang', strtoupper( sanitize_text_field( $_POST['dw_prefix_referral_pedagang'] ) ) );
        update_option( 'dw_ref_auto_verify', sanitize_key( $_POST['dw_ref_auto_verify'] ) );
        update_option( 'dw_buyer_referral_points', absint( $_POST['dw_buyer_referral_points'] ) );
    } elseif ( $tab === 'notification' ) {
        update_option( 'dw_default_order_sound_url', esc_url_raw( $_POST['dw_default_order_sound_url'] ) );
        update_option( 'dw_default_order_sound_type', sanitize_text_field( $_POST['dw_default_order_sound_type'] ) );
    }

    add_settings_error( 'dw_settings_notices', 'saved', 'Pengaturan berhasil disimpan.', 'success' );
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect( admin_url( 'admin.php?page=dw-settings&tab=' . sanitize_key($tab) ) ); exit;
}
add_action( 'admin_init', 'dw_settings_save_handler' );

/**
 * Render Halaman Pengaturan
 */
function dw_admin_settings_page_handler() {
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
    $errors = get_transient('settings_errors'); 
    if($errors) { 
        foreach($errors as $error) {
            add_settings_error($error['setting'], $error['code'], $error['message'], $error['type']);
        }
        settings_errors('dw_settings_notices'); 
        delete_transient('settings_errors'); 
    }
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline" style="font-weight: 800;">Pengaturan Sistem Desa Wisata</h1>
        <hr class="wp-header-end">

        

        <div class="dw-settings-container">
            <!-- Sidebar Nav -->
            <div class="dw-sidebar-nav">
                <a href="?page=dw-settings&tab=general" class="dw-nav-item <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> Umum
                </a>
                <a href="?page=dw-settings&tab=payment" class="dw-nav-item <?php echo $active_tab == 'payment' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-money-alt"></span> Pembayaran
                </a>
                <a href="?page=dw-settings&tab=referral" class="dw-nav-item <?php echo $active_tab == 'referral' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-share-alt"></span> Referral & Reward
                </a>
                <a href="?page=dw-settings&tab=whatsapp" class="dw-nav-item <?php echo $active_tab == 'whatsapp' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-whatsapp"></span> Notifikasi WA
                </a>
                <a href="?page=dw-settings&tab=notification" class="dw-nav-item <?php echo $active_tab == 'notification' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-megaphone"></span> Nada Pesanan
                </a>
            </div>

            <!-- Content Area -->
            <div class="dw-settings-content">
                <form method="post">
                    <input type="hidden" name="active_tab" value="<?php echo esc_attr($active_tab); ?>">
                    <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>

                    <?php if ($active_tab == 'general'): ?>
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-admin-site"></span> Identitas Aplikasi</h3>
                            <div class="dw-input-group">
                                <label>Nama Aplikasi / Platform</label>
                                <input type="text" name="dw_app_name" value="<?php echo esc_attr(get_option('dw_app_name', 'Desa Wisata')); ?>">
                            </div>
                            <div class="dw-input-group">
                                <label>Nomor WhatsApp Admin Utama</label>
                                <input type="text" name="dw_admin_phone" value="<?php echo esc_attr(get_option('dw_admin_phone')); ?>" placeholder="62812xxxx">
                                <p class="dw-help-text">Gunakan format kode negara (62). Nomor ini akan menerima notifikasi pendaftaran sistem.</p>
                            </div>
                            <div class="dw-input-group">
                                <label>Alamat Kantor / Sekretariat</label>
                                <textarea name="dw_company_address" rows="3"><?php echo esc_textarea(get_option('dw_company_address')); ?></textarea>
                            </div>
                        </div>

                    <?php elseif ($active_tab == 'payment'): ?>
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-bank"></span> Rekening Admin</h3>
                            <div class="dw-input-group">
                                <label>Nama Bank</label>
                                <input type="text" name="dw_bank_name" value="<?php echo esc_attr(get_option('dw_bank_name')); ?>" placeholder="Contoh: BANK BRI">
                            </div>
                            <div class="dw-input-group">
                                <label>Nomor Rekening</label>
                                <input type="text" name="dw_bank_account" value="<?php echo esc_attr(get_option('dw_bank_account')); ?>">
                            </div>
                            <div class="dw-input-group">
                                <label>Atas Nama</label>
                                <input type="text" name="dw_bank_holder" value="<?php echo esc_attr(get_option('dw_bank_holder')); ?>">
                            </div>
                            
                            <div class="dw-input-group" style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 25px;">
                                <label>QRIS Platform</label>
                                <div style="display:flex; gap:10px; align-items:center; margin-bottom: 15px;">
                                    <input type="text" name="dw_qris_image_url" id="dw_qris_field" value="<?php echo esc_attr(get_option('dw_qris_image_url')); ?>" placeholder="URL Gambar QRIS">
                                    <button type="button" class="button" id="btn_upl_qris_admin">Pilih Gambar</button>
                                </div>
                                <div class="dw-qris-preview">
                                    <img id="prev_qris_admin" src="<?php echo esc_url(get_option('dw_qris_image_url') ?: 'https://placehold.co/200x200?text=No+QRIS'); ?>" style="max-width:200px; height:auto; display:block; border-radius: 8px;">
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

                    <?php elseif ($active_tab == 'referral'): ?>
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-awards"></span> Referral & Reward Kuota</h3>
                            <p class="dw-help-text" style="margin-bottom: 25px;">Atur hadiah otomatis untuk pedagang yang berhasil mengajak pembeli baru bergabung.</p>
                            
                            <div class="dw-input-group">
                                <label>Hadiah Kuota Transaksi (Bonus)</label>
                                <input type="number" name="dw_bonus_quota_referral" value="<?php echo esc_attr(get_option('dw_bonus_quota_referral', 5)); ?>" min="0">
                                <p class="dw-help-text">Jumlah kuota transaksi GRATIS yang diberikan kepada Pedagang setiap kali ada 1 Pembeli baru mendaftar melalui link referral mereka.</p>
                            </div>

                            <div class="dw-input-group">
                                <label>Prefix Kode Referral Pedagang</label>
                                <input type="text" name="dw_prefix_referral_pedagang" value="<?php echo esc_attr(get_option('dw_prefix_referral_pedagang', 'TOKO')); ?>" placeholder="Contoh: TOKO">
                                <p class="dw-help-text">Awalan kode referral otomatis untuk pedagang (Misal: TOKO-XXXX). Gunakan maksimal 5 karakter.</p>
                            </div>

                            <div class="dw-input-group">
                                <label>Metode Verifikasi Reward</label>
                                <select name="dw_ref_auto_verify">
                                    <?php $current_verify = get_option('dw_ref_auto_verify', 'auto'); ?>
                                    <option value="auto" <?php selected($current_verify, 'auto'); ?>>Berikan Kuota Otomatis (Instan)</option>
                                    <option value="manual" <?php selected($current_verify, 'manual'); ?>>Tinjau Manual Oleh Admin</option>
                                </select>
                            </div>

                            <div class="dw-input-group" style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 25px;">
                                <label>Poin Referral Pembeli</label>
                                <input type="number" name="dw_buyer_referral_points" value="<?php echo esc_attr(get_option('dw_buyer_referral_points', 10)); ?>" min="0">
                                <p class="dw-help-text">Jumlah poin yang diberikan kepada Pembeli lama setiap kali ada Pembeli baru mendaftar menggunakan kode referral mereka.</p>
                            </div>
                        </div>

                    <?php elseif ($active_tab == 'whatsapp'): ?>
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-whatsapp"></span> Integrasi WhatsApp Gateway</h3>
                            <div class="dw-input-group">
                                <label>API URL Endpoint</label>
                                <input type="text" name="dw_wa_api_url" value="<?php echo esc_attr(get_option('dw_wa_api_url')); ?>" placeholder="https://api.fonnte.com/send">
                            </div>
                            <div class="dw-input-group">
                                <label>API Key / Token</label>
                                <input type="password" name="dw_wa_api_key" value="<?php echo esc_attr(get_option('dw_wa_api_key')); ?>">
                            </div>
                            <div class="dw-input-group">
                                <label>Sender Number / ID</label>
                                <input type="text" name="dw_wa_sender" value="<?php echo esc_attr(get_option('dw_wa_sender')); ?>">
                            </div>

                            <div class="dw-input-group" style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 25px;">
                                <label>Link YouTube Nada Peringatan Pesanan (Global)</label>
                                <input type="text" name="dw_order_notification_youtube" value="<?php echo esc_attr(get_option('dw_order_notification_youtube')); ?>" placeholder="https://www.youtube.com/watch?v=xxxx">
                                <p class="dw-help-text">Masukkan link YouTube untuk nada peringatan saat ada pesanan masuk di dashboard toko (Default jika pedagang tidak mengatur sendiri).</p>
                            </div>
                        </div>

                    <?php elseif ($active_tab == 'notification'): ?>
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-megaphone"></span> Pengaturan Nada Pesanan Masuk (Default)</h3>
                            <p class="dw-help-text">Atur nada default yang akan digunakan oleh semua toko jika mereka belum mengatur nada sendiri.</p>
                            
                            <div class="dw-input-group">
                                <label>Tipe Nada Default</label>
                                <select name="dw_default_order_sound_type" id="dw_sound_type">
                                    <?php $current_type = get_option('dw_default_order_sound_type', 'default'); ?>
                                    <option value="default" <?php selected($current_type, 'default'); ?>>Suara Default Sistem</option>
                                    <option value="upload" <?php selected($current_type, 'upload'); ?>>Upload File (MP3/MP4)</option>
                                    <option value="youtube" <?php selected($current_type, 'youtube'); ?>>Link YouTube</option>
                                </select>
                            </div>

                            <div class="dw-input-group" id="group_sound_upload" style="<?php echo $current_type != 'upload' ? 'display:none;' : ''; ?>">
                                <label>File Audio/Video</label>
                                <div style="display:flex; gap:10px; align-items:center; margin-bottom: 15px;">
                                    <input type="text" name="dw_default_order_sound_url" id="dw_sound_url_field" value="<?php echo esc_attr(get_option('dw_default_order_sound_url')); ?>" placeholder="URL File MP3/MP4">
                                    <button type="button" class="button" id="btn_upl_sound_default">Pilih File</button>
                                </div>
                                <p class="dw-help-text">Upload file MP3 atau MP4 untuk digunakan sebagai nada pesanan.</p>
                            </div>

                            <div class="dw-input-group" id="group_sound_youtube" style="<?php echo $current_type != 'youtube' ? 'display:none;' : ''; ?>">
                                <label>Link YouTube</label>
                                <input type="text" name="dw_default_order_sound_url_yt" id="dw_sound_url_yt_field" value="<?php echo $current_type == 'youtube' ? esc_attr(get_option('dw_default_order_sound_url')) : ''; ?>" placeholder="https://www.youtube.com/watch?v=xxxx">
                                <p class="dw-help-text">Masukkan link YouTube untuk nada peringatan.</p>
                            </div>
                        </div>
                        <script>
                        jQuery(document).ready(function($){
                            $('#dw_sound_type').change(function(){
                                var val = $(this).val();
                                if(val == 'upload') {
                                    $('#group_sound_upload').show();
                                    $('#group_sound_youtube').hide();
                                } else if(val == 'youtube') {
                                    $('#group_sound_upload').hide();
                                    $('#group_sound_youtube').show();
                                } else {
                                    $('#group_sound_upload').hide();
                                    $('#group_sound_youtube').hide();
                                }
                            });

                            $('#btn_upl_sound_default').click(function(e){
                                e.preventDefault();
                                var frame = wp.media({title:'Pilih Nada Pesanan', multiple:false, library:{type:['audio', 'video']}});
                                frame.on('select', function(){ 
                                    var url = frame.state().get('selection').first().toJSON().url; 
                                    $('#dw_sound_url_field').val(url); 
                                });
                                frame.open();
                            });

                            // Sync YouTube field to main URL field on submit
                            $('form').submit(function(){
                                if($('#dw_sound_type').val() == 'youtube') {
                                    $('#dw_sound_url_field').val($('#dw_sound_url_yt_field').val());
                                }
                            });
                        });
                        </script>
                    <?php endif; ?>
                    
                    <button type="submit" name="dw_settings_submit" class="dw-btn-save">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>
    <?php
}
