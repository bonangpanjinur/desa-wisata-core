<?php
/**
 * File Path: includes/list-tables/class-dw-banner-list-table.php
 * 
 * @package DesaWisataCore
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class DW_Banner_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'Banner',
            'plural'   => 'Banners',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'gambar'    => 'Gambar',
            'judul'     => 'Judul',
            'status'    => 'Status',
            'prioritas' => 'Prioritas',
            'created'   => 'Tanggal Dibuat'
        ];
    }

    public function get_sortable_columns() {
        return [
            'judul'     => [ 'judul', false ],
            'status'    => [ 'status', false ],
            'prioritas' => [ 'prioritas', true ],
            'created'   => [ 'created_at', false ]
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_banner';
        
        $this->process_bulk_action();

        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'prioritas';
        $order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'ASC';
        $search  = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        $where = '';
        if ( ! empty( $search ) ) {
            $where = $wpdb->prepare( " WHERE judul LIKE %s ", '%' . $wpdb->esc_like( $search ) . '%' );
        }

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name $where" );
        
        $this->items = $wpdb->get_results( 
            $wpdb->prepare( 
                "SELECT * FROM $table_name $where ORDER BY $orderby $order LIMIT %d OFFSET %d", 
                $per_page, 
                $offset 
            ), 
            ARRAY_A 
        );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'prioritas':
                return '<strong>' . esc_html( $item[ $column_name ] ) . '</strong>';
            case 'status':
                $class = ( $item['status'] === 'aktif' ) ? 'aktif' : 'nonaktif';
                $label = ( $item['status'] === 'aktif' ) ? 'Aktif' : 'Nonaktif';
                return sprintf( '<span class="%s">%s</span>', $class, $label );
            case 'created':
                return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) );
            default:
                return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
        }
    }

    protected function column_judul( $item ) {
        $id = $item['id'];
        $nonce = wp_create_nonce( 'dw_delete_banner_' . $id );
        
        $actions = [
            'edit'   => sprintf( '<a href="?page=%s&action=edit&id=%s">Edit</a>', $_REQUEST['page'], $id ),
            'delete' => sprintf(
                '<a href="?page=%s&action=delete&id=%s&_wpnonce=%s" class="dw-confirm-link" data-confirm-message="%s" style="color:#a00;">Hapus</a>',
                $_REQUEST['page'],
                $id,
                $nonce,
                esc_attr( 'Apakah Anda yakin ingin menghapus banner ini?' )
            ),
        ];

        return sprintf( '<strong><a class="row-title" href="?page=%s&action=edit&id=%s">%s</a></strong> %s', 
            $_REQUEST['page'], 
            $id, 
            esc_html( $item['judul'] ), 
            $this->row_actions( $actions ) 
        );
    }

    protected function column_gambar( $item ) {
        if ( empty( $item['gambar'] ) ) {
            return '<em>No Image</em>';
        }
        return sprintf( 
            '<img src="%s" width="120" style="border-radius:4px; max-height:60px; object-fit:cover; border: 1px solid #ccc;" alt="%s" />', 
            esc_url( $item['gambar'] ), 
            esc_attr( $item['judul'] ) 
        );
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', $item['id'] );
    }

    protected function get_bulk_actions() {
        return [ 'delete' => 'Hapus Permanen' ];
    }

    public function process_bulk_action() {
        if ( 'delete' === $this->current_action() ) {
            // Verify nonce for bulk action if needed, but usually WP handles this via check_admin_referer
            $ids = isset( $_REQUEST['ids'] ) ? array_map( 'absint', $_REQUEST['ids'] ) : [];
            
            if ( ! empty( $ids ) ) {
                global $wpdb;
                $table = $wpdb->prefix . 'dw_banner';
                $ids_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                
                $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id IN ($ids_placeholders)", $ids ) );
                
                // Redirect to avoid re-submission
                wp_redirect( add_query_arg( [ 'page' => 'dw-banner', 'message' => 'deleted' ], admin_url( 'admin.php' ) ) );
                exit;
            }
        }
    }
}
