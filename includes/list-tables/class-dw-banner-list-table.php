<?php
/**
 * File Path: includes/list-tables/class-dw-banner-list-table.php
 *
 * PERBAIKAN:
 * - Disesuaikan untuk menggunakan tabel `dw_banner` yang baru.
 * - Menambahkan kolom 'prioritas' dan 'status'.
 * - Menambahkan aksi Edit dan Hapus.
 * - Menambahkan bulk actions.
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class DW_Banner_List_Table extends WP_List_Table {

	public function __construct() {
        parent::__construct( [ 'singular' => 'Banner', 'plural' => 'Banner', 'ajax' => false ] );
    }

	public function get_columns() {
		return [
			'cb'        => '<input type="checkbox" />',
			'gambar'    => 'Gambar',
			'judul'     => 'Judul',
			'status'    => 'Status',
			'prioritas' => 'Prioritas',
		];
	}

	public function prepare_items() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'dw_banner';
        
        $this->process_bulk_action();

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$this->items = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY prioritas ASC", ARRAY_A );
	}

	protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'judul':
            case 'prioritas':
				return esc_html( $item[ $column_name ] );
			case 'status':
				return '<span class="' . esc_attr($item[$column_name]) . '">' . ucfirst(esc_html($item[$column_name])) . '</span>';
			default:
				return print_r( $item, true );
		}
	}

    protected function column_judul($item) {
        $nonce = wp_create_nonce('dw_delete_banner_nonce');
        $actions = [
            'edit' => sprintf('<a href="?page=%s&action=edit&id=%s">Edit</a>', $_REQUEST['page'], $item['id']),
            // --- PERBAIKAN (ganti onclick dengan class) ---
            'delete' => sprintf(
                '<a href="?page=%s&action=delete&id=%s&_wpnonce=%s" class="dw-confirm-link" data-confirm-message="%s" style="color:#a00;">Hapus</a>',
                $_REQUEST['page'],
                $item['id'],
                $nonce,
                esc_attr('Apakah Anda yakin ingin menghapus banner ini?')
            ),
            // --- AKHIR PERBAIKAN ---
        ];
        return sprintf('<strong>%s</strong> %s', esc_html($item['judul']), $this->row_actions($actions));
    }

    protected function column_gambar( $item ) {
        return sprintf('<img src="%s" width="150" style="border-radius:4px; max-height:80px; object-fit:cover;" alt="%s" />', esc_url($item['gambar']), esc_attr($item['judul']));
    }

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', $item['id'] );
	}
    
    protected function get_sortable_columns() {
        return [
            'judul'     => ['judul', false],
            'status'    => ['status', false],
            'prioritas' => ['prioritas', true],
        ];
    }

    protected function get_bulk_actions() {
        return ['delete' => 'Hapus'];
    }

    public function process_bulk_action() {
        if ('delete' === $this->current_action() && current_user_can('dw_manage_banners')) {
            $ids = isset($_REQUEST['ids']) ? array_map('absint', $_REQUEST['ids']) : [];
            if (count($ids)) {
                global $wpdb;
                $table = $wpdb->prefix . 'dw_banner';
                $ids_str = implode(',', $ids);
                $wpdb->query("DELETE FROM $table WHERE id IN ($ids_str)");
                add_settings_error('dw_banner_notices', 'bulk_deleted', 'Banner yang dipilih berhasil dihapus.', 'success');
                set_transient('settings_errors', get_settings_errors(), 30);
            }
        }
    }
}