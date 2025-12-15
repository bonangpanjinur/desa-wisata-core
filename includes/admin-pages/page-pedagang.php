<?php
/**
 * File Path: includes/admin-pages/page-pedagang.php
 * Description: Manajemen CRUD Data Toko/Pedagang dengan Form Lengkap (Verifikasi & Shipping).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * =========================================================================
 * 1. HANDLER: SIMPAN / UPDATE DATA (POST)
 * =========================================================================
 */
function dw_pedagang_form_handler() {
    // Cek apakah form disubmit
    if (!isset($_POST['dw_submit_pedagang'])) { return; }

    // Validasi Nonce & Permission
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_pedagang_nonce')) {
        wp_die('Security check failed. Silakan refresh halaman.');
    }
    if (!current_user_can('dw_manage_pedagang')) {
        wp_die('Anda tidak memiliki izin untuk mengelola data toko.');
    }

    global $wpdb;
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_new = ($id === 0);

    // --- URL Redirect ---
    $redirect_url = admin_url('admin.php?page=dw-pedagang');
    if (!$is_new) { 
        $redirect_url = admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $id); 
    }

    // --- Validasi Input Wajib ---
    if (empty($_POST['id_user']) || empty($_POST['nama_toko']) || empty($_POST['nama_pemilik'])) {
        add_settings_error('dw_pedagang_notices', 'empty_fields', 'User, Nama Toko, dan Nama Pemilik wajib diisi.', 'error');
        set_transient('dw_pedagang_form_data', $_POST, 60);
        wp_redirect($is_new ? admin_url('admin.php?page=dw-pedagang&action=add') : $redirect_url); 
        exit;
    }

    $id_user_baru = intval($_POST['id_user']);

    // --- Cek Duplikat User (Satu User = Satu Toko) ---
    if ($is_new) {
        $existing_store = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_pedagang WHERE id_user = %d", $id_user_baru));
        if ($existing_store) {
            add_settings_error('dw_pedagang_notices', 'user_exists', 'User ini sudah memiliki toko. Satu user hanya boleh punya satu toko.', 'error');
            set_transient('dw_pedagang_form_data', $_POST, 60);
            wp_redirect(admin_url('admin.php?page=dw-pedagang&action=add'));
            exit;
        }
    } else {
        $existing_store = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_pedagang WHERE id_user = %d AND id != %d", $id_user_baru, $id));
        if ($existing_store) {
            add_settings_error('dw_pedagang_notices', 'user_exists', 'User yang dipilih sudah memiliki toko lain.', 'error');
            wp_redirect($redirect_url);
            exit;
        }
    }

    // --- Sanitasi Data Input ---
    $nomor_wa = sanitize_text_field($_POST['nomor_wa']);
    
    // Status Akun & Pendaftaran
    $status_akun = sanitize_text_field($_POST['status_akun']); 
    $status_pendaftaran = isset($_POST['status_pendaftaran']) ? sanitize_text_field($_POST['status_pendaftaran']) : 'menunggu_desa';

    if ($status_pendaftaran !== 'disetujui' && $status_akun === 'aktif') {
        $status_akun = 'nonaktif'; 
    }

    $data = [
        // INFO DASAR
        'id_user'       => $id_user_baru, 
        'nama_toko'     => sanitize_text_field($_POST['nama_toko']),
        'slug_toko'     => sanitize_title($_POST['nama_toko']), 
        'nama_pemilik'  => sanitize_text_field($_POST['nama_pemilik']), 
        'nomor_wa'      => $nomor_wa,
        'url_gmaps'     => esc_url_raw($_POST['url_gmaps'] ?? ''), 
        'deskripsi_toko'=> sanitize_textarea_field($_POST['deskripsi_toko'] ?? ''), 
        
        // VERIFIKASI (BARU)
        'nik'           => sanitize_text_field($_POST['nik'] ?? ''),
        'url_ktp'       => esc_url_raw($_POST['url_ktp'] ?? ''),
        'foto_profil'   => esc_url_raw($_POST['foto_profil'] ?? ''),
        
        // KEUANGAN
        'no_rekening'   => sanitize_text_field($_POST['bank_rekening'] ?? ''), 
        'nama_bank'     => sanitize_text_field($_POST['bank_nama'] ?? ''),
        'atas_nama_rekening'=> sanitize_text_field($_POST['bank_atas_nama'] ?? ''), 
        'qris_image_url'=> esc_url_raw($_POST['qris_url'] ?? ''),
        
        // PENGATURAN PENGIRIMAN (BARU)
        'shipping_ojek_lokal_aktif' => isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0,
        'shipping_nasional_aktif'   => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,

        // STATUS
        'status_akun'   => $status_akun,
        'status_pendaftaran' => $status_pendaftaran,
        
        // DATA WILAYAH API
        'api_provinsi_id' => sanitize_text_field($_POST['provinsi_id'] ?? ''),
        'api_kabupaten_id' => sanitize_text_field($_POST['kabupaten_id'] ?? ''),
        'api_kecamatan_id' => sanitize_text_field($_POST['kecamatan_id'] ?? ''),
        'api_kelurahan_id' => sanitize_text_field($_POST['kelurahan_id'] ?? ''),
        
        // DATA NAMA WILAYAH (CACHE)
        'provinsi_nama' => sanitize_text_field($_POST['provinsi_nama'] ?? ''),
        'kabupaten_nama' => sanitize_text_field($_POST['kabupaten_nama'] ?? ''),
        'kecamatan_nama' => sanitize_text_field($_POST['kecamatan_nama'] ?? ''),
        'kelurahan_nama' => sanitize_text_field($_POST['desa_nama'] ?? ''),
        'alamat_lengkap' => sanitize_textarea_field($_POST['alamat_lengkap_manual'] ?? ''),
    ];

    // =====================================================================
    // LOGIKA RELASI DESA (SMART LINK)
    // =====================================================================
    $pilihan_relasi = isset($_POST['id_desa_selection']) ? sanitize_text_field($_POST['id_desa_selection']) : 'auto';
    $final_id_desa = null;

    if ($pilihan_relasi === 'auto') {
        if (!empty($data['api_kelurahan_id'])) {
            $final_id_desa = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_desa WHERE api_kelurahan_id = %s AND status = 'aktif' LIMIT 1",
                $data['api_kelurahan_id']
            ));
        }
    } else {
        $manual_id = intval($pilihan_relasi);
        $final_id_desa = ($manual_id > 0) ? $manual_id : null;
    }
    
    $data['id_desa'] = $final_id_desa;

    // Sisa Transaksi (Hanya Admin)
    if (current_user_can('administrator') && isset($_POST['sisa_transaksi'])) {
        $data['sisa_transaksi'] = absint($_POST['sisa_transaksi']);
    }

    // --- EKSEKUSI DATABASE ---
    if ($is_new) {
        $options = get_option('dw_settings');
        if (!isset($data['sisa_transaksi'])) {
            $data['sisa_transaksi'] = isset($options['kuota_gratis_default']) ? absint($options['kuota_gratis_default']) : 0;
        }
        $data['created_at'] = current_time('mysql');

        $inserted = $wpdb->insert($table_pedagang, $data);
        
        if ($inserted) {
            $id = $wpdb->insert_id;
            $user = new WP_User($id_user_baru);
            if ($user->exists() && !$user->has_cap('administrator')) {
                $user->add_role('pedagang');
            }
            add_settings_error('dw_pedagang_notices', 'create_success', 'Toko baru berhasil dibuat.', 'success');
            wp_redirect(admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $id)); 
            exit;
        } else {
            add_settings_error('dw_pedagang_notices', 'create_failed', 'Gagal membuat toko: ' . $wpdb->last_error, 'error');
            set_transient('dw_pedagang_form_data', $_POST, 60);
            wp_redirect(admin_url('admin.php?page=dw-pedagang&action=add'));
            exit;
        }

    } else {
        // Update Data
        $old_data = $wpdb->get_row($wpdb->prepare("SELECT id_user, status_akun FROM $table_pedagang WHERE id = %d", $id));
        $updated = $wpdb->update($table_pedagang, $data, ['id' => $id]);

        // Manage Role Changes
        if ($old_data && $old_data->id_user != $id_user_baru) {
             $old_user = new WP_User($old_data->id_user);
             if ($old_user->exists() && !$old_user->has_cap('administrator')) {
                 $old_user->remove_role('pedagang'); $old_user->add_role('subscriber');
             }
        }
        // Activate Role for New/Current User
        $current_user_obj = new WP_User($id_user_baru);
        if ($current_user_obj->exists() && !$current_user_obj->has_cap('administrator')) {
             if ($status_akun === 'aktif') $current_user_obj->add_role('pedagang');
        }

        if ($updated !== false) {
            add_settings_error('dw_pedagang_notices', 'update_success', 'Data toko berhasil diperbarui.', 'success');
        } else {
            add_settings_error('dw_pedagang_notices', 'update_failed', 'Gagal update database.', 'error');
        }
        
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect($redirect_url);
        exit;
    }
}
add_action('admin_init', 'dw_pedagang_form_handler');


