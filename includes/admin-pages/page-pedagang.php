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
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_save_pedagang_nonce')) {
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
    
    // Validasi JSON Zona
    $zona_json = isset($_POST['shipping_ojek_lokal_zona']) ? wp_unslash($_POST['shipping_ojek_lokal_zona']) : '';
    if (!empty($zona_json) && is_null(json_decode($zona_json))) {
        $zona_json = '[]'; // Reset jika invalid
    }

    $data = [
        'id_user'       => $id_user,
        'nama_toko'     => sanitize_text_field($_POST['nama_toko']),
        'slug_toko'     => sanitize_title($_POST['nama_toko']),
        'nama_pemilik'  => sanitize_text_field($_POST['nama_pemilik']),
        'nomor_wa'      => sanitize_text_field($_POST['nomor_wa']),
        'alamat_lengkap'=> sanitize_textarea_field($_POST['alamat_lengkap_manual']),
        'url_gmaps'     => esc_url_raw($_POST['url_gmaps']),
        
        // Data Wilayah (Hanya simpan Kecamatan ID sesuai struktur tabel baru)
        'api_kecamatan_id' => sanitize_text_field($_POST['kecamatan_id_mirror'] ?? ''),
        
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

        // Pengiriman
        'shipping_ojek_lokal_aktif' => isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0,
        'shipping_nasional_aktif'   => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,
        'shipping_ojek_lokal_zona'  => $zona_json
    ];

    // 7. LOGIKA RELASI DESA (INTI PERMINTAAN ANDA)
    // Cari apakah ada Desa Wisata Aktif di Kelurahan ini?
    $id_desa_found = null;
    $pilihan_relasi = isset($_POST['id_desa_selection']) ? sanitize_text_field($_POST['id_desa_selection']) : 'auto';

    if ($pilihan_relasi === 'auto') {
        if (!empty($kel_id)) {
            $id_desa_found = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_desa WHERE api_kelurahan_id = %s AND status = 'aktif' LIMIT 1",
                $kel_id
            ));
        }
    } else {
        // Manual Override
        $manual_id = intval($pilihan_relasi);
        $id_desa_found = ($manual_id > 0) ? $manual_id : null;
    }
    
    // Simpan ID Desa (atau NULL jika tidak ketemu/auto gagal)
    $data['id_desa'] = $id_desa_found;


    // 8. EKSEKUSI QUERY
    if ($is_new) {
        // --- INSERT ---
        $data['created_at'] = current_time('mysql');
        
        $inserted = $wpdb->insert($table_pedagang, $data);
        
        if ($inserted) {
            $new_id = $wpdb->insert_id;
            // Update Role User -> Pedagang
            $u = new WP_User($id_user);
            if ($u->exists() && !$u->has_cap('administrator')) $u->add_role('pedagang');

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
        if ($u->exists() && $data['status_akun'] === 'aktif' && !$u->has_cap('administrator')) {
            $u->add_role('pedagang');
        }

        if ($updated !== false) {
            add_settings_error('dw_pedagang_notices', 'success', 'Data Toko diperbarui. Relasi Desa: ' . ($id_desa_found ? "Terhubung (ID $id_desa_found)" : "Independen"), 'success');
        } else {
            // Update return false jika data sama persis, tapi ini bukan error
            add_settings_error('dw_pedagang_notices', 'info', 'Data Toko diperbarui (atau tidak ada perubahan).', 'success');
        }
        wp_redirect(admin_url('admin.php?page=dw-pedagang&action=edit&id='.$id)); exit;
    }
}
// PENTING: Kaitkan handler ini ke admin_init agar berjalan sebelum render halaman
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
    
    if (!$toko) {
         add_settings_error('dw_pedagang_notices', 'delete_error', 'Data tidak ditemukan.', 'error');
         wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
    }

    // 1. Hapus Produk (Post Type)
    $produk_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='dw_produk' AND post_author = {$toko->id_user}");
    if (!empty($produk_ids)) {
        foreach($produk_ids as $pid) wp_delete_post($pid, true);
    }

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
    // Tangkap aksi delete sebelum render apapun
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
    if (!class_exists('DW_Pedagang_List_Table')) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pedagang-list-table.php';
    }
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
    
    // Data Wilayah Pre-fill (Logic reverse engineering dari API ID jika data di DB parsial)
    // Asumsi: Kita butuh Provinsi, Kabupaten, Kecamatan, Kelurahan untuk dropdown
    // Karena di DB hanya simpan api_kecamatan_id, kita perlu logic extra di JS atau Helper untuk load parent.
    // Sederhananya, kita load kosong dulu jika baru, atau load by API jika ada fitur reverse lookup.
    // Di sini saya gunakan variabel placeholder agar tidak error.
    
    $prov = ''; 
    $kab  = ''; 
    $kec  = $item->api_kecamatan_id ?? ''; 
    $kel  = ''; // Kelurahan tidak disimpan di DB pedagang baru, tapi dipakai untuk lookup desa

    // Load Helper API
    $list_prov = function_exists('dw_get_api_provinsi') ? dw_get_api_provinsi() : [];
    
    // Default JSON Zona
    $zona_json = isset($item->shipping_ojek_lokal_zona) ? $item->shipping_ojek_lokal_zona : '';
    if (empty($zona_json)) {
        $zona_json = "[\n  {\"id\": \"zona_1\", \"nama\": \"Area Dekat (0-3km)\", \"harga\": 5000},\n  {\"id\": \"zona_2\", \"nama\": \"Area Jauh (3-10km)\", \"harga\": 15000}\n]";
    }
    
    // Cek Relasi Saat Ini
    $current_id_desa = $item->id_desa ?? null;
    $all_desas = $wpdb->get_results("SELECT id, nama_desa, kecamatan FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif' ORDER BY nama_desa ASC");

    // Helper Image Preview
    function dw_img_preview($url, $placeholder = 'Foto') {
        return $url ? $url : "https://placehold.co/150x150/e2e8f0/64748b?text=$placeholder";
    }

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
                                                <?php echo esc_html($u->display_name . " ({$u->user_login})"); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr><th>Nama Toko</th><td><input type="text" name="nama_toko" value="<?php echo esc_attr($item->nama_toko ?? ''); ?>" class="regular-text" required></td></tr>
                            <tr><th>Nama Pemilik</th><td><input type="text" name="nama_pemilik" value="<?php echo esc_attr($item->nama_pemilik ?? ''); ?>" class="regular-text" required></td></tr>
                            <tr><th>WhatsApp</th><td><input type="text" name="nomor_wa" value="<?php echo esc_attr($item->nomor_wa ?? ''); ?>" class="regular-text" placeholder="0812..."></td></tr>
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
                            <!-- Override Relasi -->
                            <tr>
                                <th>Afiliasi Desa</th>
                                <td>
                                    <select name="id_desa_selection" id="id_desa_selection" class="regular-text">
                                        <option value="auto" <?php echo ($current_id_desa === null) ? 'selected' : ''; ?>>⚡ Otomatis (Sesuai Alamat)</option>
                                        <optgroup label="--- Override Manual ---">
                                            <?php foreach ($all_desas as $desa): ?>
                                                <option value="<?php echo $desa->id; ?>" <?php selected($current_id_desa, $desa->id); ?>><?php echo esc_html($desa->nama_desa); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                    <div id="relasi-status-box" style="margin-top:10px; display:none;"></div>
                                </td>
                            </tr>

                            <!-- PROVINSI -->
                            <tr>
                                <th>Provinsi</th>
                                <td>
                                    <select id="dw_provinsi" class="regular-text">
                                        <option value="">Pilih Provinsi</option>
                                        <?php foreach($list_prov as $p) echo "<option value='{$p['code']}'>{$p['name']}</option>"; ?>
                                    </select>
                                    <!-- MIRROR INPUT (Wajib ada untuk simpan data) -->
                                    <input type="hidden" name="provinsi_id_mirror" id="provinsi_id_mirror" value="">
                                </td>
                            </tr>
                            
                            <!-- KABUPATEN -->
                            <tr>
                                <th>Kabupaten</th>
                                <td>
                                    <select id="dw_kabupaten" class="regular-text" disabled><option value="">Pilih Kabupaten</option></select>
                                    <input type="hidden" name="kabupaten_id_mirror" id="kabupaten_id_mirror" value="">
                                </td>
                            </tr>

                            <!-- KECAMATAN -->
                            <tr>
                                <th>Kecamatan</th>
                                <td>
                                    <select id="dw_kecamatan" class="regular-text" disabled><option value="">Pilih Kecamatan</option></select>
                                    <input type="hidden" name="kecamatan_id_mirror" id="kecamatan_id_mirror" value="<?php echo esc_attr($kec); ?>">
                                </td>
                            </tr>

                            <!-- KELURAHAN (KUNCI RELASI) -->
                            <tr>
                                <th>Kelurahan/Desa</th>
                                <td>
                                    <select id="dw_desa" class="regular-text" disabled><option value="">Pilih Kelurahan</option></select>
                                    <input type="hidden" name="kelurahan_id_mirror" id="kelurahan_id_mirror" value="">
                                </td>
                            </tr>

                            <tr><th>Detail Jalan</th><td><textarea name="alamat_lengkap_manual" class="large-text" rows="2"><?php echo esc_textarea($item->alamat_lengkap ?? ''); ?></textarea></td></tr>
                            <tr><th>Maps URL</th><td><input type="text" name="url_gmaps" value="<?php echo esc_attr($item->url_gmaps ?? ''); ?>" class="regular-text"></td></tr>
                        </table>
                    </div>
                </div>

                <!-- BOX 3: STATUS & PENGIRIMAN -->
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle">3. Pengiriman & Status</h2></div>
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
                                        <option value="suspend" <?php selected($item->status_akun ?? '', 'suspend'); ?>>Suspend</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Pengiriman -->
                            <tr>
                                <th>Metode Pengiriman</th>
                                <td>
                                    <label><input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked(1, $item->shipping_ojek_lokal_aktif ?? 0); ?>> Ojek Lokal</label><br>
                                    <label><input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked(1, $item->shipping_nasional_aktif ?? 0); ?>> Kurir Nasional (JNE/POS)</label>
                                </td>
                            </tr>
                            <tr>
                                <th>Zona Tarif Ojek (JSON)</th>
                                <td>
                                    <textarea name="shipping_ojek_lokal_zona" rows="4" class="large-text code"><?php echo esc_textarea($zona_json); ?></textarea>
                                    <p class="description">Format JSON: <code>[{"id":"zona1", "nama":"Dekat", "harga":5000}]</code></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- BOX 4: VERIFIKASI & BANK -->
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle">4. Data Verifikasi & Bank</h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <tr><th>NIK</th><td><input type="text" name="nik" value="<?php echo esc_attr($item->nik ?? ''); ?>" class="regular-text"></td></tr>
                            
                            <tr><th>Foto Profil</th><td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <img src="<?php echo dw_img_preview($item->foto_profil ?? '', 'Profil'); ?>" class="img-preview" style="width:60px; height:60px; object-fit:cover; border:1px solid #ddd;">
                                    <input type="hidden" name="foto_profil" class="img-url" value="<?php echo esc_attr($item->foto_profil ?? ''); ?>">
                                    <button type="button" class="button btn-upload">Upload</button>
                                </div>
                            </td></tr>
                            
                            <tr><th>Foto KTP</th><td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <img src="<?php echo dw_img_preview($item->url_ktp ?? '', 'KTP'); ?>" class="img-preview" style="width:100px; height:60px; object-fit:cover; border:1px solid #ddd;">
                                    <input type="hidden" name="url_ktp" class="img-url" value="<?php echo esc_attr($item->url_ktp ?? ''); ?>">
                                    <button type="button" class="button btn-upload">Upload</button>
                                </div>
                            </td></tr>
                            
                            <tr><th>Bank</th><td><input type="text" name="bank_nama" value="<?php echo esc_attr($item->nama_bank ?? ''); ?>" placeholder="Nama Bank"></td></tr>
                            <tr><th>No. Rekening</th><td><input type="text" name="bank_rekening" value="<?php echo esc_attr($item->no_rekening ?? ''); ?>" placeholder="Nomor Rekening"></td></tr>
                            <tr><th>Atas Nama</th><td><input type="text" name="bank_atas_nama" value="<?php echo esc_attr($item->atas_nama_rekening ?? ''); ?>" placeholder="Atas Nama"></td></tr>
                            
                            <tr><th>QRIS</th><td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <img src="<?php echo dw_img_preview($item->qris_image_url ?? '', 'QRIS'); ?>" class="img-preview" style="width:60px; height:60px; object-fit:cover; border:1px solid #ddd;">
                                    <input type="hidden" name="qris_url" class="img-url" value="<?php echo esc_attr($item->qris_image_url ?? ''); ?>">
                                    <button type="button" class="button btn-upload">Upload</button>
                                </div>
                            </td></tr>
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
        
        // --- MEDIA UPLOADER ---
        $('.btn-upload').click(function(e) {
            e.preventDefault();
            var $container = $(this).parent();
            var uploader = wp.media({
                title: 'Pilih Gambar', button: { text: 'Gunakan' }, multiple: false
            }).on('select', function() {
                var attachment = uploader.state().get('selection').first().toJSON();
                $container.find('.img-url').val(attachment.url);
                $container.find('.img-preview').attr('src', attachment.url);
            }).open();
        });

        // --- CASCADING DROPDOWN (Manual AJAX Call needed if not using global helper) ---
        // Untuk penyederhanaan di file ini, kita gunakan logika standar:
        // Load Kabupaten saat Provinsi berubah, dst.
        
        function loadRegion(type, parentId, targetSelector) {
            if(!parentId) return;
            $(targetSelector).prop('disabled', true).html('<option>Loading...</option>');
            
            $.post(ajaxurl, {
                action: 'dw_get_region_options', // Pastikan action ini ada di ajax-handlers.php
                type: type,
                parent_id: parentId
            }, function(res) {
                $(targetSelector).html(res).prop('disabled', false);
            });
        }

        $('#dw_provinsi').change(function(){
            $('#provinsi_id_mirror').val($(this).val());
            loadRegion('kabupaten', $(this).val(), '#dw_kabupaten');
        });
        
        $('#dw_kabupaten').change(function(){
            $('#kabupaten_id_mirror').val($(this).val());
            loadRegion('kecamatan', $(this).val(), '#dw_kecamatan');
        });
        
        $('#dw_kecamatan').change(function(){
            $('#kecamatan_id_mirror').val($(this).val());
            loadRegion('kelurahan', $(this).val(), '#dw_desa');
        });
        
        $('#dw_desa').change(function(){
            $('#kelurahan_id_mirror').val($(this).val());
            checkRelasi();
        });

        // --- CHECK RELASI OTOMATIS ---
        function checkRelasi() {
            var kelId = $('#dw_desa').val();
            var $box = $('#relasi-status-box');
            var $mode = $('#id_desa_selection').val();
            
            if($mode !== 'auto') {
                $box.html("Mode Manual Aktif.").show(); return;
            }
            if(!kelId) return;
            
            $box.html("Mengecek...").show();
            
            $.post(ajaxurl, {
                action: 'dw_check_desa_match_from_address',
                kel_id: kelId
            }, function(res) {
                if(res.success && res.data.matched) {
                    $box.html("<b style='color:green'>✅ Terhubung: " + res.data.nama_desa + "</b>");
                } else {
                    $box.html("<b style='color:orange'>ℹ️ Independen (Tidak ada desa)</b>");
                }
            });
        }
    });
    </script>
    <?php
}