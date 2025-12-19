<?php
/**
 * File Name:   admin-menus.php
 * File Folder: includes/
 * Description: Mengatur menu admin dan meload halaman admin.
 * * UPDATE:
 * - Menambahkan Menu Panel Ojek (Khusus Role Ojek).
 * - Membersihkan dashboard untuk Ojek.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. LOAD FILE HALAMAN (EAGER LOADING)
 * Memuat file logic admin segera jika user berada di area admin.
 */
if ( is_admin() ) {
    // Dashboard & Master Data
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-dashboard.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-wisata.php'; 
    
    // Fitur Utama
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pedagang.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-produk.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-paket-transaksi.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pesanan-pedagang.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikasi-paket.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa-verifikasi-pedagang.php';
    
    // [NEW] HALAMAN OJEK
    // Pastikan file ini ada (saya buatkan di bawah)
    if (file_exists(DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-ojek.php')) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-ojek.php';
    }

    // Fitur Pendukung
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-komisi.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-banner.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-reviews.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-chat.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-logs.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-settings.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-ongkir.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-templates.php';
}

/**
 * 2. FUNGSI RENDER (JEMBATAN UI)
 */

function dw_render_dashboard() { if (function_exists('dw_dashboard_page_render')) dw_dashboard_page_render(); }
function dw_render_desa() { if (function_exists('dw_desa_page_render')) dw_desa_page_render(); }
function dw_render_pedagang() { if (function_exists('dw_pedagang_page_render')) dw_pedagang_page_render(); }

function dw_render_produk() { 
    if (function_exists('dw_produk_page_render')) {
        dw_produk_page_render();
    } elseif (function_exists('dw_produk_page_info_render')) {
        dw_produk_page_info_render();
    }
} 

function dw_render_wisata() { if (function_exists('dw_wisata_page_render')) dw_wisata_page_render(); }
function dw_render_pesanan() { if (function_exists('dw_pesanan_pedagang_page_render')) dw_pesanan_pedagang_page_render(); }
function dw_render_komisi() { if (function_exists('dw_komisi_page_render')) dw_komisi_page_render(); }
function dw_render_paket() { if (function_exists('dw_paket_transaksi_page_render')) dw_paket_transaksi_page_render(); }

function dw_render_verifikasi_paket() { 
    if (function_exists('dw_render_page_verifikasi_paket')) {
        dw_render_page_verifikasi_paket(); 
    } elseif (function_exists('dw_verifikasi_paket_page_render')) {
        dw_verifikasi_paket_page_render();
    } else {
        echo '<div class="notice notice-error"><p>Error: Fungsi render verifikasi paket tidak ditemukan.</p></div>';
    }
}

// [UPDATE] Callback Khusus Promosi
function dw_render_promosi() { 
    if ( defined('DW_CORE_PLUGIN_DIR') ) {
        $file_path = DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-promosi.php';
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
            if ( function_exists( 'dw_promosi_page_render' ) ) {
                dw_promosi_page_render();
            } else {
                echo '<div class="notice notice-error"><p>Fungsi <code>dw_promosi_page_render</code> tidak ditemukan.</p></div>';
            }
        }
    }
}

function dw_render_banner() { if (function_exists('dw_banner_page_render')) dw_banner_page_render(); }
function dw_render_reviews() { if (function_exists('dw_reviews_moderation_page_render')) dw_reviews_moderation_page_render(); }
function dw_render_chat() { if (function_exists('dw_chat_page_render')) dw_chat_page_render(); }
function dw_render_logs() { if (function_exists('dw_logs_page_render')) dw_logs_page_render(); }
function dw_render_settings() { if (function_exists('dw_admin_settings_page_handler')) dw_admin_settings_page_handler(); }
function dw_render_verifikasi_desa() { if (function_exists('dw_admin_desa_verifikasi_page_render')) dw_admin_desa_verifikasi_page_render(); }

// [NEW] Callback Render Panel Ojek
function dw_render_ojek_panel() {
    if (function_exists('dw_ojek_panel_page_render')) {
        dw_ojek_panel_page_render();
    } else {
        echo '<div class="wrap"><h1>Panel Driver Ojek</h1><p>Halaman antarmuka driver sedang dimuat...</p></div>';
    }
}

