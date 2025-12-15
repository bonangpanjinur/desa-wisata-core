<?php
/**
 * File Name:   class-dw-desa-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-desa-list-table.php
 *
 * --- UPDATE (STATISTIK DESA) ---
 * - Menambahkan kolom "Jml. Wisata" dan "Jml. Pedagang".
 * - Mengoptimalkan query dengan sub-query COUNT() agar performa tetap cepat.
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DW_Desa_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'Desa', 'plural' => 'Desa', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'cb'              => '<input type="checkbox" />',
            'nama_desa'       => 'Nama Desa',
            'kabupaten'       => 'Kabupaten/Kota',
            'jumlah_wisata'   => 'Wisata',   // Kolom Baru
            'jumlah_pedagang' => 'Pedagang', // Kolom Baru
            'status'          => 'Status',
            'created_at'      => 'Tanggal Dibuat'
        ];
    }
    
    protected function get_bulk_actions() {
        return ['delete' => 'Hapus'];
    }

    public function process_bulk_action() {
        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['ids']) ? array_map('intval', $_REQUEST['ids']) : [];
            if (count($ids) > 0) {
                global $wpdb;
                foreach ($ids as $id) {
                    // Logic hapus relasi bisa ditambahkan di sini jika perlu
                    $wpdb->delete($wpdb->prefix . 'dw_desa', ['id' => $id]);
                }
            }
        }
    }

    protected function get_sortable_columns() {
        return [
            'nama_desa'       => ['nama_desa', false],
            'jumlah_wisata'   => ['count_wisata', false],   // Sortable berdasarkan count
            'jumlah_pedagang' => ['count_pedagang', false], // Sortable berdasarkan count
            'created_at'      => ['created_at', true]
        ];
    }

    protected function column_default( $item, $column_name ) {
        switch($column_name) {
            case 'status':
                $status_style = ($item['status'] == 'aktif') ? 'color:green;font-weight:bold;' : 'color:orange;';
                return sprintf('<span style="%s">%s</span>', $status_style, ucfirst(esc_html($item['status'])));
            case 'created_at':
                return date('d M Y', strtotime($item['created_at']));
            // Renderer untuk kolom hitungan
            case 'jumlah_wisata':
                $count = isset($item['count_wisata']) ? $item['count_wisata'] : 0;
                if ($count > 0) {
                    return sprintf('<span class="dw-badge" style="background:#e0f2f1; color:#00695c; padding:4px 8px; border-radius:4px; font-weight:600;">%s Objek</span>', number_format_i18n($count));
                }
                return '<span style="color:#aaa;">-</span>';
            case 'jumlah_pedagang':
                $count = isset($item['count_pedagang']) ? $item['count_pedagang'] : 0;
                if ($count > 0) {
                    return sprintf('<span class="dw-badge" style="background:#fff3e0; color:#e65100; padding:4px 8px; border-radius:4px; font-weight:600;">%s Toko</span>', number_format_i18n($count));
                }
                return '<span style="color:#aaa;">-</span>';
            default:
                return esc_html($item[ $column_name ]);
        }
    }

    protected function column_nama_desa( $item ) {
        $page = $_REQUEST['page'] ?? 'dw-desa';
        $nonce = wp_create_nonce('dw_desa_action');
        
        $actions = [
            'edit'   => sprintf( '<a href="?page=%s&action=edit&id=%s">Edit</a>', $page, $item['id'] ),
            'delete' => sprintf( 
                '<a href="#" onclick="if(confirm(\'Hapus desa ini beserta data pedagang terkait?\')) { var f = document.createElement(\'form\'); f.method=\'POST\'; f.innerHTML=\'<input type=hidden name=action_desa value=delete><input type=hidden name=desa_id value=%s><input type=hidden name=_wpnonce value=%s>\'; document.body.appendChild(f); f.submit(); } return false;" style="color:red;">Hapus</a>', 
                $item['id'], $nonce 
            )
        ];
        
        // Tambahkan foto kecil jika ada
        $thumb = '';
        if (!empty($item['foto'])) {
            $thumb = sprintf('<img src="%s" style="width:32px; height:32px; object-fit:cover; border-radius:4px; float:left; margin-right:10px;" />', esc_url($item['foto']));
        }
        
        return sprintf( '%1$s<strong>%2$s</strong> %3$s', $thumb, esc_html($item['nama_desa']), $this->row_actions( $actions ) );
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item['id']);
    }

    public function prepare_items() {
        global $wpdb;
        $table_desa     = $wpdb->prefix . 'dw_desa';
        $table_wisata   = $wpdb->prefix . 'dw_wisata';
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        
        $this->process_bulk_action();
        
        $per_page = 20;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        
        $where_sql = '';
        if ( ! empty( $_REQUEST['s'] ) ) {
            $search = '%' . $wpdb->esc_like( stripslashes( $_REQUEST['s'] ) ) . '%';
            $where_sql = $wpdb->prepare(" WHERE d.nama_desa LIKE %s OR d.kabupaten LIKE %s", $search, $search);
        }

        // Hitung total item
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa d $where_sql");

        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;
        
        $orderby = $_REQUEST['orderby'] ?? 'created_at';
        $order = $_REQUEST['order'] ?? 'desc';
        
        // Whitelist orderby untuk keamanan
        $allowed_sorts = ['nama_desa', 'created_at', 'count_wisata', 'count_pedagang'];
        if(!in_array($orderby, $allowed_sorts)) $orderby = 'created_at';

        // --- QUERY UTAMA DENGAN SUB-QUERY HITUNGAN ---
        // Ini lebih efisien daripada melakukan query COUNT di dalam loop (N+1 problem)
        $query = "SELECT d.*, 
                    (SELECT COUNT(w.id) FROM $table_wisata w WHERE w.id_desa = d.id AND w.status != 'trash') as count_wisata,
                    (SELECT COUNT(p.id) FROM $table_pedagang p WHERE p.id_desa = d.id AND p.status_akun != 'suspend') as count_pedagang
                  FROM $table_desa d 
                  $where_sql 
                  ORDER BY $orderby $order 
                  LIMIT %d OFFSET %d";
                  
        $this->items = $wpdb->get_results( $wpdb->prepare($query, $per_page, $offset), ARRAY_A );
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}
?>