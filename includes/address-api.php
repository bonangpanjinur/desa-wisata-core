<?php
// includes/address-api.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Address_API {

    /**
     * URL API Wilayah Indonesia (Wilayah.id)
     */
    private static function get_base_url() {
        return 'https://wilayah.id/api';
    }

    /**
     * Fetch all provinces
     * Endpoint: https://wilayah.id/api/provinces.json
     */
    public static function get_provinces() {
        // 1. Cek Cache (Cache Key Baru '_v4' untuk reset)
        $cached_provinces = get_transient( 'dw_provinces_list_v4' );
        if ( false !== $cached_provinces && !empty($cached_provinces) ) {
            return $cached_provinces;
        }

        $provinces = array();

        // 2. Fetch API
        $response = wp_remote_get( self::get_base_url() . '/provinces.json', array( 'timeout' => 10 ) );

        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            // Struktur Wilayah.id: { "data": [ { "code": "11", "name": "ACEH" }, ... ] }
            if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
                foreach ( $data['data'] as $prov ) {
                    $provinces[] = array(
                        'province_id' => $prov['code'], // Menggunakan 'code' sebagai ID
                        'province'    => ucwords(strtolower($prov['name']))
                    );
                }
                
                // Simpan cache 30 hari
                set_transient( 'dw_provinces_list_v4', $provinces, 30 * DAY_IN_SECONDS );
                return $provinces;
            }
        }

        // 3. Fallback jika API Gagal
        if ( empty( $provinces ) ) {
            $provinces = self::get_fallback_provinces();
        }

        return $provinces;
    }

    /**
     * Fetch cities by province ID
     * Endpoint: https://wilayah.id/api/regencies/[PROVINCE_CODE].json
     */
    public static function get_cities( $province_id ) {
        if ( empty( $province_id ) ) return array();

        $cache_key = 'dw_cities_v4_' . $province_id;
        $cached_cities = get_transient( $cache_key );

        if ( false !== $cached_cities && !empty($cached_cities) ) {
            return $cached_cities;
        }

        $cities = array();

        // Fetch API
        $url = self::get_base_url() . "/regencies/{$province_id}.json";
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );

        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
                foreach ( $data['data'] as $city ) {
                    // Format Name: "KABUPATEN ACEH SELATAN" atau "KOTA BANDA ACEH"
                    $full_name = $city['name'];
                    
                    // Deteksi Tipe (Kota/Kabupaten)
                    if ( stripos( $full_name, 'KOTA ' ) === 0 ) {
                        $type = 'Kota';
                        $clean_name = trim( substr( $full_name, 5 ) ); // Hapus 'KOTA '
                    } elseif ( stripos( $full_name, 'KABUPATEN ' ) === 0 ) {
                        $type = 'Kabupaten';
                        $clean_name = trim( substr( $full_name, 10 ) ); // Hapus 'KABUPATEN '
                    } else {
                        $type = 'Kabupaten'; // Default
                        $clean_name = $full_name;
                    }

                    $cities[] = array(
                        'city_id'   => $city['code'], // Menggunakan 'code'
                        'city_name' => ucwords(strtolower($clean_name)),
                        'type'      => $type
                    );
                }

                set_transient( $cache_key, $cities, 30 * DAY_IN_SECONDS );
                return $cities;
            }
        }

        // Fallback Error
        return array(
            array('city_id' => '0', 'city_name' => 'Gagal Memuat Kota (Cek Koneksi)', 'type' => 'System')
        );
    }

    /**
     * Fetch Districts (Kecamatan)
     * Endpoint: https://wilayah.id/api/districts/[REGENCY_CODE].json
     */
    public static function get_subdistricts( $city_id ) {
        if ( empty( $city_id ) ) return array();

        $cache_key = 'dw_districts_v4_' . $city_id;
        $cached = get_transient( $cache_key );
        if ( $cached ) return $cached;

        $subdistricts = array();
        $url = self::get_base_url() . "/districts/{$city_id}.json";
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );

        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
                foreach ( $data['data'] as $dist ) {
                    $subdistricts[] = array(
                        'subdistrict_id'   => $dist['code'],
                        'subdistrict_name' => ucwords(strtolower($dist['name']))
                    );
                }
                set_transient( $cache_key, $subdistricts, 30 * DAY_IN_SECONDS );
            }
        }
        
        return $subdistricts;
    }

    /**
     * Data Statis Provinsi (Fallback Manual)
     * Kode disesuaikan dengan standar BPS/Wilayah.id (2 digit)
     */
    private static function get_fallback_provinces() {
        return array(
            array("province_id" => "11", "province" => "Aceh"),
            array("province_id" => "12", "province" => "Sumatera Utara"),
            array("province_id" => "13", "province" => "Sumatera Barat"),
            array("province_id" => "14", "province" => "Riau"),
            array("province_id" => "15", "province" => "Jambi"),
            array("province_id" => "16", "province" => "Sumatera Selatan"),
            array("province_id" => "17", "province" => "Bengkulu"),
            array("province_id" => "18", "province" => "Lampung"),
            array("province_id" => "19", "province" => "Kep. Bangka Belitung"),
            array("province_id" => "21", "province" => "Kep. Riau"),
            array("province_id" => "31", "province" => "DKI Jakarta"),
            array("province_id" => "32", "province" => "Jawa Barat"),
            array("province_id" => "33", "province" => "Jawa Tengah"),
            array("province_id" => "34", "province" => "DI Yogyakarta"),
            array("province_id" => "35", "province" => "Jawa Timur"),
            array("province_id" => "36", "province" => "Banten"),
            array("province_id" => "51", "province" => "Bali"),
            array("province_id" => "52", "province" => "Nusa Tenggara Barat"),
            array("province_id" => "53", "province" => "Nusa Tenggara Timur"),
            array("province_id" => "61", "province" => "Kalimantan Barat"),
            array("province_id" => "62", "province" => "Kalimantan Tengah"),
            array("province_id" => "63", "province" => "Kalimantan Selatan"),
            array("province_id" => "64", "province" => "Kalimantan Timur"),
            array("province_id" => "65", "province" => "Kalimantan Utara"),
            array("province_id" => "71", "province" => "Sulawesi Utara"),
            array("province_id" => "72", "province" => "Sulawesi Tengah"),
            array("province_id" => "73", "province" => "Sulawesi Selatan"),
            array("province_id" => "74", "province" => "Sulawesi Tenggara"),
            array("province_id" => "75", "province" => "Gorontalo"),
            array("province_id" => "76", "province" => "Sulawesi Barat"),
            array("province_id" => "81", "province" => "Maluku"),
            array("province_id" => "82", "province" => "Maluku Utara"),
            array("province_id" => "91", "province" => "Papua Barat"),
            array("province_id" => "94", "province" => "Papua")
        );
    }

    /**
     * Hitung Ongkir (Flat Rate Manual)
     */
    public static function get_cost( $origin, $destination, $weight, $courier ) {
        // Flat Rate Sederhana
        $cost_value = 15000 * ceil($weight/1000);

        return array(
            array(
                'service' => 'STD',
                'description' => 'Standard Delivery',
                'cost' => array(
                    array(
                        'value' => $cost_value,
                        'etd' => '3-7 Hari',
                        'note' => 'Tarif Estimasi (Manual)'
                    )
                )
            )
        );
    }
}