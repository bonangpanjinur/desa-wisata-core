<?php
/**
 * File Name:   includes/admin-pages/page-verifikasi-akses-desa.php
 * Description: Halaman Admin untuk Menyetujui Akses Fitur Verifikasi Pedagang bagi Desa.
 */

if (!defined('ABSPATH')) exit;

function dw_verifikasi_akses_desa_page_render() {
    global $wpdb;
    $table_desa  = $wpdb->prefix . 'dw_desa';
    $table_users = $wpdb->users;

    $message = '';
    $message_type = '';

    // --- LOGIC: ACTION HANDLER ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_akses'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_verify_akses_desa')) {
            echo '<div class="notice notice-error"><p>Keamanan tidak valid.</p></div>'; return;
        }

        $desa_id = intval($_POST['desa_id']);
        $action  = sanitize_text_field($_POST['action_akses']); // 'approve' or 'reject'

        if ($action === 'approve') {
            $wpdb->update(
                $table_desa, 
                ['status_akses_verifikasi' => 'active'], 
                ['id' => $desa_id]
            );
            $message = "Akses fitur verifikasi pedagang untuk desa ini telah DIBUKA.";
            $message_type = "success";

        } elseif ($action === 'reject') {
            $wpdb->update(
                $table_desa, 
                ['status_akses_verifikasi' => 'locked', 'bukti_bayar_akses' => null], // Reset ke locked
                ['id' => $desa_id]
            );
            $message = "Permintaan akses ditolak. Desa harus mengajukan ulang.";
            $message_type = "error";
        }
    }

    // --- VIEW: LIST DATA ---
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending'; // pending | active

    $where_status = ($tab === 'pending') ? "status_akses_verifikasi = 'pending'" : "status_akses_verifikasi = 'active'";

    $sql = "SELECT d.*, u.display_name as admin_name 
            FROM $table_desa d
            LEFT JOIN $table_users u ON d.id_user_desa = u.ID
            WHERE $where_status
            ORDER BY d.updated_at DESC";

    $results = $wpdb->get_results($sql);
    ?>

    <div class="wrap dw-wrapper">
        <h1 class="wp-heading-inline">Permintaan Akses Fitur Desa</h1>
        <hr class="wp-header-end">

        <?php if (!empty($message)): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="dw-tabs-nav">
            <a href="?page=dw-verifikasi-akses-desa&tab=pending" class="dw-tab-link <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                Menunggu Persetujuan
            </a>
            <a href="?page=dw-verifikasi-akses-desa&tab=active" class="dw-tab-link <?php echo $tab === 'active' ? 'active' : ''; ?>">
                Akses Aktif
            </a>
        </div>

        <div class="dw-card-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nama Desa</th>
                        <th>Admin Pengelola</th>
                        <th>Lokasi</th>
                        <th>Bukti Pembayaran</th>
                        <th>Status Saat Ini</th>
                        <?php if ($tab === 'pending'): ?>
                        <th width="150" style="text-align:right;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): foreach ($results as $row): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($row->nama_desa); ?></strong>
                        </td>
                        <td><?php echo esc_html($row->admin_name); ?></td>
                        <td>
                            <?php echo esc_html($row->kecamatan . ', ' . $row->kabupaten); ?>
                        </td>
                        <td>
                            <?php if ($row->bukti_bayar_akses): ?>
                                <a href="<?php echo esc_url($row->bukti_bayar_akses); ?>" target="_blank" class="button button-small">
                                    <span class="dashicons dashicons-visibility"></span> Lihat Bukti
                                </a>
                            <?php else: ?>
                                <span class="text-muted">- Tidak ada bukti -</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if($row->status_akses_verifikasi == 'pending') echo '<span class="dw-badge dw-warning">Menunggu</span>';
                            if($row->status_akses_verifikasi == 'active') echo '<span class="dw-badge dw-success">Aktif</span>';
                            ?>
                        </td>
                        <?php if ($tab === 'pending'): ?>
                        <td style="text-align:right;">
                            <form method="post" style="display:inline-flex; gap:5px;">
                                <?php wp_nonce_field('dw_verify_akses_desa'); ?>
                                <input type="hidden" name="desa_id" value="<?php echo $row->id; ?>">
                                
                                <button type="submit" name="action_akses" value="reject" class="button" onclick="return confirm('Tolak permintaan ini?');" title="Tolak">
                                    <span class="dashicons dashicons-no-alt" style="color:#d63638;"></span>
                                </button>
                                <button type="submit" name="action_akses" value="approve" class="button button-primary" onclick="return confirm('Buka akses verifikasi untuk desa ini?');" title="Setujui">
                                    Setujui Akses
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 20px;">Tidak ada permintaan baru.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
            .dw-tabs-nav { margin-bottom: 20px; border-bottom: 1px solid #c3c4c7; }
            .dw-tab-link { display: inline-block; padding: 10px 15px; text-decoration: none; color: #50575e; font-weight: 600; border: 1px solid transparent; border-bottom: none; margin-bottom: -1px; }
            .dw-tab-link.active { background: #fff; border-color: #c3c4c7; border-bottom-color: #fff; color: #1d2327; }
            .dw-tab-link:hover { background: #f0f0f1; }
            .dw-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #fff; }
            .dw-warning { background: #f0b849; color: #333; }
            .dw-success { background: #46b450; }
            .dw-card-table { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .wp-list-table { border: none; }
        </style>
    </div>
    <?php
}
?>