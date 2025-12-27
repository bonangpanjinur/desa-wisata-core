<?php
/**
 * File Name:   includes/class-dw-referral-handler.php
 * Description: Class utama untuk menangani logika sistem referral global v3.9.
 * Menangani generate kode unik, validasi pemilik, dan pencatatan relasi jaringan.
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Referral_Handler {

    /**
     * 1. GENERATE KODE REFERRAL UNIK
     * Membuat kode acak dengan prefix tertentu dan memastikan belum dipakai.
     * * @param string $type Jenis entitas ('desa', 'pedagang', 'verifikator', 'pembeli')
     * @return string Kode unik (Contoh: VF-AB123)
     */
    public static function generate_code($type = 'pembeli') {
        global $wpdb;
        
        $prefix_map = [
            'desa'        => 'DS', // Desa
            'pedagang'    => 'UM', // UMKM
            'verifikator' => 'VF', // Verifikator
            'pembeli'     => 'PB'  // Pembeli
        ];

        $prefix = isset($prefix_map[$type]) ? $prefix_map[$type] : 'USR';
        $is_unique = false;
        $code = '';

        // Loop sampai menemukan kode yang belum ada di database manapun
        while (!$is_unique) {
            // Format: PREFIX + 5 Karakter Random (Angka/Huruf) -> Contoh: VF-9A2K1
            $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
            $code = $prefix . '-' . $random;

            // Cek ketersediaan di semua tabel
            $exists_desa = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE kode_referal = %s", $code));
            $exists_umkm = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE kode_referal = %s", $code));
            $exists_verif = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_verifikator WHERE kode_referal = %s", $code));
            $exists_user = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pembeli WHERE kode_referal = %s", $code));

            if (!$exists_desa && !$exists_umkm && !$exists_verif && !$exists_user) {
                $is_unique = true;
            }
        }

        return $code;
    }

    /**
     * 2. CARI PEMILIK KODE (UPLINE)
     * Mencari tahu kode referral ini milik siapa.
     * * @param string $code Kode referral yang diinput
     * @return array|false Data pemilik ['type', 'id', 'name'] atau false jika invalid
     */
    public static function get_referral_owner($code) {
        global $wpdb;
        $code = strtoupper(sanitize_text_field($code));

        // 1. Cek Verifikator (Prioritas)
        $verif = $wpdb->get_row($wpdb->prepare("SELECT id, nama_lengkap FROM {$wpdb->prefix}dw_verifikator WHERE kode_referal = %s AND status = 'aktif'", $code));
        if ($verif) return ['type' => 'verifikator', 'id' => $verif->id, 'name' => $verif->nama_lengkap];

        // 2. Cek Desa
        $desa = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE kode_referal = %s", $code));
        if ($desa) return ['type' => 'desa', 'id' => $desa->id, 'name' => $desa->nama_desa];

        // 3. Cek Pedagang
        $umkm = $wpdb->get_row($wpdb->prepare("SELECT id, nama_toko FROM {$wpdb->prefix}dw_pedagang WHERE kode_referal = %s", $code));
        if ($umkm) return ['type' => 'pedagang', 'id' => $umkm->id, 'name' => $umkm->nama_toko];

        // 4. Cek Pembeli
        $pembeli = $wpdb->get_row($wpdb->prepare("SELECT id, nama_pembeli FROM {$wpdb->prefix}dw_pembeli WHERE kode_referal = %s", $code));
        if ($pembeli) return ['type' => 'pembeli', 'id' => $pembeli->id, 'name' => $pembeli->nama_pembeli];

        return false;
    }

    /**
     * 3. CATAT RELASI (SIAPA AJAK SIAPA)
     * Menyimpan log permanen ke tabel dw_referral_relations
     * * @param string $child_type Tipe yang mendaftar ('pedagang' / 'pembeli')
     * @param int $child_id ID dari yang mendaftar (Primary Key tabel ybs)
     * @param string $referral_code Kode yang digunakan
     * @return bool True jika berhasil
     */
    public static function log_relation($child_type, $child_id, $referral_code) {
        global $wpdb;

        if (empty($referral_code)) return false;

        $owner = self::get_referral_owner($referral_code);

        if ($owner) {
            // Simpan ke tabel relations
            $wpdb->insert(
                $wpdb->prefix . 'dw_referral_relations',
                array(
                    'referral_code' => $referral_code,
                    'parent_type'   => $owner['type'],
                    'parent_id'     => $owner['id'],
                    'child_type'    => $child_type,
                    'child_id'      => $child_id,
                    'created_at'    => current_time('mysql')
                ),
                array('%s', '%s', '%d', '%s', '%d', '%s')
            );

            // Update statistik Parent (Opsional, contoh logika reward)
            // Misalnya: Menambah poin ke Verifikator/Desa
            // self::add_reward_point($owner['type'], $owner['id']);

            return true;
        }

        return false;
    }

    /**
     * 4. SET KODE REFERRAL UNTUK USER LAMA (Utility)
     * Bisa dijalankan via Cron atau tombol Admin untuk mengisi kode kosong.
     */
    public static function populate_missing_codes() {
        global $wpdb;

        // Populate Verifikator
        $verifs = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}dw_verifikator WHERE kode_referal IS NULL OR kode_referal = ''");
        foreach($verifs as $v) {
            $wpdb->update("{$wpdb->prefix}dw_verifikator", ['kode_referal' => self::generate_code('verifikator')], ['id' => $v->id]);
        }

        // Populate Pedagang
        $umkms = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE kode_referal IS NULL OR kode_referal = ''");
        foreach($umkms as $u) {
            $wpdb->update("{$wpdb->prefix}dw_pedagang", ['kode_referal' => self::generate_code('pedagang')], ['id' => $u->id]);
        }
    }
}