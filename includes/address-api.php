<?php
// includes/address-api.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Address_API {

    /**
     * Mendapatkan API Key dari settingan
     */
    private static function get_api_key() {
        $options = get_option( 'dw_settings_ongkir', array() );
        return isset( $options['api_key'] ) ? $options['api_key'] : '';
    }

    /**
     * Mendapatkan Base URL API (Misal RajaOngkir Starter/Pro)
     */
    private static function get_base_url() {
        // Ganti 'starter' dengan 'pro' jika menggunakan akun pro
        return 'https://api.rajaongkir.com/starter'; 
    }

    /**
     * Fetch all provinces
     */
    public static function get_provinces() {
        // Cek Transient (Cache) WordPress selama 1 minggu
        $cached_provinces = get_transient( 'dw_provinces_list' );
        if ( false !== $cached_provinces ) {
            return $cached_provinces;
        }

        $api_key = self::get_api_key();
        if ( empty( $api_key ) ) return array();

        $response = wp_remote_get( self::get_base_url() . '/province', array(
            'headers' => array( 'key' => $api_key )
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'DW API Error (Provinces): ' . $response->get_error_message() );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['rajaongkir']['results'] ) ) {
            $provinces = $data['rajaongkir']['results'];
            // Simpan cache
            set_transient( 'dw_provinces_list', $provinces, WEEK_IN_SECONDS );
            return $provinces;
        }

        return array();
    }

    /**
     * Fetch cities by province ID (Perbaikan Poin 2)
     */
    public static function get_cities( $province_id ) {
        // Cek Cache spesifik per provinsi
        $cache_key = 'dw_cities_' . $province_id;
        $cached_cities = get_transient( $cache_key );

        if ( false !== $cached_cities ) {
            return $cached_cities;
        }

        $api_key = self::get_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'API Key RajaOngkir belum diatur.' );
        }

        $url = self::get_base_url() . '/city?province=' . $province_id;

        $response = wp_remote_get( $url, array(
            'headers' => array( 'key' => $api_key )
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['rajaongkir']['status']['code'] ) && $data['rajaongkir']['status']['code'] != 200 ) {
            return new WP_Error( 'api_error', $data['rajaongkir']['status']['description'] );
        }

        if ( isset( $data['rajaongkir']['results'] ) ) {
            $cities = $data['rajaongkir']['results'];
            set_transient( $cache_key, $cities, WEEK_IN_SECONDS ); // Cache 1 minggu
            return $cities;
        }

        return array();
    }

    /**
     * Mendapatkan ongkos kirim
     */
    public static function get_cost( $origin, $destination, $weight, $courier ) {
        $api_key = self::get_api_key();
        if ( empty( $api_key ) ) return new WP_Error('no_key', 'API Key missing');

        $url = self::get_base_url() . '/cost';

        $args = array(
            'headers' => array(
                'key' => $api_key,
                'content-type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weight,
                'courier' => $courier
            )
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['rajaongkir']['results'][0]['costs'] ) ) {
            return $data['rajaongkir']['results'][0]['costs'];
        }

        return new WP_Error( 'no_cost', 'Gagal mengambil data ongkir.' );
    }
}