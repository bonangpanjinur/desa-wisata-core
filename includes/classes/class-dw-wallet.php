<?php
/**
 * Wallet Logic Handler
 * Path: includes/classes/class-dw-wallet.php
 * Description: Menangani logika saldo (Topup, Penarikan, Mutasi) untuk Desa & Verifikator.
 * Version: 1.1.0
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Wallet {

    /**
     * Ambil Saldo User saat ini
     * * @param int $user_id ID User WordPress
     * @return float Jumlah saldo saat ini
     */
    public static function get_balance( $user_id ) {
        global $wpdb;
        $table_wallet = $wpdb->prefix . 'dw_wallet';
        
        // Cek apakah tabel ada untuk menghindari error saat belum aktivasi
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_wallet'" ) != $table_wallet ) {
            return 0.00;
        }
        
        $balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM $table_wallet WHERE user_id = %d", $user_id ) );
        
        return $balance ? (float) $balance : 0.00;
    }

    /**
     * Tambah Saldo (Credit)
     * Digunakan saat komisi masuk.
     * * @param int $user_id ID User Penerima
     * @param float $amount Jumlah uang
     * @param string $description Keterangan mutasi
     * @param int $ref_id ID Referensi (misal ID Transaksi/Paket)
     */
    public static function add_balance( $user_id, $amount, $description = '', $ref_id = 0 ) {
        global $wpdb;
        $table_wallet = $wpdb->prefix . 'dw_wallet';
        $table_logs   = $wpdb->prefix . 'dw_wallet_logs';

        // 1. Cek apakah user sudah punya wallet, jika belum buatkan
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_wallet WHERE user_id = %d", $user_id ) );
        if ( ! $exists ) {
            $wpdb->insert( $table_wallet, [ 'user_id' => $user_id, 'balance' => 0 ] );
            $wallet_id = $wpdb->insert_id;
        } else {
            $wallet_id = $exists;
        }

        // 2. Update Saldo (Tambah)
        $wpdb->query( $wpdb->prepare( "UPDATE $table_wallet SET balance = balance + %f WHERE user_id = %d", $amount, $user_id ) );

        // 3. Catat Log Mutasi
        $current_balance = self::get_balance( $user_id );
        $wpdb->insert( $table_logs, [
            'wallet_id'      => $wallet_id,
            'transaction_id' => $ref_id, // ID referensi (misal ID transaksi asal komisi)
            'type'           => 'credit', // Uang Masuk
            'amount'         => $amount,
            'description'    => $description,
            'balance_after'  => $current_balance,
            'created_at'     => current_time( 'mysql' )
        ] );

        return true;
    }

    /**
     * Kurangi Saldo (Debit)
     * Digunakan saat penarikan dana.
     * * @param int $user_id ID User Penarik
     * @param float $amount Jumlah uang
     * @param string $description Keterangan
     * @param int $ref_id ID Referensi (misal ID Withdrawal)
     */
    public static function deduct_balance( $user_id, $amount, $description = '', $ref_id = 0 ) {
        global $wpdb;
        $table_wallet = $wpdb->prefix . 'dw_wallet';
        $table_logs   = $wpdb->prefix . 'dw_wallet_logs';

        $current_balance = self::get_balance( $user_id );

        // Validasi Saldo
        if ( $current_balance < $amount ) {
            return new WP_Error( 'insufficient_funds', 'Saldo tidak mencukupi. Saldo saat ini: Rp ' . number_format( $current_balance, 0, ',', '.' ) );
        }

        // 1. Ambil ID Wallet
        $wallet_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_wallet WHERE user_id = %d", $user_id ) );

        // 2. Potong Saldo
        $wpdb->query( $wpdb->prepare( "UPDATE $table_wallet SET balance = balance - %f WHERE user_id = %d", $amount, $user_id ) );

        // 3. Catat Log
        $new_balance = $current_balance - $amount;
        $wpdb->insert( $table_logs, [
            'wallet_id'     => $wallet_id,
            'withdrawal_id' => $ref_id, // ID Penarikan
            'type'          => 'debit', // Uang Keluar
            'amount'        => $amount,
            'description'   => $description,
            'balance_after' => $new_balance,
            'created_at'    => current_time( 'mysql' )
        ] );

        return true;
    }

    /**
     * Proses Request Penarikan (Withdrawal) ke Xendit
     * * @param int $user_id ID User
     * @param float $amount Jumlah Penarikan
     * @param array $bank_details Array berisi 'bank_code', 'account_number', 'account_name'
     */
    public static function request_withdrawal( $user_id, $amount, $bank_details ) {
        global $wpdb;
        
        // 1. Cek Minimal Penarikan (Default 10rb)
        $min_withdrawal = get_option( 'dw_min_withdrawal_amount', 10000 ); 
        if ( $amount < $min_withdrawal ) {
            return new WP_Error( 'min_limit', 'Minimal penarikan adalah Rp ' . number_format( $min_withdrawal, 0, ',', '.' ) );
        }

        // 2. Cek Saldo Cukup
        $current_balance = self::get_balance( $user_id );
        if ( $current_balance < $amount ) {
            return new WP_Error( 'insufficient_funds', 'Saldo Anda (Rp ' . number_format( $current_balance, 0, ',', '.' ) . ') tidak cukup.' );
        }

        // 3. Simpan ke Tabel Withdrawals (Status Awal: Processing)
        $table_wd = $wpdb->prefix . 'dw_withdrawals';
        $wpdb->insert( $table_wd, [
            'user_id'        => $user_id,
            'amount'         => $amount,
            'bank_code'      => isset($bank_details['bank_code']) ? $bank_details['bank_code'] : '',
            'account_number' => isset($bank_details['account_number']) ? $bank_details['account_number'] : '',
            'account_name'   => isset($bank_details['account_name']) ? $bank_details['account_name'] : '',
            'status'         => 'processing', // Langsung processing karena auto-transfer trigger Xendit
            'created_at'     => current_time( 'mysql' )
        ] );
        $wd_id = $wpdb->insert_id;

        if ( ! $wd_id ) {
             return new WP_Error( 'db_error', 'Gagal membuat data penarikan di database.' );
        }

        // 4. Potong Saldo User (Lock dana agar tidak ditarik ganda)
        $deduct = self::deduct_balance( $user_id, $amount, 'Penarikan Dana ID: #' . $wd_id, $wd_id );
        if ( is_wp_error( $deduct ) ) {
             // Rollback jika gagal potong (jarang terjadi karena sudah dicek di awal)
             $wpdb->delete( $table_wd, [ 'id' => $wd_id ] );
             return $deduct;
        }

        // 5. Trigger Xendit Disbursement (Auto Transfer)
        // Pastikan class Gateway sudah diload
        if ( ! class_exists( 'DW_Xendit_Gateway' ) ) {
            require_once( plugin_dir_path( dirname( __FILE__ ) ) . 'classes/gateways/class-dw-xendit-gateway.php' );
        }

        $gateway = new DW_Xendit_Gateway();
        $result = $gateway->create_disbursement( 
            $wd_id, 
            $amount, 
            $bank_details['bank_code'], 
            $bank_details['account_number'], 
            $bank_details['account_name'],
            'Penarikan Saldo Desa Wisata #' . $wd_id
        );

        if ( is_wp_error( $result ) ) {
            // Jika API Xendit Error/Down, kembalikan saldo (Refund)
            self::add_balance( $user_id, $amount, 'Refund: Gagal koneksi Xendit (ID #' . $wd_id . ')', $wd_id );
            
            // Update status jadi failed
            $wpdb->update( $table_wd, [ 
                'status' => 'failed', 
                'failure_reason' => $result->get_error_message() 
            ], [ 'id' => $wd_id ] );
            
            return $result;
        }

        return $wd_id; // Return ID Penarikan jika sukses request
    }
}