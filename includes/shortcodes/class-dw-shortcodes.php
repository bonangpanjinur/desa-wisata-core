<?php
// includes/shortcodes/class-dw-shortcodes.php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class DW_Shortcodes
 * Bertugas sebagai loader utama untuk semua modul shortcode.
 */
class DW_Shortcodes {

    public function __construct() {
        $this->load_dependencies();
        $this->init_shortcodes();
    }

    private function load_dependencies() {
        $path = plugin_dir_path( __FILE__ );

        // 1. Modul E-commerce
        require_once $path . 'class-dw-shortcode-product-list.php';
        require_once $path . 'class-dw-shortcode-cart.php';
        require_once $path . 'class-dw-shortcode-checkout.php';

        // 2. Modul Dashboard Role
        require_once $path . 'class-dw-shortcode-desa.php';
        require_once $path . 'class-dw-shortcode-pedagang.php';
        require_once $path . 'class-dw-shortcode-verifikator.php';
        require_once $path . 'class-dw-shortcode-ojek.php';
        
        // 3. Modul Kasir (BARU)
        require_once $path . 'class-dw-shortcode-pos.php';
    }

    private function init_shortcodes() {
        // Inisialisasi setiap kelas
        new DW_Shortcode_Product_List();
        new DW_Shortcode_Cart();
        new DW_Shortcode_Checkout();
        
        new DW_Shortcode_Desa();
        new DW_Shortcode_Pedagang();
        new DW_Shortcode_Verifikator();
        new DW_Shortcode_Ojek();
        
        new DW_Shortcode_POS(); // Init POS
    }
}