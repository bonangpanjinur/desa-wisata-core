<?php
/**
 * File Name:    includes/admin-pages/page-pusat-verifikasi.php
 * Description: Pusat Validasi (Unified Verification Center) untuk Admin.
 * Design Ref:   page-desa.php (Modern UI/UX Standard)
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_pusat_verifikasi_render() {
    global $wpdb;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pedagang';
    
    // 1. Ambil Statistik Live (Prefix dw_)
    $t_prefix = $wpdb->prefix . 'dw_';
    $count_pedagang = $wpdb->get_var("SELECT COUNT(*) FROM {$t_prefix}pedagang WHERE status = 'pending'");
    $count_paket    = $wpdb->get_var("SELECT COUNT(*) FROM {$t_prefix}produk WHERE status = 'pending'");
    $count_desa     = $wpdb->get_var("SELECT COUNT(*) FROM {$t_prefix}desa WHERE status = 'pending'");

    ?>
    <!-- Dependencies -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <style>
        /* Modern Design System - Ref: page-desa.php */
        :root { 
            --dw-primary: #4f46e5; 
            --dw-primary-dark: #3730a3;
            --dw-bg: #f8fafc; 
            --dw-text: #1e293b;
            --dw-text-light: #64748b;
            --dw-radius: 1rem;
            --dw-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        #wpcontent { background-color: var(--dw-bg) !important; padding-left: 20px !important; }
        .dw-admin-wrap { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--dw-text); max-width: 1400px; margin: 0 auto; }
        #wpfooter { display: none; }
        
        /* Vibrant Premium Header - Gradient High Contrast */
        .premium-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.2);
            padding: 2.5rem;
            color: #ffffff;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        /* Modern Stats Box */
        .stat-box-modern {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stat-box-modern:hover { transform: translateY(-3px); background: rgba(255, 255, 255, 0.25); }
        .stat-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: rgba(255, 255, 255, 0.7); letter-spacing: 0.1em; }
        .stat-value { font-size: 1.75rem; font-weight: 900; color: #ffffff; }

        /* Modern Navigation Tabs */
        .dw-modern-tabs { 
            display: flex; 
            gap: 0.75rem; 
            margin-bottom: 2rem; 
            background: #ffffff; 
            padding: 0.6rem; 
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            box-shadow: var(--dw-shadow);
        }

        .dw-modern-tab { 
            text-decoration: none !important; 
            color: var(--dw-text-light); 
            padding: 0.75rem 1.5rem; 
            font-weight: 700; 
            font-size: 0.875rem; 
            border-radius: 0.85rem; 
            display: flex; 
            align-items: center; 
            gap: 0.6rem; 
            transition: all 0.2s;
        }

        .dw-modern-tab:hover { background: #f1f5f9; color: var(--dw-primary); }

        .dw-modern-tab.active { 
            background: var(--dw-primary); 
            color: #ffffff !important; 
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
        }

        .badge-count {
            background: rgba(0, 0, 0, 0.1);
            color: inherit;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 800;
        }
        .active .badge-count { background: rgba(255, 255, 255, 0.2); }

        /* Card Content Style - Ref: page-desa.php */
        .dw-modern-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1.5rem;
            box-shadow: var(--dw-shadow);
            padding: 2rem;
            min-height: 400px;
        }

        /* Helper Info Banner */
        .admin-helper-banner {
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 1rem;
            padding: 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #9a3412;
        }

        .icon-helper {
            width: 3rem; height: 3rem; background: #ffedd5; border-radius: 0.75rem;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
    </style>

    <div class="wrap dw-admin-wrap mt-8 pr-6 pb-20">
        
        <!-- Header: Super Admin Command Center -->
        <div class="premium-header relative">
            <div class="relative z-10 flex flex-col lg:flex-row justify-between items-center gap-8">
                <div class="text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 bg-white/20 px-3 py-1 rounded-full backdrop-blur-md border border-white/20 mb-3">
                        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                        <span class="text-[10px] font-black uppercase tracking-widest">Akses Super Admin Pusat</span>
                    </div>
                    <h1 class="text-4xl font-extrabold tracking-tight mb-2">Pusat Validasi Global</h1>
                    <p class="text-indigo-100 font-medium text-lg">Membantu verifikasi tanpa mengganggu data referral/kepemilikan asli.</p>
                </div>
                
                <div class="flex flex-wrap justify-center gap-4">
                    <div class="stat-box-modern">
                        <p class="stat-label">Pedagang</p>
                        <p class="stat-value"><?php echo number_format($count_pedagang); ?></p>
                    </div>
                    <div class="stat-box-modern">
                        <p class="stat-label">Produk</p>
                        <p class="stat-value"><?php echo number_format($count_paket); ?></p>
                    </div>
                    <div class="stat-box-modern">
                        <p class="stat-label">Akun Desa</p>
                        <p class="stat-value"><?php echo number_format($count_desa); ?></p>
                    </div>
                </div>
            </div>
            <!-- Decorative circle -->
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
        </div>

        <!-- Role Insight Banner -->
        <div class="admin-helper-banner shadow-sm">
            <div class="icon-helper">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5"></path></svg>
            </div>
            <div>
                <h4 class="font-black text-sm uppercase mb-1">Filosofi Bantuan Pusat</h4>
                <p class="text-sm opacity-90 leading-snug">Sebagai Admin, Anda dapat melakukan verifikasi data milik verifikator mana pun. Tindakan Anda bersifat **fasilitator**; sistem akan tetap mencatat referral sesuai pendaftar awal.</p>
            </div>
        </div>

        <!-- Modern Tabs Navigation -->
        <nav class="dw-modern-tabs">
            <a href="?page=dw-pusat-verifikasi&tab=pedagang" class="dw-modern-tab <?php echo $active_tab == 'pedagang' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-store"></span>
                <span>Pedagang / UMKM</span>
                <?php if($count_pedagang > 0): ?><span class="badge-count"><?php echo $count_pedagang; ?></span><?php endif; ?>
            </a>
            <a href="?page=dw-pusat-verifikasi&tab=paket" class="dw-modern-tab <?php echo $active_tab == 'paket' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-archive"></span>
                <span>Paket & Produk</span>
                <?php if($count_paket > 0): ?><span class="badge-count"><?php echo $count_paket; ?></span><?php endif; ?>
            </a>
            <a href="?page=dw-pusat-verifikasi&tab=desa" class="dw-modern-tab <?php echo $active_tab == 'desa' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-admin-home"></span>
                <span>Verifikasi Akun Desa</span>
                <?php if($count_desa > 0): ?><span class="badge-count"><?php echo $count_desa; ?></span><?php endif; ?>
            </a>
        </nav>

        <!-- Main Content Area: Modern Card Wrapper -->
        <div class="dw-modern-card">
            <?php
            switch ($active_tab) {
                case 'pedagang':
                    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikator-umkm.php';
                    if (function_exists('dw_render_verifikator_umkm_page')) {
                        // Admin melihat semua pedagang dengan status pending
                        dw_render_verifikator_umkm_page(); 
                    }
                    break;
                case 'paket':
                    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikasi-paket.php';
                    if (function_exists('dw_render_page_verifikasi_paket')) {
                        dw_render_page_verifikasi_paket();
                    }
                    break;
                case 'desa':
                    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa-verifikasi-pedagang.php';
                    if (function_exists('dw_admin_desa_verifikasi_page_render')) {
                        dw_admin_desa_verifikasi_page_render();
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}