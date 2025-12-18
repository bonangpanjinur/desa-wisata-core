<?php
// includes/promotions.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Promotions {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dw_promotions';
    }

    /**
     * Add new promotion (Create)
     */
    public function add_promotion( $data ) {
        global $wpdb;

        // Validasi Dasar
        if ( empty( $data['code'] ) || empty( $data['amount'] ) ) {
            return false;
        }

        // Pastikan Code Uppercase
        $data['code'] = strtoupper( trim( $data['code'] ) );

        // Cek duplikasi kode
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $this->table_name WHERE code = %s", $data['code'] ) );
        if ( $existing ) {
            return false; // Kode sudah ada
        }

        // Format Date agar aman untuk SQL
        $data['start_date'] = date( 'Y-m-d H:i:s', strtotime( $data['start_date'] ) );
        $data['end_date']   = date( 'Y-m-d H:i:s', strtotime( $data['end_date'] ) );
        $data['created_at'] = current_time( 'mysql' );

        $format = array( '%s', '%s', '%f', '%s', '%s', '%d', '%f', '%s', '%s', '%s' );

        return $wpdb->insert( $this->table_name, $data, $format );
    }

    /**
     * Update existing promotion
     */
    public function update_promotion( $id, $data ) {
        global $wpdb;

        if ( empty( $id ) ) return false;

        // Sanitasi Code (tidak boleh ubah code jadi duplikat yg lain, tapi boleh code sama jika ID sama)
        if ( isset( $data['code'] ) ) {
            $data['code'] = strtoupper( trim( $data['code'] ) );
        }

        // Validasi Tanggal
        if ( isset( $data['start_date'] ) && isset( $data['end_date'] ) ) {
            if ( strtotime( $data['start_date'] ) > strtotime( $data['end_date'] ) ) {
                // Tanggal mulai lebih besar dari selesai? Tukar atau reject. Di sini kita set sama.
                $data['end_date'] = $data['start_date'];
            }
        }

        $where = array( 'id' => $id );
        
        // Remove created_at from update data if exists
        if( isset($data['created_at']) ) unset($data['created_at']);

        return $wpdb->update( $this->table_name, $data, $where );
    }

    /**
     * Delete Promotion
     */
    public function delete_promotion( $id ) {
        global $wpdb;
        return $wpdb->delete( $this->table_name, array( 'id' => $id ) );
    }

    /**
     * Check valid promotion by code
     * Updated: Now supports Global Usage Limit check
     */
    public function check_validity( $code, $cart_total, $user_id = 0 ) {
        global $wpdb;
        $code = strtoupper( trim( $code ) );

        $promo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE code = %s AND status = 'active'", $code ) );

        if ( ! $promo ) {
            return new WP_Error( 'invalid_code', 'Kode voucher tidak ditemukan atau tidak aktif.' );
        }

        $now = current_time( 'timestamp' );
        
        // 1. Cek Tanggal
        if ( $now < strtotime( $promo->start_date ) || $now > strtotime( $promo->end_date ) ) {
            return new WP_Error( 'expired', 'Masa berlaku voucher sudah habis atau belum mulai.' );
        }

        // 2. Cek Min Pembelian
        if ( $cart_total < $promo->min_purchase ) {
            return new WP_Error( 'min_purchase', 'Total belanja belum memenuhi syarat minimum voucher.' );
        }

        // 3. Cek Limit Penggunaan Global (Kuota Voucher)
        if ( isset( $promo->usage_limit ) && $promo->usage_limit > 0 ) {
            $used_count = $this->get_usage_count( $code );
            
            if ( $used_count >= $promo->usage_limit ) {
                return new WP_Error( 'limit_reached', 'Kuota penggunaan voucher ini sudah habis.' );
            }
        }

        // 4. Cek Limit Per User (Opsional: 1 User 1x pakai)
        // Jika Anda ingin mengaktifkan fitur "1 user 1 voucher", uncomment kode di bawah ini:
        /*
        if ( $user_id > 0 ) {
            $user_usage = $this->get_user_usage_count( $code, $user_id );
            if ( $user_usage > 0 ) {
                return new WP_Error( 'user_limit', 'Anda sudah menggunakan voucher ini sebelumnya.' );
            }
        }
        */

        return $promo;
    }

    /**
     * Helper: Hitung berapa kali voucher sudah dipakai secara global
     */
    private function get_usage_count( $code ) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'dw_orders'; // Asumsi nama tabel order Anda
        
        // Cek apakah tabel order ada untuk mencegah error
        if ( $wpdb->get_var("SHOW TABLES LIKE '$orders_table'") != $orders_table ) {
            return 0; // Tabel tidak ditemukan, anggap belum dipakai
        }

        // Hitung transaksi yang statusnya 'completed' atau 'processing' dengan kode promo ini
        $query = $wpdb->prepare( "
            SELECT COUNT(*) FROM $orders_table 
            WHERE coupon_code = %s 
            AND status IN ('processing', 'completed')
        ", $code );

        return (int) $wpdb->get_var( $query );
    }

    /**
     * Helper: Hitung pemakaian per user
     */
    private function get_user_usage_count( $code, $user_id ) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'dw_orders';

        if ( $wpdb->get_var("SHOW TABLES LIKE '$orders_table'") != $orders_table ) {
            return 0;
        }

        $query = $wpdb->prepare( "
            SELECT COUNT(*) FROM $orders_table 
            WHERE coupon_code = %s 
            AND user_id = %d
            AND status IN ('processing', 'completed')
        ", $code, $user_id );

        return (int) $wpdb->get_var( $query );
    }
}