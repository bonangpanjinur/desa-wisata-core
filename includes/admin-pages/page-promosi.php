<?php
/**
 * File Name:   page-promosi.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-promosi.php
 *
 * Halaman admin untuk mengelola Promosi.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// **DIPINDAHKAN**: Logika handler dipisah dari render untuk praktik terbaik.
function dw_promosi_action_handler() {
    // Pastikan ini adalah request yang benar
    if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], ['approve', 'reject'] ) || ! isset( $_GET['id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_promosi';
    $action     = sanitize_key( $_GET['action'] );
    $item_id    = absint( $_GET['id'] );

    // Verifikasi nonce dan kapabilitas
    if ( $item_id > 0 && wp_verify_nonce( $_GET['_wpnonce'], 'dw_promo_action_' . $item_id ) && current_user_can('dw_manage_promosi') ) {
        $new_status = ( $action === 'approve' ) ? 'aktif' : 'ditolak';
        $wpdb->update( $table_name, [ 'status' => $new_status ], [ 'id' => $item_id ] );

        // Update status unggulan pada produk/wisata terkait (yang merupakan CPT)
        $promo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $item_id ) );
        if ( $promo ) {
            // Target ID sekarang adalah post_id dari CPT
            if ( in_array($promo->tipe, ['produk', 'wisata']) ) {
                update_post_meta( $promo->target_id, '_dw_unggulan', ($new_status === 'aktif' ? 'ya' : 'tidak') );
            }
        }

        // Redirect untuk membersihkan URL
        wp_redirect( remove_query_arg( [ 'action', 'id', '_wpnonce' ], wp_get_referer() ) );
        exit;
    }
}
add_action('admin_init', 'dw_promosi_action_handler');


/**
 * Fungsi untuk merender halaman list promosi.
 */
function dw_admin_promosi_page_handler() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-promosi-list-table.php';
    
    // Tampilkan List Table
    $list_table = new DW_Promosi_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Promosi</h1>
        <p>Setujui atau tolak pengajuan promosi dari penjual atau pengelola desa.</p>
        
        <form id="dw-promosi-filter" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}
