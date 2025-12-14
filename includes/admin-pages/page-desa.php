<?php
// Pastikan tidak diakses langsung
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'dw_desa';

// --- 1. HANDLE POST REQUEST (SAVE/UPDATE/DELETE) ---
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_desa'])) {
    
    // Verifikasi Nonce untuk keamanan
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_action')) {
        die('Security check failed');
    }

    $action = sanitize_text_field($_POST['action_desa']);

    if ($action === 'delete' && isset($_POST['desa_id'])) {
        // --- LOGIKA DELETE ---
        $wpdb->delete($table_name, ['id' => intval($_POST['desa_id'])]);
        $message = 'Data desa berhasil dihapus.';
        $message_type = 'success';
    
    } elseif ($action === 'save') {
        // --- LOGIKA SAVE/UPDATE ---
        
        // Sanitasi Input
        $data = [
            'id_user_desa' => intval($_POST['id_user_desa']),
            'nama_desa'    => sanitize_text_field($_POST['nama_desa']),
            'deskripsi'    => wp_kses_post($_POST['deskripsi']),
            'provinsi'     => sanitize_text_field($_POST['provinsi_nama']), // Simpan Nama, bukan ID
            'kabupaten'    => sanitize_text_field($_POST['kabupaten_nama']),
            'kecamatan'    => sanitize_text_field($_POST['kecamatan_nama']),
            'kelurahan'    => sanitize_text_field($_POST['kelurahan_nama']),
            // Simpan juga ID untuk keperluan API kedepannya jika perlu
            'alamat_json'  => json_encode([
                'prov_id' => sanitize_text_field($_POST['provinsi']),
                'kab_id'  => sanitize_text_field($_POST['kabupaten']),
                'kec_id'  => sanitize_text_field($_POST['kecamatan']),
                'kel_id'  => sanitize_text_field($_POST['kelurahan'])
            ]),
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
$is_edit = isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']);
$edit_data = null;

if ($is_edit) {
    $edit_id = intval($_GET['id']);
    $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
    
    // Decode JSON alamat untuk pre-fill dropdown
    $alamat_ids = json_decode($edit_data->alamat_json ?? '{}', true);
}

// Ambil list user untuk dropdown 'Kepala Desa / Akun Admin Desa'
$users = get_users(['role__in' => ['administrator', 'dw_admin_desa']]);

?>

<div class="wrap">
    <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
    <a href="<?php echo admin_url('admin.php?page=dw-desa&action=new'); ?>" class="page-title-action">Tambah Baru</a>
    <hr class="wp-header-end">

    <!-- NOTIFIKASI -->
    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>

    <!-- VIEW: FORM INPUT (NEW/EDIT) -->
    <?php if (isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit')): ?>
        
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

                    <!-- WILAYAH (Hidden Fields untuk menyimpan Nama Wilayah) -->
                    <input type="hidden" name="provinsi_nama" id="provinsi_nama" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>">
                    <input type="hidden" name="kabupaten_nama" id="kabupaten_nama" value="<?php echo esc_attr($edit_data->kabupaten ?? ''); ?>">
                    <input type="hidden" name="kecamatan_nama" id="kecamatan_nama" value="<?php echo esc_attr($edit_data->kecamatan ?? ''); ?>">
                    <input type="hidden" name="kelurahan_nama" id="kelurahan_nama" value="<?php echo esc_attr($edit_data->kelurahan ?? ''); ?>">

                    <!-- DROPDOWNS WILAYAH (Trigger JS) -->
                    <tr>
                        <th scope="row">Alamat Wilayah</th>
                        <td>
                            <!-- Atribut data-selected digunakan oleh JS untuk pre-fill saat edit -->
                            <div style="margin-bottom: 10px;">
                                <select name="provinsi" id="select_provinsi" class="regular-text" 
                                    data-selected="<?php echo esc_attr($alamat_ids['prov_id'] ?? ''); ?>">
                                    <option value="">Pilih Provinsi</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <select name="kabupaten" id="select_kabupaten" class="regular-text" 
                                    data-selected="<?php echo esc_attr($alamat_ids['kab_id'] ?? ''); ?>" disabled>
                                    <option value="">Pilih Kabupaten/Kota</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <select name="kecamatan" id="select_kecamatan" class="regular-text" 
                                    data-selected="<?php echo esc_attr($alamat_ids['kec_id'] ?? ''); ?>" disabled>
                                    <option value="">Pilih Kecamatan</option>
                                </select>
                            </div>
                            <div>
                                <select name="kelurahan" id="select_kelurahan" class="regular-text" 
                                    data-selected="<?php echo esc_attr($alamat_ids['kel_id'] ?? ''); ?>" disabled>
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
                                <option value="nonaktif" <?php selected($edit_data ? $edit_data->status : '', 'nonaktif'); ?>>Nonaktif</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $is_edit ? 'Update Desa' : 'Simpan Desa Baru'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=dw-desa'); ?>" class="button button-secondary">Batal</a>
                </p>
            </form>
        </div>

        <!-- Script Khusus Halaman Ini (Media Uploader & Address Logic) -->
        <script>
        jQuery(document).ready(function($){
            // --- 1. MEDIA UPLOADER ---
            var mediaUploader;
            $('#upload_foto_btn').click(function(e) {
                e.preventDefault();
                if (mediaUploader) { mediaUploader.open(); return; }
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Pilih Foto Desa',
                    button: { text: 'Gunakan Foto Ini' },
                    multiple: false
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
                $('#foto_desa_url').val('');
                $('#foto_preview').hide();
                $(this).hide();
            });

            // --- 2. ADDRESS API HELPER (Simpan Nama saat Change) ---
            // Pastikan Anda memanggil fungsi load wilayah di file JS terpisah (admin-scripts.js)
            // Di sini kita hanya menangkap perubahannya untuk mengisi Hidden Input Nama
            
            $('#select_provinsi').change(function() {
                $('#provinsi_nama').val($(this).find("option:selected").text());
            });
            $('#select_kabupaten').change(function() {
                $('#kabupaten_nama').val($(this).find("option:selected").text());
            });
            $('#select_kecamatan').change(function() {
                $('#kecamatan_nama').val($(this).find("option:selected").text());
            });
            $('#select_kelurahan').change(function() {
                $('#kelurahan_nama').val($(this).find("option:selected").text());
            });
        });
        </script>

    <!-- VIEW: LIST TABLE -->
    <?php else: ?>
        
        <?php
        // Load List Table Class
        require_once DW_CORE_PATH . 'includes/list-tables/class-dw-desa-list-table.php';
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