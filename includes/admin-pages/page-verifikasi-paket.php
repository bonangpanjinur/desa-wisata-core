<?php
/**
 * File: includes/admin-pages/page-verifikasi-paket.php
 * * Admin Page: Verifikasi Paket Wisata
 * * Halaman untuk admin pusat memverifikasi paket wisata yang diajukan oleh desa/mitra.
 * * Termasuk logika distribusi komisi ke Verifikator/Desa.
 */

defined( 'ABSPATH' ) || exit;

function dw_render_verifikasi_paket_page() {
    global $wpdb;

    // --- 1. Security & Setup ---
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'desa-wisata-core' ) );
    }

    $table_pembelian   = $wpdb->prefix . 'dw_pembelian_paket';
    $table_pedagang    = $wpdb->prefix . 'dw_pedagang';
    $table_users       = $wpdb->users; 
    $table_paket       = $wpdb->prefix . 'dw_paket_transaksi';
    $table_desa        = $wpdb->prefix . 'dw_desa';
    $table_verifikator = $wpdb->prefix . 'dw_verifikator';

    $message      = '';
    $message_type = '';

    // --- 2. Handle Action (Approve/Reject) ---
    if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'dw_verifikasi_paket' ) ) {
        $id_pembelian = intval( $_GET['id'] );
        $action       = $_GET['action']; // 'approve' or 'reject'
        $status_baru  = ( $action == 'approve' ) ? 'disetujui' : 'ditolak';
        
        // Ambil Data Pembelian & Pedagang Terkait
        $pembelian = $wpdb->get_row( "SELECT * FROM $table_pembelian WHERE id = $id_pembelian" );
        
        if ($pembelian && $pembelian->status == 'pending') {
            
            // Siapkan Data Update Dasar
            $update_data = [
                'status'       => $status_baru,
                'processed_at' => current_time( 'mysql' )
            ];

            // LOGIKA APPROVE
            if ( $status_baru == 'disetujui' ) {
                $pedagang   = $wpdb->get_row( "SELECT * FROM $table_pedagang WHERE id = {$pembelian->id_pedagang}" );
                $paket_info = $wpdb->get_row( "SELECT * FROM $table_paket WHERE id = {$pembelian->id_paket}" );
                
                // A. Tambah Kuota Pedagang
                $quota_add = $pembelian->jumlah_transaksi;
                $wpdb->query( "UPDATE $table_pedagang SET sisa_transaksi = sisa_transaksi + $quota_add, status_akun = 'aktif' WHERE id = {$pembelian->id_pedagang}" );

                // B. Log Quota
                $wpdb->insert($wpdb->prefix . 'dw_quota_logs', [
                    'user_id'      => $pedagang->id_user,
                    'quota_change' => $quota_add,
                    'type'         => 'purchase',
                    'description'  => 'Pembelian Paket: ' . $pembelian->nama_paket_snapshot,
                    'reference_id' => $id_pembelian
                ]);

                // C. DETEKSI REFERRER & HITUNG KOMISI
                $referrer_id   = 0;
                $referrer_type = null;

                if ( !empty($pedagang->id_verifikator) && $pedagang->id_verifikator > 0 ) {
                    $referrer_id   = $pedagang->id_verifikator;
                    $referrer_type = 'verifikator';
                } elseif ( !empty($pedagang->id_desa) && $pedagang->id_desa > 0 ) {
                    $referrer_id   = $pedagang->id_desa;
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
                $update_data['referrer_id']         = $referrer_id;
                $update_data['referrer_type']       = $referrer_type;
                $update_data['komisi_nominal_cair'] = $komisi;

                // D. DISTRIBUSI KOMISI (JIKA ADA)
                if ( $komisi > 0 && $referrer_id ) {
                    // 1. Catat di Riwayat Komisi (History)
                    $wpdb->insert( $wpdb->prefix . 'dw_riwayat_komisi', [
                        'id_penerima'        => $referrer_id,
                        'role_penerima'      => $referrer_type,
                        'id_sumber_pedagang' => $pedagang->id,
                        'id_pembelian_paket' => $id_pembelian,
                        'jumlah_komisi'      => $komisi,
                        'keterangan'         => "Komisi dari pembelian paket '{$pembelian->nama_paket_snapshot}' oleh {$pedagang->nama_toko}"
                    ]);

                    // 2. Catat di Payout Ledger (Hutang Platform ke Referrer)
                    $wpdb->insert( $wpdb->prefix . 'dw_payout_ledger', [
                        'order_id'        => $id_pembelian, 
                        'payable_to_type' => $referrer_type,
                        'payable_to_id'   => $referrer_id,
                        'amount'          => $komisi,
                        'status'          => 'unpaid'
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
            
            $message = 'Status pembelian berhasil diperbarui menjadi <strong>' . strtoupper($status_baru) . '</strong>.';
            if($status_baru == 'disetujui') {
                $message .= ' Kuota pedagang telah ditambahkan.';
                if(isset($komisi) && $komisi > 0) {
                    $message .= ' Komisi sebesar Rp ' . number_format($komisi) . ' telah dialokasikan ke ' . ucfirst($referrer_type) . '.';
                }
            }
            $message_type = 'success';
        }
    }

    // --- 3. View Logic (Query Data) ---
    $rows = $wpdb->get_results("
        SELECT t.*, 
               p.nama_toko, p.nama_pemilik, u.user_email, 
               p.id_desa, p.id_verifikator,
               d.nama_desa,
               v.nama_lengkap as nama_verifikator
        FROM $table_pembelian t
        JOIN $table_pedagang p ON t.id_pedagang = p.id
        JOIN $table_users u ON p.id_user = u.ID
        LEFT JOIN $table_desa d ON p.id_desa = d.id
        LEFT JOIN $table_verifikator v ON p.id_verifikator = v.id
        WHERE t.status = 'pending'
        ORDER BY t.created_at ASC
    ");

    ?>
    <div class="wrap dw-admin-wrapper">
        <!-- Header Section -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Validasi dan setujui paket wisata yang diajukan oleh mitra desa.</p>
            </div>
            <div class="dw-header-actions">
                <!-- Aksi global jika ada -->
            </div>
        </div>

        <!-- Body Section -->
        <div class="dw-content-body">
            
            <!-- Custom Notice Handling -->
            <?php if ( $message ) : ?>
                <div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible" style="margin-left: 0; margin-bottom: 20px;">
                    <p><?php echo $message; ?></p>
                </div>
            <?php endif; ?>

            <!-- Main Table Card -->
            <div class="dw-card">
                <div class="dw-card-header" style="border-bottom: 1px solid #f0f0f1; padding-bottom: 15px; margin-bottom: 15px;">
                    <h3 style="margin: 0;">Daftar Tagihan Pending</h3>
                </div>
                
                <?php if ( empty( $rows ) ) : ?>
                    <div style="padding: 40px; text-align: center; color: #646970;">
                        <span class="dashicons dashicons-yes" style="font-size: 48px; width: 48px; height: 48px; color: #c3e6cb; display: block; margin: 0 auto 10px;"></span>
                        <p style="font-size: 16px; margin: 0;">Tidak ada tagihan yang perlu diverifikasi saat ini.</p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped table-view-list dw-modern-table" style="border: none; box-shadow: none;">
                        <thead>
                            <tr>
                                <th>ID & Tanggal</th>
                                <th>Pedagang</th>
                                <th>Paket Dibeli</th>
                                <th>Bukti Bayar</th>
                                <th>Distribusi Komisi</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $rows as $item ) : 
                                // Deteksi Siapa yang dapat komisi
                                $calon_penerima = '<span class="dw-badge" style="background:#f0f0f1; color:#646970; padding:2px 8px; border-radius:4px; font-size:11px;">Platform (Admin)</span>';
                                
                                if ($item->id_verifikator > 0) {
                                    $calon_penerima = '<div style="display:flex; flex-direction:column; gap:4px;">
                                            <span style="background:#f6e05e; color:#744210; padding:2px 8px; border-radius:4px; font-size:11px; display:inline-block; width:fit-content;"><span class="dashicons dashicons-groups" style="font-size:12px;"></span> Verifikator</span>
                                            <small style="color:#646970;">' . esc_html($item->nama_verifikator) . '</small>
                                    </div>';
                                } elseif ($item->id_desa > 0) {
                                    $calon_penerima = '<div style="display:flex; flex-direction:column; gap:4px;">
                                            <span style="background:#e6fffa; color:#234e52; padding:2px 8px; border-radius:4px; font-size:11px; display:inline-block; width:fit-content;"><span class="dashicons dashicons-admin-home" style="font-size:12px;"></span> Desa</span>
                                            <small style="color:#646970;">' . esc_html($item->nama_desa) . '</small>
                                    </div>';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo $item->id; ?></strong><br>
                                        <span style="color:#646970; font-size:12px;"><?php echo date( 'd M Y H:i', strtotime( $item->created_at ) ); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $item->nama_toko ); ?></strong><br>
                                        <div style="font-size:12px; color:#646970; margin-top:2px;">
                                            <?php echo esc_html( $item->nama_pemilik ); ?><br>
                                            <?php echo esc_html($item->user_email); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:600; color:#2271b1;"><?php echo esc_html( $item->nama_paket_snapshot ); ?></span><br>
                                        <span style="font-size:13px;">Rp <?php echo number_format( $item->harga_paket, 0, ',', '.' ); ?></span><br>
                                        <span style="background:#d1e7dd; color:#0f5132; padding:2px 6px; border-radius:4px; font-size:10px;">+<?php echo $item->jumlah_transaksi; ?> Kuota</span>
                                    </td>
                                    <td>
                                        <?php if ( $item->url_bukti_bayar ) : ?>
                                            <a href="<?php echo esc_url( $item->url_bukti_bayar ); ?>" target="_blank" class="button button-small">
                                                <span class="dashicons dashicons-visibility" style="margin-top:3px;"></span> Lihat
                                            </a>
                                        <?php else : ?>
                                            <span style="color:#d63638;">Tidak ada file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $calon_penerima; ?></td>
                                    <td style="text-align: right;">
                                        <?php 
                                        $nonce_url_approve = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-paket&action=approve&id=' . $item->id ), 'dw_verifikasi_paket' );
                                        $nonce_url_reject = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-paket&action=reject&id=' . $item->id ), 'dw_verifikasi_paket' );
                                        ?>
                                        <div style="display:flex; justify-content:flex-end; gap:5px;">
                                            <a href="<?php echo $nonce_url_approve; ?>" class="button button-primary" onclick="return confirm('Yakin setujui pembayaran? Kuota akan bertambah & komisi dicatat.');" title="Setujui">
                                                Setujui
                                            </a>
                                            <a href="<?php echo $nonce_url_reject; ?>" class="button button-secondary" onclick="return confirm('Yakin tolak pembayaran ini?');" title="Tolak" style="color: #d63638; border-color: #d63638;">
                                                Tolak
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Info Box Card -->
            <div class="dw-card" style="background-color: #f0f6fc; border-color: #cce5ff;">
                <h4 style="margin-top:0;"><span class="dashicons dashicons-info"></span> Logika Sistem Komisi</h4>
                <ul style="margin-bottom:0; padding-left:20px;">
                    <li><strong>Prioritas Verifikator:</strong> Jika Pedagang memiliki <code>ID Verifikator</code>, komisi masuk ke Verifikator.</li>
                    <li><strong>Prioritas Desa:</strong> Jika tidak ada Verifikator tapi ada <code>ID Desa</code>, komisi masuk ke Desa.</li>
                    <li><strong>Independen:</strong> Jika tidak keduanya, pendapatan masuk ke Platform (Admin).</li>
                    <li>Proses pembagian saldo terjadi <strong>otomatis</strong> saat tombol <strong>"Setujui"</strong> diklik.</li>
                </ul>
            </div>

        </div>
    </div>
    <?php
}