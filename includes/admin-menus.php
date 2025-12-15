<?php
/**
 * File Path: includes/admin-menus.php
 *
 * --- PERUBAHAN (MODEL 3: PAKET KUOTA) ---
 * - MENGHAPUS menu "Laporan Komisi". Diganti dengan "Payout Komisi".
 * - MENAMBAHKAN menu "Paket Transaksi" (CRUD paket).
 * - MENAMBAHKAN menu "Verifikasi Paket" (Verifikasi pembelian paket).
 * - Menambahkan helper `dw_get_pending_paket_count` for badge notifikasi.
 * - Memperbarui `dw_load_admin_dependencies` untuk memuat file-file halaman baru.
 * - Memperbarui widget dashboard untuk mencerminkan alur kuota, bukan komisi.
 *
 * --- PERBAIKAN (SARAN PENINGKATAN UX) ---
 * - Menambahkan fungsi `dw_hide_menus_for_roles` untuk menyembunyikan
 * menu Super Admin (Settings, Payouts, Logs, dll.) dari role Pedagang & Admin Desa.
 *
 * --- PERBAIKAN (BUG FIX V3.2.2) ---
 * - Memperbaiki syntax error 'unexpected s' di hook 'dw_order_status_changed'.
 * - MENAMBAHKAN titik koma (;) yang hilang di akhir add_action 'dw_order_status_changed'
 * yang menyebabkan 500 Internal Server Error (PHP Parse Error).
 *
 * --- PERBAIKAN (REQUEST PENGGUNA) ---
 * - Memperbaiki kondisi `if` untuk menampilkan menu Produk agar Administrator
 * (yang memiliki 'manage_options') juga dapat melihatnya.
 * * --- PERBAIKAN (FATAL ERROR REDECLARE) ---
 * - Menghapus fungsi dw_get_pending_reviews_count() yang duplikat dengan helpers.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mendapatkan jumlah pedagang yang menunggu verifikasi kelayakan desa.
 * Digunakan untuk badge notifikasi Admin Desa.
 * @return int
 */
function dw_get_desa_pending_pedagang_count($desa_id) {
    global $wpdb;
    
    if (empty($desa_id)) {
        return 0;
    }

    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    
    // Gunakan cache untuk performa
    $cache_key = 'dw_desa_pending_pedagang_' . $desa_id;
    $count = wp_cache_get($cache_key, 'desa_wisata_core');

    if (false === $count) {
        // Query: HANYA Menunggu Verifikasi Kelayakan DARI Desa (`menunggu_desa`)
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(p.id)
             FROM $table_pedagang p
             WHERE p.id_desa = %d AND p.status_pendaftaran = %s",
            $desa_id, 'menunggu_desa'
        ));
        // Cache selama 5 menit
        wp_cache_set($cache_key, $count, 'desa_wisata_core', MINUTE_IN_SECONDS * 5);
    }
    return $count;
}

/**
 * BARU: Mendapatkan jumlah pesanan yang statusnya 'menunggu_konfirmasi'.
 * Digunakan untuk badge notifikasi Pedagang.
 * @return int
 */
function dw_get_pending_orders_count() {
    global $wpdb;
    $current_user_id = get_current_user_id();
    
    $pedagang_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $current_user_id));

    if (empty($pedagang_id)) {
        return 0;
    }

    $table_name = $wpdb->prefix . 'dw_transaksi';
    $cache_key = 'dw_pedagang_pending_orders_' . $pedagang_id;
    $count = wp_cache_get($cache_key, 'desa_wisata_core');

    if (false === $count) {
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_name WHERE id_pedagang = %d AND status_pesanan = %s",
            $pedagang_id,
            'menunggu_konfirmasi'
        ));
        // Cache selama 1 menit
        wp_cache_set($cache_key, $count, 'desa_wisata_core', MINUTE_IN_SECONDS);
    }
    return $count;
}

// dw_get_pending_reviews_count() DIHAPUS - Sudah ada di includes/helpers.php

/**
 * BARU (MODEL 3): Mendapatkan jumlah pembelian paket yang menunggu verifikasi.
 * @return int
 */
