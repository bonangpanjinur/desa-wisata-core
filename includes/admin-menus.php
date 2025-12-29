<?php
/**
 * File Name:   includes/admin-menus.php
 * Description: Mengatur menu admin dan meload halaman admin.
 * UPDATE: Memastikan 3 menu utama (Reward, Desa, Pedagang) muncul dan terhubung file yang benar.
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. FUNGSI RENDER (LAZY LOADING)
 */

function dw_render_dashboard() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-dashboard.php';
    if (function_exists('dw_dashboard_page_render')) dw_dashboard_page_render(); 
}

// MENU 2: DATA DESA
function dw_render_desa() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa.php';
    if (function_exists('dw_desa_page_render')) {
        dw_desa_page_render(); 
    } elseif (function_exists('dw_render_page_desa')) {
        dw_render_page_desa(); 
    }
}

// KHUSUS ADMIN DESA: Verifikasi Pedagang
function dw_render_desa_verifikasi() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa-verifikasi-pedagang.php';
    if (function_exists('dw_admin_desa_verifikasi_page_render')) {
        dw_admin_desa_verifikasi_page_render();
    }
}

// MENU 3: TOKO ATAU PEDAGANG
function dw_render_pedagang() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pedagang.php';
    if (function_exists('dw_render_page_pedagang')) {
        dw_render_page_pedagang(); 
    }
}

function dw_render_produk() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-produk.php';
    if (function_exists('dw_produk_page_info_render')) {
        dw_produk_page_info_render(); 
    } elseif (function_exists('dw_produk_page_render')) {
        dw_produk_page_render();
    }
} 

function dw_render_wisata() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-wisata.php';
    if (function_exists('dw_wisata_page_render')) dw_wisata_page_render(); 
}

function dw_render_pesanan() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pesanan-pedagang.php';
    if (function_exists('dw_pesanan_pedagang_page_render')) dw_pesanan_pedagang_page_render(); 
}

function dw_render_pembeli() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pembeli.php';
    if (function_exists('dw_render_page_pembeli')) dw_render_page_pembeli();
}

function dw_render_komisi() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-komisi.php';
    if (function_exists('dw_komisi_page_render')) dw_komisi_page_render(); 
}

function dw_render_paket() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-paket-transaksi.php';
    if (function_exists('dw_render_paket_transaksi_page')) {
        dw_render_paket_transaksi_page();
    } elseif (function_exists('dw_paket_transaksi_page_render')) {
        dw_paket_transaksi_page_render(); 
    }
}

function dw_render_verifikasi_paket() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikasi-paket.php';
    if (function_exists('dw_render_page_verifikasi_paket')) dw_render_page_verifikasi_paket(); 
}

function dw_render_promosi() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-promosi.php';
    if ( function_exists( 'dw_promosi_page_render' ) ) dw_promosi_page_render();
}

function dw_render_banner() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-banner.php';
    if (function_exists('dw_banner_page_render')) dw_banner_page_render(); 
}

function dw_render_reviews() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-reviews.php';
    if (function_exists('dw_reviews_moderation_page_render')) dw_reviews_moderation_page_render(); 
}

function dw_render_chat() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-chat.php';
    if (function_exists('dw_chat_page_render')) dw_chat_page_render(); 
}

function dw_render_logs() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-logs.php';
    if (function_exists('dw_logs_page_render')) dw_logs_page_render(); 
}

function dw_render_settings() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-settings.php';
    if (function_exists('dw_admin_settings_page_handler')) dw_admin_settings_page_handler(); 
}

function dw_render_ojek_management() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-ojek-management.php';
    if (function_exists('dw_ojek_management_page_render')) dw_ojek_management_page_render();
}

function dw_render_ongkir() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-ongkir.php';
    if (function_exists('dw_ongkir_page_render')) {
        dw_ongkir_page_render();
    } elseif (function_exists('dw_render_page_ongkir')) {
        dw_render_page_ongkir();
    }
}

function dw_render_templates() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-templates.php';
    if (function_exists('dw_templates_page_render')) {
        dw_templates_page_render();
    } elseif (function_exists('dw_render_page_templates')) {
        dw_render_page_templates();
    }
}

// MENU 1: REFERRAL REWARDS (Fix Typos & Path)
function dw_render_referral_rewards() {
    $file_path_typo = DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-refferal-rewards.php';
    $file_path_correct = DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-referral-rewards.php';
    
    if (file_exists($file_path_correct)) {
        require_once $file_path_correct;
    } elseif (file_exists($file_path_typo)) {
        require_once $file_path_typo;
    } else {
        echo '<div class="wrap"><h1>Log Reward Referral</h1><p>File halaman (page-referral-rewards.php) tidak ditemukan.</p></div>';
        return;
    }

    if (function_exists('dw_render_referral_rewards_page')) {
        dw_render_referral_rewards_page();
    }
}

// Verifikator List
function dw_render_verifikator_list_page() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikator-list.php';
    if (function_exists('dw_render_page_verifikator_list')) {
        dw_render_page_verifikator_list();
    }
}

// Verifikator Dashboard
function dw_render_verifikator_dashboard_page() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikator-umkm.php';
    if (function_exists('dw_render_verifikator_umkm_page')) {
        dw_render_verifikator_umkm_page();
    } elseif (function_exists('dw_render_page_verifikasi_umkm')) {
        dw_render_page_verifikasi_umkm();
    }
}

