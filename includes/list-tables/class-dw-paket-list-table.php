<?php
/**
 * DW Paket List Table
 * Menangani tabel daftar paket transaksi (Pedagang & Ojek).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class DW_Paket_List_Table extends WP_List_Table {

    public $target_role = ''; // 'pedagang' atau 'ojek'

    public function __construct($role = '') {
        parent::__construct([
            'singular' => 'paket',
            'plural'   => 'pakets',
            'ajax'     => false
        ]);
        $this->target_role = $role;
    }

    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'nama_paket'   => 'Nama Paket',
            'harga'        => 'Harga',
            'detail'       => 'Detail Kuota',
            'komisi'       => 'Komisi Desa',
            'status'       => 'Status',
            'target_role'  => 'Untuk' // Opsional jika ingin ditampilkan
        ];
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="paket[]" value="%s" />', $item['id']
        );
    }

    protected function column_nama_paket($item) {
        $edit_link = admin_url('admin.php?page=dw-paket-transaksi&action=edit&id=' . $item['id']);
        $delete_nonce = wp_create_nonce('dw_delete_paket_' . $item['id']);
        
        $actions = [
            'edit' => sprintf('<a href="%s">Edit</a>', $edit_link),
            'delete' => sprintf('<a href="?page=dw-paket-transaksi&action=delete&id=%s&_wpnonce=%s" onclick="return confirm(\'Hapus paket ini?\')">Hapus</a>', $item['id'], $delete_nonce)
        ];

        return sprintf('<strong><a href="%s">%s</a></strong>%s', $edit_link, esc_html($item['nama_paket']), $this->row_actions($actions));
    }

    protected function column_harga($item) {
        return 'Rp ' . number_format($item['harga'], 0, ',', '.');
    }

    protected function column_detail($item) {
        $label = ($item['target_role'] == 'ojek') ? 'Trip' : 'Transaksi';
        return '<strong>' . $item['jumlah_transaksi'] . ' ' . $label . '</strong>';
    }

    protected function column_komisi($item) {
        return $item['persentase_komisi_desa'] . '%';
    }

    protected function column_status($item) {
        return ($item['status'] == 'aktif') ? '<span style="color:green;font-weight:bold;">Aktif</span>' : '<span style="color:gray;">Nonaktif</span>';
    }

    protected function column_target_role($item) {
        return ucfirst($item['target_role']);
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_paket_transaksi';

        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = ['nama_paket' => ['nama_paket', true], 'harga' => ['harga', false]];

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Query Filter
        $where = "WHERE 1=1";
        
        // Filter by Role (Tab)
        if (!empty($this->target_role)) {
            $where .= $wpdb->prepare(" AND target_role = %s", $this->target_role);
        }

        // Pagination
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where");
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $offset = ($this->get_pagenum() - 1) * $per_page;
        $sql = "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT $offset, $per_page";
        
        $this->items = $wpdb->get_results($sql, ARRAY_A);
    }
}