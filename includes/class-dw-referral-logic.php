<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Referral_Logic {

    /**
     * Mencari data pemilik kode referral
     * @param string $code Kode yang diinput user
     * @return array|false Data pemilik kode (type, id, user_id, parent_desa_id)
     */
    public function get_referrer_data( $code ) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . 'dw_';

        if ( empty( $code ) ) {
            return false;
        }

        // 1. Cek apakah kode milik DESA
        // Table: dw_desa, Kolom: kode_referral
        $desa = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user_desa FROM {$table_prefix}desa WHERE kode_referral = %s", $code ) );
        if ( $desa ) {
            return [
                'type'           => 'desa',
                'id'             => $desa->id,        
                'user_id'        => $desa->id_user_desa,   
                'parent_desa_id' => $desa->id         
            ];
        }

        // 2. Cek apakah kode milik VERIFIKATOR
        // Table: dw_verifikator, Kolom: kode_referral
        $verif = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user FROM {$table_prefix}verifikator WHERE kode_referral = %s", $code ) );
        if ( $verif ) {
            return [
                'type'           => 'verifikator',
                'id'             => $verif->id,
                'user_id'        => $verif->id_user,
                // Catatan: Pada schema activation.php v3.6, dw_verifikator tidak memiliki kolom id_desa eksplisit.
                // Kita set 0 atau null, nanti logika bisnis bisa mencarinya via lokasi/api_kabupaten_id jika perlu.
                'parent_desa_id' => 0 
            ];
        }

        // 3. Cek apakah kode milik PEDAGANG (Untuk dipakai Pembeli)
        // Table: dw_pedagang, Kolom: kode_referral_saya
        $pedagang = $wpdb->get_row( $wpdb->prepare( "SELECT id, id_user FROM {$table_prefix}pedagang WHERE kode_referral_saya = %s", $code ) );
        if ( $pedagang ) {
            return [
                'type'    => 'pedagang',
                'id'      => $pedagang->id,
                'user_id' => $pedagang->id_user
            ];
        }

        return false;
    }

    /**
     * Generate Kode Referral Unik
     */
    public function generate_referral_code( $role, $name_fragment = 'USER' ) {
        $prefix = 'USR';
        if ( $role === 'desa' ) $prefix = 'DESA';
        if ( $role === 'verifikator' ) $prefix = 'VER';
        if ( $role === 'pedagang' ) $prefix = 'PDG';
        
        $clean_name = preg_replace('/[^A-Za-z0-9]/', '', $name_fragment);
        $name_code  = strtoupper( substr( $clean_name, 0, 3 ) );
        $random = strtoupper( substr( md5( time() . rand() ), 0, 4 ) );
        
        return $prefix . '-' . $name_code . '-' . $random;
    }

    /**
     * Tambah Bonus Kuota Transaksi ke Pedagang
     */
    public function add_transaction_quota( $pedagang_user_id, $code_used = '', $new_user_id = 0 ) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . 'dw_';
        
        // Ambil data pedagang
        $pedagang = $wpdb->get_row( $wpdb->prepare("SELECT id, sisa_transaksi FROM {$table_prefix}pedagang WHERE id_user = %d", $pedagang_user_id) );
        
        if(!$pedagang) return;

        $bonus_amount = (int) get_option( 'dw_referral_bonus_quota', 5 );
        $new_quota    = (int)$pedagang->sisa_transaksi + $bonus_amount;
        
        // Update tabel pedagang (kolom sisa_transaksi)
        $wpdb->update( 
            "{$table_prefix}pedagang", 
            [ 
                'sisa_transaksi' => $new_quota,
                'total_referral_pembeli' => $wpdb->raw('total_referral_pembeli + 1')
            ], 
            [ 'id_user' => $pedagang_user_id ] 
        );

        // Catat di tabel referral_reward
        $wpdb->insert(
            "{$table_prefix}referral_reward",
            [
                'id_pedagang' => $pedagang->id,
                'id_user_baru' => $new_user_id,
                'kode_referral_used' => $code_used,
                'bonus_quota_diberikan' => $bonus_amount,
                'status' => 'verified'
            ]
        );
        
        // Catat di quota logs
        $wpdb->insert(
            "{$table_prefix}quota_logs",
            [
                'user_id' => $pedagang_user_id,
                'quota_change' => $bonus_amount,
                'type' => 'referral_bonus',
                'description' => "Bonus referral dari user ID $new_user_id",
                'reference_id' => $new_user_id
            ]
        );
    }
}