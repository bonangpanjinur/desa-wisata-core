<?php
/**
 * File: includes/admin-pages/page-pedagang.php
 * Description: Manajemen CRUD Toko (Pedagang) + Auto Relasi Desa Wisata.
 * * PERBAIKAN PENTING:
 * 1. Menambahkan Transient Notices (set_transient) agar pesan error TIDAK HILANG saat redirect.
 * 2. Memperbaiki nama Action AJAX di script JS ('dw_get_wilayah' bukan 'dw_get_region_options').
 * 3. Memastikan logika Admin Desa memaksa ID Desa yang benar.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * --------------------------------------------------------------------------
 * HANDLER: SIMPAN / UPDATE DATA (POST)
 * --------------------------------------------------------------------------
 */
function dw_pedagang_form_handler() {
    if (!isset($_POST['dw_submit_pedagang'])) return;

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_save_pedagang_nonce')) {
        wp_die('Security check failed. Silakan refresh halaman.');
    }
    if (!current_user_can('dw_manage_pedagang')) {
        wp_die('Akses ditolak. Anda tidak memiliki izin.');
    }

    global $wpdb;
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';
    $current_user_id = get_current_user_id();

    // Cek Konteks Admin Desa
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $admin_desa_id  = 0;
    
    if (!$is_super_admin) {
        $admin_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE id_user_desa = %d", $current_user_id));
        if (!$admin_desa_id) {
            wp_die('Error Fatal: Akun Admin Desa Anda belum terhubung dengan data Desa manapun. Hubungi Super Admin.');
        }
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_new = ($id === 0);

    // 1. Validasi Input Wajib
    if (empty($_POST['id_user']) || empty($_POST['nama_toko']) || empty($_POST['nama_pemilik'])) {
        dw_set_notice('User, Nama Toko, dan Pemilik wajib diisi.', 'error');
        wp_redirect(dw_get_redirect_url($id)); exit;
    }

    // 2. Cek Duplikat User (Satu user hanya boleh punya satu toko)
    $id_user = intval($_POST['id_user']);
    $cek_sql = $is_new 
        ? "SELECT id FROM $table_pedagang WHERE id_user = $id_user" 
        : "SELECT id FROM $table_pedagang WHERE id_user = $id_user AND id != $id";
    
    if ($wpdb->get_var($cek_sql)) {
        dw_set_notice('Gagal: User WordPress yang dipilih sudah memiliki Toko lain.', 'error');
        wp_redirect(dw_get_redirect_url($id)); exit;
    }

    // 3. Persiapan Data
    $kel_id = sanitize_text_field($_POST['kelurahan_id_mirror'] ?? '');
    $zona_json = isset($_POST['shipping_ojek_lokal_zona']) ? wp_unslash($_POST['shipping_ojek_lokal_zona']) : '[]';
    if (is_null(json_decode($zona_json))) $zona_json = '[]';

    $data = [
        'id_user'       => $id_user,
        'nama_toko'     => sanitize_text_field($_POST['nama_toko']),
        'slug_toko'     => sanitize_title($_POST['nama_toko']),
        'nama_pemilik'  => sanitize_text_field($_POST['nama_pemilik']),
        'nomor_wa'      => sanitize_text_field($_POST['nomor_wa']),
        'alamat_lengkap'=> sanitize_textarea_field($_POST['alamat_lengkap_manual']),
        'url_gmaps'     => esc_url_raw($_POST['url_gmaps']),
        'api_kecamatan_id' => sanitize_text_field($_POST['kecamatan_id_mirror'] ?? ''),
        'status_akun'        => sanitize_text_field($_POST['status_akun']),
        'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran'] ?? 'menunggu_desa'),
        'nik'           => sanitize_text_field($_POST['nik']),
        'url_ktp'       => esc_url_raw($_POST['url_ktp']),
        'foto_profil'   => esc_url_raw($_POST['foto_profil']),
        'no_rekening'   => sanitize_text_field($_POST['bank_rekening']),
        'nama_bank'     => sanitize_text_field($_POST['bank_nama']),
        'atas_nama_rekening' => sanitize_text_field($_POST['bank_atas_nama']),
        'qris_image_url'     => esc_url_raw($_POST['qris_url']),
        'shipping_ojek_lokal_aktif' => isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0,
        'shipping_nasional_aktif'   => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,
        'shipping_ojek_lokal_zona'  => $zona_json
    ];

    // 4. Logika Relasi Desa
    if ($is_super_admin) {
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
            $manual_id = intval($pilihan_relasi);
            $id_desa_found = ($manual_id > 0) ? $manual_id : null;
        }
        $data['id_desa'] = $id_desa_found;
    } else {
        // Admin Desa: PAKSA masuk ke desa mereka sendiri
        $data['id_desa'] = $admin_desa_id;
        
        // Fallback: Jika data kecamatan kosong, isi dengan data desa admin agar tidak null
        if (empty($data['api_kecamatan_id'])) {
            $desa_loc = $wpdb->get_row("SELECT api_kecamatan_id FROM $table_desa WHERE id = $admin_desa_id");
            if ($desa_loc) $data['api_kecamatan_id'] = $desa_loc->api_kecamatan_id;
        }
    }

    // 5. Eksekusi Database
    if ($is_new) {
        $data['created_at'] = current_time('mysql');
        $inserted = $wpdb->insert($table_pedagang, $data);
        
        if ($inserted) {
            $new_id = $wpdb->insert_id;
            // Update Role WP
            $u = new WP_User($id_user);
            if ($u->exists() && !$u->has_cap('administrator')) $u->add_role('pedagang');

            dw_set_notice('Toko berhasil dibuat.', 'success');
            wp_redirect(admin_url('admin.php?page=dw-pedagang&action=edit&id='.$new_id)); exit;
        } else {
            dw_set_notice('Gagal menyimpan ke database: ' . $wpdb->last_error, 'error');
            wp_redirect(dw_get_redirect_url(0)); exit;
        }
    } else {
        $updated = $wpdb->update($table_pedagang, $data, ['id' => $id]);
        
        $u = new WP_User($id_user);
        if ($u->exists() && $data['status_akun'] === 'aktif' && !$u->has_cap('administrator')) {
            $u->add_role('pedagang');
        }
        
        dw_set_notice('Data Toko diperbarui.', 'success');
        wp_redirect(admin_url('admin.php?page=dw-pedagang&action=edit&id='.$id)); exit;
    }
}
add_action('admin_init', 'dw_pedagang_form_handler');

