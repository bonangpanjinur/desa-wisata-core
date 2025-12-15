<?php
/**
 * File Name:   page-komisi.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-komisi.php
 *
 * [UPDATED] Laporan Komisi dengan UI Modern (Cards & Clean Table).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler: Tandai Sudah Dibayar.
 */
function dw_mark_payout_paid_handler() {
    if ( ! isset( $_POST['dw_mark_paid_nonce'] ) || ! wp_verify_nonce( $_POST['dw_mark_paid_nonce'], 'dw_mark_paid_action' ) ) wp_die( 'Security check failed.' );
    if ( ! current_user_can( 'dw_manage_settings' ) ) wp_die( 'Akses ditolak.' );

    $desa_id = isset( $_POST['desa_id'] ) ? absint( $_POST['desa_id'] ) : 0;
    if ( $desa_id <= 0 ) wp_die( 'ID Desa tidak valid.' );

    global $wpdb;
    $updated = $wpdb->update(
        $wpdb->prefix . 'dw_payout_ledger',
        [ 'status' => 'paid', 'paid_at' => current_time('mysql', 1) ],
        [ 'payable_to_type' => 'desa', 'payable_to_id' => $desa_id, 'status' => 'unpaid' ]
    );

    if ( $updated !== false ) {
        add_settings_error( 'dw_komisi_notices', 'paid', "Payout untuk Desa ID #{$desa_id} berhasil ditandai lunas.", 'success' );
    } else {
        add_settings_error( 'dw_komisi_notices', 'failed', 'Gagal update database.', 'error' );
    }
    
    set_transient( 'settings_errors', get_settings_errors(), 30 );
    wp_redirect( admin_url( 'admin.php?page=dw-komisi' ) );
    exit;
}
add_action( 'admin_post_dw_mark_payout_paid', 'dw_mark_payout_paid_handler' );


/**
 * Render Halaman Laporan.
 */
