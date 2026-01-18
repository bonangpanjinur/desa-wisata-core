<?php
/**
 * File: includes/classes/class-dw-transaction-handler.php
 * * Class DW_Transaction_Handler
 * Menangani logika status pesanan, validasi pengiriman, pencairan dana, DAN REFUND.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Transaction_Handler {

    /**
     * Inisialisasi Hooks
     */
    public static function init() {
        // Trigger pencairan dana ke pedagang saat order completed
        add_action('woocommerce_order_status_completed', [__CLASS__, 'disburse_funds_to_merchant'], 10, 1);
        
        // Logika Transisi Status (Refund/Cancel)
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_status_transition'], 10, 4);

        // Hook untuk Cron Job Auto-Complete
        add_action('dw_cron_auto_complete_orders', [__CLASS__, 'run_auto_complete_logic']);
    }

    /**
     * 1. Logika Pencairan Dana ke Pedagang (Escrow -> Merchant Wallet)
     * Dipanggil saat status order 'completed'.
     */
    public static function disburse_funds_to_merchant($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Cek apakah sudah pernah dicairkan
        if ($order->get_meta('_dw_funds_disbursed')) return;

        // Identifikasi Pedagang (Author dari produk pertama)
        $items = $order->get_items();
        $merchant_id = 0;
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $author_id = get_post_field('post_author', $product_id);
            if ($author_id) {
                $merchant_id = $author_id;
                break;
            }
        }

        if (!$merchant_id) return;

        // Hitung Total Bersih
        $order_total = $order->get_total();
        $commission_rate = get_option('dw_global_commission_percent', 5); 
        $admin_fee = ($order_total * $commission_rate) / 100;
        $merchant_earning = $order_total - $admin_fee;

        // Masukkan ke Wallet Pedagang
        if (class_exists('DW_Wallet')) {
            DW_Wallet::add_balance(
                $merchant_id, 
                $merchant_earning, 
                "Penjualan Order #$order_id", 
                'sale_earning', 
                $order_id
            );

            $order->update_meta_data('_dw_funds_disbursed', true);
            $order->update_meta_data('_dw_admin_fee', $admin_fee);
            $order->save();
        }
    }

    /**
     * 2. Logika Cron Job Auto-Complete
     */
    public static function run_auto_complete_logic() {
        $orders = wc_get_orders([
            'status' => 'processing',
            'limit'  => 50, 
        ]);

        foreach ($orders as $order) {
            self::check_and_complete_order($order);
        }
    }

    private static function check_and_complete_order($order) {
        $order_id = $order->get_id();
        $shipping_methods = $order->get_shipping_methods();
        $method_id = '';
        foreach( $shipping_methods as $shipping_item ) {
            $method_id = $shipping_item->get_method_id();
            break;
        }

        // Logika Ojek
        if (strpos($method_id, 'ojek') !== false || $order->get_meta('_is_ojek_order')) {
            $delivered_date = $order->get_meta('_ojek_delivered_date');
            if ($delivered_date) {
                $time_diff = current_time('timestamp') - strtotime($delivered_date);
                if ($time_diff > 86400) { // 24 Jam
                    $order->update_status('completed', 'Auto-completed: 24 jam setelah diantar ojek.');
                }
            }
        }
        // Logika Ekspedisi
        elseif ($order->get_meta('_shipping_receipt_number')) {
            $shipped_date = $order->get_meta('_shipping_date');
            if ($shipped_date) {
                $auto_days = get_option('dw_auto_complete_days', 5);
                $time_limit = $auto_days * 86400;
                $time_diff = current_time('timestamp') - strtotime($shipped_date);
                if ($time_diff > $time_limit) {
                    $order->update_status('completed', "Auto-completed: $auto_days hari setelah input resi.");
                }
            }
        }
    }

    /**
     * 3. Handler Transisi Status (REFUND LOGIC)
     */
    public static function handle_status_transition($order_id, $from, $to, $order) {
        // FASE 4: Refund System
        // Jika status berubah menjadi Cancelled atau Refunded
        if (in_array($to, ['cancelled', 'refunded', 'failed'])) {
            
            // Cek apakah pembayaran sudah sukses sebelumnya (Processing/On-Hold -> Cancelled)
            // Jika status sebelumnya 'pending', berarti belum bayar, jadi tidak perlu refund.
            if (in_array($from, ['processing', 'completed', 'on-hold'])) {
                
                // Cek apakah sudah di-refund sebelumnya
                if ($order->get_meta('_dw_order_refunded')) return;

                $buyer_id = $order->get_user_id();
                $refund_amount = $order->get_total();

                // Lakukan Refund ke Wallet Pembeli
                if ($buyer_id && class_exists('DW_Wallet')) {
                    DW_Wallet::add_balance(
                        $buyer_id,
                        $refund_amount,
                        "Refund Order #$order_id (Status: $to)",
                        'refund',
                        $order_id
                    );

                    $order->update_meta_data('_dw_order_refunded', true);
                    $order->add_order_note("Saldo sebesar Rp$refund_amount telah dikembalikan ke Wallet User ID: $buyer_id");
                    $order->save();

                    // NOTE: Jika order 'completed' -> 'refunded', kita harus tarik saldo dari Pedagang juga.
                    // Tapi ini kompleks karena saldo pedagang mungkin sudah ditarik.
                    // Untuk V1, Admin harus manual handle saldo pedagang minus.
                }
            }
        }
    }
}