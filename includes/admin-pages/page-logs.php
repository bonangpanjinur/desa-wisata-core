<?php
/**
 * File Name:   page-logs.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-logs.php
 *
 * Halaman admin untuk menampilkan Logs.
 *
 * @package DesaWisataCore
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Merender halaman Logs.
 */
function dw_logs_page_render() {
    // --- PERBAIKAN: Memuat class List Table jika belum ada ---
    if ( ! class_exists( 'DW_Logs_List_Table' ) ) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-logs-list-table.php';
    }

    $logsListTable = new DW_Logs_List_Table();
    $logsListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Log Aktivitas</h1>
        </div>
        <p>Halaman ini menampilkan log aktivitas penting dalam sistem.</p>
        <form method="post">
            <?php $logsListTable->display(); ?>
        </form>
    </div>
    <?php
}
?>