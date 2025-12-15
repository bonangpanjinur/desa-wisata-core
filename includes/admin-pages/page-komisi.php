<?php
/**
 * File Name:   page-komisi.php
 * Description: Dashboard Keuangan & Payout dengan UI Card.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler Mark Paid (disingkat)
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

    // Query Data
    $unpaid = $wpdb->get_results("SELECT l.payable_to_id as desa_id, d.nama_desa, SUM(l.amount) as total FROM {$wpdb->prefix}dw_payout_ledger l JOIN {$wpdb->prefix}dw_desa d ON l.payable_to_id=d.id WHERE l.status='unpaid' AND l.payable_to_type='desa' GROUP BY l.payable_to_id");
    $total_unpaid = array_sum(array_column($unpaid, 'total'));
    $total_income = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}dw_payout_ledger WHERE payable_to_type='platform' AND status='paid'") ?: 0;
    ?>
    <div class="wrap dw-wrap">
        <h1>Keuangan & Komisi</h1>
        
        <style>
            .dw-stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom:30px; margin-top:20px; }
            .dw-stat-card { background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; box-shadow:0 1px 2px rgba(0,0,0,0.05); display:flex; align-items:center; }
            .dw-icon-box { width:50px; height:50px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:24px; margin-right:15px; }
            .stat-unpaid .dw-icon-box { background:#fee2e2; color:#b91c1c; }
            .stat-income .dw-icon-box { background:#dcfce7; color:#15803d; }
            .dw-stat-num { font-size:24px; font-weight:800; line-height:1.2; }
            .dw-stat-label { font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase; }
            .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; overflow:hidden; }
        </style>

        <div class="dw-stats-grid">
            <div class="dw-stat-card stat-unpaid">
                <div class="dw-icon-box"><span class="dashicons dashicons-warning"></span></div>
                <div>
                    <div class="dw-stat-label">Hutang ke Desa</div>
                    <div class="dw-stat-num" style="color:#b91c1c;">Rp <?php echo number_format($total_unpaid,0,',','.'); ?></div>
                </div>
            </div>
            <div class="dw-stat-card stat-income">
                <div class="dw-icon-box"><span class="dashicons dashicons-chart-line"></span></div>
                <div>
                    <div class="dw-stat-label">Pendapatan Platform</div>
                    <div class="dw-stat-num" style="color:#15803d;">Rp <?php echo number_format($total_income,0,',','.'); ?></div>
                </div>
            </div>
        </div>

        <h3>Tagihan Payout ke Desa</h3>
        <div class="dw-card-table">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Nama Desa</th><th style="text-align:right;">Nominal (Rp)</th><th style="text-align:right;">Aksi</th></tr></thead>
                <tbody>
                    <?php if(empty($unpaid)): ?><tr><td colspan="3" style="text-align:center; padding:20px;">Semua lunas!</td></tr><?php endif; ?>
                    <?php foreach($unpaid as $row): ?>
                    <tr>
                        <td><strong><?php echo esc_html($row->nama_desa); ?></strong></td>
                        <td style="text-align:right; font-weight:bold; color:#b91c1c;"><?php echo number_format($row->total,0,',','.'); ?></td>
                        <td style="text-align:right;">
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <input type="hidden" name="action" value="dw_mark_payout_paid">
                                <input type="hidden" name="desa_id" value="<?php echo $row->desa_id; ?>">
                                <?php wp_nonce_field('dw_mark_paid_action', 'dw_mark_paid_nonce'); ?>
                                <button class="button button-primary button-small" onclick="return confirm('Sudah transfer?');">Tandai Lunas</button>
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