<?php
/**
 * File: includes/admin-pages/page-settings.php
 * * Admin Page: Pengaturan Sistem
 * * Menggunakan Tab Navigasi modern dan Card wrapper.
 * * UPDATE FASE 4 (Final Fix): Xendit di API Tab, Wallet Settings di Payment Tab.
 */

defined( 'ABSPATH' ) || exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin-ui-components.php';

/**
 * Handler Simpan Pengaturan
 */
function dw_settings_save_handler() {
    if ( ! isset( $_POST['dw_settings_submit'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['dw_save_settings_nonce_field'], 'dw_save_settings_action' ) ) return;

    $tab = $_POST['active_tab'] ?? 'general';

    // Ambil setting general existing
    $gen_settings = get_option( 'dw_settings_general', [] );
    
    // Ambil payment settings existing (untuk Xendit & Wallet)
    $payment_settings = get_option( 'dw_payment_settings', [] );

    if ( $tab === 'general' ) {
        // Simpan Individual Options (Legacy Support)
        update_option( 'dw_app_name', sanitize_text_field( $_POST['dw_app_name'] ) );
        update_option( 'dw_admin_phone', sanitize_text_field( $_POST['dw_admin_phone'] ) );
        update_option( 'dw_company_address', sanitize_textarea_field( $_POST['dw_company_address'] ) );

        // Sync ke dw_settings_general
        $gen_settings['desa_name'] = sanitize_text_field( $_POST['dw_app_name'] );
        $gen_settings['desa_address'] = sanitize_textarea_field( $_POST['dw_company_address'] );
        update_option( 'dw_settings_general', $gen_settings );

    } elseif ( $tab === 'payment' ) {
        // 1. Simpan Bank Manual (Legacy)
        update_option( 'dw_bank_name', sanitize_text_field( $_POST['dw_bank_name'] ) );
        update_option( 'dw_bank_account', sanitize_text_field( $_POST['dw_bank_account'] ) );
        update_option( 'dw_bank_holder', sanitize_text_field( $_POST['dw_bank_holder'] ) );
        update_option( 'dw_qris_image_url', esc_url_raw( $_POST['dw_qris_image_url'] ) );

        // 2. Simpan Wallet Limit (Minimal Penarikan)
        update_option( 'dw_min_withdrawal_amount', absint( $_POST['dw_min_withdrawal_amount'] ) );

    } elseif ( $tab === 'api' ) {
        // --- XENDIT CONFIGURATION (Disimpan di dw_payment_settings) ---
        $payment_settings['xendit_mode'] = sanitize_text_field( $_POST['xendit_mode'] );
        $payment_settings['xendit_secret_key'] = sanitize_text_field( $_POST['xendit_secret_key'] );
        $payment_settings['xendit_callback_token'] = sanitize_text_field( $_POST['xendit_callback_token'] );
        
        update_option( 'dw_payment_settings', $payment_settings );

        // --- UPDATE JUGA dw_settings_general AGAR KOMPATIBEL DENGAN KODE LAMA ---
        $gen_settings['xendit_mode'] = sanitize_text_field( $_POST['xendit_mode'] );
        $gen_settings['xendit_secret_key'] = sanitize_text_field( $_POST['xendit_secret_key'] );
        $gen_settings['xendit_callback_token'] = sanitize_text_field( $_POST['xendit_callback_token'] );
        update_option( 'dw_settings_general', $gen_settings );

    } elseif ( $tab === 'whatsapp' ) {
        update_option( 'dw_wa_api_url', esc_url_raw( $_POST['dw_wa_api_url'] ) );
        update_option( 'dw_wa_api_key', sanitize_text_field( $_POST['dw_wa_api_key'] ) );
        update_option( 'dw_wa_sender', sanitize_text_field( $_POST['dw_wa_sender'] ) );
        update_option( 'dw_order_notification_youtube', esc_url_raw( $_POST['dw_order_notification_youtube'] ) );
        
        $gen_settings['wa_gateway_url'] = esc_url_raw( $_POST['dw_wa_api_url'] );
        $gen_settings['wa_api_key'] = sanitize_text_field( $_POST['dw_wa_api_key'] );
        update_option( 'dw_settings_general', $gen_settings );

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

function dw_admin_settings_page_handler() {
    // Cek user capability
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
    
    // Retrieve errors/notices
    $errors = get_transient('settings_errors');
    if($errors) {
        foreach($errors as $error) {
            add_settings_error($error['setting'], $error['code'], $error['message'], $error['type']);
        }
        delete_transient('settings_errors');
    }

    // Ambil Data Options
    $settings_gen = get_option( 'dw_settings_general', [] );
    $payment_settings = get_option( 'dw_payment_settings', [] );
    
    // Fallback: Jika di payment_settings kosong, coba ambil dari settings_gen (Legacy)
    if ( empty($payment_settings['xendit_secret_key']) && !empty($settings_gen['xendit_secret_key']) ) {
        $payment_settings['xendit_secret_key'] = $settings_gen['xendit_secret_key'];
        $payment_settings['xendit_callback_token'] = $settings_gen['xendit_callback_token'];
        $payment_settings['xendit_mode'] = $settings_gen['xendit_mode'];
    }
    ?>
    <div class="wrap dw-admin-wrapper">
        <!-- Header Section -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Konfigurasi utama sistem Desa Wisata & Marketplace.</p>
            </div>
        </div>

        <!-- Body Section -->
        <div class="dw-content-body">
            <?php settings_errors('dw_settings_notices'); ?>

            <!-- Navigation Tabs -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="?page=dw-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Umum</a>
                <a href="?page=dw-settings&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">Pembayaran & Wallet</a>
                <a href="?page=dw-settings&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">API & Integrasi</a>
                <a href="?page=dw-settings&tab=whatsapp" class="nav-tab <?php echo $active_tab == 'whatsapp' ? 'nav-tab-active' : ''; ?>">Notifikasi (WA)</a>
                <a href="?page=dw-settings&tab=referral" class="nav-tab <?php echo $active_tab == 'referral' ? 'nav-tab-active' : ''; ?>">Referral & Reward</a>
                <a href="?page=dw-settings&tab=notification" class="nav-tab <?php echo $active_tab == 'notification' ? 'nav-tab-active' : ''; ?>">Nada Pesanan</a>
            </nav>

            <!-- Settings Form in Card -->
            <div class="dw-card">
                <form method="post">
                    <input type="hidden" name="active_tab" value="<?php echo esc_attr($active_tab); ?>">
                    <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>

                    <?php if ($active_tab == 'general'): ?>
                        <!-- GENERAL TAB CONTENT -->
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-admin-site"></span> Identitas Aplikasi</h3>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="dw_app_name">Nama Aplikasi / Platform</label></th>
                                        <td>
                                            <input name="dw_app_name" type="text" id="dw_app_name" value="<?php echo esc_attr(get_option('dw_app_name', 'Desa Wisata')); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_admin_phone">Nomor WhatsApp Admin Utama</label></th>
                                        <td>
                                            <input name="dw_admin_phone" type="text" id="dw_admin_phone" value="<?php echo esc_attr(get_option('dw_admin_phone')); ?>" class="regular-text" placeholder="62812xxxx">
                                            <p class="description">Gunakan format kode negara (62).</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_company_address">Alamat Kantor / Sekretariat</label></th>
                                        <td>
                                            <textarea name="dw_company_address" id="dw_company_address" rows="3" class="large-text code"><?php echo esc_textarea(get_option('dw_company_address')); ?></textarea>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($active_tab == 'payment'): ?>
                        <!-- PAYMENT TAB (Wallet & Manual Bank Only) -->
                        
                        <!-- 1. WALLET SETTINGS SECTION -->
                        <div class="dw-form-section" style="background: #fdfdfd; border-bottom: 1px solid #eee; margin-bottom: 20px; padding-bottom: 20px;">
                            <h3 style="color: #d63638;"><span class="dashicons dashicons-wallet"></span> Pengaturan Penarikan (Wallet)</h3>
                            <p class="description">Atur batasan penarikan saldo komisi untuk Mitra (Desa/Verifikator).</p>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="dw_min_withdrawal_amount">Batas Minimal Penarikan (IDR)</label></th>
                                        <td>
                                            <input type="number" name="dw_min_withdrawal_amount" id="dw_min_withdrawal_amount" value="<?php echo esc_attr( get_option( 'dw_min_withdrawal_amount', 10000 ) ); ?>" class="regular-text">
                                            <p class="description">Saldo minimal yang harus dimiliki mitra agar tombol "Tarik Saldo" aktif.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 2. MANUAL BANK SECTION -->
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-bank"></span> Rekening Admin (Manual Transfer)</h3>
                            <p class="description">Opsi cadangan jika gateway otomatis bermasalah.</p>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="dw_bank_name">Nama Bank</label></th>
                                        <td><input name="dw_bank_name" type="text" id="dw_bank_name" value="<?php echo esc_attr(get_option('dw_bank_name')); ?>" class="regular-text" placeholder="Contoh: BANK BRI"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_bank_account">Nomor Rekening</label></th>
                                        <td><input name="dw_bank_account" type="text" id="dw_bank_account" value="<?php echo esc_attr(get_option('dw_bank_account')); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_bank_holder">Atas Nama</label></th>
                                        <td><input name="dw_bank_holder" type="text" id="dw_bank_holder" value="<?php echo esc_attr(get_option('dw_bank_holder')); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_qris_field">URL Gambar QRIS</label></th>
                                        <td>
                                            <div style="display:flex; gap:10px; align-items:center; margin-bottom: 15px;">
                                                <input type="text" name="dw_qris_image_url" id="dw_qris_field" value="<?php echo esc_attr(get_option('dw_qris_image_url')); ?>" class="regular-text" placeholder="URL Gambar QRIS">
                                                <button type="button" class="button" id="btn_upl_qris_admin">Pilih Gambar</button>
                                            </div>
                                            <div class="dw-qris-preview">
                                                <img id="prev_qris_admin" src="<?php echo esc_url(get_option('dw_qris_image_url') ?: 'https://placehold.co/200x200?text=No+QRIS'); ?>" style="max-width:200px; height:auto; display:block; border-radius: 8px;">
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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

                    <?php elseif ($active_tab == 'api'): ?>
                        <!-- API & INTEGRASI TAB (XENDIT CONFIGURATION IS HERE) -->
                        <div class="dw-form-section">
                            <h3 style="color: #0073aa;"><span class="dashicons dashicons-rest-api"></span> Payment Gateway (Xendit)</h3>
                            <p class="description">Hubungkan aplikasi dengan Xendit untuk pembayaran otomatis (Topup Paket) dan pencairan dana (Wallet Withdrawal).</p>
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="xendit_mode">Mode Environment</label></th>
                                        <td>
                                            <select name="xendit_mode" id="xendit_mode">
                                                <option value="sandbox" <?php selected( isset($payment_settings['xendit_mode']) ? $payment_settings['xendit_mode'] : '', 'sandbox' ); ?>>Sandbox (Test)</option>
                                                <option value="production" <?php selected( isset($payment_settings['xendit_mode']) ? $payment_settings['xendit_mode'] : '', 'production' ); ?>>Production (Live)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="xendit_secret_key">Xendit Secret API Key</label></th>
                                        <td>
                                            <input type="password" name="xendit_secret_key" id="xendit_secret_key" value="<?php echo esc_attr( isset($payment_settings['xendit_secret_key']) ? $payment_settings['xendit_secret_key'] : '' ); ?>" class="large-text">
                                            <p class="description">Dapatkan di Dashboard Xendit > Settings > API Keys.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="xendit_callback_token">Callback Verification Token</label></th>
                                        <td>
                                            <input type="text" name="xendit_callback_token" id="xendit_callback_token" value="<?php echo esc_attr( isset($payment_settings['xendit_callback_token']) ? $payment_settings['xendit_callback_token'] : '' ); ?>" class="large-text">
                                            <p class="description">Dapatkan di Dashboard Xendit > Settings > Callbacks.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Webhook URL (Invoice Paid)</th>
                                        <td>
                                            <code style="background: #f0f0f1; padding: 5px; display: block; margin-top: 5px;"><?php echo site_url('/wp-json/dw-api/v1/webhook'); ?></code>
                                            <p class="description">Salin URL ini ke Dashboard Xendit bagian <b>Callback URL</b>.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- OTHER API SETTINGS -->
                        <div class="dw-form-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                            <h3><span class="dashicons dashicons-location"></span> Layanan Lainnya</h3>
                            <p class="description">Integrasi layanan pihak ketiga lainnya (Google Maps, Firebase, dll).</p>
                            <!-- Placeholder for future API keys -->
                            <p><i>(Belum ada integrasi lain)</i></p>
                        </div>

                    <?php elseif ($active_tab == 'referral'): ?>
                        <!-- REFERRAL TAB -->
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-awards"></span> Referral & Reward Kuota</h3>
                            <p class="description" style="margin-bottom: 25px;">Atur hadiah otomatis untuk pedagang yang berhasil mengajak pembeli baru bergabung.</p>
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="dw_bonus_quota_referral">Hadiah Kuota Transaksi (Bonus)</label></th>
                                        <td>
                                            <input name="dw_bonus_quota_referral" type="number" id="dw_bonus_quota_referral" value="<?php echo esc_attr(get_option('dw_bonus_quota_referral', 5)); ?>" min="0" class="small-text">
                                            <p class="description">Jumlah kuota transaksi GRATIS yang diberikan kepada Pedagang setiap kali ada 1 Pembeli baru mendaftar melalui link referral mereka.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_prefix_referral_pedagang">Prefix Kode Referral Pedagang</label></th>
                                        <td>
                                            <input name="dw_prefix_referral_pedagang" type="text" id="dw_prefix_referral_pedagang" value="<?php echo esc_attr(get_option('dw_prefix_referral_pedagang', 'TOKO')); ?>" class="regular-text" placeholder="Contoh: TOKO">
                                            <p class="description">Awalan kode referral otomatis untuk pedagang (Misal: TOKO-XXXX). Gunakan maksimal 5 karakter.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_ref_auto_verify">Metode Verifikasi Reward</label></th>
                                        <td>
                                            <select name="dw_ref_auto_verify" id="dw_ref_auto_verify">
                                                <?php $current_verify = get_option('dw_ref_auto_verify', 'auto'); ?>
                                                <option value="auto" <?php selected($current_verify, 'auto'); ?>>Berikan Kuota Otomatis (Instan)</option>
                                                <option value="manual" <?php selected($current_verify, 'manual'); ?>>Tinjau Manual Oleh Admin</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_buyer_referral_points">Poin Referral Pembeli</label></th>
                                        <td>
                                            <input name="dw_buyer_referral_points" type="number" id="dw_buyer_referral_points" value="<?php echo esc_attr(get_option('dw_buyer_referral_points', 10)); ?>" min="0" class="small-text">
                                            <p class="description">Jumlah poin yang diberikan kepada Pembeli lama setiap kali ada Pembeli baru mendaftar menggunakan kode referral mereka.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($active_tab == 'whatsapp'): ?>
                        <!-- WHATSAPP TAB -->
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-whatsapp"></span> Integrasi WhatsApp Gateway</h3>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="dw_wa_api_url">API URL Endpoint</label></th>
                                        <td><input name="dw_wa_api_url" type="text" id="dw_wa_api_url" value="<?php echo esc_attr(get_option('dw_wa_api_url')); ?>" class="regular-text" placeholder="https://api.fonnte.com/send"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_wa_api_key">API Key / Token</label></th>
                                        <td><input name="dw_wa_api_key" type="password" id="dw_wa_api_key" value="<?php echo esc_attr(get_option('dw_wa_api_key')); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="dw_wa_sender">Sender Number / ID</label></th>
                                        <td><input name="dw_wa_sender" type="text" id="dw_wa_sender" value="<?php echo esc_attr(get_option('dw_wa_sender')); ?>" class="regular-text"></td>
                                    </tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 25px;">Notifikasi Pesanan</h3>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="dw_order_notification_youtube">Link YouTube Nada Peringatan (Global)</label></th>
                                        <td>
                                            <input name="dw_order_notification_youtube" type="text" id="dw_order_notification_youtube" value="<?php echo esc_attr(get_option('dw_order_notification_youtube')); ?>" class="regular-text" placeholder="https://www.youtube.com/watch?v=xxxx">
                                            <p class="description">Masukkan link YouTube untuk nada peringatan saat ada pesanan masuk di dashboard toko (Default jika pedagang tidak mengatur sendiri).</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($active_tab == 'notification'): ?>
                        <!-- NOTIFICATION TAB -->
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-megaphone"></span> Pengaturan Nada Pesanan Masuk (Default)</h3>
                            <p class="description">Atur nada default yang akan digunakan oleh semua toko jika mereka belum mengatur nada sendiri.</p>
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="dw_sound_type">Tipe Nada Default</label></th>
                                        <td>
                                            <select name="dw_default_order_sound_type" id="dw_sound_type">
                                                <?php $current_type = get_option('dw_default_order_sound_type', 'default'); ?>
                                                <option value="default" <?php selected($current_type, 'default'); ?>>Suara Default Sistem</option>
                                                <option value="upload" <?php selected($current_type, 'upload'); ?>>Upload File (MP3/MP4)</option>
                                                <option value="youtube" <?php selected($current_type, 'youtube'); ?>>Link YouTube</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="group_sound_upload" style="<?php echo $current_type != 'upload' ? 'display:none;' : ''; ?>">
                                        <th scope="row"><label for="dw_sound_url_field">File Audio/Video</label></th>
                                        <td>
                                            <div style="display:flex; gap:10px; align-items:center;">
                                                <input type="text" name="dw_default_order_sound_url" id="dw_sound_url_field" value="<?php echo esc_attr(get_option('dw_default_order_sound_url')); ?>" class="regular-text" placeholder="URL File MP3/MP4">
                                                <button type="button" class="button" id="btn_upl_sound_default">Pilih File</button>
                                            </div>
                                            <p class="description">Upload file MP3 atau MP4 untuk digunakan sebagai nada pesanan.</p>
                                        </td>
                                    </tr>
                                    <tr id="group_sound_youtube" style="<?php echo $current_type != 'youtube' ? 'display:none;' : ''; ?>">
                                        <th scope="row"><label for="dw_sound_url_yt_field">Link YouTube</label></th>
                                        <td>
                                            <input type="text" name="dw_default_order_sound_url_yt" id="dw_sound_url_yt_field" value="<?php echo $current_type == 'youtube' ? esc_attr(get_option('dw_default_order_sound_url')) : ''; ?>" class="regular-text" placeholder="https://www.youtube.com/watch?v=xxxx">
                                            <p class="description">Masukkan link YouTube untuk nada peringatan.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
                    
                    <?php 
                    // Tombol Submit
                    submit_button( 'Simpan Perubahan', 'primary large', 'dw_settings_submit' );
                    ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}