<?php
/**
 * File Path: includes/list-tables/class-dw-zona-ongkir-list-table.php
 *
 * --- PERUBAHAN (STRATEGI ONGKIR BARU) ---
 * - File ini tidak lagi digunakan. Tabel `dw_zona_ongkir_lokal` (dikelola admin)
 * telah digantikan oleh logika pengiriman per-pedagang.
 * - File `page-ongkir.php` yang memanggil kelas ini juga sudah dikosongkan.
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DW_Zona_Ongkir_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'Zona Ongkir', 'plural' => 'Zona Ongkir', 'ajax' => false ] );
        // Konstruktor kosong untuk mencegah error, tapi file ini tidak akan dipanggil.
    }
    
    public function prepare_items() {
        $this->_column_headers = [ [], [], [] ];
        $this->items = []; // Kosongkan
    }
    
    public function no_items() {
        _e( 'Tabel ini tidak digunakan lagi. Pengaturan ongkir ojek lokal sekarang dikelola oleh Pedagang.', 'desa-wisata-core' );
    }

    // Sisanya bisa dikosongkan
}
