<?php
/**
 * DW Wallet Class
 * Path: includes/classes/class-dw-wallet.php
 * Description: Menangani logika Dompet Digital User (Topup, Withdrawal, Mutasi).
 * * UPDATE FASE 5 (Security): 
 * - Implementasi Atomic Transactions ($wpdb->query('START TRANSACTION')).
 * - Implementasi Row Locking (SELECT ... FOR UPDATE) untuk mencegah Race Condition.
 * - Validasi Saldo Anti-Minus.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DW_Wallet {

    /**
     * Mengambil Saldo User
     * * @param int $user_id ID User
     * @param bool $lock Set true untuk mengunci row database (HANYA gunakan dalam transaction)
     * @return float Jumlah saldo saat ini
     */
    public function get_balance( $user_id, $lock = false ) {
        global $wpdb;
        $table_wallet = $wpdb->prefix . 'dw_wallet';

        // Pastikan tabel wallet ada
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_wallet'" ) != $table_wallet ) {
            return 0.00;
        }

        if ( $lock ) {
            // LOCKING QUERY: Membaca nilai saldo dan mengunci barisnya agar proses lain harus menunggu
            // Kita kunci row di tabel dw_wallet.
            $current = $wpdb->get_var( $wpdb->prepare(
                "SELECT balance FROM {$table_wallet} 
                 WHERE user_id = %d 
                 LIMIT 1 FOR UPDATE",
                $user_id
            ) );

            return $current !== null ? (float) $current : 0.00;
        }
        
        // Standard non-locking read
        $balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM $table_wallet WHERE user_id = %d", $user_id ) );
        return $balance ? (float) $balance : 0.00;
    }

    /**
     * Menambah Saldo (Credit) - Thread Safe
     * * @param int $user_id
     * @param float $amount
     * @param string $description
     * @param string $ref_id (Opsional, misal ID Order atau ID Topup)
     * @return bool True jika sukses
     */
    public function add_balance( $user_id, $amount, $description = '', $ref_id = '' ) {
        global $wpdb;
        $amount = (float) $amount;
        $table_wallet = $wpdb->prefix . 'dw_wallet';
        $table_logs   = $wpdb->prefix . 'dw_wallet_logs';

        if ( $amount <= 0 ) {
            return false;
        }

        // 1. MULAI TRANSAKSI
        $wpdb->query( 'START TRANSACTION' );

        try {
            // Cek apakah user sudah punya wallet, jika belum buatkan
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_wallet WHERE user_id = %d", $user_id ) );
            if ( ! $exists ) {
                $wpdb->insert( $table_wallet, [ 'user_id' => $user_id, 'balance' => 0 ] );
                $wallet_id = $wpdb->insert_id;
            } else {
                $wallet_id = $exists;
            }

            // 2. AMBIL SALDO (DENGAN LOCK)
            // Proses lain yang mencoba akses saldo user ini akan antre di sini
            $current_balance = $this->get_balance( $user_id, true );
            
            // 3. HITUNG SALDO BARU
            $new_balance = $current_balance + $amount;

            // 4. UPDATE DATABASE
            $updated = $wpdb->update( $table_wallet, [ 'balance' => $new_balance ], [ 'user_id' => $user_id ] );

            if ( $updated === false ) {
                throw new Exception( 'Gagal update tabel wallet.' );
            }

            // 5. CATAT LOG MUTASI (AUDIT TRAIL)
            $this->log_transaction( $wallet_id, 'credit', $amount, $new_balance, $description, $ref_id );

            // 6. SELESAI
            $wpdb->query( 'COMMIT' );
            return true;

        } catch ( Exception $e ) {
            // Jika ada error, batalkan semua perubahan DB di atas
            $wpdb->query( 'ROLLBACK' );
            error_log( "Wallet Add Error [User $user_id]: " . $e->getMessage() );
            return false;
        }
    }

    /**
     * Mengurangi Saldo (Debit) - Thread Safe & Anti-Minus
     * * @param int $user_id
     * @param float $amount
     * @param string $description
     * @param string $ref_id
     * @return bool|WP_Error True jika sukses, WP_Error jika gagal
     */
    public function deduct_balance( $user_id, $amount, $description = '', $ref_id = '' ) {
        global $wpdb;
        $amount = (float) $amount;
        $table_wallet = $wpdb->prefix . 'dw_wallet';

        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_amount', 'Nominal harus lebih dari 0.' );
        }

        // 1. MULAI TRANSAKSI
        $wpdb->query( 'START TRANSACTION' );

        try {
            // Cek wallet user
            $wallet_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_wallet WHERE user_id = %d", $user_id ) );
            if ( ! $wallet_id ) {
                 throw new Exception( 'Dompet user tidak ditemukan.' );
            }

            // 2. AMBIL SALDO (DENGAN LOCK)
            $current_balance = $this->get_balance( $user_id, true );

            // 3. VALIDASI SALDO (CRITICAL)
            if ( $current_balance < $amount ) {
                throw new Exception( 'Saldo tidak mencukupi (Insufficient Funds). Saldo saat ini: ' . number_format($current_balance, 0, ',', '.') );
            }

            // 4. HITUNG SALDO BARU
            $new_balance = $current_balance - $amount;

            // 5. UPDATE DATABASE
            $updated = $wpdb->update( $table_wallet, [ 'balance' => $new_balance ], [ 'user_id' => $user_id ] );

            if ( $updated === false ) {
                throw new Exception( 'Gagal update tabel wallet.' );
            }

            // 6. CATAT LOG MUTASI
            $this->log_transaction( $wallet_id, 'debit', $amount, $new_balance, $description, $ref_id );

            // 7. SELESAI
            $wpdb->query( 'COMMIT' );
            return true;

        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'wallet_error', $e->getMessage() );
        }
    }

    /**
     * Proses Request Penarikan (Withdrawal) ke Xendit
     * * @param int $user_id ID User
     * @param float $amount Jumlah Penarikan
     * @param array $bank_details Array berisi 'bank_code', 'account_number', 'account_name'
     */
    public function request_withdrawal( $user_id, $amount, $bank_details ) {
        global $wpdb;
        
        // 1. Cek Minimal Penarikan (Default 10rb)
        $min_withdrawal = get_option( 'dw_min_withdrawal_amount', 10000 ); 
        if ( $amount < $min_withdrawal ) {
            return new WP_Error( 'min_limit', 'Minimal penarikan adalah Rp ' . number_format( $min_withdrawal, 0, ',', '.' ) );
        }

        // 2. Cek Saldo Cukup (Non-locking check first for UX)
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
        // Gunakan object instance method deduct_balance
        $deduct = $this->deduct_balance( $user_id, $amount, 'Penarikan Dana ID: #' . $wd_id, $wd_id );
        
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
            $this->add_balance( $user_id, $amount, 'Refund: Gagal koneksi Xendit (ID #' . $wd_id . ')', $wd_id );
            
            // Update status jadi failed
            $wpdb->update( $table_wd, [ 
                'status' => 'failed', 
                'failure_reason' => $result->get_error_message() 
            ], [ 'id' => $wd_id ] );
            
            return $result;
        }

        return $wd_id; // Return ID Penarikan jika sukses request
    }

    /**
     * Internal Logger untuk Mencatat Riwayat Mutasi ke Tabel Log Khusus
     */
    private function log_transaction( $wallet_id, $type, $amount, $balance_after, $desc, $ref_id ) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'dw_wallet_logs';
        
        // Coba insert ke tabel logs khusus wallet
        $wpdb->insert( $table_logs, [
            'wallet_id'      => $wallet_id,
            'transaction_id' => is_numeric($ref_id) ? $ref_id : 0, // ID referensi
            'type'           => $type, // 'credit' atau 'debit'
            'amount'         => $amount,
            'description'    => $desc,
            'balance_after'  => $balance_after,
            'created_at'     => current_time( 'mysql' )
        ] );

        // Fallback logging ke system log (opsional)
        if ( function_exists( 'dw_log_activity' ) ) {
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}dw_wallet WHERE id = %d", $wallet_id));
            $action_code = ($type === 'credit') ? 'WALLET_CREDIT' : 'WALLET_DEBIT';
            dw_log_activity( $action_code, "[$type] Rp " . number_format($amount) . " - $desc", $user_id );
        }
    }
}