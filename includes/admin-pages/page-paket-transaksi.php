<?php
/**
 * File Name:   page-paket-transaksi.php
 * Description: CRUD Paket Transaksi dengan Tampilan Modern.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler Simpan & Hapus
 */
function dw_paket_form_handler() {
    if (!isset($_POST['dw_submit_paket'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_paket_nonce')) wp_die('Security check failed.');
    if (!current_user_can('dw_manage_settings')) wp_die('Akses ditolak.');

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_paket_transaksi';
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $redirect_url = admin_url('admin.php?page=dw-paket-transaksi');

    if (empty($_POST['nama_paket']) || !isset($_POST['harga']) || !isset($_POST['jumlah_transaksi'])) {
        add_settings_error('dw_paket_notices', 'fields_empty', 'Data wajib diisi.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect($redirect_url . '&action=add'); exit;
    }

    $data = [
        'nama_paket'   => sanitize_text_field($_POST['nama_paket']),
        'deskripsi'    => sanitize_textarea_field($_POST['deskripsi']),
        'harga'        => floatval($_POST['harga']),
        'jumlah_transaksi' => absint($_POST['jumlah_transaksi']),
        'persentase_komisi_desa' => floatval($_POST['persentase_komisi_desa']),
        'status'       => sanitize_key($_POST['status']),
    ];

    if ($id > 0) {
        $wpdb->update($table_name, $data, ['id' => $id]);
        add_settings_error('dw_paket_notices', 'updated', 'Paket diperbarui.', 'success');
    } else {
        $wpdb->insert($table_name, $data);
        add_settings_error('dw_paket_notices', 'created', 'Paket dibuat.', 'success');
    }
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect($redirect_url); exit;
}
add_action('admin_init', 'dw_paket_form_handler');

function dw_paket_delete_handler() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'delete_paket' || !isset($_GET['id'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_paket_nonce')) wp_die('Security check failed.');
    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'dw_paket_transaksi', ['id' => absint($_GET['id'])]);
    add_settings_error('dw_paket_notices', 'deleted', 'Paket dihapus.', 'success');
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-paket-transaksi')); exit;
}
add_action('admin_init', 'dw_paket_delete_handler');

/**
 * Render Halaman
 */
function dw_paket_transaksi_page_render() {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'add' || ($action === 'edit_paket' && isset($_GET['id']))) {
        dw_paket_form_render(isset($_GET['id']) ? absint($_GET['id']) : 0);
        return;
    }

    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dw_paket_transaksi ORDER BY harga ASC");
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Paket Kuota Transaksi</h1>
        <a href="?page=dw-paket-transaksi&action=add" class="page-title-action">Tambah Paket Baru</a>
        <hr class="wp-header-end">
        
        <?php $e = get_transient('settings_errors'); if($e){ settings_errors('dw_paket_notices'); delete_transient('settings_errors'); } ?>

        <style>
            .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); overflow:hidden; margin-top:15px; }
            .dw-badge { padding:4px 8px; border-radius:12px; font-size:11px; font-weight:600; }
            .badge-active { background:#dcfce7; color:#166534; }
            .badge-nonaktif { background:#fee2e2; color:#991b1b; }
            .price-tag { font-weight:700; color:#1d2327; }
            .quota-tag { background:#e0f2f1; color:#00695c; padding:2px 6px; border-radius:4px; font-weight:600; font-size:12px; }
        </style>

        <div class="dw-card-table">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Nama Paket</th><th>Harga</th><th>Kuota</th><th>Komisi Desa</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php if(empty($rows)): ?><tr><td colspan="6" style="text-align:center; padding:20px;">Belum ada data.</td></tr><?php endif; ?>
                    <?php foreach($rows as $r): 
                        $edit = "?page=dw-paket-transaksi&action=edit_paket&id={$r->id}";
                        $del = wp_nonce_url("?page=dw-paket-transaksi&action=delete_paket&id={$r->id}", 'dw_delete_paket_nonce');
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($r->nama_paket); ?></strong><br><small style="color:#777;"><?php echo esc_html($r->deskripsi); ?></small></td>
                        <td><span class="price-tag">Rp <?php echo number_format($r->harga,0,',','.'); ?></span></td>
                        <td><span class="quota-tag">+<?php echo number_format($r->jumlah_transaksi); ?> Trx</span></td>
                        <td><?php echo $r->persentase_komisi_desa; ?>%</td>
                        <td><span class="dw-badge <?php echo $r->status=='aktif'?'badge-active':'badge-nonaktif'; ?>"><?php echo ucfirst($r->status); ?></span></td>
                        <td>
                            <a href="<?php echo $edit; ?>" class="button button-small">Edit</a>
                            <a href="<?php echo $del; ?>" class="button button-small" style="color:#b32d2e;" onclick="return confirm('Hapus paket?');">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function dw_paket_form_render($id) {
    global $wpdb;
    $item = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_paket_transaksi WHERE id=%d", $id)) : null;
    ?>
    <div class="wrap">
        <h1><?php echo $id ? 'Edit Paket' : 'Tambah Paket Baru'; ?></h1>
        <form method="post" action="<?php echo admin_url('admin.php?page=dw-paket-transaksi'); ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="dw_submit_paket" value="1">
            <?php wp_nonce_field('dw_save_paket_nonce'); ?>
            
            <div class="card" style="padding:20px; max-width:600px;">
                <table class="form-table">
                    <tr><th>Nama Paket</th><td><input name="nama_paket" type="text" value="<?php echo esc_attr($item->nama_paket??''); ?>" class="regular-text" required></td></tr>
                    <tr><th>Deskripsi</th><td><textarea name="deskripsi" class="large-text" rows="2"><?php echo esc_textarea($item->deskripsi??''); ?></textarea></td></tr>
                    <tr><th>Harga (Rp)</th><td><input name="harga" type="number" value="<?php echo esc_attr($item->harga??''); ?>" class="regular-text" required></td></tr>
                    <tr><th>Kuota Transaksi</th><td><input name="jumlah_transaksi" type="number" value="<?php echo esc_attr($item->jumlah_transaksi??'100'); ?>" class="regular-text" required></td></tr>
                    <tr><th>Komisi Desa (%)</th><td><input name="persentase_komisi_desa" type="number" step="0.1" value="<?php echo esc_attr($item->persentase_komisi_desa??'20'); ?>" class="regular-text"></td></tr>
                    <tr><th>Status</th><td><select name="status"><option value="aktif" <?php selected($item->status??'','aktif'); ?>>Aktif</option><option value="nonaktif" <?php selected($item->status??'','nonaktif'); ?>>Nonaktif</option></select></td></tr>
                </table>
                <div style="margin-top:20px;">
                    <?php submit_button('Simpan Paket', 'primary', 'dw_submit_paket', false); ?>
                    <a href="?page=dw-paket-transaksi" class="button">Batal</a>
                </div>
            </div>
        </form>
    </div>
    <?php
}
?>