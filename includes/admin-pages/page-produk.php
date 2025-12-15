<?php
/**
 * File Name:   page-produk.php
 * Description: CRUD Produk Custom Table dengan kolom Relasi Desa.
 */

if (!defined('ABSPATH')) exit;

function dw_produk_page_info_render() {
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa'; // Tambahan tabel Desa
    
    $message = ''; $msg_type = '';

    // --- HANDLE ACTION (SAVE) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_produk'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_prod_save')) {
            echo '<div class="notice notice-error"><p>Security Fail.</p></div>'; return;
        }

        $nama = sanitize_text_field($_POST['nama_produk']);
        $data = [
            'id_pedagang'  => intval($_POST['id_pedagang']),
            'nama_produk'  => $nama,
            'slug'         => sanitize_title($nama),
            'deskripsi'    => wp_kses_post($_POST['deskripsi']),
            'harga'        => floatval($_POST['harga']),
            'stok'         => intval($_POST['stok']),
            'kategori'     => sanitize_text_field($_POST['kategori']),
            'foto_utama'   => esc_url_raw($_POST['foto_utama']),
            'status'       => sanitize_text_field($_POST['status']),
            'updated_at'   => current_time('mysql')
        ];

        if (!empty($_POST['produk_id'])) {
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
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_produk WHERE id = %d", intval($_GET['id'])));
    }

    // Ambil list Pedagang untuk Form Dropdown
    $list_pedagang = $wpdb->get_results("SELECT id, nama_toko FROM $table_pedagang WHERE status_akun='aktif'");

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Produk (Custom Table)</h1>
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
                        <tr>
                            <th><label>Pilih Pedagang *</label></th>
                            <td>
                                <select name="id_pedagang" required class="regular-text">
                                    <option value="">-- Pilih Toko/Pedagang --</option>
                                    <?php foreach($list_pedagang as $p): ?>
                                        <option value="<?php echo $p->id; ?>" <?php selected($edit_data ? $edit_data->id_pedagang : '', $p->id); ?>>
                                            <?php echo esc_html($p->nama_toko); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Produk ini milik siapa?</p>
                            </td>
                        </tr>
                        <tr><th>Nama Produk</th><td><input name="nama_produk" type="text" value="<?php echo esc_attr($edit_data->nama_produk ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>5, 'media_buttons'=>false]); ?></td></tr>
                        <tr><th>Harga (Rp)</th><td><input name="harga" type="number" value="<?php echo esc_attr($edit_data->harga ?? 0); ?>" class="regular-text"></td></tr>
                        <tr><th>Stok</th><td><input name="stok" type="number" value="<?php echo esc_attr($edit_data->stok ?? 0); ?>" class="small-text"></td></tr>
                        <tr><th>Kategori</th><td><input name="kategori" type="text" value="<?php echo esc_attr($edit_data->kategori ?? ''); ?>" class="regular-text" placeholder="Contoh: Makanan, Kerajinan"></td></tr>
                        
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
            <!-- TABEL LIST PRODUK -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="20%">Nama Produk</th>
                        <th width="20%">Toko (Pedagang)</th>
                        <th width="20%">Asal Desa</th> <!-- KOLOM BARU -->
                        <th width="15%">Harga</th>
                        <th width="10%">Stok</th>
                        <th width="10%">Status</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // QUERY UPDATE: JOIN ke dw_desa juga
                    $rows = $wpdb->get_results("
                        SELECT pr.*, pe.nama_toko, d.nama_desa 
                        FROM $table_produk pr 
                        LEFT JOIN $table_pedagang pe ON pr.id_pedagang = pe.id 
                        LEFT JOIN $table_desa d ON pe.id_desa = d.id
                        ORDER BY pr.id DESC
                    ");
                    
                    if($rows):
                        foreach($rows as $r): 
                            $edit_url = "?page=dw-produk&action=edit&id={$r->id}";
                            
                            // Logika Tampilan Desa
                            $desa_html = !empty($r->nama_desa) 
                                ? '<span class="dashicons dashicons-location" style="font-size:14px; color:#2271b1;"></span> ' . esc_html($r->nama_desa)
                                : '<span style="color:#a00;">- Belum Terhubung -</span>';
                            
                            // Logika Badge Status
                            $status_style = 'background:#eee; color:#666;';
                            if($r->status == 'aktif') $status_style = 'background:#dcfce7; color:#166534;';
                            if($r->status == 'habis') $status_style = 'background:#fee2e2; color:#991b1b;';
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_produk); ?></a></strong>
                            <br><small style="color:#777;"><?php echo esc_html($r->kategori); ?></small>
                        </td>
                        <td><?php echo esc_html($r->nama_toko); ?></td>
                        
                        <!-- ISI KOLOM DESA -->
                        <td><?php echo $desa_html; ?></td> 
                        
                        <td>Rp <?php echo number_format($r->harga); ?></td>
                        <td><?php echo $r->stok; ?></td>
                        <td><span style="padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; <?php echo $status_style; ?>"><?php echo strtoupper($r->status); ?></span></td>
                        <td><a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; 
                    else: ?>
                        <tr><td colspan="7">Belum ada produk. Silakan tambah baru.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}