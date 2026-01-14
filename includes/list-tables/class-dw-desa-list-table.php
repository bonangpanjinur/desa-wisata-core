<?php
/**
 * Class List Table Desa
 * Path: includes/list-tables/class-dw-desa-list-table.php
 * * Menangani tampilan tabel data desa dengan query yang aman.
 * @package DesaWisataCore
 * @version 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class DW_Desa_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'desa',
            'plural'   => 'desa',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'title'       => 'Nama Desa',
            'author'      => 'Admin Desa',
            'date'        => 'Tanggal Dibuat',
            'actions'     => 'Aksi'
        ];
    }

    public function get_sortable_columns() {
        return [
            'title' => ['post_title', true],
            'date'  => ['post_date', false],
        ];
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'title':
                return '<strong>' . esc_html($item->post_title) . '</strong>';
            case 'author':
                $user = get_userdata($item->post_author);
                return $user ? esc_html($user->display_name) : '-';
            case 'date':
                return esc_html($item->post_date);
            default:
                return print_r($item, true); // Fallback for debugging (escaped automatically by print_r if viewed in source, but better to be explicit in production)
        }
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="desa[]" value="%d" />',
            $item->ID
        );
    }

    protected function column_actions($item) {
        $actions = [];
        
        $edit_link = get_edit_post_link($item->ID);
        $actions['edit'] = sprintf(
            '<a href="%s">Edit</a>', 
            esc_url($edit_link)
        );

        // Jika menggunakan post type 'desa', delete link standar WP bisa digunakan
        $delete_link = get_delete_post_link($item->ID);
        if ($delete_link) {
            $actions['delete'] = sprintf(
                '<a href="%s" style="color:red;" onclick="return confirm(\'%s\')">Trash</a>',
                esc_url($delete_link),
                esc_js(__('Apakah Anda yakin ingin memindahkan desa ini ke sampah?', 'desa-wisata-core'))
            );
        }

        return implode(' | ', $actions);
    }
    
    // Override column_title untuk menampilkan aksi row
    protected function column_title($item) {
        return sprintf(
            '<strong><a class="row-title" href="%s" aria-label="%s">%s</a></strong>%s',
            esc_url(get_edit_post_link($item->ID)),
            esc_attr(sprintf(__('Edit &#8220;%s&#8221;'), $item->post_title)),
            esc_html($item->post_title),
            $this->row_actions($this->column_actions_array($item))
        );
    }

    private function column_actions_array($item) {
        $actions = [];
        $actions['edit'] = sprintf('<a href="%s">Edit</a>', esc_url(get_edit_post_link($item->ID)));
        
        if (current_user_can('delete_post', $item->ID)) {
             $actions['trash'] = sprintf(
                '<a href="%s" class="submitdelete" aria-label="%s">Trash</a>',
                esc_url(get_delete_post_link($item->ID)),
                esc_attr(sprintf(__('Move &#8220;%s&#8221; to the Trash'), $item->post_title))
            );
        }
        return $actions;
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Query Post Type 'desa' secara manual agar lebih fleksibel dibanding WP_Query
        // Namun tetap menggunakan prepare()
        $sql = "SELECT ID, post_title, post_author, post_date FROM {$wpdb->posts} WHERE post_type = 'desa' AND post_status = 'publish'";
        $query_args = [];

        // Pencarian Aman
        if (!empty($_REQUEST['s'])) {
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $sql .= " AND post_title LIKE %s";
            $query_args[] = $search_term;
        }

        // Sorting
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], ['post_title', 'post_date'])) ? $_REQUEST['orderby'] : 'post_date';
        $order = (isset($_REQUEST['order']) && strtoupper($_REQUEST['order']) == 'ASC') ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $orderby $order"; // Orderby/order sudah divalidasi whitelist di atas

        // Pagination
        $sql .= " LIMIT %d OFFSET %d";
        $query_args[] = $per_page;
        $query_args[] = $offset;

        // Eksekusi
        if (!empty($query_args)) {
            $this->items = $wpdb->get_results($wpdb->prepare($sql, $query_args));
        } else {
            // Harusnya tidak masuk sini karena limit/offset selalu ada, tapi untuk jaga-jaga:
            // Jika query args kosong (misal limit dihapus), query tetap harus jalan.
            // Tapi karena limit/offset wajib di logic ini, kita bisa asumsikan selalu ada args.
             $this->items = $wpdb->get_results($wpdb->prepare($sql, $query_args)); // Tetap prepare
        }

        // Hitung Total
        $count_sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'desa' AND post_status = 'publish'";
        if (!empty($_REQUEST['s'])) {
             $count_sql .= " AND post_title LIKE %s";
             // Ambil argumen pertama saja (search term)
             $total_items = $wpdb->get_var($wpdb->prepare($count_sql, $query_args[0]));
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