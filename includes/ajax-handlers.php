<?php
// includes/ajax-handlers.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle AJAX request for fetching cities based on province ID.
 */
function dw_get_cities_handler() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dw_admin_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Validasi keamanan gagal.' ) );
    }

    $province_id = isset( $_POST['province_id'] ) ? sanitize_text_field( $_POST['province_id'] ) : '';

    if ( empty( $province_id ) ) {
        wp_send_json_error( array( 'message' => 'ID Provinsi tidak ditemukan.' ) );
    }

    if ( class_exists( 'DW_Address_API' ) ) {
        $cities = DW_Address_API::get_cities( $province_id );
        if ( is_wp_error( $cities ) ) {
            wp_send_json_error( array( 'message' => $cities->get_error_message() ) );
        } else {
            wp_send_json_success( array( 'cities' => $cities ) );
        }
    } else {
        wp_send_json_error( array( 'message' => 'Class API tidak ditemukan.' ) );
    }
}
add_action( 'wp_ajax_dw_get_cities', 'dw_get_cities_handler' );

/**
 * Handle AJAX request for fetching subdistricts.
 */
function dw_get_subdistricts_handler() {
    check_ajax_referer( 'dw_admin_nonce', 'nonce' );
    $city_id = isset( $_POST['city_id'] ) ? sanitize_text_field( $_POST['city_id'] ) : '';

    if ( class_exists( 'DW_Address_API' ) && !empty($city_id) ) {
        $subdistricts = DW_Address_API::get_subdistricts( $city_id );
        if ( ! empty( $subdistricts ) && ! is_wp_error( $subdistricts ) ) {
            wp_send_json_success( array( 'subdistricts' => $subdistricts ) );
        } else {
            wp_send_json_error( array( 'message' => 'Gagal mengambil data kecamatan.' ) );
        }
    } else {
        wp_send_json_error( array( 'message' => 'API Error atau ID Kota kosong.' ) );
    }
}
add_action( 'wp_ajax_dw_get_subdistricts', 'dw_get_subdistricts_handler' );

/**
 * Handle AJAX for Saving Promotions (Voucher)
 */
function dw_save_promotion_ajax_handler() {
    check_ajax_referer( 'dw_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    $promo_data = array(
        'code'          => sanitize_text_field($_POST['code']),
        'discount_type' => sanitize_text_field($_POST['discount_type']),
        'amount'        => floatval($_POST['amount']),
        'start_date'    => sanitize_text_field($_POST['start_date']),
        'end_date'      => sanitize_text_field($_POST['end_date']),
        'min_purchase'  => floatval($_POST['min_purchase']),
        'status'        => 'active'
    );

    $promo_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

    if ( class_exists( 'DW_Promotions' ) ) {
        $promotions = new DW_Promotions();
        $result = ($promo_id > 0) ? $promotions->update_promotion( $promo_id, $promo_data ) : $promotions->add_promotion( $promo_data );
        
        if ( $result ) {
            wp_send_json_success( array( 'message' => 'Promosi berhasil disimpan.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Gagal menyimpan. Cek kode duplikat.' ) );
        }
    }
}
add_action( 'wp_ajax_dw_save_promotion', 'dw_save_promotion_ajax_handler' );

/**
 * NEW: Handle AJAX for Saving Ad Pricing Settings
 * Menyimpan paket harga untuk Banner, Wisata, dan Produk
 */
function dw_save_ad_settings_handler() {
    // 1. Verifikasi Keamanan
    check_ajax_referer( 'dw_admin_nonce', 'nonce' );

    // 2. Verifikasi Izin
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized access.' ) );
    }

    // 3. Ambil data mentah
    $raw_data = isset( $_POST['ad_packages'] ) ? $_POST['ad_packages'] : array();

    // 4. Sanitasi Data (PENTING untuk keamanan array multidimensi)
    $clean_data = array(
        'banner' => array(),
        'wisata' => array(),
        'produk' => array()
    );

    foreach ( array('banner', 'wisata', 'produk') as $type ) {
        if ( isset( $raw_data[$type] ) && is_array( $raw_data[$type] ) ) {
            foreach ( $raw_data[$type] as $item ) {
                if ( ! empty( $item['name'] ) && ! empty( $item['days'] ) && ! empty( $item['price'] ) ) {
                    $clean_data[$type][] = array(
                        'name'  => sanitize_text_field( $item['name'] ),
                        'days'  => intval( $item['days'] ),
                        'price' => floatval( $item['price'] )
                    );
                }
            }
        }
        // Urutkan berdasarkan harga termurah atau hari tersedikit (opsional)
        // usort($clean_data[$type], function($a, $b) { return $a['price'] - $b['price']; });
    }

    // 5. Simpan ke WP Options
    if ( update_option( 'dw_ad_packages', $clean_data ) ) {
        wp_send_json_success( array( 'message' => 'Pengaturan harga berhasil diperbarui.' ) );
    } else {
        // Jika data tidak berubah, update_option return false, tapi ini bukan error sebenarnya.
        wp_send_json_success( array( 'message' => 'Pengaturan tersimpan (tidak ada perubahan data).' ) );
    }
}
add_action( 'wp_ajax_dw_save_ad_settings', 'dw_save_ad_settings_handler' );