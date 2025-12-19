<?php
/**
 * Halaman Manajemen Paket Transaksi
 * Mengelola paket kuota untuk Pedagang & Ojek.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-paket-list-table.php';

function dw_paket_transaksi_page_render() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    
    // Ambil Tab Aktif (Default: pedagang)
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pedagang';

    // Handle Form Save / Delete
    dw_handle_paket_actions();

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Paket Transaksi & Kuota</h1>';
    
    if ($action == 'list') {
        echo '<a href="?page=dw-paket-transaksi&action=add&tab=' . $active_tab . '" class="page-title-action">Tambah Paket Baru</a>';
        echo '<hr class="wp-header-end">';
        
        // TABS NAVIGASI
        ?>
        <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <a href="?page=dw-paket-transaksi&tab=pedagang" class="nav-tab <?php echo $active_tab == 'pedagang' ? 'nav-tab-active' : ''; ?>">Paket Pedagang</a>
            <a href="?page=dw-paket-transaksi&tab=ojek" class="nav-tab <?php echo $active_tab == 'ojek' ? 'nav-tab-active' : ''; ?>">Paket Ojek</a>
        </nav>
        <?php

        // Render List Table dengan filter role sesuai tab
        $table = new DW_Paket_List_Table($active_tab);
        $table->prepare_items();
        
        echo '<form method="post">';
        $table->display();
        echo '</form>';

    } else {
        // Form Tambah/Edit
        dw_render_paket_form($action, $id, $active_tab);
    }
    
    echo '</div>';
}

/**
 * Render Form Tambah/Edit
 */
function dw_render_paket_form($action, $id, $active_tab) {
    global $wpdb;
    $title = ($action == 'edit') ? 'Edit Paket' : 'Tambah Paket Baru';
    
    $data = [
        'nama_paket' => '',
        'deskripsi' => '',
        'harga' => '',
        'jumlah_transaksi' => '',
        'target_role' => $active_tab, // Auto-select based on previous tab
        'persentase_komisi_desa' => 0,
        'status' => 'aktif'
    ];

    if ($action == 'edit' && $id > 0) {
        $paket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_paket_transaksi WHERE id = %d", $id));
        if ($paket) {
            $data = (array) $paket;
        }
    }
    ?>
    <h1><?php echo $title; ?> (<?php echo ucfirst($data['target_role']); ?>)</h1>
    <a href="?page=dw-paket-transaksi&tab=<?php echo $data['target_role']; ?>" class="button">Kembali</a>
    
    <form method="post" action="" class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
        <?php wp_nonce_field('dw_save_paket', 'dw_paket_nonce'); ?>
        <input type="hidden" name="dw_action_type" value="<?php echo $action; ?>">
        <input type="hidden" name="paket_id" value="<?php echo $id; ?>">

        <table class="form-table">
            <tr>
                <th>Target Pengguna</th>
                <td>
                    <select name="target_role">
                        <option value="pedagang" <?php selected($data['target_role'], 'pedagang'); ?>>Pedagang</option>
                        <option value="ojek" <?php selected($data['target_role'], 'ojek'); ?>>Driver Ojek</option>
                    </select>
                    <p class="description">Paket ini ditujukan untuk siapa?</p>
                </td>
            </tr>
            <tr>
                <th>Nama Paket</th>
                <td><input type="text" name="nama_paket" value="<?php echo esc_attr($data['nama_paket']); ?>" class="large-text" required></td>
            </tr>
            <tr>
                <th>Harga (Rp)</th>
                <td><input type="number" name="harga" value="<?php echo esc_attr($data['harga']); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th>Jumlah Kuota</th>
                <td>
                    <input type="number" name="jumlah_transaksi" value="<?php echo esc_attr($data['jumlah_transaksi']); ?>" class="small-text" required>
                    <span class="description"><?php echo ($data['target_role'] == 'ojek') ? 'Trip / Perjalanan' : 'Transaksi Penjualan'; ?></span>
                </td>
            </tr>
            <tr>
                <th>Komisi Desa (%)</th>
                <td><input type="number" step="0.01" name="persentase_komisi_desa" value="<?php echo esc_attr($data['persentase_komisi_desa']); ?>" class="small-text"> %</td>
            </tr>
            <tr>
                <th>Deskripsi</th>
                <td><textarea name="deskripsi" class="large-text" rows="3"><?php echo esc_textarea($data['deskripsi']); ?></textarea></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <select name="status">
                        <option value="aktif" <?php selected($data['status'], 'aktif'); ?>>Aktif</option>
                        <option value="nonaktif" <?php selected($data['status'], 'nonaktif'); ?>>Nonaktif</option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">Simpan Paket</button>
        </p>
    </form>
    <?php
}

/**
 * Handle Save/Delete Actions
 */
function dw_handle_paket_actions() {
    global $wpdb;
    
    // DELETE
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        check_admin_referer('dw_delete_paket_' . $_GET['id']);
        $wpdb->delete($wpdb->prefix . 'dw_paket_transaksi', ['id' => absint($_GET['id'])]);
        echo '<div class="notice notice-success is-dismissible"><p>Paket berhasil dihapus.</p></div>';
    }

    // SAVE (ADD/EDIT)
    if (isset($_POST['dw_paket_nonce']) && wp_verify_nonce($_POST['dw_paket_nonce'], 'dw_save_paket')) {
        $data = [
            'nama_paket' => sanitize_text_field($_POST['nama_paket']),
            'deskripsi' => sanitize_textarea_field($_POST['deskripsi']),
            'harga' => absint($_POST['harga']),
            'jumlah_transaksi' => absint($_POST['jumlah_transaksi']),
            'target_role' => sanitize_text_field($_POST['target_role']),
            'persentase_komisi_desa' => floatval($_POST['persentase_komisi_desa']),
            'status' => sanitize_text_field($_POST['status'])
        ];

        if ($_POST['dw_action_type'] == 'add') {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'dw_paket_transaksi', $data);
        } else {
            $data['updated_at'] = current_time('mysql');
            $wpdb->update($wpdb->prefix . 'dw_paket_transaksi', $data, ['id' => absint($_POST['paket_id'])]);
        }

        echo '<div class="notice notice-success is-dismissible"><p>Paket berhasil disimpan.</p></div>';
    }
}
?>