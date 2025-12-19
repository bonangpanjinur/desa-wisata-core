<?php
/**
 * Halaman Manajemen Ojek (Admin Side)
 * Menampilkan list, form tambah/edit, dan proses approval.
 * * * UPDATE: Form Pendaftaran Lengkap (User Select, Dokumen, Wilayah)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load List Table Class
require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-ojek-list-table.php';

function dw_ojek_management_page_render() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    // Enqueue Media Uploader scripts
    if ($action == 'add' || $action == 'edit') {
        wp_enqueue_media();
    }

    // Handle Actions (Save/Approve/Delete)
    dw_handle_ojek_actions();

    echo '<div class="wrap">';
    
    if ($action == 'add' || $action == 'edit') {
        dw_render_ojek_form($action, $id);
    } else {
        dw_render_ojek_list();
    }
    
    echo '</div>';
}

/**
 * Render Tabel List Ojek
 */
function dw_render_ojek_list() {
    $ojek_table = new DW_Ojek_List_Table();
    $ojek_table->prepare_items();
    
    ?>
    <h1 class="wp-heading-inline">Manajemen Ojek Desa</h1>
    <a href="?page=dw-manajemen-ojek&action=add" class="page-title-action">Tambah Ojek Baru</a>
    <hr class="wp-header-end">
    
    <form method="post">
        <?php
        $ojek_table->search_box('Cari Driver', 'search_id');
        $ojek_table->display(); 
        ?>
    </form>
    <?php
}

/**
 * Render Form Tambah/Edit Ojek
 */
