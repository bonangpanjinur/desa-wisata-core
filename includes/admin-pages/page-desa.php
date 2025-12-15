<?php
/**
 * File Name:   page-desa.php
 * Description: CRUD Desa dengan Integrasi Alamat API Wilayah (Full Support).
 */

if (!defined('ABSPATH')) exit;

function dw_desa_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_desa';
    $message = '';
    $message_type = '';

    // --- LOGIC: SAVE / UPDATE / DELETE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_desa'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_action')) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>'; return;
        }

        $action = sanitize_text_field($_POST['action_desa']);

        // DELETE
        if ($action === 'delete' && !empty($_POST['desa_id'])) {
            $deleted = $wpdb->delete($table_name, ['id' => intval($_POST['desa_id'])]);
            if ($deleted !== false) {
                $message = 'Data desa berhasil dihapus.'; $message_type = 'success';
            } else {
                $message = 'Gagal menghapus desa: ' . $wpdb->last_error; $message_type = 'error';
            }
        
        // SAVE / UPDATE
        } elseif ($action === 'save') {
            $nama_desa = sanitize_text_field($_POST['nama_desa']);
            $slug = sanitize_title($nama_desa);
            
            $data = [
                'id_user_desa' => intval($_POST['id_user_desa']),
                'nama_desa'    => $nama_desa,
                'slug_desa'    => $slug,
                'deskripsi'    => wp_kses_post($_POST['deskripsi']),
                'foto'         => esc_url_raw($_POST['foto_desa_url']),
                'status'       => sanitize_text_field($_POST['status']),
                
                // DATA WILAYAH (API)
                'api_provinsi_id' => sanitize_text_field($_POST['provinsi_id']),
                'api_kabupaten_id'=> sanitize_text_field($_POST['kabupaten_id']),
                'api_kecamatan_id'=> sanitize_text_field($_POST['kecamatan_id']),
                'api_kelurahan_id'=> sanitize_text_field($_POST['kelurahan_id']),
                
                // NAMA WILAYAH (CACHE)
                'provinsi'     => sanitize_text_field($_POST['provinsi_nama']), 
                'kabupaten'    => sanitize_text_field($_POST['kabupaten_nama']),
                'kecamatan'    => sanitize_text_field($_POST['kecamatan_nama']),
                'kelurahan'    => sanitize_text_field($_POST['desa_nama']), // Perhatikan name='desa_nama' dari JS
                
                'alamat_lengkap' => sanitize_textarea_field($_POST['alamat_lengkap']),
                'updated_at'   => current_time('mysql')
            ];

            if (!empty($_POST['desa_id'])) {
                // Update
                $result = $wpdb->update($table_name, $data, ['id' => intval($_POST['desa_id'])]);
                if ($result !== false) {
                    $message = 'Data desa diperbarui.'; $message_type = 'success';
                } else {
                    $message = 'Gagal update: ' . $wpdb->last_error; $message_type = 'error';
                }
            } else {
                // Insert
                $data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table_name, $data);
                if ($result) {
                    $message = 'Desa baru ditambahkan.'; $message_type = 'success';
                } else {
                    $message = 'Gagal menyimpan: ' . $wpdb->last_error; $message_type = 'error';
                }
            }
        }
    }

    // --- PREPARE DATA FOR VIEW ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }
    
    // User List untuk Dropdown Admin Desa
    $users = get_users(['role__in' => ['administrator', 'admin_desa', 'editor']]);

    // --- LOGIKA API WILAYAH (Server Side Pre-fill) ---
    // Mengambil data awal agar dropdown terisi saat mode Edit
    $provinsi_id    = $edit_data->api_provinsi_id ?? '';
    $kabupaten_id   = $edit_data->api_kabupaten_id ?? '';
    $kecamatan_id   = $edit_data->api_kecamatan_id ?? '';
    $kelurahan_id   = $edit_data->api_kelurahan_id ?? '';

    // Pastikan fungsi helper tersedia
    $provinsi_list  = function_exists('dw_get_api_provinsi') ? dw_get_api_provinsi() : [];
    $kabupaten_list = (!empty($provinsi_id) && function_exists('dw_get_api_kabupaten')) ? dw_get_api_kabupaten($provinsi_id) : [];
    $kecamatan_list = (!empty($kabupaten_id) && function_exists('dw_get_api_kecamatan')) ? dw_get_api_kecamatan($kabupaten_id) : [];
    $desa_list_api  = (!empty($kecamatan_id) && function_exists('dw_get_api_desa')) ? dw_get_api_desa($kecamatan_id) : [];

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-desa&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <div class="card" style="padding: 20px; max-width: 100%; margin-top: 20px;">
                <form method="post">
                    <?php wp_nonce_field('dw_desa_action'); ?>
                    <input type="hidden" name="action_desa" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="desa_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <!-- BAGIAN 1: INFO DASAR -->
                        <tr valign="top">
                            <th scope="row"><h3>Informasi Umum</h3></th>
                            <td></td>
                        </tr>
                        <tr>
                            <th><label>Akun Admin Desa</label></th>
                            <td>
                                <select name="id_user_desa" class="regular-text" required>
                                    <option value="">-- Pilih User WordPress --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user_desa : '', $user->ID); ?>>
                                            <?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">User ini yang akan mengelola data desa.</p>
                            </td>
                        </tr>
                        <tr><th>Nama Desa</th><td><input name="nama_desa" type="text" value="<?php echo esc_attr($edit_data->nama_desa ?? ''); ?>" class="regular-text" required placeholder="Contoh: Desa Wisata Pujon Kidul"></td></tr>
                        <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>5, 'media_buttons'=>false]); ?></td></tr>
                        
                        <tr><th>Foto Desa</th><td>
                            <input type="text" name="foto_desa_url" id="foto_desa_url" value="<?php echo esc_attr($edit_data->foto ?? ''); ?>" class="regular-text">
                            <button type="button" class="button" id="btn_upload_foto">Upload Foto</button>
                        </td></tr>

                        <!-- BAGIAN 2: ALAMAT LENGKAP (API) -->
                        <tr valign="top">
                            <th scope="row"><h3 style="margin-top:20px;">Lokasi & Alamat</h3></th>
                            <td><hr></td>
                        </tr>

                        <!-- PROVINSI -->
                        <tr>
                            <th><label for="dw_provinsi">Provinsi</label></th>
                            <td>
                                <select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select regular-text" required>
                                    <option value="">-- Pilih Provinsi --</option>
                                    <?php foreach ($provinsi_list as $prov) : ?>
                                        <option value="<?php echo esc_attr($prov['code']); ?>" <?php selected($provinsi_id, $prov['code']); ?>>
                                            <?php echo esc_html($prov['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <!-- KABUPATEN -->
                        <tr>
                            <th><label for="dw_kabupaten">Kabupaten/Kota</label></th>
                            <td>
                                <select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select regular-text" <?php disabled(empty($kabupaten_list)); ?> required>
                                    <option value="">-- Pilih Kabupaten --</option>
                                    <?php foreach ($kabupaten_list as $kab) : ?>
                                        <option value="<?php echo esc_attr($kab['code']); ?>" <?php selected($kabupaten_id, $kab['code']); ?>>
                                            <?php echo esc_html($kab['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <!-- KECAMATAN -->
                        <tr>
                            <th><label for="dw_kecamatan">Kecamatan</label></th>
                            <td>
                                <select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select regular-text" <?php disabled(empty($kecamatan_list)); ?> required>
                                    <option value="">-- Pilih Kecamatan --</option>
                                    <?php foreach ($kecamatan_list as $kec) : ?>
                                        <option value="<?php echo esc_attr($kec['code']); ?>" <?php selected($kecamatan_id, $kec['code']); ?>>
                                            <?php echo esc_html($kec['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <!-- KELURAHAN / DESA -->
                        <tr>
                            <th><label for="dw_desa">Kelurahan/Desa</label></th>
                            <td>
                                <select name="kelurahan_id" id="dw_desa" class="dw-desa-select regular-text" <?php disabled(empty($desa_list_api)); ?> required>
                                    <option value="">-- Pilih Kelurahan --</option>
                                    <?php foreach ($desa_list_api as $desa) : ?>
                                        <option value="<?php echo esc_attr($desa['code']); ?>" <?php selected($kelurahan_id, $desa['code']); ?>>
                                            <?php echo esc_html($desa['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Pilih Kelurahan administratif tempat Desa Wisata berada.</p>
                            </td>
                        </tr>

                        <!-- DETAIL JALAN -->
                        <tr>
                            <th><label for="alamat_lengkap">Detail Alamat</label></th>
                            <td>
                                <textarea name="alamat_lengkap" id="alamat_lengkap" class="large-text" rows="3" placeholder="Nama Jalan, Dusun, RT/RW..."><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                            </td>
                        </tr>

                        <!-- STATUS -->
                        <tr>
                            <th>Status</th>
                            <td>
                                <select name="status">
                                    <option value="aktif" <?php selected($edit_data ? $edit_data->status : '', 'aktif'); ?>>Aktif</option>
                                    <option value="pending" <?php selected($edit_data ? $edit_data->status : '', 'pending'); ?>>Pending</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <!-- HIDDEN INPUTS UNTUK MENYIMPAN NAMA TEXT WILAYAH -->
                    <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>">
                    <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr($edit_data->kabupaten ?? ''); ?>">
                    <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr($edit_data->kecamatan ?? ''); ?>">
                    <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr($edit_data->kelurahan ?? ''); ?>">

                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Simpan Data Desa">
                        <a href="?page=dw-desa" class="button">Batal</a>
                    </p>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($){
                // 1. Media Uploader
                var mediaUploader;
                $('#btn_upload_foto').click(function(e) {
                    e.preventDefault();
                    if (mediaUploader) { mediaUploader.open(); return; }
                    mediaUploader = wp.media.frames.file_frame = wp.media({
                        title: 'Pilih Foto Desa', button: { text: 'Gunakan Foto' }, multiple: false
                    });
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#foto_desa_url').val(attachment.url);
                    });
                    mediaUploader.open();
                });

                // PERBAIKAN: Menghapus logika Address/Wilayah inline karena sudah ditangani
                // secara otomatis oleh assets/js/admin-scripts.js menggunakan ID yang sama 
                // (#dw_provinsi, #dw_kabupaten, dll) dan mekanisme admin-ajax.php yang lebih stabil.
            });
            </script>
        <?php else: ?>
            
            <!-- LIST TABLE VIEW -->
            <?php 
            if (!class_exists('DW_Desa_List_Table')) require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-desa-list-table.php';
            $list_table = new DW_Desa_List_Table(); 
            $list_table->prepare_items(); 
            ?>
            <form id="desa-filter" method="get">
                <input type="hidden" name="page" value="dw-desa" />
                <?php $list_table->search_box('Cari Desa', 'search_id'); ?>
                <?php $list_table->display(); ?>
            </form>

        <?php endif; ?>
    </div>
    <?php
}