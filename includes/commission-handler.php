<?php
/**
 * File Name:   includes/commission-handler.php
 * Description: Handles all commission-related logic.
 */

if (!defined('ABSPATH')) exit;

/**
 * Calculates and records commission for a transaction.
 *
 * @param int $order_id The ID of the order.
 * @param int $pedagang_id The ID of the merchant.
 * @param float $total_transaksi The total transaction amount.
 */
function dw_record_commission($order_id, $pedagang_id, $total_transaksi) {
    global $wpdb;
    $pedagang_table = $wpdb->prefix . 'dw_pedagang';
    $desa_table = $wpdb->prefix . 'dw_desa';
    $payout_ledger_table = $wpdb->prefix . 'dw_payout_ledger';

    // Get merchant data
    $pedagang = $wpdb->get_row($wpdb->prepare(
        "SELECT id_desa, approved_by FROM $pedagang_table WHERE id = %d",
        $pedagang_id
    ));

    if (!$pedagang || !$pedagang->id_desa || $pedagang->approved_by !== 'desa') {
        return; // No commission if not linked to a village or not approved by a village
    }

    // Get village data
    $desa = $wpdb->get_row($wpdb->prepare(
        "SELECT persentase_komisi_penjualan FROM $desa_table WHERE id = %d",
        $pedagang->id_desa
    ));

    if (!$desa || $desa->persentase_komisi_penjualan <= 0) {
        return; // No commission if percentage is not set
    }

    // Calculate commission
    $commission_amount = ($total_transaksi * $desa->persentase_komisi_penjualan) / 100;

    // Record commission in the payout ledger
    $wpdb->insert(
        $payout_ledger_table,
        [
            'order_id' => $order_id,
            'payable_to_type' => 'desa',
            'payable_to_id' => $pedagang->id_desa,
            'amount' => $commission_amount,
            'status' => 'unpaid',
        ]
    );
}
