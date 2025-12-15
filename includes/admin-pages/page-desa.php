<?php
/**
 * File Name:   page-desa.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-desa.php
 * * Perbaikan: Membungkus seluruh logika ke dalam fungsi dw_desa_page_render()
 * agar bisa dipanggil oleh menu admin dengan benar.
 */

if (!defined('ABSPATH')) {
    exit;
}

// FUNGSI UTAMA RENDER HALAMAN DESA
function dw_desa_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_desa';

    // --- 1. HANDLE POST REQUEST (SAVE/UPDATE/DELETE) ---
    $message = '';
    $message_type = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_desa'])) {
        
        // Verifikasi Nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_action')) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
            return;
        }

        $action = sanitize_text_field($_POST['action_desa']);

        if ($action === 'delete' && isset($_POST['desa_id'])) {
            // LOGIKA DELETE
            // Hapus relasi dulu sebelum hapus desa
            if (function_exists('dw_handle_desa_deletion')) {
                dw_handle_desa_deletion(intval($_POST['desa_id']));
            }
            $wpdb->delete($table_name, ['id' => intval($_POST['desa_id'])]);
            $message = 'Data desa berhasil dihapus.';
            $message_type = 'success';
        
        } elseif ($action === 'save') {
            // LOGIKA SAVE/UPDATE
            $data = [
                'id_user_desa' => intval($_POST['id_user_desa']),
                'nama_desa'    => sanitize_text_field($_POST['nama_desa']),
                'deskripsi'    => wp_kses_post($_POST['deskripsi']),
                'provinsi'     => sanitize_text_field($_POST['provinsi_nama']), 
                'kabupaten'    => sanitize_text_field($_POST['kabupaten_nama']),
                'kecamatan'    => sanitize_text_field($_POST['kecamatan_nama']),
                'kelurahan'    => sanitize_text_field($_POST['kelurahan_nama']),
                'api_provinsi_id' => sanitize_text_field($_POST['provinsi_id']),
                'api_kabupaten_id'=> sanitize_text_field($_POST['kabupaten_id']),
                'api_kecamatan_id'=> sanitize_text_field($_POST['kecamatan_id']),
                'api_kelurahan_id'=> sanitize_text_field($_POST['kelurahan_id']),
                'alamat_lengkap' => sanitize_textarea_field($_POST['alamat_lengkap']),
                'foto'         => esc_url_raw($_POST['foto_desa_url']),
                'status'       => sanitize_text_field($_POST['status']),
                'updated_at'   => current_time('mysql')
            ];

            if (!empty($_POST['desa_id'])) {
                // UPDATE
                $where = ['id' => intval($_POST['desa_id'])];
                $wpdb->update($table_name, $data, $where);
                $message = 'Data desa berhasil diperbarui.';
            } else {
                // INSERT
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table_name, $data);
                $message = 'Data desa baru berhasil ditambahkan.';
            }
            $message_type = 'success';
        }
    }

    // --- 2. PREPARE DATA FOR EDIT OR LIST ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;

    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
        $edit_id = intval($_GET['id']);
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
    }

    // Ambil list user untuk dropdown
    $users = get_users(['role__in' => ['administrator', 'admin_desa']]);

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
        <?php if(!$is_edit): ?>
            <a href="<?php echo admin_url('admin.php?page=dw-desa&action=new'); ?>" class="page-title-action">Tambah Baru</a>
        <?php endif; ?>
        <hr class="wp-header-end">

        <!-- NOTIFIKASI -->
        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <!-- VIEW: FORM INPUT (NEW/EDIT) -->
        <?php if ($is_edit): ?>
            
            <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px;">
                <form method="post" action="<?php echo admin_url('admin.php?page=dw-desa'); ?>">
                    <?php wp_nonce_field('dw_desa_action'); ?>
                    <input type="hidden" name="action_desa" value="save">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="desa_id" value="<?php echo esc_attr($edit_data->id); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <!-- Akun Pengelola -->
                        <tr>
                            <th scope="row"><label for="id_user_desa">Akun Admin Desa</label></th>
                            <td>
                                <select name="id_user_desa" id="id_user_desa" class="regular-text" required>
                                    <option value="">-- Pilih User --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user_desa : '', $user->ID); ?>>
                                            <?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Pilih user WordPress yang akan mengelola desa ini.</p>
                            </td>
                        </tr>

                        <!-- Nama Desa -->
                        <tr>
                            <th scope="row"><label for="nama_desa">Nama Desa</label></th>
                            <td>
                                <input name="nama_desa" type="text" id="nama_desa" value="<?php echo esc_attr($edit_data->nama_desa ?? ''); ?>" class="regular-text" required>
                            </td>
                        </tr>

                        <!-- Deskripsi -->
                        <tr>
                            <th scope="row"><label for="deskripsi">Deskripsi</label></th>
                            <td>
                                <?php 
                                $content = $edit_data->deskripsi ?? '';
                                wp_editor($content, 'deskripsi', ['textarea_rows' => 5, 'media_buttons' => false]); 
                                ?>
                            </td>
                        </tr>

                        <!-- Foto Utama -->
                        <tr>
                            <th scope="row">Foto Desa</th>
                            <td>
                                <div class="image-preview-wrapper">
                                    <img id="foto_preview" src="<?php echo esc_url($edit_data->foto ?? ''); ?>" style="max-width: 200px; display: <?php echo empty($edit_data->foto) ? 'none' : 'block'; ?>; margin-bottom: 10px; border-radius: 8px;">
                                </div>
                                <input type="hidden" name="foto_desa_url" id="foto_desa_url" value="<?php echo esc_attr($edit_data->foto ?? ''); ?>">
                                <button type="button" class="button" id="upload_foto_btn">Pilih Foto</button>
                                <button type="button" class="button is-link is-destructive" id="remove_foto_btn" style="display: <?php echo empty($edit_data->foto) ? 'none' : 'inline-block'; ?>;">Hapus</button>
                            </td>
                        </tr>

                        <!-- Hidden Inputs untuk Nama Wilayah -->
                        <input type="hidden" name="provinsi_nama" id="provinsi_nama" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>">
                        <input type="hidden" name="kabupaten_nama" id="kabupaten_nama" value="<?php echo esc_attr($edit_data->kabupaten ?? ''); ?>">
                        <input type="hidden" name="kecamatan_nama" id="kecamatan_nama" value="<?php echo esc_attr($edit_data->kecamatan ?? ''); ?>">
                        <input type="hidden" name="kelurahan_nama" id="kelurahan_nama" value="<?php echo esc_attr($edit_data->kelurahan ?? ''); ?>">

                        <!-- DROPDOWNS WILAYAH -->
                        <tr>
                            <th scope="row">Alamat Wilayah</th>
                            <td>
                                <div class="dw-address-wrapper">
                                    <select name="provinsi_id" id="select_provinsi" class="regular-text" data-selected="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>" style="margin-bottom:10px; display:block;">
                                        <option value="">Pilih Provinsi</option>
                                    </select>
                                    <select name="kabupaten_id" id="select_kabupaten" class="regular-text" data-selected="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>" style="margin-bottom:10px; display:block;" disabled>
                                        <option value="">Pilih Kabupaten/Kota</option>
                                    </select>
                                    <select name="kecamatan_id" id="select_kecamatan" class="regular-text" data-selected="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>" style="margin-bottom:10px; display:block;" disabled>
                                        <option value="">Pilih Kecamatan</option>
                                    </select>
                                    <select name="kelurahan_id" id="select_kelurahan" class="regular-text" data-selected="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>" style="margin-bottom:10px; display:block;" disabled>
                                        <option value="">Pilih Kelurahan</option>
                                    </select>
                                </div>
                            </td>
                        </tr>

                        <!-- Alamat Detail -->
                        <tr>
                            <th scope="row"><label for="alamat_lengkap">Alamat Lengkap</label></th>
                            <td>
                                <textarea name="alamat_lengkap" id="alamat_lengkap" class="large-text" rows="3"><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                            </td>
                        </tr>

                        <!-- Status -->
                        <tr>
                            <th scope="row"><label for="status">Status</label></th>
                            <td>
                                <select name="status" id="status">
                                    <option value="aktif" <?php selected($edit_data ? $edit_data->status : 'aktif', 'aktif'); ?>>Aktif</option>
                                    <option value="pending" <?php selected($edit_data ? $edit_data->status : '', 'pending'); ?>>Pending</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo isset($_GET['id']) ? 'Update Desa' : 'Simpan Desa Baru'; ?>">
                        <a href="<?php echo admin_url('admin.php?page=dw-desa'); ?>" class="button button-secondary">Batal</a>
                    </p>
                </form>
            </div>

            <!-- Script JS untuk Halaman Ini -->
            <script>
            jQuery(document).ready(function($){
                // Media Uploader
                var mediaUploader;
                $('#upload_foto_btn').click(function(e) {
                    e.preventDefault();
                    if (mediaUploader) { mediaUploader.open(); return; }
                    mediaUploader = wp.media.frames.file_frame = wp.media({
                        title: 'Pilih Foto Desa', button: { text: 'Gunakan Foto Ini' }, multiple: false
                    });
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#foto_desa_url').val(attachment.url);
                        $('#foto_preview').attr('src', attachment.url).show();
                        $('#remove_foto_btn').show();
                    });
                    mediaUploader.open();
                });
                $('#remove_foto_btn').click(function(){
                    $('#foto_desa_url').val(''); $('#foto_preview').hide(); $(this).hide();
                });

                // Address logic ditangani oleh admin-scripts.js via ID #select_provinsi dll.
            });
            </script>

        <!-- VIEW: LIST TABLE -->
        <?php else: ?>
            
            <?php
            // Pastikan class sudah diload di admin-menus.php, jika belum, require di sini untuk jaga-jaga
            if (!class_exists('DW_Desa_List_Table')) {
                require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-desa-list-table.php';
            }
            $desa_list_table = new DW_Desa_List_Table();
            $desa_list_table->prepare_items();
            ?>
            
            <form id="desa-filter" method="get">
                <input type="hidden" name="page" value="dw-desa" />
                <?php $desa_list_table->search_box('Cari Desa', 'search_id'); ?>
                <?php $desa_list_table->display(); ?>
            </form>

        <?php endif; ?>
    </div>
    <?php
}
?>