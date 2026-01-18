<?php
/**
 * File: includes/admin-pages/page-settings.php
 * Admin Page: Pengaturan Sistem
 * Update: Fix AJAX Path "Error Koneksi API" & Dropdown Wilayah
 */

defined( 'ABSPATH' ) || exit;

// Include UI components if exists
if ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'admin-ui-components.php' ) ) {
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin-ui-components.php';
}

/**
 * --- [LOGIC 1] HANDLER AJAX INTERNAL UNTUK FETCH KOTA ---
 * Fix: Logika ini dipindah ke dalam hook 'admin_init' untuk mencegah error 
 * 'Call to undefined function wp_verify_nonce' saat file dimuat terlalu awal.
 */
function dw_handle_city_ajax_request() {
    if ( isset( $_POST['dw_action'] ) && $_POST['dw_action'] === 'dw_get_cities_internal' ) {
        // Bersihkan output buffer agar JSON valid (mencegah error syntax token <)
        if (ob_get_length()) ob_clean();

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dw_region_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
            exit;
        }

        $prov_id = sanitize_text_field( $_POST['prov_id'] );
        
        // LOAD FILE API WILAYAH (FIXED PATH)
        if ( ! class_exists( 'DW_Address_API' ) && ! function_exists('dw_get_cities') ) {
            if ( defined('DW_CORE_PATH') && file_exists( DW_CORE_PATH . 'includes/address-api.php' ) ) {
                require_once DW_CORE_PATH . 'includes/address-api.php';
            } else {
                // Fallback: Naik satu level dari 'admin-pages' ke 'includes'
                $manual_path = dirname( dirname( __FILE__ ) ) . '/address-api.php';
                if ( file_exists( $manual_path ) ) {
                    require_once $manual_path;
                }
            }
        }

        $cities = [];
        // Prioritaskan Class, lalu Function
        if ( class_exists( 'DW_Address_API' ) && method_exists( 'DW_Address_API', 'get_cities' ) ) {
            $cities = DW_Address_API::get_cities( $prov_id );
        } elseif ( function_exists( 'dw_get_cities' ) ) {
            $cities = dw_get_cities( $prov_id );
        }

        if ( ! empty($cities) ) {
            wp_send_json_success( $cities );
        } else {
            wp_send_json_error( 'Data kota tidak ditemukan atau API file belum dimuat.' );
        }
        exit; 
    }
}
add_action( 'admin_init', 'dw_handle_city_ajax_request' );

/**
 * LOGIC HANDLER: CRUD TARIF OJEK
 */
