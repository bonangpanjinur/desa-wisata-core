<?php
/**
 * File Name: page-verifikasi-paket.php
 * Description: Verifikasi Pembelian Paket (UI Card Grid).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_verifikasi_paket_page_render() {
    global $wpdb;
    
    // Ambil Transaksi Pending
    $pending = $wpdb->get_results("SELECT pp.*, p.nama_toko, p.nama_pemilik FROM {$wpdb->prefix}dw_pembelian_paket pp JOIN {$wpdb->prefix}dw_pedagang p ON pp.id_pedagang=p.id WHERE pp.status='pending' ORDER BY pp.created_at ASC");
    
    // Ambil Riwayat (History)
    $history = $wpdb->get_results("SELECT pp.*, p.nama_toko FROM {$wpdb->prefix}dw_pembelian_paket pp JOIN {$wpdb->prefix}dw_pedagang p ON pp.id_pedagang=p.id WHERE pp.status!='pending' ORDER BY pp.created_at DESC LIMIT 5");
    
    // Pesan Notifikasi WP
    settings_errors('dw_verif_msg'); 
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Verifikasi Topup Paket</h1>
        <hr class="wp-header-end">
        
        <style>
            .dw-verif-container { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 20px; }
            .dw-request-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; gap: 20px; }
            .dw-proof-thumb { width: 100px; height: 100px; border-radius: 6px; object-fit: cover; border: 1px solid #eee; flex-shrink: 0; cursor: pointer; }
            .dw-req-details { flex-grow: 1; }
            .dw-req-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
            .dw-shop-name { font-size: 16px; font-weight: 700; color: #1e293b; }
            .dw-req-meta { font-size: 13px; color: #64748b; margin-bottom: 5px; }
            .dw-pkg-badge { background: #eff6ff; color: #1d4ed8; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 10px; }
            
            .dw-actions { display: flex; gap: 10px; margin-top: 15px; }
            /* Tambahkan style spinner */
            .dw-spinner { display:none; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; margin-left: 10px; vertical-align: middle; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            
            .btn-approve { background: #16a34a; color: #fff; border: none; padding: 8px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.2s; }
            .btn-approve:hover { background: #15803d; }
            .btn-approve:disabled { background: #ccc; cursor: not-allowed; }
            
            .btn-reject { background: #fff; color: #dc2626; border: 1px solid #dc2626; padding: 8px 15px; border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.2s; }
            .btn-reject:hover { background: #fef2f2; }
            .btn-reject:disabled { border-color: #ccc; color: #ccc; cursor: not-allowed; }
            
            .dw-history-list { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; overflow: hidden; }
            .dw-hist-item { padding: 15px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
            .dw-hist-status { font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; }
            .st-approved { background: #dcfce7; color: #166534; }
            .st-rejected { background: #fee2e2; color: #991b1b; }
        </style>

        <div class="dw-verif-container">
            <!-- Kolom Kiri: Pending Requests -->
            <div class="dw-pending-col">
                <h3 style="margin-top: 0; margin-bottom: 20px;">Permintaan Masuk (<?php echo count($pending); ?>)</h3>
                
                <?php if(empty($pending)): ?>
                    <div class="dw-request-card" style="justify-content: center; color: #94a3b8; padding: 40px;">
                        Tidak ada permintaan verifikasi baru.
                    </div>
                <?php else: foreach($pending as $req): ?>
                    <div class="dw-request-card" id="card-<?php echo $req->id; ?>">
                        <?php if(!empty($req->url_bukti_bayar)): ?>
                        <a href="<?php echo esc_url($req->url_bukti_bayar); ?>" target="_blank">
                            <img src="<?php echo esc_url($req->url_bukti_bayar); ?>" class="dw-proof-thumb" title="Klik untuk perbesar">
                        </a>
                        <?php else: ?>
                            <div class="dw-proof-thumb" style="display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#94a3b8;">No Image</div>
                        <?php endif; ?>

                        <div class="dw-req-details">
                            <div class="dw-req-header">
                                <div class="dw-shop-name"><?php echo esc_html($req->nama_toko); ?> <span style="font-weight:400; font-size:13px; color:#64748b;">(<?php echo esc_html($req->nama_pemilik); ?>)</span></div>
                                <div style="font-size:12px; color:#64748b;"><?php echo date('d M H:i', strtotime($req->created_at)); ?></div>
                            </div>
                            
                            <div class="dw-pkg-badge">
                                <?php echo esc_html($req->nama_paket_snapshot); ?> • Rp <?php echo number_format($req->harga_paket,0,',','.'); ?>
                            </div>
                            <div class="dw-req-meta">Kuota Tambahan: <strong>+<?php echo $req->jumlah_transaksi; ?> Transaksi</strong></div>
                            
                            <!-- ACTIONS BUTTONS -->
                            <div class="dw-actions">
                                <button type="button" 
                                    class="btn-approve dw-verify-paket-btn" 
                                    data-id="<?php echo $req->id; ?>" 
                                    data-type="approve">
                                    <span class="dashicons dashicons-yes" style="vertical-align:middle;"></span> Terima & Aktifkan
                                </button>
                                
                                <button type="button" 
                                    class="btn-reject dw-verify-paket-btn" 
                                    data-id="<?php echo $req->id; ?>" 
                                    data-type="reject">
                                    Tolak
                                </button>
                                <div class="dw-spinner" id="spinner-<?php echo $req->id; ?>"></div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Kolom Kanan: History -->
            <div class="dw-history-col">
                <h3 style="margin-top: 0; margin-bottom: 20px;">Riwayat Terakhir</h3>
                <div class="dw-history-list">
                    <?php if(empty($history)): ?>
                        <div class="dw-hist-item" style="color:#94a3b8; justify-content:center;">Kosong</div>
                    <?php else: foreach($history as $hist): 
                        $st_class = ($hist->status == 'disetujui') ? 'st-approved' : 'st-rejected';
                        ?>
                        <div class="dw-hist-item">
                            <div>
                                <div style="font-weight:600; font-size:13px;"><?php echo esc_html($hist->nama_toko); ?></div>
                                <div style="font-size:11px; color:#64748b;"><?php echo esc_html($hist->nama_paket_snapshot); ?></div>
                            </div>
                            <div style="text-align:right;">
                                <span class="dw-hist-status <?php echo $st_class; ?>"><?php echo ucfirst($hist->status); ?></span>
                                <div style="font-size:10px; color:#94a3b8; margin-top:3px;"><?php echo date('d/m', strtotime($hist->created_at)); ?></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>