<?php
/**
 * File Name:   includes/admin-menus.php
 * Description: Mengatur menu admin dan meload halaman admin.
 * UPDATE: Fix Fatal Error & Path Loading
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definisi Konstanta Path (Fallback Safety)
if ( ! defined( 'DW_CORE_PATH' ) ) {
    if ( defined( 'DW_CORE_PLUGIN_DIR' ) ) {
        define( 'DW_CORE_PATH', DW_CORE_PLUGIN_DIR );
    } else {
        define( 'DW_CORE_PATH', plugin_dir_path( dirname( __FILE__ ) ) );
    }
}

/**
 * 1. FUNGSI RENDER (LAZY LOADING)
 */

function dw_render_dashboard() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-dashboard.php';
    if (function_exists('dw_dashboard_page_render')) dw_dashboard_page_render(); 
}

function dw_render_manajemen_pesanan_pusat() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-manajemen-pesanan-pusat.php';
    if (function_exists('dw_manajemen_pesanan_pusat_render')) dw_manajemen_pesanan_pusat_render();
}

function dw_render_kupon() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-kupon.php';
    if (function_exists('dw_kupon_page_render')) dw_kupon_page_render();
}

function dw_render_komplain() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-komplain.php';
    if (function_exists('dw_komplain_page_render')) dw_komplain_page_render();
}

function dw_render_pusat_verifikasi() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-pusat-verifikasi.php';
    if (function_exists('dw_pusat_verifikasi_render')) dw_pusat_verifikasi_render();
}

// MENU 2: DATA DESA
function dw_render_desa() { 
    $file = DW_CORE_PATH . 'includes/admin-pages/page-desa.php';
    if (file_exists($file)) {
        require_once $file;
        if (function_exists('dw_desa_page_render')) {
            dw_desa_page_render(); 
        } else {
            echo '<div class="notice notice-error"><p>Fungsi render desa tidak ditemukan.</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>File page-desa.php tidak ditemukan.</p></div>';
    }
}

// KHUSUS ADMIN DESA: Verifikasi Pedagang
function dw_render_desa_verifikasi() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-desa-verifikasi-pedagang.php';
    if (function_exists('dw_admin_desa_verifikasi_page_render')) {
        dw_admin_desa_verifikasi_page_render();
    }
}

// MENU 3: TOKO ATAU PEDAGANG
function dw_render_pedagang() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-pedagang.php';
    if (function_exists('dw_pedagang_page_render')) {
        dw_pedagang_page_render(); 
    } elseif (function_exists('dw_render_page_pedagang')) {
        dw_render_page_pedagang(); 
    }
}

function dw_render_produk() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-produk.php';
    if (function_exists('dw_produk_page_info_render')) {
        dw_produk_page_info_render(); 
    } elseif (function_exists('dw_produk_page_render')) {
        dw_produk_page_render();
    }
} 

function dw_render_wisata() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-wisata.php';
    if (function_exists('dw_wisata_page_render')) dw_wisata_page_render(); 
}

function dw_render_pesanan() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-pesanan-pedagang.php';
    if (function_exists('dw_pesanan_pedagang_page_render')) dw_pesanan_pedagang_page_render(); 
}

function dw_render_pembeli() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-pembeli.php';
    if (function_exists('dw_render_page_pembeli')) dw_render_page_pembeli();
}

function dw_render_komisi() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-komisi.php';
    if (function_exists('dw_render_komisi_page')) {
         dw_render_komisi_page(); 
    } elseif (function_exists('dw_komisi_page_render')) {
         dw_komisi_page_render(); 
    }
}

function dw_render_paket() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-paket-transaksi.php';
    if (function_exists('dw_render_paket_transaksi_page')) {
        dw_render_paket_transaksi_page();
    } elseif (function_exists('dw_paket_transaksi_page_render')) {
        dw_paket_transaksi_page_render(); 
    }
}

