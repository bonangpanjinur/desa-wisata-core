<?php
/**
 * File Name:   includes/class-dw-referral-logic.php
 * Description: Mesin utama pengolah reward referral (Pedagang ke Pembeli).
 * Menangani deteksi link, pencatatan reward, dan pemberian kuota gratis.
 */

if (!defined('ABSPATH')) exit;

class DW_Referral_Logic {

    /**
     * Inisialisasi Hook
     */
    public static function init() {
        // Tangkap parameter 'ref' saat pengunjung datang
        add_action('init', [__CLASS__, 'capture_referral_cookie']);
        
        // Berikan hadiah saat user sukses mendaftar (hook standar WP)
        add_action('user_register', [__CLASS__, 'process_referral_reward'], 10, 1);
    }

    /**
     * 1. Simpan kode referral di Cookie (agar tetap ingat meskipun daftar 1 jam kemudian)
     */
    public static function capture_referral_cookie() {
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            $ref_code = sanitize_text_field($_GET['ref']);
            // Simpan cookie selama 24 jam
            setcookie('dw_referral_code', $ref_code, time() + (86400 * 1), COOKIEPATH, COOKIE_DOMAIN);
        }
    }

    /**
     * 2. Proses Pemberian Hadiah Kuota
     */
    public static function process_referral_reward($user_id) {
        global $wpdb;
        
        // Ambil kode referral dari cookie
        $ref_code = isset($_COOKIE['dw_referral_code']) ? sanitize_text_field($_COOKIE['dw_referral_code']) : '';
        
        if (empty($ref_code)) return;

        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        $table_reward   = $wpdb->prefix . 'dw_referral_reward';
        $table_logs     = $wpdb->prefix . 'dw_quota_logs';

        // Cari siapa pemilik kode referral ini (Pedagang)
        $pedagang = $wpdb->get_row($wpdb->prepare(
            "SELECT id, id_user, sisa_transaksi FROM $table_pedagang WHERE kode_referral_saya = %s", 
            $ref_code
        ));

        if ($pedagang) {
            $bonus_quota = get_option('dw_bonus_quota_referral', 5);

            // 1. Catat di Tabel Reward (Riwayat)
            $wpdb->insert($table_reward, [
                'id_pedagang'           => $pedagang->id,
                'id_user_baru'          => $user_id,
                'kode_referral_used'    => $ref_code,
                'bonus_quota_diberikan' => $bonus_quota,
                'status'                => 'verified'
            ]);

            // 2. Tambah Sisa Transaksi Pedagang
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_pedagang SET sisa_transaksi = sisa_transaksi + %d, total_referral_pembeli = total_referral_pembeli + 1 WHERE id = %d",
                $bonus_quota, $pedagang->id
            ));

            // 3. Catat Log Kuota untuk Audit
            $wpdb->insert($table_logs, [
                'user_id'      => $pedagang->id_user,
                'quota_change' => $bonus_quota,
                'type'         => 'referral_bonus',
                'description'  => 'Bonus referral pembeli baru (User ID: '.$user_id.')'
            ]);

            // Hapus cookie setelah digunakan
            setcookie('dw_referral_code', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
    }
}

// Jalankan Mesin
DW_Referral_Logic::init();