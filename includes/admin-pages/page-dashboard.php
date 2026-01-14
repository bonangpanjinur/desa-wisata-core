<?php
/**
 * Halaman Dashboard Utama (Optimized with Caching)
 * Path: includes/admin-pages/page-dashboard.php
 * Description: Menampilkan ringkasan statistik sistem dengan performa tinggi.
 * Version: 2.5.0
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render Halaman Dashboard
 */
function dw_dashboard_page_render() {
    // 1. Security Check
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.', 'desa-wisata-core'));
    }

    global $wpdb;
    
    // --- CACHING LOGIC ---
    // Kita simpan statistik dalam transient selama 1 jam (3600 detik)
    // Kunci cache unik
    $cache_key = 'dw_admin_dashboard_stats';
    $stats = get_transient($cache_key);

    // Jika cache tidak ada (false), lakukan query database
    if ($stats === false) {
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        $table_transaksi = $wpdb->prefix . 'dw_transaksi';
        $table_desa = $wpdb->prefix . 'dw_desa';

        // Query Berat 1: Total Pedagang
        $total_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM $table_pedagang");
        
        // Query Berat 2: Total Desa
        $total_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");

        // Query Berat 3: Total Transaksi Sukses
        // Menggunakan index 'status' yang sudah kita optimasi
        $total_omset = $wpdb->get_var("SELECT SUM(total_amount) FROM $table_transaksi WHERE status = 'completed'");
        
        // Query Berat 4: Transaksi Hari Ini
        $today = date('Y-m-d');
        $trx_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_transaksi WHERE DATE(created_at) = %s", 
            $today
        ));

        // Simpan dalam array
        $stats = [
            'total_pedagang' => $total_pedagang ? $total_pedagang : 0,
            'total_desa'     => $total_desa ? $total_desa : 0,
            'total_omset'    => $total_omset ? $total_omset : 0,
            'trx_today'      => $trx_today ? $trx_today : 0,
            'last_updated'   => current_time('mysql')
        ];

        // Set Transient (Cache) - Expire dalam 1 Jam
        set_transient($cache_key, $stats, HOUR_IN_SECONDS);
    }

    // --- RENDER VIEW ---
    ?>
    <div class="wrap dw-admin-wrapper">
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1>Dashboard Utama</h1>
                <p class="dw-subtitle">Ringkasan aktivitas ekosistem Desa Wisata.</p>
            </div>
            <div class="dw-header-actions">
                <!-- Tombol Refresh Cache Manual -->
                <form method="post">
                    <?php wp_nonce_field('dw_refresh_dashboard'); ?>
                    <input type="hidden" name="dw_action" value="refresh_stats">
                    <button type="submit" class="dw-button dw-button-secondary" title="Data diperbarui setiap 1 jam. Klik untuk paksa update.">
                        <span class="dashicons dashicons-update" style="margin-right:5px;"></span> Refresh Data
                    </button>
                </form>
            </div>
        </div>

        <!-- Notifikasi Cache Status -->
        <div class="notice notice-info inline" style="margin: 0 0 20px 0; border-left-color: var(--dw-brand-blue);">
            <p>
                <span class="dashicons dashicons-clock" style="color: var(--dw-brand-blue); vertical-align: middle;"></span> 
                Data terakhir diperbarui: <strong><?php echo esc_html(date_i18n('d M Y, H:i', strtotime($stats['last_updated']))); ?></strong>.
                (Cache aktif selama 1 jam untuk performa).
            </p>
        </div>

        <!-- STATS GRID -->
        <div class="dw-stats-grid">
            <!-- Card 1: Pedagang -->
            <div class="dw-stat-card">
                <div class="dw-stat-icon-wrapper bg-blue">
                    <span class="dashicons dashicons-store"></span>
                </div>
                <div class="dw-stat-info">
                    <span class="dw-stat-label">Total Pedagang</span>
                    <h4 class="dw-stat-value"><?php echo esc_html(number_format($stats['total_pedagang'])); ?></h4>
                </div>
            </div>

            <!-- Card 2: Desa -->
            <div class="dw-stat-card">
                <div class="dw-stat-icon-wrapper bg-green">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                </div>
                <div class="dw-stat-info">
                    <span class="dw-stat-label">Desa Terdaftar</span>
                    <h4 class="dw-stat-value"><?php echo esc_html(number_format($stats['total_desa'])); ?></h4>
                </div>
            </div>

            <!-- Card 3: Omset -->
            <div class="dw-stat-card">
                <div class="dw-stat-icon-wrapper bg-purple">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="dw-stat-info">
                    <span class="dw-stat-label">Total Omset Sistem</span>
                    <h4 class="dw-stat-value">Rp <?php echo esc_html(number_format($stats['total_omset'], 0, ',', '.')); ?></h4>
                </div>
            </div>

            <!-- Card 4: Transaksi Hari Ini -->
            <div class="dw-stat-card">
                <div class="dw-stat-icon-wrapper bg-orange">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <div class="dw-stat-info">
                    <span class="dw-stat-label">Transaksi Hari Ini</span>
                    <h4 class="dw-stat-value"><?php echo esc_html(number_format($stats['trx_today'])); ?></h4>
                </div>
            </div>
        </div>

        <!-- SECTION: LOG AKTIVITAS TERBARU (Real-time, tidak dicache) -->
        <div class="dw-grid-2-col">
            <div class="dw-card">
                <div class="dw-card-header">
                    <h3 class="card-heading">Aktivitas Terbaru</h3>
                </div>
                <div class="dw-card-body">
                    <?php 
                    // Ambil log terbaru (limit 5) langsung dari DB agar admin tau realtime action
                    $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dw_logs ORDER BY id DESC LIMIT 5");
                    
                    if($logs): ?>
                        <ul class="dw-activity-list">
                            <?php foreach($logs as $log): 
                                $user = get_userdata($log->user_id);
                                $name = $user ? $user->display_name : 'System';
                            ?>
                            <li class="activity-item" style="border-bottom:1px solid #eee; padding:10px 0;">
                                <div class="activity-icon" style="background:#f1f5f9; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:15px;">
                                    <span class="dashicons dashicons-clock" style="font-size:16px; color:#64748b;"></span>
                                </div>
                                <div class="activity-info">
                                    <h4 style="margin:0; font-size:14px;"><?php echo esc_html($log->activity); ?></h4>
                                    <p style="margin:2px 0 0; font-size:12px; color:#64748b;">
                                        Oleh: <strong><?php echo esc_html($name); ?></strong> &bull; 
                                        <?php echo human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' lalu'; ?>
                                    </p>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color:#64748b; text-align:center;">Belum ada aktivitas tercatat.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Placeholder Grafik (Bisa dikembangkan dengan Chart.js nanti) -->
            <div class="dw-card">
                <div class="dw-card-header">
                    <h3 class="card-heading">Pertumbuhan Transaksi (30 Hari)</h3>
                </div>
                <div class="dw-card-body" style="display:flex; align-items:center; justify-content:center; height:200px; background:#f8fafc;">
                    <p style="color:#94a3b8;">Grafik akan tersedia setelah data transaksi cukup.</p>
                </div>
            </div>
        </div>

    </div>
    <?php
}

/**
 * Handle Refresh Cache Manual
 */
function dw_handle_dashboard_refresh() {
    if (isset($_POST['dw_action']) && $_POST['dw_action'] == 'refresh_stats') {
        check_admin_referer('dw_refresh_dashboard');
        if (current_user_can('manage_options')) {
            delete_transient('dw_admin_dashboard_stats');
            wp_redirect(remove_query_arg('dw_action')); // Redirect agar tidak resubmit form
            exit;
        }
    }
}
add_action('admin_init', 'dw_handle_dashboard_refresh');