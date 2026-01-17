<?php
/**
 * Activation Handler
 * Path: includes/activation.php
 * Description: Menangani pembuatan dan update struktur tabel database (Full Enterprise Schema).
 * Version: 2.7.0 (Financial System Integrated)
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}

function dw_activation_run() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    /* =========================================
       1. ENTITAS UTAMA (MASTER DATA)
       ========================================= */

    // 1. Tabel Desa
    $table_desa = $wpdb->prefix . 'dw_desa';
    $sql_desa = "CREATE TABLE $table_desa (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_user_desa bigint(20) UNSIGNED NOT NULL,
        nama_desa varchar(255) NOT NULL,
        slug_desa varchar(255) NOT NULL,
        kode_referral varchar(50) DEFAULT NULL,
        deskripsi text,
        nomor_wa varchar(20) DEFAULT NULL,
        foto varchar(255) DEFAULT NULL,
        foto_sampul varchar(255) DEFAULT NULL,
        foto_admin varchar(255) DEFAULT NULL,
        total_pendapatan decimal(15,2) DEFAULT 0,
        saldo_komisi decimal(15,2) DEFAULT 0,
        no_rekening_desa varchar(50) DEFAULT NULL,
        nama_bank_desa varchar(100) DEFAULT NULL,
        atas_nama_rekening_desa varchar(100) DEFAULT NULL,
        qris_image_url_desa varchar(255) DEFAULT NULL,
        status enum('aktif','pending') DEFAULT 'pending',
        provinsi varchar(100),
        kabupaten varchar(100),
        kecamatan varchar(100),
        kelurahan varchar(100),
        api_provinsi_id varchar(20),
        api_kabupaten_id varchar(20),
        api_kecamatan_id varchar(20),
        api_kelurahan_id varchar(20),
        jam_buka time DEFAULT NULL,
        jam_tutup time DEFAULT NULL,
        alamat_lengkap text,
        kode_pos varchar(10) DEFAULT NULL,
        status_akses_verifikasi enum('locked', 'pending', 'active') DEFAULT 'locked',
        bukti_bayar_akses varchar(255) DEFAULT NULL,
        alasan_penolakan text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY kode_referral (kode_referral),
        KEY id_user_desa (id_user_desa),
        KEY slug_desa (slug_desa),
        KEY idx_lokasi (api_kabupaten_id)
    ) $charset_collate;";
    dbDelta($sql_desa);

    // 2. Tabel Pedagang
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $sql_pedagang = "CREATE TABLE $table_pedagang (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_user bigint(20) UNSIGNED NOT NULL,
        id_desa bigint(20) DEFAULT NULL,
        id_verifikator bigint(20) DEFAULT 0,
        nama_toko varchar(255) NOT NULL,
        slug_toko varchar(255) NOT NULL,
        kode_referral_saya varchar(50) DEFAULT NULL,
        terdaftar_melalui_kode varchar(50) DEFAULT NULL,
        nama_pemilik varchar(255) NOT NULL,
        nomor_wa varchar(20) NOT NULL,
        alamat_lengkap text,
        url_gmaps text DEFAULT NULL,
        latitude decimal(10,8),
        longitude decimal(11,8),
        fcm_token varchar(255),
        url_ktp varchar(255),
        nik varchar(50),
        foto_admin varchar(255) DEFAULT NULL,
        foto_profil varchar(255),
        foto_sampul varchar(255),
        no_rekening varchar(50) DEFAULT NULL,
        nama_bank varchar(100) DEFAULT NULL,
        atas_nama_rekening varchar(100) DEFAULT NULL,
        qris_image_url varchar(255) DEFAULT NULL,
        order_notification_sound varchar(255) DEFAULT NULL,
        order_notification_type enum('upload', 'youtube', 'default') DEFAULT 'default',
        rating_toko decimal(3,2) DEFAULT 0,
        total_ulasan_toko int(11) DEFAULT 0,
        status_pendaftaran enum('menunggu','disetujui','ditolak','menunggu_desa') DEFAULT 'menunggu_desa',
        status_akun enum('aktif','nonaktif','suspend','nonaktif_habis_kuota') DEFAULT 'nonaktif',
        is_verified tinyint(1) DEFAULT 0,
        verified_at datetime DEFAULT NULL,
        is_independent tinyint(1) DEFAULT 1,
        approved_by varchar(20) DEFAULT NULL,
        sisa_transaksi int(11) DEFAULT 0,
        total_referral_pembeli int(11) DEFAULT 0,
        jam_buka time DEFAULT NULL,
        jam_tutup time DEFAULT NULL,
        shipping_ojek_lokal_aktif tinyint(1) DEFAULT 0,
        shipping_ojek_lokal_zona longtext, 
        shipping_nasional_aktif tinyint(1) DEFAULT 0,
        shipping_nasional_harga decimal(15,2) DEFAULT 0,
        shipping_profiles longtext,
        allow_pesan_di_tempat tinyint(1) DEFAULT 0,
        galeri longtext,
        api_provinsi_id varchar(20),
        api_kabupaten_id varchar(20),
        api_kecamatan_id varchar(20),
        api_kelurahan_id varchar(20),
        provinsi_nama varchar(100),
        kabupaten_nama varchar(100),
        kecamatan_nama varchar(100),
        kelurahan_nama varchar(100),
        kode_pos varchar(10) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        UNIQUE KEY kode_referral_saya (kode_referral_saya),
        KEY id_desa (id_desa),
        KEY id_verifikator (id_verifikator),
        KEY slug_toko (slug_toko),
        KEY idx_lokasi_geo (latitude, longitude)
    ) $charset_collate;";
    dbDelta($sql_pedagang);

    // 2B. Tabel Ojek (Driver)
    $table_ojek = $wpdb->prefix . 'dw_ojek';
    $sql_ojek = "CREATE TABLE $table_ojek (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_user bigint(20) UNSIGNED NOT NULL,
        nama_lengkap varchar(255) NOT NULL,
        no_hp varchar(20) NOT NULL,
        nik varchar(50),
        no_kartu_ojek varchar(50),
        plat_nomor varchar(20) NOT NULL,
        merk_motor varchar(100) NOT NULL,
        foto_profil varchar(255),
        foto_ktp varchar(255),
        foto_kartu_ojek varchar(255),
        foto_motor varchar(255),
        status_pendaftaran enum('menunggu','disetujui','ditolak') DEFAULT 'menunggu',
        status_kerja enum('offline','online','busy') DEFAULT 'offline',
        api_provinsi_id varchar(20),
        api_kabupaten_id varchar(20),
        api_kecamatan_id varchar(20),
        api_kelurahan_id varchar(20),
        alamat_domisili text,
        rating_avg decimal(3,2) DEFAULT 0,
        total_trip int(11) DEFAULT 0,
        lokasi_terakhir_lat varchar(50),
        lokasi_terakhir_lng varchar(50),
        last_heartbeat datetime DEFAULT NULL,
        fcm_token varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        KEY idx_lokasi_ojek (api_kecamatan_id, status_kerja)
    ) $charset_collate;";
    dbDelta($sql_ojek);

    // 2C. Tabel Verifikator UMKM
    $table_verifikator = $wpdb->prefix . 'dw_verifikator';
    $sql_verifikator = "CREATE TABLE $table_verifikator (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        nama_lengkap varchar(255) NOT NULL,
        foto_profil varchar(255) DEFAULT NULL,
        nik varchar(50) NOT NULL,
        kode_referral varchar(50),
        nomor_wa varchar(20) NOT NULL,
        alamat_lengkap text,
        provinsi varchar(100),
        kabupaten varchar(100),
        kecamatan varchar(100),
        kelurahan varchar(100),
        api_provinsi_id varchar(20),
        api_kabupaten_id varchar(20),
        api_kecamatan_id varchar(20),
        api_kelurahan_id varchar(20),
        
        -- DATA KEUANGAN VERIFIKATOR (Pastikan kolom ini ada)
        no_rekening varchar(50) DEFAULT NULL,
        nama_bank varchar(100) DEFAULT NULL,
        atas_nama_rekening varchar(100) DEFAULT NULL,
        
        total_verifikasi_sukses int(11) DEFAULT 0,
        total_pendapatan_komisi decimal(15,2) DEFAULT 0,
        saldo_saat_ini decimal(15,2) DEFAULT 0,
        kode_pos varchar(10) DEFAULT NULL,
        status enum('aktif','pending','nonaktif') DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_id (user_id),
        UNIQUE KEY kode_referral (kode_referral)
    ) $charset_collate;";
    dbDelta( $sql_verifikator );

    // 2D. Tabel Pembeli (Wisatawan/Member)
    $table_pembeli = $wpdb->prefix . 'dw_pembeli';
    $sql_pembeli = "CREATE TABLE $table_pembeli (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_user bigint(20) UNSIGNED NOT NULL,
        nama_lengkap varchar(255) NOT NULL,
        no_hp varchar(20),
        nik varchar(50),
        foto_profil varchar(255),
        tgl_lahir date DEFAULT NULL,
        jenis_kelamin enum('L','P') DEFAULT NULL,
        alamat_lengkap text,
        provinsi varchar(100),
        kabupaten varchar(100),
        kecamatan varchar(100),
        kelurahan varchar(100),
        api_provinsi_id varchar(20),
        api_kabupaten_id varchar(20),
        api_kecamatan_id varchar(20),
        api_kelurahan_id varchar(20),
        kode_pos varchar(10) DEFAULT NULL,
        kode_referral varchar(50) DEFAULT NULL,
        poin_reward int(11) DEFAULT 0,
        terdaftar_melalui_kode varchar(50) DEFAULT NULL, 
        referrer_id bigint(20) DEFAULT 0, 
        referrer_type varchar(50) DEFAULT NULL,
        status_akun enum('aktif','suspend','banned') DEFAULT 'aktif',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY id_user (id_user),
        KEY idx_referral (terdaftar_melalui_kode)
    ) $charset_collate;";
    dbDelta($sql_pembeli);

    /* =========================================
       2. KONTEN (INVENTORY & WISATA)
       ========================================= */

    // 3. Tabel Wisata
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $sql_wisata = "CREATE TABLE $table_wisata (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_desa bigint(20) NOT NULL,
        nama_wisata varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        kategori varchar(100),
        deskripsi longtext,
        harga_tiket decimal(15,2) DEFAULT 0,
        jam_buka time DEFAULT NULL,
        jam_tutup time DEFAULT NULL,
        fasilitas text,
        kontak_pengelola varchar(50),
        lokasi_maps text,
        foto_utama varchar(255),
        video_url varchar(255),
        galeri longtext, 
        rating_avg decimal(3,2) DEFAULT 0,
        total_ulasan int(11) DEFAULT 0,
        status enum('aktif','nonaktif') DEFAULT 'aktif',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY id_desa (id_desa),
        KEY slug (slug),
        KEY kategori (kategori)
    ) $charset_collate;";
    dbDelta($sql_wisata);

    // 4. Tabel Produk
    $table_produk = $wpdb->prefix . 'dw_produk';
    $sql_produk = "CREATE TABLE $table_produk (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_pedagang bigint(20) NOT NULL,
        nama_produk varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        deskripsi longtext,
        harga decimal(15,2) NOT NULL DEFAULT 0,
        harga_coret decimal(15,2) DEFAULT 0,
        stok int(11) DEFAULT 0,
        berat_gram int(11) DEFAULT 0,
        kondisi enum('baru','bekas') DEFAULT 'baru',
        kategori varchar(100),
        foto_utama varchar(255),
        galeri longtext,
        terjual int(11) DEFAULT 0,
        rating_avg decimal(3,2) DEFAULT 0,
        dilihat int(11) DEFAULT 0,
        is_featured tinyint(1) DEFAULT 0,
        status enum('aktif','nonaktif','habis','arsip') DEFAULT 'aktif',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY id_pedagang (id_pedagang),
        KEY slug (slug),
        KEY harga (harga),
        KEY kategori (kategori),
        INDEX idx_deleted (deleted_at)
    ) $charset_collate;";
    dbDelta($sql_produk);

    // 5. Tabel Variasi Produk
    $table_variasi = $wpdb->prefix . 'dw_produk_variasi';
    $sql_variasi = "CREATE TABLE $table_variasi (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_produk bigint(20) NOT NULL,
        deskripsi_variasi varchar(255) NOT NULL,
        harga_variasi decimal(15,2) NOT NULL,
        stok_variasi int(11) DEFAULT 0,
        sku varchar(100),
        foto varchar(255) DEFAULT NULL,
        is_default tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id),
        KEY id_produk (id_produk)
    ) $charset_collate;";
    dbDelta($sql_variasi);

    /* =========================================
       3. TRANSAKSI (E-COMMERCE FLOW)
       ========================================= */

    // 6. Tabel Transaksi Utama (E-Commerce)
    // Updated: Menambahkan kolom Profit Sharing dan Payment Channel
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';
    $sql_transaksi = "CREATE TABLE $table_transaksi (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        kode_unik varchar(50) NOT NULL,
        invoice_id varchar(50), 
        id_pembeli bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20), 
        customer_name varchar(100), 
        total_produk decimal(15,2) DEFAULT 0,
        total_ongkir decimal(15,2) DEFAULT 0,
        biaya_layanan decimal(15,2) DEFAULT 0,
        total_diskon decimal(15,2) DEFAULT 0,
        kode_promo_used varchar(50) DEFAULT NULL,
        total_transaksi decimal(15,2) DEFAULT 0,
        total_amount decimal(15,2), 
        discount_amount decimal(15,2) DEFAULT 0, 
        
        -- PROFIT SHARING (KEUANGAN)
        platform_fee decimal(15,2) DEFAULT 0, -- Keuntungan Sistem
        partner_commission decimal(15,2) DEFAULT 0, -- Komisi Mitra
        
        payment_token varchar(255) DEFAULT NULL,
        payment_url text DEFAULT NULL,
        xendit_external_id varchar(100),
        xendit_payment_method varchar(50),
        payment_channel varchar(50), -- OVO, DANA, BCA, etc
        paid_at datetime,
        
        nama_penerima varchar(255),
        no_hp varchar(20),
        alamat_lengkap text,
        ojek_data longtext, 
        provinsi varchar(100),
        kabupaten varchar(100),
        kecamatan varchar(100),
        kelurahan varchar(100),
        kode_pos varchar(10),
        metode_pembayaran varchar(50),
        status_transaksi enum('menunggu_pembayaran','pembayaran_dikonfirmasi','pembayaran_gagal','diproses','dikirim','selesai','dibatalkan','refunded','menunggu_driver','penawaran_driver','nego','menunggu_penjemputan','dalam_perjalanan') DEFAULT 'menunggu_pembayaran',
        status varchar(20) DEFAULT 'pending', 
        items_json longtext, 
        bukti_pembayaran varchar(255) DEFAULT NULL,
        catatan_pembeli text,
        tanggal_transaksi datetime DEFAULT CURRENT_TIMESTAMP,
        batas_bayar datetime DEFAULT NULL,
        tanggal_pembayaran datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode_unik (kode_unik),
        KEY id_pembeli (id_pembeli),
        KEY status_transaksi (status_transaksi),
        KEY idx_created_at (created_at),
        KEY idx_xendit (xendit_external_id)
    ) $charset_collate;";
    dbDelta($sql_transaksi);

    // 7. Tabel Sub Transaksi (Per Toko)
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $sql_sub = "CREATE TABLE $table_sub (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_transaksi bigint(20) NOT NULL,
        id_pedagang bigint(20) NOT NULL,
        nama_toko varchar(255),
        sub_total decimal(15,2) NOT NULL,
        ongkir decimal(15,2) NOT NULL,
        total_pesanan_toko decimal(15,2) NOT NULL,
        metode_pengiriman varchar(100),
        kurir_nama varchar(100),
        kurir_layanan varchar(100),
        no_resi varchar(100) DEFAULT NULL,
        status_pesanan enum('menunggu_konfirmasi','diproses','diantar_ojek','dikirim_ekspedisi','selesai','dibatalkan','lunas') DEFAULT 'menunggu_konfirmasi',
        alasan_batal text DEFAULT NULL,
        total_refund decimal(15,2) DEFAULT 0,
        alasan_refund text DEFAULT NULL,
        catatan_penjual text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_transaksi (id_transaksi),
        KEY id_pedagang (id_pedagang)
    ) $charset_collate;";
    dbDelta($sql_sub);

    // 8. Tabel Item Transaksi (Detail Produk)
    $table_items = $wpdb->prefix . 'dw_transaksi_items';
    $sql_items = "CREATE TABLE $table_items (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_sub_transaksi bigint(20) NOT NULL,
        id_produk bigint(20) NOT NULL,
        id_variasi bigint(20) DEFAULT 0,
        nama_produk varchar(255) NOT NULL,
        foto_snapshot varchar(255) DEFAULT NULL,
        berat_snapshot int(11) DEFAULT 0,
        nama_variasi varchar(255) DEFAULT NULL,
        harga_normal decimal(15,2) DEFAULT 0,
        diskon_item decimal(15,2) DEFAULT 0,
        ditanggung_oleh enum('platform','pedagang') DEFAULT 'pedagang',
        harga_satuan decimal(15,2) NOT NULL,
        jumlah int(11) NOT NULL,
        total_harga decimal(15,2) NOT NULL,
        catatan_item text,
        is_reviewed tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id),
        KEY id_sub_transaksi (id_sub_transaksi),
        KEY id_produk (id_produk)
    ) $charset_collate;";
    dbDelta($sql_items);

    /* =========================================
       4. MODEL BISNIS & DUKUNGAN
       ========================================= */

    // 9. Paket Transaksi
    $table_paket = $wpdb->prefix . 'dw_paket_transaksi';
    $sql_paket = "CREATE TABLE $table_paket (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        nama_paket varchar(100) NOT NULL,
        deskripsi text,
        harga decimal(15,2) NOT NULL,
        jumlah_transaksi int(11) NOT NULL,
        target_role enum('pedagang','ojek') NOT NULL DEFAULT 'pedagang', 
        persentase_komisi decimal(5,2) DEFAULT 0,
        komisi_nominal decimal(15,2) DEFAULT 0,
        status enum('aktif','nonaktif') DEFAULT 'aktif',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_paket);

    // 10. Pembelian Paket (Topup Kuota)
    // Updated: Menambahkan kolom lengkap untuk Xendit & Profit Sharing
    $table_pembelian = $wpdb->prefix . 'dw_pembelian_paket';
    $sql_pembelian = "CREATE TABLE $table_pembelian (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_pedagang bigint(20) NOT NULL,
        user_id bigint(20), 
        id_paket bigint(20) NOT NULL,
        paket_id int(11), 
        nama_paket_snapshot varchar(100),
        harga_paket decimal(15,2) NOT NULL,
        harga decimal(15,2), 
        jumlah_transaksi int(11) NOT NULL,
        
        referrer_id bigint(20) DEFAULT 0, 
        referrer_type enum('desa','verifikator') DEFAULT NULL,
        
        -- KEUANGAN & PROFIT SHARING
        platform_fee decimal(15,2) DEFAULT 0, -- Keuntungan Sistem
        partner_commission decimal(15,2) DEFAULT 0, -- Komisi Referral/Mitra
        persentase_komisi_referrer decimal(5,2) DEFAULT 0,
        komisi_nominal_cair decimal(15,2) DEFAULT 0,
        
        -- PEMBAYARAN (XENDIT)
        url_bukti_bayar varchar(255),
        status enum('pending','disetujui','ditolak','paid','expired') DEFAULT 'pending',
        catatan_admin text,
        xendit_external_id varchar(100),
        payment_url text, -- Link bayar Xendit
        payment_channel varchar(50), -- Metode bayar (BCA, QRIS, etc)
        payment_method varchar(50), -- Virtual Account, Retail, etc
        paid_at datetime,
        
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        processed_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY id_pedagang (id_pedagang),
        KEY idx_referrer (referrer_id, referrer_type),
        KEY idx_xendit_paket (xendit_external_id)
    ) $charset_collate;";
    dbDelta($sql_pembelian);

    // 11. Payout Ledger
    $table_ledger = $wpdb->prefix . 'dw_payout_ledger';
    $sql_ledger = "CREATE TABLE $table_ledger (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL, 
        payable_to_type varchar(50) NOT NULL, 
        payable_to_id bigint(20) NOT NULL, 
        amount decimal(18,2) NOT NULL,
        status varchar(50) DEFAULT 'unpaid',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        paid_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY status_lookup (payable_to_type, payable_to_id, status)
    ) $charset_collate;";
    dbDelta($sql_ledger);

    // 11B. Riwayat Komisi Masuk
    $table_riwayat = $wpdb->prefix . 'dw_riwayat_komisi';
    $sql_riwayat = "CREATE TABLE $table_riwayat (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_penerima bigint(20) NOT NULL,
        role_penerima varchar(50) NOT NULL,
        id_sumber_pedagang bigint(20) NOT NULL,
        id_pembelian_paket bigint(20) NOT NULL,
        jumlah_komisi decimal(15,2) NOT NULL,
        keterangan text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_penerima (id_penerima, role_penerima)
    ) $charset_collate;";
    dbDelta($sql_riwayat);

    // 12. Cart
    $table_cart = $wpdb->prefix . 'dw_cart';
    $sql_cart = "CREATE TABLE $table_cart ( 
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NULL,
        session_id varchar(64) NULL,
        id_produk bigint(20) NOT NULL,
        id_variasi bigint(20) DEFAULT 0,
        qty int(11) NOT NULL DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_session (user_id, session_id)
    ) $charset_collate;";
    dbDelta($sql_cart);

    // 13. Chat
    $table_chat = $wpdb->prefix . 'dw_chat_message';
    $sql_chat = "CREATE TABLE $table_chat (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        produk_id bigint(20) DEFAULT 0,
        order_id bigint(20) DEFAULT 0,
        sender_id bigint(20) UNSIGNED NOT NULL,
        receiver_id bigint(20) UNSIGNED NOT NULL,
        message text NOT NULL,
        is_read tinyint(1) DEFAULT 0,
        attachment_url varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY chat_pair (sender_id, receiver_id),
        KEY order_id (order_id)
    ) $charset_collate;";
    dbDelta($sql_chat);

    // 14. Promosi
    $table_promosi = $wpdb->prefix . 'dw_promosi';
    $sql_promosi = "CREATE TABLE $table_promosi (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        tipe enum('produk','wisata') NOT NULL,
        target_id bigint(20) NOT NULL,
        pemohon_id bigint(20) UNSIGNED NOT NULL,
        durasi_hari int(11) NOT NULL,
        biaya decimal(10,2) NOT NULL,
        status enum('pending','aktif','selesai','ditolak') DEFAULT 'pending',
        mulai_tanggal datetime DEFAULT NULL,
        finished_tanggal datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_promosi);

    // 15. Ulasan
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';
    $sql_ulasan = "CREATE TABLE $table_ulasan (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        tipe varchar(50) NOT NULL,
        target_id bigint(20) NOT NULL,
        target_type varchar(20) NOT NULL DEFAULT 'post',
        user_id bigint(20) UNSIGNED NOT NULL,
        transaction_id bigint(20) DEFAULT NULL,
        rating int(1) NOT NULL,
        komentar text,
        status_moderasi varchar(20) DEFAULT 'approved',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY target_index (target_id, target_type),
        KEY type_index (tipe)
    ) $charset_collate;";
    dbDelta($sql_ulasan);

    // 16. Audit Logs
    $table_logs = $wpdb->prefix . 'dw_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED DEFAULT 0,
        actor_id bigint(20) UNSIGNED DEFAULT 0,
        activity text NOT NULL, 
        type varchar(50) DEFAULT 'info',
        details longtext, 
        ip_address varchar(50), 
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY idx_created (created_at)
    ) $charset_collate;";
    dbDelta($sql_logs);

    // 17. Banner
    $table_banner = $wpdb->prefix . 'dw_banner';
    $sql_banner = "CREATE TABLE $table_banner (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        judul varchar(255),
        gambar varchar(255) NOT NULL,
        link varchar(255),
        status enum('aktif','nonaktif') DEFAULT 'aktif',
        prioritas int(11) DEFAULT 10,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_banner);
    
    // 19. Revoked Tokens
    $table_revoked = $wpdb->prefix . 'dw_revoked_tokens';
    $sql_revoked = "CREATE TABLE $table_revoked (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        token_hash varchar(64) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        revoked_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY idx_token_hash (token_hash)
    ) $charset_collate;";
    dbDelta($sql_revoked);

    // 20. Refresh Tokens
    $table_refresh = $wpdb->prefix . 'dw_refresh_tokens';
    $sql_refresh = "CREATE TABLE $table_refresh (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        token varchar(255) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        expires_at datetime NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY token (token)
    ) $charset_collate;";
    dbDelta($sql_refresh);

    // 21. WhatsApp Templates
    $table_wa = $wpdb->prefix . 'dw_whatsapp_templates';
    $sql_wa = "CREATE TABLE $table_wa (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        kode varchar(50) NOT NULL,
        judul varchar(100) NOT NULL,
        template_pesan text NOT NULL,
        trigger_event varchar(50),
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode (kode)
    ) $charset_collate;";
    dbDelta($sql_wa);

    // 22. Favorites (Wishlist)
    $table_fav = $wpdb->prefix . 'dw_favorites';
    $sql_fav = "CREATE TABLE $table_fav (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        object_id bigint(20) UNSIGNED NOT NULL,
        object_type varchar(20) NOT NULL DEFAULT 'produk',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY object_lookup (object_id, object_type),
        UNIQUE KEY user_object (user_id, object_id, object_type)
    ) $charset_collate;";
    dbDelta($sql_fav);

    // 23. Quota Logs
    $table_quota = $wpdb->prefix . 'dw_quota_logs';
    $sql_quota = "CREATE TABLE $table_quota (
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
    dbDelta($sql_quota);

    // 24. Tabel Reward Referral
    $table_reward = $wpdb->prefix . 'dw_referral_reward';
    $sql_reward = "CREATE TABLE $table_reward (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        id_pedagang bigint(20) NOT NULL,
        id_user_baru bigint(20) UNSIGNED NOT NULL,
        kode_referral_used varchar(50) NOT NULL,
        bonus_quota_diberikan int(11) DEFAULT 0,
        status enum('pending', 'verified', 'fraud') DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY id_pedagang (id_pedagang),
        KEY id_user_baru (id_user_baru)
    ) $charset_collate;";
    dbDelta($sql_reward);

    // 25. Tabel Kupon
    $table_coupon = $wpdb->prefix . 'dw_coupons';
    $sql_coupon = "CREATE TABLE $table_coupon (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        kode varchar(50) NOT NULL,
        nominal decimal(15,2) NOT NULL,
        expired_at date NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kode (kode)
    ) $charset_collate;";
    dbDelta($sql_coupon);

    // 26. Tabel Komplain
    $table_complaints = $wpdb->prefix . 'dw_complaints';
    $sql_complaints = "CREATE TABLE $table_complaints (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        keterangan text NOT NULL,
        status enum('open','resolved') DEFAULT 'open',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_complaints);

    /* =========================================
       6. FITUR KEUANGAN & WALLET (NEW)
       ========================================= */

    // 27. Tabel Saldo/Dompet (NEW - Untuk Desa & Verifikator)
    $table_wallet = $wpdb->prefix . 'dw_wallet';
    $sql_wallet = "CREATE TABLE $table_wallet (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL, -- User ID WordPress (Bisa Verifikator/Admin Desa)
        balance decimal(15,2) NOT NULL DEFAULT 0,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta($sql_wallet);

    // 28. Tabel Mutasi Saldo / Wallet Logs (NEW - Buku Besar)
    $table_wallet_logs = $wpdb->prefix . 'dw_wallet_logs';
    $sql_wallet_logs = "CREATE TABLE $table_wallet_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        wallet_id bigint(20) NOT NULL,
        transaction_id bigint(20) DEFAULT NULL, -- ID Referensi (Misal ID Pembelian Paket)
        withdrawal_id bigint(20) DEFAULT NULL, -- ID Referensi Penarikan
        type varchar(20) NOT NULL, -- credit (masuk) / debit (keluar)
        amount decimal(15,2) NOT NULL DEFAULT 0,
        description varchar(255),
        balance_after decimal(15,2) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY wallet_id (wallet_id)
    ) $charset_collate;";
    dbDelta($sql_wallet_logs);

    // 29. Tabel Withdrawal / Penarikan (NEW - Riwayat Request)
    $table_withdrawals = $wpdb->prefix . 'dw_withdrawals';
    $sql_withdrawals = "CREATE TABLE $table_withdrawals (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        amount decimal(15,2) NOT NULL,
        
        -- Snapshot Data Bank (Penting untuk History jika user ganti rek)
        bank_code varchar(20) NOT NULL,
        account_number varchar(50) NOT NULL,
        account_name varchar(100) NOT NULL,
        
        status varchar(20) DEFAULT 'pending', -- pending, processing, completed, failed
        xendit_disbursement_id varchar(100),
        failure_reason text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta($sql_withdrawals);

    // 30. Tabel Log Pembayaran Xendit (NEW - Debugging)
    $table_payment_logs = $wpdb->prefix . 'dw_payment_logs';
    $sql_payment_logs = "CREATE TABLE $table_payment_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_type varchar(50) NOT NULL, -- invoice.paid, disbursement.succeeded
        reference_id varchar(100),
        gateway_id varchar(100),
        payload longtext,
        status varchar(20),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_payment_logs);

    /* =========================================
       5. FINALISASI
       ========================================= */

    update_option('dw_core_db_version', '4.1.0'); // Bump version to force update
    
    // Log kesuksesan
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[DW Core] Tabel database Enterprise + Financial Features berhasil dibuat/diupdate.');
    }

    if (function_exists('dw_create_roles_and_caps')) {
        dw_create_roles_and_caps();
    }
    
    flush_rewrite_rules();
}

/**
 * Backward Compatibility Wrapper
 */
function dw_core_activate_plugin() {
    dw_activation_run();
}

/**
 * Registrasi Hook Aktivasi
 */
if (defined('DW_CORE_FILE')) {
    register_activation_hook(DW_CORE_FILE, 'dw_activation_run');
}