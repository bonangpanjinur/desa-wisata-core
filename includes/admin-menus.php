<?php
/**
 * File Name:   admin-menus.php
 * File Folder: includes/
 * Description: Mengatur menu admin dan meload halaman admin.
 * * [FIXED]
 * - Meload page-produk.php & page-pedagang.php di awal (is_admin).
 * - Mengubah menu 'Produk' agar mengarah ke Halaman Custom (dw-produk), bukan CPT bawaan WP.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. LOAD FILE HALAMAN (EAGER LOADING)
 * Memuat file logic admin segera jika user berada di area admin.
 * Ini memastikan handler form (admin_init) tereksekusi sebelum header dikirim.
 */
if ( is_admin() ) {
    // Dashboard & Master Data
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-dashboard.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-wisata.php'; 
    
    // Fitur Utama (Handler Form ada di sini - WAJIB LOAD DI SINI)
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pedagang.php';       // Fix Toko
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-produk.php';         // Fix Produk
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-paket-transaksi.php'; // Fix Paket
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pesanan-pedagang.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikasi-paket.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa-verifikasi-pedagang.php';
    
    // Fitur Pendukung
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-komisi.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-promosi.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-banner.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-reviews.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-chat.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-logs.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-settings.php';
    
    // File Ongkir & Template (Non-critical handlers, tapi diload demi konsistensi)
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-ongkir.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-templates.php';
}

/**
 * 2. FUNGSI RENDER (JEMBATAN UI)
 * Fungsi ini membungkus pemanggilan fungsi render dari file-file di atas.
 */

function dw_render_dashboard() { if (function_exists('dw_dashboard_page_render')) dw_dashboard_page_render(); }
function dw_render_desa() { if (function_exists('dw_desa_page_render')) dw_desa_page_render(); }
function dw_render_pedagang() { if (function_exists('dw_pedagang_page_render')) dw_pedagang_page_render(); }
function dw_render_produk() { if (function_exists('dw_produk_page_info_render')) dw_produk_page_info_render(); } // Callback Produk Baru
function dw_render_pesanan() { if (function_exists('dw_pesanan_pedagang_page_render')) dw_pesanan_pedagang_page_render(); }
function dw_render_komisi() { if (function_exists('dw_komisi_page_render')) dw_komisi_page_render(); }
function dw_render_paket() { if (function_exists('dw_paket_transaksi_page_render')) dw_paket_transaksi_page_render(); }
function dw_render_verifikasi_paket() { if (function_exists('dw_verifikasi_paket_page_render')) dw_verifikasi_paket_page_render(); }
function dw_render_promosi() { if (function_exists('dw_admin_promosi_page_handler')) dw_admin_promosi_page_handler(); }
function dw_render_banner() { if (function_exists('dw_banner_page_render')) dw_banner_page_render(); }
function dw_render_reviews() { if (function_exists('dw_reviews_moderation_page_render')) dw_reviews_moderation_page_render(); }
function dw_render_chat() { if (function_exists('dw_chat_page_render')) dw_chat_page_render(); }
function dw_render_logs() { if (function_exists('dw_logs_page_render')) dw_logs_page_render(); }
function dw_render_settings() { if (function_exists('dw_admin_settings_page_handler')) dw_admin_settings_page_handler(); }
function dw_render_verifikasi_desa() { if (function_exists('dw_admin_desa_verifikasi_page_render')) dw_admin_desa_verifikasi_page_render(); }


/**
 * 3. MENDAFTARKAN MENU
 * Mendaftarkan menu utama dan submenu ke sidebar admin WordPress.
 */
