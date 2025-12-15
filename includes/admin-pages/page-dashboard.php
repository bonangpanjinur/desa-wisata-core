<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-dashboard.php
 * Description: Dashboard Admin (Classic Design) + Statistik Wisata.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;
    $current_user = wp_get_current_user();

    // --- 1. DATA STATISTIK UTAMA ---
    
    // Desa
    $count_desa = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_desa'") == $wpdb->prefix . 'dw_desa') {
        $count_desa = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif'");
    }

    // [BARU] Wisata
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

    // --- 3. DATA GRAFIK (7 HARI TERAKHIR) ---
    $chart_data = [];
    $max_value = 0;
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_transaksi'") == $wpdb->prefix . 'dw_transaksi') {
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('D', strtotime("-$i days")); 
            $full_date_label = date_i18n('j M', strtotime("-$i days"));
            
            $day_omset = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_bayar) FROM {$wpdb->prefix}dw_transaksi 
                 WHERE DATE(tanggal_transaksi) = %s AND status_pembayaran = 'paid'",
                $date
            ));
            $day_omset = $day_omset ?: 0;
            
            if ($day_omset > $max_value) $max_value = $day_omset;
            
            $chart_data[] = [
                'label' => $label,
                'full_label' => $full_date_label,
                'value' => $day_omset
            ];
        }
    }
    if ($max_value == 0) $max_value = 1;

    ?>
    <div class="wrap dw-wrap">
        
        <!-- HEADER / WELCOME BANNER -->
        <div class="dw-welcome-banner">
            <div style="display: flex; align-items: center; gap: 20px;">
                <?php echo get_avatar($current_user->ID, 64, '', '', ['class' => 'dw-avatar', 'style' => 'border-radius: 50%; border: 2px solid #f0f0f1;']); ?>
                <div>
                    <h2 style="margin: 0; font-size: 22px; color: #1d2327;">Halo, <?php echo esc_html($current_user->display_name); ?>! 👋</h2>
                    <p style="margin: 5px 0 0; color: #646970; font-size: 14px;">Berikut ringkasan aktivitas ekosistem Desa Wisata hari ini.</p>
                </div>
            </div>
            <div style="text-align: right; min-width: 120px;">
                <p style="margin: 0; font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">HARI INI</p>
                <strong style="font-size: 18px; color: #1d2327; display: block; margin-top: 2px;"><?php echo date_i18n('l, d F Y'); ?></strong>
                <span style="color: #00a32a; font-size: 12px;">System Online</span>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="dw-dashboard-cards">
            <!-- Card 1: Omset -->
            <div class="dw-card">
                <div style="margin-bottom:10px;">
                    <h3 style="margin:0;">Total Omset</h3>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-money-alt" style="font-size:32px; width:32px; height:32px; color:#646970;"></span>
                    <p class="dw-card-number" style="color: #2271b1;">Rp <?php echo number_format($omset, 0, ',', '.'); ?></p>
                </div>
            </div>
            
            <!-- Card 2: Desa -->
            <div class="dw-card">
                <div style="margin-bottom:10px;">
                    <h3 style="margin:0;">Desa Aktif</h3>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-location" style="font-size:32px; width:32px; height:32px; color:#646970;"></span>
                    <p class="dw-card-number"><?php echo number_format($count_desa); ?></p>
                    <a href="?page=dw-desa" style="margin-left:auto;">Kelola &rarr;</a>
                </div>
            </div>

            <!-- Card 3: Wisata (BARU) -->
            <div class="dw-card">
                <div style="margin-bottom:10px;">
                    <h3 style="margin:0;">Objek Wisata</h3>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-palmtree" style="font-size:32px; width:32px; height:32px; color:#646970;"></span>
                    <p class="dw-card-number"><?php echo number_format($count_wisata); ?></p>
                    <a href="edit.php?post_type=dw_wisata" style="margin-left:auto;">Lihat &rarr;</a>
                </div>
            </div>

            <!-- Card 4: Pedagang -->
            <div class="dw-card">
                <div style="margin-bottom:10px;">
                    <h3 style="margin:0;">Pedagang</h3>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-store" style="font-size:32px; width:32px; height:32px; color:#646970;"></span>
                    <p class="dw-card-number"><?php echo number_format($count_pedagang); ?></p>
                    <a href="?page=dw-pedagang" style="margin-left:auto;">Kelola &rarr;</a>
                </div>
            </div>

            <!-- Card 5: Produk -->
            <div class="dw-card">
                <div style="margin-bottom:10px;">
                    <h3 style="margin:0;">Produk</h3>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-cart" style="font-size:32px; width:32px; height:32px; color:#646970;"></span>
                    <p class="dw-card-number"><?php echo number_format($count_produk); ?></p>
                    <a href="edit.php?post_type=dw_produk" style="margin-left:auto;">Lihat &rarr;</a>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT COLUMNS -->
        <div class="dw-dashboard-columns">
            
            <!-- LEFT: ANALYTICS CHART -->
            <div class="dw-chart-container" style="flex: 2; min-width: 300px;">
                <div class="dw-chart-header">
                    <h3>Statistik Penjualan (7 Hari Terakhir)</h3>
                </div>
                
                <?php if ($omset == 0): ?>
                    <div style="text-align: center; padding: 40px; color: #a7aaad;">
                        <p>Belum ada data transaksi.</p>
                    </div>
                <?php else: ?>
                    <div class="dw-chart-bars">
                        <?php foreach ($chart_data as $data): 
                            $height_percent = ($data['value'] / $max_value) * 100;
                            if ($data['value'] > 0 && $height_percent < 5) $height_percent = 5;
                        ?>
                            <div class="dw-bar-group">
                                <div class="dw-bar-tooltip">
                                    <?php echo $data['full_label']; ?>: Rp <?php echo number_format($data['value'], 0, ',', '.'); ?>
                                </div>
                                <div class="dw-bar" style="height: <?php echo $height_percent; ?>%;"></div>
                                <div class="dw-bar-label"><?php echo $data['label']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: RECENT ACTIVITY -->
            <div class="dw-recent-activity" style="flex: 1; min-width: 300px;">
                <div class="dw-activity-header" style="display:flex; justify-content:space-between;">
                    <h3>Transaksi Terakhir</h3>
                    <a href="?page=dw-logs" style="text-decoration:none; font-size:12px;">Lihat Log</a>
                </div>
                
                <div class="dw-activity-list">
                    <?php if (empty($recent_orders)): ?>
                        <p style="text-align:center; color:#999;">Belum ada transaksi.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($recent_orders as $order): ?>
                                <li>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="background: #e6f0f8; color: #2271b1; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <span class="dashicons dashicons-cart"></span>
                                        </div>
                                        <div>
                                            <strong style="display: block; font-size: 13px;">#<?php echo esc_html($order->kode_unik); ?></strong>
                                            <span style="font-size: 11px; color: #646970;">
                                                <?php echo esc_html($order->pembeli ?: 'Guest'); ?> &bull; <?php echo human_time_diff(strtotime($order->tanggal_transaksi), current_time('timestamp')) . ' lalu'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="display: block; font-weight: 600; font-size: 13px;">Rp <?php echo number_format($order->total_bayar, 0, ',', '.'); ?></span>
                                        <?php 
                                        $status_color = '#dba617'; 
                                        if ($order->status_pembayaran == 'paid') $status_color = '#00a32a'; 
                                        elseif ($order->status_pembayaran == 'failed') $status_color = '#d63638'; 
                                        ?>
                                        <span style="font-size: 10px; background: <?php echo $status_color; ?>; color: #fff; padding: 2px 6px; border-radius: 4px;">
                                            <?php echo strtoupper($order->status_pembayaran); ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
    <?php
}
?>