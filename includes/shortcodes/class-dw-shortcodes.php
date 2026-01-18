<?php
/**
 * Class DW_Shortcodes
 * Central registry untuk semua shortcode di plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Shortcodes {

    public static function init() {
        // Init Shortcode Ojek
        if (class_exists('DW_Shortcode_Ojek')) {
            DW_Shortcode_Ojek::init();
        }

        // Init Shortcode Desa & Wisata
        if (class_exists('DW_Shortcode_Desa')) {
            DW_Shortcode_Desa::init();
        }

        // Init Shortcode Cart & Checkout (Custom)
        if (class_exists('DW_Shortcode_Cart')) {
            DW_Shortcode_Cart::init();
        }
        if (class_exists('DW_Shortcode_Checkout')) {
            DW_Shortcode_Checkout::init();
        }
        
        // Init Shortcode Pedagang & Produk
        if (class_exists('DW_Shortcode_Pedagang')) {
            DW_Shortcode_Pedagang::init();
        }
        if (class_exists('DW_Shortcode_Product_List')) {
            DW_Shortcode_Product_List::init();
        }

        // FASE 4: Init Shortcode Wallet
        if (class_exists('DW_Shortcode_Wallet')) {
            DW_Shortcode_Wallet::init();
        }
        
        // Shortcode lainnya...
        if (class_exists('DW_Shortcode_Verifikator')) {
            DW_Shortcode_Verifikator::init();
        }
        if (class_exists('DW_Shortcode_POS')) {
            DW_Shortcode_POS::init();
        }
    }
}