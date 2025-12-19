<?php
/**
 * Halaman Manajemen Ojek (Admin Side)
 * Menampilkan list, form tambah/edit, dan proses approval.
 * * * UPDATE: UI/UX Enhancement - Tabbed Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load List Table Class
require_once plugin_dir_path(dirname(__FILE__)) . 'list-tables/class-dw-ojek-list-table.php';

// Pastikan file Address API dimuat
require_once plugin_dir_path(dirname(__FILE__)) . 'address-api.php';

/**
 * Fungsi Utama Rendering Halaman
 */
function dw_ojek_management_page_render() {
    global $wpdb;
    $table_ojek = $wpdb->prefix . 'dw_ojek';
    
    // Inisialisasi API Wilayah
    $address_api = new DW_Address_API();
    
    // Ambil slug halaman & view saat ini
    $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dw-ojek-management';
    $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : (isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list');
    
    // Ambil ID dari URL untuk mode edit awal
    $url_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $message = '';

    // --- LOGIC SAVE DATA (POST HANDLER) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dw_action']) && $_POST['dw_action'] === 'save_ojek') {
        
        // 1. Cek Keamanan
        if (!isset($_POST['dw_ojek_nonce']) || !wp_verify_nonce($_POST['dw_ojek_nonce'], 'dw_save_ojek')) {
            wp_die('Security check failed (Nonce verification error).');
        }

        // 2. Ambil ID dari Form (Hidden Input)
        $post_id = isset($_POST['ojek_id']) ? absint($_POST['ojek_id']) : 0;
        $user_id = get_current_user_id();

        // 3. Siapkan Data (Gunakan isset untuk mencegah warning)
        $data = [
            'nama_lengkap'      => isset($_POST['nama_lengkap']) ? sanitize_text_field(wp_unslash($_POST['nama_lengkap'])) : '',
            'no_hp'             => isset($_POST['no_hp']) ? sanitize_text_field(wp_unslash($_POST['no_hp'])) : '',
            'plat_nomor'        => isset($_POST['plat_nomor']) ? sanitize_text_field(wp_unslash($_POST['plat_nomor'])) : '',
            'merk_motor'        => isset($_POST['merk_motor']) ? sanitize_text_field(wp_unslash($_POST['merk_motor'])) : '',
            'alamat_domisili'   => isset($_POST['alamat_domisili']) ? sanitize_textarea_field(wp_unslash($_POST['alamat_domisili'])) : '',
            
            // Wilayah
            'api_provinsi_id'   => isset($_POST['api_provinsi_id']) ? sanitize_text_field(wp_unslash($_POST['api_provinsi_id'])) : '',
            'api_kabupaten_id'  => isset($_POST['api_kabupaten_id']) ? sanitize_text_field(wp_unslash($_POST['api_kabupaten_id'])) : '',
            'api_kecamatan_id'  => isset($_POST['api_kecamatan_id']) ? sanitize_text_field(wp_unslash($_POST['api_kecamatan_id'])) : '',
            'api_kelurahan_id'  => isset($_POST['api_kelurahan_id']) ? sanitize_text_field(wp_unslash($_POST['api_kelurahan_id'])) : '',
            
            // Foto
            'foto_profil'       => isset($_POST['foto_profil']) ? esc_url_raw($_POST['foto_profil']) : '',
            'foto_ktp'          => isset($_POST['foto_ktp']) ? esc_url_raw($_POST['foto_ktp']) : '',
            'foto_kartu_ojek'   => isset($_POST['foto_kartu_ojek']) ? esc_url_raw($_POST['foto_kartu_ojek']) : '',
            'foto_motor'        => isset($_POST['foto_motor']) ? esc_url_raw($_POST['foto_motor']) : '',
            
            'status_pendaftaran' => isset($_POST['status_pendaftaran']) ? sanitize_text_field($_POST['status_pendaftaran']) : 'menunggu',
            'status_kerja'       => isset($_POST['status_kerja']) ? sanitize_text_field($_POST['status_kerja']) : 'offline',
        ];

        // 4. Eksekusi Query
        if ($post_id > 0) {
            // --- UPDATE ---
            $data['updated_at'] = current_time('mysql');
            $updated = $wpdb->update($table_ojek, $data, ['id' => $post_id], null, ['%d']);
            
            if ($updated !== false) {
                $message = '<div class="notice notice-success is-dismissible"><p>Data Ojek berhasil diperbarui.</p></div>';
            } else {
                $message = '<div class="notice notice-error is-dismissible"><p>Gagal memperbarui data. DB Error: '. $wpdb->last_error .'</p></div>';
            }
        } else {
            // --- INSERT ---
            $data['id_user'] = $user_id; 
            $data['created_at'] = current_time('mysql');
            $inserted = $wpdb->insert($table_ojek, $data);
            
            if ($inserted) {
                $redirect_url = admin_url('admin.php?page=' . $current_page_slug . '&msg=added');
                echo "<script>window.location.href='$redirect_url';</script>";
                exit;
            } else {
                $message = '<div class="notice notice-error is-dismissible"><p>Gagal menyimpan data baru. DB Error: '. $wpdb->last_error .'</p></div>';
            }
        }
    }

    // --- LOGIC DELETE ---
    if ($view == 'delete' && $url_id > 0) {
        $wpdb->delete($table_ojek, ['id' => $url_id]);
        $redirect_url = admin_url('admin.php?page=' . $current_page_slug . '&msg=deleted');
        echo "<script>window.location.href='$redirect_url';</script>";
        exit;
    }

    // --- VIEW CONTROLLER ---
    if ($view == 'add' || $view == 'edit') {
        
        $row = null;
        $form_id_value = 0;
        
        // Inisialisasi nilai default
        $vals = [
            'nama_lengkap' => '', 'no_hp' => '', 'alamat_domisili' => '', 'merk_motor' => '', 'plat_nomor' => '',
            'api_provinsi_id' => '', 'api_kabupaten_id' => '', 'api_kecamatan_id' => '', 'api_kelurahan_id' => '',
            'foto_profil' => '', 'foto_ktp' => '', 'foto_kartu_ojek' => '', 'foto_motor' => '',
            'status_pendaftaran' => 'menunggu', 'status_kerja' => 'offline'
        ];

        // Helper API Method Detection
        $fetch_api_data = function($methods, $arg = null) {
            foreach ($methods as $method) {
                if (method_exists('DW_Address_API', $method)) {
                    return call_user_func(['DW_Address_API', $method], $arg);
                }
            }
            return [];
        };

        // Data Default Wilayah
        $list_provinsi = $fetch_api_data(['get_provinces']); 
        $list_kabupaten = [];
        $list_kecamatan = [];
        $list_kelurahan = [];

        // Jika Edit, ambil data
        if ($view == 'edit' && $url_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_ojek WHERE id = %d", $url_id));
            
            if ($row) {
                $form_id_value = $row->id;
                foreach ($vals as $key => $val) {
                    if (isset($row->$key)) $vals[$key] = $row->$key;
                }

                // Populate Wilayah
                if (!empty($vals['api_provinsi_id'])) {
                    $list_kabupaten = $fetch_api_data(['get_cities', 'get_regencies'], $vals['api_provinsi_id']);
                }
                if (!empty($vals['api_kabupaten_id'])) {
                    $list_kecamatan = $fetch_api_data(['get_subdistricts', 'get_districts'], $vals['api_kabupaten_id']);
                }
                if (!empty($vals['api_kecamatan_id'])) {
                    $list_kelurahan = $fetch_api_data(['get_villages'], $vals['api_kecamatan_id']);
                }
            }
        }

        wp_enqueue_media();
        ?>
        
        <style>
            /* UI Reset & Base Styles */
            .dw-wrap { max-width: 1200px; margin: 20px auto; }
            .dw-header { 
                display: flex; align-items: center; justify-content: space-between; 
                margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #dcdcde;
            }
            .dw-header h1 { margin: 0; font-size: 24px; color: #1d2327; font-weight: 600; }
            
            /* TABS STYLING */
            .dw-tabs-container { margin-bottom: 20px; }
            .dw-tabs-nav {
                display: flex;
                border-bottom: 1px solid #c3c4c7;
                background: #fff;
                margin: 0; padding: 0 10px;
                border-radius: 4px 4px 0 0;
            }
            .dw-tab-link {
                padding: 15px 20px;
                font-weight: 600;
                color: #50575e;
                cursor: pointer;
                border-bottom: 3px solid transparent;
                transition: all 0.2s ease;
                font-size: 14px;
                display: flex; align-items: center; gap: 8px;
            }
            .dw-tab-link:hover { color: #2271b1; background: #f6f7f7; }
            .dw-tab-link.active { color: #2271b1; border-bottom-color: #2271b1; }
            .dw-tab-link .dashicons { font-size: 18px; width: 18px; height: 18px; margin-right: 4px; }

            .dw-tab-body {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-top: 0;
                padding: 30px;
                border-radius: 0 0 4px 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            }
            .dw-tab-content { display: none; animation: dwFadeIn 0.3s ease; }
            .dw-tab-content.active { display: block; }
            
            @keyframes dwFadeIn {
                from { opacity: 0; transform: translateY(5px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Form Layout */
            .dw-layout-grid { display: grid; grid-template-columns: 3fr 1fr; gap: 20px; }
            .dw-form-row { margin-bottom: 20px; }
            .dw-form-row label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3338; font-size: 13px; }
            
            .dw-input-control {
                width: 100%; height: 40px; padding: 0 12px;
                border: 1px solid #8c8f94; border-radius: 4px;
                font-size: 14px; box-sizing: border-box;
                transition: border-color 0.15s ease;
            }
            .dw-input-control:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
            textarea.dw-input-control { height: auto; padding: 12px; }
            select.dw-input-control:disabled { background-color: #f6f7f7; color: #a7aaad; border-color: #dcdcde; }

            /* Right Sidebar Card */
            .dw-sidebar-card {
                background: #fff; border: 1px solid #c3c4c7;
                padding: 20px; border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                position: sticky; top: 40px;
            }
            .dw-card-header { 
                font-weight: 700; font-size: 14px; text-transform: uppercase; color: #1d2327; 
                padding-bottom: 10px; margin-bottom: 15px; border-bottom: 1px solid #f0f0f1;
                display: flex; align-items: center; justify-content: space-between;
            }

            /* Image Upload Grid */
            .dw-img-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .dw-img-wrapper {
                background: #f6f7f7; border: 2px dashed #c3c4c7; border-radius: 6px;
                padding: 10px; text-align: center; height: 180px; 
                display: flex; align-items: center; justify-content: center;
                flex-direction: column; overflow: hidden; position: relative;
                transition: border-color 0.2s;
            }
            .dw-img-wrapper:hover { border-color: #2271b1; background: #fff; }
            .dw-img-wrapper.has-image { border-style: solid; border-color: #dcdcde; background: #fff; padding: 0; }
            .dw-img-wrapper img { max-width: 100%; max-height: 100%; object-fit: contain; }
            .dw-img-placeholder { color: #a7aaad; display: flex; flex-direction: column; align-items: center; gap: 8px; }
            
            /* Buttons */
            .dw-btn-full { width: 100%; text-align: center; justify-content: center; margin-bottom: 10px; height: 42px !important; font-size: 14px !important; }
            .dw-btn-group { display: flex; gap: 10px; }
            .dw-btn-outline { 
                flex: 1; text-align: center; justify-content: center; display: flex; align-items: center;
                border: 1px solid #c3c4c7; background: #fff; color: #2271b1; height: 36px; border-radius: 4px; text-decoration: none; font-weight: 500; 
            }
            .dw-btn-outline:hover { border-color: #2271b1; background: #f0f6fc; }
            .dw-btn-danger { color: #d63638; border-color: #d63638; }
            .dw-btn-danger:hover { background: #d63638; color: #fff; border-color: #d63638; }

            @media (max-width: 960px) {
                .dw-layout-grid { grid-template-columns: 1fr; }
                .dw-tabs-container { order: 2; }
                .dw-sidebar-card { order: 1; margin-bottom: 20px; position: static; }
                .dw-img-grid { grid-template-columns: 1fr; }
            }
        </style>
        
        <div class="wrap dw-wrap">
            <div class="dw-header">
                <h1><?php echo ($view == 'edit') ? 'Edit Ojek' : 'Tambah Ojek Baru'; ?></h1>
                <a href="<?php echo admin_url('admin.php?page=' . $current_page_slug); ?>" class="page-title-action">
                    <span class="dashicons dashicons-arrow-left-alt2"></span> Kembali ke Daftar
                </a>
            </div>
            
            <?php echo $message; ?>

            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="dw_action" value="save_ojek">
                <input type="hidden" name="ojek_id" value="<?php echo esc_attr($form_id_value); ?>">
                <?php wp_nonce_field('dw_save_ojek', 'dw_ojek_nonce'); ?>

                <div class="dw-layout-grid">
                    <!-- KOLOM KIRI: TABS -->
                    <div class="left-column">
                        <div class="dw-tabs-container">
                            <!-- Tab Navigation -->
                            <div class="dw-tabs-nav">
                                <div class="dw-tab-link active" data-tab="tab-identity">
                                    <span class="dashicons dashicons-id"></span> Data Diri
                                </div>
                                <div class="dw-tab-link" data-tab="tab-vehicle">
                                    <span class="dashicons dashicons-car"></span> Kendaraan
                                </div>
                                <div class="dw-tab-link" data-tab="tab-docs">
                                    <span class="dashicons dashicons-format-image"></span> Dokumen
                                </div>
                            </div>

                            <!-- Tab Contents -->
                            <div class="dw-tab-body">
                                <!-- TAB 1: DATA DIRI -->
                                <div id="tab-identity" class="dw-tab-content active">
                                    <div class="dw-form-row">
                                        <label>Nama Lengkap</label>
                                        <input type="text" name="nama_lengkap" class="dw-input-control" value="<?php echo esc_attr($vals['nama_lengkap']); ?>" required>
                                    </div>
                                    <div class="dw-form-row">
                                        <label>Nomor WhatsApp / HP</label>
                                        <input type="text" name="no_hp" class="dw-input-control" value="<?php echo esc_attr($vals['no_hp']); ?>" required>
                                    </div>
                                    <div class="dw-form-row">
                                        <label>Alamat Domisili</label>
                                        <textarea name="alamat_domisili" rows="3" class="dw-input-control"><?php echo esc_textarea($vals['alamat_domisili']); ?></textarea>
                                    </div>
                                    
                                    <h3 style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Wilayah Domisili</h3>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <div class="dw-form-row">
                                            <label>Provinsi</label>
                                            <select name="api_provinsi_id" id="api_provinsi_id" class="dw-input-control" required>
                                                <option value="">Pilih Provinsi</option>
                                                <?php foreach ($list_provinsi as $prov): ?>
                                                    <option value="<?php echo esc_attr($prov['id']); ?>" <?php selected($vals['api_provinsi_id'], $prov['id']); ?>>
                                                        <?php echo esc_html($prov['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="dw-form-row">
                                            <label>Kabupaten/Kota</label>
                                            <?php $dis_kab = (empty($list_kabupaten) && empty($vals['api_kabupaten_id'])) ? 'disabled' : ''; ?>
                                            <select name="api_kabupaten_id" id="api_kabupaten_id" class="dw-input-control" required <?php echo $dis_kab; ?>>
                                                <option value="">Pilih Kabupaten</option>
                                                <?php if (!empty($list_kabupaten)): ?>
                                                    <?php foreach ($list_kabupaten as $item): ?>
                                                        <option value="<?php echo esc_attr($item['id']); ?>" <?php selected($vals['api_kabupaten_id'], $item['id']); ?>>
                                                            <?php echo esc_html($item['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php elseif(!empty($vals['api_kabupaten_id'])): ?>
                                                    <option value="<?php echo esc_attr($vals['api_kabupaten_id']); ?>" selected>ID: <?php echo esc_html($vals['api_kabupaten_id']); ?> (Current)</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="dw-form-row">
                                            <label>Kecamatan</label>
                                            <?php $dis_kec = (empty($list_kecamatan) && empty($vals['api_kecamatan_id'])) ? 'disabled' : ''; ?>
                                            <select name="api_kecamatan_id" id="api_kecamatan_id" class="dw-input-control" required <?php echo $dis_kec; ?>>
                                                <option value="">Pilih Kecamatan</option>
                                                <?php if (!empty($list_kecamatan)): ?>
                                                    <?php foreach ($list_kecamatan as $item): ?>
                                                        <option value="<?php echo esc_attr($item['id']); ?>" <?php selected($vals['api_kecamatan_id'], $item['id']); ?>>
                                                            <?php echo esc_html($item['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php elseif(!empty($vals['api_kecamatan_id'])): ?>
                                                    <option value="<?php echo esc_attr($vals['api_kecamatan_id']); ?>" selected>ID: <?php echo esc_html($vals['api_kecamatan_id']); ?> (Current)</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="dw-form-row">
                                            <label>Desa/Kelurahan</label>
                                            <?php $dis_kel = (empty($list_kelurahan) && empty($vals['api_kelurahan_id'])) ? 'disabled' : ''; ?>
                                            <select name="api_kelurahan_id" id="api_kelurahan_id" class="dw-input-control" required <?php echo $dis_kel; ?>>
                                                <option value="">Pilih Desa</option>
                                                <?php if (!empty($list_kelurahan)): ?>
                                                    <?php foreach ($list_kelurahan as $item): ?>
                                                        <option value="<?php echo esc_attr($item['id']); ?>" <?php selected($vals['api_kelurahan_id'], $item['id']); ?>>
                                                            <?php echo esc_html($item['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php elseif(!empty($vals['api_kelurahan_id'])): ?>
                                                    <option value="<?php echo esc_attr($vals['api_kelurahan_id']); ?>" selected>ID: <?php echo esc_html($vals['api_kelurahan_id']); ?> (Current)</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB 2: KENDARAAN -->
                                <div id="tab-vehicle" class="dw-tab-content">
                                    <div class="dw-form-row">
                                        <label>Merk & Tipe Motor</label>
                                        <input type="text" name="merk_motor" class="dw-input-control" value="<?php echo esc_attr($vals['merk_motor']); ?>" placeholder="Contoh: Honda Vario 125" required>
                                        <p class="description">Masukkan merk dan tipe motor yang digunakan.</p>
                                    </div>
                                    <div class="dw-form-row">
                                        <label>Plat Nomor</label>
                                        <input type="text" name="plat_nomor" class="dw-input-control" value="<?php echo esc_attr($vals['plat_nomor']); ?>" style="text-transform: uppercase;" required>
                                        <p class="description">Format plat nomor (Contoh: KB 1234 XY).</p>
                                    </div>
                                </div>

                                <!-- TAB 3: DOKUMEN -->
                                <div id="tab-docs" class="dw-tab-content">
                                    <div class="dw-img-grid">
                                        <?php 
                                        if (!function_exists('dw_render_img_input')) {
                                            function dw_render_img_input($field, $label, $val) {
                                                $has_img = !empty($val);
                                                ?>
                                                <div class="dw-form-row">
                                                    <label><?php echo $label; ?></label>
                                                    <div class="dw-img-wrapper <?php echo $has_img ? 'has-image' : ''; ?>" id="wrap_<?php echo $field; ?>">
                                                        <?php if($has_img): ?>
                                                            <img src="<?php echo esc_url($val); ?>">
                                                        <?php else: ?>
                                                            <div class="dw-img-placeholder">
                                                                <span class="dashicons dashicons-camera" style="font-size:32px; width:32px; height:32px;"></span>
                                                                <small>Upload Foto</small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <input type="hidden" name="<?php echo $field; ?>" id="<?php echo $field; ?>" value="<?php echo esc_attr($val); ?>">
                                                    <div style="display:flex; gap:5px;">
                                                        <button type="button" class="button dw-up-btn" data-target="<?php echo $field; ?>" style="flex:1;">Pilih Foto</button>
                                                        <button type="button" class="button dw-del-btn" data-target="<?php echo $field; ?>" style="color:#a00; <?php echo $has_img ? '' : 'display:none;'; ?>">Hapus</button>
                                                    </div>
                                                </div>
                                                <?php
                                            }
                                        }
                                        dw_render_img_input('foto_profil', 'Foto Profil', $vals['foto_profil']);
                                        dw_render_img_input('foto_ktp', 'Foto KTP', $vals['foto_ktp']);
                                        dw_render_img_input('foto_kartu_ojek', 'Kartu Ojek (Opsional)', $vals['foto_kartu_ojek']);
                                        dw_render_img_input('foto_motor', 'Foto Motor', $vals['foto_motor']);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KOLOM KANAN: STATUS & AKSI -->
                    <div class="right-column">
                        <div class="dw-sidebar-card">
                            <div class="dw-card-header">
                                <span>Status</span>
                                <span class="dashicons dashicons-flag"></span>
                            </div>
                            <div class="dw-form-row">
                                <label>Status Pendaftaran</label>
                                <select name="status_pendaftaran" class="dw-input-control">
                                    <option value="menunggu" <?php selected($vals['status_pendaftaran'], 'menunggu'); ?>>Menunggu Verifikasi</option>
                                    <option value="disetujui" <?php selected($vals['status_pendaftaran'], 'disetujui'); ?>>Disetujui</option>
                                    <option value="ditolak" <?php selected($vals['status_pendaftaran'], 'ditolak'); ?>>Ditolak</option>
                                </select>
                            </div>
                            <div class="dw-form-row">
                                <label>Status Kerja</label>
                                <select name="status_kerja" class="dw-input-control">
                                    <option value="offline" <?php selected($vals['status_kerja'], 'offline'); ?>>Offline</option>
                                    <option value="online" <?php selected($vals['status_kerja'], 'online'); ?>>Online</option>
                                    <option value="busy" <?php selected($vals['status_kerja'], 'busy'); ?>>Sibuk</option>
                                </select>
                            </div>
                            
                            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #f0f0f1;">
                            
                            <button type="submit" class="button button-primary button-large dw-btn-full">
                                <span class="dashicons dashicons-saved" style="margin-top:4px; margin-right:5px;"></span> Simpan Perubahan
                            </button>
                            
                            <div class="dw-btn-group">
                                <a href="<?php echo admin_url('admin.php?page=' . $current_page_slug); ?>" class="dw-btn-outline">Batal</a>
                                <?php if ($view == 'edit' && $url_id > 0) : ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=' . $current_page_slug . '&action=delete&id=' . $url_id), 'delete_ojek_' . $url_id); ?>" class="dw-btn-outline dw-btn-danger" onclick="return confirm('Hapus permanen data ini?');">Hapus</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // -- TABS LOGIC --
            $('.dw-tab-link').click(function() {
                var tabID = $(this).data('tab');
                
                // Remove active class from tabs
                $('.dw-tab-link').removeClass('active');
                $(this).addClass('active');
                
                // Hide all contents
                $('.dw-tab-content').removeClass('active');
                // Show target content
                $('#'+tabID).addClass('active');
            });

            // -- Media Uploader --
            $('.dw-up-btn').click(function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                var frame = wp.media({ title: 'Pilih Foto', multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var url = frame.state().get('selection').first().toJSON().url;
                    $('#'+target).val(url);
                    $('#wrap_'+target).html('<img src="'+url+'">').addClass('has-image');
                    $('.dw-del-btn[data-target="'+target+'"]').show();
                });
                frame.open();
            });

            $('.dw-del-btn').click(function(e){
                e.preventDefault();
                var target = $(this).data('target');
                $('#'+target).val('');
                $('#wrap_'+target).html('<div class="dw-img-placeholder"><span class="dashicons dashicons-camera" style="font-size:32px; width:32px; height:32px;"></span><small>Upload Foto</small></div>').removeClass('has-image');
                $(this).hide();
            });

            // -- Address API --
            function fetchRegion(action, parentId, targetSelect) {
                if (!parentId) {
                    $(targetSelect).html('<option value="">Pilih...</option>').prop('disabled', true);
                    return;
                }
                $(targetSelect).html('<option>Loading...</option>').prop('disabled', true);
                
                $.get(ajaxurl, { action: action, province_id: parentId, regency_id: parentId, district_id: parentId }, function(res) {
                    if (res.success) {
                        var opts = '<option value="">Pilih...</option>';
                        $.each(res.data, function(i, item) { opts += '<option value="' + item.id + '">' + item.name + '</option>'; });
                        $(targetSelect).html(opts).prop('disabled', false);
                    }
                });
            }

            $('#api_provinsi_id').change(function() {
                fetchRegion('dw_fetch_regencies', $(this).val(), '#api_kabupaten_id');
                $('#api_kecamatan_id, #api_kelurahan_id').html('<option value="">Pilih...</option>').prop('disabled', true);
            });
            $('#api_kabupaten_id').change(function() {
                fetchRegion('dw_fetch_districts', $(this).val(), '#api_kecamatan_id');
                $('#api_kelurahan_id').html('<option value="">Pilih...</option>').prop('disabled', true);
            });
            $('#api_kecamatan_id').change(function() {
                fetchRegion('dw_fetch_villages', $(this).val(), '#api_kelurahan_id');
            });
        });
        </script>
        <?php
    } else {
        // --- VIEW: TABLE LIST ---
        $ojek_table = new DW_Ojek_List_Table();
        $ojek_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Manajemen Ojek</h1>
            <a href="<?php echo admin_url('admin.php?page=' . $current_page_slug . '&action=add'); ?>" class="page-title-action">Tambah Baru</a>
            <hr class="wp-header-end">

            <?php if(isset($_GET['msg']) && $_GET['msg']=='added'): ?>
                <div class="notice notice-success is-dismissible"><p>Ojek berhasil ditambahkan.</p></div>
            <?php elseif(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
                <div class="notice notice-success is-dismissible"><p>Data berhasil dihapus.</p></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
                <?php
                $ojek_table->search_box('Cari Nama/Plat', 'search_id');
                $ojek_table->display();
                ?>
            </form>
        </div>
        <?php
    }
}
?>