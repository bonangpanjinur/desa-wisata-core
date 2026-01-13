<?php
/**
 * File Name: includes/admin-pages/page-pusat-verifikasi.php
 * Description: Pusat Validasi (Unified Verification Center) untuk Admin.
 * Layout: Split View (List Kiri - Detail Kanan)
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- 1. DEFINISI PATH & INCLUDE (SOLUSI ERROR BLANK PAGE) ---
$current_dir = dirname(__FILE__); // includes/admin-pages
$includes_dir = dirname($current_dir); // includes

// Include UI components
$ui_path = $includes_dir . '/admin-ui-components.php';
if (file_exists($ui_path)) {
    require_once $ui_path;
}

/**
 * FUNGSI RENDER UTAMA
 * Nama fungsi disesuaikan dengan panggilan di admin-menus.php: dw_pusat_verifikasi_render
 */
if (!function_exists('dw_pusat_verifikasi_render')) {
    function dw_pusat_verifikasi_render() {
        global $wpdb;
        
        // --- LOGIC: HANDLE POST ACTIONS (VERIFIKASI) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('dw_verify_action')) {
            $type = sanitize_text_field($_POST['verify_type']);
            $id   = intval($_POST['verify_id']);
            $act  = sanitize_text_field($_POST['verify_decision']); // approve / reject
            
            if ($type === 'pedagang') {
                // Update status pendaftaran
                $status = ($act === 'approve') ? 'disetujui' : 'ditolak';
                $update_data = [
                    'status_pendaftaran' => $status, 
                    'verified_at' => current_time('mysql'), 
                    'is_verified' => ($act === 'approve' ? 1 : 0)
                ];
                
                // Jika diapprove, aktifkan juga akunnya agar bisa login
                if ($act === 'approve') {
                    $update_data['status_akun'] = 'aktif';
                    // Opsional: Set approved_by
                    $current_user = wp_get_current_user();
                    $update_data['approved_by'] = $current_user->user_login;
                }

                $wpdb->update("{$wpdb->prefix}dw_pedagang", $update_data, ['id' => $id]);
                
                // Add Feedback
                add_settings_error('dw_verify', 'success', "Pedagang berhasil " . ucfirst($status), 'updated');
            }
        }

        // --- PREPARE DATA ---
        // 1. Ambil Statistik Live
        $t_prefix = $wpdb->prefix . 'dw_';
        
        // Cek kolom yang valid untuk pending count
        // Pedagang: status_pendaftaran = 'menunggu'
        $count_pedagang = $wpdb->get_var("SELECT COUNT(*) FROM {$t_prefix}pedagang WHERE status_pendaftaran = 'menunggu'");
        
        // Desa: status_akses_verifikasi = 'pending'
        $count_desa     = $wpdb->get_var("SELECT COUNT(*) FROM {$t_prefix}desa WHERE status_akses_verifikasi = 'pending'");

        // 2. Ambil Data Antrean (List Kiri)
        // Default tab pedagang
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pedagang';
        
        $pending_list = [];
        if ($active_tab == 'pedagang') {
            $pending_list = $wpdb->get_results("SELECT * FROM {$t_prefix}pedagang WHERE status_pendaftaran = 'menunggu' ORDER BY created_at ASC");
        } elseif ($active_tab == 'desa') {
            $pending_list = $wpdb->get_results("SELECT * FROM {$t_prefix}desa WHERE status_akses_verifikasi = 'pending' ORDER BY created_at ASC");
        }

        // 3. Ambil Data Detail (Kanan) jika ada ID terpilih
        $selected_item = null;
        $selected_type = $active_tab; 
        
        if (isset($_GET['view_id']) && isset($_GET['type'])) {
            $vid = intval($_GET['view_id']);
            $vtype = sanitize_text_field($_GET['type']);
            
            if ($vtype === 'pedagang') {
                $selected_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t_prefix}pedagang WHERE id = %d", $vid));
            } elseif ($vtype === 'desa') {
                $selected_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t_prefix}desa WHERE id = %d", $vid));
            }
        }
        ?>
        
        <!-- Dependencies Font & Tailwind -->
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

        <style>
            /* CSS Reset & Admin Style Wrapper */
            .dw-admin-wrap { font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; max-width: 1400px; margin: 20px 20px 0 0; }
            #wpcontent { background-color: #f8fafc !important; padding-left: 20px !important; }
            
            /* Stats Card */
            .dw-stat-card {
                background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;
                display: flex; align-items: center; gap: 15px; transition: all 0.2s; cursor: pointer;
            }
            .dw-stat-card:hover, .dw-stat-card.active-stat {
                border-color: #4f46e5; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1);
            }
            .dw-stat-icon-wrapper {
                width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;
            }
            .bg-blue { background: #e0e7ff; color: #4338ca; }
            .bg-green { background: #dcfce7; color: #15803d; }
            
            .dw-stat-value { margin: 0; font-size: 24px; font-weight: 800; line-height: 1; }
            .dw-stat-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }

            /* Split Layout */
            .dw-grid-2-col { display: grid; grid-template-columns: 350px 1fr; gap: 24px; align-items: start; }
            @media (max-width: 1024px) { .dw-grid-2-col { grid-template-columns: 1fr; } }

            /* Card */
            .dw-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
            .dw-card-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
            .card-heading { margin: 0; font-size: 16px; font-weight: 700; color: #1e293b; }
            .dw-card-body { padding: 20px; }

            /* List Items */
            .list-item { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.1s; }
            .list-item:hover { background: #f8fafc; }
            .list-item.selected { background: #eff6ff; border-left: 3px solid #4f46e5; }
            
            /* Buttons */
            .dw-button { 
                padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: 1px solid transparent; 
                display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
            }
            .dw-button-primary { background: #4f46e5; color: white; border-color: #4f46e5; }
            .dw-button-primary:hover { background: #4338ca; }
            .dw-button-secondary { background: #fff; border-color: #cbd5e1; color: #475569; }
            .dw-button-secondary:hover { background: #f8fafc; border-color: #94a3b8; }
        </style>

        <div class="wrap dw-admin-wrap">
            <!-- HEADER -->
            <div class="mb-8">
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Pusat Validasi</h1>
                <p class="text-slate-500 mt-1 text-base">Verifikasi data pendaftaran mitra baru (UMKM & Desa).</p>
            </div>

            <!-- NOTIFIKASI WP -->
            <?php settings_errors(); ?>

            <div class="dw-content-body">
                
                <!-- STATS CARDS (TAB NAVIGATION) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <a href="?page=dw-pusat-verifikasi&tab=pedagang" style="text-decoration:none;">
                        <div class="dw-stat-card <?php echo ($active_tab == 'pedagang') ? 'active-stat ring-2 ring-indigo-500 ring-offset-2' : ''; ?>">
                            <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-store"></span></div>
                            <div>
                                <h4 class="dw-stat-value"><?php echo intval($count_pedagang); ?></h4>
                                <span class="dw-stat-label">Pedagang Pending</span>
                            </div>
                        </div>
                    </a>
                    <a href="?page=dw-pusat-verifikasi&tab=desa" style="text-decoration:none;">
                        <div class="dw-stat-card <?php echo ($active_tab == 'desa') ? 'active-stat ring-2 ring-indigo-500 ring-offset-2' : ''; ?>">
                            <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-admin-home"></span></div>
                            <div>
                                <h4 class="dw-stat-value"><?php echo intval($count_desa); ?></h4>
                                <span class="dw-stat-label">Desa Pending</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- MAIN LAYOUT: SPLIT VIEW -->
                <div class="dw-grid-2-col">
                    
                    <!-- KOLOM KIRI: LIST ANTREAN -->
                    <div class="dw-column-list">
                        <div class="dw-card h-full min-h-[500px] flex flex-col">
                            <div class="dw-card-header bg-slate-50">
                                <h3 class="card-heading text-sm uppercase tracking-wider text-slate-500">Antrean <?php echo ucfirst($active_tab); ?></h3>
                                <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-1 rounded"><?php echo count($pending_list); ?></span>
                            </div>
                            <div class="dw-card-body p-0 overflow-y-auto max-h-[600px]">
                                <?php if ($pending_list): foreach ($pending_list as $p): 
                                    $is_active = ($selected_item && $selected_item->id == $p->id) ? 'selected' : '';
                                    $title = ($active_tab == 'pedagang') ? $p->nama_toko : $p->nama_desa;
                                    $sub = ($active_tab == 'pedagang') ? $p->nama_pemilik : 'Kec. ' . $p->kecamatan;
                                ?>
                                    <div class="list-item <?php echo $is_active; ?>" onclick="window.location.href='?page=dw-pusat-verifikasi&tab=<?php echo $active_tab; ?>&type=<?php echo $active_tab; ?>&view_id=<?php echo $p->id; ?>'">
                                        <div class="flex justify-between items-start mb-1">
                                            <strong class="text-slate-800 text-sm"><?php echo esc_html($title); ?></strong>
                                            <small class="text-xs text-slate-400"><?php echo date('d M', strtotime($p->created_at)); ?></small>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            <?php echo esc_html($sub); ?>
                                        </div>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="p-8 text-center text-slate-400">
                                        <span class="dashicons dashicons-yes-alt text-4xl mb-2 block mx-auto text-emerald-300"></span>
                                        Tidak ada antrean <?php echo $active_tab; ?>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- KOLOM KANAN: DETAIL & AKSI -->
                    <div class="dw-column-detail">
                        <?php if ($selected_item): ?>
                            <div class="dw-card border-t-4 border-t-indigo-500 shadow-lg">
                                <div class="dw-card-header">
                                    <h3 class="card-heading">Detail Verifikasi</h3>
                                    <span class="bg-amber-100 text-amber-700 border border-amber-200 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Menunggu Review</span>
                                </div>
                                <div class="dw-card-body">
                                    
                                    <?php if($active_tab == 'pedagang'): ?>
                                        <!-- DETAIL PEDAGANG -->
                                        <div class="flex items-center gap-4 mb-6">
                                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center overflow-hidden border border-slate-200">
                                                <img src="<?php echo $selected_item->foto_profil ?: 'https://via.placeholder.com/80'; ?>" class="w-full h-full object-cover">
                                            </div>
                                            <div>
                                                <h2 class="text-2xl font-bold text-slate-800 m-0 leading-tight"><?php echo esc_html($selected_item->nama_toko); ?></h2>
                                                <p class="text-slate-500 m-0">Pemilik: <?php echo esc_html($selected_item->nama_pemilik); ?></p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4 mb-6">
                                            <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">NIK Pemilik</label>
                                                <div class="font-mono text-sm text-slate-700"><?php echo esc_html($selected_item->nik); ?></div>
                                            </div>
                                            <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                                <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Kontak WA</label>
                                                <div class="font-mono text-sm text-slate-700"><?php echo esc_html($selected_item->nomor_wa); ?></div>
                                            </div>
                                        </div>

                                        <div class="mb-6">
                                            <label class="text-xs font-bold text-slate-500 uppercase block mb-2">Alamat Lengkap</label>
                                            <div class="bg-white p-4 rounded-lg border border-slate-200 text-sm text-slate-600">
                                                <?php echo esc_html($selected_item->alamat_lengkap); ?><br>
                                                <span class="text-slate-400 text-xs mt-1 block">
                                                    Kel. <?php echo esc_html($selected_item->kelurahan_nama); ?>, 
                                                    Kec. <?php echo esc_html($selected_item->kecamatan_nama); ?>, 
                                                    <?php echo esc_html($selected_item->kabupaten_nama); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mb-8">
                                            <label class="text-xs font-bold text-slate-500 uppercase block mb-2">Dokumen KTP</label>
                                            <div class="bg-slate-100 p-4 rounded-lg border border-slate-200 flex justify-center">
                                                <?php if($selected_item->url_ktp): ?>
                                                    <a href="<?php echo esc_url($selected_item->url_ktp); ?>" target="_blank">
                                                        <img src="<?php echo esc_url($selected_item->url_ktp); ?>" class="max-h-48 rounded shadow-sm hover:scale-105 transition-transform">
                                                    </a>
                                                <?php else: ?>
                                                    <div class="text-slate-400 flex flex-col items-center">
                                                        <span class="dashicons dashicons-format-image text-4xl mb-1"></span>
                                                        <span>Tidak ada foto KTP</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    <?php elseif($active_tab == 'desa'): ?>
                                        <!-- DETAIL DESA -->
                                        <div class="mb-6">
                                            <h2 class="text-2xl font-bold text-slate-800 m-0"><?php echo esc_html($selected_item->nama_desa); ?></h2>
                                            <p class="text-slate-500">Lokasi: <?php echo esc_html($selected_item->kecamatan . ', ' . $selected_item->kabupaten); ?></p>
                                        </div>
                                        
                                        <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg mb-6">
                                            <h4 class="text-yellow-800 font-bold text-sm uppercase m-0 mb-2">Bukti Pembayaran Premium</h4>
                                            <?php if($selected_item->bukti_bayar_akses): ?>
                                                <a href="<?php echo esc_url($selected_item->bukti_bayar_akses); ?>" target="_blank">
                                                    <img src="<?php echo esc_url($selected_item->bukti_bayar_akses); ?>" class="max-h-64 rounded border border-yellow-100">
                                                </a>
                                            <?php else: ?>
                                                <p class="text-yellow-600 text-sm">Tidak ada bukti bayar yang dilampirkan.</p>
                                            <?php endif; ?>
                                        </div>

                                    <?php endif; ?>

                                    <!-- ACTION BUTTONS -->
                                    <div class="bg-slate-50 p-5 rounded-xl border-t border-slate-100">
                                        <form method="post">
                                            <?php wp_nonce_field('dw_verify_action'); ?>
                                            <input type="hidden" name="verify_type" value="<?php echo esc_attr($active_tab); ?>">
                                            <input type="hidden" name="verify_id" value="<?php echo esc_attr($selected_item->id); ?>">
                                            
                                            <div class="flex gap-4">
                                                <button type="submit" name="verify_decision" value="approve" class="dw-button dw-button-primary w-full text-base py-3 shadow-lg shadow-indigo-200 hover:shadow-indigo-300 transition-all">
                                                    <span class="dashicons dashicons-yes mr-2"></span> Setujui & Aktifkan
                                                </button>
                                                <button type="submit" name="verify_decision" value="reject" class="dw-button dw-button-secondary w-full text-base py-3 text-red-600 border-red-100 hover:bg-red-50">
                                                    <span class="dashicons dashicons-no mr-2"></span> Tolak
                                                </button>
                                            </div>
                                            <p class="text-center text-xs text-slate-400 mt-3">
                                                Tindakan ini akan mengirim notifikasi email/WA ke pengguna terkait.
                                            </p>
                                        </form>
                                    </div>

                                </div>
                            </div>
                        <?php else: ?>
                            <!-- EMPTY STATE DETAIL -->
                            <div class="dw-card h-full min-h-[500px] flex items-center justify-center bg-slate-50/50 border-dashed border-2">
                                <div class="text-center text-slate-400">
                                    <span class="dashicons dashicons-arrow-left-alt2 text-5xl mb-4 block mx-auto opacity-20"></span>
                                    <h3 class="text-lg font-bold text-slate-500 m-0">Pilih Data</h3>
                                    <p class="m-0 text-sm">Klik salah satu item dari daftar antrean di sebelah kiri<br>untuk melihat detail dan melakukan validasi.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}