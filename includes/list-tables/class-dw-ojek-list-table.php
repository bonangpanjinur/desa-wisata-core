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
            '<input type="checkbox" name="ojek[]" value="%s" />', esc_attr($item['id'])
        );
    }

    protected function column_nama_lengkap($item) {
        $edit_link = admin_url('admin.php?page=dw-manajemen-ojek&action=edit&id=' . absint($item['id']));
        
        $actions = [
            'edit' => sprintf('<a href="%s">Edit</a>', esc_url($edit_link)),
            'delete' => sprintf('<a href="?page=dw-manajemen-ojek&action=delete&id=%s&_wpnonce=%s" onclick="return confirm(\'Yakin hapus?\')">Hapus</a>', absint($item['id']), wp_create_nonce('delete_ojek_' . $item['id']))
        ];

        return sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong><br><small>%s</small>%s',
            esc_url($edit_link),
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

        return sprintf('<span style="color:%s; font-weight:bold;">%s</span>', esc_attr($color), esc_html(ucfirst($status)));
    }

    protected function column_status_kerja($item) {
        $status = $item['status_kerja'];
        $badge_class = ($status == 'online') ? 'dashicons-yes-alt' : 'dashicons-dismiss';
        $color = ($status == 'online') ? 'green' : '#999';
        
        return sprintf('<span style="color:%s;"><span class="dashicons %s"></span> %s</span>', esc_attr($color), esc_attr($badge_class), esc_html(ucfirst($status)));
    }

    protected function column_rating($item) {
        return '⭐ ' . number_format($item['rating_avg'], 1);
    }

    protected function column_actions($item) {
        if ($item['status_pendaftaran'] == 'menunggu') {
            $approve_url = wp_nonce_url(admin_url('admin.php?page=dw-manajemen-ojek&action=approve&id=' . absint($item['id'])), 'approve_ojek_' . $item['id']);
            return sprintf('<a href="%s" class="button button-small button-primary">Setujui</a>', esc_url($approve_url));
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

        // Whitelist columns for sorting
        $sortable_columns = array_keys($this->get_sortable_columns());
        $orderby = (!empty($_GET['orderby']) && in_array($_GET['orderby'], $sortable_columns)) ? $_GET['orderby'] : 'created_at';
        $order = (!empty($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
        
        // Search
        $search_term = (!empty($_POST['s'])) ? sanitize_text_field($_POST['s']) : '';
        $where_sql = "WHERE 1=1";
        $query_args = [];
        if ($search_term) {
            $where_sql .= " AND (nama_lengkap LIKE %s OR plat_nomor LIKE %s)";
            $query_args[] = '%' . $wpdb->esc_like($search_term) . '%';
            $query_args[] = '%' . $wpdb->esc_like($search_term) . '%';
        }

        // Pagination
        $count_query = "SELECT COUNT(id) FROM $table_name $where_sql";
        if (!empty($query_args)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $query_args));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $offset = ($this->get_pagenum() - 1) * $per_page;
        
        $sql = "SELECT * FROM $table_name $where_sql ORDER BY $orderby $order LIMIT %d, %d";
        $query_args[] = $offset;
        $query_args[] = $per_page;
        
        $this->items = $wpdb->get_results($wpdb->prepare($sql, $query_args), ARRAY_A);
    }
}
