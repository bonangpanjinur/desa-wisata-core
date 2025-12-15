<?php
/**
 * File Path: includes/admin-pages/page-pedagang.php
 *
 * --- UPDATE FITUR (AUTO-RELASI DESA) ---
 * - Saat Admin menyimpan data Toko, sistem otomatis mengecek apakah Alamat (Kelurahan)
 * yang dipilih cocok dengan salah satu Desa Wisata Aktif.
 * - Jika cocok, Toko otomatis dihubungkan ke Desa tersebut (kolom id_desa terisi).
 * - Menambahkan feedback visual AJAX di form agar Admin tahu status koneksinya sebelum menyimpan.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
  * Form handler untuk update data pedagang (Form Utama).
  */
function dw_pedagang_form_handler() {
    if (!isset($_POST['dw_submit_pedagang'])) { return; }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $redirect_url = admin_url('admin.php?page=dw-pedagang');
    if ($id > 0) { $redirect_url = admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $id); }
    else { $redirect_url = admin_url('admin.php?page=dw-pedagang&action=add'); }

    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_pedagang_nonce')) {
        wp_die('Security check failed.');
        exit;
    }
    if (!current_user_can('dw_manage_pedagang')) {
        wp_die('Anda tidak memiliki izin.');
        exit;
    }


    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';

    // Validasi input dasar
    if (empty($_POST['id_user']) || empty($_POST['nama_toko']) || empty($_POST['nama_pemilik'])) {
        add_settings_error('dw_pedagang_notices', 'empty_fields', 'User, Nama Toko, dan Nama Pemilik wajib diisi.', 'error');
        set_transient('dw_pedagang_form_data', $_POST, 60);
        set_transient('settings_errors', get_settings_errors(), 30); wp_redirect($redirect_url); exit;
    }
    $nomor_wa = sanitize_text_field($_POST['nomor_wa']);
    
    // Validasi sederhana WA
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
        'url_ktp' => esc_url_raw($_POST['url_ktp'] ?? null),
        'deskripsi_toko'=> sanitize_textarea_field($_POST['deskripsi_toko'] ?? null), 
        'status_akun' => $status_akun_input,
        'no_rekening' => sanitize_text_field($_POST['bank_rekening'] ?? null), 
        'nama_bank' => sanitize_text_field($_POST['bank_nama'] ?? null),
        'atas_nama_rekening'=> sanitize_text_field($_POST['bank_atas_nama'] ?? null), 
        'qris_image_url' => esc_url_raw($_POST['qris_url'] ?? null),
    ];
    
    // --- DATA ALAMAT DARI FORM ADMIN ---
    $data['api_provinsi_id'] = isset($_POST['provinsi_id']) ? sanitize_text_field($_POST['provinsi_id']) : null;
    $data['api_kabupaten_id'] = isset($_POST['kabupaten_id']) ? sanitize_text_field($_POST['kabupaten_id']) : null;
    $data['api_kecamatan_id'] = isset($_POST['kecamatan_id']) ? sanitize_text_field($_POST['kecamatan_id']) : null;
    $data['api_kelurahan_id'] = isset($_POST['kelurahan_id']) ? sanitize_text_field($_POST['kelurahan_id']) : null;
    
    // Simpan nama wilayah juga (cache)
    $data['provinsi_nama'] = isset($_POST['provinsi_nama']) ? sanitize_text_field($_POST['provinsi_nama']) : null;
    $data['kabupaten_nama'] = isset($_POST['kabupaten_nama']) ? sanitize_text_field($_POST['kabupaten_nama']) : null;
    $data['kecamatan_nama'] = isset($_POST['kecamatan_nama']) ? sanitize_text_field($_POST['kecamatan_nama']) : null;
    $data['kelurahan_nama'] = isset($_POST['desa_nama']) ? sanitize_text_field($_POST['desa_nama']) : null;
    $data['alamat_lengkap'] = isset($_POST['alamat_lengkap_manual']) ? sanitize_textarea_field($_POST['alamat_lengkap_manual']) : ''; 

    // =====================================================================
    // LOGIKA PENTING: AUTO-MATCH DESA (RELASI OTOMATIS)
    // =====================================================================
    $matched_desa_id = NULL;
    // PERBAIKAN: Hanya gunakan ID Kelurahan sebagai kunci pencocokan utama (agar konsisten dengan AJAX/Visual)
    if (!empty($data['api_kelurahan_id'])) {
        // Cari di tabel dw_desa, apakah ada desa yang memiliki ID Kelurahan yang sama
        // Dan pastikan desa tersebut statusnya 'aktif'
        $matched_desa_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dw_desa 
             WHERE api_kelurahan_id = %s 
             AND status = 'aktif'
             LIMIT 1", // Ambil 1 yang cocok
            $data['api_kelurahan_id']
        ));
    }
    
    // Jika ketemu, set id_desa. Jika tidak, set NULL (toko berdiri sendiri).
    $data['id_desa'] = $matched_desa_id ? (int)$matched_desa_id : null;
    // =====================================================================

    // Hanya Super Admin yang bisa edit 'sisa_transaksi' secara manual
    if (current_user_can('administrator') && isset($_POST['sisa_transaksi'])) {
        $data['sisa_transaksi'] = absint($_POST['sisa_transaksi']);
    }

    if ($status_pendaftaran_input !== null) { $data['status_pendaftaran'] = $status_pendaftaran_input; }
    $is_new_pedagang = ($id === 0);

    if ($id > 0) { // Jika EDIT
        $pedagang_data_lama = $wpdb->get_row($wpdb->prepare("SELECT id_user, status_akun, status_pendaftaran FROM $table_name WHERE id = %d", $id));
        $id_user_lama = $pedagang_data_lama ? absint($pedagang_data_lama->id_user) : 0;

        $updated = $wpdb->update($table_name, $data, ['id' => $id]);

        if ($updated !== false) {
             $success_msg = 'Data Toko berhasil diperbarui.';
             if ($data['id_desa']) {
                 $success_msg .= ' Toko terhubung dengan Desa ID #' . $data['id_desa'] . '.';
             } else {
                 $success_msg .= ' (Toko tidak terhubung ke Desa Wisata manapun).';
             }
             add_settings_error('dw_pedagang_notices', 'pedagang_updated', $success_msg, 'success');

             // Handle perubahan User ID & Role
             if ($id_user_lama > 0 && $id_user_baru !== $id_user_lama) {
                 $user_lama = get_userdata($id_user_lama);
                 if ($user_lama && !$user_lama->has_cap('administrator')) {
                     $user_lama->remove_role('pedagang');
                     if (empty($user_lama->roles)) { $user_lama->add_role('subscriber'); }
                 }
                 
                 $user_baru = get_userdata($id_user_baru);
                 if ($status_akun_input === 'aktif' && $user_baru) {
                     $user_baru->add_role('pedagang');
                 } 
                 elseif ($user_baru && !$user_baru->has_cap('administrator') && !$user_baru->has_cap('pedagang')) {
                      if (empty($user_baru->roles)) { $user_baru->set_role('subscriber'); }
                 }
                 if (function_exists('dw_log_activity')) dw_log_activity('PEDAGANG_USER_CHANGED', "User Toko #{$id} diubah dari #{$id_user_lama} ke #{$id_user_baru}.", get_current_user_id());
             }

             // Handle Aktivasi/Deaktivasi Role
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
             error_log("[DW Plugin] DB Update failed for Pedagang ID {$id}. Error: " . $wpdb->last_error);
             add_settings_error('dw_pedagang_notices', 'update_failed', 'Gagal memperbarui data toko.', 'error');
        }

    } else { // Jika TAMBAH BARU
        $options = get_option('dw_settings');
        $default_kuota = isset($options['kuota_gratis_default']) ? absint($options['kuota_gratis_default']) : 0; 

        $data['status_pendaftaran'] = $status_pendaftaran_input ?? 'menunggu_desa'; 
        $data['status_akun'] = $status_akun_input ?? 'nonaktif'; 
        $data['sisa_transaksi'] = $default_kuota; 
        $data['created_at'] = current_time('mysql');

        $inserted_pedagang = $wpdb->insert($table_name, $data);
        if ($inserted_pedagang === false) {
            add_settings_error('dw_pedagang_notices', 'insert_failed', 'Gagal menyimpan toko baru.', 'error');
            set_transient('dw_pedagang_form_data', $_POST, 60);
            set_transient('settings_errors', get_settings_errors(), 30); wp_redirect($redirect_url); exit;
        }
        $id = $wpdb->insert_id;

        $msg = 'Data Toko berhasil dibuat.';
        if ($data['id_desa']) $msg .= ' Otomatis terhubung ke Desa Wisata setempat.';
        add_settings_error('dw_pedagang_notices', 'pedagang_created', $msg, 'success');

        // Setup Role Awal
        $user = get_userdata($id_user_baru);
        if ($user && !user_can($user, 'administrator') && !$user->has_cap('pedagang')) {
            if (empty($user->roles)) { $user->set_role('subscriber'); }
        }
    }

    delete_transient('dw_pedagang_form_data');
    set_transient('settings_errors', get_settings_errors(), 30);

    $final_redirect_url = admin_url('admin.php?page=dw-pedagang');
    if (get_settings_errors('dw_pedagang_notices') || !$is_new_pedagang) {
        $final_redirect_url = admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $id);
    }
    wp_redirect($final_redirect_url);
    exit;
 }
 add_action('admin_init', 'dw_pedagang_form_handler');


 /**
  * Fungsi render halaman utama (list table).
  */
 function dw_pedagang_page_render() {
     $action = isset($_GET['action']) ? $_GET['action'] : 'list';
     $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

     // Handler Hapus
     if ('dw_delete' === $action && $id > 0) {
          $nonce = $_GET['_wpnonce'] ?? '';
          if (!wp_verify_nonce($nonce, 'dw_delete_pedagang_action')) {
               add_settings_error('dw_pedagang_notices', 'security_failed', 'Nonce invalid.', 'error');
               set_transient('settings_errors', get_settings_errors(), 30);
               wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
          }
          if (function_exists('dw_pedagang_delete_handler')) {
               dw_pedagang_delete_handler(); 
          } else {
                add_settings_error('dw_pedagang_notices', 'handler_missing', 'Fungsi hapus tidak ditemukan.', 'error');
                set_transient('settings_errors', get_settings_errors(), 30);
          }
          wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
     }

     if ('add' === $action || ('edit' === $action && $id > 0)) {
         dw_pedagang_form_render($id); return;
     }

     if (!class_exists('DW_Pedagang_List_Table')) { require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pedagang-list-table.php'; }
     $pedagangListTable = new DW_Pedagang_List_Table();
     $pedagangListTable->prepare_items();
     ?>
     <div class="wrap dw-wrap">
         <div class="dw-header">
             <h1>Manajemen Toko</h1>
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
  * Form untuk Tambah/Edit Pedagang.
  */
 function dw_pedagang_form_render($id = 0) {
     global $wpdb;
     $item = null; 
     $page_title = 'Tambah Toko Baru';
     $is_super_admin = current_user_can('administrator');

     $transient_data = get_transient('dw_pedagang_form_data');
     if ($transient_data) {
         $item = (object) $transient_data; 
         delete_transient('dw_pedagang_form_data');
         $id = isset($item->id) ? intval($item->id) : $id; 
         if ($id > 0) $page_title = 'Edit Toko'; 
     } elseif ($id > 0) {
         $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $id));
         if (!$item) { wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit; }
         $page_title = 'Edit Toko';
     }

     // List User
     $current_pedagang_user_id = $item->id_user ?? 0;
     $existing_pedagang_user_ids = $wpdb->get_col( "SELECT id_user FROM {$wpdb->prefix}dw_pedagang WHERE id_user > 0" );
     $args = [ 'orderby' => 'display_name' ];
     $exclude_ids = [];
     if ($id > 0) {
         $exclude_ids = array_diff($existing_pedagang_user_ids, [$current_pedagang_user_id]);
     } else {
         $exclude_ids = $existing_pedagang_user_ids;
     }
     $exclude_ids = array_filter(array_map('absint', $exclude_ids), function($val) { return $val > 0; });
     if (!empty($exclude_ids)) { $args['exclude'] = $exclude_ids; }
     $users = get_users($args);
     if ($id > 0 && $current_pedagang_user_id > 0) {
         $current_user_obj = get_userdata($current_pedagang_user_id);
         if ($current_user_obj) {
             $user_exists = false;
             foreach ($users as $u) { if ($u->ID == $current_pedagang_user_id) { $user_exists = true; break; } }
             if (!$user_exists) { array_unshift($users, $current_user_obj); }
         }
     }

     $qris_url = $item->qris_image_url ?? '';
     $current_status_akun = $item->status_akun ?? 'nonaktif';
     $current_status_pendaftaran = $item->status_pendaftaran ?? ($id > 0 ? 'menunggu' : 'menunggu_desa');
     $pendaftaran_statuses = ['menunggu','menunggu_desa', 'disetujui', 'ditolak']; 
     
    // Data Alamat
    $provinsi_id    = $item->api_provinsi_id ?? '';
    $kabupaten_id   = $item->api_kabupaten_id ?? '';
    $kecamatan_id   = $item->api_kecamatan_id ?? '';
    $kelurahan_id   = $item->api_kelurahan_id ?? '';

    $provinsi_list  = dw_get_api_provinsi();
    $kabupaten_list = !empty($provinsi_id) ? dw_get_api_kabupaten($provinsi_id) : [];
    $kecamatan_list = !empty($kabupaten_id) ? dw_get_api_kecamatan($kabupaten_id) : [];
    $desa_list_api  = !empty($kecamatan_id) ? dw_get_api_desa($kecamatan_id) : [];
     
     $sisa_kuota = $item->sisa_transaksi ?? 0; 
     if ($id === 0 && !$transient_data) { 
         $options = get_option('dw_settings');
         $sisa_kuota = isset($options['kuota_gratis_default']) ? absint($options['kuota_gratis_default']) : 0; 
     }

     ?>
     <div class="wrap dw-wrap">
         <h1><?php echo esc_html($page_title); ?></h1>
         <?php settings_errors('dw_pedagang_notices'); ?>

         <form method="post" class="dw-form-card"> 
              <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
             <?php wp_nonce_field('dw_save_pedagang_nonce'); ?>
             <input type="hidden" name="dw_submit_pedagang" value="1">

             <h3 style="margin-top: 0;"><span class="dashicons dashicons-admin-users"></span> Informasi Dasar</h3>
              <table class="form-table dw-form-table">
                  <tr><th><label for="id_user">Pengguna WordPress <span style="color:red;">*</span></label></th><td><select name="id_user" id="id_user" required style="width: 100%;"><option value="">-- Pilih Pengguna --</option><?php foreach($users as $user) : ?><option value="<?php echo esc_attr($user->ID); ?>" <?php selected($item->id_user ?? '', $user->ID); ?>><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option><?php endforeach; ?></select><p class="description">Pilih user WP yang akan menjadi pedagang ini.</p></td></tr>
                  <tr><th><label for="nama_toko">Nama Toko / Usaha <span style="color:red;">*</span></label></th><td><input name="nama_toko" id="nama_toko" type="text" value="<?php echo esc_attr($item->nama_toko ?? ''); ?>" required></td></tr>
                  <tr><th><label for="nama_pemilik">Nama Pemilik <span style="color:red;">*</span></label></th><td><input name="nama_pemilik" id="nama_pemilik" type="text" value="<?php echo esc_attr($item->nama_pemilik ?? ''); ?>" required></td></tr>
                  
                  <tr><th><label for="nomor_wa">Nomor WhatsApp</label></th><td><input name="nomor_wa" id="nomor_wa" type="text" value="<?php echo esc_attr($item->nomor_wa ?? ''); ?>" placeholder="Contoh: 6281234567890"><p class="description">Gunakan format internasional (62xxxxxx).</p></td></tr>
                  <tr><th><label for="url_gmaps">URL Google Maps</label></th><td><input name="url_gmaps" id="url_gmaps" type="url" class="regular-text" value="<?php echo esc_attr($item->url_gmaps ?? ''); ?>" placeholder="https://maps.app.goo.gl/xxxxxx"><p class="description">Salin URL lokasi dari Google Maps.</p></td></tr>
                  <tr><th><label for="deskripsi_toko">Deskripsi Toko</label></th><td><textarea name="deskripsi_toko" id="deskripsi_toko" rows="3"><?php echo esc_textarea($item->deskripsi_toko ?? ''); ?></textarea></td></tr>
             </table>

             <hr>
             <h3><span class="dashicons dashicons-location"></span> Alamat Toko & Relasi Desa</h3>
             <p class="description">Pilih lokasi toko. Jika <strong>Desa/Kelurahan</strong> yang dipilih terdaftar sebagai Desa Wisata Aktif, toko ini akan <strong>otomatis terhubung</strong>.</p>
             
             <div class="dw-address-wrapper">
                <table class="form-table">
                    <tr><th><label for="dw_provinsi">Provinsi <span style="color:red;">*</span></label></th>
                        <td><select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select" style="width: 100%;" required>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinsi_list as $prov) : ?>
                                <option value="<?php echo esc_attr($prov['code']); ?>" <?php selected($provinsi_id, $prov['code']); ?>><?php echo esc_html($prov['name']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                     <tr><th><label for="dw_kabupaten">Kabupaten/Kota <span style="color:red;">*</span></label></th>
                        <td><select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select" <?php disabled(empty($kabupaten_list)); ?> style="width: 100%;" required>
                            <option value="">Pilih Provinsi Dulu</option>
                            <?php foreach ($kabupaten_list as $kab) : ?>
                                <option value="<?php echo esc_attr($kab['code']); ?>" <?php selected($kabupaten_id, $kab['code']); ?>><?php echo esc_html($kab['name']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                    <tr><th><label for="dw_kecamatan">Kecamatan <span style="color:red;">*</span></label></th>
                        <td><select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select" <?php disabled(empty($kecamatan_list)); ?> style="width: 100%;" required>
                            <option value="">Pilih Kabupaten Dulu</option>
                             <?php foreach ($kecamatan_list as $kec) : ?>
                                <option value="<?php echo esc_attr($kec['code']); ?>" <?php selected($kecamatan_id, $kec['code']); ?>><?php echo esc_html($kec['name']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                     <tr><th><label for="dw_desa">Desa/Kelurahan <span style="color:red;">*</span></label></th>
                        <td><select name="kelurahan_id" id="dw_desa" class="dw-desa-select" <?php disabled(empty($desa_list_api)); ?> style="width: 100%;" required>
                            <option value="">Pilih Kecamatan Dulu</option>
                             <?php foreach ($desa_list_api as $desa) : ?>
                                <option value="<?php echo esc_attr($desa['code']); ?>" <?php selected($kelurahan_id, $desa['code']); ?>><?php echo esc_html($desa['name']); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                    <tr><th><label for="alamat_lengkap_manual">Detail Jalan</label></th>
                        <td><textarea name="alamat_lengkap_manual" id="alamat_lengkap_manual" rows="2" placeholder="Contoh: Jln. Merdeka No. 10, RT 01/RW 02"><?php echo esc_textarea($item->alamat_lengkap ?? ''); ?></textarea>
                            <p class="description">Alamat jalan, RT/RW, atau patokan.</p></td>
                        </td>
                    </tr>
                </table>
                
                <!-- FEEDBACK VISUAL UNTUK AUTO-RELASI -->
                <div id="dw-desa-match-status" style="margin-top: 15px; padding: 15px; border-radius: 6px; display: none; border: 1px solid transparent;"></div>

                <!-- HIDDEN INPUTS UNTUK NAMA WILAYAH -->
                <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr($item->provinsi_nama ?? ''); ?>">
                <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr($item->kabupaten_nama ?? ''); ?>">
                <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr($item->kecamatan_nama ?? ''); ?>">
                <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr($item->kelurahan_nama ?? ''); ?>">
            </div>

             <hr>
             <h3><span class="dashicons dashicons-bank"></span> Informasi Pembayaran</h3>
             <table class="form-table dw-form-table">
                  <tr><th><label for="bank_rekening">Nomor Rekening</label></th><td><input name="bank_rekening" id="bank_rekening" type="text" value="<?php echo esc_attr($item->no_rekening ?? ''); ?>" placeholder="Contoh: 1234567890"></td></tr>
                  <tr><th><label for="bank_nama">Nama Bank</label></th><td><input name="bank_nama" id="bank_nama" type="text" value="<?php echo esc_attr($item->nama_bank ?? ''); ?>" placeholder="Contoh: BCA / Bank Mandiri"></td></tr>
                  <tr><th><label for="bank_atas_nama">Atas Nama</label></th><td><input name="bank_atas_nama" id="bank_atas_nama" type="text" value="<?php echo esc_attr($item->atas_nama_rekening ?? ''); ?>" placeholder="Contoh: Budi Santoso"></td></tr>
                  <tr><th><label for="qris_url">QRIS (Gambar)</label></th><td><div class="dw-image-uploader-wrapper"><img src="<?php echo esc_url($qris_url ?: 'https://placehold.co/150x150/e2e8f0/64748b?text=QRIS'); ?>" data-default-src="https://placehold.co/150x150/e2e8f0/64748b?text=QRIS" class="dw-image-preview" alt="QRIS" style="width:150px; height:150px; object-fit:contain; border-radius:4px; border:1px solid #ddd;"/><input name="qris_url" type="hidden" value="<?php echo esc_attr($qris_url); ?>" class="dw-image-url"><button type="button" class="button dw-upload-button">Pilih/Ubah Gambar QRIS</button><button type="button" class="button button-link-delete dw-remove-image-button" style="<?php echo empty($qris_url) ? 'display:none;' : ''; ?>">Hapus Gambar</button></div></td></tr>
             </table>
             <hr>
             <h3><span class="dashicons dashicons-admin-generic"></span> Status & Kuota</h3>
             <table class="form-table dw-form-table">
                 <tr>
                     <th><label for="status_pendaftaran">Status Pendaftaran</label></th>
                     <td>
                         <?php if ($is_super_admin): ?>
                             <select name="status_pendaftaran" id="status_pendaftaran">
                                 <?php foreach($pendaftaran_statuses as $status): ?>
                                     <option value="<?php echo esc_attr($status); ?>" <?php selected($current_status_pendaftaran, $status); ?>>
                                         <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                     </option>
                                 <?php endforeach; ?>
                             </select>
                         <?php else: ?>
                              <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', esc_attr($current_status_pendaftaran))); ?>" disabled style="background-color: #f0f0f1;">
                              <input type="hidden" name="status_pendaftaran" id="status_pendaftaran" value="<?php echo esc_attr($current_status_pendaftaran); ?>"> 
                         <?php endif; ?>
                     </td>
                 </tr>

                 <tr>
                     <th><label for="status_akun">Status Akun</label></th>
                     <td>
                         <select name="status_akun" id="status_akun">
                             <option value="nonaktif" <?php selected($current_status_akun, 'nonaktif'); ?>>Nonaktif (Manual)</option>
                             <option value="aktif" <?php selected($current_status_akun, 'aktif'); ?> >Aktif</option>
                             <option value="nonaktif_habis_kuota" <?php selected($current_status_akun, 'nonaktif_habis_kuota'); ?>>Nonaktif (Kuota Habis)</option>
                         </select>
                         <p class="description" id="status_akun_desc">
                             <?php if ($current_status_akun === 'aktif'): ?> Akun Aktif. <?php elseif ($current_status_akun === 'nonaktif_habis_kuota'): ?> Akun dibekukan (kuota habis). <?php endif; ?>
                         </p> 
                     </td>
                 </tr>
                 
                 <tr>
                     <th><label for="sisa_transaksi">Sisa Kuota</label></th>
                     <td>
                         <input name="sisa_transaksi" id="sisa_transaksi" type="number" step="1" min="0" 
                                value="<?php echo esc_attr($sisa_kuota); ?>" 
                                <?php disabled(!$is_super_admin); ?>
                                class="small-text">
                     </td>
                 </tr>

             </table>
              <div class="dw-form-footer"> <?php submit_button('Simpan Perubahan Data', 'primary', 'dw_submit_pedagang', false); ?> <a href="<?php echo admin_url('admin.php?page=dw-pedagang'); ?>" class="button button-secondary">Kembali ke Daftar</a> </div> </form>
              
     </div>
     <script>
         jQuery(function($){
             // Select2 User
             if ($('#id_user').length > 0 && typeof $.fn.select2 === 'function') {
                  $('#id_user').select2({ width: '100%', placeholder: 'Ketik nama/email...', allowClear: true });
             }

            // Logic Status Akun
            var $statusPendaftaran = $('#status_pendaftaran');
            var $statusAkun = $('#status_akun');
            var $statusAkunDesc = $('#status_akun_desc');

            function updateStatusAkunLogic() {
                var statusPendaftaranVal = $statusPendaftaran.val();
                if (statusPendaftaranVal === 'disetujui') {
                    $statusAkun.prop('disabled', false);
                } else {
                    $statusAkun.val('nonaktif').prop('disabled', true);
                    $statusAkunDesc.text('Akun hanya bisa aktif jika pendaftaran disetujui.');
                }
            }
            updateStatusAkunLogic(); 
            $statusPendaftaran.on('change', updateStatusAkunLogic); 

            // Logic Auto Match Desa
            var $provSelect = $('#dw_provinsi');
            var $kabSelect = $('#dw_kabupaten');
            var $kecSelect = $('#dw_kecamatan');
            var $kelSelect = $('#dw_desa'); 
            var $statusBox = $('#dw-desa-match-status');

            function checkDesaMatch() {
                var kel_id = $kelSelect.val();
                var kec_id = $kecSelect.val();
                var kab_id = $kabSelect.val();

                if (!kel_id || !kec_id || !kab_id) {
                    $statusBox.hide();
                    return;
                }
                
                $statusBox.html('<span class="dashicons dashicons-update spin"></span> Mengecek ketersediaan Desa Wisata...').css({'borderColor': '#ddd', 'backgroundColor': '#f9f9f9', 'color': '#666'}).show();

                $.post(dw_admin_vars.ajax_url, {
                    action: 'dw_check_desa_match_from_address',
                    nonce: dw_admin_vars.nonce,
                    kel_id: kel_id,
                    kec_id: kec_id,
                    kab_id: kab_id
                }).done(function(response) {
                    if (response.success) {
                        if (response.data.matched) {
                            $statusBox.html('<span class="dashicons dashicons-yes-alt"></span> <strong>Terhubung Otomatis!</strong><br>Alamat ini terdaftar sebagai Desa Wisata <strong>' + response.data.nama_desa + '</strong>. Toko akan otomatis menjadi bagian dari desa ini.')
                                      .css({'borderColor': '#c3e6cb', 'backgroundColor': '#d4edda', 'color': '#155724'});
                        } else {
                            $statusBox.html('<span class="dashicons dashicons-info"></span> <strong>Info:</strong> Alamat ini belum terdaftar sebagai Desa Wisata. Toko akan berdiri sendiri (Independen).')
                                      .css({'borderColor': '#bee5eb', 'backgroundColor': '#d1ecf1', 'color': '#0c5460'});
                        }
                    } else {
                        $statusBox.html('Gagal mengecek kecocokan desa.').css({'borderColor': '#f5c6cb', 'backgroundColor': '#f8d7da', 'color': '#721c24'});
                    }
                }).fail(function() {
                     $statusBox.html('Error koneksi.').css({'borderColor': '#f5c6cb', 'backgroundColor': '#f8d7da', 'color': '#721c24'});
                });
            }

            $kelSelect.on('change', checkDesaMatch);
            // Cek saat load (jika edit)
            if ($kelSelect.val()) {
                // Beri jeda sedikit agar nama desa terload di select2/dropdown jika pakai ajax load
                setTimeout(checkDesaMatch, 1000); 
            }
         });
     </script>
     <?php
 }
 ?>