function dw_render_ojek_form($action, $id) {
    global $wpdb;
    $title = 'Tambah Ojek Baru';
    
    // Default Data Structure
    $data = [
        'id_user'            => '',
        'nama_lengkap'       => '',
        'no_hp'              => '',
        'nik'                => '',
        'no_kartu_ojek'      => '',
        'plat_nomor'         => '',
        'merk_motor'         => '',
        'alamat_domisili'    => '',
        'api_provinsi_id'    => '',
        'api_kabupaten_id'   => '',
        'api_kecamatan_id'   => '',
        'api_kelurahan_id'   => '',
        'foto_profil'        => '',
        'foto_ktp'           => '',
        'foto_kartu_ojek'    => '',
        'foto_motor'         => '',
        'status_pendaftaran' => 'menunggu',
        'status_kerja'       => 'offline'
    ];

    $user_email = '';

    // Jika Edit Mode, ambil data dari database
    if ($action == 'edit' && $id > 0) {
        $title = 'Edit Data Ojek';
        $ojek = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_ojek WHERE id = %d", $id));
        
        if ($ojek) {
            $data = (array) $ojek;
            $user = get_userdata($ojek->id_user);
            $user_email = $user ? $user->user_email : '';
        }
    }
    ?>
    <h1><?php echo $title; ?></h1>
    
    <form method="post" action="" class="card dw-admin-form" style="max-width: 1000px; padding: 20px;">
        <?php wp_nonce_field('dw_save_ojek_action', 'dw_save_ojek_nonce'); ?>
        <input type="hidden" name="dw_action_type" value="<?php echo $action; ?>">
        <input type="hidden" name="ojek_id" value="<?php echo $id; ?>">

        <!-- BAGIAN 1: AKUN PENGGUNA -->
        <h3 class="dw-section-title">1. Akun Pengguna</h3>
        <table class="form-table">
            <?php if ($action == 'add'): ?>
                <tr>
                    <th><label>Pilih User Pengguna</label></th>
                    <td>
                        <?php 
                        // Dropdown user yang belum jadi ojek
                        $users = get_users(['role__not_in' => ['dw_ojek', 'dw_pedagang']]);
                        ?>
                        <select name="id_user_existing" class="dw-select2 regular-text">
                            <option value="">-- Buat User Baru --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Pilih user yang sudah ada, atau kosongkan untuk membuat user baru di bawah ini.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Email (User Baru)</label></th>
                    <td>
                        <input type="email" name="new_user_email" class="regular-text">
                        <p class="description">Diisi hanya jika membuat user baru.</p>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <th><label>Akun Terhubung</label></th>
                    <td>
                        <input type="text" value="<?php echo esc_attr($user_email); ?>" class="regular-text" readonly>
                        <p class="description">User WordPress yang terhubung dengan akun ojek ini.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- BAGIAN 2: DATA DIRI & ALAMAT -->
        <h3 class="dw-section-title">2. Data Diri & Domisili</h3>
        <table class="form-table">
            <tr>
                <th><label>Nama Lengkap</label></th>
                <td><input type="text" name="nama_lengkap" value="<?php echo esc_attr($data['nama_lengkap']); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label>No. HP (WhatsApp)</label></th>
                <td><input type="text" name="no_hp" value="<?php echo esc_attr($data['no_hp']); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label>NIK (KTP)</label></th>
                <td><input type="text" name="nik" value="<?php echo esc_attr($data['nik']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label>Nomor Kartu Ojek</label></th>
                <td><input type="text" name="no_kartu_ojek" value="<?php echo esc_attr($data['no_kartu_ojek']); ?>" class="regular-text" placeholder="Opsional"></td>
            </tr>
            
            <!-- Integrasi API Wilayah (Placeholder dropdown) -->
            <tr>
                <th><label>Provinsi</label></th>
                <td><input type="text" name="api_provinsi_id" value="<?php echo esc_attr($data['api_provinsi_id']); ?>" class="regular-text" placeholder="ID Provinsi"></td>
            </tr>
            <tr>
                <th><label>Kota/Kabupaten</label></th>
                <td><input type="text" name="api_kabupaten_id" value="<?php echo esc_attr($data['api_kabupaten_id']); ?>" class="regular-text" placeholder="ID Kabupaten"></td>
            </tr>
            <tr>
                <th><label>Kecamatan</label></th>
                <td><input type="text" name="api_kecamatan_id" value="<?php echo esc_attr($data['api_kecamatan_id']); ?>" class="regular-text" placeholder="ID Kecamatan"></td>
            </tr>
            <tr>
                <th><label>Kelurahan</label></th>
                <td><input type="text" name="api_kelurahan_id" value="<?php echo esc_attr($data['api_kelurahan_id']); ?>" class="regular-text" placeholder="ID Kelurahan"></td>
            </tr>
            <tr>
                <th><label>Alamat Lengkap</label></th>
                <td><textarea name="alamat_domisili" class="large-text" rows="3"><?php echo esc_textarea($data['alamat_domisili']); ?></textarea></td>
            </tr>
        </table>

        <!-- BAGIAN 3: KENDARAAN -->
        <h3 class="dw-section-title">3. Data Kendaraan</h3>
        <table class="form-table">
            <tr>
                <th><label>Merk & Tipe Motor</label></th>
                <td><input type="text" name="merk_motor" value="<?php echo esc_attr($data['merk_motor']); ?>" class="regular-text" placeholder="Contoh: Honda Vario 150"></td>
            </tr>
            <tr>
                <th><label>Plat Nomor</label></th>
                <td><input type="text" name="plat_nomor" value="<?php echo esc_attr($data['plat_nomor']); ?>" class="regular-text" style="text-transform:uppercase;" placeholder="D 1234 ABC"></td>
            </tr>
        </table>

        <!-- BAGIAN 4: DOKUMEN FOTO -->
        <h3 class="dw-section-title">4. Dokumen & Foto</h3>
        <table class="form-table">
            <?php 
            $docs = [
                'foto_profil' => 'Foto Profil',
                'foto_ktp' => 'Foto KTP',
                'foto_kartu_ojek' => 'Foto Kartu Ojek/SIM',
                'foto_motor' => 'Foto Motor'
            ];
            foreach($docs as $key => $label): 
                $img_url = $data[$key];
            ?>
            <tr>
                <th><label><?php echo $label; ?></label></th>
                <td>
                    <div class="dw-media-uploader">
                        <input type="text" name="<?php echo $key; ?>" id="<?php echo $key; ?>" value="<?php echo esc_url($img_url); ?>" class="regular-text">
                        <button type="button" class="button dw-upload-btn" data-target="<?php echo $key; ?>">Upload / Pilih</button>
                        <div class="preview-area" style="margin-top:5px;">
                            <?php if($img_url): ?>
                                <img src="<?php echo esc_url($img_url); ?>" style="max-height:100px; border:1px solid #ddd; padding:3px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- BAGIAN 5: STATUS -->
        <h3 class="dw-section-title">5. Status Keanggotaan</h3>
        <table class="form-table">
            <tr>
                <th><label>Status Pendaftaran</label></th>
                <td>
                    <select name="status_pendaftaran">
                        <option value="menunggu" <?php selected($data['status_pendaftaran'], 'menunggu'); ?>>Menunggu Verifikasi</option>
                        <option value="disetujui" <?php selected($data['status_pendaftaran'], 'disetujui'); ?>>Disetujui (Aktif)</option>
                        <option value="ditolak" <?php selected($data['status_pendaftaran'], 'ditolak'); ?>>Ditolak</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Status Kerja</label></th>
                <td>
                    <select name="status_kerja">
                        <option value="offline" <?php selected($data['status_kerja'], 'offline'); ?>>Offline</option>
                        <option value="online" <?php selected($data['status_kerja'], 'online'); ?>>Online</option>
                        <option value="busy" <?php selected($data['status_kerja'], 'busy'); ?>>Sibuk (Dalam Perjalanan)</option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">Simpan Data Ojek</button>
            <a href="?page=dw-manajemen-ojek" class="button">Batal</a>
        </p>
    </form>

    <!-- Simple JS for Media Uploader -->
    <script>
    jQuery(document).ready(function($){
        // Select2
        if($.fn.select2) {
            $('.dw-select2').select2();
        }

        // Media Uploader
        $('.dw-upload-btn').click(function(e) {
            e.preventDefault();
            var targetID = $(this).data('target');
            var custom_uploader = wp.media({
                title: 'Pilih Gambar',
                button: { text: 'Gunakan Gambar Ini' },
                multiple: false
            }).on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $('#' + targetID).val(attachment.url);
                $('#' + targetID).siblings('.preview-area').html('<img src="' + attachment.url + '" style="max-height:100px;">');
            }).open();
        });
    });
    </script>
    <?php
}

