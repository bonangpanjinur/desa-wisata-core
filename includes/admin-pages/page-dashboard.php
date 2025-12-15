<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-dashboard.php
 * Description: Menampilkan halaman utama Dashboard Admin dengan tampilan UI/UX yang lebih modern dan rapi.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;
    $current_user = wp_get_current_user();

    // --- 1. DATA STATISTIK UTAMA ---
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
                'date' => $date,
                'value' => $day_omset
            ];
        }
    }
    
    if ($max_value == 0) $max_value = 1;

    ?>
    <div class="wrap dw-wrap" style="max-width: 1200px; margin: 20px auto;">
        
        <!-- HEADER / WELCOME BANNER -->
        <div class="dw-welcome-banner" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e4e7; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <?php echo get_avatar($current_user->ID, 64, '', '', ['class' => 'dw-avatar', 'style' => 'border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 3px solid #fff;']); ?>
                <div>
                    <h2 style="margin: 0; font-size: 24px; color: #1d2327; font-weight: 700;">Halo, <?php echo esc_html($current_user->display_name); ?>! 👋</h2>
                    <p style="margin: 6px 0 0; color: #646970; font-size: 14px; font-weight: 500;">Berikut ringkasan aktivitas ekosistem Desa Wisata hari ini.</p>
                </div>
            </div>
            <div style="text-align: right; background: #fff; padding: 10px 20px; border-radius: 8px; border: 1px solid #eee;">
                <p style="margin: 0; font-size: 11px; color: #8c8f94; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">Hari Ini</p>
                <strong style="font-size: 16px; color: #1d2327; display: block; margin-top: 2px;"><?php echo date_i18n('l, d F Y'); ?></strong>
            </div>
        </div>

        <!-- STAT CARDS (Menggunakan CSS Grid untuk kerapian) -->
        <div class="dw-dashboard-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 30px;">
            
            <div class="dw-card" style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid #e2e4e7; transition: transform 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <h3 style="margin: 0; font-size: 13px; font-weight: 600; color: #646970; text-transform: uppercase;">Total Omset</h3>
                    </div>
                    <div style="background: #eef2f7; color: #2271b1; padding: 8px; border-radius: 8px;">
                        <span class="dashicons dashicons-money-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
                    </div>
                </div>
                <p class="dw-card-number" style="font-size: 28px; font-weight: 700; color: #1d2327; margin: 0; letter-spacing: -0.5px;">Rp <?php echo number_format($omset, 0, ',', '.'); ?></p>
            </div>
            
            <div class="dw-card" style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid #e2e4e7; transition: transform 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <h3 style="margin: 0; font-size: 13px; font-weight: 600; color: #646970; text-transform: uppercase;">Desa Aktif</h3>
                    </div>
                    <div style="background: #eef9f3; color: #00a32a; padding: 8px; border-radius: 8px;">
                        <span class="dashicons dashicons-location" style="font-size: 20px; width: 20px; height: 20px;"></span>
                    </div>
                </div>
                <p class="dw-card-number" style="font-size: 28px; font-weight: 700; color: #1d2327; margin: 0;"><?php echo number_format($count_desa); ?></p>
                <div style="margin-top: 10px; font-size: 12px;">
                    <a href="?page=dw-desa" style="text-decoration: none; color: #2271b1;">Kelola Desa &rarr;</a>
                </div>
            </div>

            <div class="dw-card" style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid #e2e4e7; transition: transform 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <h3 style="margin: 0; font-size: 13px; font-weight: 600; color: #646970; text-transform: uppercase;">Total Toko</h3>
                    </div>
                    <div style="background: #fff8e5; color: #dba617; padding: 8px; border-radius: 8px;">
                        <span class="dashicons dashicons-store" style="font-size: 20px; width: 20px; height: 20px;"></span>
                    </div>
                </div>
                <p class="dw-card-number" style="font-size: 28px; font-weight: 700; color: #1d2327; margin: 0;"><?php echo number_format($count_pedagang); ?></p>
                <div style="margin-top: 10px; font-size: 12px;">
                    <a href="?page=dw-pedagang" style="text-decoration: none; color: #2271b1;">Lihat Toko &rarr;</a>
                </div>
            </div>

            <div class="dw-card" style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid #e2e4e7; transition: transform 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <h3 style="margin: 0; font-size: 13px; font-weight: 600; color: #646970; text-transform: uppercase;">Produk</h3>
                    </div>
                    <div style="background: #f0f0f1; color: #50575e; padding: 8px; border-radius: 8px;">
                        <span class="dashicons dashicons-cart" style="font-size: 20px; width: 20px; height: 20px;"></span>
                    </div>
                </div>
                <p class="dw-card-number" style="font-size: 28px; font-weight: 700; color: #1d2327; margin: 0;"><?php echo number_format($count_produk); ?></p>
                <div style="margin-top: 10px; font-size: 12px;">
                    <a href="edit.php?post_type=dw_produk" style="text-decoration: none; color: #2271b1;">Daftar Produk &rarr;</a>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT COLUMNS -->
        <div class="dw-dashboard-columns" style="display: flex; flex-wrap: wrap; gap: 24px; align-items: flex-start;">
            
            <!-- LEFT: ANALYTICS CHART -->
            <div class="dw-chart-container" style="flex: 2; min-width: 350px; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid #e2e4e7;">
                <div class="dw-chart-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f1; padding-bottom: 15px; margin-bottom: 25px;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #1d2327;">Analitik Penjualan</h3>
                    <span style="font-size: 12px; color: #646970; background: #f6f7f7; padding: 4px 10px; border-radius: 20px;">7 Hari Terakhir</span>
                </div>
                
                <?php if (empty($chart_data) || $omset == 0): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #a7aaad;">
                        <span class="dashicons dashicons-chart-bar" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 10px; opacity: 0.5;"></span>
                        <p style="margin:0;">Belum ada data transaksi yang cukup untuk ditampilkan.</p>
                    </div>
                <?php else: ?>
                    <!-- Chart with simple grid background -->
                    <div class="dw-chart-bars" style="height: 250px; display: flex; align-items: flex-end; gap: 15px; background-image: repeating-linear-gradient(0deg, #f0f0f1 0px, #f0f0f1 1px, transparent 1px, transparent 20%); background-size: 100% 20%; padding-top: 20px; position: relative;">
                        <?php foreach ($chart_data as $data): 
                            $height_percent = ($data['value'] / $max_value) * 100;
                            if ($data['value'] > 0 && $height_percent < 5) $height_percent = 5;
                            $bar_color = ($data['value'] > 0) ? '#2271b1' : '#e2e4e7';
                        ?>
                            <div class="dw-bar-group" style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative; height: 100%;">
                                <div class="dw-bar-tooltip" style="margin-bottom: 8px; opacity: 0; transition: all 0.2s; position: absolute; bottom: <?php echo $height_percent; ?>%; background: #1d2327; color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 11px; white-space: nowrap; pointer-events: none; transform: translateY(5px); z-index: 10;">
                                    <strong><?php echo $data['full_label']; ?></strong><br>Rp <?php echo number_format($data['value'], 0, ',', '.'); ?>
                                </div>
                                <div class="dw-bar" style="width: 60%; background: <?php echo $bar_color; ?>; border-radius: 4px 4px 0 0; height: <?php echo $height_percent; ?>%; transition: height 0.5s ease; position: relative;"></div>
                                <div class="dw-bar-label" style="margin-top: 12px; font-size: 11px; font-weight: 500; color: #646970;"><?php echo $data['label']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: RECENT ACTIVITY -->
            <div class="dw-recent-activity" style="flex: 1; min-width: 300px; background: #fff; padding: 0; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid #e2e4e7; overflow: hidden;">
                <div class="dw-activity-header" style="padding: 20px 24px; border-bottom: 1px solid #f0f0f1; background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #1d2327;">Transaksi Terakhir</h3>
                    <a href="?page=dw-logs" style="font-size: 12px; text-decoration: none; color: #2271b1;">Lihat Log</a>
                </div>
                
                <div class="dw-activity-list" style="padding: 0;">
                    <?php if (empty($recent_orders)): ?>
                        <div style="padding: 30px; text-align: center; color: #a7aaad; font-size: 13px;">Belum ada transaksi terbaru.</div>
                    <?php else: ?>
                        <ul style="margin: 0; padding: 0; list-style: none;">
                            <?php foreach ($recent_orders as $order): ?>
                                <li style="padding: 16px 24px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; transition: background 0.15s;">
                                    <div style="display: flex; align-items: center; gap: 16px;">
                                        <div style="background: #f0f6fc; color: #2271b1; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <span class="dashicons dashicons-cart" style="font-size: 20px;"></span>
                                        </div>
                                        <div>
                                            <strong style="display: block; color: #1d2327; font-size: 13px; margin-bottom: 3px;">#<?php echo esc_html($order->kode_unik); ?></strong>
                                            <span style="font-size: 11px; color: #646970;">
                                                <?php echo esc_html($order->pembeli ?: 'Guest'); ?> &bull; <?php echo human_time_diff(strtotime($order->tanggal_transaksi), current_time('timestamp')); ?> lalu
                                            </span>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="display: block; font-weight: 700; color: #1d2327; font-size: 13px; margin-bottom: 4px;">Rp <?php echo number_format($order->total_bayar, 0, ',', '.'); ?></span>
                                        <?php 
                                        $status_color = '#dba617'; $status_bg = '#fff8e5'; 
                                        $status_label = $order->status_pembayaran;
                                        if ($status_label == 'paid') { $status_color = '#008a20'; $status_bg = '#dcfce7'; }
                                        elseif ($status_label == 'failed' || $status_label == 'expired') { $status_color = '#d63638'; $status_bg = '#fce8e8'; }
                                        ?>
                                        <span style="font-size: 10px; padding: 2px 8px; border-radius: 4px; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; font-weight: 600; letter-spacing: 0.5px; border: 1px solid <?php echo $status_color; ?>20;">
                                            <?php echo strtoupper($status_label); ?>
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
    
    <!-- Inline Style untuk Interaksi & Responsif -->
    <style>
        .dw-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important; }
        .dw-activity-list li:hover { background-color: #fcfcfc; }
        .dw-activity-list li:last-child { border-bottom: none; }
        .dw-bar-group:hover .dw-bar-tooltip { opacity: 1 !important; transform: translateY(0) !important; }
        .dw-bar-group:hover .dw-bar { opacity: 0.85; }
        
        @media (max-width: 782px) {
            .dw-dashboard-columns { flex-direction: column; }
            .dw-chart-container, .dw-recent-activity { width: 100%; min-width: 100% !important; }
        }
    </style>
    <?php
}
?>