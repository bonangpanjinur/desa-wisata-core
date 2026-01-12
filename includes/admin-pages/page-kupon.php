<?php
/**
 * File Name:   page-kupon.php
 * Description: Manajemen Kupon Sederhana.
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin-ui-components.php';

function dw_kupon_page_render() {
    global $wpdb;
    $table_coupons = $wpdb->prefix . 'dw_coupons';
    
    // Handle Form Submission
    if (isset($_POST['dw_add_coupon']) && check_admin_referer('dw_add_coupon_action', 'dw_add_coupon_nonce')) {
        $kode = sanitize_text_field($_POST['kode']);
        $nominal = floatval($_POST['nominal']);
        $expired = sanitize_text_field($_POST['expired']);
        
        $wpdb->insert($table_coupons, [
            'kode' => $kode,
            'nominal' => $nominal,
            'expired_at' => $expired,
            'created_at' => current_time('mysql')
        ]);
        echo '<div class="updated"><p>Kupon berhasil ditambahkan!</p></div>';
    }

    $coupons = $wpdb->get_results("SELECT * FROM $table_coupons ORDER BY created_at DESC");
    ?>
    <div class="wrap dw-wrap">
        <h1>Manajemen Kupon</h1>
        
        <div class="dw-card" style="padding: 20px; margin-bottom: 20px;">
            <h3>Tambah Kupon Baru</h3>
            <form method="post">
                <?php wp_nonce_field('dw_add_coupon_action', 'dw_add_coupon_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Kode Kupon</th>
                        <td><input type="text" name="kode" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Nominal Diskon (Rp)</th>
                        <td><input type="number" name="nominal" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Tanggal Expired</th>
                        <td><input type="date" name="expired" required class="regular-text"></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="dw_add_coupon" class="button button-primary" value="Simpan Kupon">
                </p>
            </form>
        </div>

        <h3>Daftar Kupon</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nominal</th>
                    <th>Expired</th>
                    <th>Dibuat Pada</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($coupons): foreach ($coupons as $coupon): ?>
                    <tr>
                        <td><strong><?php echo esc_html($coupon->kode); ?></strong></td>
                        <td>Rp <?php echo number_format($coupon->nominal, 0, ',', '.'); ?></td>
                        <td><?php echo esc_html($coupon->expired_at); ?></td>
                        <td><?php echo esc_html($coupon->created_at); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">Belum ada kupon.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
