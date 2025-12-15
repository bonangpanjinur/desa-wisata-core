<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-dashboard.php
 * Description: Menampilkan halaman utama Dashboard Admin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;

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

    // --- 2. DATA GRAFIK (7 HARI TERAKHIR) ---
    $chart_data = [];
    $max_value = 0;
    
    // Hanya generate chart jika tabel transaksi ada
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dw_transaksi'") == $wpdb->prefix . 'dw_transaksi') {
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('D', strtotime("-$i days")); 
            
            $day_omset = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_bayar) FROM {$wpdb->prefix}dw_transaksi 
                 WHERE DATE(tanggal_transaksi) = %s AND status_pembayaran = 'paid'",
                $date
            ));
            $day_omset = $day_omset ?: 0;
            
            if ($day_omset > $max_value) $max_value = $day_omset;
            
            $chart_data[] = [
                'label' => $label,
                'date' => $date,
                'value' => $day_omset
            ];
        }
    }
    
    if ($max_value == 0) $max_value = 1;

    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <div>
                <h1>Dashboard Desa Wisata</h1>
                <p style="margin:0; color:#666;">Ringkasan aktivitas platform Anda.</p>
            </div>
            <div style="text-align:right;">
                <span class="dw-status-badge status-aktif">System Online</span>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="dw-dashboard-cards">
            <div class="dw-card">
                <div class="dw-card-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="dw-card-content">
                    <h3>Total Omset</h3>
                    <p class="dw-card-number">Rp <?php echo number_format($omset, 0, ',', '.'); ?></p>
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
            </div>
        </div>

        <!-- ANALYTICS CHART -->
        <div class="dw-chart-container">
            <div class="dw-chart-header">
                <h3>Statistik Penjualan (7 Hari Terakhir)</h3>
            </div>
            
            <?php if (empty($chart_data)): ?>
                <p>Belum ada data transaksi.</p>
            <?php else: ?>
                <div class="dw-chart-bars">
                    <?php foreach ($chart_data as $data): 
                        $height_percent = ($data['value'] / $max_value) * 100;
                        if ($data['value'] > 0 && $height_percent < 5) $height_percent = 5;
                    ?>
                        <div class="dw-bar-group">
                            <div class="dw-bar-tooltip">
                                <?php echo $data['date']; ?>: Rp <?php echo number_format($data['value'], 0, ',', '.'); ?>
                            </div>
                            <div class="dw-bar" style="height: <?php echo $height_percent; ?>%;"></div>
                            <div class="dw-bar-label"><?php echo $data['label']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>