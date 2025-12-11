<?php
/**
 * File Path: includes/admin-pages/page-desa.php
 *
 * --- FASE 1: REFAKTOR PENDAFTARAN GRATIS ---
 * PERUBAHAN:
 * - MENGHAPUS field "Komisi Pendaftaran Nominal".
 * - MENGHAPUS kolom-kolom Rekonsiliasi (`total_komisi_sa_terkumpul`, `is_blocked`, `last_setoran_at`).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Form handler untuk menyimpan data desa
function dw_desa_form_handler() {
    if (!isset($_POST['dw_submit_desa'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_desa_nonce')) wp_die('Security check failed.');
    if (!current_user_can('dw_manage_desa')) wp_die('Anda tidak memiliki izin.');

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_desa';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Validasi spesifik
    if (empty($_POST['nama_desa'])) {
        add_settings_error('dw_desa_notices', 'nama_desa_empty', 'Nama Desa Wisata tidak boleh kosong.', 'error');
        set_transient('dw_desa_form_data', $_POST, 60);
        wp_redirect(admin_url('admin.php?page=dw-desa&action=' . ($id ? 'edit&id='.$id : 'add'))); // Redirect kembali ke form
        exit;
    }

    if (empty($_POST['provinsi_id']) || empty($_POST['kabupaten_id'])) {
         add_settings_error('dw_desa_notices', 'location_incomplete', 'ID Provinsi dan Kabupaten wajib diisi untuk fungsi Ongkir dan Geotagging.', 'error');
         set_transient('dw_desa_form_data', $_POST, 60);
         wp_redirect(admin_url('admin.php?page=dw-desa&action=' . ($id ? 'edit&id='.$id : 'add'))); // Redirect kembali ke form
         exit;
    }

    // Ambil data lama (komisi penjualan persentase)
    $old_data = $id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT persentase_komisi_penjualan FROM $table_name WHERE id = %d", $id), ARRAY_A) : [];

    $data = [
        'id_user_desa'        => isset($_POST['id_user_desa']) ? absint($_POST['id_user_desa']) : null,
        'nama_desa'           => sanitize_text_field($_POST['nama_desa']),
        'deskripsi'           => wp_kses_post($_POST['deskripsi']),
        'status'              => sanitize_text_field($_POST['status']),
        'foto'                => esc_url_raw($_POST['foto']),
        // 'komisi_pendaftaran_nominal' => ... DIHAPUS

        'persentase_komisi_penjualan' => $old_data['persentase_komisi_penjualan'] ?? 0.00, // Tetap ada untuk Fase 2

        // Detail Rekening Desa
        'no_rekening_desa'    => sanitize_text_field($_POST['no_rekening_desa'] ?? null),
        'nama_bank_desa'      => sanitize_text_field($_POST['nama_bank_desa'] ?? null),
        'atas_nama_rekening_desa' => sanitize_text_field($_POST['atas_nama_rekening_desa'] ?? null), 
        'qris_image_url_desa' => esc_url_raw($_POST['qris_image_url_desa'] ?? null),

        // Data Alamat API
        'id_provinsi'         => isset($_POST['provinsi_id']) ? sanitize_text_field($_POST['provinsi_id']) : null,
        'id_kabupaten'        => isset($_POST['kabupaten_id']) ? sanitize_text_field($_POST['kabupaten_id']) : null,
        'id_kecamatan'        => isset($_POST['kecamatan_id']) ? sanitize_text_field($_POST['kecamatan_id']) : null,
        'id_kelurahan'        => isset($_POST['kelurahan_id']) ? sanitize_text_field($_POST['kelurahan_id']) : null,
        'provinsi'            => isset($_POST['provinsi_nama']) ? sanitize_text_field($_POST['provinsi_nama']) : null,
        'kabupaten'           => isset($_POST['kabupaten_nama']) ? sanitize_text_field($_POST['kabupaten_nama']) : null,
        'kecamatan'           => isset($_POST['kecamatan_nama']) ? sanitize_text_field($_POST['kecamatan_nama']) : null,
        'kelurahan'           => isset($_POST['desa_nama']) ? sanitize_text_field($_POST['desa_nama']) : null,
    ];

    if ($id > 0) {
        $wpdb->update($table_name, $data, ['id' => $id]);
        do_action('dw_desa_updated', $id, $data);
        add_settings_error('dw_desa_notices', 'desa_updated', 'Data Desa berhasil diperbarui.', 'success');
    } else {
        $wpdb->insert($table_name, $data);
        add_settings_error('dw_desa_notices', 'desa_created', 'Data Desa berhasil dibuat.', 'success');
    }

    delete_transient('dw_desa_form_data');
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-desa'));
    exit;
}
add_action('admin_init', 'dw_desa_form_handler');

// Handler untuk penghapusan (tetap sama)
function dw_desa_delete_handler() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'delete' || !isset($_GET['id'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_desa_nonce')) wp_die('Security check failed.');
    if (!current_user_can('dw_manage_desa')) wp_die('Anda tidak memiliki izin.');

    global $wpdb;
    $id = intval($_GET['id']);

    do_action('dw_before_desa_deleted', $id);

    $wpdb->delete($wpdb->prefix . 'dw_desa', ['id' => $id]);
    add_settings_error('dw_desa_notices', 'desa_deleted', 'Data Desa berhasil dihapus.', 'success');
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-desa'));
    exit;
}
add_action('admin_init', 'dw_desa_delete_handler');

// Fungsi render halaman utama (list table)
function dw_desa_page_render() {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ('add' === $action || 'edit' === $action) {
        dw_desa_form_render($id);
        return;
    }

    if (!class_exists('DW_Desa_List_Table')) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-desa-list-table.php';
    }
    $desaListTable = new DW_Desa_List_Table();
    $desaListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Manajemen Desa</h1>
            <a href="<?php echo admin_url('admin.php?page=dw-desa&action=add'); ?>" class="page-title-action">Tambah Desa Baru</a>
        </div>

        <?php
        $errors = get_transient('settings_errors');
        if($errors) {
            settings_errors('dw_desa_notices');
            delete_transient('settings_errors');
        }
        ?>

        <form method="post">
            <?php $desaListTable->search_box('Cari Desa', 'search_id'); ?>
            <?php $desaListTable->display(); ?>
        </form>
    </div>
    <?php
}

function dw_desa_form_render($id = 0) {
    global $wpdb;
    $item = null;
    $page_title = 'Tambah Desa Baru';

    $transient_data = get_transient('dw_desa_form_data');
    if ($transient_data) {
        $item = $transient_data; 
        delete_transient('dw_desa_form_data');
    } elseif ($id > 0) {
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_desa WHERE id = %d", $id), ARRAY_A);
        $page_title = 'Edit Desa';
        if (!$item) {
             echo '<div class="notice notice-error"><p>Data Desa tidak ditemukan.</p></div>';
             return;
        }
    }

    $users = get_users(['role__in' => ['admin_desa', 'administrator']]);

    $provinsi_id    = $item['id_provinsi'] ?? '';
    $kabupaten_id   = $item['id_kabupaten'] ?? '';
    $kecamatan_id   = $item['id_kecamatan'] ?? '';
    $kelurahan_id   = $item['id_kelurahan'] ?? '';

    $provinsi_list  = dw_get_api_provinsi();
    $kabupaten_list = !empty($provinsi_id) ? dw_get_api_kabupaten($provinsi_id) : [];
    $kecamatan_list = !empty($kabupaten_id) ? dw_get_api_kecamatan($kabupaten_id) : [];
    $desa_list      = !empty($kecamatan_id) ? dw_get_api_desa($kecamatan_id) : [];

    // Data Rekening Desa
    $no_rekening_desa = $item['no_rekening_desa'] ?? '';
    $nama_bank_desa = $item['nama_bank_desa'] ?? '';
    $atas_nama_rekening_desa = $item['atas_nama_rekening_desa'] ?? ''; 
    $qris_image_url_desa = $item['qris_image_url_desa'] ?? '';

    // Ambil Komisi Penjualan Global untuk Info
    $dw_settings = get_option('dw_settings');
    $komisi_desa_global = $dw_settings['persentase_komisi_desa_global'] ?? 0.00;
    ?>
    <div class="wrap dw-wrap">
        <h1><?php echo esc_html($page_title); ?></h1>
        <?php settings_errors('dw_desa_notices'); ?>

        <form method="post" class="dw-form-card">
            <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('dw_save_desa_nonce'); ?>

            <h3>Informasi Dasar Desa</h3>
            <table class="form-table dw-form-table">
                <tbody>
                    <tr><th><label for="nama_desa">Nama Desa Wisata</label></th>
                        <td><input name="nama_desa" id="nama_desa" type="text" value="<?php echo esc_attr($item['nama_desa'] ?? ''); ?>" required></td></tr>
                    <tr><th><label for="id_user_desa">Akun Pengelola Desa</label></th>
                        <td><select name="id_user_desa" id="id_user_desa">
                                <option value="">-- Tidak Ada --</option>
                                <?php foreach ($users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($item['id_user_desa'] ?? '', $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
                                <?php endforeach; ?>
                            </select></td></tr>
                    <tr><th><label for="deskripsi">Deskripsi</label></th>
                        <td><textarea name="deskripsi" id="deskripsi" rows="5"><?php echo esc_textarea($item['deskripsi'] ?? ''); ?></textarea></td></tr>
                    <tr><th><label for="foto">Foto Utama Desa</label></th>
                        <td>
                            <div class="dw-image-uploader-wrapper">
                                <img src="<?php echo esc_url($item['foto'] ?? 'https://placehold.co/100x100/e2e8f0/64748b?text=Pilih+Gambar'); ?>" class="dw-image-preview" style="width:100px; height:100px; object-fit:cover; border-radius:4px;"/>
                                <input name="foto" type="hidden" value="<?php echo esc_attr($item['foto'] ?? ''); ?>" class="dw-image-url">
                                <button type="button" class="button dw-upload-button">Pilih/Ubah Gambar</button>
                                <button type="button" class="button button-link-delete dw-remove-image-button" style="<?php echo empty($item['foto']) ? 'display:none;' : ''; ?>">Hapus Gambar</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <hr>
            <h3>Alamat Desa</h3>
            <div class="dw-address-wrapper">
                <table class="form-table">
                    <tr><th><label for="dw_provinsi">Provinsi</label></th>
                        <td><select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select" style="width: 100%;">
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinsi_list as $prov) : ?>
                                <option value="<?php echo esc_attr($prov['code']); ?>" <?php selected($provinsi_id, $prov['code']); ?>><?php echo esc_html($prov['name']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                     <tr><th><label for="dw_kabupaten">Kabupaten/Kota</label></th>
                        <td><select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select" <?php disabled(empty($kabupaten_list)); ?> style="width: 100%;">
                            <option value="">Pilih Provinsi Dulu</option>
                            <?php foreach ($kabupaten_list as $kab) : ?>
                                <option value="<?php echo esc_attr($kab['code']); ?>" <?php selected($kabupaten_id, $kab['code']); ?>><?php echo esc_html($kab['name']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                    <tr><th><label for="dw_kecamatan">Kecamatan</label></th>
                        <td><select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select" <?php disabled(empty($kecamatan_list)); ?> style="width: 100%;">
                            <option value="">Pilih Kabupaten Dulu</option>
                             <?php foreach ($kecamatan_list as $kec) : ?>
                                <option value="<?php echo esc_attr($kec['code']); ?>" <?php selected($kecamatan_id, $kec['code']); ?>><?php echo esc_html($kec['name']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                     <tr><th><label for="dw_desa">Desa/Kelurahan</label></th>
                        <td><select name="kelurahan_id" id="dw_desa" class="dw-desa-select" <?php disabled(empty($desa_list)); ?> style="width: 100%;">
                            <option value="">Pilih Kecamatan Dulu</option>
                             <?php foreach ($desa_list as $desa) : ?>
                                <option value="<?php echo esc_attr($desa['code']); ?>" <?php selected($kelurahan_id, $desa['code']); ?>><?php echo esc_html($desa['name']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                </table>
                <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr($item['provinsi'] ?? ''); ?>">
                <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr($item['kabupaten'] ?? ''); ?>">
                <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr($item['kecamatan'] ?? ''); ?>">
                <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr($item['kelurahan'] ?? ''); ?>">
            </div>

            <hr>
            <h3><span class="dashicons dashicons-bank"></span> Rekening Bank & QRIS Desa</h3>
            <p class="description">Rekening ini akan digunakan Super Admin untuk mentransfer komisi penjualan (Fase 2).</p>
            <table class="form-table dw-form-table">
                 <tr><th><label for="no_rekening_desa">Nomor Rekening</label></th><td><input name="no_rekening_desa" id="no_rekening_desa" type="text" value="<?php echo esc_attr($no_rekening_desa); ?>" placeholder="Contoh: 1234567890"></td></tr>
                 <tr><th><label for="nama_bank_desa">Nama Bank</label></th><td><input name="nama_bank_desa" id="nama_bank_desa" type="text" value="<?php echo esc_attr($nama_bank_desa); ?>" placeholder="Contoh: Bank BRI Unit Desa"></td></tr>
                 <tr><th><label for="atas_nama_rekening_desa">Atas Nama Rekening</label></th><td><input name="atas_nama_rekening_desa" id="atas_nama_rekening_desa" type="text" value="<?php echo esc_attr($atas_nama_rekening_desa); ?>" placeholder="Contoh: BUMDes Maju Sejahtera"></td></tr>
                 <tr><th><label for="qris_image_url_desa">QRIS Desa</label></th><td><div class="dw-image-uploader-wrapper"><img src="<?php echo esc_url($qris_image_url_desa ?: 'https://placehold.co/150x150/e2e8f0/64748b?text=QRIS+Desa'); ?>" data-default-src="https://placehold.co/150x150/e2e8f0/64748b?text=QRIS+Desa" class="dw-image-preview" alt="QRIS Desa" style="width:150px; height:150px; object-fit:contain; border-radius:4px; border:1px solid #ddd;"/><input name="qris_image_url_desa" type="hidden" value="<?php echo esc_attr($qris_image_url_desa); ?>" class="dw-image-url"><button type="button" class="button dw-upload-button">Pilih/Ubah QRIS</button><button type="button" class="button button-link-delete dw-remove-image-button" style="<?php echo empty($qris_image_url_desa) ? 'display:none;' : ''; ?>">Hapus Gambar</button></div><p class="description">Unggah gambar QRIS Desa (opsional).</p></td></tr>
            </table>

            <hr>
            <h3>Pengaturan Komisi & Status</h3>
            <table class="form-table">
                 <tr><th><label for="status">Status Desa</label></th>
                        <td><select name="status" id="status">
                                <option value="aktif" <?php selected($item['status'] ?? 'aktif', 'aktif'); ?>>Aktif</option>
                                <option value="pending" <?php selected($item['status'] ?? '', 'pending'); ?>>Pending</option>
                            </select></td></tr>
                    
                    <!-- FIELD KOMISI PENDAFTARAN DIHAPUS -->
                    <!-- <tr><th><label for="komisi_pendaftaran_nominal">Komisi Pendaftaran Desa (Rp)</label></th> ... </tr> -->

                    <tr><th><label>Komisi Penjualan Desa</label></th>
                        <td><p class="description">Komisi Penjualan Desa diatur secara **Global** oleh Super Admin sebesar <strong><?php echo esc_html($komisi_desa_global); ?>%</strong> di halaman Pengaturan.</p></td></tr>
            </table>

             <!-- BAGIAN REKONSILIASI DIHAPUS -->
             <?php // if ($id > 0) : ... <h3> Status Rekonsiliasi ... </table> ... endif; ?>


             <div class="dw-form-footer">
                <?php submit_button('Simpan Desa', 'primary', 'dw_submit_desa', false); ?>
                <a href="<?php echo admin_url('admin.php?page=dw-desa'); ?>" class="button button-secondary">Batal</a>
            </div>
        </form>
    </div>
    <?php
}

?>
