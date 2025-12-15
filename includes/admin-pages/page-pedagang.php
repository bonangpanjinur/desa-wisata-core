<?php
/**
 * File: includes/admin-pages/page-pedagang.php
 * Description: Manajemen CRUD Toko (Pedagang) + Auto Relasi Desa Wisata.
 * * LOGIKA UTAMA:
 * 1. CRUD: Handle Create, Update, Delete dengan validasi ketat.
 * 2. RELASI: Saat disimpan, sistem mengecek 'api_kelurahan_id'.
 * - Jika cocok dengan Desa Wisata Aktif -> Simpan ID Desa.
 * - Jika tidak cocok -> ID Desa NULL (Independen).
 * 3. STABILITAS: Menggunakan Hidden Input Mirror untuk menjamin data wilayah terkirim.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * --------------------------------------------------------------------------
 * HANDLER: SIMPAN / UPDATE DATA (POST)
 * --------------------------------------------------------------------------
 */
function dw_pedagang_form_handler() {
    // 1. Cek apakah ini request POST dari form pedagang
    if (!isset($_POST['dw_submit_pedagang'])) return;

    // 2. Security Check (Nonce & Permission)
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_pedagang_nonce')) {
        wp_die('Security check failed. Silakan refresh halaman.');
    }
    if (!current_user_can('dw_manage_pedagang')) {
        wp_die('Akses ditolak.');
    }

    global $wpdb;
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';

    // 3. Tentukan Mode (Tambah Baru / Edit)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_new = ($id === 0);

    // 4. Validasi Input Wajib
    if (empty($_POST['id_user']) || empty($_POST['nama_toko']) || empty($_POST['nama_pemilik'])) {
        add_settings_error('dw_pedagang_notices', 'empty', 'User, Nama Toko, dan Pemilik wajib diisi.', 'error');
        // Redirect kembali
        $url = $is_new ? admin_url('admin.php?page=dw-pedagang&action=add') : admin_url('admin.php?page=dw-pedagang&action=edit&id='.$id);
        wp_redirect($url); exit;
    }

    // 5. Cek Duplikat User (1 User = 1 Toko)
    $id_user = intval($_POST['id_user']);
    $cek_sql = $is_new 
        ? "SELECT id FROM $table_pedagang WHERE id_user = $id_user" 
        : "SELECT id FROM $table_pedagang WHERE id_user = $id_user AND id != $id";
    
    if ($wpdb->get_var($cek_sql)) {
        add_settings_error('dw_pedagang_notices', 'duplicate', 'User ini sudah memiliki toko lain.', 'error');
        $url = $is_new ? admin_url('admin.php?page=dw-pedagang&action=add') : admin_url('admin.php?page=dw-pedagang&action=edit&id='.$id);
        wp_redirect($url); exit;
    }

    // 6. Persiapan Data (Sanitasi)
    // PENTING: Ambil data wilayah dari MIRROR (Hidden Input) agar aman dari masalah dropdown disabled
    $kel_id = sanitize_text_field($_POST['kelurahan_id_mirror'] ?? '');
    
    $data = [
        'id_user'       => $id_user,
        'nama_toko'     => sanitize_text_field($_POST['nama_toko']),
        'slug_toko'     => sanitize_title($_POST['nama_toko']),
        'nama_pemilik'  => sanitize_text_field($_POST['nama_pemilik']),
        'nomor_wa'      => sanitize_text_field($_POST['nomor_wa']),
        'deskripsi_toko'=> sanitize_textarea_field($_POST['deskripsi_toko']),
        'alamat_lengkap'=> sanitize_textarea_field($_POST['alamat_lengkap_manual']),
        'url_gmaps'     => esc_url_raw($_POST['url_gmaps']),
        
        // Data Wilayah (Dari Mirror)
        'api_provinsi_id'  => sanitize_text_field($_POST['provinsi_id_mirror'] ?? ''),
        'api_kabupaten_id' => sanitize_text_field($_POST['kabupaten_id_mirror'] ?? ''),
        'api_kecamatan_id' => sanitize_text_field($_POST['kecamatan_id_mirror'] ?? ''),
        'api_kelurahan_id' => $kel_id,
        
        // Cache Nama Wilayah
        'provinsi_nama'  => sanitize_text_field($_POST['provinsi_nama'] ?? ''),
        'kabupaten_nama' => sanitize_text_field($_POST['kabupaten_nama'] ?? ''),
        'kecamatan_nama' => sanitize_text_field($_POST['kecamatan_nama'] ?? ''),
        'kelurahan_nama' => sanitize_text_field($_POST['desa_nama'] ?? ''),

        // Status
        'status_akun'        => sanitize_text_field($_POST['status_akun']),
        'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran'] ?? 'menunggu_desa'),
        
        // Verifikasi & Bank
        'nik'           => sanitize_text_field($_POST['nik']),
        'url_ktp'       => esc_url_raw($_POST['url_ktp']),
        'foto_profil'   => esc_url_raw($_POST['foto_profil']),
        'no_rekening'   => sanitize_text_field($_POST['bank_rekening']),
        'nama_bank'     => sanitize_text_field($_POST['bank_nama']),
        'atas_nama_rekening' => sanitize_text_field($_POST['bank_atas_nama']),
        'qris_image_url'     => esc_url_raw($_POST['qris_url']),
    ];

    // 7. LOGIKA RELASI DESA (INTI PERMINTAAN ANDA)
    // Cari apakah ada Desa Wisata Aktif di Kelurahan ini?
    $id_desa_found = null;
    if (!empty($kel_id)) {
        $id_desa_found = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_desa WHERE api_kelurahan_id = %s AND status = 'aktif' LIMIT 1",
            $kel_id
        ));
    }
    // Simpan ID Desa (atau NULL jika tidak ketemu)
    $data['id_desa'] = $id_desa_found;


    // 8. EKSEKUSI QUERY
    if ($is_new) {
        // --- INSERT ---
        $data['created_at'] = current_time('mysql');
        // Default kuota
        $options = get_option('dw_settings');
        $data['sisa_transaksi'] = isset($options['kuota_gratis_default']) ? intval($options['kuota_gratis_default']) : 0;

        $inserted = $wpdb->insert($table_pedagang, $data);
        
        if ($inserted) {
            $new_id = $wpdb->insert_id;
            // Update Role User -> Pedagang
            $u = new WP_User($id_user);
            if (!$u->has_cap('administrator')) $u->add_role('pedagang');

            add_settings_error('dw_pedagang_notices', 'success', 'Toko berhasil dibuat. Relasi Desa: ' . ($id_desa_found ? "Terhubung (ID $id_desa_found)" : "Independen"), 'success');
            wp_redirect(admin_url('admin.php?page=dw-pedagang&action=edit&id='.$new_id)); exit;
        } else {
            add_settings_error('dw_pedagang_notices', 'fail', 'Gagal insert database: '.$wpdb->last_error, 'error');
        }

    } else {
        // --- UPDATE ---
        $updated = $wpdb->update($table_pedagang, $data, ['id' => $id]);
        
        // Cek Role User (Update role jika user diganti/status berubah)
        // (Logika sederhana: Pastikan user saat ini punya role pedagang jika aktif)
        $u = new WP_User($id_user);
        if ($data['status_akun'] === 'aktif' && !$u->has_cap('administrator')) {
            $u->add_role('pedagang');
        }

        if ($updated !== false) {
            add_settings_error('dw_pedagang_notices', 'success', 'Data Toko diperbarui. Relasi Desa: ' . ($id_desa_found ? "Terhubung (ID $id_desa_found)" : "Independen"), 'success');
        } else {
            add_settings_error('dw_pedagang_notices', 'fail', 'Gagal update (Mungkin tidak ada perubahan data).', 'warning');
        }
        wp_redirect(admin_url('admin.php?page=dw-pedagang&action=edit&id='.$id)); exit;
    }
}
add_action('admin_init', 'dw_pedagang_form_handler');


