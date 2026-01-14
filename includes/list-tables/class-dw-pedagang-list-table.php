<?php
/**
 * Class List Table Pedagang
 * Path: includes/list-tables/class-dw-pedagang-list-table.php
 * * Menangani tampilan tabel data pedagang dengan query yang aman (Prepared Statements).
 * @package DesaWisataCore
 * @version 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class DW_Pedagang_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pedagang',
            'plural'   => 'pedagang',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'nama_pemilik' => 'Nama Pemilik',
            'nama_toko'    => 'Nama Toko',
            'lokasi'       => 'Lokasi',
            'kontak'       => 'Kontak',
            'status'       => 'Status',
            'tanggal'      => 'Tanggal Daftar',
            'actions'      => 'Aksi'
        ];
    }

    public function get_sortable_columns() {
        return [
            'nama_pemilik' => ['nama_pemilik', true],
            'nama_toko'    => ['nama_toko', false],
            'status'       => ['status', false],
            'tanggal'      => ['created_at', false]
        ];
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'nama_pemilik':
            case 'nama_toko':
            case 'status':
                return esc_html($item[$column_name]); // Escaping output
            case 'tanggal':
                return esc_html($item['created_at']);
            case 'lokasi':
                return esc_html(($item['latitude'] ?? '-') . ', ' . ($item['longitude'] ?? '-'));
            case 'kontak':
                return esc_html($item['phone'] ?? '-'); 
            default:
                return print_r($item, true);
        }
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="pedagang[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    protected function column_actions($item) {
        // Gunakan atribut data-* dan class standard untuk handler JS yang aman
        $actions = [];
        
        $actions['edit'] = sprintf(
            '<a href="#" class="dw-action-btn edit" data-action="edit" data-id="%d">Edit</a>', 
            $item['id']
        );

        if ($item['status'] !== 'verified') {
            $actions['verify'] = sprintf(
                '<a href="#" class="dw-action-btn verify" data-action="verify" data-id="%d" style="color:green;">Verifikasi</a>', 
                $item['id']
            );
        }

        $actions['delete'] = sprintf(
            '<a href="#" class="dw-action-btn delete" data-action="delete" data-id="%d" style="color:red;">Hapus</a>', 
            $item['id']
        );

        return implode(' | ', $actions);
    }

    /**
     * Prepare Items dengan SQL Prepared Statement
     */
    public function prepare_items() {
        global $wpdb;

        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $table_name = $wpdb->prefix . 'dw_pedagang';
        
        // Base Query
        $sql = "SELECT * FROM $table_name";
        $where_clauses = [];
        $query_args = [];

        // Pencarian Aman (SQL Injection Prevention)
        if (!empty($_REQUEST['s'])) {
            // Gunakan placeholder %s untuk string dan esc_like untuk wildcard
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where_clauses[] = "(nama_pemilik LIKE %s OR nama_toko LIKE %s)";
            $query_args[] = $search_term;
            $query_args[] = $search_term;
        }

        // Filter Status
        if (!empty($_REQUEST['status_filter'])) {
            $where_clauses[] = "status = %s";
            $query_args[] = sanitize_text_field($_REQUEST['status_filter']);
        }

        // Gabungkan WHERE
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        // Sorting
        $orderby = (isset($_REQUEST['orderby'])) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_at';
        $order = (isset($_REQUEST['order']) && strtoupper($_REQUEST['order']) == 'ASC') ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $orderby $order";

        // Pagination Limit
        $sql .= " LIMIT %d OFFSET %d";
        $query_args[] = $per_page;
        $query_args[] = $offset;

        // Eksekusi Query dengan Prepare
        // Perhatikan: Jika tidak ada pencarian, query_args hanya berisi limit & offset
        if (!empty($query_args)) {
            $this->items = $wpdb->get_results($wpdb->prepare($sql, $query_args), ARRAY_A);
        } else {
            // Fallback jika array kosong (seharusnya tidak terjadi karena limit/offset selalu ada)
            $this->items = $wpdb->get_results($sql, ARRAY_A);
        }

        // Hitung Total Item untuk Pagination
        $count_sql = "SELECT COUNT(id) FROM $table_name";
        if (!empty($where_clauses)) {
            // Kita perlu query args tanpa limit/offset untuk count
            $count_args = array_slice($query_args, 0, count($query_args) - 2); 
            $count_sql .= " WHERE " . implode(' AND ', $where_clauses);
            $total_items = $wpdb->get_var($wpdb->prepare($count_sql, $count_args));
        } else {
            $total_items = $wpdb->get_var($count_sql);
        }

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}