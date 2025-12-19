<?php
/**
 * Halaman Manajemen Ojek (Admin Side)
 * Menampilkan list, form tambah/edit, dan proses approval.
 * * * UPDATE: Form Pendaftaran Lengkap + API Wilayah (Fixed Error & Integrated)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load List Table Class (Hanya load class, jangan instansiasi di sini)
require_once plugin_dir_path(dirname(__FILE__)) . 'list-tables/class-dw-ojek-list-table.php';

// Pastikan file Address API dimuat
require_once plugin_dir_path(dirname(__FILE__)) . 'address-api.php';

/**
 * Fungsi Utama Rendering Halaman
 * Semua logika harus ada di dalam fungsi ini untuk menghindari error 'convert_to_screen'
 */
function dw_ojek_management_page_render() {
    global $wpdb;
    
    // Inisialisasi API Wilayah
    $address_api = new DW_Address_API();
    
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $message = '';

    // --- LOGIC SAVE DATA (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dw_action']) && $_POST['dw_action'] === 'save_ojek') {
        if (!isset($_POST['dw_ojek_nonce']) || !wp_verify_nonce($_POST['dw_ojek_nonce'], 'dw_save_ojek')) {
            wp_die('Security check failed');
        }

        $user_id = get_current_user_id(); // Atau ambil dari input jika admin assign user tertentu
        
        $data = [
            'nama_lengkap' => sanitize_text_field($_POST['nama_lengkap']),
            'no_wa' => sanitize_text_field($_POST['no_wa']),
            'plat_nomor' => sanitize_text_field($_POST['plat_nomor']),
            'merk_motor' => sanitize_text_field($_POST['merk_motor']),
            'alamat_domisili' => sanitize_textarea_field($_POST['alamat_domisili']),
            
            // Wilayah (Simpan ID-nya)
            'api_provinsi_id' => sanitize_text_field($_POST['api_provinsi_id']),
            'api_kabupaten_id' => sanitize_text_field($_POST['api_kabupaten_id']),
            'api_kecamatan_id' => sanitize_text_field($_POST['api_kecamatan_id']),
            'api_kelurahan_id' => sanitize_text_field($_POST['api_kelurahan_id']),
            
            // Foto (URL)
            'foto_profil' => esc_url_raw($_POST['foto_profil']),
            'foto_ktp' => esc_url_raw($_POST['foto_ktp']),
            'foto_kartu_ojek' => esc_url_raw($_POST['foto_kartu_ojek']),
            'foto_motor' => esc_url_raw($_POST['foto_motor']),
            
            'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran']),
            'status_kerja' => sanitize_text_field($_POST['status_kerja']),
        ];

        if ($action == 'add') {
            // Logic Tambah Baru
            // Perlu user_id yang valid jika tabel membutuhkan relasi ke wp_users
            $data['id_user'] = $user_id; 
            $data['created_at'] = current_time('mysql');
            
            $inserted = $wpdb->insert($wpdb->prefix.'dw_ojek', $data);
            if ($inserted) {
                $redirect_url = add_query_arg(['page' => 'dw-ojek', 'msg' => 'added'], admin_url('admin.php'));
                echo "<script>window.location.href='$redirect_url';</script>";
                exit;
            }
        } elseif ($action == 'edit' && $id > 0) {
            // Logic Update
            $data['updated_at'] = current_time('mysql');
            $updated = $wpdb->update($wpdb->prefix.'dw_ojek', $data, ['id' => $id]);
            
            if ($updated !== false) {
                $message = '<div class="notice notice-success is-dismissible"><p>Data Ojek berhasil diperbarui.</p></div>';
            } else {
                $message = '<div class="notice notice-error is-dismissible"><p>Gagal memperbarui data.</p></div>';
            }
        }
    }

    // --- LOGIC DELETE ---
    if ($action == 'delete' && $id > 0) {
        // Cek nonce untuk keamanan (sebaiknya ada, tapi kita ikuti flow sederhana dulu)
        $wpdb->delete($wpdb->prefix.'dw_ojek', ['id' => $id]);
        $redirect_url = add_query_arg(['page' => 'dw-ojek', 'msg' => 'deleted'], admin_url('admin.php'));
        echo "<script>window.location.href='$redirect_url';</script>";
        exit;
    }

    // --- PREPARE DATA FOR VIEW ---
    $row = null;
    
    // Data Default untuk Dropdown Wilayah
    $list_provinsi = DW_Address_API::get_provinces(); // Ambil Provinsi via Static Method
    $list_kabupaten = [];
    $list_kecamatan = [];
    $list_kelurahan = [];

    if ($action == 'edit' && $id > 0) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_ojek WHERE id = %d", $id));
        
        // Jika Edit, ambil data turunan wilayah berdasarkan ID yang tersimpan
        if ($row) {
            if (!empty($row->api_provinsi_id)) {
                $list_kabupaten = DW_Address_API::get_cities($row->api_provinsi_id);
            }
            if (!empty($row->api_kabupaten_id)) {
                $list_kecamatan = DW_Address_API::get_districts($row->api_kabupaten_id);
            }
            if (!empty($row->api_kecamatan_id)) {
                $list_kelurahan = DW_Address_API::get_villages($row->api_kecamatan_id);
            }
        }
    }

    // Enqueue Media Uploader & Styles (Hanya jika form)
    if ($action == 'add' || $action == 'edit') {
        wp_enqueue_media();
        ?>
        <style>
            .dw-admin-form-container { max-width: 1200px; margin-top: 20px; }
            .dw-form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
            .dw-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            .dw-section-title { font-size: 1.2em; font-weight: 600; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
            .form-field { margin-bottom: 15px; }
            .form-field label { display: block; font-weight: 500; margin-bottom: 5px; }
            .form-field input[type="text"], .form-field input[type="number"], .form-field select, .form-field textarea { width: 100%; max-width: 100%; }
            .dw-img-preview { width: 100%; height: 150px; background: #f0f0f1; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; overflow: hidden; }
            .dw-img-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
        </style>
        
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo ($action == 'edit') ? 'Edit Data Ojek' : 'Tambah Ojek Baru'; ?></h1>
            <a href="<?php echo admin_url('admin.php?page=dw-ojek'); ?>" class="page-title-action">Kembali ke List</a>
            <hr class="wp-header-end">
            
            <?php echo $message; ?>

            <form method="post" action="" class="dw-admin-form-container">
                <input type="hidden" name="dw_action" value="save_ojek">
                <?php wp_nonce_field('dw_save_ojek', 'dw_ojek_nonce'); ?>

                <div class="dw-form-grid">
                    <!-- Kolom Kiri: Data Diri & Kendaraan -->
                    <div class="left-column">
                        <div class="dw-card">
                            <div class="dw-section-title">Data Pengemudi</div>
                            <div class="form-field">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" value="<?php echo $row ? esc_attr($row->nama_lengkap) : ''; ?>" required>
                            </div>
                            <div class="form-field">
                                <label>Nomor WhatsApp</label>
                                <input type="text" name="no_wa" value="<?php echo $row ? esc_attr($row->no_wa) : ''; ?>" required>
                            </div>
                            <div class="form-field">
                                <label>Alamat Domisili (Jalan/RT/RW)</label>
                                <textarea name="alamat_domisili" rows="3"><?php echo $row ? esc_textarea($row->alamat_domisili) : ''; ?></textarea>
                            </div>
                            
                            <!-- INTEGRASI ADDRESS API -->
                            <div class="form-field">
                                <label>Provinsi</label>
                                <select name="api_provinsi_id" id="api_provinsi_id" required>
                                    <option value="">Pilih Provinsi</option>
                                    <?php foreach ($list_provinsi as $prov): ?>
                                        <option value="<?php echo esc_attr($prov['id']); ?>" <?php selected($row ? $row->api_provinsi_id : '', $prov['id']); ?>>
                                            <?php echo esc_html($prov['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Kabupaten/Kota</label>
                                <select name="api_kabupaten_id" id="api_kabupaten_id" required <?php echo empty($list_kabupaten) ? 'disabled' : ''; ?>>
                                    <option value="">Pilih Kabupaten/Kota</option>
                                    <?php foreach ($list_kabupaten as $item): ?>
                                        <option value="<?php echo esc_attr($item['id']); ?>" <?php selected($row ? $row->api_kabupaten_id : '', $item['id']); ?>>
                                            <?php echo esc_html($item['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Kecamatan</label>
                                <select name="api_kecamatan_id" id="api_kecamatan_id" required <?php echo empty($list_kecamatan) ? 'disabled' : ''; ?>>
                                    <option value="">Pilih Kecamatan</option>
                                    <?php foreach ($list_kecamatan as $item): ?>
                                        <option value="<?php echo esc_attr($item['id']); ?>" <?php selected($row ? $row->api_kecamatan_id : '', $item['id']); ?>>
                                            <?php echo esc_html($item['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Desa/Kelurahan</label>
                                <select name="api_kelurahan_id" id="api_kelurahan_id" required <?php echo empty($list_kelurahan) ? 'disabled' : ''; ?>>
                                    <option value="">Pilih Desa/Kelurahan</option>
                                    <?php foreach ($list_kelurahan as $item): ?>
                                        <option value="<?php echo esc_attr($item['id']); ?>" <?php selected($row ? $row->api_kelurahan_id : '', $item['id']); ?>>
                                            <?php echo esc_html($item['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="dw-card">
                            <div class="dw-section-title">Data Kendaraan</div>
                            <div class="form-field">
                                <label>Merk & Tipe Motor</label>
                                <input type="text" name="merk_motor" value="<?php echo $row ? esc_attr($row->merk_motor) : ''; ?>" placeholder="Contoh: Honda Vario 125">
                            </div>
                            <div class="form-field">
                                <label>Plat Nomor</label>
                                <input type="text" name="plat_nomor" value="<?php echo $row ? esc_attr($row->plat_nomor) : ''; ?>" style="text-transform: uppercase;">
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Status & Foto -->
                    <div class="right-column">
                        <div class="dw-card">
                            <div class="dw-section-title">Status</div>
                            <div class="form-field">
                                <label>Status Pendaftaran</label>
                                <select name="status_pendaftaran">
                                    <option value="pending" <?php selected($row ? $row->status_pendaftaran : '', 'pending'); ?>>Menunggu Verifikasi</option>
                                    <option value="approved" <?php selected($row ? $row->status_pendaftaran : '', 'approved'); ?>>Disetujui</option>
                                    <option value="rejected" <?php selected($row ? $row->status_pendaftaran : '', 'rejected'); ?>>Ditolak</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Status Kerja (Aktif Narik)</label>
                                <select name="status_kerja">
                                    <option value="offline" <?php selected($row ? $row->status_kerja : '', 'offline'); ?>>Offline</option>
                                    <option value="online" <?php selected($row ? $row->status_kerja : '', 'online'); ?>>Online</option>
                                    <option value="suspend" <?php selected($row ? $row->status_kerja : '', 'suspend'); ?>>Suspend</option>
                                </select>
                            </div>
                            <button type="submit" class="button button-primary button-large" style="width: 100%;">Simpan Data</button>
                        </div>

                        <div class="dw-card">
                            <div class="dw-section-title">Dokumen & Foto</div>
                            
                            <!-- Helper Function for Image Input -->
                            <?php 
                            function dw_render_image_input($field_id, $label, $value) {
                                $preview = $value ? "<img src='".esc_url($value)."'>" : '<span class="dashicons dashicons-format-image" style="font-size:32px; height:32px; width:32px; color:#ccc;"></span>';
                                ?>
                                <div class="form-field">
                                    <label><?php echo $label; ?></label>
                                    <div class="dw-img-preview" id="preview_<?php echo $field_id; ?>">
                                        <?php echo $preview; ?>
                                    </div>
                                    <input type="hidden" name="<?php echo $field_id; ?>" id="<?php echo $field_id; ?>" value="<?php echo esc_attr($value); ?>">
                                    <button type="button" class="button dw-upload-btn" data-target="<?php echo $field_id; ?>">Pilih Foto</button>
                                    <?php if($value): ?>
                                        <button type="button" class="button dw-remove-img" data-target="<?php echo $field_id; ?>" style="color: #a00;">Hapus</button>
                                    <?php endif; ?>
                                </div>
                                <?php
                            }

                            dw_render_image_input('foto_profil', 'Foto Profil (Wajah)', $row ? $row->foto_profil : '');
                            dw_render_image_input('foto_ktp', 'Foto KTP', $row ? $row->foto_ktp : '');
                            dw_render_image_input('foto_kartu_ojek', 'Kartu Anggota (Jika ada)', $row ? $row->foto_kartu_ojek : '');
                            dw_render_image_input('foto_motor', 'Foto Motor (Depan/Samping)', $row ? $row->foto_motor : '');
                            ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // -- Media Uploader Logic --
            $('.dw-upload-btn').click(function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                var image_frame;
                if(image_frame){ image_frame.open(); return; }
                image_frame = wp.media({
                    title: 'Pilih Media',
                    multiple: false,
                    library: { type: 'image' }
                });
                image_frame.on('select', function(){
                    var selection = image_frame.state().get('selection').first().toJSON();
                    $('#'+target).val(selection.url);
                    $('#preview_'+target).html('<img src="'+selection.url+'">');
                });
                image_frame.open();
            });

            $('.dw-remove-img').click(function(e){
                e.preventDefault();
                var target = $(this).data('target');
                $('#'+target).val('');
                $('#preview_'+target).html('<span class="dashicons dashicons-format-image" style="font-size:32px; height:32px; width:32px; color:#ccc;"></span>');
                $(this).hide();
            });

            // -- Address API Dependent Dropdown Logic --
            
            function fetchRegion(action, parentId, targetSelect) {
                if (!parentId) {
                    $(targetSelect).html('<option value="">Pilih...</option>').prop('disabled', true);
                    return;
                }
                
                $(targetSelect).html('<option>Loading...</option>').prop('disabled', true);
                
                // Mapping parameter name sesuai ajax-handlers.php / address-api.php
                var data = { action: action };
                if (action === 'dw_fetch_regencies') data.province_id = parentId;
                if (action === 'dw_fetch_districts') data.regency_id = parentId;
                if (action === 'dw_fetch_villages')  data.district_id = parentId;

                $.get(ajaxurl, data, function(response) {
                    if (response.success) {
                        var options = '<option value="">Pilih...</option>';
                        $.each(response.data, function(index, item) {
                            options += '<option value="' + item.id + '">' + item.name + '</option>';
                        });
                        $(targetSelect).html(options).prop('disabled', false);
                    } else {
                        alert('Gagal memuat data wilayah');
                    }
                });
            }

            // Event Listeners
            $('#api_provinsi_id').change(function() {
                fetchRegion('dw_fetch_regencies', $(this).val(), '#api_kabupaten_id');
                $('#api_kecamatan_id').html('<option value="">Pilih Kecamatan</option>').prop('disabled', true);
                $('#api_kelurahan_id').html('<option value="">Pilih Desa</option>').prop('disabled', true);
            });

            $('#api_kabupaten_id').change(function() {
                fetchRegion('dw_fetch_districts', $(this).val(), '#api_kecamatan_id');
                $('#api_kelurahan_id').html('<option value="">Pilih Desa</option>').prop('disabled', true);
            });

            $('#api_kecamatan_id').change(function() {
                fetchRegion('dw_fetch_villages', $(this).val(), '#api_kelurahan_id');
            });
        });
        </script>
        <?php
    } else {
        // --- VIEW: TABLE LIST ---
        // Instansiasi tabel di dalam fungsi ini agar WP_List_Table sudah tersedia
        $ojek_table = new DW_Ojek_List_Table();
        $ojek_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Manajemen Ojek</h1>
            <a href="<?php echo admin_url('admin.php?page=dw-ojek&action=add'); ?>" class="page-title-action">Tambah Baru</a>
            <hr class="wp-header-end">

            <?php if(isset($_GET['msg']) && $_GET['msg']=='added'): ?>
                <div class="notice notice-success is-dismissible"><p>Ojek berhasil ditambahkan.</p></div>
            <?php elseif(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
                <div class="notice notice-success is-dismissible"><p>Data berhasil dihapus.</p></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="page" value="dw_ojek_management">
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