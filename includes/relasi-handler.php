<?php
/**
 * File Name:   includes/relasi-handler.php
 * Description: Handles automatic relationship logic between merchants and villages.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Syncs independent merchants when a new village is created.
 *
 * @param int $desa_id The ID of the newly created village.
 * @param string $kelurahan_id The kelurahan ID of the new village.
 */
function dw_sync_independent_merchants_to_desa($desa_id, $kelurahan_id) {
    global $wpdb;
    $pedagang_table = $wpdb->prefix . 'dw_pedagang';

    if (empty($kelurahan_id) || empty($desa_id)) {
        return;
    }

    // Find all independent merchants in the same kelurahan
    $wpdb->update(
        $pedagang_table,
        [
            'id_desa' => $desa_id,
            'is_independent' => 0,
        ],
        [
            'api_kelurahan_id' => $kelurahan_id,
            'is_independent' => 1, // Only target independent merchants
        ]
    );
}

/**
 * Checks and updates a merchant's relationship status based on their address.
 *
 * @param int $pedagang_id The ID of the merchant.
 * @param string $kelurahan_id The kelurahan ID of the merchant.
 */
function dw_check_and_update_merchant_relation($pedagang_id, $kelurahan_id) {
    global $wpdb;
    $pedagang_table = $wpdb->prefix . 'dw_pedagang';
    $desa_table = $wpdb->prefix . 'dw_desa';

    if (empty($kelurahan_id) || empty($pedagang_id)) {
        return;
    }

    // Check if a village exists in the same kelurahan
    $desa = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $desa_table WHERE api_kelurahan_id = %s LIMIT 1",
        $kelurahan_id
    ));

    if ($desa) {
        // Village found, link the merchant
        $wpdb->update(
            $pedagang_table,
            ['id_desa' => $desa->id, 'is_independent' => 0],
            ['id' => $pedagang_id]
        );
    } else {
        // No village found, ensure merchant is independent
        $wpdb->update(
            $pedagang_table,
            ['id_desa' => 0, 'is_independent' => 1],
            ['id' => $pedagang_id]
        );
    }
}