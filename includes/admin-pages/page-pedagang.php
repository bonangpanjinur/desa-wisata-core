<?php
/**
 * Halaman Manajemen Pedagang
 * UI/UX Updated: Modern Card Layout & Diagnostic Address Loading
 */

// Pastikan tidak diakses langsung
if (!defined('ABSPATH')) {
    exit;
}

// --- DIAGNOSTIC LOADER: ADDRESS API ---
// Array untuk menyimpan log path yang dicoba (untuk debugging jika gagal)
$dw_debug_paths = [];

// Cek apakah fungsi sudah ada sebelumnya
if (!function_exists('dw_get_provinces')) {
    
    // STRATEGI 1: Gunakan Konstanta Plugin (Paling Akurat)
    if (defined('DW_CORE_PLUGIN_DIR')) {
        $path1 = DW_CORE_PLUGIN_DIR . 'includes/address-api.php';
        $dw_debug_paths[] = "Try Constant: " . $path1;
        if (file_exists($path1)) {
            require_once $path1;
        }
    }

    // STRATEGI 2: Gunakan Path Relatif WordPress (plugin_dir_path)
    // Naik 2 level dari file ini: admin-pages -> includes -> root plugin? Tidak, ini ada di includes/admin-pages/
    // Target: includes/address-api.php
    if (!function_exists('dw_get_provinces')) {
        // dirname(__FILE__) = .../includes/admin-pages
        // dirname(dirname(__FILE__)) = .../includes
        $path2 = dirname(dirname(__FILE__)) . '/address-api.php';
        $dw_debug_paths[] = "Try Relative: " . $path2;
        if (file_exists($path2)) {
            require_once $path2;
        }
    }

    // STRATEGI 3: Cek Helpers (Siapa tahu fungsinya ada di sana)
    if (!function_exists('dw_get_provinces')) {
        $path3 = dirname(dirname(__FILE__)) . '/helpers.php';
        $dw_debug_paths[] = "Try Helper: " . $path3;
        if (file_exists($path3)) {
            require_once $path3;
        }
    }
}

/**
 * Fungsi utama render page
 */
