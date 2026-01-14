<?php
/**
 * File: includes/admin-pages/page-reviews.php
 * * Admin Page: Moderasi Ulasan
 * * Halaman Admin untuk Moderasi Ulasan (Reviews Management).
 */

defined( 'ABSPATH' ) || exit;

// Include UI components
if ( defined( 'DW_CORE_PLUGIN_DIR' ) ) {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-ui-components.php';
} else {
    require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/admin-ui-components.php';
}

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

    if (!$valid_nonce) return;

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
        set_transient('settings_errors_dw_reviews', get_settings_errors(), 30);
        
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
        if ( defined( 'DW_CORE_PLUGIN_DIR' ) ) {
            require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-reviews-list-table.php';
        } else {
            require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/list-tables/class-dw-reviews-list-table.php';
        }
    }

    // --- A. HITUNG STATISTIK ---
    $table_name = $wpdb->prefix . 'dw_ulasan';
    // Gunakan table prefix yang benar, fallback jika tabel belum ada (untuk safety display)
    $count_total = 0;
    $count_pending = 0;
    $count_approved = 0;
    $count_rejected = 0;

    // Check if table exists first to avoid fatal errors on fresh install
    if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name ) {
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status_moderasi = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status_moderasi = 'disetujui' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status_moderasi = 'ditolak' THEN 1 ELSE 0 END) as rejected
            FROM $table_name
        ");

        if ($stats) {
            $count_total = (int) $stats->total;
            $count_pending = (int) $stats->pending;
            $count_approved = (int) $stats->approved;
            $count_rejected = (int) $stats->rejected;
        }
    }

    // --- B. SETUP LIST TABLE ---
    $list_table = new DW_Reviews_List_Table();
    $list_table->prepare_items();

    ?>
    <div class="wrap dw-admin-wrapper">
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Moderasi ulasan dan rating produk dari pembeli.</p>
            </div>
        </div>

        <?php
        // Tampilkan Notifikasi
        $errors = get_transient('settings_errors_dw_reviews');
        if ($errors) {
            echo '<div class="dw-content-body" style="padding-bottom:0;">';
            settings_errors('dw_reviews_notices');
            echo '</div>';
            delete_transient('settings_errors_dw_reviews');
        }
        ?>

        <div class="dw-content-body">
            
            <!-- 1. STATS CARDS (GRID LAYOUT) -->
            <div class="dw-stats-grid">
                
                <!-- Total -->
                <div class="dw-stat-card card-total">
                    <div class="dw-stat-icon-wrapper bg-blue">
                        <span class="dashicons dashicons-format-chat"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($count_total); ?></h3>
                        <p class="dw-stat-label">Total Ulasan</p>
                    </div>
                </div>

                <!-- Pending -->
                <div class="dw-stat-card card-pending">
                    <div class="dw-stat-icon-wrapper bg-orange">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($count_pending); ?></h3>
                        <p class="dw-stat-label">Menunggu Moderasi</p>
                    </div>
                </div>

                <!-- Disetujui -->
                <div class="dw-stat-card card-approved">
                    <div class="dw-stat-icon-wrapper bg-green">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($count_approved); ?></h3>
                        <p class="dw-stat-label">Disetujui</p>
                    </div>
                </div>

                <!-- Ditolak -->
                <div class="dw-stat-card card-rejected">
                    <div class="dw-stat-icon-wrapper bg-purple">
                        <span class="dashicons dashicons-dismiss"></span>
                    </div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($count_rejected); ?></h3>
                        <p class="dw-stat-label">Ditolak</p>
                    </div>
                </div>

            </div>

            <!-- 2. FILTER TABS & TABLE -->
            <div class="dw-card">
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                    
                    <?php 
                    $list_table->views();
                    $list_table->search_box( 'Cari Review', 'search_id' );
                    $list_table->display(); 
                    ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}