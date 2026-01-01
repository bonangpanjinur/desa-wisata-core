<?php
/**
 * File Name:   activation.php
 * File Folder: includes/
 * Description: File aktivasi plugin utuh terintegrasi v3.8.
 * Menangani pembuatan tabel database Desa Wisata Core.
 * * FIXES APPLIED:
 * 1. UPDATE: Menambahkan tabel `dw_pembeli` untuk menyimpan profil wisatawan/member.
 * 2. UPDATE v3.9: Menambahkan kolom 'referrer_id' & 'referrer_type' di tabel pembelian_paket.
 * 3. General Cleanup & Error Logging.
 * 4. UPDATE v4.0: Menambahkan kolom alamat lengkap & wilayah API ke tabel dw_pembeli.
 * * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fungsi Utama Aktivasi Plugin
 */
function dw_activate_plugin() {
    // Debugging: Cek apakah fungsi ini terpanggil (cek di debug.log)
    error_log( '[DW Core] Aktivasi dimulai...' );

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = $wpdb->prefix . 'dw_';

    // Wajib ada untuk fungsi dbDelta()
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    /* =========================================
       1. ENTITAS UTAMA (MASTER DATA)
       ========================================= */

    // 1. Tabel Desa
    // foto = Logo Desa (Entity), foto_admin = Foto Orang (Person)
    $sql_desa = "CREATE TABLE {$table_prefix}desa (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user_desa BIGINT(20) UNSIGNED NOT NULL,
        nama_desa VARCHAR(255) NOT NULL,
        slug_desa VARCHAR(255) NOT NULL,
        kode_referral VARCHAR(50) DEFAULT NULL,
        deskripsi TEXT,
        foto VARCHAR(255) DEFAULT NULL,
        foto_sampul VARCHAR(255) DEFAULT NULL,
        foto_admin VARCHAR(255) DEFAULT NULL, 
        total_pendapatan DECIMAL(15,2) DEFAULT 0,
        saldo_komisi DECIMAL(15,2) DEFAULT 0,
        no_rekening_desa VARCHAR(50) DEFAULT NULL,
        nama_bank_desa VARCHAR(100) DEFAULT NULL,
        atas_nama_rekening_desa VARCHAR(100) DEFAULT NULL,
        qris_image_url_desa VARCHAR(255) DEFAULT NULL,
        status ENUM('aktif','pending') DEFAULT 'pending',
        provinsi VARCHAR(100),
        kabupaten VARCHAR(100),
        kecamatan VARCHAR(100),
        kelurahan VARCHAR(100),
        api_provinsi_id VARCHAR(20),
        api_kabupaten_id VARCHAR(20),
        api_kecamatan_id VARCHAR(20),
        api_kelurahan_id VARCHAR(20),
        alamat_lengkap TEXT,
        kode_pos VARCHAR(10) DEFAULT NULL,
        status_akses_verifikasi ENUM('locked', 'pending', 'active') DEFAULT 'locked',
        bukti_bayar_akses VARCHAR(255) DEFAULT NULL,
        alasan_penolakan TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode_referral (kode_referral),
        KEY id_user_desa (id_user_desa),
        KEY slug_desa (slug_desa),
        KEY idx_lokasi (api_kabupaten_id)
    ) $charset_collate;";
    
    dbDelta( $sql_desa );

    // 2. Tabel Pedagang
    // foto_profil = Logo Toko (Entity), foto_admin = Foto Pemilik (Person)
    $sql_pedagang = "CREATE TABLE {$table_prefix}pedagang (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user BIGINT(20) UNSIGNED NOT NULL,
        id_desa BIGINT(20) DEFAULT NULL,
        id_verifikator BIGINT(20) DEFAULT 0,
        nama_toko VARCHAR(255) NOT NULL,
        slug_toko VARCHAR(255) NOT NULL,
        kode_referral_saya VARCHAR(50) DEFAULT NULL,
        terdaftar_melalui_kode VARCHAR(50) DEFAULT NULL,
        nama_pemilik VARCHAR(255) NOT NULL,
        nomor_wa VARCHAR(20) NOT NULL,
        alamat_lengkap TEXT,
        url_gmaps TEXT DEFAULT NULL,
        url_ktp VARCHAR(255),
        nik VARCHAR(50),
        foto_admin VARCHAR(255) DEFAULT NULL,
        foto_profil VARCHAR(255),
        foto_sampul VARCHAR(255), 
        no_rekening VARCHAR(50) DEFAULT NULL,
        nama_bank VARCHAR(100) DEFAULT NULL,
        atas_nama_rekening VARCHAR(100) DEFAULT NULL,
        qris_image_url VARCHAR(255) DEFAULT NULL,
        rating_toko DECIMAL(3,2) DEFAULT 0,
        total_ulasan_toko INT DEFAULT 0,
        status_pendaftaran ENUM('menunggu','disetujui','ditolak','menunggu_desa') DEFAULT 'menunggu_desa',
        status_akun ENUM('aktif','nonaktif','suspend','nonaktif_habis_kuota') DEFAULT 'nonaktif',
        is_verified TINYINT(1) DEFAULT 0,
        verified_at DATETIME DEFAULT NULL,
        is_independent TINYINT(1) DEFAULT 1,
        approved_by VARCHAR(20) DEFAULT NULL,
        sisa_transaksi INT DEFAULT 0,
        total_referral_pembeli INT DEFAULT 0,
        shipping_ojek_lokal_aktif TINYINT(1) DEFAULT 0,
        shipping_ojek_lokal_zona JSON DEFAULT NULL,
        shipping_nasional_aktif TINYINT(1) DEFAULT 0,
        shipping_nasional_harga DECIMAL(15,2) DEFAULT 0,
        shipping_profiles JSON DEFAULT NULL,
        allow_pesan_di_tempat TINYINT(1) DEFAULT 0,
        api_provinsi_id VARCHAR(20),
        api_kabupaten_id VARCHAR(20),
        api_kecamatan_id VARCHAR(20),
        api_kelurahan_id VARCHAR(20),
        provinsi_nama VARCHAR(100),
        kabupaten_nama VARCHAR(100),
        kecamatan_nama VARCHAR(100),
        kelurahan_nama VARCHAR(100),
        kode_pos VARCHAR(10) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        UNIQUE KEY kode_referral_saya (kode_referral_saya),
        KEY id_desa (id_desa),
        KEY id_verifikator (id_verifikator),
        KEY slug_toko (slug_toko)
    ) $charset_collate;";
    dbDelta($sql_pedagang);

    // 2B. Tabel Ojek
    $sql_ojek = "CREATE TABLE {$table_prefix}ojek (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user BIGINT(20) UNSIGNED NOT NULL,
        nama_lengkap VARCHAR(255) NOT NULL,
        no_hp VARCHAR(20) NOT NULL,
        nik VARCHAR(50),
        no_kartu_ojek VARCHAR(50), 
        plat_nomor VARCHAR(20) NOT NULL,
        merk_motor VARCHAR(100) NOT NULL,
        foto_profil VARCHAR(255),
        foto_ktp VARCHAR(255),
        foto_kartu_ojek VARCHAR(255), 
        foto_motor VARCHAR(255),
        status_pendaftaran ENUM('menunggu','disetujui','ditolak') DEFAULT 'menunggu',
        status_kerja ENUM('offline','online','busy') DEFAULT 'offline',
        api_provinsi_id VARCHAR(20),
        api_kabupaten_id VARCHAR(20),
        api_kecamatan_id VARCHAR(20),
        api_kelurahan_id VARCHAR(20),
        alamat_domisili TEXT,
        rating_avg DECIMAL(3,2) DEFAULT 0,
        total_trip INT DEFAULT 0,
        lokasi_terakhir_lat VARCHAR(50),
        lokasi_terakhir_lng VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        KEY idx_lokasi_ojek (api_kecamatan_id, status_kerja)
    ) $charset_collate;";
    dbDelta($sql_ojek);

    // 2C. Tabel Verifikator UMKM
    $sql_verifikator = "CREATE TABLE {$table_prefix}verifikator (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user BIGINT(20) UNSIGNED NOT NULL,
        nama_lengkap VARCHAR(255) NOT NULL,
        foto_profil VARCHAR(255) DEFAULT NULL,
        nik VARCHAR(50) NOT NULL,
        kode_referral VARCHAR(50),
        nomor_wa VARCHAR(20) NOT NULL,
        alamat_lengkap TEXT,
        provinsi VARCHAR(100),
        kabupaten VARCHAR(100),
        kecamatan VARCHAR(100),
        kelurahan VARCHAR(100),
        api_provinsi_id VARCHAR(20),
        api_kabupaten_id VARCHAR(20),
        api_kecamatan_id VARCHAR(20),
        api_kelurahan_id VARCHAR(20),
        total_verifikasi_sukses INT DEFAULT 0,
        total_pendapatan_komisi DECIMAL(15,2) DEFAULT 0,
        saldo_saat_ini DECIMAL(15,2) DEFAULT 0,
        kode_pos VARCHAR(10) DEFAULT NULL,
        status ENUM('aktif','pending','nonaktif') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        UNIQUE KEY kode_referral (kode_referral),
        KEY idx_lokasi_v (api_kabupaten_id)
    ) $charset_collate;";
    dbDelta($sql_verifikator);

    // 2D. Tabel Pembeli (Wisatawan/Member) [UPDATED FIELDS]
    // Menambahkan terdaftar_melalui_kode untuk tracking referral pedagang
    $sql_pembeli = "CREATE TABLE {$table_prefix}pembeli (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user BIGINT(20) UNSIGNED NOT NULL,
        nama_lengkap VARCHAR(255) NOT NULL,
        no_hp VARCHAR(20),
        nik VARCHAR(50),
        foto_profil VARCHAR(255),
        tgl_lahir DATE DEFAULT NULL,
        jenis_kelamin ENUM('L','P') DEFAULT NULL,
        alamat_lengkap TEXT,
        provinsi VARCHAR(100), kabupaten VARCHAR(100), kecamatan VARCHAR(100), kelurahan VARCHAR(100),
        api_provinsi_id VARCHAR(20), api_kabupaten_id VARCHAR(20), api_kecamatan_id VARCHAR(20), api_kelurahan_id VARCHAR(20),
        kode_pos VARCHAR(10) DEFAULT NULL,
        poin_reward INT DEFAULT 0,
        terdaftar_melalui_kode VARCHAR(50) DEFAULT NULL, 
        referrer_id BIGINT(20) DEFAULT 0, 
        referrer_type VARCHAR(50) DEFAULT NULL,
        status_akun ENUM('aktif','suspend','banned') DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        KEY idx_referral (terdaftar_melalui_kode)
    ) $charset_collate;";
    dbDelta($sql_pembeli);

    
    /* =========================================
       2. KONTEN (INVENTORY & WISATA)
       ========================================= */

    // 3. Tabel Wisata
    $sql_wisata = "CREATE TABLE {$table_prefix}wisata (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_desa BIGINT(20) NOT NULL,
        nama_wisata VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        kategori VARCHAR(100),
        deskripsi LONGTEXT,
        harga_tiket DECIMAL(15,2) DEFAULT 0,
        jam_buka VARCHAR(100),
        fasilitas TEXT,
        kontak_pengelola VARCHAR(50),
        lokasi_maps TEXT,
        foto_utama VARCHAR(255),
        galeri JSON,
        rating_avg DECIMAL(3,2) DEFAULT 0,
        total_ulasan INT DEFAULT 0,
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_desa (id_desa),
        KEY slug (slug),
        KEY kategori (kategori)
    ) $charset_collate;";
    dbDelta( $sql_wisata );

    // 4. Tabel Produk
    $sql_produk = "CREATE TABLE {$table_prefix}produk (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_pedagang BIGINT(20) NOT NULL,
        nama_produk VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        deskripsi LONGTEXT,
        harga DECIMAL(15,2) NOT NULL DEFAULT 0,
        stok INT DEFAULT 0,
        berat_gram INT DEFAULT 0,
        kondisi ENUM('baru','bekas') DEFAULT 'baru',
        kategori VARCHAR(100),
        foto_utama VARCHAR(255),
        galeri JSON,
        terjual INT DEFAULT 0,
        rating_avg DECIMAL(3,2) DEFAULT 0,
        dilihat INT DEFAULT 0,
        status ENUM('aktif','nonaktif','habis','arsip') DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_pedagang (id_pedagang),
        KEY slug (slug),
        KEY harga (harga),
        KEY kategori (kategori)
    ) $charset_collate;";
    dbDelta( $sql_produk );

    // 5. Tabel Variasi Produk
    $sql_variasi = "CREATE TABLE {$table_prefix}produk_variasi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_produk BIGINT(20) NOT NULL,
        deskripsi_variasi VARCHAR(255) NOT NULL,
        harga_variasi DECIMAL(15,2) NOT NULL,
        stok_variasi INT DEFAULT 0,
        sku VARCHAR(100),
        foto VARCHAR(255) DEFAULT NULL,
        is_default TINYINT(1) DEFAULT 0,
        PRIMARY KEY  (id),
        KEY id_produk (id_produk)
    ) $charset_collate;";
    dbDelta( $sql_variasi );

    /* =========================================
       3. TRANSAKSI (E-COMMERCE FLOW)
       ========================================= */

    // 6. Tabel Transaksi Utama
    $sql_transaksi = "CREATE TABLE {$table_prefix}transaksi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        kode_unik VARCHAR(50) NOT NULL,
        id_pembeli BIGINT(20) UNSIGNED NOT NULL,
        total_produk DECIMAL(15,2) DEFAULT 0,
        total_ongkir DECIMAL(15,2) DEFAULT 0,
        biaya_layanan DECIMAL(15,2) DEFAULT 0,
        total_transaksi DECIMAL(15,2) DEFAULT 0,
        nama_penerima VARCHAR(255),
        no_hp VARCHAR(20),
        alamat_lengkap TEXT,
        ojek_data JSON DEFAULT NULL,
        provinsi VARCHAR(100),
        kabupaten VARCHAR(100),
        kecamatan VARCHAR(100),
        kelurahan VARCHAR(100),
        kode_pos VARCHAR(10),
        metode_pembayaran VARCHAR(50),
        status_transaksi ENUM('menunggu_pembayaran','pembayaran_dikonfirmasi','pembayaran_gagal','diproses','dikirim','selesai','dibatalkan','refunded','menunggu_driver','penawaran_driver','nego','menunggu_penjemputan','dalam_perjalanan') DEFAULT 'menunggu_pembayaran',
        url_bukti_bayar VARCHAR(255) DEFAULT NULL,
        bukti_pembayaran VARCHAR(255) DEFAULT NULL,
        catatan_pembeli TEXT,
        tanggal_transaksi DATETIME DEFAULT CURRENT_TIMESTAMP,
        tanggal_pembayaran DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode_unik (kode_unik),
        KEY id_pembeli (id_pembeli),
        KEY status_transaksi (status_transaksi)
    ) $charset_collate;";
    dbDelta( $sql_transaksi );

    // 7. Tabel Sub Transaksi
    $sql_sub = "CREATE TABLE {$table_prefix}transaksi_sub (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_transaksi BIGINT(20) NOT NULL,
        id_pedagang BIGINT(20) NOT NULL,
        nama_toko VARCHAR(255),
        sub_total DECIMAL(15,2) NOT NULL,
        ongkir DECIMAL(15,2) NOT NULL,
        total_pesanan_toko DECIMAL(15,2) NOT NULL,
        metode_pengiriman VARCHAR(100),
        kurir_nama VARCHAR(100),
        kurir_layanan VARCHAR(100),
        no_resi VARCHAR(100) DEFAULT NULL,
        status_pesanan ENUM('menunggu_konfirmasi','diproses','diantar_ojek','dikirim_ekspedisi','selesai','dibatalkan','lunas') DEFAULT 'menunggu_konfirmasi',
        catatan_penjual TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_transaksi (id_transaksi),
        KEY id_pedagang (id_pedagang)
    ) $charset_collate;";
    dbDelta( $sql_sub );

    // 8. Tabel Item Transaksi
    $sql_items = "CREATE TABLE {$table_prefix}transaksi_items (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_sub_transaksi BIGINT(20) NOT NULL,
        id_produk BIGINT(20) NOT NULL,
        id_variasi BIGINT(20) DEFAULT 0,
        nama_produk VARCHAR(255) NOT NULL,
        nama_variasi VARCHAR(255) DEFAULT NULL,
        harga_satuan DECIMAL(15,2) NOT NULL,
        jumlah INT NOT NULL,
        total_harga DECIMAL(15,2) NOT NULL,
        catatan_item TEXT,
        PRIMARY KEY  (id),
        KEY id_sub_transaksi (id_sub_transaksi),
        KEY id_produk (id_produk)
    ) $charset_collate;";
    dbDelta( $sql_items );

    /* =========================================
       4. MODEL BISNIS & DUKUNGAN
       ========================================= */

    // 9. Paket Transaksi
    // UPDATE: Menambahkan komisi_nominal & persentase_komisi (Generic)
    $sql_paket = "CREATE TABLE {$table_prefix}paket_transaksi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        nama_paket VARCHAR(100) NOT NULL,
        deskripsi TEXT,
        harga DECIMAL(15,2) NOT NULL,
        jumlah_transaksi INT NOT NULL,
        target_role ENUM('pedagang','ojek') NOT NULL DEFAULT 'pedagang', 
        persentase_komisi DECIMAL(5,2) DEFAULT 0,
        komisi_nominal DECIMAL(15,2) DEFAULT 0,
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_paket );

    // 10. Pembelian Paket
    // UPDATE: Menambahkan referrer_id & referrer_type untuk snapshot relasi komisi
    $sql_pembelian = "CREATE TABLE {$table_prefix}pembelian_paket (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_pedagang BIGINT(20) NOT NULL,
        id_paket BIGINT(20) NOT NULL,
        nama_paket_snapshot VARCHAR(100) NOT NULL,
        harga_paket DECIMAL(15,2) NOT NULL,
        jumlah_transaksi INT NOT NULL,
        referrer_id BIGINT(20) DEFAULT 0, 
        referrer_type ENUM('desa','verifikator') DEFAULT NULL,
        persentase_komisi_referrer DECIMAL(5,2) DEFAULT 0,
        komisi_nominal_cair DECIMAL(15,2) DEFAULT 0,
        url_bukti_bayar VARCHAR(255),
        status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
        catatan_admin TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY id_pedagang (id_pedagang),
        KEY idx_referrer (referrer_id, referrer_type)
    ) $charset_collate;";
    dbDelta( $sql_pembelian );

    // 11. Payout Ledger
    $sql_ledger = "CREATE TABLE {$table_prefix}payout_ledger (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        order_id BIGINT(20) NOT NULL, 
        payable_to_type VARCHAR(50) NOT NULL, 
        payable_to_id BIGINT(20) NOT NULL, 
        amount DECIMAL(18,2) NOT NULL,
        status VARCHAR(50) DEFAULT 'unpaid',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY status_lookup (payable_to_type, payable_to_id, status)
    ) $charset_collate;";
    dbDelta( $sql_ledger );

    // 11B. Riwayat Komisi Masuk
    $sql_riwayat_komisi = "CREATE TABLE {$table_prefix}riwayat_komisi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_penerima BIGINT(20) NOT NULL,
        role_penerima VARCHAR(50) NOT NULL,
        id_sumber_pedagang BIGINT(20) NOT NULL,
        id_pembelian_paket BIGINT(20) NOT NULL,
        jumlah_komisi DECIMAL(15,2) NOT NULL,
        keterangan TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_penerima (id_penerima, role_penerima)
    ) $charset_collate;";
    dbDelta($sql_riwayat_komisi);

    // 12. Cart
    $sql_cart = "CREATE TABLE {$table_prefix}cart ( 
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NULL,
        session_id VARCHAR(64) NULL,
        id_produk BIGINT(20) NOT NULL,
        id_variasi BIGINT(20) DEFAULT 0,
        qty INT NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_session (user_id, session_id)
    ) $charset_collate;";
    dbDelta( $sql_cart );

    // 13. Chat
    $sql_chat = "CREATE TABLE {$table_prefix}chat_message (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        produk_id BIGINT(20) DEFAULT 0,
        order_id BIGINT(20) DEFAULT 0,
        sender_id BIGINT(20) UNSIGNED NOT NULL,
        receiver_id BIGINT(20) UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        attachment_url VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY chat_pair (sender_id, receiver_id),
        KEY order_id (order_id)
    ) $charset_collate;";
    dbDelta( $sql_chat );

    // 14. Promosi
    $sql_promosi = "CREATE TABLE {$table_prefix}promosi (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    tipe ENUM('produk','wisata') NOT NULL,
    target_id BIGINT(20) NOT NULL,
    pemohon_id BIGINT(20) UNSIGNED NOT NULL,
    durasi_hari INT NOT NULL,
    biaya DECIMAL(10,2) NOT NULL,
    status ENUM('pending','aktif','selesai','ditolak') DEFAULT 'pending',
    mulai_tanggal DATETIME DEFAULT NULL,
    finished_tanggal DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id)
) $charset_collate;";
    dbDelta( $sql_chat );

    // 15. Ulasan
    $sql_ulasan = "CREATE TABLE {$table_prefix}ulasan (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        tipe VARCHAR(50) NOT NULL,
        target_id BIGINT(20) NOT NULL,
        target_type VARCHAR(20) NOT NULL DEFAULT 'post',
        user_id BIGINT(20) UNSIGNED NOT NULL,
        transaction_id BIGINT(20) DEFAULT NULL,
        rating INT(1) NOT NULL,
        komentar TEXT,
        status_moderasi VARCHAR(20) DEFAULT 'approved',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY target_index (target_id, target_type),
        KEY type_index (tipe)
    ) $charset_collate;";
    dbDelta( $sql_ulasan );

    // 16. Audit Logs
    $sql_logs = "CREATE TABLE {$table_prefix}logs (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        activity TEXT NOT NULL, 
        type VARCHAR(50) DEFAULT 'info',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta( $sql_logs );

    // 17. Banner
    $sql_banner = "CREATE TABLE {$table_prefix}banner (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        judul VARCHAR(255),
        gambar VARCHAR(255) NOT NULL,
        link VARCHAR(255),
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        prioritas INT DEFAULT 10,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_banner );
    
    // 18. User Alamat
    $sql_alamat = "CREATE TABLE {$table_prefix}user_alamat (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        nama_penerima VARCHAR(255),
        no_hp VARCHAR(20),
        alamat_lengkap TEXT,
        provinsi VARCHAR(100), kabupaten VARCHAR(100), kecamatan VARCHAR(100), kelurahan VARCHAR(100),
        kode_pos VARCHAR(10), 
        api_provinsi_id VARCHAR(20), api_kabupaten_id VARCHAR(20), api_kecamatan_id VARCHAR(20), api_kelurahan_id VARCHAR(20),
        is_default TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta( $sql_alamat );

    // 19. Revoked Tokens
    $sql_revoked = "CREATE TABLE {$table_prefix}revoked_tokens (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        token_hash VARCHAR(64) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        revoked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY idx_token_hash (token_hash)
    ) $charset_collate;";
    dbDelta( $sql_revoked );

    // 20. Refresh Tokens
    $sql_refresh = "CREATE TABLE {$table_prefix}refresh_tokens (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        token VARCHAR(255) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY token (token)
    ) $charset_collate;";
    dbDelta( $sql_refresh );

    // 21. WhatsApp Templates
    $sql_wa = "CREATE TABLE {$table_prefix}whatsapp_templates (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        kode VARCHAR(50) NOT NULL,
        judul VARCHAR(100) NOT NULL,
        template_pesan TEXT NOT NULL,
        trigger_event VARCHAR(50),
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode (kode)
    ) $charset_collate;";
    dbDelta( $sql_wa );

    // 22. Wishlist
    $sql_wishlist = "CREATE TABLE {$table_prefix}wishlist (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        item_id BIGINT(20) UNSIGNED NOT NULL,
        item_type VARCHAR(20) NOT NULL DEFAULT 'wisata', 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY item_lookup (item_id, item_type),
        UNIQUE KEY unique_like (user_id, item_id, item_type) 
    ) $charset_collate;";
    dbDelta( $sql_wishlist );

    // 23. Quota Logs
    $sql_quota_logs = "CREATE TABLE {$table_prefix}quota_logs (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) NOT NULL,
        quota_change INT(11) NOT NULL,
        type VARCHAR(50) NOT NULL,
        description TEXT,
        reference_id BIGINT(20) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta( $sql_quota_logs);

    // 24. Tabel Reward Referral
    $sql_referral_reward = "CREATE TABLE {$table_prefix}referral_reward (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_pedagang BIGINT(20) NOT NULL,
        id_user_baru BIGINT(20) UNSIGNED NOT NULL,
        kode_referral_used VARCHAR(50) NOT NULL,
        bonus_quota_diberikan INT DEFAULT 0,
        status ENUM('pending', 'verified', 'fraud') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_pedagang (id_pedagang),
        KEY id_user_baru (id_user_baru)
    ) $charset_collate;";
    dbDelta($sql_referral_reward);

    /* =========================================
       5. FINALISASI
       ========================================= */

    update_option( 'dw_core_db_version', '3.7' ); 
    
    // Log kesuksesan
    error_log( '[DW Core] Tabel database berhasil dibuat/diupdate.' );

    if ( ! function_exists( 'dw_create_roles_and_caps' ) ) {
        $roles_file = dirname( __FILE__ ) . '/roles-capabilities.php';
        if ( file_exists( $roles_file ) ) { require_once $roles_file; }
    }

    if ( function_exists( 'dw_create_roles_and_caps' ) ) {
        dw_create_roles_and_caps();
    }
    
    flush_rewrite_rules();
}

/**
 * FIX FATAL ERROR: Wrapper untuk aktivasi
 */
function dw_core_activate_plugin() {
    dw_activate_plugin();
}

/**
 * Registrasi Hook Aktivasi
 * * Logic sebelumnya mencoba menebak path file utama. Ini berisiko jika folder direname.
 * Gunakan konstanta DW_CORE_FILE yang didefinisikan di file utama plugin jika ada,
 * atau gunakan fallback ke path default.
 */
if ( defined( 'DW_CORE_FILE' ) ) {
    register_activation_hook( DW_CORE_FILE, 'dw_activate_plugin' );
} else {
    // Fallback manual jika constant belum di-load (jarang terjadi jika flow normal)
    $main_plugin_file = WP_PLUGIN_DIR . '/desa-wisata-core/desa-wisata-core.php';
    if (file_exists($main_plugin_file)) {
        register_activation_hook( $main_plugin_file, 'dw_activate_plugin' );
    }
}