<?php
/**
 * File Name:   page-promosi.php
 * Description: Menampilkan List Table Promosi dengan wrapper yang rapi.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler (Dipisah dari render)
function dw_promosi_action_handler() {
    if (!isset($_GET['action']) || !isset($_GET['id']) || !isset($_GET['_wpnonce'])) return;
    $act = $_GET['action'];
    if (!in_array($act, ['approve','reject'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_promo_action_'.$_GET['id'])) wp_die('Security fail');
    
    global $wpdb;
    $status = ($act === 'approve') ? 'aktif' : 'ditolak';
    $wpdb->update("{$wpdb->prefix}dw_promosi", ['status'=>$status], ['id'=>absint($_GET['id'])]);
    wp_redirect(remove_query_arg(['action','id','_wpnonce'])); exit;
}
add_action('admin_init', 'dw_promosi_action_handler');

function dw_admin_promosi_page_handler() {
    if(!class_exists('DW_Promosi_List_Table')) require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-promosi-list-table.php';
    $table = new DW_Promosi_List_Table();
    $table->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Promosi</h1>
        <hr class="wp-header-end">
        
        <style>
            .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); padding:10px; margin-top:20px; }
        </style>
        
        <div class="dw-card-table">
            <form method="get">
                <input type="hidden" name="page" value="dw-promosi">
                <?php $table->display(); ?>
            </form>
        </div>
    </div>
    <?php
}
?>