/**
 * =========================================================================
 * 2. HANDLER: HAPUS PERMANEN
 * =========================================================================
 */
function dw_pedagang_delete_handler() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'dw_delete' || !isset($_GET['id'])) return;
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    if (!wp_verify_nonce($nonce, 'dw_delete_pedagang_action')) wp_die('Link expired.');
    if (!current_user_can('administrator')) wp_die('Akses ditolak.');

    global $wpdb;
    $pedagang_id = intval($_GET['id']);
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_produk   = $wpdb->prefix . 'dw_produk';
    
    $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id_user, nama_toko FROM $table_pedagang WHERE id = %d", $pedagang_id));
    if (!$pedagang) return;

    // Hapus Produk (CPT & Table)
    $produk_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'dw_produk' AND post_author = %d", $pedagang->id_user));
    foreach ($produk_ids as $pid) wp_delete_post($pid, true);
    $wpdb->delete($table_produk, ['id_pedagang' => $pedagang_id]);

    // Hapus Toko
    $deleted = $wpdb->delete($table_pedagang, ['id' => $pedagang_id]);

    // Downgrade Role
    if ($deleted && $pedagang->id_user) {
        $user = new WP_User($pedagang->id_user);
        if ($user->exists() && !$user->has_cap('administrator')) {
            $user->remove_role('pedagang'); $user->add_role('subscriber');
        }
    }
    
    add_settings_error('dw_pedagang_notices', 'deleted', "Toko dihapus.", 'success');
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
}