function dw_render_verifikasi_paket() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-verifikasi-paket.php';
    if (function_exists('dw_render_verifikasi_paket_page')) {
        dw_render_verifikasi_paket_page(); 
    } elseif (function_exists('dw_render_page_verifikasi_paket')) {
        dw_render_page_verifikasi_paket(); 
    }
}

function dw_render_promosi() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-promosi.php';
    if ( function_exists( 'dw_promosi_page_render' ) ) dw_promosi_page_render();
}

function dw_render_banner() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-banner.php';
    if (function_exists('dw_banner_page_render')) dw_banner_page_render(); 
}

function dw_render_reviews() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-reviews.php';
    if (function_exists('dw_reviews_moderation_page_render')) dw_reviews_moderation_page_render(); 
}

function dw_render_chat() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-chat.php';
    if (function_exists('dw_chat_page_render')) dw_chat_page_render(); 
}

function dw_render_logs() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-logs.php';
    if (function_exists('dw_logs_page_render')) dw_logs_page_render(); 
}

function dw_render_settings() { 
    require_once DW_CORE_PATH . 'includes/admin-pages/page-settings.php';
    if (function_exists('dw_admin_settings_page_handler')) dw_admin_settings_page_handler(); 
}

function dw_render_ojek_management() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-ojek-management.php';
    if (function_exists('dw_ojek_management_page_render')) dw_ojek_management_page_render();
}

function dw_render_ongkir() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-ongkir.php';
    if (function_exists('dw_ongkir_page_render')) {
        dw_ongkir_page_render();
    } elseif (function_exists('dw_render_page_ongkir')) {
        dw_render_page_ongkir();
    }
}

function dw_render_templates() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-templates.php';
    if (function_exists('dw_templates_page_render')) {
        dw_templates_page_render();
    } elseif (function_exists('dw_render_page_templates')) {
        dw_render_page_templates();
    }
}

function dw_render_referral_rewards() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-referral-rewards.php';
    if (function_exists('dw_render_referral_rewards_page')) {
        dw_render_referral_rewards_page();
    }
}

// [FIX UTAMA] Ganti nama fungsi wrapper agar tidak bentrok dengan page-verifikator-list.php
function dw_render_verifikator_list_page_wrapper() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-verifikator-list.php';
    if (function_exists('dw_render_verifikator_list_page')) {
        dw_render_verifikator_list_page();
    } elseif (function_exists('dw_render_page_verifikator_list')) {
        dw_render_page_verifikator_list();
    }
}

