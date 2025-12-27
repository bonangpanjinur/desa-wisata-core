<?php
/**
 * File: includes/list-tables/class-dw-verifikator-list-table.php
 * Deskripsi: List Table untuk menampilkan daftar akun Verifikator UMKM.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class DW_Verifikator_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'verifikator',
            'plural'   => 'verifikator',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'user'     => 'Nama / Username',
            'v_code'   => 'Kode Unik Agen',
            'merchants' => 'UMKM Binaan',
            'balance'  => 'Saldo Komisi',
            'registered' => 'Tgl Daftar'
        ];
    }

    protected function get_sortable_columns() {
        return [
            'registered' => ['registered', true]
        ];
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $args = [
            'role' => 'verifikator_umkm',
            'number' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby' => 'user_registered',
            'order' => 'DESC'
        ];

        if (!empty($_REQUEST['s'])) {
            $args['search'] = '*' . esc_attr($_REQUEST['s']) . '*';
            $args['search_columns'] = ['user_login', 'display_name', 'user_email'];
        }

        $user_query = new WP_User_Query($args);
        $this->items = $user_query->get_results();

        $this->set_pagination_args([
            'total_items' => $user_query->get_total(),
            'per_page'    => $per_page
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'user':
                $edit_link = get_edit_user_link($item->ID);
                return '<strong><a href="'.$edit_link.'">' . esc_html($item->display_name) . '</a></strong><br/><small>' . esc_html($item->user_email) . '</small>';
            case 'v_code':
                $code = get_user_meta($item->ID, 'dw_verifier_code', true);
                return $code ? '<code style="font-weight:bold; color:#2271b1">' . esc_html($code) . '</code>' : '<span style="color:#666">Belum Ada</span>';
            case 'merchants':
                global $wpdb;
                $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dw_pedagang WHERE id_verifikator = %d", $item->ID));
                return '<strong>' . (int)$count . '</strong> Pedagang';
            case 'balance':
                $bal = (float) get_user_meta($item->ID, 'dw_balance', true);
                return '<span style="color:#46b450; font-weight:bold;">' . dw_format_rupiah($bal) . '</span>';
            case 'registered':
                return date('d/m/Y H:i', strtotime($item->user_registered));
            case 'cb':
                return sprintf('<input type="checkbox" name="users[]" value="%s" />', $item->ID);
            default:
                return '-';
        }
    }
}