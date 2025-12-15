<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-dashboard.php
 * Description: Menampilkan halaman utama Dashboard Admin dengan tampilan yang ditingkatkan (UI Upgrade).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;
    $current_user = wp_get_current_user();

    // --- 1. DATA STATISTIK UTAMA ---
    // Pastikan tabel ada sebelum query untuk menghindari error fatal
    $count_desa = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_desa'") == $wpdb->prefix . 'dw_desa') {
        $count_desa = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif'");
    }

    $count_pedagang = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_pedagang'") == $wpdb->prefix . 'dw_pedagang') {
        $count_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_pedagang WHERE status_akun = 'aktif'");
    }
    
    $count_produk = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_produk'") == $wpdb->prefix . 'dw_produk') {
        $count_produk = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_produk WHERE status = 'aktif'");
    }
    
    // Hitung Omset
    $omset = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_transaksi'") == $wpdb->prefix . 'dw_transaksi') {
        $omset = $wpdb->get_var("SELECT SUM(total_bayar) FROM {$wpdb->prefix}dw_transaksi WHERE status_pembayaran = 'paid'") ?: 0;
    }

    // --- 2. DATA TRANSAKSI TERAKHIR (BARU) ---
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
                'date' => $date,
                'value' => $day_omset
            ];
        }
    }
    
    if ($max_value == 0) $max_value = 1;

    ?>
    <div class="wrap dw-wrap">
        
        <!-- HEADER / WELCOME BANNER -->
        <div class="dw-welcome-banner" style="background: #fff; padding: 25px; border-radius: 8px; border-left: 5px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <?php echo get_avatar($current_user->ID, 64, '', '', ['class' => 'dw-avatar', 'style' => 'border-radius: 50%; border: 2px solid #f0f0f1;']); ?>
                <div>
                    <h2 style="margin: 0; font-size: 22px; color: #1d2327;">Halo, <?php echo esc_html($current_user->display_name); ?>! 👋</h2>
                    <p style="margin: 5px 0 0; color: #646970; font-size: 14px;">Selamat datang kembali di panel pengelolaan Desa Wisata.</p>
                </div>
            </div>
            <div style="text-align: right; min-width: 120px;">
                <p style="margin: 0; font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">Hari ini</p>
                <strong style="font-size: 18px; color: #1d2327; display: block; margin-top: 2px;"><?php echo date_i18n('l, d F Y'); ?></strong>
                <span class="dw-status-badge status-aktif" style="margin-top: 5px; display: inline-block; font-size: 10px;">System Online</span>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="dw-dashboard-cards">
            <div class="dw-card">
                <div class="dw-card-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="dw-card-content">
                    <h3>Total Omset</h3>
                    <p class="dw-card-number" style="color: #2271b1;">Rp <?php echo number_format($omset, 0, ',', '.'); ?></p>
                </div>
            </div>
            
            <div class="dw-card">
                <div class="dw-card-icon"><span class="dashicons dashicons-location"></span></div>
                <div class="dw-card-content">
                    <h3>Desa Aktif</h3>
                    <p class="dw-card-number"><?php echo number_format($count_desa); ?></p>
                </div>
                <div class="dw-card-action"><a href="?page=dw-desa">Kelola &rarr;</a></div>
            </div>

            <div class="dw-card">
                <div class="dw-card-icon"><span class="dashicons dashicons-store"></span></div>
                <div class="dw-card-content">
                    <h3>Pedagang</h3>
                    <p class="dw-card-number"><?php echo number_format($count_pedagang); ?></p>
                </div>
                <div class="dw-card-action"><a href="?page=dw-pedagang">Kelola &rarr;</a></div>
            </div>

            <div class="dw-card">
                <div class="dw-card-icon"><span class="dashicons dashicons-cart"></span></div>
                <div class="dw-card-content">
                    <h3>Produk</h3>
                    <p class="dw-card-number"><?php echo number_format($count_produk); ?></p>
                </div>
                <div class="dw-card-action"><a href="edit.php?post_type=dw_produk">Lihat &rarr;</a></div>
            </div>
        </div>

        <!-- MAIN CONTENT COLUMNS -->
        <div class="dw-dashboard-columns" style="display: flex; flex-wrap: wrap; gap: 20px;">
            
            <!-- LEFT: ANALYTICS CHART -->
            <div class="dw-chart-container" style="flex: 2; min-width: 300px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #dcdcde;">
                <div class="dw-chart-header" style="border-bottom: 1px solid #f0f0f1; padding-bottom: 15px; margin-bottom: 20px;">
                    <h3 style="margin: 0; font-size: 16px; color: #1d2327;">Statistik Penjualan (7 Hari Terakhir)</h3>
                </div>
                
                <?php if (empty($chart_data) || $omset == 0): ?>
                    <div style="text-align: center; padding: 40px; color: #a7aaad;">
                        <span class="dashicons dashicons-chart-bar" style="font-size: 48px; width: 48px; height: 48px;"></span>
                        <p>Belum ada data transaksi yang cukup untuk ditampilkan.</p>
                    </div>
                <?php else: ?>
                    <div class="dw-chart-bars" style="height: 250px; align-items: flex-end; gap: 2%;">
                        <?php foreach ($chart_data as $data): 
                            $height_percent = ($data['value'] / $max_value) * 100;
                            // Minimal height agar bar tetap terlihat meski nilai kecil (tapi > 0)
                            if ($data['value'] > 0 && $height_percent < 5) $height_percent = 5;
                            $bar_color = ($data['value'] > 0) ? '#2271b1' : '#f0f0f1';
                        ?>
                            <div class="dw-bar-group" style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative; height: 100%;">
                                <div class="dw-bar-tooltip" style="margin-bottom: 5px; opacity: 0; transition: opacity 0.2s; position: absolute; bottom: <?php echo $height_percent; ?>%; background: #1d2327; color: #fff; padding: 5px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; pointer-events: none;">
                                    <?php echo $data['full_label']; ?>: Rp <?php echo number_format($data['value'], 0, ',', '.'); ?>
                                </div>
                                <div class="dw-bar" style="width: 70%; background: <?php echo $bar_color; ?>; border-radius: 4px 4px 0 0; height: <?php echo $height_percent; ?>%; transition: height 0.5s ease; position: relative;"></div>
                                <div class="dw-bar-label" style="margin-top: 10px; font-size: 11px; color: #646970;"><?php echo $data['label']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: RECENT ACTIVITY -->
            <div class="dw-recent-activity" style="flex: 1; min-width: 300px; background: #fff; padding: 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #dcdcde; overflow: hidden;">
                <div class="dw-activity-header" style="padding: 20px; border-bottom: 1px solid #f0f0f1; background: #fbfbfc;">
                    <h3 style="margin: 0; font-size: 16px; color: #1d2327;">Transaksi Terakhir</h3>
                </div>
                
                <div class="dw-activity-list" style="padding: 0;">
                    <?php if (empty($recent_orders)): ?>
                        <div style="padding: 20px; text-align: center; color: #a7aaad;">Belum ada transaksi terbaru.</div>
                    <?php else: ?>
                        <ul style="margin: 0; padding: 0; list-style: none;">
                            <?php foreach ($recent_orders as $order): ?>
                                <li style="padding: 15px 20px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="background: #e6f0f8; color: #2271b1; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <span class="dashicons dashicons-cart" style="font-size: 18px;"></span>
                                        </div>
                                        <div>
                                            <strong style="display: block; color: #1d2327; font-size: 13px;">#<?php echo esc_html($order->kode_unik); ?></strong>
                                            <span style="font-size: 11px; color: #646970;">
                                                <?php echo esc_html($order->pembeli ?: 'Guest'); ?> &bull; <span title="<?php echo esc_attr($order->tanggal_transaksi); ?>"><?php echo human_time_diff(strtotime($order->tanggal_transaksi), current_time('timestamp')) . ' lalu'; ?></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="display: block; font-weight: 600; color: #1d2327; font-size: 13px;">Rp <?php echo number_format($order->total_bayar, 0, ',', '.'); ?></span>
                                        <?php 
                                        $status_color = '#dba617'; // default pending/warning
                                        $status_label = $order->status_pembayaran;
                                        if ($status_label == 'paid') { $status_color = '#00a32a'; }
                                        elseif ($status_label == 'failed' || $status_label == 'expired') { $status_color = '#d63638'; }
                                        ?>
                                        <span style="font-size: 10px; padding: 2px 8px; border-radius: 10px; background: <?php echo $status_color; ?>; color: #fff; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">
                                            <?php echo esc_html($status_label); ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if (!empty($recent_orders)): ?>
                <div class="dw-activity-footer" style="padding: 15px; text-align: center; background: #fbfbfc; border-top: 1px solid #f0f0f1;">
                    <a href="?page=dw-logs" style="text-decoration: none; font-weight: 500; font-size: 13px;">Lihat Log Aktivitas Lengkap &rarr;</a>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
    
    <!-- Inline Style untuk Hover Effect pada List -->
    <style>
        .dw-activity-list li:hover { background-color: #f6f7f7; }
        .dw-bar-group:hover .dw-bar-tooltip { opacity: 1 !important; }
        .dw-bar-group:hover .dw-bar { opacity: 0.8; }
    </style>
    <?php
}
?>