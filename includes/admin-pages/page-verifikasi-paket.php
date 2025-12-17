<?php
/**
 * Halaman Verifikasi Paket Transaksi
 * Menampilkan daftar transaksi pembelian paket yang menunggu verifikasi admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_transaksi = $wpdb->prefix . 'dw_transaksi_paket';
$table_paket = $wpdb->prefix . 'dw_paket_transaksi';
$table_users = $wpdb->prefix . 'users';

// Ambil data transaksi yang statusnya 'pending'
$query = "SELECT t.*, p.nama_paket, p.harga, p.durasi_hari, u.display_name, u.user_email 
          FROM $table_transaksi t 
          JOIN $table_paket p ON t.paket_id = p.id 
          JOIN $table_users u ON t.user_id = u.ID 
          WHERE t.status = 'pending' 
          ORDER BY t.created_at ASC";

$transaksi_pending = $wpdb->get_results($query);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Verifikasi Pembelian Paket</h1>
    <hr class="wp-header-end">

    <div class="dw-admin-container">
        <?php if (empty($transaksi_pending)) : ?>
            <div class="notice notice-info inline">
                <p>Tidak ada permintaan verifikasi paket yang menunggu (Pending).</p>
            </div>
        <?php else : ?>
            <div class="card">
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th>Tanggal Request</th>
                            <th>Pedagang</th>
                            <th>Paket</th>
                            <th>Harga</th>
                            <th>Metode Pembayaran</th>
                            <th>Bukti Transfer</th>
                            <th class="column-primary">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaksi_pending as $row) : ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($row->created_at)); ?></td>
                                <td>
                                    <strong><?php echo esc_html($row->display_name); ?></strong><br>
                                    <span class="description"><?php echo esc_html($row->user_email); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo esc_html($row->nama_paket); ?></span><br>
                                    <small><?php echo intval($row->durasi_hari); ?> Hari</small>
                                </td>
                                <td>Rp <?php echo number_format($row->harga, 0, ',', '.'); ?></td>
                                <td><?php echo esc_html(ucfirst($row->metode_pembayaran)); ?></td>
                                <td>
                                    <?php if (!empty($row->bukti_transfer)) : ?>
                                        <a href="<?php echo esc_url($row->bukti_transfer); ?>" target="_blank" class="button button-secondary button-small">
                                            <span class="dashicons dashicons-visibility" style="margin-top:3px;"></span> Lihat Bukti
                                        </a>
                                    <?php else : ?>
                                        <span class="description">- Tidak ada bukti -</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-primary">
                                    <div class="actions-wrapper" style="display: flex; gap: 5px;">
                                        <!-- Tombol Terima -->
                                        <button type="button" 
                                                class="button button-primary btn-verifikasi-paket" 
                                                data-id="<?php echo esc_attr($row->id); ?>" 
                                                data-action="approve"
                                                data-nonce="<?php echo wp_create_nonce('dw_verify_paket_' . $row->id); ?>">
                                            <span class="dashicons dashicons-yes-alt" style="margin-top: 4px;"></span> Terima
                                        </button>
                                        
                                        <!-- Tombol Tolak -->
                                        <button type="button" 
                                                class="button button-secondary btn-verifikasi-paket" 
                                                data-id="<?php echo esc_attr($row->id); ?>" 
                                                data-action="reject"
                                                data-nonce="<?php echo wp_create_nonce('dw_verify_paket_' . $row->id); ?>">
                                            <span class="dashicons dashicons-dismiss" style="margin-top: 4px;"></span> Tolak
                                        </button>
                                    </div>
                                    <div class="spinner" id="spinner-<?php echo esc_attr($row->id); ?>" style="float:none;"></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .badge {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    .badge-primary { background: #e5f5fa; color: #0085ba; border: 1px solid #0085ba; }
</style>