<?php
/**
 * Halaman Manajemen Pedagang
 * UI/UX Updated: Modern Card Layout & Fixed Address Logic
 */

// Pastikan tidak diakses langsung
if (!defined('ABSPATH')) {
    exit;
}

// --- PERBAIKAN: LOGIKA LOAD API ALAMAT YANG LEBIH KUAT ---
if (!function_exists('dw_get_provinces')) {
    // Cara 1: Coba pakai Konstanta Plugin (jika ada)
    $loaded = false;
    if (defined('DW_CORE_PLUGIN_DIR')) {
        $api_path = DW_CORE_PLUGIN_DIR . 'includes/address-api.php';
        if (file_exists($api_path)) {
            require_once $api_path;
            $loaded = true;
        }
    }

    // Cara 2: Fallback pakai Relative Path (jika Cara 1 gagal)
    if (!$loaded || !function_exists('dw_get_provinces')) {
        // Naik satu folder dari /admin-pages/ ke /includes/
        $manual_path = dirname(dirname(__FILE__)) . '/address-api.php';
        if (file_exists($manual_path)) {
            require_once $manual_path;
        }
    }
}

/**
 * Fungsi utama render page
 */
function dw_pedagang_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';
    
    $message = '';
    $message_type = '';
    
    // --- 1. SETUP ACTION & DATA ---
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $pedagang_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Default data structure
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

    // Load existing data for EDIT
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

        // Validation
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

    // --- 3. CUSTOM STYLES (INLINE FOR SIMPLICITY) ---
    ?>
    <style>
        .dw-pedagang-wrap { max-width: 1200px; margin-top: 20px; }
        .dw-card { background: #fff; border: 1px solid #dcdcde; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 6px; padding: 20px; margin-bottom: 20px; }
        .dw-card-header { border-bottom: 1px solid #f0f0f1; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .dw-card-header h3 { margin: 0; font-size: 16px; font-weight: 600; color: #1d2327; }
        
        .dw-grid-2 { display: grid; grid-template-columns: 3fr 2fr; gap: 25px; }
        @media (max-width: 960px) { .dw-grid-2 { grid-template-columns: 1fr; } }

        .dw-form-group { margin-bottom: 15px; }
        .dw-form-group label { display: block; font-weight: 500; margin-bottom: 6px; color: #2c3338; }
        .dw-form-group .required { color: #d63638; }
        .dw-form-control { width: 100%; padding: 0 12px; height: 40px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        .dw-form-control:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
        textarea.dw-form-control { height: auto; padding-top: 10px; resize: vertical; }
        
        .dw-photo-upload-area { display: flex; align-items: center; gap: 15px; background: #f6f7f7; padding: 15px; border-radius: 4px; border: 1px dashed #c3c4c7; }
        .dw-photo-preview { width: 80px; height: 80px; border-radius: 4px; background: #ddd; object-fit: cover; display: none; }
        .dw-photo-preview.active { display: block; }
        
        .dw-action-bar { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .dw-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .dw-badge-pending { background: #f0b849; color: #fff; }
        .dw-badge-verified { background: #4ab866; color: #fff; }
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
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible" style="margin-left: 0;">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <?php 
        // --- VIEW: FORM INPUT (ADD / EDIT) ---
        if ($action == 'add' || $action == 'edit'): 
        ?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('dw_save_pedagang_nonce'); ?>
            
            <div class="dw-grid-2">
                <!-- LEFT COLUMN: PRIMARY INFO -->
                <div class="dw-col-left">
                    <!-- Card: Informasi Toko -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3><span class="dashicons dashicons-store" style="margin-right: 5px;"></span> Informasi Toko</h3>
                        </div>
                        
                        <div class="dw-form-group">
                            <label>Nama Toko / Usaha <span class="required">*</span></label>
                            <input type="text" name="nama_toko" class="dw-form-control" value="<?php echo esc_attr($data['nama_toko']); ?>" required placeholder="Contoh: Keripik Singkong Barokah">
                        </div>
                        
                        <div class="dw-form-group">
                            <label>Deskripsi Singkat</label>
                            <textarea name="deskripsi_toko" class="dw-form-control" rows="4"><?php echo esc_textarea($data['deskripsi_toko']); ?></textarea>
                        </div>

                        <div class="dw-form-group">
                            <label>Foto Profil Toko</label>
                            <div class="dw-photo-upload-area">
                                <?php $has_photo = !empty($data['foto_toko']); ?>
                                <img id="preview-img" src="<?php echo $has_photo ? esc_url($data['foto_toko']) : ''; ?>" class="dw-photo-preview <?php echo $has_photo ? 'active' : ''; ?>">
                                <div>
                                    <input type="file" name="foto_toko" id="foto_toko" accept="image/*" onchange="previewImage(this)">
                                    <p class="description" style="margin-top: 5px;">Format: JPG, PNG. Maksimal 2MB.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Informasi Pemilik -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3><span class="dashicons dashicons-admin-users" style="margin-right: 5px;"></span> Data Pemilik</h3>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="dw-form-group">
                                <label>Nama Lengkap <span class="required">*</span></label>
                                <input type="text" name="nama_pedagang" class="dw-form-control" value="<?php echo esc_attr($data['nama_pedagang']); ?>" required>
                            </div>
                            <div class="dw-form-group">
                                <label>NIK (KTP) <span class="required">*</span></label>
                                <input type="text" name="nik" class="dw-form-control" value="<?php echo esc_attr($data['nik']); ?>" required>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="dw-form-group">
                                <label>Email <span class="required">*</span></label>
                                <input type="email" name="email_pedagang" class="dw-form-control" value="<?php echo esc_attr($data['email']); ?>" required>
                            </div>
                            <div class="dw-form-group">
                                <label>No. WhatsApp / HP</label>
                                <input type="text" name="telepon_pedagang" class="dw-form-control" value="<?php echo esc_attr($data['telepon']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: ADDRESS & MISC -->
                <div class="dw-col-right">
                    <!-- Card: Status Verifikasi -->
                    <div class="dw-card" style="border-top: 3px solid #2271b1;">
                        <div class="dw-card-header">
                            <h3>Status Verifikasi</h3>
                        </div>
                        <div class="dw-form-group">
                            <select name="status_verifikasi" class="dw-form-control">
                                <option value="pending" <?php selected($data['status_verifikasi'], 'pending'); ?>>Menunggu Verifikasi (Pending)</option>
                                <option value="verified" <?php selected($data['status_verifikasi'], 'verified'); ?>>Terverifikasi</option>
                                <option value="rejected" <?php selected($data['status_verifikasi'], 'rejected'); ?>>Ditolak</option>
                            </select>
                        </div>
                        <div class="dw-action-bar" style="margin-top: 10px; justify-content: space-between;">
                            <a href="<?php echo remove_query_arg(['action', 'id']); ?>" class="button">Batal</a>
                            <button type="submit" name="submit_pedagang" class="button button-primary">Simpan Data</button>
                        </div>
                    </div>

                    <!-- Card: Lokasi & Alamat -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3><span class="dashicons dashicons-location" style="margin-right: 5px;"></span> Alamat Lengkap</h3>
                        </div>

                        <div class="dw-form-group">
                            <label>Provinsi</label>
                            <select name="provinsi" id="provinsi" class="dw-form-control">
                                <option value="">-- Pilih Provinsi --</option>
                                <?php
                                if (function_exists('dw_get_provinces')) {
                                    $provinces = dw_get_provinces();
                                    if ($provinces) {
                                        foreach ($provinces as $id => $name) {
                                            $selected = ($data['provinsi'] == $id) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                                        }
                                    } else {
                                        echo '<option value="" disabled>Data provinsi kosong/gagal dimuat</option>';
                                    }
                                } else {
                                    echo '<option value="" disabled>API Helper tidak ditemukan</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="dw-form-group">
                            <label>Kabupaten / Kota</label>
                            <select name="kota" id="kota" class="dw-form-control" <?php echo empty($data['kota']) ? 'disabled' : ''; ?> data-selected="<?php echo esc_attr($data['kota']); ?>">
                                <option value="">-- Pilih Kabupaten/Kota --</option>
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
                            <label>Alamat Rinci (Jalan, RT/RW)</label>
                            <textarea name="alamat_rinci" class="dw-form-control" rows="2"><?php echo esc_textarea($data['alamat_lengkap']); ?></textarea>
                        </div>
                    </div>

                    <!-- Card: Bank -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3><span class="dashicons dashicons-money" style="margin-right: 5px;"></span> Rekening Bank</h3>
                        </div>
                        <div class="dw-form-group">
                            <label>Nama Bank</label>
                            <input type="text" name="nama_bank" class="dw-form-control" value="<?php echo esc_attr($data['nama_bank']); ?>" placeholder="BCA / BRI / Mandiri">
                        </div>
                        <div class="dw-form-group">
                            <label>Nomor Rekening</label>
                            <input type="text" name="no_rekening" class="dw-form-control" value="<?php echo esc_attr($data['no_rekening']); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php 
        // --- VIEW: LIST TABLE ---
        else: 
            if (!class_exists('DW_Pedagang_List_Table')) {
                require_once dirname(__DIR__) . '/list-tables/class-dw-pedagang-list-table.php';
            }
            $pedagang_list_table = new DW_Pedagang_List_Table();
            $pedagang_list_table->prepare_items();
        ?>
            <div class="dw-card">
                <form id="pedagang-filter" method="get">
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                    <?php $pedagang_list_table->display(); ?>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script type="text/javascript">
    // Fungsi Preview Image Sederhana
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
        // === LOGIKA ALAMAT (AJAX) ===
        
        function loadRegion(action, id, targetSelector, placeholder) {
            if (!id) return;
            var $target = $(targetSelector);
            $target.prop('disabled', true).html('<option>Memuat data...</option>');
            
            $.ajax({
                url: ajaxurl, // WordPress global ajaxurl
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
                        
                        // Trigger change untuk load level di bawahnya jika sedang edit
                        if (selectedVal) {
                            $target.trigger('change');
                            $target.data('selected', ''); // Clear agar tidak looping
                        }
                    } else {
                        $target.html('<option value="">Gagal memuat data</option>');
                        console.error('API Error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    $target.html('<option value="">Koneksi Error</option>');
                    console.error('AJAX Error:', error);
                }
            });
        }

        // Event Listeners
        $('#provinsi').on('change', function() {
            var id = $(this).val();
            $('#kota').html('<option value="">-- Pilih Kabupaten/Kota --</option>').prop('disabled', true);
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            if(id) loadRegion('dw_get_kota', id, '#kota', '-- Pilih Kabupaten/Kota --');
        });

        $('#kota').on('change', function() {
            var id = $(this).val();
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            if(id) loadRegion('dw_get_kecamatan', id, '#kecamatan', '-- Pilih Kecamatan --');
        });

        $('#kecamatan').on('change', function() {
            var id = $(this).val();
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            if(id) loadRegion('dw_get_desa', id, '#desa', '-- Pilih Desa --');
        });

        // Trigger on Edit Mode Load
        var initProv = $('#provinsi').val();
        if (initProv) {
            // Trigger manual load tanpa mengubah value yg sudah ada
            loadRegion('dw_get_kota', initProv, '#kota', '-- Pilih Kabupaten/Kota --');
        }
    });
    </script>
    <?php
}