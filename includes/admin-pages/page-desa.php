<?php
/**
 * File Name:   includes/admin-pages/page-desa.php
 * Description: CRUD Desa Wisata dengan Statistik Real-time & UI/UX Form Modern.
 */

if (!defined('ABSPATH')) exit;

// Pastikan file API Address dimuat jika belum
$address_api_path = dirname(dirname(__FILE__)) . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

function dw_desa_page_render() {
    global $wpdb;
    
    // Definisi nama tabel sesuai skema database activation.php
    $table_desa     = $wpdb->prefix . 'dw_desa';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_wisata   = $wpdb->prefix . 'dw_wisata'; 
    $table_users    = $wpdb->users;
    
    // Pastikan media uploader di-enqueue
    wp_enqueue_media();
    
    $message = '';
    $message_type = '';

    // --- LOGIC: SAVE / UPDATE / DELETE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_desa'])) {
        
        // Validasi Nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_action')) {
            echo '<div class="notice notice-error"><p>Keamanan tidak valid (Nonce Failed).</p></div>'; return;
        }

        $action = sanitize_text_field($_POST['action_desa']);

        // DELETE
        if ($action === 'delete' && !empty($_POST['desa_id'])) {
            $desa_id_to_delete = intval($_POST['desa_id']);

            // 1. Update pedagang: Set id_desa menjadi NULL
            $wpdb->update($table_pedagang, ['id_desa' => null], ['id_desa' => $desa_id_to_delete]); 
            
            // 2. Hapus wisata (Cascade Delete manual)
            if($wpdb->get_var("SHOW TABLES LIKE '$table_wisata'") == $table_wisata) {
                $wpdb->delete($table_wisata, ['id_desa' => $desa_id_to_delete]);
            }
            
            // 3. Hapus Desa
            $deleted = $wpdb->delete($table_desa, ['id' => $desa_id_to_delete]);

            if ($deleted) {
                $message = "Desa berhasil dihapus. Pedagang terkait telah dilepas, dan data wisata dihapus.";
                $message_type = "success";
            } else {
                $message = "Gagal menghapus desa.";
                $message_type = "error";
            }
        }
        
        // SAVE / UPDATE
        elseif ($action === 'save') {
            // Mapping Input Form
            $id_user_desa = intval($_POST['user_id']); 
            $nama_desa    = sanitize_text_field($_POST['nama_desa']);
            $deskripsi    = wp_kses_post($_POST['deskripsi']);
            $status       = sanitize_text_field($_POST['status']);
            $slug_desa    = sanitize_title($nama_desa);

            // Data Lokasi
            $api_provinsi_id  = sanitize_text_field($_POST['api_provinsi_id']);
            $api_kabupaten_id = sanitize_text_field($_POST['api_kabupaten_id']);
            $api_kecamatan_id = sanitize_text_field($_POST['api_kecamatan_id']);
            $api_kelurahan_id = sanitize_text_field($_POST['api_kelurahan_id']);

            $provinsi_name    = sanitize_text_field($_POST['provinsi_name']);
            $kabupaten_name   = sanitize_text_field($_POST['kabupaten_name']);
            $kecamatan_name   = sanitize_text_field($_POST['kecamatan_name']);
            $kelurahan_name   = sanitize_text_field($_POST['kelurahan_name']);
            $alamat_lengkap   = sanitize_textarea_field($_POST['alamat']);

            // Data Keuangan & Foto
            $nama_bank_desa            = sanitize_text_field($_POST['nama_bank']);
            $no_rekening_desa          = sanitize_text_field($_POST['no_rekening']);
            $atas_nama_rekening_desa   = sanitize_text_field($_POST['atas_nama']);
            $qris_image_url_desa       = esc_url_raw($_POST['qris_image']);
            $foto_desa                 = esc_url_raw($_POST['foto_desa']);
            
            // NOTE: Persentase komisi dihapus karena dikelola via Paket Transaksi
            // NOTE: total_pendapatan tidak diupdate manual di sini (hanya via transaksi)

            // --- VALIDASI ---
            $allow_save = true;

            // 1. Cek Kelurahan ID
            if (empty($api_kelurahan_id)) {
                $allow_save = false;
                $message = "Gagal Menyimpan: <strong>Lokasi Kelurahan Wajib Dipilih.</strong> Pastikan dropdown wilayah sudah lengkap.";
                $message_type = "error";
            } 
            
            // 2. Cek Duplikat Kelurahan
            if ($allow_save) {
                $check_sql = "SELECT id, nama_desa FROM $table_desa WHERE api_kelurahan_id = %s";
                $check_args = [$api_kelurahan_id];

                if (!empty($_POST['desa_id'])) {
                    $check_sql .= " AND id != %d";
                    $check_args[] = intval($_POST['desa_id']);
                }

                $duplicate_desa = $wpdb->get_row($wpdb->prepare($check_sql, $check_args));

                if ($duplicate_desa) {
                    $allow_save = false;
                    $message = "Gagal Menyimpan: Kelurahan <strong>" . esc_html($kelurahan_name) . "</strong> sudah digunakan oleh desa: <strong>" . esc_html($duplicate_desa->nama_desa) . "</strong>.";
                    $message_type = "error";
                }
            }

            // --- EKSEKUSI ---
            if ($allow_save) {
                $data = [
                    'id_user_desa'            => $id_user_desa,
                    'nama_desa'               => $nama_desa,
                    'slug_desa'               => $slug_desa,
                    'deskripsi'               => $deskripsi,
                    'foto'                    => $foto_desa,
                    'status'                  => $status,
                    'alamat_lengkap'          => $alamat_lengkap,
                    'provinsi'                => $provinsi_name,
                    'kabupaten'               => $kabupaten_name,
                    'kecamatan'               => $kecamatan_name,
                    'kelurahan'               => $kelurahan_name,
                    'api_provinsi_id'         => $api_provinsi_id,
                    'api_kabupaten_id'        => $api_kabupaten_id,
                    'api_kecamatan_id'        => $api_kecamatan_id,
                    'api_kelurahan_id'        => $api_kelurahan_id,
                    'nama_bank_desa'          => $nama_bank_desa,
                    'no_rekening_desa'        => $no_rekening_desa,
                    'atas_nama_rekening_desa' => $atas_nama_rekening_desa,
                    'qris_image_url_desa'     => $qris_image_url_desa,
                    'updated_at'              => current_time('mysql')
                ];

                if (!empty($_POST['desa_id'])) {
                    $desa_id = intval($_POST['desa_id']);
                    $updated = $wpdb->update($table_desa, $data, ['id' => $desa_id]);
                    if ($updated !== false) {
                        $message = "Data desa berhasil diperbarui.";
                        $message_type = "success";
                    } else {
                        $message = "Tidak ada perubahan.";
                        $message_type = "warning";
                    }
                } else {
                    $data['created_at'] = current_time('mysql');
                    // total_pendapatan default 0 dari database
                    $inserted = $wpdb->insert($table_desa, $data);
                    if ($inserted) {
                        $message = "Desa baru berhasil ditambahkan.";
                        $message_type = "success";
                    } else {
                        $message = "Gagal menambahkan desa. " . $wpdb->last_error;
                        $message_type = "error";
                    }
                }
            }
        }
    }

    // --- VIEW LOGIC ---
    $view = isset($_GET['view']) ? $_GET['view'] : 'list';
    $edit_data = null;

    if ($view === 'edit' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id = %d", $id));
    }

    // --- RENDER HEADER ---
    ?>
    <div class="wrap dw-wrapper">
        <div class="dw-header">
            <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
            <?php if ($view === 'list'): ?>
                <a href="<?php echo admin_url('admin.php?page=dw-desa&view=add'); ?>" class="dw-btn dw-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> Tambah Desa
                </a>
            <?php else: ?>
                <a href="<?php echo admin_url('admin.php?page=dw-desa'); ?>" class="dw-btn dw-btn-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span> Kembali
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="dw-notice dw-notice-<?php echo $message_type; ?>">
                <div class="dw-notice-content">
                    <span class="dashicons dashicons-<?php echo $message_type === 'success' ? 'yes' : 'warning'; ?>"></span>
                    <?php echo ($message); ?>
                </div>
                <button type="button" class="dw-notice-dismiss"><span class="dashicons dashicons-dismiss"></span></button>
            </div>
        <?php endif; ?>

        <?php 
        // --- FORM VIEW (ADD / EDIT) ---
        if ($view === 'add' || ($view === 'edit' && $edit_data)): 
            $is_edit = ($view === 'edit');
            $action_url = admin_url('admin.php?page=dw-desa&view=' . $view . ($is_edit ? '&id=' . $edit_data->id : ''));
            
            // Logic Data Preservation (agar tidak hilang saat error)
            $use_post = ($message_type === 'error' && !empty($_POST));
            
            // Data extraction helpers
            $v_nama     = $use_post ? sanitize_text_field($_POST['nama_desa']) : ($is_edit ? $edit_data->nama_desa : '');
            $v_desc     = $use_post ? wp_kses_post($_POST['deskripsi']) : ($is_edit ? $edit_data->deskripsi : '');
            $v_status   = $use_post ? sanitize_text_field($_POST['status']) : ($is_edit ? $edit_data->status : '');
            $v_user     = $use_post ? intval($_POST['user_id']) : ($is_edit ? $edit_data->id_user_desa : '');
            $v_foto     = $use_post ? esc_url_raw($_POST['foto_desa']) : ($is_edit ? $edit_data->foto : '');
            
            // Location Data
            $v_api_prov = $use_post ? $_POST['api_provinsi_id'] : ($is_edit ? $edit_data->api_provinsi_id : '');
            $v_api_kab  = $use_post ? $_POST['api_kabupaten_id'] : ($is_edit ? $edit_data->api_kabupaten_id : '');
            $v_api_kec  = $use_post ? $_POST['api_kecamatan_id'] : ($is_edit ? $edit_data->api_kecamatan_id : '');
            $v_api_kel  = $use_post ? $_POST['api_kelurahan_id'] : ($is_edit ? $edit_data->api_kelurahan_id : '');
            
            $v_nm_prov  = $use_post ? $_POST['provinsi_name'] : ($is_edit ? $edit_data->provinsi : '');
            $v_nm_kab   = $use_post ? $_POST['kabupaten_name'] : ($is_edit ? $edit_data->kabupaten : '');
            $v_nm_kec   = $use_post ? $_POST['kecamatan_name'] : ($is_edit ? $edit_data->kecamatan : '');
            $v_nm_kel   = $use_post ? $_POST['kelurahan_name'] : ($is_edit ? $edit_data->kelurahan : '');
            $v_alamat   = $use_post ? $_POST['alamat'] : ($is_edit ? $edit_data->alamat_lengkap : '');

            // Finance Data
            $v_bank     = $use_post ? $_POST['nama_bank'] : ($is_edit ? $edit_data->nama_bank_desa : '');
            $v_rek      = $use_post ? $_POST['no_rekening'] : ($is_edit ? $edit_data->no_rekening_desa : '');
            $v_an       = $use_post ? $_POST['atas_nama'] : ($is_edit ? $edit_data->atas_nama_rekening_desa : '');
            $v_qris     = $use_post ? $_POST['qris_image'] : ($is_edit ? $edit_data->qris_image_url_desa : '');
        ?>
            
            <form method="post" action="<?php echo $action_url; ?>" id="dw-desa-form">
                <?php wp_nonce_field('dw_desa_action'); ?>
                <input type="hidden" name="action_desa" value="save">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="desa_id" value="<?php echo esc_attr($edit_data->id); ?>">
                <?php endif; ?>

                <div class="dw-layout-grid">
                    <!-- MAIN COLUMN -->
                    <div class="dw-main-col">
                        <div class="dw-card">
                            <div class="dw-tabs-header">
                                <button type="button" class="dw-tab-item active" data-tab="tab-general">Informasi Umum</button>
                                <button type="button" class="dw-tab-item" data-tab="tab-location">Lokasi & Wilayah</button>
                                <button type="button" class="dw-tab-item" data-tab="tab-finance">Keuangan & QRIS</button>
                            </div>

                            <div class="dw-card-body">
                                <!-- TAB: UMUM -->
                                <div id="tab-general" class="dw-tab-content active">
                                    <div class="dw-form-group">
                                        <label class="dw-label">Nama Desa Wisata <span class="required">*</span></label>
                                        <input type="text" name="nama_desa" class="dw-form-control dw-input-lg" value="<?php echo esc_attr($v_nama); ?>" placeholder="Contoh: Desa Wisata Penglipuran" required>
                                    </div>
                                    <div class="dw-form-group">
                                        <label class="dw-label">Deskripsi & Profil Desa</label>
                                        <?php wp_editor($v_desc, 'deskripsi', ['textarea_rows' => 12, 'media_buttons' => true, 'editor_class' => 'dw-editor']); ?>
                                    </div>
                                </div>

                                <!-- TAB: LOKASI -->
                                <div id="tab-location" class="dw-tab-content" style="display:none;">
                                    <div class="dw-alert dw-alert-info">
                                        <span class="dashicons dashicons-info"></span> Data wilayah diambil otomatis dari server API. Pastikan semua dropdown terpilih.
                                    </div>

                                    <!-- Hidden Inputs -->
                                    <input type="hidden" name="provinsi_name" id="provinsi_name" value="<?php echo esc_attr($v_nm_prov); ?>">
                                    <input type="hidden" name="kabupaten_name" id="kabupaten_name" value="<?php echo esc_attr($v_nm_kab); ?>">
                                    <input type="hidden" name="kecamatan_name" id="kecamatan_name" value="<?php echo esc_attr($v_nm_kec); ?>">
                                    <input type="hidden" name="kelurahan_name" id="kelurahan_name" value="<?php echo esc_attr($v_nm_kel); ?>">

                                    <!-- Init Data -->
                                    <div id="dw-location-data" 
                                        data-prov="<?php echo esc_attr($v_api_prov); ?>"
                                        data-kab="<?php echo esc_attr($v_api_kab); ?>"
                                        data-kec="<?php echo esc_attr($v_api_kec); ?>"
                                        data-kel="<?php echo esc_attr($v_api_kel); ?>"
                                    ></div>

                                    <div class="dw-grid-2">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Provinsi <span class="required">*</span></label>
                                            <select name="api_provinsi_id" id="api_provinsi_id" class="dw-form-control" required>
                                                <option value="">Memuat data...</option>
                                            </select>
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Kabupaten / Kota <span class="required">*</span></label>
                                            <select name="api_kabupaten_id" id="api_kabupaten_id" class="dw-form-control" disabled required>
                                                <option value="">Pilih Provinsi dulu...</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="dw-grid-2">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Kecamatan <span class="required">*</span></label>
                                            <select name="api_kecamatan_id" id="api_kecamatan_id" class="dw-form-control" disabled required>
                                                <option value="">Pilih Kabupaten dulu...</option>
                                            </select>
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Kelurahan / Desa <span class="required">*</span></label>
                                            <select name="api_kelurahan_id" id="api_kelurahan_id" class="dw-form-control" disabled required>
                                                <option value="">Pilih Kecamatan dulu...</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="dw-form-group">
                                        <label class="dw-label">Alamat Lengkap (Jalan, RT/RW)</label>
                                        <textarea name="alamat" class="dw-form-control" rows="3" placeholder="Contoh: Jl. Mawar No. 12, RT 01/RW 02"><?php echo esc_textarea($v_alamat); ?></textarea>
                                    </div>
                                </div>

                                <!-- TAB: KEUANGAN -->
                                <div id="tab-finance" class="dw-tab-content" style="display:none;">
                                    <h3 class="dw-section-title">Informasi Rekening Bank</h3>
                                    <div class="dw-grid-3">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Nama Bank</label>
                                            <input type="text" name="nama_bank" class="dw-form-control" placeholder="Contoh: BCA, BRI" value="<?php echo esc_attr($v_bank); ?>">
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Nomor Rekening</label>
                                            <input type="text" name="no_rekening" class="dw-form-control" placeholder="1234xxxxxx" value="<?php echo esc_attr($v_rek); ?>">
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Atas Nama</label>
                                            <input type="text" name="atas_nama" class="dw-form-control" placeholder="Nama Pemilik Rekening" value="<?php echo esc_attr($v_an); ?>">
                                        </div>
                                    </div>

                                    <div class="dw-separator"></div>
                                    
                                    <h3 class="dw-section-title">Pembayaran Digital (QRIS)</h3>
                                    <div class="dw-form-group">
                                        <div class="dw-media-box">
                                            <div class="dw-media-preview <?php echo $v_qris ? 'active' : ''; ?>">
                                                <img src="<?php echo esc_url($v_qris); ?>" id="preview_qris">
                                                <?php if(!$v_qris): ?>
                                                    <div class="dw-media-placeholder">
                                                        <span class="dashicons dashicons-qr"></span>
                                                        <p>Belum ada QRIS diupload</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="dw-media-controls">
                                                <input type="hidden" name="qris_image" id="input_qris" value="<?php echo esc_url($v_qris); ?>">
                                                <button type="button" class="button btn_upload_media" data-target="#input_qris" data-preview="#preview_qris">Pilih Gambar QRIS</button>
                                                <button type="button" class="button dw-btn-danger btn_remove_media" data-target="#input_qris" data-preview="#preview_qris" style="<?php echo $v_qris ? '' : 'display:none;'; ?>">Hapus</button>
                                            </div>
                                            <p class="description">Upload gambar QRIS yang valid agar pembeli dapat melakukan scan pembayaran.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SIDEBAR COLUMN -->
                    <div class="dw-sidebar-col">
                        <!-- Box: Publish -->
                        <div class="dw-card dw-sidebar-card">
                            <div class="dw-card-header">
                                <h3>Terbitkan</h3>
                            </div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <label class="dw-label">Status</label>
                                    <select name="status" class="dw-form-control">
                                        <option value="aktif" <?php selected($v_status, 'aktif'); ?>>Aktif</option>
                                        <option value="pending" <?php selected($v_status, 'pending'); ?>>Pending</option>
                                    </select>
                                </div>
                                <div class="dw-form-actions">
                                    <button type="submit" id="btn-submit-desa" class="dw-btn dw-btn-primary dw-btn-block">
                                        <span class="dashicons dashicons-saved"></span> <?php echo $is_edit ? 'Simpan Perubahan' : 'Terbitkan Desa'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Box: Pengelola -->
                        <div class="dw-card dw-sidebar-card">
                            <div class="dw-card-header">
                                <h3>Pengaturan Pengelola</h3>
                            </div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <label class="dw-label">Admin Desa</label>
                                    <?php
                                    $users = get_users(['role__in' => ['subscriber', 'editor', 'administrator']]);
                                    echo '<select name="user_id" class="dw-form-control dw-select2">';
                                    echo '<option value="">-- Pilih User --</option>';
                                    foreach ($users as $user) {
                                        $selected = ($v_user == $user->ID) ? 'selected' : '';
                                        echo '<option value="' . $user->ID . '" ' . $selected . '>' . esc_html($user->display_name) . ' (' . $user->user_email . ')</option>';
                                    }
                                    echo '</select>';
                                    ?>
                                    <p class="description">User ini akan memiliki akses dashboard desa.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Box: Foto Utama -->
                        <div class="dw-card dw-sidebar-card">
                            <div class="dw-card-header">
                                <h3>Foto Utama Desa</h3>
                            </div>
                            <div class="dw-card-body">
                                <div class="dw-media-box">
                                    <div class="dw-media-preview thumbnail <?php echo $v_foto ? 'active' : ''; ?>">
                                        <img src="<?php echo esc_url($v_foto); ?>" id="preview_foto_desa">
                                        <?php if(!$v_foto): ?>
                                            <div class="dw-media-placeholder">
                                                <span class="dashicons dashicons-format-image"></span>
                                                <p>Belum ada foto</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dw-media-controls-sm">
                                        <input type="hidden" name="foto_desa" id="input_foto_desa" value="<?php echo esc_url($v_foto); ?>">
                                        <button type="button" class="button btn_upload_media" data-target="#input_foto_desa" data-preview="#preview_foto_desa">Pilih Foto</button>
                                        <button type="button" class="button-link dw-text-danger btn_remove_media" data-target="#input_foto_desa" data-preview="#preview_foto_desa" style="<?php echo $v_foto ? '' : 'display:none;'; ?>">Hapus</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- MODERN CSS -->
            <style>
                :root {
                    --dw-primary: #2271b1;
                    --dw-primary-hover: #135e96;
                    --dw-bg: #f0f0f1;
                    --dw-border: #dcdcde;
                    --dw-text: #3c434a;
                    --dw-text-light: #646970;
                    --dw-radius: 4px;
                    --dw-shadow: 0 1px 2px rgba(0,0,0,0.05);
                }

                /* Layout Grid */
                .dw-wrapper { margin: 20px 20px 0 0; }
                .dw-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                .dw-layout-grid { display: grid; grid-template-columns: 1fr 280px; gap: 20px; align-items: start; }
                @media(max-width: 850px) { .dw-layout-grid { grid-template-columns: 1fr; } }

                /* Cards */
                .dw-card { background: #fff; border: 1px solid var(--dw-border); border-radius: var(--dw-radius); box-shadow: var(--dw-shadow); margin-bottom: 20px; overflow: hidden; }
                .dw-card-header { padding: 12px 15px; border-bottom: 1px solid var(--dw-border); background: #fbfbfb; }
                .dw-card-header h3 { margin: 0; font-size: 14px; font-weight: 600; color: var(--dw-text); }
                .dw-card-body { padding: 20px; }
                .dw-sidebar-card .dw-card-body { padding: 15px; }

                /* Tabs */
                .dw-tabs-header { display: flex; border-bottom: 1px solid var(--dw-border); background: #f6f7f7; }
                .dw-tab-item { background: none; border: none; padding: 15px 20px; font-size: 14px; color: var(--dw-text-light); font-weight: 500; cursor: pointer; border-right: 1px solid var(--dw-border); transition: all 0.2s; outline: none; }
                .dw-tab-item:hover { background: #fff; color: var(--dw-primary); }
                .dw-tab-item.active { background: #fff; color: var(--dw-primary); border-bottom: 2px solid transparent; box-shadow: inset 0 3px 0 var(--dw-primary); font-weight: 600; }

                /* Forms */
                .dw-form-group { margin-bottom: 20px; }
                .dw-form-group:last-child { margin-bottom: 0; }
                .dw-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dw-text); font-size: 13px; }
                .dw-form-control { width: 100%; height: 40px; padding: 0 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; color: #2c3338; box-sizing: border-box; transition: border-color 0.15s ease-in-out; }
                .dw-form-control:focus { border-color: var(--dw-primary); box-shadow: 0 0 0 1px var(--dw-primary); outline: none; }
                textarea.dw-form-control { height: auto; padding: 10px; line-height: 1.5; }
                .dw-input-lg { height: 45px; font-size: 16px; font-weight: 500; }
                .required { color: #d63638; }
                .description { font-size: 12px; color: var(--dw-text-light); margin-top: 5px; font-style: italic; }

                /* Grids within Forms */
                .dw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .dw-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
                @media(max-width: 600px) { .dw-grid-2, .dw-grid-3 { grid-template-columns: 1fr; } }

                /* Input Groups */
                .dw-input-group { display: flex; align-items: center; }
                .dw-input-group .dw-form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; }
                .dw-input-group-text { background: #f0f0f1; border: 1px solid #8c8f94; border-left: 0; padding: 0 12px; height: 40px; line-height: 38px; border-top-right-radius: 4px; border-bottom-right-radius: 4px; color: var(--dw-text-light); font-weight: 500; }

                /* Buttons & Actions */
                .dw-btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 4px; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.2s; border: none; font-size: 13px; line-height: 1.4; gap: 5px; }
                .dw-btn-primary { background: var(--dw-primary); color: #fff; }
                .dw-btn-primary:hover { background: var(--dw-primary-hover); color: #fff; }
                .dw-btn-secondary { background: #fff; border: 1px solid var(--dw-primary); color: var(--dw-primary); }
                .dw-btn-secondary:hover { background: #f0f6fc; }
                .dw-btn-block { width: 100%; display: flex; margin-top: 10px; }
                .dw-text-danger { color: #d63638!important; }
                .dw-text-danger:hover { color: #a00!important; text-decoration: underline; }

                /* Media Uploader Box */
                .dw-media-box { border: 2px dashed #c3c4c7; padding: 15px; border-radius: 4px; text-align: center; background: #fafafa; }
                .dw-media-preview { position: relative; margin-bottom: 10px; display: none; }
                .dw-media-preview.active { display: block; }
                .dw-media-preview img { max-width: 100%; height: auto; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .dw-media-preview.thumbnail img { max-height: 150px; }
                .dw-media-placeholder { padding: 20px; color: #a7aaad; }
                .dw-media-placeholder .dashicons { font-size: 32px; width: 32px; height: 32px; margin-bottom: 5px; }
                .dw-media-placeholder p { margin: 0; font-size: 13px; }
                .dw-media-controls { display: flex; gap: 10px; justify-content: center; }
                .dw-media-controls-sm { display: flex; flex-direction: column; gap: 5px; align-items: center; }

                /* Separator & Misc */
                .dw-separator { border-top: 1px solid #eee; margin: 25px 0; }
                .dw-section-title { font-size: 15px; font-weight: 600; color: #1d2327; margin: 0 0 15px 0; padding-left: 10px; border-left: 3px solid var(--dw-primary); }
                .dw-alert { padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px; }
                .dw-alert-info { background: #e5f5fa; border: 1px solid #b8e6f5; color: #006799; }

                /* Notice Styling Override */
                .dw-notice { background: #fff; border-left: 4px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.1); padding: 12px 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 2px; }
                .dw-notice-success { border-left-color: #46b450; }
                .dw-notice-error { border-left-color: #d63638; }
                .dw-notice-content { display: flex; align-items: center; gap: 8px; font-weight: 500; color: #1d2327; }
                .dw-notice-dismiss { background: none; border: none; cursor: pointer; color: #787c82; }
                .dw-notice-dismiss:hover { color: #d63638; }
            </style>

            <script>
            jQuery(document).ready(function($) {
                // TAB SWITCHING
                $('.dw-tab-item').on('click', function() {
                    var target = $(this).data('tab');
                    $('.dw-tab-item').removeClass('active');
                    $('.dw-tab-content').hide();
                    
                    $(this).addClass('active');
                    $('#' + target).fadeIn(200);
                });

                // Dismiss Notice
                $('.dw-notice-dismiss').on('click', function() { $(this).closest('.dw-notice').fadeOut(); });

                // Form Submit: Enable selects
                $('#dw-desa-form').on('submit', function() {
                    $('#api_kabupaten_id, #api_kecamatan_id, #api_kelurahan_id').prop('disabled', false);
                    var btn = $('#btn-submit-desa');
                    btn.addClass('disabled').html('<span class="dashicons dashicons-update spin"></span> Menyimpan...');
                });

                // MEDIA UPLOADER LOGIC
                function setupMediaUploader(btnClass) {
                    $(document).on('click', btnClass, function(e) {
                        e.preventDefault();
                        var btn = $(this);
                        var targetInput = btn.data('target');
                        var targetPreview = btn.data('preview');
                        var container = $(targetPreview).closest('.dw-media-box');
                        var removeBtn = container.find('.btn_remove_media');
                        var previewDiv = container.find('.dw-media-preview');
                        var placeholder = container.find('.dw-media-placeholder');

                        var frame = wp.media({ title: 'Pilih Gambar', multiple: false, library: { type: 'image' } });
                        frame.on('select', function(){
                            var selection = frame.state().get('selection').first().toJSON();
                            $(targetInput).val(selection.url);
                            $(targetPreview).attr('src', selection.url);
                            previewDiv.addClass('active');
                            placeholder.hide();
                            removeBtn.show();
                        });
                        frame.open();
                    });
                }

                setupMediaUploader('.btn_upload_media');

                $(document).on('click', '.btn_remove_media', function(e){
                    e.preventDefault();
                    var btn = $(this);
                    var targetInput = btn.data('target');
                    var targetPreview = btn.data('preview');
                    var container = $(targetPreview).closest('.dw-media-box');
                    var previewDiv = container.find('.dw-media-preview');
                    var placeholder = container.find('.dw-media-placeholder');
                    
                    $(targetInput).val('');
                    $(targetPreview).attr('src', '');
                    previewDiv.removeClass('active');
                    placeholder.show();
                    btn.hide();
                });

                // ADDRESS API LOGIC
                var locData = $('#dw-location-data');
                var initVals = {
                    prov: locData.data('prov'), kab: locData.data('kab'),
                    kec: locData.data('kec'), kel: locData.data('kel')
                };

                function loadArea(action, parentId, target, selected, callback) {
                    var $target = $(target);
                    $target.html('<option>Memuat...</option>').prop('disabled', true);
                    
                    $.get(ajaxurl, { action: action, province_id: parentId, regency_id: parentId, district_id: parentId }, function(res) {
                        if(res.success) {
                            var opts = '<option value="">-- Pilih --</option>';
                            $.each(res.data, function(i, v) {
                                opts += '<option value="'+v.id+'" '+(v.id==selected?'selected':'')+'>'+v.name+'</option>';
                            });
                            $target.html(opts).prop('disabled', false);
                            if(callback) callback();
                        }
                    });
                }

                function updateName(el, target) { $(target).val($(el).find('option:selected').text()); }

                // Chain Load Logic
                loadArea('dw_fetch_provinces', null, '#api_provinsi_id', initVals.prov, function(){
                    if(initVals.prov) $('#api_provinsi_id').trigger('change');
                });

                $('#api_provinsi_id').change(function(){
                    updateName(this, '#provinsi_name');
                    var id = $(this).val();
                    $('#api_kabupaten_id, #api_kecamatan_id, #api_kelurahan_id').empty().append('<option value="">Pilih...</option>').prop('disabled', true);
                    
                    if(id) {
                        var nextVal = (id == initVals.prov) ? initVals.kab : '';
                        loadArea('dw_fetch_regencies', id, '#api_kabupaten_id', nextVal, function(){ 
                            if(nextVal) $('#api_kabupaten_id').trigger('change'); 
                        });
                    }
                });

                $('#api_kabupaten_id').change(function(){
                    updateName(this, '#kabupaten_name');
                    var id = $(this).val();
                    $('#api_kecamatan_id, #api_kelurahan_id').empty().append('<option value="">Pilih...</option>').prop('disabled', true);
                    
                    if(id) {
                        var nextVal = (id == initVals.kab) ? initVals.kec : '';
                        loadArea('dw_fetch_districts', id, '#api_kecamatan_id', nextVal, function(){ 
                            if(nextVal) $('#api_kecamatan_id').trigger('change'); 
                        });
                    }
                });

                $('#api_kecamatan_id').change(function(){
                    updateName(this, '#kecamatan_name');
                    var id = $(this).val();
                    $('#api_kelurahan_id').empty().append('<option value="">Pilih...</option>').prop('disabled', true);
                    
                    if(id) {
                        var nextVal = (id == initVals.kec) ? initVals.kel : '';
                        loadArea('dw_fetch_villages', id, '#api_kelurahan_id', nextVal);
                    }
                });

                $('#api_kelurahan_id').change(function(){ updateName(this, '#kelurahan_name'); });
            });
            </script>

        <?php 
        // --- LIST VIEW (MODERN TABLE) ---
        else: 
            // Pagination & Search
            $pagenum    = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
            $limit      = 10;
            $offset     = ($pagenum - 1) * $limit;
            $search     = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

            $where_sql = "WHERE 1=1";
            if ($search) {
                $where_sql .= $wpdb->prepare(" AND (d.nama_desa LIKE %s OR d.kabupaten LIKE %s)", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
            }

            // QUERY UTAMA DENGAN STATISTIK
            // Update: Menambahkan total_pendapatan jika ada kolomnya, asumsikan ada default 0
            $sql = "SELECT d.*, u.display_name as admin_name,
                    (SELECT COUNT(id) FROM $table_pedagang WHERE id_desa = d.id) as count_pedagang,
                    (SELECT COUNT(id) FROM $table_wisata WHERE id_desa = d.id) as count_wisata
                    FROM $table_desa d 
                    LEFT JOIN $table_users u ON d.id_user_desa = u.ID 
                    $where_sql 
                    ORDER BY d.created_at DESC 
                    LIMIT %d OFFSET %d";
            
            $results     = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
            $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa d $where_sql");
            $total_pages = ceil($total_items / $limit);

            // Statistik Global
            $total_all_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
            $total_all_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM $table_pedagang WHERE id_desa IS NOT NULL");
            $total_all_wisata = $wpdb->get_var("SELECT COUNT(id) FROM $table_wisata");
            // Hitung Total Pendapatan Seluruh Desa (Asumsi field total_pendapatan ada)
            $total_all_revenue = $wpdb->get_var("SELECT SUM(total_pendapatan) FROM $table_desa");
        ?>
            
            <!-- Modern List UI -->
            <div class="dw-list-container">
                
                <!-- Dashboard Mini (Summary) -->
                <div class="dw-stats-row">
                    <div class="dw-stat-card">
                        <div class="dw-stat-icon"><span class="dashicons dashicons-admin-home"></span></div>
                        <div class="dw-stat-info">
                            <h3><?php echo number_format($total_all_desa); ?></h3>
                            <span>Total Desa</span>
                        </div>
                    </div>
                    <div class="dw-stat-card">
                        <div class="dw-stat-icon icon-blue"><span class="dashicons dashicons-store"></span></div>
                        <div class="dw-stat-info">
                            <h3><?php echo number_format($total_all_pedagang); ?></h3>
                            <span>Total Pedagang Terdaftar</span>
                        </div>
                    </div>
                    <div class="dw-stat-card">
                        <div class="dw-stat-icon icon-green"><span class="dashicons dashicons-palmtree"></span></div>
                        <div class="dw-stat-info">
                            <h3><?php echo number_format($total_all_wisata); ?></h3>
                            <span>Total Objek Wisata</span>
                        </div>
                    </div>
                    <div class="dw-stat-card">
                        <div class="dw-stat-icon icon-purple"><span class="dashicons dashicons-money-alt"></span></div>
                        <div class="dw-stat-info">
                            <h3>Rp <?php echo number_format($total_all_revenue ?? 0, 0, ',', '.'); ?></h3>
                            <span>Total Pendapatan Desa</span>
                        </div>
                    </div>
                </div>

                <div class="dw-list-header">
                    <form method="get">
                        <input type="hidden" name="page" value="dw-desa">
                        <div class="dw-search-box">
                            <span class="dashicons dashicons-search"></span>
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari desa atau lokasi...">
                            <button type="submit" class="button">Cari</button>
                        </div>
                    </form>
                    <div class="dw-list-meta">
                        Total Hasil: <strong><?php echo $total_items; ?></strong> Desa
                    </div>
                </div>

                <div class="dw-table-card">
                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                            <tr>
                                <th width="60">ID</th>
                                <th class="col-info">Informasi Desa</th>
                                <th class="col-loc">Lokasi</th>
                                <th class="col-stats" width="150">Statistik</th>
                                <th width="120">Pendapatan</th>
                                <th width="100">Status</th>
                                <th width="120" style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($results): foreach ($results as $row): ?>
                            <tr>
                                <td style="color:#888;">#<?php echo $row->id; ?></td>
                                <td>
                                    <div class="dw-item-title">
                                        <a href="<?php echo admin_url('admin.php?page=dw-desa&view=edit&id='.$row->id); ?>">
                                            <?php echo esc_html($row->nama_desa); ?>
                                        </a>
                                    </div>
                                    <div class="dw-item-meta">
                                        <span class="dashicons dashicons-admin-users"></span> <?php echo $row->admin_name ? esc_html($row->admin_name) : '<i style="color:#d63638">Belum ada admin</i>'; ?>
                                        &bull; <code style="font-size:11px;"><?php echo esc_html($row->slug_desa); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <?php if($row->kabupaten): ?>
                                        <div class="dw-loc-main"><span class="dashicons dashicons-location-alt"></span> <?php echo esc_html($row->kabupaten); ?></div>
                                        <div class="dw-loc-sub"><?php echo esc_html($row->kecamatan . ', ' . $row->kelurahan); ?></div>
                                    <?php else: ?>
                                        <span style="color:#999;">- Belum set lokasi -</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dw-stat-pill" title="Jumlah Pedagang">
                                        <span class="dashicons dashicons-store"></span> <?php echo $row->count_pedagang; ?>
                                    </div>
                                    <div class="dw-stat-pill" title="Jumlah Wisata">
                                        <span class="dashicons dashicons-palmtree"></span> <?php echo $row->count_wisata; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:600; color:#46b450;">
                                        Rp <?php echo number_format($row->total_pendapatan ?? 0, 0, ',', '.'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = ($row->status === 'aktif') ? 'dw-badge-success' : 'dw-badge-warning';
                                    echo '<span class="dw-badge ' . $status_class . '">' . ucfirst($row->status) . '</span>';
                                    ?>
                                </td>
                                <td style="text-align:right;">
                                    <div class="dw-actions">
                                        <a href="<?php echo admin_url('admin.php?page=dw-desa&view=edit&id='.$row->id); ?>" class="dw-action-btn edit" title="Edit"><span class="dashicons dashicons-edit"></span></a>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('Hapus desa ini? Pedagang & Wisata akan dilepas relasinya.');">
                                            <?php wp_nonce_field('dw_desa_action'); ?>
                                            <input type="hidden" name="action_desa" value="delete">
                                            <input type="hidden" name="desa_id" value="<?php echo $row->id; ?>">
                                            <button type="submit" class="dw-action-btn delete" title="Hapus"><span class="dashicons dashicons-trash"></span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 40px;">
                                    <span class="dashicons dashicons-info" style="font-size:40px; color:#ccc; display:block; margin:0 auto 10px;"></span>
                                    <h3 style="margin:0; color:#666;">Data tidak ditemukan</h3>
                                    <p>Belum ada desa wisata yang ditambahkan.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="dw-pagination">
                        <?php echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'total' => $total_pages,
                            'current' => $pagenum,
                            'prev_text' => '&lsaquo;',
                            'next_text' => '&rsaquo;'
                        ]); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- List specific styles included in the common block above or keep minimal here if needed -->
            <style>
                .dw-list-container { max-width: 100%; }
                /* Stats Dashboard */
                .dw-stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
                .dw-stat-card { background: #fff; padding: 20px; border-radius: 4px; border: 1px solid #dcdcde; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
                .dw-stat-icon { width: 50px; height: 50px; background: #f0f0f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #50575e; }
                .dw-stat-icon .dashicons { font-size: 24px; width: 24px; height: 24px; }
                .dw-stat-icon.icon-blue { background: #e5f5fa; color: #0073aa; }
                .dw-stat-icon.icon-green { background: #e7f7ed; color: #008a20; }
                .dw-stat-icon.icon-purple { background: #f0f0f1; color: #8224e3; }
                .dw-stat-info h3 { margin: 0 0 5px; font-size: 24px; color: #1d2327; }
                .dw-stat-info span { font-size: 13px; color: #646970; font-weight: 500; }

                .dw-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
                .dw-search-box { position: relative; }
                .dw-search-box .dashicons { position: absolute; left: 8px; top: 8px; color: #aaa; }
                .dw-search-box input { padding-left: 30px; border-radius: 4px; border: 1px solid #ccc; width: 250px; height: 36px; }
                
                .dw-table-card { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); overflow: hidden; }
                .wp-list-table { border: none; box-shadow: none; }
                .wp-list-table th { font-weight: 600; color: #1d2327; padding: 15px 10px; }
                .wp-list-table td { padding: 12px 10px; vertical-align: middle; }
                
                .dw-item-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
                .dw-item-title a { text-decoration: none; color: #2271b1; }
                .dw-item-title a:hover { color: #135e96; }
                .dw-item-meta { font-size: 12px; color: #646970; display: flex; align-items: center; gap: 5px; }
                .dw-item-meta .dashicons { font-size: 14px; width: 14px; height: 14px; }
                
                .dw-loc-main { font-weight: 500; color: #3c434a; }
                .dw-loc-sub { font-size: 11px; color: #8c8f94; }

                .dw-stat-pill { background: #f0f0f1; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; color: #50575e; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 3px; }
                .dw-stat-pill .dashicons { font-size: 14px; width: 14px; height: 14px; color: #2271b1; }
                
                .dw-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
                .dw-badge-success { background: #e7f7ed; color: #008a20; }
                .dw-badge-warning { background: #fff8e5; color: #996800; }
                
                .dw-actions { display: flex; gap: 5px; justify-content: flex-end; }
                .dw-action-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid #dcdcde; background: #fff; color: #50575e; cursor: pointer; transition: all 0.2s; text-decoration: none; }
                .dw-action-btn:hover { border-color: #2271b1; color: #2271b1; }
                .dw-action-btn.delete:hover { border-color: #d63638; color: #d63638; background: #fff0f0; }

                .dw-pagination { margin-top: 20px; text-align: right; }
                .dw-pagination .page-numbers { display: inline-block; padding: 6px 12px; margin-left: 4px; border: 1px solid #c3c4c7; background: #fff; color: #2271b1; text-decoration: none; border-radius: 4px; font-size: 13px; }
                .dw-pagination .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; font-weight: 600; }

                @media(max-width: 960px) { 
                    .dw-stats-row { grid-template-columns: 1fr; }
                }
            </style>
        <?php endif; ?>
    </div>
    <?php
}