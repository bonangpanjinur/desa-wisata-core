<?php
/**
 * File Name:   page-komisi.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-komisi.php
 *
 * --- PERUBAHAN (REKOMENDASI MVP+) ---
 * - Poin 4: Halaman ini DIBANGUN ULANG TOTAL untuk membaca dari tabel `dw_payout_ledger`.
 * - Menampilkan ringkasan komisi 'unpaid' (belum dibayar) per desa.
 * - Menyediakan tombol "Tandai Sudah Dibayar" untuk setiap desa.
 * - Menambahkan handler `admin_post` untuk memproses aksi "Tandai Sudah Dibayar".
 * - Menampilkan laporan komisi platform (yang selalu 'paid').
 *
 * --- PERBAIKAN (Alur Gratis) ---
 * - MENGHAPUS semua referensi ke Komisi Pendaftaran.
 * - Halaman ini sekarang HANYA menampilkan Komisi Penjualan.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Poin 4: Handler untuk aksi "Tandai Sudah Dibayar".
 */
function dw_mark_payout_paid_handler() {
    // Cek nonce dan kapabilitas
    if ( ! isset( $_POST['dw_mark_paid_nonce'] ) || ! wp_verify_nonce( $_POST['dw_mark_paid_nonce'], 'dw_mark_paid_action' ) ) {
        wp_die( 'Security check failed.' );
    }
    if ( ! current_user_can( 'dw_manage_settings' ) ) { // Gunakan kapabilitas yang sesuai
        wp_die( 'Anda tidak memiliki izin untuk melakukan aksi ini.' );
    }

    $desa_id = isset( $_POST['desa_id'] ) ? absint( $_POST['desa_id'] ) : 0;
    if ( $desa_id <= 0 ) {
        wp_die( 'ID Desa tidak valid.' );
    }

    global $wpdb;
    $ledger_table = $wpdb->prefix . 'dw_payout_ledger';

    // Update semua entri 'unpaid' untuk desa ini menjadi 'paid'
    $updated = $wpdb->update(
        $ledger_table,
        [ 
            'status' => 'paid',
            'paid_at' => current_time('mysql', 1) 
        ],
        [ // WHERE
            'payable_to_type' => 'desa',
            'payable_to_id' => $desa_id,
            'status' => 'unpaid'
        ],
        [ '%s', '%s' ], // format data
        [ '%s', '%d', '%s' ] // format WHERE
    );

    if ( $updated !== false ) {
        add_settings_error( 'dw_komisi_notices', 'payout_marked_paid', "Semua komisi terutang untuk Desa ID #{$desa_id} telah ditandai lunas.", 'success' );
    } else {
        add_settings_error( 'dw_komisi_notices', 'payout_mark_failed', 'Gagal memperbarui status payout: ' . $wpdb->last_error, 'error' );
    }
    
    set_transient( 'settings_errors', get_settings_errors(), 30 );
    wp_redirect( admin_url( 'admin.php?page=dw-komisi' ) );
    exit;
}
add_action( 'admin_post_dw_mark_payout_paid', 'dw_mark_payout_paid_handler' );


/**
 * Merender halaman Laporan Komisi (dibangun ulang untuk Poin 4).
 */
