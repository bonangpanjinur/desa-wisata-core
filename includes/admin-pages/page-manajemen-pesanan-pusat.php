<?php
/**
 * File: includes/admin-pages/page-manajemen-pesanan-pusat.php
 * * Admin Page: Pusat Komando Pesanan (Centralized Order Hub)
 * * Menampilkan monitoring transaksi, status pesanan, dan fitur override admin.
 */

defined( 'ABSPATH' ) || exit;

// Include UI components
if ( defined( 'DW_CORE_PLUGIN_DIR' ) ) {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-ui-components.php';
} else {
    require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/admin-ui-components.php';
}

/**
 * Handler Override Status oleh Admin Pusat
 */
function dw_handle_admin_order_override() {
    if (isset($_POST['dw_admin_override']) && isset($_POST['dw_admin_override_nonce'])) {
        if (!wp_verify_nonce($_POST['dw_admin_override_nonce'], 'dw_admin_override_action')) {
            wp_die('Keamanan verifikasi gagal.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Anda tidak memiliki izin.');
        }
        
        $sub_order_id = absint($_POST['sub_order_id']);
        $new_status   = sanitize_text_field($_POST['new_status']);
        
        if ($sub_order_id && $new_status) {
            global $wpdb;
            $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
            
            $updated = $wpdb->update(
                $table_sub,
                array('status_pesanan' => $new_status),
                array('id' => $sub_order_id),
                array('%s'),
                array('%d')
            );

            if ($updated !== false) {
                // Log activity if logging function exists (optional)
                if (function_exists('dw_add_log')) {
                    dw_add_log(get_current_user_id(), "ADMIN HUB: #$sub_order_id status paksa diperbarui menjadi $new_status", 'warning');
                }
                
                // Redirect with success message
                $redirect_url = add_query_arg('dw_msg', 'updated', wp_get_referer());
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }
}
// Jalankan handler segera setelah file dimuat
add_action('admin_init', 'dw_handle_admin_order_override');

function dw_manajemen_pesanan_pusat_render() {
    global $wpdb;
    
    // 1. Inisialisasi Filter & Pencarian
    $filter_desa   = isset($_GET['desa_id']) ? absint($_GET['desa_id']) : 0;
    $filter_status = isset($_GET['order_status']) ? sanitize_text_field($_GET['order_status']) : '';
    $search_query  = isset($_GET['s_invoice']) ? sanitize_text_field($_GET['s_invoice']) : '';

    // 2. Definisi Nama Tabel
    $t_prefix   = $wpdb->prefix . 'dw_';
    $t_sub      = $t_prefix . 'transaksi_sub';
    $t_main     = $t_prefix . 'transaksi';
    $t_pedagang = $t_prefix . 'pedagang';
    $t_desa     = $t_prefix . 'desa';
    $t_items    = $t_prefix . 'transaksi_items';

    // 3. Bangun Query Utama
    $sql = "SELECT s.*, t.kode_unik, t.nama_penerima, t.no_hp as hp_pembeli, t.alamat_lengkap as alamat_pembeli, 
                   p.nama_toko, p.nomor_wa as hp_pedagang, d.nama_desa 
            FROM $t_sub s
            JOIN $t_main t ON s.id_transaksi = t.id
            JOIN $t_pedagang p ON s.id_pedagang = p.id
            JOIN $t_desa d ON p.id_desa = d.id
            WHERE 1=1";

    if ($filter_desa > 0) {
        $sql .= $wpdb->prepare(" AND p.id_desa = %d", $filter_desa);
    }
    if ($filter_status !== '') {
        $sql .= $wpdb->prepare(" AND s.status_pesanan = %s", $filter_status);
    }
    if (!empty($search_query)) {
        $sql .= $wpdb->prepare(" AND t.kode_unik LIKE %s", '%' . $wpdb->esc_like($search_query) . '%');
    }

    $sql .= " ORDER BY s.created_at DESC";
    $orders = $wpdb->get_results($sql);
    
    // Ambil data Desa untuk filter dropdown
    $desas = $wpdb->get_results("SELECT id, nama_desa FROM $t_desa WHERE status = 'aktif'");

    // 4. Hitung Statistik
    $stat_pending = $wpdb->get_var("SELECT COUNT(*) FROM $t_sub WHERE status_pesanan = 'menunggu_konfirmasi'");
    $stat_selesai = $wpdb->get_var("SELECT COUNT(*) FROM $t_sub WHERE status_pesanan = 'selesai' AND DATE(created_at) = CURDATE()");

    ?>
    <div class="wrap dw-admin-wrapper">
        
        <!-- HEADER -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Pusat kontrol monitoring transaksi pedagang secara real-time.</p>
            </div>
            <div class="dw-header-actions">
                <!-- Optional Actions -->
            </div>
        </div>

        <div class="dw-content-body">
            
            <!-- NOTIFIKASI -->
            <?php if (isset($_GET['dw_msg']) && $_GET['dw_msg'] === 'updated'): ?>
                <div class="notice notice-success is-dismissible" style="margin-left:0; margin-bottom:20px;">
                    <p>✅ Berhasil! Status pesanan telah diperbarui.</p>
                </div>
            <?php endif; ?>

            <!-- STATS GRID -->
            <div class="dw-stats-grid" style="margin-bottom: 25px;">
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-orange">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($stat_pending); ?></h3>
                        <p class="dw-stat-label">Antrean Baru</p>
                    </div>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-green">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($stat_selesai); ?></h3>
                        <p class="dw-stat-label">Sukses Hari Ini</p>
                    </div>
                </div>
                <!-- Placeholder Stat Cards for Balance/Total -->
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-blue">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo count($orders); ?></h3>
                        <p class="dw-stat-label">Total Ditampilkan</p>
                    </div>
                </div>
            </div>

            <!-- FILTER CARD -->
            <div class="dw-card" style="margin-bottom: 30px;">
                <div class="dw-card-body">
                    <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                        <input type="hidden" name="page" value="dw-manajemen-pesanan-pusat">
                        
                        <div class="dw-form-group" style="margin-bottom:0;">
                            <label class="dw-label" style="font-size: 12px; margin-bottom: 5px;">ID Pesanan</label>
                            <input type="text" name="s_invoice" value="<?php echo esc_attr($search_query); ?>" placeholder="Contoh: INV-..." class="dw-input">
                        </div>

                        <div class="dw-form-group" style="margin-bottom:0;">
                            <label class="dw-label" style="font-size: 12px; margin-bottom: 5px;">Filter Desa</label>
                            <select name="desa_id" class="dw-select">
                                <option value="0">🌍 Semua Desa</option>
                                <?php foreach ($desas as $desa): ?>
                                    <option value="<?php echo $desa->id; ?>" <?php selected($filter_desa, $desa->id); ?>><?php echo esc_html($desa->nama_desa); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dw-form-group" style="margin-bottom:0;">
                            <label class="dw-label" style="font-size: 12px; margin-bottom: 5px;">Status Pesanan</label>
                            <select name="order_status" class="dw-select">
                                <option value="">🎯 Semua Status</option>
                                <option value="menunggu_konfirmasi" <?php selected($filter_status, 'menunggu_konfirmasi'); ?>>⏳ Antrean</option>
                                <option value="diproses" <?php selected($filter_status, 'diproses'); ?>>⚙️ Diproses</option>
                                <option value="diantar_ojek" <?php selected($filter_status, 'diantar_ojek'); ?>>🛵 Pengiriman</option>
                                <option value="selesai" <?php selected($filter_status, 'selesai'); ?>>✅ Selesai</option>
                                <option value="dibatalkan" <?php selected($filter_status, 'dibatalkan'); ?>>❌ Batal</option>
                            </select>
                        </div>

                        <div class="dw-form-group" style="margin-bottom:0;">
                            <button type="submit" class="button button-primary" style="width: 100%; justify-content: center;">Cari Data</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ORDERS GRID LIST -->
            <div class="dw-grid-orders" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px;">
                <?php if ($orders): foreach ($orders as $order): 
                    // Config Status Badge
                    $status = $order->status_pesanan;
                    $status_badges = [
                        'menunggu_konfirmasi' => '<span class="dw-badge status-warning">⏳ Antrean</span>',
                        'diproses'            => '<span class="dw-badge status-info">⚙️ Diproses</span>',
                        'diantar_ojek'        => '<span class="dw-badge status-info">🛵 Kurir Ojek</span>',
                        'dikirim_ekspedisi'   => '<span class="dw-badge status-info">🚚 Ekspedisi</span>',
                        'selesai'             => '<span class="dw-badge status-success">✅ Selesai</span>',
                        'dibatalkan'          => '<span class="dw-badge status-danger">❌ Batal</span>'
                    ];
                    $badge = $status_badges[$status] ?? '<span class="dw-badge status-neutral">'.esc_html($status).'</span>';
                    
                    // Fetch Items
                    $items = $wpdb->get_results($wpdb->prepare("SELECT nama_produk, jumlah, total_harga FROM $t_items WHERE id_sub_transaksi = %d", $order->id));
                    
                    // Format WA Links
                    $hp_pedagang = preg_replace('/[^0-9]/', '', $order->hp_pedagang);
                    if(substr($hp_pedagang, 0, 1) == '0') $hp_pedagang = '62' . substr($hp_pedagang, 1);
                    $wa_pedagang = "https://wa.me/" . $hp_pedagang;

                    $hp_pembeli = preg_replace('/[^0-9]/', '', $order->hp_pembeli);
                    if(substr($hp_pembeli, 0, 1) == '0') $hp_pembeli = '62' . substr($hp_pembeli, 1);
                    $wa_pembeli  = "https://wa.me/" . $hp_pembeli;
                ?>
                    <!-- ORDER CARD ITEM -->
                    <div class="dw-card" style="margin-bottom:0; display:flex; flex-direction:column;">
                        <!-- Card Header -->
                        <div class="dw-card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:15px;">
                            <div style="width:100%;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <span style="font-size: 10px; font-weight: 700; color: #4f46e5; background: #eef2ff; padding: 2px 8px; border-radius: 10px; border: 1px solid #c7d2fe;"><?php echo esc_html($order->nama_desa); ?></span>
                                    <?php echo $badge; ?>
                                </div>
                                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #1e293b;">#<?php echo $order->kode_unik; ?></h3>
                                <p style="margin: 2px 0 0; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;"><?php echo date('d M Y • H:i', strtotime($order->created_at)); ?></p>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="dw-card-body" style="padding:15px; background:#f8fafc; flex:1;">
                            <!-- Info -->
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px;">
                                <div>
                                    <p style="margin:0; font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase;">Pedagang</p>
                                    <p style="margin:0; font-size:12px; font-weight:600; color:#334155;"><?php echo esc_html($order->nama_toko); ?></p>
                                </div>
                                <div>
                                    <p style="margin:0; font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase;">Pelanggan</p>
                                    <p style="margin:0; font-size:12px; font-weight:600; color:#334155;"><?php echo esc_html($order->nama_penerima); ?></p>
                                </div>
                            </div>

                            <!-- Item List -->
                            <div style="border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                                <p style="margin: 0 0 8px; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">Item Belanja (<?php echo count($items); ?>)</p>
                                <div style="max-height: 100px; overflow-y: auto;">
                                    <?php foreach ($items as $item): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 6px; border-radius: 4px; border: 1px solid #e2e8f0; margin-bottom: 4px;">
                                            <div style="overflow: hidden; margin-right: 10px;">
                                                <p style="margin: 0; font-size: 11px; font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo esc_html($item->nama_produk); ?></p>
                                                <p style="margin: 0; font-size: 9px; font-weight: 700; color: #94a3b8;">x <?php echo $item->jumlah; ?></p>
                                            </div>
                                            <p style="margin: 0; font-size: 11px; font-weight: 700; color: #4f46e5;">Rp<?php echo number_format($item->total_harga, 0, ',', '.'); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="dw-card-body" style="padding:15px; border-top:1px solid #f1f5f9;">
                            <!-- Grand Total -->
                            <div style="background: #1e293b; border-radius: 8px; padding: 10px; color: #fff; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <div>
                                    <span style="display: block; font-size: 9px; font-weight: 700; text-transform: uppercase; opacity: 0.8; color:#fff;">Total</span>
                                    <span style="font-size: 16px; font-weight: 800; color:#fff;">Rp <?php echo number_format($order->total_pesanan_toko, 0, ',', '.'); ?></span>
                                </div>
                                <span class="dashicons dashicons-money-alt" style="font-size: 20px; width: 20px; height: 20px; color:#fff;"></span>
                            </div>

                            <!-- Action Buttons -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 15px;">
                                <a href="<?php echo $wa_pedagang; ?>" target="_blank" class="button dw-button-secondary" style="justify-content: center; font-size: 11px;">
                                    <span class="dashicons dashicons-whatsapp" style="margin-right:4px;"></span> Toko
                                </a>
                                <a href="<?php echo $wa_pembeli; ?>" target="_blank" class="button dw-button-secondary" style="justify-content: center; font-size: 11px;">
                                    <span class="dashicons dashicons-whatsapp" style="margin-right:4px;"></span> Pembeli
                                </a>
                            </div>
                            
                            <!-- Admin Override Form -->
                            <div style="background: #f1f5f9; padding: 10px; border-radius: 6px;">
                                <form method="post" action="">
                                    <?php wp_nonce_field('dw_admin_override_action', 'dw_admin_override_nonce'); ?>
                                    <input type="hidden" name="sub_order_id" value="<?php echo $order->id; ?>">
                                    <input type="hidden" name="dw_admin_override" value="1">
                                    
                                    <label style="display: block; font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Update Status</label>
                                    <div style="display: flex; gap: 4px;">
                                        <select name="new_status" style="flex: 1; font-size: 11px; height: 28px; line-height: 1; min-height: 28px; padding: 0 4px;">
                                            <option value="">Pilih...</option>
                                            <option value="menunggu_konfirmasi" <?php selected($status, 'menunggu_konfirmasi'); ?>>Antrean</option>
                                            <option value="diproses" <?php selected($status, 'diproses'); ?>>Proses</option>
                                            <option value="diantar_ojek" <?php selected($status, 'diantar_ojek'); ?>>Ojek</option>
                                            <option value="selesai" <?php selected($status, 'selesai'); ?>>Selesai</option>
                                            <option value="dibatalkan" <?php selected($status, 'dibatalkan'); ?>>Batal</option>
                                        </select>
                                        <button type="submit" class="button button-primary button-small" style="height: 28px; line-height: 1; font-size: 10px;">SET</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <!-- Empty State -->
                    <div style="grid-column: 1 / -1;">
                        <div class="dw-card">
                            <div class="dw-card-body" style="padding: 50px; text-align: center;">
                                <span class="dashicons dashicons-cart" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 15px;"></span>
                                <h3 style="margin: 0 0 5px; color: #334155;">Tidak Ada Data Pesanan</h3>
                                <p style="margin: 0; color: #64748b;">Belum ada transaksi yang sesuai dengan filter Anda.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
    <?php
}