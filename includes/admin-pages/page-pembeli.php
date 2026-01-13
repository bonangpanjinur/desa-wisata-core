<?php
/**
 * File Name: includes/admin-pages/page-pembeli.php
 * Description: Manajemen Data Pembeli (User Role: Subscriber/Customer) dengan UI Modern.
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// Helper untuk mengambil data pembeli
function dw_get_pembeli_list($search = '', $paged = 1, $per_page = 10) {
    $args = [
        'role__in' => ['subscriber', 'customer'],
        'search'   => $search ? '*' . $search . '*' : '',
        'number'   => $per_page,
        'paged'    => $paged,
        'orderby'  => 'registered',
        'order'    => 'DESC',
    ];
    
    $user_query = new WP_User_Query($args);
    return [
        'users' => $user_query->get_results(),
        'total' => $user_query->get_total(),
    ];
}

// Handler Aksi (Contoh: Reset Password Manual)
if (isset($_GET['action']) && $_GET['action'] == 'reset_pass' && isset($_GET['uid']) && check_admin_referer('dw_reset_pass')) {
    $u = get_userdata(intval($_GET['uid']));
    if ($u) {
        $key = get_password_reset_key($u);
        if (!is_wp_error($key)) {
            $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($u->user_login), 'login');
            // Simpan link di transient untuk ditampilkan di notifikasi
            set_transient('dw_reset_link_' . get_current_user_id(), $reset_link, 60);
            wp_redirect(remove_query_arg(['action', 'uid', '_wpnonce']));
            exit;
        }
    }
}

// --- VIEW ---
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$data = dw_get_pembeli_list($search, $paged, 10);
$total_pages = ceil($data['total'] / 10);

// Cek notifikasi link reset
$reset_link_msg = get_transient('dw_reset_link_' . get_current_user_id());
if ($reset_link_msg) {
    delete_transient('dw_reset_link_' . get_current_user_id());
}

?>

<div class="wrap dw-admin-wrapper">
    
    <!-- HEADER -->
    <div class="dw-page-header">
        <div class="dw-header-title">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="dw-subtitle">Daftar pengguna yang terdaftar sebagai pembeli atau wisatawan.</p>
        </div>
        <div class="dw-header-actions">
            <a href="<?php echo admin_url('user-new.php'); ?>" class="dw-button dw-button-primary">
                <span class="dashicons dashicons-plus-alt2"></span> Tambah User Baru
            </a>
        </div>
    </div>

    <!-- BODY -->
    <div class="dw-content-body">
        <?php settings_errors(); ?>

        <?php if ($reset_link_msg) : ?>
            <div class="notice notice-success is-dismissible" style="margin-bottom: 20px;">
                <p><strong>Link Reset Password Berhasil Dibuat:</strong></p>
                <code style="display:block; padding:10px; background:#f0f0f1; margin:5px 0;"><?php echo esc_url($reset_link_msg); ?></code>
                <p>Salin link di atas dan kirimkan kepada pengguna.</p>
            </div>
        <?php endif; ?>

        <!-- STATS SIMPLE -->
        <div class="dw-stats-grid">
            <div class="dw-stat-card">
                <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-admin-users"></span></div>
                <h4 class="dw-stat-value"><?php echo number_format($data['total']); ?></h4>
                <span class="dw-stat-label">Total Pembeli</span>
            </div>
             <!-- Placeholder Stats Lainnya -->
             <div class="dw-stat-card">
                <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-cart"></span></div>
                <h4 class="dw-stat-value">-</h4>
                <span class="dw-stat-label">Total Transaksi</span>
            </div>
        </div>

        <div class="dw-card">
            <div class="dw-card-header">
                <h3 class="card-heading">Data Pelanggan</h3>
                <form method="get" style="display:flex; gap:10px;">
                    <input type="hidden" name="page" value="dw-pembeli">
                    <input type="text" name="s" class="dw-input" placeholder="Cari nama/email..." value="<?php echo esc_attr($search); ?>">
                    <button class="dw-button dw-button-secondary">Cari</button>
                </form>
            </div>
            
            <div class="dw-card-body" style="padding:0;">
                <div class="dw-table-wrapper" style="border:none; border-radius:0;">
                    <table class="dw-modern-table">
                        <thead>
                            <tr>
                                <th width="60">Avatar</th>
                                <th>Nama Lengkap</th>
                                <th>Email & Kontak</th>
                                <th>Tanggal Daftar</th>
                                <th>Status</th>
                                <th style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data['users'])) : ?>
                                <?php foreach ($data['users'] as $user) : 
                                    // Ambil data tambahan dari usermeta jika ada (misal no hp dari plugin/custom field)
                                    $phone = get_user_meta($user->ID, 'billing_phone', true) ?: '-';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="dw-thumb-wrapper" style="border-radius:50%; width:40px; height:40px; overflow:hidden;">
                                                <?php echo get_avatar($user->ID, 40); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($user->display_name); ?></strong>
                                            <div class="dw-text-muted">@<?php echo esc_html($user->user_login); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo esc_html($user->user_email); ?></div>
                                            <div class="dw-text-muted"><span class="dashicons dashicons-smartphone" style="font-size:14px; vertical-align:middle;"></span> <?php echo esc_html($phone); ?></div>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($user->user_registered)); ?>
                                        </td>
                                        <td>
                                            <span class="dw-badge status-success">Aktif</span>
                                        </td>
                                        <td style="text-align:right;">
                                            <div style="display:flex; gap:5px; justify-content:flex-end;">
                                                <a href="<?php echo wp_nonce_url(add_query_arg(['action' => 'reset_pass', 'uid' => $user->ID]), 'dw_reset_pass'); ?>" class="dw-button dw-button-secondary" style="padding: 4px 8px; font-size: 12px;" onclick="return confirm('Generate link reset password untuk user ini?');" title="Generate Reset Link">
                                                    <span class="dashicons dashicons-admin-network" style="margin:0;"></span>
                                                </a>
                                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="dw-button dw-button-secondary" style="padding: 4px 8px; font-size: 12px;" title="Edit User">
                                                    <span class="dashicons dashicons-edit" style="margin:0;"></span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:30px; color:#646970;">Tidak ada data pembeli ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1) : ?>
                    <div style="padding:15px; text-align:right; border-top:1px solid #e2e8f0;">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                        ]);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>