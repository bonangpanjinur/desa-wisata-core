<?php
/**
 * File Name:   class-dw-templates-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-templates-list-table.php
 */
if (!class_exists('WP_List_Table')) { require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php'); }

class DW_Templates_List_Table extends WP_List_Table {
    public function __construct() { parent::__construct(['singular' => 'Template', 'plural' => 'Templates']); }
    public function get_columns() { return ['cb' => '<input type="checkbox" />', 'kode' => 'Kode', 'judul' => 'Judul', 'trigger_event' => 'Pemicu']; }
    protected function column_default($item, $column_name) { return esc_html($item[$column_name]); }
    public function prepare_items() {
        global $wpdb; $table = $wpdb->prefix . 'dw_whatsapp_templates';
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    }
}
