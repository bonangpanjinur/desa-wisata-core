<?php
/**
 * File Path: includes/activation.php
 *
 * --- PERUBAHAN (RELASI ALAMAT) ---
 * - Mengubah `id_desa` di `dw_pedagang` menjadi `NULL DEFAULT NULL`.
 * - Menambahkan kolom alamat terstruktur (api_..._id) ke `dw_pedagang`.
 * - Menambahkan KEY `idx_alamat_api` untuk pencocokan cepat.
 * - Menambahkan kolom `api_..._id` ke `dw_desa` untuk pencocokan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fungsi utama untuk aktivasi plugin.
 * Membuat atau memperbarui semua tabel database yang diperlukan.
 */
function dw_core_activate_plugin() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_prefix = $wpdb->prefix . 'dw_';

	// Define table structures with new columns and indexes (Cleaned SQL - NO COMMENTS INSIDE)
	$sql = "
    CREATE TABLE {$table_prefix}desa (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user_desa BIGINT(20) UNSIGNED,
        nama_desa VARCHAR(255) NOT NULL,
        deskripsi TEXT,
        foto VARCHAR(255) DEFAULT NULL,
        persentase_komisi_penjualan DECIMAL(5,2) DEFAULT 0,
        no_rekening_desa VARCHAR(50) DEFAULT NULL,
        nama_bank_desa VARCHAR(100) DEFAULT NULL,
        atas_nama_rekening_desa VARCHAR(100) DEFAULT NULL,
        qris_image_url_desa VARCHAR(255) DEFAULT NULL,
        status ENUM('aktif','pending') DEFAULT 'pending',
        id_provinsi VARCHAR(20),
        provinsi VARCHAR(100),
        id_kabupaten VARCHAR(20),
        kabupaten VARCHAR(100),
        id_kecamatan VARCHAR(20),
        kecamatan VARCHAR(100),
        id_kelurahan VARCHAR(20),
        kelurahan VARCHAR(100),
        api_provinsi_id VARCHAR(20) DEFAULT NULL,
        api_kabupaten_id VARCHAR(20) DEFAULT NULL,
        api_kecamatan_id VARCHAR(20) DEFAULT NULL,
        api_kelurahan_id VARCHAR(20) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_user_desa (id_user_desa),
        KEY status (status),
        KEY id_kabupaten (id_kabupaten),
        KEY idx_api_alamat (api_kelurahan_id, api_kecamatan_id, api_kabupaten_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}pedagang (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user BIGINT(20) UNSIGNED NOT NULL,
        id_desa BIGINT(20) NULL DEFAULT NULL,
        nama_toko VARCHAR(255) NOT NULL,
        nama_pemilik VARCHAR(255) NOT NULL,
        nomor_wa VARCHAR(20) NOT NULL,
        alamat_lengkap TEXT,
        url_gmaps TEXT DEFAULT NULL,
        url_ktp VARCHAR(255),
        deskripsi_toko TEXT,
        no_rekening VARCHAR(50) DEFAULT NULL,
        nama_bank VARCHAR(100) DEFAULT NULL,
        atas_nama_rekening VARCHAR(100) DEFAULT NULL,
        qris_image_url VARCHAR(255) DEFAULT NULL,
        status_pendaftaran ENUM('menunggu','disetujui','ditolak','menunggu_desa') DEFAULT 'menunggu_desa',
        status_akun ENUM('aktif','nonaktif','nonaktif_habis_kuota') DEFAULT 'nonaktif',
        sisa_transaksi INT(11) NOT NULL DEFAULT 0,
        shipping_ojek_lokal_aktif TINYINT(1) DEFAULT 0,
        shipping_ojek_lokal_zona JSON DEFAULT NULL,
        shipping_nasional_aktif TINYINT(1) DEFAULT 0,
        shipping_nasional_harga DECIMAL(10,2) DEFAULT 0,
        shipping_profiles JSON DEFAULT NULL,
        allow_pesan_di_tempat TINYINT(1) DEFAULT 0,
        
        -- KOLOM ALAMAT BARU --
        api_provinsi_id VARCHAR(20) DEFAULT NULL,
        api_kabupaten_id VARCHAR(20) DEFAULT NULL,
        api_kecamatan_id VARCHAR(20) DEFAULT NULL,
        api_kelurahan_id VARCHAR(20) DEFAULT NULL,
        provinsi_nama VARCHAR(100) DEFAULT NULL,
        kabupaten_nama VARCHAR(100) DEFAULT NULL,
        kecamatan_nama VARCHAR(100) DEFAULT NULL,
        kelurahan_nama VARCHAR(100) DEFAULT NULL,

        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        KEY id_desa (id_desa),
        KEY status_pendaftaran (status_pendaftaran),
        KEY status_akun (status_akun),
        KEY idx_alamat_api (api_kelurahan_id, api_kecamatan_id, api_kabupaten_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}produk_variasi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_produk BIGINT(20) NOT NULL,
        deskripsi_variasi VARCHAR(255) NOT NULL,
        harga_variasi DECIMAL(10,2) NOT NULL,
        stok_variasi INT DEFAULT NULL,
        PRIMARY KEY (id),
        KEY id_produk (id_produk)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}transaksi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_pembeli BIGINT(20) UNSIGNED NOT NULL,
        id_pedagang BIGINT(20) NOT NULL,
        kode_unik VARCHAR(20) NOT NULL,
        total_harga_produk DECIMAL(12,2) NOT NULL,
        estimasi_ongkir DECIMAL(10,2) DEFAULT NULL,
        biaya_ongkir_final DECIMAL(10,2) DEFAULT NULL,
        total_akhir DECIMAL(12,2) DEFAULT NULL,
        metode_pengiriman ENUM('ojek_lokal','ekspedisi','nasional','nasional_profil','di_tempat','belum_dipilih') DEFAULT 'belum_dipilih',
        alamat_pengiriman TEXT,
        url_bukti_bayar VARCHAR(255) DEFAULT NULL,
        status_pesanan ENUM('menunggu_konfirmasi', 'menunggu_pembayaran', 'lunas', 'diproses', 'diantar_ojek', 'dikirim_ekspedisi', 'selesai', 'dibatalkan') DEFAULT 'menunggu_pembayaran',
        nomor_resi VARCHAR(100) DEFAULT NULL,
        catatan_pembeli TEXT DEFAULT NULL,
        catatan_penjual TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode_unik (kode_unik),
        KEY id_pembeli (id_pembeli),
        KEY id_pedagang (id_pedagang),
        KEY status_pesanan (status_pesanan),
        KEY created_at (created_at)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}transaksi_item (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_transaksi BIGINT(20) NOT NULL,
        id_produk BIGINT(20) NOT NULL,
        id_variasi BIGINT(20) DEFAULT NULL,
        nama_produk_snapshot VARCHAR(255) NOT NULL,
        deskripsi_variasi_snapshot VARCHAR(255) DEFAULT NULL,
        harga_snapshot DECIMAL(10,2) NOT NULL,
        kuantitas INT NOT NULL,
        PRIMARY KEY (id),
        KEY id_transaksi (id_transaksi),
        KEY id_produk (id_produk)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}promosi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        tipe ENUM('produk','wisata') NOT NULL,
        target_id BIGINT(20) NOT NULL,
        pemohon_id BIGINT(20) UNSIGNED NOT NULL,
        durasi INT NOT NULL,
        biaya DECIMAL(10,2) NOT NULL,
        status ENUM('pending','aktif','ditolak','selesai') DEFAULT 'pending',
        mulai DATETIME DEFAULT NULL,
        selesai DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status),
        KEY tipe_target (tipe, target_id),
        KEY pemohon_id (pemohon_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}ulasan (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        target_id BIGINT(20) NOT NULL,
        target_type VARCHAR(50) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        rating INT(1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
        komentar TEXT DEFAULT NULL,
        status_moderasi ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY target (target_id, target_type),
        KEY user_id (user_id),
        KEY status_moderasi (status_moderasi)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}logs (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT NULL,
        aksi VARCHAR(255) NOT NULL,
        keterangan TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY created_at (created_at),
        KEY user_id (user_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}banner (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        judul VARCHAR(255) NOT NULL,
        gambar VARCHAR(255) NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        prioritas INT DEFAULT 10,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY status_prioritas (status, prioritas)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}chat_message (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        produk_id BIGINT(20) NOT NULL,
        sender_id BIGINT(20) UNSIGNED NOT NULL,
        receiver_id BIGINT(20) UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_produk_created (produk_id, created_at),
        KEY idx_sender_receiver_created (sender_id, receiver_id, created_at),
        KEY idx_receiver_read_created (receiver_id, is_read, created_at)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}revoked_tokens (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        token_hash VARCHAR(64) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        revoked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token_hash (token_hash),
        KEY expires_at (expires_at)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}refresh_tokens (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        token VARCHAR(128) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY token (token),
        KEY user_id (user_id),
        KEY expires_at (expires_at)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}carts (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NULL,
        guest_id VARCHAR(64) NULL,
        product_id BIGINT(20) NOT NULL,
        variation_id BIGINT(20) NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_item (user_id, product_id, variation_id),
        UNIQUE KEY guest_item (guest_id(64), product_id, variation_id),
        KEY user_id (user_id),
        KEY guest_id (guest_id),
        KEY product_id (product_id)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}payout_ledger (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        order_id BIGINT(20) NOT NULL,
        payable_to_type ENUM('desa','platform') NOT NULL,
        payable_to_id BIGINT(20) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('unpaid','paid') DEFAULT 'unpaid',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY payable (payable_to_type, payable_to_id, status)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}paket_transaksi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        nama_paket VARCHAR(255) NOT NULL,
        deskripsi TEXT DEFAULT NULL,
        harga DECIMAL(12,2) NOT NULL,
        jumlah_transaksi INT(11) NOT NULL,
        persentase_komisi_desa DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY status (status)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}pembelian_paket (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_pedagang BIGINT(20) NOT NULL,
        id_paket BIGINT(20) NOT NULL,
        nama_paket_snapshot VARCHAR(255) NOT NULL,
        harga_paket DECIMAL(12,2) NOT NULL,
        jumlah_transaksi INT(11) NOT NULL,
        persentase_komisi_desa DECIMAL(5,2) NOT NULL,
        url_bukti_bayar VARCHAR(255) NOT NULL,
        status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
        catatan_admin TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY id_pedagang (id_pedagang),
        KEY status (status)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}transaksi_arsip (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_pembeli BIGINT(20) UNSIGNED NOT NULL,
        id_pedagang BIGINT(20) NOT NULL,
        kode_unik VARCHAR(20) NOT NULL,
        total_harga_produk DECIMAL(12,2) NOT NULL,
        estimasi_ongkir DECIMAL(10,2) DEFAULT NULL,
        biaya_ongkir_final DECIMAL(10,2) DEFAULT NULL,
        total_akhir DECIMAL(12,2) DEFAULT NULL,
        metode_pengiriman ENUM('ojek_lokal','ekspedisi','nasional','nasional_profil','di_tempat','belum_dipilih') DEFAULT 'belum_dipilih',
        alamat_pengiriman TEXT,
        url_bukti_bayar VARCHAR(255) DEFAULT NULL,
        status_pesanan ENUM('menunggu_konfirmasi', 'menunggu_pembayaran', 'lunas', 'diproses', 'diantar_ojek', 'dikirim_ekspedisi', 'selesai', 'dibatalkan') DEFAULT 'menunggu_pembayaran',
        nomor_resi VARCHAR(100) DEFAULT NULL,
        catatan_pembeli TEXT DEFAULT NULL,
        catatan_penjual TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_pembeli (id_pembeli),
        KEY id_pedagang (id_pedagang),
        KEY status_pesanan (status_pesanan),
        KEY created_at (created_at)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}transaksi_item_arsip (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_transaksi BIGINT(20) NOT NULL,
        id_produk BIGINT(20) NOT NULL,
        id_variasi BIGINT(20) DEFAULT NULL,
        nama_produk_snapshot VARCHAR(255) NOT NULL,
        deskripsi_variasi_snapshot VARCHAR(255) DEFAULT NULL,
        harga_snapshot DECIMAL(10,2) NOT NULL,
        kuantitas INT NOT NULL,
        PRIMARY KEY (id),
        KEY id_transaksi (id_transaksi),
        KEY id_produk (id_produk)
    ) $charset_collate;

    CREATE TABLE {$table_prefix}pedagang_summary (
        id_pedagang BIGINT(20) NOT NULL,
        total_penjualan_lifetime DECIMAL(20,2) NOT NULL DEFAULT 0.00,
        total_order_lifetime BIGINT(20) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id_pedagang)
    ) $charset_collate;
