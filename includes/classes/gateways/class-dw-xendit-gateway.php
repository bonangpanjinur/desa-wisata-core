<?php
/**
 * Xendit Gateway Handler
 * Path: includes/classes/gateways/class-dw-xendit-gateway.php
 * Description: Menangani komunikasi API dengan Xendit (Invoice & Disbursement).
 * * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DW_Xendit_Gateway {

    private $api_key;
    private $callback_token;
    private $api_url = 'https://api.xendit.co';

    public function __construct() {
        $settings = get_option( 'dw_payment_settings', [] ); // Ambil dari settingan baru
        $this->api_key = isset( $settings['xendit_secret_key'] ) ? $settings['xendit_secret_key'] : '';
        $this->callback_token = isset( $settings['xendit_callback_token'] ) ? $settings['xendit_callback_token'] : '';
    }

    /**
     * 1. Buat Invoice (Untuk Pembelian Kuota)
     */
    public function create_invoice( $transaction_id, $amount, $payer_email, $desc ) {
        if ( empty( $this->api_key ) ) return new WP_Error( 'config_error', 'API Key kosong.' );

        $endpoint = $this->api_url . '/v2/invoices';
        $external_id = 'TRX-' . $transaction_id . '-' . time(); // Unik ID

        $body = [
            'external_id' => $external_id,
            'amount'      => $amount,
            'payer_email' => $payer_email,
            'description' => $desc,
            'success_redirect_url' => home_url('/transaksi-berhasil'), // Ganti halaman sukses
            'failure_redirect_url' => home_url('/transaksi-gagal'),
        ];

        return $this->send_request( 'POST', $endpoint, $body );
    }

    /**
     * 2. Buat Disbursement (Transfer Otomatis ke User)
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

        // Jika sukses request ke Xendit, update ID Xendit di database
        if ( ! is_wp_error( $response ) && isset( $response['id'] ) ) {
            global $wpdb;
            $wpdb->update( 
                $wpdb->prefix . 'dw_withdrawals', 
                [ 'xendit_disbursement_id' => $response['id'] ], 
                [ 'id' => $wd_id ] 
            );
        }

        return $response;
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
            'body'      => json_encode( $body ),
            'timeout'   => 45
        ];

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            return $body;
        } else {
            return new WP_Error( 'xendit_error', isset($body['message']) ? $body['message'] : 'Error Xendit' );
        }
    }

    public function verify_token( $token ) {
        return $token === $this->callback_token;
    }
}