/**
 * =========================================================================
 * 3. RENDER HALAMAN UTAMA (LIST TABLE)
 * =========================================================================
 */
function dw_pedagang_page_render() {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($action === 'dw_delete') { dw_pedagang_delete_handler(); return; }
    if ('add' === $action || ('edit' === $action && $id > 0)) { dw_pedagang_form_render($id); return; }

    if (!class_exists('DW_Pedagang_List_Table')) require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pedagang-list-table.php';
    $pedagangListTable = new DW_Pedagang_List_Table();
    $pedagangListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Manajemen Toko (Pedagang)</h1>
            <a href="<?php echo admin_url('admin.php?page=dw-pedagang&action=add'); ?>" class="page-title-action">Tambah Toko Baru</a>
        </div>
        <?php $errors = get_transient('settings_errors'); if($errors) { settings_errors('dw_pedagang_notices'); delete_transient('settings_errors'); } ?>
        <form method="get"> 
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
            <?php $pedagangListTable->search_box('Cari Toko', 'pedagang_search'); ?>
            <?php $pedagangListTable->display(); ?>
        </form>
    </div>
    <?php
}


/**
 * =========================================================================
 * 4. RENDER HALAMAN FORM (ADD/EDIT)
 * =========================================================================
 */
function dw_pedagang_form_render($id = 0) {
    global $wpdb;
    $item = null; 
    $page_title = 'Tambah Toko Baru';
    $is_super_admin = current_user_can('administrator');

    // Data handling
    $transient_data = get_transient('dw_pedagang_form_data');
    if ($transient_data) {
        $item = (object) $transient_data; 
        delete_transient('dw_pedagang_form_data');
        if ($id > 0) $page_title = 'Edit Toko'; 
    } elseif ($id > 0) {
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $id));
        if (!$item) { echo '<div class="notice notice-error"><p>Data tidak ditemukan.</p></div>'; return; }
        $page_title = 'Edit Toko';
    }

    $all_users = get_users(['orderby' => 'display_name']); 
    $all_desas = $wpdb->get_results("SELECT id, nama_desa, kecamatan FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif' ORDER BY nama_desa ASC");

    // Pre-fill
    $provinsi_id    = $item->api_provinsi_id ?? '';
    $kabupaten_id   = $item->api_kabupaten_id ?? '';
    $kecamatan_id   = $item->api_kecamatan_id ?? '';
    $kelurahan_id   = $item->api_kelurahan_id ?? '';

    $provinsi_list  = function_exists('dw_get_api_provinsi') ? dw_get_api_provinsi() : [];
    $kabupaten_list = !empty($provinsi_id) ? dw_get_api_kabupaten($provinsi_id) : [];
    $kecamatan_list = !empty($kabupaten_id) ? dw_get_api_kecamatan($kabupaten_id) : [];
    $desa_list_api  = !empty($kecamatan_id) ? dw_get_api_desa($kecamatan_id) : [];

    $current_id_desa = $item->id_desa ?? null;
    $sisa_kuota = $item->sisa_transaksi ?? (get_option('dw_settings')['kuota_gratis_default'] ?? 0);
    
    // Helper function untuk gambar
    function dw_img_preview($url, $placeholder = 'Foto') {
        $src = $url ? $url : "https://placehold.co/150x150/e2e8f0/64748b?text=$placeholder";
        return $src;
    }
    ?>
    <div class="wrap dw-wrap">
        <h1><?php echo esc_html($page_title); ?></h1>
        <?php settings_errors('dw_pedagang_notices'); ?>

        <form method="post" class="dw-form-card"> 
             <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
             <?php wp_nonce_field('dw_save_pedagang_nonce'); ?>
             <input type="hidden" name="dw_submit_pedagang" value="1">

             <!-- 1. INFO DASAR -->
             <div class="postbox">
                 <div class="postbox-header"><h2 class="hndle">Informasi Dasar</h2></div>
                 <div class="inside">
                    <table class="form-table">
                        <tr><th>Pengguna WP <span class="text-red">*</span></th><td>
                            <select name="id_user" id="id_user" class="regular-text" required>
                                <option value="">-- Pilih User --</option>
                                <?php foreach($all_users as $u): ?>
                                    <option value="<?php echo $u->ID; ?>" <?php selected($item->id_user ?? '', $u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th>Nama Toko <span class="text-red">*</span></th><td><input name="nama_toko" type="text" value="<?php echo esc_attr($item->nama_toko ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>Nama Pemilik <span class="text-red">*</span></th><td><input name="nama_pemilik" type="text" value="<?php echo esc_attr($item->nama_pemilik ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>WhatsApp</th><td><input name="nomor_wa" type="text" value="<?php echo esc_attr($item->nomor_wa ?? ''); ?>" class="regular-text" placeholder="628xxxx"></td></tr>
                        <tr><th>Deskripsi Toko</th><td><textarea name="deskripsi_toko" rows="3" class="large-text"><?php echo esc_textarea($item->deskripsi_toko ?? ''); ?></textarea></td></tr>
                    </table>
                 </div>
             </div>
             
             <!-- 2. DATA VERIFIKASI -->
             <div class="postbox">
                 <div class="postbox-header"><h2 class="hndle">Verifikasi Identitas</h2></div>
                 <div class="inside">
                     <table class="form-table">
                        <tr><th>NIK (KTP)</th><td><input name="nik" type="text" value="<?php echo esc_attr($item->nik ?? ''); ?>" class="regular-text" placeholder="16 digit NIK"></td></tr>
                        
                        <!-- Foto Profil -->
                        <tr><th>Foto Profil</th><td>
                            <div class="dw-media-box" style="display:flex; gap:10px; align-items:flex-start;">
                                <img src="<?php echo dw_img_preview($item->foto_profil ?? '', 'Profil'); ?>" class="img-preview" style="width:80px; height:80px; object-fit:cover; border-radius:50%; border:1px solid #ddd;">
                                <div>
                                    <input type="hidden" name="foto_profil" class="img-url" value="<?php echo esc_attr($item->foto_profil ?? ''); ?>">
                                    <button type="button" class="button btn-upload">Upload Foto Profil</button>
                                    <button type="button" class="button button-link-delete btn-remove" <?php echo empty($item->foto_profil) ? 'style="display:none;"' : ''; ?>>Hapus</button>
                                </div>
                            </div>
                        </td></tr>

                        <!-- Foto KTP -->
                        <tr><th>Foto KTP</th><td>
                            <div class="dw-media-box" style="display:flex; gap:10px; align-items:flex-start;">
                                <img src="<?php echo dw_img_preview($item->url_ktp ?? '', 'KTP'); ?>" class="img-preview" style="width:150px; height:100px; object-fit:cover; border-radius:4px; border:1px solid #ddd;">
                                <div>
                                    <input type="hidden" name="url_ktp" class="img-url" value="<?php echo esc_attr($item->url_ktp ?? ''); ?>">
                                    <button type="button" class="button btn-upload">Upload KTP</button>
                                    <button type="button" class="button button-link-delete btn-remove" <?php echo empty($item->url_ktp) ? 'style="display:none;"' : ''; ?>>Hapus</button>
                                </div>
                            </div>
                        </td></tr>
                     </table>
                 </div>
             </div>

             <!-- 3. LOKASI & RELASI -->
             <div class="postbox" style="border-left: 4px solid #2271b1;">
                 <div class="postbox-header"><h2 class="hndle">Lokasi & Afiliasi Desa</h2></div>
                 <div class="inside">
                     <table class="form-table">
                        <tr><th>Afiliasi Desa</th><td>
                            <select name="id_desa_selection" id="id_desa_selection" class="regular-text">
                                <option value="auto" <?php echo ($current_id_desa === null) ? 'selected' : ''; ?>>⚡ Otomatis (Sesuai Alamat)</option>
                                <optgroup label="--- Override Manual ---">
                                    <?php foreach ($all_desas as $desa): ?>
                                        <option value="<?php echo $desa->id; ?>" <?php selected($current_id_desa, $desa->id); ?>><?php echo esc_html($desa->nama_desa); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <div id="dw-desa-match-status" style="margin-top: 10px; display:none;"></div>
                        </td></tr>

                        <!-- Form Alamat -->
                        <tr><th>Provinsi</th><td><select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select regular-text" required><option value="">Pilih Provinsi</option><?php foreach ($provinsi_list as $p) echo "<option value='{$p['code']}' ".selected($provinsi_id,$p['code'],false).">{$p['name']}</option>"; ?></select></td></tr>
                        <tr><th>Kabupaten</th><td><select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select regular-text" <?php disabled(empty($kabupaten_list)); ?> required><option value="">Pilih Kabupaten</option><?php foreach ($kabupaten_list as $k) echo "<option value='{$k['code']}' ".selected($kabupaten_id,$k['code'],false).">{$k['name']}</option>"; ?></select></td></tr>
                        <tr><th>Kecamatan</th><td><select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select regular-text" <?php disabled(empty($kecamatan_list)); ?> required><option value="">Pilih Kecamatan</option><?php foreach ($kecamatan_list as $kc) echo "<option value='{$kc['code']}' ".selected($kecamatan_id,$kc['code'],false).">{$kc['name']}</option>"; ?></select></td></tr>
                        <tr><th>Kelurahan/Desa</th><td><select name="kelurahan_id" id="dw_desa" class="dw-desa-select regular-text" <?php disabled(empty($desa_list_api)); ?> required><option value="">Pilih Kelurahan</option><?php foreach ($desa_list_api as $d) echo "<option value='{$d['code']}' ".selected($kelurahan_id,$d['code'],false).">{$d['name']}</option>"; ?></select></td></tr>
                        <tr><th>Detail Jalan</th><td><textarea name="alamat_lengkap_manual" class="large-text" rows="2"><?php echo esc_textarea($item->alamat_lengkap ?? ''); ?></textarea></td></tr>
                        <tr><th>Google Maps</th><td><input name="url_gmaps" type="url" value="<?php echo esc_attr($item->url_gmaps ?? ''); ?>" class="regular-text" placeholder="https://maps.app.goo.gl/..."></td></tr>
                     </table>
                     
                     <!-- Hidden Inputs Wilayah -->
                    <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr($item->provinsi_nama ?? ''); ?>">
                    <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr($item->kabupaten_nama ?? ''); ?>">
                    <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr($item->kecamatan_nama ?? ''); ?>">
                    <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr($item->kelurahan_nama ?? ''); ?>">
                 </div>
             </div>

             <!-- 4. PENGATURAN PENGIRIMAN (SHIPPING) -->
             <div class="postbox">
                 <div class="postbox-header"><h2 class="hndle">Pengaturan Pengiriman</h2></div>
                 <div class="inside">
                     <table class="form-table">
                         <tr>
                             <th>Metode Aktif</th>
                             <td>
                                 <fieldset>
                                     <label style="margin-right: 20px;">
                                         <input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked(1, $item->shipping_ojek_lokal_aktif ?? 0); ?>> 
                                         <strong>Ojek Lokal</strong> (Tarif per Jarak/Zona)
                                     </label>
                                     <label>
                                         <input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked(1, $item->shipping_nasional_aktif ?? 0); ?>> 
                                         <strong>Kurir Nasional</strong> (JNE, POS, dll - via RajaOngkir)
                                     </label>
                                 </fieldset>
                             </td>
                         </tr>
                     </table>
                 </div>
             </div>

             <!-- 5. KEUANGAN & STATUS -->
             <div class="postbox">
                 <div class="postbox-header"><h2 class="hndle">Keuangan & Status</h2></div>
                 <div class="inside">
                    <table class="form-table">
                        <tr><th>No. Rekening</th><td><input name="bank_rekening" type="text" value="<?php echo esc_attr($item->no_rekening ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th>Nama Bank</th><td><input name="bank_nama" type="text" value="<?php echo esc_attr($item->nama_bank ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th>Atas Nama</th><td><input name="bank_atas_nama" type="text" value="<?php echo esc_attr($item->atas_nama_rekening ?? ''); ?>" class="regular-text"></td></tr>
                        
                        <!-- QRIS -->
                        <tr><th>QRIS</th><td>
                            <div class="dw-media-box" style="display:flex; gap:10px; align-items:flex-start;">
                                <img src="<?php echo dw_img_preview($item->qris_image_url ?? '', 'QRIS'); ?>" class="img-preview" style="width:100px; height:100px; object-fit:contain; border:1px solid #ddd;">
                                <div>
                                    <input type="hidden" name="qris_url" class="img-url" value="<?php echo esc_attr($item->qris_image_url ?? ''); ?>">
                                    <button type="button" class="button btn-upload">Upload QRIS</button>
                                    <button type="button" class="button button-link-delete btn-remove" <?php echo empty($item->qris_image_url) ? 'style="display:none;"' : ''; ?>>Hapus</button>
                                </div>
                            </div>
                        </td></tr>

                        <tr style="border-top:1px solid #ddd;"><th>Status Pendaftaran</th><td>
                             <select name="status_pendaftaran">
                                 <option value="menunggu_desa" <?php selected($item->status_pendaftaran ?? '', 'menunggu_desa'); ?>>Menunggu Desa</option>
                                 <option value="disetujui" <?php selected($item->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                                 <option value="ditolak" <?php selected($item->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                             </select>
                        </td></tr>
                        <tr><th>Status Akun</th><td>
                             <select name="status_akun">
                                 <option value="nonaktif" <?php selected($item->status_akun ?? '', 'nonaktif'); ?>>Nonaktif</option>
                                 <option value="aktif" <?php selected($item->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                                 <option value="nonaktif_habis_kuota" <?php selected($item->status_akun ?? '', 'nonaktif_habis_kuota'); ?>>Dibekukan</option>
                             </select>
                        </td></tr>
                        <?php if ($is_super_admin): ?>
                        <tr><th>Sisa Kuota</th><td><input type="number" name="sisa_transaksi" value="<?php echo esc_attr($sisa_kuota); ?>" class="small-text"></td></tr>
                        <?php endif; ?>
                    </table>
                 </div>
             </div>

              <div class="dw-form-footer"> 
                  <?php submit_button('Simpan Data Toko', 'primary', 'dw_submit_pedagang', false); ?> 
                  <a href="<?php echo admin_url('admin.php?page=dw-pedagang'); ?>" class="button button-secondary">Kembali</a> 
              </div> 
         </form>
     </div>

     <!-- SCRIPTS -->
     <script>
         jQuery(document).ready(function($){
            // 1. Generic Media Uploader
            $('.btn-upload').click(function(e) {
                e.preventDefault();
                var $container = $(this).closest('.dw-media-box');
                var $imgInput = $container.find('.img-url');
                var $imgPreview = $container.find('.img-preview');
                var $btnRemove = $container.find('.btn-remove');
                
                var uploader = wp.media({
                    title: 'Pilih Gambar', button: { text: 'Gunakan Gambar Ini' }, multiple: false
                }).on('select', function() {
                    var attachment = uploader.state().get('selection').first().toJSON();
                    $imgInput.val(attachment.url);
                    $imgPreview.attr('src', attachment.url);
                    $btnRemove.show();
                }).open();
            });

            $('.btn-remove').click(function(e){
                e.preventDefault();
                var $container = $(this).closest('.dw-media-box');
                $container.find('.img-url').val('');
                $container.find('.img-preview').attr('src', "https://placehold.co/150x150/e2e8f0/64748b?text=Kosong");
                $(this).hide();
            });

            // 2. Logic Smart Link Desa
            var $statusBox = $('#dw-desa-match-status');
            var $modeSelect = $('#id_desa_selection');
            var $kelSelect = $('#dw_desa');
            
            function checkAutoMatch() {
                var kel_id = $kelSelect.val();
                if ($modeSelect.val() !== 'auto') {
                    $statusBox.html('<div style="padding:10px; background:#fff8e5; border-left:4px solid #ffba00;"><strong>Mode Manual.</strong> Toko akan dihubungkan ke desa yang dipilih.</div>').show();
                    return;
                }
                if (!kel_id) { $statusBox.hide(); return; }
                $statusBox.html('<em>Mendeteksi...</em>').show();
                
                $.post(dw_admin_vars.ajax_url, {
                    action: 'dw_check_desa_match_from_address', nonce: dw_admin_vars.nonce, kel_id: kel_id
                }).done(function(res) {
                    if(res.success && res.data.matched) {
                         $statusBox.html('<div style="padding:10px; background:#d4edda; border-left:4px solid #28a745; color:#155724;">✅ <strong>Terdeteksi: ' + res.data.nama_desa + '</strong><br>Akan terhubung otomatis.</div>');
                    } else {
                         $statusBox.html('<div style="padding:10px; background:#e2e3e5; border-left:4px solid #383d41;">ℹ️ <strong>Tidak ditemukan desa di wilayah ini.</strong> Toko akan berdiri sendiri.</div>');
                    }
                });
            }
            $('#dw_provinsi, #dw_kabupaten, #dw_kecamatan, #dw_desa').change(function(){ if ($modeSelect.val() === 'auto') checkAutoMatch(); });
            $modeSelect.change(checkAutoMatch);
            setTimeout(checkAutoMatch, 1000);
         });
     </script>
     <?php
 }
 ?>