";

	dbDelta( $sql ); // Menjalankan query CREATE/UPDATE tabel

    // Hapus tabel lama yang tidak terpakai
    $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}zona_ongkir_lokal");
    $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}ongkir"); // Hapus juga tabel ongkir desa-ke-desa jika ada

	// Memanggil fungsi untuk membuat/memperbarui roles dan capabilities
	if (function_exists('dw_create_roles_and_caps')) {
        dw_create_roles_and_caps();
    } else {
        error_log('Fungsi dw_create_roles_and_caps() tidak ditemukan saat aktivasi plugin Desa Wisata Core.');
    }

	// Update opsi versi database plugin
    update_option('dw_core_db_version', DW_CORE_VERSION);

    // Set pengaturan default jika belum ada
    if ( ! get_option('dw_settings') ) {
        // --- PERUBAHAN: Hapus komisi, tambahkan kuota gratis ---
        $default_settings = [
            'biaya_promosi_produk' => 10000,
            'kuota_gratis_default' => 100, // Kuota gratis untuk pedagang baru
            'delete_data_on_uninstall' => false
        ];
        // --- AKHIR PERUBAHAN ---
        add_option('dw_settings', $default_settings);
    }

    // Pastikan cron jobs terjadwal
    if (!wp_next_scheduled('dw_hourly_cron_hook')) {
        wp_schedule_event(time() + (5 * MINUTE_IN_SECONDS), 'hourly', 'dw_hourly_cron_hook');
    }
     if (!wp_next_scheduled('dw_daily_cron_hook')) {
        wp_schedule_event(time() + (10 * MINUTE_IN_SECONDS), 'daily', 'dw_daily_cron_hook');
    }
    
    // BARU: Tambahkan cron bulanan untuk arsip
    if (!wp_next_scheduled('dw_monthly_cron_hook')) {
        wp_schedule_event(time() + (15 * MINUTE_IN_SECONDS), 'monthly', 'dw_monthly_cron_hook');
    }

    // Membersihkan rewrite rules untuk CPT dan taksonomi baru
    flush_rewrite_rules();

    // Log aktivasi (opsional)
    if (function_exists('dw_log_activity')) {
        dw_log_activity('PLUGIN_ACTIVATED', 'Plugin Desa Wisata Core versi ' . DW_CORE_VERSION . ' diaktifkan.', 0);
    }
}
?>