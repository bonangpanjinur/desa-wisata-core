<?php
/**
 * Halaman Manajemen Ojek (Admin Side)
 * Menampilkan list, form tambah/edit, dan proses approval.
 * * * UPDATE: Form Pendaftaran Lengkap + API Wilayah
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load List Table Class
require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-ojek-list-table.php';

function dw_ojek_management_page_render() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    // Enqueue Media Uploader & Styles
    if ($action == 'add' || $action == 'edit') {
        wp_enqueue_media();
        ?>
        <style>
            .dw-admin-form-container { max-width: 1200px; margin-top: 20px; }
            .dw-form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
            .dw-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            .dw-card h3 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; font-size: 1.1em; color: #1d2327; }
            .dw-form-row { margin-bottom: 15px; }
            .dw-form-row label { display: block; font-weight: 600; margin-bottom: 5px; color: #444; }
            .dw-form-row input[type="text"], .dw-form-row input[type="email"], .dw-form-row textarea, .dw-form-row select { width: 100%; box-sizing: border-box; }
            .dw-media-preview { margin-top: 10px; background: #f6f7f7; border: 1px dashed #c3c4c7; padding: 10px; text-align: center; border-radius: 4px; min-height: 80px; display: flex; align-items: center; justify-content: center; position: relative; }
            .dw-media-preview img { max-width: 100%; max-height: 150px; display: block; margin: 0 auto; }
            .dw-media-actions { margin-top: 8px; display: flex; gap: 5px; justify-content: center; }
            .select2-container { width: 100% !important; }
            @media (max-width: 782px) { .dw-form-grid { grid-template-columns: 1fr; } }
        </style>
        <?php
    }

    // Handle Actions
    dw_handle_ojek_actions();

    echo '<div class="wrap">';
    
    if ($action == 'add' || $action == 'edit') {
        dw_render_ojek_form($action, $id);
    } else {
        dw_render_ojek_list();
    }
    
    echo '</div>';
}

function dw_render_ojek_list() {
    $ojek_table = new DW_Ojek_List_Table();
    $ojek_table->prepare_items();
    ?>
    <h1 class="wp-heading-inline">Manajemen Ojek Desa</h1>
    <a href="?page=dw-manajemen-ojek&action=add" class="page-title-action">Tambah Ojek Baru</a>
    <hr class="wp-header-end">
    <form method="post">
        <?php $ojek_table->search_box('Cari Driver', 'search_id'); $ojek_table->display(); ?>
    </form>
    <?php
}

function dw_render_ojek_form($action, $id) {
    global $wpdb;
    $title = ($action == 'add') ? 'Pendaftaran Ojek Baru' : 'Edit Data Ojek';
    $back_url = admin_url('admin.php?page=dw-manajemen-ojek');
    
    // Default Data
    $data = [
        'id_user' => '', 'nama_lengkap' => '', 'no_hp' => '', 'nik' => '', 
        'no_kartu_ojek' => '', 'plat_nomor' => '', 'merk_motor' => '', 'alamat_domisili' => '',
        'api_provinsi_id' => '', 'api_kabupaten_id' => '', 'api_kecamatan_id' => '', 'api_kelurahan_id' => '',
        'foto_profil' => '', 'foto_ktp' => '', 'foto_kartu_ojek' => '', 'foto_motor' => '',
        'status_pendaftaran' => 'menunggu', 'status_kerja' => 'offline'
    ];
    
    $user_email = '';

    if ($action == 'edit' && $id > 0) {
        $ojek = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_ojek WHERE id = %d", $id));
        if ($ojek) {
            $data = (array) $ojek;
            $user = get_userdata($ojek->id_user);
            $user_email = $user ? $user->user_email : '(User dihapus)';
        }
    }
    ?>
    
    <div class="dw-admin-form-container">
        <h1 class="wp-heading-inline"><?php echo $title; ?></h1>
        <a href="<?php echo $back_url; ?>" class="page-title-action">Kembali</a>
        <hr class="wp-header-end">

        <form method="post" action="">
            <?php wp_nonce_field('dw_save_ojek_action', 'dw_save_ojek_nonce'); ?>
            <input type="hidden" name="dw_action_type" value="<?php echo $action; ?>">
            <input type="hidden" name="ojek_id" value="<?php echo $id; ?>">

            <div class="dw-form-grid">
                
                <!-- KOLOM KIRI -->
                <div class="dw-col-main">
                    
                    <!-- 1. AKUN PENGGUNA -->
                    <div class="dw-card">
                        <h3>1. Akun Pengguna</h3>
                        <?php if ($action == 'add'): ?>
                            <div class="dw-form-row">
                                <label>Pilih Pengguna (Belum Punya Akun Ojek)</label>
                                <?php 
                                // Query User yang BELUM terdaftar di tabel dw_ojek & dw_pedagang
                                $sql_users = "SELECT ID, display_name, user_email FROM {$wpdb->users} 
                                              WHERE ID NOT IN (SELECT id_user FROM {$wpdb->prefix}dw_ojek) 
                                              AND ID NOT IN (SELECT id_user FROM {$wpdb->prefix}dw_pedagang)
                                              ORDER BY user_registered DESC LIMIT 100";
                                $users = $wpdb->get_results($sql_users);
                                ?>
                                <select name="id_user_existing" class="dw-select2 regular-text">
                                    <option value="">-- Buat User Baru Otomatis --</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="dw-form-row" id="new_email_row">
                                <label>Email (Untuk Buat User Baru)</label>
                                <input type="email" name="new_user_email" placeholder="Masukkan email aktif...">
                                <p class="description">Diisi jika tidak memilih pengguna di atas.</p>
                            </div>
                        <?php else: ?>
                            <div class="dw-form-row">
                                <label>Akun Terhubung</label>
                                <input type="text" value="<?php echo esc_attr($user_email); ?>" readonly style="background:#f9f9f9;">
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 2. DATA DIRI & ALAMAT -->
                    <div class="dw-card">
                        <h3>2. Data Diri & Domisili</h3>
                        <div class="dw-form-row">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" value="<?php echo esc_attr($data['nama_lengkap']); ?>" required>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="dw-form-row">
                                <label>No. HP / WA</label>
                                <input type="text" name="no_hp" value="<?php echo esc_attr($data['no_hp']); ?>" required>
                            </div>
                            <div class="dw-form-row">
                                <label>NIK (KTP)</label>
                                <input type="text" name="nik" value="<?php echo esc_attr($data['nik']); ?>">
                            </div>
                        </div>

                        <!-- WILAYAH BERJENJANG -->
                        <div class="dw-form-row">
                            <label>Provinsi</label>
                            <select name="api_provinsi_id" id="sel_provinsi" class="dw-select2" data-selected="<?php echo $data['api_provinsi_id']; ?>">
                                <option value="">Pilih Provinsi</option>
                            </select>
                        </div>
                        <div class="dw-form-row">
                            <label>Kabupaten / Kota</label>
                            <select name="api_kabupaten_id" id="sel_kabupaten" class="dw-select2" data-selected="<?php echo $data['api_kabupaten_id']; ?>" disabled>
                                <option value="">Pilih Kabupaten</option>
                            </select>
                        </div>
                        <div class="dw-form-row">
                            <label>Kecamatan</label>
                            <select name="api_kecamatan_id" id="sel_kecamatan" class="dw-select2" data-selected="<?php echo $data['api_kecamatan_id']; ?>" disabled>
                                <option value="">Pilih Kecamatan</option>
                            </select>
                        </div>
                        <div class="dw-form-row">
                            <label>Kelurahan / Desa</label>
                            <select name="api_kelurahan_id" id="sel_kelurahan" class="dw-select2" data-selected="<?php echo $data['api_kelurahan_id']; ?>" disabled>
                                <option value="">Pilih Kelurahan</option>
                            </select>
                        </div>
                        <div class="dw-form-row">
                            <label>Detail Alamat (Jalan, RT/RW)</label>
                            <textarea name="alamat_domisili" rows="2"><?php echo esc_textarea($data['alamat_domisili']); ?></textarea>
                        </div>
                    </div>

                    <!-- 3. DATA KENDARAAN -->
                    <div class="dw-card">
                        <h3>3. Data Kendaraan</h3>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="dw-form-row">
                                <label>Merk Motor (Contoh: Honda Beat)</label>
                                <input type="text" name="merk_motor" value="<?php echo esc_attr($data['merk_motor']); ?>" required>
                            </div>
                            <div class="dw-form-row">
                                <label>Plat Nomor (D 1234 ABC)</label>
                                <input type="text" name="plat_nomor" value="<?php echo esc_attr($data['plat_nomor']); ?>" style="text-transform:uppercase;" required>
                            </div>
                        </div>
                        <div class="dw-form-row">
                            <label>Nomor Kartu Anggota Ojek (Jika ada)</label>
                            <input type="text" name="no_kartu_ojek" value="<?php echo esc_attr($data['no_kartu_ojek']); ?>">
                        </div>
                    </div>
                </div>

                <!-- KOLOM KANAN -->
                <div class="dw-col-sidebar">
                    <!-- STATUS -->
                    <div class="dw-card" style="border-top:3px solid #2271b1;">
                        <h3>Status Keanggotaan</h3>
                        <div class="dw-form-row">
                            <label>Status Pendaftaran</label>
                            <select name="status_pendaftaran">
                                <option value="menunggu" <?php selected($data['status_pendaftaran'], 'menunggu'); ?>>Menunggu Verifikasi</option>
                                <option value="disetujui" <?php selected($data['status_pendaftaran'], 'disetujui'); ?>>Disetujui (Aktif)</option>
                                <option value="ditolak" <?php selected($data['status_pendaftaran'], 'ditolak'); ?>>Ditolak</option>
                            </select>
                        </div>
                        <div class="dw-form-row">
                            <label>Status Kerja</label>
                            <select name="status_kerja">
                                <option value="offline" <?php selected($data['status_kerja'], 'offline'); ?>>Offline</option>
                                <option value="online" <?php selected($data['status_kerja'], 'online'); ?>>Online</option>
                                <option value="busy" <?php selected($data['status_kerja'], 'busy'); ?>>Sibuk</option>
                            </select>
                        </div>
                        <div style="margin-top:20px;">
                            <button type="submit" class="button button-primary button-large" style="width:100%;">Simpan Data</button>
                        </div>
                    </div>

                    <!-- FOTO DOKUMEN -->
                    <div class="dw-card">
                        <h3>Dokumen Foto</h3>
                        
                        <?php 
                        $docs = [
                            'foto_profil' => 'Foto Profil',
                            'foto_ktp' => 'Foto KTP',
                            'foto_kartu_ojek' => 'Foto Kartu Ojek',
                            'foto_motor' => 'Foto Motor'
                        ];
                        foreach($docs as $field => $label): 
                            $img = $data[$field];
                        ?>
                        <div class="dw-form-row">
                            <label><?php echo $label; ?></label>
                            <input type="hidden" name="<?php echo $field; ?>" id="<?php echo $field; ?>" value="<?php echo esc_url($img); ?>">
                            
                            <div class="dw-media-preview" id="preview_<?php echo $field; ?>">
                                <?php if($img): ?>
                                    <img src="<?php echo esc_url($img); ?>">
                                <?php else: ?>
                                    <span style="color:#aaa; font-size:12px;">Belum ada foto</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="dw-media-actions">
                                <button type="button" class="button button-small dw-upload-btn" data-target="<?php echo $field; ?>">Pilih</button>
                                <button type="button" class="button button-small dw-remove-btn" data-target="<?php echo $field; ?>" style="color:#a00;">Hapus</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- JAVASCRIPT: WILAYAH & MEDIA UPLOADER -->
    <script>
    jQuery(document).ready(function($){
        // 1. INIT SELECT2
        if($.fn.select2) {
            $('.dw-select2').select2({ width: '100%' });
        }

        // 2. MEDIA UPLOADER
        $('.dw-upload-btn').click(function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            var uploader = wp.media({
                title: 'Pilih Foto',
                button: { text: 'Gunakan Foto Ini' },
                multiple: false
            }).on('select', function() {
                var attachment = uploader.state().get('selection').first().toJSON();
                $('#' + target).val(attachment.url);
                $('#preview_' + target).html('<img src="' + attachment.url + '">');
            }).open();
        });

        $('.dw-remove-btn').click(function() {
            var target = $(this).data('target');
            $('#' + target).val('');
            $('#preview_' + target).html('<span style="color:#aaa; font-size:12px;">Belum ada foto</span>');
        });

        // 3. API WILAYAH BERJENJANG
        function loadWilayah(type, id, targetEl, selectedId) {
            var el = $(targetEl);
            el.html('<option value="">Loading...</option>').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                data: { action: 'dw_get_wilayah', type: type, id: id },
                success: function(res) {
                    el.html('<option value="">Pilih ' + type.charAt(0).toUpperCase() + type.slice(1) + '</option>');
                    if(res.success) {
                        $.each(res.data, function(i, item){
                            var isSel = (item.id == selectedId) ? 'selected' : '';
                            el.append('<option value="'+item.id+'" '+isSel+'>'+item.name+'</option>');
                        });
                        el.prop('disabled', false);
                        // Trigger change manually if we set a value
                        if(selectedId) el.trigger('change');
                    }
                }
            });
        }

        // Init Provinsi
        var selProv = $('#sel_provinsi').data('selected');
        loadWilayah('provinsi', '', '#sel_provinsi', selProv);

        // Chain Events
        $('#sel_provinsi').change(function(){
            var id = $(this).val();
            if(id) loadWilayah('kabupaten', id, '#sel_kabupaten', $('#sel_kabupaten').data('selected'));
            else $('#sel_kabupaten').html('<option value="">Pilih Kabupaten</option>').prop('disabled', true);
        });

        $('#sel_kabupaten').change(function(){
            var id = $(this).val();
            if(id) loadWilayah('kecamatan', id, '#sel_kecamatan', $('#sel_kecamatan').data('selected'));
            else $('#sel_kecamatan').html('<option value="">Pilih Kecamatan</option>').prop('disabled', true);
        });

        $('#sel_kecamatan').change(function(){
            var id = $(this).val();
            if(id) loadWilayah('kelurahan', id, '#sel_kelurahan', $('#sel_kelurahan').data('selected'));
            else $('#sel_kelurahan').html('<option value="">Pilih Kelurahan</option>').prop('disabled', true);
        });
    });
    </script>
    <?php
}

function dw_handle_ojek_actions() {
    global $wpdb;
    
    // DELETE
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = absint($_GET['id']);
        check_admin_referer('delete_ojek_' . $id);
        
        // Cek apakah punya user, jika ya cabut rolenya (opsional)
        $uid = $wpdb->get_var("SELECT id_user FROM {$wpdb->prefix}dw_ojek WHERE id = $id");
        if($uid) {
            $u = new WP_User($uid);
            $u->remove_role('dw_ojek');
        }

        $wpdb->delete($wpdb->prefix.'dw_ojek', ['id' => $id]);
        echo '<div class="notice notice-success is-dismissible"><p>Data ojek dihapus.</p></div>';
    }

    // SAVE
    if (isset($_POST['dw_save_ojek_nonce']) && wp_verify_nonce($_POST['dw_save_ojek_nonce'], 'dw_save_ojek_action')) {
        $action = $_POST['dw_action_type'];
        $nama = sanitize_text_field($_POST['nama_lengkap']);
        $user_id = 0;
        $error = '';

        // 1. Handle User
        if ($action == 'add') {
            if (!empty($_POST['id_user_existing'])) {
                $user_id = absint($_POST['id_user_existing']);
                wp_update_user(['ID' => $user_id, 'display_name' => $nama]);
            } else {
                $email = sanitize_email($_POST['new_user_email']);
                if (!is_email($email)) $error = 'Email tidak valid';
                elseif (email_exists($email)) $error = 'Email sudah terdaftar';
                else {
                    $pass = wp_generate_password();
                    $new_uid = wp_create_user($email, $pass, $email);
                    if (is_wp_error($new_uid)) $error = $new_uid->get_error_message();
                    else $user_id = $new_uid;
                }
            }
            if(!$error && $user_id) {
                $u = new WP_User($user_id);
                $u->add_role('dw_ojek');
            }
        } else {
            $ojek_id = absint($_POST['ojek_id']);
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT id_user FROM {$wpdb->prefix}dw_ojek WHERE id = %d", $ojek_id));
            if($user_id) wp_update_user(['ID' => $user_id, 'display_name' => $nama]);
        }

        if ($error) {
            echo '<div class="notice notice-error"><p>' . $error . '</p></div>';
            return;
        }

        // 2. Save Data
        $data = [
            'nama_lengkap' => $nama,
            'no_hp' => sanitize_text_field($_POST['no_hp']),
            'nik' => sanitize_text_field($_POST['nik']),
            'no_kartu_ojek' => sanitize_text_field($_POST['no_kartu_ojek']),
            'plat_nomor' => sanitize_text_field($_POST['plat_nomor']),
            'merk_motor' => sanitize_text_field($_POST['merk_motor']),
            'alamat_domisili' => sanitize_textarea_field($_POST['alamat_domisili']),
            
            // Wilayah
            'api_provinsi_id' => sanitize_text_field($_POST['api_provinsi_id']),
            'api_kabupaten_id' => sanitize_text_field($_POST['api_kabupaten_id']),
            'api_kecamatan_id' => sanitize_text_field($_POST['api_kecamatan_id']),
            'api_kelurahan_id' => sanitize_text_field($_POST['api_kelurahan_id']),
            
            // Foto
            'foto_profil' => esc_url_raw($_POST['foto_profil']),
            'foto_ktp' => esc_url_raw($_POST['foto_ktp']),
            'foto_kartu_ojek' => esc_url_raw($_POST['foto_kartu_ojek']),
            'foto_motor' => esc_url_raw($_POST['foto_motor']),
            
            'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran']),
            'status_kerja' => sanitize_text_field($_POST['status_kerja']),
        ];

        if ($action == 'add') {
            $data['id_user'] = $user_id;
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix.'dw_ojek', $data);
        } else {
            $data['updated_at'] = current_time('mysql');
            $wpdb->update($wpdb->prefix.'dw_ojek', $data, ['id' => absint($_POST['ojek_id'])]);
        }

        echo '<div class="notice notice-success is-dismissible"><p>Data berhasil disimpan.</p></div>';
    }
}
?>