<?php
/**
 * File Name:   page-verifikasi-paket.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-verifikasi-paket.php
 *
 * [BARU] Halaman Admin untuk memverifikasi pembelian paket (Model 3).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler untuk Aksi Admin: Setujui atau Tolak Setoran Paket.
 */
function dw_verifikasi_paket_handler() {
    if (!isset($_POST['dw_verifikasi_paket_action'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_verifikasi_paket_nonce')) wp_die('Security check failed.');

    if (!current_user_can('dw_manage_settings')) { // Hanya Super Admin
        wp_die('Anda tidak punya izin.');
    }

    global $wpdb;
    $pembelian_id = absint($_POST['pembelian_id']);
    $action_type = sanitize_key($_POST['action_type']); // 'approve_paket' or 'reject_paket'
    $redirect_url = admin_url('admin.php?page=dw-verifikasi-paket');

    // 1. Ambil data pembelian
    $pembelian = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dw_pembelian_paket WHERE id = %d AND status = 'pending'", $pembelian_id
    ));
    
    if (!$pembelian) {
        add_settings_error('dw_verifikasi_paket_notices', 'not_found', 'Data pembelian tidak ditemukan atau sudah diproses.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect($redirect_url);
        exit;
    }

    // 2. Ambil data pedagang & desa terkait
    $pedagang = $wpdb->get_row($wpdb->prepare(
        "SELECT id_desa FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $pembelian->id_pedagang
    ));
    if (!$pedagang) {
         add_settings_error('dw_verifikasi_paket_notices', 'pedagang_not_found', 'Pedagang terkait tidak ditemukan.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect($redirect_url);
        exit;
    }
    $id_desa = $pedagang->id_desa;

    // 3. Proses Aksi
    if ($action_type === 'approve_paket') {
        
        // 3a. Update status pembelian
        $wpdb->update(
            $wpdb->prefix . 'dw_pembelian_paket',
            ['status' => 'disetujui', 'processed_at' => current_time('mysql', 1)],
            ['id' => $pembelian_id]
        );

        // 3b. Tambah kuota ke pedagang & Aktifkan akunnya
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}dw_pedagang 
             SET sisa_transaksi = sisa_transaksi + %d, status_akun = 'aktif' 
             WHERE id = %d",
            $pembelian->jumlah_transaksi,
            $pembelian->id_pedagang
        ));

        // 3c. Hitung & Catat Komisi ke Payout Ledger
        $harga_paket = (float) $pembelian->harga_paket;
        $persen_desa = (float) $pembelian->persentase_komisi_desa;
        $komisi_desa = ($harga_paket * $persen_desa) / 100;
        $komisi_platform = $harga_paket - $komisi_desa;
        $current_time = current_time('mysql', 1);

        // Catat Payout untuk Desa (Unpaid)
        if ($komisi_desa > 0 && $id_desa > 0) {
            $wpdb->insert(
                $wpdb->prefix . 'dw_payout_ledger',
                [
                    'order_id' => $pembelian_id, // Gunakan ID pembelian paket sebagai referensi
                    'payable_to_type' => 'desa',
                    'payable_to_id' => $id_desa,
                    'amount' => $komisi_desa,
                    'status' => 'unpaid',
                    'created_at' => $current_time
                ]
            );
        }
        // Catat Payout untuk Platform (Paid)
        if ($komisi_platform > 0) {
            $wpdb->insert(
                $wpdb->prefix . 'dw_payout_ledger',
                [
                    'order_id' => $pembelian_id,
                    'payable_to_type' => 'platform',
                    'payable_to_id' => 0, // 0 = Platform
                    'amount' => $komisi_platform,
                    'status' => 'paid',
                    'created_at' => $current_time,
                    'paid_at' => $current_time
                ]
            );
        }

        dw_log_activity('PAKET_APPROVED', "Admin menyetujui pembelian paket #{$pembelian_id} (sejumlah Rp " . number_format($harga_paket) . ") oleh Pedagang #{$pembelian->id_pedagang}. Kuota +{$pembelian->jumlah_transaksi}. Komisi: Desa=Rp {$komisi_desa}, Platform=Rp {$komisi_platform}.", get_current_user_id());
        add_settings_error('dw_verifikasi_paket_notices', 'paket_approved', 'Pembelian paket disetujui. Kuota pedagang telah ditambahkan.', 'success');

    } elseif ($action_type === 'reject_paket') {
        $catatan = sanitize_textarea_field($_POST['catatan_admin'] ?? 'Bukti transfer tidak valid.');
        
        $wpdb->update(
            $wpdb->prefix . 'dw_pembelian_paket',
            ['status' => 'ditolak', 'processed_at' => current_time('mysql', 1), 'catatan_admin' => $catatan],
            ['id' => $pembelian_id]
        );
        
        dw_log_activity('PAKET_REJECTED', "Admin menolak pembelian paket #{$pembelian_id}. Alasan: {$catatan}", get_current_user_id());
        add_settings_error('dw_verifikasi_paket_notices', 'paket_rejected', 'Pembelian paket ditolak.', 'warning');
    }

    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'dw_verifikasi_paket_handler');


