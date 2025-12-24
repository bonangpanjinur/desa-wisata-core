<?php
/**
 * File Name:   includes/admin-pages/page-verifikasi-paket.php
 * Description: Halaman Admin untuk Verifikasi Pembelian Paket, Update Kuota, & Bagi Hasil ke Desa.
 */

if (!defined('ABSPATH')) exit;

function dw_verifikasi_paket_page_render() {
    global $wpdb;
    $table_pembelian = $wpdb->prefix . 'dw_pembelian_paket';
    $table_pedagang  = $wpdb->prefix . 'dw_pedagang';
    $table_desa      = $wpdb->prefix . 'dw_desa';
    $table_users     = $wpdb->users;
    $table_ledger    = $wpdb->prefix . 'dw_payout_ledger'; // Tabel Riwayat Saldo

    $message = '';
    $message_type = '';

    // --- LOGIC: PROCESS ACTION (APPROVE / REJECT) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_paket'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_verify_paket')) {
            echo '<div class="notice notice-error"><p>Keamanan tidak valid (Security Check Failed).</p></div>'; return;
        }

        $pembelian_id = intval($_POST['pembelian_id']);
        $action       = sanitize_text_field($_POST['action_paket']); // 'approve' or 'reject'

        // Ambil data pembelian
        $pembelian = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pembelian WHERE id = %d", $pembelian_id));

        if (!$pembelian || $pembelian->status !== 'pending') {
            $message = "Data tidak ditemukan atau sudah diproses sebelumnya.";
            $message_type = "warning";
        } else {
            if ($action === 'approve') {
                // 1. Update Status Pembelian jadi 'disetujui'
                $wpdb->update(
                    $table_pembelian, 
                    ['status' => 'disetujui', 'processed_at' => current_time('mysql')], 
                    ['id' => $pembelian_id]
                );

                // 2. Update Kuota Pedagang (Tambah sisa transaksi)
                $current_quota = $wpdb->get_var($wpdb->prepare("SELECT sisa_transaksi FROM $table_pedagang WHERE id = %d", $pembelian->id_pedagang));
                $new_quota     = intval($current_quota) + intval($pembelian->jumlah_transaksi);
                
                // Pastikan status akun aktif jika sebelumnya nonaktif/habis kuota
                $wpdb->update(
                    $table_pedagang,
                    [
                        'sisa_transaksi' => $new_quota,
                        'status_akun'    => 'aktif' 
                    ],
                    ['id' => $pembelian->id_pedagang]
                );

                // 3. HITUNG KOMISI & UPDATE SALDO DESA
                $id_desa = $wpdb->get_var($wpdb->prepare("SELECT id_desa FROM $table_pedagang WHERE id = %d", $pembelian->id_pedagang));

                if ($id_desa) {
                    // Rumus: Harga Paket * (Persentase / 100)
                    $harga_paket = floatval($pembelian->harga_paket);
                    $persentase  = floatval($pembelian->persentase_komisi_desa);
                    
                    $komisi_rupiah = 0;
                    if ($harga_paket > 0 && $persentase > 0) {
                        $komisi_rupiah = $harga_paket * ($persentase / 100);
                    }

                    if ($komisi_rupiah > 0) {
                        // A. Tambahkan ke Saldo Utama Desa
                        $sql_update_desa = "UPDATE $table_desa SET total_pendapatan = total_pendapatan + %f WHERE id = %d";
                        $wpdb->query($wpdb->prepare($sql_update_desa, $komisi_rupiah, $id_desa));
                        
                        // B. Catat di Ledger (Audit Trail) - WAJIB agar data akuntansi valid
                        $wpdb->insert(
                            $table_ledger,
                            [
                                'order_id'        => $pembelian_id, // ID Pembelian Paket sebagai referensi
                                'payable_to_type' => 'desa',
                                'payable_to_id'   => $id_desa,
                                'amount'          => $komisi_rupiah,
                                'status'          => 'unpaid', // 'unpaid' artinya masuk dompet tapi belum dicairkan ke bank
                                'created_at'      => current_time('mysql')
                            ]
                        );

                        $message = "Paket disetujui. Kuota Pedagang bertambah " . $pembelian->jumlah_transaksi . ". Desa mendapatkan komisi Rp " . number_format($komisi_rupiah, 0, ',', '.') . " (Tercatat di Ledger).";
                    } else {
                        $message = "Paket disetujui. Kuota bertambah, namun Desa tidak mendapat komisi (0%).";
                    }
                } else {
                    $message = "Paket disetujui. Kuota bertambah. (Pedagang Independent, tidak ada bagi hasil).";
                }

                $message_type = "success";

            } elseif ($action === 'reject') {
                // Proses Penolakan
                $wpdb->update(
                    $table_pembelian, 
                    ['status' => 'ditolak', 'processed_at' => current_time('mysql')], 
                    ['id' => $pembelian_id]
                );
                $message = "Permintaan pembelian paket telah ditolak.";
                $message_type = "error";
            }
        }
    }

    // --- VIEW: LIST DATA ---
    $pagenum = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $limit = 10;
    $offset = ($pagenum - 1) * $limit;
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

    $where_status = ($tab === 'pending') ? "status = 'pending'" : "status IN ('disetujui', 'ditolak')";

    $sql = "SELECT p.*, d.nama_toko, u.display_name as nama_pemilik, desa.nama_desa 
            FROM $table_pembelian p
            LEFT JOIN $table_pedagang d ON p.id_pedagang = d.id
            LEFT JOIN $table_users u ON d.id_user = u.ID
            LEFT JOIN $table_desa desa ON d.id_desa = desa.id
            WHERE p.$where_status
            ORDER BY p.created_at DESC LIMIT %d OFFSET %d";

    $results = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_pembelian WHERE $where_status");
    $total_pages = ceil($total_items / $limit);

    ?>
    <div class="wrap dw-wrapper">
        <h1 class="wp-heading-inline">Verifikasi Paket Transaksi</h1>
        <hr class="wp-header-end">

        <?php if (!empty($message)): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="dw-tabs-nav">
            <a href="?page=dw-verifikasi-paket&tab=pending" class="dw-tab-link <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                Perlu Persetujuan
            </a>
            <a href="?page=dw-verifikasi-paket&tab=history" class="dw-tab-link <?php echo $tab === 'history' ? 'active' : ''; ?>">
                Riwayat Transaksi
            </a>
        </div>

        <div class="dw-card-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="120">Tanggal</th>
                        <th>Pedagang & Desa</th>
                        <th>Paket</th>
                        <th>Harga & Komisi</th>
                        <th>Bukti Bayar</th>
                        <th>Status</th>
                        <?php if ($tab === 'pending'): ?>
                        <th width="150" style="text-align:right;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): foreach ($results as $row): 
                        $komisi_rp = $row->harga_paket * ($row->persentase_komisi_desa / 100);    
                    ?>
                    <tr>
                        <td><?php echo date('d M Y H:i', strtotime($row->created_at)); ?></td>
                        <td>
                            <strong><?php echo esc_html($row->nama_toko); ?></strong><br>
                            <small class="text-muted"><?php echo esc_html($row->nama_pemilik); ?></small><br>
                            <?php if($row->nama_desa): ?>
                                <span class="dw-badge-desa"><span class="dashicons dashicons-location"></span> <?php echo esc_html($row->nama_desa); ?></span>
                            <?php else: ?>
                                <span class="dw-badge-independent">Independent</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="dw-packet-name"><?php echo esc_html($row->nama_paket_snapshot); ?></div>
                            <small>+<?php echo $row->jumlah_transaksi; ?> Transaksi</small>
                        </td>
                        <td>
                            <div class="dw-price">Rp <?php echo number_format($row->harga_paket, 0, ',', '.'); ?></div>
                            <?php if($row->nama_desa): ?>
                                <div class="dw-commission">
                                    <span class="dashicons dashicons-money-alt"></span> Desa: Rp <?php echo number_format($komisi_rp, 0, ',', '.'); ?> (<?php echo $row->persentase_komisi_desa; ?>%)
                                </div>
                            <?php else: ?>
                                <small class="text-muted">- Tidak ada bagi hasil -</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row->url_bukti_bayar): ?>
                                <a href="<?php echo esc_url($row->url_bukti_bayar); ?>" target="_blank" class="button button-small"><span class="dashicons dashicons-media-default"></span> Bukti</a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $status_labels = [
                                'pending' => '<span class="dw-badge dw-warning">Menunggu</span>',
                                'disetujui' => '<span class="dw-badge dw-success">Disetujui</span>',
                                'ditolak' => '<span class="dw-badge dw-error">Ditolak</span>',
                            ];
                            echo isset($status_labels[$row->status]) ? $status_labels[$row->status] : $row->status;
                            ?>
                        </td>
                        <?php if ($tab === 'pending'): ?>
                        <td style="text-align:right;">
                            <form method="post" style="display:inline-flex; gap:5px;">
                                <?php wp_nonce_field('dw_verify_paket'); ?>
                                <input type="hidden" name="pembelian_id" value="<?php echo $row->id; ?>">
                                
                                <button type="submit" name="action_paket" value="reject" class="button" onclick="return confirm('Tolak pembayaran ini?');" title="Tolak">
                                    <span class="dashicons dashicons-no-alt" style="color:#d63638; margin-top:3px;"></span>
                                </button>
                                <button type="submit" name="action_paket" value="approve" class="button button-primary" onclick="return confirm('Setujui pembayaran? Kuota pedagang & Saldo desa akan bertambah otomatis.');" title="Setujui">
                                    Setujui
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" style="text-align:center; padding: 20px;">Belum ada data transaksi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'total' => $total_pages,
                        'current' => $pagenum
                    ]); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Styles -->
        <style>
            .dw-wrapper { margin-top: 20px; }
            .dw-tabs-nav { margin-bottom: 20px; border-bottom: 1px solid #c3c4c7; }
            .dw-tab-link { display: inline-block; padding: 10px 15px; text-decoration: none; color: #50575e; font-weight: 600; border: 1px solid transparent; border-bottom: none; margin-bottom: -1px; border-radius: 4px 4px 0 0; }
            .dw-tab-link:hover { background: #f6f7f7; color: #2271b1; }
            .dw-tab-link.active { background: #fff; border-color: #c3c4c7; border-bottom-color: #fff; color: #1d2327; }
            
            .dw-card-table { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); overflow: hidden; border-radius: 4px; }
            .wp-list-table { border: none; }
            .wp-list-table th { font-weight: 600; }
            .wp-list-table td { vertical-align: middle; }

            .dw-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
            .dw-warning { background: #f0b849; color: #fff; }
            .dw-success { background: #46b450; color: #fff; }
            .dw-error { background: #d63638; color: #fff; }
            
            .dw-badge-desa { display: inline-block; background: #e5f5fa; color: #0073aa; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-top: 3px; font-weight: 500; }
            .dw-badge-independent { display: inline-block; background: #f0f0f1; color: #646970; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-top: 3px; font-style: italic; }
            
            .dw-price { font-weight: 600; color: #1d2327; }
            .dw-commission { font-size: 11px; color: #46b450; margin-top: 2px; }
            .dw-packet-name { font-weight: 600; color: #2271b1; }
            .text-muted { color: #8c8f94; font-size: 12px; }
        </style>
    </div>
    <?php
}