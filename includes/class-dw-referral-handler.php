<?php
/**
 * File Name:       class-dw-referral-handler.php
 * File Folder:     includes/
 * Description:     Menangani logika referral pendaftaran pedagang dan atribusi komisi.
 * Logic Update: Mendukung split ownership (Milik Desa vs Milik Verifikator).
 * @package         DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Referral_Handler {

    /**
     * Cookie name for referral tracking
     */
    const COOKIE_NAME = 'dw_ref_code';

    /**
     * Duration of referral cookie (30 days)
     */
    const COOKIE_EXPIRY = 2592000; 

    public function __construct() {
        // 1. Capture Referral Code dari URL
        add_action( 'init', array( $this, 'capture_referral_code' ) );

        // 2. Hook saat Pedagang Baru Mendaftar/Dibuat
        // Asumsi action hook ini dipanggil di form registrasi pedagang
        add_action( 'dw_after_create_pedagang', array( $this, 'process_new_pedagang_referral' ), 10, 2 );

        // 3. Hook saat Pembeli Baru Mendaftar
        add_action( 'dw_after_create_pembeli', array( $this, 'process_new_pembeli_referral' ), 10, 2 );
    }

    /**
     * Menangkap ?ref=KODE dari URL dan simpan ke Cookie
     */
    public function capture_referral_code() {
        if ( isset( $_GET['ref'] ) && ! empty( $_GET['ref'] ) ) {
            $ref_code = sanitize_text_field( $_GET['ref'] );
            
            // Validasi kode referral ada di database (bisa milik Desa atau Verifikator)
            if ( $this->is_valid_referral_code( $ref_code ) ) {
                setcookie( self::COOKIE_NAME, $ref_code, time() + self::COOKIE_EXPIRY, COOKIEPATH, COOKIE_DOMAIN );
            }
        }
    }

    /**
     * Validasi apakah kode referral valid (milik Desa atau Verifikator aktif)
     */
    private function is_valid_referral_code( $code ) {
        global $wpdb;
        
        // Cek di tabel Desa
        $desa = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}dw_desa WHERE kode_referral = %s AND status = 'aktif'", $code ) );
        if ( $desa ) return true;

        // Cek di tabel Verifikator
        $verifikator = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}dw_verifikator WHERE kode_referral = %s AND status = 'aktif'", $code ) );
        if ( $verifikator ) return true;

        // Cek di tabel Pembeli
        $pembeli = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}dw_pembeli WHERE kode_referral = %s AND status_akun = 'aktif'", $code ) );
        if ( $pembeli ) return true;

        return false;
    }

    /**
     * Proses Referral saat Pedagang Baru Mendaftar
     * @param int $pedagang_id ID dari tabel dw_pedagang
     * @param int $user_id ID dari tabel wp_users
     */
    public function process_new_pedagang_referral( $pedagang_id, $user_id ) {
        global $wpdb;

        $ref_code = '';

        // Prioritas 1: Ambil dari Input Form (jika user manual input kode)
        if ( isset( $_POST['kode_referral'] ) && ! empty( $_POST['kode_referral'] ) ) {
            $ref_code = sanitize_text_field( $_POST['kode_referral'] );
        } 
        // Prioritas 2: Ambil dari Cookie
        elseif ( isset( $_COOKIE[self::COOKIE_NAME] ) ) {
            $ref_code = sanitize_text_field( $_COOKIE[self::COOKIE_NAME] );
        }

        if ( empty( $ref_code ) ) {
            return; // Tidak ada referral, skip
        }

        // --- LOGIKA UTAMA: Tentukan Pemilik Kode (Desa atau Verifikator) ---

        // 1. Cek Apakah Milik Desa?
        $desa = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user_desa FROM {$wpdb->prefix}dw_desa WHERE kode_referral = %s", $ref_code ) );
        
        if ( $desa ) {
            // Update Pedagang: Set id_desa, id_verifikator = 0
            $wpdb->update( 
                "{$wpdb->prefix}dw_pedagang", 
                array( 
                    'id_desa' => $desa->id,
                    'id_verifikator' => 0,
                    'terdaftar_melalui_kode' => $ref_code
                ), 
                array( 'id' => $pedagang_id ) 
            );

            // Simpan Meta: Tujuan Komisi = Desa
            update_user_meta( $user_id, 'dw_commission_dest_type', 'desa' );
            update_user_meta( $user_id, 'dw_commission_dest_id', $desa->id );
            
            // Log
            do_action( 'dw_log_activity', $user_id, "Mendaftar via Referral Desa: $ref_code" );
            return;
        }

        // 2. Cek Apakah Milik Verifikator?
        $verifikator = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user FROM {$wpdb->prefix}dw_verifikator WHERE kode_referral = %s", $ref_code ) );

        if ( $verifikator ) {
            // Update Pedagang: Set id_verifikator, id_desa bisa NULL atau ikut lokasi
            // Note: id_desa mungkin perlu diisi berdasarkan lokasi geografis nanti, tapi ownership referral ada di verifikator
            $wpdb->update( 
                "{$wpdb->prefix}dw_pedagang", 
                array( 
                    'id_verifikator' => $verifikator->id,
                    'terdaftar_melalui_kode' => $ref_code
                    // 'id_desa' dibiarkan NULL atau diisi logic geolocation terpisah
                ), 
                array( 'id' => $pedagang_id ) 
            );

            // Simpan Meta: Tujuan Komisi = Verifikator
            update_user_meta( $user_id, 'dw_commission_dest_type', 'verifikator' );
            update_user_meta( $user_id, 'dw_commission_dest_id', $verifikator->id );

            // Log
            do_action( 'dw_log_activity', $user_id, "Mendaftar via Referral Verifikator: $ref_code" );
            return;
        }
    }

    /**
     * Proses Referral saat Pembeli Baru Mendaftar
     * @param int $pembeli_id ID dari tabel dw_pembeli
     * @param int $user_id ID dari tabel wp_users
     */
    public function process_new_pembeli_referral( $pembeli_id, $user_id ) {
        global $wpdb;

        $ref_code = '';

        // Prioritas 1: Ambil dari Input Form
        if ( isset( $_POST['kode_referral'] ) && ! empty( $_POST['kode_referral'] ) ) {
            $ref_code = sanitize_text_field( $_POST['kode_referral'] );
        } 
        // Prioritas 2: Ambil dari Cookie
        elseif ( isset( $_COOKIE[self::COOKIE_NAME] ) ) {
            $ref_code = sanitize_text_field( $_COOKIE[self::COOKIE_NAME] );
        }

        if ( empty( $ref_code ) ) {
            return;
        }

        // 1. Cek Apakah Milik Pembeli Lain?
        $referrer = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user FROM {$wpdb->prefix}dw_pembeli WHERE kode_referral = %s", $ref_code ) );
        
        if ( $referrer ) {
            // Update Pembeli Baru: Set referrer_id, referrer_type, terdaftar_melalui_kode
            $wpdb->update( 
                "{$wpdb->prefix}dw_pembeli", 
                array( 
                    'referrer_id' => $referrer->id,
                    'referrer_type' => 'pembeli',
                    'terdaftar_melalui_kode' => $ref_code
                ), 
                array( 'id' => $pembeli_id ) 
            );

            // Berikan Poin ke Referrer
            $points = get_option('dw_buyer_referral_points', 10);
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}dw_pembeli SET poin_reward = poin_reward + %d WHERE id = %d",
                $points, $referrer->id
            ));

            // Log
            do_action( 'dw_log_activity', $user_id, "Mendaftar via Referral Pembeli: $ref_code" );
            do_action( 'dw_log_activity', $referrer->id_user, "Mendapatkan $points poin dari referral pembeli baru" );
            return;
        }

        // 2. Cek Apakah Milik Pedagang? (Opsional: Pembeli daftar lewat link toko)
        $pedagang = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user FROM {$wpdb->prefix}dw_pedagang WHERE kode_referral_saya = %s", $ref_code ) );
        if ( $pedagang ) {
            $wpdb->update( 
                "{$wpdb->prefix}dw_pembeli", 
                array( 
                    'referrer_id' => $pedagang->id,
                    'referrer_type' => 'pedagang',
                    'terdaftar_melalui_kode' => $ref_code
                ), 
                array( 'id' => $pembeli_id ) 
            );
            
            // Berikan bonus kuota ke pedagang jika diatur
            $bonus_quota = get_option('dw_bonus_quota_referral', 5);
            if ($bonus_quota > 0) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}dw_pedagang SET sisa_transaksi = sisa_transaksi + %d, total_referral_pembeli = total_referral_pembeli + 1 WHERE id = %d",
                    $bonus_quota, $pedagang->id
                ));
            }

            do_action( 'dw_log_activity', $user_id, "Mendaftar via Referral Pedagang: $ref_code" );
            return;
        }
    }
}

new DW_Referral_Handler();