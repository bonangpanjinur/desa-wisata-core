<?php
/**
 * Page: Verifikasi Pembelian Paket (Super Admin)
 * Description: Memvalidasi transfer paket & Membagi Komisi ke Referrer (Desa/Verifikator).
 * UPDATE v3.9: Logika Otomatis mendeteksi Referrer dari Data Pedagang.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dw_render_verifikasi_paket_page() {
    global $wpdb;
    $table_pembelian = $wpdb->prefix . 'dw_pembelian_paket';
    $table_pedagang  = $wpdb->prefix . 'dw_pedagang';
    $table_users     = $wpdb->base_prefix . 'users'; // Base prefix for global users
    $table_paket     = $wpdb->prefix . 'dw_paket_transaksi';

    // --- HANDLE ACTION APPROVE/REJECT ---
    if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'dw_verifikasi_paket' ) ) {
        $id_pembelian = intval( $_GET['id'] );
        $action = $_GET['action']; // 'approve' or 'reject'
        $status_baru  = ( $action == 'approve' ) ? 'disetujui' : 'ditolak';
        
        // 1. Ambil Data Pembelian & Pedagang Terkait
        $pembelian = $wpdb->get_row( "SELECT * FROM $table_pembelian WHERE id = $id_pembelian" );
        
        if ($pembelian && $pembelian->status == 'pending') {
            
            // Siapkan Data Update Dasar
            $update_data = [
                'status' => $status_baru,
                'processed_at' => current_time( 'mysql' )
            ];

            // LOGIKA APPROVE
            if ( $status_baru == 'disetujui' ) {
                $pedagang = $wpdb->get_row( "SELECT * FROM $table_pedagang WHERE id = {$pembelian->id_pedagang}" );
                $paket_info = $wpdb->get_row( "SELECT * FROM $table_paket WHERE id = {$pembelian->id_paket}" );
                
                // A. Tambah Kuota Pedagang
                $quota_add = $pembelian->jumlah_transaksi;
                $wpdb->query( "UPDATE $table_pedagang SET sisa_transaksi = sisa_transaksi + $quota_add, status_akun = 'aktif' WHERE id = {$pembelian->id_pedagang}" );

                // B. Log Quota
                $wpdb->insert($wpdb->prefix . 'dw_quota_logs', [
                    'user_id' => $pedagang->id_user,
                    'quota_change' => $quota_add,
                    'type' => 'purchase',
                    'description' => 'Pembelian Paket: ' . $pembelian->nama_paket_snapshot,
                    'reference_id' => $id_pembelian
                ]);

                // C. DETEKSI REFERRER & HITUNG KOMISI
                // Logika: Cek Verifikator dulu, kalau tidak ada baru Cek Desa
                $referrer_id = 0;
                $referrer_type = null;

                if ( !empty($pedagang->id_verifikator) && $pedagang->id_verifikator > 0 ) {
                    $referrer_id = $pedagang->id_verifikator;
                    $referrer_type = 'verifikator';
                } elseif ( !empty($pedagang->id_desa) && $pedagang->id_desa > 0 ) {
                    $referrer_id = $pedagang->id_desa;
                    $referrer_type = 'desa';
                }

                // Hitung Nominal Komisi
                $komisi = 0;
                if ( $referrer_id && $paket_info ) {
                    if ( $paket_info->komisi_nominal > 0 ) {
                        $komisi = $paket_info->komisi_nominal;
                    } elseif ( $paket_info->persentase_komisi > 0 ) {
                        $komisi = ($paket_info->persentase_komisi / 100) * $pembelian->harga_paket;
                    }
                }

                // Update Data Pembelian dengan Info Komisi
                $update_data['referrer_id'] = $referrer_id;
                $update_data['referrer_type'] = $referrer_type;
                $update_data['komisi_nominal_cair'] = $komisi;

                // D. DISTRIBUSI KOMISI (JIKA ADA)
                if ( $komisi > 0 && $referrer_id ) {
                    // 1. Catat di Riwayat Komisi (History)
                    $wpdb->insert( $wpdb->prefix . 'dw_riwayat_komisi', [
                        'id_penerima' => $referrer_id,
                        'role_penerima' => $referrer_type,
                        'id_sumber_pedagang' => $pedagang->id,
                        'id_pembelian_paket' => $id_pembelian,
                        'jumlah_komisi' => $komisi,
                        'keterangan' => "Komisi dari pembelian paket '{$pembelian->nama_paket_snapshot}' oleh {$pedagang->nama_toko}"
                    ]);

                    // 2. Catat di Payout Ledger (Hutang Platform ke Referrer)
                    $wpdb->insert( $wpdb->prefix . 'dw_payout_ledger', [
                        'order_id' => $id_pembelian, 
                        'payable_to_type' => $referrer_type,
                        'payable_to_id' => $referrer_id,
                        'amount' => $komisi,
                        'status' => 'unpaid'
                    ]);

                    // 3. Update Saldo Real di Tabel Master User
                    if ( $referrer_type == 'desa' ) {
                        $wpdb->query( "UPDATE {$wpdb->prefix}dw_desa SET saldo_komisi = saldo_komisi + $komisi, total_pendapatan = total_pendapatan + $komisi WHERE id = $referrer_id" );
                    } elseif ( $referrer_type == 'verifikator' ) {
                        $wpdb->query( "UPDATE {$wpdb->prefix}dw_verifikator SET saldo_saat_ini = saldo_saat_ini + $komisi, total_pendapatan_komisi = total_pendapatan_komisi + $komisi WHERE id = $referrer_id" );
                    }
                }
            } // End Approve Logic

            // Eksekusi Update Status Pembelian
            $wpdb->update( $table_pembelian, $update_data, array( 'id' => $id_pembelian ) );
            
            echo '<div class="notice notice-success is-dismissible"><p>Status pembelian diperbarui. ' . ($status_baru == 'disetujui' ? 'Kuota ditambahkan & Komisi didistribusikan.' : '') . '</p></div>';
        }
    }

    // --- VIEW TABLE ---
    // Query Join untuk menampilkan Nama Pedagang & User WP
    $rows = $wpdb->get_results("
        SELECT t.*, p.nama_toko, p.nama_pemilik, u.user_email, p.id_desa, p.id_verifikator
        FROM $table_pembelian t
        JOIN $table_pedagang p ON t.id_pedagang = p.id
        JOIN $table_users u ON p.id_user = u.ID
        WHERE t.status = 'pending'
        ORDER BY t.created_at ASC
    ");
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Verifikasi Pembayaran Paket</h1>
        
        <?php if ( empty( $rows ) ) : ?>
            <div class="notice notice-info inline" style="margin-top:20px;">
                <p>Tidak ada tagihan pending saat ini.</p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Pedagang / Toko</th>
                        <th>Paket</th>
                        <th>Bukti Bayar</th>
                        <th>Potensi Komisi Ke</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $item ) : 
                        // Deteksi Preview Siapa yang dapat komisi
                        $calon_penerima = '<span style="color:#999">-Admin-</span>';
                        if ($item->id_verifikator > 0) {
                            $calon_penerima = '<span class="dw-badge dw-badge-warning">Verifikator</span>';
                        } elseif ($item->id_desa > 0) {
                            $calon_penerima = '<span class="dw-badge dw-badge-primary">Desa</span>';
                        }
                    ?>
                        <tr>
                            <td>#<?php echo $item->id; ?></td>
                            <td><?php echo date( 'd M H:i', strtotime( $item->created_at ) ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $item->nama_toko ); ?></strong><br>
                                <small><?php echo esc_html( $item->nama_pemilik ); ?> (<?php echo esc_html($item->user_email); ?>)</small>
                            </td>
                            <td>
                                <?php echo esc_html( $item->nama_paket_snapshot ); ?><br>
                                <strong>Rp <?php echo number_format( $item->harga_paket, 0, ',', '.' ); ?></strong>
                            </td>
                            <td>
                                <?php if ( $item->url_bukti_bayar ) : ?>
                                    <a href="<?php echo esc_url( $item->url_bukti_bayar ); ?>" target="_blank" class="button button-small">Lihat Bukti</a>
                                <?php else : ?>
                                    <span style="color:red;">Tidak ada file</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $calon_penerima; ?></td>
                            <td>
                                <?php 
                                $nonce_url_approve = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-paket&action=approve&id=' . $item->id ), 'dw_verifikasi_paket' );
                                $nonce_url_reject = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-paket&action=reject&id=' . $item->id ), 'dw_verifikasi_paket' );
                                ?>
                                <a href="<?php echo $nonce_url_approve; ?>" class="button button-primary" onclick="return confirm('Setujui pembayaran & proses komisi otomatis?');">Terima</a>
                                <a href="<?php echo $nonce_url_reject; ?>" class="button button-secondary" style="color: #a00;" onclick="return confirm('Tolak pembayaran ini?');">Tolak</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="margin-top: 30px; background: #f0f0f1; padding: 15px; border-left: 4px solid #2271b1;">
            <h4>Catatan Sistem Komisi v3.9:</h4>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Jika Pedagang terdaftar via <strong>Verifikator</strong>, komisi masuk ke Saldo Verifikator.</li>
                <li>Jika Pedagang terdaftar via <strong>Desa</strong> (tanpa Verifikator), komisi masuk ke Saldo Desa.</li>
                <li>Jika Independen, seluruh pendapatan masuk ke Admin (Platform).</li>
                <li>Proses pembagian saldo terjadi otomatis saat tombol <strong>"Terima"</strong> diklik.</li>
            </ul>
        </div>
    </div>
    
    <style>
    .dw-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; color: #fff; }
    .dw-badge-primary { background-color: #2271b1; }
    .dw-badge-warning { background-color: #f0b849; color: #333; }
    </style>
    <?php
}