<?php
/**
 * File Name:   cron-jobs.php
 * File Folder: includes/
 * File Path:   includes/cron-jobs.php
 *
 * Menangani tugas terjadwal (Cron Jobs) untuk pemeliharaan sistem.
 * --- PERBAIKAN ---
 * - Memperbaiki error undefined constant DW_CORE_FILE.
 * - Hook register_deactivation_hook sebaiknya dijalankan di file utama, 
 * tapi di sini kita beri pengecekan agar tidak error.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hook ke event jadwal WordPress
add_action('dw_daily_cleanup_event', 'dw_run_daily_cleanup');

/**
 * Fungsi utama pembersihan harian.
 */
function dw_run_daily_cleanup() {
    dw_cleanup_refresh_tokens();
    dw_cleanup_revoked_tokens();
    dw_cleanup_old_logs();
    dw_cleanup_abandoned_carts();
}

/**
 * Hapus refresh token yang sudah kadaluarsa.
 */
function dw_cleanup_refresh_tokens() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_refresh_tokens';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return;

    $wpdb->query("DELETE FROM $table_name WHERE expires_at < NOW()");
}

/**
 * Hapus blacklist token yang sudah expired.
 */
function dw_cleanup_revoked_tokens() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_revoked_tokens';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return;

    $wpdb->query("DELETE FROM $table_name WHERE expires_at < NOW()");
}

/**
 * Hapus log aktivitas yang lebih tua dari 30 hari.
 */
function dw_cleanup_old_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_logs';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return;

    $wpdb->query("DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
}

/**
 * Hapus keranjang belanja yang ditinggalkan lebih dari 7 hari.
 */
function dw_cleanup_abandoned_carts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_cart';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return;

    $wpdb->query("DELETE FROM $table_name WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
}

// --- PERBAIKAN: Pindahkan register_deactivation_hook ke file utama plugin ---
// Namun, jika Anda ingin fungsi ini dipanggil saat deaktivasi, kita definisikan saja fungsinya di sini.
// Hook-nya sendiri sebaiknya dipindahkan ke desa-wisata-core.php:
// register_deactivation_hook( __FILE__, 'dw_deactivate_cron' );

/**
 * Fungsi ini harus dipanggil oleh hook deaktivasi di file utama plugin.
 */
function dw_deactivate_cron() {
    $timestamp = wp_next_scheduled( 'dw_daily_cleanup_event' );
    if ($timestamp) {
        wp_unschedule_event( $timestamp, 'dw_daily_cleanup_event' );
    }
    
    // Hapus cron jobs lain jika ada
    $hourly = wp_next_scheduled('dw_hourly_cron_hook');
    if ($hourly) wp_unschedule_event($hourly, 'dw_hourly_cron_hook');
    
    $daily = wp_next_scheduled('dw_daily_cron_hook');
    if ($daily) wp_unschedule_event($daily, 'dw_daily_cron_hook');
    
    $monthly = wp_next_scheduled('dw_monthly_cron_hook');
    if ($monthly) wp_unschedule_event($monthly, 'dw_monthly_cron_hook');
}
?>
