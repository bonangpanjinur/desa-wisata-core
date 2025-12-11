<?php
/**
 * File Name:   class-dw-promosi-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-promosi-list-table.php
 *
 * @package DesaWisataCore
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class DW_Promosi_List_Table extends WP_List_Table {

	public function __construct() {
        parent::__construct( [ 'singular' => 'Promosi', 'plural' => 'Promosi', 'ajax' => false ] );
    }

	public function get_columns() {
		return [
			'cb'         => '<input type="checkbox" />',
			'tipe'       => 'Tipe',
			'target'     => 'Target Promosi',
			'pemohon_id' => 'Pemohon',
			'status'     => 'Status',
			'mulai'      => 'Tanggal Mulai',
		];
	}

	public function prepare_items() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'dw_promosi';

		$per_page     = 20;
		$this->set_pagination_args( [
			'total_items' => $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" ),
			'per_page'    => $per_page,
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$this->items = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, ( $this->get_pagenum() - 1 ) * $per_page )
		);
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'pemohon_id':
				$user = get_userdata( $item->pemohon_id );
				return $user ? $user->display_name : 'N/A';
			case 'mulai':
				return $item->mulai ? date( 'd M Y', strtotime( $item->mulai ) ) : '-';
			default:
				return ucfirst( $item->$column_name );
		}
	}
    
    public function column_target( $item ) {
        if ( in_array($item->tipe, ['produk', 'wisata']) ) {
            $title = get_the_title( $item->target_id );
            $link  = get_edit_post_link( $item->target_id );
            return sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $title ) );
        }
        return 'N/A';
    }

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%s" />', $item->id );
	}
    
    protected function get_sortable_columns() {
        return [ 'status' => ['status', false], 'mulai'  => ['mulai', false] ];
    }
    
    public function column_status($item) {
        if ($item->status === 'pending') {
            $approve_url = add_query_arg([ 'action' => 'approve', 'id' => $item->id, '_wpnonce' => wp_create_nonce('dw_promo_action_' . $item->id) ]);
            $reject_url = add_query_arg([ 'action' => 'reject', 'id' => $item->id, '_wpnonce' => wp_create_nonce('dw_promo_action_' . $item->id) ]);
            $actions = [
                'approve' => sprintf('<a href="%s" style="color:green;">Setujui</a>', esc_url($approve_url)),
                'reject' => sprintf('<a href="%s" style="color:red;">Tolak</a>', esc_url($reject_url)),
            ];
            return sprintf('<strong>Pending</strong> %s', $this->row_actions($actions));
        }
        
        return sprintf('<span style="color:%s;">%s</span>', $item->status === 'aktif' ? 'green' : 'gray', ucfirst($item->status));
    }
}

