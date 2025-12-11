<?php
/**
 * File Name:   promotions.php
 * File Folder: includes/
 * File Path:   includes/promotions.php
 *
 * Mengelola fungsionalitas promosi.
 *
 * @package DesaWisataCore
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Fungsi untuk memeriksa dan menonaktifkan promosi yang sudah berakhir
function dw_check_expired_promotions() {
    global $wpdb;
    $table = $wpdb->prefix . 'dw_promosi';
    
    $expired_promos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'aktif' AND selesai < %s",
            current_time('mysql')
        )
    );

    foreach ($expired_promos as $promo) {
        $wpdb->update($table, ['status' => 'selesai'], ['id' => $promo->id]);
        // Update juga status 'unggulan' di tabel produk/wisata
    }
}
add_action('dw_daily_cron_hook', 'dw_check_expired_promotions');

// Pastikan cron job terdaftar
if (!wp_next_scheduled('dw_daily_cron_hook')) {
    wp_schedule_event(time(), 'daily', 'dw_daily_cron_hook');
}

