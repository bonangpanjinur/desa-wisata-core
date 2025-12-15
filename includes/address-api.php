<?php
/**
 * File Name:   address-api.php
 * File Folder: includes/
 * File Path:   includes/address-api.php
 *
 * Mengelola integrasi dengan API eksternal untuk data wilayah Indonesia.
 * API Source: https://wilayah.id/
 *
 * PERBAIKAN: Menggunakan `set_transient` alih-alih `wp_cache_set` agar data
 * tersimpan di database dan tidak hilang saat refresh halaman (Persistent Cache).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Durasi cache (dalam detik) - 1 Minggu agar sangat cepat
define('DW_WILAYAH_CACHE_DURATION', WEEK_IN_SECONDS); 

/**
 * Mengambil daftar semua provinsi dari API.
 * Hasilnya disimpan dalam transient database.
 *
 * @return array Daftar provinsi atau array kosong jika gagal.
 */
function dw_get_api_provinsi() {
    $cache_key = 'dw_transient_provinsi_v4'; // Ganti versi key untuk refresh cache
    $provinsi = get_transient( $cache_key ); // Ubah ke get_transient

    if ( false === $provinsi ) {
        $response = wp_remote_get( 'https://wilayah.id/api/provinces.json', ['timeout' => 15] ); // Tambah timeout
        
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            error_log("Gagal mengambil data provinsi: " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)));
            return [];
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $provinsi = ( isset( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : [];
        
        if (!empty($provinsi)) {
            set_transient( $cache_key, $provinsi, DW_WILAYAH_CACHE_DURATION ); // Simpan ke DB
        }
    }
    return $provinsi;
}

/**
 * Mengambil daftar kabupaten/kota berdasarkan ID provinsi.
 *
 * @param string $provinsi_id ID provinsi dari API.
 * @return array Daftar kabupaten atau array kosong jika gagal.
 */
function dw_get_api_kabupaten( $provinsi_id ) {
    $provinsi_id = preg_replace( '/[^0-9]/', '', $provinsi_id );
    if ( empty( $provinsi_id ) ) return [];

    $cache_key = 'dw_transient_kabupaten_v4_' . $provinsi_id;
    $kabupaten = get_transient( $cache_key );

    if ( false === $kabupaten ) {
        $response = wp_remote_get( "https://wilayah.id/api/regencies/{$provinsi_id}.json", ['timeout' => 15] );
        
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            error_log("Gagal mengambil data kabupaten (Prov ID: {$provinsi_id})");
            return [];
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $kabupaten = ( isset( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : [];
        
        if (!empty($kabupaten)) {
            set_transient( $cache_key, $kabupaten, DW_WILAYAH_CACHE_DURATION );
        }
    }
    return $kabupaten;
}

/**
 * Mengambil daftar kecamatan berdasarkan ID kabupaten/kota.
 *
 * @param string $kabupaten_id ID kabupaten dari API.
 * @return array Daftar kecamatan atau array kosong jika gagal.
 */
function dw_get_api_kecamatan( $kabupaten_id ) {
    $kabupaten_id = preg_replace( '/[^0-9.]/', '', $kabupaten_id );
    if ( empty( $kabupaten_id ) ) return [];

    $cache_key = 'dw_transient_kecamatan_v4_' . $kabupaten_id;
    $kecamatan = get_transient( $cache_key );

    if ( false === $kecamatan ) {
        $response = wp_remote_get( "https://wilayah.id/api/districts/{$kabupaten_id}.json", ['timeout' => 15] );
        
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
             error_log("Gagal mengambil data kecamatan (Kab ID: {$kabupaten_id})");
            return [];
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $kecamatan = ( isset( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : [];
        
        if (!empty($kecamatan)) {
            set_transient( $cache_key, $kecamatan, DW_WILAYAH_CACHE_DURATION );
        }
    }
    return $kecamatan;
}

/**
 * Mengambil daftar kelurahan/desa berdasarkan ID kecamatan.
 *
 * @param string $kecamatan_id ID kecamatan dari API.
 * @return array Daftar desa atau array kosong jika gagal.
 */
function dw_get_api_desa( $kecamatan_id ) {
    $kecamatan_id = preg_replace( '/[^0-9.]/', '', $kecamatan_id );
    if ( empty( $kecamatan_id ) ) return [];

    $cache_key = 'dw_transient_desa_v4_' . $kecamatan_id;
    $desa = get_transient( $cache_key );

    if ( false === $desa ) {
        $response = wp_remote_get( "https://wilayah.id/api/villages/{$kecamatan_id}.json", ['timeout' => 15] );
        
         if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
             error_log("Gagal mengambil data desa (Kec ID: {$kecamatan_id})");
            return [];
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $desa = ( isset( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : [];
        
        if (!empty($desa)) {
            set_transient( $cache_key, $desa, DW_WILAYAH_CACHE_DURATION );
        }
    }
    return $desa;
}
?>