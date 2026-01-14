<?php
/**
 * File: includes/rest-api/api-payment.php
 * * Menangani Callback/Webhook dari Xendit.
 * Endpoint: /wp-json/dw-api/v1/payment/callback
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Payment_API {

    public function register_routes() {
        register_rest_route( 'dw-api/v1', '/payment/callback', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_xendit_callback' ],
            'permission_callback' => '__return_true', // Terbuka karena dipanggil oleh server Xendit
        ]);
    }

    /**
     * Handler Callback Webhook
     */
    public function handle_xendit_callback( $request ) {
        global $wpdb;

        // 1. Inisialisasi Gateway untuk verifikasi
        require_once DW_PLUGIN_DIR . 'includes/classes/gateways/class-dw-xendit-gateway.php';
        $gateway = new DW_Xendit_Gateway();

        // 2. Verifikasi Token (Security)
        if ( ! $gateway->verify_webhook( $request ) ) {
            return new WP_Error( 'forbidden', 'Invalid Callback Token', [ 'status' => 403 ] );
        }

        // 3. Ambil Data Body
        $params = $request->get_json_params();

        // Pastikan ini notifikasi invoice paid
        if ( ! isset( $params['status'] ) || $params['status'] !== 'PAID' ) {
            return new WP_REST_Response( [ 'message' => 'Ignored, not PAID status' ], 200 );
        }

        $external_id = sanitize_text_field( $params['external_id'] ); // Format: TRX-USERID-TIMESTAMP
        $payment_method = isset( $params['payment_method'] ) ? sanitize_text_field( $params['payment_method'] ) : 'XENDIT';
        $paid_amount = isset( $params['paid_amount'] ) ? (int) $params['paid_amount'] : 0;

        // 4. Cari Transaksi di Database
        // Kita asumsikan external_id disimpan di kolom khusus atau kita parse ID nya
        // Format external ID saat create invoice: DW-TOPUP-{ID_TRANSAKSI}
        
        $parts = explode( '-', $external_id );
        if ( count( $parts ) < 3 || $parts[1] !== 'TOPUP' ) {
             // Handle jika format external ID bukan untuk topup paket
             return new WP_REST_Response( [ 'message' => 'Ignored, invalid external ID format' ], 200 );
        }

        $transaksi_id = (int) $parts[2];

        // Cek data transaksi
        $transaksi = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM {$wpdb->prefix}dw_transaksi_paket WHERE id = %d AND status = 'pending'", 
            $transaksi_id 
        ) );

        if ( ! $transaksi ) {
            return new WP_REST_Response( [ 'message' => 'Transaction not found or already paid' ], 200 );
        }

        // 5. Update Status Transaksi
        $updated = $wpdb->update(
            "{$wpdb->prefix}dw_transaksi_paket",
            [
                'status' => 'success',
                'metode_pembayaran' => $payment_method,
                'xendit_external_id' => $external_id, // Kolom ini harus sudah ada dari Fase 3
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $transaksi_id ]
        );

        if ( $updated === false ) {
            return new WP_Error( 'db_error', 'Gagal update status transaksi', [ 'status' => 500 ] );
        }

        // 6. Tambahkan Kuota/Paket ke User
        // Panggil fungsi helper yang logic-nya terpusat
        $this->process_paket_activation( $transaksi->user_id, $transaksi->paket_id );

        // 7. Catat Log
        dw_add_log( $transaksi->user_id, 'payment_success', "Pembayaran via Xendit berhasil. TRX ID: $transaksi_id. Amount: $paid_amount" );

        return new WP_REST_Response( [ 'message' => 'Payment processed successfully' ], 200 );
    }

    /**
     * Logika Aktivasi Paket (Private helper)
     */
    private function process_paket_activation( $user_id, $paket_id ) {
        global $wpdb;

        // Ambil detail paket
        $paket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dw_paket_transaksi WHERE id = %d", $paket_id ) );

        if ( ! $paket ) return;

        // Ambil kuota user saat ini
        $current_quota = get_user_meta( $user_id, 'dw_kuota_transaksi', true );
        $current_quota = $current_quota ? (int) $current_quota : 0;

        // Tambahkan kuota
        $new_quota = $current_quota + (int) $paket->jumlah_transaksi;
        update_user_meta( $user_id, 'dw_kuota_transaksi', $new_quota );

        // Update masa aktif (opsional, logika sederhana: tambah 30 hari dari sekarang)
        $expiry = date( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
        update_user_meta( $user_id, 'dw_paket_expiry', $expiry );
    }
}