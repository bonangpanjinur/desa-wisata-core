<?php
/**
 * File Name:   page-logs.php
 * Description: Monitoring Aktivitas Sistem dengan UI Modern.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin-ui-components.php';

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