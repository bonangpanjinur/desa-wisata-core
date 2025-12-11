<?php
/**
 * File Name:   uninstall.php
 * File Folder: desa-wisata-core/
 * File Path:   desa-wisata-core/uninstall.php
 *
 * --- PERUBAHAN (MODEL 3: PAKET KUOTA) ---
 * - MENAMBAHKAN tabel `dw_paket_transaksi` dan `dw_pembelian_paket` ke daftar drop.
 */

// Mencegah akses langsung.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Keamanan: Jangan hapus data secara default.
// Admin harus secara eksplisit mengaktifkan opsi "Hapus data saat uninstal" di pengaturan plugin.
$options = get_option('dw_settings');
if ( ! empty($options['delete_data_on_uninstall']) && $options['delete_data_on_uninstall'] == true ) {
    
    global $wpdb;
    
    // Daftar semua tabel kustom untuk dihapus
    $tables = [
        'desa', 'kategori_wisata', 'wisata', 'pedagang', 'kategori_produk',
        'produk', 'produk_variasi', 'transaksi', 'transaksi_item',
        'pembayaran_promosi', 
        'ulasan', 
        'logs',
        'banner',
        'chat_message',
        'revoked_tokens',
        'refresh_tokens',
        'carts',
        'payout_ledger', // Ditambahkan di Model 2, tetap dipakai di Model 3
        'paket_transaksi', // BARU
        'pembelian_paket' // BARU
    ];

    foreach ($tables as $table) {
        $table_name_full = $wpdb->prefix . 'dw_' . $table;
        // Cek dulu apakah tabel ada sebelum drop
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name_full'") == $table_name_full) {
             $wpdb->query("DROP TABLE IF EXISTS {$table_name_full}");
        }
    }
    
    // Hapus tabel transaksi_pendaftaran secara eksplisit jika masih ada dari instalasi lama
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dw_transaksi_pendaftaran");
    // Hapus tabel ongkir lama secara eksplisit
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dw_zona_ongkir_lokal");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dw_ongkir");


    // Hapus CPT dan taksonomi yang terkait (opsional, tapi bersih)
    $cpt_posts = get_posts([
        'post_type' => ['dw_produk', 'dw_wisata'],
        'numberposts' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    ]);
    foreach ($cpt_posts as $post_id) {
        wp_delete_post($post_id, true);
    }

    // Hapus role kustom
    remove_role('admin_kabupaten');
    remove_role('admin_desa');
    remove_role('pedagang');

    // Hapus opsi
    delete_option('dw_settings');
    delete_option('dw_core_db_version');

    // Hapus cron job
    wp_clear_scheduled_hook('dw_hourly_cron_hook');
    wp_clear_scheduled_hook('dw_daily_cron_hook');
}