function dw_handle_ojek_actions() {
    if (isset($_POST['dw_save_rate']) && check_admin_referer('dw_save_rate_nonce')) {
        // Pastikan handler ojek dimuat
        if ( ! class_exists( 'DW_Ojek_Handler' ) ) {
            if ( defined('DW_CORE_PATH') ) {
                require_once DW_CORE_PATH . 'includes/classes/class-dw-ojek-handler.php';
            } elseif ( file_exists( dirname( dirname( __FILE__ ) ) . '/classes/class-dw-ojek-handler.php' ) ) {
                require_once dirname( dirname( __FILE__ ) ) . '/classes/class-dw-ojek-handler.php';
            }
        }
        
        $nama_kabupaten = isset($_POST['nama_kabupaten']) ? sanitize_text_field($_POST['nama_kabupaten']) : '';

        $rate_data = [
            'api_kabupaten_id' => sanitize_text_field($_POST['api_kabupaten_id']),
            'nama_kabupaten' => $nama_kabupaten,
            'base_fare' => floatval($_POST['base_fare']),
            'price_per_km' => floatval($_POST['price_per_km']),
            'min_distance_km' => floatval($_POST['min_distance_km']),
            'commission_percent' => floatval($_POST['commission_percent']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if ( class_exists('DW_Ojek_Handler') ) {
            $result = DW_Ojek_Handler::save_rate($rate_data);
            if ($result !== false) {
                add_settings_error('dw_settings_notices', 'rate_saved', 'Tarif wilayah ' . $nama_kabupaten . ' berhasil disimpan.', 'success');
            } else {
                add_settings_error('dw_settings_notices', 'rate_failed', 'Gagal menyimpan. Pastikan ID Kabupaten belum ada sebelumnya.', 'error');
            }
        } else {
            add_settings_error('dw_settings_notices', 'class_missing', 'Class DW_Ojek_Handler tidak ditemukan.', 'error');
        }
    }

    if (isset($_GET['action']) && $_GET['action'] == 'delete_rate' && isset($_GET['id']) && isset($_GET['tab']) && $_GET['tab'] == 'ojek') {
        if ( ! class_exists( 'DW_Ojek_Handler' ) ) {
             if ( defined('DW_CORE_PATH') ) {
                require_once DW_CORE_PATH . 'includes/classes/class-dw-ojek-handler.php';
            } elseif ( file_exists( dirname( dirname( __FILE__ ) ) . '/classes/class-dw-ojek-handler.php' ) ) {
                require_once dirname( dirname( __FILE__ ) ) . '/classes/class-dw-ojek-handler.php';
            }
        }
        
        if ( class_exists('DW_Ojek_Handler') ) {
            DW_Ojek_Handler::delete_rate(intval($_GET['id']));
            add_settings_error('dw_settings_notices', 'rate_deleted', 'Tarif berhasil dihapus.', 'success');
        }
    }
}
add_action('admin_init', 'dw_handle_ojek_actions');

/**
 * Handler Simpan Pengaturan Utama
 */
function dw_settings_save_handler() {
    if ( ! isset( $_POST['dw_settings_submit'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['dw_save_settings_nonce_field'], 'dw_save_settings_action' ) ) return;

    $tab = $_POST['active_tab'] ?? 'general';
    $gen_settings = get_option( 'dw_settings_general', [] );
    $payment_settings = get_option( 'dw_payment_settings', [] );

    if ( $tab === 'general' ) {
        update_option( 'dw_app_name', sanitize_text_field( $_POST['dw_app_name'] ) );
        update_option( 'dw_admin_phone', sanitize_text_field( $_POST['dw_admin_phone'] ) );
        update_option( 'dw_company_address', sanitize_textarea_field( $_POST['dw_company_address'] ) );
        update_option( 'dw_maps_api_key', sanitize_text_field( $_POST['dw_maps_api_key'] ) );

        $gen_settings['desa_name'] = sanitize_text_field( $_POST['dw_app_name'] );
        $gen_settings['desa_address'] = sanitize_textarea_field( $_POST['dw_company_address'] );
        update_option( 'dw_settings_general', $gen_settings );

    } elseif ( $tab === 'marketplace' ) {
        if ( isset( $_POST['dw_buyer_fee_type'] ) ) update_option( 'dw_buyer_fee_type', sanitize_text_field( $_POST['dw_buyer_fee_type'] ) );
        if ( isset( $_POST['dw_buyer_fee_value'] ) ) update_option( 'dw_buyer_fee_value', floatval( $_POST['dw_buyer_fee_value'] ) );
        if ( isset( $_POST['dw_merchant_fee_type'] ) ) update_option( 'dw_merchant_fee_type', sanitize_text_field( $_POST['dw_merchant_fee_type'] ) );
        if ( isset( $_POST['dw_merchant_fee_value'] ) ) update_option( 'dw_merchant_fee_value', floatval( $_POST['dw_merchant_fee_value'] ) );

    } elseif ( $tab === 'ojek' ) {
        update_option('dw_ojek_min_balance', sanitize_text_field($_POST['dw_ojek_min_balance']));
        update_option('dw_ojek_global_commission', sanitize_text_field($_POST['dw_ojek_global_commission']));
        update_option('dw_ojek_welcome_balance', sanitize_text_field($_POST['dw_ojek_welcome_balance']));
        if(isset($_POST['dw_ojek_global_base_fare'])) update_option('dw_ojek_global_base_fare', sanitize_text_field($_POST['dw_ojek_global_base_fare']));
        if(isset($_POST['dw_ojek_global_price_km'])) update_option('dw_ojek_global_price_km', sanitize_text_field($_POST['dw_ojek_global_price_km']));
        if(isset($_POST['dw_ojek_global_min_km'])) update_option('dw_ojek_global_min_km', sanitize_text_field($_POST['dw_ojek_global_min_km']));

    } elseif ( $tab === 'payment' ) {
        update_option( 'dw_bank_name', sanitize_text_field( $_POST['dw_bank_name'] ) );
        update_option( 'dw_bank_account', sanitize_text_field( $_POST['dw_bank_account'] ) );
        update_option( 'dw_bank_holder', sanitize_text_field( $_POST['dw_bank_holder'] ) );
        update_option( 'dw_qris_image_url', esc_url_raw( $_POST['dw_qris_image_url'] ) );
        update_option( 'dw_min_withdrawal_amount', absint( $_POST['dw_min_withdrawal_amount'] ) );

    } elseif ( $tab === 'api' ) {
        $payment_settings['xendit_mode'] = sanitize_text_field( $_POST['xendit_mode'] );
        $payment_settings['xendit_secret_key'] = sanitize_text_field( $_POST['xendit_secret_key'] );
        $payment_settings['xendit_callback_token'] = sanitize_text_field( $_POST['xendit_callback_token'] );
        update_option( 'dw_payment_settings', $payment_settings );

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
    if ( ! current_user_can( 'manage_options' ) ) return;

    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
    $errors = get_transient('settings_errors');
    if($errors) { foreach($errors as $error) add_settings_error($error['setting'], $error['code'], $error['message'], $error['type']); delete_transient('settings_errors'); }

    $settings_gen = get_option( 'dw_settings_general', [] );
    $payment_settings = get_option( 'dw_payment_settings', [] );
    $buyer_fee_type = get_option( 'dw_buyer_fee_type', 'fixed' );
    $buyer_fee_value = get_option( 'dw_buyer_fee_value', 0 );
    $merchant_fee_type = get_option( 'dw_merchant_fee_type', 'percentage' );
    $merchant_fee_value = get_option( 'dw_merchant_fee_value', 5 );
    
    if ( empty($payment_settings['xendit_secret_key']) && !empty($settings_gen['xendit_secret_key']) ) {
        $payment_settings['xendit_secret_key'] = $settings_gen['xendit_secret_key'];
        $payment_settings['xendit_callback_token'] = $settings_gen['xendit_callback_token'];
        $payment_settings['xendit_mode'] = $settings_gen['xendit_mode'];
    }
    ?>
    <div class="wrap dw-admin-wrapper">
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Konfigurasi utama sistem Desa Wisata & Marketplace.</p>
            </div>
        </div>

        <div class="dw-content-body">
            <?php settings_errors('dw_settings_notices'); ?>

            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="?page=dw-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Umum</a>
                <a href="?page=dw-settings&tab=marketplace" class="nav-tab <?php echo $active_tab == 'marketplace' ? 'nav-tab-active' : ''; ?>">Marketplace</a>
                <a href="?page=dw-settings&tab=ojek" class="nav-tab <?php echo $active_tab == 'ojek' ? 'nav-tab-active' : ''; ?>">Ojek & Transport</a>
                <a href="?page=dw-settings&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">Keuangan</a>
                <a href="?page=dw-settings&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">API</a>
                <a href="?page=dw-settings&tab=whatsapp" class="nav-tab <?php echo $active_tab == 'whatsapp' ? 'nav-tab-active' : ''; ?>">Notifikasi</a>
                <a href="?page=dw-settings&tab=referral" class="nav-tab <?php echo $active_tab == 'referral' ? 'nav-tab-active' : ''; ?>">Referral</a>
                <a href="?page=dw-settings&tab=notification" class="nav-tab <?php echo $active_tab == 'notification' ? 'nav-tab-active' : ''; ?>">Sound</a>
            </nav>

            <div class="dw-card">
                <form method="post">
                    <input type="hidden" name="active_tab" value="<?php echo esc_attr($active_tab); ?>">
                    <?php wp_nonce_field( 'dw_save_settings_action', 'dw_save_settings_nonce_field' ); ?>

                    <?php if ($active_tab == 'general'): ?>
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-admin-site"></span> Identitas Aplikasi</h3>
                            <table class="form-table">
                                <tr><th scope="row">Nama Aplikasi</th><td><input name="dw_app_name" type="text" value="<?php echo esc_attr(get_option('dw_app_name', 'Desa Wisata')); ?>" class="regular-text"></td></tr>
                                <tr><th scope="row">Nomor WhatsApp Admin</th><td><input name="dw_admin_phone" type="text" value="<?php echo esc_attr(get_option('dw_admin_phone')); ?>" class="regular-text" placeholder="62812xxxx"></td></tr>
                                <tr><th scope="row">Alamat Kantor</th><td><textarea name="dw_company_address" rows="3" class="large-text code"><?php echo esc_textarea(get_option('dw_company_address')); ?></textarea></td></tr>
                                <tr><th scope="row">Google Maps API Key</th><td><input name="dw_maps_api_key" type="text" value="<?php echo esc_attr(get_option('dw_maps_api_key')); ?>" class="regular-text"></td></tr>
                            </table>
                        </div>
                    <?php elseif ($active_tab == 'marketplace'): ?>
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-chart-pie"></span> Skema Biaya & Komisi</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Biaya Layanan Pembeli</th>
                                    <td>
                                        <select name="dw_buyer_fee_type"><option value="fixed" <?php selected( $buyer_fee_type, 'fixed' ); ?>>Nominal Tetap (Rp)</option><option value="percentage" <?php selected( $buyer_fee_type, 'percentage' ); ?>>Persentase (%)</option></select>
                                        <input name="dw_buyer_fee_value" type="number" step="0.01" value="<?php echo esc_attr( $buyer_fee_value ); ?>" class="regular-text" style="width: 150px;">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Komisi Pedagang</th>
                                    <td>
                                        <select name="dw_merchant_fee_type"><option value="fixed" <?php selected( $merchant_fee_type, 'fixed' ); ?>>Nominal Tetap (Rp)</option><option value="percentage" <?php selected( $merchant_fee_type, 'percentage' ); ?>>Persentase (%)</option></select>
                                        <input name="dw_merchant_fee_value" type="number" step="0.01" value="<?php echo esc_attr( $merchant_fee_value ); ?>" class="regular-text" style="width: 150px;">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php elseif ($active_tab == 'ojek'): ?>
                        <div class="dw-form-section">
                            <h3><span class="dashicons dashicons-car"></span> Konfigurasi Bisnis Ojek</h3>
                            <div style="background: #e5f6fd; border: 1px solid #cceeff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                                <h4 style="margin-top:0; color: #0073aa;">Tarif Dasar (Global Fallback)</h4>
                                <div style="display:flex; gap: 15px; flex-wrap: wrap;">
                                    <div><label style="display:block; font-size:12px;">Tarif Buka Pintu (Rp)</label><input type="number" name="dw_ojek_global_base_fare" value="<?php echo esc_attr(get_option('dw_ojek_global_base_fare', 7000)); ?>" class="regular-text" style="width: 120px;"></div>
                                    <div><label style="display:block; font-size:12px;">Harga per KM (Rp)</label><input type="number" name="dw_ojek_global_price_km" value="<?php echo esc_attr(get_option('dw_ojek_global_price_km', 2500)); ?>" class="regular-text" style="width: 120px;"></div>
                                    <div><label style="display:block; font-size:12px;">Jarak Minimum (KM)</label><input type="number" name="dw_ojek_global_min_km" value="<?php echo esc_attr(get_option('dw_ojek_global_min_km', 2)); ?>" class="small-text" step="0.1" style="width: 80px;"></div>
                                </div>
                            </div>
                            <table class="form-table">
                                <tr><th scope="row">Saldo Minimum Driver (Rp)</th><td><input type="number" name="dw_ojek_min_balance" value="<?php echo esc_attr(get_option('dw_ojek_min_balance', 20000)); ?>" class="regular-text"></td></tr>
                                <tr><th scope="row">Komisi Global (%)</th><td><input type="number" name="dw_ojek_global_commission" value="<?php echo esc_attr(get_option('dw_ojek_global_commission', 10)); ?>" class="regular-text" step="0.1"></td></tr>
                                <tr><th scope="row">Saldo Awal Bonus (Rp)</th><td><input type="number" name="dw_ojek_welcome_balance" value="<?php echo esc_attr(get_option('dw_ojek_welcome_balance', 10000)); ?>" class="regular-text"></td></tr>
                            </table>
                        </div>
                    <?php elseif ($active_tab == 'payment'): ?>
                        <div class="dw-form-section">
                            <h3>Pengaturan Penarikan</h3>
                            <table class="form-table">
                                <tr><th scope="row">Batas Minimal Penarikan</th><td><input type="number" name="dw_min_withdrawal_amount" value="<?php echo esc_attr( get_option( 'dw_min_withdrawal_amount', 10000 ) ); ?>" class="regular-text"></td></tr>
                            </table>
                        </div>
                        <div class="dw-form-section">
                            <h3>Rekening Admin Manual</h3>
                            <table class="form-table">
                                <tr><th scope="row">Nama Bank</th><td><input name="dw_bank_name" type="text" value="<?php echo esc_attr(get_option('dw_bank_name')); ?>" class="regular-text"></td></tr>
                                <tr><th scope="row">No. Rekening</th><td><input name="dw_bank_account" type="text" value="<?php echo esc_attr(get_option('dw_bank_account')); ?>" class="regular-text"></td></tr>
                                <tr><th scope="row">Atas Nama</th><td><input name="dw_bank_holder" type="text" value="<?php echo esc_attr(get_option('dw_bank_holder')); ?>" class="regular-text"></td></tr>
                                <tr><th scope="row">URL QRIS</th><td><input type="text" name="dw_qris_image_url" id="dw_qris_field" value="<?php echo esc_attr(get_option('dw_qris_image_url')); ?>" class="regular-text"> <button type="button" class="button" id="btn_upl_qris_admin">Upload</button></td></tr>
                            </table>
                        </div>
                        <script>jQuery(document).ready(function($){ $('#btn_upl_qris_admin').click(function(e){ e.preventDefault(); var frame = wp.media({title:'Upload QRIS', multiple:false, library:{type:'image'}}); frame.on('select', function(){ var url = frame.state().get('selection').first().toJSON().url; $('#dw_qris_field').val(url); }); frame.open(); }); });</script>
                    <?php elseif ($active_tab == 'api'): ?>
                        <div class="dw-form-section"><h3>Xendit Gateway</h3><table class="form-table"><tr><th scope="row">Mode</th><td><select name="xendit_mode"><option value="sandbox" <?php selected($payment_settings['xendit_mode']??'', 'sandbox'); ?>>Sandbox</option><option value="production" <?php selected($payment_settings['xendit_mode']??'', 'production'); ?>>Production</option></select></td></tr><tr><th scope="row">Secret Key</th><td><input type="password" name="xendit_secret_key" value="<?php echo esc_attr($payment_settings['xendit_secret_key']??''); ?>" class="large-text"></td></tr><tr><th scope="row">Callback Token</th><td><input type="text" name="xendit_callback_token" value="<?php echo esc_attr($payment_settings['xendit_callback_token']??''); ?>" class="large-text"></td></tr><tr><th scope="row">Webhook URL</th><td><code><?php echo site_url('/wp-json/dw-api/v1/webhook'); ?></code></td></tr></table></div>
                    <?php elseif ($active_tab == 'referral'): ?>
                        <div class="dw-form-section"><h3>Referral</h3><table class="form-table"><tr><th scope="row">Bonus Kuota</th><td><input name="dw_bonus_quota_referral" type="number" value="<?php echo esc_attr(get_option('dw_bonus_quota_referral', 5)); ?>" class="small-text"></td></tr><tr><th scope="row">Prefix Kode</th><td><input name="dw_prefix_referral_pedagang" type="text" value="<?php echo esc_attr(get_option('dw_prefix_referral_pedagang', 'TOKO')); ?>" class="regular-text"></td></tr><tr><th scope="row">Metode Verifikasi</th><td><select name="dw_ref_auto_verify"><option value="auto" <?php selected(get_option('dw_ref_auto_verify','auto'),'auto'); ?>>Otomatis</option><option value="manual" <?php selected(get_option('dw_ref_auto_verify','auto'),'manual'); ?>>Manual</option></select></td></tr></table></div>
                    <?php elseif ($active_tab == 'whatsapp'): ?>
                        <div class="dw-form-section"><h3>WhatsApp</h3><table class="form-table"><tr><th scope="row">API URL</th><td><input name="dw_wa_api_url" type="text" value="<?php echo esc_attr(get_option('dw_wa_api_url')); ?>" class="regular-text"></td></tr><tr><th scope="row">API Key</th><td><input name="dw_wa_api_key" type="password" value="<?php echo esc_attr(get_option('dw_wa_api_key')); ?>" class="regular-text"></td></tr><tr><th scope="row">Sender</th><td><input name="dw_wa_sender" type="text" value="<?php echo esc_attr(get_option('dw_wa_sender')); ?>" class="regular-text"></td></tr></table></div>
                    <?php elseif ($active_tab == 'notification'): ?>
                        <div class="dw-form-section"><h3>Sound</h3><table class="form-table"><tr><th scope="row">Tipe Nada</th><td><select name="dw_default_order_sound_type"><option value="default">Default</option><option value="upload">Upload</option><option value="youtube">YouTube</option></select></td></tr><tr><th scope="row">URL File</th><td><input type="text" name="dw_default_order_sound_url" value="<?php echo esc_attr(get_option('dw_default_order_sound_url')); ?>" class="regular-text"></td></tr></table></div>
                    <?php endif; ?>
                    <?php submit_button( 'Simpan Perubahan', 'primary large', 'dw_settings_submit' ); ?>
                </form>
            </div>

            <?php if ($active_tab == 'ojek'): ?>
                <hr style="margin: 30px 0; border: 0; border-top: 1px solid #ccc;">
                <h3>Tarif Per Wilayah (Kabupaten)</h3>
                <p class="description">Gunakan Dropdown di bawah untuk menambah tarif baru atau mengupdate tarif wilayah yang sudah ada.</p>

                <div class="dw-card" style="background: #f0f0f1; border: 1px solid #ddd;">
                    <strong><span class="dashicons dashicons-plus-alt2"></span> Tambah / Edit Tarif</strong>
                    <form method="post" action="" style="margin-top: 15px;">
                        <?php wp_nonce_field('dw_save_rate_nonce'); ?>
                        
                        <!-- DROPDOWN WILAYAH BERTINGKAT -->
                        <div style="display: flex; gap: 10px; margin-bottom: 15px; background: #fff; padding: 10px; border: 1px solid #e5e5e5; border-radius: 4px;">
                            <div style="flex: 1;">
                                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">1. Pilih Provinsi</label>
                                <select id="dw_select_provinsi" style="width: 100%;">
                                    <option value="">-- Pilih Provinsi --</option>
                                    <?php 
                                    // Load API Wilayah
                                    if ( ! class_exists( 'DW_Address_API' ) ) {
                                        if ( defined('DW_CORE_PATH') && file_exists( DW_CORE_PATH . 'includes/address-api.php' ) ) {
                                            require_once DW_CORE_PATH . 'includes/address-api.php';
                                        } elseif ( file_exists( dirname( dirname( __FILE__ ) ) . '/address-api.php' ) ) {
                                            require_once dirname( dirname( __FILE__ ) ) . '/address-api.php';
                                        }
                                    }

                                    $provinces = [];
                                    if ( class_exists('DW_Address_API') && method_exists('DW_Address_API', 'get_provinces') ) {
                                        $provinces = DW_Address_API::get_provinces();
                                    } elseif ( function_exists('dw_get_provinces') ) {
                                        $provinces = dw_get_provinces();
                                    }

                                    if ( ! empty( $provinces ) && is_array( $provinces ) ) {
                                        foreach ($provinces as $prov) {
                                            // Handle format object or array
                                            $id = is_object($prov) ? $prov->id : (isset($prov['id']) ? $prov['id'] : '');
                                            $name = is_object($prov) ? $prov->name : (isset($prov['name']) ? $prov['name'] : '');
                                            if ($id && $name) {
                                                echo '<option value="' . esc_attr($id) . '">' . esc_html($name) . '</option>';
                                            }
                                        }
                                    } else {
                                        echo '<option disabled>Data Provinsi Tidak Ditemukan</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div style="flex: 1;">
                                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">2. Pilih Kabupaten/Kota</label>
                                <select id="dw_select_kabupaten" name="api_kabupaten_id" style="width: 100%;" disabled>
                                    <option value="">-- Pilih Provinsi Dulu --</option>
                                </select>
                                <input type="hidden" name="nama_kabupaten" id="dw_input_nama_kabupaten">
                            </div>
                        </div>

                        <!-- INPUT HARGA -->
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                            <div style="flex: 1;">
                                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">Tarif Dasar (Rp)</label>
                                <input type="number" name="base_fare" value="5000" required style="width: 100%;">
                            </div>
                            <div style="flex: 1;">
                                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">Harga / KM (Rp)</label>
                                <input type="number" name="price_per_km" value="2000" required style="width: 100%;">
                            </div>
                            <div style="flex: 1;">
                                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">Jarak Min (KM)</label>
                                <input type="number" name="min_distance_km" value="1" step="0.1" required style="width: 100%;">
                            </div>
                            <div style="flex: 1;">
                                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">Komisi Lokal (%)</label>
                                <input type="number" name="commission_percent" value="10" step="0.1" style="width: 100%;">
                            </div>
                             <div style="flex: 0.5;">
                                <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">Aktif</label>
                                <input type="checkbox" name="is_active" value="1" checked>
                            </div>
                            <div style="flex: 0;">
                                 <input type="submit" name="dw_save_rate" class="button button-secondary" value="Simpan Tarif">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- AJAX SCRIPT -->
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#dw_select_provinsi').change(function() {
                        var provId = $(this).val();
                        var $citySelect = $('#dw_select_kabupaten');
                        
                        $citySelect.empty().append('<option value="">Loading...</option>').prop('disabled', true);
                        $('#dw_input_nama_kabupaten').val('');

                        if (provId) {
                            $.ajax({
                                url: window.location.href, // Self-Post to trigger PHP block at top
                                type: 'POST',
                                data: {
                                    dw_action: 'dw_get_cities_internal',
                                    prov_id: provId,
                                    nonce: '<?php echo wp_create_nonce("dw_region_nonce"); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        $citySelect.empty().append('<option value="">-- Pilih Kabupaten --</option>');
                                        $.each(response.data, function(index, item) {
                                            // Handle baik object maupun array
                                            var id = item.id || item['id'];
                                            var name = item.name || item['nama'] || item['name'];
                                            $citySelect.append('<option value="' + id + '">' + name + '</option>');
                                        });
                                        $citySelect.prop('disabled', false);
                                    } else {
                                        $citySelect.empty().append('<option value="">Gagal memuat: ' + response.data + '</option>');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error(xhr.responseText);
                                    $citySelect.empty().append('<option value="">Error Koneksi API (Cek Console)</option>');
                                }
                            });
                        } else {
                            $citySelect.empty().append('<option value="">-- Pilih Provinsi Dulu --</option>');
                        }
                    });

                    $('#dw_select_kabupaten').change(function() {
                        var cityName = $(this).find('option:selected').text();
                        if ($(this).val()) {
                            $('#dw_input_nama_kabupaten').val(cityName);
                        } else {
                            $('#dw_input_nama_kabupaten').val('');
                        }
                    });
                });
                </script>

                <!-- LIST TARIF -->
                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>ID Kab</th>
                            <th>Nama Kabupaten</th>
                            <th>Tarif Dasar</th>
                            <th>Harga / KM</th>
                            <th>Min. Jarak</th>
                            <th>Komisi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'dw_ojek_rates';
                        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                            $rates = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
                            if ($rates) {
                                foreach ($rates as $rate) {
                                    echo '<tr>';
                                    echo '<td>' . esc_html($rate->api_kabupaten_id) . '</td>';
                                    echo '<td>' . esc_html($rate->nama_kabupaten) . '</td>';
                                    echo '<td>Rp ' . number_format($rate->base_fare, 0, ',', '.') . '</td>';
                                    echo '<td>Rp ' . number_format($rate->price_per_km, 0, ',', '.') . '</td>';
                                    echo '<td>' . esc_html($rate->min_distance_km) . ' KM</td>';
                                    echo '<td>' . esc_html($rate->commission_percent) . '%</td>';
                                    echo '<td>' . ($rate->is_active ? '<span style="color:green; font-weight:bold;">Aktif</span>' : '<span style="color:red;">Nonaktif</span>') . '</td>';
                                    echo '<td><a href="?page=dw-settings&tab=ojek&action=delete_rate&id=' . $rate->id . '" class="button button-small button-link-delete" onclick="return confirm(\'Hapus?\')">Hapus</a></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8" style="text-align:center;">Belum ada data.</td></tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" style="text-align:center; color:red;">Tabel DB belum siap.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}