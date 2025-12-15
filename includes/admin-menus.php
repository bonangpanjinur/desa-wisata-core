<?php
/**
 * File Name:   admin-menus.php
 * File Folder: includes/
 * File Path:   includes/admin-menus.php
 * * Description: 
 * File ini mengatur struktur menu sidebar di dashboard WordPress.
 * Menangani logika tampilan menu berdasarkan Role (Admin vs Pedagang vs Admin Desa).
 * Juga mengatur notifikasi badge (bubble merah) pada menu.
 * * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. HELPER NOTIFIKASI BADGE (Bubble Merah di Menu)
 */

// Hitung pedagang yang menunggu verifikasi kelayakan (Untuk Admin Desa)
function dw_get_desa_pending_pedagang_count($desa_id) {
    global $wpdb;
    if (empty($desa_id)) return 0;

    $count = wp_cache_get('dw_desa_pending_pedagang_' . $desa_id, 'desa_wisata_core');
    if (false === $count) {
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM {$wpdb->prefix}dw_pedagang WHERE id_desa = %d AND status_pendaftaran = 'menunggu_desa'",
            $desa_id
        ));
        wp_cache_set('dw_desa_pending_pedagang_' . $desa_id, $count, 'desa_wisata_core', MINUTE_IN_SECONDS * 5);
    }
    return $count;
}

// Hitung pesanan baru (Untuk Pedagang)
function dw_get_pending_orders_count() {
    global $wpdb;
    $current_user_id = get_current_user_id();
    $pedagang_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $current_user_id));

    if (empty($pedagang_id)) return 0;

    $cache_key = 'dw_pedagang_pending_orders_' . $pedagang_id;
    $count = wp_cache_get($cache_key, 'desa_wisata_core');
    if (false === $count) {
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM {$wpdb->prefix}dw_transaksi_sub WHERE id_pedagang = %d AND status_pesanan = 'menunggu_konfirmasi'",
            $pedagang_id
        ));
        wp_cache_set($cache_key, $count, 'desa_wisata_core', MINUTE_IN_SECONDS);
    }
    return $count;
}

// Hitung pembelian paket pending (Untuk Super Admin)
function dw_get_pending_paket_count() {
    global $wpdb;
    $cache_key = 'dw_pending_paket_count';
    $count = wp_cache_get($cache_key, 'desa_wisata_core');
    if (false === $count) {
        $count = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_pembelian_paket WHERE status = 'pending'");
        wp_cache_set($cache_key, $count, 'desa_wisata_core', MINUTE_IN_SECONDS);
    }
    return $count;
}


/**
 * 2. MENDAFTARKAN MENU
 */
