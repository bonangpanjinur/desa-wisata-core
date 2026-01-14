<?php
/**
 * File: includes/admin-pages/page-komisi.php
 * * Admin Page: Laporan Komisi & Payout
 * * Menampilkan data komisi admin, desa, dan verifikator serta fitur payout.
 */

defined( 'ABSPATH' ) || exit;

function dw_render_komisi_page() {
    global $wpdb;

    // --- 1. Security & Setup ---
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'desa-wisata-core' ) );
    }

    $t_ledger      = $wpdb->prefix . 'dw_payout_ledger';
    $t_desa        = $wpdb->prefix . 'dw_desa';
    $t_verifikator = $wpdb->prefix . 'dw_verifikator';

    // --- 2. Handle Action: Mark as Paid ---
    if ( isset($_POST['dw_action']) && $_POST['dw_action'] == 'mark_paid' && check_admin_referer('dw_payout_action') ) {
        $type = sanitize_text_field($_POST['payable_type']);
        $id   = intval($_POST['payable_id']);
        
        // Update Ledger
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $t_ledger SET status = 'paid', paid_at = %s WHERE payable_to_type = %s AND payable_to_id = %d AND status = 'unpaid'",
            current_time('mysql'), $type, $id
        ));
        
        if ($updated !== false) {
            add_settings_error( 'dw_komisi', 'payout_success', 'Pembayaran berhasil ditandai lunas.', 'success' );
        } else {
            add_settings_error( 'dw_komisi', 'payout_failed', 'Gagal memperbarui status pembayaran.', 'error' );
        }
    }

    // --- 3. Data Queries ---

    // A. Unpaid Desa (Group by Desa)
    $unpaid_desa = $wpdb->get_results("
        SELECT 
            l.payable_to_id, 
            d.nama_desa, 
            d.nama_bank_desa, 
            d.no_rekening_desa, 
            d.atas_nama_rekening_desa,
            SUM(l.amount) as total_tagihan, 
            COUNT(l.id) as jumlah_transaksi 
        FROM $t_ledger l 
        JOIN $t_desa d ON l.payable_to_id = d.id 
        WHERE l.status = 'unpaid' AND l.payable_to_type = 'desa' 
        GROUP BY l.payable_to_id
    ");

    // B. Unpaid Verifikator
    $unpaid_verif = $wpdb->get_results("
        SELECT 
            l.payable_to_id, 
            v.nama_lengkap, 
            v.nomor_wa,
            SUM(l.amount) as total_tagihan, 
            COUNT(l.id) as jumlah_transaksi 
        FROM $t_ledger l 
        JOIN $t_verifikator v ON l.payable_to_id = v.id 
        WHERE l.status = 'unpaid' AND l.payable_to_type = 'verifikator' 
        GROUP BY l.payable_to_id
    ");

    // C. Platform Revenue
    $platform_revenue_paid = $wpdb->get_var("SELECT SUM(amount) FROM $t_ledger WHERE payable_to_type='platform' AND status='paid'") ?: 0;
    $platform_revenue_unpaid = $wpdb->get_var("SELECT SUM(amount) FROM $t_ledger WHERE payable_to_type='platform' AND status='unpaid'") ?: 0; 
    
    // D. History Transfer (10 Terakhir)
    $paid_history = $wpdb->get_results("
        SELECT l.*, 
        CASE 
            WHEN l.payable_to_type = 'desa' THEN (SELECT nama_desa FROM $t_desa WHERE id = l.payable_to_id)
            WHEN l.payable_to_type = 'verifikator' THEN (SELECT nama_lengkap FROM $t_verifikator WHERE id = l.payable_to_id)
            ELSE 'Platform'
        END as nama_penerima
        FROM $t_ledger l 
        WHERE l.status = 'paid' AND l.payable_to_type IN ('desa', 'verifikator')
        ORDER BY l.paid_at DESC 
        LIMIT 10
    ");

    // Tab Handling
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'desa';

    ?>
    <div class="wrap dw-admin-wrapper">
        <!-- Header -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Laporan pendapatan komisi platform dan manajemen payout ke mitra.</p>
            </div>
            <div class="dw-header-actions">
                <button type="button" class="button button-secondary" onclick="window.print();">
                    <span class="dashicons dashicons-printer" style="margin-top: 3px;"></span> Cetak Laporan
                </button>
            </div>
        </div>

        <div class="dw-content-body">
            <?php settings_errors('dw_komisi'); ?>

            <!-- Statistics Grid (3 Columns) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px;">
                <!-- Card Desa -->
                <div class="dw-card" style="border-left: 4px solid #2271b1; margin-bottom: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 5px; color: #646970; font-size: 13px; text-transform: uppercase;">Tagihan Desa</h3>
                            <p style="font-size: 24px; font-weight: bold; margin: 0; color: #1d2327;">
                                Rp <?php echo number_format(array_sum(array_column($unpaid_desa, 'total_tagihan')), 0, ',', '.'); ?>
                            </p>
                            <p class="description" style="margin: 5px 0 0;"><?php echo count($unpaid_desa); ?> Desa menunggu transfer</p>
                        </div>
                        <span class="dashicons dashicons-admin-home" style="font-size: 40px; width: 40px; height: 40px; color: #dcdcde;"></span>
                    </div>
                </div>

                <!-- Card Verifikator -->
                <div class="dw-card" style="border-left: 4px solid #dba617; margin-bottom: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 5px; color: #646970; font-size: 13px; text-transform: uppercase;">Tagihan Verifikator</h3>
                            <p style="font-size: 24px; font-weight: bold; margin: 0; color: #1d2327;">
                                Rp <?php echo number_format(array_sum(array_column($unpaid_verif, 'total_tagihan')), 0, ',', '.'); ?>
                            </p>
                            <p class="description" style="margin: 5px 0 0;"><?php echo count($unpaid_verif); ?> Verifikator menunggu transfer</p>
                        </div>
                        <span class="dashicons dashicons-groups" style="font-size: 40px; width: 40px; height: 40px; color: #dcdcde;"></span>
                    </div>
                </div>

                <!-- Card Platform -->
                <div class="dw-card" style="border-left: 4px solid #00a32a; margin-bottom: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 5px; color: #646970; font-size: 13px; text-transform: uppercase;">Pendapatan Platform</h3>
                            <p style="font-size: 24px; font-weight: bold; margin: 0; color: #1d2327;">
                                Rp <?php echo number_format($platform_revenue_paid + $platform_revenue_unpaid, 0, ',', '.'); ?>
                            </p>
                            <p class="description" style="margin: 5px 0 0;">Akumulasi Net (Bersih)</p>
                        </div>
                        <span class="dashicons dashicons-chart-line" style="font-size: 40px; width: 40px; height: 40px; color: #dcdcde;"></span>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 0; border-bottom: none;">
                <a href="?page=dw-komisi&tab=desa" class="nav-tab <?php echo $active_tab == 'desa' ? 'nav-tab-active' : ''; ?>" style="background: <?php echo $active_tab == 'desa' ? '#fff' : '#f0f0f1'; ?>; border-bottom: 1px solid <?php echo $active_tab == 'desa' ? '#fff' : '#c3c4c7'; ?>;">
                    Komisi Desa <?php if(count($unpaid_desa)>0) echo '<span class="update-plugins count-<?php echo count($unpaid_desa); ?>"><span class="plugin-count">'.count($unpaid_desa).'</span></span>'; ?>
                </a>
                <a href="?page=dw-komisi&tab=verifikator" class="nav-tab <?php echo $active_tab == 'verifikator' ? 'nav-tab-active' : ''; ?>" style="background: <?php echo $active_tab == 'verifikator' ? '#fff' : '#f0f0f1'; ?>; border-bottom: 1px solid <?php echo $active_tab == 'verifikator' ? '#fff' : '#c3c4c7'; ?>;">
                    Komisi Verifikator <?php if(count($unpaid_verif)>0) echo '<span class="update-plugins count-<?php echo count($unpaid_verif); ?>"><span class="plugin-count">'.count($unpaid_verif).'</span></span>'; ?>
                </a>
                <a href="?page=dw-komisi&tab=riwayat" class="nav-tab <?php echo $active_tab == 'riwayat' ? 'nav-tab-active' : ''; ?>" style="background: <?php echo $active_tab == 'riwayat' ? '#fff' : '#f0f0f1'; ?>; border-bottom: 1px solid <?php echo $active_tab == 'riwayat' ? '#fff' : '#c3c4c7'; ?>;">
                    Riwayat Transfer
                </a>
            </nav>

            <!-- Main Content Card -->
            <div class="dw-card" style="margin-top: -1px; border-top-left-radius: 0;">
                
                <!-- TAB 1: DESA -->
                <?php if ($active_tab == 'desa'): ?>
                    <div style="padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; margin-bottom: 15px;">
                        <h3 style="margin: 0;">Tagihan Komisi Desa</h3>
                        <p class="description" style="margin: 5px 0 0;">Daftar komisi yang harus ditransfer ke Desa Wisata.</p>
                    </div>
                    
                    <?php if(empty($unpaid_desa)): ?>
                        <div style="padding: 40px; text-align: center; color: #646970;">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 48px; width: 48px; height: 48px; color: #c3e6cb; display: block; margin: 0 auto 10px;"></span>
                            <p>Semua tagihan desa sudah lunas! 🎉</p>
                        </div>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Nama Desa</th>
                                    <th>Info Rekening (Tujuan Transfer)</th>
                                    <th style="text-align:center;">Jml Transaksi</th>
                                    <th style="text-align:right;">Total Tagihan</th>
                                    <th style="text-align:right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($unpaid_desa as $row): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($row->nama_desa); ?></strong></td>
                                        <td>
                                            <?php if($row->nama_bank_desa && $row->no_rekening_desa): ?>
                                                <div style="line-height: 1.4;">
                                                    <strong><?php echo esc_html($row->nama_bank_desa); ?></strong><br>
                                                    <?php echo esc_html($row->no_rekening_desa); ?><br>
                                                    <span style="font-size:12px; color:#646970;">a.n <?php echo esc_html($row->atas_nama_rekening_desa); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span style="background:#f6e05e; color:#744210; padding:2px 6px; border-radius:4px; font-size:11px;">Belum set rekening</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;"><?php echo $row->jumlah_transaksi; ?></td>
                                        <td style="text-align:right; font-weight:bold; color:#d63638;">
                                            Rp <?php echo number_format($row->total_tagihan, 0, ',', '.'); ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <form method="post" onsubmit="return confirm('Konfirmasi: Anda sudah mentransfer Rp <?php echo number_format($row->total_tagihan); ?> ke <?php echo esc_js($row->nama_desa); ?>?');">
                                                <?php wp_nonce_field('dw_payout_action'); ?>
                                                <input type="hidden" name="dw_action" value="mark_paid">
                                                <input type="hidden" name="payable_type" value="desa">
                                                <input type="hidden" name="payable_id" value="<?php echo $row->payable_to_id; ?>">
                                                <button type="submit" class="button button-primary">
                                                    <span class="dashicons dashicons-yes" style="margin-top:3px;"></span> Tandai Lunas
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <!-- TAB 2: VERIFIKATOR -->
                <?php elseif ($active_tab == 'verifikator'): ?>
                    <div style="padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; margin-bottom: 15px;">
                        <h3 style="margin: 0;">Tagihan Komisi Verifikator</h3>
                        <p class="description" style="margin: 5px 0 0;">Daftar komisi untuk Verifikator UMKM/Pedagang.</p>
                    </div>

                    <?php if(empty($unpaid_verif)): ?>
                        <div style="padding: 40px; text-align: center; color: #646970;">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 48px; width: 48px; height: 48px; color: #c3e6cb; display: block; margin: 0 auto 10px;"></span>
                            <p>Semua tagihan verifikator aman! 🎉</p>
                        </div>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Nama Verifikator</th>
                                    <th>Kontak (WA)</th>
                                    <th style="text-align:center;">Jml Transaksi</th>
                                    <th style="text-align:right;">Total Tagihan</th>
                                    <th style="text-align:right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($unpaid_verif as $row): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($row->nama_lengkap); ?></strong></td>
                                        <td>
                                            <?php if($row->nomor_wa): ?>
                                                <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row->nomor_wa)); ?>" target="_blank" class="button button-small">
                                                    <span class="dashicons dashicons-whatsapp" style="margin-top:3px;"></span> <?php echo esc_html($row->nomor_wa); ?>
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;"><?php echo $row->jumlah_transaksi; ?></td>
                                        <td style="text-align:right; font-weight:bold; color:#dba617;">
                                            Rp <?php echo number_format($row->total_tagihan, 0, ',', '.'); ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <form method="post" onsubmit="return confirm('Konfirmasi: Anda sudah mentransfer Rp <?php echo number_format($row->total_tagihan); ?> ke <?php echo esc_js($row->nama_lengkap); ?>?');">
                                                <?php wp_nonce_field('dw_payout_action'); ?>
                                                <input type="hidden" name="dw_action" value="mark_paid">
                                                <input type="hidden" name="payable_type" value="verifikator">
                                                <input type="hidden" name="payable_id" value="<?php echo $row->payable_to_id; ?>">
                                                <button type="submit" class="button button-primary">
                                                    <span class="dashicons dashicons-yes" style="margin-top:3px;"></span> Tandai Lunas
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <!-- TAB 3: RIWAYAT -->
                <?php elseif ($active_tab == 'riwayat'): ?>
                    <div style="padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; margin-bottom: 15px;">
                        <h3 style="margin: 0;">Riwayat Transfer Terakhir</h3>
                    </div>

                    <?php if(empty($paid_history)): ?>
                        <div style="padding: 40px; text-align: center; color: #646970;">
                            <p>Belum ada riwayat transfer.</p>
                        </div>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>ID Trx</th>
                                    <th>Penerima</th>
                                    <th>Tipe</th>
                                    <th>Jumlah Transfer</th>
                                    <th>Tanggal Transfer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($paid_history as $row): ?>
                                    <tr>
                                        <td>#<?php echo $row->id; ?></td>
                                        <td><strong><?php echo esc_html($row->nama_penerima); ?></strong></td>
                                        <td>
                                            <?php if($row->payable_to_type == 'desa'): ?>
                                                <span style="background:#e6fffa; color:#234e52; padding:2px 8px; border-radius:4px; font-size:11px;">Desa</span>
                                            <?php else: ?>
                                                <span style="background:#fffaf0; color:#9c4221; padding:2px 8px; border-radius:4px; font-size:11px;">Verifikator</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #10b981; font-weight: 600;">Rp <?php echo number_format($row->amount, 0, ',', '.'); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($row->paid_at)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php
}