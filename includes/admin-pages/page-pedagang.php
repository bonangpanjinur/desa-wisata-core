<?php
/**
 * File: includes/admin-pages/page-pedagang.php
 * Description: CRUD Toko (Pedagang) dengan Tampilan Tabel Modern & Upload QRIS.
 * * [UPDATED]
 * - Tampilan tabel dipercantik dengan CSS Badge dan Thumbnail.
 * - Kolom Foto Profil ditambahkan.
 * - Layout aksi dan status lebih rapi.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * --------------------------------------------------------------------------
 * 1. HANDLER: SIMPAN & HAPUS DATA
 * --------------------------------------------------------------------------
 */
function dw_pedagang_form_handler() {
    // Cek tombol submit
    if (!isset($_POST['dw_submit_pedagang'])) return;

    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_pedagang_nonce')) wp_die('Security Fail');
    if (!current_user_can('dw_manage_pedagang')) wp_die('Akses Ditolak');

    global $wpdb;
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';
    $current_user_id = get_current_user_id();

    // Cek Role Admin
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $admin_desa_id  = 0;
    
    // Jika Admin Desa, paksa ID Desa
    if (!$is_super_admin) {
        $admin_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE id_user_desa = %d", $current_user_id));
        if (!$admin_desa_id) wp_die('Akun Anda belum terhubung dengan Desa manapun.');
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_new = ($id === 0);

    // --- Validasi Data ---
    if (empty($_POST['id_user']) || empty($_POST['nama_toko']) || empty($_POST['nama_pemilik'])) {
        dw_set_pedagang_notice('User, Nama Toko, dan Pemilik wajib diisi.', 'error');
        wp_redirect(admin_url('admin.php?page=dw-pedagang&action='. ($id>0 ? 'edit&id='.$id : 'add'))); exit;
    }

    // JSON Handling
    $raw_zona = isset($_POST['shipping_ojek_lokal_zona']) ? wp_unslash($_POST['shipping_ojek_lokal_zona']) : '[]';
    $zona_json = (json_decode($raw_zona) !== null) ? $raw_zona : '[]';

    $data = [
        'id_user'       => intval($_POST['id_user']),
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
        
        // Data Keuangan
        'no_rekening'   => sanitize_text_field($_POST['no_rekening']),
        'nama_bank'     => sanitize_text_field($_POST['nama_bank']),
        'atas_nama_rekening' => sanitize_text_field($_POST['atas_nama_rekening']),
        'qris_image_url'     => esc_url_raw($_POST['qris_image_url']), 
        
        'shipping_ojek_lokal_aktif' => isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0,
        'shipping_nasional_aktif'   => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,
        'shipping_ojek_lokal_zona'  => $zona_json
    ];

    // --- Relasi Desa ---
    if ($is_super_admin) {
        $pilihan_relasi = isset($_POST['id_desa_selection']) ? sanitize_text_field($_POST['id_desa_selection']) : 'auto';
        if ($pilihan_relasi !== 'auto') {
            $data['id_desa'] = intval($pilihan_relasi);
        } else {
            // Logic auto based on kelurahan (optional)
        }
    } else {
        $data['id_desa'] = $admin_desa_id;
    }

    // --- Simpan ke DB ---
    if ($is_new) {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table_pedagang, $data);
        
        // Update Role
        $u = new WP_User($data['id_user']);
        if ($u->exists() && !$u->has_cap('administrator')) $u->add_role('pedagang');

        dw_set_pedagang_notice('Toko berhasil dibuat. User kini memiliki akses Pedagang.', 'success');
    } else {
        $wpdb->update($table_pedagang, $data, ['id' => $id]);
        dw_set_pedagang_notice('Data Toko berhasil diperbarui.', 'success');
    }

    // REDIRECT KE LIST TABEL
    wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
}
add_action('admin_init', 'dw_pedagang_form_handler');

function dw_set_pedagang_notice($message, $type = 'success') {
    add_settings_error('dw_pedagang_notices', 'dw_msg', $message, $type);
    set_transient('settings_errors', get_settings_errors(), 30);
}