function dw_register_admin_menus() {
    
    // MENU UTAMA: Dashboard
    add_menu_page('Desa Wisata', 'Desa Wisata', 'read', 'dw-dashboard', 'dw_render_dashboard', 'dashicons-location-alt', 20);

    // SUBMENU: Dashboard
    add_submenu_page('dw-dashboard', 'Dashboard', 'Dashboard', 'read', 'dw-dashboard', 'dw_render_dashboard');
    
    // SUBMENU: Desa
    add_submenu_page('dw-dashboard', 'Desa', 'Desa', 'dw_manage_desa', 'dw-desa', 'dw_render_desa');

    // SUBMENU: Wisata (Tetap pakai CPT WP karena skemanya cocok)
    add_submenu_page('dw-dashboard', 'Wisata', 'Wisata', 'edit_posts', 'edit.php?post_type=dw_wisata');
    
    // SUBMENU: Produk (FIX: Ubah dari CPT WP ke Custom Page 'dw-produk')
    // Karena Anda menggunakan tabel khusus 'dw_produk', jangan pakai 'edit.php?post_type=...'
    if (!current_user_can('admin_desa')) {
        add_submenu_page('dw-dashboard', 'Produk', 'Produk', 'edit_posts', 'dw-produk', 'dw_render_produk');
    }

    // SUBMENU: Toko / Pedagang
    add_submenu_page('dw-dashboard', 'Manajemen Toko', 'Toko', 'dw_manage_pedagang', 'dw-pedagang', 'dw_render_pedagang');

    // SUBMENU: Transaksi & Keuangan (Khusus Pedagang)
    if (current_user_can('pedagang')) {
        add_submenu_page('dw-dashboard', 'Pesanan Saya', 'Pesanan Saya', 'dw_manage_pesanan', 'dw-pesanan-pedagang', 'dw_render_pesanan');
        add_submenu_page('dw-dashboard', 'Inkuiri Produk', 'Inkuiri Produk', 'read', 'dw-chat-inquiry', 'dw_render_chat');
    }

    // SUBMENU: Keuangan & Paket (Khusus Admin/Super Admin)
    if (current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Paket Transaksi', 'Paket Transaksi', 'dw_manage_settings', 'dw-paket-transaksi', 'dw_render_paket');
        add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket', 'dw_manage_settings', 'dw-verifikasi-paket', 'dw_render_verifikasi_paket');
        add_submenu_page('dw-dashboard', 'Payout Komisi', 'Payout Komisi', 'dw_manage_settings', 'dw-komisi', 'dw_render_komisi');
    }

    // SUBMENU: Admin Desa
    if (current_user_can('admin_desa')) {
         add_submenu_page('dw-dashboard', 'Verifikasi Pedagang', 'Verifikasi Pedagang', 'dw_approve_pedagang', 'dw-desa-verifikasi', 'dw_render_verifikasi_desa');
    }

    // SUBMENU: Tools Lain
    if (current_user_can('dw_manage_promosi')) {
        add_submenu_page('dw-dashboard', 'Promosi', 'Promosi', 'dw_manage_promosi', 'dw-promosi', 'dw_render_promosi');
    }
    if (current_user_can('dw_manage_banners')) {
        add_submenu_page('dw-dashboard', 'Banner', 'Banner', 'dw_manage_banners', 'dw-banner', 'dw_render_banner');
    }
    if (current_user_can('moderate_comments')) {
        add_submenu_page('dw-dashboard', 'Moderasi Ulasan', 'Ulasan', 'moderate_comments', 'dw-reviews', 'dw_render_reviews');
    }
    if (current_user_can('dw_view_logs')) {
        add_submenu_page('dw-dashboard', 'Logs', 'Logs', 'dw_view_logs', 'dw_logs', 'dw_render_logs');
    }
    if (current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Pengaturan', 'Pengaturan', 'dw_manage_settings', 'dw-settings', 'dw_render_settings');
    }
}
add_action('admin_menu', 'dw_register_admin_menus');

/**
 * 4. HIDE MENUS (Cleanup Visual)
 * Menyembunyikan menu bawaan WordPress yang tidak relevan agar dashboard lebih bersih.
 */
function dw_cleanup_admin_menu() {
    if (current_user_can('manage_options')) return;
    
    // Logika sembunyikan menu bawaan WP yang tidak perlu
    remove_menu_page('edit.php'); // Posts
    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'dw_cleanup_admin_menu', 999);
?>