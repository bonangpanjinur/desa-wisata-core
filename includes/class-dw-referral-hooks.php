<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Referral_Hooks {

    private $logic;

    public function __construct() {
        $this->logic = new DW_Referral_Logic();
        add_action( 'user_register', [ $this, 'handle_user_registration' ], 10, 1 );
    }

    public function handle_user_registration( $user_id ) {
        $user_info = get_userdata( $user_id );
        $roles     = $user_info->roles;
        $name      = $user_info->display_name;
        $input_code = isset( $_POST['referral_code'] ) ? sanitize_text_field( $_POST['referral_code'] ) : '';

        global $wpdb;
        $table_prefix = $wpdb->prefix . 'dw_';

        // --- 1. PEDAGANG ---
        if ( in_array( 'pedagang', $roles ) || in_array( 'umkm', $roles ) ) {
            // A. Relasi
            $this->process_pedagang_relation( $user_id, $input_code, $name );
            
            // B. Generate Kode Sendiri
            $new_code = $this->logic->generate_referral_code( 'pedagang', $name );
            
            // Update table dw_pedagang
            $wpdb->update( 
                "{$table_prefix}pedagang", 
                ['kode_referral_saya' => $new_code], 
                ['id_user' => $user_id] 
            );
        }

        // --- 2. PEMBELI ---
        if ( in_array( 'subscriber', $roles ) || in_array( 'pembeli', $roles ) ) {
            $this->process_pembeli_bonus( $user_id, $input_code );
        }

        // --- 3. DESA ---
        if ( in_array( 'desa', $roles ) ) {
            $new_code = $this->logic->generate_referral_code( 'desa', $name );
            $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$table_prefix}desa WHERE id_user_desa = %d", $user_id) );
            
            if($exists) {
                $wpdb->update( "{$table_prefix}desa", ['kode_referral' => $new_code], ['id_user_desa' => $user_id] );
            } else {
                // Buat row baru dengan slug dan data dummy mandatory
                $slug = sanitize_title($name);
                $wpdb->insert( "{$table_prefix}desa", [
                    'id_user_desa' => $user_id, 
                    'nama_desa' => $name, 
                    'slug_desa' => $slug,
                    'kode_referral' => $new_code,
                    'status' => 'pending'
                ]);
            }
        }

        // --- 4. VERIFIKATOR ---
        if ( in_array( 'verifikator', $roles ) ) {
            $new_code = $this->logic->generate_referral_code( 'verifikator', $name );
            $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$table_prefix}verifikator WHERE id_user = %d", $user_id) );
            
            if($exists) {
                $wpdb->update( "{$table_prefix}verifikator", ['kode_referral' => $new_code], ['id_user' => $user_id] );
            } else {
                // Insert baru
                $wpdb->insert( "{$table_prefix}verifikator", [
                    'id_user' => $user_id, 
                    'nama_lengkap' => $name, 
                    'nik' => '-', // Mandatory field di schema v3.6
                    'nomor_wa' => '-',
                    'kode_referral' => $new_code
                ]);
            }
        }
    }

    private function process_pedagang_relation( $user_id, $code, $shop_name ) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . 'dw_';
        
        $referrer_data = $this->logic->get_referrer_data( $code );

        $id_desa        = 0; // null di DB, tapi kita set 0 jika perlu atau null
        $id_verifikator = 0;
        $is_independent = 1; // 1 = Admin Managed / Independent
        $terdaftar_via  = $code;

        if ( $referrer_data ) {
            $is_independent = 0; // Terhubung ke Desa/Verifikator
            
            if ( $referrer_data['type'] === 'desa' ) {
                $id_desa = $referrer_data['id'];
            } elseif ( $referrer_data['type'] === 'verifikator' ) {
                $id_verifikator = $referrer_data['id'];
                // Karena schema verifikator tidak punya kolom parent desa, kita biarkan id_desa kosong/null
                // Atau set id_desa = 0.
            }
        }

        // Cek data pedagang
        $row_exists = $wpdb->get_row( $wpdb->prepare("SELECT id FROM {$table_prefix}pedagang WHERE id_user = %d", $user_id) );

        $data = [
            'id_desa'         => $id_desa ?: NULL,
            'id_verifikator'  => $id_verifikator,
            'is_independent'  => $is_independent,
            'terdaftar_melalui_kode' => $terdaftar_via
        ];

        if ( $row_exists ) {
            $wpdb->update( "{$table_prefix}pedagang", $data, ['id_user' => $user_id] );
        } else {
            // Insert data baru (Schema Pedagang v3.6 banyak kolom not null)
            // Kita isi default dummy untuk field NOT NULL
            $slug = sanitize_title($shop_name);
            $insert_data = array_merge($data, [
                'id_user' => $user_id,
                'nama_toko' => $shop_name,
                'slug_toko' => $slug,
                'nama_pemilik' => $shop_name,
                'nomor_wa' => '-',
                'status_pendaftaran' => 'menunggu_desa'
            ]);
            $wpdb->insert( "{$table_prefix}pedagang", $insert_data );
        }
    }

    private function process_pembeli_bonus( $user_id, $code ) {
        if ( empty( $code ) ) return;
        $referrer_data = $this->logic->get_referrer_data( $code );

        if ( $referrer_data && $referrer_data['type'] === 'pedagang' ) {
            $pedagang_user_id = $referrer_data['user_id'];
            update_user_meta( $user_id, 'dw_referred_by_pedagang', $pedagang_user_id );
            
            // Panggil fungsi add bonus yang sudah support table referral_reward
            $this->logic->add_transaction_quota( $pedagang_user_id, $code, $user_id );
        }
    }
}