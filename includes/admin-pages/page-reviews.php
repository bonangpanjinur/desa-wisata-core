<?php
/**
 * File Name:   page-reviews.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-reviews.php
 *
 * Halaman Admin untuk Moderasi Ulasan (Reviews Management).
 *
 * FITUR LENGKAP:
 * - Statistik Ringkasan (Cards).
 * - Filter Tab (Semua, Pending, Disetujui, Ditolak).
 * - Tampilan Tabel Modern.
 * - Handler Aksi Row & Bulk.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * --------------------------------------------------------------------------
 * 1. HANDLER: AKSI INDIVIDUAL (Approve/Reject/Trash via Link)
 * --------------------------------------------------------------------------
 */
function dw_reviews_handle_row_actions() {
    // Cek parameter aksi
    if (!isset($_GET['action']) || !isset($_GET['review_id'])) return;

    $action = sanitize_key($_GET['action']);
    $review_id = absint($_GET['review_id']);
    $nonce = sanitize_text_field($_REQUEST['_wpnonce'] ?? '');

    // Validasi Nonce spesifik per aksi
    $valid_nonce = false;
    if ($action === 'approve' && wp_verify_nonce($nonce, 'dw-approve-review_' . $review_id)) $valid_nonce = true;
    if ($action === 'reject' && wp_verify_nonce($nonce, 'dw-reject-review_' . $review_id)) $valid_nonce = true;
    if ($action === 'trash' && wp_verify_nonce($nonce, 'dw-trash-review_' . $review_id)) $valid_nonce = true;

    if (!$valid_nonce) return; // Fail silently atau wp_die jika mau strict

    if (!current_user_can('moderate_comments')) wp_die('Akses ditolak.');

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_ulasan';
    $message = '';
    $msg_type = 'success';

    if ('approve' === $action) {
        $wpdb->update($table_name, ['status_moderasi' => 'disetujui'], ['id' => $review_id]);
        $message = 'Ulasan berhasil disetujui dan ditampilkan.';
    } elseif ('reject' === $action) {
        $wpdb->update($table_name, ['status_moderasi' => 'ditolak'], ['id' => $review_id]);
        $message = 'Ulasan telah ditolak.';
        $msg_type = 'warning';
    } elseif ('trash' === $action) {
        $wpdb->delete($table_name, ['id' => $review_id]);
        $message = 'Ulasan berhasil dihapus permanen.';
    }

    if ($message) {
        add_settings_error('dw_reviews_notices', 'action_done', $message, $msg_type);
        set_transient('settings_errors', get_settings_errors(), 30);
        
        // Hapus cache hitungan pending
        wp_cache_delete('dw_pending_reviews_count', 'desa_wisata_core');
    }

    // Redirect bersih untuk menghapus parameter query
    wp_redirect(remove_query_arg(['action', 'review_id', '_wpnonce'], wp_get_referer()));
    exit;
}
add_action('admin_init', 'dw_reviews_handle_row_actions');


/**
 * --------------------------------------------------------------------------
 * 2. RENDER HALAMAN UTAMA
 * --------------------------------------------------------------------------
 */
