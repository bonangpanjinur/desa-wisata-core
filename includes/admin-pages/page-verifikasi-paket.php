<?php
/**
 * Page: Verifikasi Pembelian Paket
 * Description: Halaman Admin Pusat untuk memvalidasi transfer pembayaran paket.
 * Update: Menambahkan kolom informasi User Role (Desa/Verifikator) pembeli.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_pembelian = $wpdb->prefix . 'dw_pembelian_paket';
$table_pedagang  = $wpdb->prefix . 'dw_pedagang';
$table_users     = $wpdb->prefix . 'users';

// Handle Action Approve/Reject
if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'dw_verifikasi_paket' ) ) {
    $id_pembelian = intval( $_GET['id'] );
    $status_baru  = ( $_GET['action'] == 'approve' ) ? 'disetujui' : 'ditolak';
    
    // Update Status
    $wpdb->update( 
        $table_pembelian, 
        array( 'status' => $status_baru, 'processed_at' => current_time( 'mysql' ) ), 
        array( 'id' => $id_pembelian ) 
    );

    // Jika Approve, Tambah Kuota (Logic disederhanakan)
    if ( $status_baru == 'disetujui' ) {
        $pembelian = $wpdb->get_row( "SELECT * FROM $table_pembelian WHERE id = $id_pembelian" );
        if ( $pembelian ) {
            // Panggil fungsi helper tambah kuota (asumsi fungsi ada di helpers.php)
            if ( function_exists('dw_add_pedagang_quota') ) {
                dw_add_pedagang_quota( $pembelian->id_pedagang, $pembelian->jumlah_transaksi, 'pembelian_paket', $id_pembelian );
            }
        }
        echo '<div class="notice notice-success"><p>Pembelian paket berhasil disetujui dan kuota ditambahkan.</p></div>';
    }
}

// Query Data Pending
$items = $wpdb->get_results( 
    "SELECT p.*, d.nama_toko, d.nama_pemilik, u.user_email, u.ID as user_wp_id
    FROM $table_pembelian p 
    LEFT JOIN $table_pedagang d ON p.id_pedagang = d.id
    LEFT JOIN $table_users u ON d.id_user = u.ID
    WHERE p.status = 'pending' 
    ORDER BY p.created_at ASC" 
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Verifikasi Pembelian Paket & Kuota</h1>
    <hr class="wp-header-end">

    <div class="dw-card">
        <h3>Permohonan Pembelian Pending</h3>
        
        <?php if ( empty( $items ) ) : ?>
            <p>Tidak ada permohonan pembelian paket yang menunggu verifikasi.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="120">Tanggal</th>
                        <th>Pemohon (User)</th>
                        <th>Role / Tipe</th>
                        <th>Paket</th>
                        <th>Harga</th>
                        <th>Bukti Bayar</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $item ) : 
                        // Deteksi Role User WordPress
                        $user_info = get_userdata( $item->user_wp_id );
                        $roles = $user_info ? $user_info->roles : array();
                        $role_label = 'Pedagang Biasa';
                        $badge_class = 'secondary';

                        if ( in_array( 'dw_desa', $roles ) ) {
                            $role_label = 'Akun Desa';
                            $badge_class = 'primary';
                        } elseif ( in_array( 'dw_verifikator', $roles ) ) {
                            $role_label = 'Verifikator UMKM';
                            $badge_class = 'warning'; // Orange
                        }
                    ?>
                        <tr>
                            <td><?php echo date( 'd M Y H:i', strtotime( $item->created_at ) ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $item->nama_toko ); ?></strong><br>
                                <small><?php echo esc_html( $item->nama_pemilik ); ?> (<?php echo esc_html( $item->user_email ); ?>)</small>
                            </td>
                            <td>
                                <span class="dw-badge dw-badge-<?php echo $badge_class; ?>">
                                    <?php echo $role_label; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo esc_html( $item->nama_paket_snapshot ); ?></strong><br>
                                <small>+<?php echo number_format( $item->jumlah_transaksi ); ?> Transaksi</small>
                            </td>
                            <td>Rp <?php echo number_format( $item->harga_paket, 0, ',', '.' ); ?></td>
                            <td>
                                <?php if ( $item->url_bukti_bayar ) : ?>
                                    <a href="<?php echo esc_url( $item->url_bukti_bayar ); ?>" target="_blank" class="button button-small">Lihat Bukti</a>
                                <?php else : ?>
                                    <span class="description">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $nonce_url_approve = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-paket&action=approve&id=' . $item->id ), 'dw_verifikasi_paket' );
                                $nonce_url_reject = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-paket&action=reject&id=' . $item->id ), 'dw_verifikasi_paket' );
                                ?>
                                <a href="<?php echo $nonce_url_approve; ?>" class="button button-primary" onclick="return confirm('Yakin setujui pembayaran ini? Kuota akan langsung masuk.');">Setujui</a>
                                <a href="<?php echo $nonce_url_reject; ?>" class="button button-secondary" style="color: #a00;" onclick="return confirm('Tolak pembayaran ini?');">Tolak</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
    /* CSS Inline Sederhana untuk Badge */
    .dw-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        color: #fff;
    }
    .dw-badge-primary { background-color: #2271b1; } /* Biru WP */
    .dw-badge-warning { background-color: #f0b849; color: #1d2327; } /* Kuning/Orange */
    .dw-badge-secondary { background-color: #646970; } /* Abu-abu */
    .dw-card {
        background: #fff;
        padding: 20px;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        margin-top: 20px;
    }
</style>