function dw_get_pending_paket_count() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pembelian_paket';
    $cache_key = 'dw_pending_paket_count';
    $count = wp_cache_get($cache_key, 'desa_wisata_core');

    if (false === $count) {
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_name WHERE status = %s",
            'pending'
        ));
        // Cache selama 1 menit
        wp_cache_set($cache_key, $count, 'desa_wisata_core', MINUTE_IN_SECONDS);
    }
    return $count;
}


/**
 * Mendaftarkan semua menu admin.
 */
function dw_register_admin_menus() {
    
    // Admin Desa harus terhubung ke Desa dulu untuk hitungan
    $admin_desa_id = 0;
    $admin_desa_desa_id = 0;
    if (current_user_can('admin_desa')) {
        global $wpdb;
        $admin_desa_id = get_current_user_id();
        $admin_desa_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $admin_desa_id));
    }


    // Hitung notifikasi Admin Desa (HANYA Kelayakan)
    $desa_pending_pedagang_count = 0;
    $desa_pedagang_badge = '';
    if ($admin_desa_desa_id) {
        $desa_pending_pedagang_count = dw_get_desa_pending_pedagang_count($admin_desa_desa_id); // Fungsi sudah disederhanakan
        $desa_pedagang_badge = $desa_pending_pedagang_count > 0 ? sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="pedagang-count">%s</span></span>', $desa_pending_pedagang_count, number_format_i18n($desa_pending_pedagang_count)) : '';
    }

    // Hitung notifikasi Pesanan (khusus Pedagang)
    $pending_orders_count = 0;
    $orders_badge = '';
    if (current_user_can('dw_manage_pesanan')) {
        $pending_orders_count = dw_get_pending_orders_count();
        $orders_badge = $pending_orders_count > 0 ? sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="orders-count">%s</span></span>', $pending_orders_count, number_format_i18n($pending_orders_count)) : '';
    }

    // Hitung notifikasi Ulasan
    $pending_reviews_count = 0;
    $review_badge = '';
    // Hanya Super Admin & Admin Kab yang melihat ini di menu
    if (current_user_can('moderate_comments') && !current_user_can('admin_desa') && !current_user_can('pedagang')) {
        $pending_reviews_count = dw_get_pending_reviews_count();
        $review_badge = $pending_reviews_count > 0 ? sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="review-count">%s</span></span>', $pending_reviews_count, number_format_i18n($pending_reviews_count)) : '';
    }


    // BARU (MODEL 3): Hitung notifikasi Verifikasi Paket
    $pending_paket_count = 0;
    $paket_badge = '';
     if (current_user_can('dw_manage_settings')) {
        $pending_paket_count = dw_get_pending_paket_count();
        $paket_badge = $pending_paket_count > 0 ? sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="paket-count">%s</span></span>', $pending_paket_count, number_format_i18n($pending_paket_count)) : '';
     }

    // Menu Utama
    add_menu_page(
         'Desa Wisata',
         'Desa Wisata',
         'read',
         'dw-dashboard',
         'dw_dashboard_page_render',
         'dashicons-location-alt',
         20
     );

    // Submenu
    add_submenu_page('dw-dashboard', 'Dashboard', 'Dashboard', 'read', 'dw-dashboard', 'dw_dashboard_page_render');

    // --- Manajemen Konten Inti ---
    add_submenu_page('dw-dashboard', 'Desa', 'Desa', 'dw_manage_desa', 'dw-desa', 'dw_desa_page_render');
    add_submenu_page('dw-dashboard', 'Wisata', 'Wisata', 'edit_posts', 'edit.php?post_type=dw_wisata');
    add_submenu_page('dw-dashboard', 'Kategori Wisata', '→ Kategori Wisata', 'manage_categories', 'edit-tags.php?taxonomy=kategori_wisata&post_type=dw_wisata');
    
    // PERBAIKAN (REQUEST PENGGUNA):
    // Kondisi diubah untuk menyertakan Administrator (manage_options)
    // atau Pedagang (edit_dw_produks), dan mengecualikan Admin Desa.
    if ( (current_user_can('edit_dw_produks') || current_user_can('manage_options')) && !current_user_can('admin_desa') ) {
        add_submenu_page('dw-dashboard', 'Produk', 'Produk', 'edit_posts', 'edit.php?post_type=dw_produk');
        add_submenu_page('dw-dashboard', 'Kategori Produk', '→ Kategori Produk', 'manage_categories', 'edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk');
    }


    // --- Laporan & Komisi (Admin) ---
    if (current_user_can('dw_manage_settings')) {
         // Nama diubah menjadi "Payout" agar lebih jelas
         add_submenu_page('dw-dashboard', 'Payout Komisi Desa', 'Payout Komisi', 'dw_manage_settings', 'dw-komisi', 'dw_komisi_page_render');
    }

    // --- BARU: Manajemen Paket (Admin) ---
    if (current_user_can('dw_manage_settings')) {
         add_submenu_page('dw-dashboard', 'Paket Transaksi', 'Paket Transaksi', 'dw_manage_settings', 'dw-paket-transaksi', 'dw_paket_transaksi_page_render');
         
         add_submenu_page('dw-dashboard', 'Verifikasi Paket', 'Verifikasi Paket' . $paket_badge, 'dw_manage_settings', 'dw-verifikasi-paket', 'dw_verifikasi_paket_page_render');
    }

    // --- Manajemen Pengguna & Toko ---
    add_submenu_page( 'dw-dashboard', 'Manajemen Toko', 'Toko', 'dw_manage_pedagang', 'dw-pedagang', 'dw_pedagang_page_render' );
     if (current_user_can('list_users')) {
        add_submenu_page('dw-dashboard', 'Pengguna', 'Pengguna', 'list_users', 'users.php');
     }

     // --- Menu Khusus Admin Desa (BARU: Verifikasi Pedagang) ---
    if (current_user_can('admin_desa') && $admin_desa_desa_id) {
         add_submenu_page('dw-dashboard', 'Verifikasi Pedagang', 'Verifikasi Pedagang' . $desa_pedagang_badge, 'dw_approve_pedagang', 'dw-desa-verifikasi', 'dw_admin_desa_verifikasi_page_render');
    }

     // --- Menu Khusus Pedagang ---
    if (current_user_can('pedagang') && !current_user_can('administrator')) {
        add_submenu_page('dw-dashboard', 'Pesanan Saya', 'Pesanan Saya' . $orders_badge, 'dw_manage_pesanan', 'dw-pesanan-pedagang', 'dw_pesanan_pedagang_page_render');
        add_submenu_page('dw-dashboard', 'Inkuiri Produk', 'Inkuiri Produk', 'read', 'dw-chat-inquiry', 'dw_chat_page_render');
        // TODO: Tambahkan halaman "Beli Kuota" untuk Pedagang di sini
    }

    // --- Fitur Tambahan (Admin & Admin Kab) ---
    if (current_user_can('dw_manage_promosi')) {
        add_submenu_page('dw-dashboard', 'Promosi', 'Promosi', 'dw_manage_promosi', 'dw-promosi', 'dw_admin_promosi_page_handler');
    }
     if (current_user_can('dw_manage_banners')) {
        add_submenu_page('dw-dashboard', 'Banner', 'Banner', 'dw_manage_banners', 'dw-banner', 'dw_banner_page_render');
    }

    // --- Moderasi & Pengaturan (Admin) ---
     if (current_user_can('moderate_comments')) {
        add_submenu_page( 'dw-dashboard', 'Moderasi Ulasan', 'Ulasan' . $review_badge, 'moderate_comments', 'dw-reviews', 'dw_reviews_moderation_page_render' );
    }
     if (current_user_can('dw_view_logs')) {
        add_submenu_page('dw-dashboard', 'Logs', 'Logs', 'dw_view_logs', 'dw_logs_page_render');
    }
     if (current_user_can('dw_manage_settings')) {
        add_submenu_page('dw-dashboard', 'Pengaturan', 'Pengaturan', 'dw_manage_settings', 'dw-settings', 'dw_admin_settings_page_handler');
    }
}

