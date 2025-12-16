<?php
/**
 * Halaman Manajemen Pedagang
 * Menangani tampilan (UI), logika penyimpanan (Add/Edit), dan data pedagang.
 */

// Pastikan tidak diakses langsung
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fungsi utama untuk merender halaman Pedagang.
 * Dipanggil oleh dw_render_pedagang() di admin-menus.php
 */
function dw_pedagang_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';
    
    $message = '';
    $message_type = '';
    
    // --- 1. SETUP ACTION (EDIT/ADD/DELETE) ---
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $pedagang_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Default values for form
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
        'foto_toko' => ''
    ];

    // Jika mode EDIT, ambil data dari database
    if ($action == 'edit' && $pedagang_id > 0) {
        $existing_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $pedagang_id), ARRAY_A);
        if ($existing_data) {
            $data = array_merge($data, $existing_data);
        }
    }

    // --- 2. LOGIKA PENYIMPANAN DATA (SUBMIT) ---
    if (isset($_POST['submit_pedagang']) && check_admin_referer('dw_save_pedagang_nonce')) {
        // Sanitize Input
        $nama = sanitize_text_field($_POST['nama_pedagang']);
        $email = sanitize_email($_POST['email_pedagang']);
        $telepon = sanitize_text_field($_POST['telepon_pedagang']);
        $nik = sanitize_text_field($_POST['nik']);
        $nama_toko = sanitize_text_field($_POST['nama_toko']);
        $deskripsi = sanitize_textarea_field($_POST['deskripsi_toko']);
        $nama_bank = sanitize_text_field($_POST['nama_bank']);
        $no_rekening = sanitize_text_field($_POST['no_rekening']);
        
        // Alamat
        $provinsi = sanitize_text_field($_POST['provinsi']);
        $kota = sanitize_text_field($_POST['kota']);
        $kecamatan = sanitize_text_field($_POST['kecamatan']);
        $desa = sanitize_text_field($_POST['desa']);
        $alamat_rinci = sanitize_textarea_field($_POST['alamat_rinci']);

        // Handle File Upload (Foto Toko)
        $foto_url = $data['foto_toko']; // Keep old photo by default
        if (!empty($_FILES['foto_toko']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('foto_toko', 0);
            if (!is_wp_error($attachment_id)) {
                $foto_url = wp_get_attachment_url($attachment_id);
            }
        }

        // Validasi Wajib
        if (empty($nama) || empty($email) || empty($nama_toko)) {
            $message = 'Nama, Email, dan Nama Toko wajib diisi.';
            $message_type = 'error';
        } else {
            // Data array untuk database
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
                'status_verifikasi' => 'pending', // Default pending update
                'updated_at' => current_time('mysql')
            );

            if ($pedagang_id > 0) {
                // UPDATE
                $wpdb->update($table_name, $db_data, ['id' => $pedagang_id]);
                $message = 'Data pedagang berhasil diperbarui.';
                $message_type = 'success';
                $action = 'list'; // Kembali ke list setelah edit
            } else {
                // INSERT
                // Cek email duplikat
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

    // --- 3. TAMPILAN HALAMAN ---
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Toko / Pedagang</h1>
        
        <?php if ($action == 'list'): ?>
            <a href="<?php echo add_query_arg('action', 'add'); ?>" class="page-title-action">Tambah Baru</a>
        <?php else: ?>
            <a href="<?php echo remove_query_arg(['action', 'id']); ?>" class="page-title-action">Kembali ke Daftar</a>
        <?php endif; ?>
        
        <hr class="wp-header-end">

        <?php if (!empty($message)) : ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <?php 
        // --- VIEW: FORM INPUT (ADD / EDIT) ---
        if ($action == 'add' || $action == 'edit'): 
        ?>
            <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">
                <h2><?php echo ($action == 'edit') ? 'Edit Data Pedagang' : 'Tambah Pedagang Baru'; ?></h2>
                
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('dw_save_pedagang_nonce'); ?>
                    
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <!-- Kolom Kiri: Informasi Dasar -->
                        <div style="flex: 1; min-width: 300px;">
                            <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Informasi Pemilik</h3>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th><label for="nama_pedagang">Nama Lengkap <span class="required">*</span></label></th>
                                    <td><input name="nama_pedagang" type="text" id="nama_pedagang" value="<?php echo esc_attr($data['nama_pedagang']); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="nik">NIK (KTP) <span class="required">*</span></label></th>
                                    <td><input name="nik" type="text" id="nik" value="<?php echo esc_attr($data['nik']); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="email_pedagang">Email <span class="required">*</span></label></th>
                                    <td><input name="email_pedagang" type="email" id="email_pedagang" value="<?php echo esc_attr($data['email']); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="telepon_pedagang">No. Telepon / WA</label></th>
                                    <td><input name="telepon_pedagang" type="text" id="telepon_pedagang" value="<?php echo esc_attr($data['telepon']); ?>" class="regular-text"></td>
                                </tr>
                            </table>

                            <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 20px;">Informasi Toko</h3>
                            <table class="form-table">
                                <tr>
                                    <th><label for="nama_toko">Nama Toko <span class="required">*</span></label></th>
                                    <td><input name="nama_toko" type="text" id="nama_toko" value="<?php echo esc_attr($data['nama_toko']); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="deskripsi_toko">Deskripsi</label></th>
                                    <td><textarea name="deskripsi_toko" id="deskripsi_toko" class="large-text" rows="3"><?php echo esc_textarea($data['deskripsi_toko']); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label for="foto_toko">Foto Toko</label></th>
                                    <td>
                                        <?php if (!empty($data['foto_toko'])): ?>
                                            <img src="<?php echo esc_url($data['foto_toko']); ?>" style="max-width: 100px; display: block; margin-bottom: 5px; border: 1px solid #ccc;">
                                        <?php endif; ?>
                                        <input type="file" name="foto_toko" id="foto_toko" accept="image/*">
                                        <p class="description">Format: JPG, PNG. Maks 2MB.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Kolom Kanan: Alamat & Bank -->
                        <div style="flex: 1; min-width: 300px;">
                            <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Lokasi & Alamat</h3>
                            <table class="form-table">
                                <tr>
                                    <th><label for="provinsi">Provinsi</label></th>
                                    <td>
                                        <select name="provinsi" id="provinsi" class="regular-text" style="width: 100%;">
                                            <option value="">-- Pilih Provinsi --</option>
                                            <?php
                                            if (function_exists('dw_get_provinces')) {
                                                $provinces = dw_get_provinces();
                                                foreach ($provinces as $id => $name) {
                                                    $selected = ($data['provinsi'] == $id) ? 'selected' : '';
                                                    echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="kota">Kabupaten/Kota</label></th>
                                    <td>
                                        <select name="kota" id="kota" class="regular-text" style="width: 100%;" <?php echo empty($data['kota']) ? 'disabled' : ''; ?> data-selected="<?php echo esc_attr($data['kota']); ?>">
                                            <option value="">-- Pilih Kabupaten/Kota --</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="kecamatan">Kecamatan</label></th>
                                    <td>
                                        <select name="kecamatan" id="kecamatan" class="regular-text" style="width: 100%;" <?php echo empty($data['kecamatan']) ? 'disabled' : ''; ?> data-selected="<?php echo esc_attr($data['kecamatan']); ?>">
                                            <option value="">-- Pilih Kecamatan --</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="desa">Desa/Kelurahan</label></th>
                                    <td>
                                        <select name="desa" id="desa" class="regular-text" style="width: 100%;" <?php echo empty($data['desa']) ? 'disabled' : ''; ?> data-selected="<?php echo esc_attr($data['desa']); ?>">
                                            <option value="">-- Pilih Desa --</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="alamat_rinci">Alamat Lengkap</label></th>
                                    <td><textarea name="alamat_rinci" id="alamat_rinci" class="large-text" rows="2" placeholder="Jalan, RT/RW..."><?php echo esc_textarea($data['alamat_lengkap']); ?></textarea></td>
                                </tr>
                            </table>

                            <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 20px;">Informasi Bank</h3>
                            <table class="form-table">
                                <tr>
                                    <th><label for="nama_bank">Nama Bank</label></th>
                                    <td><input name="nama_bank" type="text" id="nama_bank" value="<?php echo esc_attr($data['nama_bank']); ?>" class="regular-text" placeholder="Contoh: BCA, BRI"></td>
                                </tr>
                                <tr>
                                    <th><label for="no_rekening">No. Rekening</label></th>
                                    <td><input name="no_rekening" type="text" id="no_rekening" value="<?php echo esc_attr($data['no_rekening']); ?>" class="regular-text"></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <p class="submit" style="margin-top: 30px; border-top: 1px solid #ccc; padding-top: 20px;">
                        <input type="submit" name="submit_pedagang" id="submit" class="button button-primary button-large" value="<?php echo ($action == 'edit') ? 'Simpan Perubahan' : 'Simpan Pedagang Baru'; ?>">
                        <a href="<?php echo remove_query_arg(['action', 'id']); ?>" class="button button-secondary button-large">Batal</a>
                    </p>
                </form>
            </div>

        <?php 
        // --- VIEW: LIST TABLE ---
        else: 
            if (!class_exists('DW_Pedagang_List_Table')) {
                require_once dirname(__DIR__) . '/list-tables/class-dw-pedagang-list-table.php';
            }
            $pedagang_list_table = new DW_Pedagang_List_Table();
            $pedagang_list_table->prepare_items();
        ?>
            <div id="list-pedagang-container" style="margin-top: 20px;">
                <form id="pedagang-filter" method="get">
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                    <?php $pedagang_list_table->display(); ?>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- SCRIPT KHUSUS UNTUK ALAMAT OTOMATIS -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Fungsi generik untuk load data wilayah
        function loadRegion(action, id, targetSelector, placeholder) {
            if (!id) return;
            var $target = $(targetSelector);
            $target.prop('disabled', true).html('<option>Loading...</option>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: action,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        var options = '<option value="">' + placeholder + '</option>';
                        var selectedVal = $target.data('selected'); // Ambil nilai tersimpan (untuk edit mode)
                        
                        $.each(response.data, function(key, value) {
                            var isSelected = (key == selectedVal) ? 'selected' : '';
                            options += '<option value="' + key + '" ' + isSelected + '>' + value + '</option>';
                        });
                        
                        $target.html(options).prop('disabled', false);
                        
                        // Trigger change jika ada selected value agar anak-anaknya juga terload (chaining)
                        if (selectedVal) {
                            $target.trigger('change');
                            $target.data('selected', ''); // Clear data agar tidak re-trigger loop
                        }
                    } else {
                        $target.html('<option value="">Gagal memuat data</option>');
                    }
                },
                error: function() {
                    $target.html('<option value="">Error</option>');
                }
            });
        }

        // 1. Provinsi Change -> Load Kota
        $('#provinsi').on('change', function() {
            var provId = $(this).val();
            // Reset anak-anaknya
            $('#kota').html('<option value="">-- Pilih Kabupaten/Kota --</option>').prop('disabled', true);
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            
            if(provId) {
                // Gunakan action 'dw_get_kota' sesuai standar plugin ini
                loadRegion('dw_get_kota', provId, '#kota', '-- Pilih Kabupaten/Kota --');
            }
        });

        // 2. Kota Change -> Load Kecamatan
        $('#kota').on('change', function() {
            var kotaId = $(this).val();
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            
            if(kotaId) {
                loadRegion('dw_get_kecamatan', kotaId, '#kecamatan', '-- Pilih Kecamatan --');
            }
        });

        // 3. Kecamatan Change -> Load Desa
        $('#kecamatan').on('change', function() {
            var kecId = $(this).val();
            $('#desa').html('<option value="">-- Pilih Desa --</option>').prop('disabled', true);
            
            if(kecId) {
                loadRegion('dw_get_desa', kecId, '#desa', '-- Pilih Desa --');
            }
        });

        // TRIGGER OTOMATIS SAAT EDIT MODE (Page Load)
        // Jika dropdown provinsi sudah terpilih (dari PHP), trigger change untuk load kota, dst.
        // Tapi kita perlu hati-hati agar tidak mereset value yang sudah ada. 
        // Makanya di fungsi loadRegion kita pakai data-selected.
        
        var initProv = $('#provinsi').val();
        if (initProv) {
            // Kita trigger manual load kota dengan ID provinsi saat ini
            loadRegion('dw_get_kota', initProv, '#kota', '-- Pilih Kabupaten/Kota --');
        }
    });
    </script>
    <?php
}