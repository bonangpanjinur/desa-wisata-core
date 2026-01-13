<?php
/**
 * File Name: page-manajemen-pesanan-pusat.php
 * Description: Pusat Komando Pesanan (Centralized Order Hub) untuk Admin.
 * Didesain khusus untuk membantu Admin mengelola pesanan pedagang.
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) exit;

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

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin-ui-components.php';

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
    <!-- Dependencies -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <style>
        :root { 
            --dw-brand: #6366f1; 
            --dw-brand-dark: #4f46e5;
            --dw-bg: #f8fafc; 
            --dw-text: #0f172a;
            --dw-text-light: #64748b;
        }
        #wpcontent { background-color: var(--dw-bg) !important; padding-left: 20px !important; }
        .dw-admin-wrap { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--dw-text); max-width: 1400px; margin: 0 auto; }
        #wpfooter { display: none; }
        
        .premium-header {
            background: linear-gradient(135deg, var(--dw-brand) 0%, var(--dw-brand-dark) 100%);
            border-radius: 2rem;
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.2);
        }

        .stat-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .order-card { 
            border-radius: 1.5rem; 
            background: #ffffff; 
            border: 1px solid #e2e8f0; 
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .order-card:hover { 
            box-shadow: 0 25px 30px -10px rgba(99, 102, 241, 0.15);
            transform: translateY(-4px);
            border-color: #c7d2fe;
        }

        .status-badge-modern {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.4rem 0.8rem;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .btn-action { transition: all 0.2s; }
        .btn-action:hover { transform: scale(1.03); }

        .label-upper {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--dw-text-light);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* Status Colors */
        .status-waiting { background: #fff7ed; color: #9a3412; border: 1px solid #ffedd5; }
        .status-process { background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; }
        .status-courier { background: #f5f3ff; color: #5b21b6; border: 1px solid #ede9fe; }
        .status-success { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
        .status-cancel  { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
        
        .input-modern {
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            outline: none;
        }
        .input-modern:focus { border-color: var(--dw-brand); background-color: #fff; }

        /* Grand Total Fix - Dark Theme for contrast */
        .grand-total-box {
            background: #1e293b; /* Dark Navy */
            border-radius: 1.25rem;
            padding: 1.25rem;
            color: #ffffff !important; /* Force White Text */
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .grand-total-box span { color: #ffffff !important; }
        .grand-total-box .total-label { color: #94a3b8 !important; }
    </style>

    <div class="wrap dw-admin-wrap mt-8 pr-6 pb-20">
        
        <!-- Flash Message Feedback -->
        <?php if (isset($_GET['dw_msg']) && $_GET['dw_msg'] === 'updated'): ?>
            <div class="bg-emerald-500 text-white p-4 rounded-2xl mb-6 shadow-lg flex items-center gap-3 animate-bounce">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3"></path></svg>
                <span class="font-bold">Berhasil! Status pesanan telah diperbarui.</span>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="premium-header p-8 mb-8 text-white relative overflow-hidden">
            <div class="relative z-10 flex flex-col lg:flex-row justify-between items-center gap-8">
                <div class="text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 bg-white/20 px-3 py-1 rounded-full backdrop-blur-md border border-white/20 mb-3">
                        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-white">Sistem Kontrol Lintas Desa</span>
                    </div>
                    <h1 class="text-4xl font-extrabold tracking-tight mb-2">Order Dashboard</h1>
                    <p class="text-indigo-100 text-base font-medium opacity-90">Manajemen alur transaksi UMKM terpusat secara real-time.</p>
                </div>
                
                <div class="flex flex-wrap justify-center gap-4">
                    <div class="stat-badge px-6 py-4 rounded-2xl flex items-center gap-4">
                        <div class="w-12 h-12 bg-amber-400 rounded-xl flex items-center justify-center text-amber-900">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m9-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-white/70 mb-1">Antrean Baru</p>
                            <p class="text-2xl font-black text-white leading-none"><?php echo number_format($stat_pending); ?></p>
                        </div>
                    </div>
                    <div class="stat-badge px-6 py-4 rounded-2xl flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-400 rounded-xl flex items-center justify-center text-emerald-900">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-white/70 mb-1">Sukses Hari Ini</p>
                            <p class="text-2xl font-black text-white leading-none"><?php echo number_format($stat_selesai); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 mb-10">
            <form method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-center">
                <input type="hidden" name="page" value="dw-manajemen-pesanan-pusat">
                
                <div class="relative">
                    <input type="text" name="s_invoice" value="<?php echo esc_attr($search_query); ?>" placeholder="ID Pesanan..." class="input-modern w-full py-3 pl-10 pr-4">
                    <svg class="w-5 h-5 absolute left-3.5 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"></path></svg>
                </div>

                <select name="desa_id" class="input-modern w-full py-3 px-4 cursor-pointer">
                    <option value="0">🌍 Semua Desa</option>
                    <?php foreach ($desas as $desa): ?>
                        <option value="<?php echo $desa->id; ?>" <?php selected($filter_desa, $desa->id); ?>><?php echo esc_html($desa->nama_desa); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="order_status" class="input-modern w-full py-3 px-4 cursor-pointer">
                    <option value="">🎯 Semua Status</option>
                    <option value="menunggu_konfirmasi" <?php selected($filter_status, 'menunggu_konfirmasi'); ?>>⏳ Antrean</option>
                    <option value="diproses" <?php selected($filter_status, 'diproses'); ?>>⚙️ Diproses</option>
                    <option value="diantar_ojek" <?php selected($filter_status, 'diantar_ojek'); ?>>🛵 Pengiriman</option>
                    <option value="selesai" <?php selected($filter_status, 'selesai'); ?>>✅ Selesai</option>
                    <option value="dibatalkan" <?php selected($filter_status, 'dibatalkan'); ?>>❌ Batal</option>
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">Cari</button>
                    <a href="<?php echo admin_url('admin.php?page=dw-manajemen-pesanan-pusat'); ?>" class="bg-slate-100 text-slate-500 p-3 rounded-xl hover:bg-rose-100 transition shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-width="2.5"></path></svg>
                    </a>
                </div>
            </form>
        </div>

<<<<<<< HEAD
        <!-- Orders Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
=======
        

        <div class="dw-order-grid">
>>>>>>> 8f35bfe1b0fa76d9bd39ff009bf998dedb914bc0
            <?php if ($orders): foreach ($orders as $order): 
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
                
                $items = $wpdb->get_results($wpdb->prepare("SELECT nama_produk, jumlah, total_harga FROM $t_items WHERE id_sub_transaksi = %d", $order->id));
                
                $wa_pedagang = "https://wa.me/" . preg_replace('/[^0-9]/', '', $order->hp_pedagang);
                $wa_pembeli  = "https://wa.me/" . preg_replace('/[^0-9]/', '', $order->hp_pembeli);
            ?>
                <div class="order-card overflow-hidden">
                    <div class="p-6 border-b border-slate-50 bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-100 uppercase tracking-widest"><?php echo esc_html($order->nama_desa); ?></span>
                            <div class="status-badge-modern <?php echo $cfg['class']; ?>">
                                <span><?php echo $cfg['icon']; ?></span> <?php echo $cfg['label']; ?>
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-slate-800 tracking-tighter mb-1 uppercase">#<?php echo $order->kode_unik; ?></h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo date('d M Y • H:i', strtotime($order->created_at)); ?></p>
                    </div>

                    <div class="p-6 space-y-6 flex-1 bg-slate-50/30">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-white shadow-sm border border-slate-100 rounded-xl flex items-center justify-center text-orange-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16" stroke-width="2"></path></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="label-upper mb-1">Pedagang</p>
                                <p class="text-sm font-extrabold text-slate-800 truncate"><?php echo esc_html($order->nama_toko); ?></p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-white shadow-sm border border-slate-100 rounded-xl flex items-center justify-center text-blue-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="2"></path></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="label-upper mb-1">Pelanggan</p>
                                <p class="text-sm font-extrabold text-slate-800 truncate"><?php echo esc_html($order->nama_penerima); ?></p>
                                <p class="text-[11px] text-slate-500 truncate mt-1"><?php echo esc_html($order->alamat_pembeli); ?></p>
                            </div>
                        </div>

                        <div class="pt-5 border-t border-slate-200/50">
                            <p class="label-upper text-slate-800 mb-3">Item Belanja (<?php echo count($items); ?>)</p>
                            <div class="max-h-36 overflow-y-auto custom-scroll pr-2 space-y-2">
                                <?php foreach ($items as $item): ?>
                                    <div class="flex justify-between items-center bg-white p-2.5 rounded-xl border border-slate-100 shadow-sm">
                                        <div class="min-w-0 mr-4">
                                            <p class="text-xs font-bold text-slate-700 truncate"><?php echo esc_html($item->nama_produk); ?></p>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase">X <?php echo $item->jumlah; ?></p>
                                        </div>
                                        <p class="text-xs font-black text-indigo-700 italic shrink-0">Rp<?php echo number_format($item->total_harga, 0, ',', '.'); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-white border-t border-slate-100 rounded-b-[1.5rem] space-y-5">
                        
                        <!-- FIXED GRAND TOTAL: High Contrast (White Text on Dark Navy) -->
                        <div class="grand-total-box">
                            <div class="flex flex-col">
                                <span class="total-label text-[9px] font-black uppercase tracking-widest">Grand Total</span>
                                <span class="text-xl font-black tracking-tight italic">Rp<?php echo number_format($order->total_pesanan_toko, 0, ',', '.'); ?></span>
                            </div>
                            <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5"></path></svg>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <a href="<?php echo $wa_pedagang; ?>" target="_blank" class="btn-action flex items-center justify-center gap-2 bg-orange-50 text-orange-600 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest border border-orange-100 hover:bg-orange-100 transition shadow-sm">
                                WA Toko
                            </a>
                            <a href="<?php echo $wa_pembeli; ?>" target="_blank" class="btn-action flex items-center justify-center gap-2 bg-emerald-50 text-emerald-600 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest border border-emerald-100 hover:bg-emerald-100 transition shadow-sm">
                                WA Pembeli
                            </a>
                        </div>
                        
                        <!-- QUICK ACTIONS FORM -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <form method="post" action="">
                                <?php wp_nonce_field('dw_admin_override_action', 'dw_admin_override_nonce'); ?>
                                <input type="hidden" name="sub_order_id" value="<?php echo $order->id; ?>">
                                <input type="hidden" name="dw_admin_override" value="1">
                                
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest text-center mb-2">Update Status Admin</p>
                                <div class="flex gap-2">
                                    <select name="new_status" class="input-modern flex-1 px-4 py-2 text-[10px] font-black uppercase cursor-pointer appearance-none bg-no-repeat bg-[right_0.75rem_center]" style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'/%3E%3C/svg%3E'); background-size: 1rem;">
                                        <option value="">Pilih Aksi...</option>
                                        <option value="menunggu_konfirmasi" <?php selected($status, 'menunggu_konfirmasi'); ?>>⏳ Antrean</option>
                                        <option value="diproses" <?php selected($status, 'diproses'); ?>>⚙️ Diproses</option>
                                        <option value="diantar_ojek" <?php selected($status, 'diantar_ojek'); ?>>🛵 Kirim Ojek</option>
                                        <option value="selesai" <?php selected($status, 'selesai'); ?>>✅ Selesaikan</option>
                                        <option value="dibatalkan" <?php selected($status, 'dibatalkan'); ?>>❌ Batalkan</option>
                                    </select>
                                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-indigo-700 transition shadow-lg">SET</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="md:col-span-2 xl:col-span-3 py-32 text-center bg-white rounded-[2.5rem] border-2 border-dashed border-slate-200 flex flex-col items-center justify-center">
                    <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 mb-6">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" stroke-width="2.5"></path></svg>
                    </div>
                    <p class="text-slate-400 font-extrabold uppercase tracking-[0.3em] text-xl mb-2">Data Kosong</p>
                    <p class="text-slate-400 font-medium text-base">Tidak ada aliran pesanan masuk saat ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}