// Helper: Set Notice Transient (Agar pesan tidak hilang saat redirect)
function dw_set_notice($message, $type = 'success') {
    add_settings_error('dw_pedagang_notices', 'dw_msg', $message, $type);
    set_transient('settings_errors', get_settings_errors(), 30);
}

// Helper: Redirect URL
function dw_get_redirect_url($id = 0) {
    return ($id > 0) ? admin_url('admin.php?page=dw-pedagang&action=edit&id='.$id) : admin_url('admin.php?page=dw-pedagang&action=add');
}

// --- DELETE HANDLER ---
function dw_pedagang_delete_handler() {
    if (!isset($_GET['id']) || !isset($_GET['_wpnonce'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_pedagang_action')) wp_die('Security Fail');
    
    global $wpdb;
    $id = intval($_GET['id']);
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $current_user_id = get_current_user_id();
    
    // Cek Data
    $pedagang = $wpdb->get_row("SELECT id_user, nama_toko, id_desa FROM {$wpdb->prefix}dw_pedagang WHERE id = $id");
    if (!$pedagang) {
         dw_set_notice('Data tidak ditemukan.', 'error');
         wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
    }

    // Security Check: Admin Desa hanya boleh hapus pedagang desanya
    if (!$is_super_admin) {
        $my_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $current_user_id));
        if (!$my_desa_id || $my_desa_id != $pedagang->id_desa) {
            wp_die('Akses Ditolak. Anda tidak boleh menghapus pedagang dari desa lain.');
        }
    }

    // Hapus Produk & Data
    $produk_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='dw_produk' AND post_author = {$pedagang->id_user}");
    if (!empty($produk_ids)) { foreach($produk_ids as $pid) wp_delete_post($pid, true); }

    $wpdb->delete($wpdb->prefix.'dw_pedagang', ['id' => $id]);

    // Downgrade Role
    $u = new WP_User($pedagang->id_user);
    if ($u->exists() && !$u->has_cap('administrator')) {
        $u->remove_role('pedagang');
        $u->add_role('subscriber');
    }

    dw_set_notice("Toko '{$pedagang->nama_toko}' berhasil dihapus.", 'success');
    wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
}

// --- RENDER UTAMA ---
function dw_pedagang_page_render() {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'dw_delete') { dw_pedagang_delete_handler(); return; }
    if ($action === 'add' || $action === 'edit') { 
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        dw_pedagang_form_render($id); return; 
    }

    // List Table View
    if (!class_exists('DW_Pedagang_List_Table')) { require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pedagang-list-table.php'; }
    $table = new DW_Pedagang_List_Table();
    $table->prepare_items();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Toko (UMKM)</h1>
        <a href="?page=dw-pedagang&action=add" class="page-title-action">Tambah Toko Baru</a>
        <hr class="wp-header-end">
        <?php 
        // Tampilkan pesan error/sukses dari transient
        $errors = get_transient('settings_errors');
        if ($errors) {
            settings_errors('dw_pedagang_notices');
            delete_transient('settings_errors');
        }
        ?>
        <form method="get">
            <input type="hidden" name="page" value="dw-pedagang">
            <?php $table->search_box('Cari Toko', 's'); ?>
            <?php $table->display(); ?>
        </form>
    </div>
    <?php
}

// --- FORM RENDER ---
function dw_pedagang_form_render($id) {
    global $wpdb;
    $item = null;
    $title = "Tambah Toko Baru";
    $current_user_id = get_current_user_id();
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');

    // Data Context Admin Desa
    $my_desa_data = null;
    if (!$is_super_admin) {
        $my_desa_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa, api_kecamatan_id, api_kelurahan_id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $current_user_id));
        if (!$my_desa_data) {
            echo '<div class="notice notice-error"><p>Error: Akun Anda belum terhubung ke Desa. Hubungi Admin.</p></div>'; return;
        }
    }

    if ($id > 0) {
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $id));
        if (!$item) { echo "<div class='error'><p>Data tidak ditemukan.</p></div>"; return; }
        // Security check edit
        if (!$is_super_admin && $item->id_desa != $my_desa_data->id) {
            echo "<div class='error'><p>Akses Ditolak: Ini bukan pedagang desa Anda.</p></div>"; return;
        }
        $title = "Edit Toko: " . esc_html($item->nama_toko);
    }

    $users = get_users(['orderby' => 'display_name']);
    $zona_json = isset($item->shipping_ojek_lokal_zona) ? $item->shipping_ojek_lokal_zona : "[\n  {\"id\": \"zona_1\", \"nama\": \"Area Dekat (0-3km)\", \"harga\": 5000}\n]";
    function dw_img_preview($url, $placeholder = 'Foto') { return $url ? $url : "https://placehold.co/150x150/e2e8f0/64748b?text=$placeholder"; }
    $list_prov = function_exists('dw_get_api_provinsi') ? dw_get_api_provinsi() : [];
    
    // Tampilkan notifikasi di Form juga
    $errors = get_transient('settings_errors');
    if ($errors) { settings_errors('dw_pedagang_notices'); delete_transient('settings_errors'); }
    ?>
    <div class="wrap">
        <h1><?php echo $title; ?></h1>
        
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

                <!-- BOX 2: LOKASI & RELASI -->
                <div class="postbox" style="border-left: 4px solid #2271b1;">
                    <div class="postbox-header"><h2 class="hndle">2. Alamat & Relasi Desa</h2></div>
                    <div class="inside">
                        
                        <?php if ($is_super_admin): ?>
                            <!-- ADMIN BEBAS: FITUR LENGKAP -->
                            <p class="description">Pilih Kelurahan untuk menghubungkan toko ke Desa Wisata secara otomatis.</p>
                            <table class="form-table">
                                <tr>
                                    <th>Afiliasi Desa</th>
                                    <td>
                                        <select name="id_desa_selection" id="id_desa_selection" class="regular-text">
                                            <option value="auto" <?php echo ($item && empty($item->id_desa)) ? 'selected' : ''; ?>>⚡ Otomatis (Sesuai Alamat)</option>
                                            <optgroup label="--- Override Manual ---">
                                                <?php 
                                                $all_desas = $wpdb->get_results("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif'");
                                                foreach ($all_desas as $desa): ?>
                                                    <option value="<?php echo $desa->id; ?>" <?php selected($item->id_desa ?? '', $desa->id); ?>><?php echo esc_html($desa->nama_desa); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </td>
                                </tr>
                                <!-- PROVINSI -->
                                <tr><th>Provinsi</th><td>
                                    <select id="dw_provinsi" class="regular-text"><option value="">Pilih Provinsi</option><?php foreach($list_prov as $p) echo "<option value='{$p['code']}'>{$p['name']}</option>"; ?></select>
                                    <input type="hidden" name="provinsi_id_mirror" id="provinsi_id_mirror">
                                </td></tr>
                                <!-- KABUPATEN -->
                                <tr><th>Kabupaten</th><td>
                                    <select id="dw_kabupaten" class="regular-text" disabled><option>Pilih Kabupaten</option></select>
                                    <input type="hidden" name="kabupaten_id_mirror" id="kabupaten_id_mirror">
                                </td></tr>
                                <!-- KECAMATAN -->
                                <tr><th>Kecamatan</th><td>
                                    <select id="dw_kecamatan" class="regular-text" disabled><option>Pilih Kecamatan</option></select>
                                    <input type="hidden" name="kecamatan_id_mirror" id="kecamatan_id_mirror" value="<?php echo esc_attr($item->api_kecamatan_id ?? ''); ?>">
                                </td></tr>
                                <!-- KELURAHAN -->
                                <tr><th>Kelurahan/Desa</th><td>
                                    <select id="dw_desa" class="regular-text" disabled><option>Pilih Kelurahan</option></select>
                                    <input type="hidden" name="kelurahan_id_mirror" id="kelurahan_id_mirror">
                                </td></tr>
                            </table>

                        <?php else: ?>
                            <!-- ADMIN DESA: TERKUNCI & SEDERHANA -->
                            <div style="background:#e6fffa; color:#045f57; padding:15px; border-radius:5px; border:1px solid #b2f5ea;">
                                <p><strong>Terhubung ke Desa:</strong> <?php echo esc_html($my_desa_data->nama_desa); ?></p>
                                <p class="description">Pedagang ini akan otomatis terdaftar di bawah desa Anda. Alamat kecamatan/kelurahan akan mengikuti data desa jika tidak diisi manual.</p>
                            </div>
                            <!-- Hidden input mirrors agar form handler tidak error -->
                            <input type="hidden" name="kecamatan_id_mirror" value="<?php echo esc_attr($my_desa_data->api_kecamatan_id); ?>">
                            <input type="hidden" name="kelurahan_id_mirror" value="<?php echo esc_attr($my_desa_data->api_kelurahan_id); ?>">
                        <?php endif; ?>

                        <table class="form-table">
                            <tr><th>Detail Jalan</th><td><textarea name="alamat_lengkap_manual" class="large-text" rows="2"><?php echo esc_textarea($item->alamat_lengkap ?? ''); ?></textarea></td></tr>
                            <tr><th>Maps URL</th><td><input type="text" name="url_gmaps" value="<?php echo esc_attr($item->url_gmaps ?? ''); ?>" class="regular-text"></td></tr>
                        </table>
                    </div>
                </div>

                <!-- BOX 3 & 4 (STATUS & BANK) -->
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle">3. Pengiriman & Status</h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <tr><th>Status Pendaftaran</th><td>
                                <select name="status_pendaftaran">
                                    <option value="menunggu_desa" <?php selected($item->status_pendaftaran ?? '', 'menunggu_desa'); ?>>Menunggu Desa</option>
                                    <option value="disetujui" <?php selected($item->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                                    <option value="ditolak" <?php selected($item->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                                </select>
                            </td></tr>
                            <tr><th>Status Akun</th><td>
                                <select name="status_akun">
                                    <option value="aktif" <?php selected($item->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                                    <option value="nonaktif" <?php selected($item->status_akun ?? '', 'nonaktif'); ?>>Nonaktif</option>
                                </select>
                            </td></tr>
                            <tr><th>Opsi Pengiriman</th><td>
                                <label><input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked(1, $item->shipping_ojek_lokal_aktif ?? 0); ?>> Ojek Lokal</label><br>
                                <label><input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked(1, $item->shipping_nasional_aktif ?? 0); ?>> Ekspedisi Nasional</label>
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
    
    <!-- SCRIPT AJAX WILAYAH HANYA JIKA SUPER ADMIN -->
    <?php if ($is_super_admin): ?>
    <script>
    jQuery(document).ready(function($){
        // PERBAIKAN: Menggunakan action 'dw_get_wilayah' bukan 'dw_get_region_options'
        function loadRegion(type, parentId, targetSelector) {
            if(!parentId) return;
            $(targetSelector).prop('disabled', true).html('<option>Loading...</option>');
            $.get(dw_admin_vars.ajax_url, { 
                action: 'dw_get_wilayah', // <-- ACTION YANG BENAR
                type: type, 
                id: parentId, // <-- PARAM YANG BENAR (id bukan parent_id)
                nonce: dw_admin_vars.nonce 
            }, function(res) {
                if(res.success) {
                    var options = '<option value="">-- Pilih --</option>';
                    $.each(res.data, function(i, item){
                        options += '<option value="' + item.code + '">' + item.name + '</option>';
                    });
                    $(targetSelector).html(options).prop('disabled', false);
                } else {
                    $(targetSelector).html('<option>Gagal memuat</option>');
                }
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
        });
    });
    </script>
    <?php endif; ?>
    <?php
}
?>