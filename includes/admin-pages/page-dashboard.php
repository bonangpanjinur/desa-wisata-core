<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * Description: Dashboard Admin Premium (Clean UI) dengan data Real-Time.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;
    $current_user = wp_get_current_user();

    // --- 1. PENGAMBILAN DATA (DATABASE REAL) ---
    
    // Cek keberadaan tabel untuk menghindari error jika plugin baru diaktifkan
    $table_desa = $wpdb->prefix . 'dw_desa';
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_produk = $wpdb->prefix . 'dw_produk';
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';

    // Helper function safe count
    $get_count = function($table, $where = "status = 'aktif'") use ($wpdb) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) return 0;
        return (int) $wpdb->get_var("SELECT COUNT(id) FROM $table WHERE $where");
    };

    $count_desa = $get_count($table_desa, "status = 'aktif'");
    $count_wisata = $get_count($table_wisata, "status = 'aktif'");
    $count_pedagang = $get_count($table_pedagang, "status_akun = 'aktif'");
    $count_produk = $get_count($table_produk, "status = 'aktif'");

    // Hitung Omset (Total Bayar Transaksi 'paid')
    $omset = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_transaksi'") == $table_transaksi) {
        $omset = (float) $wpdb->get_var("SELECT SUM(total_bayar) FROM $table_transaksi WHERE status_pembayaran = 'paid'");
    }

    // Ambil 5 Transaksi Terakhir
    $recent_orders = [];
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_transaksi'") == $table_transaksi) {
        $recent_orders = $wpdb->get_results(
            "SELECT t.*, u.display_name as pembeli 
             FROM $table_transaksi t
             LEFT JOIN {$wpdb->users} u ON t.id_pembeli = u.ID
             ORDER BY t.created_at DESC LIMIT 5"
        );
    }

    // Data Grafik (Omset 7 Hari Terakhir)
    $chart_data = [];
    $max_value = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_transaksi'") == $table_transaksi) {
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('D', strtotime("-$i days")); 
            $full_label = date_i18n('j F Y', strtotime("-$i days"));
            
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_bayar) FROM $table_transaksi 
                 WHERE DATE(tanggal_transaksi) = %s AND status_pembayaran = 'paid'", $date
            )) ?: 0;
            
            if ($val > $max_value) $max_value = $val;
            $chart_data[] = ['label' => $label, 'value' => (float)$val, 'full_label' => $full_label];
        }
    }
    if ($max_value == 0) $max_value = 1; // Prevent division by zero

    ?>
    <div class="wrap dw-wrap">
        
        <!-- 1. HERO BANNER -->
        <div class="dw-dashboard-hero">
            <div class="dw-hero-content">
                <h2>Ahlan Wa Sahlan, <?php echo esc_html($current_user->display_name); ?>! 👋</h2>
                <p>Pantau performa ekosistem Desa Wisata Anda secara real-time hari ini.</p>
            </div>
            <div class="dw-hero-date">
                <span>HARI INI</span>
                <strong><?php echo date_i18n('l, d F Y'); ?></strong>
            </div>
        </div>

        <!-- 2. STATS GRID (Clean White Cards) -->
        <div class="dw-stats-grid">
            
            <!-- Card 1: Omset -->
            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-blue">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <h3 class="dw-stat-value">Rp <?php echo number_format($omset, 0, ',', '.'); ?></h3>
                    <div class="dw-stat-label">Total Omset</div>
                </div>
            </div>

            <!-- Card 2: Desa -->
            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-green">
                        <span class="dashicons dashicons-location"></span>
                    </div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_desa); ?></h3>
                    <div class="dw-stat-label">Desa Terdaftar</div>
                </div>
            </div>

            <!-- Card 3: Wisata (Sesuai Request) -->
            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-teal">
                        <span class="dashicons dashicons-palmtree"></span>
                    </div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_wisata); ?></h3>
                    <div class="dw-stat-label">Objek Wisata</div>
                </div>
            </div>

            <!-- Card 4: Pedagang -->
            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-purple">
                        <span class="dashicons dashicons-store"></span>
                    </div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_pedagang); ?></h3>
                    <div class="dw-stat-label">Toko / UMKM</div>
                </div>
            </div>

            <!-- Card 5: Produk -->
            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-orange">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_produk); ?></h3>
                    <div class="dw-stat-label">Produk Tersedia</div>
                </div>
            </div>

        </div>

        <!-- 3. SPLIT CONTENT: Chart & List -->
        <div class="dw-dashboard-split">
            
            <!-- Left: Chart (Grafik CSS Murni) -->
            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h3>Tren Pendapatan (7 Hari Terakhir)</h3>
                </div>
                <div class="dw-panel-body">
                    <?php if ($omset > 0): ?>
                        <div class="dw-css-chart">
                            <?php foreach ($chart_data as $data): 
                                $height = ($data['value'] / $max_value) * 100;
                                $bg_color = ($data['value'] > 0) ? '#2563eb' : '#e2e8f0'; // Biru aktif, abu kosong
                            ?>
                                <div class="dw-chart-column">
                                    <!-- Tooltip Data -->
                                    <div class="dw-chart-tooltip">
                                        <?php echo $data['full_label']; ?>: Rp <?php echo number_format($data['value'], 0, ',', '.'); ?>
                                    </div>
                                    <!-- Bar -->
                                    <div class="dw-chart-bar" style="height: <?php echo max(4, $height); ?>%; background-color: <?php echo $bg_color; ?>;"></div>
                                    <!-- Label Hari -->
                                    <div class="dw-chart-label"><?php echo $data['label']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding: 60px 0; color: #94a3b8;">
                            <span class="dashicons dashicons-chart-bar" style="font-size:48px; height:48px; width:48px; margin-bottom:10px;"></span>
                            <p>Belum ada data transaksi yang cukup untuk ditampilkan.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Recent Transactions List -->
            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h3>Transaksi Terakhir</h3>
                    <a href="?page=dw-logs" style="font-size:12px; text-decoration:none; color:#2563eb;">Lihat Semua</a>
                </div>
                <div class="dw-panel-body" style="padding-top:0; padding-bottom:0;">
                    <div class="dw-activity-list">
                        <?php if (empty($recent_orders)): ?>
                            <p style="padding:20px; text-align:center; color:#94a3b8;">Belum ada transaksi terbaru.</p>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <span class="dashicons dashicons-cart"></span>
                                    </div>
                                    <div class="activity-info">
                                        <h4>#<?php echo esc_html($order->kode_unik); ?></h4>
                                        <p><?php echo esc_html($order->pembeli ?: 'Guest'); ?> &bull; <?php echo human_time_diff(strtotime($order->tanggal_transaksi)); ?> lalu</p>
                                    </div>
                                    <div class="activity-meta">
                                        <span class="activity-amount">Rp <?php echo number_format($order->total_bayar, 0, ',', '.'); ?></span>
                                        <?php 
                                        $status_class = 'badge-pending';
                                        if ($order->status_pembayaran == 'paid') $status_class = 'badge-paid';
                                        if ($order->status_pembayaran == 'failed') $status_class = 'badge-failed';
                                        ?>
                                        <span class="activity-badge <?php echo $status_class; ?>"><?php echo strtoupper($order->status_pembayaran); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </div>
    <?php
}
?>