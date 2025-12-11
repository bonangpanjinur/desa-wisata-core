<?php
/**
 * File Name:   class-dw-paket-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-paket-list-table.php
 *
 * [BARU] List Table untuk halaman Admin "Paket Transaksi".
 *
 * @package DesaWisataCore
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DW_Paket_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'Paket', 'plural' => 'Paket', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'nama_paket'   => 'Nama Paket',
            'harga'        => 'Harga (Rp)',
            'jumlah_transaksi' => 'Jumlah Kuota Transaksi',
            'persentase_komisi_desa' => 'Komisi Desa (%)',
            'status'       => 'Status',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'nama_paket'   => ['nama_paket', false],
            'harga'        => ['harga', false],
            'status'       => ['status', false],
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_paket_transaksi';

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'harga';
        $order = isset($_GET['order']) ? sanitize_key($_GET['order']) : 'asc';

        // TODO: Tambahkan paginasi jika perlu
        $this->items = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY $orderby $order", ARRAY_A );
    }

    protected function column_default( $item, $column_name ) {
        switch ($column_name) {
            case 'harga':
                return '<strong>Rp ' . number_format($item['harga'], 0, ',', '.') . '</strong>';
            case 'jumlah_transaksi':
                return number_format_i18n($item['jumlah_transaksi']) . ' trx';
            case 'persentase_komisi_desa':
                return esc_html($item['persentase_komisi_desa']) . ' %';
            case 'status':
                return '<span class="dw-status-' . esc_attr($item['status']) . '">' . ucfirst(esc_html($item['status'])) . '</span>';
            default:
                return esc_html($item[$column_name]);
        }
    }

    protected function column_nama_paket($item) {
        $nonce = wp_create_nonce('dw_delete_paket_nonce');
        $actions = [
            'edit' => sprintf('<a href="?page=%s&action=edit_paket&id=%s">Edit</a>', $_REQUEST['page'], $item['id']),
            'delete' => sprintf(
                '<a href="?page=%s&action=delete_paket&id=%s&_wpnonce=%s" class="dw-confirm-link" data-confirm-message="Yakin ingin menghapus paket ini?">Hapus</a>',
                $_REQUEST['page'], $item['id'], $nonce
            ),
        ];
        return sprintf('<strong>%s</strong><br><small>%s</small> %s', 
            esc_html($item['nama_paket']), 
            esc_html($item['deskripsi']), 
            $this->row_actions($actions)
        );
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item['id']);
    }

    public function no_items() {
        _e( 'Belum ada paket transaksi yang dibuat.', 'desa-wisata-core' );
    }
}
?>
