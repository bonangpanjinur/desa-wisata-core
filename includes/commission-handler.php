<?php
/**
 * File: includes/commission-handler.php
 * Deskripsi: Handler Utama Distribusi Dana (Marketplace Split Bill).
 * UPDATE FASE 5: Logika "Marketplace" dimana uang masuk ke Admin dulu, baru didistribusikan.
 * * Flow:
 * 1. Webhook Xendit masuk (PAID) -> Update Status Order.
 * 2. Fungsi dw_distribute_order_commissions() dipanggil.
 * 3. Hitung Hak Pedagang = (Harga Barang - Komisi Admin) + Ongkir.
 * 4. Hitung Pendapatan Admin = Fee Pembeli + Komisi Admin.
 * 5. Transfer saldo ke Wallet masing-masing.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fungsi Utama: Distribusi Dana per Order
 * Dipanggil saat pesanan berubah status menjadi 'pembayaran_dikonfirmasi' (PAID).
 *
 * @param int $order_id ID Transaksi (dw_transaksi)
 * @return void
 */
function dw_distribute_order_commissions( $order_id ) {
    global $wpdb;

    // 1. SAFETY CHECK: Idempotency
    // Pastikan komisi untuk order ini belum pernah didistribusikan sebelumnya.
    if ( get_post_meta( $order_id, '_dw_commission_distributed', true ) ) {
        return; 
    }

    // 2. Ambil Data Transaksi Utama
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';
    $order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_transaksi WHERE id = %d", $order_id ) );

    if ( ! $order ) {
        return;
    }

    // Hanya proses jika status valid (Sudah Bayar)
    // Sesuaikan status dengan flow sistem Anda. Biasanya: 'pembayaran_dikonfirmasi' atau 'diproses'
    if ( ! in_array( $order->status_transaksi, ['pembayaran_dikonfirmasi', 'diproses', 'dikirim', 'selesai'] ) ) {
        return;
    }

    // 3. Ambil Snapshot Fee yang disimpan saat Checkout
    // Ini penting agar jika admin mengubah setting fee di kemudian hari, transaksi lama tidak berubah.
    $buyer_fee     = (float) get_post_meta( $order_id, '_dw_fee_buyer_amount', true );
    $merchant_rate = (float) get_post_meta( $order_id, '_dw_fee_merchant_rate', true );

    // Fallback: Jika data meta kosong (transaksi legacy), ambil dari setting saat ini
    if ( $merchant_rate <= 0 && get_post_meta( $order_id, '_dw_fee_merchant_rate', true ) === '' ) {
        $merchant_rate = (float) get_option( 'dw_merchant_fee_value', 5 );
    }

    // 4. Ambil Sub-Transaksi (Per Toko/Pedagang)
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $sub_orders = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_sub WHERE id_transaksi = %d", $order_id ) );

    $total_admin_revenue_from_commission = 0;

    // Inisialisasi Wallet Class
    if ( ! class_exists( 'DW_Wallet' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'classes/class-dw-wallet.php';
    }
    $wallet = new DW_Wallet();

    // 5. LOOPING: Proses Setiap Pedagang
    foreach ( $sub_orders as $sub ) {
        $pedagang_id_user = $sub->id_pedagang; // Asumsi: kolom ini menyimpan ID User WP si pedagang
        
        $sub_total_barang = (float) $sub->sub_total; // Harga Barang Saja
        $ongkir_toko      = (float) $sub->ongkir;    // Ongkos Kirim

        // --- HITUNGAN DUIT ---
        // Komisi Admin = % dari Harga Barang
        $potongan_admin = $sub_total_barang * ( $merchant_rate / 100 );
        
        // Pendapatan Bersih Pedagang dari Barang
        $net_barang = $sub_total_barang - $potongan_admin;

        // Total Transfer ke Pedagang = Net Barang + Ongkir Full
        // (Ongkir diteruskan 100% ke pedagang agar mereka bisa bayar kurir/bensin)
        $total_transfer_pedagang = $net_barang + $ongkir_toko;

        // --- EKSEKUSI TRANSFER WALLET ---
        if ( $total_transfer_pedagang > 0 ) {
            $wallet->add_balance(
                $pedagang_id_user,
                $total_transfer_pedagang,
                sprintf( "Penjualan Order #%s (Toko: %s) - Potongan Admin %s%%", $order->kode_unik, $sub->nama_toko, $merchant_rate )
            );
        }

        // Akumulasi pendapatan admin dari sub-transaksi ini
        $total_admin_revenue_from_commission += $potongan_admin;
    }

    // 6. CATAT PENDAPATAN PLATFORM
    // Total Cuan Admin = Fee Pembeli + Total Komisi dari semua pedagang
    $total_platform_revenue = $buyer_fee + $total_admin_revenue_from_commission;

    if ( $total_platform_revenue > 0 ) {
        // Simpan ke Wallet Super Admin (User ID 1) sebagai pencatatan
        // Atau buat tabel log khusus pendapatan platform
        $wallet->add_balance(
            1, // ID User Super Admin
            $total_platform_revenue,
            sprintf( "Platform Revenue - Order #%s (Fee Pembeli: %s, Komisi: %s)", $order->kode_unik, $buyer_fee, $total_admin_revenue_from_commission )
        );
    }

    // 7. FLAG SELESAI
    // Tandai bahwa order ini sudah diproses komisinya agar tidak double
    update_post_meta( $order_id, '_dw_commission_distributed', current_time( 'mysql' ) );
    
    // Log Activity (Opsional)
    if ( function_exists( 'dw_log_activity' ) ) {
        dw_log_activity( 'COMMISSION_DISTRIBUTED', "Order #{$order_id}: Pedagang & Admin telah menerima dana.", $order->id_pembeli );
    }
}

/**
 * Hook untuk menjalankan fungsi di atas.
 * Anda bisa menempelkan hook ini di tempat Anda mengupdate status order menjadi 'paid'.
 * Contoh: do_action('dw_order_status_paid', $order_id);
 */
add_action( 'dw_order_status_paid', 'dw_distribute_order_commissions' );

/**
 * [LEGACY SUPPORT] 
 * Fungsi lama dijaga agar tidak error jika dipanggil kode lain, 
 * tapi dialihkan ke logika baru atau dikosongkan jika tidak relevan.
 */
function dw_process_transaction_commissions( $transaction_id, $total_admin_fee_dummy = 0 ) {
    // Forward ke fungsi baru
    dw_distribute_order_commissions( $transaction_id );
}