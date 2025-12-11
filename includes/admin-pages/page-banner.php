<?php
/**
 * File Path: includes/admin-pages/page-banner.php
 *
 * PERBAIKAN:
 * - Mengimplementasikan fungsionalitas CRUD lengkap untuk Banner.
 * - Menambahkan form untuk menambah/mengedit banner dengan Media Uploader.
 * - Menambahkan handler untuk menyimpan, memperbarui, dan menghapus data.
 * - Menggunakan sistem notifikasi admin untuk feedback pengguna.
 *
 * PERBAIKAN (ANALISIS API):
 * - Menambahkan `delete_transient('dw_api_banners_cache')` setelah
 * membuat, mengedit, atau menghapus banner agar API publik
 * selalu menyajikan data terbaru.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler untuk form Tambah/Edit Banner
function dw_banner_form_handler() {
    if (!isset($_POST['dw_submit_banner'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_banner_nonce')) wp_die('Security check failed.');
    if (!current_user_can('dw_manage_banners')) wp_die('Anda tidak punya izin.');

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_banner';
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

    if (empty($_POST['judul']) || empty($_POST['gambar'])) {
        add_settings_error('dw_banner_notices', 'fields_empty', 'Judul dan Gambar wajib diisi.', 'error');
        set_transient('dw_banner_form_data', $_POST, 60);
        return;
    }

    $data = [
        'judul'     => sanitize_text_field($_POST['judul']),
        'gambar'    => esc_url_raw($_POST['gambar']),
        'link'      => isset($_POST['link']) ? esc_url_raw($_POST['link']) : '',
        'status'    => sanitize_text_field($_POST['status']),
        'prioritas' => absint($_POST['prioritas']),
    ];

    if ($id > 0) {
        $wpdb->update($table_name, $data, ['id' => $id]);
        add_settings_error('dw_banner_notices', 'banner_updated', 'Banner berhasil diperbarui.', 'success');
    } else {
        $wpdb->insert($table_name, $data);
        add_settings_error('dw_banner_notices', 'banner_created', 'Banner berhasil dibuat.', 'success');
    }

    delete_transient('dw_api_banners_cache'); // --- PERBAIKAN PERFORMA: Hapus cache ---

    delete_transient('dw_banner_form_data');
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-banner'));
    exit;
}
add_action('admin_init', 'dw_banner_form_handler');

// Handler untuk penghapusan
function dw_banner_delete_handler() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'delete' || !isset($_GET['id'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_banner_nonce')) wp_die('Security check failed.');
    if (!current_user_can('dw_manage_banners')) wp_die('Anda tidak memiliki izin.');

    global $wpdb;
    $id = absint($_GET['id']);
    $wpdb->delete($wpdb->prefix . 'dw_banner', ['id' => $id]);
    add_settings_error('dw_banner_notices', 'banner_deleted', 'Banner berhasil dihapus.', 'success');
    
    delete_transient('dw_api_banners_cache'); // --- PERBAIKAN PERFORMA: Hapus cache ---
    
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-banner'));
    exit;
}
add_action('admin_init', 'dw_banner_delete_handler');

// Render Halaman Utama (List atau Form)
function dw_banner_page_render() {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    if ('add' === $action || 'edit' === $action) {
        dw_banner_form_render($id);
        return;
    }
    
    $bannerListTable = new DW_Banner_List_Table();
    $bannerListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Manajemen Banner & Slider</h1>
            <a href="<?php echo admin_url('admin.php?page=dw-banner&action=add'); ?>" class="page-title-action">Tambah Banner</a>
        </div>
        <?php
        $errors = get_transient('settings_errors');
        if($errors) {
            settings_errors('dw_banner_notices');
            delete_transient('settings_errors');
        }
        ?>
        <p>Kelola gambar yang tampil pada slider di halaman depan website Anda. Urutkan berdasarkan prioritas (angka terkecil tampil lebih dulu).</p>
         <form method="post">
            <input type="hidden" name="page" value="dw-banner">
            <?php $bannerListTable->display(); ?>
        </form>
    </div>
    <?php
}

// Render Form Tambah/Edit
function dw_banner_form_render($id = 0) {
    global $wpdb;
    $item = null;
    $page_title = 'Tambah Banner Baru';

    $transient_data = get_transient('dw_banner_form_data');
    if ($transient_data) {
        $item = (object) $transient_data;
        delete_transient('dw_banner_form_data');
    } elseif ($id > 0) {
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_banner WHERE id = %d", $id));
        $page_title = 'Edit Banner';
    }
    ?>
    <div class="wrap dw-wrap">
        <h1><?php echo esc_html($page_title); ?></h1>
        <?php settings_errors('dw_banner_notices'); ?>
        
        <form method="post" class="dw-form-card">
            <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('dw_save_banner_nonce'); ?>
            <table class="form-table dw-form-table">
                <tr>
                    <th scope="row"><label for="judul">Judul Banner</label></th>
                    <td><input name="judul" type="text" id="judul" value="<?php echo esc_attr($item->judul ?? ''); ?>" class="regular-text" required>
                    <p class="description">Teks ini akan menjadi alt text pada gambar.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gambar">Gambar Banner</label></th>
                    <td>
                        <div class="dw-image-uploader-wrapper">
                            <img src="<?php echo esc_url($item->gambar ?? 'https://placehold.co/300x150/e2e8f0/64748b?text=Pilih+Gambar'); ?>" class="dw-image-preview" style="width:300px; height:150px; object-fit:cover; border-radius:4px;"/>
                            <input name="gambar" type="hidden" value="<?php echo esc_attr($item->gambar ?? ''); ?>" class="dw-image-url">
                            <button type="button" class="button dw-upload-button">Pilih/Ubah Gambar</button>
                            <button type="button" class="button button-link-delete dw-remove-image-button" style="<?php echo empty($item->gambar) ? 'display:none;' : ''; ?>">Hapus Gambar</button>
                            <p class="description">Disarankan ukuran gambar 1200x400 pixel.</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="link">URL Link (Opsional)</label></th>
                    <td><input name="link" type="url" id="link" value="<?php echo esc_attr($item->link ?? ''); ?>" class="large-text" placeholder="https://...">
                    <p class="description">Jika diisi, banner akan bisa diklik dan mengarah ke URL ini.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="status">Status</label></th>
                    <td><select name="status" id="status">
                        <option value="aktif" <?php selected($item->status ?? 'aktif', 'aktif'); ?>>Aktif</option>
                        <option value="nonaktif" <?php selected($item->status ?? '', 'nonaktif'); ?>>Nonaktif</option>
                    </select></td>
                </tr>
                <tr>
                    <th scope="row"><label for="prioritas">Prioritas</label></th>
                    <td><input name="prioritas" type="number" step="1" id="prioritas" value="<?php echo esc_attr($item->prioritas ?? '10'); ?>" class="small-text">
                    <p class="description">Angka lebih kecil akan ditampilkan lebih dulu.</p></td>
                </tr>
            </table>
            <div class="dw-form-footer">
                <?php submit_button('Simpan Banner', 'primary', 'dw_submit_banner', false); ?>
                 <a href="<?php echo admin_url('admin.php?page=dw-banner'); ?>" class="button button-secondary">Batal</a>
            </div>
        </form>
    </div>
    <?php
}

// Perlu require List Table Class jika file ini dimuat sebelum admin-menus.php
// (Tapi dalam kasus ini, admin-menus.php memuat ini, jadi aman)
// if ( ! class_exists( 'DW_Banner_List_Table' ) ) {
// 	require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-banner-list-table.php';
// }
?>