/**
 * --------------------------------------------------------------------------
 * HANDLER: DELETE (HAPUS PERMANEN)
 * --------------------------------------------------------------------------
 */
function dw_pedagang_delete_handler() {
    if (!isset($_GET['id']) || !isset($_GET['_wpnonce'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_pedagang_action')) wp_die('Security Fail');
    if (!current_user_can('administrator')) wp_die('Access Denied');

    global $wpdb;
    $id = intval($_GET['id']);
    
    // Ambil data user sebelum dihapus
    $toko = $wpdb->get_row("SELECT id_user, nama_toko FROM {$wpdb->prefix}dw_pedagang WHERE id = $id");
    
    // 1. Hapus Produk (Post Type)
    $produk_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='dw_produk' AND post_author = {$toko->id_user}");
    foreach($produk_ids as $pid) wp_delete_post($pid, true);

    // 2. Hapus Data Toko
    $wpdb->delete($wpdb->prefix.'dw_pedagang', ['id' => $id]);

    // 3. Downgrade User Role
    $u = new WP_User($toko->id_user);
    if ($u->exists() && !$u->has_cap('administrator')) {
        $u->remove_role('pedagang');
        $u->add_role('subscriber');
    }

    add_settings_error('dw_pedagang_notices', 'deleted', "Toko '{$toko->nama_toko}' berhasil dihapus permanen.", 'success');
    wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
}


/**
 * --------------------------------------------------------------------------
 * RENDER: MAIN PAGE CONTROLLER
 * --------------------------------------------------------------------------
 */
function dw_pedagang_page_render() {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'dw_delete') {
        dw_pedagang_delete_handler();
        return;
    }
    
    if ($action === 'add' || $action === 'edit') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        dw_pedagang_form_render($id);
        return;
    }

    // List Table View
    require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pedagang-list-table.php';
    $table = new DW_Pedagang_List_Table();
    $table->prepare_items();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Toko (UMKM)</h1>
        <a href="?page=dw-pedagang&action=add" class="page-title-action">Tambah Toko Baru</a>
        <hr class="wp-header-end">
        <?php settings_errors('dw_pedagang_notices'); ?>
        <form method="get">
            <input type="hidden" name="page" value="dw-pedagang">
            <?php $table->search_box('Cari Toko', 's'); ?>
            <?php $table->display(); ?>
        </form>
    </div>
    <?php
}


/**
 * --------------------------------------------------------------------------
 * RENDER: FORM INPUT (ADD / EDIT)
 * --------------------------------------------------------------------------
 */
function dw_pedagang_form_render($id) {
    global $wpdb;
    $item = null;
    $title = "Tambah Toko Baru";

    if ($id > 0) {
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $id));
        if (!$item) { echo "<div class='error'><p>Data tidak ditemukan.</p></div>"; return; }
        $title = "Edit Toko: " . esc_html($item->nama_toko);
    }

    // Data Penunjang
    $users = get_users(['orderby' => 'display_name']);
    
    // Data Wilayah Pre-fill
    $prov = $item->api_provinsi_id ?? '';
    $kab  = $item->api_kabupaten_id ?? '';
    $kec  = $item->api_kecamatan_id ?? '';
    $kel  = $item->api_kelurahan_id ?? '';

    // Load Helper API (Pastikan fungsi ini ada di plugin Anda)
    $list_prov = function_exists('dw_get_api_provinsi') ? dw_get_api_provinsi() : [];
    $list_kab  = ($prov && function_exists('dw_get_api_kabupaten')) ? dw_get_api_kabupaten($prov) : [];
    $list_kec  = ($kab && function_exists('dw_get_api_kecamatan')) ? dw_get_api_kecamatan($kab) : [];
    $list_kel  = ($kec && function_exists('dw_get_api_desa')) ? dw_get_api_desa($kec) : [];

    ?>
    <div class="wrap">
        <h1><?php echo $title; ?></h1>
        <?php settings_errors('dw_pedagang_notices'); ?>

        <form method="post" action="">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php wp_nonce_field('dw_save_pedagang_nonce'); ?>
            <input type="hidden" name="dw_submit_pedagang" value="1">

            <div class="metabox-holder">
                <!-- BOX 1: INFO DASAR -->
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle">1. Informasi Pemilik & Toko</h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th>User WordPress</th>
                                <td>
                                    <select name="id_user" class="regular-text" required>
                                        <option value="">-- Pilih User --</option>
                                        <?php foreach($users as $u): ?>
                                            <option value="<?php echo $u->ID; ?>" <?php selected($item->id_user ?? '', $u->ID); ?>>
                                                <?php echo esc_html($u->display_name . " ({$u->user_email})"); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr><th>Nama Toko</th><td><input type="text" name="nama_toko" value="<?php echo esc_attr($item->nama_toko ?? ''); ?>" class="regular-text" required></td></tr>
                            <tr><th>Nama Pemilik</th><td><input type="text" name="nama_pemilik" value="<?php echo esc_attr($item->nama_pemilik ?? ''); ?>" class="regular-text" required></td></tr>
                            <tr><th>WhatsApp</th><td><input type="text" name="nomor_wa" value="<?php echo esc_attr($item->nomor_wa ?? ''); ?>" class="regular-text"></td></tr>
                            <tr><th>Deskripsi</th><td><textarea name="deskripsi_toko" class="large-text" rows="3"><?php echo esc_textarea($item->deskripsi_toko ?? ''); ?></textarea></td></tr>
                        </table>
                    </div>
                </div>

                <!-- BOX 2: LOKASI & RELASI (CRITICAL SECTION) -->
                <div class="postbox" style="border-left: 4px solid #2271b1;">
                    <div class="postbox-header"><h2 class="hndle">2. Alamat & Relasi Desa Wisata</h2></div>
                    <div class="inside">
                        <p class="description">
                            Sistem akan otomatis menghubungkan Toko ke Desa Wisata berdasarkan <strong>Kelurahan</strong> yang dipilih.
                            <br>Jika Kelurahan tidak terdaftar sebagai Desa Wisata, toko akan berstatus <strong>Independen</strong>.
                        </p>
                        <hr>

                        <table class="form-table">
                            <!-- PROVINSI -->
                            <tr>
                                <th>Provinsi</th>
                                <td>
                                    <select id="dw_provinsi" name="provinsi_id_dummy" class="regular-text">
                                        <option value="">Pilih Provinsi</option>
                                        <?php foreach($list_prov as $p) echo "<option value='{$p['code']}' ".selected($prov,$p['code'],false).">{$p['name']}</option>"; ?>
                                    </select>
                                    <!-- MIRROR INPUT (Wajib ada untuk simpan data) -->
                                    <input type="hidden" name="provinsi_id_mirror" id="provinsi_id_mirror" value="<?php echo esc_attr($prov); ?>">
                                    <input type="hidden" name="provinsi_nama" class="dw-provinsi-nama" value="<?php echo esc_attr($item->provinsi_nama ?? ''); ?>">
                                </td>
                            </tr>
                            
                            <!-- KABUPATEN -->
                            <tr>
                                <th>Kabupaten</th>
                                <td>
                                    <select id="dw_kabupaten" name="kabupaten_id_dummy" class="regular-text" <?php disabled(empty($list_kab)); ?>>
                                        <option value="">Pilih Kabupaten</option>
                                        <?php foreach($list_kab as $k) echo "<option value='{$k['code']}' ".selected($kab,$k['code'],false).">{$k['name']}</option>"; ?>
                                    </select>
                                    <input type="hidden" name="kabupaten_id_mirror" id="kabupaten_id_mirror" value="<?php echo esc_attr($kab); ?>">
                                    <input type="hidden" name="kabupaten_nama" class="dw-kabupaten-nama" value="<?php echo esc_attr($item->kabupaten_nama ?? ''); ?>">
                                </td>
                            </tr>

                            <!-- KECAMATAN -->
                            <tr>
                                <th>Kecamatan</th>
                                <td>
                                    <select id="dw_kecamatan" name="kecamatan_id_dummy" class="regular-text" <?php disabled(empty($list_kec)); ?>>
                                        <option value="">Pilih Kecamatan</option>
                                        <?php foreach($list_kec as $kc) echo "<option value='{$kc['code']}' ".selected($kec,$kc['code'],false).">{$kc['name']}</option>"; ?>
                                    </select>
                                    <input type="hidden" name="kecamatan_id_mirror" id="kecamatan_id_mirror" value="<?php echo esc_attr($kec); ?>">
                                    <input type="hidden" name="kecamatan_nama" class="dw-kecamatan-nama" value="<?php echo esc_attr($item->kecamatan_nama ?? ''); ?>">
                                </td>
                            </tr>

                            <!-- KELURAHAN (KUNCI RELASI) -->
                            <tr>
                                <th>Kelurahan/Desa</th>
                                <td>
                                    <select id="dw_desa" name="kelurahan_id_dummy" class="regular-text" <?php disabled(empty($list_kel)); ?>>
                                        <option value="">Pilih Kelurahan</option>
                                        <?php foreach($list_kel as $kl) echo "<option value='{$kl['code']}' ".selected($kel,$kl['code'],false).">{$kl['name']}</option>"; ?>
                                    </select>
                                    <input type="hidden" name="kelurahan_id_mirror" id="kelurahan_id_mirror" value="<?php echo esc_attr($kel); ?>">
                                    <input type="hidden" name="desa_nama" class="dw-desa-nama" value="<?php echo esc_attr($item->kelurahan_nama ?? ''); ?>">
                                    
                                    <!-- INDIKATOR RELASI -->
                                    <div id="relasi-status-box" style="margin-top:10px; padding:8px; background:#f0f0f1; border:1px solid #ccc; border-radius:4px; display:inline-block;">
                                        Status Relasi: <span>Menunggu pilihan...</span>
                                    </div>
                                </td>
                            </tr>

                            <tr><th>Detail Jalan</th><td><textarea name="alamat_lengkap_manual" class="large-text" rows="2"><?php echo esc_textarea($item->alamat_lengkap ?? ''); ?></textarea></td></tr>
                            <tr><th>Maps URL</th><td><input type="text" name="url_gmaps" value="<?php echo esc_attr($item->url_gmaps ?? ''); ?>" class="regular-text"></td></tr>
                        </table>
                    </div>
                </div>

                <!-- BOX 3: STATUS & KEUANGAN -->
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle">3. Status & Data Bank</h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th>Status Pendaftaran</th>
                                <td>
                                    <select name="status_pendaftaran">
                                        <option value="menunggu_desa" <?php selected($item->status_pendaftaran ?? '', 'menunggu_desa'); ?>>Menunggu Desa</option>
                                        <option value="disetujui" <?php selected($item->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                                        <option value="ditolak" <?php selected($item->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Status Akun</th>
                                <td>
                                    <select name="status_akun">
                                        <option value="nonaktif" <?php selected($item->status_akun ?? '', 'nonaktif'); ?>>Nonaktif</option>
                                        <option value="aktif" <?php selected($item->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                                        <option value="nonaktif_habis_kuota" <?php selected($item->status_akun ?? '', 'nonaktif_habis_kuota'); ?>>Dibekukan</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- DATA BANK & VERIFIKASI (DISIMPAN TAPI TIDAK DITAMPILKAN SEMUA UNTUK RINGKAS) -->
                            <!-- Tambahkan field hidden/input sesuai kebutuhan detail Anda -->
                            <tr><th>NIK</th><td><input type="text" name="nik" value="<?php echo esc_attr($item->nik ?? ''); ?>"></td></tr>
                            <tr><th>Nama Bank</th><td><input type="text" name="bank_nama" value="<?php echo esc_attr($item->nama_bank ?? ''); ?>"></td></tr>
                            <tr><th>No Rekening</th><td><input type="text" name="bank_rekening" value="<?php echo esc_attr($item->no_rekening ?? ''); ?>"></td></tr>
                            <tr><th>Atas Nama</th><td><input type="text" name="bank_atas_nama" value="<?php echo esc_attr($item->atas_nama_rekening ?? ''); ?>"></td></tr>
                            
                            <!-- Hidden Fields for Images (Simplifikasi, bisa tambah uploader jika perlu) -->
                            <input type="hidden" name="url_ktp" value="<?php echo esc_attr($item->url_ktp ?? ''); ?>">
                            <input type="hidden" name="foto_profil" value="<?php echo esc_attr($item->foto_profil ?? ''); ?>">
                            <input type="hidden" name="qris_url" value="<?php echo esc_attr($item->qris_image_url ?? ''); ?>">
                        </table>
                    </div>
                </div>

                <div class="submit">
                    <input type="submit" name="dw_submit_pedagang" id="submit" class="button button-primary" value="Simpan Data Toko">
                    <a href="?page=dw-pedagang" class="button">Batal</a>
                </div>
            </div>
        </form>
    </div>

    <!-- SCRIPT SYNC & CHECK RELASI -->
    <script>
    jQuery(document).ready(function($){
        
        // 1. FUNGSI SYNC: Dropdown -> Hidden Mirror
        // Ini menjamin data terkirim meski dropdown disabled/loading
        function syncInputs() {
            $('#provinsi_id_mirror').val( $('#dw_provinsi').val() );
            $('#kabupaten_id_mirror').val( $('#dw_kabupaten').val() );
            $('#kecamatan_id_mirror').val( $('#dw_kecamatan').val() );
            $('#kelurahan_id_mirror').val( $('#dw_desa').val() );
        }

        // 2. FUNGSI CEK RELASI (AJAX)
        function checkRelasi() {
            var kelId = $('#dw_desa').val();
            var $box = $('#relasi-status-box span');
            
            if(!kelId) {
                $box.html("<em>Pilih kelurahan dulu...</em>"); return;
            }
            
            $box.html("Mengecek...");
            
            $.post(ajaxurl, {
                action: 'dw_check_desa_match_from_address',
                kel_id: kelId,
                // nonce jika perlu
            }, function(res) {
                if(res.success && res.data.matched) {
                    $box.html("<b style='color:green'>✅ Terhubung: " + res.data.nama_desa + "</b>");
                } else {
                    $box.html("<b style='color:orange'>ℹ️ Independen (Tidak ada desa)</b>");
                }
            });
        }

        // Events
        $('#dw_provinsi, #dw_kabupaten, #dw_kecamatan').on('change', function(){
            syncInputs(); // Sync saat level atas berubah
        });
        
        $('#dw_desa').on('change', function(){
            syncInputs();
            checkRelasi();
        });

        // Init
        syncInputs();
        if($('#dw_desa').val()) checkRelasi();
    });
    </script>
    <?php
}