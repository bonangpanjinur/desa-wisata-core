<?php
/**
 * File Name:   class-dw-desa-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-desa-list-table.php
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DW_Desa_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'Desa', 'plural' => 'Desa', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'nama_desa'   => 'Nama Desa',
            'kabupaten'   => 'Kabupaten/Kota', // Tambahan kolom
            'status'      => 'Status',
            'created_at'  => 'Tanggal Dibuat'
        ];
    }
    
    protected function get_bulk_actions() {
        return ['delete' => 'Hapus'];
    }

    public function process_bulk_action() {
        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['ids']) ? array_map('intval', $_REQUEST['ids']) : [];
            if (count($ids) > 0) {
                global $wpdb;
                foreach ($ids as $id) {
                    if (function_exists('dw_handle_desa_deletion')) dw_handle_desa_deletion($id);
                    $wpdb->delete($wpdb->prefix . 'dw_desa', ['id' => $id]);
                }
            }
        }
    }

    protected function get_sortable_columns() {
        return [
            'nama_desa'  => ['nama_desa', false],
            'created_at' => ['created_at', true]
        ];
    }

    protected function column_default( $item, $column_name ) {
        switch($column_name) {
            case 'status':
                return ucfirst(esc_html($item['status']));
            case 'created_at':
                return date('d M Y', strtotime($item['created_at']));
            default:
                return esc_html($item[ $column_name ]);
        }
    }

    protected function column_nama_desa( $item ) {
        $page = $_REQUEST['page'] ?? 'dw-desa';
        $nonce = wp_create_nonce('dw_desa_action');
        
        $actions = [
            'edit'   => sprintf( '<a href="?page=%s&action=edit&id=%s">Edit</a>', $page, $item['id'] ),
            'delete' => sprintf( 
                '<a href="#" onclick="if(confirm(\'Hapus desa ini beserta data pedagang terkait?\')) { var f = document.createElement(\'form\'); f.method=\'POST\'; f.innerHTML=\'<input type=hidden name=action_desa value=delete><input type=hidden name=desa_id value=%s><input type=hidden name=_wpnonce value=%s>\'; document.body.appendChild(f); f.submit(); } return false;" style="color:red;">Hapus</a>', 
                $item['id'], $nonce 
            )
        ];
        
        return sprintf( '<strong>%1$s</strong> %2$s', esc_html($item['nama_desa']), $this->row_actions( $actions ) );
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item['id']);
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_desa';
        
        $this->process_bulk_action();
        
        $per_page = 20;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        
        $where_sql = '';
        if ( ! empty( $_REQUEST['s'] ) ) {
            $search = '%' . $wpdb->esc_like( stripslashes( $_REQUEST['s'] ) ) . '%';
            $where_sql = $wpdb->prepare(" WHERE nama_desa LIKE %s OR kabupaten LIKE %s", $search, $search);
        }

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_sql");

        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;
        
        $orderby = $_REQUEST['orderby'] ?? 'created_at';
        $order = $_REQUEST['order'] ?? 'desc';
        
        // Whitelist orderby
        if(!in_array($orderby, ['nama_desa', 'created_at'])) $orderby = 'created_at';

        $query = "SELECT * FROM $table_name $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $this->items = $wpdb->get_results( $wpdb->prepare($query, $per_page, $offset), ARRAY_A );
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}
?>