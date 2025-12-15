<?php
/**
 * File Name:   page-paket-transaksi.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-paket-transaksi.php
 *
 * [FIXED] 
 * - Memperbaiki nama tombol submit agar sesuai dengan handler PHP.
 * - Memastikan data tersimpan ke tabel dw_paket_transaksi.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler untuk form Tambah/Edit Paket.
 */
function dw_paket_form_handler() {
    // FIX: Mengecek nama tombol yang benar ('dw_submit_paket')
    if (!isset($_POST['dw_submit_paket'])) return;
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_paket_nonce')) wp_die('Security check failed.');
    if (!current_user_can('dw_manage_settings')) wp_die('Anda tidak punya izin.');

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_paket_transaksi';
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $redirect_url = admin_url('admin.php?page=dw-paket-transaksi');

    if (empty($_POST['nama_paket']) || !isset($_POST['harga']) || !isset($_POST['jumlah_transaksi'])) {
        add_settings_error('dw_paket_notices', 'fields_empty', 'Nama, Harga, dan Jumlah Transaksi wajib diisi.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect($redirect_url . ($id == 0 ? '&action=add' : '&action=edit_paket&id='.$id));
        exit;
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
        $updated = $wpdb->update($table_name, $data, ['id' => $id]);
        if ($updated === false) {
             add_settings_error('dw_paket_notices', 'db_error', 'Gagal update database: ' . $wpdb->last_error, 'error');
        } else {
             add_settings_error('dw_paket_notices', 'paket_updated', 'Paket berhasil diperbarui.', 'success');
        }
    } else {
        $inserted = $wpdb->insert($table_name, $data);
        if ($inserted) {
            add_settings_error('dw_paket_notices', 'paket_created', 'Paket berhasil dibuat.', 'success');
        } else {
            add_settings_error('dw_paket_notices', 'db_error', 'Gagal insert database: ' . $wpdb->last_error, 'error');
        }
    }

    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'dw_paket_form_handler');

/**
 * Handler untuk Hapus Paket.
 */
function dw_paket_delete_handler() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'delete_paket' || !isset($_GET['id'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_paket_nonce')) wp_die('Security check failed.');
    if (!current_user_can('dw_manage_settings')) wp_die('Anda tidak memiliki izin.');

    global $wpdb;
    $id = absint($_GET['id']);
    $wpdb->delete($wpdb->prefix . 'dw_paket_transaksi', ['id' => $id]);
    add_settings_error('dw_paket_notices', 'paket_deleted', 'Paket berhasil dihapus.', 'success');
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-paket-transaksi'));
    exit;
}
add_action('admin_init', 'dw_paket_delete_handler');


/**
 * Render Halaman Utama (List atau Form).
 */
function dw_paket_transaksi_page_render() {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    if ('add' === $action || ('edit_paket' === $action && $id > 0)) {
        dw_paket_form_render($id);
        return;
    }
    
    if ( ! class_exists( 'DW_Paket_List_Table' ) ) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-paket-list-table.php';
    }
    
    $paketListTable = new DW_Paket_List_Table();
    $paketListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Paket Kuota Transaksi</h1>
            <a href="<?php echo admin_url('admin.php?page=dw-paket-transaksi&action=add'); ?>" class="page-title-action">Tambah Paket Baru</a>
        </div>
        <?php
        $errors = get_transient('settings_errors');
        if($errors) {
            settings_errors('dw_paket_notices');
            delete_transient('settings_errors');
        }
        ?>
        <p>Kelola paket-paket kuota transaksi yang dapat dibeli oleh pedagang.</p>
         <form method="get">
            <input type="hidden" name="page" value="dw-paket-transaksi">
            <?php $paketListTable->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * Render Form Tambah/Edit Paket.
 */
function dw_paket_form_render($id = 0) {
    global $wpdb;
    $item = null;
    $page_title = 'Tambah Paket Baru';

    if ($id > 0) {
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_paket_transaksi WHERE id = %d", $id));
        $page_title = 'Edit Paket';
    }
    ?>
    <div class="wrap dw-wrap">
        <h1><?php echo esc_html($page_title); ?></h1>
        <?php settings_errors('dw_paket_notices'); ?>
        
        <!-- FIX: Action form diarahkan ke halaman yang sama agar handler admin_init tertangkap -->
        <form method="post" class="dw-form-card" action="">
            <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
            <!-- FIX: Name input hidden ini harus sama dengan yang dicek di handler -->
            <input type="hidden" name="dw_submit_paket" value="1">
            <?php wp_nonce_field('dw_save_paket_nonce'); ?>
            
            <table class="form-table dw-form-table">
                <tr>
                    <th scope="row"><label for="nama_paket">Nama Paket <span style="color:red;">*</span></label></th>
                    <td><input name="nama_paket" type="text" id="nama_paket" value="<?php echo esc_attr($item->nama_paket ?? ''); ?>" class="regular-text" required>
                    <p class="description">Contoh: Paket Bronze</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="deskripsi">Deskripsi Singkat</label></th>
                    <td><textarea name="deskripsi" id="deskripsi" rows="3" class="large-text"><?php echo esc_textarea($item->deskripsi ?? ''); ?></textarea>
                    <p class="description">Contoh: Cocok untuk toko yang baru memulai.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="harga">Harga (Rp) <span style="color:red;">*</span></label></th>
                    <td><input name="harga" type="number" id="harga" value="<?php echo esc_attr($item->harga ?? '50000'); ?>" class="regular-text" required min="0" step="1000"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="jumlah_transaksi">Jumlah Kuota Transaksi <span style="color:red;">*</span></label></th>
                    <td><input name="jumlah_transaksi" type="number" id="jumlah_transaksi" value="<?php echo esc_attr($item->jumlah_transaksi ?? '100'); ?>" class="regular-text" required min="1" step="1">
                    <p class="description">Jumlah pesanan yang bisa diproses menggunakan paket ini.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="persentase_komisi_desa">Komisi Desa (%) <span style="color:red;">*</span></label></th>
                    <td><input name="persentase_komisi_desa" type="number" id="persentase_komisi_desa" value="<?php echo esc_attr($item->persentase_komisi_desa ?? '20.0'); ?>" class="regular-text" required min="0" max="100" step="0.1">
                    <p class="description">Persentase dari Harga Paket yang akan dialokasikan sebagai komisi (utang) ke Admin Desa.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="status">Status</label></th>
                    <td><select name="status" id="status">
                        <option value="aktif" <?php selected($item->status ?? 'aktif', 'aktif'); ?>>Aktif (Bisa dibeli)</option>
                        <option value="nonaktif" <?php selected($item->status ?? '', 'nonaktif'); ?>>Nonaktif (Disembunyikan)</option>
                    </select></td>
                </tr>
            </table>
            
            <div class="dw-form-footer">
                <!-- FIX: Name tombol disesuaikan, atau gunakan input hidden seperti di atas -->
                <?php submit_button('Simpan Paket', 'primary', 'dw_submit_paket', false); ?>
                 <a href="<?php echo admin_url('admin.php?page=dw-paket-transaksi'); ?>" class="button button-secondary">Batal</a>
            </div>
        </form>
    </div>
    <?php
}
?>