<?php
/**
 * Class DW_Invoice
 * Menangani pembuatan dan download Invoice PDF untuk transaksi Desa Wisata.
 * Path: includes/classes/class-dw-invoice.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pastikan autoloader Composer dimuat jika Dompdf digunakan
if (file_exists(DW_PATH . 'vendor/autoload.php')) {
    require_once DW_PATH . 'vendor/autoload.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;

class DW_Invoice {

    public static function init() {
        // Hook untuk menangani request download invoice via URL admin-post
        // URL: admin-post.php?action=dw_download_invoice&id=123&nonce=...
        add_action('admin_post_dw_download_invoice', [__CLASS__, 'handle_download_request']);
        add_action('admin_post_nopriv_dw_download_invoice', [__CLASS__, 'handle_download_request']); // Untuk frontend user
    }

    /**
     * Handler utama saat tombol download diklik
     */
    public static function handle_download_request() {
        if (!isset($_GET['id']) || !isset($_GET['_wpnonce'])) {
            wp_die('Permintaan tidak valid.');
        }

        $order_id = intval($_GET['id']);
        
        // Verifikasi Nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_invoice_' . $order_id)) {
            wp_die('Tautan kadaluarsa atau tidak aman.');
        }

        // Cek Hak Akses
        if (!self::can_access_invoice($order_id)) {
            wp_die('Anda tidak memiliki izin untuk melihat invoice ini.');
        }

        // Generate Invoice
        self::generate($order_id);
    }

    /**
     * Cek apakah user saat ini boleh download invoice ini
     */
    private static function can_access_invoice($order_id) {
        if (current_user_can('manage_options')) return true; // Admin boleh semua

        global $wpdb;
        $current_user_id = get_current_user_id();
        
        // Cek Pembeli
        $pembeli_id = $wpdb->get_var($wpdb->prepare("SELECT id_pembeli FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));
        if ($pembeli_id == $current_user_id) return true;

        // Cek Pedagang (Sub-Order check)
        // Jika user adalah pedagang, cek apakah order ini mengandung produk dia
        $pedagang_row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $current_user_id));
        if ($pedagang_row) {
            $has_sub = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_transaksi_sub WHERE id_transaksi = %d AND id_pedagang = %d", $order_id, $pedagang_row->id));
            if ($has_sub) return true;
        }

        return false;
    }

    /**
     * Fungsi Inti Generate PDF
     */
    public static function generate($order_id) {
        $html = self::get_invoice_html($order_id);
        $filename = 'Invoice-DW-' . $order_id . '.pdf';

        // Cek apakah Dompdf tersedia
        if (class_exists('Dompdf\Dompdf')) {
            $options = new Options();
            $options->set('isRemoteEnabled', true); // Izinkan gambar dari URL (logo, foto produk)
            $options->set('defaultFont', 'Helvetica');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Stream file ke browser (Download)
            $dompdf->stream($filename, ["Attachment" => false]); // false = preview, true = download
            exit;
        } else {
            // Fallback: Cetak HTML biasa jika library PDF tidak ada
            echo $html;
            echo '<script>window.print();</script>';
            exit;
        }
    }

    /**
     * Menyusun Template HTML Invoice
     */
    private static function get_invoice_html($order_id) {
        global $wpdb;
        
        // Ambil Data Transaksi Utama
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));
        if (!$order) wp_die('Pesanan tidak ditemukan.');

        // Ambil Data Sub-Transaksi (Toko) & Item
        $subs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi_sub WHERE id_transaksi = %d", $order_id));
        
        // Logo & Info Situs
        $site_name = get_bloginfo('name');
        $site_logo = get_site_icon_url(); // Favicon sebagai logo, atau ganti URL kustom
        if(empty($site_logo)) $site_logo = 'https://via.placeholder.com/80?text=LOGO';

        // CSS Inline untuk PDF
        $css = "
            body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #333; }
            .header { width: 100%; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
            .logo { width: 60px; height: auto; vertical-align: middle; }
            .site-title { font-size: 20px; font-weight: bold; margin-left: 10px; vertical-align: middle; }
            .invoice-title { float: right; font-size: 24px; font-weight: bold; color: #4f46e5; text-transform: uppercase; }
            
            .info-table { width: 100%; margin-bottom: 20px; }
            .info-table td { vertical-align: top; padding: 5px; }
            .info-label { font-weight: bold; color: #666; width: 120px; }
            
            .box { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #eee; }
            
            .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .item-table th { background: #4f46e5; color: #fff; padding: 10px; text-align: left; }
            .item-table td { border-bottom: 1px solid #eee; padding: 10px; }
            .item-table tr:last-child td { border-bottom: none; }
            
            .total-table { width: 40%; float: right; border-top: 2px solid #333; }
            .total-table td { padding: 8px; text-align: right; }
            .grand-total { font-size: 16px; font-weight: bold; color: #4f46e5; }
            
            .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
            .badge { padding: 3px 8px; border-radius: 4px; font-weight: bold; color: white; font-size: 10px; }
            .bg-green { background-color: #27ae60; }
            .bg-yellow { background-color: #f39c12; }
            .bg-red { background-color: #c0392b; }
        ";

        // Status Badge
        $status_color = 'bg-yellow';
        $status_text = 'MENUNGGU PEMBAYARAN';
        if ($order->status_transaksi == 'pembayaran_dikonfirmasi' || $order->status_transaksi == 'selesai') {
            $status_color = 'bg-green';
            $status_text = 'LUNAS';
        } elseif ($order->status_transaksi == 'dibatalkan') {
            $status_color = 'bg-red';
            $status_text = 'DIBATALKAN';
        }

        // HTML Content
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice #<?php echo $order->kode_unik; ?></title>
            <style><?php echo $css; ?></style>
        </head>
        <body>
            
            <div class="header">
                <table width="100%">
                    <tr>
                        <td>
                            <img src="<?php echo $site_logo; ?>" class="logo">
                            <span class="site-title"><?php echo $site_name; ?></span>
                        </td>
                        <td align="right">
                            <div class="invoice-title">INVOICE</div>
                            <div>No: #<?php echo $order->kode_unik; ?></div>
                            <div style="margin-top:5px;"><span class="badge <?php echo $status_color; ?>"><?php echo $status_text; ?></span></div>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="info-table">
                <tr>
                    <td width="50%">
                        <div class="box">
                            <strong>Diterbitkan Oleh:</strong><br>
                            <?php echo $site_name; ?><br>
                            <small>Platform Desa Wisata Terpadu</small>
                        </div>
                    </td>
                    <td width="50%">
                        <div class="box">
                            <strong>Ditagihkan Kepada:</strong><br>
                            <?php echo esc_html($order->nama_penerima); ?><br>
                            <?php echo esc_html($order->no_hp); ?><br>
                            <br>
                            <strong>Alamat Pengiriman:</strong><br>
                            <?php echo esc_html($order->alamat_lengkap); ?><br>
                            <?php echo esc_html($order->kecamatan . ', ' . $order->kabupaten . ', ' . $order->provinsi . ' ' . $order->kode_pos); ?>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="info-table">
                <tr>
                    <td><span class="info-label">Tanggal Order:</span> <?php echo date('d F Y, H:i', strtotime($order->tanggal_transaksi)); ?></td>
                    <td><span class="info-label">Metode Bayar:</span> <?php echo strtoupper(str_replace('_', ' ', $order->metode_pembayaran)); ?></td>
                </tr>
            </table>

            <!-- LIST ITEM -->
            <table class="item-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th width="15%" style="text-align:center;">Jml</th>
                        <th width="20%" style="text-align:right;">Harga Satuan</th>
                        <th width="20%" style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total_produk = 0;
                    foreach($subs as $sub): 
                        // Header Toko
                        echo '<tr><td colspan="4" style="background:#f0f0f0; font-weight:bold; font-size:11px;">Penjual: ' . esc_html($sub->nama_toko) . ' | Ekspedisi: ' . esc_html(str_replace('_', ' ', $sub->metode_pengiriman)) . '</td></tr>';
                        
                        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi_items WHERE id_sub_transaksi = %d", $sub->id));
                        foreach($items as $item):
                            $meta = '';
                            if(!empty($item->nama_variasi)) $meta = '<br><small style="color:#666;">Variasi: ' . esc_html($item->nama_variasi) . '</small>';
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($item->nama_produk); ?></strong>
                                <?php echo $meta; ?>
                            </td>
                            <td align="center"><?php echo $item->jumlah; ?></td>
                            <td align="right">Rp <?php echo number_format($item->harga_satuan, 0, ',', '.'); ?></td>
                            <td align="right">Rp <?php echo number_format($item->total_harga, 0, ',', '.'); ?></td>
                        </tr>
                    <?php 
                        endforeach;
                        // Ongkir per Toko
                        if($sub->ongkir > 0):
                    ?>
                        <tr>
                            <td colspan="3" align="right" style="color:#666;">Ongkos Kirim (<?php echo esc_html($sub->nama_toko); ?>)</td>
                            <td align="right">Rp <?php echo number_format($sub->ongkir, 0, ',', '.'); ?></td>
                        </tr>
                    <?php endif; endforeach; ?>
                </tbody>
            </table>

            <!-- TOTAL -->
            <table class="total-table">
                <tr>
                    <td>Total Produk</td>
                    <td>Rp <?php echo number_format($order->total_produk, 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td>Total Ongkir</td>
                    <td>Rp <?php echo number_format($order->total_ongkir, 0, ',', '.'); ?></td>
                </tr>
                <?php 
                $fee = get_post_meta($order_id, '_dw_fee_buyer_amount', true);
                if($fee > 0): 
                ?>
                <tr>
                    <td>Biaya Layanan</td>
                    <td>Rp <?php echo number_format($fee, 0, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="grand-total">TOTAL BAYAR</td>
                    <td class="grand-total">Rp <?php echo number_format($order->total_transaksi, 0, ',', '.'); ?></td>
                </tr>
            </table>

            <div class="footer">
                Invoice ini sah dan diproses oleh komputer. Terima kasih telah berbelanja di Desa Wisata.
            </div>

        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}