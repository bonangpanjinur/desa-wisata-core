<?php
/**
 * File Name:   includes/admin-pages/page-desa.php
 * Description: CRUD Desa Wisata, Verifikasi Premium & Pengaturan.
 * Sesuai skema tabel dw_desa (Single Table Architecture).
 */

if (!defined('ABSPATH')) exit;

// Pastikan file API Address dimuat jika belum
$address_api_path = dirname(dirname(__FILE__)) . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

function dw_desa_page_render() {
    global $wpdb;
    
    // Definisi nama tabel
    $table_desa     = $wpdb->prefix . 'dw_desa';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_wisata   = $wpdb->prefix . 'dw_wisata'; 
    $table_users    = $wpdb->users;
    
    // Pastikan media uploader di-enqueue
    wp_enqueue_media();
    
    $message = '';
    $message_type = '';

    // --- 1. HANDLE POST ACTIONS (GLOBAL) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // A. SIMPAN PENGATURAN HARGA
        if (isset($_POST['action_save_settings']) && check_admin_referer('dw_desa_settings_save')) {
            $settings = get_option('dw_settings', []);
            $settings['harga_premium_desa'] = absint($_POST['harga_premium_desa']);
            update_option('dw_settings', $settings);
            $message = 'Pengaturan harga berhasil disimpan.';
            $message_type = 'success';
        }

        // B. VERIFIKASI PEMBAYARAN (Tab Verifikasi)
        if (isset($_POST['action_verify_desa']) && check_admin_referer('dw_verify_desa')) {
            $desa_id = absint($_POST['desa_id']);
            $decision = sanitize_key($_POST['decision']); 
            
            if ($decision === 'approve') {
                $wpdb->update($table_desa, ['status_akses_verifikasi' => 'active', 'alasan_penolakan' => null], ['id' => $desa_id]);
                $message = 'Desa berhasil di-upgrade ke status PREMIUM.';
            } else {
                $reason = sanitize_textarea_field($_POST['alasan_penolakan']);
                $wpdb->update($table_desa, ['status_akses_verifikasi' => 'locked', 'alasan_penolakan' => $reason], ['id' => $desa_id]);
                $message = 'Pengajuan Premium ditolak. Status dikembalikan ke Free (Locked).';
            }
            $message_type = ($decision === 'approve') ? 'success' : 'warning';
        }

        // C. CRUD DESA (CREATE/UPDATE/DELETE)
        if (isset($_POST['action_desa']) && check_admin_referer('dw_desa_action')) {
            $action = sanitize_text_field($_POST['action_desa']);

            // DELETE
            if ($action === 'delete' && !empty($_POST['desa_id'])) {
                $desa_id_to_delete = intval($_POST['desa_id']);
                
                // Update pedagang: Set id_desa menjadi NULL (Safe Delete untuk anak data)
                $wpdb->update($table_pedagang, ['id_desa' => null], ['id_desa' => $desa_id_to_delete]); 
                
                // Hapus wisata (Opsional: bisa di set null atau delete cascade)
                if($wpdb->get_var("SHOW TABLES LIKE '$table_wisata'") == $table_wisata) {
                    $wpdb->delete($table_wisata, ['id_desa' => $desa_id_to_delete]);
                }
                
                // Hapus Desa
                $deleted = $wpdb->delete($table_desa, ['id' => $desa_id_to_delete]);

                if ($deleted) {
                    $message = "Desa berhasil dihapus."; $message_type = "success";
                } else {
                    $message = "Gagal menghapus desa."; $message_type = "error";
                }
            }
            
            // SAVE / UPDATE
            elseif ($action === 'save') {
                $id_user_desa = intval($_POST['user_id']); 
                $nama_desa    = sanitize_text_field($_POST['nama_desa']);
                $deskripsi    = wp_kses_post($_POST['deskripsi']);
                $status       = sanitize_text_field($_POST['status']);
                $slug_desa    = sanitize_title($nama_desa);
                
                // Membership Status & Bukti Bayar (Manual Admin Override)
                $status_akses = sanitize_text_field($_POST['status_akses_verifikasi']);
                $bukti_bayar  = esc_url_raw($_POST['bukti_bayar_akses']); 

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
                $foto_desa                 = esc_url_raw($_POST['foto_desa']); // Logo
                $foto_sampul               = esc_url_raw($_POST['foto_sampul']); // Cover (Baru)

                // Validasi
                $allow_save = true;
                if (empty($api_kelurahan_id)) {
                    $allow_save = false;
                    $message = "Gagal Menyimpan: <strong>Lokasi Kelurahan Wajib Dipilih.</strong>";
                    $message_type = "error";
                } 
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
                        $message = "Kelurahan ini sudah digunakan oleh desa lain.";
                        $message_type = "error";
                    }
                }

                if ($allow_save) {
                    $data = [
                        'id_user_desa'            => $id_user_desa,
                        'nama_desa'               => $nama_desa,
                        'slug_desa'               => $slug_desa,
                        'deskripsi'               => $deskripsi,
                        'foto'                    => $foto_desa,
                        'foto_sampul'             => $foto_sampul, // Simpan Cover
                        'status'                  => $status,
                        // Membership fields
                        'status_akses_verifikasi' => $status_akses, 
                        'bukti_bayar_akses'       => $bukti_bayar,
                        
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
                        $wpdb->update($table_desa, $data, ['id' => $desa_id]);
                        $message = "Data desa berhasil diperbarui.";
                        $message_type = "success";
                    } else {
                        $data['created_at'] = current_time('mysql');
                        $wpdb->insert($table_desa, $data);
                        $message = "Desa baru berhasil ditambahkan.";
                        $message_type = "success";
                    }
                }
            }
        }
    }

    // --- LOGIKA TAB NAVIGASI ---
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'data_desa';
    // Sub-view untuk Tab Data Desa (List/Add/Edit)
    $view = isset($_GET['view']) ? $_GET['view'] : 'list';
    
    // Hitung Pending Verifikasi
    $count_pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_desa WHERE status_akses_verifikasi = 'pending'");
    ?>

    <div class="wrap dw-wrapper">
        <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
        
        <!-- NAVIGASI TAB UTAMA -->
        <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <a href="?page=dw-desa&tab=data_desa" class="nav-tab <?php echo $active_tab == 'data_desa' ? 'nav-tab-active' : ''; ?>">
                Data Desa & Statistik
            </a>
            <a href="?page=dw-desa&tab=verifikasi" class="nav-tab <?php echo $active_tab == 'verifikasi' ? 'nav-tab-active' : ''; ?>">
                Verifikasi Premium
                <?php if($count_pending > 0) echo '<span class="dw-badge-count">'.$count_pending.'</span>'; ?>
            </a>
            <a href="?page=dw-desa&tab=pengaturan" class="nav-tab <?php echo $active_tab == 'pengaturan' ? 'nav-tab-active' : ''; ?>">
                Pengaturan Harga
            </a>
        </nav>

        <?php if (!empty($message)): ?>
            <div class="dw-notice dw-notice-<?php echo $message_type; ?>">
                <div class="dw-notice-content">
                    <span class="dashicons dashicons-<?php echo $message_type === 'success' ? 'yes' : 'warning'; ?>"></span>
                    <?php echo $message; ?>
                </div>
                <button type="button" class="dw-notice-dismiss"><span class="dashicons dashicons-dismiss"></span></button>
            </div>
        <?php endif; ?>

        <!-- KONTEN TAB -->
        <div class="dw-tab-content-container">
            
            <!-- 1. TAB DATA DESA (DESAIN LAMA ANDA) -->
            <?php if($active_tab == 'data_desa'): 
                $edit_data = null;
                if ($view === 'edit' && isset($_GET['id'])) {
                    $id = intval($_GET['id']);
                    $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id = %d", $id));
                }
            ?>
                
                <!-- HEADER TOMBOL (Hanya muncul di tab Data Desa) -->
                <div class="dw-header" style="margin-top:0;">
                    <div></div> <!-- Spacer -->
                    <?php if ($view === 'list'): ?>
                        <a href="<?php echo admin_url('admin.php?page=dw-desa&tab=data_desa&view=add'); ?>" class="dw-btn dw-btn-primary">
                            <span class="dashicons dashicons-plus-alt2"></span> Tambah Desa
                        </a>
                    <?php else: ?>
                        <a href="<?php echo admin_url('admin.php?page=dw-desa&tab=data_desa'); ?>" class="dw-btn dw-btn-secondary">
                            <span class="dashicons dashicons-arrow-left-alt"></span> Kembali ke List
                        </a>
                    <?php endif; ?>
                </div>

                <?php 
                // --- FORM VIEW (ADD / EDIT) ---
                if ($view === 'add' || ($view === 'edit' && $edit_data)): 
                    $is_edit = ($view === 'edit');
                    $action_url = admin_url('admin.php?page=dw-desa&tab=data_desa&view=' . $view . ($is_edit ? '&id=' . $edit_data->id : ''));
                    
                    // Helper variables (Preserve Data)
                    $v_nama     = $is_edit ? $edit_data->nama_desa : '';
                    $v_desc     = $is_edit ? $edit_data->deskripsi : '';
                    $v_status   = $is_edit ? $edit_data->status : 'aktif';
                    $v_akses    = $is_edit ? $edit_data->status_akses_verifikasi : 'locked';
                    $v_bukti    = $is_edit ? $edit_data->bukti_bayar_akses : '';
                    $v_user     = $is_edit ? $edit_data->id_user_desa : '';
                    $v_foto     = $is_edit ? $edit_data->foto : ''; // Logo
                    $v_foto_sampul = $is_edit ? ($edit_data->foto_sampul ?? '') : ''; // Cover
                    
                    // Location
                    $v_api_prov = $is_edit ? $edit_data->api_provinsi_id : '';
                    $v_api_kab  = $is_edit ? $edit_data->api_kabupaten_id : '';
                    $v_api_kec  = $is_edit ? $edit_data->api_kecamatan_id : '';
                    $v_api_kel  = $is_edit ? $edit_data->api_kelurahan_id : '';
                    $v_nm_prov  = $is_edit ? $edit_data->provinsi : '';
                    $v_nm_kab   = $is_edit ? $edit_data->kabupaten : '';
                    $v_nm_kec   = $is_edit ? $edit_data->kecamatan : '';
                    $v_nm_kel   = $is_edit ? $edit_data->kelurahan : '';
                    $v_alamat   = $is_edit ? $edit_data->alamat_lengkap : '';
                    // Finance
                    $v_bank     = $is_edit ? $edit_data->nama_bank_desa : '';
                    $v_rek      = $is_edit ? $edit_data->no_rekening_desa : '';
                    $v_an       = $is_edit ? $edit_data->atas_nama_rekening_desa : '';
                    $v_qris     = $is_edit ? $edit_data->qris_image_url_desa : '';
                ?>
                    
                    <form method="post" action="<?php echo $action_url; ?>" id="dw-desa-form">
                        <?php wp_nonce_field('dw_desa_action'); ?>
                        <input type="hidden" name="action_desa" value="save">
                        <?php if ($is_edit): ?><input type="hidden" name="desa_id" value="<?php echo esc_attr($edit_data->id); ?>"><?php endif; ?>

                        <div class="dw-layout-grid">
                            <!-- MAIN COLUMN -->
                            <div class="dw-main-col">
                                <div class="dw-card">
                                    <div class="dw-tabs-header">
                                        <button type="button" class="dw-tab-item active" data-tab="tab-general">Informasi Umum</button>
                                        <button type="button" class="dw-tab-item" data-tab="tab-location">Lokasi & Wilayah</button>
                                        <button type="button" class="dw-tab-item" data-tab="tab-finance">Keuangan & Bukti Bayar</button>
                                    </div>
                                    <div class="dw-card-body">
                                        <!-- TAB: UMUM -->
                                        <div id="tab-general" class="dw-tab-content active">
                                            <div class="dw-form-group">
                                                <label class="dw-label">Nama Desa Wisata <span class="required">*</span></label>
                                                <input type="text" name="nama_desa" class="dw-form-control dw-input-lg" value="<?php echo esc_attr($v_nama); ?>" required>
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">Deskripsi & Profil Desa</label>
                                                <?php wp_editor($v_desc, 'deskripsi', ['textarea_rows' => 12, 'media_buttons' => true, 'editor_class' => 'dw-editor']); ?>
                                            </div>
                                        </div>

                                        <!-- TAB: LOKASI -->
                                        <div id="tab-location" class="dw-tab-content" style="display:none;">
                                            <div class="dw-alert dw-alert-info">
                                                <span class="dashicons dashicons-info"></span> Data wilayah diambil otomatis.
                                            </div>
                                            <input type="hidden" name="provinsi_name" id="provinsi_name" value="<?php echo esc_attr($v_nm_prov); ?>">
                                            <input type="hidden" name="kabupaten_name" id="kabupaten_name" value="<?php echo esc_attr($v_nm_kab); ?>">
                                            <input type="hidden" name="kecamatan_name" id="kecamatan_name" value="<?php echo esc_attr($v_nm_kec); ?>">
                                            <input type="hidden" name="kelurahan_name" id="kelurahan_name" value="<?php echo esc_attr($v_nm_kel); ?>">
                                            
                                            <!-- Init Data JS hook -->
                                            <div id="dw-location-data" data-prov="<?php echo esc_attr($v_api_prov); ?>" data-kab="<?php echo esc_attr($v_api_kab); ?>" data-kec="<?php echo esc_attr($v_api_kec); ?>" data-kel="<?php echo esc_attr($v_api_kel); ?>"></div>

                                            <div class="dw-grid-2">
                                                <div class="dw-form-group"><label class="dw-label">Provinsi</label><select name="api_provinsi_id" id="api_provinsi_id" class="dw-form-control" required><option value="">Memuat...</option></select></div>
                                                <div class="dw-form-group"><label class="dw-label">Kabupaten/Kota</label><select name="api_kabupaten_id" id="api_kabupaten_id" class="dw-form-control" disabled required></select></div>
                                            </div>
                                            <div class="dw-grid-2">
                                                <div class="dw-form-group"><label class="dw-label">Kecamatan</label><select name="api_kecamatan_id" id="api_kecamatan_id" class="dw-form-control" disabled required></select></div>
                                                <div class="dw-form-group"><label class="dw-label">Kelurahan/Desa</label><select name="api_kelurahan_id" id="api_kelurahan_id" class="dw-form-control" disabled required></select></div>
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">Alamat Lengkap</label>
                                                <textarea name="alamat" class="dw-form-control" rows="3"><?php echo esc_textarea($v_alamat); ?></textarea>
                                            </div>
                                        </div>

                                        <!-- TAB: KEUANGAN & BUKTI BAYAR -->
                                        <div id="tab-finance" class="dw-tab-content" style="display:none;">
                                            <h3 class="dw-section-title">Rekening Bank (Untuk Menerima Pembayaran Wisata)</h3>
                                            <div class="dw-grid-3">
                                                <div class="dw-form-group"><label class="dw-label">Nama Bank</label><input type="text" name="nama_bank" class="dw-form-control" value="<?php echo esc_attr($v_bank); ?>"></div>
                                                <div class="dw-form-group"><label class="dw-label">No Rekening</label><input type="text" name="no_rekening" class="dw-form-control" value="<?php echo esc_attr($v_rek); ?>"></div>
                                                <div class="dw-form-group"><label class="dw-label">Atas Nama</label><input type="text" name="atas_nama" class="dw-form-control" value="<?php echo esc_attr($v_an); ?>"></div>
                                            </div>
                                            
                                            <div class="dw-separator"></div>
                                            
                                            <div class="dw-grid-2">
                                                <!-- QRIS DESA -->
                                                <div>
                                                    <h3 class="dw-section-title">QRIS Desa</h3>
                                                    <div class="dw-form-group">
                                                        <div class="dw-media-box">
                                                            <div class="dw-media-preview <?php echo $v_qris ? 'active' : ''; ?>">
                                                                <img src="<?php echo esc_url($v_qris); ?>" id="preview_qris">
                                                            </div>
                                                            <div class="dw-media-controls">
                                                                <input type="hidden" name="qris_image" id="input_qris" value="<?php echo esc_url($v_qris); ?>">
                                                                <button type="button" class="button btn_upload_media" data-target="#input_qris" data-preview="#preview_qris">Upload QRIS</button>
                                                                <button type="button" class="button dw-btn-danger btn_remove_media" data-target="#input_qris" data-preview="#preview_qris" style="<?php echo $v_qris ? '' : 'display:none;'; ?>">Hapus</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- BUKTI BAYAR PREMIUM -->
                                                <div>
                                                    <h3 class="dw-section-title">Bukti Transfer Premium</h3>
                                                    <div class="dw-alert dw-alert-info" style="margin-bottom:10px; font-size:12px;">Admin bisa upload manual jika perlu.</div>
                                                    <div class="dw-form-group">
                                                        <div class="dw-media-box" style="border-color:#b8e6f5; background:#f0f6fc;">
                                                            <div class="dw-media-preview <?php echo $v_bukti ? 'active' : ''; ?>">
                                                                <img src="<?php echo esc_url($v_bukti); ?>" id="preview_bukti">
                                                            </div>
                                                            <div class="dw-media-controls">
                                                                <input type="hidden" name="bukti_bayar_akses" id="input_bukti" value="<?php echo esc_url($v_bukti); ?>">
                                                                <button type="button" class="button btn_upload_media" data-target="#input_bukti" data-preview="#preview_bukti">Upload Bukti</button>
                                                                <button type="button" class="button dw-btn-danger btn_remove_media" data-target="#input_bukti" data-preview="#preview_bukti" style="<?php echo $v_bukti ? '' : 'display:none;'; ?>">Hapus</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SIDEBAR COLUMN -->
                            <div class="dw-sidebar-col">
                                <!-- Publish Box -->
                                <div class="dw-card dw-sidebar-card">
                                    <div class="dw-card-header"><h3>Terbitkan</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Status Publikasi</label>
                                            <select name="status" class="dw-form-control">
                                                <option value="aktif" <?php selected($v_status, 'aktif'); ?>>Aktif</option>
                                                <option value="pending" <?php selected($v_status, 'pending'); ?>>Pending</option>
                                            </select>
                                        </div>
                                        <!-- Membership Status di Sidebar -->
                                        <div class="dw-form-group" style="background:#f0f6fc; padding:10px; border-radius:4px; border:1px solid #cce5ff;">
                                            <label class="dw-label">Membership Status</label>
                                            <select name="status_akses_verifikasi" class="dw-form-control">
                                                <option value="locked" <?php selected($v_akses, 'locked'); ?>>Free (Locked)</option>
                                                <option value="active" <?php selected($v_akses, 'active'); ?>>Premium (Active)</option>
                                                <option value="pending" <?php selected($v_akses, 'pending'); ?>>Pending Verification</option>
                                            </select>
                                            <p class="description">Ganti manual status membership desa di sini.</p>
                                        </div>
                                        <div class="dw-form-actions">
                                            <button type="submit" id="btn-submit-desa" class="dw-btn dw-btn-primary dw-btn-block">
                                                <span class="dashicons dashicons-saved"></span> <?php echo $is_edit ? 'Simpan Perubahan' : 'Terbitkan Desa'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pengelola Box -->
                                <div class="dw-card dw-sidebar-card">
                                    <div class="dw-card-header"><h3>Pengelola</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Admin Desa</label>
                                            <?php
                                            // --- PERBAIKAN LOGIKA PENGELOLA ---
                                            $exclude_query = "SELECT DISTINCT id_user_desa FROM $table_desa";
                                            if ($is_edit && isset($edit_data->id)) {
                                                $exclude_query .= $wpdb->prepare(" WHERE id != %d", $edit_data->id);
                                            }
                                            $assigned_user_ids = $wpdb->get_col($exclude_query);

                                            // Hanya menampilkan role 'admin_desa'
                                            $user_args = [
                                                'role'     => 'admin_desa', 
                                                'exclude'  => $assigned_user_ids,
                                                'orderby'  => 'display_name',
                                                'order'    => 'ASC'
                                            ];
                                            $users = get_users($user_args);

                                            echo '<select name="user_id" class="dw-form-control">';
                                            echo '<option value="">-- Pilih User --</option>';
                                            
                                            $current_user_found = false;
                                            
                                            foreach ($users as $user) {
                                                $selected = ($v_user == $user->ID) ? 'selected' : '';
                                                if ($selected) $current_user_found = true;
                                                echo '<option value="' . $user->ID . '" ' . $selected . '>' . esc_html($user->display_name) . ' (' . implode(', ', $user->roles) . ')</option>';
                                            }

                                            // Fallback untuk user saat ini
                                            if ($v_user && !$current_user_found) {
                                                $u = get_user_by('id', $v_user);
                                                if ($u) {
                                                    echo '<option value="' . $u->ID . '" selected>' . esc_html($u->display_name) . ' (Saat Ini)</option>';
                                                }
                                            }

                                            echo '</select>';
                                            ?>
                                            <p class="description" style="font-size:11px; margin-top:5px;">Hanya menampilkan user dengan role 'Admin Desa' yang belum memiliki desa wisata.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Foto Utama Box (LOGO) -->
                                <div class="dw-card dw-sidebar-card">
                                    <div class="dw-card-header"><h3>Logo Desa</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-media-box">
                                            <div class="dw-media-preview thumbnail <?php echo $v_foto ? 'active' : ''; ?>">
                                                <img src="<?php echo esc_url($v_foto); ?>" id="preview_foto_desa">
                                            </div>
                                            <div class="dw-media-controls-sm">
                                                <input type="hidden" name="foto_desa" id="input_foto_desa" value="<?php echo esc_url($v_foto); ?>">
                                                <button type="button" class="button btn_upload_media" data-target="#input_foto_desa" data-preview="#preview_foto_desa">Pilih Logo</button>
                                                <button type="button" class="button-link dw-text-danger btn_remove_media" data-target="#input_foto_desa" data-preview="#preview_foto_desa" style="<?php echo $v_foto ? '' : 'display:none;'; ?>">Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Foto Sampul Box (BARU) -->
                                <div class="dw-card dw-sidebar-card">
                                    <div class="dw-card-header"><h3>Foto Sampul</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-media-box">
                                            <div class="dw-media-preview thumbnail <?php echo $v_foto_sampul ? 'active' : ''; ?>">
                                                <img src="<?php echo esc_url($v_foto_sampul); ?>" id="preview_foto_sampul">
                                            </div>
                                            <div class="dw-media-controls-sm">
                                                <input type="hidden" name="foto_sampul" id="input_foto_sampul" value="<?php echo esc_url($v_foto_sampul); ?>">
                                                <button type="button" class="button btn_upload_media" data-target="#input_foto_sampul" data-preview="#preview_foto_sampul">Pilih Sampul</button>
                                                <button type="button" class="button-link dw-text-danger btn_remove_media" data-target="#input_foto_sampul" data-preview="#preview_foto_sampul" style="<?php echo $v_foto_sampul ? '' : 'display:none;'; ?>">Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </form>

                <?php 
                // --- LIST VIEW (MODERN TABLE) ---
                else: 
                    // Query Stats & List (Sama seperti sebelumnya)
                    $pagenum     = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
                    $limit       = 10;
                    $offset      = ($pagenum - 1) * $limit;
                    $search      = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
                    $where_sql = "WHERE 1=1";
                    if ($search) {
                        $where_sql .= $wpdb->prepare(" AND (d.nama_desa LIKE %s OR d.kabupaten LIKE %s)", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
                    }
                    $sql = "SELECT d.*, u.display_name as admin_name,
                            (SELECT COUNT(id) FROM $table_pedagang WHERE id_desa = d.id) as count_pedagang,
                            (SELECT COUNT(id) FROM $table_wisata WHERE id_desa = d.id) as count_wisata
                            FROM $table_desa d 
                            LEFT JOIN $table_users u ON d.id_user_desa = u.ID 
                            $where_sql ORDER BY d.created_at DESC LIMIT %d OFFSET %d";
                    $results     = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
                    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa d $where_sql");
                    $total_pages = ceil($total_items / $limit);
                    
                    // Stats Dashboard
                    $total_all_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
                    $total_all_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM $table_pedagang WHERE id_desa IS NOT NULL");
                    $total_all_wisata = $wpdb->get_var("SELECT COUNT(id) FROM $table_wisata");
                    $total_all_revenue = $wpdb->get_var("SELECT SUM(total_pendapatan) FROM $table_desa");
                ?>
                    
                    <!-- Modern List UI -->
                    <div class="dw-list-container">
                        <!-- Stats Row -->
                        <div class="dw-stats-row">
                            <div class="dw-stat-card"><div class="dw-stat-icon"><span class="dashicons dashicons-admin-home"></span></div><div class="dw-stat-info"><h3><?php echo number_format($total_all_desa); ?></h3><span>Total Desa</span></div></div>
                            <div class="dw-stat-card"><div class="dw-stat-icon icon-blue"><span class="dashicons dashicons-store"></span></div><div class="dw-stat-info"><h3><?php echo number_format($total_all_pedagang); ?></h3><span>Pedagang</span></div></div>
                            <div class="dw-stat-card"><div class="dw-stat-icon icon-green"><span class="dashicons dashicons-palmtree"></span></div><div class="dw-stat-info"><h3><?php echo number_format($total_all_wisata); ?></h3><span>Wisata</span></div></div>
                            <div class="dw-stat-card"><div class="dw-stat-icon icon-purple"><span class="dashicons dashicons-money-alt"></span></div><div class="dw-stat-info"><h3>Rp <?php echo number_format($total_all_revenue ?? 0, 0, ',', '.'); ?></h3><span>Pendapatan</span></div></div>
                        </div>

                        <!-- Table -->
                        <div class="dw-table-card">
                            <table class="wp-list-table widefat fixed striped table-view-list">
                                <thead>
                                    <tr>
                                        <th width="50">ID</th>
                                        <th width="70">Logo</th> <!-- Kolom Logo Baru -->
                                        <th class="col-info">Informasi Desa</th>
                                        <th class="col-loc">Lokasi</th>
                                        <th width="120">Membership</th>
                                        <th class="col-stats" width="150">Statistik</th>
                                        <th width="120">Pendapatan</th>
                                        <th width="120" style="text-align:right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($results): foreach ($results as $row): 
                                        // Badge Logic
                                        $membership_class = 'dw-badge-locked';
                                        $membership_label = 'FREE';
                                        if($row->status_akses_verifikasi === 'active') { $membership_class = 'dw-badge-premium'; $membership_label = 'PREMIUM'; }
                                        elseif($row->status_akses_verifikasi === 'pending') { $membership_class = 'dw-badge-warning'; $membership_label = 'PENDING'; }
                                    ?>
                                    <tr>
                                        <td style="color:#888;">#<?php echo $row->id; ?></td>
                                        <!-- Tampilkan Logo di Tabel -->
                                        <td>
                                            <?php if($row->foto): ?>
                                                <img src="<?php echo esc_url($row->foto); ?>" class="dw-admin-thumb" style="width:40px; height:40px; border-radius:4px; object-fit:cover; border:1px solid #ddd;">
                                            <?php else: ?>
                                                <div style="width:40px; height:40px; background:#f0f0f1; border-radius:4px;"></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dw-item-title"><a href="<?php echo admin_url('admin.php?page=dw-desa&tab=data_desa&view=edit&id='.$row->id); ?>"><?php echo esc_html($row->nama_desa); ?></a></div>
                                            <div class="dw-item-meta"><span class="dashicons dashicons-admin-users"></span> <?php echo $row->admin_name ? esc_html($row->admin_name) : 'No Admin'; ?></div>
                                        </td>
                                        <td>
                                            <?php if($row->kabupaten): ?>
                                                <div class="dw-loc-main"><?php echo esc_html($row->kabupaten); ?></div>
                                                <div class="dw-loc-sub"><?php echo esc_html($row->kecamatan); ?></div>
                                            <?php else: echo '<span style="color:#999;">-</span>'; endif; ?>
                                        </td>
                                        <td><span class="dw-badge <?php echo $membership_class; ?>"><?php echo $membership_label; ?></span></td>
                                        <td>
                                            <div class="dw-stat-pill"><span class="dashicons dashicons-store"></span> <?php echo $row->count_pedagang; ?></div>
                                            <div class="dw-stat-pill"><span class="dashicons dashicons-palmtree"></span> <?php echo $row->count_wisata; ?></div>
                                        </td>
                                        <td><span style="font-weight:600; color:#46b450;">Rp <?php echo number_format($row->total_pendapatan ?? 0, 0, ',', '.'); ?></span></td>
                                        <td style="text-align:right;">
                                            <div class="dw-actions">
                                                <a href="<?php echo admin_url('admin.php?page=dw-desa&tab=data_desa&view=edit&id='.$row->id); ?>" class="dw-action-btn edit" title="Edit"><span class="dashicons dashicons-edit"></span></a>
                                                <form method="post" style="display:inline-block;" onsubmit="return confirm('Hapus desa ini?');">
                                                    <?php wp_nonce_field('dw_desa_action'); ?>
                                                    <input type="hidden" name="action_desa" value="delete">
                                                    <input type="hidden" name="desa_id" value="<?php echo $row->id; ?>">
                                                    <button type="submit" class="dw-action-btn delete" title="Hapus"><span class="dashicons dashicons-trash"></span></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="8" style="text-align:center; padding:30px;">Belum ada desa.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; // End View (List/Edit) ?>

            <!-- 2. TAB VERIFIKASI (Membaca langsung dari Tabel Desa) -->
            <?php elseif($active_tab == 'verifikasi'): 
                $pending_desas = $wpdb->get_results("SELECT * FROM $table_desa WHERE status_akses_verifikasi = 'pending' ORDER BY updated_at ASC");
            ?>
                <div class="dw-card" style="margin-bottom:20px;">
                    <div class="dw-card-header"><h3>Antrean Verifikasi Upgrade Premium</h3></div>
                    <div class="dw-card-body">
                        <?php if (empty($pending_desas)): ?>
                            <div class="dw-alert dw-alert-info">Tidak ada permintaan verifikasi saat ini.</div>
                        <?php else: ?>
                            <?php foreach ($pending_desas as $d): ?>
                                <div style="border:1px solid #eee; padding:15px; border-radius:4px; margin-bottom:15px; background:#fafafa;">
                                    <h4 style="margin-top:0;"><?php echo esc_html($d->nama_desa); ?> <span class="dw-badge dw-badge-warning">PENDING</span></h4>
                                    <div class="dw-grid-2">
                                        <div>
                                            <p><strong>Lokasi:</strong> <?php echo esc_html($d->kecamatan . ', ' . $d->kabupaten); ?></p>
                                            <p><strong>Kontak:</strong> <?php $u = get_user_by('id', $d->id_user_desa); echo $u ? $u->display_name.' ('.$u->user_email.')' : '-'; ?></p>
                                        </div>
                                        <div>
                                            <strong>Bukti Bayar:</strong><br>
                                            <?php if($d->bukti_bayar_akses): ?>
                                                <a href="<?php echo esc_url($d->bukti_bayar_akses); ?>" target="_blank"><img src="<?php echo esc_url($d->bukti_bayar_akses); ?>" style="height:120px; border:1px solid #ddd; padding:4px; background:#fff;"></a>
                                            <?php else: echo '<span style="color:red;">Tidak ada file bukti bayar yang tersimpan.</span>'; endif; ?>
                                        </div>
                                    </div>
                                    <div style="margin-top:10px; display:flex; gap:10px;">
                                        <form method="post">
                                            <?php wp_nonce_field('dw_verify_desa'); ?>
                                            <input type="hidden" name="action_verify_desa" value="1">
                                            <input type="hidden" name="desa_id" value="<?php echo $d->id; ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <button type="submit" class="dw-btn dw-btn-primary" onclick="return confirm('Setujui Premium?');">Setujui</button>
                                        </form>
                                        <button type="button" class="dw-btn dw-btn-secondary" onclick="jQuery('#reject-<?php echo $d->id; ?>').toggle();">Tolak</button>
                                    </div>
                                    <div id="reject-<?php echo $d->id; ?>" style="display:none; margin-top:10px;">
                                        <form method="post">
                                            <?php wp_nonce_field('dw_verify_desa'); ?>
                                            <input type="hidden" name="action_verify_desa" value="1">
                                            <input type="hidden" name="desa_id" value="<?php echo $d->id; ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <textarea name="alasan_penolakan" class="dw-form-control" placeholder="Alasan penolakan..." required></textarea>
                                            <button type="submit" class="dw-btn dw-btn-danger" style="margin-top:5px;">Kirim Penolakan</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <!-- 3. TAB PENGATURAN -->
            <?php elseif($active_tab == 'pengaturan'): 
                $settings = get_option('dw_settings', []);
                $harga = isset($settings['harga_premium_desa']) ? $settings['harga_premium_desa'] : 0;
                
                // Ambil Data Pembayaran dari Option Global
                $bank_name    = get_option('dw_bank_name', '-');
                $bank_account = get_option('dw_bank_account', '-');
                $bank_holder  = get_option('dw_bank_holder', '-');
                $qris_url     = get_option('dw_qris_image_url', ''); 
            ?>
                <div class="dw-card" style="max-width:600px;">
                    <div class="dw-card-header"><h3>Pengaturan Membership</h3></div>
                    <div class="dw-card-body">
                        <form method="post">
                            <?php wp_nonce_field('dw_desa_settings_save'); ?>
                            <input type="hidden" name="action_save_settings" value="1">
                            <div class="dw-form-group">
                                <label class="dw-label">Harga Upgrade Premium (Rp)</label>
                                <input type="number" name="harga_premium_desa" class="dw-form-control" value="<?php echo esc_attr($harga); ?>">
                                <p class="description">Biaya pendaftaran untuk upgrade ke status Premium.</p>
                            </div>
                            
                            <div class="dw-form-group">
                                <label class="dw-label">Info Pembayaran (Preview Data)</label>
                                <div class="dw-alert dw-alert-info">
                                    <span class="dashicons dashicons-info"></span> Data di bawah diambil dari <strong>Pengaturan Pembayaran Plugin Utama</strong>.
                                </div>
                                <div style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:4px;">
                                    <p><strong>Rekening Bank:</strong></p>
                                    <div style="background:#f9f9f9; padding:10px; border-radius:4px; margin-bottom:10px;">
                                        <ul style="margin:0; padding-left:20px;">
                                            <li><strong>Bank:</strong> <?php echo esc_html($bank_name); ?></li>
                                            <li><strong>No. Rek:</strong> <?php echo esc_html($bank_account); ?></li>
                                            <li><strong>A.N:</strong> <?php echo esc_html($bank_holder); ?></li>
                                        </ul>
                                    </div>
                                    
                                    <p><strong>QRIS Code:</strong></p>
                                    <?php if($qris_url): ?>
                                        <img src="<?php echo esc_url($qris_url); ?>" style="width:150px; height:auto; border:1px solid #ccc; padding:5px; border-radius:4px;">
                                    <?php else: ?>
                                        <em style="color:#999;">Belum ada gambar QRIS.</em>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <button type="submit" class="dw-btn dw-btn-primary">Simpan Pengaturan</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODERN CSS -->
    <style>
        :root { --dw-primary: #2271b1; --dw-primary-hover: #135e96; --dw-bg: #f0f0f1; --dw-border: #dcdcde; --dw-text: #3c434a; --dw-text-light: #646970; --dw-radius: 4px; --dw-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .dw-wrapper { margin: 20px 20px 0 0; }
        .dw-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .dw-layout-grid { display: grid; grid-template-columns: 1fr 280px; gap: 20px; align-items: start; }
        .dw-card { background: #fff; border: 1px solid var(--dw-border); border-radius: var(--dw-radius); box-shadow: var(--dw-shadow); margin-bottom: 20px; overflow: hidden; }
        .dw-card-header { padding: 12px 15px; border-bottom: 1px solid var(--dw-border); background: #fbfbfb; }
        .dw-card-header h3 { margin: 0; font-size: 14px; font-weight: 600; color: var(--dw-text); }
        .dw-card-body { padding: 20px; }
        .dw-sidebar-card .dw-card-body { padding: 15px; }
        .dw-tabs-header { display: flex; border-bottom: 1px solid var(--dw-border); background: #f6f7f7; }
        .dw-tab-item { background: none; border: none; padding: 15px 20px; font-size: 14px; color: var(--dw-text-light); font-weight: 500; cursor: pointer; border-right: 1px solid var(--dw-border); transition: all 0.2s; outline: none; }
        .dw-tab-item:hover { background: #fff; color: var(--dw-primary); }
        .dw-tab-item.active { background: #fff; color: var(--dw-primary); border-bottom: 2px solid transparent; box-shadow: inset 0 3px 0 var(--dw-primary); font-weight: 600; }
        .dw-form-group { margin-bottom: 20px; }
        .dw-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dw-text); font-size: 13px; }
        .dw-form-control { width: 100%; height: 40px; padding: 0 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; color: #2c3338; box-sizing: border-box; }
        textarea.dw-form-control { height: auto; padding: 10px; line-height: 1.5; }
        .dw-input-lg { height: 45px; font-size: 16px; font-weight: 500; }
        .dw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .dw-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .dw-btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 4px; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.2s; border: none; font-size: 13px; line-height: 1.4; gap: 5px; }
        .dw-btn-primary { background: var(--dw-primary); color: #fff; }
        .dw-btn-secondary { background: #fff; border: 1px solid var(--dw-primary); color: var(--dw-primary); }
        .dw-btn-danger { background: #d63638; color: #fff; }
        .dw-btn-block { width: 100%; display: flex; margin-top: 10px; }
        .dw-media-box { border: 2px dashed #c3c4c7; padding: 15px; border-radius: 4px; text-align: center; background: #fafafa; }
        .dw-media-preview { display: none; margin-bottom: 10px; }
        .dw-media-preview.active { display: block; }
        .dw-media-preview img { max-width: 100%; height: auto; border-radius: 4px; }
        .dw-stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
        .dw-stat-card { background: #fff; padding: 20px; border-radius: 4px; border: 1px solid #dcdcde; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .dw-stat-icon { width: 50px; height: 50px; background: #f0f0f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #50575e; }
        .dw-stat-icon.icon-blue { background: #e5f5fa; color: #0073aa; }
        .dw-stat-icon.icon-green { background: #e7f7ed; color: #008a20; }
        .dw-stat-icon.icon-purple { background: #f0f0f1; color: #8224e3; }
        .dw-stat-info h3 { margin: 0 0 5px; font-size: 24px; color: #1d2327; }
        .dw-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
        .dw-badge-premium { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .dw-badge-locked { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .dw-badge-warning { background: #fff8e5; color: #996800; }
        .dw-badge-count { display: inline-block; background: #d63638; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 5px; vertical-align: text-top; }
        .dw-table-card { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); overflow: hidden; }
        .wp-list-table th { font-weight: 600; color: #1d2327; padding: 15px 10px; }
        .dw-actions { display: flex; gap: 5px; justify-content: flex-end; }
        .dw-action-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid #dcdcde; background: #fff; color: #50575e; cursor: pointer; text-decoration: none; }
        .dw-action-btn:hover { border-color: #2271b1; color: #2271b1; }
        .dw-alert { padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px; }
        .dw-alert-info { background: #e5f5fa; border: 1px solid #b8e6f5; color: #006799; }
        .dw-notice { background: #fff; border-left: 4px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.1); padding: 12px 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 2px; }
        .dw-notice-success { border-left-color: #46b450; }
        .dw-notice-error { border-left-color: #d63638; }
        .dw-notice-content { display: flex; align-items: center; gap: 8px; font-weight: 500; }
        .dw-notice-dismiss { background: none; border: none; cursor: pointer; color: #787c82; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Tab Switching Logic for Form
        $('.dw-tab-item').on('click', function() {
            var target = $(this).data('tab');
            $('.dw-tab-item').removeClass('active');
            $('.dw-tab-content').hide();
            $(this).addClass('active');
            $('#' + target).fadeIn(200);
        });
        $('.dw-notice-dismiss').on('click', function() { $(this).closest('.dw-notice').fadeOut(); });
        
        // Media Uploader
        function setupMediaUploader(btnClass) {
            $(document).on('click', btnClass, function(e) {
                e.preventDefault();
                var btn = $(this), targetInput = btn.data('target'), targetPreview = btn.data('preview');
                var container = $(targetPreview).closest('.dw-media-box');
                var frame = wp.media({ title: 'Pilih Gambar', multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var selection = frame.state().get('selection').first().toJSON();
                    $(targetInput).val(selection.url);
                    $(targetPreview).attr('src', selection.url).parent().addClass('active');
                    container.find('.btn_remove_media').show();
                });
                frame.open();
            });
        }
        setupMediaUploader('.btn_upload_media');
        $(document).on('click', '.btn_remove_media', function(e){
            e.preventDefault();
            var btn = $(this), targetInput = btn.data('target'), targetPreview = btn.data('preview');
            $(targetInput).val('');
            $(targetPreview).attr('src', '').parent().removeClass('active');
            btn.hide();
        });

        // Address API logic
        var locData = $('#dw-location-data');
        if(locData.length) {
            var initVals = { prov: locData.data('prov'), kab: locData.data('kab'), kec: locData.data('kec'), kel: locData.data('kel') };
            function loadArea(action, parentId, target, selected, callback) {
                $.get(ajaxurl, { action: action, province_id: parentId, regency_id: parentId, district_id: parentId }, function(res) {
                    if(res.success) {
                        var opts = '<option value="">-- Pilih --</option>';
                        $.each(res.data, function(i, v) { opts += '<option value="'+v.id+'" '+(v.id==selected?'selected':'')+'>'+v.name+'</option>'; });
                        $(target).html(opts).prop('disabled', false);
                        if(callback) callback();
                    }
                });
            }
            function updateName(el, target) { $(target).val($(el).find('option:selected').text()); }
            
            loadArea('dw_fetch_provinces', null, '#api_provinsi_id', initVals.prov, function(){ if(initVals.prov) $('#api_provinsi_id').trigger('change'); });
            $('#api_provinsi_id').change(function(){ updateName(this, '#provinsi_name'); var id = $(this).val(); $('#api_kabupaten_id, #api_kecamatan_id, #api_kelurahan_id').empty().prop('disabled', true); if(id) loadArea('dw_fetch_regencies', id, '#api_kabupaten_id', (id==initVals.prov?initVals.kab:''), function(){ if(id==initVals.prov) $('#api_kabupaten_id').trigger('change'); }); });
            $('#api_kabupaten_id').change(function(){ updateName(this, '#kabupaten_name'); var id = $(this).val(); $('#api_kecamatan_id, #api_kelurahan_id').empty().prop('disabled', true); if(id) loadArea('dw_fetch_districts', id, '#api_kecamatan_id', (id==initVals.kab?initVals.kec:''), function(){ if(id==initVals.kab) $('#api_kecamatan_id').trigger('change'); }); });
            $('#api_kecamatan_id').change(function(){ updateName(this, '#kecamatan_name'); var id = $(this).val(); $('#api_kelurahan_id').empty().prop('disabled', true); if(id) loadArea('dw_fetch_villages', id, '#api_kelurahan_id', (id==initVals.kec?initVals.kel:'')); });
            $('#api_kelurahan_id').change(function(){ updateName(this, '#kelurahan_name'); });
        }
    });
    </script>
    <?php
}
?>