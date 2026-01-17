<?php
/**
 * Payment Webhook Handler
 * Path: includes/rest-api/api-payment.php
 * Description: Menangani notifikasi (Webhook) dari Xendit untuk Pembelian Paket & Penarikan Saldo.
 * Version: 2.1.0 (Profit Sharing & Wallet Integrated)
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_API_Payment {

    /**
     * Registrasi Endpoint API
     * URL: /wp-json/dw-api/v1/webhook
     */
    public function register_routes() {
        register_rest_route( 'dw-api/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_webhook' ],
            'permission_callback' => '__return_true', // Xendit memerlukan akses publik
        ]);
    }

    /**
     * Handle Request dari Xendit
     */
    public function handle_webhook( $request ) {
        // 1. Verifikasi Token Callback Xendit
        $xendit_token = $request->get_header( 'x-callback-token' );
        $gateway = new DW_Xendit_Gateway();

        if ( ! $gateway->verify_token( $xendit_token ) ) {
            return new WP_Error( 'forbidden', 'Invalid Verification Token', [ 'status' => 403 ] );
        }

        // 2. Ambil Data JSON
        $data = $request->get_json_params();

        // Log mentah untuk debugging (Opsional, simpan di tabel dw_payment_logs)
        $this->log_event( 'webhook_received', json_encode( $data ) );

        // 3. Routing Berdasarkan Tipe Event / Status

        // KASUS A: Invoice Lunas (Pembelian Paket / Transaksi)
        if ( isset( $data['status'] ) && $data['status'] === 'PAID' ) {
            $this->process_invoice_paid( $data );
            return new WP_REST_Response( [ 'message' => 'Invoice processed' ], 200 );
        }

        // KASUS B: Transfer Berhasil (Disbursement Completed)
        if ( isset( $data['status'] ) && $data['status'] === 'COMPLETED' && isset( $data['bank_code'] ) ) {
            $this->process_disbursement_update( $data, 'completed' );
            return new WP_REST_Response( [ 'message' => 'Disbursement completed' ], 200 );
        }

        // KASUS C: Transfer Gagal (Disbursement Failed)
        if ( isset( $data['status'] ) && $data['status'] === 'FAILED' && isset( $data['bank_code'] ) ) {
            $this->process_disbursement_update( $data, 'failed' );
            return new WP_REST_Response( [ 'message' => 'Disbursement failed processed' ], 200 );
        }

        return new WP_REST_Response( [ 'message' => 'Event ignored' ], 200 );
    }

    /**
     * Proses Invoice yang Sudah Dibayar
     * Mencari apakah ini pembelian paket kuota atau transaksi e-commerce
     */
    private function process_invoice_paid( $data ) {
        global $wpdb;
        $external_id = $data['external_id']; // Contoh: TRX-123-1782332
        
        // Cek di tabel Pembelian Paket (Prioritas Utama untuk Sistem Profit Sharing)
        $table_paket = $wpdb->prefix . 'dw_pembelian_paket';
        $paket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_paket WHERE xendit_external_id = %s", $external_id ) );

        if ( $paket ) {
            $this->process_topup_success( $paket, $data );
            return;
        }

        // Jika bukan paket, cek di tabel Transaksi E-commerce (Opsional)
        $table_transaksi = $wpdb->prefix . 'dw_transaksi';
        $transaksi = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_transaksi WHERE xendit_external_id = %s", $external_id ) );

        if ( $transaksi ) {
            // Logika untuk transaksi produk biasa (update status jadi 'menunggu_konfirmasi')
            $wpdb->update( 
                $table_transaksi, 
                [ 'status' => 'paid', 'status_transaksi' => 'pembayaran_dikonfirmasi', 'paid_at' => current_time( 'mysql' ) ], 
                [ 'id' => $transaksi->id ] 
            );
        }
    }

    /**
     * LOGIKA INTI: Profit Sharing Pembelian Paket
     */
    private function process_topup_success( $paket, $data ) {
        global $wpdb;
        
        // 1. Ambil Nilai Transaksi & Settingan
        $total_amount = $data['amount'];
        $settings     = get_option( 'dw_payment_settings', [] );
        $fee_type     = isset( $settings['fee_type'] ) ? $settings['fee_type'] : 'percent';
        $fee_value    = isset( $settings['fee_value'] ) ? (float) $settings['fee_value'] : 0;

        // 2. Hitung Pembagian Hasil (Profit Sharing)
        $platform_fee = 0; // Pendapatan Sistem
        
        if ( $fee_type === 'percent' ) {
            $platform_fee = $total_amount * ( $fee_value / 100 );
        } else {
            $platform_fee = $fee_value; // Nominal tetap (Fixed)
        }

        // Safety: Fee tidak boleh lebih besar dari total
        if ( $platform_fee > $total_amount ) $platform_fee = $total_amount;

        $partner_commission = $total_amount - $platform_fee; // Sisa untuk Mitra (Desa/Verifikator)

        // 3. Update Status Pembelian Paket di Database
        $table_paket = $wpdb->prefix . 'dw_pembelian_paket';
        $wpdb->update(
            $table_paket,
            [
                'status'              => 'paid',
                'payment_method'      => isset( $data['payment_method'] ) ? $data['payment_method'] : 'Xendit',
                'payment_channel'     => isset( $data['payment_channel'] ) ? $data['payment_channel'] : '',
                'paid_at'             => current_time( 'mysql' ),
                'platform_fee'        => $platform_fee,
                'partner_commission'  => $partner_commission,
                'komisi_nominal_cair' => $partner_commission // Sama dengan partner commission
            ],
            [ 'id' => $paket->id ]
        );

        // 4. Distribusi Komisi ke Wallet Mitra
        // Cari siapa referrer atau pemilik wilayah pedagang ini
        $mitra_id = $this->get_mitra_penerima_komisi( $paket );

        if ( $mitra_id ) {
            // Load Class Wallet
            if ( ! class_exists( 'DW_Wallet' ) ) {
                require_once( plugin_dir_path( __FILE__ ) . '../classes/class-dw-wallet.php' );
            }

            // Tambah Saldo ke Dompet Mitra
            DW_Wallet::add_balance(
                $mitra_id,
                $partner_commission,
                "Komisi Pembelian Paket #" . $paket->id . " (Pedagang ID: " . $paket->id_pedagang . ")",
                $paket->id
            );
        } else {
            // Jika tidak ada mitra (Pedagang Independent), semua masuk ke Platform (Opsional)
            // Di sini kita biarkan tercatat di platform_fee saja.
        }

        // 5. Tambah Kuota ke Pedagang (PENTING)
        $this->add_quota_to_merchant( $paket->id_pedagang, $paket->jumlah_transaksi );
    }

    /**
     * Handle Update Status Penarikan (Disbursement)
     */
    private function process_disbursement_update( $data, $status ) {
        global $wpdb;
        $external_id = $data['external_id']; // Format: WD-{id}-{time}
        
        // Ambil ID Withdrawal dari string external_id
        $parts = explode( '-', $external_id );
        if ( count( $parts ) < 2 ) return;
        $wd_id = $parts[1];

        $table_wd = $wpdb->prefix . 'dw_withdrawals';

        // Update Status
        $update_data = [ 
            'status' => $status, 
            'updated_at' => current_time( 'mysql' ) 
        ];

        if ( $status === 'failed' ) {
            $update_data['failure_reason'] = isset( $data['failure_code'] ) ? $data['failure_code'] : 'Unknown Error';
        }

        $wpdb->update( $table_wd, $update_data, [ 'id' => $wd_id ] );

        // Jika GAGAL, lakukan REFUND ke dompet user
        if ( $status === 'failed' ) {
            $wd = $wpdb->get_row( "SELECT * FROM $table_wd WHERE id = $wd_id" );
            if ( $wd ) {
                if ( ! class_exists( 'DW_Wallet' ) ) {
                    require_once( plugin_dir_path( __FILE__ ) . '../classes/class-dw-wallet.php' );
                }
                
                // Kembalikan saldo
                DW_Wallet::add_balance( 
                    $wd->user_id, 
                    $wd->amount, 
                    "Refund: Penarikan Gagal #" . $wd->id . " (" . $update_data['failure_reason'] . ")", 
                    $wd->id 
                );
            }
        }
    }

    /**
     * Helper: Menentukan siapa Mitra yang berhak dapat komisi
     * (Desa atau Verifikator)
     */
    private function get_mitra_penerima_komisi( $paket ) {
        // Cek apakah di tabel pembelian sudah tercatat referrer?
        if ( ! empty( $paket->referrer_id ) ) {
            // Perlu mapping ID User WP dari ID Referrer (tergantung struktur relasi Anda)
            // Asumsi: referrer_id di tabel paket merujuk ke ID User WP verifikator/admin desa
            return $paket->referrer_id;
        }

        // Fallback: Cek data pedagang
        global $wpdb;
        $pedagang = $wpdb->get_row( "SELECT id_user, id_desa, id_verifikator FROM {$wpdb->prefix}dw_pedagang WHERE id = {$paket->id_pedagang}" );

        if ( $pedagang ) {
            // Prioritas 1: Verifikator
            if ( ! empty( $pedagang->id_verifikator ) ) {
                // Ambil user_id dari tabel verifikator
                $uid = $wpdb->get_var( "SELECT user_id FROM {$wpdb->prefix}dw_verifikator WHERE id = {$pedagang->id_verifikator}" );
                if ( $uid ) return $uid;
            }

            // Prioritas 2: Desa
            if ( ! empty( $pedagang->id_desa ) ) {
                // Ambil user_id_desa dari tabel desa
                $uid = $wpdb->get_var( "SELECT id_user_desa FROM {$wpdb->prefix}dw_desa WHERE id = {$pedagang->id_desa}" );
                if ( $uid ) return $uid;
            }
        }

        return 0; // Tidak ada mitra
    }

    /**
     * Helper: Tambah Kuota Transaksi ke Pedagang
     */
    private function add_quota_to_merchant( $pedagang_id, $quota ) {
        global $wpdb;
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        
        // Update kuota (sisa_transaksi + kuota baru) dan set status akun aktif
        $wpdb->query( $wpdb->prepare( 
            "UPDATE $table_pedagang 
             SET sisa_transaksi = sisa_transaksi + %d, 
                 status_akun = 'aktif' 
             WHERE id = %d", 
            $quota, 
            $pedagang_id 
        ) );
    }

    /**
     * Helper: Log Debugging
     */
    private function log_event( $type, $payload ) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'dw_payment_logs';
        // Pastikan tabel log ada sebelum insert
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_logs'" ) === $table_logs ) {
            $wpdb->insert( $table_logs, [
                'event_type' => $type,
                'payload'    => $payload,
                'status'     => 'received'
            ]);
        }
    }
}