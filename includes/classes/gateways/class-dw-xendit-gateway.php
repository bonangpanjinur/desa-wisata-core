<?php
/**
 * File: includes/classes/gateways/class-dw-xendit-gateway.php
 * * Class ini menangani komunikasi HTTP ke API Xendit.
 * Menggunakan wp_remote_post() bawaan WordPress (tanpa library eksternal yang berat).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Xendit_Gateway {

    private $api_url = 'https://api.xendit.co/v2/invoices';
    private $secret_key;
    private $verification_token;

    public function __construct() {
        $options = get_option( 'dw_settings_general' );
        $this->secret_key = isset( $options['xendit_secret_key'] ) ? $options['xendit_secret_key'] : '';
        $this->verification_token = isset( $options['xendit_callback_token'] ) ? $options['xendit_callback_token'] : '';
    }

    /**
     * Membuat Invoice Xendit
     * * @param array $params Data invoice (external_id, amount, email, description)
     * @return array|WP_Error Response dari Xendit
     */
    public function create_invoice( $params ) {
        if ( empty( $this->secret_key ) ) {
            return new WP_Error( 'xendit_config_error', 'Xendit Secret Key belum diatur di pengaturan.' );
        }

        $body = [
            'external_id'      => $params['external_id'],
            'amount'           => (int) $params['amount'],
            'payer_email'      => sanitize_email( $params['payer_email'] ),
            'description'      => sanitize_text_field( $params['description'] ),
            'currency'         => 'IDR',
            'should_send_email'=> true,
            'success_redirect_url' => get_site_url() . '/dashboard-toko?payment=success',
            'failure_redirect_url' => get_site_url() . '/dashboard-toko?payment=failed'
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $this->secret_key . ':' ),
                'Content-Type'  => 'application/json'
            ],
            'body'    => json_encode( $body ),
            'timeout' => 45
        ];

        $response = wp_remote_post( $this->api_url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body_response = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $response_code !== 200 ) {
            return new WP_Error( 'xendit_api_error', isset($body_response['message']) ? $body_response['message'] : 'Unknown error from Xendit' );
        }

        return $body_response;
    }

    /**
     * Memverifikasi Webhook Masuk
     * * @param WP_REST_Request $request
     * @return bool Valid atau tidak
     */
    public function verify_webhook( $request ) {
        $headers = $request->get_headers();
        
        // Verifikasi token callback jika diatur di pengaturan
        if ( ! empty( $this->verification_token ) ) {
            $x_callback_token = isset( $headers['x_callback_token'][0] ) ? $headers['x_callback_token'][0] : '';
            if ( $x_callback_token !== $this->verification_token ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get Invoice Status (Opsional, untuk pengecekan manual)
     */
    public function get_invoice( $invoice_id ) {
        if ( empty( $this->secret_key ) ) {
            return new WP_Error( 'xendit_config_error', 'Xendit Secret Key belum diatur.' );
        }

        $args = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $this->secret_key . ':' ),
            ]
        ];

        $response = wp_remote_get( $this->api_url . '/' . $invoice_id, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}