/**
 * Memuat semua file yang dibutuhkan untuk halaman admin.
 */
function dw_load_admin_dependencies() {
    // Memuat semua class List Table yang dibutuhkan oleh halaman admin
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-desa-list-table.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pedagang-list-table.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-promosi-list-table.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-banner-list-table.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pesanan-pedagang-list-table.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-logs-list-table.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-chat-list-table.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-reviews-list-table.php';
    
    // BARU: List table untuk paket
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-paket-list-table.php';


    // Memuat file-file yang berisi fungsi render untuk setiap halaman admin.
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-dashboard.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pedagang.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-pesanan-pedagang.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-promosi.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-banner.php';
    // require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-ongkir.php'; // <-- PERBAIKAN: File ini dihapus
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-logs.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-settings.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-chat.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-reviews.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-komisi.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-desa-verifikasi-pedagang.php';
    
    // BARU: Halaman untuk paket
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-paket-transaksi.php';
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-pages/page-verifikasi-paket.php';
}

// Mendaftarkan menu dan memuat dependensi pada hook yang tepat.
add_action('admin_menu', 'dw_register_admin_menus');
add_action('admin_menu', 'dw_load_admin_dependencies');

/**
 * Handler untuk notifikasi admin saat pedagang baru mendaftar (via API/Form).
 */
