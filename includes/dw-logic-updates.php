<?php
/**
 * File Name:   includes/dw-logic-updates.php
 * Description: Logika Inti untuk Relasi Otomatis berdasarkan Wilayah dan Sistem Komisi Berjenjang.
 * * File ini menangani:
 * 1. Sinkronisasi otomatis pedagang ke desa wisata berdasarkan kesamaan Kelurahan.
 * 2. Pencarian pedagang independen saat sebuah desa wisata baru didaftarkan.
 * 3. Perhitungan komisi paket transaksi yang bergantung pada pihak verifikator (Admin vs Desa).
 */

if (!defined('ABSPATH')) exit;

class DW_Logic_Updates {

    /**
     * Inisialisasi hook WordPress
     */
    public static function init() {
        // Trigger saat pedagang disimpan atau diperbarui untuk mengecek relasi desa
        add_action('save_post_pedagang', [__CLASS__, 'sync_pedagang_relation'], 10, 3);
        
        // Trigger saat desa wisata baru mendaftar untuk mengklaim pedagang independen di wilayahnya
        add_action('save_post_desa_wisata', [__CLASS__, 'sync_new_desa_to_merchants'], 10, 3);
    }

    /**
     * Sinkronisasi Pedagang ke Desa berdasarkan Kelurahan
     * * Jika pedagang memiliki alamat kelurahan yang sama dengan desa wisata,
     * maka otomatis akan tercatat sebagai pedagang di bawah desa tersebut.
     */
    public static function sync_pedagang_relation($post_id, $post, $update) {
        // Hindari autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'pedagang') return;

        // Ambil data kelurahan dari meta pedagang
        $kelurahan = get_post_meta($post_id, '_kelurahan', true);
        if (empty($kelurahan)) return;

        // Cari Desa Wisata yang memiliki kelurahan yang sama
        $desas = get_posts([
            'post_type' => 'desa_wisata',
            'meta_query' => [
                [
                    'key' => '_kelurahan',
                    'value' => $kelurahan,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        if (!empty($desas)) {
            // Jika ditemukan desa yang cocok di wilayah yang sama
            $desa_id = $desas[0]->ID;
            update_post_meta($post_id, '_desa_id', $desa_id);
            update_post_meta($post_id, '_is_independent', 'no');
        } else {
            // Jika tidak ada desa terdaftar di wilayah tersebut, status menjadi independen
            update_post_meta($post_id, '_desa_id', '');
            update_post_meta($post_id, '_is_independent', 'yes');
        }
    }

    /**
     * Menghubungkan Pedagang Independen ke Desa Baru
     * * Jika sebuah kelurahan baru mendaftarkan akun Desa Wisata, maka semua
     * pedagang independen di kelurahan tersebut akan otomatis terhubung ke desa ini.
     */
    public static function sync_new_desa_to_merchants($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'desa_wisata') return;

        $kelurahan = get_post_meta($post_id, '_kelurahan', true);
        if (empty($kelurahan)) return;

        // Cari semua pedagang yang saat ini berstatus 'independen' di kelurahan ini
        $pedagangs = get_posts([
            'post_type' => 'pedagang',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_kelurahan',
                    'value' => $kelurahan,
                    'compare' => '='
                ],
                [
                    'key' => '_is_independent',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ]
        ]);

        // Hubungkan setiap pedagang yang ditemukan ke desa baru tersebut
        foreach ($pedagangs as $p) {
            update_post_meta($p->ID, '_desa_id', $post_id);
            update_post_meta($p->ID, '_is_independent', 'no');
        }
    }

    /**
     * Logika Perhitungan Komisi untuk Desa
     * * Menghitung berapa besar komisi yang didapat desa dari pembelian paket pedagang.
     * Syarat: 
     * 1. Pedagang harus terhubung dengan Desa tersebut.
     * 2. Pendaftaran pedagang harus di-ACC/Approve oleh pihak Desa (bukan Admin pusat).
     */
    public static function calculate_desa_commission($pedagang_id, $paket_id, $total_amount) {
        // Ambil data siapa yang meng-ACC (approved_by: 'desa' atau 'admin')
        $approved_by = get_post_meta($pedagang_id, '_approved_by', true); 
        $desa_id = get_post_meta($pedagang_id, '_desa_id', true);

        /**
         * KONDISI KHUSUS:
         * Jika tidak ada relasi desa atau jika verifikasi dilakukan oleh Admin pusat,
         * maka desa tidak mendapatkan persentase (komisi = 0).
         */
        if (empty($desa_id) || $approved_by !== 'desa') {
            return 0;
        }

        // Ambil persentase komisi yang diatur pada paket transaksi tersebut
        $percentage = get_post_meta($paket_id, '_commission_percentage', true);
        if (!$percentage || $percentage <= 0) return 0;

        // Hitung nilai nominal komisi
        return ($percentage / 100) * $total_amount;
    }
}

// Jalankan inisialisasi logika
DW_Logic_Updates::init();