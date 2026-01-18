<?php
/**
 * File: includes/classes/class-dw-wallet.php
 * * Class DW_Wallet
 * Menangani saldo user, topup, withdraw, dan logging transaksi.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Wallet {

    /**
     * Ambil Saldo User
     */
    public static function get_balance($user_id) {
        $balance = get_user_meta($user_id, '_dw_wallet_balance', true);
        return $balance ? (float) $balance : 0.0;
    }

    /**
     * Tambah Saldo (Credit)
     */
    public static function add_balance($user_id, $amount, $description = '', $type = 'credit', $reference_id = 0) {
        if ($amount <= 0) return false;

        $current_balance = self::get_balance($user_id);
        $new_balance = $current_balance + $amount;

        update_user_meta($user_id, '_dw_wallet_balance', $new_balance);
        self::log_transaction($user_id, $amount, 'in', $type, $description, $new_balance, $reference_id);

        return true;
    }

    /**
     * Kurangi Saldo (Debit)
     */
    public static function deduct_balance($user_id, $amount, $description = '', $type = 'debit', $reference_id = 0) {
        if ($amount <= 0) return false;

        $current_balance = self::get_balance($user_id);

        // Validasi saldo cukup (kecuali tipe tertentu)
        if ($type !== 'commission_deduction' && $current_balance < $amount) {
            return new WP_Error('insufficient_funds', 'Saldo tidak mencukupi.');
        }

        $new_balance = $current_balance - $amount;
        update_user_meta($user_id, '_dw_wallet_balance', $new_balance);

        self::log_transaction($user_id, $amount, 'out', $type, $description, $new_balance, $reference_id);

        return true;
    }

    /**
     * Buat Request Penarikan (Withdraw)
     * Menggunakan Custom Post Type 'dw_withdrawal'
     */
    public static function create_withdrawal_request($user_id, $amount, $bank_details = '') {
        $balance = self::get_balance($user_id);
        
        // 1. Validasi Saldo
        if ($balance < $amount) {
            return new WP_Error('insufficient_funds', 'Saldo tidak mencukupi untuk penarikan.');
        }

        // 2. Kunci Saldo (Deduct sementara)
        // Kita kurangi saldo "real" sekarang agar tidak bisa dipakai belanja. 
        // Jika ditolak admin, kita refund balik.
        $deduct = self::deduct_balance($user_id, $amount, 'Request Penarikan Dana (Pending)', 'withdraw_hold', 0);
        
        if (is_wp_error($deduct)) {
            return $deduct;
        }

        // 3. Buat Record Request (CPT)
        $post_data = array(
            'post_title'    => 'Withdraw - ' . get_userdata($user_id)->display_name . ' - ' . date('Y-m-d H:i'),
            'post_status'   => 'pending', // Pending review admin
            'post_type'     => 'dw_withdrawal',
            'post_author'   => $user_id
        );

        $request_id = wp_insert_post($post_data);

        if ($request_id) {
            update_post_meta($request_id, '_withdraw_amount', $amount);
            update_post_meta($request_id, '_bank_details', $bank_details);
            update_post_meta($request_id, '_withdraw_status', 'pending');
            
            // Update reference ID di log transaksi sebelumnya (opsional, agak tricky karena log sudah tertulis)
            return $request_id;
        } else {
            // Rollback saldo jika gagal insert post
            self::add_balance($user_id, $amount, 'Rollback: Gagal buat request withdraw', 'rollback', 0);
            return new WP_Error('system_error', 'Gagal membuat permintaan penarikan.');
        }
    }

    /**
     * Proses Penarikan (Admin Action)
     * @param int $request_id
     * @param string $action 'approve' or 'reject'
     */
    public static function process_withdrawal($request_id, $action) {
        $post = get_post($request_id);
        if (!$post || $post->post_type !== 'dw_withdrawal') return false;

        $current_status = get_post_meta($request_id, '_withdraw_status', true);
        if ($current_status !== 'pending') return new WP_Error('invalid_status', 'Permintaan ini sudah diproses sebelumnya.');

        $user_id = $post->post_author;
        $amount = get_post_meta($request_id, '_withdraw_amount', true);

        if ($action === 'approve') {
            // Status jadi publish/approved
            wp_update_post(['ID' => $request_id, 'post_status' => 'publish']);
            update_post_meta($request_id, '_withdraw_status', 'approved');
            update_post_meta($request_id, '_processed_date', current_time('mysql'));
            
            // Saldo sudah dipotong saat request, jadi tidak perlu aksi saldo lagi.
            // Bisa kirim notif email ke user.
            return true;

        } elseif ($action === 'reject') {
            // Status jadi trash/draft
            wp_update_post(['ID' => $request_id, 'post_status' => 'draft']);
            update_post_meta($request_id, '_withdraw_status', 'rejected');
            
            // KEMBALIKAN SALDO KE USER
            self::add_balance($user_id, $amount, "Pengembalian: Penarikan Ditolak #$request_id", 'withdraw_refund', $request_id);
            
            return true;
        }

        return false;
    }

    /**
     * Log Transaksi ke Database
     */
    private static function log_transaction($user_id, $amount, $flow, $type, $description, $balance_after, $reference_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_wallet_transactions';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
             // Fallback Meta
             $history = get_user_meta($user_id, '_dw_wallet_history', true);
             if (!is_array($history)) $history = [];
             $history[] = [
                 'date' => current_time('mysql'),
                 'amount' => $amount,
                 'flow' => $flow,
                 'type' => $type,
                 'desc' => $description,
                 'ref' => $reference_id
             ];
             // Keep last 50
             if (count($history) > 50) array_shift($history);
             update_user_meta($user_id, '_dw_wallet_history', $history);
             return;
        }

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'amount' => $amount,
                'flow' => $flow,
                'type' => $type,
                'description' => $description,
                'balance_after' => $balance_after,
                'reference_id' => $reference_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%f', '%s', '%s', '%s', '%f', '%d', '%s')
        );
    }
}