/**
 * Render Halaman Verifikasi Pembelian Paket.
 */
function dw_verifikasi_paket_page_render() {
    global $wpdb;
    
    $pending_purchases = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT pp.*, p.nama_toko 
             FROM {$wpdb->prefix}dw_pembelian_paket pp
             JOIN {$wpdb->prefix}dw_pedagang p ON pp.id_pedagang = p.id
             WHERE pp.status = %s 
             ORDER BY pp.created_at ASC",
            'pending'
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
        <p>Verifikasi setoran pembelian paket kuota dari pedagang. Setelah disetujui, kuota akan otomatis ditambahkan ke akun pedagang.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">Pedagang (Toko)</th>
                    <th style="width: 25%;">Detail Paket</th>
                    <th style="width: 15%;">Bukti Bayar</th>
                    <th style="width: 15%;">Tanggal Pengajuan</th>
                    <th style="width: 25%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pending_purchases)): ?>
                    <tr><td colspan="5">Tidak ada pembelian paket yang menunggu verifikasi.</td></tr>
                <?php else: foreach ($pending_purchases as $item): ?>
                    <tr>
                        <td><strong><?php echo esc_html($item['nama_toko']); ?></strong></td>
                        <td>
                            <?php echo esc_html($item['nama_paket_snapshot']); ?><br>
                            Harga: <strong>Rp <?php echo number_format($item['harga_paket'], 0, ',', '.'); ?></strong><br>
                            Kuota: <?php echo number_format_i18n($item['jumlah_transaksi']); ?> trx
                        </td>
                        <td>
                            <a href="<?php echo esc_url($item['url_bukti_bayar']); ?>" target="_blank" class="button button-small">Lihat Bukti</a>
                        </td>
                        <td><?php echo date('d M Y H:i', strtotime($item['created_at'])); ?></td>
                        <td>
                             <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block; margin-bottom: 5px;">
                                <input type="hidden" name="action" value="dw_verifikasi_paket_action">
                                <input type="hidden" name="pembelian_id" value="<?php echo esc_attr($item['id']); ?>">
                                <input type="hidden" name="action_type" value="approve_paket">
                                <?php wp_nonce_field('dw_verifikasi_paket_nonce'); ?>
                                <button type="submit" class="button button-primary" onclick="return confirm('Setujui pembelian paket ini? Kuota akan ditambahkan ke pedagang.');">Setujui</button>
                            </form>
                             <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block;">
                                <input type="hidden" name="action" value="dw_verifikasi_paket_action">
                                <input type="hidden" name="pembelian_id" value="<?php echo esc_attr($item['id']); ?>">
                                <input type="hidden" name="action_type" value="reject_paket">
                                <?php wp_nonce_field('dw_verifikasi_paket_nonce'); ?>
                                <input type="text" name="catatan_admin" placeholder="Alasan Tolak (opsional)" style="width: 100px;">
                                <button type="submit" class="button button-secondary" onclick="return confirm('Yakin menolak setoran ini?');">Tolak</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