function dw_handle_new_pedagang_registration($user_id, $nama_toko) {
    // Kirim Email notifikasi ke Admin Desa terkait
    global $wpdb;
    $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id_desa FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id));
    if ($pedagang) {
        $desa_user_id = $wpdb->get_var($wpdb->prepare("SELECT id_user_desa FROM {$wpdb->prefix}dw_desa WHERE id = %d", $pedagang->id_desa));
        if ($desa_user_id) {
             $admin_desa_user = get_userdata($desa_user_id);
             if ($admin_desa_user) {
                 $subject = '[' . get_bloginfo('name') . '] Pedagang Baru Menunggu Verifikasi Kelayakan: ' . $nama_toko;
                 // Ubah pesan (tidak ada lagi pembayaran)
                 $message = "Halo Admin Desa,\n\n" .
                           "Pedagang baru, {$nama_toko} (User ID: {$user_id}), telah mendaftar di Desa Anda.\n\n" .
                           "Anda wajib memverifikasi kelayakan pedagang ini. Jika disetujui, akun mereka akan langsung aktif.\n\n" .
                           "Lihat di:\n" . admin_url('admin.php?page=dw-desa-verifikasi') . "\n\n" .
                           "Terima kasih,\nSistem " . get_bloginfo('name');
                 wp_mail($admin_desa_user->user_email, $subject, $message);
                 // Invalidate cache notifikasi Admin Desa
                 wp_cache_delete('dw_desa_pending_pedagang_' . $pedagang->id_desa, 'desa_wisata_core');
             }
        }
    }
}
add_action('dw_new_pedagang_registered', 'dw_handle_new_pedagang_registration', 10, 2);


// Helper untuk mendapatkan ID pedagang dari user ID (digunakan di notifikasi)
function dw_get_pedagang_id_by_user_id($user_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id));
}


// Hook untuk menghapus cache notifikasi saat di halaman relevan atau saat status diubah
add_action('admin_init', function() {
    $current_screen = get_current_screen();
    if ($current_screen) {
        $is_desa_admin = current_user_can('admin_desa');
        
        if ($current_screen->id === 'desa-wisata_page_dw-reviews') {
             wp_cache_delete('dw_pending_reviews_count', 'desa_wisata_core');
        } elseif ($current_screen->id === 'desa-wisata_page_dw-pesanan-pedagang') {
             global $wpdb;
             $current_user_id = get_current_user_id();
             $pedagang_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $current_user_id));
             if ($pedagang_id) {
                 wp_cache_delete('dw_pedagang_pending_orders_' . $pedagang_id, 'desa_wisata_core');
             }
        } elseif ($is_desa_admin && $current_screen->id === 'desa-wisata_page_dw-desa-verifikasi') {
             // Invalidate cache notifikasi Admin Desa saat dia membuka halaman verifikasi
             global $wpdb;
             $desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", get_current_user_id()));
             if ($desa_id) {
                 wp_cache_delete('dw_desa_pending_pedagang_' . $desa_id, 'desa_wisata_core');
             }
        }
        // BARU: Hapus cache saat Super Admin membuka halaman Verifikasi Paket
        elseif (current_user_can('dw_manage_settings') && $current_screen->id === 'desa-wisata_page_dw-verifikasi-paket') {
            wp_cache_delete('dw_pending_paket_count', 'desa_wisata_core');
        }
    }
});


// Hook untuk mengupdate cache hitungan saat status ulasan diubah (dari page-reviews.php)
add_action('dw_review_status_updated', function() {
    wp_cache_delete('dw_pending_reviews_count', 'desa_wisata_core');
});

