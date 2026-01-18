<?php
/**
 * Halaman Admin: Manajemen Penarikan Dana (Withdrawals)
 * Path: includes/admin-pages/page-withdrawals.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle Action (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'dw_withdraw_action')) {
        $request_id = intval($_GET['id']);
        $action = $_GET['action']; // 'approve' or 'reject'

        if (class_exists('DW_Wallet')) {
            $result = DW_Wallet::process_withdrawal($request_id, $action);
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Permintaan berhasil diproses.</p></div>';
            }
        }
    }
}

// Pagination setup
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$args = array(
    'post_type'      => 'dw_withdrawal',
    'post_status'    => array('pending', 'publish', 'draft'), // Tampilkan semua untuk history
    'posts_per_page' => 20,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC'
);
$query = new WP_Query($args);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Permintaan Penarikan Dana</h1>
    <hr class="wp-header-end">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>User / Pemohon</th>
                <th>Nominal</th>
                <th>Bank / E-Wallet</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $post_id = get_the_ID();
                    $amount = get_post_meta($post_id, '_withdraw_amount', true);
                    $bank = get_post_meta($post_id, '_bank_details', true);
                    $status = get_post_meta($post_id, '_withdraw_status', true); // pending, approved, rejected
                    $author_id = get_the_author_meta('ID');
                    $author_name = get_the_author_meta('display_name');
                ?>
                <tr>
                    <td><?php echo get_the_date('d M Y H:i'); ?></td>
                    <td>
                        <strong><?php echo esc_html($author_name); ?></strong><br>
                        <small>ID: <?php echo $author_id; ?></small>
                    </td>
                    <td>Rp <?php echo number_format($amount, 0, ',', '.'); ?></td>
                    <td><?php echo esc_html($bank); ?></td>
                    <td>
                        <?php 
                        if ($status == 'pending') echo '<span class="dw-badge dw-warning">Pending</span>';
                        elseif ($status == 'approved') echo '<span class="dw-badge dw-success">Disetujui</span>';
                        elseif ($status == 'rejected') echo '<span class="dw-badge dw-danger">Ditolak</span>';
                        else echo $status;
                        ?>
                    </td>
                    <td>
                        <?php if ($status == 'pending') : ?>
                            <?php 
                            $nonce_url = wp_nonce_url(admin_url('admin.php?page=dw-withdrawals&id='.$post_id), 'dw_withdraw_action'); 
                            ?>
                            <a href="<?php echo $nonce_url . '&action=approve'; ?>" class="button button-primary" onclick="return confirm('Setujui pencairan dana ini?');">Approve</a>
                            <a href="<?php echo $nonce_url . '&action=reject'; ?>" class="button button-secondary" onclick="return confirm('Tolak dan kembalikan saldo?');">Reject</a>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6">Belum ada permintaan penarikan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php 
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $query->max_num_pages,
                'current' => $paged
            ));
            ?>
        </div>
    </div>
    <?php wp_reset_postdata(); ?>
</div>

<style>
.dw-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; color: #fff; }
.dw-warning { background-color: #f0ad4e; }
.dw-success { background-color: #5cb85c; }
.dw-danger { background-color: #d9534f; }
</style>