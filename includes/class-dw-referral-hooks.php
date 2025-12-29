<?php
/**
 * Class DW_Referral_Hooks
 * Menghubungkan event Pendaftaran & Transaksi dengan Logic Referral.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Referral_Hooks {

    public function init() {
        // Saat Pedagang Register -> Cek Kode -> Bind ke Verifikator/Desa
        add_action( 'dw_after_pedagang_register', array( $this, 'bind_pedagang_to_referrer' ), 10, 2 );

        // Saat Pembeli Register -> Cek Kode Pedagang -> Kasih Bonus ke Pedagang
        add_action( 'dw_after_pembeli_register', array( $this, 'reward_pedagang_referral' ), 10, 2 );

        // Saat Admin ACC Pembelian Paket -> Cairkan Komisi
        add_action( 'dw_packet_purchase_approved', array( $this, 'process_commission_payment' ), 10, 2 );
    }

    /**
     * 1. Binding Pedagang ke Pemilik Kode
     */
    public function bind_pedagang_to_referrer( $pedagang_id, $post_data ) {
        global $wpdb;
        
        $referral_code = isset( $post_data['kode_referral'] ) ? sanitize_text_field( $post_data['kode_referral'] ) : '';

        // JIKA TIDAK ADA KODE: Verifikasi lari ke Admin (Desa/Verifikator tidak di-set secara spesifik untuk verifikasi ini)
        if ( empty( $referral_code ) ) {
            // Opsional: Anda bisa set flag khusus jika mau, tapi default NULL/0 sudah cukup.
            return;
        }

        // Cari pemilik
        $owner = DW_Referral_Logic::resolve_owner( $referral_code );

        if ( $owner ) {
            $update_data = array(
                'terdaftar_melalui_kode' => $referral_code
            );

            // LOGIKA VERIFIKASI SESUAI REQUEST:
            // Jika kode Verifikator -> Yang verifikasi Verifikator
            if ( $owner['type'] === 'verifikator' ) {
                $update_data['id_verifikator'] = $owner['id'];
                // Status pendaftaran tetap menunggu agar diverifikasi di halaman verifikator
            }
            // Jika kode Desa -> Yang verifikasi Desa
            elseif ( $owner['type'] === 'desa' ) {
                $update_data['id_desa'] = $owner['id'];
                $update_data['id_verifikator'] = 0; // Pastikan 0 agar tidak masuk list verifikator
            }

            $wpdb->update( "{$wpdb->prefix}dw_pedagang", $update_data, array( 'id' => $pedagang_id ) );
        }
    }

    /**
     * 2. Reward Kuota Pedagang (Saat Pembeli Daftar)
     */
    public function reward_pedagang_referral( $user_id_pembeli, $post_data ) {
        global $wpdb;
        $referral_code = isset( $post_data['kode_referral_pedagang'] ) ? sanitize_text_field( $post_data['kode_referral_pedagang'] ) : '';
        
        if ( empty( $referral_code ) ) return;

        $owner = DW_Referral_Logic::resolve_owner( $referral_code );

        if ( $owner && $owner['type'] === 'pedagang' ) {
            $pedagang_id = $owner['id'];
            $bonus_kuota = 5; // Configurable

            // Log Reward
            $wpdb->insert(
                "{$wpdb->prefix}dw_referral_reward",
                array(
                    'id_pedagang' => $pedagang_id,
                    'id_user_baru' => $user_id_pembeli,
                    'kode_referral_used' => $referral_code,
                    'bonus_quota_diberikan' => $bonus_kuota,
                    'status' => 'verified'
                )
            );

            // Tambah Kuota
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}dw_pedagang SET sisa_transaksi = sisa_transaksi + %d, total_referral_pembeli = total_referral_pembeli + 1 WHERE id = %d", $bonus_kuota, $pedagang_id ) );
        }
    }

    /**
     * 3. Trigger Komisi
     */
    public function process_commission_payment( $purchase_id, $pedagang_id ) {
        DW_Referral_Logic::distribute_commission( $purchase_id, $pedagang_id );
    }
}