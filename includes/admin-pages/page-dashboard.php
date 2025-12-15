<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * Description: Dashboard Admin Premium dengan Smart Context (Role-Based) & Optimized Query.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;

    // --- 1. DEFINISI TABEL ---
    $table_desa = $wpdb->prefix . 'dw_desa';
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_produk = $wpdb->prefix . 'dw_produk';
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';
    $table_sub_transaksi = $wpdb->prefix . 'dw_transaksi_sub';

    // Cek tabel utama ada
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_transaksi'") != $table_transaksi) {
        echo '<div class="notice notice-error"><p>Tabel database belum lengkap. Silakan jalankan migrasi/aktivasi ulang.</p></div>';
        return;
    }

    // --- 2. TENTUKAN KONTEKS ROLE ---
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $is_admin_desa  = current_user_can('admin_desa');
    
    // Default Filter (Global)
    $where_desa_sql = ""; 
    $where_pedagang_sql = "";
    $managed_desa_id = 0;

    // Logika Khusus Admin Desa
    if ($is_admin_desa && !$is_super_admin) {
        $managed_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE id_user_desa = %d", $user_id));
        
        if (!$managed_desa_id) {
            echo '<div class="wrap dw-wrap"><div class="notice notice-warning"><p>Akun Anda belum terhubung dengan Desa manapun. Hubungi Administrator.</p></div></div>';
            return;
        }

        // Filter Query
        $where_desa_sql = "AND id = $managed_desa_id"; // Hanya desanya sendiri
        $where_pedagang_sql = "AND id_desa = $managed_desa_id"; // Hanya pedagang di desanya
    }

    // --- 3. HITUNG STATISTIK (COUNTERS) ---

    // A. Jumlah Desa
    if ($is_super_admin) {
        $count_desa = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_desa WHERE status = 'aktif'");
    } else {
        $count_desa = 1; // Admin Desa hanya mengelola 1 desa
    }

    // B. Jumlah Wisata (Difilter berdasarkan desa jika Admin Desa)
    $sql_wisata = "SELECT COUNT(id) FROM $table_wisata WHERE status = 'aktif'";
    if ($managed_desa_id) $sql_wisata .= " AND id_desa = $managed_desa_id";
    $count_wisata = (int) $wpdb->get_var($sql_wisata);

    // C. Jumlah Pedagang
    $sql_pedagang = "SELECT COUNT(id) FROM $table_pedagang WHERE status_akun = 'aktif' $where_pedagang_sql";
    $count_pedagang = (int) $wpdb->get_var($sql_pedagang);

    // D. Jumlah Produk (Harus join ke pedagang untuk filter desa)
    $sql_produk = "SELECT COUNT(p.id) FROM $table_produk p 
                   JOIN $table_pedagang d ON p.id_pedagang = d.id 
                   WHERE p.status = 'aktif' AND d.status_akun = 'aktif'";
    if ($managed_desa_id) $sql_produk .= " AND d.id_desa = $managed_desa_id";
    $count_produk = (int) $wpdb->get_var($sql_produk);

    // E. Hitung Omset (Kompleks: Global vs Per Desa)
    $omset = 0;
    if ($is_super_admin) {
        // Global: Ambil dari total_bayar transaksi utama
        $omset = (float) $wpdb->get_var("SELECT SUM(total_bayar) FROM $table_transaksi WHERE status_pembayaran = 'paid'");
    } else {
        // Per Desa: Ambil dari sub_transaksi (penjualan toko) yang tokonya ada di desa ini
        // Status 'selesai' atau 'lunas' pada sub transaksi
        $sql_omset_desa = $wpdb->prepare("
            SELECT SUM(sub.total_pesanan_toko) 
            FROM $table_sub_transaksi sub
            JOIN $table_pedagang p ON sub.id_pedagang = p.id
            WHERE p.id_desa = %d 
            AND sub.status_pesanan IN ('selesai', 'lunas', 'dikirim', 'diproses')", 
            $managed_desa_id
        );
        $omset = (float) $wpdb->get_var($sql_omset_desa);
    }

    // --- 4. DATA GRAFIK (OPTIMIZED SINGLE QUERY) ---
    // Mengambil data 7 hari terakhir dalam satu tarikan
    
    $chart_data = [];
    $max_value = 0;
    
    // Siapkan array tanggal 7 hari ke belakang (default 0)
    for ($i = 6; $i >= 0; $i--) {
        $date_key = date('Y-m-d', strtotime("-$i days"));
        $chart_data[$date_key] = [
            'label' => date('D', strtotime($date_key)),
            'full_label' => date_i18n('j F Y', strtotime($date_key)),
            'value' => 0
        ];
    }

    if ($is_super_admin) {
        // Query Agregat Global
        $results = $wpdb->get_results("
            SELECT DATE(tanggal_transaksi) as tgl, SUM(total_bayar) as total 
            FROM $table_transaksi 
            WHERE status_pembayaran = 'paid' 
            AND tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(tanggal_transaksi)
        ");
    } else {
        // Query Agregat Per Desa
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(sub.created_at) as tgl, SUM(sub.total_pesanan_toko) as total
            FROM $table_sub_transaksi sub
            JOIN $table_pedagang p ON sub.id_pedagang = p.id
            WHERE p.id_desa = %d
            AND sub.status_pesanan IN ('selesai', 'lunas', 'dikirim', 'diproses')
            AND sub.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(sub.created_at)
        ", $managed_desa_id));
    }

    // Masukkan hasil query ke array chart
    if ($results) {
        foreach ($results as $row) {
            if (isset($chart_data[$row->tgl])) {
                $chart_data[$row->tgl]['value'] = (float) $row->total;
                if ($row->total > $max_value) $max_value = (float) $row->total;
            }
        }
    }
    if ($max_value == 0) $max_value = 1; // Cegah division by zero

    // --- 5. TRANSAKSI TERAKHIR ---
    $recent_orders = [];
    if ($is_super_admin) {
        $recent_orders = $wpdb->get_results(
            "SELECT t.*, u.display_name as pembeli 
             FROM $table_transaksi t
             LEFT JOIN {$wpdb->users} u ON t.id_pembeli = u.ID
             ORDER BY t.created_at DESC LIMIT 5"
        );
    } else {
        // Transaksi terakhir yang melibatkan pedagang di desa ini
        $recent_orders = $wpdb->get_results($wpdb->prepare(
            "SELECT sub.id, sub.status_pesanan as status_transaksi, sub.total_pesanan_toko as total_bayar, sub.created_at, 
                    p.nama_toko, main.kode_unik
             FROM $table_sub_transaksi sub
             JOIN $table_pedagang p ON sub.id_pedagang = p.id
             JOIN $table_transaksi main ON sub.id_transaksi = main.id
             WHERE p.id_desa = %d
             ORDER BY sub.created_at DESC LIMIT 5",
             $managed_desa_id
        ));
    }

    // --- RENDER VIEW ---
    ?>
    <div class="wrap dw-wrap">
        
        <!-- HERO BANNER -->
        <div class="dw-dashboard-hero">
            <div class="dw-hero-content">
                <h2>Selamat Datang, <?php echo esc_html($current_user->display_name); ?>! 👋</h2>
                <p>
                    <?php if ($is_super_admin): ?>
                        Memantau performa ekosistem <strong>Global (Semua Desa)</strong>.
                    <?php else: ?>
                        Memantau performa <strong>Desa Anda</strong>.
                    <?php endif; ?>
                </p>
            </div>
            <div class="dw-hero-date">
                <span>HARI INI</span>
                <strong><?php echo date_i18n('l, d F Y'); ?></strong>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="dw-stats-grid">
            
            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-blue">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <h3 class="dw-stat-value">Rp <?php echo number_format($omset, 0, ',', '.'); ?></h3>
                    <div class="dw-stat-label">Omset <?php echo $is_super_admin ? 'Global' : 'Desa'; ?></div>
                </div>
            </div>

            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-green">
                        <span class="dashicons dashicons-location"></span>
                    </div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_desa); ?></h3>
                    <div class="dw-stat-label"><?php echo $is_super_admin ? 'Total Desa' : 'Status Desa Aktif'; ?></div>
                </div>
            </div>

            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-teal">
                        <span class="dashicons dashicons-palmtree"></span>
                    </div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_wisata); ?></h3>
                    <div class="dw-stat-label">Objek Wisata</div>
                </div>
            </div>

            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-purple">
                        <span class="dashicons dashicons-store"></span>
                    </div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_pedagang); ?></h3>
                    <div class="dw-stat-label">Toko / UMKM</div>
                </div>
            </div>

            <div class="dw-stat-card">
                <div>
                    <div class="dw-stat-icon-wrapper bg-orange">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <h3 class="dw-stat-value"><?php echo number_format($count_produk); ?></h3>
                    <div class="dw-stat-label">Produk Aktif</div>
                </div>
            </div>

        </div>

        <!-- SPLIT CONTENT -->
        <div class="dw-dashboard-split">
            
            <!-- Left: Chart -->
            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h3>Grafik Pendapatan (7 Hari Terakhir)</h3>
                </div>
                <div class="dw-panel-body">
                    <div class="dw-css-chart">
                        <?php foreach ($chart_data as $data): 
                            $height = ($data['value'] / $max_value) * 100;
                            $bg_color = ($data['value'] > 0) ? '#2563eb' : '#e2e8f0'; 
                        ?>
                            <div class="dw-chart-column">
                                <div class="dw-chart-tooltip">
                                    <?php echo $data['full_label']; ?>: Rp <?php echo number_format($data['value'], 0, ',', '.'); ?>
                                </div>
                                <div class="dw-chart-bar" style="height: <?php echo max(4, $height); ?>%; background-color: <?php echo $bg_color; ?>;"></div>
                                <div class="dw-chart-label"><?php echo $data['label']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Recent Activity -->
            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h3>Transaksi Terakhir</h3>
                    <!-- Link diperbaiki: ke halaman pesanan pedagang jika admin desa, atau logs jika super admin -->
                    <?php $link_all = $is_super_admin ? '?page=dw-logs' : '?page=dw-pedagang'; ?> 
                    <!-- Note: Admin desa sebenarnya butuh halaman "Laporan Transaksi Desa" yang belum ada, jadi kita arahkan ke pedagang dulu atau disable -->
                </div>
                <div class="dw-panel-body" style="padding-top:0; padding-bottom:0;">
                    <div class="dw-activity-list">
                        <?php if (empty($recent_orders)): ?>
                            <p style="padding:20px; text-align:center; color:#94a3b8;">Belum ada data transaksi.</p>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <span class="dashicons dashicons-cart"></span>
                                    </div>
                                    <div class="activity-info">
                                        <h4>#<?php echo esc_html($order->kode_unik); ?></h4>
                                        <p>
                                            <?php 
                                            // Tampilkan nama pembeli (Super Admin) atau nama toko (Admin Desa)
                                            if ($is_super_admin) {
                                                echo esc_html($order->pembeli ?: 'Guest');
                                            } else {
                                                echo esc_html($order->nama_toko);
                                            }
                                            ?> 
                                            &bull; <?php echo human_time_diff(strtotime($order->created_at)); ?> lalu
                                        </p>
                                    </div>
                                    <div class="activity-meta">
                                        <span class="activity-amount">Rp <?php echo number_format($order->total_bayar, 0, ',', '.'); ?></span>
                                        <?php 
                                        // Mapping status class
                                        $st = $is_super_admin ? $order->status_pembayaran : $order->status_transaksi;
                                        $status_class = 'badge-pending';
                                        if (in_array($st, ['paid', 'lunas', 'selesai'])) $status_class = 'badge-paid';
                                        if (in_array($st, ['failed', 'dibatalkan'])) $status_class = 'badge-failed';
                                        ?>
                                        <span class="activity-badge <?php echo $status_class; ?>">
                                            <?php echo strtoupper(str_replace('_', ' ', $st)); ?>
                                        </span>
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