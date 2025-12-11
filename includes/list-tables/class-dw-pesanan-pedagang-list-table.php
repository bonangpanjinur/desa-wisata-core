<?php
/**
 * File Path: includes/list-tables/class-dw-pesanan-pedagang-list-table.php
 *
 * Menampilkan list table pesanan untuk pedagang.
 *
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) exit;

// --- PERBAIKAN (ANALISIS ANDA) ---
// Menambahkan require_once untuk 'cart.php'.
// File 'orders.php' yang lama telah dihapus, dan fungsinya (seperti dw_get_pedagang_orders)
// dipindahkan ke 'cart.php'. File ini membutuhkannya.
if ( defined( 'DW_CORE_PLUGIN_DIR' ) ) {
    require_once DW_CORE_PLUGIN_DIR . 'includes/cart.php';
}
// --- AKHIR PERBAIKAN ---


if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

// PERBAIKAN: Menghapus referensi ke file includes/orders.php yang sudah Anda hapus.
// Fungsionalitasnya dipindahkan ke includes/cart.php
// require_once DW_CORE_PLUGIN_DIR . 'includes/orders.php'; // <--- DIHAPUS

class DW_Pesanan_Pedagang_List_Table extends WP_List_Table {

    private $pedagang_id;
    private $status_counts;

    public function __construct($pedagang_id) {
        $this->pedagang_id = $pedagang_id;
        parent::__construct([
            'singular' => 'Pesanan',
            'plural'   => 'Pesanan',
            'ajax'     => false
        ]);
    }

    protected function get_views() {
        $current = $this->get_current_status_filter();
        $base_url = remove_query_arg(['status', 'paged']);
        
        $views = [];
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            $base_url,
            $current === 'all' ? 'current' : '',
            'Semua',
            $this->get_total_orders_count()
        );

        $statuses = $this->get_order_statuses();
        foreach ($statuses as $status => $label) {
            $count = $this->get_total_orders_count($status);
            $views[$status] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                add_query_arg('status', $status, $base_url),
                $current === $status ? 'current' : '',
                $label,
                $count
            );
        }
        return $views;
    }

    private function get_current_status_filter() {
        return isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
    }

    private function get_order_statuses() {
        return [
            'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
            'diproses'            => 'Diproses',
            'dikirim'             => 'Dikirim',
            'selesai'             => 'Selesai',
            'dibatalkan'          => 'Dibatalkan',
        ];
    }

    private function get_total_orders_count($status = '') {
        if (!isset($this->status_counts)) {
            $this->status_counts = [];
            $all_statuses = $this->get_order_statuses();
            foreach ($all_statuses as $key => $label) {
                // PERBAIKAN: Memanggil fungsi dari cart.php
                $this->status_counts[$key] = dw_get_pedagang_orders_count($this->pedagang_id, $key);
            }
            $this->status_counts['all'] = array_sum($this->status_counts);
        }
        return $status === 'all' || empty($status) ? $this->status_counts['all'] : ($this->status_counts[$status] ?? 0);
    }

    public function get_columns() {
        return [
            'kode_unik'         => 'ID Pesanan',
            'tanggal_transaksi' => 'Tanggal',
            'status_pesanan'    => 'Status Toko',
            'total_transaksi'   => 'Total (Global)',
            'pembeli'           => 'Pembeli',
            'status_transaksi'  => 'Status Pembayaran',
            'actions'           => 'Aksi',
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'kode_unik':
                return '<strong>#' . esc_html($item['kode_unik']) . '</strong>';
            case 'tanggal_transaksi':
                return date_i18n('d M Y, H:i', strtotime($item['tanggal_transaksi']));
            case 'status_pesanan':
                // PERBAIKAN: Memanggil fungsi badge baru yang akan dibuat di helpers.php
                // Kita asumsikan fungsi dw_get_order_status_badge ada di helpers.php
                if (function_exists('dw_get_order_status_badge')) {
                    return dw_get_order_status_badge($item['status_pesanan']);
                }
                return esc_html($item['status_pesanan']);
            case 'total_transaksi':
                return 'Rp ' . number_format($item['total_transaksi'], 0, ',', '.');
            case 'pembeli':
                $user_info = get_userdata($item['id_pembeli']);
                $nama_pembeli = $user_info ? $user_info->display_name : 'Guest';
                return esc_html($nama_pembeli) . '<br><small>' . esc_html($item['nama_penerima']) . ' (' . esc_html($item['no_hp']) . ')</small>';
            case 'status_transaksi':
                 // PERBAIKAN: Memanggil fungsi badge baru yang akan dibuat di helpers.php
                 if (function_exists('dw_get_order_status_badge')) {
                     return dw_get_order_status_badge($item['status_transaksi']);
                 }
                 return esc_html($item['status_transaksi']);
            case 'actions':
                // TODO: Tambah link ke detail sub_order
                return sprintf('<a href="%s" class="button button-small">Lihat Detail</a>', 
                    get_admin_url(null, 'admin.php?page=dw-pesanan-pedagang&action=view&order_id=' . $item['order_id'] . '&sub_order_id=' . $item['sub_order_id'])
                );
            default:
                return print_r($item, true);
        }
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $status_filter = $this->get_current_status_filter();
        if ($status_filter === 'all') $status_filter = '';

        $total_items = $this->get_total_orders_count($status_filter);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        // PERBAIKAN: Memanggil fungsi dari cart.php
        $this->items = dw_get_pedagang_orders($this->pedagang_id, $per_page, $current_page, $status_filter);
    }
}
?>