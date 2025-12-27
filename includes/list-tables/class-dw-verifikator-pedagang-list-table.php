<?php
/**
 * File: includes/list-tables/class-dw-verifikator-pedagang-list-table.php
 * Deskripsi: Class tabel untuk menampilkan UMKM binaan verifikator.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class DW_Verifikator_Pedagang_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'umkm_binaan',
            'plural'   => 'umkm_binaan',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'name'     => 'Nama UMKM',
            'address'  => 'Alamat Desa',
            'status'   => 'Status Akun',
            'income'   => 'Kontribusi Saldo',
            'registered' => 'Tgl Daftar'
        ];
    }

    public function prepare_items() {
        $current_user_id = get_current_user_id();
        $per_page = 20;
        
        $args = [
            'role'       => 'pedagang',
            'meta_query' => [
                [
                    'key'   => 'dw_verified_by',
                    'value' => $current_user_id
                ]
            ],
            'number' => $per_page,
            'offset' => ($this->get_pagenum() - 1) * $per_page
        ];

        $user_query = new WP_User_Query($args);
        $this->items = $user_query->get_results();
        
        $this->set_pagination_args([
            'total_items' => $user_query->get_total(),
            'per_page'    => $per_page
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'name':
                return '<strong>' . esc_html($item->display_name) . '</strong><br/><small>' . esc_html($item->user_email) . '</small>';
            case 'address':
                $desa = get_user_meta($item->ID, 'dw_alamat_desa', true);
                return $desa ? esc_html($desa) : '-';
            case 'status':
                $is_verified = get_user_meta($item->ID, 'dw_is_verified', true);
                return $is_verified 
                    ? '<span style="color:white; background:green; padding:2px 8px; border-radius:10px; font-size:11px">Aktif</span>' 
                    : '<span style="color:white; background:orange; padding:2px 8px; border-radius:10px; font-size:11px">Menunggu</span>';
            case 'income':
                // Fitur pengembangan: total komisi yang masuk dari pedagang ini
                return 'Rp ' . number_format(0, 0, ',', '.');
            case 'registered':
                return date('d/m/Y', strtotime($item->user_registered));
            case 'cb':
                return sprintf('<input type="checkbox" name="users[]" value="%s" />', $item->ID);
            default:
                return '-';
        }
    }
}