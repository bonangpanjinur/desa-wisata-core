<?php
/**
 * File Name:   includes/admin-menus.php
 * Description: Mengatur menu admin dan meload halaman admin secara Lazy Loading.
 * UPDATE v3.6: Integrasi Penuh Manajemen Pembeli, Verifikator UMKM, & Semua Fitur Operasional.
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. FUNGSI RENDER (LAZY LOADING)
 * Memuat file halaman hanya saat menu diklik untuk efisiensi performa.
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
    if (function_exists('dw_produk_page_render')) dw_produk_page_render(); 
} 

function dw_render_wisata() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-wisata.php';
    if (function_exists('dw_wisata_page_render')) dw_wisata_page_render(); 
}

function dw_render_pesanan() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pesanan-pedagang.php';
    if (function_exists('dw_pesanan_pedagang_page_render')) dw_pesanan_pedagang_page_render(); 
}

// v3.6: Render Manajemen Pembeli
function dw_render_pembeli() { 
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pembeli.php';
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
    if (function_exists('dw_promosi_page_render')) dw_promosi_page_render();
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

// Render Khusus Verifikator
function dw_render_verifikator_list_page() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikator-list.php';
}

function dw_render_verifikator_dashboard_page() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikator-umkm.php';
}

/**
 * 2. MENDAFTARKAN MENU KE WORDPRESS
 */
function dw_register_admin_menus() {
    
    // Parent Menu Utama
    add_menu_page('Desa Wisata', 'Desa Wisata', 'read', 'dw-dashboard', 'dw_render_dashboard', 'dashicons-location-alt', 20);
    
    // Submenu: Dashboard Utama
    add_submenu_page('dw-dashboard', 'Dashboard', 'Dashboard', 'read', 'dw-dashboard', 'dw_render_dashboard');

    // --- KELOMPOK: MANAJEMEN PENGGUNA (ADMIN PUSAT) ---
    if (current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'List Verifikator', 'List Verifikator', 'manage_options', 'dw-verifikator-list', 'dw_render_verifikator_list_page');
        add_submenu_page('dw-dashboard', 'Manajemen Pembeli', 'Pembeli/Wisatawan', 'manage_options', 'dw-pembeli', 'dw_render_pembeli');
    }

    // --- KELOMPOK: VERIFIKASI AKUN ---
    if (current_user_can('verifikator_umkm') || current_user_can('administrator')) {
        add_submenu_page('dw-dashboard', 'Verifikasi Akun', 'Verifikasi Akun', 'read', 'dw-verifikator-dashboard', 'dw_render_verifikator_dashboard_page');
    }

    // --- KELOMPOK: DATA MASTER ---
    add_submenu_page('dw-dashboard', 'Desa', 'Data Desa', 'dw_manage_desa', 'dw-desa', 'dw_render_desa');
    add_submenu_page('dw-dashboard', 'Wisata', 'Objek Wisata', 'edit_posts', 'dw-wisata', 'dw_render_wisata');
    add_submenu_page('dw-dashboard', 'Produk UMKM', 'Produk UMKM', 'edit_posts', 'dw-produk', 'dw_render_produk');
    add_submenu_page('dw-dashboard', 'Manajemen Toko', 'Toko/Pedagang', 'dw_manage_pedagang', 'dw-pedagang', 'dw_render_pedagang');
    
    // --- KELOMPOK: OPERASIONAL PEDAGANG & OJEK ---
    if (current_user_can('pedagang')) {
        add_submenu_page('dw-dashboard', 'Pesanan Saya', 'Pesanan Masuk', 'dw_manage_pesanan', 'dw-pesanan-pedagang', 'dw_render_pesanan');
        add_submenu_page('dw-dashboard', 'Inkuiri Produk', 'Inkuiri (Chat)', 'read', 'dw-chat-inquiry', 'dw_render_chat');
    }

    if (current_user_can('dw_verify_ojek') || current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Manajemen Ojek', 'Ojek Desa', 'dw_verify_ojek', 'dw-manajemen-ojek', 'dw_render_ojek_management');
    }

    // --- KELOMPOK: KEUANGAN, PROMOSI & SETTINGS (ADMIN ONLY) ---
    if (current_user_can('manage_options')) {
        add_submenu_page('dw-dashboard', 'Paket Transaksi', 'Paket & Kuota', 'manage_options', 'dw-paket-transaksi', 'dw_render_paket');
        add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket', 'manage_options', 'dw-verifikasi-paket', 'dw_render_verifikasi_paket');
        add_submenu_page('dw-dashboard', 'Payout Komisi', 'Payout Komisi', 'manage_options', 'dw-komisi', 'dw_render_komisi');
        add_submenu_page('dw-dashboard', 'Promosi', 'Promosi/Iklan', 'manage_options', 'dw-promosi', 'dw_render_promosi');
        add_submenu_page('dw-dashboard', 'Banner', 'Banner Promo', 'manage_options', 'dw-banner', 'dw_render_banner');
        add_submenu_page('dw-dashboard', 'Moderasi Ulasan', 'Ulasan/Review', 'moderate_comments', 'dw-reviews', 'dw_render_reviews');
        add_submenu_page('dw-dashboard', 'System Logs', 'Logs Aktivitas', 'manage_options', 'dw-logs', 'dw_render_logs');
        add_submenu_page('dw-dashboard', 'Pengaturan', 'Pengaturan', 'manage_options', 'dw-settings', 'dw_render_settings');
    }
}
add_action('admin_menu', 'dw_register_admin_menus');

/**
 * 3. CLEANUP ADMIN MENU (OJEK MODE)
 */
function dw_cleanup_admin_menu_ojek() {
    if (current_user_can('manage_options')) return;
    if (current_user_can('dw_ojek')) {
        // Hapus menu standar WordPress untuk tampilan bersih driver ojek
        $menus_to_remove = array('index.php','upload.php','tools.php','themes.php','plugins.php','users.php','options-general.php','profile.php','edit.php','edit-comments.php');
        foreach($menus_to_remove as $m) remove_menu_page($m);
    }
}
add_action('admin_menu', 'dw_cleanup_admin_menu_ojek', 999);