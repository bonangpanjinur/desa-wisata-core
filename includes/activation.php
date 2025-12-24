<?php
/**
 * File Name:   activation.php
 * File Folder: includes/
 * Description: File aktivasi plugin yang berisi seluruh skema database custom.
 * * UPDATE: 
 * - Menghapus fungsi dw_setup_roles() internal untuk mencegah duplikasi.
 * - Delegasi pembuatan role ke includes/roles-capabilities.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dw_activate_plugin() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = $wpdb->prefix . 'dw_';

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

       /* =========================================
       1. ENTITAS UTAMA (MASTER DATA)
       ========================================= */

    $sql_desa = "CREATE TABLE {$table_prefix}desa (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user_desa BIGINT(20) UNSIGNED NOT NULL,
        nama_desa VARCHAR(255) NOT NULL,
        slug_desa VARCHAR(255) NOT NULL,
        deskripsi TEXT,
        foto VARCHAR(255) DEFAULT NULL,
        
        -- Field Keuangan
        total_pendapatan DECIMAL(15,2) DEFAULT 0,
        no_rekening_desa VARCHAR(50) DEFAULT NULL,
        nama_bank_desa VARCHAR(100) DEFAULT NULL,
        atas_nama_rekening_desa VARCHAR(100) DEFAULT NULL,
        qris_image_url_desa VARCHAR(255) DEFAULT NULL,
        
        -- Field Status & Lokasi
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

        -- [BARU] Field Akses Fitur Verifikasi Pedagang
        status_akses_verifikasi ENUM('locked', 'pending', 'active') DEFAULT 'locked',
        bukti_bayar_akses VARCHAR(255) DEFAULT NULL,

        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_user_desa (id_user_desa),
        KEY slug_desa (slug_desa),
        KEY idx_lokasi (api_kabupaten_id)
    ) $charset_collate;";
    dbDelta( $sql_desa );


    // 2. Tabel Pedagang (UMKM)
    $sql_pedagang = "CREATE TABLE {$table_prefix}pedagang (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user BIGINT(20) UNSIGNED NOT NULL,
        id_desa BIGINT(20) DEFAULT NULL,
        nama_toko VARCHAR(255) NOT NULL,
        slug_toko VARCHAR(255) NOT NULL,
        nama_pemilik VARCHAR(255) NOT NULL,
        nomor_wa VARCHAR(20) NOT NULL,
        alamat_lengkap TEXT,
        url_gmaps TEXT DEFAULT NULL,
        url_ktp VARCHAR(255),
        nik VARCHAR(50),
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
        is_independent TINYINT(1) DEFAULT 1,
        approved_by VARCHAR(20) DEFAULT NULL,
        sisa_transaksi INT DEFAULT 0,
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        KEY id_desa (id_desa),
        KEY slug_toko (slug_toko)
    ) $charset_collate;";
    dbDelta($sql_pedagang);

    // 2B. Tabel Ojek (NEW - Driver)
     $sql_ojek = "CREATE TABLE {$table_prefix}ojek (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_user BIGINT(20) UNSIGNED NOT NULL,
        nama_lengkap VARCHAR(255) NOT NULL,
        no_hp VARCHAR(20) NOT NULL,
        nik VARCHAR(50),
        no_kartu_ojek VARCHAR(50), 
        plat_nomor VARCHAR(20) NOT NULL,
        merk_motor VARCHAR(100) NOT NULL,
        
        -- Foto Dokumen
        foto_profil VARCHAR(255),
        foto_ktp VARCHAR(255),
        foto_kartu_ojek VARCHAR(255), 
        foto_motor VARCHAR(255),
        
        status_pendaftaran ENUM('menunggu','disetujui','ditolak') DEFAULT 'menunggu',
        status_kerja ENUM('offline','online','busy') DEFAULT 'offline',
        
        -- Filter Wilayah
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
       3. TRANSAKSI (E-COMMERCE FLOW & OJEK)
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
        
        -- Data Khusus Ojek
        ojek_data JSON DEFAULT NULL, 
        
        provinsi VARCHAR(100),
        kabupaten VARCHAR(100),
        kecamatan VARCHAR(100),
        kelurahan VARCHAR(100),
        kode_pos VARCHAR(10),
        metode_pembayaran VARCHAR(50),
        status_transaksi ENUM(
            'menunggu_pembayaran','pembayaran_dikonfirmasi','pembayaran_gagal','diproses','dikirim','selesai','dibatalkan','refunded',
            'menunggu_driver', 'penawaran_driver', 'nego', 'menunggu_penjemputan', 'dalam_perjalanan'
        ) DEFAULT 'menunggu_pembayaran',
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
       4. MODEL 3: PAKET & KOMISI
       ========================================= */

    // 9. Tabel Paket Transaksi
    $sql_paket = "CREATE TABLE {$table_prefix}paket_transaksi (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        nama_paket VARCHAR(100) NOT NULL,
        deskripsi TEXT,
        harga DECIMAL(15,2) NOT NULL,
        jumlah_transaksi INT NOT NULL,
        target_role ENUM('pedagang','ojek') NOT NULL DEFAULT 'pedagang', 
        persentase_komisi_desa DECIMAL(5,2) DEFAULT 0,
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_paket );

    // 10. Tabel Pembelian Paket
    $sql_pembelian = "CREATE TABLE {$table_prefix}pembelian_paket (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        id_pedagang BIGINT(20) NOT NULL,
        id_paket BIGINT(20) NOT NULL,
        nama_paket_snapshot VARCHAR(100) NOT NULL,
        harga_paket DECIMAL(15,2) NOT NULL,
        jumlah_transaksi INT NOT NULL,
        persentase_komisi_desa DECIMAL(5,2) DEFAULT 0,
        url_bukti_bayar VARCHAR(255),
        status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
        catatan_admin TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY id_pedagang (id_pedagang)
    ) $charset_collate;";
    dbDelta( $sql_pembelian );

    // 11. Tabel Payout Ledger
    $sql_ledger = "CREATE TABLE {$table_prefix}payout_ledger (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        order_id BIGINT(20) NOT NULL, 
        payable_to_type ENUM('desa','platform') NOT NULL,
        payable_to_id BIGINT(20) NOT NULL, 
        amount DECIMAL(15,2) NOT NULL,
        status ENUM('unpaid','paid') DEFAULT 'unpaid',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY status_lookup (payable_to_type, payable_to_id, status)
    ) $charset_collate;";
    dbDelta( $sql_ledger );

    /* =========================================
       5. PENDUKUNG LAINNYA
       ========================================= */

    // 12. Tabel Cart
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

    // 13. Tabel Chat Message
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

    // 14. Tabel Promosi
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
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_promosi );

    // 15. Tabel Ulasan (UNIVERSAL)
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

    // 16. Tabel Logs
    $sql_logs = "CREATE TABLE {$table_prefix}logs (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        aksi VARCHAR(50) NOT NULL, 
        keterangan TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_logs );

    // 17. Tabel Banner
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
    
    // 18. Tabel User Alamat
    $sql_alamat = "CREATE TABLE {$table_prefix}user_alamat (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        nama_penerima VARCHAR(255),
        no_hp VARCHAR(20),
        alamat_lengkap TEXT,
        provinsi VARCHAR(100),
        kabupaten VARCHAR(100),
        kecamatan VARCHAR(100),
        kelurahan VARCHAR(100),
        kode_pos VARCHAR(10),
        api_provinsi_id VARCHAR(20),
        api_kabupaten_id VARCHAR(20),
        api_kecamatan_id VARCHAR(20),
        api_kelurahan_id VARCHAR(20),
        is_default TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta( $sql_alamat );

    // 19. Tabel Revoked Tokens
    $sql_revoked = "CREATE TABLE {$table_prefix}revoked_tokens (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        token_hash VARCHAR(64) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        revoked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY token_hash (token_hash)
    ) $charset_collate;";
    dbDelta( $sql_revoked );

    // 20. Tabel Refresh Tokens
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

    // 21. Tabel Template WhatsApp
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

    // 22. Tabel Wishlist
    $table_wishlist = $wpdb->prefix . 'dw_wishlist';
    $sql_wishlist = "CREATE TABLE $table_wishlist (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        item_id bigint(20) UNSIGNED NOT NULL,
        item_type varchar(20) NOT NULL DEFAULT 'wisata', -- 'wisata' atau 'produk'
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY item_lookup (item_id, item_type),
        UNIQUE KEY unique_like (user_id, item_id, item_type) 
    ) $charset_collate;";
    dbDelta( $sql_wishlist );

    /* =========================================
       6. FITUR OJEK & KEUANGAN (NEW)
       ========================================= */

    // 23. Tabel Log Kuota
    $table_quota_logs = $table_prefix . 'quota_logs';
    $sql_quota_logs = "CREATE TABLE $table_quota_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        quota_change int(11) NOT NULL,
        type varchar(50) NOT NULL,
        description text,
        reference_id bigint(20) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta($sql_quota_logs);

    // Update versi DB
    update_option( 'dw_core_db_version', '1.1.3' ); 
    
    // Roles Setup (Delegasi ke roles-capabilities.php agar tidak duplikat)
    // Pastikan file roles-capabilities sudah di-load jika belum (tergantung context)
    if ( ! function_exists( 'dw_create_roles_and_caps' ) ) {
        require_once dirname( __FILE__ ) . '/roles-capabilities.php';
    }

    if ( function_exists( 'dw_create_roles_and_caps' ) ) {
        dw_create_roles_and_caps();
    }
    
    flush_rewrite_rules();
}

/**
 * Alias wrapper for legacy support if needed
 */
function dw_core_activate_plugin() {
    dw_activate_plugin();
}
?>