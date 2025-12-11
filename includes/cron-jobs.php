<?php
/**
 * File Name:   cron-jobs.php
 * File Folder: desa-wisata-core/includes/
 * File Path:   desa-wisata-core/includes/cron-jobs.php
 *
 * Mengelola semua tugas terjadwal (WP Cron) untuk plugin Desa Wisata Core.
 * Termasuk pembersihan data lama, pengiriman notifikasi, dll.
 *
 * --- PERBAIKAN FATAL ERROR (v3.1.2) ---
 * - Mengganti semua pemanggilan `dw_log()` (undefined)
 * - menjadi `dw_log_activity()` (fungsi yang benar).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Mendaftarkan jadwal cron kustom jika belum ada.
 *
 * @param array $schedules Jadwal yang ada.
 * @return array Jadwal yang telah ditambahkan.
 */
function dw_add_custom_cron_schedules( $schedules ) {
	// Menambahkan jadwal "setiap 5 menit"
	if ( ! isset( $schedules['every_5_minutes'] ) ) {
		$schedules['every_5_minutes'] = array(
			'interval' => 300, // 5 * 60 detik
			'display'  => __( 'Setiap 5 Menit', 'desa-wisata-core' ),
		);
	}

	// Menambahkan jadwal "setiap 15 menit"
	if ( ! isset( $schedules['every_15_minutes'] ) ) {
		$schedules['every_15_minutes'] = array(
			'interval' => 900, // 15 * 60 detik
			'display'  => __( 'Setiap 15 Menit', 'desa-wisata-core' ),
		);
	}

	return $schedules;
}
add_filter( 'cron_schedules', 'dw_add_custom_cron_schedules' );

/**
 * Mendaftarkan event cron saat plugin diaktifkan.
 */
function dw_schedule_custom_cron_events() {
	// Event per jam
	if ( ! wp_next_scheduled( 'dw_hourly_cron_event' ) ) {
		wp_schedule_event( time(), 'hourly', 'dw_hourly_cron_event' );
	}

	// Event harian
	if ( ! wp_next_scheduled( 'dw_daily_cron_event' ) ) {
		wp_schedule_event( time(), 'daily', 'dw_daily_cron_event' );
	}

	// Event setiap 5 menit (misal: untuk antrian notifikasi)
	if ( ! wp_next_scheduled( 'dw_5_minute_cron_event' ) ) {
		wp_schedule_event( time(), 'every_5_minutes', 'dw_5_minute_cron_event' );
	}
}
// Didaftarkan di activation.php
// register_activation_hook( DW_CORE_PLUGIN_FILE, 'dw_schedule_custom_cron_events' );

/**
 * Menghapus event cron saat plugin dinonaktifkan.
 */
function dw_clear_custom_cron_events() {
	wp_clear_scheduled_hook( 'dw_hourly_cron_event' );
	wp_clear_scheduled_hook( 'dw_daily_cron_event' );
	wp_clear_scheduled_hook( 'dw_5_minute_cron_event' );
}
// Didaftarkan di deactivation.php
// register_deactivation_hook( DW_CORE_PLUGIN_FILE, 'dw_clear_custom_cron_events' );


// ----- HOOK UNTUK MENJALANKAN FUNGSI CRON -----

// Hook untuk event per jam
add_action( 'dw_hourly_cron_event', 'dw_run_hourly_tasks' );
// Hook untuk event harian
add_action( 'dw_daily_cron_event', 'dw_run_daily_tasks' );
// Hook untuk event 5 menit
add_action( 'dw_5_minute_cron_event', 'dw_run_5_minute_tasks' );


// ----- FUNGSI-FUNGSI YANG DIJALANKAN OLEH CRON -----

/**
 * Menjalankan semua tugas per jam.
 */
function dw_run_hourly_tasks() {
	// dw_log_activity( "Menjalankan cron per jam..." ); // Bisa terlalu 'berisik'
	
	// 1. Membersihkan token autentikasi yang kedaluwarsa
	dw_cleanup_expired_tokens();

	// 2. Membersihkan keranjang yang ditinggalkan (jika perlu)
	// dw_cleanup_abandoned_carts();
}

/**
 * Menjalankan semua tugas harian.
 */
function dw_run_daily_tasks() {
    // --- PERBAIKAN FATAL ERROR ---
	dw_log_activity( "Menjalankan cron harian..." ); // Menggunakan dw_log_activity

	// 1. Membersihkan log lama (misal: lebih dari 30 hari)
	dw_cleanup_old_logs();

	// 2. Mengirim ringkasan laporan harian (jika perlu)
	// dw_send_daily_summary_report();
}