function dw_register_admin_menus() {
    
    // --- Persiapan Data Badge Notifikasi ---
    $admin_desa_id = 0;
    $admin_desa_desa_id = 0;
    
    // Badge Admin Desa (Verifikasi Pedagang)
    $desa_pedagang_badge = '';
    if (current_user_can('admin_desa')) {
        global $wpdb;
        $admin_desa_id = get_current_user_id();
        $admin_desa_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $admin_desa_id));
        
        if ($admin_desa_desa_id) {
            $count = dw_get_desa_pending_pedagang_count($admin_desa_desa_id);
            if ($count > 0) $desa_pedagang_badge = sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="pedagang-count">%s</span></span>', $count, number_format_i18n($count));
        }
    }

    // Badge Pedagang (Pesanan Baru)
    $orders_badge = '';
    if (current_user_can('dw_manage_pesanan')) {
        $count = dw_get_pending_orders_count();
        if ($count > 0) $orders_badge = sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="orders-count">%s</span></span>', $count, number_format_i18n($count));
    }

    // Badge Super Admin (Paket & Ulasan)
    $review_badge = '';
    $paket_badge = '';
    if (current_user_can('manage_options')) {
        // Ulasan
        if (function_exists('dw_get_pending_reviews_count')) {
            $r_count = dw_get_pending_reviews_count();
            if ($r_count > 0) $review_badge = sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="review-count">%s</span></span>', $r_count, number_format_i18n($r_count));
        }
        // Paket
        $p_count = dw_get_pending_paket_count();
        if ($p_count > 0) $paket_badge = sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="paket-count">%s</span></span>', $p_count, number_format_i18n($p_count));
    }


    // --- MENU UTAMA ---
    add_menu_page(
         'Desa Wisata',          // Page Title
         'Desa Wisata',          // Menu Title
         'read',                 // Capability
         'dw-dashboard',         // Slug
         'dw_dashboard_page_render', // Callback function
         'dashicons-location-alt', // Icon
         20                      // Position
     );

    // --- SUBMENU ---
    
    // 1. Dashboard
    add_submenu_page('dw-dashboard', 'Dashboard', 'Dashboard', 'read', 'dw-dashboard', 'dw_dashboard_page_render');

    // 2. Desa (Master Data)
    add_submenu_page('dw-dashboard', 'Desa', 'Desa', 'dw_manage_desa', 'dw-desa', 'dw_desa_page_render');

    // 3. Wisata (CPT)
    add_submenu_page('dw-dashboard', 'Wisata', 'Wisata', 'edit_posts', 'edit.php?post_type=dw_wisata');
    // Kategori Wisata
    add_submenu_page('dw-dashboard', 'Kategori Wisata', '→ Kategori Wisata', 'manage_categories', 'edit-tags.php?taxonomy=kategori_wisata&post_type=dw_wisata');
    
    // 4. Produk (CPT)
    if ( (current_user_can('edit_dw_produks') || current_user_can('manage_options')) && !current_user_can('admin_desa') ) {
        add_submenu_page('dw-dashboard', 'Produk', 'Produk', 'edit_posts', 'edit.php?post_type=dw_produk');
        add_submenu_page('dw-dashboard', 'Kategori Produk', '→ Kategori Produk', 'manage_categories', 'edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk');
    }

    // 5. Keuangan (Payout Komisi)
    if (current_user_can('dw_manage_settings')) {
         add_submenu_page('dw-dashboard', 'Payout Komisi', 'Payout Komisi', 'dw_manage_settings', 'dw-komisi', 'dw_komisi_page_render');
    }

    // 6. Paket & Kuota
    if (current_user_can('dw_manage_settings')) {
         add_submenu_page('dw-dashboard', 'Paket Transaksi', 'Paket Transaksi', 'dw_manage_settings', 'dw-paket-transaksi', 'dw_paket_transaksi_page_render');
         add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket' . $paket_badge, 'dw_manage_settings', 'dw-verifikasi-paket', 'dw_verifikasi_paket_page_render');
    }

    // 7. Toko / Pedagang
    add_submenu_page( 'dw-dashboard', 'Manajemen Toko', 'Toko', 'dw_manage_pedagang', 'dw-pedagang', 'dw_pedagang_page_render' );
    
    // 8. Pengguna
    if (current_user_can('list_users')) {
        add_submenu_page('dw-dashboard', 'Pengguna', 'Pengguna', 'list_users', 'users.php');
    }

    // --- MENU KHUSUS ADMIN DESA ---
    if (current_user_can('admin_desa') && $admin_desa_desa_id) {
         add_submenu_page('dw-dashboard', 'Verifikasi Pedagang', 'Verifikasi Pedagang' . $desa_pedagang_badge, 'dw_approve_pedagang', 'dw-desa-verifikasi', 'dw_admin_desa_verifikasi_page_render');
    }

    // --- MENU KHUSUS PEDAGANG ---
    if (current_user_can('pedagang') && !current_user_can('administrator')) {
        add_submenu_page('dw-dashboard', 'Pesanan Saya', 'Pesanan Saya' . $orders_badge, 'dw_manage_pesanan', 'dw-pesanan-pedagang', 'dw_pesanan_pedagang_page_render');
        add_submenu_page('dw-dashboard', 'Inkuiri Produk', 'Inkuiri Produk', 'read', 'dw-chat-inquiry', 'dw_chat_page_render');
    }

    // 9. Promosi
    if (current_user_can('dw_manage_promosi')) {
        add_submenu_page('dw-dashboard', 'Promosi', 'Promosi', 'dw_manage_promosi', 'dw-promosi', 'dw_admin_promosi_page_handler');
    }
    
    // 10. Banner
    if (current_user_can('dw_manage_banners')) {
        add_submenu_page('dw-dashboard', 'Banner', 'Banner', 'dw_manage_banners', 'dw-banner', 'dw_banner_page_render');
    }

    // 11. Ulasan
    if (current_user_can('moderate_comments')) {
        add_submenu_page( 'dw-dashboard', 'Moderasi Ulasan', 'Ulasan' . $review_badge, 'moderate_comments', 'dw-reviews', 'dw_reviews_moderation_page_render' );
    }
    
    // 12. Logs
    if (current_user_can('dw_view_logs')) {
        add_submenu_page('dw-dashboard', 'Logs', 'Logs', 'dw_view_logs', 'dw_logs_page_render', 'dw_logs_page_render');
    }
    
    // 13. Pengaturan
    if (current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Pengaturan', 'Pengaturan', 'dw_manage_settings', 'dw-settings', 'dw_admin_settings_page_handler');
    }
}
add_action('admin_menu', 'dw_register_admin_menus');

// --- (Sisa kode dependensi & widget sama, hanya header yang ditambahkan) ---
function dw_load_admin_dependencies() {
    // List Tables & Renderers include here
}
add_action('admin_menu', 'dw_load_admin_dependencies');
?>