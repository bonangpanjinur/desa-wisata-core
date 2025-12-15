<?php
/**
 * File Path: includes/admin-pages/page-pedagang.php
 * Description: Manajemen Data Toko/Pedagang dengan fitur "Smart Link" ke Desa Wisata.
 * * LOGIKA RELASI DESA:
 * 1. Mode Otomatis (Default): Mencocokkan 'api_kelurahan_id' toko dengan desa terdaftar.
 * - Cocok: id_desa terisi.
 * - Tidak Cocok: id_desa NULL (Toko Independen/Milik Admin).
 * 2. Mode Manual (Admin Override): Admin memilih spesifik Desa, mengabaikan alamat fisik.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
  * Form handler untuk update data pedagang.
  */
function dw_pedagang_form_handler() {
    if (!isset($_POST['dw_submit_pedagang'])) { return; }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $redirect_url = admin_url('admin.php?page=dw-pedagang');
    if ($id > 0) { $redirect_url = admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $id); }
    else { $redirect_url = admin_url('admin.php?page=dw-pedagang&action=add'); }

    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_pedagang_nonce')) {
        wp_die('Security check failed.');
    }
    if (!current_user_can('dw_manage_pedagang')) {
        wp_die('Anda tidak memiliki izin.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';

    // --- 1. Validasi Input Dasar ---
    if (empty($_POST['id_user']) || empty($_POST['nama_toko']) || empty($_POST['nama_pemilik'])) {
        add_settings_error('dw_pedagang_notices', 'empty_fields', 'User, Nama Toko, dan Nama Pemilik wajib diisi.', 'error');
        set_transient('dw_pedagang_form_data', $_POST, 60);
        set_transient('settings_errors', get_settings_errors(), 30); wp_redirect($redirect_url); exit;
    }
    $nomor_wa = sanitize_text_field($_POST['nomor_wa']);
    
    // Validasi WA
    if (!empty($nomor_wa) && !preg_match('/^[0-9+ ]{8,15}$/', preg_replace('/[^0-9+]/', '', $nomor_wa))) {
        add_settings_error('dw_pedagang_notices', 'invalid_wa', 'Format Nomor WhatsApp tidak valid.', 'error');
        set_transient('dw_pedagang_form_data', $_POST, 60);
        set_transient('settings_errors', get_settings_errors(), 30); wp_redirect($redirect_url); exit;
    }

    $id_user_baru = intval($_POST['id_user']);
    $status_akun_input = sanitize_text_field($_POST['status_akun']); 
    $status_pendaftaran_input = isset($_POST['status_pendaftaran']) && current_user_can('administrator') ? sanitize_text_field($_POST['status_pendaftaran']) : null;
    
    // Logika Status Akun vs Pendaftaran
    if ($status_pendaftaran_input !== null && $status_pendaftaran_input !== 'disetujui') {
        $status_akun_input = 'nonaktif';
    } 
    elseif ($status_akun_input !== 'nonaktif_habis_kuota') {
        if ($id > 0) {
            $current_pendaftaran_status = $status_pendaftaran_input ?? $wpdb->get_var($wpdb->prepare("SELECT status_pendaftaran FROM $table_name WHERE id = %d", $id));
            if ($current_pendaftaran_status !== 'disetujui') {
                $status_akun_input = 'nonaktif';
            }
        } else if (!$status_pendaftaran_input || $status_pendaftaran_input !== 'disetujui') {
             $status_akun_input = 'nonaktif';
        }
    }

    $data = [
        'id_user' => $id_user_baru, 
        'nama_toko' => sanitize_text_field($_POST['nama_toko']),
        'nama_pemilik' => sanitize_text_field($_POST['nama_pemilik']), 
        'nomor_wa' => $nomor_wa,
        'url_gmaps' => esc_url_raw($_POST['url_gmaps'] ?? null), 
        'deskripsi_toko'=> sanitize_textarea_field($_POST['deskripsi_toko'] ?? null), 
        'status_akun' => $status_akun_input,
        'no_rekening' => sanitize_text_field($_POST['bank_rekening'] ?? null), 
        'nama_bank' => sanitize_text_field($_POST['bank_nama'] ?? null),
        'atas_nama_rekening'=> sanitize_text_field($_POST['bank_atas_nama'] ?? null), 
        'qris_image_url' => esc_url_raw($_POST['qris_url'] ?? null),
        
        // Data Wilayah
        'api_provinsi_id' => sanitize_text_field($_POST['provinsi_id'] ?? ''),
        'api_kabupaten_id' => sanitize_text_field($_POST['kabupaten_id'] ?? ''),
        'api_kecamatan_id' => sanitize_text_field($_POST['kecamatan_id'] ?? ''),
        'api_kelurahan_id' => sanitize_text_field($_POST['kelurahan_id'] ?? ''),
        'provinsi_nama' => sanitize_text_field($_POST['provinsi_nama'] ?? ''),
        'kabupaten_nama' => sanitize_text_field($_POST['kabupaten_nama'] ?? ''),
        'kecamatan_nama' => sanitize_text_field($_POST['kecamatan_nama'] ?? ''),
        'kelurahan_nama' => sanitize_text_field($_POST['desa_nama'] ?? ''),
        'alamat_lengkap' => sanitize_textarea_field($_POST['alamat_lengkap_manual'] ?? ''),
    ];

    // =====================================================================
    // LOGIKA RELASI DESA (ANALISIS PERMINTAAN USER)
    // =====================================================================
    // Ambil input override dari Admin
    $pilihan_relasi = isset($_POST['id_desa_selection']) ? sanitize_text_field($_POST['id_desa_selection']) : 'auto';

    if ($pilihan_relasi === 'auto') {
        // --- OPSI 1: OTOMATIS BERDASARKAN ALAMAT ---
        $matched_desa_id = NULL;
        if (!empty($data['api_kelurahan_id'])) {
            // Cari Desa Wisata yang 'aktif' di kelurahan ini
            $matched_desa_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}dw_desa 
                 WHERE api_kelurahan_id = %s 
                 AND status = 'aktif'
                 LIMIT 1",
                $data['api_kelurahan_id']
            ));
        }
        // Jika ketemu, link. Jika tidak, NULL (Toko Independen/Admin).
        $data['id_desa'] = $matched_desa_id ? (int)$matched_desa_id : null;

    } else {
        // --- OPSI 2: MANUAL (ADMIN MEMAKSA RELASI) ---
        // Jika admin memilih ID spesifik, gunakan itu.
        // Jika admin memilih "0" atau opsi kosong manual, berarti dipaksa independen.
        $manual_id = intval($pilihan_relasi);
        $data['id_desa'] = ($manual_id > 0) ? $manual_id : null;
    }
    // =====================================================================

    // Sisa Transaksi (Hanya Admin)
    if (current_user_can('administrator') && isset($_POST['sisa_transaksi'])) {
        $data['sisa_transaksi'] = absint($_POST['sisa_transaksi']);
    }

    if ($status_pendaftaran_input !== null) { $data['status_pendaftaran'] = $status_pendaftaran_input; }
    $is_new_pedagang = ($id === 0);

    // --- EKSEKUSI DATABASE ---
    if ($id > 0) { // Update
        $pedagang_data_lama = $wpdb->get_row($wpdb->prepare("SELECT id_user, status_akun, status_pendaftaran FROM $table_name WHERE id = %d", $id));
        $id_user_lama = $pedagang_data_lama ? absint($pedagang_data_lama->id_user) : 0;

        $updated = $wpdb->update($table_name, $data, ['id' => $id]);

        if ($updated !== false) {
             $success_msg = 'Data Toko berhasil diperbarui.';
             
             // Pesan Feedback Relasi
             if ($data['id_desa']) {
                 $nama_desa = $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM {$wpdb->prefix}dw_desa WHERE id = %d", $data['id_desa']));
                 $success_msg .= ' Terhubung ke: <strong>' . esc_html($nama_desa) . '</strong>.';
             } else {
                 $success_msg .= ' Status: <strong>Independen (Dikelola Admin)</strong>.';
             }
             
             add_settings_error('dw_pedagang_notices', 'pedagang_updated', $success_msg, 'success');

             // Handle Role Changes (User lama vs User baru)
             // (Kode manajemen role sama seperti sebelumnya...)
             if ($id_user_lama > 0 && $id_user_baru !== $id_user_lama) {
                 $user_lama = get_userdata($id_user_lama);
                 if ($user_lama && !$user_lama->has_cap('administrator')) {
                     $user_lama->remove_role('pedagang');
                     if (empty($user_lama->roles)) { $user_lama->add_role('subscriber'); }
                 }
                 $user_baru = get_userdata($id_user_baru);
                 if ($status_akun_input === 'aktif' && $user_baru) {
                     $user_baru->add_role('pedagang');
                 } elseif ($user_baru && !$user_baru->has_cap('administrator') && !$user_baru->has_cap('pedagang')) {
                      if (empty($user_baru->roles)) { $user_baru->set_role('subscriber'); }
                 }
             }
             // Handle Aktivasi Role pada User yang sama
            if ($id_user_baru === $id_user_lama && $pedagang_data_lama) {
                $user = get_userdata($id_user_baru);
                if ($user) {
                    if ($status_akun_input === 'aktif' && $pedagang_data_lama->status_akun !== 'aktif') {
                        $user->add_role('pedagang');
                    } elseif ($status_akun_input !== 'aktif' && $pedagang_data_lama->status_akun === 'aktif') {
                        $user->remove_role('pedagang');
                        if (empty($user->roles)) { $user->add_role('subscriber'); }
                    }
                }
            }

        } else {
             add_settings_error('dw_pedagang_notices', 'update_failed', 'Gagal memperbarui data toko.', 'error');
        }

    } else { // Insert Baru
        $options = get_option('dw_settings');
        $default_kuota = isset($options['kuota_gratis_default']) ? absint($options['kuota_gratis_default']) : 0; 
        $data['status_pendaftaran'] = $status_pendaftaran_input ?? 'menunggu_desa'; 
        $data['status_akun'] = $status_akun_input ?? 'nonaktif'; 
        $data['sisa_transaksi'] = $default_kuota; 
        $data['created_at'] = current_time('mysql');

        $inserted = $wpdb->insert($table_name, $data);
        if ($inserted) {
            $id = $wpdb->insert_id;
            $msg = 'Toko baru berhasil dibuat.';
            if ($data['id_desa']) $msg .= ' Otomatis terhubung ke Desa Wisata setempat.';
            else $msg .= ' Toko diset sebagai Independen.';
            add_settings_error('dw_pedagang_notices', 'pedagang_created', $msg, 'success');
            
            // Set role
            $user = get_userdata($id_user_baru);
            if ($user && !user_can($user, 'administrator') && !$user->has_cap('pedagang')) {
                if (empty($user->roles)) { $user->set_role('subscriber'); }
            }
        } else {
            add_settings_error('dw_pedagang_notices', 'insert_failed', 'Gagal menyimpan toko baru.', 'error');
        }
    }

    delete_transient('dw_pedagang_form_data');
    set_transient('settings_errors', get_settings_errors(), 30);
    $final_redirect = ($is_new_pedagang && !get_settings_errors('dw_pedagang_notices')) ? admin_url('admin.php?page=dw-pedagang') : admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $id);
    wp_redirect($final_redirect);
    exit;
 }
 add_action('admin_init', 'dw_pedagang_form_handler');


 /**
  * Fungsi render halaman utama.
  */
 function dw_pedagang_page_render() {
     $action = isset($_GET['action']) ? $_GET['action'] : 'list';
     $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

     // Delete handler
     if ('dw_delete' === $action && $id > 0) {
          if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'dw_delete_pedagang_action')) wp_die('Security check failed');
          if (function_exists('dw_pedagang_delete_handler')) dw_pedagang_delete_handler(); 
          wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
     }

     if ('add' === $action || ('edit' === $action && $id > 0)) {
         dw_pedagang_form_render($id); return;
     }

     // List Table
     if (!class_exists('DW_Pedagang_List_Table')) { require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pedagang-list-table.php'; }
     $table = new DW_Pedagang_List_Table();
     $table->prepare_items();
     ?>
     <div class="wrap dw-wrap">
         <h1 class="wp-heading-inline">Manajemen Toko</h1>
         <a href="<?php echo admin_url('admin.php?page=dw-pedagang&action=add'); ?>" class="page-title-action">Tambah Toko Baru</a>
         <hr class="wp-header-end">
         <?php settings_errors('dw_pedagang_notices'); ?>
         <form method="get"> 
             <input type="hidden" name="page" value="dw-pedagang">
             <?php $table->search_box('Cari Toko', 'pedagang_search'); ?>
             <?php $table->display(); ?>
         </form>
     </div>
     <?php
 }

 /**
  * Form Render
  */
 function dw_pedagang_form_render($id = 0) {
     global $wpdb;
     $item = null; 
     $page_title = 'Tambah Toko Baru';
     $is_super_admin = current_user_can('administrator');

     // Ambil data (Transient atau DB)
     $transient_data = get_transient('dw_pedagang_form_data');
     if ($transient_data) {
         $item = (object) $transient_data; 
         delete_transient('dw_pedagang_form_data');
         $id = $item->id ?? $id;
     } elseif ($id > 0) {
         $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $id));
     }
     if ($id > 0) $page_title = 'Edit Toko';

     // List User WP
     $users = get_users(['orderby' => 'display_name']); // Disederhanakan utk contoh
     
     // List Semua Desa Aktif (Untuk Dropdown Manual Override)
     $all_desas = $wpdb->get_results("SELECT id, nama_desa, kecamatan, kabupaten FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif' ORDER BY nama_desa ASC");

     // Data Wilayah
     $provinsi_id    = $item->api_provinsi_id ?? '';
     $kabupaten_id   = $item->api_kabupaten_id ?? '';
     $kecamatan_id   = $item->api_kecamatan_id ?? '';
     $kelurahan_id   = $item->api_kelurahan_id ?? '';

     $provinsi_list  = dw_get_api_provinsi();
     $kabupaten_list = !empty($provinsi_id) ? dw_get_api_kabupaten($provinsi_id) : [];
     $kecamatan_list = !empty($kabupaten_id) ? dw_get_api_kecamatan($kabupaten_id) : [];
     $desa_list_api  = !empty($kecamatan_id) ? dw_get_api_desa($kecamatan_id) : [];

     // Tentukan Current Desa ID (Apakah Null, atau ada isinya)
     $current_id_desa = $item->id_desa ?? null;
     
     // Logika Dropdown Selection:
     // Jika $current_id_desa NULL, berarti 'auto' (atau memang tidak ketemu).
     // Namun di UI kita set default 'auto'. Jika user mau override, mereka pilih ID.
     // Kita perlu tahu apakah kondisi sekarang itu "Auto" atau "Manual". 
     // Karena di DB tidak ada flag "is_manual", kita asumsikan untuk Edit Form:
     // - Default tampilkan 'auto'.
     // - Admin bisa ganti ke ID tertentu jika mau.
     // - Jika admin ingin melihat desa mana yang sedang aktif, kita beri info text.

     ?>
     <div class="wrap dw-wrap">
         <h1><?php echo esc_html($page_title); ?></h1>
         <?php settings_errors('dw_pedagang_notices'); ?>

         <form method="post" class="dw-form-card"> 
              <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
             <?php wp_nonce_field('dw_save_pedagang_nonce'); ?>
             <input type="hidden" name="dw_submit_pedagang" value="1">

             <div class="postbox">
                 <div class="postbox-header"><h2 class="hndle">Informasi Dasar</h2></div>
                 <div class="inside">
                    <table class="form-table">
                        <tr><th><label>Pengguna WordPress <span class="text-red">*</span></label></th><td>
                            <select name="id_user" id="id_user" class="regular-text" required>
                                <option value="">-- Pilih User --</option>
                                <?php foreach($users as $u): ?>
                                    <option value="<?php echo $u->ID; ?>" <?php selected($item->id_user ?? '', $u->ID); ?>>
                                        <?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th>Nama Toko <span class="text-red">*</span></th><td><input name="nama_toko" type="text" value="<?php echo esc_attr($item->nama_toko ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>Nama Pemilik <span class="text-red">*</span></th><td><input name="nama_pemilik" type="text" value="<?php echo esc_attr($item->nama_pemilik ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>WhatsApp</th><td><input name="nomor_wa" type="text" value="<?php echo esc_attr($item->nomor_wa ?? ''); ?>" class="regular-text"></td></tr>
                    </table>
                 </div>
             </div>

             <!-- PENGATURAN LOKASI & RELASI DESA (INTI PERUBAHAN) -->
             <div class="postbox" style="border-left: 4px solid #2271b1;">
                 <div class="postbox-header"><h2 class="hndle">Lokasi & Afiliasi Desa Wisata</h2></div>
                 <div class="inside">
                     <p class="description" style="margin-bottom:15px;">
                         Sistem secara default akan menghubungkan Toko ke Desa Wisata berdasarkan <strong>Kecocokan Alamat (Kelurahan)</strong>.
                         <br>Jika tidak ada Desa Wisata di alamat tersebut, Toko akan berstatus <strong>Independen (Milik Admin)</strong>.
                         <br>Admin dapat melakukan <strong>Override Manual</strong> di bawah ini jika ingin memaksakan relasi.
                     </p>

                     <table class="form-table">
                        <!-- 1. PILIHAN RELASI (AUTO vs MANUAL) -->
                        <tr style="background-color: #f0f6fc;">
                            <th><label for="id_desa_selection"><strong>Afiliasi Desa</strong></label></th>
                            <td>
                                <select name="id_desa_selection" id="id_desa_selection" class="regular-text" style="border: 1px solid #2271b1;">
                                    <option value="auto" <?php echo ($current_id_desa === null) ? 'selected' : ''; ?>>⚡ Otomatis (Sesuai Alamat)</option>
                                    <optgroup label="--- Pilihan Manual (Override) ---">
                                        <?php foreach ($all_desas as $desa): ?>
                                            <option value="<?php echo $desa->id; ?>" <?php selected($current_id_desa, $desa->id); ?>>
                                                <?php echo esc_html($desa->nama_desa); ?> 
                                                (<?php echo esc_html($desa->kecamatan); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <p class="description" id="desa_relasi_desc">
                                    Biarkan "Otomatis" agar sistem mendeteksi lokasi. Pilih nama Desa jika ingin memaksa toko ini masuk ke desa tertentu.
                                </p>
                                <!-- FEEDBACK VISUAL -->
                                <div id="dw-desa-match-status" style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px; display:none;"></div>
                            </td>
                        </tr>

                        <!-- 2. FORM ALAMAT -->
                        <tr><th>Provinsi</th><td>
                            <select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select regular-text" required>
                                <option value="">-- Pilih Provinsi --</option>
                                <?php foreach ($provinsi_list as $prov) : ?>
                                    <option value="<?php echo esc_attr($prov['code']); ?>" <?php selected($provinsi_id, $prov['code']); ?>><?php echo esc_html($prov['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th>Kabupaten</th><td>
                            <select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select regular-text" <?php disabled(empty($kabupaten_list)); ?> required>
                                <option value="">-- Pilih Kabupaten --</option>
                                <?php foreach ($kabupaten_list as $kab) : ?>
                                    <option value="<?php echo esc_attr($kab['code']); ?>" <?php selected($kabupaten_id, $kab['code']); ?>><?php echo esc_html($kab['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th>Kecamatan</th><td>
                            <select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select regular-text" <?php disabled(empty($kecamatan_list)); ?> required>
                                <option value="">-- Pilih Kecamatan --</option>
                                <?php foreach ($kecamatan_list as $kec) : ?>
                                    <option value="<?php echo esc_attr($kec['code']); ?>" <?php selected($kecamatan_id, $kec['code']); ?>><?php echo esc_html($kec['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th>Kelurahan/Desa</th><td>
                            <select name="kelurahan_id" id="dw_desa" class="dw-desa-select regular-text" <?php disabled(empty($desa_list_api)); ?> required>
                                <option value="">-- Pilih Kelurahan --</option>
                                <?php foreach ($desa_list_api as $desa) : ?>
                                    <option value="<?php echo esc_attr($desa['code']); ?>" <?php selected($kelurahan_id, $desa['code']); ?>><?php echo esc_html($desa['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th>Detail Jalan</th><td>
                            <textarea name="alamat_lengkap_manual" class="large-text" rows="2"><?php echo esc_textarea($item->alamat_lengkap ?? ''); ?></textarea>
                        </td></tr>
                     </table>
                     
                     <!-- Hidden Inputs Nama Wilayah -->
                    <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr($item->provinsi_nama ?? ''); ?>">
                    <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr($item->kabupaten_nama ?? ''); ?>">
                    <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr($item->kecamatan_nama ?? ''); ?>">
                    <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr($item->kelurahan_nama ?? ''); ?>">
                 </div>
             </div>

             <!-- KEUANGAN & STATUS (Sederhana) -->
             <div class="postbox">
                 <div class="postbox-header"><h2 class="hndle">Status & Pembayaran</h2></div>
                 <div class="inside">
                    <table class="form-table">
                        <tr><th>Status Akun</th><td>
                             <select name="status_akun">
                                 <option value="nonaktif" <?php selected($item->status_akun ?? '', 'nonaktif'); ?>>Nonaktif</option>
                                 <option value="aktif" <?php selected($item->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                             </select>
                        </td></tr>
                        <tr><th>Sisa Kuota</th><td><input type="number" name="sisa_transaksi" value="<?php echo esc_attr($item->sisa_transaksi ?? 0); ?>" class="small-text"></td></tr>
                    </table>
                 </div>
             </div>

              <div class="dw-form-footer"> 
                  <?php submit_button('Simpan Data Toko', 'primary', 'dw_submit_pedagang', false); ?> 
                  <a href="<?php echo admin_url('admin.php?page=dw-pedagang'); ?>" class="button button-secondary">Kembali</a> 
              </div> 
         </form>
     </div>

     <script>
         jQuery(document).ready(function($){
            var $statusBox = $('#dw-desa-match-status');
            var $modeSelect = $('#id_desa_selection');
            var $kelSelect = $('#dw_desa');
            
            // Fungsi Cek Otomatis (AJAX)
            function checkAutoMatch() {
                var kel_id = $kelSelect.val();
                if (!kel_id) { $statusBox.hide(); return; }

                // Jika mode Manual dipilih, jangan jalankan cek AJAX (atau beri info saja)
                if ($modeSelect.val() !== 'auto') {
                    var selectedText = $modeSelect.find('option:selected').text();
                    $statusBox.html('<span class="dashicons dashicons-lock"></span> <strong>Mode Manual Aktif.</strong><br>Toko ini akan dihubungkan ke: <strong>' + selectedText + '</strong> (Mengabaikan alamat).')
                              .css({'borderColor': '#ffba00', 'backgroundColor': '#fff8e5', 'color': '#997404'}).show();
                    return;
                }

                $statusBox.html('Mendeteksi Desa Wisata...').show();
                
                $.post(ajaxurl, {
                    action: 'dw_check_desa_match_from_address',
                    nonce: '<?php echo wp_create_nonce("dw_admin_ajax_nonce"); ?>', // Gunakan nonce umum jika handler khusus belum ada
                    kel_id: kel_id
                }).done(function(res) {
                    if(res.success && res.data.matched) {
                         $statusBox.html('<span class="dashicons dashicons-yes-alt"></span> <strong>Terdeteksi: ' + res.data.nama_desa + '</strong><br>Toko akan otomatis masuk ke desa ini saat disimpan.')
                                   .css({'borderColor': '#c3e6cb', 'backgroundColor': '#d4edda', 'color': '#155724'});
                    } else {
                         $statusBox.html('<span class="dashicons dashicons-info"></span> <strong>Tidak Ada Desa Terdaftar.</strong><br>Toko ini akan berdiri sendiri (Milik Admin) atau bisa Anda tambahkan relasi manual nanti.')
                                   .css({'borderColor': '#bee5eb', 'backgroundColor': '#d1ecf1', 'color': '#0c5460'});
                    }
                });
            }

            // Trigger saat alamat berubah atau mode berubah
            $('#dw_provinsi, #dw_kabupaten, #dw_kecamatan, #dw_desa').change(function(){
                if ($modeSelect.val() === 'auto') checkAutoMatch();
            });
            $modeSelect.change(checkAutoMatch);
            
            // Run on load
            setTimeout(checkAutoMatch, 1000);
         });
     </script>
     <?php
 }
 ?>