<?php
/**
 * DW Ojek List Table
 * Menangani tampilan tabel daftar ojek di admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class DW_Ojek_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'ojek',
            'plural'   => 'ojeks',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'                 => '<input type="checkbox" />',
            'nama_lengkap'       => 'Nama Driver',
            'info_kendaraan'     => 'Info Kendaraan',
            'lokasi'             => 'Domisili',
            'status_pendaftaran' => 'Status Pendaftaran',
            'status_kerja'       => 'Status Kerja',
            'rating'             => 'Rating',
            'actions'            => 'Aksi'
        ];
    }

    public function get_sortable_columns() {
        return [
            'nama_lengkap'       => ['nama_lengkap', true],
            'status_pendaftaran' => ['status_pendaftaran', false],
            'created_at'         => ['created_at', false]
        ];
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="ojek[]" value="%s" />', $item['id']
        );
    }

    protected function column_nama_lengkap($item) {
        $edit_link = admin_url('admin.php?page=dw-manajemen-ojek&action=edit&id=' . $item['id']);
        
        $actions = [
            'edit' => sprintf('<a href="%s">Edit</a>', $edit_link),
            'delete' => sprintf('<a href="?page=dw-manajemen-ojek&action=delete&id=%s&_wpnonce=%s" onclick="return confirm(\'Yakin hapus?\')">Hapus</a>', $item['id'], wp_create_nonce('delete_ojek_' . $item['id']))
        ];

        return sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong><br><small>%s</small>%s',
            $edit_link,
            esc_html($item['nama_lengkap']),
            esc_html($item['no_hp']),
            $this->row_actions($actions)
        );
    }

    protected function column_info_kendaraan($item) {
        return sprintf(
            '<strong>%s</strong><br>%s',
            esc_html($item['merk_motor']),
            esc_html($item['plat_nomor'])
        );
    }

    protected function column_lokasi($item) {
        // Ambil nama wilayah jika ID tersedia (Opsional: Query nama wilayah jika perlu)
        // Untuk efisiensi, kita tampilkan alamat lengkap saja dulu
        return esc_html(wp_trim_words($item['alamat_domisili'], 5));
    }

    protected function column_status_pendaftaran($item) {
        $status = $item['status_pendaftaran'];
        $color = 'gray';
        if ($status == 'disetujui') $color = 'green';
        if ($status == 'ditolak') $color = 'red';
        if ($status == 'menunggu') $color = 'orange';

        return sprintf('<span style="color:%s; font-weight:bold;">%s</span>', $color, ucfirst($status));
    }

    protected function column_status_kerja($item) {
        $status = $item['status_kerja'];
        $badge_class = ($status == 'online') ? 'dashicons-yes-alt' : 'dashicons-dismiss';
        $color = ($status == 'online') ? 'green' : '#999';
        
        return sprintf('<span style="color:%s;"><span class="dashicons %s"></span> %s</span>', $color, $badge_class, ucfirst($status));
    }

    protected function column_rating($item) {
        return '⭐ ' . number_format($item['rating_avg'], 1);
    }

    protected function column_actions($item) {
        if ($item['status_pendaftaran'] == 'menunggu') {
            $approve_url = wp_nonce_url(admin_url('admin.php?page=dw-manajemen-ojek&action=approve&id=' . $item['id']), 'approve_ojek_' . $item['id']);
            return sprintf('<a href="%s" class="button button-small button-primary">Setujui</a>', $approve_url);
        }
        return '';
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ojek';

        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Query
        $orderby = (!empty($_GET['orderby'])) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = (!empty($_GET['order'])) ? sanitize_text_field($_GET['order']) : 'DESC';
        
        // Search
        $search_term = (!empty($_POST['s'])) ? sanitize_text_field($_POST['s']) : '';
        $where_sql = "WHERE 1=1";
        if ($search_term) {
            $where_sql .= " AND (nama_lengkap LIKE '%$search_term%' OR plat_nomor LIKE '%$search_term%')";
        }

        // Pagination
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_sql");
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $offset = ($this->get_pagenum() - 1) * $per_page;
        
        $sql = "SELECT * FROM $table_name $where_sql ORDER BY $orderby $order LIMIT $offset, $per_page";
        
        $this->items = $wpdb->get_results($sql, ARRAY_A);
    }
}