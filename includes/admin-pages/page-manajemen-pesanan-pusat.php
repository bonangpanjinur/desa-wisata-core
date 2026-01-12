<?php
/**
 * File Name:   page-manajemen-pesanan-pusat.php
 * Description: Pusat Komando Pesanan (Centralized Order Hub) untuk Admin.
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin-ui-components.php';

function dw_manajemen_pesanan_pusat_render() {
    global $wpdb;
    
    // Filter Desa
    $filter_desa = isset($_GET['desa_id']) ? absint($_GET['desa_id']) : 0;
    
    // Query Pesanan (Card View)
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $table_main = $wpdb->prefix . 'dw_transaksi';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa = $wpdb->prefix . 'dw_desa';
    
    $query = "SELECT s.*, t.nama_penerima, t.alamat_penerima, t.hp_penerima, p.nama_toko, p.hp_pedagang, d.nama_desa 
              FROM $table_sub s
              JOIN $table_main t ON s.id_transaksi = t.id
              JOIN $table_pedagang p ON s.id_pedagang = p.id_user
              JOIN $table_desa d ON p.id_desa = d.id";
    
    if ($filter_desa > 0) {
        $query .= $wpdb->prepare(" WHERE p.id_desa = %d", $filter_desa);
    }
    
    $query .= " ORDER BY s.created_at DESC";
    $orders = $wpdb->get_results($query);
    $desas = $wpdb->get_results("SELECT id, nama_desa FROM $table_desa WHERE status = 'aktif'");
    
    ?>
    <div class="wrap dw-wrap">
        <h1>Pusat Komando Pesanan</h1>
        <p>Admin dapat memantau dan memverifikasi semua pesanan dari seluruh desa.</p>
        
        <div class="tablenav top">
            <form method="get">
                <input type="hidden" name="page" value="dw-manajemen-pesanan-pusat">
                <select name="desa_id">
                    <option value="0">Semua Desa</option>
                    <?php foreach ($desas as $desa): ?>
                        <option value="<?php echo $desa->id; ?>" <?php selected($filter_desa, $desa->id); ?>><?php echo esc_html($desa->nama_desa); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="Filter">
            </form>
        </div>

        

        <div class="dw-order-grid">
            <?php if ($orders): foreach ($orders as $order): 
                $status_class = 'status-' . strtolower($order->status_pesanan);
                $wa_pedagang = "https://wa.me/" . preg_replace('/[^0-9]/', '', $order->hp_pedagang) . "?text=" . urlencode("Halo Pedagang, ada pesanan baru #" . $order->id . " untuk produk " . $order->total_pesanan_toko);
                $wa_pembeli = "https://wa.me/" . preg_replace('/[^0-9]/', '', $order->hp_penerima) . "?text=" . urlencode("Halo " . $order->nama_penerima . ", pesanan Anda #" . $order->id . " sedang kami proses.");
            ?>
                <div class="dw-order-card <?php echo $status_class; ?>">
                    <div class="dw-order-header">
                        <strong>#<?php echo $order->id; ?></strong>
                        <span class="badge-desa"><?php echo esc_html($order->nama_desa); ?></span>
                    </div>
                    <div class="dw-order-body">
                        <p><strong>Toko:</strong> <?php echo esc_html($order->nama_toko); ?></p>
                        <p><strong>Pembeli:</strong> <?php echo esc_html($order->nama_penerima); ?></p>
                        <p><strong>Total:</strong> Rp <?php echo number_format($order->total_pesanan_toko, 0, ',', '.'); ?></p>
                        <p><strong>Status:</strong> <?php echo strtoupper($order->status_pesanan); ?></p>
                    </div>
                    <div class="dw-order-footer">
                        <a href="<?php echo $wa_pedagang; ?>" target="_blank" class="button button-secondary">Hubungi Pedagang</a>
                        <a href="<?php echo $wa_pembeli; ?>" target="_blank" class="button button-secondary">Hubungi Pembeli</a>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('dw_admin_override_nonce', 'dw_admin_override_action'); ?>
                            <input type="hidden" name="sub_order_id" value="<?php echo $order->id; ?>">
                            <select name="new_status" onchange="this.form.submit()">
                                <option value="">Ubah Status</option>
                                <option value="pending">Pending</option>
                                <option value="diproses">Diproses</option>
                                <option value="selesai">Selesai</option>
                                <option value="dibatalkan">Batalkan</option>
                            </select>
                            <input type="hidden" name="dw_admin_override" value="1">
                        </form>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <p>Tidak ada pesanan ditemukan.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Admin Override Handler
add_action('admin_init', function() {
    if (isset($_POST['dw_admin_override']) && check_admin_referer('dw_admin_override_action', 'dw_admin_override_nonce')) {
        if (!current_user_can('manage_options')) return;
        
        $sub_order_id = absint($_POST['sub_order_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        if ($sub_order_id && $new_status) {
            dw_update_sub_order_status($sub_order_id, $new_status, 'Diubah oleh Admin Pusat');
            wp_redirect(remove_query_arg(['dw_admin_override', 'dw_admin_override_action', 'dw_admin_override_nonce']));
            exit;
        }
    }
});