function dw_pedagang_delete_handler() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'dw_delete' || !isset($_GET['id'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_pedagang_action')) wp_die('Security Fail');
    
    global $wpdb;
    $id = intval($_GET['id']);
    $wpdb->delete($wpdb->prefix.'dw_pedagang', ['id' => $id]);

    dw_set_pedagang_notice('Toko berhasil dihapus.', 'success');
    wp_redirect(admin_url('admin.php?page=dw-pedagang')); exit;
}

/**
 * --------------------------------------------------------------------------
 * 2. RENDER PAGE
 * --------------------------------------------------------------------------
 */
function dw_pedagang_page_render() {
    // Cek Handler Delete
    dw_pedagang_delete_handler();

    $action = $_GET['action'] ?? 'list';
    if ($action === 'add' || $action === 'edit') { 
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        dw_pedagang_form_render($id); 
    } else {
        dw_pedagang_list_render();
    }
}

/**
 * RENDER TABEL LIST (MANUAL) - DIPERCANTIK
 */
function dw_pedagang_list_render() {
    global $wpdb;
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';

    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    $sql = "SELECT p.*, d.nama_desa, u.user_login, u.display_name 
            FROM $table_pedagang p 
            LEFT JOIN $table_desa d ON p.id_desa = d.id 
            LEFT JOIN {$wpdb->users} u ON p.id_user = u.ID";
    
    if (!$is_super_admin) {
        $current_user_id = get_current_user_id();
        $desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE id_user_desa = %d", $current_user_id));
        $sql .= " WHERE p.id_desa = " . intval($desa_id);
    }

    $sql .= " ORDER BY p.id DESC";
    $rows = $wpdb->get_results($sql);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Toko (UMKM)</h1>
        <a href="?page=dw-pedagang&action=add" class="page-title-action">Tambah Toko Baru</a>
        <hr class="wp-header-end">

        <!-- CSS untuk Tabel Cantik -->
        <style>
            .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); overflow:hidden; margin-top:15px; }
            .dw-thumb-profile { width:48px; height:48px; border-radius:50%; object-fit:cover; border:1px solid #ddd; background:#f0f0f1; }
            .dw-badge { display:inline-block; padding:4px 8px; border-radius:12px; font-size:11px; font-weight:600; line-height:1; white-space:nowrap; }
            .dw-badge-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
            .dw-badge-warning { background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
            .dw-badge-danger { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
            .dw-badge-neutral { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }
            .dw-contact-link { text-decoration:none; color:#2271b1; }
            .dw-contact-link:hover { text-decoration:underline; }
            .column-foto { width: 60px; text-align: center; }
        </style>

        <?php 
        $errors = get_transient('settings_errors');
        if ($errors) { settings_errors('dw_pedagang_notices'); delete_transient('settings_errors'); }
        ?>

        <div class="dw-card-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-foto">Foto</th>
                        <th width="20%">Nama Toko</th>
                        <th width="15%">Pemilik</th>
                        <th width="15%">Asal Desa</th>
                        <th width="15%">Kontak & QRIS</th>
                        <th width="10%">Status Akun</th>
                        <th width="10%">Pendaftaran</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($rows): foreach($rows as $r): 
                        $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}";
                        $del_url = wp_nonce_url("?page=dw-pedagang&action=dw_delete&id={$r->id}", 'dw_delete_pedagang_action');
                        $desa_html = $r->nama_desa ? '<span class="dashicons dashicons-location"></span> '.esc_html($r->nama_desa) : '-';
                        
                        // Foto Profil Placeholder
                        $img_src = !empty($r->foto_profil) ? $r->foto_profil : 'https://placehold.co/100x100/e2e8f0/64748b?text=Toko';

                        // Badge Status Akun
                        $status_akun_badge = ($r->status_akun == 'aktif') 
                            ? '<span class="dw-badge dw-badge-success">Aktif</span>' 
                            : '<span class="dw-badge dw-badge-danger">Nonaktif</span>';

                        // Badge Status Daftar
                        $reg_class = 'dw-badge-neutral';
                        if($r->status_pendaftaran == 'disetujui') $reg_class = 'dw-badge-success';
                        elseif($r->status_pendaftaran == 'menunggu_desa') $reg_class = 'dw-badge-warning';
                        elseif($r->status_pendaftaran == 'ditolak') $reg_class = 'dw-badge-danger';
                        $status_reg_badge = '<span class="dw-badge '.$reg_class.'">'.strtoupper(str_replace('_',' ',$r->status_pendaftaran)).'</span>';
                        
                        // QRIS Icon
                        $qris_icon = !empty($r->qris_image_url) ? '<span title="Ada QRIS" class="dashicons dashicons-qr" style="color:#166534;"></span>' : '<span title="Tidak ada QRIS" class="dashicons dashicons-qr" style="color:#ccc;"></span>';
                    ?>
                    <tr>
                        <td class="column-foto" style="vertical-align:middle;">
                            <img src="<?php echo esc_url($img_src); ?>" class="dw-thumb-profile">
                        </td>
                        <td>
                            <strong><a href="<?php echo $edit_url; ?>" style="font-size:14px;"><?php echo esc_html($r->nama_toko); ?></a></strong>
                            <br><small style="color:#64748b;">@<?php echo esc_html($r->slug_toko); ?></small>
                        </td>
                        <td>
                            <span style="font-weight:600;"><?php echo esc_html($r->nama_pemilik); ?></span><br>
                            <small>User: <?php echo esc_html($r->user_login); ?></small>
                        </td>
                        <td><?php echo $desa_html; ?></td>
                        <td>
                            <?php if($r->nomor_wa): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/^0/','62',$r->nomor_wa); ?>" target="_blank" class="dw-contact-link"><span class="dashicons dashicons-whatsapp"></span> <?php echo esc_html($r->nomor_wa); ?></a>
                            <?php else: ?> - <?php endif; ?>
                            <div style="margin-top:4px;">
                                <?php echo $qris_icon; ?> 
                                <?php if($r->url_gmaps): ?><a href="<?php echo esc_url($r->url_gmaps); ?>" target="_blank" title="Lokasi Maps"><span class="dashicons dashicons-location-alt"></span></a><?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo $status_akun_badge; ?></td>
                        <td><?php echo $status_reg_badge; ?></td>
                        <td>
                            <a href="<?php echo $edit_url; ?>" class="button button-small" style="margin-bottom:4px;">Edit</a>
                            <a href="<?php echo $del_url; ?>" class="button button-small" onclick="return confirm('Hapus toko ini?');" style="color:#b32d2e;">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:20px;">Belum ada data toko. Silakan tambah baru.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * RENDER FORM ADD/EDIT
 */
function dw_pedagang_form_render($id) {
    global $wpdb;
    $item = null;
    $title = "Tambah Toko Baru";
    $current_user_id = get_current_user_id();
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');

    if (!$is_super_admin) {
        $my_desa_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $current_user_id));
        if (!$my_desa_data) { echo '<div class="notice notice-error"><p>Error: Akun Admin Desa tidak valid.</p></div>'; return; }
    }

    if ($id > 0) {
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $id));
        if (!$item) { echo "<div class='error'><p>Data hilang.</p></div>"; return; }
        $title = "Edit Toko: " . esc_html($item->nama_toko);
    }

    $users = get_users(['orderby' => 'display_name']);
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
                <!-- BOX 1: INFO PEMILIK -->
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle">1. Informasi Pemilik & Toko</h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th style="width:200px;">User WordPress *</th>
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
                            <!-- Tambah Upload Foto Profil Toko -->
                            <tr><th>Foto Profil Toko</th><td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <?php $prof_img = !empty($item->foto_profil) ? $item->foto_profil : ''; ?>
                                    <img id="img_prev_prof" src="<?php echo $prof_img ? esc_url($prof_img) : 'https://placehold.co/80x80?text=No+Img'; ?>" style="width:60px; height:60px; object-fit:cover; border-radius:50%; border:1px solid #ddd;">
                                    <div>
                                        <input type="text" name="foto_profil" id="foto_profil" value="<?php echo esc_attr($prof_img); ?>" class="regular-text" placeholder="URL Foto">
                                        <button type="button" class="button" id="btn_upl_prof">Upload Foto</button>
                                    </div>
                                </div>
                            </td></tr>
                            <tr><th>Nama Toko *</th><td><input type="text" name="nama_toko" value="<?php echo esc_attr($item->nama_toko ?? ''); ?>" class="regular-text" required></td></tr>
                            <tr><th>Nama Pemilik (KTP) *</th><td><input type="text" name="nama_pemilik" value="<?php echo esc_attr($item->nama_pemilik ?? ''); ?>" class="regular-text" required></td></tr>
                            <tr><th>WhatsApp</th><td><input type="text" name="nomor_wa" value="<?php echo esc_attr($item->nomor_wa ?? ''); ?>" class="regular-text"></td></tr>
                            <tr><th>NIK</th><td><input type="text" name="nik" value="<?php echo esc_attr($item->nik ?? ''); ?>" class="regular-text"></td></tr>
                        </table>
                    </div>
                </div>

                <!-- BOX 2: LOKASI -->
                <div class="postbox" style="border-left: 4px solid #2271b1;">
                    <div class="postbox-header"><h2 class="hndle">2. Lokasi & Relasi Desa</h2></div>
                    <div class="inside">
                        <?php if ($is_super_admin): ?>
                            <table class="form-table">
                                <tr>
                                    <th style="width:200px;">Hubungkan ke Desa</th>
                                    <td>
                                        <select name="id_desa_selection" class="regular-text">
                                            <option value="auto">-- Auto (Berdasarkan Alamat) --</option>
                                            <optgroup label="Pilih Manual">
                                                <?php 
                                                $all_desas = $wpdb->get_results("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif'");
                                                foreach ($all_desas as $desa): ?>
                                                    <option value="<?php echo $desa->id; ?>" <?php selected($item->id_desa ?? '', $desa->id); ?>><?php echo esc_html($desa->nama_desa); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <div style="background:#f0f9ff; padding:15px; border-left:4px solid #72aee6; margin-bottom:15px;">
                                <strong>Desa Afiliasi:</strong> <?php echo esc_html($my_desa_data->nama_desa); ?>
                            </div>
                        <?php endif; ?>

                        <table class="form-table">
                            <tr><th style="width:200px;">Alamat Lengkap</th><td><textarea name="alamat_lengkap_manual" class="large-text" rows="2"><?php echo esc_textarea($item->alamat_lengkap ?? ''); ?></textarea></td></tr>
                            <tr><th>URL Google Maps</th><td><input type="text" name="url_gmaps" value="<?php echo esc_attr($item->url_gmaps ?? ''); ?>" class="regular-text"></td></tr>
                        </table>
                    </div>
                </div>

                <!-- BOX 3: KEUANGAN & QRIS -->
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle">3. Keuangan & QRIS</h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <tr><th style="width:200px;">Bank</th><td>
                                <input type="text" name="nama_bank" value="<?php echo esc_attr($item->nama_bank ?? ''); ?>" placeholder="Nama Bank" class="regular-text">
                            </td></tr>
                            <tr><th>No. Rekening</th><td>
                                <input type="text" name="no_rekening" value="<?php echo esc_attr($item->no_rekening ?? ''); ?>" placeholder="Nomor Rekening" class="regular-text">
                            </td></tr>
                            <tr><th>Atas Nama</th><td>
                                <input type="text" name="atas_nama_rekening" value="<?php echo esc_attr($item->atas_nama_rekening ?? ''); ?>" placeholder="Nama Pemilik Rekening" class="regular-text">
                            </td></tr>
                            
                            <!-- UPLOAD QRIS -->
                            <tr><th>QRIS (Scan)</th><td>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="text" name="qris_image_url" id="qris_image_url" value="<?php echo esc_attr($item->qris_image_url ?? ''); ?>" class="regular-text" placeholder="URL Gambar QRIS">
                                    <button type="button" class="button" id="btn_upl_qris"><span class="dashicons dashicons-upload" style="margin-top:3px;"></span> Upload QRIS</button>
                                </div>
                                <div style="margin-top:10px; background:#f0f0f1; padding:10px; border-radius:5px; display:inline-block;">
                                    <img id="img_prev_qris" src="<?php echo esc_url($item->qris_image_url ?? ''); ?>" style="max-width:200px; display:<?php echo empty($item->qris_image_url ?? '') ? 'none' : 'block'; ?>;">
                                    <span id="no_img_qris" style="color:#777; display:<?php echo empty($item->qris_image_url ?? '') ? 'block' : 'none'; ?>;">Belum ada gambar QRIS</span>
                                </div>
                            </td></tr>

                            <tr><td colspan="2"><hr></td></tr>

                            <tr><th>Status Pendaftaran</th><td>
                                <select name="status_pendaftaran">
                                    <option value="menunggu_desa" <?php selected($item->status_pendaftaran ?? '', 'menunggu_desa'); ?>>Menunggu Persetujuan</option>
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
                        </table>
                    </div>
                </div>

                <div class="submit">
                    <input type="submit" name="dw_submit_pedagang" class="button button-primary button-large" value="Simpan Data Toko">
                    <a href="?page=dw-pedagang" class="button button-large">Batal</a>
                </div>
            </div>
        </form>
    </div>

    <!-- SCRIPT UPLOADER -->
    <script>
    jQuery(document).ready(function($){
        function dw_setup_uploader(btnId, inputId, imgId, noImgId) {
            $(btnId).click(function(e){
                e.preventDefault();
                var frame = wp.media({ title: 'Pilih Gambar', multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $(inputId).val(attachment.url);
                    $(imgId).attr('src', attachment.url).show();
                    if(noImgId) $(noImgId).hide();
                });
                frame.open();
            });
        }
        dw_setup_uploader('#btn_upl_qris', '#qris_image_url', '#img_prev_qris', '#no_img_qris');
        dw_setup_uploader('#btn_upl_prof', '#foto_profil', '#img_prev_prof', null);
    });
    </script>
    <?php
}
?>