function dw_komisi_page_render() {
    if (!current_user_can('dw_manage_settings')) {
        wp_die('Anda tidak punya izin untuk melihat laporan ini.');
    }

    global $wpdb;
    $ledger_table = $wpdb->prefix . 'dw_payout_ledger';
    $desa_table = $wpdb->prefix . 'dw_desa';
    
    // Tampilkan notifikasi jika ada
    $errors = get_transient('settings_errors');
    if($errors) {
        settings_errors('dw_komisi_notices');
        delete_transient('settings_errors');
    }

    // --- 1. Query Payout Desa yang Belum Dibayar ---
    $desa_unpaid_commissions = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            l.payable_to_id AS desa_id, 
            d.nama_desa, 
            SUM(l.amount) AS total_unpaid,
            COUNT(l.id) AS total_orders
         FROM $ledger_table l
         JOIN $desa_table d ON l.payable_to_id = d.id
         WHERE l.payable_to_type = %s AND l.status = %s
         GROUP BY l.payable_to_id, d.nama_desa
         ORDER BY total_unpaid DESC",
        'desa', 'unpaid'
    ), ARRAY_A );
    
    $total_unpaid_all_desa = array_sum(wp_list_pluck($desa_unpaid_commissions, 'total_unpaid'));

    // --- 2. Query Total Payout Platform (Selalu 'paid') ---
    $platform_total_income = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(amount) FROM $ledger_table WHERE payable_to_type = %s AND status = %s",
        'platform', 'paid'
    ));
    
    // --- 3. Query Payout Desa yang Sudah Dibayar (Ringkasan) ---
    $desa_paid_commissions = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            l.payable_to_id AS desa_id, 
            d.nama_desa, 
            SUM(l.amount) AS total_paid
         FROM $ledger_table l
         JOIN $desa_table d ON l.payable_to_id = d.id
         WHERE l.payable_to_type = %s AND l.status = %s
         GROUP BY l.payable_to_id, d.nama_desa
         ORDER BY total_paid DESC",
        'desa', 'paid'
    ), ARRAY_A );

    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Manajemen Payout Komisi</h1>
        </div>
        <p>Halaman ini melacak komisi penjualan yang menjadi hak Desa dan mengelola pembayarannya (payout).</p>

        <div class="dw-dashboard-cards">
            <div class="dw-card" style="background-color: #ffebee; border-color: #b71c1c;">
                <div class="dw-card-icon"><span class="dashicons dashicons-warning" style="color: #b71c1c;"></span></div>
                <div class="dw-card-content">
                    <h3>Total Utang Payout ke Desa</h3>
                    <p class="dw-card-number" style="color: #b71c1c;">Rp <?php echo number_format($total_unpaid_all_desa, 0, ',', '.'); ?></p>
                    <p class="description">Total komisi penjualan yang harus segera ditransfer ke semua Desa.</p>
                </div>
            </div>

            <div class="dw-card" style="background-color: #e8f5e9;">
                <div class="dw-card-icon"><span class="dashicons dashicons-chart-bar" style="color: #1b5e20;"></span></div>
                <div class="dw-card-content">
                    <h3>Total Pendapatan Platform</h3>
                    <p class="dw-card-number">Rp <?php echo number_format($platform_total_income, 0, ',', '.'); ?></p>
                    <p class="description">Total komisi penjualan yang telah diterima oleh Platform (Super Admin).</p>
                </div>
            </div>
        </div>
        
        <h2 style="margin-top: 30px;">Payout Tertunda (Unpaid) ke Desa</h2>
        <p>Daftar komisi yang terkumpul per desa dan menunggu untuk dibayarkan (ditransfer manual oleh Super Admin).</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 10%;">ID Desa</th>
                    <th style="width: 40%;">Nama Desa</th>
                    <th style="width: 15%;">Jumlah Transaksi</th>
                    <th style="width: 20%; text-align: right;">Total Utang (Rp)</th>
                    <th style="width: 15%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($desa_unpaid_commissions)): ?>
                    <tr><td colspan="5">Tidak ada payout yang tertunda.</td></tr>
                <?php else: foreach ($desa_unpaid_commissions as $desa): ?>
                    <tr>
                        <td><?php echo esc_html($desa['desa_id']); ?></td>
                        <td><strong><?php echo esc_html($desa['nama_desa']); ?></strong></td>
                        <td><?php echo number_format_i18n($desa['total_orders']); ?> transaksi</td>
                        <td style="text-align: right; font-weight: bold; color: #b71c1c;">
                            Rp <?php echo number_format($desa['total_unpaid'], 0, ',', '.'); ?>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="dw_mark_payout_paid">
                                <input type="hidden" name="desa_id" value="<?php echo esc_attr($desa['desa_id']); ?>">
                                <?php wp_nonce_field('dw_mark_paid_action', 'dw_mark_paid_nonce'); ?>
                                <button type="submit" class="button button-primary" onclick="return confirm('Anda yakin sudah mentransfer Rp <?php echo number_format($desa['total_unpaid'], 0, ',', '.'); ?> ke <?php echo esc_attr($desa['nama_desa']); ?>?');">
                                    Tandai Sudah Dibayar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <h2 style="margin-top: 30px;">Riwayat Payout (Paid) ke Desa</h2>
        <p>Daftar komisi yang sudah lunas dibayarkan ke Desa.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 10%;">ID Desa</th>
                    <th style="width: 50%;">Nama Desa</th>
                    <th style="width: 40%; text-align: right;">Total Telah Dibayar (Rp)</th>
                </tr>
            </thead>
            <tbody>
                 <?php if (empty($desa_paid_commissions)): ?>
                    <tr><td colspan="3">Belum ada riwayat payout.</td></tr>
                <?php else: foreach ($desa_paid_commissions as $desa): ?>
                    <tr>
                        <td><?php echo esc_html($desa['desa_id']); ?></td>
                        <td><?php echo esc_html($desa['nama_desa']); ?></td>
                        <td style="text-align: right; color: green;">
                            Rp <?php echo number_format($desa['total_paid'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

    </div>
    <?php
}
