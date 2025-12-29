<?php
/**
 * File Name:   includes/admin-pages/page-komisi.php
 * Description: Dashboard Keuangan & Payout (Desa & Verifikator).
 * UPDATE v3.9: Menampilkan tab terpisah untuk Hutang ke Desa & Hutang ke Verifikator.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_komisi_page_render() {
    global $wpdb;

    // --- AMBIL DATA ---
    // 1. Unpaid Desa
    $unpaid_desa = $wpdb->get_results("
        SELECT l.payable_to_id, d.nama_desa as nama_penerima, SUM(l.amount) as total, COUNT(l.id) as trx_count 
        FROM {$wpdb->prefix}dw_payout_ledger l 
        JOIN {$wpdb->prefix}dw_desa d ON l.payable_to_id = d.id 
        WHERE l.status='unpaid' AND l.payable_to_type='desa' 
        GROUP BY l.payable_to_id
    ");

    // 2. Unpaid Verifikator
    $unpaid_verif = $wpdb->get_results("
        SELECT l.payable_to_id, v.nama_lengkap as nama_penerima, SUM(l.amount) as total, COUNT(l.id) as trx_count 
        FROM {$wpdb->prefix}dw_payout_ledger l 
        JOIN {$wpdb->prefix}dw_verifikator v ON l.payable_to_id = v.id 
        WHERE l.status='unpaid' AND l.payable_to_type='verifikator' 
        GROUP BY l.payable_to_id
    ");

    // 3. Platform Revenue
    $platform_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}dw_payout_ledger WHERE payable_to_type='platform' AND status='paid'") ?: 0;
    
    // 4. Riwayat Transfer Terakhir
    $paid_history = $wpdb->get_results("
        SELECT l.payable_to_id, l.payable_to_type, SUM(l.amount) as total, MAX(l.paid_at) as last_paid,
        CASE 
            WHEN l.payable_to_type = 'desa' THEN (SELECT nama_desa FROM {$wpdb->prefix}dw_desa WHERE id = l.payable_to_id)
            WHEN l.payable_to_type = 'verifikator' THEN (SELECT nama_lengkap FROM {$wpdb->prefix}dw_verifikator WHERE id = l.payable_to_id)
            ELSE 'Unknown'
        END as nama_penerima
        FROM {$wpdb->prefix}dw_payout_ledger l 
        WHERE l.status='paid' AND l.payable_to_type IN ('desa', 'verifikator')
        GROUP BY l.payable_to_id, l.payable_to_type
        ORDER BY last_paid DESC LIMIT 10
    ");

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'desa';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Laporan Komisi & Payout</h1>
        
        <div class="dw-stats-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
            <div class="dw-stat-card" style="background:#fff; padding:20px; border-left:4px solid #d63638; box-shadow:0 1px 2px rgba(0,0,0,.1);">
                <h3 style="margin:0; font-size:12px; color:#666;">HUTANG KE DESA</h3>
                <p style="font-size:24px; font-weight:bold; margin:5px 0; color:#d63638;">
                    Rp <?php echo number_format(array_sum(array_column($unpaid_desa, 'total')), 0, ',', '.'); ?>
                </p>
            </div>
            <div class="dw-stat-card" style="background:#fff; padding:20px; border-left:4px solid #dba617; box-shadow:0 1px 2px rgba(0,0,0,.1);">
                <h3 style="margin:0; font-size:12px; color:#666;">HUTANG KE VERIFIKATOR</h3>
                <p style="font-size:24px; font-weight:bold; margin:5px 0; color:#dba617;">
                    Rp <?php echo number_format(array_sum(array_column($unpaid_verif, 'total')), 0, ',', '.'); ?>
                </p>
            </div>
            <div class="dw-stat-card" style="background:#fff; padding:20px; border-left:4px solid #00a32a; box-shadow:0 1px 2px rgba(0,0,0,.1);">
                <h3 style="margin:0; font-size:12px; color:#666;">PENDAPATAN PLATFORM (NET)</h3>
                <p style="font-size:24px; font-weight:bold; margin:5px 0; color:#00a32a;">
                    Rp <?php echo number_format($platform_revenue, 0, ',', '.'); ?>
                </p>
            </div>
        </div>

        <nav class="nav-tab-wrapper">
            <a href="?page=dw-komisi&tab=desa" class="nav-tab <?php echo $active_tab == 'desa' ? 'nav-tab-active' : ''; ?>">Komisi Desa</a>
            <a href="?page=dw-komisi&tab=verifikator" class="nav-tab <?php echo $active_tab == 'verifikator' ? 'nav-tab-active' : ''; ?>">Komisi Verifikator</a>
            <a href="?page=dw-komisi&tab=riwayat" class="nav-tab <?php echo $active_tab == 'riwayat' ? 'nav-tab-active' : ''; ?>">Riwayat Transfer</a>
        </nav>

        <div class="dw-tab-content" style="margin-top: 20px; background: #fff; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.1);">
            
            <?php if ($active_tab == 'desa'): ?>
                <h3>Tagihan Komisi Desa (Pending Transfer)</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr><th>Nama Desa</th><th>Jumlah Transaksi</th><th>Total Komisi</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($unpaid_desa)): ?>
                            <tr><td colspan="4">Tidak ada tagihan pending.</td></tr>
                        <?php else: foreach($unpaid_desa as $row): ?>
                            <tr>
                                <td><strong><?php echo esc_html($row->nama_penerima); ?></strong></td>
                                <td><?php echo $row->trx_count; ?></td>
                                <td style="color:#d63638; font-weight:bold;">Rp <?php echo number_format($row->total, 0, ',', '.'); ?></td>
                                <td>
                                    <button class="button button-small btn-pay-modal" 
                                        data-type="desa"
                                        data-id="<?php echo $row->payable_to_id; ?>" 
                                        data-name="<?php echo esc_attr($row->nama_penerima); ?>"
                                        data-amount="<?php echo number_format($row->total, 0, ',', '.'); ?>">
                                        <span class="dashicons dashicons-yes"></span> Tandai Lunas
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <?php elseif ($active_tab == 'verifikator'): ?>
                <h3>Tagihan Komisi Verifikator (Pending Transfer)</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr><th>Nama Verifikator</th><th>Jumlah Transaksi</th><th>Total Komisi</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($unpaid_verif)): ?>
                            <tr><td colspan="4">Tidak ada tagihan pending.</td></tr>
                        <?php else: foreach($unpaid_verif as $row): ?>
                            <tr>
                                <td><strong><?php echo esc_html($row->nama_penerima); ?></strong></td>
                                <td><?php echo $row->trx_count; ?></td>
                                <td style="color:#dba617; font-weight:bold;">Rp <?php echo number_format($row->total, 0, ',', '.'); ?></td>
                                <td>
                                    <button class="button button-small btn-pay-modal" 
                                        data-type="verifikator"
                                        data-id="<?php echo $row->payable_to_id; ?>" 
                                        data-name="<?php echo esc_attr($row->nama_penerima); ?>"
                                        data-amount="<?php echo number_format($row->total, 0, ',', '.'); ?>">
                                        <span class="dashicons dashicons-yes"></span> Tandai Lunas
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <?php elseif ($active_tab == 'riwayat'): ?>
                <h3>10 Transfer Terakhir</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr><th>Penerima</th><th>Tipe</th><th>Total Dibayarkan</th><th>Waktu</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($paid_history)): ?>
                            <tr><td colspan="4">Belum ada riwayat transfer.</td></tr>
                        <?php else: foreach($paid_history as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->nama_penerima); ?></td>
                                <td><span class="dw-badge <?php echo $row->payable_to_type == 'desa' ? 'dw-badge-primary' : 'dw-badge-warning'; ?>"><?php echo ucfirst($row->payable_to_type); ?></span></td>
                                <td style="color: #10b981; font-weight: 600;">Rp <?php echo number_format($row->total, 0, ',', '.'); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($row->last_paid)); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL CONFIRM (Simple JS) -->
    <script>
    jQuery(document).ready(function($){
        $('.btn-pay-modal').click(function(e){
            e.preventDefault();
            var name = $(this).data('name');
            var amount = $(this).data('amount');
            var id = $(this).data('id');
            var type = $(this).data('type');
            
            if(confirm('Konfirmasi Transfer Manual\\n\\nKepada: ' + name + '\\nJumlah: Rp ' + amount + '\\n\\nPastikan Anda sudah mentransfer uang secara nyata. Aksi ini akan menghapus hutang di sistem.')) {
                // Trigger AJAX Payout (Menggunakan handler yang sudah ada atau buat baru)
                // Disini kita gunakan simple redirect logic dulu untuk MVP, idealnya pakai AJAX Handler dw_process_payout
                // Kita asumsikan ada ajax handler di includes/ajax-handlers.php bernama 'dw_process_payout'
                
                $.post(ajaxurl, {
                    action: 'dw_process_payout',
                    payable_id: id,
                    payable_type: type,
                    security: '<?php echo wp_create_nonce("dw_payout_nonce"); ?>' // Pastikan nonce ini ada di backend
                }, function(res) {
                    if(res.success) {
                        alert('Berhasil! Data telah diperbarui.');
                        location.reload();
                    } else {
                        alert('Gagal: ' + (res.data || 'Error'));
                    }
                });
            }
        });
    });
    </script>
    <?php
}