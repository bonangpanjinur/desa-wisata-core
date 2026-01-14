<?php
/**
 * File: includes/admin-pages/page-promosi.php
 * * Admin Page: Promosi / Iklan
 * * Refaktor UI menggunakan Golden Template & Modern Layout.
 * * Fitur: Daftar Permintaan, Approval, dan Pengaturan Paket Iklan.
 */

defined( 'ABSPATH' ) || exit;

// Include UI components
// Pastikan konstanta yang benar digunakan
if ( defined( 'DW_CORE_PLUGIN_DIR' ) ) {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-ui-components.php';
} else {
    require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/admin-ui-components.php';
}

function dw_promosi_page_render() {
    global $wpdb;
    $table_promosi = $wpdb->prefix . 'dw_promosi';
    $table_users   = $wpdb->users;
    
    // --- 1. HANDLE POST ACTIONS (Save Settings) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_settings'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_ad_settings_action')) {
            add_settings_error('dw_promosi_msg', 'nonce_error', 'Keamanan tidak valid.', 'error');
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

            add_settings_error('dw_promosi_msg', 'settings_saved', 'Pengaturan Iklan & Paket berhasil disimpan.', 'success');
        }
    }

    // --- 2. HANDLE GET ACTIONS (Approval / Reject) ---
    if (isset($_GET['action']) && isset($_GET['id']) && check_admin_referer('dw_promo_action')) {
        $promo_id = intval($_GET['id']);
        $action_type = $_GET['action']; // 'approve' or 'reject'
        $new_status = ($action_type == 'approve') ? 'aktif' : 'ditolak';
        
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
        add_settings_error('dw_promosi_msg', 'status_updated', 'Status promosi diperbarui menjadi '.ucfirst($new_status).'.', 'success');
    }

    // --- 3. PREPARE DATA FOR VIEW ---
    $item_settings = get_option('dw_ad_item_settings', ['price_per_day' => 10000, 'max_quota' => 10, 'default_days' => 7, 'active' => 1]);
    $banner_packages = get_option('dw_ad_banner_packages', []);

    // Pagination & Filter Logic (Manual Implementation to mimic List Table)
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

    // Stats for Dashboard
    $used_quota_item = $wpdb->get_var("SELECT COUNT(id) FROM $table_promosi WHERE status = 'aktif' AND tipe IN ('produk', 'wisata')");
    $used_quota_banner = $wpdb->get_var("SELECT COUNT(id) FROM $table_promosi WHERE status = 'aktif' AND tipe = 'banner'");
    $quota_percent_item = ($item_settings['max_quota'] > 0) ? min(100, ($used_quota_item / $item_settings['max_quota']) * 100) : 0;

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'requests'; // Default tab

    ?>
    <div class="wrap dw-admin-wrapper">
        <!-- Header Section -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Pusat kontrol iklan dan promosi berbayar.</p>
            </div>
            <div class="dw-header-actions">
                <!-- Placeholder untuk tombol aksi tambahan jika diperlukan -->
            </div>
        </div>

        <div class="dw-content-body">
            <?php settings_errors('dw_promosi_msg'); ?>

            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 20px; border-bottom: 1px solid #c3c4c7;">
                <a href="?page=dw-promosi&tab=requests" class="nav-tab <?php echo $active_tab == 'requests' ? 'nav-tab-active' : ''; ?>" style="<?php echo $active_tab == 'requests' ? 'background: #fff; border-bottom: 1px solid #fff;' : 'background: #f0f0f1;'; ?>">
                    <span class="dashicons dashicons-list-view" style="font-size:16px; margin-right:4px; vertical-align:text-bottom;"></span> Daftar Permintaan
                </a>
                <a href="?page=dw-promosi&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>" style="<?php echo $active_tab == 'settings' ? 'background: #fff; border-bottom: 1px solid #fff;' : 'background: #f0f0f1;'; ?>">
                    <span class="dashicons dashicons-admin-settings" style="font-size:16px; margin-right:4px; vertical-align:text-bottom;"></span> Pengaturan & Paket
                </a>
            </nav>

            <!-- TAB 1: DAFTAR PERMINTAAN -->
            <?php if ($active_tab == 'requests'): ?>
                
                <!-- Quick Stats Grid -->
                <div class="dw-grid-2-col" style="margin-bottom: 20px;">
                    <!-- Card 1: Slot Sorotan -->
                    <div class="dw-card" style="margin-bottom:0; border-left: 4px solid #dba617; display: flex; flex-direction: column; justify-content: space-between;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div>
                                <h3 style="margin:0; font-size:13px; color:#646970; text-transform:uppercase; letter-spacing:0.5px;">Iklan Sorotan</h3>
                                <div style="font-size:28px; font-weight:bold; margin:5px 0; color:#1d2327;">
                                    <?php echo $used_quota_item; ?> <span style="font-size:14px; font-weight:normal; color:#646970;">/ <?php echo $item_settings['max_quota']; ?> Slot</span>
                                </div>
                            </div>
                            <div style="background: #fff8e1; padding: 10px; border-radius: 50%;">
                                <span class="dashicons dashicons-star-filled" style="font-size:24px; color:#f59e0b; width: 24px; height: 24px;"></span>
                            </div>
                        </div>
                        <div style="background:#f0f0f1; height:6px; border-radius:3px; overflow:hidden;">
                            <div style="background:#f59e0b; height:100%; width:<?php echo $quota_percent_item; ?>%;"></div>
                        </div>
                        <div style="font-size: 11px; color: #646970; margin-top: 5px; text-align: right;"><?php echo round($quota_percent_item); ?>% Terisi</div>
                    </div>

                    <!-- Card 2: Slot Banner -->
                    <div class="dw-card" style="margin-bottom:0; border-left: 4px solid #2271b1; display: flex; flex-direction: column; justify-content: space-between;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div>
                                <h3 style="margin:0; font-size:13px; color:#646970; text-transform:uppercase; letter-spacing:0.5px;">Iklan Banner</h3>
                                <div style="font-size:28px; font-weight:bold; margin:5px 0; color:#1d2327;">
                                    <?php echo $used_quota_banner ? $used_quota_banner : 0; ?> <span style="font-size:14px; font-weight:normal; color:#646970;">Tayang</span>
                                </div>
                            </div>
                            <div style="background: #eef2f7; padding: 10px; border-radius: 50%;">
                                <span class="dashicons dashicons-images-alt2" style="font-size:24px; color:#2271b1; width: 24px; height: 24px;"></span>
                            </div>
                        </div>
                        <div style="margin-top:auto; font-size:12px; color:#2271b1; font-weight: 500;">Status Tayang Aktif</div>
                    </div>
                </div>

                <!-- Main Table Card -->
                <div class="dw-card">
                    <!-- Filters -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1;">
                        <div class="subsubsub" style="margin:0; float: none;">
                            <a href="?page=dw-promosi&tab=requests" class="<?php echo empty($status_filter) ? 'current' : ''; ?>">Semua</a> |
                            <a href="?page=dw-promosi&tab=requests&status_filter=pending" class="<?php echo $status_filter=='pending' ? 'current' : ''; ?>">Menunggu</a> |
                            <a href="?page=dw-promosi&tab=requests&status_filter=aktif" class="<?php echo $status_filter=='aktif' ? 'current' : ''; ?>">Tayang</a>
                        </div>
                        <form method="get" style="display:flex; gap:5px;">
                            <input type="hidden" name="page" value="dw-promosi">
                            <input type="hidden" name="tab" value="requests">
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari nama pemohon..." style="border-radius: 4px; border: 1px solid #8c8f94;">
                            <button type="submit" class="button">Cari</button>
                        </form>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="120" style="font-weight: 600;">Tanggal</th>
                                <th style="font-weight: 600;">Pemohon</th>
                                <th style="font-weight: 600;">Detail Iklan</th>
                                <th style="font-weight: 600;">Info Biaya</th>
                                <th width="100" style="font-weight: 600;">Status</th>
                                <th width="120" style="text-align:right; font-weight: 600;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($rows): foreach($rows as $r): 
                                $approve_url = wp_nonce_url("?page=dw-promosi&action=approve&id={$r->id}&tab=requests", 'dw_promo_action');
                                $reject_url = wp_nonce_url("?page=dw-promosi&action=reject&id={$r->id}&tab=requests", 'dw_promo_action');
                            ?>
                            <tr class="<?php echo $r->status == 'pending' ? 'active' : ''; // Highlight pending rows ?>">
                                <td style="vertical-align: middle;">
                                    <?php echo date('d M Y', strtotime($r->created_at)); ?>
                                </td>
                                <td style="vertical-align: middle;">
                                    <strong><?php echo esc_html($r->pemohon_name); ?></strong><br>
                                    <span style="font-size:11px; color:#646970;">ID #<?php echo $r->id; ?></span>
                                </td>
                                <td style="vertical-align: middle;">
                                    <?php if($r->tipe == 'produk'): ?>
                                        <span style="background:#e5f6fd; color:#0c5460; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:600; border: 1px solid #bce8f1;">PRODUK</span>
                                    <?php elseif($r->tipe == 'wisata'): ?>
                                        <span style="background:#d4edda; color:#155724; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:600; border: 1px solid #c3e6cb;">WISATA</span>
                                    <?php else: ?>
                                        <span style="background:#f3e5f5; color:#4a148c; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:600; border: 1px solid #e1bee7;">BANNER</span>
                                    <?php endif; ?>
                                    <br>
                                    <strong style="display: block; margin-top: 4px;"><?php echo esc_html($r->nama_target ? $r->nama_target : '-'); ?></strong>
                                </td>
                                <td style="vertical-align: middle;">
                                    <span style="font-weight: 600; color: #1d2327;">Rp <?php echo number_format($r->biaya, 0, ',', '.'); ?></span><br>
                                    <span style="font-size:11px; color:#646970;"><span class="dashicons dashicons-clock" style="font-size:12px; margin-top:2px; color: #8c8f94;"></span> <?php echo $r->durasi_hari; ?> Hari</span>
                                </td>
                                <td style="vertical-align: middle;">
                                    <?php 
                                        $s_bg = '#f0f0f1'; $s_col = '#646970'; $s_border = '#dcdcde';
                                        if($r->status == 'aktif') { $s_bg = '#d1e7dd'; $s_col = '#0f5132'; $s_border = '#badbcc'; }
                                        if($r->status == 'pending') { $s_bg = '#fff3cd'; $s_col = '#856404'; $s_border = '#ffeeba'; }
                                        if($r->status == 'ditolak') { $s_bg = '#f8d7da'; $s_col = '#721c24'; $s_border = '#f5c6cb'; }
                                    ?>
                                    <span style="background:<?php echo $s_bg; ?>; color:<?php echo $s_col; ?>; border: 1px solid <?php echo $s_border; ?>; padding:4px 8px; border-radius:4px; font-weight:600; font-size:11px; display: inline-block; min-width: 60px; text-align: center;">
                                        <?php echo ucfirst($r->status); ?>
                                    </span>
                                </td>
                                <td style="text-align:right; vertical-align: middle;">
                                    <?php if($r->status == 'pending'): ?>
                                        <div style="display:flex; justify-content:flex-end; gap:5px;">
                                            <a href="<?php echo $approve_url; ?>" class="button button-small button-primary" title="Setujui" onclick="return confirm('Setujui iklan ini?');">
                                                <span class="dashicons dashicons-yes" style="margin-top: 2px;"></span>
                                            </a>
                                            <a href="<?php echo $reject_url; ?>" class="button button-small" style="color:#d63638; border-color:#d63638;" title="Tolak" onclick="return confirm('Tolak iklan ini?');">
                                                <span class="dashicons dashicons-no" style="margin-top: 2px;"></span>
                                            </a>
                                        </div>
                                    <?php elseif($r->status == 'aktif'): ?>
                                        <span style="color:#00a32a; font-weight: 500; font-size: 12px; display: flex; align-items: center; justify-content: flex-end; gap: 4px;">
                                            <span class="dashicons dashicons-visibility" style="font-size: 16px;"></span> Live
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #a0a0a0;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color: #646970;">
                                <span class="dashicons dashicons-info" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px; color: #a0a0a0;"></span><br>
                                Belum ada permintaan iklan yang ditemukan.
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <?php echo paginate_links(['total' => $total_pages, 'current' => $paged, 'format' => '&paged=%#%']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            <!-- TAB 2: PENGATURAN & PAKET -->
            <?php elseif ($active_tab == 'settings'): ?>
                <form method="post">
                    <?php wp_nonce_field('dw_ad_settings_action'); ?>
                    <input type="hidden" name="action_settings" value="save">

                    <div class="dw-grid-2-col" style="align-items: start;">
                        
                        <!-- Setting 1: Iklan Sorotan -->
                        <div class="dw-card">
                            <div class="dw-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #f0f0f1; padding-bottom: 15px; margin-bottom: 15px;">
                                <h3 style="margin:0; font-size: 16px;">Iklan Sorotan (Populer)</h3>
                                <label style="display:flex; align-items:center; gap:5px; font-size: 13px;">
                                    <input type="checkbox" name="item_active" value="1" <?php checked($item_settings['active'], 1); ?>> Aktifkan
                                </label>
                            </div>
                            <div class="dw-card-body">
                                <p class="description" style="margin-bottom:20px; font-style: italic;">Iklan yang muncul di halaman depan dan posisi teratas arsip.</p>
                                
                                <div style="margin-bottom:20px;">
                                    <label style="font-weight:600; display: block; margin-bottom: 5px;">Harga Per Hari (Rp)</label>
                                    <input type="number" name="item_price" value="<?php echo esc_attr($item_settings['price_per_day']); ?>" class="widefat" required>
                                </div>
                                
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                    <div>
                                        <label style="font-weight:600; display: block; margin-bottom: 5px;">Kuota Maksimal (Slot)</label>
                                        <input type="number" name="item_quota" value="<?php echo esc_attr($item_settings['max_quota']); ?>" class="widefat" required>
                                    </div>
                                    <div>
                                        <label style="font-weight:600; display: block; margin-bottom: 5px;">Durasi Default (Hari)</label>
                                        <input type="number" name="item_days" value="<?php echo esc_attr($item_settings['default_days'] ?? 7); ?>" class="widefat" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Setting 2: Paket Banner (Repeater) -->
                        <div class="dw-card">
                            <div class="dw-card-header" style="border-bottom: 1px solid #f0f0f1; padding-bottom: 15px; margin-bottom: 15px;">
                                <h3 style="margin:0; font-size: 16px;">Paket Iklan Banner</h3>
                            </div>
                            <div class="dw-card-body">
                                <p class="description" style="margin-bottom:20px; font-style: italic;">Kelola paket pilihan untuk iklan di carousel utama.</p>
                                
                                <table class="widefat fixed" id="dw-packages-table" style="border:none; margin-bottom: 15px;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding-left: 0;">Nama Paket</th>
                                            <th width="60">Hari</th>
                                            <th width="100">Harga (Rp)</th>
                                            <th width="60">Kuota</th>
                                            <th width="30"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="package-rows">
                                        <?php if(!empty($banner_packages)): foreach($banner_packages as $i => $pkg): ?>
                                        <tr style="border-bottom: 1px solid #f0f0f1;">
                                            <td style="padding-left: 0;"><input type="text" name="ad_packages[<?php echo $i; ?>][name]" value="<?php echo esc_attr($pkg['name']); ?>" style="width:100%;" required></td>
                                            <td><input type="number" name="ad_packages[<?php echo $i; ?>][days]" value="<?php echo esc_attr($pkg['days']); ?>" style="width:100%;" required></td>
                                            <td><input type="number" name="ad_packages[<?php echo $i; ?>][price]" value="<?php echo esc_attr($pkg['price']); ?>" style="width:100%;" required></td>
                                            <td><input type="number" name="ad_packages[<?php echo $i; ?>][quota]" value="<?php echo esc_attr($pkg['quota']); ?>" style="width:100%;" required></td>
                                            <td style="vertical-align: middle;"><button type="button" class="button remove-row" style="color:#d63638; border-color: transparent; background: transparent; padding: 0;"><span class="dashicons dashicons-no-alt"></span></button></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr class="empty-row"><td colspan="5" style="text-align:center; color: #a0a0a0; padding: 20px;">Belum ada paket yang dibuat.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                
                                <button type="button" class="button button-secondary" id="add-package-btn" style="width: 100%; justify-content: center; display: flex; align-items: center; gap: 5px;">
                                    <span class="dashicons dashicons-plus-alt2"></span> Tambah Paket Baru
                                </button>
                            </div>
                        </div>

                    </div>

                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #c3c4c7;">
                    <div style="text-align: right;">
                        <button type="submit" class="button button-primary button-large">Simpan Perubahan</button>
                    </div>
                </form>

                <!-- Script Repeater -->
                <script>
                jQuery(document).ready(function($) {
                    $('#add-package-btn').click(function() {
                        var index = new Date().getTime(); // Unique ID
                        var row = `<tr style="border-bottom: 1px solid #f0f0f1;">
                            <td style="padding-left: 0;"><input type="text" name="ad_packages[${index}][name]" style="width:100%;" required placeholder="Nama Paket"></td>
                            <td><input type="number" name="ad_packages[${index}][days]" style="width:100%;" required></td>
                            <td><input type="number" name="ad_packages[${index}][price]" style="width:100%;" required></td>
                            <td><input type="number" name="ad_packages[${index}][quota]" style="width:100%;" required></td>
                            <td style="vertical-align: middle;"><button type="button" class="button remove-row" style="color:#d63638; border-color: transparent; background: transparent; padding: 0;"><span class="dashicons dashicons-no-alt"></span></button></td>
                        </tr>`;
                        $('#package-rows').append(row);
                        $('.empty-row').remove();
                    });

                    $(document).on('click', '.remove-row', function() {
                        $(this).closest('tr').remove();
                        if($('#package-rows tr').length === 0) {
                             $('#package-rows').html('<tr class="empty-row"><td colspan="5" style="text-align:center; color: #a0a0a0; padding: 20px;">Belum ada paket yang dibuat.</td></tr>');
                        }
                    });
                });
                </script>
            <?php endif; ?>

        </div>
    </div>
    <?php
}