<?php
/**
 * File Name:   page-logs.php
 * Description: Halaman Log dengan tampilan tabel yang rapi.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function dw_logs_page_render() {
    if ( ! class_exists( 'DW_Logs_List_Table' ) ) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-logs-list-table.php';
    }
    $table = new DW_Logs_List_Table();
    $table->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Log Aktivitas</h1>
        <hr class="wp-header-end">
        
        <div class="card" style="padding:0; margin-top:20px; overflow:hidden;">
            <form method="post">
                <?php $table->display(); ?>
            </form>
        </div>
    </div>
    <?php
}
?>