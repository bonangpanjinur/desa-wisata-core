<?php
/**
 * File Name:   includes/admin-pages/page-promosi.php
 * Description: Manajemen Iklan Terpusat (Request & Pengaturan Paket) dengan UI/UX Premium.
 */

if (!defined('ABSPATH')) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . "admin-ui-components.php";

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

    <div class="wrap dw-wrapper">
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
                <div class="dw-modern-table-card">
                    <table class="wp-list-table widefat fixed striped dw-modern-table-premium">
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
                                            <a href="<?php echo $approve_url; ?>" class="dw-button-action success tooltip" title="Setujui" onclick="return confirm('Setujui iklan ini?');"><span class="dashicons dashicons-yes"></span></a>
                                            <a href="<?php echo $reject_url; ?>" class="dw-button-action danger tooltip" title="Tolak" onclick="return confirm('Tolak iklan ini?');"><span class="dashicons dashicons-no"></span></a>
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
                                                <td><button type="button" class="dw-button-icon remove-row text-red"><span class="dashicons dashicons-no-alt"></span></button></td>
                                            </tr>
                                            <?php endforeach; else: ?>
                                            <tr class="empty-row"><td colspan="5">Belum ada paket.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="setting-actions">
                                    <button type="button" class="dw-button-outline" id="add-package-btn"><span class="dashicons dashicons-plus-alt2"></span> Tambah Paket</button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="dw-form-footer">
                        <button type="submit" class="dw-button-primary large">Simpan Perubahan</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    

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
                <td><button type="button" class="dw-button-icon remove-row text-red"><span class="dashicons dashicons-no-alt"></span></button></td>
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