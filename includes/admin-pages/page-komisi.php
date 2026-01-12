<?php
/**
 * File Name:   includes/admin-pages/page-komisi.php
 * Description: Dashboard Keuangan & Payout (Desa & Verifikator).
 * UPDATE v4.0: Penyesuaian dengan Database Schema (Bank Detail & Contact Info).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin-ui-components.php';

function dw_komisi_page_render() {
    global $wpdb;
    
    // Setup Nama Tabel
    $t_ledger      = $wpdb->prefix . 'dw_payout_ledger';
    $t_desa        = $wpdb->prefix . 'dw_desa';
    $t_verifikator = $wpdb->prefix . 'dw_verifikator';

    // --- LOGIC: MARK AS PAID (PHP Handler untuk fallback non-AJAX) ---
    if (isset($_POST['dw_action']) && $_POST['dw_action'] == 'mark_paid' && check_admin_referer('dw_payout_action')) {
        $type = sanitize_text_field($_POST['payable_type']);
        $id   = intval($_POST['payable_id']);
        
        // Update Ledger
        $wpdb->query($wpdb->prepare(
            "UPDATE $t_ledger SET status = 'paid', paid_at = %s WHERE payable_to_type = %s AND payable_to_id = %d AND status = 'unpaid'",
            current_time('mysql'), $type, $id
        ));
        
        echo '<?php echo dw_admin_render_alert('Pembayaran berhasil ditandai lunas.', 'success'); ?>';
    }

    // --- AMBIL DATA ---

    // 1. Unpaid Desa (Group by Desa to show total pending)
    // Mengambil info rekening dari tabel dw_desa
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

    // 2. Unpaid Verifikator
    // Mengambil info kontak (WA) dari tabel dw_verifikator karena belum ada kolom bank
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

    // 3. Platform Revenue (Pendapatan Bersih Admin)
    // Asumsi: Jika di ledger ada payable_to_type = 'platform' (opsional, tergantung implementasi insert saat transaksi)
    // Atau sisa dari total transaksi dikurangi payout. Untuk saat ini kita query ledger saja.
    $platform_revenue_paid = $wpdb->get_var("SELECT SUM(amount) FROM $t_ledger WHERE payable_to_type='platform' AND status='paid'") ?: 0;
    $platform_revenue_unpaid = $wpdb->get_var("SELECT SUM(amount) FROM $t_ledger WHERE payable_to_type='platform' AND status='unpaid'") ?: 0; // Masih mengendap
    
    // 4. Riwayat Transfer Terakhir (10 Terakhir)
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

    <div class="wrap">
        <h1 class="wp-heading-inline"><span class="dashicons dashicons-money-alt"></span> Keuangan & Komisi</h1>
        <hr class="wp-header-end">
        
        <!-- DASHBOARD STATS -->
        <div class="dw-stats-grid">
            <!-- Card Desa -->
            <div class="dw-stat-card border-left-red">
                <div class="stat-icon"><span class="dashicons dashicons-admin-home"></span></div>
                <div class="stat-content">
                    <h3>Tagihan Desa</h3>
                    <p class="stat-number">Rp <?php echo number_format(array_sum(array_column($unpaid_desa, 'total_tagihan')), 0, ',', '.'); ?></p>
                    <span class="stat-desc"><?php echo count($unpaid_desa); ?> Desa menunggu transfer</span>
                </div>
            </div>

            <!-- Card Verifikator -->
            <div class="dw-stat-card border-left-orange">
                <div class="stat-icon"><span class="dashicons dashicons-groups"></span></div>
                <div class="stat-content">
                    <h3>Tagihan Verifikator</h3>
                    <p class="stat-number">Rp <?php echo number_format(array_sum(array_column($unpaid_verif, 'total_tagihan')), 0, ',', '.'); ?></p>
                    <span class="stat-desc"><?php echo count($unpaid_verif); ?> Verifikator menunggu transfer</span>
                </div>
            </div>

            <!-- Card Platform -->
            <div class="dw-stat-card border-left-green">
                <div class="stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
                <div class="stat-content">
                    <h3>Pendapatan Platform</h3>
                    <p class="stat-number">Rp <?php echo number_format($platform_revenue_paid + $platform_revenue_unpaid, 0, ',', '.'); ?></p>
                    <span class="stat-desc">Akumulasi bersih (Net)</span>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <nav class="nav-tab-wrapper">
            <a href="?page=dw-komisi&tab=desa" class="nav-tab <?php echo $active_tab == 'desa' ? 'nav-tab-active' : ''; ?>">
                Komisi Desa <?php if(count($unpaid_desa)>0) echo '<span class="dw-count">'.count($unpaid_desa).'</span>'; ?>
            </a>
            <a href="?page=dw-komisi&tab=verifikator" class="nav-tab <?php echo $active_tab == 'verifikator' ? 'nav-tab-active' : ''; ?>">
                Komisi Verifikator <?php if(count($unpaid_verif)>0) echo '<span class="dw-count">'.count($unpaid_verif).'</span>'; ?>
            </a>
            <a href="?page=dw-komisi&tab=riwayat" class="nav-tab <?php echo $active_tab == 'riwayat' ? 'nav-tab-active' : ''; ?>">Riwayat Transfer</a>
        </nav>

        <div class="dw-tab-container">
            
            <!-- TAB 1: DESA -->
            <?php if ($active_tab == 'desa'): ?>
                <div class="dw-table-header">
                    <h3>Tagihan Komisi Desa</h3>
                    <p class="description">Daftar komisi yang harus ditransfer ke Desa Wisata.</p>
                </div>
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
                        <?php if(empty($unpaid_desa)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 20px;">Semua tagihan desa sudah lunas! 🎉</td></tr>
                        <?php else: foreach($unpaid_desa as $row): ?>
                            <tr>
                                <td><strong><?php echo esc_html($row->nama_desa); ?></strong></td>
                                <td>
                                    <?php if($row->nama_bank_desa && $row->no_rekening_desa): ?>
                                        <div class="dw-bank-info">
                                            <strong><?php echo esc_html($row->nama_bank_desa); ?></strong><br>
                                            <?php echo esc_html($row->no_rekening_desa); ?><br>
                                            <em style="font-size:12px;">a.n <?php echo esc_html($row->atas_nama_rekening_desa); ?></em>
                                        </div>
                                    <?php else: ?>
                                        <span class="dw-badge-warning">Belum set rekening</span>
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
                                        <button type="submit" class="button button-primary btn-payout">
                                            <span class="dashicons dashicons-yes"></span> Tandai Lunas
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <!-- TAB 2: VERIFIKATOR -->
            <?php elseif ($active_tab == 'verifikator'): ?>
                <div class="dw-table-header">
                    <h3>Tagihan Komisi Verifikator</h3>
                    <p class="description">Daftar komisi untuk Verifikator UMKM/Pedagang.</p>
                </div>
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
                        <?php if(empty($unpaid_verif)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 20px;">Semua tagihan verifikator aman! 🎉</td></tr>
                        <?php else: foreach($unpaid_verif as $row): ?>
                            <tr>
                                <td><strong><?php echo esc_html($row->nama_lengkap); ?></strong></td>
                                <td>
                                    <?php if($row->nomor_wa): ?>
                                        <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row->nomor_wa)); ?>" target="_blank" class="button button-small">
                                            <span class="dashicons dashicons-whatsapp"></span> <?php echo esc_html($row->nomor_wa); ?>
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
                                        <button type="submit" class="button button-primary btn-payout">
                                            <span class="dashicons dashicons-yes"></span> Tandai Lunas
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <!-- TAB 3: RIWAYAT -->
            <?php elseif ($active_tab == 'riwayat'): ?>
                <div class="dw-table-header">
                    <h3>Riwayat Transfer Terakhir</h3>
                </div>
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
                        <?php if(empty($paid_history)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 20px;">Belum ada riwayat transfer.</td></tr>
                        <?php else: foreach($paid_history as $row): ?>
                            <tr>
                                <td>#<?php echo $row->id; ?></td>
                                <td><strong><?php echo esc_html($row->nama_penerima); ?></strong></td>
                                <td>
                                    <?php if($row->payable_to_type == 'desa'): ?>
                                        <span class="dw-badge dw-badge-blue">Desa</span>
                                    <?php else: ?>
                                        <span class="dw-badge dw-badge-orange">Verifikator</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #10b981; font-weight: 600;">Rp <?php echo number_format($row->amount, 0, ',', '.'); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($row->paid_at)); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    </div>

    <!-- STYLE -->
    
    <?php
}