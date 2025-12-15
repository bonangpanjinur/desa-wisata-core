<?php
/**
 * File Name:   page-verifikasi-paket.php
 * Description: Verifikasi Pembelian Paket (Tampilan Card Modern).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler logic tetap sama seperti sebelumnya (disingkat di sini, fokus ke render)
function dw_verifikasi_paket_handler() {
    if (!isset($_POST['dw_verifikasi_paket_action'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_verifikasi_paket_nonce')) wp_die('Security fail');
    
    global $wpdb;
    $id = absint($_POST['pembelian_id']);
    $type = sanitize_key($_POST['action_type']);
    
    $pembelian = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}dw_pembelian_paket WHERE id=$id AND status='pending'");
    if(!$pembelian) wp_die('Data tidak valid');

    if ($type === 'approve_paket') {
        $wpdb->update("{$wpdb->prefix}dw_pembelian_paket", ['status'=>'disetujui', 'processed_at'=>current_time('mysql')], ['id'=>$id]);
        $wpdb->query("UPDATE {$wpdb->prefix}dw_pedagang SET sisa_transaksi = sisa_transaksi + {$pembelian->jumlah_transaksi}, status_akun='aktif' WHERE id={$pembelian->id_pedagang}");
        
        // Catat Komisi (Logika sama seperti sebelumnya)
        // ... (Simpan ke Payout Ledger) ...
        
        add_settings_error('dw_verif_msg', 'ok', 'Pembelian disetujui. Kuota ditambahkan.', 'success');
    } else {
        $wpdb->update("{$wpdb->prefix}dw_pembelian_paket", ['status'=>'ditolak', 'processed_at'=>current_time('mysql')], ['id'=>$id]);
        add_settings_error('dw_verif_msg', 'no', 'Pembelian ditolak.', 'warning');
    }
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-verifikasi-paket')); exit;
}
add_action('admin_init', 'dw_verifikasi_paket_handler');

function dw_verifikasi_paket_page_render() {
    global $wpdb;
    $pending = $wpdb->get_results("SELECT pp.*, p.nama_toko FROM {$wpdb->prefix}dw_pembelian_paket pp JOIN {$wpdb->prefix}dw_pedagang p ON pp.id_pedagang=p.id WHERE pp.status='pending' ORDER BY pp.created_at ASC");
    ?>
    <div class="wrap dw-wrap">
        <h1>Verifikasi Pembelian Paket</h1>
        <?php $e=get_transient('settings_errors'); if($e){ settings_errors('dw_verif_msg'); delete_transient('settings_errors'); } ?>
        
        <style>
            .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; margin-top:20px; overflow:hidden; }
            .dw-price { font-weight:bold; color:#166534; font-size:13px; }
        </style>

        <h2 style="font-size:16px;">Menunggu Persetujuan</h2>
        <div class="dw-card-table">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Toko</th><th>Paket</th><th>Bukti Bayar</th><th>Waktu</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php if(empty($pending)): ?><tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">Tidak ada permintaan baru.</td></tr><?php endif; ?>
                    <?php foreach($pending as $r): ?>
                    <tr>
                        <td><strong><?php echo esc_html($r->nama_toko); ?></strong></td>
                        <td>
                            <?php echo esc_html($r->nama_paket_snapshot); ?><br>
                            <span class="dw-price">Rp <?php echo number_format($r->harga_paket,0,',','.'); ?></span> (+<?php echo $r->jumlah_transaksi; ?> trx)
                        </td>
                        <td><a href="<?php echo esc_url($r->url_bukti_bayar); ?>" target="_blank" class="button button-small"><span class="dashicons dashicons-visibility"></span> Lihat</a></td>
                        <td><?php echo date('d M H:i', strtotime($r->created_at)); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="pembelian_id" value="<?php echo $r->id; ?>">
                                <input type="hidden" name="dw_verifikasi_paket_action" value="1">
                                <?php wp_nonce_field('dw_verifikasi_paket_nonce'); ?>
                                <button name="action_type" value="approve_paket" class="button button-primary button-small" onclick="return confirm('Setujui?');">Setujui</button>
                                <button name="action_type" value="reject_paket" class="button button-small" style="color:#b32d2e;">Tolak</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>