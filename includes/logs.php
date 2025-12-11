<?php
/**
 * File Name:   logs.php
 * File Folder: includes/
 * File Path:   includes/logs.php
 *
 * Mengelola pencatatan aktivitas (logging).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Fungsi helper untuk mencatat aktivitas penting ke database.
 *
 * Fungsi ini akan merekam tindakan yang dilakukan oleh pengguna ke dalam tabel log
 * untuk keperluan audit dan pelacakan.
 *
 * @param string $action      Aksi yang dilakukan (contoh: 'PENJUAL_APPROVED', 'PROMO_CREATED').
 * @param string $description Deskripsi detail dari aktivitas.
 * @param int|null $user_id   ID pengguna yang melakukan aksi. Jika null, akan mengambil pengguna yang sedang login.
 *
 * @return void
 */
function dw_log_activity( $action, $description, $user_id = null ) {
    global $wpdb;

    // Jika user_id tidak disediakan, ambil dari pengguna yang sedang login
    if ( is_null( $user_id ) ) {
        $user_id = get_current_user_id();
    }

    $table_name = $wpdb->prefix . 'dw_logs';

    $wpdb->insert(
        $table_name,
        array(
            'user_id'     => $user_id,
            'aksi'        => sanitize_text_field( $action ),
            'keterangan'  => sanitize_textarea_field( $description ),
            'created_at'  => current_time( 'mysql' ),
        ),
        array(
            '%d', // user_id
            '%s', // aksi
            '%s', // keterangan
            '%s', // created_at
        )
    );
}

/**
 * Contoh penggunaan:
 * * // Saat admin menyetujui penjual dengan ID 123:
 * // dw_log_activity( 'PENJUAL_APPROVED', 'Admin menyetujui penjual dengan ID: 123' );
 *
 * // Saat penjual (dengan user ID 45) membuat produk baru:
 * // dw_log_activity( 'PRODUK_CREATED', 'Penjual membuat produk baru: "Kopi Lokal Mantap"', 45 );
 */

