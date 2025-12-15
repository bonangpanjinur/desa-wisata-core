<?php
/**
 * File Name:   page-promosi.php
 * Description: Manajemen Iklan Berbayar dengan Dashboard Statistik.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler Aksi (Approve/Reject)
function dw_promosi_action_handler() {
    if (!isset($_GET['action']) || !isset($_GET['id']) || !isset($_GET['_wpnonce'])) return;
    $act = $_GET['action'];
    if (!in_array($act, ['approve','reject'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_promo_action_'.$_GET['id'])) wp_die('Security fail');
    
    global $wpdb;
    $status = ($act === 'approve') ? 'aktif' : 'ditolak';
    
    // Update status
    $wpdb->update("{$wpdb->prefix}dw_promosi", ['status'=>$status], ['id'=>absint($_GET['id'])]);
    
    // Jika approve, set tanggal mulai & selesai otomatis
    if ($status === 'aktif') {
        $promo = $wpdb->get_row("SELECT durasi_hari FROM {$wpdb->prefix}dw_promosi WHERE id=".absint($_GET['id']));
        $start = current_time('mysql');
        $end = date('Y-m-d H:i:s', strtotime("+$promo->durasi_hari days", strtotime($start)));
        $wpdb->update("{$wpdb->prefix}dw_promosi", ['mulai_tanggal'=>$start, 'selesai_tanggal'=>$end], ['id'=>absint($_GET['id'])]);
    }

    wp_redirect(remove_query_arg(['action','id','_wpnonce'])); exit;
}
add_action('admin_init', 'dw_promosi_action_handler');

function dw_admin_promosi_page_handler() {
    if(!class_exists('DW_Promosi_List_Table')) require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-promosi-list-table.php';
    
    // Query Stats
    global $wpdb;
    $pending = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dw_promosi WHERE status='pending'");
    $active = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dw_promosi WHERE status='aktif'");
    $revenue = $wpdb->get_var("SELECT SUM(biaya) FROM {$wpdb->prefix}dw_promosi WHERE status='aktif' OR status='selesai'") ?: 0;

    $table = new DW_Promosi_List_Table();
    $table->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Iklan Promosi</h1>
        <hr class="wp-header-end">
        
        <style>
            .dw-promo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
            .dw-stat-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .dw-stat-icon { font-size: 32px; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; background: #f0f0f1; border-radius: 50%; margin-right: 20px; }
            .dw-stat-text h3 { margin: 0; font-size: 14px; color: #64748b; text-transform: uppercase; }
            .dw-stat-text .num { font-size: 28px; font-weight: 800; color: #1e293b; line-height: 1.2; }
            
            .card-pending { border-bottom: 4px solid #f59e0b; } .icon-pending { color: #f59e0b; background: #fffbeb; }
            .card-active { border-bottom: 4px solid #10b981; } .icon-active { color: #10b981; background: #ecfdf5; }
            .card-revenue { border-bottom: 4px solid #3b82f6; } .icon-revenue { color: #3b82f6; background: #eff6ff; }
            
            .dw-table-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; padding: 0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        </style>

        <div class="dw-promo-grid">
            <div class="dw-stat-card card-pending">
                <div class="dw-stat-icon icon-pending"><span class="dashicons dashicons-clock"></span></div>
                <div class="dw-stat-text"><h3>Menunggu Approve</h3><div class="num"><?php echo number_format($pending); ?></div></div>
            </div>
            <div class="dw-stat-card card-active">
                <div class="dw-stat-icon icon-active"><span class="dashicons dashicons-megaphone"></span></div>
                <div class="dw-stat-text"><h3>Iklan Aktif</h3><div class="num"><?php echo number_format($active); ?></div></div>
            </div>
            <div class="dw-stat-card card-revenue">
                <div class="dw-stat-icon icon-revenue"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="dw-stat-text"><h3>Total Pendapatan</h3><div class="num">Rp <?php echo number_format($revenue, 0, ',', '.'); ?></div></div>
            </div>
        </div>
        
        <div class="dw-table-card">
            <form method="get">
                <input type="hidden" name="page" value="dw-promosi">
                <?php $table->display(); ?>
            </form>
        </div>
    </div>
    <?php
}
?>