/**
 * 2. MENDAFTARKAN MENU
 */
function dw_register_admin_menus() {
    
    add_menu_page('Desa Wisata', 'Desa Wisata', 'read', 'dw-dashboard', 'dw_render_dashboard', 'dashicons-location-alt', 20);
    
    add_submenu_page('dw-dashboard', 'Dashboard', 'Dashboard', 'read', 'dw-dashboard', 'dw_render_dashboard');

    // --- MANAJEMEN PENGGUNA ---
    // Pastikan user admin melihat ini
    if (current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Daftar Verifikator', 'List Verifikator', 'manage_options', 'dw-verifikator-list', 'dw_render_verifikator_list_page');
        add_submenu_page('dw-dashboard', 'Manajemen Pembeli', 'Pembeli/Wisatawan', 'manage_options', 'dw-pembeli', 'dw_render_pembeli');
        
        // Menu 1: Log Reward Referral
        add_submenu_page('dw-dashboard', 'Log Reward Referral', 'Reward Referral', 'manage_options', 'dw-referral-reward', 'dw_render_referral_rewards');
    }

    // --- VERIFIKASI AKUN ---
    if (current_user_can('verifikator_umkm') || current_user_can('administrator')) {
        add_submenu_page('dw-dashboard', 'Verifikasi Akun', 'Verifikasi Akun', 'read', 'dw-verifikator-dashboard', 'dw_render_verifikator_dashboard_page');
    }
    
    // Verifikasi oleh Desa
    if (current_user_can('dw_approve_pedagang') || current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Verifikasi Pedagang', 'Verifikasi Pedagang', 'read', 'dw-desa-verifikasi', 'dw_render_desa_verifikasi');
    }

    // --- DATA MASTER ---
    // Menu 2 & 3: Desa & Pedagang
    // Menggunakan 'manage_options' sebagai fallback agar Admin selalu bisa akses jika capability khusus bermasalah
    add_submenu_page('dw-dashboard', 'Data Desa', 'Data Desa', 'read', 'dw-desa', 'dw_render_desa');
    add_submenu_page('dw-dashboard', 'Objek Wisata', 'Objek Wisata', 'edit_posts', 'dw-wisata', 'dw_render_wisata');
    add_submenu_page('dw-dashboard', 'Produk UMKM', 'Produk UMKM', 'edit_posts', 'dw-produk', 'dw_render_produk');
    add_submenu_page('dw-dashboard', 'Toko/Pedagang', 'Toko/Pedagang', 'read', 'dw-pedagang', 'dw_render_pedagang');
    
    // --- OPERASIONAL ---
    if (current_user_can('pedagang')) {
        add_submenu_page('dw-dashboard', 'Pesanan Masuk', 'Pesanan Masuk', 'dw_manage_pesanan', 'dw-pesanan-pedagang', 'dw_render_pesanan');
        add_submenu_page('dw-dashboard', 'Inkuiri Chat', 'Inkuiri (Chat)', 'read', 'dw-chat-inquiry', 'dw_render_chat');
    }

    if (current_user_can('dw_verify_ojek') || current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Manajemen Ojek', 'Ojek Desa', 'read', 'dw-manajemen-ojek', 'dw_render_ojek_management');
    }

    // --- ADMIN SETTINGS ---
    if (current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Paket & Kuota', 'Paket & Kuota', 'manage_options', 'dw-paket-transaksi', 'dw_render_paket');
        add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket', 'manage_options', 'dw-verifikasi-paket', 'dw_render_verifikasi_paket');
        add_submenu_page('dw-dashboard', 'Payout Komisi', 'Payout Komisi', 'manage_options', 'dw-komisi', 'dw_render_komisi');
        add_submenu_page('dw-dashboard', 'Promosi/Iklan', 'Promosi/Iklan', 'manage_options', 'dw-promosi', 'dw_render_promosi');
        add_submenu_page('dw-dashboard', 'Banner Promo', 'Banner Promo', 'manage_options', 'dw-banner', 'dw_render_banner');
        add_submenu_page('dw-dashboard', 'Ulasan/Review', 'Ulasan/Review', 'moderate_comments', 'dw-reviews', 'dw_render_reviews');
        
        add_submenu_page('dw-dashboard', 'Ongkos Kirim', 'Ongkos Kirim', 'manage_options', 'dw-ongkir', 'dw_render_ongkir');
        add_submenu_page('dw-dashboard', 'Template WA', 'Template WA', 'manage_options', 'dw-templates', 'dw_render_templates');
        
        add_submenu_page('dw-dashboard', 'Logs Aktivitas', 'Logs Aktivitas', 'manage_options', 'dw-logs', 'dw_render_logs');
        add_submenu_page('dw-dashboard', 'Pengaturan Sistem', 'Pengaturan', 'manage_options', 'dw-settings', 'dw_render_settings');
    }
}
add_action('admin_menu', 'dw_register_admin_menus');

/**
 * 3. CLEANUP MENU
 */
function dw_cleanup_admin_menu_system() {
    if (current_user_can('manage_options')) return;
    
    if (current_user_can('dw_ojek') || current_user_can('pedagang')) {
        $menus_to_remove = array('index.php','upload.php','tools.php','themes.php','plugins.php','users.php','options-general.php','profile.php','edit.php','edit-comments.php');
        foreach($menus_to_remove as $m) remove_menu_page($m);
    }
}
add_action('admin_menu', 'dw_cleanup_admin_menu_system', 999);