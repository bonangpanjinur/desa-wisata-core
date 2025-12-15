<?php
/**
 * File Name:   page-produk.php
 * Description: CRUD Produk dengan handler admin_init terpisah.
 */

if (!defined('ABSPATH')) exit;

/**
 * --------------------------------------------------------------------------
 * 1. HANDLER: SIMPAN & HAPUS PRODUK (Logic Only)
 * --------------------------------------------------------------------------
 */
function dw_produk_form_handler() {
    // Cek apakah ini request simpan produk
    if (!isset($_POST['dw_submit_produk'])) return;

    // Security Check
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_prod_save')) wp_die('Security check failed.');
    
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Ambil data Toko milik user login
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    // Validasi Akses Pedagang
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

    // Siapkan Data
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

    if ($produk_id > 0) {
        // Mode Edit: Cek Kepemilikan (jika bukan admin)
        if (!$is_super_admin) {
            $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_produk WHERE id=%d AND id_pedagang=%d", $produk_id, $id_pedagang_input));
            if (!$check) wp_die('Dilarang mengedit produk toko lain.');
        }

        $wpdb->update($table_produk, $data, ['id' => $produk_id]);
        dw_add_notice('Produk berhasil diperbarui.', 'success');
    } else {
        // Mode Baru
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table_produk, $data);
        dw_add_notice('Produk baru berhasil ditambahkan.', 'success');
    }

    // Redirect Bersih (PRG Pattern)
    wp_redirect(admin_url('admin.php?page=dw-produk'));
    exit;
}
add_action('admin_init', 'dw_produk_form_handler');

// Helper Notice
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

    // Handle View Mode
    $action = $_GET['action'] ?? 'list';
    $is_edit = ($action == 'new' || $action == 'edit');
    $edit_data = null;

    if ($action == 'edit' && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_produk WHERE id=%d", intval($_GET['id'])));
        // Security check view
        if (!$is_super_admin && (!$my_pedagang_data || $edit_data->id_pedagang != $my_pedagang_data->id)) {
            echo '<div class="notice notice-error"><p>Akses ditolak.</p></div>'; return;
        }
    }

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Produk</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-produk&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php 
        // Tampilkan Notifikasi
        $errors = get_transient('settings_errors');
        if($errors) { settings_errors('dw_produk_msg'); delete_transient('settings_errors'); }
        ?>

        <?php if($is_edit): ?>
            <!-- FORM EDIT / BARU -->
            <div class="card" style="padding:20px; max-width:800px;">
                <form method="post" action="">
                    <!-- TRIGGER HANDLER -->
                    <input type="hidden" name="dw_submit_produk" value="1">
                    <?php wp_nonce_field('dw_prod_save'); ?>
                    
                    <?php if($edit_data): ?><input type="hidden" name="produk_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label>Toko *</label></th>
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
                                    <input type="text" class="regular-text" value="<?php echo esc_attr($my_pedagang_data->nama_toko ?? 'Anda belum punya toko'); ?>" readonly disabled>
                                    <p class="description">Produk otomatis masuk ke toko Anda.</p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr><th>Nama Produk</th><td><input name="nama_produk" type="text" value="<?php echo esc_attr($edit_data->nama_produk ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>5, 'media_buttons'=>false]); ?></td></tr>
                        <tr><th>Harga (Rp)</th><td><input name="harga" type="number" value="<?php echo esc_attr($edit_data->harga ?? 0); ?>" class="regular-text" required></td></tr>
                        
                        <tr><th>Berat (Gram)</th><td><input name="berat_gram" type="number" value="<?php echo esc_attr($edit_data->berat_gram ?? 0); ?>" class="small-text"></td></tr>
                        <tr><th>Kondisi</th><td>
                            <select name="kondisi">
                                <option value="baru" <?php selected($edit_data ? $edit_data->kondisi : '', 'baru'); ?>>Baru</option>
                                <option value="bekas" <?php selected($edit_data ? $edit_data->kondisi : '', 'bekas'); ?>>Bekas</option>
                            </select>
                        </td></tr>

                        <tr><th>Stok</th><td><input name="stok" type="number" value="<?php echo esc_attr($edit_data->stok ?? 0); ?>" class="small-text"></td></tr>
                        <tr><th>Kategori</th><td><input name="kategori" type="text" value="<?php echo esc_attr($edit_data->kategori ?? ''); ?>" class="regular-text"></td></tr>
                        
                        <tr><th>Foto Produk</th><td>
                            <input type="text" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>" class="regular-text">
                            <button type="button" class="button" id="btn_upl">Upload</button>
                        </td></tr>

                        <tr><th>Status</th><td>
                            <select name="status">
                                <option value="aktif" <?php selected($edit_data ? $edit_data->status : '', 'aktif'); ?>>Aktif</option>
                                <option value="habis" <?php selected($edit_data ? $edit_data->status : '', 'habis'); ?>>Habis</option>
                                <option value="arsip" <?php selected($edit_data ? $edit_data->status : '', 'arsip'); ?>>Arsip</option>
                            </select>
                        </td></tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Simpan Produk">
                        <a href="?page=dw-produk" class="button">Batal</a>
                    </p>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($){
                $('#btn_upl').click(function(e){
                    e.preventDefault(); var frame = wp.media({title:'Foto Produk', multiple:false});
                    frame.on('select', function(){ $('#foto_utama').val(frame.state().get('selection').first().toJSON().url); });
                    frame.open();
                });
            });
            </script>

        <?php else: ?>
            <!-- TABEL LIST -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="20%">Nama Produk</th>
                        <th width="15%">Toko</th>
                        <th width="15%">Harga</th>
                        <th width="10%">Stok</th>
                        <th width="10%">Status</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sql_list = "SELECT pr.*, pe.nama_toko FROM $table_produk pr LEFT JOIN $table_pedagang pe ON pr.id_pedagang = pe.id";
                    if (!$is_super_admin) $sql_list .= " WHERE pr.id_pedagang = " . intval($my_pedagang_data->id ?? 0);
                    $sql_list .= " ORDER BY pr.id DESC";
                    
                    $rows = $wpdb->get_results($sql_list);
                    if($rows): foreach($rows as $r): 
                        $edit_url = "?page=dw-produk&action=edit&id={$r->id}";
                    ?>
                    <tr>
                        <td><strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_produk); ?></a></strong></td>
                        <td><?php echo esc_html($r->nama_toko); ?></td>
                        <td>Rp <?php echo number_format($r->harga); ?></td>
                        <td><?php echo $r->stok; ?></td>
                        <td><?php echo strtoupper($r->status); ?></td>
                        <td><a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6">Belum ada data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
?>