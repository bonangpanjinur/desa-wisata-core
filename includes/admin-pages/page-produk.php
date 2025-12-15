<?php
/**
 * File Name:   page-produk.php
 * Description: Manajemen Produk dengan Tampilan Tabel Modern & Thumbnail.
 * * [UPDATED]
 * - Tabel menampilkan foto thumbnail produk.
 * - Format harga tebal dan berwarna.
 * - Badge status produk lebih modern.
 */

if (!defined('ABSPATH')) exit;

/**
 * --------------------------------------------------------------------------
 * 1. HANDLER: SIMPAN & HAPUS PRODUK
 * --------------------------------------------------------------------------
 */
function dw_produk_form_handler() {
    if (!isset($_POST['dw_submit_produk'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_prod_save')) wp_die('Security check failed.');
    
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    $id_pedagang_input = 0;
    if ($is_super_admin) {
        $id_pedagang_input = intval($_POST['id_pedagang']);
    } else {
        if (!$my_pedagang_data) {
            dw_add_notice('Anda belum terdaftar sebagai pedagang.', 'error');
            wp_redirect(admin_url('admin.php?page=dw-produk')); exit;
        }
        $id_pedagang_input = intval($my_pedagang_data->id);
    }

    $data = [
        'id_pedagang'  => $id_pedagang_input,
        'nama_produk'  => sanitize_text_field($_POST['nama_produk']),
        'slug'         => sanitize_title($_POST['nama_produk']),
        'deskripsi'    => wp_kses_post($_POST['deskripsi']),
        'harga'        => floatval($_POST['harga']),
        'stok'         => intval($_POST['stok']),
        'berat_gram'   => intval($_POST['berat_gram']),
        'kondisi'      => sanitize_key($_POST['kondisi']),
        'kategori'     => sanitize_text_field($_POST['kategori']),
        'foto_utama'   => esc_url_raw($_POST['foto_utama']),
        'status'       => sanitize_text_field($_POST['status']),
        'updated_at'   => current_time('mysql')
    ];

    $produk_id = isset($_POST['produk_id']) ? intval($_POST['produk_id']) : 0;
    $notif_msg = '';

    if ($produk_id > 0) {
        if (!$is_super_admin) {
            $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_produk WHERE id=%d AND id_pedagang=%d", $produk_id, $id_pedagang_input));
            if (!$check) wp_die('Dilarang mengedit produk toko lain.');
        }
        $wpdb->update($table_produk, $data, ['id' => $produk_id]);
        $notif_msg = 'Produk berhasil diperbarui.';
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table_produk, $data);
        $notif_msg = 'Produk baru berhasil ditambahkan.';
    }

    dw_add_notice($notif_msg, 'success');
    wp_redirect(admin_url('admin.php?page=dw-produk'));
    exit;
}
add_action('admin_init', 'dw_produk_form_handler');

function dw_add_notice($msg, $type) {
    add_settings_error('dw_produk_msg', 'dw_msg', $msg, $type);
    set_transient('settings_errors', get_settings_errors(), 30);
}

/**
 * --------------------------------------------------------------------------
 * 2. RENDER: TAMPILAN UI
 * --------------------------------------------------------------------------
 */
function dw_produk_page_info_render() {
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_toko FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $del_id = intval($_GET['id']);
        if ($is_super_admin) {
            $wpdb->delete($table_produk, ['id' => $del_id]);
            dw_add_notice('Produk dihapus.', 'success');
            echo "<script>window.location='admin.php?page=dw-produk';</script>"; return;
        }
    }

    $action = $_GET['action'] ?? 'list';
    $is_edit = ($action == 'new' || $action == 'edit');
    $edit_data = null;

    if ($action == 'edit' && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_produk WHERE id=%d", intval($_GET['id'])));
        if (!$edit_data) { echo '<div class="notice notice-error"><p>Produk tidak ditemukan.</p></div>'; return; }
    }

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Produk</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-produk&action=new" class="page-title-action">Tambah Produk Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php 
        $errors = get_transient('settings_errors');
        if($errors) { settings_errors('dw_produk_msg'); delete_transient('settings_errors'); }
        ?>

        <?php if($is_edit): ?>
            <!-- === FORM EDIT / BARU === -->
            <div class="card" style="padding:20px; max-width:900px; margin-top:20px;">
                <form method="post" action="">
                    <input type="hidden" name="dw_submit_produk" value="1">
                    <?php wp_nonce_field('dw_prod_save'); ?>
                    <?php if($edit_data): ?><input type="hidden" name="produk_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th style="width:200px;"><label>Toko Pemilik *</label></th>
                            <td>
                                <?php if ($is_super_admin): ?>
                                    <?php $list_pedagang = $wpdb->get_results("SELECT id, nama_toko FROM $table_pedagang WHERE status_akun='aktif'"); ?>
                                    <select name="id_pedagang" required class="regular-text">
                                        <option value="">-- Pilih Toko --</option>
                                        <?php foreach($list_pedagang as $p): ?>
                                            <option value="<?php echo $p->id; ?>" <?php selected($edit_data ? $edit_data->id_pedagang : '', $p->id); ?>>
                                                <?php echo esc_html($p->nama_toko); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" class="regular-text" value="<?php echo esc_attr($my_pedagang_data->nama_toko ?? 'Anda belum punya toko'); ?>" readonly style="background:#f0f0f1;">
                                    <p class="description">Produk ini otomatis masuk ke toko Anda.</p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr><th>Nama Produk *</th><td><input name="nama_produk" type="text" value="<?php echo esc_attr($edit_data->nama_produk ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>6, 'media_buttons'=>true]); ?></td></tr>
                        
                        <tr><th>Harga (Rp) *</th><td><input name="harga" type="number" value="<?php echo esc_attr($edit_data->harga ?? 0); ?>" class="regular-text" required></td></tr>
                        <tr><th>Stok *</th><td><input name="stok" type="number" value="<?php echo esc_attr($edit_data->stok ?? 0); ?>" class="small-text" required></td></tr>
                        
                        <tr><th>Berat (Gram)</th><td><input name="berat_gram" type="number" value="<?php echo esc_attr($edit_data->berat_gram ?? 0); ?>" class="small-text"></td></tr>
                        <tr><th>Kondisi</th><td>
                            <label><input type="radio" name="kondisi" value="baru" <?php checked($edit_data ? $edit_data->kondisi : 'baru', 'baru'); ?>> Baru</label> &nbsp;&nbsp;
                            <label><input type="radio" name="kondisi" value="bekas" <?php checked($edit_data ? $edit_data->kondisi : '', 'bekas'); ?>> Bekas</label>
                        </td></tr>

                        <tr><th>Kategori</th><td><input name="kategori" type="text" value="<?php echo esc_attr($edit_data->kategori ?? ''); ?>" class="regular-text"></td></tr>
                        
                        <tr><th>Foto Utama</th><td>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <div>
                                    <input type="text" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>" class="regular-text">
                                    <button type="button" class="button" id="btn_upl">Pilih Gambar</button>
                                </div>
                                <img id="img_prev_prod" src="<?php echo !empty($edit_data->foto_utama) ? esc_url($edit_data->foto_utama) : 'https://placehold.co/100x100?text=No+Img'; ?>" style="height:60px; width:60px; object-fit:cover; border-radius:4px; border:1px solid #ddd;">
                            </div>
                        </td></tr>

                        <tr><th>Status</th><td>
                            <select name="status">
                                <option value="aktif" <?php selected($edit_data ? $edit_data->status : '', 'aktif'); ?>>🟢 Aktif</option>
                                <option value="habis" <?php selected($edit_data ? $edit_data->status : '', 'habis'); ?>>🔴 Habis</option>
                                <option value="arsip" <?php selected($edit_data ? $edit_data->status : '', 'arsip'); ?>>📁 Arsip</option>
                            </select>
                        </td></tr>
                    </table>
                    
                    <div style="margin-top:20px; border-top:1px solid #ddd; padding-top:20px;">
                        <input type="submit" class="button button-primary button-large" value="Simpan Data Produk">
                        <a href="?page=dw-produk" class="button button-large">Batal</a>
                    </div>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($){
                $('#btn_upl').click(function(e){
                    e.preventDefault(); var frame = wp.media({title:'Foto Produk', multiple:false});
                    frame.on('select', function(){ 
                        var url = frame.state().get('selection').first().toJSON().url;
                        $('#foto_utama').val(url); 
                        $('#img_prev_prod').attr('src', url);
                    });
                    frame.open();
                });
            });
            </script>

        <?php else: ?>
            <!-- === TABEL LIST PRODUK (DIPERCANTIK) === -->
            <style>
                .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); overflow:hidden; margin-top:15px; }
                .dw-thumb-prod { width:50px; height:50px; border-radius:4px; object-fit:cover; border:1px solid #eee; background:#f9f9f9; }
                .dw-price-tag { color:#1d2327; font-weight:700; font-size:13px; }
                .dw-stock-badge { background:#f3f4f6; border:1px solid #e5e7eb; padding:2px 8px; border-radius:4px; font-size:11px; }
                .dw-badge { display:inline-block; padding:4px 8px; border-radius:12px; font-size:11px; font-weight:600; line-height:1; }
                .dw-badge-active { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
                .dw-badge-habis { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
                .dw-badge-arsip { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }
                .column-gambar { width: 60px; text-align:center; }
            </style>

            <div class="dw-card-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-gambar">Gambar</th>
                            <th width="20%">Nama Produk</th>
                            <th width="15%">Toko (Pedagang)</th>
                            <th width="15%">Asal Desa</th>
                            <th width="12%">Harga</th>
                            <th width="8%">Stok</th>
                            <th width="10%">Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sql_list = "SELECT pr.*, pe.nama_toko, d.nama_desa 
                                     FROM $table_produk pr 
                                     LEFT JOIN $table_pedagang pe ON pr.id_pedagang = pe.id 
                                     LEFT JOIN $table_desa d ON pe.id_desa = d.id";
                        
                        if (!$is_super_admin) $sql_list .= " WHERE pr.id_pedagang = " . intval($my_pedagang_data->id ?? 0);
                        $sql_list .= " ORDER BY pr.id DESC";
                        
                        $rows = $wpdb->get_results($sql_list);
                        
                        if($rows): foreach($rows as $r): 
                            $edit_url = "?page=dw-produk&action=edit&id={$r->id}";
                            $del_url = wp_nonce_url("?page=dw-produk&action=delete&id={$r->id}", 'dw_del_prod');
                            
                            $img_url = !empty($r->foto_utama) ? $r->foto_utama : 'https://placehold.co/100x100?text=Produk';
                            
                            // Status Badge
                            $st_class = 'dw-badge-arsip';
                            if($r->status == 'aktif') $st_class = 'dw-badge-active';
                            if($r->status == 'habis') $st_class = 'dw-badge-habis';
                            $status_html = '<span class="dw-badge '.$st_class.'">'.strtoupper($r->status).'</span>';
                        ?>
                        <tr>
                            <td class="column-gambar" style="vertical-align:middle;">
                                <img src="<?php echo esc_url($img_url); ?>" class="dw-thumb-prod">
                            </td>
                            <td>
                                <strong><a href="<?php echo $edit_url; ?>" style="font-size:14px;"><?php echo esc_html($r->nama_produk); ?></a></strong>
                                <br><small style="color:#64748b;"><?php echo esc_html($r->kategori); ?></small>
                            </td>
                            <td><?php echo esc_html($r->nama_toko); ?></td>
                            <td><span class="dashicons dashicons-location"></span> <?php echo esc_html($r->nama_desa ? $r->nama_desa : '-'); ?></td>
                            <td><span class="dw-price-tag">Rp <?php echo number_format($r->harga, 0, ',', '.'); ?></span></td>
                            <td><span class="dw-stock-badge"><?php echo $r->stok; ?></span></td>
                            <td><?php echo $status_html; ?></td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small button-primary">Edit</a>
                                <a href="<?php echo $del_url; ?>" class="button button-small" onclick="return confirm('Yakin hapus produk ini?');" style="color:#b32d2e;">Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="8" style="text-align:center; padding:20px;">Belum ada data produk. Silakan tambah baru.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>