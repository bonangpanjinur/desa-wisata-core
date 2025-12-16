<?php
/**
 * File Name:   includes/admin-pages/page-pedagang.php
 * Description: Manajemen Toko / Pedagang (Auto Relasi Desa by Alamat).
 */

if (!defined('ABSPATH')) exit;

// Pastikan Media Enqueue diload untuk upload foto
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_media();
});

function dw_pedagang_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';
    $table_desa = $wpdb->prefix . 'dw_desa';
    $message = '';
    $message_type = '';

    // --- 1. LOGIC: SAVE / UPDATE / DELETE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pedagang'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_pedagang_action')) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>'; return;
        }

        $action = sanitize_text_field($_POST['action_pedagang']);

        // DELETE
        if ($action === 'delete' && !empty($_POST['pedagang_id'])) {
            $deleted = $wpdb->delete($table_name, ['id' => intval($_POST['pedagang_id'])]);
            if ($deleted !== false) {
                $message = 'Data pedagang berhasil dihapus.'; $message_type = 'success';
            } else {
                $message = 'Gagal menghapus pedagang: ' . $wpdb->last_error; $message_type = 'error';
            }
        
        // SAVE / UPDATE
        } elseif ($action === 'save') {
            
            // Auto generate slug
            $nama_toko = sanitize_text_field($_POST['nama_toko']);
            $slug_toko = sanitize_title($nama_toko);

            // Sanitasi Checkbox
            $ojek_aktif = isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0;
            $nasional_aktif = isset($_POST['shipping_nasional_aktif']) ? 1 : 0;
            $pesan_di_tempat = isset($_POST['allow_pesan_di_tempat']) ? 1 : 0;
            
            // --- LOGIKA OTOMATISASI RELASI DESA ---
            // Cek apakah ada Desa Wisata di kelurahan ini
            $kelurahan_id = sanitize_text_field($_POST['kelurahan_id']); // ID API Kelurahan
            $determined_id_desa = 0; // Default: Independen

            if (!empty($kelurahan_id)) {
                // Cari ID Desa yang punya api_kelurahan_id sama
                $found_desa = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_desa WHERE api_kelurahan_id = %s LIMIT 1", 
                    $kelurahan_id
                ));
                
                if ($found_desa) {
                    $determined_id_desa = $found_desa; // Relasi Otomatis
                }
            }
            // -------------------------------------

            $input = [
                'id_user'           => intval($_POST['id_user']),
                'id_desa'           => $determined_id_desa, // Hasil auto-detect
                'nama_toko'         => $nama_toko,
                'slug_toko'         => $slug_toko,
                'nama_pemilik'      => sanitize_text_field($_POST['nama_pemilik']),
                'nomor_wa'          => sanitize_text_field($_POST['nomor_wa']),
                'nik'               => sanitize_text_field($_POST['nik']),
                
                // URLs & Uploads (Profil & Sampul)
                'foto_profil'       => esc_url_raw($_POST['foto_profil_url']),
                'foto_sampul'       => esc_url_raw($_POST['foto_sampul_url']),
                'url_ktp'           => esc_url_raw($_POST['url_ktp']),
                'url_gmaps'         => esc_url_raw($_POST['url_gmaps']),
                
                // Keuangan
                'nama_bank'         => sanitize_text_field($_POST['nama_bank']),
                'no_rekening'       => sanitize_text_field($_POST['no_rekening']),
                'atas_nama_rekening'=> sanitize_text_field($_POST['atas_nama_rekening']),
                'qris_image_url'    => esc_url_raw($_POST['qris_image_url']),
                
                // Status & Stats
                'status_pendaftaran'=> sanitize_text_field($_POST['status_pendaftaran']),
                'status_akun'       => sanitize_text_field($_POST['status_akun']),
                'rating_toko'       => floatval($_POST['rating_toko']),
                'sisa_transaksi'    => intval($_POST['sisa_transaksi']),
                
                // Shipping
                'shipping_ojek_lokal_aktif' => $ojek_aktif,
                'shipping_ojek_lokal_zona'  => sanitize_textarea_field($_POST['shipping_ojek_lokal_zona']),
                'shipping_nasional_aktif'   => $nasional_aktif,
                'shipping_nasional_harga'   => floatval($_POST['shipping_nasional_harga']),
                'allow_pesan_di_tempat'     => $pesan_di_tempat,

                // Wilayah API (ID)
                'api_provinsi_id'   => sanitize_text_field($_POST['provinsi_id']),
                'api_kabupaten_id'  => sanitize_text_field($_POST['kabupaten_id']),
                'api_kecamatan_id'  => sanitize_text_field($_POST['kecamatan_id']),
                'api_kelurahan_id'  => sanitize_text_field($_POST['kelurahan_id']),
                
                // Wilayah Nama
                'provinsi_nama'     => sanitize_text_field($_POST['provinsi_nama']),
                'kabupaten_nama'    => sanitize_text_field($_POST['kabupaten_nama']),
                'kecamatan_nama'    => sanitize_text_field($_POST['kecamatan_nama']),
                'kelurahan_nama'    => sanitize_text_field($_POST['desa_nama']),
                'alamat_lengkap'    => sanitize_textarea_field($_POST['alamat_lengkap']),
                
                'created_at'        => current_time('mysql')
            ];

            // Validasi Dasar
            if (empty($input['nama_pemilik']) || empty($input['nama_toko']) || empty($input['id_user'])) {
                $message = 'Nama Pemilik, Nama Toko, dan Akun User wajib diisi.'; 
                $message_type = 'error';
            } else {
                if (!empty($_POST['pedagang_id'])) {
                    // UPDATE
                    unset($input['created_at']);
                    $result = $wpdb->update($table_name, $input, ['id' => intval($_POST['pedagang_id'])]);
                    $message = 'Data pedagang diperbarui. ';
                    
                    // Feedback status relasi
                    if ($determined_id_desa > 0) {
                        $message .= 'Toko terhubung ke Desa Wisata setempat.';
                    } else {
                        $message .= 'Status Toko: Independen (Tidak ada Desa terdaftar di lokasi ini).';
                    }
                    $message_type = 'success';
                } else {
                    // INSERT
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE id_user = %d", $input['id_user']));
                    if ($exists) {
                        $message = 'User ini sudah memiliki toko.'; $message_type = 'error';
                    } else {
                        $result = $wpdb->insert($table_name, $input);
                        $message = 'Pedagang baru ditambahkan. ';
                        if ($determined_id_desa > 0) {
                            $message .= 'Otomatis terhubung ke Desa Wisata.';
                        } else {
                            $message .= 'Status Toko: Independen.';
                        }
                        $message_type = 'success';
                    }
                }
            }
        }
    }

    // --- 2. PREPARE DATA FOR VIEW ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    $nama_desa_terkait = 'Independen'; // Default text

    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
        
        // Ambil nama desa jika ada relasi
        if ($edit_data && $edit_data->id_desa) {
            $nama_desa_terkait = $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM $table_desa WHERE id = %d", $edit_data->id_desa));
        }
    }

    // Helper Data Dropdowns
    $users = get_users(['role__in' => ['subscriber', 'customer', 'pedagang', 'administrator']]);
    
    // Load Helper Wilayah
    if (!function_exists('dw_get_api_provinsi')) {
        $helper_path = dirname(dirname(__FILE__)) . '/address-api.php';
        if(file_exists($helper_path)) include_once $helper_path;
    }
    $provinsi_list  = function_exists('dw_get_api_provinsi') ? dw_get_api_provinsi() : [];
    
    ?>
    <style>
        .dw-tab-nav { display:flex; border-bottom:1px solid #c3c4c7; margin-bottom:20px; gap:5px; }
        .dw-tab-btn { padding:10px 20px; border:1px solid transparent; border-bottom:none; cursor:pointer; background:#f0f0f1; font-weight:600; text-decoration:none; color:#3c434a; border-radius:4px 4px 0 0; }
        .dw-tab-btn.active { background:#fff; border-color:#c3c4c7; border-bottom-color:#fff; color:#2271b1; margin-bottom:-1px; }
        .dw-tab-content { display:none; }
        .dw-tab-content.active { display:block; }
        .dw-readonly-box { background: #f0f0f1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; color: #555; }
    </style>

    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Toko & Pedagang</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-pedagang&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            
            <!-- === FORM VIEW (ADD / EDIT) === -->
            <div class="card" style="padding: 0; max-width: 100%; margin-top: 20px; overflow:hidden;">
                
                <form method="post" id="form-pedagang">
                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                    <input type="hidden" name="action_pedagang" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <!-- Tab Navigation -->
                    <div style="background:#f6f7f7; padding:15px 20px 0 20px; border-bottom:1px solid #dfdfdf;">
                        <div class="dw-tab-nav">
                            <a href="#tab-info" class="dw-tab-btn active">Informasi Utama</a>
                            <a href="#tab-lokasi" class="dw-tab-btn">Lokasi & Alamat</a>
                            <a href="#tab-keuangan" class="dw-tab-btn">Keuangan & Legalitas</a>
                            <a href="#tab-pengiriman" class="dw-tab-btn">Pengiriman & Status</a>
                        </div>
                    </div>

                    <div style="padding:20px;">
                        
                        <!-- TAB 1: INFORMASI UTAMA -->
                        <div id="tab-info" class="dw-tab-content active">
                            <table class="form-table">
                                <tr>
                                    <th>Akun Pemilik (User WP)</th>
                                    <td>
                                        <select name="id_user" class="regular-text" required>
                                            <option value="">-- Pilih User --</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo $u->ID; ?>" <?php selected($edit_data ? $edit_data->id_user : '', $u->ID); ?>><?php echo $u->display_name; ?> (<?php echo $u->user_login; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>Nama Toko / Usaha</th>
                                    <td><input name="nama_toko" type="text" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="regular-text" required placeholder="Contoh: Keripik Singkong Barokah"></td>
                                </tr>
                                <tr>
                                    <th>Nama Pemilik (Sesuai KTP)</th>
                                    <td><input name="nama_pemilik" type="text" value="<?php echo esc_attr($edit_data->nama_pemilik ?? ''); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th>Nomor WhatsApp</th>
                                    <td><input name="nomor_wa" type="text" value="<?php echo esc_attr($edit_data->nomor_wa ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>NIK</th>
                                    <td><input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                
                                <!-- FOTO PROFIL -->
                                <tr>
                                    <th>Foto Profil (Logo)</th>
                                    <td>
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <div style="flex-grow:0;">
                                                <input type="text" name="foto_profil_url" id="foto_profil_url" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>" class="regular-text">
                                                <button type="button" class="button" id="btn_upload_profil">Upload Logo</button>
                                            </div>
                                            <img id="preview_foto_profil" src="<?php echo !empty($edit_data->foto_profil) ? esc_url($edit_data->foto_profil) : 'https://placehold.co/80x80?text=Logo'; ?>" style="height:80px; width:80px; object-fit:cover; border-radius:50%; border:1px solid #ddd;">
                                        </div>
                                    </td>
                                </tr>

                                <!-- FOTO SAMPUL -->
                                <tr>
                                    <th>Foto Sampul (Banner)</th>
                                    <td>
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <div style="flex-grow:0;">
                                                <input type="text" name="foto_sampul_url" id="foto_sampul_url" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>" class="regular-text">
                                                <button type="button" class="button" id="btn_upload_sampul">Upload Banner</button>
                                            </div>
                                            <img id="preview_foto_sampul" src="<?php echo !empty($edit_data->foto_sampul) ? esc_url($edit_data->foto_sampul) : 'https://placehold.co/200x80?text=Banner'; ?>" style="height:80px; width:200px; object-fit:cover; border-radius:4px; border:1px solid #ddd;">
                                        </div>
                                        <p class="description">Disarankan rasio 3:1 (Contoh: 1200x400px)</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- TAB 2: LOKASI -->
                        <div id="tab-lokasi" class="dw-tab-content">
                             <table class="form-table">
                                <!-- INFORMASI STATUS RELASI (READONLY) -->
                                <tr>
                                    <th>Afiliasi Desa</th>
                                    <td>
                                        <div class="dw-readonly-box">
                                            <strong><?php echo esc_html($nama_desa_terkait); ?></strong>
                                            <p class="description" style="margin:5px 0 0 0;">
                                                Status ini otomatis ditentukan oleh sistem berdasarkan <strong>Kelurahan/Desa</strong> yang dipilih di bawah.
                                                <br>Jika di lokasi tersebut terdaftar Desa Wisata, toko akan otomatis terhubung.
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Provinsi</th>
                                    <td>
                                        <select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select regular-text">
                                            <option value="">-- Pilih Provinsi --</option>
                                            <?php foreach ($provinsi_list as $prov) : ?>
                                                <option value="<?php echo esc_attr($prov['code']); ?>"><?php echo esc_html($prov['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if(!empty($edit_data->provinsi_nama)): ?>
                                            <p class="description">Tersimpan: <?php echo esc_html($edit_data->provinsi_nama . ', ' . $edit_data->kabupaten_nama); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Kabupaten/Kota</th>
                                    <td><select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select regular-text" disabled><option value="">-- Pilih Kabupaten --</option></select></td>
                                </tr>
                                <tr>
                                    <th>Kecamatan</th>
                                    <td><select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select regular-text" disabled><option value="">-- Pilih Kecamatan --</option></select></td>
                                </tr>
                                <tr>
                                    <th>Kelurahan/Desa</th>
                                    <td><select name="kelurahan_id" id="dw_desa" class="dw-desa-select regular-text" disabled><option value="">-- Pilih Kelurahan --</option></select></td>
                                </tr>
                                <tr>
                                    <th>Alamat Lengkap</th>
                                    <td><textarea name="alamat_lengkap" class="large-text" rows="3"><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th>Link Google Maps</th>
                                    <td><input name="url_gmaps" type="url" value="<?php echo esc_attr($edit_data->url_gmaps ?? ''); ?>" class="large-text" placeholder="https://maps.google.com/..."></td>
                                </tr>

                                <!-- HIDDEN INPUTS -->
                                <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr($edit_data->provinsi_nama ?? ''); ?>">
                                <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr($edit_data->kabupaten_nama ?? ''); ?>">
                                <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr($edit_data->kecamatan_nama ?? ''); ?>">
                                <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr($edit_data->kelurahan_nama ?? ''); ?>">
                            </table>
                        </div>

                        <!-- TAB 3: KEUANGAN & LEGALITAS -->
                        <div id="tab-keuangan" class="dw-tab-content">
                            <table class="form-table">
                                <tr>
                                    <th>Nama Bank</th>
                                    <td><input name="nama_bank" type="text" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Nomor Rekening</th>
                                    <td><input name="no_rekening" type="text" value="<?php echo esc_attr($edit_data->no_rekening ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>Atas Nama Rekening</th>
                                    <td><input name="atas_nama_rekening" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening ?? ''); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th>QRIS Image</th>
                                    <td>
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <input type="text" name="qris_image_url" id="qris_image_url" value="<?php echo esc_attr($edit_data->qris_image_url ?? ''); ?>" class="regular-text">
                                            <button type="button" class="button" id="btn_upload_qris">Upload QRIS</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr><td colspan="2"><hr></td></tr>
                                <tr>
                                    <th>Scan KTP</th>
                                    <td>
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <input type="text" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>" class="regular-text">
                                            <button type="button" class="button" id="btn_upload_ktp">Upload KTP</button>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- TAB 4: PENGIRIMAN & STATUS -->
                        <div id="tab-pengiriman" class="dw-tab-content">
                            <table class="form-table">
                                <tr>
                                    <th>Status Pendaftaran</th>
                                    <td>
                                        <select name="status_pendaftaran">
                                            <option value="menunggu_desa" <?php selected($edit_data ? $edit_data->status_pendaftaran : '', 'menunggu_desa'); ?>>Menunggu Verifikasi Desa</option>
                                            <option value="menunggu" <?php selected($edit_data ? $edit_data->status_pendaftaran : '', 'menunggu'); ?>>Menunggu Verifikasi Admin</option>
                                            <option value="disetujui" <?php selected($edit_data ? $edit_data->status_pendaftaran : '', 'disetujui'); ?>>Disetujui</option>
                                            <option value="ditolak" <?php selected($edit_data ? $edit_data->status_pendaftaran : '', 'ditolak'); ?>>Ditolak</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status Akun</th>
                                    <td>
                                        <select name="status_akun">
                                            <option value="aktif" <?php selected($edit_data ? $edit_data->status_akun : '', 'aktif'); ?>>Aktif</option>
                                            <option value="nonaktif" <?php selected($edit_data ? $edit_data->status_akun : '', 'nonaktif'); ?>>Non-Aktif</option>
                                            <option value="suspend" <?php selected($edit_data ? $edit_data->status_akun : '', 'suspend'); ?>>Suspend</option>
                                            <option value="nonaktif_habis_kuota" <?php selected($edit_data ? $edit_data->status_akun : '', 'nonaktif_habis_kuota'); ?>>Habis Kuota</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Sisa Transaksi</th>
                                    <td><input type="number" name="sisa_transaksi" value="<?php echo esc_attr($edit_data->sisa_transaksi ?? '0'); ?>" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th>Rating Toko (Manual)</th>
                                    <td><input type="number" step="0.1" max="5" name="rating_toko" value="<?php echo esc_attr($edit_data->rating_toko ?? '0'); ?>" class="small-text"></td>
                                </tr>
                                <tr><td colspan="2"><hr></td></tr>
                                
                                <tr>
                                    <th>Pengiriman Lokal (Ojek)</th>
                                    <td>
                                        <label><input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked($edit_data && $edit_data->shipping_ojek_lokal_aktif); ?>> Aktifkan Ojek Lokal</label>
                                        <br><br>
                                        <label>Zona & Harga (JSON/Text):</label><br>
                                        <textarea name="shipping_ojek_lokal_zona" class="large-text" rows="3"><?php echo esc_textarea($edit_data->shipping_ojek_lokal_zona ?? ''); ?></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Pengiriman Nasional</th>
                                    <td>
                                        <label><input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked($edit_data && $edit_data->shipping_nasional_aktif); ?>> Aktifkan Pengiriman Nasional</label>
                                        <br>
                                        <label>Harga Flat: </label>
                                        <input type="number" name="shipping_nasional_harga" value="<?php echo esc_attr($edit_data->shipping_nasional_harga ?? '0'); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Pesan di Tempat</th>
                                    <td>
                                        <label><input type="checkbox" name="allow_pesan_di_tempat" value="1" <?php checked($edit_data && $edit_data->allow_pesan_di_tempat); ?>> Izinkan Pesan Makan Ditempat</label>
                                    </td>
                                </tr>
                            </table>
                        </div>

                    </div>

                    <div style="background:#f6f7f7; padding:15px 20px; border-top:1px solid #dfdfdf; text-align:right;">
                        <input type="submit" class="button button-primary button-large" value="Simpan Data Pedagang">
                        <a href="?page=dw-pedagang" class="button button-large">Batal</a>
                    </div>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($){
                // Tab Logic
                $('.dw-tab-btn').click(function(e){
                    e.preventDefault();
                    $('.dw-tab-btn').removeClass('active');
                    $('.dw-tab-content').removeClass('active');
                    $(this).addClass('active');
                    var target = $(this).attr('href');
                    $(target).addClass('active');
                });

                // Uploader Generic
                function dw_setup_uploader(btnId, inputId, imgId) {
                    $(btnId).click(function(e){
                        e.preventDefault();
                        var frame = wp.media({ title: 'Pilih File/Gambar', multiple: false });
                        frame.on('select', function(){
                            var url = frame.state().get('selection').first().toJSON().url;
                            $(inputId).val(url);
                            if(imgId) $(imgId).attr('src', url);
                        });
                        frame.open();
                    });
                }
                
                dw_setup_uploader('#btn_upload_profil', '#foto_profil_url', '#preview_foto_profil');
                dw_setup_uploader('#btn_upload_sampul', '#foto_sampul_url', '#preview_foto_sampul');
                dw_setup_uploader('#btn_upload_qris', '#qris_image_url', null);
                dw_setup_uploader('#btn_upload_ktp', '#url_ktp', null);
            });
            </script>

        <?php else: ?>

            <!-- === TABEL LIST PEDAGANG MODERN === -->
            <?php 
                $per_page = 10;
                $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $offset = ($paged - 1) * $per_page;
                $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

                // Query
                $where_sql = "WHERE 1=1";
                if (!empty($search)) {
                    $where_sql .= $wpdb->prepare(" AND (nama_toko LIKE %s OR nama_pemilik LIKE %s)", "%$search%", "%$search%");
                }

                $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_sql");
                $total_pages = ceil($total_items / $per_page);

                $sql = "SELECT p.*, d.nama_desa 
                        FROM $table_name p 
                        LEFT JOIN $table_desa d ON p.id_desa = d.id
                        $where_sql 
                        ORDER BY p.created_at DESC 
                        LIMIT %d OFFSET %d";
                $rows = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));
            ?>

            <div class="tablenav top" style="display:flex; justify-content:flex-end; margin-bottom:15px;">
                <form method="get">
                    <input type="hidden" name="page" value="dw-pedagang">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Toko / Pemilik...">
                        <input type="submit" class="button" value="Cari">
                    </p>
                </form>
            </div>

            <style>
                .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); overflow:hidden; }
                .dw-thumb-toko { width:60px; height:60px; border-radius:50%; object-fit:cover; border:1px solid #eee; background:#f9f9f9; }
                .dw-badge { display:inline-block; padding:4px 8px; border-radius:12px; font-size:11px; font-weight:600; line-height:1; }
                
                /* Status Pendaftaran */
                .badge-disetujui { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
                .badge-menunggu { background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
                .badge-ditolak { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
                .badge-menunggu_desa { background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; }
                
                /* Status Akun */
                .badge-aktif { color: #166534; font-weight: bold; }
                .badge-nonaktif { color: #991b1b; font-weight: bold; }
                .badge-suspend { color: #9a3412; font-weight: bold; background: #ffedd5; padding: 2px 6px; border-radius: 4px; }
                
                .dw-contact-info { font-size:12px; color:#64748b; margin-top:2px; }
                .dw-pagination { margin-top: 15px; text-align: right; }
                .dw-pagination .page-numbers { padding: 5px 10px; border: 1px solid #ccc; text-decoration: none; background: #fff; border-radius: 3px; margin-left: 2px; }
                .dw-pagination .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; }
            </style>

            <div class="dw-card-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="80px" style="text-align:center;">Logo</th>
                            <th width="25%">Info Toko</th>
                            <th width="20%">Pemilik</th>
                            <th width="15%">Lokasi (Desa)</th>
                            <th width="10%">Pendaftaran</th>
                            <th width="10%">Akun</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r): 
                            $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}";
                            $img_src = !empty($r->foto_profil) ? $r->foto_profil : 'https://placehold.co/100x100/f1f5f9/64748b?text=Logo';
                        ?>
                        <tr>
                            <td style="text-align:center; vertical-align:middle;">
                                <img src="<?php echo esc_url($img_src); ?>" class="dw-thumb-toko">
                            </td>
                            <td>
                                <strong><a href="<?php echo $edit_url; ?>" style="font-size:14px;"><?php echo esc_html($r->nama_toko); ?></a></strong>
                                <br><small style="color:#888;">Rating: <?php echo $r->rating_toko; ?>/5</small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($r->nama_pemilik); ?></strong>
                                <div class="dw-contact-info">
                                    <span class="dashicons dashicons-whatsapp"></span> <?php echo esc_html($r->nomor_wa ? $r->nomor_wa : '-'); ?>
                                </div>
                            </td>
                            <td>
                                <?php if($r->nama_desa): ?>
                                    <span class="dashicons dashicons-location" style="font-size:14px;color:#666;"></span> <?php echo esc_html($r->nama_desa); ?>
                                <?php else: ?>
                                    <span style="color:#aaa;">- Independen -</span>
                                <?php endif; ?>
                                <div style="font-size:11px;color:#888;"><?php echo esc_html($r->kabupaten_nama); ?></div>
                            </td>
                            <td>
                                <span class="dw-badge badge-<?php echo $r->status_pendaftaran; ?>">
                                    <?php echo ucfirst(str_replace('_',' ',$r->status_pendaftaran)); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-<?php echo $r->status_akun; ?>">
                                    <?php echo ucfirst(str_replace('_',' ',$r->status_akun)); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small button-primary">Edit</a>
                                <form method="post" action="" style="display:inline-block;" onsubmit="return confirm('Hapus Pedagang?');">
                                    <input type="hidden" name="action_pedagang" value="delete">
                                    <input type="hidden" name="pedagang_id" value="<?php echo $r->id; ?>">
                                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                                    <button type="submit" class="button button-small" style="color:#b32d2e; border-color:#b32d2e; background:transparent;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#777;">Belum ada data pedagang.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="dw-pagination">
                <?php 
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $paged
                ]); 
                ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}
?>