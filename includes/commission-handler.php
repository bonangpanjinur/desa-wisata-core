<?php
/**
 * File: includes/commission-handler.php
 * Deskripsi: Logika inti pembagian komisi dinamis lintas aktor (Platform, Desa, Verifikator).
 * Update: Integrasi dengan Payout Ledger untuk pencatatan riwayat komisi.
 */

if (!defined('ABSPATH')) exit;

/**
 * Memproses pembagian komisi saat transaksi sukses
 * * @param int $transaction_id ID Transaksi / Order
 * @param float $total_admin_fee Total potongan biaya layanan yang diambil oleh sistem
 */
function dw_process_transaction_commissions($transaction_id, $total_admin_fee) {
    global $wpdb;
    
    // 1. Dapatkan data pedagang dari transaksi
    $pedagang_user_id = get_post_meta($transaction_id, '_pedagang_user_id', true);
    if (!$pedagang_user_id) return;

    // 2. Cari siapa Verifikatornya (pemilik kode)
    $verifier_id = get_user_meta($pedagang_user_id, 'dw_verified_by', true);
    
    // 3. Cari Desa induk (relasi wilayah v3.4)
    $desa_id = get_user_meta($pedagang_user_id, 'dw_parent_desa_id', true);
    
    // Cari User ID dari Desa tersebut untuk pengiriman saldo
    $desa_user_id = 0;
    if ($desa_id) {
        $desa_user_id = $wpdb->get_var($wpdb->prepare("SELECT id_user_desa FROM {$wpdb->prefix}dw_desa WHERE id = %d", $desa_id));
    }

    // 4. Ambil persentase pengaturan dinamis dari Admin
    $settings = dw_get_commission_settings();
    
    // 5. Hitung porsi masing-masing aktor
    $share_platform = ($settings['platform'] / 100) * $total_admin_fee;
    $share_desa     = ($settings['desa'] / 100) * $total_admin_fee;
    $share_verifier = ($settings['verifier'] / 100) * $total_admin_fee;

    // 6. Distribusi Saldo & Pencatatan Ledger (Riwayat)
    $ledger_table = $wpdb->prefix . 'dw_payout_ledger';

    // A. Jatah Platform (Super Admin ID 1)
    dw_add_user_balance(1, $share_platform, "Platform Commission - Trans #$transaction_id");
    
    // B. Jatah Desa (Pemilik Wilayah)
    if ($desa_user_id && $share_desa > 0) {
        dw_add_user_balance($desa_user_id, $share_desa, "Wilayah Fee - Trans #$transaction_id");
        
        // Catat ke Ledger (seperti versi sebelumnya)
        $wpdb->insert($ledger_table, [
            'order_id'        => $transaction_id,
            'payable_to_type' => 'desa',
            'payable_to_id'   => $desa_id,
            'amount'          => $share_desa,
            'status'          => 'paid_to_balance', // Status khusus saldo internal
        ]);
    }

    // C. Jatah Verifikator (Pemilik Kode)
    if ($verifier_id && $share_verifier > 0) {
        dw_add_user_balance($verifier_id, $share_verifier, "Verifier Fee - Trans #$transaction_id");
        
        // Catat ke Ledger
        $wpdb->insert($ledger_table, [
            'order_id'        => $transaction_id,
            'payable_to_type' => 'verifikator',
            'payable_to_id'   => $verifier_id,
            'amount'          => $share_verifier,
            'status'          => 'paid_to_balance',
        ]);
    } else if ($share_verifier > 0) {
        // Jika tidak ada verifikator, jatahnya kembali ke Platform
        dw_add_user_balance(1, $share_verifier, "Verifier Fee (Admin Fallback) - Trans #$transaction_id");
    }
}

/**
 * Fungsi lama untuk kompatibilitas jika masih dipanggil di bagian lain
 * Dialihkan ke sistem pemrosesan komisi dinamis yang baru.
 */
function dw_record_commission($order_id, $pedagang_id, $total_transaksi) {
    // Karena sekarang sistem menggunakan potongan biaya layanan (Admin Fee) sebagai dasar pembagian,
    // Kita asumsikan Admin Fee dihitung di sini atau diambil dari pengaturan global.
    $admin_fee_pct = get_option('dw_global_admin_fee_percent', 10); // Misal default 10%
    $total_admin_fee = ($total_transaksi * $admin_fee_pct) / 100;
    
    dw_process_transaction_commissions($order_id, $total_admin_fee);
}