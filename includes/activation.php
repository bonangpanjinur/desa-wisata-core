<?php
/**
 * File Name:   activation.php
 * File Folder: includes/
 * File Path:   includes/activation.php
 * * Description: 
 * File ini menangani proses aktivasi plugin dan pembuatan skema database.
 * Berisi semua perintah CREATE TABLE untuk struktur data Full Custom (Scalable).
 * * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dw_core_activate_plugin() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = $wpdb->prefix . 'dw_';

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    /* =========================================
       1. ENTITAS UTAMA (MASTER DATA)
       ========================================= */

    // 1. Tabel Desa
    $sql_desa = "CREATE TABLE {$table_prefix}desa (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user_desa BIGINT(20) UNSIGNED,
        nama_desa VARCHAR(255) NOT NULL,
        slug_desa VARCHAR(255) NOT NULL,
        deskripsi TEXT,
        foto VARCHAR(255) DEFAULT NULL,
        persentase_komisi_penjualan DECIMAL(5,2) DEFAULT 0,
        no_rekening_desa VARCHAR(50) DEFAULT NULL,
        nama_bank_desa VARCHAR(100) DEFAULT NULL,
        atas_nama_rekening_desa VARCHAR(100) DEFAULT NULL,
        qris_image_url_desa VARCHAR(255) DEFAULT NULL,
        status ENUM('aktif','pending') DEFAULT 'pending',
        
        -- Lokasi
        provinsi VARCHAR(100),
        kabupaten VARCHAR(100),
        kecamatan VARCHAR(100),
        kelurahan VARCHAR(100),
        api_provinsi_id VARCHAR(20),
        api_kabupaten_id VARCHAR(20),
        api_kecamatan_id VARCHAR(20),
        api_kelurahan_id VARCHAR(20),
        alamat_lengkap TEXT,
        
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_user_desa (id_user_desa),
        KEY slug_desa (slug_desa),
        KEY idx_lokasi (api_kabupaten_id)
    ) $charset_collate;";
    dbDelta( $sql_desa );

    // 2. Tabel Pedagang (Anak dari Desa)
    $sql_pedagang = "CREATE TABLE {$table_prefix}pedagang (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user BIGINT(20) UNSIGNED NOT NULL,
        id_desa BIGINT(20) NULL DEFAULT NULL, -- Relasi ke Desa
        nama_toko VARCHAR(255) NOT NULL,
        slug_toko VARCHAR(255) NOT NULL,
        nama_pemilik VARCHAR(255) NOT NULL,
        nomor_wa VARCHAR(20) NOT NULL,
        alamat_lengkap TEXT,
        url_gmaps TEXT DEFAULT NULL,
        
        -- Verifikasi
        url_ktp VARCHAR(255),
        nik VARCHAR(50),
        foto_profil VARCHAR(255),
        
        -- Keuangan
        no_rekening VARCHAR(50) DEFAULT NULL,
        nama_bank VARCHAR(100) DEFAULT NULL,
        atas_nama_rekening VARCHAR(100) DEFAULT NULL,
        qris_image_url VARCHAR(255) DEFAULT NULL,
        
        -- Status
        status_pendaftaran ENUM('menunggu','disetujui','ditolak','menunggu_desa') DEFAULT 'menunggu_desa',
        status_akun ENUM('aktif','nonaktif','suspend') DEFAULT 'nonaktif',
        
        -- Pengiriman
        shipping_ojek_lokal_aktif TINYINT(1) DEFAULT 0,
        shipping_ojek_lokal_zona JSON DEFAULT NULL,
        shipping_nasional_aktif TINYINT(1) DEFAULT 0,
        
        -- Lokasi API
        api_kecamatan_id VARCHAR(20) DEFAULT NULL,
        
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        KEY id_desa (id_desa),
        KEY slug_toko (slug_toko)
    ) $charset_collate;";
    dbDelta( $sql_pedagang );

    /* =========================================
       2. KONTEN (INVENTORY & WISATA)
       ========================================= */

    // 3. Tabel Wisata (Anak dari Desa - Pengganti WP Posts)
    $sql_wisata = "CREATE TABLE {$table_prefix}wisata (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_desa BIGINT(20) NOT NULL, -- Relasi ke Desa
        nama_wisata VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        deskripsi LONGTEXT,
        
        -- Atribut
        harga_tiket DECIMAL(15,2) DEFAULT 0,
        jam_buka VARCHAR(100),
        fasilitas TEXT,
        kontak_pengelola VARCHAR(50),
        lokasi_maps TEXT,
        
        -- Media
        foto_utama VARCHAR(255),
        galeri JSON, -- Menyimpan array URL gambar
        
        rating_avg DECIMAL(3,2) DEFAULT 0,
        total_ulasan INT DEFAULT 0,
        
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY id_desa (id_desa),
        KEY slug (slug)
    ) $charset_collate;";
    dbDelta( $sql_wisata );

    // 4. Tabel Produk (Anak dari Pedagang - Pengganti WP Posts)
    $sql_produk = "CREATE TABLE {$table_prefix}produk (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_pedagang BIGINT(20) NOT NULL, -- Relasi ke Pedagang
        nama_produk VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        deskripsi LONGTEXT,
        
        -- Atribut Dasar
        harga DECIMAL(15,2) NOT NULL DEFAULT 0,
        stok INT DEFAULT 0,
        berat_gram INT DEFAULT 0,
        kondisi ENUM('baru','bekas') DEFAULT 'baru',
        kategori VARCHAR(100),
        
        -- Media
        foto_utama VARCHAR(255),
        galeri JSON,
        
        -- Stats
        terjual INT DEFAULT 0,
        rating_avg DECIMAL(3,2) DEFAULT 0,
        dilihat INT DEFAULT 0,
        
        status ENUM('aktif','nonaktif','habis','arsip') DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY id_pedagang (id_pedagang),
        KEY slug (slug),
        KEY harga (harga),
        KEY kategori (kategori)
    ) $charset_collate;";
    dbDelta( $sql_produk );

    // 5. Tabel Produk Variasi
    $sql_variasi = "CREATE TABLE {$table_prefix}produk_variasi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_produk BIGINT(20) NOT NULL,
        nama_variasi VARCHAR(255) NOT NULL, -- Contoh: 'Merah, XL'
        harga DECIMAL(15,2) NOT NULL,
        stok INT DEFAULT 0,
        sku VARCHAR(100),
        foto VARCHAR(255) DEFAULT NULL,
        is_default TINYINT(1) DEFAULT 0,
        PRIMARY KEY (id),
        KEY id_produk (id_produk)
    ) $charset_collate;";
    dbDelta( $sql_variasi );

    /* =========================================
       3. TRANSAKSI (E-COMMERCE FLOW)
       ========================================= */

    // 6. Tabel Transaksi Utama (Master Invoice)
    $sql_transaksi = "CREATE TABLE {$table_prefix}transaksi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        kode_unik VARCHAR(50) NOT NULL, -- Invoice ID (TRX-12345)
        id_pembeli BIGINT(20) UNSIGNED NOT NULL,
        
        -- Total Global
        total_belanja DECIMAL(15,2) DEFAULT 0,
        total_ongkir DECIMAL(15,2) DEFAULT 0,
        biaya_layanan DECIMAL(15,2) DEFAULT 0,
        total_bayar DECIMAL(15,2) DEFAULT 0,
        
        -- Data Pengiriman & Pembayaran
        alamat_pengiriman JSON, -- Snapshot alamat saat beli
        metode_pembayaran VARCHAR(50),
        status_pembayaran ENUM('unpaid','paid','failed','expired') DEFAULT 'unpaid',
        status_transaksi ENUM('pending','proses','dikirim','selesai','batal') DEFAULT 'pending',
        
        url_bukti_bayar VARCHAR(255) DEFAULT NULL,
        tanggal_transaksi DATETIME DEFAULT CURRENT_TIMESTAMP,
        tanggal_bayar DATETIME DEFAULT NULL,
        
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode_unik (kode_unik),
        KEY id_pembeli (id_pembeli),
        KEY status_transaksi (status_transaksi)
    ) $charset_collate;";
    dbDelta( $sql_transaksi );

    // 7. Tabel Sub Transaksi (Per Toko/Pedagang)
    $sql_transaksi_sub = "CREATE TABLE {$table_prefix}transaksi_sub (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_transaksi BIGINT(20) NOT NULL,
        id_pedagang BIGINT(20) NOT NULL,
        
        sub_total_produk DECIMAL(15,2) NOT NULL,
        ongkir DECIMAL(15,2) NOT NULL,
        kurir_nama VARCHAR(100),
        kurir_layanan VARCHAR(100),
        no_resi VARCHAR(100) DEFAULT NULL,
        
        status_pesanan ENUM('menunggu_konfirmasi','diproses','dikirim','selesai','batal') DEFAULT 'menunggu_konfirmasi',
        catatan_pembeli TEXT,
        
        PRIMARY KEY  (id),
        KEY id_transaksi (id_transaksi),
        KEY id_pedagang (id_pedagang)
    ) $charset_collate;";
    dbDelta( $sql_transaksi_sub );

    // 8. Tabel Transaksi Items (Detail Produk)
    $sql_transaksi_items = "CREATE TABLE {$table_prefix}transaksi_items (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_transaksi BIGINT(20) NOT NULL,
        id_sub_transaksi BIGINT(20) NOT NULL,
        id_produk BIGINT(20) NOT NULL,
        id_variasi BIGINT(20) DEFAULT 0,
        
        -- Snapshot Data (Penting jika harga produk berubah nanti)
        nama_produk_snapshot VARCHAR(255) NOT NULL,
        nama_variasi_snapshot VARCHAR(255) DEFAULT NULL,
        harga_satuan_snapshot DECIMAL(15,2) NOT NULL,
        qty INT NOT NULL,
        subtotal DECIMAL(15,2) NOT NULL,
        
        catatan_item TEXT,
        
        PRIMARY KEY (id),
        KEY id_sub_transaksi (id_sub_transaksi),
        KEY id_produk (id_produk)
    ) $charset_collate;";
    dbDelta( $sql_transaksi_items );

    /* =========================================
       4. PENDUKUNG (SUPPORTING)
       ========================================= */

    // 9. Tabel Cart (Keranjang Belanja)
    $sql_cart = "CREATE TABLE {$table_prefix}cart ( 
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NULL,
        session_id VARCHAR(64) NULL, -- Untuk guest
        id_produk BIGINT(20) NOT NULL,
        id_variasi BIGINT(20) DEFAULT 0,
        qty INT NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_session (user_id, session_id)
    ) $charset_collate;";
    dbDelta( $sql_cart );

    // 10. Tabel Chat
    $sql_chat = "CREATE TABLE {$table_prefix}chat_message (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        sender_id BIGINT(20) UNSIGNED NOT NULL,
        receiver_id BIGINT(20) UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        attachment_url VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY chat_pair (sender_id, receiver_id)
    ) $charset_collate;";
    dbDelta( $sql_chat );

    // 11. Tabel Promosi
    $sql_promosi = "CREATE TABLE {$table_prefix}promosi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        tipe ENUM('produk','wisata') NOT NULL,
        target_id BIGINT(20) NOT NULL,
        pemohon_id BIGINT(20) UNSIGNED NOT NULL,
        durasi_hari INT NOT NULL,
        biaya DECIMAL(10,2) NOT NULL,
        status ENUM('pending','aktif','selesai','ditolak') DEFAULT 'pending',
        mulai_tanggal DATETIME DEFAULT NULL,
        selesai_tanggal DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_promosi );

    // 12. Tabel Ulasan (Reviews)
    $sql_ulasan = "CREATE TABLE {$table_prefix}reviews (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        tipe ENUM('produk','wisata') NOT NULL,
        target_id BIGINT(20) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        rating INT(1) NOT NULL,
        komentar TEXT,
        foto_ulasan JSON,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY target_lookup (tipe, target_id)
    ) $charset_collate;";
    dbDelta( $sql_ulasan );

    // 13. Tabel Logs (Penting untuk Audit)
    $sql_logs = "CREATE TABLE {$table_prefix}logs (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        aksi VARCHAR(50) NOT NULL, 
        keterangan TEXT,
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_logs );

    // 14. Tabel Banner
    $sql_banner = "CREATE TABLE {$table_prefix}banner (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        lokasi ENUM('home','wisata','market') DEFAULT 'home',
        gambar_url VARCHAR(255) NOT NULL,
        link_url VARCHAR(255),
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        urutan INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_banner );

    // Default Options & Roles
    update_option('dw_core_db_version', DW_CORE_VERSION);
    
    // Setup Cron (Jika belum ada)
    if (!wp_next_scheduled('dw_daily_cron_hook')) {
        wp_schedule_event(time(), 'daily', 'dw_daily_cron_hook');
    }

    // Role Capabilities
    add_role('pedagang', 'Pedagang Desa', array('read' => true, 'upload_files' => true));
    add_role('pengelola_desa', 'Admin Desa', array('read' => true, 'upload_files' => true, 'list_users' => true));
    
    flush_rewrite_rules();
}
?>