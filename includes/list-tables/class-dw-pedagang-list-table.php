<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DW_Pedagang_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'pedagang',
            'plural'   => 'pedagang',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'foto_profil' => 'Profil',
            'nama_toko'   => 'Nama Toko',
            'pemilik'     => 'Pemilik',
            'lokasi'      => 'Lokasi & Relasi',
            'status'      => 'Status',
            'tanggal'     => 'Terdaftar',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'nama_toko' => [ 'nama_toko', false ],
            'status'    => [ 'status_akun', false ],
            'tanggal'   => [ 'created_at', false ],
        ];
    }

    // ... (column_default, column_cb, dll SAMA SEPERTI SEBELUMNYA) ...
    // Saya persingkat bagian display kolom agar fokus ke LOGIKA FILTERING di prepare_items

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'tanggal': return date_i18n(get_option('date_format'), strtotime($item['created_at']));
            default: return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
        }
    }
    
    protected function column_cb( $item ) {
        return sprintf('<input type="checkbox" name="pedagang[]" value="%s" />', $item['id']);
    }

    protected function column_foto_profil( $item ) {
        $url = !empty($item['foto_profil']) ? $item['foto_profil'] : 'https://placehold.co/50x50/e2e8f0/64748b?text=IMG';
        return sprintf('<img src="%s" style="width:40px; height:40px; object-fit:cover; border-radius:50%%;" />', esc_url($url));
    }

    protected function column_nama_toko( $item ) {
        $edit_url = admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $item['id']);
        $delete_url = admin_url('admin.php?page=dw-pedagang&action=dw_delete&id=' . $item['id'] . '&_wpnonce=' . wp_create_nonce('dw_delete_pedagang_action'));
        
        return sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong><div class="row-actions"><span class="edit"><a href="%s">Edit</a> | </span><span class="trash"><a href="%s" onclick="return confirm(\'Hapus?\')" style="color:red">Hapus</a></span></div>',
            $edit_url, esc_html($item['nama_toko']), $edit_url, $delete_url
        );
    }

    protected function column_pemilik($item) {
        return '<strong>' . esc_html($item['nama_pemilik']) . '</strong><br><span style="color:#666;">' . esc_html($item['nomor_wa']) . '</span>';
    }

    protected function column_lokasi($item) {
        global $wpdb;
        $desa_name = $item['id_desa'] ? $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM {$wpdb->prefix}dw_desa WHERE id = %d", $item['id_desa'])) : 'Independen';
        return '<span class="dashicons dashicons-location"></span> ' . esc_html($desa_name);
    }

    protected function column_status($item) {
        $st = $item['status_akun'];
        $color = ($st == 'aktif') ? 'green' : 'red';
        return "<span style='color:$color; font-weight:bold;'>" . ucfirst($st) . "</span>";
    }

    // --- BAGIAN PENTING: FILTER DATA BERDASARKAN ROLE ---
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_pedagang';
        
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Cek Role User
        $current_user_id = get_current_user_id();
        $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');
        $where = "WHERE 1=1";

        if (!$is_super_admin) {
            // Jika Admin Desa, hanya tampilkan pedagang dari desanya
            $my_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $current_user_id));
            
            if ($my_desa_id) {
                $where .= " AND id_desa = " . intval($my_desa_id);
            } else {
                // Jika user admin desa tapi belum punya desa (error case), jangan tampilkan apapun
                $where .= " AND 1=0"; 
            }
        }

        // Search
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        if ($search) {
            $where .= $wpdb->prepare(" AND (nama_toko LIKE %s OR nama_pemilik LIKE %s)", "%$search%", "%$search%");
        }

        // Query
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where");
        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }
}
?>