// Hook untuk mengupdate cache hitungan pesanan saat status pesanan diubah (dari orders.php)
add_action('dw_order_status_changed', function($order_id, $new_status, $old_status) {
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare("SELECT id_pedagang FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));
    if ($order) {
        // --- PERBAIKAN BUG SINTAKS ---
        wp_cache_delete('dw_pedagang_pending_orders_' . $order->id_pedagang, 'desa_wisata_core');
        // --- AKHIR PERBAIKAN ---
    }
}, 10, 3); // <-- PERBAIKAN: TITIK KOMA (;) DITAMBAHKAN DI SINI

// BARU: Hook untuk mengupdate cache hitungan paket saat diverifikasi
add_action('dw_paket_pembelian_verified', function() {
    wp_cache_delete('dw_pending_paket_count', 'desa_wisata_core');
});


// Menambahkan widget dashboard kustom
function dw_register_dashboard_widgets() {
    // Widget untuk Pedagang
    if (current_user_can('pedagang')) {
        wp_add_dashboard_widget(
            'dw_pedagang_dashboard_widget',
            'Ringkasan Toko Anda',
            'dw_render_pedagang_dashboard_widget'
        );
    }
    // Widget untuk Admin Desa
    elseif (current_user_can('admin_desa')) {
         wp_add_dashboard_widget(
            'dw_admin_desa_dashboard_widget',
            'Ringkasan Desa Anda',
            'dw_render_admin_desa_dashboard_widget'
        );
    }
}
add_action('wp_dashboard_setup', 'dw_register_dashboard_widgets');

// Callback render widget dashboard pedagang (diperbarui untuk Model 3)
function dw_render_pedagang_dashboard_widget() {
    global $wpdb;
    $user_id = get_current_user_id();
    $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id, sisa_transaksi FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id));
    $pedagang_id = $pedagang->id ?? 0;
    $sisa_transaksi = $pedagang->sisa_transaksi ?? 0;

    $pending_confirmation_count = dw_get_pending_orders_count();
    $unread_chat_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT produk_id) FROM {$wpdb->prefix}dw_chat_message WHERE receiver_id = %d AND is_read = 0",
        $user_id
    ));

    echo '<p>Selamat datang di dashboard pedagang Anda!</p>';
    
    // Tampilkan Sisa Kuota
    echo '<div style="padding: 10px; background-color: #f0f8ff; border: 1px solid #cce5ff; border-radius: 4px; margin-bottom: 15px;">';
    if ($sisa_transaksi > 0) {
        echo 'Sisa Kuota Transaksi Anda: <strong style="font-size: 1.2em;">' . number_format_i18n($sisa_transaksi) . '</strong>';
    } else {
        echo '<strong style="color: red; font-size: 1.1em;">Kuota Transaksi Anda Habis!</strong><br>Akun Anda dibekukan. Segera beli paket untuk dapat bertransaksi kembali.';
    }
    echo '</div>';

    echo '<p>Pesanan menunggu konfirmasi pembayaran: <strong>' . number_format_i18n($pending_confirmation_count) . '</strong></p>';
    echo '<p>Percakapan belum dibaca: <strong>' . number_format_i18n($unread_chat_count) . '</strong></p>';
    echo '<p style="margin-top: 15px;">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=dw-pesanan-pedagang')) . '" class="button button-primary">Kelola Pesanan</a> ';
    echo '<a href="'. esc_url(admin_url('admin.php?page=dw-chat-inquiry')) .'" class="button">Lihat Pesan</a> ';
    echo '<a href="'. esc_url(admin_url('edit.php?post_type=dw_produk')) .'" class="button">Kelola Produk</a>';
    // TODO: Tambahkan link ke halaman "Beli Kuota"
    echo '</p>';
}