function dw_komisi_page_render() {
    if (!current_user_can('dw_manage_settings')) wp_die('Akses ditolak.');

    global $wpdb;
    $ledger_table = $wpdb->prefix . 'dw_payout_ledger';
    $desa_table = $wpdb->prefix . 'dw_desa';
    
    // Notifikasi
    $errors = get_transient('settings_errors');
    if($errors) { settings_errors('dw_komisi_notices'); delete_transient('settings_errors'); }

    // DATA 1: Unpaid (Utang)
    $desa_unpaid = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.payable_to_id AS desa_id, d.nama_desa, SUM(l.amount) AS total_unpaid, COUNT(l.id) AS total_orders
         FROM $ledger_table l JOIN $desa_table d ON l.payable_to_id = d.id
         WHERE l.payable_to_type = %s AND l.status = %s GROUP BY l.payable_to_id, d.nama_desa ORDER BY total_unpaid DESC",
        'desa', 'unpaid'
    ), ARRAY_A );
    $total_debt = array_sum(wp_list_pluck($desa_unpaid, 'total_unpaid'));

    // DATA 2: Platform Income
    $platform_income = (float) $wpdb->get_var( $wpdb->prepare("SELECT SUM(amount) FROM $ledger_table WHERE payable_to_type = %s AND status = %s", 'platform', 'paid'));
    
    // DATA 3: History Paid
    $desa_paid = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.payable_to_id AS desa_id, d.nama_desa, SUM(l.amount) AS total_paid
         FROM $ledger_table l JOIN $desa_table d ON l.payable_to_id = d.id
         WHERE l.payable_to_type = %s AND l.status = %s GROUP BY l.payable_to_id, d.nama_desa ORDER BY total_paid DESC LIMIT 10",
        'desa', 'paid'
    ), ARRAY_A );

    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header"><h1>Manajemen Payout Komisi</h1></div>
        
        <!-- Styles -->
        <style>
            .dw-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .dw-card { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; align-items: center; }
            .dw-card-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 20px; }
            .dw-card-content h3 { margin: 0 0 5px 0; font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; }
            .dw-card-amount { font-size: 28px; font-weight: 800; margin: 0; line-height: 1; }
            
            .card-debt { border-left: 4px solid #ef4444; }
            .icon-debt { background: #fee2e2; color: #ef4444; }
            .text-debt { color: #ef4444; }
            
            .card-income { border-left: 4px solid #10b981; }
            .icon-income { background: #d1fae5; color: #10b981; }
            .text-income { color: #10b981; }

            .dw-table-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; margin-bottom: 30px; }
            .dw-btn-pay { background: #1e40af; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
            .dw-btn-pay:hover { background: #1e3a8a; }
        </style>

        <!-- Summary Cards -->
        <div class="dw-summary-grid">
            <!-- Card 1: Utang ke Desa -->
            <div class="dw-card card-debt">
                <div class="dw-card-icon icon-debt"><span class="dashicons dashicons-warning"></span></div>
                <div class="dw-card-content">
                    <h3>Total Utang Payout (Desa)</h3>
                    <p class="dw-card-amount text-debt">Rp <?php echo number_format($total_debt, 0, ',', '.'); ?></p>
                </div>
            </div>
            <!-- Card 2: Pendapatan Platform -->
            <div class="dw-card card-income">
                <div class="dw-card-icon icon-income"><span class="dashicons dashicons-chart-line"></span></div>
                <div class="dw-card-content">
                    <h3>Pendapatan Platform</h3>
                    <p class="dw-card-amount text-income">Rp <?php echo number_format($platform_income, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>

        <!-- Tabel Unpaid -->
        <h2 style="font-size: 18px; margin-bottom: 10px;">Tagihan Payout ke Desa</h2>
        <div class="dw-table-card">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="30%">Nama Desa</th>
                        <th width="20%">Transaksi Terkumpul</th>
                        <th width="25%">Total Komisi (Unpaid)</th>
                        <th width="25%" style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($desa_unpaid)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px; color:#aaa;">Tidak ada payout yang tertunda. Semua lunas!</td></tr>
                    <?php else: foreach ($desa_unpaid as $row): ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['nama_desa']); ?></strong></td>
                            <td><?php echo number_format_i18n($row['total_orders']); ?> item</td>
                            <td style="font-weight:bold; color:#ef4444;">Rp <?php echo number_format($row['total_unpaid'], 0, ',', '.'); ?></td>
                            <td style="text-align:right;">
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <input type="hidden" name="action" value="dw_mark_payout_paid">
                                    <input type="hidden" name="desa_id" value="<?php echo esc_attr($row['desa_id']); ?>">
                                    <?php wp_nonce_field('dw_mark_paid_action', 'dw_mark_paid_nonce'); ?>
                                    <button type="submit" class="dw-btn-pay" onclick="return confirm('Konfirmasi: Anda SUDAH mentransfer Rp <?php echo number_format($row['total_unpaid'], 0, ',', '.'); ?> ke <?php echo esc_js($row['nama_desa']); ?>?');">
                                        <span class="dashicons dashicons-saved" style="font-size:16px; vertical-align:middle;"></span> Tandai Lunas
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tabel History (Paid) -->
        <h2 style="font-size: 18px; margin-bottom: 10px;">Riwayat Transfer (10 Terbesar)</h2>
        <div class="dw-table-card">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="60%">Nama Desa</th>
                        <th width="40%" style="text-align:right;">Total Telah Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                     <?php if (empty($desa_paid)): ?>
                        <tr><td colspan="2" style="text-align:center; padding:15px; color:#aaa;">Belum ada riwayat.</td></tr>
                    <?php else: foreach ($desa_paid as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row['nama_desa']); ?></td>
                            <td style="text-align:right; color:#10b981; font-weight:600;">
                                Rp <?php echo number_format($row['total_paid'], 0, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>
    <?php
}
?>