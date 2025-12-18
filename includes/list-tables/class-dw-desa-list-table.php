<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DW_Desa_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'desa',
            'plural'   => 'desa',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'display_name'   => 'Nama Desa',
            'user_email'     => 'Email',
            'user_login'     => 'Username',
            'phone'          => 'No. Telepon',
            'total_wisata'   => 'Total Wisata',   // Kolom Baru
            'total_pedagang' => 'Total Pedagang', // Kolom Baru
            'registered'     => 'Terdaftar',
            'status'         => 'Status'
        ];
    }

    public function get_sortable_columns() {
        return [
            'display_name' => [ 'display_name', true ],
            'user_login'   => [ 'user_login', false ],
            'registered'   => [ 'registered', false ]
        ];
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'user_email':
            case 'user_login':
                return $item[ $column_name ];
            case 'phone':
                return get_user_meta( $item['ID'], 'phone_number', true ) ?: '-';
            case 'registered':
                return date_i18n( get_option( 'date_format' ), strtotime( $item['user_registered'] ) );
            case 'status':
                $active = get_user_meta($item['ID'], 'account_status', true);
                $badge_class = ($active === 'active') ? 'status-active' : 'status-inactive';
                $badge_text  = ($active === 'active') ? 'Aktif' : 'Non-Aktif';
                return sprintf('<span class="dw-badge %s">%s</span>', $badge_class, $badge_text);
            
            // Logika untuk menghitung jumlah wisata
            case 'total_wisata':
                $args = [
                    'post_type'  => 'wisata',
                    'meta_key'   => 'id_desa',
                    'meta_value' => $item['ID'],
                    'fields'     => 'ids',
                    'numberposts' => -1
                ];
                $count = count( get_posts( $args ) );
                return '<span class="dw-count-badge">' . $count . '</span>';

            // Logika untuk menghitung jumlah pedagang yang terhubung
            case 'total_pedagang':
                $args = [
                    'role'       => 'pedagang',
                    'meta_key'   => 'id_desa',
                    'meta_value' => $item['ID'],
                    'fields'     => 'ID'
                ];
                $user_query = new WP_User_Query( $args );
                $count = $user_query->get_total();
                return '<span class="dw-count-badge">' . $count . '</span>';

            default:
                return print_r( $item, true );
        }
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="desa[]" value="%s" />', $item['ID']
        );
    }

    protected function column_display_name( $item ) {
        $actions = [
            'edit'   => sprintf( '<a href="#" class="dw-edit-desa" data-id="%s">Edit</a>', $item['ID'] ),
            'delete' => sprintf( '<a href="#" class="dw-delete-desa" data-id="%s">Hapus</a>', $item['ID'] ),
        ];

        return sprintf( '%1$s %2$s', '<strong>' . $item['display_name'] . '</strong>', $this->row_actions( $actions ) );
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';

        $args = [
            'role'    => 'desa',
            'number'  => $per_page,
            'offset'  => ( $current_page - 1 ) * $per_page,
            'search'  => $search ? '*' . $search . '*' : '',
            'orderby' => isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'display_name',
            'order'   => isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC',
        ];

        $user_query = new WP_User_Query( $args );

        $this->items = array_map( function ( $user ) {
            return (array) $user->data;
        }, $user_query->get_results() );

        $this->set_pagination_args( [
            'total_items' => $user_query->get_total(),
            'per_page'    => $per_page,
            'total_pages' => ceil( $user_query->get_total() / $per_page ),
        ] );
    }
}