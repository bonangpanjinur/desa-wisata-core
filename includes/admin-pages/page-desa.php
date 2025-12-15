<?php
/**
 * File Name:   page-desa.php
 * Description: Fix CRUD Desa (Validasi, Error Handling, Slug Generator).
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
        
        // SAVE
        } elseif ($action === 'save') {
            $nama_desa = sanitize_text_field($_POST['nama_desa']);
            // Auto generate slug jika kosong atau buat baru
            $slug = sanitize_title($nama_desa);
            
            $data = [
                'id_user_desa' => intval($_POST['id_user_desa']),
                'nama_desa'    => $nama_desa,
                'slug_desa'    => $slug,
                'deskripsi'    => wp_kses_post($_POST['deskripsi']),
                'foto'         => esc_url_raw($_POST['foto_desa_url']),
                'status'       => sanitize_text_field($_POST['status']),
                
                // Wilayah
                'provinsi'     => sanitize_text_field($_POST['provinsi_nama']), 
                'kabupaten'    => sanitize_text_field($_POST['kabupaten_nama']),
                'kecamatan'    => sanitize_text_field($_POST['kecamatan_nama']),
                'kelurahan'    => sanitize_text_field($_POST['kelurahan_nama']),
                'api_provinsi_id' => sanitize_text_field($_POST['provinsi_id']),
                'api_kabupaten_id'=> sanitize_text_field($_POST['kabupaten_id']),
                'api_kecamatan_id'=> sanitize_text_field($_POST['kecamatan_id']),
                'api_kelurahan_id'=> sanitize_text_field($_POST['kelurahan_id']),
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

    // --- VIEW LOGIC ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }
    
    $users = get_users(['role__in' => ['administrator', 'admin_desa', 'editor', 'author']]); // Memperluas role agar dropdown tidak kosong

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-desa&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <div class="card" style="padding: 20px; max-width: 100%;">
                <form method="post">
                    <?php wp_nonce_field('dw_desa_action'); ?>
                    <input type="hidden" name="action_desa" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="desa_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label>Akun Admin Desa</label></th>
                            <td>
                                <select name="id_user_desa" class="regular-text" required>
                                    <option value="">-- Pilih User --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user_desa : '', $user->ID); ?>>
                                            <?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr><th>Nama Desa</th><td><input name="nama_desa" type="text" value="<?php echo esc_attr($edit_data->nama_desa ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>5, 'media_buttons'=>false]); ?></td></tr>
                        
                        <!-- Input Foto dengan WP Media -->
                        <tr><th>Foto Desa</th><td>
                            <input type="text" name="foto_desa_url" id="foto_desa_url" value="<?php echo esc_attr($edit_data->foto ?? ''); ?>" class="regular-text">
                            <button type="button" class="button" id="btn_upload_foto">Upload</button>
                        </td></tr>

                        <!-- Wilayah (Simulasi/Hidden jika JS Address API belum aktif) -->
                        <tr><th>Lokasi (Manual/API)</th><td>
                            <input type="text" name="provinsi_nama" placeholder="Provinsi" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>" class="regular-text">
                            <input type="text" name="kabupaten_nama" placeholder="Kabupaten" value="<?php echo esc_attr($edit_data->kabupaten ?? ''); ?>" class="regular-text">
                        </td></tr>
                        
                        <tr><th>Status</th><td>
                            <select name="status">
                                <option value="aktif" <?php selected($edit_data ? $edit_data->status : '', 'aktif'); ?>>Aktif</option>
                                <option value="pending" <?php selected($edit_data ? $edit_data->status : '', 'pending'); ?>>Pending</option>
                            </select>
                        </td></tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Simpan Data">
                        <a href="?page=dw-desa" class="button">Batal</a>
                    </p>
                </form>
            </div>
            <script>
            jQuery(document).ready(function($){
                var mediaUploader;
                $('#btn_upload_foto').click(function(e) {
                    e.preventDefault();
                    if (mediaUploader) { mediaUploader.open(); return; }
                    mediaUploader = wp.media.frames.file_frame = wp.media({
                        title: 'Pilih Foto', button: { text: 'Gunakan Foto' }, multiple: false
                    });
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#foto_desa_url').val(attachment.url);
                    });
                    mediaUploader.open();
                });
            });
            </script>
        <?php else: ?>
            <!-- LIST TABLE PLACEHOLDER -->
            <p>Silakan gunakan tombol "Tambah Baru" untuk membuat Desa. (Table View Logic di sini)</p>
            <?php 
            if (!class_exists('DW_Desa_List_Table')) require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-desa-list-table.php';
            $list_table = new DW_Desa_List_Table(); $list_table->prepare_items(); $list_table->display(); 
            ?>
        <?php endif; ?>
    </div>
    <?php
}