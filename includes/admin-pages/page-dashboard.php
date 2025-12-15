<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * Description: Dashboard Admin Premium dengan Desain UI Modern (SaaS Style).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;

    // --- 1. DEFINISI TABEL & VALIDASI ---
    $table_desa = $wpdb->prefix . 'dw_desa';
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_produk = $wpdb->prefix . 'dw_produk';
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';
    $table_sub_transaksi = $wpdb->prefix . 'dw_transaksi_sub';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_transaksi'") != $table_transaksi) {
        echo '<div class="notice notice-error"><p>Tabel database belum lengkap. Silakan jalankan migrasi/aktivasi ulang.</p></div>';
        return;
    }

    // --- 2. TENTUKAN KONTEKS ROLE ---
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $is_admin_desa  = current_user_can('admin_desa');
    $managed_desa_id = 0;
    $desa_name = '';

    if ($is_admin_desa && !$is_super_admin) {
        $managed_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE id_user_desa = %d", $user_id));
        $desa_name = $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM $table_desa WHERE id = %d", $managed_desa_id));
        
        if (!$managed_desa_id) {
            echo '<div class="wrap dw-wrap"><div class="notice notice-warning"><p>Akun Anda belum terhubung dengan Desa manapun.</p></div></div>';
            return;
        }
    }

    // --- 3. HITUNG STATISTIK ---
    // A. Statistik Dasar
    $count_desa     = $is_super_admin ? (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_desa WHERE status = 'aktif'") : 1;
    
    $sql_wisata     = "SELECT COUNT(id) FROM $table_wisata WHERE status = 'aktif'";
    if ($managed_desa_id) $sql_wisata .= " AND id_desa = $managed_desa_id";
    $count_wisata   = (int) $wpdb->get_var($sql_wisata);

    $sql_pedagang   = "SELECT COUNT(id) FROM $table_pedagang WHERE status_akun = 'aktif'";
    if ($managed_desa_id) $sql_pedagang .= " AND id_desa = $managed_desa_id";
    $count_pedagang = (int) $wpdb->get_var($sql_pedagang);

    $sql_produk     = "SELECT COUNT(p.id) FROM $table_produk p JOIN $table_pedagang d ON p.id_pedagang = d.id WHERE p.status = 'aktif' AND d.status_akun = 'aktif'";
    if ($managed_desa_id) $sql_produk .= " AND d.id_desa = $managed_desa_id";
    $count_produk   = (int) $wpdb->get_var($sql_produk);

    // B. Omset
    $omset = 0;
    if ($is_super_admin) {
        $omset = (float) $wpdb->get_var("SELECT SUM(total_bayar) FROM $table_transaksi WHERE status_pembayaran = 'paid'");
    } else {
        $sql_omset_desa = $wpdb->prepare("
            SELECT SUM(sub.total_pesanan_toko) FROM $table_sub_transaksi sub
            JOIN $table_pedagang p ON sub.id_pedagang = p.id
            WHERE p.id_desa = %d AND sub.status_pesanan IN ('selesai', 'lunas', 'dikirim', 'diproses')", 
            $managed_desa_id
        );
        $omset = (float) $wpdb->get_var($sql_omset_desa);
    }

    // --- 4. DATA GRAFIK (7 HARI TERAKHIR) ---
    $chart_data = [];
    $max_value = 0;
    for ($i = 6; $i >= 0; $i--) {
        $date_key = date('Y-m-d', strtotime("-$i days"));
        $chart_data[$date_key] = [
            'label' => date('d/m', strtotime($date_key)), 
            'full_label' => date_i18n('j F Y', strtotime($date_key)),
            'value' => 0
        ];
    }

    if ($is_super_admin) {
        $results = $wpdb->get_results("
            SELECT DATE(tanggal_transaksi) as tgl, SUM(total_bayar) as total 
            FROM $table_transaksi 
            WHERE status_pembayaran = 'paid' AND tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(tanggal_transaksi)
        ");
    } else {
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(sub.created_at) as tgl, SUM(sub.total_pesanan_toko) as total
            FROM $table_sub_transaksi sub
            JOIN $table_pedagang p ON sub.id_pedagang = p.id
            WHERE p.id_desa = %d AND sub.status_pesanan IN ('selesai', 'lunas', 'dikirim', 'diproses')
            AND sub.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(sub.created_at)
        ", $managed_desa_id));
    }

    if ($results) {
        foreach ($results as $row) {
            if (isset($chart_data[$row->tgl])) {
                $chart_data[$row->tgl]['value'] = (float) $row->total;
                if ($row->total > $max_value) $max_value = (float) $row->total;
            }
        }
    }
    if ($max_value == 0) $max_value = 1;

    // --- 5. TRANSAKSI TERAKHIR (DATA TABLE) ---
    $recent_orders = [];
    if ($is_super_admin) {
        $recent_orders = $wpdb->get_results("
            SELECT t.*, u.display_name as pembeli 
            FROM $table_transaksi t
            LEFT JOIN {$wpdb->users} u ON t.id_pembeli = u.ID
            ORDER BY t.created_at DESC LIMIT 5
        ");
    } else {
        $recent_orders = $wpdb->get_results($wpdb->prepare("
            SELECT sub.id, sub.status_pesanan as status_transaksi, sub.total_pesanan_toko as total_bayar, 
                   sub.created_at, p.nama_toko, main.kode_unik, main.id_pembeli
            FROM $table_sub_transaksi sub
            JOIN $table_pedagang p ON sub.id_pedagang = p.id
            JOIN $table_transaksi main ON sub.id_transaksi = main.id
            WHERE p.id_desa = %d
            ORDER BY sub.created_at DESC LIMIT 5
        ", $managed_desa_id));
    }

    // --- 6. RENDER VIEW DENGAN CSS MODERN ---
    ?>
    
    <!-- CSS INLINE AGAR LANGSUNG CANTIK -->
    <style>
        .dw-dashboard-container {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto 0 0;
        }
        
        /* HEADER */
        .dw-welcome-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border-radius: 12px;
            padding: 40px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dw-welcome-bg-icon {
            position: absolute;
            right: -20px;
            top: -20px;
            font-size: 200px;
            color: rgba(255,255,255,0.05);
            transform: rotate(-10deg);
            pointer-events: none;
        }
        .dw-welcome-text h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
            color: white;
        }
        .dw-welcome-text p {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .dw-welcome-actions .button {
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .dw-btn-white { background: white; color: #2563eb; }
        .dw-btn-white:hover { background: #f1f5f9; transform: translateY(-2px); }

        /* GRID STATS */
        .dw-grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .dw-stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .dw-stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        .dw-stat-icon {
            width: 50px; height: 50px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 15px;
        }
        .icon-blue   { background: #eff6ff; color: #3b82f6; }
        .icon-green  { background: #f0fdf4; color: #22c55e; }
        .icon-purple { background: #faf5ff; color: #a855f7; }
        .icon-orange { background: #fff7ed; color: #f97316; }
        
        .dw-stat-number { font-size: 28px; font-weight: 800; color: #1e293b; line-height: 1.2; }
        .dw-stat-label { font-size: 14px; color: #64748b; font-weight: 500; }

        /* MAIN CONTENT SPLIT */
        .dw-content-split { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        @media (max-width: 1024px) { .dw-content-split { grid-template-columns: 1fr; } }

        .dw-card-panel {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .dw-panel-head {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex; justify-content: space-between; align-items: center;
            background: #f8fafc;
        }
        .dw-panel-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #334155; }
        .dw-panel-body { padding: 25px; }

        /* CHART */
        .dw-chart-wrap { display: flex; align-items: flex-end; height: 200px; gap: 15px; padding-top: 20px; }
        .dw-chart-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; position: relative; cursor: pointer; }
        .dw-chart-bar { width: 100%; border-radius: 6px 6px 0 0; background: #e2e8f0; transition: height 0.5s ease, background 0.3s; min-height: 4px; }
        .dw-chart-bar:hover { background: #3b82f6; }
        .dw-chart-label { margin-top: 10px; font-size: 11px; color: #64748b; font-weight: 600; }
        .dw-chart-tooltip {
            position: absolute; bottom: 100%; margin-bottom: 8px;
            background: #1e293b; color: white; padding: 5px 10px;
            border-radius: 6px; font-size: 11px; opacity: 0;
            transition: all 0.2s; pointer-events: none; white-space: nowrap; transform: translateY(5px);
        }
        .dw-chart-bar-col:hover .dw-chart-tooltip { opacity: 1; transform: translateY(0); }

        /* TABLE TRANSAKSI */
        .dw-table-clean { width: 100%; border-collapse: collapse; }
        .dw-table-clean th { text-align: left; padding: 12px 15px; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .dw-table-clean td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; }
        .dw-table-clean tr:last-child td { border-bottom: none; }
        
        .dw-status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-success { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-failed  { background: #fee2e2; color: #991b1b; }
    </style>

    <div class="wrap dw-dashboard-container">
        
        <!-- HERO SECTION -->
        <div class="dw-welcome-header">
            <span class="dashicons dashicons-store dw-welcome-bg-icon"></span>
            <div class="dw-welcome-text">
                <h2>Halo, <?php echo esc_html($current_user->display_name); ?>! 👋</h2>
                <p>
                    <?php if ($is_super_admin): ?>
                        Berikut ringkasan performa seluruh ekosistem Desa Wisata hari ini.
                    <?php else: ?>
                        Kelola potensi Desa <strong><?php echo esc_html($desa_name); ?></strong> dengan lebih mudah.
                    <?php endif; ?>
                </p>
            </div>
            <div class="dw-welcome-actions">
                <?php if ($is_admin_desa): ?>
                    <a href="?page=dw-desa-verifikasi" class="button dw-btn-white">Verifikasi Pedagang</a>
                <?php else: ?>
                    <a href="?page=dw-desa&action=new" class="button dw-btn-white">Tambah Desa</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="dw-grid-stats">
            <div class="dw-stat-card">
                <div class="dw-stat-icon icon-blue"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="dw-stat-number">Rp <?php echo number_format($omset, 0, ',', '.'); ?></div>
                <div class="dw-stat-label">Total Omset</div>
            </div>
            <div class="dw-stat-card">
                <div class="dw-stat-icon icon-green"><span class="dashicons dashicons-store"></span></div>
                <div class="dw-stat-number"><?php echo number_format($count_pedagang); ?></div>
                <div class="dw-stat-label">Toko Aktif</div>
            </div>
            <div class="dw-stat-card">
                <div class="dw-stat-icon icon-purple"><span class="dashicons dashicons-palmtree"></span></div>
                <div class="dw-stat-number"><?php echo number_format($count_wisata); ?></div>
                <div class="dw-stat-label">Objek Wisata</div>
            </div>
            <div class="dw-stat-card">
                <div class="dw-stat-icon icon-orange"><span class="dashicons dashicons-cart"></span></div>
                <div class="dw-stat-number"><?php echo number_format($count_produk); ?></div>
                <div class="dw-stat-label">Produk Terjual</div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="dw-content-split">
            
            <!-- LEFT: CHART -->
            <div class="dw-card-panel">
                <div class="dw-panel-head">
                    <h3>📈 Tren Pendapatan (7 Hari)</h3>
                </div>
                <div class="dw-panel-body">
                    <?php if ($max_value > 1): ?>
                        <div class="dw-chart-wrap">
                            <?php foreach ($chart_data as $data): 
                                $height = ($data['value'] / $max_value) * 100;
                                $bg = ($data['value'] > 0) ? '#3b82f6' : '#e2e8f0';
                            ?>
                                <div class="dw-chart-bar-col">
                                    <div class="dw-chart-tooltip"><?php echo $data['full_label']; ?>: Rp <?php echo number_format($data['value']); ?></div>
                                    <div class="dw-chart-bar" style="height: <?php echo max(4, $height); ?>%; background: <?php echo $bg; ?>;"></div>
                                    <div class="dw-chart-label"><?php echo $data['label']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center; color:#94a3b8; padding: 40px 0;">Belum ada data transaksi yang cukup.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: RECENT ORDERS -->
            <div class="dw-card-panel">
                <div class="dw-panel-head">
                    <h3>📦 Transaksi Terbaru</h3>
                    <?php $link_all = $is_super_admin ? '?page=dw-logs' : '?page=dw-pedagang'; // Fallback link ?>
                    <a href="<?php echo $link_all; ?>" style="text-decoration:none; font-size:12px; color:#3b82f6;">Lihat Semua</a>
                </div>
                <div style="padding:0;">
                    <?php if (empty($recent_orders)): ?>
                        <p style="padding:25px; text-align:center; color:#94a3b8;">Belum ada transaksi.</p>
                    <?php else: ?>
                        <table class="dw-table-clean">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Info</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html($order->kode_unik); ?></strong></td>
                                        <td>
                                            <?php 
                                            // Menentukan nama yang ditampilkan (Pembeli atau Toko)
                                            $name = $is_super_admin ? ($order->pembeli ?: 'Guest') : ($order->nama_toko ?: 'Toko');
                                            echo esc_html(wp_trim_words($name, 2, '')); 
                                            ?>
                                            <br><small style="color:#94a3b8;"><?php echo date('d M', strtotime($order->created_at)); ?></small>
                                        </td>
                                        <td><strong>Rp <?php echo number_format($order->total_bayar, 0, ',', '.'); ?></strong></td>
                                        <td>
                                            <?php 
                                            $st = $is_super_admin ? $order->status_pembayaran : $order->status_transaksi;
                                            $cls = 'status-pending';
                                            if (in_array($st, ['paid', 'selesai', 'lunas'])) $cls = 'status-success';
                                            if (in_array($st, ['failed', 'dibatalkan'])) $cls = 'status-failed';
                                            ?>
                                            <span class="dw-status-badge <?php echo $cls; ?>"><?php echo strtoupper(substr($st, 0, 3)); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
    <?php
}
?>