function dw_pedagang_page_render() {
    global $wpdb, $dw_debug_paths; // Akses variabel debug
    $table_name = $wpdb->prefix . 'dw_pedagang';
    
    $message = '';
    $message_type = '';
    
    // --- 1. SETUP ACTION & DATA ---
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $pedagang_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Data default
    $data = [
        'nama_pedagang' => '',
        'email' => '',
        'telepon' => '',
        'nik' => '',
        'nama_toko' => '',
        'deskripsi_toko' => '',
        'provinsi' => '',
        'kota' => '',
        'kecamatan' => '',
        'desa' => '',
        'alamat_lengkap' => '',
        'nama_bank' => '',
        'no_rekening' => '',
        'foto_toko' => '',
        'status_verifikasi' => 'pending'
    ];

    // Jika mode EDIT, ambil data dari database
    if ($action == 'edit' && $pedagang_id > 0) {
        $existing_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $pedagang_id), ARRAY_A);
        if ($existing_data) {
            $data = array_merge($data, $existing_data);
        }
    }

    // --- 2. HANDLE SUBMISSION ---
    if (isset($_POST['submit_pedagang']) && check_admin_referer('dw_save_pedagang_nonce')) {
        // Sanitize inputs
        $nama = sanitize_text_field($_POST['nama_pedagang']);
        $email = sanitize_email($_POST['email_pedagang']);
        $telepon = sanitize_text_field($_POST['telepon_pedagang']);
        $nik = sanitize_text_field($_POST['nik']);
        $nama_toko = sanitize_text_field($_POST['nama_toko']);
        $deskripsi = sanitize_textarea_field($_POST['deskripsi_toko']);
        $nama_bank = sanitize_text_field($_POST['nama_bank']);
        $no_rekening = sanitize_text_field($_POST['no_rekening']);
        $status_verifikasi = sanitize_text_field($_POST['status_verifikasi']);
        
        // Address inputs
        $provinsi = sanitize_text_field($_POST['provinsi']);
        $kota = sanitize_text_field($_POST['kota']);
        $kecamatan = sanitize_text_field($_POST['kecamatan']);
        $desa = sanitize_text_field($_POST['desa']);
        $alamat_rinci = sanitize_textarea_field($_POST['alamat_rinci']);

        // Handle Photo Upload
        $foto_url = $data['foto_toko']; 
        if (!empty($_FILES['foto_toko']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('foto_toko', 0);
            if (!is_wp_error($attachment_id)) {
                $foto_url = wp_get_attachment_url($attachment_id);
            }
        }

        // Validasi
        if (empty($nama) || empty($email) || empty($nama_toko)) {
            $message = 'Nama Lengkap, Email, dan Nama Toko wajib diisi.';
            $message_type = 'error';
        } else {
            $db_data = array(
                'user_id' => get_current_user_id(),
                'nama_pedagang' => $nama,
                'email' => $email,
                'telepon' => $telepon,
                'nik' => $nik,
                'nama_toko' => $nama_toko,
                'deskripsi_toko' => $deskripsi,
                'provinsi' => $provinsi,
                'kota' => $kota,
                'kecamatan' => $kecamatan,
                'desa' => $desa,
                'alamat_lengkap' => $alamat_rinci,
                'nama_bank' => $nama_bank,
                'no_rekening' => $no_rekening,
                'foto_toko' => $foto_url,
                'status_verifikasi' => $status_verifikasi,
                'updated_at' => current_time('mysql')
            );

            if ($pedagang_id > 0) {
                // Update
                $wpdb->update($table_name, $db_data, ['id' => $pedagang_id]);
                $message = 'Data pedagang berhasil diperbarui.';
                $message_type = 'success';
                $action = 'list';
            } else {
                // Insert
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE email = %s", $email));
                if ($exists) {
                    $message = 'Email sudah terdaftar.';
                    $message_type = 'error';
                } else {
                    $db_data['created_at'] = current_time('mysql');
                    $wpdb->insert($table_name, $db_data);
                    $message = 'Pedagang baru berhasil ditambahkan.';
                    $message_type = 'success';
                    $action = 'list';
                }
            }
        }
    }

    // --- 3. CUSTOM STYLES (UI UX Modern) ---
    ?>
    <style>
        .dw-pedagang-wrap { max-width: 1200px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
        
        /* Card Styling */
        .dw-card { background: #fff; border: 1px solid #c3c4c7; border-top: 3px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 4px; padding: 0; margin-bottom: 20px; overflow: hidden; }
        .dw-card-header { background: #f6f7f7; border-bottom: 1px solid #dcdcde; padding: 15px 20px; display: flex; align-items: center; }
        .dw-card-header h3 { margin: 0; font-size: 14px; font-weight: 600; text-transform: uppercase; color: #1d2327; letter-spacing: 0.5px; }
        .dw-card-header .dashicons { margin-right: 8px; color: #50575e; }
        .dw-card-body { padding: 20px; }

        /* Grid System */
        .dw-grid-main { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .dw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 960px) { .dw-grid-main, .dw-grid-2 { grid-template-columns: 1fr; } }

        /* Form Controls */
        .dw-form-group { margin-bottom: 15px; }
        .dw-form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #1d2327; font-size: 13px; }
        .dw-form-group .required { color: #d63638; margin-left: 2px; }
        .dw-form-control { width: 100%; padding: 0 12px; height: 40px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; box-sizing: border-box; transition: all 0.2s ease; }
        .dw-form-control:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
        textarea.dw-form-control { height: auto; padding-top: 10px; resize: vertical; min-height: 80px; }
        select.dw-form-control { line-height: 2; }
        
        /* Photo Upload */
        .dw-photo-wrapper { display: flex; align-items: flex-start; gap: 15px; border: 2px dashed #c3c4c7; padding: 15px; border-radius: 4px; background: #f0f0f1; }
        .dw-photo-preview { width: 100px; height: 100px; border-radius: 4px; object-fit: cover; background: #fff; border: 1px solid #ddd; display: none; }
        .dw-photo-preview.active { display: block; }
        
        /* Buttons */
        .dw-btn-block { display: block; width: 100%; text-align: center; margin-top: 10px; }
        .dw-action-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; }

        /* Error/Status Box */
        .dw-api-status { font-size: 12px; margin-top: 5px; color: #646970; }
        .dw-api-error { color: #d63638; }
    </style>

    <div class="wrap dw-pedagang-wrap">
        <h1 class="wp-heading-inline">Manajemen Toko & Pedagang</h1>
        
        <?php if ($action == 'list'): ?>
            <a href="<?php echo add_query_arg('action', 'add'); ?>" class="page-title-action primary">Tambah Pedagang Baru</a>
        <?php else: ?>
            <a href="<?php echo remove_query_arg(['action', 'id']); ?>" class="page-title-action">Kembali ke Daftar</a>
        <?php endif; ?>
        
        <hr class="wp-header-end">

        <?php if (!empty($message)) : ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible" style="margin-left: 0; margin-top: 15px;">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <?php 
        // --- VIEW: FORM INPUT (ADD / EDIT) ---
        if ($action == 'add' || $action == 'edit'): 
        ?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('dw_save_pedagang_nonce'); ?>
            
            <div class="dw-grid-main">
                <!-- KOLOM KIRI: Informasi Utama -->
                <div class="dw-col-main">
                    <!-- 1. Informasi Toko -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <span class="dashicons dashicons-store"></span>
                            <h3>Informasi Toko / Usaha</h3>
                        </div>
                        <div class="dw-card-body">
                            <div class="dw-form-group">
                                <label>Nama Toko <span class="required">*</span></label>
                                <input type="text" name="nama_toko" class="dw-form-control" value="<?php echo esc_attr($data['nama_toko']); ?>" required placeholder="Contoh: Keripik Singkong Barokah">
                            </div>
                            
                            <div class="dw-form-group">
                                <label>Deskripsi Singkat</label>
                                <textarea name="deskripsi_toko" class="dw-form-control"><?php echo esc_textarea($data['deskripsi_toko']); ?></textarea>
                            </div>

                            <div class="dw-form-group">
                                <label>Foto Profil Toko</label>
                                <div class="dw-photo-wrapper">
                                    <?php $has_photo = !empty($data['foto_toko']); ?>
                                    <img id="preview-img" src="<?php echo $has_photo ? esc_url($data['foto_toko']) : ''; ?>" class="dw-photo-preview <?php echo $has_photo ? 'active' : ''; ?>">
                                    <div style="flex: 1;">
                                        <input type="file" name="foto_toko" id="foto_toko" accept="image/*" onchange="previewImage(this)">
                                        <p class="description">Format: JPG, PNG. Maksimal ukuran 2MB.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Data Pemilik -->
                    <div class="dw-card" style="border-top-color: #3582c4;">
                        <div class="dw-card-header">
                            <span class="dashicons dashicons-admin-users"></span>
                            <h3>Data Pemilik</h3>
                        </div>
                        <div class="dw-card-body">
                            <div class="dw-grid-2">
                                <div class="dw-form-group">
                                    <label>Nama Lengkap (Sesuai KTP) <span class="required">*</span></label>
                                    <input type="text" name="nama_pedagang" class="dw-form-control" value="<?php echo esc_attr($data['nama_pedagang']); ?>" required>
                                </div>
                                <div class="dw-form-group">
                                    <label>NIK <span class="required">*</span></label>
                                    <input type="text" name="nik" class="dw-form-control" value="<?php echo esc_attr($data['nik']); ?>" required>
                                </div>
                            </div>

                            <div class="dw-grid-2">
                                <div class="dw-form-group">
                                    <label>Email <span class="required">*</span></label>
                                    <input type="email" name="email_pedagang" class="dw-form-control" value="<?php echo esc_attr($data['email']); ?>" required>
                                </div>
                                <div class="dw-form-group">
                                    <label>No. Telepon / WhatsApp</label>
                                    <input type="text" name="telepon_pedagang" class="dw-form-control" value="<?php echo esc_attr($data['telepon']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KOLOM KANAN: Sidebar (Status & Alamat) -->
                <div class="dw-col-sidebar">
                    <!-- 3. Status & Action -->
                    <div class="dw-card" style="border-top-color: #d63638;">
                        <div class="dw-card-header">
                            <span class="dashicons dashicons-yes"></span>
                            <h3>Status & Simpan</h3>
                        </div>
                        <div class="dw-card-body">
                            <div class="dw-form-group">
                                <label>Status Verifikasi</label>
                                <select name="status_verifikasi" class="dw-form-control">
                                    <option value="pending" <?php selected($data['status_verifikasi'], 'pending'); ?>>Menunggu Verifikasi</option>
                                    <option value="verified" <?php selected($data['status_verifikasi'], 'verified'); ?>>Terverifikasi</option>
                                    <option value="rejected" <?php selected($data['status_verifikasi'], 'rejected'); ?>>Ditolak</option>
                                </select>
                            </div>
                            <div class="dw-action-bar">
                                <a href="<?php echo remove_query_arg(['action', 'id']); ?>" style="text-decoration: none; color: #d63638;">Batal</a>
                                <button type="submit" name="submit_pedagang" class="button button-primary button-large">Simpan Data</button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Lokasi & Alamat -->
                    <div class="dw-card" style="border-top-color: #46b450;">
                        <div class="dw-card-header">
                            <span class="dashicons dashicons-location"></span>
                            <h3>Alamat Lengkap</h3>
                        </div>
                        <div class="dw-card-body">
                            <div class="dw-form-group">
                                <label>Provinsi</label>
                                <select name="provinsi" id="provinsi" class="dw-form-control">
                                    <option value="">-- Pilih Provinsi --</option>
                                    <?php
                                    // Pengecekan final apakah fungsi tersedia
                                    if (function_exists('dw_get_provinces')) {
                                        $provinces = dw_get_provinces();
                                        if ($provinces) {
                                            foreach ($provinces as $id => $name) {
                                                $selected = ($data['provinsi'] == $id) ? 'selected' : '';
                                                echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                                            }
                                        } else {
                                            echo '<option value="" disabled>Data provinsi tidak tersedia</option>';
                                        }
                                    } else {
                                        // DEBUGGING: Tampilkan path yang dicoba jika gagal
                                        echo '<option value="" disabled>ERROR: API GAGAL DIMUAT</option>';
                                        if (!empty($dw_debug_paths)) {
                                            foreach($dw_debug_paths as $dbg_path) {
                                                echo '<option value="" disabled>Cek: ' . esc_html($dbg_path) . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <?php if (!function_exists('dw_get_provinces')): ?>
                                    <p class="dw-api-status dw-api-error">Fungsi dw_get_provinces() tidak ditemukan. Lihat detail di dropdown.</p>
                                <?php endif; ?>
                            </div>

                            <div class="dw-form-group">
                                <label>Kabupaten / Kota</label>
                                <select name="kota" id="kota" class="dw-form-control" <?php echo empty($data['kota']) ? 'disabled' : ''; ?> data-selected="<?php echo esc_attr($data['kota']); ?>">
                                    <option value="">-- Pilih Kabupaten --</option>
                                </select>
                            </div>

                            <div class="dw-form-group">
                                <label>Kecamatan</label>
                                <select name="kecamatan" id="kecamatan" class="dw-form-control" <?php echo empty($data['kecamatan']) ? 'disabled' : ''; ?> data-selected="<?php echo esc_attr($data['kecamatan']); ?>">
                                    <option value="">-- Pilih Kecamatan --</option>
                                </select>
                            </div>

                            <div class="dw-form-group">
                                <label>Desa / Kelurahan</label>
                                <select name="desa" id="desa" class="dw-form-control" <?php echo empty($data['desa']) ? 'disabled' : ''; ?> data-selected="<?php echo esc_attr($data['desa']); ?>">
                                    <option value="">-- Pilih Desa --</option>
                                </select>
                            </div>

                            <div class="dw-form-group">
                                <label>Alamat Rinci</label>
                                <textarea name="alamat_rinci" class="dw-form-control" rows="3" placeholder="Nama Jalan, RT/RW, Patokan..."><?php echo esc_textarea($data['alamat_lengkap']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Bank Info -->
                    <div class="dw-card" style="border-top-color: #f0b849;">
                        <div class="dw-card-header">
                            <span class="dashicons dashicons-money"></span>
                            <h3>Rekening Bank</h3>
                        </div>
                        <div class="dw-card-body">
                            <div class="dw-form-group">
                                <label>Nama Bank</label>
                                <input type="text" name="nama_bank" class="dw-form-control" value="<?php echo esc_attr($data['nama_bank']); ?>" placeholder="BCA / BRI">
                            </div>
                            <div class="dw-form-group">
                                <label>Nomor Rekening</label>
                                <input type="text" name="no_rekening" class="dw-form-control" value="<?php echo esc_attr($data['no_rekening']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php 
        // --- VIEW: LIST TABLE ---
        else: 
            // Pastikan class table tersedia
            if (!class_exists('DW_Pedagang_List_Table')) {
                // Gunakan path dinamis yang lebih aman untuk list table juga
                $table_path = dirname(__DIR__) . '/list-tables/class-dw-pedagang-list-table.php';
                if (file_exists($table_path)) {
                    require_once $table_path;
                }
            }
            
            if (class_exists('DW_Pedagang_List_Table')) {
                $pedagang_list_table = new DW_Pedagang_List_Table();
                $pedagang_list_table->prepare_items();
                ?>
                <div class="dw-card">
                    <div class="dw-card-body">
                        <form id="pedagang-filter" method="get">
                            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                            <?php $pedagang_list_table->display(); ?>
                        </form>
                    </div>
                </div>
                <?php
            } else {
                echo '<div class="notice notice-error"><p>Error: Class DW_Pedagang_List_Table tidak ditemukan di ' . dirname(__DIR__) . '/list-tables/</p></div>';
            }
        endif; 
        ?>
    </div>

    <!-- SCRIPT JAVASCRIPT: LOGIKA ALAMAT & PREVIEW -->
    <script type="text/javascript">
    function previewImage(input) {
        var preview = document.getElementById('preview-img');
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    jQuery(document).ready(function($) {
        // Fungsi Generic Load Data Wilayah
        function loadRegion(action, id, targetSelector, placeholder) {
            if (!id) return;
            var $target = $(targetSelector);
            $target.prop('disabled', true).html('<option>Sedang memuat...</option>');
            
            // Debugging
            console.log('Loading region: ' + action + ' for ID: ' + id);

            $.ajax({
                url: ajaxurl, // WordPress AJAX URL
                type: 'POST',
                data: {
                    action: action,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        var options = '<option value="">' + placeholder + '</option>';
                        var selectedVal = $target.data('selected'); 
                        
                        $.each(response.data, function(key, value) {
                            var isSelected = (key == selectedVal) ? 'selected' : '';
                            options += '<option value="' + key + '" ' + isSelected + '>' + value + '</option>';
                        });
                        
                        $target.html(options).prop('disabled', false);
                        
                        // Jika ada value tersimpan (mode edit), trigger change ke bawahnya
                        if (selectedVal) {
                            $target.trigger('change');
                            $target.data('selected', ''); // Clear agar tidak loop
                        }
                    } else {
                        $target.html('<option value="">Gagal memuat data</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    $target.html('<option value="">Error Koneksi</option>');
                }
            });
        }

        // 1. Provinsi Change
        $('#provinsi').on('change', function() {
            var id = $(this).val();
            // Reset dropdown di bawahnya
            $('#kota').html('<option value="">-- Pilih Kabupaten --</option>').prop('disabled', true);
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            
            if(id) loadRegion('dw_get_kota', id, '#kota', '-- Pilih Kabupaten --');
        });

        // 2. Kota Change
        $('#kota').on('change', function() {
            var id = $(this).val();
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            
            if(id) loadRegion('dw_get_kecamatan', id, '#kecamatan', '-- Pilih Kecamatan --');
        });

        // 3. Kecamatan Change
        $('#kecamatan').on('change', function() {
            var id = $(this).val();
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            
            if(id) loadRegion('dw_get_desa', id, '#desa', '-- Pilih Desa --');
        });

        // TRIGGER SAAT EDIT MODE (PAGE LOAD)
        var initProv = $('#provinsi').val();
        if (initProv) {
            console.log('Edit Mode Detected. Triggering Province Load.');
            loadRegion('dw_get_kota', initProv, '#kota', '-- Pilih Kabupaten --');
        }
    });
    </script>
    <?php
}