<?php
/**
 * File Name:   class-dw-seeder.php
 * File Folder: includes/
 * File Path:   includes/class-dw-seeder.php
 * * Description: 
 * Class utilitas untuk menghasilkan data dummy (palsu) ke dalam database.
 * Diperbarui: Mengutamakan pengisian data Wisata dan Produk ke Desa yang sudah ada.
 * * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DW_Seeder {

    public static function run() {
        global $wpdb;

        // 1. AMBIL DESA YANG SUDAH ADA (Agar tidak spam Desa baru)
        // Kita ambil satu desa secara acak untuk diisi datanya
        $desa_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}dw_desa ORDER BY RAND() LIMIT 1");
        
        // Jika belum ada desa sama sekali, baru kita buat
        if ( ! $desa_id ) {
            $user_id = wp_create_user( 'admindesa_' . rand(100,999), 'password123', 'admindesa'.rand(100,999).'@example.com' );
            if ( is_wp_error( $user_id ) ) $user_id = get_current_user_id();

            $wpdb->insert(
                $wpdb->prefix . 'dw_desa',
                [
                    'id_user_desa' => $user_id,
                    'nama_desa' => 'Desa Wisata Sejahtera',
                    'slug_desa' => 'desa-wisata-sejahtera-' . rand(100,999),
                    'deskripsi' => 'Desa wisata percontohan dengan banyak wahana menarik.',
                    'status' => 'aktif',
                    'created_at' => current_time('mysql')
                ]
            );
            $desa_id = $wpdb->insert_id;
        }

        // 2. GENERATE DATA WISATA (WAHANA/OBJEK)
        // Kita buat daftar variatif agar terlihat nyata
        $daftar_wisata = [
            ['nama' => 'Curug Naga', 'harga' => 15000, 'ket' => 'Air terjun eksotis dengan tebing bebatuan.'],
            ['nama' => 'Bukit Bintang', 'harga' => 5000, 'ket' => 'Pemandangan kota dari ketinggian saat malam hari.'],
            ['Taman Bunga Matahari', 'harga' => 10000, 'ket' => 'Hamparan bunga matahari yang instagramable.'],
            ['Kampung Budaya', 'harga' => 20000, 'ket' => 'Belajar kesenian dan kerajinan tangan warga lokal.'],
            ['River Tubing Sungai Elo', 'harga' => 35000, 'ket' => 'Wisata air menyusuri sungai dengan ban.'],
            ['Hutan Pinus Asri', 'harga' => 7500, 'ket' => 'Suasana hutan pinus yang sejuk dan tenang.'],
            ['Pasar Pagi Tradisional', 'harga' => 0, 'ket' => 'Pusat kuliner jaman dulu, buka setiap Minggu Pon.'],
            ['Museum Desa', 'harga' => 5000, 'ket' => 'Menyimpan sejarah dan peninggalan leluhur desa.'],
            ['Kolam Renang Alami', 'harga' => 10000, 'ket' => 'Kolam renang tanpa kaporit langsung dari mata air.'],
            ['Flying Fox Park', 'harga' => 25000, 'ket' => 'Wahana pemacu adrenalin di atas lembah.']
        ];

        foreach ($daftar_wisata as $w) {
            // Cek agar tidak duplikat di desa yang sama
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}dw_wisata WHERE nama_wisata = %s AND id_desa = %d",
                $w['nama'], $desa_id
            ));

            if ( ! $exists ) {
                $wpdb->insert(
                    $wpdb->prefix . 'dw_wisata',
                    [
                        'id_desa' => $desa_id,
                        'nama_wisata' => $w['nama'],
                        'slug_wisata' => sanitize_title($w['nama']) . '-' . rand(100, 999),
                        'deskripsi' => $w['ket'],
                        'harga_tiket' => $w['harga'],
                        'jam_buka' => '08:00',
                        'jam_tutup' => '17:00',
                        'lokasi_lat' => '-7.' . rand(100000, 999999),
                        'lokasi_lng' => '110.' . rand(100000, 999999),
                        'status' => 'aktif',
                        'created_at' => current_time('mysql')
                    ]
                );
            }
        }

        // 3. GENERATE PEDAGANG & PRODUK (TOKO)
        $kategori_toko = ['Warung Makan', 'Souvenir', 'Fashion Batik', 'Oleh-oleh Snack'];
        
        // Tambahkan 3 toko baru setiap kali run
        for ($i = 1; $i <= 3; $i++) {
            $rand_cat = $kategori_toko[array_rand($kategori_toko)];
            
            // Buat User Pedagang Dummy
            $email_pedagang = 'pedagang' . rand(10000,99999) . '@dummy.com';
            $pedagang_user_id = wp_create_user( 'user_' . rand(10000,99999), 'password', $email_pedagang );
            
            if ( ! is_wp_error( $pedagang_user_id ) ) {
                $u = new WP_User($pedagang_user_id);
                $u->set_role('pedagang');
                
                // Insert Pedagang
                $wpdb->insert(
                    $wpdb->prefix . 'dw_pedagang',
                    [
                        'id_user' => $pedagang_user_id,
                        'id_desa' => $desa_id,
                        'nama_toko' => $rand_cat . ' Buatan Ibu ' . rand(1, 100),
                        'slug_toko' => sanitize_title($rand_cat) . '-' . rand(1000,9999),
                        'nama_pemilik' => 'Ibu Pedagang ' . rand(1, 50),
                        'nomor_wa' => '0812' . rand(10000000, 99999999),
                        'status_akun' => 'aktif',
                        'status_pendaftaran' => 'disetujui'
                    ]
                );
                $pedagang_id = $wpdb->insert_id;

                // Insert 3-5 Produk untuk Toko ini
                $jumlah_produk = rand(3, 5);
                for ($j = 1; $j <= $jumlah_produk; $j++) {
                    $harga_produk = rand(5, 100) * 1000;
                    $wpdb->insert(
                        $wpdb->prefix . 'dw_produk',
                        [
                            'id_pedagang' => $pedagang_id,
                            'nama_produk' => 'Produk ' . $rand_cat . ' Unggulan ' . $j,
                            'slug' => 'produk-' . $pedagang_id . '-' . $j . '-' . rand(100,999),
                            'deskripsi' => 'Produk berkualitas tinggi asli buatan desa wisata.',
                            'harga' => $harga_produk,
                            'stok' => rand(10, 50),
                            'status' => 'aktif'
                        ]
                    );
                }
            }
        }

        // 4. GENERATE TRANSAKSI (Agar dashboard ada isinya)
        $pembeli_id = get_current_user_id();
        for ($k = 0; $k < 5; $k++) {
            $total = rand(20000, 150000);
            $wpdb->insert(
                $wpdb->prefix . 'dw_transaksi',
                [
                    'kode_unik' => 'TRX-' . date('ymd') . '-' . rand(1000, 9999),
                    'id_pembeli' => $pembeli_id,
                    'total_belanja' => $total,
                    'total_bayar' => $total + rand(1000, 5000), // plus ongkir/admin
                    'status_transaksi' => 'selesai',
                    'status_pembayaran' => 'paid',
                    'tanggal_transaksi' => date('Y-m-d H:i:s', strtotime('-'.rand(0, 3).' days')),
                    'created_at' => current_time('mysql')
                ]
            );
        }
        
        return true;
    }
}
?>