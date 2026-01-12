<?php
/**
 * File Name:   page-komplain.php
 * Description: Logbook Komplain (Sengketa Transaksi Manual).
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin-ui-components.php';

function dw_komplain_page_render() {
    global $wpdb;
    $table_complaints = $wpdb->prefix . 'dw_complaints';
    
    // Handle Form Submission
    if (isset($_POST['dw_add_complaint']) && check_admin_referer('dw_add_complaint_action', 'dw_add_complaint_nonce')) {
        $order_id = absint($_POST['order_id']);
        $keterangan = sanitize_textarea_field($_POST['keterangan']);
        
        $wpdb->insert($table_complaints, [
            'order_id' => $order_id,
            'keterangan' => $keterangan,
            'status' => 'open',
            'created_at' => current_time('mysql')
        ]);
        echo '<div class="updated"><p>Komplain berhasil dicatat!</p></div>';
    }

    $complaints = $wpdb->get_results("SELECT * FROM $table_complaints ORDER BY created_at DESC");
    ?>
    <div class="wrap dw-wrap">
        <h1>Logbook Komplain</h1>
        
        <div class="dw-card" style="padding: 20px; margin-bottom: 20px;">
            <h3>Catat Komplain Baru</h3>
            <form method="post">
                <?php wp_nonce_field('dw_add_complaint_action', 'dw_add_complaint_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>ID Pesanan</th>
                        <td><input type="number" name="order_id" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Keterangan Komplain</th>
                        <td><textarea name="keterangan" required class="large-text" rows="4"></textarea></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="dw_add_complaint" class="button button-primary" value="Simpan Komplain">
                </p>
            </form>
        </div>

        <h3>Daftar Komplain</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID Pesanan</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($complaints): foreach ($complaints as $complaint): ?>
                    <tr>
                        <td>#<?php echo $complaint->order_id; ?></td>
                        <td><?php echo nl2br(esc_html($complaint->keterangan)); ?></td>
                        <td><span class="dw-pill <?php echo $complaint->status == 'open' ? 'warning' : 'success'; ?>"><?php echo strtoupper($complaint->status); ?></span></td>
                        <td><?php echo esc_html($complaint->created_at); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">Belum ada komplain.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
