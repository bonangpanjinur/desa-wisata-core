<?php
/**
 * File Name:   page-verifikasi-paket.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-verifikasi-paket.php
 *
 * [UPDATED] Tampilan verifikasi pembelian paket lebih modern dan rapi.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler (Tetap sama, hanya render yang dipercantik)
function dw_verifikasi_paket_handler() {
    if (!isset($_POST['dw_verifikasi_paket_action'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_verifikasi_paket_nonce')) wp_die('Security check failed.');

    if (!current_user_can('dw_manage_settings')) {
        wp_die('Anda tidak punya izin.');
    }

    global $wpdb;
    $pembelian_id = absint($_POST['pembelian_id']);
    $action_type = sanitize_key($_POST['action_type']);
    $redirect_url = admin_url('admin.php?page=dw-verifikasi-paket');

    $pembelian = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pembelian_paket WHERE id = %d AND status = 'pending'", $pembelian_id));
    if (!$pembelian) {
        add_settings_error('dw_verifikasi_paket_notices', 'not_found', 'Data tidak ditemukan.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30); wp_redirect($redirect_url); exit;
    }

    $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id_desa FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $pembelian->id_pedagang));
    $id_desa = $pedagang ? $pedagang->id_desa : 0;

    if ($action_type === 'approve_paket') {
        $wpdb->update($wpdb->prefix . 'dw_pembelian_paket', ['status' => 'disetujui', 'processed_at' => current_time('mysql', 1)], ['id' => $pembelian_id]);
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}dw_pedagang SET sisa_transaksi = sisa_transaksi + %d, status_akun = 'aktif' WHERE id = %d", $pembelian->jumlah_transaksi, $pembelian->id_pedagang));

        // Catat Komisi
        $harga_paket = (float) $pembelian->harga_paket;
        $komisi_desa = ($harga_paket * (float)$pembelian->persentase_komisi_desa) / 100;
        $komisi_platform = $harga_paket - $komisi_desa;
        $time = current_time('mysql', 1);

        if ($komisi_desa > 0 && $id_desa > 0) {
            $wpdb->insert($wpdb->prefix . 'dw_payout_ledger', ['order_id' => $pembelian_id, 'payable_to_type' => 'desa', 'payable_to_id' => $id_desa, 'amount' => $komisi_desa, 'status' => 'unpaid', 'created_at' => $time]);
        }
        if ($komisi_platform > 0) {
            $wpdb->insert($wpdb->prefix . 'dw_payout_ledger', ['order_id' => $pembelian_id, 'payable_to_type' => 'platform', 'payable_to_id' => 0, 'amount' => $komisi_platform, 'status' => 'paid', 'created_at' => $time, 'paid_at' => $time]);
        }

        dw_log_activity('PAKET_APPROVED', "Admin menyetujui pembelian paket #{$pembelian_id}.", get_current_user_id());
        add_settings_error('dw_verifikasi_paket_notices', 'paket_approved', 'Pembelian disetujui.', 'success');

    } elseif ($action_type === 'reject_paket') {
        $catatan = sanitize_textarea_field($_POST['catatan_admin'] ?? 'Ditolak.');
        $wpdb->update($wpdb->prefix . 'dw_pembelian_paket', ['status' => 'ditolak', 'processed_at' => current_time('mysql', 1), 'catatan_admin' => $catatan], ['id' => $pembelian_id]);
        dw_log_activity('PAKET_REJECTED', "Admin menolak paket #{$pembelian_id}.", get_current_user_id());
        add_settings_error('dw_verifikasi_paket_notices', 'paket_rejected', 'Pembelian ditolak.', 'warning');
    }

    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'dw_verifikasi_paket_handler');


function dw_verifikasi_paket_page_render() {
    global $wpdb;
    
    // Ambil Pending
    $pending_purchases = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT pp.*, p.nama_toko 
             FROM {$wpdb->prefix}dw_pembelian_paket pp
             JOIN {$wpdb->prefix}dw_pedagang p ON pp.id_pedagang = p.id
             WHERE pp.status = %s ORDER BY pp.created_at ASC", 'pending'
        ), ARRAY_A
    );
    
    // Ambil History Terakhir (5 saja)
    $history_purchases = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT pp.*, p.nama_toko 
             FROM {$wpdb->prefix}dw_pembelian_paket pp
             JOIN {$wpdb->prefix}dw_pedagang p ON pp.id_pedagang = p.id
             WHERE pp.status != %s ORDER BY pp.created_at DESC LIMIT 5", 'pending'
        ), ARRAY_A
    );
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Verifikasi Pembelian Paket</h1>
        </div>
        
        <?php
        $errors = get_transient('settings_errors');
        if($errors) { settings_errors('dw_verifikasi_paket_notices'); delete_transient('settings_errors'); }
        ?>

        <!-- Styles -->
        <style>
            .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); overflow:hidden; margin-top:20px; }
            .dw-badge { padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold; text-transform:uppercase; }
            .badge-pending { background:#fef3c7; color:#92400e; }
            .badge-approved { background:#dcfce7; color:#166534; }
            .badge-rejected { background:#fee2e2; color:#991b1b; }
            .dw-thumb-proof { max-width: 40px; max-height: 40px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle; margin-right: 5px; }
        </style>

        <!-- TABEL PENDING -->
        <h2 style="font-size:16px; margin-top:0;">Menunggu Verifikasi</h2>
        <div class="dw-card-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="20%">Toko (Pedagang)</th>
                        <th width="20%">Paket</th>
                        <th width="15%">Bukti Bayar</th>
                        <th width="15%">Tanggal</th>
                        <th width="30%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_purchases)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:20px; color:#aaa;">Tidak ada permintaan baru.</td></tr>
                    <?php else: foreach ($pending_purchases as $item): ?>
                        <tr>
                            <td><strong><?php echo esc_html($item['nama_toko']); ?></strong></td>
                            <td>
                                <?php echo esc_html($item['nama_paket_snapshot']); ?>
                                <div style="color:#555; font-size:12px;">
                                    Rp <?php echo number_format($item['harga_paket'], 0, ',', '.'); ?> 
                                    <span style="color:#aaa;">|</span> 
                                    +<?php echo number_format_i18n($item['jumlah_transaksi']); ?> trx
                                </div>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($item['url_bukti_bayar']); ?>" target="_blank" class="button button-small" title="Lihat Bukti">
                                    <span class="dashicons dashicons-visibility" style="margin-top:2px;"></span> Lihat
                                </a>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($item['created_at'])); ?></td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                        <input type="hidden" name="action" value="dw_verifikasi_paket_action">
                                        <input type="hidden" name="pembelian_id" value="<?php echo esc_attr($item['id']); ?>">
                                        <input type="hidden" name="action_type" value="approve_paket">
                                        <?php wp_nonce_field('dw_verifikasi_paket_nonce'); ?>
                                        <button type="submit" class="button button-primary button-small" onclick="return confirm('Setujui pembelian ini?');">Setujui</button>
                                    </form>
                                    
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:flex; gap:5px;">
                                        <input type="hidden" name="action" value="dw_verifikasi_paket_action">
                                        <input type="hidden" name="pembelian_id" value="<?php echo esc_attr($item['id']); ?>">
                                        <input type="hidden" name="action_type" value="reject_paket">
                                        <?php wp_nonce_field('dw_verifikasi_paket_nonce'); ?>
                                        <input type="text" name="catatan_admin" placeholder="Alasan..." style="width:100px; padding:2px 5px; font-size:12px;">
                                        <button type="submit" class="button button-secondary button-small" onclick="return confirm('Yakin tolak?');">Tolak</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TABEL HISTORY -->
        <h2 style="font-size:16px; margin-top:30px;">Riwayat Terakhir</h2>
        <div class="dw-card-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="20%">Toko</th>
                        <th width="20%">Paket</th>
                        <th width="15%">Tanggal Proses</th>
                        <th width="15%">Status</th>
                        <th width="30%">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history_purchases)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:15px; color:#aaa;">Belum ada riwayat.</td></tr>
                    <?php else: foreach ($history_purchases as $item): 
                        $status_cls = ($item['status'] == 'disetujui') ? 'badge-approved' : 'badge-rejected';
                        $status_label = ($item['status'] == 'disetujui') ? 'Disetujui' : 'Ditolak';
                    ?>
                        <tr>
                            <td><?php echo esc_html($item['nama_toko']); ?></td>
                            <td><?php echo esc_html($item['nama_paket_snapshot']); ?></td>
                            <td><?php echo date('d M Y', strtotime($item['processed_at'])); ?></td>
                            <td><span class="dw-badge <?php echo $status_cls; ?>"><?php echo $status_label; ?></span></td>
                            <td><em style="color:#666;"><?php echo esc_html($item['catatan_admin'] ? $item['catatan_admin'] : '-'); ?></em></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>
    <?php
}
?>