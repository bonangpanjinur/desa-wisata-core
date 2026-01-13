<?php
/**
 * File Name: page-manajemen-pesanan-pusat.php
 * Description: Pusat Komando Pesanan (Centralized Order Hub) untuk Admin.
 * Didesain khusus untuk membantu Admin mengelola pesanan pedagang.
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- 1. DEFINISI PATH (SOLUSI ERROR) ---
$current_dir = dirname(__FILE__); // includes/admin-pages
$includes_dir = dirname($current_dir); // includes

// Include UI components
$ui_path = $includes_dir . '/admin-ui-components.php';
if (file_exists($ui_path)) {
    require_once $ui_path;
}

/**
 * Handler Override Status oleh Admin Pusat
 * Dipindahkan ke fungsi terpisah agar bisa dipanggil lebih awal jika perlu,
 * namun tetap dalam file ini untuk kemudahan akses.
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
                if (function_exists('dw_add_log')) {
                    dw_add_log(get_current_user_id(), "ADMIN HUB: #$sub_order_id status paksa diperbarui menjadi $new_status", 'warning');
                }
                
                // Redirect dengan feedback sukses
                $redirect_url = add_query_arg('dw_msg', 'updated', wp_get_referer());
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }
}
// Jalankan handler segera setelah file dimuat
dw_handle_admin_order_override();

function dw_manajemen_pesanan_pusat_render() {
    global $wpdb;
    
    // 1. Inisialisasi Filter & Pencarian
    $filter_desa   = isset($_GET['desa_id']) ? absint($_GET['desa_id']) : 0;
    $filter_status = isset($_GET['order_status']) ? sanitize_text_field($_GET['order_status']) : '';
    $search_query  = isset($_GET['s_invoice']) ? sanitize_text_field($_GET['s_invoice']) : '';

    // 2. Definisi Nama Tabel (Gunakan prefix yang konsisten)
    $t_prefix       = $wpdb->prefix . 'dw_';
    $t_sub          = $t_prefix . 'transaksi_sub';
    $t_main         = $t_prefix . 'transaksi';
    $t_pedagang     = $t_prefix . 'pedagang';
    $t_desa         = $t_prefix . 'desa';
    $t_items        = $t_prefix . 'transaksi_items';

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
    $desas  = $wpdb->get_results("SELECT id, nama_desa FROM $t_desa WHERE status = 'aktif'");

    // 4. Hitung Statistik
    $stat_pending = $wpdb->get_var("SELECT COUNT(*) FROM $t_sub WHERE status_pesanan = 'menunggu_konfirmasi'");
    $stat_selesai = $wpdb->get_var("SELECT COUNT(*) FROM $t_sub WHERE status_pesanan = 'selesai' AND DATE(created_at) = CURDATE()");

    ?>
    
    <!-- Dependencies Font & Tailwind (Optional jika theme admin sudah ada style) -->
    <!-- <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"> -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"> -->

    <!-- CUSTOM STYLE UNTUK DASHBOARD INI -->
    <style>
        /* Override WP Admin defaults for this page only */
        .dw-admin-wrap { max-width: 1400px; margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
        
        /* Stats Badge */
        .stat-badge {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 15px 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #fff;
        }
        
        /* Order Card */
        .order-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            margin-bottom: 0; /* Reset WP margin */
        }
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }
        
        /* Header Card */
        .order-head {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }
        
        /* Body Card */
        .order-body {
            padding: 20px;
            background: #f8fafc;
            flex: 1;
        }
        
        /* Footer Card */
        .order-foot {
            padding: 20px;
            background: #fff;
            border-top: 1px solid #f1f5f9;
        }
        
        /* Typography & Badge */
        .status-badge-modern {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            letter-spacing: 0.5px;
        }
        
        /* Status Colors */
        .status-waiting { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .status-process { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
        .status-courier { background: #f5f3ff; color: #6d28d9; border: 1px solid #ede9fe; }
        .status-success { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }
        .status-cancel  { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
        
        /* Grid Layout */
        .dw-grid-orders {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        /* Input Modern */
        .input-modern {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            width: 100%;
        }
        
        /* Grand Total Box */
        .grand-total-box {
            background: #1e293b;
            border-radius: 10px;
            padding: 15px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .grand-total-box span { color: #ffffff !important; }
        .grand-total-box .total-label { color: #94a3b8 !important; }
    </style>

    <div class="wrap dw-admin-wrap">
        
        <!-- HEADER SECTION (PREMIUM STYLE) -->
        <div style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 16px; padding: 30px; color: white; margin-bottom: 30px; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                <div>
                    <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">
                        <span style="width: 8px; height: 8px; background: #4ade80; border-radius: 50%;"></span>
                        Sistem Kontrol Pusat
                    </div>
                    <h1 style="font-size: 28px; font-weight: 800; margin: 0; color: white; line-height: 1.2;">Order Dashboard</h1>
                    <p style="margin: 5px 0 0; opacity: 0.8; font-size: 14px;">Monitoring real-time seluruh transaksi pedagang desa wisata.</p>
                </div>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <!-- Stat Badge 1 -->
                    <div class="stat-badge">
                        <div style="background: #fbbf24; color: #78350f; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;">⏳</div>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; opacity: 0.7; font-weight: 700;">Antrean Baru</div>
                            <div style="font-size: 20px; font-weight: 800; line-height: 1;"><?php echo number_format($stat_pending); ?></div>
                        </div>
                    </div>
                    <!-- Stat Badge 2 -->
                    <div class="stat-badge">
                        <div style="background: #34d399; color: #064e3b; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;">✅</div>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; opacity: 0.7; font-weight: 700;">Sukses Hari Ini</div>
                            <div style="font-size: 20px; font-weight: 800; line-height: 1;"><?php echo number_format($stat_selesai); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- NOTIFICATION FEEDBACK -->
        <?php if (isset($_GET['dw_msg']) && $_GET['dw_msg'] === 'updated'): ?>
            <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                ✅ Berhasil! Status pesanan telah diperbarui.
            </div>
        <?php endif; ?>

        <!-- FILTER BAR (CARD STYLE) -->
        <div class="dw-card" style="margin-bottom: 30px;">
            <div class="dw-card-body" style="padding: 20px;">
                <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                    <input type="hidden" name="page" value="dw-manajemen-pesanan-pusat">
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: #64748b;">ID Pesanan</label>
                        <input type="text" name="s_invoice" value="<?php echo esc_attr($search_query); ?>" placeholder="Contoh: INV-..." class="input-modern w-full py-3 pl-10 pr-4">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: #64748b;">Filter Desa</label>
                        <select name="desa_id" class="input-modern w-full py-3 px-4 cursor-pointer">
                            <option value="0">🌍 Semua Desa</option>
                            <?php foreach ($desas as $desa): ?>
                                <option value="<?php echo $desa->id; ?>" <?php selected($filter_desa, $desa->id); ?>><?php echo esc_html($desa->nama_desa); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: #64748b;">Status Pesanan</label>
                        <select name="order_status" class="input-modern w-full py-3 px-4 cursor-pointer">
                            <option value="">🎯 Semua Status</option>
                            <option value="menunggu_konfirmasi" <?php selected($filter_status, 'menunggu_konfirmasi'); ?>>⏳ Antrean</option>
                            <option value="diproses" <?php selected($filter_status, 'diproses'); ?>>⚙️ Diproses</option>
                            <option value="diantar_ojek" <?php selected($filter_status, 'diantar_ojek'); ?>>🛵 Pengiriman</option>
                            <option value="selesai" <?php selected($filter_status, 'selesai'); ?>>✅ Selesai</option>
                            <option value="dibatalkan" <?php selected($filter_status, 'dibatalkan'); ?>>❌ Batal</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="button button-primary" style="width: 100%; justify-content: center; padding: 6px 12px;">Cari Data</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ORDERS GRID LIST -->
        <div class="dw-grid-orders">
            <?php if ($orders): foreach ($orders as $order): 
                // Config Status Badge
                $status = $order->status_pesanan;
                $status_config = [
                    'menunggu_konfirmasi' => ['class' => 'status-waiting', 'icon' => '⏳', 'label' => 'Antrean'],
                    'diproses'            => ['class' => 'status-process', 'icon' => '⚙️', 'label' => 'Diproses'],
                    'diantar_ojek'        => ['class' => 'status-courier', 'icon' => '🛵', 'label' => 'Kurir Ojek'],
                    'dikirim_ekspedisi'   => ['class' => 'status-courier', 'icon' => '🚚', 'label' => 'Ekspedisi'],
                    'selesai'             => ['class' => 'status-success', 'icon' => '✅', 'label' => 'Selesai'],
                    'dibatalkan'          => ['class' => 'status-cancel',  'icon' => '❌', 'label' => 'Batal']
                ];
                $cfg = $status_config[$status] ?? ['class' => 'status-waiting', 'icon' => '📦', 'label' => $status];
                
                // Fetch Items
                $items = $wpdb->get_results($wpdb->prepare("SELECT nama_produk, jumlah, total_harga FROM $t_items WHERE id_sub_transaksi = %d", $order->id));
                
                // Format WA Links (Auto convert 08xx to 628xx)
                $hp_pedagang = preg_replace('/[^0-9]/', '', $order->hp_pedagang);
                if(substr($hp_pedagang, 0, 1) == '0') $hp_pedagang = '62' . substr($hp_pedagang, 1);
                $wa_pedagang = "https://wa.me/" . $hp_pedagang;

                $hp_pembeli = preg_replace('/[^0-9]/', '', $order->hp_pembeli);
                if(substr($hp_pembeli, 0, 1) == '0') $hp_pembeli = '62' . substr($hp_pembeli, 1);
                $wa_pembeli  = "https://wa.me/" . $hp_pembeli;
            ?>
                <!-- ORDER CARD ITEM -->
                <div class="order-card">
                    <!-- Card Header -->
                    <div class="order-head">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 11px; font-weight: 700; color: #4f46e5; background: #eef2ff; padding: 2px 8px; border-radius: 10px; border: 1px solid #c7d2fe;"><?php echo esc_html($order->nama_desa); ?></span>
                            <div class="status-badge-modern <?php echo $cfg['class']; ?>">
                                <span><?php echo $cfg['icon']; ?></span> <?php echo $cfg['label']; ?>
                            </div>
                        </div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #1e293b;">#<?php echo $order->kode_unik; ?></h3>
                        <p style="margin: 2px 0 0; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;"><?php echo date('d M Y • H:i', strtotime($order->created_at)); ?></p>
                    </div>

                    <!-- Card Body -->
                    <div class="order-body">
                        <!-- Pedagang Info -->
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                            <div style="width: 36px; height: 36px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 16px;">🏪</div>
                            <div style="overflow: hidden;">
                                <p style="margin: 0; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Pedagang</p>
                                <p style="margin: 0; font-size: 13px; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo esc_html($order->nama_toko); ?></p>
                            </div>
                        </div>

                        <!-- Pelanggan Info -->
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <div style="width: 36px; height: 36px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 16px;">👤</div>
                            <div style="overflow: hidden;">
                                <p style="margin: 0; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Pelanggan</p>
                                <p style="margin: 0; font-size: 13px; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo esc_html($order->nama_penerima); ?></p>
                                <p style="margin: 0; font-size: 11px; color: #64748b;"><?php echo esc_html($order->alamat_pembeli); ?></p>
                            </div>
                        </div>

                        <!-- Item List -->
                        <div style="border-top: 1px dashed #cbd5e1; padding-top: 15px;">
                            <p style="margin: 0 0 10px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Item Belanja (<?php echo count($items); ?>)</p>
                            <div class="custom-scroll" style="max-height: 120px; overflow-y: auto; padding-right: 5px;">
                                <?php foreach ($items as $item): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 6px;">
                                        <div style="overflow: hidden; margin-right: 10px;">
                                            <p style="margin: 0; font-size: 12px; font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo esc_html($item->nama_produk); ?></p>
                                            <p style="margin: 0; font-size: 10px; font-weight: 700; color: #94a3b8;">x <?php echo $item->jumlah; ?></p>
                                        </div>
                                        <p style="margin: 0; font-size: 12px; font-weight: 700; color: #4f46e5;">Rp<?php echo number_format($item->total_harga, 0, ',', '.'); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="order-foot">
                        <!-- Grand Total -->
                        <div class="grand-total-box">
                            <div>
                                <span style="display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; opacity: 0.8;">Grand Total</span>
                                <span style="font-size: 18px; font-weight: 800;">Rp <?php echo number_format($order->total_pesanan_toko, 0, ',', '.'); ?></span>
                            </div>
                            <span class="dashicons dashicons-money-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                            <a href="<?php echo $wa_pedagang; ?>" target="_blank" class="button" style="text-align: center; justify-content: center; display: flex; align-items: center; gap: 5px;">
                                <span class="dashicons dashicons-whatsapp"></span> Toko
                            </a>
                            <a href="<?php echo $wa_pembeli; ?>" target="_blank" class="button" style="text-align: center; justify-content: center; display: flex; align-items: center; gap: 5px;">
                                <span class="dashicons dashicons-whatsapp"></span> Pembeli
                            </a>
                        </div>
                        
                        <!-- Admin Override Form -->
                        <div style="background: #f1f5f9; padding: 10px; border-radius: 8px;">
                            <form method="post" action="">
                                <?php wp_nonce_field('dw_admin_override_action', 'dw_admin_override_nonce'); ?>
                                <input type="hidden" name="sub_order_id" value="<?php echo $order->id; ?>">
                                <input type="hidden" name="dw_admin_override" value="1">
                                
                                <label style="display: block; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; text-align: center; margin-bottom: 5px;">Force Update Status</label>
                                <div style="display: flex; gap: 5px;">
                                    <select name="new_status" style="flex: 1; font-size: 11px; height: 30px; line-height: 1; min-height: 30px;">
                                        <option value="">Pilih...</option>
                                        <option value="menunggu_konfirmasi" <?php selected($status, 'menunggu_konfirmasi'); ?>>Antrean</option>
                                        <option value="diproses" <?php selected($status, 'diproses'); ?>>Proses</option>
                                        <option value="diantar_ojek" <?php selected($status, 'diantar_ojek'); ?>>Ojek</option>
                                        <option value="selesai" <?php selected($status, 'selesai'); ?>>Selesai</option>
                                        <option value="dibatalkan" <?php selected($status, 'dibatalkan'); ?>>Batal</option>
                                    </select>
                                    <button type="submit" class="button button-primary button-small" style="height: 30px; line-height: 1;">SET</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <!-- Empty State -->
                <div style="grid-column: 1 / -1; padding: 50px; text-align: center; background: #fff; border: 2px dashed #cbd5e1; border-radius: 16px;">
                    <span class="dashicons dashicons-cart" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 15px;"></span>
                    <h3 style="margin: 0 0 5px; color: #334155;">Tidak Ada Data Pesanan</h3>
                    <p style="margin: 0; color: #64748b;">Belum ada transaksi yang sesuai dengan filter Anda.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
    <?php
}