/**
 * 3. MENDAFTARKAN MENU
 */
function dw_register_admin_menus() {
    
    // MENU UTAMA: Dashboard
    add_menu_page('Desa Wisata', 'Desa Wisata', 'read', 'dw-dashboard', 'dw_render_dashboard', 'dashicons-location-alt', 20);

    // SUBMENU: Dashboard
    add_submenu_page('dw-dashboard', 'Dashboard', 'Dashboard', 'read', 'dw-dashboard', 'dw_render_dashboard');
    
    // SUBMENU: Desa
    add_submenu_page('dw-dashboard', 'Desa', 'Desa', 'dw_manage_desa', 'dw-desa', 'dw_render_desa');

    // SUBMENU: Wisata
    add_submenu_page('dw-dashboard', 'Wisata', 'Wisata', 'edit_posts', 'dw-wisata', 'dw_render_wisata');
    
    // SUBMENU: Kategori Wisata
    add_submenu_page('dw-dashboard', 'Kategori Wisata', 'Kategori Wisata', 'manage_categories', 'edit-tags.php?taxonomy=kategori_wisata&post_type=dw_wisata');

    // SUBMENU: Produk (Hanya tampil jika user punya akses)
    if (current_user_can('edit_posts')) {
        add_submenu_page('dw-dashboard', 'Produk', 'Produk', 'edit_posts', 'dw-produk', 'dw_render_produk');
        add_submenu_page('dw-dashboard', 'Kategori Produk', 'Kategori Produk', 'manage_categories', 'edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk');
    }

    // SUBMENU: Toko / Pedagang
    add_submenu_page('dw-dashboard', 'Manajemen Toko', 'Toko', 'dw_manage_pedagang', 'dw-pedagang', 'dw_render_pedagang');

    // SUBMENU: Transaksi (Pedagang)
    if (current_user_can('pedagang')) {
        add_submenu_page('dw-dashboard', 'Pesanan Saya', 'Pesanan Saya', 'dw_manage_pesanan', 'dw-pesanan-pedagang', 'dw_render_pesanan');
        add_submenu_page('dw-dashboard', 'Inkuiri Produk', 'Inkuiri Produk', 'read', 'dw-chat-inquiry', 'dw_render_chat');
    }

    // [NEW] SUBMENU: OJEK DESA (Khusus Role Ojek / Admin yang mau pantau)
    // Menggunakan capability 'dw_view_orders' yang dimiliki role 'dw_ojek'
    if (current_user_can('dw_ojek') || current_user_can('dw_view_orders')) {
        add_submenu_page(
            'dw-dashboard', 
            'Panel Ojek', 
            'Panel Ojek', 
            'dw_view_orders', 
            'dw-ojek-panel', 
            'dw_render_ojek_panel'
        );
    }

    // SUBMENU: Keuangan & Paket (Admin)
    if (current_user_can('manage_options') || current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Paket Transaksi', 'Paket Transaksi', 'manage_options', 'dw-paket-transaksi', 'dw_render_paket');
        add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket', 'manage_options', 'dw-verifikasi-paket', 'dw_render_verifikasi_paket');
        add_submenu_page('dw-dashboard', 'Payout Komisi', 'Payout Komisi', 'manage_options', 'dw-komisi', 'dw_render_komisi');
    }

    // SUBMENU: Tools Lain
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
 * 4. HIDE MENUS (Cleanup Visual)
 * Membersihkan menu bawaan WP yang tidak relevan.
 */
function dw_cleanup_admin_menu() {
    if (current_user_can('manage_options')) return;
    
    remove_menu_page('edit.php'); // Posts
    remove_menu_page('edit-comments.php'); // Comments
    
    // [NEW] Bersihkan menu untuk Ojek
    if (current_user_can('dw_ojek')) {
        remove_menu_page('upload.php'); // Media
        remove_menu_page('tools.php');  // Tools
        remove_menu_page('themes.php'); // Appearance (biasanya auto hide, tapi just in case)
        remove_menu_page('plugins.php');
        remove_menu_page('users.php');
        remove_menu_page('options-general.php');
    }
}
add_action('admin_menu', 'dw_cleanup_admin_menu', 999);