function dw_reviews_moderation_page_render() {
    global $wpdb;
    
    // Pastikan class List Table dimuat
    if (!class_exists('DW_Reviews_List_Table')) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-reviews-list-table.php';
    }

    // --- A. HITUNG STATISTIK ---
    $table_name = $wpdb->prefix . 'dw_ulasan';
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_moderasi = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status_moderasi = 'disetujui' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status_moderasi = 'ditolak' THEN 1 ELSE 0 END) as rejected
        FROM $table_name
    ");

    $count_total = (int) ($stats->total ?? 0);
    $count_pending = (int) ($stats->pending ?? 0);
    $count_approved = (int) ($stats->approved ?? 0);
    $count_rejected = (int) ($stats->rejected ?? 0);

    // --- B. SETUP LIST TABLE ---
    $reviewsListTable = new DW_Reviews_List_Table();
    $reviewsListTable->prepare_items();

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Moderasi Ulasan & Rating</h1>
        <hr class="wp-header-end">

        <?php
        // Tampilkan Notifikasi
        $errors = get_transient('settings_errors');
        if ($errors) {
            settings_errors('dw_reviews_notices');
            delete_transient('settings_errors');
        }
        ?>

        <!-- 1. STATS CARDS -->
        <style>
            .dw-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; margin-top: 20px; }
            .dw-stat-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #c3c4c7; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; }
            .dw-stat-content h3 { margin: 0 0 5px; font-size: 13px; color: #64748b; text-transform: uppercase; font-weight: 600; }
            .dw-stat-number { font-size: 24px; font-weight: 700; color: #1e293b; line-height: 1; }
            .dw-stat-icon { font-size: 24px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; }
            
            .card-total { border-bottom: 4px solid #3b82f6; } .icon-total { background: #eff6ff; color: #3b82f6; }
            .card-pending { border-bottom: 4px solid #f59e0b; } .icon-pending { background: #fffbeb; color: #f59e0b; }
            .card-approved { border-bottom: 4px solid #10b981; } .icon-approved { background: #f0fdf4; color: #10b981; }
            .card-rejected { border-bottom: 4px solid #ef4444; } .icon-rejected { background: #fef2f2; color: #ef4444; }

            /* Table Styles */
            .dw-table-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); padding: 0; overflow: hidden; }
            .tablenav.top { padding: 10px 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; margin: 0; }
            .wp-list-table { border: none; box-shadow: none; }
            .wp-list-table thead th { border-bottom: 1px solid #e2e8f0; font-weight: 600; }
            .wp-list-table td { vertical-align: middle; }
        </style>

        <div class="dw-stats-grid">
            <div class="dw-stat-card card-total">
                <div class="dw-stat-content">
                    <h3>Total Ulasan</h3>
                    <div class="dw-stat-number"><?php echo number_format($count_total); ?></div>
                </div>
                <div class="dw-stat-icon icon-total"><span class="dashicons dashicons-format-chat"></span></div>
            </div>
            <div class="dw-stat-card card-pending">
                <div class="dw-stat-content">
                    <h3>Menunggu Moderasi</h3>
                    <div class="dw-stat-number"><?php echo number_format($count_pending); ?></div>
                </div>
                <div class="dw-stat-icon icon-pending"><span class="dashicons dashicons-clock"></span></div>
            </div>
            <div class="dw-stat-card card-approved">
                <div class="dw-stat-content">
                    <h3>Disetujui</h3>
                    <div class="dw-stat-number"><?php echo number_format($count_approved); ?></div>
                </div>
                <div class="dw-stat-icon icon-approved"><span class="dashicons dashicons-yes-alt"></span></div>
            </div>
            <div class="dw-stat-card card-rejected">
                <div class="dw-stat-content">
                    <h3>Ditolak</h3>
                    <div class="dw-stat-number"><?php echo number_format($count_rejected); ?></div>
                </div>
                <div class="dw-stat-icon icon-rejected"><span class="dashicons dashicons-dismiss"></span></div>
            </div>
        </div>

        <!-- 2. FILTER TABS (Manual Links) -->
        <h2 class="nav-tab-wrapper" style="margin-bottom: 0; border-bottom: none;">
            <?php 
            $current_status = $_GET['status'] ?? 'all'; 
            $base_url = admin_url('admin.php?page=dw-reviews');
            ?>
            <a href="<?php echo $base_url; ?>" class="nav-tab <?php echo $current_status == 'all' ? 'nav-tab-active' : ''; ?>">
                Semua <span class="count">(<?php echo $count_total; ?>)</span>
            </a>
            <a href="<?php echo add_query_arg('status', 'pending', $base_url); ?>" class="nav-tab <?php echo $current_status == 'pending' ? 'nav-tab-active' : ''; ?>">
                Pending <span class="count" style="color: #d97706;">(<?php echo $count_pending; ?>)</span>
            </a>
            <a href="<?php echo add_query_arg('status', 'disetujui', $base_url); ?>" class="nav-tab <?php echo $current_status == 'disetujui' ? 'nav-tab-active' : ''; ?>">
                Disetujui <span class="count" style="color: #059669;">(<?php echo $count_approved; ?>)</span>
            </a>
            <a href="<?php echo add_query_arg('status', 'ditolak', $base_url); ?>" class="nav-tab <?php echo $current_status == 'ditolak' ? 'nav-tab-active' : ''; ?>">
                Ditolak <span class="count" style="color: #dc2626;">(<?php echo $count_rejected; ?>)</span>
            </a>
        </h2>

        <!-- 3. TABLE WRAPPER -->
        <div class="dw-table-card">
            <form method="post">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php 
                // Jika ada filter status, kirim ke list table (perlu modif di prepare_items jika belum support)
                // Di sini kita asumsikan DW_Reviews_List_Table sudah membaca $_GET['status']
                // Jika belum, kita inject hidden input atau list table membacanya sendiri.
                $reviewsListTable->display(); 
                ?>
            </form>
        </div>

        <p class="description" style="margin-top: 15px;">
            <span class="dashicons dashicons-info"></span> Ulasan yang disetujui akan tampil di halaman publik (Detail Produk / Wisata). Ulasan yang ditolak akan disembunyikan.
        </p>
    </div>
    <?php
}
?>