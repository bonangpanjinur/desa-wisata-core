<?php
/**
 * File Name:   page-komisi.php
 * Description: Dashboard Keuangan & Payout dengan UI Premium.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler Mark Paid
function dw_mark_payout_paid_handler() {
    if (!isset($_POST['dw_mark_paid_nonce']) || !wp_verify_nonce($_POST['dw_mark_paid_nonce'], 'dw_mark_paid_action')) return;
    global $wpdb;
    $wpdb->update("{$wpdb->prefix}dw_payout_ledger", ['status'=>'paid', 'paid_at'=>current_time('mysql')], ['payable_to_type'=>'desa', 'payable_to_id'=>absint($_POST['desa_id']), 'status'=>'unpaid']);
    add_settings_error('dw_komisi_notices', 'ok', 'Payout ditandai lunas.', 'success');
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-komisi')); exit;
}
add_action('admin_post_dw_mark_payout_paid', 'dw_mark_payout_paid_handler');

function dw_komisi_page_render() {
    global $wpdb;
    $e = get_transient('settings_errors'); if($e){ settings_errors('dw_komisi_notices'); delete_transient('settings_errors'); }

    // Stats
    $unpaid_rows = $wpdb->get_results("SELECT l.payable_to_id as desa_id, d.nama_desa, SUM(l.amount) as total, COUNT(l.id) as trx_count FROM {$wpdb->prefix}dw_payout_ledger l JOIN {$wpdb->prefix}dw_desa d ON l.payable_to_id=d.id WHERE l.status='unpaid' AND l.payable_to_type='desa' GROUP BY l.payable_to_id");
    $total_debt = array_sum(array_column($unpaid_rows, 'total'));
    $platform_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}dw_payout_ledger WHERE payable_to_type='platform' AND status='paid'") ?: 0;
    $paid_history = $wpdb->get_results("SELECT l.payable_to_id, d.nama_desa, SUM(l.amount) as total, MAX(l.paid_at) as last_paid FROM {$wpdb->prefix}dw_payout_ledger l JOIN {$wpdb->prefix}dw_desa d ON l.payable_to_id=d.id WHERE l.status='paid' AND l.payable_to_type='desa' GROUP BY l.payable_to_id ORDER BY last_paid DESC LIMIT 5");

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Keuangan & Komisi</h1>
        <hr class="wp-header-end">
        
        <style>
            .dw-finance-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin: 25px 0; }
            .dw-finance-card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-left: 5px solid; display: flex; justify-content: space-between; align-items: center; }
            .dw-finance-card.debt { border-color: #ef4444; }
            .dw-finance-card.revenue { border-color: #10b981; }
            
            .dw-finance-info h3 { margin: 0 0 5px; font-size: 14px; color: #64748b; text-transform: uppercase; font-weight: 700; }
            .dw-finance-amount { font-size: 32px; font-weight: 800; line-height: 1; }
            .debt .dw-finance-amount { color: #ef4444; }
            .revenue .dw-finance-amount { color: #10b981; }
            
            .dw-finance-icon { font-size: 40px; opacity: 0.2; }
            
            .dw-section-title { font-size: 18px; font-weight: 600; color: #334155; margin: 30px 0 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
            .dw-badge-count { background: #ef4444; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; vertical-align: middle; }
            
            .dw-clean-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .dw-clean-table th { background: #f8fafc; color: #475569; font-weight: 600; text-align: left; padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
            .dw-clean-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
            .dw-clean-table tr:last-child td { border-bottom: none; }
            .dw-clean-table tr:hover { background: #f8fafc; }
            
            .btn-pay { background: #2271b1; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
            .btn-pay:hover { background: #1d4ed8; }
        </style>

        <div class="dw-finance-grid">
            <div class="dw-finance-card debt">
                <div class="dw-finance-info">
                    <h3>Utang Komisi Desa (Unpaid)</h3>
                    <div class="dw-finance-amount">Rp <?php echo number_format($total_debt, 0, ',', '.'); ?></div>
                </div>
                <div class="dw-finance-icon dashicons dashicons-warning"></div>
            </div>
            <div class="dw-finance-card revenue">
                <div class="dw-finance-info">
                    <h3>Pendapatan Platform</h3>
                    <div class="dw-finance-amount">Rp <?php echo number_format($platform_revenue, 0, ',', '.'); ?></div>
                </div>
                <div class="dw-finance-icon dashicons dashicons-chart-area"></div>
            </div>
        </div>

        <h2 class="dw-section-title">
            <span>Tagihan Payout ke Desa</span>
            <?php if(count($unpaid_rows) > 0): ?><span class="dw-badge-count"><?php echo count($unpaid_rows); ?> Desa</span><?php endif; ?>
        </h2>
        
        <table class="dw-clean-table">
            <thead>
                <tr>
                    <th>Nama Desa</th>
                    <th>Jumlah Transaksi</th>
                    <th>Total Komisi Tertahan</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($unpaid_rows)): ?>
                    <tr><td colspan="4" style="text-align:center; padding: 30px; color: #94a3b8;">Tidak ada tagihan pending. Semua aman!</td></tr>
                <?php else: foreach($unpaid_rows as $row): ?>
                    <tr>
                        <td><strong><?php echo esc_html($row->nama_desa); ?></strong></td>
                        <td><?php echo number_format($row->trx_count); ?> transaksi</td>
                        <td style="color: #ef4444; font-weight: 700; font-size: 16px;">Rp <?php echo number_format($row->total, 0, ',', '.'); ?></td>
                        <td style="text-align: right;">
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <input type="hidden" name="action" value="dw_mark_payout_paid">
                                <input type="hidden" name="desa_id" value="<?php echo $row->desa_id; ?>">
                                <?php wp_nonce_field('dw_mark_paid_action', 'dw_mark_paid_nonce'); ?>
                                <button class="btn-pay" onclick="return confirm('Konfirmasi transfer Rp <?php echo number_format($row->total, 0, ',', '.'); ?> ke <?php echo esc_js($row->nama_desa); ?>?');">
                                    <span class="dashicons dashicons-yes"></span> Tandai Lunas
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <h2 class="dw-section-title" style="margin-top: 40px;">Riwayat Transfer Terakhir</h2>
        <table class="dw-clean-table">
            <thead><tr><th>Nama Desa</th><th style="text-align:right;">Total Dibayarkan</th><th style="text-align:right;">Terakhir Transfer</th></tr></thead>
            <tbody>
                <?php if(empty($paid_history)): ?>
                    <tr><td colspan="3" style="text-align:center; padding: 20px; color: #94a3b8;">Belum ada riwayat.</td></tr>
                <?php else: foreach($paid_history as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->nama_desa); ?></td>
                        <td style="text-align:right; color: #10b981; font-weight: 600;">Rp <?php echo number_format($row->total, 0, ',', '.'); ?></td>
                        <td style="text-align:right; color: #64748b;"><?php echo date('d M Y H:i', strtotime($row->last_paid)); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>