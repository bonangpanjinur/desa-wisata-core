<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * Description: Dashboard Admin Premium dengan Desain UI Modern (SaaS Style).
 * Disesuaikan dengan Database Schema v3.7+ (activation.php).
 * Update: UI/UX Refinement - Layout Grid, Modern Cards, Clean Typography, Responsive Tables.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_dashboard_page_render() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID;

    // --- 1. SETUP TABEL DATABASE ---
    $t_desa        = $wpdb->prefix . 'dw_desa';
    $t_pedagang    = $wpdb->prefix . 'dw_pedagang';
    $t_produk      = $wpdb->prefix . 'dw_produk';
    $t_wisata      = $wpdb->prefix . 'dw_wisata';
    $t_transaksi   = $wpdb->prefix . 'dw_transaksi';     // Transaksi Induk
    $t_sub         = $wpdb->prefix . 'dw_transaksi_sub'; // Transaksi Anak
    $t_verifikator = $wpdb->prefix . 'dw_verifikator';
    $t_ojek        = $wpdb->prefix . 'dw_ojek';

    // --- 2. IDENTIFIKASI ROLE & CONTEXT ---
    $role_context = 'guest';
    $context_id   = 0; // ID Desa atau ID Pedagang

    if ( current_user_can('administrator') ) {
        $role_context = 'super_admin';
    } else {
        // Cek apakah Admin Desa
        $desa = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $t_desa WHERE id_user_desa = %d", $user_id) );
        if ( $desa ) {
            $role_context = 'admin_desa';
            $context_id   = $desa->id;
        } else {
            // Cek apakah Pedagang
            $pedagang = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $t_pedagang WHERE id_user = %d", $user_id) );
            if ( $pedagang ) {
                $role_context = 'pedagang';
                $context_id   = $pedagang->id;
            }
        }
    }

    // --- 3. INISIALISASI STATISTIK ---
    $stats = [
        'income'       => 0,
        'orders_count' => 0,
        'desa'         => 0,
        'wisata'       => 0,
        'pedagang'     => 0,
        'verifikator'  => 0,
        'produk'       => 0,
        'pembeli'      => 0,
        'ojek'         => 0,
    ];

    // --- 4. HITUNG STATISTIK (Berdasarkan Role) ---
    // Logika perhitungan tetap sama, hanya presentasi yang berubah

    if ( $role_context === 'super_admin' ) {
        // --- GLOBAL STATS ---
        $stats['income']       = $wpdb->get_var("SELECT SUM(total_transaksi) FROM $t_transaksi WHERE status_transaksi = 'selesai'");
        $stats['orders_count'] = $wpdb->get_var("SELECT COUNT(id) FROM $t_transaksi");
        $stats['desa']         = $wpdb->get_var("SELECT COUNT(id) FROM $t_desa WHERE status = 'aktif'");
        $stats['wisata']       = $wpdb->get_var("SELECT COUNT(id) FROM $t_wisata WHERE status = 'aktif'");
        $stats['pedagang']     = $wpdb->get_var("SELECT COUNT(id) FROM $t_pedagang WHERE status_akun = 'aktif'");
        $stats['verifikator']  = $wpdb->get_var("SELECT COUNT(id) FROM $t_verifikator WHERE status = 'aktif'");
        $stats['produk']       = $wpdb->get_var("SELECT COUNT(id) FROM $t_produk WHERE status = 'aktif'");
        $stats['ojek']         = $wpdb->get_var("SELECT COUNT(id) FROM $t_ojek WHERE status_pendaftaran = 'disetujui'");
        $stats['pembeli']      = $wpdb->get_var("SELECT COUNT(DISTINCT id_pembeli) FROM $t_transaksi");

        $recent_orders = $wpdb->get_results("SELECT id, kode_unik, nama_penerima, total_transaksi, status_transaksi, created_at FROM $t_transaksi ORDER BY created_at DESC LIMIT 5");

    } elseif ( $role_context === 'admin_desa' ) {
        // --- DESA STATS ---
        $stats['income']       = $wpdb->get_var( $wpdb->prepare("SELECT SUM(s.total_pesanan_toko) FROM $t_sub s JOIN $t_pedagang p ON s.id_pedagang = p.id WHERE p.id_desa = %d AND s.status_pesanan = 'selesai'", $context_id) );
        $stats['orders_count'] = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(s.id) FROM $t_sub s JOIN $t_pedagang p ON s.id_pedagang = p.id WHERE p.id_desa = %d", $context_id) );
        $stats['desa']         = 1;
        $stats['wisata']       = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM $t_wisata WHERE id_desa = %d AND status = 'aktif'", $context_id) );
        $stats['pedagang']     = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM $t_pedagang WHERE id_desa = %d AND status_akun = 'aktif'", $context_id) );
        $stats['produk']       = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(prod.id) FROM $t_produk prod JOIN $t_pedagang ped ON prod.id_pedagang = ped.id WHERE ped.id_desa = %d AND prod.status = 'aktif'", $context_id) );
        $stats['pembeli']      = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(DISTINCT t.id_pembeli) FROM $t_transaksi_sub s JOIN $t_transaksi t ON s.id_transaksi = t.id JOIN $t_pedagang p ON s.id_pedagang = p.id WHERE p.id_desa = %d", $context_id) );

        $recent_orders = $wpdb->get_results( $wpdb->prepare("SELECT s.id, t.kode_unik, s.nama_toko, s.total_pesanan_toko as total_transaksi, s.status_pesanan as status_transaksi, s.created_at FROM $t_sub s JOIN $t_transaksi t ON s.id_transaksi = t.id JOIN $t_pedagang p ON s.id_pedagang = p.id WHERE p.id_desa = %d ORDER BY s.created_at DESC LIMIT 5", $context_id) );

    } elseif ( $role_context === 'pedagang' ) {
        // --- PEDAGANG STATS ---
        $stats['income']       = $wpdb->get_var( $wpdb->prepare("SELECT SUM(total_pesanan_toko) FROM $t_sub WHERE id_pedagang = %d AND status_pesanan = 'selesai'", $context_id) );
        $stats['orders_count'] = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM $t_sub WHERE id_pedagang = %d", $context_id) );
        $stats['produk']       = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM $t_produk WHERE id_pedagang = %d AND status = 'aktif'", $context_id) );
        $stats['pembeli']      = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(DISTINCT t.id_pembeli) FROM $t_transaksi_sub s JOIN $t_transaksi t ON s.id_transaksi = t.id WHERE s.id_pedagang = %d", $context_id) );

        $recent_orders = $wpdb->get_results( $wpdb->prepare("SELECT s.id, t.kode_unik, t.nama_penerima, s.total_pesanan_toko as total_transaksi, s.status_pesanan as status_transaksi, s.created_at FROM $t_sub s JOIN $t_transaksi t ON s.id_transaksi = t.id WHERE s.id_pedagang = %d ORDER BY s.created_at DESC LIMIT 5", $context_id) );
    }

    // Formatting
    $income_formatted = 'Rp ' . number_format((float)$stats['income'], 0, ',', '.');
    
    // Greeting Waktu
    $hour = current_time('G');
    if ($hour < 12) { $greeting = "Selamat Pagi"; }
    elseif ($hour < 15) { $greeting = "Selamat Siang"; }
    elseif ($hour < 18) { $greeting = "Selamat Sore"; }
    else { $greeting = "Selamat Malam"; }

    // Role Label
    $role_label = 'Pengunjung';
    $role_badge_color = '#64748b'; // default grey
    
    if($role_context == 'super_admin') { $role_label = 'Administrator Pusat'; $role_badge_color = '#2563eb'; }
    elseif($role_context == 'admin_desa') { $role_label = 'Admin Desa'; $role_badge_color = '#059669'; }
    elseif($role_context == 'pedagang') { $role_label = 'Mitra Pedagang'; $role_badge_color = '#d97706'; }

    ?>
    <div class="wrap dw-dashboard-wrap">
        
        <!-- Header Section with Modern Gradient -->
        <div class="dw-header-section">
            <div class="dw-header-content">
                <div class="dw-user-avatar">
                    <?php echo get_avatar($user_id, 64); ?>
                </div>
                <div class="dw-welcome-text">
                    <h1><?php echo $greeting . ', ' . esc_html($current_user->display_name); ?> 👋</h1>
                    <div class="dw-meta-info">
                        <span class="dw-role-pill" style="background-color: <?php echo $role_badge_color; ?>15; color: <?php echo $role_badge_color; ?>;">
                            <?php echo $role_label; ?>
                        </span>
                        <span class="dw-date-pill">
                            <span class="dashicons dashicons-calendar-alt"></span> <?php echo date_i18n('l, d F Y'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 
            GRID STATISTIK UTAMA 
            Desain Card Baru: Icon + Value + Label dengan layout yang lebih bersih
        -->
        <div class="dw-section-title">Ringkasan Statistik</div>
        <div class="dw-stats-grid">
            
            <!-- 1. PENDAPATAN -->
            <div class="dw-stat-card card-income">
                <div class="card-icon-wrapper">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="card-data">
                    <span class="data-value"><?php echo $income_formatted; ?></span>
                    <span class="data-label">Total Pendapatan</span>
                </div>
            </div>

            <!-- 2. TRANSAKSI -->
            <div class="dw-stat-card card-order">
                <div class="card-icon-wrapper">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <div class="card-data">
                    <span class="data-value"><?php echo number_format($stats['orders_count']); ?></span>
                    <span class="data-label">Transaksi</span>
                </div>
            </div>

            <!-- 3. PEMBELI -->
            <div class="dw-stat-card card-buyer">
                <div class="card-icon-wrapper">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="card-data">
                    <span class="data-value"><?php echo number_format($stats['pembeli']); ?></span>
                    <span class="data-label">Pelanggan</span>
                </div>
            </div>

            <!-- 4. PRODUK -->
            <div class="dw-stat-card card-product">
                <div class="card-icon-wrapper">
                    <span class="dashicons dashicons-products"></span>
                </div>
                <div class="card-data">
                    <span class="data-value"><?php echo number_format($stats['produk']); ?></span>
                    <span class="data-label">Produk Aktif</span>
                </div>
            </div>

            <!-- Kondisional Cards -->
            <?php if ($role_context === 'super_admin'): ?>
                <div class="dw-stat-card card-village">
                    <div class="card-icon-wrapper"><span class="dashicons dashicons-building"></span></div>
                    <div class="card-data">
                        <span class="data-value"><?php echo number_format($stats['desa']); ?></span>
                        <span class="data-label">Desa Wisata</span>
                    </div>
                </div>
                <div class="dw-stat-card card-verifier">
                    <div class="card-icon-wrapper"><span class="dashicons dashicons-id-alt"></span></div>
                    <div class="card-data">
                        <span class="data-value"><?php echo number_format($stats['verifikator']); ?></span>
                        <span class="data-label">Verifikator</span>
                    </div>
                </div>
                <div class="dw-stat-card card-ojek">
                    <div class="card-icon-wrapper"><span class="dashicons dashicons-buddicons-buddypress-logo"></span></div>
                    <div class="card-data">
                        <span class="data-value"><?php echo number_format($stats['ojek']); ?></span>
                        <span class="data-label">Mitra Ojek</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role_context !== 'pedagang'): ?>
                <div class="dw-stat-card card-tourism">
                    <div class="card-icon-wrapper"><span class="dashicons dashicons-location-alt"></span></div>
                    <div class="card-data">
                        <span class="data-value"><?php echo number_format($stats['wisata']); ?></span>
                        <span class="data-label">Objek Wisata</span>
                    </div>
                </div>
                <div class="dw-stat-card card-merchant">
                    <div class="card-icon-wrapper"><span class="dashicons dashicons-store"></span></div>
                    <div class="card-data">
                        <span class="data-value"><?php echo number_format($stats['pedagang']); ?></span>
                        <span class="data-label">Pedagang</span>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <!-- End Stats Grid -->

        <!-- Layout 2 Kolom -->
        <div class="dw-dashboard-layout">
            
            <!-- Kolom Kiri: Transaksi Terbaru -->
            <div class="dw-column-main">
                <div class="dw-card dw-card-table">
                    <div class="dw-card-header">
                        <h3 class="card-heading">
                            <span class="dashicons dashicons-list-view"></span> Transaksi Terbaru
                        </h3>
                        <?php if($role_context == 'super_admin'): ?>
                            <a href="<?php echo admin_url('admin.php?page=dw-transaksi'); ?>" class="dw-btn-link">Lihat Semua &rarr;</a>
                        <?php elseif($role_context == 'pedagang'): ?>
                            <a href="<?php echo admin_url('admin.php?page=dw-pesanan-pedagang'); ?>" class="dw-btn-link">Lihat Semua &rarr;</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dw-table-wrapper">
                        <?php if ( empty($recent_orders) ) : ?>
                            <div class="dw-empty-state">
                                <div class="empty-icon-bg">
                                    <span class="dashicons dashicons-cart"></span>
                                </div>
                                <h4>Belum ada transaksi</h4>
                                <p>Transaksi yang masuk akan muncul di sini secara real-time.</p>
                            </div>
                        <?php else : ?>
                            <table class="dw-modern-table">
                                <thead>
                                    <tr>
                                        <th>ID Order</th>
                                        <th><?php echo ($role_context == 'admin_desa') ? 'Toko' : 'Info Pelanggan'; ?></th>
                                        <th>Tanggal</th>
                                        <th class="text-right">Total</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $recent_orders as $order ) : ?>
                                        <tr>
                                            <td class="col-id">
                                                <span class="order-id">#<?php echo esc_html($order->kode_unik); ?></span>
                                            </td>
                                            <td class="col-info">
                                                <div class="info-primary">
                                                    <?php 
                                                    if ($role_context == 'admin_desa' && isset($order->nama_toko)) {
                                                        echo esc_html($order->nama_toko);
                                                    } else {
                                                        echo esc_html(isset($order->nama_penerima) ? $order->nama_penerima : '-'); 
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="col-date">
                                                <?php echo date_i18n('d M, H:i', strtotime($order->created_at)); ?>
                                            </td>
                                            <td class="col-total text-right">
                                                Rp <?php echo number_format($order->total_transaksi, 0, ',', '.'); ?>
                                            </td>
                                            <td class="col-status text-center">
                                                <?php 
                                                $st = $order->status_transaksi;
                                                $badge_class = 'status-neutral';
                                                
                                                if (in_array($st, ['menunggu_pembayaran', 'menunggu_konfirmasi'])) $badge_class = 'status-warning';
                                                elseif (in_array($st, ['pembayaran_dikonfirmasi', 'diproses', 'dikirim', 'diantar_ojek', 'dalam_perjalanan'])) $badge_class = 'status-processing';
                                                elseif (in_array($st, ['selesai', 'lunas'])) $badge_class = 'status-success';
                                                elseif (in_array($st, ['dibatalkan', 'pembayaran_gagal', 'refunded'])) $badge_class = 'status-danger';
                                                
                                                $status_label = ucwords(str_replace('_', ' ', $st));
                                                ?>
                                                <span class="dw-badge <?php echo $badge_class; ?>">
                                                    <?php echo esc_html($status_label); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Quick Actions & Info -->
            <div class="dw-column-side">
                
                <!-- Quick Actions Grid -->
                <div class="dw-card">
                    <div class="dw-card-header">
                        <h3 class="card-heading"><span class="dashicons dashicons-star-filled"></span> Akses Cepat</h3>
                    </div>
                    <div class="dw-quick-grid">
                        <?php if($role_context == 'pedagang'): ?>
                            <a href="<?php echo admin_url('admin.php?page=dw-produk&action=new'); ?>" class="quick-item">
                                <div class="quick-icon icon-add"><span class="dashicons dashicons-plus"></span></div>
                                <span>Produk Baru</span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=dw-settings'); ?>" class="quick-item">
                                <div class="quick-icon icon-settings"><span class="dashicons dashicons-store"></span></div>
                                <span>Toko Saya</span>
                            </a>
                        <?php elseif($role_context == 'admin_desa'): ?>
                             <a href="<?php echo admin_url('admin.php?page=dw-wisata&action=new'); ?>" class="quick-item">
                                <div class="quick-icon icon-add"><span class="dashicons dashicons-location"></span></div>
                                <span>Wisata Baru</span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=dw-verifikasi-pedagang'); ?>" class="quick-item">
                                <div class="quick-icon icon-check"><span class="dashicons dashicons-yes"></span></div>
                                <span>Verifikasi</span>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo admin_url('admin.php?page=dw-desa'); ?>" class="quick-item">
                                <div class="quick-icon icon-blue"><span class="dashicons dashicons-building"></span></div>
                                <span>Data Desa</span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=dw-verifikator-list'); ?>" class="quick-item">
                                <div class="quick-icon icon-purple"><span class="dashicons dashicons-id-alt"></span></div>
                                <span>Verifikator</span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=dw-settings'); ?>" class="quick-item">
                                <div class="quick-icon icon-gray"><span class="dashicons dashicons-gear"></span></div>
                                <span>Pengaturan</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Info (Clean List) -->
                <?php if($role_context == 'super_admin'): ?>
                <div class="dw-card">
                    <div class="dw-card-header">
                        <h3 class="card-heading"><span class="dashicons dashicons-performance"></span> Sistem</h3>
                    </div>
                    <div class="dw-system-info">
                        <div class="sys-item">
                            <span class="sys-label">DB Version</span>
                            <span class="sys-val">v<?php echo get_option('dw_core_db_version', '1.0'); ?></span>
                        </div>
                        <div class="sys-item">
                            <span class="sys-label">PHP</span>
                            <span class="sys-val"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="sys-item">
                            <span class="sys-label">Memory</span>
                            <span class="sys-val"><?php echo ini_get('memory_limit'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Enhanced CSS Style -->
    <style>
        /* Base Reset & Fonts */
        .dw-dashboard-wrap {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 1280px;
            margin: 20px auto 0;
            padding-right: 20px;
        }
        .dw-dashboard-wrap * { box-sizing: border-box; }

        /* HEADER SECTION */
        .dw-header-section {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        .dw-header-content { display: flex; align-items: center; gap: 20px; }
        .dw-user-avatar img { border-radius: 50%; border: 3px solid #f1f5f9; }
        .dw-welcome-text h1 {
            margin: 0 0 8px 0;
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }
        .dw-meta-info { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .dw-role-pill, .dw-date-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 500;
        }
        .dw-date-pill { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        .dw-date-pill .dashicons { font-size: 16px; width: 16px; height: 16px; }

        /* SECTION TITLE */
        .dw-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 16px;
        }

        /* STATS GRID */
        .dw-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .dw-stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dw-stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08); }
        
        /* Card Icons with Gradients */
        .card-icon-wrapper {
            width: 56px; height: 56px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .card-icon-wrapper .dashicons { font-size: 28px; width: 28px; height: 28px; color: #fff; }

        /* Card Colors */
        .card-income .card-icon-wrapper { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .card-order .card-icon-wrapper { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .card-buyer .card-icon-wrapper { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .card-product .card-icon-wrapper { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .card-village .card-icon-wrapper { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .card-tourism .card-icon-wrapper { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .card-merchant .card-icon-wrapper { background: linear-gradient(135deg, #d946ef 0%, #c026d3 100%); }
        .card-verifier .card-icon-wrapper { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }
        .card-ojek .card-icon-wrapper { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }

        .card-data { display: flex; flex-direction: column; }
        .data-value { font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.2; margin-bottom: 2px; }
        .data-label { font-size: 13px; color: #64748b; font-weight: 500; }

        /* LAYOUT COLUMNS */
        .dw-dashboard-layout { display: grid; grid-template-columns: 2.5fr 1fr; gap: 24px; }
        @media(max-width: 1024px) { .dw-dashboard-layout { grid-template-columns: 1fr; } }

        /* CARDS GENERAL */
        .dw-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 24px; }
        .dw-card-header {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-heading { margin: 0; font-size: 16px; font-weight: 600; color: #334155; display: flex; align-items: center; gap: 8px; }
        .dw-btn-link { font-size: 13px; font-weight: 500; color: #3b82f6; text-decoration: none; }
        .dw-btn-link:hover { text-decoration: underline; }

        /* TABLE STYLING */
        .dw-table-wrapper { width: 100%; overflow-x: auto; }
        .dw-modern-table { width: 100%; border-collapse: collapse; text-align: left; }
        .dw-modern-table th { padding: 12px 24px; background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        .dw-modern-table td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; }
        .dw-modern-table tr:last-child td { border-bottom: none; }
        .dw-modern-table tr:hover td { background: #fcfcfc; }
        
        .order-id { font-family: monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; font-size: 13px; }
        .info-primary { font-weight: 500; color: #0f172a; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* BADGES */
        .dw-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .status-neutral { background: #f1f5f9; color: #64748b; }
        .status-warning { background: #fffbeb; color: #b45309; }
        .status-processing { background: #eff6ff; color: #1d4ed8; }
        .status-success { background: #f0fdf4; color: #15803d; }
        .status-danger { background: #fef2f2; color: #b91c1c; }

        /* QUICK ACTIONS GRID */
        .dw-quick-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 20px; }
        .quick-item {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 16px 10px; text-decoration: none; color: #475569; font-size: 13px; font-weight: 500;
            transition: all 0.2s;
        }
        .quick-item:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; transform: translateY(-2px); }
        .quick-icon {
            width: 36px; height: 36px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .quick-icon .dashicons { font-size: 20px; width: 20px; height: 20px; }
        
        .icon-add { background: #dbeafe; color: #2563eb; }
        .icon-settings { background: #f1f5f9; color: #475569; }
        .icon-check { background: #dcfce7; color: #16a34a; }
        .icon-blue { background: #e0f2fe; color: #0284c7; }
        .icon-purple { background: #f3e8ff; color: #7e22ce; }
        .icon-gray { background: #f3f4f6; color: #4b5563; }

        /* SYSTEM INFO */
        .dw-system-info { padding: 0; }
        .sys-item { display: flex; justify-content: space-between; padding: 12px 24px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .sys-item:last-child { border-bottom: none; }
        .sys-label { color: #64748b; }
        .sys-val { font-weight: 600; color: #334155; }

        /* EMPTY STATE */
        .dw-empty-state { padding: 40px 20px; text-align: center; color: #94a3b8; }
        .empty-icon-bg { 
            width: 64px; height: 64px; background: #f8fafc; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
        }
        .empty-icon-bg .dashicons { font-size: 32px; width: 32px; height: 32px; color: #cbd5e1; }
        .dw-empty-state h4 { margin: 0 0 6px 0; color: #334155; font-size: 16px; }
        .dw-empty-state p { margin: 0; font-size: 13px; color: #64748b; }

    </style>
    <?php
}
?>