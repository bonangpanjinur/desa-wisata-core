<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * Description: Dashboard Admin dengan tampilan Modern SaaS + Statistik Wisata.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;
    $current_user = wp_get_current_user();

    // --- 1. DATA STATISTIK UTAMA (REAL TIME) ---
    // Desa
    $count_desa = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_desa'") == $wpdb->prefix . 'dw_desa') {
        $count_desa = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif'");
    }

    // Wisata (DIPERTAHANKAN)
    $count_wisata = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_wisata'") == $wpdb->prefix . 'dw_wisata') {
        $count_wisata = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_wisata WHERE status = 'aktif'");
    }

    // Pedagang
    $count_pedagang = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_pedagang'") == $wpdb->prefix . 'dw_pedagang') {
        $count_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_pedagang WHERE status_akun = 'aktif'");
    }
    
    // Produk
    $count_produk = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_produk'") == $wpdb->prefix . 'dw_produk') {
        $count_produk = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_produk WHERE status = 'aktif'");
    }
    
    // Omset
    $omset = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_transaksi'") == $wpdb->prefix . 'dw_transaksi') {
        $omset = $wpdb->get_var("SELECT SUM(total_bayar) FROM {$wpdb->prefix}dw_transaksi WHERE status_pembayaran = 'paid'") ?: 0;
    }

    // --- 2. DATA TRANSAKSI TERAKHIR ---
    $recent_orders = [];
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_transaksi'") == $wpdb->prefix . 'dw_transaksi') {
        $recent_orders = $wpdb->get_results(
            "SELECT t.*, u.display_name as pembeli 
             FROM {$wpdb->prefix}dw_transaksi t
             LEFT JOIN {$wpdb->users} u ON t.id_pembeli = u.ID
             ORDER BY t.created_at DESC LIMIT 5"
        );
    }

    // --- 3. DATA GRAFIK SEDERHANA (7 HARI) ---
    $chart_data = [];
    $max_value = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_transaksi'") == $wpdb->prefix . 'dw_transaksi') {
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('D', strtotime("-$i days")); 
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_bayar) FROM {$wpdb->prefix}dw_transaksi 
                 WHERE DATE(tanggal_transaksi) = %s AND status_pembayaran = 'paid'", $date
            )) ?: 0;
            
            if ($val > $max_value) $max_value = $val;
            $chart_data[] = ['label' => $label, 'value' => $val, 'date' => $date];
        }
    }
    if ($max_value == 0) $max_value = 1;

    ?>
    <div class="wrap dw-wrap">
        
        <!-- HEADER: Title & Profile -->
        <div class="dw-saas-header">
            <div class="dw-saas-title">
                <h1>Dashboard</h1>
                <p>Ringkasan aktivitas ekosistem Desa Wisata hari ini.</p>
            </div>
            <div class="dw-user-profile">
                <div class="dw-user-avatar">
                    <?php echo get_avatar($current_user->ID, 32); ?>
                </div>
                <div class="dw-user-info">
                    <span><?php echo esc_html($current_user->display_name); ?></span>
                    <small><?php echo ucfirst($current_user->roles[0]); ?></small>
                </div>
            </div>
        </div>

        <!-- HERO BANNER (SaaS Style) -->
        <div class="dw-saas-banner">
            <h2>Ahlan Wa Sahlan, <?php echo esc_html($current_user->display_name); ?>! 👋</h2>
            <p>Pantau performa desa wisata dan transaksi secara real-time hari ini.</p>
        </div>

        <!-- STATS GRID (Dengan Kartu Wisata) -->
        <div class="dw-stats-grid">
            <!-- Card 1: Total Omset -->
            <div class="dw-stat-card">
                <div class="dw-stat-header">
                    <div class="dw-stat-icon icon-blue">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <span class="dw-trend-badge trend-up">Est. Omset</span>
                </div>
                <div>
                    <h3 class="dw-stat-value">Rp <?php echo number_format($omset, 0, ',', '.'); ?></h3>
                    <span class="dw-stat-label">Total Pendapatan</span>
                </div>
            </div>

            <!-- Card 2: Desa Aktif -->
            <div class="dw-stat-card">
                <div class="dw-stat-header">
                    <div class="dw-stat-icon icon-green">
                        <span class="dashicons dashicons-location"></span>
                    </div>
                    <a href="?page=dw-desa" style="text-decoration:none; font-size:12px;">Detail &rarr;</a>
                </div>
                <div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_desa); ?></h3>
                    <span class="dw-stat-label">Desa Terdaftar</span>
                </div>
            </div>

            <!-- Card 3: Wisata (BARU DITAMBAHKAN KEMBALI) -->
            <div class="dw-stat-card">
                <div class="dw-stat-header">
                    <div class="dw-stat-icon icon-teal">
                        <span class="dashicons dashicons-palmtree"></span>
                    </div>
                    <a href="edit.php?post_type=dw_wisata" style="text-decoration:none; font-size:12px;">Lihat &rarr;</a>
                </div>
                <div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_wisata); ?></h3>
                    <span class="dw-stat-label">Objek Wisata</span>
                </div>
            </div>

            <!-- Card 4: Pedagang -->
            <div class="dw-stat-card">
                <div class="dw-stat-header">
                    <div class="dw-stat-icon icon-purple">
                        <span class="dashicons dashicons-store"></span>
                    </div>
                    <span class="dw-trend-badge trend-neutral">Aktif</span>
                </div>
                <div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_pedagang); ?></h3>
                    <span class="dw-stat-label">Toko / UMKM</span>
                </div>
            </div>

            <!-- Card 5: Produk -->
            <div class="dw-stat-card">
                <div class="dw-stat-header">
                    <div class="dw-stat-icon icon-orange">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <a href="edit.php?post_type=dw_produk" style="text-decoration:none; font-size:12px;">Lihat &rarr;</a>
                </div>
                <div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_produk); ?></h3>
                    <span class="dw-stat-label">Produk Tersedia</span>
                </div>
            </div>
        </div>

        <!-- CONTENT GRID -->
        <div class="dw-content-grid">
            
            <!-- Left: Chart -->
            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h3>Tren Pendapatan (7 Hari Terakhir)</h3>
                    <span style="font-size:12px; color:#64748b;"><?php echo date('d M') . ' - ' . date('d M', strtotime('-6 days')); ?></span>
                </div>
                <div class="dw-panel-body">
                    <?php if ($omset > 0): ?>
                        <div class="dw-saas-chart">
                            <?php foreach ($chart_data as $data): 
                                $height = ($data['value'] / $max_value) * 100;
                                $bg_color = ($data['value'] > 0) ? 'var(--saas-primary)' : '#e2e8f0'; 
                            ?>
                                <div class="dw-chart-col" title="<?php echo $data['date'] . ': Rp ' . number_format($data['value']); ?>">
                                    <div class="dw-chart-bar" style="height: <?php echo max(5, $height); ?>%; background-color: <?php echo $bg_color; ?>;"></div>
                                    <span class="dw-chart-label"><?php echo $data['label']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding:40px; color:#94a3b8;">
                            <span class="dashicons dashicons-chart-area" style="font-size:40px; height:40px; width:40px; margin-bottom:10px;"></span>
                            <p>Belum ada data transaksi yang cukup.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: List (Transaksi Terakhir) -->
            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h3>Transaksi Terakhir</h3>
                    <a href="?page=dw-logs" style="font-size:12px; text-decoration:none;">Log Aktivitas</a>
                </div>
                <div class="dw-panel-body" style="padding-top:0; padding-bottom:0;">
                    <?php if (empty($recent_orders)): ?>
                        <p style="padding:20px 0; color:#94a3b8; text-align:center;">Belum ada transaksi.</p>
                    <?php else: ?>
                        <div class="dw-list-group">
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="dw-list-item">
                                    <div class="dw-list-icon">
                                        <span class="dashicons dashicons-cart"></span>
                                    </div>
                                    <div class="dw-list-content">
                                        <span class="dw-list-title">#<?php echo esc_html($order->kode_unik); ?></span>
                                        <span class="dw-list-sub"><?php echo esc_html($order->pembeli ?: 'Guest'); ?> &bull; <?php echo human_time_diff(strtotime($order->tanggal_transaksi)); ?> lalu</span>
                                    </div>
                                    <div class="dw-list-action">
                                        <div style="font-weight:700; font-size:13px;">Rp <?php echo number_format($order->total_bayar, 0, ',', '.'); ?></div>
                                        <?php 
                                        $badge_class = 'badge-warning';
                                        if ($order->status_pembayaran == 'paid') $badge_class = 'badge-success';
                                        if ($order->status_pembayaran == 'failed') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="dw-badge-pill <?php echo $badge_class; ?>"><?php echo strtoupper($order->status_pembayaran); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
    <?php
}
?>