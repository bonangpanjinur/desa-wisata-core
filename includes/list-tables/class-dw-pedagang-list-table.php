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
            'cb'        => '<input type="checkbox" />',
            'nama_toko' => 'Nama Toko',
            'pemilik'   => 'Pemilik',
            'lokasi'    => 'Lokasi & Relasi Desa', // Kolom penting
            'status'    => 'Status',
            'tanggal'   => 'Tanggal Daftar',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'nama_toko' => [ 'nama_toko', false ],
            'tanggal'   => [ 'created_at', false ],
        ];
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'tanggal': return $item['created_at'];
            default: return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
        }
    }

    protected function column_cb( $item ) {
        return sprintf('<input type="checkbox" name="pedagang[]" value="%s" />', $item['id']);
    }

    protected function column_nama_toko( $item ) {
        // Build Actions (Edit & Delete)
        $edit_url = admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $item['id']);
        $delete_nonce = wp_create_nonce('dw_delete_pedagang_action');
        $delete_url = admin_url('admin.php?page=dw-pedagang&action=dw_delete&id=' . $item['id'] . '&_wpnonce=' . $delete_nonce);

        $actions = [
            'edit'   => sprintf('<a href="%s">Edit</a>', $edit_url),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'Hapus permanen?\')" style="color:red">Hapus</a>', $delete_url),
        ];

        return sprintf('<strong><a href="%s">%s</a></strong>%s', $edit_url, esc_html($item['nama_toko']), $this->row_actions($actions));
    }

    protected function column_pemilik($item) {
        $u = get_userdata($item['id_user']);
        $login = $u ? $u->user_login : 'User Dihapus';
        return esc_html($item['nama_pemilik']) . '<br><small style="color:#666">WP: ' . $login . '</small>';
    }

    protected function column_lokasi($item) {
        global $wpdb;
        $html = '';
        
        // 1. Cek Relasi Desa
        if (!empty($item['id_desa'])) {
            $desa_name = $wpdb->get_var("SELECT nama_desa FROM {$wpdb->prefix}dw_desa WHERE id = " . intval($item['id_desa']));
            if ($desa_name) {
                $html .= '<strong style="color:#007cba">Desa: ' . esc_html($desa_name) . '</strong><br>';
            } else {
                $html .= '<strong style="color:red">Desa ID #'.$item['id_desa'].' (Not Found)</strong><br>';
            }
        } else {
            $html .= '<em style="color:#666">Independen (Pusat)</em><br>';
        }

        // 2. Alamat Fisik
        if ($item['kelurahan_nama']) {
            $html .= '<small>Kel. ' . esc_html($item['kelurahan_nama']) . '</small>';
        }
        return $html;
    }

    protected function column_status($item) {
        $st_daftar = $item['status_pendaftaran'];
        $st_akun   = $item['status_akun'];
        
        $color = ($st_akun == 'aktif') ? 'green' : 'red';
        
        return sprintf(
            'Daftar: <strong>%s</strong><br>Akun: <strong style="color:%s">%s</strong>',
            ucfirst($st_daftar),
            $color,
            ucfirst($st_akun)
        );
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_pedagang';
        
        // Pagination
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Query Utama
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }
}