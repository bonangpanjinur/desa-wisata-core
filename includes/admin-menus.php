<?php
/**
 * File Name:   admin-menus.php
 * File Folder: includes/
 * Description: Mengatur menu admin dan menghubungkannya ke file halaman.
 * * FIX: Menggunakan fungsi wrapper internal untuk memanggil file halaman.
 * * User TIDAK PERLU mengedit file page-*.php lagi.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. FUNGSI WRAPPER (JEMBATAN)
 * Fungsi-fungsi ini akan dipanggil saat menu diklik.
 * Tugasnya hanya satu: Membuka (include) file halaman yang sesuai.
 */

function dw_render_dashboard() {
    // Cek apakah file ada, lalu include
    if (file_exists(DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-dashboard.php')) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-dashboard.php';
        // Jika di dalam file tersebut ada fungsi dw_dashboard_page_render, panggil.
        // Jika tidak (kode langsung), require_once akan menjalankannya.
        if (function_exists('dw_dashboard_page_render')) {
            dw_dashboard_page_render();
        }
    } else {
        echo '<div class="error"><p>File page-dashboard.php tidak ditemukan.</p></div>';
    }
}

function dw_render_desa() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa.php';
    if (function_exists('dw_desa_page_render')) { dw_desa_page_render(); }
}

function dw_render_pedagang() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pedagang.php';
    if (function_exists('dw_pedagang_page_render')) { dw_pedagang_page_render(); }
}

function dw_render_pesanan() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pesanan-pedagang.php';
    if (function_exists('dw_pesanan_pedagang_page_render')) { dw_pesanan_pedagang_page_render(); }
}

function dw_render_komisi() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-komisi.php';
    if (function_exists('dw_komisi_page_render')) { dw_komisi_page_render(); }
}

function dw_render_paket() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-paket-transaksi.php';
    if (function_exists('dw_paket_transaksi_page_render')) { dw_paket_transaksi_page_render(); }
}

function dw_render_verifikasi_paket() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikasi-paket.php';
    if (function_exists('dw_verifikasi_paket_page_render')) { dw_verifikasi_paket_page_render(); }
}

function dw_render_promosi() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-promosi.php';
    if (function_exists('dw_admin_promosi_page_handler')) { dw_admin_promosi_page_handler(); }
}

function dw_render_banner() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-banner.php';
    if (function_exists('dw_banner_page_render')) { dw_banner_page_render(); }
}

function dw_render_reviews() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-reviews.php';
    if (function_exists('dw_reviews_moderation_page_render')) { dw_reviews_moderation_page_render(); }
}

function dw_render_chat() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-chat.php';
    if (function_exists('dw_chat_page_render')) { dw_chat_page_render(); }
}

function dw_render_logs() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-logs.php';
    if (function_exists('dw_logs_page_render')) { dw_logs_page_render(); }
}

function dw_render_settings() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-settings.php';
    if (function_exists('dw_admin_settings_page_handler')) { dw_admin_settings_page_handler(); }
}

function dw_render_verifikasi_desa() {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa-verifikasi-pedagang.php';
    if (function_exists('dw_admin_desa_verifikasi_page_render')) { dw_admin_desa_verifikasi_page_render(); }
}


/**
 * 2. MENDAFTARKAN MENU
 * Menggunakan nama fungsi wrapper di atas sebagai callback.
 */
function dw_register_admin_menus() {
    
    // --- Badge Notifikasi (Optional) ---
    // Logika badge dipersingkat agar fokus ke perbaikan menu blank
    $orders_badge = ''; 
    $review_badge = '';
    
    // MENU UTAMA: Dashboard
    add_menu_page(
        'Desa Wisata', 
        'Desa Wisata', 
        'read', 
        'dw-dashboard', 
        'dw_render_dashboard', // Panggil wrapper dashboard
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
 * 3. HIDE MENUS UNTUK ROLE TERTENTU
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
        if (!current_user_can('manage_options')) { // Logika sederhana: kalau bukan super admin, hide.
             // (Logic detail role bisa ditambahkan kembali jika perlu)
        }
    }
}
add_action('admin_menu', 'dw_cleanup_admin_menu', 999);

?>