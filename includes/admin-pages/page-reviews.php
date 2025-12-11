<?php
/**
 * File Name:   page-reviews.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-reviews.php
 *
 * BARU: Halaman admin untuk moderasi ulasan (Approve, Reject, Trash).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler untuk aksi individual (Approve, Reject, Trash) dari link row action.
 */
function dw_reviews_handle_row_actions() {
    // Cek apakah ada aksi dan ID
    if (!isset($_GET['action']) || !isset($_GET['review_id'])) return;

    $action = sanitize_key($_GET['action']);
    $review_id = absint($_GET['review_id']);
    $nonce = sanitize_text_field($_REQUEST['_wpnonce'] ?? '');

    // Verifikasi nonce sesuai aksi
    $valid_nonce = false;
    if ($action === 'approve' && wp_verify_nonce($nonce, 'dw-approve-review_' . $review_id)) $valid_nonce = true;
    if ($action === 'reject' && wp_verify_nonce($nonce, 'dw-reject-review_' . $review_id)) $valid_nonce = true;
    if ($action === 'trash' && wp_verify_nonce($nonce, 'dw-trash-review_' . $review_id)) $valid_nonce = true;

    if (!$valid_nonce) wp_die('Aksi tidak valid atau nonce salah.');

    // Cek kapabilitas
    if (!current_user_can('moderate_comments')) wp_die('Anda tidak punya izin.');

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_ulasan';
    $message = '';

    if ('approve' === $action) {
        $wpdb->update($table_name, ['status_moderasi' => 'disetujui'], ['id' => $review_id]);
        $message = 'Ulasan berhasil disetujui.';
    } elseif ('reject' === $action) {
        $wpdb->update($table_name, ['status_moderasi' => 'ditolak'], ['id' => $review_id]);
        $message = 'Ulasan berhasil ditolak.';
    } elseif ('trash' === $action) {
        $wpdb->delete($table_name, ['id' => $review_id]);
        $message = 'Ulasan berhasil dihapus permanen.';
    }

    if ($message) {
        add_settings_error('dw_reviews_notices', 'action_success', $message, 'success');
        set_transient('settings_errors', get_settings_errors(), 30);
        // Trigger action to update pending count cache
        do_action('dw_review_status_updated');
    }

    // Redirect kembali ke halaman list setelah aksi
    wp_redirect(remove_query_arg(['action', 'review_id', '_wpnonce'], wp_get_referer()));
    exit;
}
add_action('admin_init', 'dw_reviews_handle_row_actions');


/**
 * Merender halaman moderasi ulasan.
 */
function dw_reviews_moderation_page_render() {
    // Pastikan class list table sudah dimuat (seharusnya sudah di admin-menus.php)
    if (!class_exists('DW_Reviews_List_Table')) {
         echo '<div class="notice notice-error"><p>Error: DW_Reviews_List_Table class not found.</p></div>';
         return;
    }

    $reviewsListTable = new DW_Reviews_List_Table();
    $reviewsListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Moderasi Ulasan</h1>
        </div>

        <?php
        // Tampilkan notifikasi dari settings errors API
        settings_errors('dw_reviews_notices');
        ?>

        <p>Tinjau dan kelola ulasan yang dikirim oleh pengguna sebelum ditampilkan di website.</p>

        <form method="post">
            <?php // Hidden fields untuk bulk actions (nonce sudah di handle WP_List_Table) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
            // $reviewsListTable->search_box('Cari Ulasan', 'review_search'); // Tambahkan jika perlu
            $reviewsListTable->display();
            ?>
        </form>
    </div>
    <?php
}
