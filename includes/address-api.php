<?php
/**
 * File Name:   address-api.php
 * File Folder: includes/
 * File Path:   includes/address-api.php
 *
 * Mengelola integrasi dengan API eksternal untuk data wilayah Indonesia.
 * API Source: https://wilayah.id/
 *
 * PENINGKATAN: Mengurangi durasi cache menjadi 1 hari.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Durasi cache (dalam detik) - DIUBAH menjadi 1 hari
define('DW_WILAYAH_CACHE_DURATION', DAY_IN_SECONDS); // Cache selama 1 hari

/**
 * Mengambil daftar semua provinsi dari API.
 * Hasilnya disimpan dalam cache.
 *
 * @return array Daftar provinsi atau array kosong jika gagal.
 */
function dw_get_api_provinsi() {
    $cache_key = 'dw_api_provinsi_v3';
    $provinsi = wp_cache_get( $cache_key, 'desa_wisata' );

    if ( false === $provinsi ) {
        $response = wp_remote_get( 'https://wilayah.id/api/provinces.json' );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            error_log("Gagal mengambil data provinsi dari wilayah.id: " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)));
            return [];
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $provinsi = ( isset( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : [];
        wp_cache_set( $cache_key, $provinsi, 'desa_wisata', DW_WILAYAH_CACHE_DURATION ); // Gunakan durasi baru
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

    $cache_key = 'dw_api_kabupaten_v3_' . $provinsi_id;
    $kabupaten = wp_cache_get( $cache_key, 'desa_wisata' );

    if ( false === $kabupaten ) {
        $response = wp_remote_get( "https://wilayah.id/api/regencies/{$provinsi_id}.json" );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            error_log("Gagal mengambil data kabupaten (Prov ID: {$provinsi_id}) dari wilayah.id: " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)));
            return [];
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $kabupaten = ( isset( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : [];
        wp_cache_set( $cache_key, $kabupaten, 'desa_wisata', DW_WILAYAH_CACHE_DURATION ); // Gunakan durasi baru
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

    $cache_key = 'dw_api_kecamatan_v3_' . $kabupaten_id;
    $kecamatan = wp_cache_get( $cache_key, 'desa_wisata' );

    if ( false === $kecamatan ) {
        $response = wp_remote_get( "https://wilayah.id/api/districts/{$kabupaten_id}.json" );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
             error_log("Gagal mengambil data kecamatan (Kab ID: {$kabupaten_id}) dari wilayah.id: " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)));
            return [];
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $kecamatan = ( isset( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : [];
        wp_cache_set( $cache_key, $kecamatan, 'desa_wisata', DW_WILAYAH_CACHE_DURATION ); // Gunakan durasi baru
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

    $cache_key = 'dw_api_desa_v3_' . $kecamatan_id;
    $desa = wp_cache_get( $cache_key, 'desa_wisata' );

    if ( false === $desa ) {
        $response = wp_remote_get( "https://wilayah.id/api/villages/{$kecamatan_id}.json" );
         if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
             error_log("Gagal mengambil data desa (Kec ID: {$kecamatan_id}) dari wilayah.id: " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)));
            return [];
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $desa = ( isset( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : [];
        wp_cache_set( $cache_key, $desa, 'desa_wisata', DW_WILAYAH_CACHE_DURATION ); // Gunakan durasi baru
    }
    return $desa;
}