// Callback render widget dashboard admin desa (diperbarui untuk Model 3)
function dw_render_admin_desa_dashboard_widget() {
    global $wpdb;
    $user_id = get_current_user_id();
    $desa = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id));
    $desa_id = $desa->id ?? 0;
    $desa_name = $desa->nama_desa ?? 'Desa Tidak Ditemukan';
    
    // Hitung jumlah pedagang yang perlu diverifikasi
    $pending_pedagang_count = dw_get_desa_pending_pedagang_count($desa_id);
    
    // Hitung jumlah wisata di desa ini
    $wisata_count = 0;
    if ($desa_id) {
        $wisata_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'dw_wisata' AND p.post_status = 'publish' AND pm.meta_key = '_dw_id_desa' AND pm.meta_value = %d",
             $desa_id
        ));
    }

     echo '<p>Selamat datang di dashboard admin untuk <strong>' . esc_html($desa_name) . '</strong>!</p>';
     if ($pending_pedagang_count > 0) {
         echo '<p style="color:red;"><strong>' . $pending_pedagang_count . ' Pedagang Menunggu Verifikasi Kelayakan.</strong></p>';
     }
      
     echo '<p>Jumlah Wisata Terdaftar: <strong>' . number_format_i18n($wisata_count) . '</strong></p>';
     echo '<p style="margin-top: 15px;">';
     echo '<a href="' . esc_url(admin_url('admin.php?page=dw-desa-verifikasi')) . '" class="button button-primary">Verifikasi Pedagang (' . $pending_pedagang_count . ')</a> ';
     echo '<a href="' . esc_url(admin_url('edit.php?post_type=dw_wisata')) . '" class="button">Kelola Wisata</a> ';
     echo '</p>';
}

// --- PERBAIKAN (SARAN PENINGKATAN UX) ---
/**
 * 2. (UX) Sembunyikan menu admin yang tidak relevan untuk role Pedagang & Admin Desa
 */
function dw_hide_menus_for_roles() {
    // Jika Super Admin, jangan lakukan apa-apa
    if (current_user_can('manage_options')) {
        return;
    }

    // Daftar menu (slug) yang HANYA untuk Super Admin (atau Admin Kab)
    $super_admin_menus = [
        'dw-desa',             // Manajemen Desa (Hanya Admin Kab/Super)
        'dw-komisi',           // Payout Komisi
        'dw-paket-transaksi',  // Manajemen Paket
        'dw-verifikasi-paket', // Verifikasi Paket
        'dw-pedagang',         // Manajemen Toko (Hanya Admin Kab/Super)
        'users.php',           // Manajemen Pengguna
        'dw-promosi',          // Manajemen Promosi
        'dw-banner',           // Manajemen Banner
        'dw-reviews',          // Moderasi Ulasan (Admin Kab/Super)
        'dw_logs_page_render', // Slug dari add_submenu_page
        'dw-settings',         // Pengaturan
    ];
    
    // Daftar menu yang HANYA untuk Pedagang
    $pedagang_menus = [
        'dw-pesanan-pedagang',
        'dw-chat-inquiry',
        'edit.php?post_type=dw_produk',
        'profile.php',
    ];
    
    // Daftar menu yang HANYA untuk Admin Desa
    $admin_desa_menus = [
        'dw-desa-verifikasi',
        'edit.php?post_type=dw_wisata',
        'profile.php',
    ];

    // Logika untuk menyembunyikan
    if (current_user_can('admin_desa')) {
        // Admin Desa: Sembunyikan semua menu Super Admin
        foreach ($super_admin_menus as $slug) {
            remove_submenu_page('dw-dashboard', $slug);
        }
        // Sembunyikan juga menu Pedagang
        foreach ($pedagang_menus as $slug) {
             remove_submenu_page('dw-dashboard', $slug);
        }
        // Sembunyikan Kategori Produk (karena produk disembunyikan)
        remove_submenu_page('dw-dashboard', 'edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk');

    } elseif (current_user_can('pedagang')) {
        // Pedagang: Sembunyikan semua menu Super Admin
         foreach ($super_admin_menus as $slug) {
            remove_submenu_page('dw-dashboard', $slug);
        }
        // Sembunyikan juga menu Admin Desa
        foreach ($admin_desa_menus as $slug) {
             remove_submenu_page('dw-dashboard', $slug);
        }
        // Sembunyikan Kategori Wisata
        remove_submenu_page('dw-dashboard', 'edit-tags.php?taxonomy=kategori_wisata&post_type=dw_wisata');
    }
}
// Gunakan prioritas tinggi (999) agar berjalan setelah menu didaftarkan
add_action('admin_menu', 'dw_hide_menus_for_roles', 999);
?>