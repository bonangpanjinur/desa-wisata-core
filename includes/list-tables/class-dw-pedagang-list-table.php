<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DW_Pedagang_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'pedagang',
            'plural'   => 'pedagang',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'display_name' => 'Nama Pemilik',
            'toko_name'    => 'Nama Toko',
            'desa_asal'    => 'Desa Asal',
            'user_email'   => 'Email',
            'phone'        => 'No. Telepon',
            'registered'   => 'Terdaftar',
            'status'       => 'Status'
        ];
    }

    public function get_sortable_columns() {
        return [
            'display_name' => [ 'display_name', true ],
            'registered'   => [ 'registered', false ]
        ];
    }

    protected function column_default( $item, $column_name ) {
        // Debugging jika perlu: error_log(print_r($item, true));
        
        // $item adalah array hasil konversi dari objek user
        $user_id = $item['ID'];

        switch ( $column_name ) {
            case 'toko_name':
                return get_user_meta( $user_id, 'nama_toko', true ) ?: '<em>Belum set nama toko</em>';
            
            case 'desa_asal':
                $id_desa = get_user_meta( $user_id, 'id_desa', true );
                if ( $id_desa ) {
                    $desa = get_userdata( $id_desa );
                    return $desa ? $desa->display_name : 'Desa tidak ditemukan';
                }
                return '<span style="color:red;">Belum terhubung</span>';

            case 'user_email':
                return $item['user_email'];

            case 'phone':
                return get_user_meta( $user_id, 'phone_number', true ) ?: '-';

            case 'registered':
                return date_i18n( get_option( 'date_format' ), strtotime( $item['user_registered'] ) );

            case 'status':
                $active = get_user_meta( $user_id, 'account_status', true );
                $color  = ($active === 'active') ? '#d1fae5' : '#fee2e2';
                $text_color = ($active === 'active') ? '#065f46' : '#991b1b';
                $label  = ($active === 'active') ? 'Aktif' : 'Pending';
                
                return sprintf(
                    '<span style="background:%s; color:%s; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold;">%s</span>',
                    $color, $text_color, $label
                );

            default:
                return isset($item[$column_name]) ? $item[$column_name] : '';
        }
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="pedagang[]" value="%s" />', $item['ID']
        );
    }

    protected function column_display_name( $item ) {
        $actions = [
            'edit'   => sprintf( '<a href="#" class="dw-edit-pedagang" data-id="%s">Edit</a>', $item['ID'] ),
            'delete' => sprintf( '<a href="#" class="dw-delete-pedagang" data-id="%s">Hapus</a>', $item['ID'] ),
        ];

        // Mendapatkan avatar kecil
        $avatar = get_avatar( $item['ID'], 32 );
        
        return sprintf( 
            '<div style="display:flex; align-items:center; gap:10px;">%s <div><strong>%s</strong>%s</div></div>', 
            $avatar, 
            $item['display_name'], 
            $this->row_actions( $actions ) 
        );
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';

        // Query args untuk mengambil user dengan role 'pedagang'
        $args = [
            'role'    => 'pedagang', // Pastikan role ini sesuai dengan yang ada di roles-capabilities.php
            'number'  => $per_page,
            'offset'  => ( $current_page - 1 ) * $per_page,
            'search'  => $search ? '*' . $search . '*' : '',
            'orderby' => isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'display_name',
            'order'   => isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC',
        ];

        // Jika pencarian dilakukan
        if ( ! empty( $search ) ) {
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }

        $user_query = new WP_User_Query( $args );
        $results    = $user_query->get_results();

        // Konversi objek user ke array agar kompatibel dengan logic column_default
        $this->items = array_map( function ( $user ) {
            return (array) $user->data;
        }, $results );

        $this->set_pagination_args( [
            'total_items' => $user_query->get_total(),
            'per_page'    => $per_page,
            'total_pages' => ceil( $user_query->get_total() / $per_page ),
        ] );
    }
}