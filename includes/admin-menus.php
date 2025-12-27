<?php
/**
 * File Name:   includes/admin-menus.php
 * Description: Mengatur menu admin dan meload halaman admin secara Lazy Loading.
 * UPDATE v3.5: Perbaikan Fatal Error get_userdata & Integrasi Verifikator.
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. FUNGSI RENDER (LAZY LOADING)
 * Memuat file halaman HANYA saat fungsi render dipanggil oleh WordPress.
 * Ini mencegah error "undefined function" saat plugin diload awal.
 */

function dw_render_dashboard() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-dashboard.php';
    if (function_exists('dw_dashboard_page_render')) dw_dashboard_page_render(); 
}

function dw_render_desa() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa.php';
    if (function_exists('dw_desa_page_render')) dw_desa_page_render(); 
}

function dw_render_pedagang() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pedagang.php';
    if (function_exists('dw_pedagang_page_render')) dw_pedagang_page_render(); 
}

function dw_render_produk() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-produk.php';
    if (function_exists('dw_produk_page_render')) {
        dw_produk_page_render();
    } elseif (function_exists('dw_produk_page_info_render')) {
        dw_produk_page_info_render();
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

function dw_render_komisi() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-komisi.php';
    if (function_exists('dw_komisi_page_render')) dw_komisi_page_render(); 
}

function dw_render_paket() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-paket-transaksi.php';
    if (function_exists('dw_paket_transaksi_page_render')) dw_paket_transaksi_page_render(); 
}

function dw_render_verifikasi_paket() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikasi-paket.php';
    if (function_exists('dw_render_page_verifikasi_paket')) {
        dw_render_page_verifikasi_paket(); 
    } elseif (function_exists('dw_verifikasi_paket_page_render')) {
        dw_verifikasi_paket_page_render();
    }
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

// v3.5: Render Khusus Verifikator
function dw_render_verifikator_list_page() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikator-list.php';
}

function dw_render_verifikator_dashboard_page() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikator-umkm.php';
}

/**
 * 3. MENDAFTARKAN MENU
 */
function dw_register_admin_menus() {
    
    add_menu_page('Desa Wisata', 'Desa Wisata', 'read', 'dw-dashboard', 'dw_render_dashboard', 'dashicons-location-alt', 20);
    add_submenu_page('dw-dashboard', 'Dashboard', 'Dashboard', 'read', 'dw-dashboard', 'dw_render_dashboard');
    add_submenu_page('dw-dashboard', 'Desa', 'Desa', 'dw_manage_desa', 'dw-desa', 'dw_render_desa');
    add_submenu_page('dw-dashboard', 'Wisata', 'Wisata', 'edit_posts', 'dw-wisata', 'dw_render_wisata');
    add_submenu_page('dw-dashboard', 'Kategori Wisata', 'Kategori Wisata', 'manage_categories', 'edit-tags.php?taxonomy=kategori_wisata&post_type=dw_wisata');

    // Menu: List Verifikator (Admin Only)
    if (current_user_can('administrator')) {
        add_submenu_page('dw-dashboard', 'List Verifikator', 'List Verifikator', 'manage_options', 'dw-verifikator-list', 'dw_render_verifikator_list_page');
    }

    // Menu: Dashboard Saya (Verifikator Only)
    if (current_user_can('verifikator_umkm')) {
        add_submenu_page('dw-dashboard', 'Dashboard Verifikator', 'Dashboard Saya', 'read', 'dw-verifikator-dashboard', 'dw_render_verifikator_dashboard_page');
    }

    if (current_user_can('edit_posts')) {
        add_submenu_page('dw-dashboard', 'Produk', 'Produk', 'edit_posts', 'dw-produk', 'dw_render_produk');
        add_submenu_page('dw-dashboard', 'Kategori Produk', 'Kategori Produk', 'manage_categories', 'edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk');
    }

    add_submenu_page('dw-dashboard', 'Manajemen Toko', 'Toko', 'dw_manage_pedagang', 'dw-pedagang', 'dw_render_pedagang');

    if (current_user_can('dw_verify_ojek') || current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Manajemen Ojek', 'Ojek Desa', 'dw_verify_ojek', 'dw-manajemen-ojek', 'dw_render_ojek_management');
    }

    if (current_user_can('pedagang')) {
        add_submenu_page('dw-dashboard', 'Pesanan Saya', 'Pesanan Saya', 'dw_manage_pesanan', 'dw-pesanan-pedagang', 'dw_render_pesanan');
        add_submenu_page('dw-dashboard', 'Inkuiri Produk', 'Inkuiri Produk', 'read', 'dw-chat-inquiry', 'dw_render_chat');
    }

    if (current_user_can('manage_options') || current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Paket Transaksi', 'Paket Transaksi', 'manage_options', 'dw-paket-transaksi', 'dw_render_paket');
        add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket', 'manage_options', 'dw-verifikasi-paket', 'dw_render_verifikasi_paket');
        add_submenu_page('dw-dashboard', 'Payout Komisi', 'Payout Komisi', 'manage_options', 'dw-komisi', 'dw_render_komisi');
    }

    if (current_user_can('manage_options') || current_user_can('dw_manage_promosi')) {
        add_submenu_page('dw-dashboard', 'Promosi', 'Promosi', 'manage_options', 'dw-promosi', 'dw_render_promosi');
    }
    
    if (current_user_can('manage_options') || current_user_can('dw_manage_banners')) {
        add_submenu_page('dw-dashboard', 'Banner', 'Banner', 'manage_options', 'dw-banner', 'dw_render_banner');
    }
    
    if (current_user_can('moderate_comments')) {
        add_submenu_page('dw-dashboard', 'Moderasi Ulasan', 'Ulasan', 'moderate_comments', 'dw-reviews', 'dw_render_reviews');
    }
    
    if (current_user_can('manage_options') || current_user_can('dw_view_logs')) {
        add_submenu_page('dw-dashboard', 'Logs', 'Logs', 'manage_options', 'dw-logs', 'dw_render_logs');
    }
    
    if (current_user_can('manage_options') || current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Pengaturan', 'Pengaturan', 'manage_options', 'dw-settings', 'dw_render_settings');
    }
}
add_action('admin_menu', 'dw_register_admin_menus');

/**
 * 4. CLEANUP & STYLES
 */
function dw_cleanup_admin_menu_ojek() {
    if (current_user_can('manage_options')) return;
    remove_menu_page('edit.php'); 
    remove_menu_page('edit-comments.php'); 
    if (current_user_can('dw_ojek')) {
        foreach(['index.php','upload.php','tools.php','themes.php','plugins.php','users.php','options-general.php','profile.php'] as $m) remove_menu_page($m);
    }
}
add_action('admin_menu', 'dw_cleanup_admin_menu_ojek', 999);

function dw_ojek_admin_styles() {
    if (current_user_can('dw_ojek') && !current_user_can('manage_options')) {
        echo '<style>#wpadminbar { display: none !important; } html.wp-toolbar { padding-top: 0 !important; } .wrap { margin: 10px !important; } .button-hero { width: 100%; padding: 15px !important; font-size: 18px !important; text-align: center; }</style>';
    }
}
add_action('admin_head', 'dw_ojek_admin_styles');