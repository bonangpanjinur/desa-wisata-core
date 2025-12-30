<?php
/**
 * File Name:   includes/admin-pages/page-promosi.php
 * Description: Manajemen Iklan Terpusat (Request & Pengaturan Paket) dengan UI/UX Premium.
 */

if (!defined('ABSPATH')) exit;

function dw_promosi_page_render() {
    global $wpdb;
    $table_promosi = $wpdb->prefix . 'dw_promosi';
    $table_users   = $wpdb->users;
    
    // --- SAVE SETTINGS (Pengaturan Harga & Paket) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_settings'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_ad_settings_action')) {
            echo '<div class="dw-alert dw-alert-error"><div class="alert-icon"><span class="dashicons dashicons-warning"></span></div><div class="alert-content"><p>Keamanan tidak valid.</p></div><button class="dw-alert-dismiss">&times;</button></div>';
        } else {
            // 1. Simpan Setting Promosi Item (Highlight)
            $item_settings = [
                'price_per_day' => floatval($_POST['item_price']),
                'max_quota'     => intval($_POST['item_quota']),
                'default_days'  => intval($_POST['item_days']),
                'active'        => isset($_POST['item_active']) ? 1 : 0
            ];
            update_option('dw_ad_item_settings', $item_settings);

            // 2. Simpan Paket Iklan Banner (Array of Packages)
            $banner_packages = [];
            if (isset($_POST['ad_packages']) && is_array($_POST['ad_packages'])) {
                foreach ($_POST['ad_packages'] as $key => $pkg) {
                    if (!empty($pkg['name'])) {
                        $banner_packages[] = [
                            'id'    => sanitize_title($pkg['name']),
                            'name'  => sanitize_text_field($pkg['name']),
                            'days'  => intval($pkg['days']),
                            'price' => floatval($pkg['price']),
                            'quota' => intval($pkg['quota'])
                        ];
                    }
                }
            }
            update_option('dw_ad_banner_packages', $banner_packages);

            echo '<div class="dw-alert dw-alert-success"><div class="alert-icon"><span class="dashicons dashicons-yes"></span></div><div class="alert-content"><p>Pengaturan Iklan & Paket berhasil disimpan.</p></div><button class="dw-alert-dismiss">&times;</button></div>';
        }
    }

    // --- APPROVAL / REJECT LOGIC ---
    if (isset($_GET['action']) && isset($_GET['id']) && check_admin_referer('dw_promo_action')) {
        $promo_id = intval($_GET['id']);
        $new_status = ($_GET['action'] == 'approve') ? 'aktif' : 'ditolak';
        
        $update_data = ['status' => $new_status];
        
        // Jika approve, set tanggal otomatis
        if ($new_status == 'aktif') {
            $promo = $wpdb->get_row($wpdb->prepare("SELECT durasi_hari FROM $table_promosi WHERE id = %d", $promo_id));
            if ($promo) {
                $now = current_time('mysql');
                $end = date('Y-m-d H:i:s', strtotime("+$promo->durasi_hari days", strtotime($now)));
                $update_data['mulai_tanggal'] = $now;
                $update_data['selesai_tanggal'] = $end;
            }
        }

        $wpdb->update($table_promosi, $update_data, ['id' => $promo_id]);
        echo '<div class="dw-alert dw-alert-success"><div class="alert-icon"><span class="dashicons dashicons-yes"></span></div><div class="alert-content"><p>Status promosi diperbarui menjadi '.ucfirst($new_status).'.</p></div><button class="dw-alert-dismiss">&times;</button></div>';
    }

    // --- GET DATA ---
    $item_settings = get_option('dw_ad_item_settings', ['price_per_day' => 10000, 'max_quota' => 10, 'default_days' => 7, 'active' => 1]);
    $banner_packages = get_option('dw_ad_banner_packages', []);

    $per_page = 10;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

    $where_sql = "WHERE 1=1";
    if (!empty($search)) $where_sql .= $wpdb->prepare(" AND (u.display_name LIKE %s)", "%$search%");
    if (!empty($status_filter)) $where_sql .= $wpdb->prepare(" AND p.status = %s", $status_filter);

    $sql = "SELECT p.*, u.display_name as pemohon_name, 
            CASE 
                WHEN p.tipe = 'produk' THEN (SELECT nama_produk FROM {$wpdb->prefix}dw_produk WHERE id = p.target_id)
                WHEN p.tipe = 'wisata' THEN (SELECT nama_wisata FROM {$wpdb->prefix}dw_wisata WHERE id = p.target_id)
                ELSE 'Banner Umum'
            END as nama_target
            FROM $table_promosi p
            LEFT JOIN $table_users u ON p.pemohon_id = u.ID
            $where_sql 
            ORDER BY FIELD(p.status, 'pending', 'aktif', 'selesai', 'ditolak'), p.created_at DESC 
            LIMIT $per_page OFFSET $offset";
    
    $rows = $wpdb->get_results($sql);
    $total_items = $wpdb->get_var("SELECT COUNT(p.id) FROM $table_promosi p LEFT JOIN $table_users u ON p.pemohon_id = u.ID $where_sql");
    $total_pages = ceil($total_items / $per_page);

    $used_quota_item = $wpdb->get_var("SELECT COUNT(id) FROM $table_promosi WHERE status = 'aktif' AND tipe IN ('produk', 'wisata')");
    $used_quota_banner = $wpdb->get_var("SELECT COUNT(id) FROM $table_promosi WHERE status = 'aktif' AND tipe = 'banner'");
    
    // Perhitungan persentase kuota untuk progress bar
    $quota_percent_item = ($item_settings['max_quota'] > 0) ? min(100, ($used_quota_item / $item_settings['max_quota']) * 100) : 0;
    ?>

    <div class="wrap dw-admin-wrapper">
        <div class="dw-header">
            <div class="dw-header-title">
                <div class="dw-icon-box bg-gradient-purple">
                    <span class="dashicons dashicons-megaphone"></span>
                </div>
                <div>
                    <h1>Pusat Promosi</h1>
                    <p class="subtitle">Kelola iklan sorotan dan banner promosi desa wisata.</p>
                </div>
            </div>
        </div>
        
        <!-- TABS NAVIGATION -->
        <div class="dw-tabs-wrapper">
            <nav class="dw-tabs-nav">
                <a href="#tab-requests" class="dw-tab-link active" onclick="switchTab(event, 'tab-requests')">
                    <span class="dashicons dashicons-list-view"></span> Daftar Permintaan
                </a>
                <a href="#tab-settings" class="dw-tab-link" onclick="switchTab(event, 'tab-settings')">
                    <span class="dashicons dashicons-admin-settings"></span> Pengaturan & Paket
                </a>
            </nav>
        </div>

        <div class="dw-tab-content-container">
            
            <!-- TAB 1: DAFTAR PERMINTAAN -->
            <div id="tab-requests" class="dw-tab-content active">
                
                <!-- Quick Stats / Quota Monitor -->
                <div class="dw-grid-monitor">
                    <!-- Card 1: Slot Iklan Sorotan -->
                    <div class="dw-stat-card dw-card-gradient-1">
                        <div class="card-inner">
                            <div class="card-icon"><span class="dashicons dashicons-star-filled"></span></div>
                            <div class="card-info">
                                <h3>Iklan Sorotan</h3>
                                <div class="card-numbers">
                                    <span class="big-num"><?php echo $used_quota_item; ?></span>
                                    <span class="total-num">/ <?php echo $item_settings['max_quota']; ?> Slot</span>
                                </div>
                            </div>
                        </div>
                        <div class="dw-progress-wrapper">
                            <div class="dw-progress-bar">
                                <div class="bar-fill" style="width: <?php echo $quota_percent_item; ?>%"></div>
                            </div>
                            <span class="progress-label"><?php echo $quota_percent_item; ?>% Terisi</span>
                        </div>
                    </div>

                    <!-- Card 2: Slot Banner -->
                    <div class="dw-stat-card dw-card-gradient-2">
                        <div class="card-inner">
                            <div class="card-icon"><span class="dashicons dashicons-images-alt2"></span></div>
                            <div class="card-info">
                                <h3>Iklan Banner</h3>
                                <div class="card-numbers">
                                    <span class="big-num"><?php echo $used_quota_banner ? $used_quota_banner : 0; ?></span>
                                    <span class="total-num">Tayang</span>
                                </div>
                            </div>
                        </div>
                        <div class="dw-progress-wrapper">
                            <div class="dw-progress-bar blue">
                                <div class="bar-fill" style="width: 50%"></div>
                            </div>
                            <span class="progress-label">Status Tayang</span>
                        </div>
                    </div>
                </div>

                <!-- Filters & Search -->
                <div class="dw-toolbar-modern">
                    <div class="dw-filter-pills">
                        <a href="?page=dw-promosi" class="filter-pill <?php echo empty($status_filter) ? 'active' : ''; ?>">Semua</a>
                        <a href="?page=dw-promosi&status_filter=pending" class="filter-pill <?php echo $status_filter=='pending' ? 'active' : ''; ?>">
                            <span class="dot orange"></span> Menunggu
                        </a>
                        <a href="?page=dw-promosi&status_filter=aktif" class="filter-pill <?php echo $status_filter=='aktif' ? 'active' : ''; ?>">
                            <span class="dot green"></span> Tayang
                        </a>
                    </div>
                    <form method="get" class="dw-search-modern">
                        <input type="hidden" name="page" value="dw-promosi">
                        <span class="search-icon dashicons dashicons-search"></span>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari nama pemohon..." class="search-input-field">
                    </form>
                </div>

                <!-- Table -->
                <div class="dw-table-card">
                    <table class="wp-list-table widefat fixed striped dw-table-premium">
                        <thead>
                            <tr>
                                <th width="120">Tanggal</th>
                                <th>Pemohon</th>
                                <th>Detail Iklan</th>
                                <th>Info Biaya</th>
                                <th width="120">Status</th>
                                <th width="140" style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($rows): foreach($rows as $r): 
                                $approve_url = wp_nonce_url("?page=dw-promosi&action=approve&id={$r->id}", 'dw_promo_action');
                                $reject_url = wp_nonce_url("?page=dw-promosi&action=reject&id={$r->id}", 'dw_promo_action');
                            ?>
                            <tr class="<?php echo $r->status == 'pending' ? 'row-highlight' : ''; ?>">
                                <td>
                                    <div class="date-badge">
                                        <span class="day"><?php echo date('d', strtotime($r->created_at)); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($r->created_at)); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo get_avatar($r->pemohon_id, 32); ?>
                                        </div>
                                        <div class="user-details">
                                            <strong><?php echo esc_html($r->pemohon_name); ?></strong>
                                            <span class="meta-id">ID #<?php echo $r->id; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="ad-detail">
                                        <?php if($r->tipe == 'produk'): ?>
                                            <span class="dw-badge-mini blue"><span class="dashicons dashicons-cart"></span> Produk</span>
                                        <?php elseif($r->tipe == 'wisata'): ?>
                                            <span class="dw-badge-mini green"><span class="dashicons dashicons-palmtree"></span> Wisata</span>
                                        <?php else: ?>
                                            <span class="dw-badge-mini purple"><span class="dashicons dashicons-images-alt2"></span> Banner</span>
                                        <?php endif; ?>
                                        <span class="target-name"><?php echo esc_html($r->nama_target ? $r->nama_target : '-'); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="cost-info">
                                        <span class="price">Rp <?php echo number_format($r->biaya, 0, ',', '.'); ?></span>
                                        <span class="duration"><span class="dashicons dashicons-clock"></span> <?php echo $r->durasi_hari; ?> Hari</span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $s_class = 'neutral';
                                        $s_icon = 'minus';
                                        if($r->status == 'aktif') { $s_class = 'success'; $s_icon = 'yes'; }
                                        if($r->status == 'pending') { $s_class = 'warning'; $s_icon = 'clock'; }
                                        if($r->status == 'ditolak') { $s_class = 'danger'; $s_icon = 'no'; }
                                        if($r->status == 'selesai') { $s_class = 'info'; $s_icon = 'flag'; }
                                    ?>
                                    <span class="dw-status-label <?php echo $s_class; ?>">
                                        <span class="dashicons dashicons-<?php echo $s_icon; ?>"></span> <?php echo ucfirst($r->status); ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <?php if($r->status == 'pending'): ?>
                                        <div class="dw-action-group">
                                            <a href="<?php echo $approve_url; ?>" class="dw-btn-action success tooltip" title="Setujui" onclick="return confirm('Setujui iklan ini?');"><span class="dashicons dashicons-yes"></span></a>
                                            <a href="<?php echo $reject_url; ?>" class="dw-btn-action danger tooltip" title="Tolak" onclick="return confirm('Tolak iklan ini?');"><span class="dashicons dashicons-no"></span></a>
                                        </div>
                                    <?php elseif($r->status == 'aktif'): ?>
                                        <span class="text-green small-caps"><span class="dashicons dashicons-visibility"></span> Live</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" class="empty-state">
                                <span class="dashicons dashicons-info"></span>
                                <p>Belum ada permintaan iklan yang ditemukan.</p>
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="dw-pagination-modern">
                        <?php echo paginate_links(['total' => $total_pages, 'current' => $paged, 'prev_text' => '‹', 'next_text' => '›']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: PENGATURAN & PAKET -->
            <div id="tab-settings" class="dw-tab-content" style="display:none;">
                <form method="post">
                    <?php wp_nonce_field('dw_ad_settings_action'); ?>
                    <input type="hidden" name="action_settings" value="save">

                    <div class="dw-settings-layout">
                        
                        <!-- SETTING 1: PROMOSI ITEM (SOROTAN) -->
                        <div class="dw-card-setting">
                            <div class="setting-header">
                                <div class="header-icon yellow"><span class="dashicons dashicons-star-filled"></span></div>
                                <div class="header-text">
                                    <h3>Iklan Sorotan (Populer)</h3>
                                    <p>Iklan yang muncul di halaman depan dan posisi teratas arsip.</p>
                                </div>
                                <div class="header-toggle">
                                    <label class="dw-switch">
                                        <input type="checkbox" name="item_active" value="1" <?php checked($item_settings['active'], 1); ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="setting-body">
                                <div class="dw-input-group">
                                    <label>Harga Per Hari</label>
                                    <div class="input-wrapper">
                                        <span class="prefix">Rp</span>
                                        <input type="number" name="item_price" value="<?php echo esc_attr($item_settings['price_per_day']); ?>" required>
                                    </div>
                                </div>
                                <div class="dw-grid-2">
                                    <div class="dw-input-group">
                                        <label>Kuota Maksimal (Slot)</label>
                                        <input type="number" name="item_quota" value="<?php echo esc_attr($item_settings['max_quota']); ?>" required class="simple-input">
                                    </div>
                                    <div class="dw-input-group">
                                        <label>Durasi Default (Hari)</label>
                                        <input type="number" name="item_days" value="<?php echo esc_attr($item_settings['default_days'] ?? 7); ?>" required class="simple-input">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SETTING 2: PAKET BANNER (REPEATER) -->
                        <div class="dw-card-setting">
                            <div class="setting-header">
                                <div class="header-icon purple"><span class="dashicons dashicons-images-alt2"></span></div>
                                <div class="header-text">
                                    <h3>Paket Iklan Banner</h3>
                                    <p>Kelola paket pilihan untuk iklan di carousel utama.</p>
                                </div>
                            </div>
                            <div class="setting-body">
                                <div class="dw-table-wrapper">
                                    <table class="dw-repeater-table" id="dw-packages-table">
                                        <thead>
                                            <tr>
                                                <th>Nama Paket</th>
                                                <th width="80">Hari</th>
                                                <th width="120">Harga (Rp)</th>
                                                <th width="80">Kuota</th>
                                                <th width="40"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="package-rows">
                                            <?php 
                                            if(!empty($banner_packages)): 
                                                foreach($banner_packages as $i => $pkg): 
                                            ?>
                                            <tr>
                                                <td><input type="text" name="ad_packages[<?php echo $i; ?>][name]" value="<?php echo esc_attr($pkg['name']); ?>" required placeholder="Nama Paket"></td>
                                                <td><input type="number" name="ad_packages[<?php echo $i; ?>][days]" value="<?php echo esc_attr($pkg['days']); ?>" required></td>
                                                <td><input type="number" name="ad_packages[<?php echo $i; ?>][price]" value="<?php echo esc_attr($pkg['price']); ?>" required></td>
                                                <td><input type="number" name="ad_packages[<?php echo $i; ?>][quota]" value="<?php echo esc_attr($pkg['quota']); ?>" required></td>
                                                <td><button type="button" class="dw-btn-icon remove-row text-red"><span class="dashicons dashicons-no-alt"></span></button></td>
                                            </tr>
                                            <?php endforeach; else: ?>
                                            <tr class="empty-row"><td colspan="5">Belum ada paket.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="setting-actions">
                                    <button type="button" class="dw-btn-outline" id="add-package-btn"><span class="dashicons dashicons-plus-alt2"></span> Tambah Paket</button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="dw-form-footer">
                        <button type="submit" class="dw-btn-primary large">Simpan Perubahan</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <style>
        /* === RESET & VARIABLES === */
        :root {
            --dw-primary: #4f46e5; /* Modern Indigo */
            --dw-primary-hover: #4338ca;
            --dw-bg: #f9fafb;
            --dw-card-bg: #ffffff;
            --dw-text-main: #111827;
            --dw-text-sec: #6b7280;
            --dw-border: #e5e7eb;
            --dw-success: #10b981;
            --dw-warning: #f59e0b;
            --dw-danger: #ef4444;
            --dw-info: #3b82f6;
        }
        
        .dw-admin-wrapper { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px 20px 0 0; color: var(--dw-text-main); }
        .dw-admin-wrapper * { box-sizing: border-box; }
        .dw-admin-wrapper h1, .dw-admin-wrapper h2, .dw-admin-wrapper h3 { margin: 0; }

        /* === HEADER === */
        .dw-header { margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; }
        .dw-header-title { display: flex; align-items: center; gap: 15px; }
        .dw-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2); }
        .bg-gradient-purple { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .dw-header h1 { font-size: 24px; font-weight: 700; color: var(--dw-text-main); letter-spacing: -0.02em; }
        .subtitle { font-size: 14px; color: var(--dw-text-sec); margin-top: 4px; }

        /* === TABS === */
        .dw-tabs-wrapper { margin-bottom: 25px; border-bottom: 1px solid var(--dw-border); }
        .dw-tabs-nav { display: flex; gap: 30px; }
        .dw-tab-link { padding: 12px 0; font-size: 14px; font-weight: 600; color: var(--dw-text-sec); text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
        .dw-tab-link:hover { color: var(--dw-primary); }
        .dw-tab-link.active { color: var(--dw-primary); border-bottom-color: var(--dw-primary); }
        .dw-tab-link span { font-size: 16px; }
        
        .dw-tab-content { display: none; animation: fadeIn 0.4s ease; }
        .dw-tab-content.active { display: block; }

        /* === STATS / MONITOR GRID === */
        .dw-grid-monitor { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .dw-stat-card { background: var(--dw-card-bg); border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid var(--dw-border); position: relative; overflow: hidden; }
        .dw-card-gradient-1 { border-top: 4px solid var(--dw-warning); }
        .dw-card-gradient-2 { border-top: 4px solid var(--dw-primary); }
        
        .card-inner { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .card-icon { width: 42px; height: 42px; border-radius: 10px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: var(--dw-text-sec); font-size: 20px; }
        .card-info h3 { font-size: 14px; color: var(--dw-text-sec); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px; }
        .card-numbers { display: flex; align-items: baseline; gap: 8px; }
        .big-num { font-size: 28px; font-weight: 800; color: var(--dw-text-main); line-height: 1; }
        .total-num { font-size: 13px; color: var(--dw-text-sec); font-weight: 500; }
        
        .dw-progress-wrapper { display: flex; flex-direction: column; gap: 6px; }
        .dw-progress-bar { height: 8px; background: #f3f4f6; border-radius: 4px; overflow: hidden; width: 100%; }
        .bar-fill { height: 100%; border-radius: 4px; background: var(--dw-warning); transition: width 0.5s ease; }
        .dw-progress-bar.blue .bar-fill { background: var(--dw-primary); }
        .progress-label { font-size: 12px; color: var(--dw-text-sec); font-weight: 500; text-align: right; }

        /* === TOOLBAR & FILTERS === */
        .dw-toolbar-modern { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--dw-card-bg); padding: 10px 15px; border-radius: 12px; border: 1px solid var(--dw-border); box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .dw-filter-pills { display: flex; gap: 8px; }
        .filter-pill { padding: 6px 14px; font-size: 13px; font-weight: 500; color: var(--dw-text-sec); text-decoration: none; border-radius: 20px; background: transparent; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .filter-pill:hover { background: #f3f4f6; color: var(--dw-text-main); }
        .filter-pill.active { background: var(--dw-text-main); color: #fff; }
        .dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .dot.orange { background: var(--dw-warning); } .dot.green { background: var(--dw-success); }
        
        .dw-search-modern { position: relative; }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--dw-text-sec); }
        .search-input-field { padding: 8px 10px 8px 34px; border: 1px solid var(--dw-border); border-radius: 8px; width: 220px; font-size: 13px; transition: all 0.2s; background: #f9fafb; }
        .search-input-field:focus { width: 280px; border-color: var(--dw-primary); background: #fff; outline: none; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1); }

        /* === TABLE === */
        .dw-table-card { background: var(--dw-card-bg); border-radius: 12px; border: 1px solid var(--dw-border); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .dw-table-premium { border: none; border-collapse: separate; border-spacing: 0; }
        .dw-table-premium thead th { background: #f9fafb; color: var(--dw-text-sec); font-weight: 600; text-transform: uppercase; font-size: 11px; padding: 16px 20px; border-bottom: 1px solid var(--dw-border); letter-spacing: 0.05em; }
        .dw-table-premium tbody td { padding: 16px 20px; border-bottom: 1px solid var(--dw-border); vertical-align: middle; color: var(--dw-text-main); }
        .dw-table-premium tbody tr:last-child td { border-bottom: none; }
        .dw-table-premium tbody tr:hover { background: #f9fafb; }
        
        /* Table Elements */
        .date-badge { text-align: center; border: 1px solid var(--dw-border); border-radius: 8px; padding: 6px 10px; background: #fcfcfc; width: fit-content; min-width: 50px; }
        .date-badge .day { display: block; font-size: 16px; font-weight: 700; line-height: 1; margin-bottom: 2px; }
        .date-badge .month { display: block; font-size: 10px; text-transform: uppercase; color: var(--dw-text-sec); }
        
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar img { border-radius: 50%; width: 36px; height: 36px; border: 1px solid var(--dw-border); }
        .user-details { display: flex; flex-direction: column; }
        .meta-id { font-size: 11px; color: var(--dw-text-sec); }
        
        .ad-detail { display: flex; flex-direction: column; gap: 4px; }
        .dw-badge-mini { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; width: fit-content; }
        .dw-badge-mini.blue { background: #eff6ff; color: #1d4ed8; }
        .dw-badge-mini.green { background: #ecfdf5; color: #047857; }
        .dw-badge-mini.purple { background: #f5f3ff; color: #7c3aed; }
        .target-name { font-weight: 500; font-size: 13px; }
        
        .cost-info { display: flex; flex-direction: column; }
        .cost-info .price { font-weight: 700; color: var(--dw-text-main); }
        .cost-info .duration { font-size: 12px; color: var(--dw-text-sec); display: flex; align-items: center; gap: 4px; }
        
        .dw-status-label { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .dw-status-label.success { background: #ecfdf5; color: var(--dw-success); }
        .dw-status-label.warning { background: #fffbeb; color: #b45309; }
        .dw-status-label.danger { background: #fef2f2; color: var(--dw-danger); }
        .dw-status-label.neutral { background: #f3f4f6; color: var(--dw-text-sec); }
        
        .dw-action-group { display: flex; gap: 6px; justify-content: flex-end; }
        .dw-btn-action { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; border: 1px solid transparent; }
        .dw-btn-action.success { background: #ecfdf5; color: var(--dw-success); border-color: #d1fae5; }
        .dw-btn-action.success:hover { background: var(--dw-success); color: #fff; border-color: var(--dw-success); }
        .dw-btn-action.danger { background: #fef2f2; color: var(--dw-danger); border-color: #fee2e2; }
        .dw-btn-action.danger:hover { background: var(--dw-danger); color: #fff; border-color: var(--dw-danger); }
        .text-green { color: var(--dw-success); font-weight: 600; font-size: 12px; display: flex; align-items: center; gap: 4px; justify-content: flex-end; }
        .empty-state { text-align: center; padding: 40px !important; color: var(--dw-text-sec); font-size: 14px; }
        .empty-state .dashicons { font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; opacity: 0.5; }

        /* === SETTINGS LAYOUT === */
        .dw-settings-layout { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .dw-card-setting { background: var(--dw-card-bg); border: 1px solid var(--dw-border); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .setting-header { padding: 20px; border-bottom: 1px solid var(--dw-border); display: flex; align-items: center; gap: 15px; background: #f9fafb; }
        .header-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .header-icon.yellow { background: #fffbeb; color: #f59e0b; }
        .header-icon.purple { background: #f3f0ff; color: #7c3aed; }
        .header-text h3 { font-size: 16px; font-weight: 700; color: var(--dw-text-main); margin-bottom: 2px; }
        .header-text p { font-size: 12px; color: var(--dw-text-sec); margin: 0; }
        .header-toggle { margin-left: auto; }
        
        .setting-body { padding: 20px; }
        .dw-input-group { margin-bottom: 20px; }
        .dw-input-group label { display: block; font-weight: 600; font-size: 13px; color: var(--dw-text-main); margin-bottom: 8px; }
        .input-wrapper { display: flex; align-items: center; border: 1px solid var(--dw-border); border-radius: 8px; overflow: hidden; background: #fff; transition: all 0.2s; }
        .input-wrapper:focus-within { border-color: var(--dw-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .input-wrapper .prefix { background: #f3f4f6; padding: 10px 15px; font-size: 14px; font-weight: 500; color: var(--dw-text-sec); border-right: 1px solid var(--dw-border); }
        .input-wrapper input { border: none; padding: 10px 15px; font-size: 14px; width: 100%; outline: none; box-shadow: none; background: transparent; }
        .simple-input { width: 100%; padding: 10px 15px; border: 1px solid var(--dw-border); border-radius: 8px; font-size: 14px; outline: none; transition: all 0.2s; }
        .simple-input:focus { border-color: var(--dw-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .dw-table-wrapper { border: 1px solid var(--dw-border); border-radius: 8px; overflow: hidden; margin-bottom: 15px; }
        .dw-repeater-table { width: 100%; border-collapse: collapse; }
        .dw-repeater-table th { background: #f9fafb; font-size: 11px; text-transform: uppercase; color: var(--dw-text-sec); font-weight: 600; padding: 10px 15px; text-align: left; border-bottom: 1px solid var(--dw-border); }
        .dw-repeater-table td { padding: 10px 15px; border-bottom: 1px solid var(--dw-border); background: #fff; }
        .dw-repeater-table tr:last-child td { border-bottom: none; }
        .dw-repeater-table input { width: 100%; border: 1px solid var(--dw-border); border-radius: 6px; padding: 6px 10px; font-size: 13px; }
        
        .dw-btn-outline { border: 1px dashed var(--dw-border); background: transparent; width: 100%; padding: 10px; border-radius: 8px; color: var(--dw-text-sec); cursor: pointer; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .dw-btn-outline:hover { border-color: var(--dw-primary); color: var(--dw-primary); background: #f0f6fc; }
        
        /* FOOTER & BUTTONS */
        .dw-form-footer { display: flex; justify-content: flex-end; padding-top: 20px; border-top: 1px solid var(--dw-border); margin-top: 20px; }
        .dw-btn-primary { background: var(--dw-primary); color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3); }
        .dw-btn-primary:hover { background: var(--dw-primary-hover); transform: translateY(-1px); box-shadow: 0 6px 8px -1px rgba(79, 70, 229, 0.4); }
        .dw-btn-primary.large { padding: 12px 30px; font-size: 15px; }

        /* TOGGLE SWITCH */
        .dw-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .dw-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #e5e7eb; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        input:checked + .slider { background-color: var(--dw-success); }
        input:checked + .slider:before { transform: translateX(20px); }

        /* ALERTS */
        .dw-alert { display: flex; align-items: center; gap: 15px; padding: 16px; border-radius: 10px; background: #fff; border: 1px solid; margin-bottom: 25px; animation: slideIn 0.3s ease; }
        .dw-alert-success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .dw-alert-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .alert-icon { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.5); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .dw-alert-dismiss { background: transparent; border: none; margin-left: auto; font-size: 20px; cursor: pointer; color: inherit; opacity: 0.7; }
        
        .dw-pagination-modern { margin-top: 20px; text-align: right; display: flex; justify-content: flex-end; gap: 5px; }
        .dw-pagination-modern .page-numbers { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--dw-border); color: var(--dw-text-sec); text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s; background: #fff; }
        .dw-pagination-modern .page-numbers.current { background: var(--dw-primary); color: #fff; border-color: var(--dw-primary); }
        .dw-pagination-modern .page-numbers:hover:not(.current) { background: #f3f4f6; color: var(--dw-text-main); }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>

    <script>
    function switchTab(evt, tabId) {
        evt.preventDefault();
        var contents = document.getElementsByClassName("dw-tab-content");
        for (var i = 0; i < contents.length; i++) {
            contents[i].style.display = "none";
            contents[i].classList.remove("active");
        }
        var tabs = document.getElementsByClassName("dw-tab-link");
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove("active");
        }
        document.getElementById(tabId).style.display = "block";
        document.getElementById(tabId).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    jQuery(document).ready(function($) {
        // Add Package Row
        $('#add-package-btn').click(function() {
            var index = new Date().getTime(); // Unique ID
            var row = `<tr>
                <td><input type="text" name="ad_packages[${index}][name]" required placeholder="Nama Paket"></td>
                <td><input type="number" name="ad_packages[${index}][days]" required></td>
                <td><input type="number" name="ad_packages[${index}][price]" required></td>
                <td><input type="number" name="ad_packages[${index}][quota]" required></td>
                <td><button type="button" class="dw-btn-icon remove-row text-red"><span class="dashicons dashicons-no-alt"></span></button></td>
            </tr>`;
            $('#package-rows').append(row);
            $('.empty-row').remove();
        });

        // Remove Package Row
        $(document).on('click', '.remove-row', function() {
            $(this).closest('tr').remove();
        });
        
        // Dismiss Notice
        $('.dw-alert-dismiss').on('click', function(){ $(this).parent().fadeOut(); });
    });
    </script>
    <?php
}