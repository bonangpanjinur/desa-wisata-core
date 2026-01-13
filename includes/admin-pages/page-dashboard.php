<?php
/**
 * File Name:   page-dashboard.php
 * File Folder: includes/admin-pages/
 * Description: Dashboard Admin Premium dengan Desain UI Modern (SaaS Style).
 * Disesuaikan dengan Database Schema v3.7+ (activation.php).
 * Update: Refactored to use standardized UI components and external CSS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin-ui-components.php';

function dw_dashboard_page_render() {
    global $wpdb;
    $context = dw_get_admin_context();
    $current_user = $context['current_user'];
    $user_id      = $context['user_id'];
    $role_context = $context['role_context'];
    $context_id   = $context['context_id'];

    // --- 1. SETUP TABEL DATABASE ---
    $t_desa        = $wpdb->prefix . 'dw_desa';
    $t_pedagang    = $wpdb->prefix . 'dw_pedagang';
    $t_produk      = $wpdb->prefix . 'dw_produk';
    $t_wisata      = $wpdb->prefix . 'dw_wisata';
    $t_transaksi   = $wpdb->prefix . 'dw_transaksi';
    $t_sub         = $wpdb->prefix . 'dw_transaksi_sub';
    $t_verifikator = $wpdb->prefix . 'dw_verifikator';
    $t_ojek        = $wpdb->prefix . 'dw_ojek';
    $t_pembeli     = $wpdb->prefix . 'dw_pembeli';

    // --- 2. IDENTIFIKASI ROLE & CONTEXT ---
    $role_context = 'guest';
    $context_id   = 0;

    if ( current_user_can('administrator') ) {
        $role_context = 'super_admin';
    } else {
        $desa = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $t_desa WHERE id_user_desa = %d", $user_id) );
        if ( $desa ) {
            $role_context = 'admin_desa';
            $context_id   = $desa->id;
        } else {
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
    if ( $role_context === 'super_admin' ) {
        $stats['income']       = $wpdb->get_var("SELECT SUM(total_transaksi) FROM $t_transaksi WHERE status_transaksi = 'selesai'");
        $stats['orders_count'] = $wpdb->get_var("SELECT COUNT(id) FROM $t_transaksi");
        $stats['desa']         = $wpdb->get_var("SELECT COUNT(id) FROM $t_desa WHERE status = 'aktif'");
        $stats['wisata']       = $wpdb->get_var("SELECT COUNT(id) FROM $t_wisata WHERE status = 'aktif'");
        $stats['pedagang']     = $wpdb->get_var("SELECT COUNT(id) FROM $t_pedagang WHERE status_akun = 'aktif'");
        $stats['verifikator']  = $wpdb->get_var("SELECT COUNT(id) FROM $t_verifikator WHERE status = 'aktif'");
        $stats['produk']       = $wpdb->get_var("SELECT COUNT(id) FROM $t_produk WHERE status = 'aktif'");
        $stats['ojek']         = $wpdb->get_var("SELECT COUNT(id) FROM $t_ojek WHERE status_pendaftaran = 'disetujui'");
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$t_pembeli'") == $t_pembeli) {
            $stats['pembeli'] = $wpdb->get_var("SELECT COUNT(id) FROM $t_pembeli");
        } else {
            $stats['pembeli'] = count(get_users(['role' => 'subscriber']));
        }

        $recent_orders = $wpdb->get_results("SELECT id, kode_unik, nama_penerima, total_transaksi, status_transaksi, created_at FROM $t_transaksi ORDER BY created_at DESC LIMIT 5");

    } elseif ( $role_context === 'admin_desa' ) {
        $stats['income']       = $wpdb->get_var( $wpdb->prepare("SELECT SUM(s.total_pesanan_toko) FROM $t_sub s JOIN $t_pedagang p ON s.id_pedagang = p.id WHERE p.id_desa = %d AND s.status_pesanan = 'selesai'", $context_id) );
        $stats['orders_count'] = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(s.id) FROM $t_sub s JOIN $t_pedagang p ON s.id_pedagang = p.id WHERE p.id_desa = %d", $context_id) );
        $stats['desa']         = 1;
        $stats['wisata']       = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM $t_wisata WHERE id_desa = %d AND status = 'aktif'", $context_id) );
        $stats['pedagang']     = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM $t_pedagang WHERE id_desa = %d AND status_akun = 'aktif'", $context_id) );
        $stats['produk']       = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(prod.id) FROM $t_produk prod JOIN $t_pedagang ped ON prod.id_pedagang = ped.id WHERE ped.id_desa = %d AND prod.status = 'aktif'", $context_id) );
        $stats['pembeli']      = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(DISTINCT t.id_pembeli) FROM $t_transaksi t JOIN $t_sub s ON s.id_transaksi = t.id JOIN $t_pedagang p ON s.id_pedagang = p.id WHERE p.id_desa = %d", $context_id) );
        $recent_orders = $wpdb->get_results( $wpdb->prepare("SELECT s.id, t.kode_unik, s.nama_toko as nama_penerima, s.total_pesanan_toko as total_transaksi, s.status_pesanan as status_transaksi, s.created_at FROM $t_sub s JOIN $t_transaksi t ON s.id_transaksi = t.id JOIN $t_pedagang p ON s.id_pedagang = p.id WHERE p.id_desa = %d ORDER BY s.created_at DESC LIMIT 5", $context_id) );

    } elseif ( $role_context === 'pedagang' ) {
        $stats['income']       = $wpdb->get_var( $wpdb->prepare("SELECT SUM(total_pesanan_toko) FROM $t_sub WHERE id_pedagang = %d AND status_pesanan = 'selesai'", $context_id) );
        $stats['orders_count'] = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM $t_sub WHERE id_pedagang = %d", $context_id) );
        $stats['produk']       = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM $t_produk WHERE id_pedagang = %d AND status = 'aktif'", $context_id) );
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$t_pembeli'") == $t_pembeli) {
             $stats['pembeli'] = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM (SELECT id_user as user_id FROM $t_pembeli WHERE referrer_id = %d AND referrer_type = 'pedagang' UNION SELECT t.id_pembeli as user_id FROM $t_transaksi t JOIN $t_sub s ON s.id_transaksi = t.id WHERE s.id_pedagang = %d) as combined_users", $context_id, $context_id) );
        } else {
             $stats['pembeli'] = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(DISTINCT t.id_pembeli) FROM $t_transaksi t JOIN $t_sub s ON s.id_transaksi = t.id WHERE s.id_pedagang = %d", $context_id) );
        }
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
    $role_bg_color = '#f1f5f9';
    $role_text_color = '#475569';
    
    if($role_context == 'super_admin') { 
        $role_label = 'Administrator Pusat'; 
        $role_bg_color = '#eff6ff';
        $role_text_color = '#2563eb';
    }
    elseif($role_context == 'admin_desa') { 
        $role_label = 'Admin Desa'; 
        $role_bg_color = '#ecfdf5';
        $role_text_color = '#059669';
    }
    elseif($role_context == 'pedagang') { 
        $role_label = 'Mitra Pedagang'; 
        $role_bg_color = '#fffbeb';
        $role_text_color = '#d97706';
    }

    ?>
    <div class="wrap dw-wrap">
        
        <!-- Header Section -->
        <div class="dw-card" style="margin-bottom: 30px;">
            <div class="dw-card-body" style="display: flex; align-items: center; gap: 24px;">
                <div class="dw-user-avatar">
                    <?php echo get_avatar($user_id, 80, '', '', ['class' => 'dw-avatar-img']); ?>
                </div>
                <div class="dw-welcome-text">
                    <h1 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700;"><?php echo $greeting . ', ' . esc_html($current_user->display_name); ?> 👋</h1>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <span class="dw-badge" style="background-color: <?php echo $role_bg_color; ?>; color: <?php echo $role_text_color; ?>;">
                            <?php echo $role_label; ?>
                        </span>
                        <span class="dw-badge status-neutral">
                            <span class="dashicons dashicons-calendar-alt" style="font-size: 14px; margin-right: 5px;"></span> <?php echo date_i18n('l, d F Y'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dw-section-title">Ringkasan Statistik</div>
        <div class="dw-stats-grid">
            <?php 
            dw_admin_render_stat_card('dashicons-money', 'bg-green', $income_formatted, 'Total Pendapatan');
            dw_admin_render_stat_card('dashicons-cart', 'bg-blue', number_format($stats['orders_count']), 'Total Pesanan');
            dw_admin_render_stat_card('dashicons-groups', 'bg-purple', number_format($stats['pembeli']), 'Total Pembeli');
            dw_admin_render_stat_card('dashicons-archive', 'bg-orange', number_format($stats['produk']), 'Produk Aktif');
            
            if ($role_context === 'super_admin') {
                dw_admin_render_stat_card('dashicons-admin-site', 'bg-teal', number_format($stats['desa']), 'Desa Aktif');
                dw_admin_render_stat_card('dashicons-store', 'bg-purple', number_format($stats['pedagang']), 'Mitra Pedagang');
            }
            ?>
        </div>

        <div class="dw-dashboard-split">
            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h3>Pesanan Terbaru</h3>
                    <a href="<?php echo admin_url('admin.php?page=dw-manajemen-pesanan-pusat'); ?>" class="dw-button-link">Lihat Semua</a>
                </div>
                <div class="dw-panel-body" style="padding: 0;">
                    <?php
                    $headers = ['Kode Unik', 'Penerima', 'Total', 'Status', 'Tanggal'];
                    $rows = [];
                    foreach ($recent_orders as $order) {
                        $status_class = 'status-neutral';
                        if ($order->status_transaksi === 'selesai') $status_class = 'status-success';
                        elseif ($order->status_transaksi === 'pending') $status_class = 'status-warning';
                        elseif ($order->status_transaksi === 'proses') $status_class = 'status-info';
                        elseif ($order->status_transaksi === 'batal') $status_class = 'status-danger';

                        $rows[] = [
                            '<code>' . esc_html($order->kode_unik) . '</code>',
                            '<strong>' . esc_html($order->nama_penerima) . '</strong>',
                            'Rp ' . number_format($order->total_transaksi, 0, ',', '.'),
                            '<span class="dw-badge ' . $status_class . '">' . ucfirst($order->status_transaksi) . '</span>',
                            date_i18n('d M Y', strtotime($order->created_at))
                        ];
                    }
                    dw_admin_render_table($headers, $rows);
                    ?>
                </div>
            </div>

            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h3>Aksi Cepat</h3>
                </div>
                <div class="dw-panel-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <a href="<?php echo admin_url('admin.php?page=dw-produk'); ?>" class="quick-item" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 16px; border: 1px solid var(--dw-border-color); border-radius: 12px; background: #f8fafc;">
                            <span class="dashicons dashicons-plus-alt" style="font-size: 24px; margin-bottom: 8px; color: var(--dw-brand-blue);"></span>
                            <span style="font-size: 13px; font-weight: 600; color: var(--dw-text-dark);">Tambah Produk</span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=dw-settings'); ?>" class="quick-item" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 16px; border: 1px solid var(--dw-border-color); border-radius: 12px; background: #f8fafc;">
                            <span class="dashicons dashicons-admin-generic" style="font-size: 24px; margin-bottom: 8px; color: var(--dw-text-grey);"></span>
                            <span style="font-size: 13px; font-weight: 600; color: var(--dw-text-dark);">Pengaturan</span>
                        </a>
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <h4 style="font-size: 14px; margin-bottom: 12px;">Info Sistem</h4>
                        <div style="font-size: 13px; color: var(--dw-text-grey);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Versi Plugin</span>
                                <span style="font-weight: 600; color: var(--dw-text-dark);">v3.8.0</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>PHP Version</span>
                                <span style="font-weight: 600; color: var(--dw-text-dark);"><?php echo phpversion(); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}
