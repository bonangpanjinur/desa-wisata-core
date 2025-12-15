<?php
/**
 * File Name:   class-dw-seeder.php
 * File Folder: includes/
 * File Path:   includes/class-dw-seeder.php
 * * Description: 
 * Class utilitas untuk menghasilkan data dummy (palsu) ke dalam database.
 * Berguna untuk keperluan testing tampilan dashboard dan fitur transaksi.
 * * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DW_Seeder {

    public static function run() {
        global $wpdb;

        // 1. Buat User Admin Desa (Dummy)
        $user_id = wp_create_user( 'admindesa_' . rand(100,999), 'password123', 'admindesa'.rand(100,999).'@example.com' );
        if ( ! is_wp_error( $user_id ) ) {
            $user = new WP_User( $user_id );
            $user->set_role( 'pengelola_desa' );
        } else {
            $user_id = get_current_user_id(); // Fallback
        }

        // 2. Buat Desa
        $wpdb->insert(
            $wpdb->prefix . 'dw_desa',
            [
                'id_user_desa' => $user_id,
                'nama_desa' => 'Desa Wisata Indah ' . rand(1, 100),
                'slug_desa' => 'desa-wisata-indah-' . rand(1, 100),
                'deskripsi' => 'Desa wisata yang asri dan sejuk.',
                'status' => 'aktif',
                'created_at' => current_time('mysql')
            ]
        );
        $desa_id = $wpdb->insert_id;

        // 3. Buat Pedagang & Produk
        $kategori = ['Kuliner', 'Kerajinan', 'Fashion', 'Oleh-oleh'];
        
        for ($i = 1; $i <= 5; $i++) {
            // Buat User Pedagang
            $pedagang_user_id = wp_create_user( 'pedagang_' . rand(1000,9999), 'password123', 'pedagang'.rand(1000,9999).'@example.com' );
            if ( is_wp_error( $pedagang_user_id ) ) continue;
            
            $u = new WP_User($pedagang_user_id);
            $u->set_role('pedagang');

            // Insert Table Pedagang
            $wpdb->insert(
                $wpdb->prefix . 'dw_pedagang',
                [
                    'id_user' => $pedagang_user_id,
                    'id_desa' => $desa_id,
                    'nama_toko' => 'Toko ' . $kategori[array_rand($kategori)] . ' ' . $i,
                    'slug_toko' => 'toko-' . $i . '-' . rand(100,999),
                    'nama_pemilik' => 'Bapak Pedagang ' . $i,
                    'nomor_wa' => '0812345678' . $i,
                    'status_akun' => 'aktif',
                    'status_pendaftaran' => 'disetujui'
                ]
            );
            $pedagang_id = $wpdb->insert_id;

            // Buat Produk
            for ($j = 1; $j <= 3; $j++) {
                $harga = rand(10, 500) * 1000;
                $wpdb->insert(
                    $wpdb->prefix . 'dw_produk',
                    [
                        'id_pedagang' => $pedagang_id,
                        'nama_produk' => 'Produk Contoh ' . $j . ' dari Toko ' . $i,
                        'slug' => 'produk-' . $i . '-' . $j . '-' . rand(1000,9999),
                        'deskripsi' => 'Deskripsi dummy produk berkualitas.',
                        'harga' => $harga,
                        'stok' => rand(10, 100),
                        'status' => 'aktif'
                    ]
                );
            }
        }

        // 4. Buat Transaksi Dummy (7 Hari Terakhir)
        for ($d = 0; $d < 7; $d++) {
            $trx_count = rand(2, 5); 
            $date = date('Y-m-d H:i:s', strtotime("-$d days"));

            for ($k = 0; $k < $trx_count; $k++) {
                $total = rand(50000, 500000);
                $wpdb->insert(
                    $wpdb->prefix . 'dw_transaksi',
                    [
                        'kode_unik' => 'TRX-DUMMY-' . rand(10000, 99999),
                        'id_pembeli' => get_current_user_id(),
                        'total_belanja' => $total,
                        'total_bayar' => $total + 15000,
                        'status_transaksi' => 'selesai',
                        'status_pembayaran' => 'paid',
                        'tanggal_transaksi' => $date,
                        'created_at' => $date
                    ]
                );
            }
        }
        
        return true;
    }
}
?>