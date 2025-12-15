<?php
/**
 * File Name:   page-produk.php
 * Description: CRUD Produk dengan penyesuaian kolom database (Berat & Kondisi).
 */

if (!defined('ABSPATH')) exit;

function dw_produk_page_info_render() {
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';
    
    $current_user_id = get_current_user_id();
    // Cek apakah Super Admin (Bisa edit punya siapa saja)
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Ambil data Toko milik user yang sedang login (jika dia pedagang)
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_toko FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    $message = ''; $msg_type = '';

    // --- HANDLE ACTION (SAVE) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_produk'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_prod_save')) {
            echo '<div class="notice notice-error"><p>Security Fail.</p></div>'; return;
        }

        // Tentukan ID Pedagang: Jika Admin ambil dari POST, Jika Pedagang ambil dari data dirinya
        $id_pedagang_input = 0;
        if ($is_super_admin) {
            $id_pedagang_input = intval($_POST['id_pedagang']);
        } else {
            // Paksa gunakan ID Toko milik user login
            $id_pedagang_input = $my_pedagang_data ? intval($my_pedagang_data->id) : 0;
        }

        if ($id_pedagang_input === 0) {
            echo '<div class="notice notice-error"><p>Error: Anda belum terdaftar sebagai Pedagang/Toko.</p></div>'; return;
        }

        $nama = sanitize_text_field($_POST['nama_produk']);
        
        // FIX: Menambahkan field Berat dan Kondisi sesuai database
        $data = [
            'id_pedagang'  => $id_pedagang_input,
            'nama_produk'  => $nama,
            'slug'         => sanitize_title($nama),
            'deskripsi'    => wp_kses_post($_POST['deskripsi']),
            'harga'        => floatval($_POST['harga']),
            'stok'         => intval($_POST['stok']),
            'berat_gram'   => intval($_POST['berat_gram']), // Field Baru
            'kondisi'      => sanitize_key($_POST['kondisi']), // Field Baru
            'kategori'     => sanitize_text_field($_POST['kategori']),
            'foto_utama'   => esc_url_raw($_POST['foto_utama']),
            'status'       => sanitize_text_field($_POST['status']),
            'updated_at'   => current_time('mysql')
        ];

        if (!empty($_POST['produk_id'])) {
            // Security Extra: Pastikan Pedagang hanya edit produk miliknya sendiri
            if (!$is_super_admin) {
                $check_owner = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_produk WHERE id = %d AND id_pedagang = %d", intval($_POST['produk_id']), $id_pedagang_input));
                if (!$check_owner) {
                    echo '<div class="notice notice-error"><p>Dilarang mengedit produk toko lain!</p></div>'; return;
                }
            }

            $wpdb->update($table_produk, $data, ['id' => intval($_POST['produk_id'])]);
            $message = 'Produk berhasil diperbarui.'; $msg_type = 'success';
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table_produk, $data);
            $message = 'Produk baru berhasil ditambahkan.'; $msg_type = 'success';
        }
    }

    // --- VIEW LOGIC ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit');
    $edit_data = null;

    if ($is_edit && isset($_GET['id'])) {
        $query_edit = "SELECT * FROM $table_produk WHERE id = %d";
        // Filter query jika bukan admin
        if (!$is_super_admin && $my_pedagang_data) {
            $query_edit .= " AND id_pedagang = " . intval($my_pedagang_data->id);
        }
        
        $edit_data = $wpdb->get_row($wpdb->prepare($query_edit, intval($_GET['id'])));

        // Jika user coba akses ID produk orang lain via URL
        if (isset($_GET['id']) && !$edit_data) {
            echo '<div class="notice notice-error"><p>Data tidak ditemukan atau Anda tidak memiliki akses.</p></div>';
            $is_edit = false; // Batalkan mode edit
        }
    }

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Produk</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-produk&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if($message): ?><div class="notice notice-<?php echo $msg_type; ?> is-dismissible"><p><?php echo $message; ?></p></div><?php endif; ?>

        <?php if($is_edit): ?>
            <!-- FORM INPUT / EDIT -->
            <div class="card" style="padding:20px; margin-top:10px;">
                <form method="post">
                    <?php wp_nonce_field('dw_prod_save'); ?>
                    <input type="hidden" name="action_produk" value="save">
                    <?php if($edit_data): ?><input type="hidden" name="produk_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <!-- LOGIC PEMILIHAN TOKO -->
                        <tr>
                            <th><label>Toko / Pedagang *</label></th>
                            <td>
                                <?php if ($is_super_admin): ?>
                                    <!-- ADMIN: Boleh Pilih Toko Siapa Saja -->
                                    <?php $list_pedagang = $wpdb->get_results("SELECT id, nama_toko FROM $table_pedagang WHERE status_akun='aktif'"); ?>
                                    <select name="id_pedagang" required class="regular-text">
                                        <option value="">-- Pilih Toko (Mode Admin) --</option>
                                        <?php foreach($list_pedagang as $p): ?>
                                            <option value="<?php echo $p->id; ?>" <?php selected($edit_data ? $edit_data->id_pedagang : '', $p->id); ?>>
                                                <?php echo esc_html($p->nama_toko); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Sebagai Admin, Anda bisa memilih produk ini masuk ke toko mana.</p>
                                
                                <?php else: ?>
                                    <!-- PEDAGANG: Otomatis Terkunci ke Toko Sendiri -->
                                    <?php if ($my_pedagang_data): ?>
                                        <input type="text" class="regular-text" value="<?php echo esc_attr($my_pedagang_data->nama_toko); ?>" readonly style="background:#f0f0f1; color:#555;">
                                        <input type="hidden" name="id_pedagang" value="<?php echo esc_attr($my_pedagang_data->id); ?>">
                                        <p class="description">Produk ini akan otomatis masuk ke toko Anda.</p>
                                    <?php else: ?>
                                        <div class="notice notice-error inline"><p>Anda belum memiliki Toko yang terdaftar/aktif. Silakan hubungi Admin Desa.</p></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php if ($is_super_admin || $my_pedagang_data): // Hanya tampilkan form sisa jika valid ?>
                            <tr><th>Nama Produk</th><td><input name="nama_produk" type="text" value="<?php echo esc_attr($edit_data->nama_produk ?? ''); ?>" class="regular-text" required></td></tr>
                            <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>5, 'media_buttons'=>false]); ?></td></tr>
                            <tr><th>Harga (Rp)</th><td><input name="harga" type="number" value="<?php echo esc_attr($edit_data->harga ?? 0); ?>" class="regular-text"></td></tr>
                            
                            <!-- FIX: Menambahkan Berat dan Kondisi -->
                            <tr><th>Berat (Gram)</th><td><input name="berat_gram" type="number" value="<?php echo esc_attr($edit_data->berat_gram ?? 0); ?>" class="small-text"> <span class="description">gram</span></td></tr>
                            <tr><th>Kondisi</th><td>
                                <select name="kondisi">
                                    <option value="baru" <?php selected($edit_data ? $edit_data->kondisi : '', 'baru'); ?>>Baru</option>
                                    <option value="bekas" <?php selected($edit_data ? $edit_data->kondisi : '', 'bekas'); ?>>Bekas</option>
                                </select>
                            </td></tr>

                            <tr><th>Stok</th><td><input name="stok" type="number" value="<?php echo esc_attr($edit_data->stok ?? 0); ?>" class="small-text"></td></tr>
                            <tr><th>Kategori</th><td><input name="kategori" type="text" value="<?php echo esc_attr($edit_data->kategori ?? ''); ?>" class="regular-text" placeholder="Contoh: Makanan"></td></tr>
                            
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
                        <?php endif; ?>
                    </table>
                    
                    <?php if ($is_super_admin || $my_pedagang_data): ?>
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="Simpan Produk">
                            <a href="?page=dw-produk" class="button">Batal</a>
                        </p>
                    <?php endif; ?>
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
            <!-- TABEL LIST PRODUK -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="20%">Nama Produk</th>
                        <th width="20%">Toko</th>
                        <th width="20%">Asal Desa</th>
                        <th width="15%">Harga</th>
                        <th width="10%">Stok</th>
                        <th width="10%">Status</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // FILTER QUERY UNTUK TABEL
                    $sql_list = "SELECT pr.*, pe.nama_toko, d.nama_desa 
                                 FROM $table_produk pr 
                                 LEFT JOIN $table_pedagang pe ON pr.id_pedagang = pe.id 
                                 LEFT JOIN $table_desa d ON pe.id_desa = d.id";
                    
                    // Jika bukan admin, HANYA tampilkan produk milik pedagang yang login
                    if (!$is_super_admin && $my_pedagang_data) {
                        $sql_list .= " WHERE pr.id_pedagang = " . intval($my_pedagang_data->id);
                    } elseif (!$is_super_admin && !$my_pedagang_data) {
                        // User login bukan admin & bukan pedagang (Harusnya gak bisa akses, tapi jaga-jaga)
                        $sql_list .= " WHERE 1=0"; 
                    }

                    $sql_list .= " ORDER BY pr.id DESC";

                    $rows = $wpdb->get_results($sql_list);
                    
                    if($rows):
                        foreach($rows as $r): 
                            $edit_url = "?page=dw-produk&action=edit&id={$r->id}";
                            $desa_html = !empty($r->nama_desa) ? '<span class="dashicons dashicons-location" style="color:#2271b1; font-size:14px;"></span> '.esc_html($r->nama_desa) : '-';
                            $status_style = ($r->status == 'aktif') ? 'background:#dcfce7; color:#166534;' : 'background:#eee; color:#666;';
                    ?>
                    <tr>
                        <td><strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_produk); ?></a></strong><br><small><?php echo esc_html($r->kategori); ?></small></td>
                        <td><?php echo esc_html($r->nama_toko); ?></td>
                        <td><?php echo $desa_html; ?></td>
                        <td>Rp <?php echo number_format($r->harga); ?></td>
                        <td><?php echo $r->stok; ?></td>
                        <td><span style="padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600; <?php echo $status_style; ?>"><?php echo strtoupper($r->status); ?></span></td>
                        <td><a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; 
                    else: ?>
                        <tr><td colspan="7">Belum ada produk.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
?>