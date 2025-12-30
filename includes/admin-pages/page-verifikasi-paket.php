<?php
/**
 * File Name: includes/admin-pages/page-verifikasi-paket.php
 * Description: Halaman Verifikasi Pembelian Paket & Distribusi Komisi
 * Version: 3.9 (UI/UX Modern)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dw_render_page_verifikasi_paket() {
    global $wpdb;
    $table_pembelian   = $wpdb->prefix . 'dw_pembelian_paket';
    $table_pedagang    = $wpdb->prefix . 'dw_pedagang';
    $table_users       = $wpdb->users; 
    $table_paket       = $wpdb->prefix . 'dw_paket_transaksi';
    $table_desa        = $wpdb->prefix . 'dw_desa';
    $table_verifikator = $wpdb->prefix . 'dw_verifikator';

    $message = '';
    $message_type = '';

    // --- HANDLE ACTION APPROVE/REJECT ---
    if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'dw_verifikasi_paket' ) ) {
        $id_pembelian = intval( $_GET['id'] );
        $action       = $_GET['action']; // 'approve' or 'reject'
        $status_baru  = ( $action == 'approve' ) ? 'disetujui' : 'ditolak';
        
        // 1. Ambil Data Pembelian & Pedagang Terkait
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
                // Logika: Cek Verifikator dulu, kalau tidak ada baru Cek Desa
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

    // --- VIEW TABLE ---
    // Query Join untuk menampilkan Nama Pedagang, User WP, Nama Desa & Nama Verifikator
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

    <!-- CSS STYLING MODERN -->
    <style>
        :root {
            --dw-primary: #2271b1;
            --dw-primary-hover: #135e96;
            --dw-success: #00a32a;
            --dw-warning: #dba617;
            --dw-danger: #d63638;
            --dw-text-dark: #1d2327;
            --dw-text-gray: #646970;
            --dw-border: #dcdcde;
            --dw-bg-body: #f0f0f1;
        }

        .dw-admin-wrap {
            max-width: 1200px;
            margin: 20px 20px 0 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* Header */
        .dw-header-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .dw-header-action h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: var(--dw-text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Card System */
        .dw-card {
            background: #fff;
            border: 1px solid var(--dw-border);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .dw-card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--dw-bg-body);
            background: #fff;
        }
        .dw-card-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
        .dw-card-body { padding: 0; }

        /* Table */
        .dw-modern-table {
            width: 100%;
            border-collapse: collapse;
            border: none !important;
            box-shadow: none !important;
        }
        .dw-modern-table thead th {
            background: #f8f9fa;
            color: var(--dw-text-gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            padding: 15px 20px;
            border-bottom: 2px solid var(--dw-border);
            text-align: left;
        }
        .dw-modern-table tbody td {
            padding: 15px 20px;
            vertical-align: middle;
            border-bottom: 1px solid var(--dw-bg-body);
            color: var(--dw-text-dark);
            font-size: 14px;
        }
        .dw-modern-table tbody tr:last-child td { border-bottom: none; }
        .dw-modern-table tbody tr:hover { background-color: #fafafa; }

        /* Badges */
        .dw-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
            gap: 5px;
        }
        .dw-badge-primary { background: #eef6fc; color: var(--dw-primary); border: 1px solid #cce5ff; }
        .dw-badge-warning { background: #fff8e6; color: var(--dw-warning); border: 1px solid #ffeeba; }
        .dw-badge-success { background: #e7f6e9; color: var(--dw-success); border: 1px solid #c3e6cb; }
        .dw-badge-danger { background: #fbeaea; color: var(--dw-danger); border: 1px solid #f5c6cb; }
        .dw-badge-gray { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }

        /* Actions */
        .button.dw-btn-approve {
            background: var(--dw-primary);
            color: #fff;
            border: none;
            transition: all 0.2s;
        }
        .button.dw-btn-approve:hover {
            background: var(--dw-primary-hover);
            color: #fff;
            transform: translateY(-1px);
        }
        .button.dw-btn-reject {
            color: var(--dw-danger);
            border-color: var(--dw-danger);
            background: #fff;
        }
        .button.dw-btn-reject:hover {
            background: #fbeaea;
            border-color: var(--dw-danger);
            color: var(--dw-danger);
        }

        /* Info Box */
        .dw-info-box {
            background: #fff;
            border-left: 4px solid var(--dw-primary);
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .dw-info-box h4 { margin-top: 0; color: var(--dw-primary); }
        .dw-info-box ul { margin: 0; padding-left: 20px; list-style-type: disc; color: var(--dw-text-gray); }
        .dw-info-box li { margin-bottom: 5px; }
    </style>

    <div class="wrap dw-admin-wrap">
        
        <!-- HEADER -->
        <div class="dw-header-action">
            <h1>
                <span class="dashicons dashicons-money-alt" style="font-size: 28px; width: 28px; height: 28px;"></span>
                Verifikasi Pembelian Paket
            </h1>
        </div>

        <!-- NOTIFIKASI -->
        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="margin-bottom: 20px;">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <!-- TABEL DATA -->
        <div class="dw-card">
            <div class="dw-card-header">
                <h3>Daftar Tagihan Pending</h3>
            </div>
            <div class="dw-card-body">
                <?php if ( empty( $rows ) ) : ?>
                    <div style="padding: 40px; text-align: center; color: var(--dw-text-gray);">
                        <span class="dashicons dashicons-yes" style="font-size: 48px; width: 48px; height: 48px; color: #c3e6cb; display: block; margin: 0 auto 10px;"></span>
                        <p style="font-size: 16px; margin: 0;">Tidak ada tagihan yang perlu diverifikasi saat ini.</p>
                    </div>
                <?php else : ?>
                    <table class="dw-modern-table">
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
                                $calon_penerima = '<span class="dw-badge dw-badge-gray">Platform (Admin)</span>';
                                
                                if ($item->id_verifikator > 0) {
                                    $calon_penerima = '<div style="display:flex; flex-direction:column; gap:4px;">
                                        <span class="dw-badge dw-badge-warning"><span class="dashicons dashicons-groups"></span> Verifikator</span>
                                        <small style="color:#646970;">' . esc_html($item->nama_verifikator) . '</small>
                                    </div>';
                                } elseif ($item->id_desa > 0) {
                                    $calon_penerima = '<div style="display:flex; flex-direction:column; gap:4px;">
                                        <span class="dw-badge dw-badge-primary"><span class="dashicons dashicons-admin-home"></span> Desa</span>
                                        <small style="color:#646970;">' . esc_html($item->nama_desa) . '</small>
                                    </div>';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo $item->id; ?></strong><br>
                                        <span style="color:var(--dw-text-gray); font-size:12px;"><?php echo date( 'd M Y H:i', strtotime( $item->created_at ) ); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $item->nama_toko ); ?></strong><br>
                                        <div style="font-size:12px; color:var(--dw-text-gray); margin-top:2px;">
                                            <span class="dashicons dashicons-id-alt" style="font-size:14px; vertical-align:middle;"></span> <?php echo esc_html( $item->nama_pemilik ); ?><br>
                                            <span class="dashicons dashicons-email" style="font-size:14px; vertical-align:middle;"></span> <?php echo esc_html($item->user_email); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:600; color:var(--dw-primary);"><?php echo esc_html( $item->nama_paket_snapshot ); ?></span><br>
                                        <span style="font-size:13px;">Rp <?php echo number_format( $item->harga_paket, 0, ',', '.' ); ?></span><br>
                                        <span class="dw-badge dw-badge-success" style="margin-top:4px;">+<?php echo $item->jumlah_transaksi; ?> Kuota</span>
                                    </td>
                                    <td>
                                        <?php if ( $item->url_bukti_bayar ) : ?>
                                            <a href="<?php echo esc_url( $item->url_bukti_bayar ); ?>" target="_blank" class="button button-small" style="display:inline-flex; align-items:center; gap:4px;">
                                                <span class="dashicons dashicons-visibility"></span> Lihat
                                            </a>
                                        <?php else : ?>
                                            <span class="dw-badge dw-badge-danger">Tidak ada file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $calon_penerima; ?></td>
                                    <td style="text-align: right;">
                                        <?php 
                                        $nonce_url_approve = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-paket&action=approve&id=' . $item->id ), 'dw_verifikasi_paket' );
                                        $nonce_url_reject = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-paket&action=reject&id=' . $item->id ), 'dw_verifikasi_paket' );
                                        ?>
                                        <div style="display:flex; justify-content:flex-end; gap:5px;">
                                            <a href="<?php echo $nonce_url_approve; ?>" class="button dw-btn-approve" onclick="return confirm('Yakin setujui pembayaran? Kuota akan bertambah & komisi dicatat.');" title="Setujui">
                                                <span class="dashicons dashicons-yes" style="margin-top:3px;"></span> Terima
                                            </a>
                                            <a href="<?php echo $nonce_url_reject; ?>" class="button dw-btn-reject" onclick="return confirm('Yakin tolak pembayaran ini?');" title="Tolak">
                                                <span class="dashicons dashicons-no" style="margin-top:3px;"></span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- INFO BOX -->
        <div class="dw-info-box">
            <h4><span class="dashicons dashicons-info"></span> Logika Sistem Komisi v3.9</h4>
            <ul>
                <li><strong>Prioritas Verifikator:</strong> Jika Pedagang memiliki <code>ID Verifikator</code> (direkrut via referral), komisi akan masuk ke saldo <strong>Verifikator</strong>.</li>
                <li><strong>Prioritas Desa:</strong> Jika tidak ada Verifikator, namun terikat dengan <code>ID Desa</code>, komisi masuk ke saldo <strong>Desa</strong>.</li>
                <li><strong>Independen:</strong> Jika Pedagang tidak terikat keduanya, seluruh pendapatan masuk ke <strong>Platform (Admin)</strong>.</li>
                <li>Proses pembagian saldo terjadi <strong>otomatis</strong> saat tombol <strong>"Terima"</strong> diklik.</li>
            </ul>
        </div>

    </div>
    <?php
}
?>