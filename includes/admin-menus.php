<?php
/**
 * File Name:   admin-menus.php
 * File Folder: includes/
 * Description: Mengatur menu admin dan meload halaman admin.
 * * [FIXED]
 * - Meload file admin page di level root (bukan di dalam fungsi render).
 * - Ini WAJIB agar hook 'admin_init' di dalam file-file tersebut terbaca oleh WordPress
 * sebelum header dikirim/halaman diproses.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. LOAD FILE HALAMAN (EAGER LOADING)
 * Memuat file logic admin segera jika user berada di area admin.
 * Ini memastikan handler form (admin_init) tereksekusi.
 */
if ( is_admin() ) {
    // Dashboard & Master Data
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-dashboard.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-wisata.php'; // CPT UI Tweaks mungkin butuh ini
    
    // Fitur Utama (Handler Form ada di sini)
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pedagang.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-paket-transaksi.php'; // <-- FIX: Paket Transaksi diload awal
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
 * Fungsi ini sekarang hanya memanggil fungsi render utama, 
 * karena file sudah di-include di atas.
 */

function dw_render_dashboard() {
    if (function_exists('dw_dashboard_page_render')) { dw_dashboard_page_render(); }
}

function dw_render_desa() {
    if (function_exists('dw_desa_page_render')) { dw_desa_page_render(); }
}

function dw_render_pedagang() {
    if (function_exists('dw_pedagang_page_render')) { dw_pedagang_page_render(); }
}

function dw_render_pesanan() {
    if (function_exists('dw_pesanan_pedagang_page_render')) { dw_pesanan_pedagang_page_render(); }
}

function dw_render_komisi() {
    if (function_exists('dw_komisi_page_render')) { dw_komisi_page_render(); }
}

function dw_render_paket() {
    if (function_exists('dw_paket_transaksi_page_render')) { dw_paket_transaksi_page_render(); }
}

function dw_render_verifikasi_paket() {
    if (function_exists('dw_verifikasi_paket_page_render')) { dw_verifikasi_paket_page_render(); }
}

function dw_render_promosi() {
    if (function_exists('dw_admin_promosi_page_handler')) { dw_admin_promosi_page_handler(); }
}

function dw_render_banner() {
    if (function_exists('dw_banner_page_render')) { dw_banner_page_render(); }
}

function dw_render_reviews() {
    if (function_exists('dw_reviews_moderation_page_render')) { dw_reviews_moderation_page_render(); }
}

function dw_render_chat() {
    if (function_exists('dw_chat_page_render')) { dw_chat_page_render(); }
}

function dw_render_logs() {
    if (function_exists('dw_logs_page_render')) { dw_logs_page_render(); }
}

function dw_render_settings() {
    if (function_exists('dw_admin_settings_page_handler')) { dw_admin_settings_page_handler(); }
}

function dw_render_verifikasi_desa() {
    if (function_exists('dw_admin_desa_verifikasi_page_render')) { dw_admin_desa_verifikasi_page_render(); }
}


/**
 * 3. MENDAFTARKAN MENU
 * Menggunakan nama fungsi wrapper di atas sebagai callback.
 */
function dw_register_admin_menus() {
    
    // MENU UTAMA: Dashboard
    add_menu_page(
        'Desa Wisata', 
        'Desa Wisata', 
        'read', 
        'dw-dashboard', 
        'dw_render_dashboard',
        'dashicons-location-alt', 
        20
    );

    // SUBMENU: Dashboard
    add_submenu_page('dw-dashboard', 'Dashboard', 'Dashboard', 'read', 'dw-dashboard', 'dw_render_dashboard');

    // SUBMENU: Desa
    add_submenu_page('dw-dashboard', 'Desa', 'Desa', 'dw_manage_desa', 'dw-desa', 'dw_render_desa');

    // SUBMENU: Wisata (CPT - Tidak butuh wrapper karena bawaan WP)
    add_submenu_page('dw-dashboard', 'Wisata', 'Wisata', 'edit_posts', 'edit.php?post_type=dw_wisata');
    add_submenu_page('dw-dashboard', 'Kategori Wisata', '→ Kategori Wisata', 'manage_categories', 'edit-tags.php?taxonomy=kategori_wisata&post_type=dw_wisata');

    // SUBMENU: Produk (CPT)
    if (!current_user_can('admin_desa')) {
        add_submenu_page('dw-dashboard', 'Produk', 'Produk', 'edit_posts', 'edit.php?post_type=dw_produk');
        add_submenu_page('dw-dashboard', 'Kategori Produk', '→ Kategori Produk', 'manage_categories', 'edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk');
    }

    // SUBMENU: Payout Komisi
    if (current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Payout Komisi', 'Payout Komisi', 'dw_manage_settings', 'dw-komisi', 'dw_render_komisi');
    }

    // SUBMENU: Paket & Verifikasi
    if (current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Paket Transaksi', 'Paket Transaksi', 'dw_manage_settings', 'dw-paket-transaksi', 'dw_render_paket');
        add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket', 'dw_manage_settings', 'dw-verifikasi-paket', 'dw_render_verifikasi_paket');
    }

    // SUBMENU: Toko / Pedagang
    add_submenu_page('dw-dashboard', 'Manajemen Toko', 'Toko', 'dw_manage_pedagang', 'dw-pedagang', 'dw_render_pedagang');

    // SUBMENU: Pengguna
    if (current_user_can('list_users')) {
        add_submenu_page('dw-dashboard', 'Pengguna', 'Pengguna', 'list_users', 'users.php');
    }

    // SUBMENU KHUSUS: Admin Desa
    if (current_user_can('admin_desa')) {
         add_submenu_page('dw-dashboard', 'Verifikasi Pedagang', 'Verifikasi Pedagang', 'dw_approve_pedagang', 'dw-desa-verifikasi', 'dw_render_verifikasi_desa');
    }

    // SUBMENU KHUSUS: Pedagang
    if (current_user_can('pedagang')) {
        add_submenu_page('dw-dashboard', 'Pesanan Saya', 'Pesanan Saya', 'dw_manage_pesanan', 'dw-pesanan-pedagang', 'dw_render_pesanan');
        add_submenu_page('dw-dashboard', 'Inkuiri Produk', 'Inkuiri Produk', 'read', 'dw-chat-inquiry', 'dw_render_chat');
    }

    // SUBMENU: Promosi
    if (current_user_can('dw_manage_promosi')) {
        add_submenu_page('dw-dashboard', 'Promosi', 'Promosi', 'dw_manage_promosi', 'dw-promosi', 'dw_render_promosi');
    }
    
    // SUBMENU: Banner
    if (current_user_can('dw_manage_banners')) {
        add_submenu_page('dw-dashboard', 'Banner', 'Banner', 'dw_manage_banners', 'dw-banner', 'dw_render_banner');
    }

    // SUBMENU: Ulasan
    if (current_user_can('moderate_comments')) {
        add_submenu_page('dw-dashboard', 'Moderasi Ulasan', 'Ulasan', 'moderate_comments', 'dw-reviews', 'dw_render_reviews');
    }
    
    // SUBMENU: Logs
    if (current_user_can('dw_view_logs')) {
        add_submenu_page('dw-dashboard', 'Logs', 'Logs', 'dw_view_logs', 'dw_logs', 'dw_render_logs');
    }
    
    // SUBMENU: Pengaturan
    if (current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Pengaturan', 'Pengaturan', 'dw_manage_settings', 'dw-settings', 'dw_render_settings');
    }
}
add_action('admin_menu', 'dw_register_admin_menus');

/**
 * 4. HIDE MENUS UNTUK ROLE TERTENTU
 * Agar dashboard terlihat bersih untuk pedagang/admin desa.
 */
function dw_cleanup_admin_menu() {
    if (current_user_can('manage_options')) return;

    // Menu yang disembunyikan dari non-super admin
    $restricted = [
        'dw-desa', 'dw-komisi', 'dw-paket-transaksi', 'dw-verifikasi-paket',
        'dw-pedagang', 'users.php', 'dw-promosi', 'dw-banner',
        'dw-reviews', 'dw_logs', 'dw-settings'
    ];
    
    foreach ($restricted as $slug) {
        // Cek permission manual atau biarkan WP menanganinya via add_submenu_page args
        // Fungsi ini hanya backup visual.
    }
}
add_action('admin_menu', 'dw_cleanup_admin_menu', 999);

?>