function dw_render_verifikator_dashboard_page() {
    require_once DW_CORE_PATH . 'includes/admin-pages/page-verifikator-umkm.php';
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

    if (current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Pusat Pesanan', 'Pusat Pesanan', 'manage_options', 'dw-manajemen-pesanan-pusat', 'dw_render_manajemen_pesanan_pusat');
        add_submenu_page('dw-dashboard', 'Pusat Verifikasi', 'Pusat Verifikasi', 'manage_options', 'dw-pusat-verifikasi', 'dw_render_pusat_verifikasi');
    }

    if (current_user_can('manage_options')) {
        // Panggil wrapper yang baru
        add_submenu_page('dw-dashboard', 'Daftar Verifikator', 'List Verifikator', 'manage_options', 'dw-verifikasi-list', 'dw_render_verifikator_list_page_wrapper');
        add_submenu_page('dw-dashboard', 'Manajemen Pembeli', 'Pembeli/Wisatawan', 'manage_options', 'dw-pembeli', 'dw_render_pembeli');
        add_submenu_page('dw-dashboard', 'Log Reward Referral', 'Reward Referral', 'manage_options', 'dw-referral-reward', 'dw_render_referral_rewards');
    }

    if (current_user_can('verifikator') || current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Verifikasi UMKM', 'Verifikasi UMKM', 'read', 'dw-verifikasi-umkm', 'dw_render_verifikator_dashboard_page');
    }

    add_submenu_page('dw-dashboard', 'Data Desa', 'Data Desa', 'read', 'dw-desa', 'dw_render_desa');
    add_submenu_page('dw-dashboard', 'Objek Wisata', 'Objek Wisata', 'edit_posts', 'dw-wisata', 'dw_render_wisata');
    add_submenu_page('dw-dashboard', 'Produk UMKM', 'Produk UMKM', 'edit_posts', 'dw-produk', 'dw_render_produk');
    add_submenu_page('dw-dashboard', 'Toko/Pedagang', 'Toko/Pedagang', 'read', 'dw-pedagang', 'dw_render_pedagang');
    
    if (current_user_can('pedagang') || current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Pesanan Masuk', 'Pesanan Masuk', 'read', 'dw-pesanan-pedagang', 'dw_render_pesanan');
        add_submenu_page('dw-dashboard', 'Inkuiri Chat', 'Inkuiri (Chat)', 'read', 'dw-chat-inquiry', 'dw_render_chat');
    }

    if (current_user_can('dw_approve_pedagang') || current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Verifikasi Pedagang', 'Verifikasi Pedagang', 'read', 'dw-desa-verifikasi', 'dw_render_desa_verifikasi');
    }

    if (current_user_can('dw_verify_ojek') || current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Manajemen Ojek', 'Ojek Desa', 'read', 'dw-manajemen-ojek', 'dw_render_ojek_management');
    }

    if (current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Paket & Kuota', 'Paket & Kuota', 'manage_options', 'dw-paket-transaksi', 'dw_render_paket');
        add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket', 'manage_options', 'dw-verifikasi-paket', 'dw_render_verifikasi_paket');
        add_submenu_page('dw-dashboard', 'Payout Komisi', 'Payout Komisi', 'manage_options', 'dw-komisi', 'dw_render_komisi');
        add_submenu_page('dw-dashboard', 'Promosi/Iklan', 'Promosi/Iklan', 'manage_options', 'dw-promosi', 'dw_render_promosi');
        add_submenu_page('dw-dashboard', 'Banner Promo', 'Banner Promo', 'manage_options', 'dw-banner', 'dw_render_banner');
        add_submenu_page('dw-dashboard', 'Kupon Diskon', 'Kupon Diskon', 'manage_options', 'dw-kupon', 'dw_render_kupon');
        add_submenu_page('dw-dashboard', 'Ulasan/Review', 'Ulasan/Review', 'moderate_comments', 'dw-reviews', 'dw_render_reviews');
        add_submenu_page('dw-dashboard', 'Ongkos Kirim', 'Ongkos Kirim', 'manage_options', 'dw-ongkir', 'dw_render_ongkir');
        add_submenu_page('dw-dashboard', 'Template WA', 'Template WA', 'manage_options', 'dw-templates', 'dw_render_templates');
        add_submenu_page('dw-dashboard', 'Logs Aktivitas', 'Logs Aktivitas', 'manage_options', 'dw-logs', 'dw_render_logs');
        add_submenu_page('dw-dashboard', 'Logbook Komplain', 'Logbook Komplain', 'manage_options', 'dw-komplain', 'dw_render_komplain');
        add_submenu_page('dw-dashboard', 'Pengaturan Sistem', 'Pengaturan', 'manage_options', 'dw-settings', 'dw_render_settings');
    }
}
add_action('admin_menu', 'dw_register_admin_menus');

function dw_cleanup_admin_menu_system() {
    if (current_user_can('manage_options')) return;
    
    if (current_user_can('dw_ojek') || current_user_can('pedagang') || current_user_can('verifikator')) {
        $menus_to_remove = array('index.php','upload.php','tools.php','themes.php','plugins.php','users.php','options-general.php','profile.php','edit.php','edit-comments.php');
        foreach($menus_to_remove as $m) remove_menu_page($m);
    }
}
add_action('admin_menu', 'dw_cleanup_admin_menu_system', 999);