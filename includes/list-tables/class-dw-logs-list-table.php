<?php
/**
 * File Name:   class-dw-logs-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-logs-list-table.php
 */
if (!class_exists('WP_List_Table')) { require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php'); }

class DW_Logs_List_Table extends WP_List_Table {
    public function __construct() { parent::__construct(['singular' => 'Log', 'plural' => 'Logs']); }
    public function get_columns() { return ['user' => 'Pengguna', 'aksi' => 'Aksi', 'keterangan' => 'Keterangan', 'waktu' => 'Waktu']; }
    protected function column_default($item, $column_name) { 
        if ($column_name === 'user') {
            $user = get_user_by('id', $item['user_id']);
            return $user ? esc_html($user->display_name) : 'Sistem';
        }
        if ($column_name === 'waktu') return $item['created_at'];
        return esc_html($item[$column_name]);
    }
    public function prepare_items() {
        global $wpdb; $table = $wpdb->prefix . 'dw_logs';
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100", ARRAY_A); // Batasi 100 log terbaru
    }
}