/**
 * Menjalankan semua tugas 5 menitan.
 */
function dw_run_5_minute_tasks() {
	// dw_log_activity( "Menjalankan cron 5 menit..." ); // Terlalu 'berisik'
	
	// 1. Memproses antrian notifikasi WA (jika ada)
	// dw_process_whatsapp_notification_queue();
}


// ----- FUNGSI SPESIFIK TUGAS CRON -----

/**
 * CRON: Membersihkan token autentikasi yang kedaluwarsa dari tabel kustom.
 */
function dw_cleanup_expired_tokens() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'dw_revoked_tokens'; // PERBAIKAN: Nama tabel yang benar

	// Hapus token yang kedaluwarsa (misal: lebih dari 7 hari)
	$expiration_time = current_time( 'mysql', 1 ); // Waktu UTC

	$deleted = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $table_name WHERE expires_at < %s", // Hapus jika expires_at < sekarang
			$expiration_time
		)
	);

	if ( $deleted > 0 ) {
		// *** PERBAIKAN ***
        // --- PERBAIKAN FATAL ERROR ---
		dw_log_activity( "Cron: Dihapus {$deleted} token autentikasi yang kedaluwarsa." ); // Menggunakan dw_log_activity
	} elseif ( $deleted === 0 ) {
		// *** PERBAIKAN ***
        // --- PERBAIKAN FATAL ERROR ---
		// dw_log_activity( "Cron: Tidak ada token autentikasi yang kedaluwarsa untuk dihapus." ); // Komentari agar tidak berisik
	} else {
		// *** PERBAIKAN ***
		// Cek jika $deleted adalah false (error)
		if ($deleted === false) {
            // --- PERBAIKAN FATAL ERROR ---
			dw_log_activity( "Cron: Gagal menjalankan query hapus token kedaluwarsa. Error: " . $wpdb->last_error ); // Menggunakan dw_log_activity
		}
	}
}

/**
 * CRON: Membersihkan log lama dari tabel kustom.
 */
function dw_cleanup_old_logs() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'dw_logs';

	// Hapus log yang lebih tua dari 30 hari
	$expiration_time = current_time( 'mysql', 1 ); // Waktu UTC
	$expired_date    = date( 'Y-m-d H:i:s', strtotime( '-30 days', strtotime( $expiration_time ) ) );

	$deleted = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $table_name WHERE created_at < %s", // Ganti 'timestamp' menjadi 'created_at'
			$expired_date
		)
	);

	if ( $deleted > 0 ) {
        // --- PERBAIKAN FATAL ERROR ---
		dw_log_activity( "Cron: Dihapus {$deleted} entri log lama (lebih dari 30 hari)." ); // Menggunakan dw_log_activity
	}
}

/**
 * CRON: Memproses antrian notifikasi WA.
 * (Fungsi placeholder, perlu diimplementasikan jika menggunakan sistem antrian)
 */
function dw_process_whatsapp_notification_queue() {
	// global $wpdb;
	// $queue_table = $wpdb->prefix . 'dw_whatsapp_queue';
	
	// // Ambil 10 notifikasi teratas dari antrian
	// $items = $wpdb->get_results( "SELECT * FROM $queue_table WHERE status = 'pending' ORDER BY created_at ASC LIMIT 10" );
	
	// if ( ! $items ) {
	// 	return; // Antrian kosong
	// }
	
	// foreach ( $items as $item ) {
	// 	// Tandai sebagai 'processing'
	// 	$wpdb->update( $queue_table, [ 'status' => 'processing' ], [ 'id' => $item->id ] );
		
	// 	// Kirim notifikasi
	// 	$success = dw_send_whatsapp_message( $item->phone, $item->message );
		
	// 	if ( $success ) {
	// 		// Hapus dari antrian jika berhasil
	// 		$wpdb->delete( $queue_table, [ 'id' => $item->id ] );
	// 	} else {
	// 		// Tandai sebagai 'failed' untuk dicoba lagi nanti (atau setelah beberapa kali percobaan)
	// 		$wpdb->update( $queue_table, [ 'status' => 'failed', 'retries' => $item->retries + 1 ], [ 'id' => $item->id ] );
    //      // --- PERBAIKAN FATAL ERROR ---
	// 		dw_log_activity( "Cron: Gagal mengirim notifikasi WA (Queue ID {$item->id}) ke {$item->phone}." ); // Menggunakan dw_log_activity
	// 	}
	// }
}