/**
 * Handle POST Actions
 */
function dw_handle_ojek_actions() {
    global $wpdb;
    
    // 1. Approve via Link
    if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
        $id = absint($_GET['id']);
        check_admin_referer('approve_ojek_' . $id);
        
        $wpdb->update($wpdb->prefix.'dw_ojek', ['status_pendaftaran' => 'disetujui'], ['id' => $id]);
        
        // Trigger Bonus jika User Handler Ojek ada logicnya
        $ojek = $wpdb->get_row("SELECT id_user FROM {$wpdb->prefix}dw_ojek WHERE id = $id");
        if ($ojek && class_exists('DW_Ojek_Handler')) {
            DW_Ojek_Handler::check_new_ojek_bonus($ojek->id_user, 'dw_ojek', []);
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>Ojek berhasil disetujui.</p></div>';
    }

    // 2. Delete via Link
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = absint($_GET['id']);
        check_admin_referer('delete_ojek_' . $id);
        
        // Opsional: Remove role dw_ojek from user
        $user_id = $wpdb->get_var("SELECT id_user FROM {$wpdb->prefix}dw_ojek WHERE id = $id");
        if ($user_id) {
            $user = new WP_User($user_id);
            $user->remove_role('dw_ojek');
        }

        $wpdb->delete($wpdb->prefix.'dw_ojek', ['id' => $id]);
        echo '<div class="notice notice-success is-dismissible"><p>Data ojek dihapus.</p></div>';
    }

    // 3. Save Form (Add/Edit)
    if (isset($_POST['dw_save_ojek_nonce']) && wp_verify_nonce($_POST['dw_save_ojek_nonce'], 'dw_save_ojek_action')) {
        
        $action_type = $_POST['dw_action_type'];
        $nama = sanitize_text_field($_POST['nama_lengkap']);
        $user_id = 0;

        // --- A. LOGIKA USER (ADD MODE) ---
        if ($action_type == 'add') {
            // Cek apakah User Dipilih atau Buat Baru
            if (!empty($_POST['id_user_existing'])) {
                $user_id = absint($_POST['id_user_existing']);
                // Update nama user
                wp_update_user(['ID' => $user_id, 'display_name' => $nama]);
            } else {
                // Buat User Baru
                $email = sanitize_email($_POST['new_user_email']);
                if (!is_email($email)) {
                    echo '<div class="notice notice-error"><p>Email tidak valid!</p></div>';
                    return;
                }
                if (email_exists($email)) {
                    echo '<div class="notice notice-error"><p>Email sudah terdaftar!</p></div>';
                    return;
                }
                
                $password = wp_generate_password();
                $user_id = wp_create_user($email, $password, $email);
                
                if (is_wp_error($user_id)) {
                    echo '<div class="notice notice-error"><p>' . $user_id->get_error_message() . '</p></div>';
                    return;
                }
            }

            // Set Role jadi Ojek
            $user = new WP_User($user_id);
            $user->add_role('dw_ojek');
        } else {
            // Edit Mode - Ambil ID dari DB Ojek (Jangan ubah ID User)
            $ojek_id = absint($_POST['ojek_id']);
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT id_user FROM {$wpdb->prefix}dw_ojek WHERE id = %d", $ojek_id));
            
            // Update Nama User WP
            if ($user_id) wp_update_user(['ID' => $user_id, 'display_name' => $nama]);
        }

        // --- B. PREPARE DATA DB ---
        $db_data = [
            'nama_lengkap'       => $nama,
            'no_hp'              => sanitize_text_field($_POST['no_hp']),
            'nik'                => sanitize_text_field($_POST['nik']),
            'no_kartu_ojek'      => sanitize_text_field($_POST['no_kartu_ojek']),
            'merk_motor'         => sanitize_text_field($_POST['merk_motor']),
            'plat_nomor'         => sanitize_text_field($_POST['plat_nomor']),
            
            // Wilayah
            'alamat_domisili'    => sanitize_textarea_field($_POST['alamat_domisili']),
            'api_provinsi_id'    => sanitize_text_field($_POST['api_provinsi_id']),
            'api_kabupaten_id'   => sanitize_text_field($_POST['api_kabupaten_id']),
            'api_kecamatan_id'   => sanitize_text_field($_POST['api_kecamatan_id']),
            'api_kelurahan_id'   => sanitize_text_field($_POST['api_kelurahan_id']),
            
            // Foto
            'foto_profil'        => esc_url_raw($_POST['foto_profil']),
            'foto_ktp'           => esc_url_raw($_POST['foto_ktp']),
            'foto_kartu_ojek'    => esc_url_raw($_POST['foto_kartu_ojek']),
            'foto_motor'         => esc_url_raw($_POST['foto_motor']),
            
            // Status
            'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran']),
            'status_kerja'       => sanitize_text_field($_POST['status_kerja'])
        ];

        // --- C. EKSEKUSI DB ---
        if ($action_type == 'add') {
            $db_data['id_user'] = $user_id;
            $db_data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'dw_ojek', $db_data);
        } else {
            $db_data['updated_at'] = current_time('mysql');
            $wpdb->update($wpdb->prefix . 'dw_ojek', $db_data, ['id' => absint($_POST['ojek_id'])]);
        }

        echo '<div class="notice notice-success is-dismissible"><p>Data ojek berhasil disimpan.</p></div>';
    }
}
?>