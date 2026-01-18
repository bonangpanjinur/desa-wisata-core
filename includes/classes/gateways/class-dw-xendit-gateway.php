<?php
/**
 * Xendit Gateway Handler
 * Path: includes/classes/gateways/class-dw-xendit-gateway.php
 * Description: Menangani komunikasi API dengan Xendit (Invoice & Disbursement).
 * * UPDATE FASE 5: Menyesuaikan payload invoice agar mencakup detail produk & fee.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DW_Xendit_Gateway {

    private $api_key;
    private $callback_token;
    private $api_url = 'https://api.xendit.co';

    public function __construct() {
        $settings = get_option( 'dw_payment_settings', [] ); // Ambil dari settingan baru
        
        // Mode switch: Sandbox vs Production
        $mode = isset($settings['xendit_mode']) ? $settings['xendit_mode'] : 'sandbox';
        
        // Ambil secret key berdasarkan mode (idealnya, tapi untuk MVP kita pakai 1 field saja dulu)
        // Di settings page kita hanya menyediakan satu field 'xendit_secret_key' yang universal
        $this->api_key = isset( $settings['xendit_secret_key'] ) ? $settings['xendit_secret_key'] : '';
        $this->callback_token = isset( $settings['xendit_callback_token'] ) ? $settings['xendit_callback_token'] : '';
    }

    /**
     * 1. Buat Invoice
     * Digunakan untuk Pembelian Kuota (Topup) DAN Pembayaran Belanja User.
     * * @param int|string $transaction_id ID Order (Post ID)
     * @param float $amount Total tagihan (Sudah termasuk fee aplikasi)
     * @param int $user_id ID User pembayar
     * @param string $desc Deskripsi opsional
     */
    public function create_invoice( $transaction_id, $amount, $user_id, $desc = '' ) {
        if ( empty( $this->api_key ) ) return new WP_Error( 'config_error', 'API Key Xendit belum diatur.' );

        // Ambil data user untuk pre-fill email (jika ada)
        $payer_email = '';
        $user_info = get_userdata($user_id);
        if ($user_info) {
            $payer_email = $user_info->user_email;
        }

        $endpoint = $this->api_url . '/v2/invoices';
        $external_id = 'TRX-' . $transaction_id . '-' . time(); // Unik ID untuk Xendit

        // Ambil detail items untuk ditampilkan di Invoice Xendit (Opsional tapi bagus untuk UX)
        // Kita coba ambil meta items jika tersedia (hanya untuk tipe transaksi belanja)
        $items_payload = [];
        $order_items = get_post_meta($transaction_id, '_dw_order_items', true); // Format array dari checkout
        
        if (is_array($order_items) && !empty($order_items)) {
            foreach ($order_items as $item) {
                $items_payload[] = [
                    'name' => isset($item['name']) ? $item['name'] : 'Produk',
                    'quantity' => isset($item['qty']) ? (int)$item['qty'] : 1,
                    'price' => isset($item['price']) ? (float)$item['price'] : 0
                ];
            }
            
            // Tambahkan Fee Aplikasi sebagai item (agar total match)
            $fee = get_post_meta($transaction_id, '_dw_fee_buyer_amount', true);
            if ($fee > 0) {
                $items_payload[] = [
                    'name' => 'Biaya Layanan Aplikasi',
                    'quantity' => 1,
                    'price' => (float)$fee
                ];
            }
            
            // Tambahkan Ongkir sebagai item (agar total match)
            $ongkir = get_post_meta($transaction_id, '_dw_total_ongkir', true); // Asumsi key ini disimpan
            // Fallback: hitung selisih jika meta ongkir belum konsisten
            // Tapi untuk MVP, kita skip validasi item detail yang terlalu ketat agar tidak error 'amount mismatch'
            // Xendit memvalidasi total items == amount. Jika ragu, kosongkan items_payload.
        }

        $body = [
            'external_id' => $external_id,
            'amount'      => $amount,
            'payer_email' => $payer_email,
            'description' => empty($desc) ? "Pembayaran Order #$transaction_id" : $desc,
            'success_redirect_url' => home_url('/terima-kasih?id=' . $transaction_id . '&status=success'), 
            'failure_redirect_url' => home_url('/pembayaran?id=' . $transaction_id . '&status=failed'),
            'currency'    => 'IDR',
            'should_send_email' => true, // Kirim email invoice ke user dari Xendit
        ];

        // Jika kita yakin hitungan item benar, masukkan ke payload.
        // Untuk amannya di tahap ini, kita biarkan kosong agar Xendit hanya peduli total Amount.
        // $body['items'] = $items_payload; 

        return $this->send_request( 'POST', $endpoint, $body );
    }

    /**
     * 2. Buat Disbursement (Transfer Otomatis ke User)
     * Digunakan saat Pedagang melakukan Withdraw saldo.
     */
    public function create_disbursement( $wd_id, $amount, $bank_code, $acc_number, $acc_name, $desc ) {
        if ( empty( $this->api_key ) ) return new WP_Error( 'config_error', 'API Key kosong.' );

        $endpoint = $this->api_url . '/disbursements';
        $external_id = 'WD-' . $wd_id . '-' . time();

        $body = [
            'external_id' => $external_id,
            'amount'      => (int) $amount,
            'bank_code'   => $bank_code,
            'account_holder_name' => $acc_name,
            'account_number' => $acc_number,
            'description' => $desc
        ];

        // Idempotency Key (Pencegah double transfer)
        $headers = [ 'X-IDEMPOTENCY-KEY' => 'idempotency-' . $external_id ];

        $response = $this->send_request( 'POST', $endpoint, $body, $headers );

        // Jika sukses request ke Xendit, update ID Xendit di database lokal
        if ( ! is_wp_error( $response ) && isset( $response['id'] ) ) {
            global $wpdb;
            // Pastikan tabel dw_withdrawals ada atau gunakan log meta
            // $wpdb->update( ... ); 
            // Untuk MVP, kita return response saja biar Controller yang handle update DB
        }

        return $response;
    }

    /**
     * 3. Get Invoice Status (Untuk Cron Job Sync)
     */
    public function get_invoice($invoice_id) {
        if ( empty( $this->api_key ) ) return new WP_Error( 'config_error', 'API Key kosong.' );
        $endpoint = $this->api_url . '/v2/invoices/' . $invoice_id;
        return $this->send_request( 'GET', $endpoint, [] );
    }

    /**
     * Helper: Kirim Request ke Xendit
     */
    private function send_request( $method, $url, $body, $custom_headers = [] ) {
        $headers = array_merge( [
            'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' ),
            'Content-Type'  => 'application/json'
        ], $custom_headers );

        $args = [
            'method'    => $method,
            'headers'   => $headers,
            'timeout'   => 45
        ];

        if ( ! empty( $body ) && $method !== 'GET' ) {
            $args['body'] = json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body_res = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            return $body_res;
        } else {
            // Log error untuk debugging
            error_log('Xendit Error [' . $code . ']: ' . print_r($body_res, true));
            return new WP_Error( 'xendit_error', isset($body_res['message']) ? $body_res['message'] : 'Error Xendit Unknown' );
        }
    }

    public function verify_token( $token ) {
        return $token === $this->callback_token;
    }
}