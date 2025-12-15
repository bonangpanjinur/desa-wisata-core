<?php
/**
 * File Name:   page-logs.php
 * Description: Monitoring Aktivitas Sistem dengan UI Modern.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function dw_logs_page_render() {
    if ( ! class_exists( 'DW_Logs_List_Table' ) ) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-logs-list-table.php';
    }
    
    // Stats Dummy (bisa diquery real count nanti)
    global $wpdb;
    $log_table = $wpdb->prefix . 'dw_logs';
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $log_table");
    $today_logs = $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE DATE(created_at) = CURDATE()");

    $table = new DW_Logs_List_Table();
    $table->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Log Aktivitas & Audit Trail</h1>
        <hr class="wp-header-end">
        
        <style>
            .dw-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
            .dw-stat-box { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #c3c4c7; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; align-items: center; }
            .dw-stat-icon { width: 48px; height: 48px; border-radius: 50%; background: #e0f2f1; color: #00695c; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 15px; }
            .dw-stat-info h4 { margin: 0; color: #64748b; font-size: 13px; text-transform: uppercase; }
            .dw-stat-info span { font-size: 24px; font-weight: 700; color: #1e293b; }
            
            .dw-table-container { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); padding: 0; overflow: hidden; margin-top: 20px; }
            .tablenav.top { padding: 10px 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; margin: 0; }
        </style>

        <div class="dw-stats-row">
            <div class="dw-stat-box">
                <div class="dw-stat-icon"><span class="dashicons dashicons-database"></span></div>
                <div class="dw-stat-info"><h4>Total Log Tersimpan</h4><span><?php echo number_format($total_logs); ?></span></div>
            </div>
            <div class="dw-stat-box">
                <div class="dw-stat-icon" style="background:#fff7ed; color:#c2410c;"><span class="dashicons dashicons-clock"></span></div>
                <div class="dw-stat-info"><h4>Aktivitas Hari Ini</h4><span><?php echo number_format($today_logs); ?></span></div>
            </div>
            <div class="dw-stat-box">
                <div class="dw-stat-icon" style="background:#eff6ff; color:#1d4ed8;"><span class="dashicons dashicons-shield"></span></div>
                <div class="dw-stat-info"><h4>Status Sistem</h4><span style="font-size:16px; color:#16a34a;">Normal</span></div>
            </div>
        </div>

        <div class="dw-table-container">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <!-- Placeholder untuk filter log level jika diperlukan -->
                </div>
                <div class="alignright">
                    <form method="post">
                        <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($_REQUEST['s'] ?? ''); ?>" placeholder="Cari Log ID / User...">
                        <input type="submit" id="search-submit" class="button" value="Cari Log">
                    </form>
                </div>
                <br class="clear">
            </div>
            
            <form method="post">
                <?php $table->display(); ?>
            </form>
        </div>
    </div>
    <?php
}
?>