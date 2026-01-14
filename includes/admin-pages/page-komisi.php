<?php
/**
 * File: includes/admin-pages/page-komisi.php
 * * Admin Page: Laporan Komisi & Payout
 * * Menampilkan data komisi admin, desa, dan verifikator serta fitur payout.
 * * Refaktor UI: Menggunakan 'admin-style.css' (Clean Style).
 */

defined( 'ABSPATH' ) || exit;

// Include UI components
if ( defined( 'DW_CORE_PLUGIN_DIR' ) ) {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-ui-components.php';
} else {
    require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/admin-ui-components.php';
}

function dw_render_komisi_page() {
    global $wpdb;

    // --- 1. Security & Setup ---
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'desa-wisata-core' ) );
    }

    $t_ledger        = $wpdb->prefix . 'dw_payout_ledger';
    $t_desa          = $wpdb->prefix . 'dw_desa';
    $t_verifikator   = $wpdb->prefix . 'dw_verifikator';

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

    // Helper counts for badges
    $count_desa_pending = count($unpaid_desa);
    $count_verif_pending = count($unpaid_verif);
    
    // Calculate totals for stats cards
    $total_tagihan_desa = array_sum(array_column($unpaid_desa, 'total_tagihan'));
    $total_tagihan_verif = array_sum(array_column($unpaid_verif, 'total_tagihan'));
    $total_platform = $platform_revenue_paid + $platform_revenue_unpaid;

    ?>
    <div class="wrap dw-admin-wrapper">
        
        <!-- Header Page -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Laporan pendapatan komisi platform dan manajemen payout ke mitra.</p>
            </div>
            <div class="dw-header-actions">
                <button type="button" class="dw-button dw-button-secondary" onclick="window.print();">
                    <span class="dashicons dashicons-printer" style="margin-right: 8px;"></span> Cetak Laporan
                </button>
            </div>
        </div>

        <div class="dw-content-body">
            <?php settings_errors('dw_komisi'); ?>

            <!-- Statistics Grid -->
            <div class="dw-stats-grid">
                
                <!-- Card Desa -->
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-blue">
                        <span class="dashicons dashicons-admin-home"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value">Rp <?php echo number_format($total_tagihan_desa, 0, ',', '.'); ?></h3>
                        <p class="dw-stat-label">Tagihan Desa (<?php echo $count_desa_pending; ?> Pending)</p>
                    </div>
                </div>

                <!-- Card Verifikator -->
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-orange">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value">Rp <?php echo number_format($total_tagihan_verif, 0, ',', '.'); ?></h3>
                        <p class="dw-stat-label">Tagihan Verifikator (<?php echo $count_verif_pending; ?> Pending)</p>
                    </div>
                </div>

                <!-- Card Platform -->
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-green">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value">Rp <?php echo number_format($total_platform, 0, ',', '.'); ?></h3>
                        <p class="dw-stat-label">Pendapatan Platform (Net)</p>
                    </div>
                </div>

            </div>
            
            <!-- Tabs Navigation -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 24px;">
                <a href="?page=dw-komisi&tab=desa" class="nav-tab <?php echo $active_tab == 'desa' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-home" style="margin-right: 4px;"></span> Komisi Desa 
                    <?php if($count_desa_pending > 0) echo '<span class="dw-badge status-warning" style="margin-left:5px;">'.$count_desa_pending.'</span>'; ?>
                </a>
                <a href="?page=dw-komisi&tab=verifikator" class="nav-tab <?php echo $active_tab == 'verifikator' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-groups" style="margin-right: 4px;"></span> Komisi Verifikator 
                    <?php if($count_verif_pending > 0) echo '<span class="dw-badge status-warning" style="margin-left:5px;">'.$count_verif_pending.'</span>'; ?>
                </a>
                <a href="?page=dw-komisi&tab=riwayat" class="nav-tab <?php echo $active_tab == 'riwayat' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-backup" style="margin-right: 4px;"></span> Riwayat Transfer
                </a>
            </nav>

            <!-- Main Content Card -->
            <div class="dw-card">
                
                <!-- TAB 1: DESA -->
                <?php if ($active_tab == 'desa'): ?>
                    <div class="dw-card-header">
                        <h3>Daftar Tagihan Desa</h3>
                        <span class="dw-badge status-info">Total: <?php echo $count_desa_pending; ?> Desa</span>
                    </div>
                    <div class="dw-card-body">
                        <?php if(empty($unpaid_desa)): ?>
                            <div style="padding: 40px; text-align: center; color: var(--dw-text-grey);">
                                <span class="dashicons dashicons-yes-alt" style="font-size: 48px; width: 48px; height: 48px; color: var(--dw-success); display: block; margin: 0 auto 15px;"></span>
                                <h3 style="margin: 0 0 5px; color: var(--dw-text-dark);">Semua Lunas!</h3>
                                <p style="margin: 0;">Tidak ada tagihan desa yang perlu dibayar saat ini.</p>
                            </div>
                        <?php else: ?>
                            <div class="dw-table-wrapper">
                                <table class="dw-modern-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Desa</th>
                                            <th>Info Rekening (Tujuan)</th>
                                            <th class="text-center">Jml Transaksi</th>
                                            <th class="text-right">Total Tagihan</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($unpaid_desa as $row): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: var(--dw-text-dark);">
                                                    <?php echo esc_html($row->nama_desa); ?>
                                                </td>
                                                <td>
                                                    <?php if($row->nama_bank_desa && $row->no_rekening_desa): ?>
                                                        <div style="line-height: 1.5;">
                                                            <span class="dw-badge status-info" style="margin-bottom: 4px; font-size: 11px;"><?php echo esc_html($row->nama_bank_desa); ?></span><br>
                                                            <span style="font-family: monospace; font-size: 14px; color: var(--dw-text-dark);"><?php echo esc_html($row->no_rekening_desa); ?></span><br>
                                                            <span style="font-size:12px; color: var(--dw-text-grey);">a.n <?php echo esc_html($row->atas_nama_rekening_desa); ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="dw-badge status-warning">Belum set rekening</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="dw-badge status-neutral"><?php echo $row->jumlah_transaksi; ?></span>
                                                </td>
                                                <td class="text-right">
                                                    <span style="color: var(--dw-danger); font-weight: 700;">
                                                        Rp <?php echo number_format($row->total_tagihan, 0, ',', '.'); ?>
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <form method="post" onsubmit="return confirm('Konfirmasi: Anda sudah mentransfer Rp <?php echo number_format($row->total_tagihan); ?> ke <?php echo esc_js($row->nama_desa); ?>?');">
                                                        <?php wp_nonce_field('dw_payout_action'); ?>
                                                        <input type="hidden" name="dw_action" value="mark_paid">
                                                        <input type="hidden" name="payable_type" value="desa">
                                                        <input type="hidden" name="payable_id" value="<?php echo $row->payable_to_id; ?>">
                                                        <button type="submit" class="dw-button dw-button-primary">
                                                            <span class="dashicons dashicons-yes" style="margin-right:6px;"></span> Tandai Lunas
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                <!-- TAB 2: VERIFIKATOR -->
                <?php elseif ($active_tab == 'verifikator'): ?>
                    <div class="dw-card-header">
                        <h3>Daftar Tagihan Verifikator</h3>
                        <span class="dw-badge status-warning">Total: <?php echo $count_verif_pending; ?> Orang</span>
                    </div>
                    <div class="dw-card-body">
                        <?php if(empty($unpaid_verif)): ?>
                            <div style="padding: 40px; text-align: center; color: var(--dw-text-grey);">
                                <span class="dashicons dashicons-yes-alt" style="font-size: 48px; width: 48px; height: 48px; color: var(--dw-success); display: block; margin: 0 auto 15px;"></span>
                                <h3 style="margin: 0 0 5px; color: var(--dw-text-dark);">Semua Aman!</h3>
                                <p style="margin: 0;">Tidak ada tagihan verifikator yang pending.</p>
                            </div>
                        <?php else: ?>
                            <div class="dw-table-wrapper">
                                <table class="dw-modern-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Verifikator</th>
                                            <th>Kontak (WA)</th>
                                            <th class="text-center">Jml Transaksi</th>
                                            <th class="text-right">Total Tagihan</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($unpaid_verif as $row): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: var(--dw-text-dark);">
                                                    <?php echo esc_html($row->nama_lengkap); ?>
                                                </td>
                                                <td>
                                                    <?php if($row->nomor_wa): ?>
                                                        <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row->nomor_wa)); ?>" target="_blank" class="dw-button dw-button-secondary" style="padding: 6px 12px; font-size: 12px;">
                                                            <span class="dashicons dashicons-whatsapp" style="margin-right:6px; color: var(--dw-success);"></span> <?php echo esc_html($row->nomor_wa); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="dw-badge status-neutral">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="dw-badge status-neutral"><?php echo $row->jumlah_transaksi; ?></span>
                                                </td>
                                                <td class="text-right">
                                                    <span style="color: var(--dw-warning); font-weight: 700;">
                                                        Rp <?php echo number_format($row->total_tagihan, 0, ',', '.'); ?>
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <form method="post" onsubmit="return confirm('Konfirmasi: Anda sudah mentransfer Rp <?php echo number_format($row->total_tagihan); ?> ke <?php echo esc_js($row->nama_lengkap); ?>?');">
                                                        <?php wp_nonce_field('dw_payout_action'); ?>
                                                        <input type="hidden" name="dw_action" value="mark_paid">
                                                        <input type="hidden" name="payable_type" value="verifikator">
                                                        <input type="hidden" name="payable_id" value="<?php echo $row->payable_to_id; ?>">
                                                        <button type="submit" class="dw-button dw-button-primary">
                                                            <span class="dashicons dashicons-yes" style="margin-right:6px;"></span> Tandai Lunas
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                <!-- TAB 3: RIWAYAT -->
                <?php elseif ($active_tab == 'riwayat'): ?>
                    <div class="dw-card-header">
                        <h3>Riwayat Transfer Terakhir</h3>
                    </div>
                    <div class="dw-card-body">
                        <?php if(empty($paid_history)): ?>
                            <div style="padding: 40px; text-align: center; color: var(--dw-text-grey);">
                                <p style="margin: 0;">Belum ada riwayat transfer yang tercatat.</p>
                            </div>
                        <?php else: ?>
                            <div class="dw-table-wrapper">
                                <table class="dw-modern-table">
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
                                                <td style="color: var(--dw-text-grey); font-family: monospace;">#<?php echo $row->id; ?></td>
                                                <td><strong><?php echo esc_html($row->nama_penerima); ?></strong></td>
                                                <td>
                                                    <?php if($row->payable_to_type == 'desa'): ?>
                                                        <span class="dw-badge status-info">Desa</span>
                                                    <?php else: ?>
                                                        <span class="dw-badge status-warning">Verifikator</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="color: var(--dw-success); font-weight: 700;">
                                                    Rp <?php echo number_format($row->amount, 0, ',', '.'); ?>
                                                </td>
                                                <td style="color: var(--dw-text-grey);">
                                                    <?php echo date('d M Y H:i', strtotime($row->paid_at)); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php
}