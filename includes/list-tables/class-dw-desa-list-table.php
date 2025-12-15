<?php
/**
 * File Name:   class-dw-desa-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-desa-list-table.php
 *
 * PERBAIKAN:
 * - Menghapus `onclick="return confirm()"` dari link Hapus.
 * - Menambahkan `class="dw-confirm-link"` dan `data-confirm-message="..."`
 * agar ditangani oleh `admin-scripts.js` yang sudah diperbarui.
 * - FIX ERROR: Undefined index: page (menambahkan fallback variable).
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
            'status'      => 'Status',
            'created_at'  => 'Tanggal Dibuat'
        ];
    }
    
    protected function get_bulk_actions() {
        return ['delete' => 'Hapus'];
    }

    public function process_bulk_action() {
        if ('delete' === $this->current_action() && current_user_can('dw_manage_desa')) {
            $ids = isset($_REQUEST['ids']) ? array_map('intval', $_REQUEST['ids']) : [];
            if (count($ids) > 0) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'dw_desa';
                foreach ($ids as $id) {
                    do_action('dw_before_desa_deleted', $id);
                    $wpdb->delete($table_name, ['id' => $id], ['%d']);
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
                return '<span class="dw-status-' . esc_attr($item['status']) . '">' . ucfirst(esc_html($item['status'])) . '</span>';
            case 'created_at':
                return date('d M Y, H:i', strtotime($item['created_at']));
            default:
                return esc_html($item[ $column_name ]);
        }
    }

    protected function column_nama_desa( $item ) {
        $nonce = wp_create_nonce('dw_delete_desa_nonce');
        
        // --- PERBAIKAN ERROR UNDEFINED INDEX: PAGE ---
        // Gunakan nilai default 'dw-desa' jika parameter 'page' tidak ada di URL
        $page_slug = isset($_REQUEST['page']) ? $_REQUEST['page'] : 'dw-desa';

        // --- PERBAIKAN CONFIRM LINK ---
        // Menghapus onclick="return confirm(...)" dari link Hapus
        // Menambahkan class="dw-confirm-link" dan data-confirm-message
        $delete_link = sprintf(
            '<a href="?page=%s&action=%s&id=%s&_wpnonce=%s" class="dw-confirm-link" data-confirm-message="%s" style="color:#a00;">Hapus</a>', 
            esc_attr($page_slug), 
            'delete', 
            $item['id'], 
            $nonce,
            esc_attr('Anda yakin ingin menghapus desa ini? Semua relasi ke pedagang dan wisata akan dilepaskan.')
        );
        
        $actions = [
            'edit'   => sprintf( '<a href="?page=%s&action=%s&id=%s">Edit</a>', esc_attr($page_slug), 'edit', $item['id'] ),
            'delete' => $delete_link
        ];
        // --- AKHIR PERBAIKAN ---
        
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
        
        $orderby = ( ! empty( $_REQUEST['orderby'] ) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns())) ) ? $_REQUEST['orderby'] : 'created_at';
        $order = ( ! empty( $_REQUEST['order'] ) && in_array(strtolower($_REQUEST['order']), ['asc', 'desc']) ) ? $_REQUEST['order'] : 'desc';

        $where_sql = '';
        if ( ! empty( $_REQUEST['s'] ) ) {
            $search_term = '%' . $wpdb->esc_like( stripslashes( $_REQUEST['s'] ) ) . '%';
            $where_sql = $wpdb->prepare(" WHERE nama_desa LIKE %s", $search_term);
        }

        // Hitung total item dengan filter
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name" . $where_sql);

        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;
        
        // Ambil data untuk halaman saat ini
        $query = "SELECT * FROM $table_name" . $where_sql . " ORDER BY " . esc_sql($orderby) . " " . esc_sql($order) . " LIMIT %d OFFSET %d";
        $this->items = $wpdb->get_results( $wpdb->prepare($query, $per_page, $offset), ARRAY_A );
        
        // Atur argumen paginasi
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}
?>