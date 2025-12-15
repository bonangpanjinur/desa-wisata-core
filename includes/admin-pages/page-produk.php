<?php
/**
 * File Name:   page-produk.php
 * Description: CRUD Produk & Variasi dengan tampilan 2 kolom (WordPress Native Style).
 * * UPDATE:
 * - Sinkronisasi dengan tabel dw_produk (berat, kondisi, kategori, galeri).
 * - Penambahan fitur CRUD Variasi Produk (dw_produk_variasi) dengan Repeater Javascript.
 * - Layout 2 Kolom (Main & Sidebar).
 */

if (!defined('ABSPATH')) exit;

function dw_produk_page_render() {
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_variasi  = $wpdb->prefix . 'dw_produk_variasi';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang'; // Asumsi ada tabel pedagang
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Cari Data Pedagang User Ini (jika bukan admin)
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_toko FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    $message = ''; $msg_type = '';

    // --- HANDLE ACTION SAVE/UPDATE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_produk'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_produk_save')) {
            echo '<div class="notice notice-error"><p>Security Fail.</p></div>'; return;
        }

        // 1. Tentukan ID Pedagang
        $id_pedagang_input = 0;
        if ($is_super_admin) {
            $id_pedagang_input = intval($_POST['id_pedagang']);
        } else {
            $id_pedagang_input = $my_pedagang_data ? intval($my_pedagang_data->id) : 0;
        }

        if ($id_pedagang_input === 0) {
            echo '<div class="notice notice-error"><p>Error: Akun Anda tidak terhubung dengan Data Pedagang manapun.</p></div>'; return;
        }

        // 2. Proses Galeri (Array URL -> JSON)
        $galeri_json = '[]';
        if (!empty($_POST['galeri_urls'])) {
            $galeri_array = array_filter(explode(',', $_POST['galeri_urls']));
            $galeri_json = json_encode(array_values($galeri_array));
        }

        $nama_produk = sanitize_text_field($_POST['nama_produk']);
        
        // Data Utama Produk
        $data_produk = [
            'id_pedagang' => $id_pedagang_input,
            'nama_produk' => $nama_produk,
            'slug'        => sanitize_title($nama_produk),
            'deskripsi'   => wp_kses_post($_POST['deskripsi']),
            'harga'       => floatval($_POST['harga']),
            'stok'        => intval($_POST['stok']),
            'berat_gram'  => intval($_POST['berat_gram']),
            'kondisi'     => sanitize_text_field($_POST['kondisi']),
            'kategori'    => sanitize_text_field($_POST['kategori']),
            'foto_utama'  => esc_url_raw($_POST['foto_utama']),
            'galeri'      => $galeri_json,
            'status'      => sanitize_text_field($_POST['status']),
            'updated_at'  => current_time('mysql')
        ];

        // SAVE PRODUK
        $produk_id = 0;
        if (!empty($_POST['produk_id'])) {
            // Edit Mode
            $produk_id = intval($_POST['produk_id']);
            
            // Security Owner Check
            if (!$is_super_admin) {
                $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_produk WHERE id = %d AND id_pedagang = %d", $produk_id, $id_pedagang_input));
                if (!$check) { echo '<div class="notice notice-error"><p>Akses Ditolak.</p></div>'; return; }
            }

            $wpdb->update($table_produk, $data_produk, ['id' => $produk_id]);
            $message = 'Produk berhasil diperbarui.'; $msg_type = 'success';
        } else {
            // New Mode
            $data_produk['created_at'] = current_time('mysql');
            $wpdb->insert($table_produk, $data_produk);
            $produk_id = $wpdb->insert_id;
            $message = 'Produk baru berhasil ditambahkan.'; $msg_type = 'success';
        }

        // 3. HANDLE VARIASI (Delete all old variations -> Re-insert new ones)
        // Ini metode sederhana untuk memastikan sinkronisasi data variasi
        if ($produk_id) {
            $wpdb->delete($table_variasi, ['id_produk' => $produk_id]);

            if (!empty($_POST['variasi_deskripsi'])) {
                $count_var = count($_POST['variasi_deskripsi']);
                for ($i = 0; $i < $count_var; $i++) {
                    if (empty($_POST['variasi_deskripsi'][$i])) continue;

                    $var_data = [
                        'id_produk'         => $produk_id,
                        'deskripsi_variasi' => sanitize_text_field($_POST['variasi_deskripsi'][$i]),
                        'harga_variasi'     => floatval($_POST['variasi_harga'][$i]),
                        'stok_variasi'      => intval($_POST['variasi_stok'][$i]),
                        'sku'               => sanitize_text_field($_POST['variasi_sku'][$i]),
                        'foto'              => esc_url_raw($_POST['variasi_foto'][$i]),
                        'is_default'        => (isset($_POST['variasi_default']) && $_POST['variasi_default'] == $i) ? 1 : 0
                    ];
                    $wpdb->insert($table_variasi, $var_data);
                }
            }
        }
    }

    // --- VIEW LOGIC ---
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $is_edit = ($action == 'new' || $action == 'edit');
    $edit_data = null;
    $variasi_data = [];

    if ($action == 'edit' && isset($_GET['id'])) {
        $id_produk = intval($_GET['id']);
        $query = "SELECT * FROM $table_produk WHERE id = %d";
        if (!$is_super_admin && $my_pedagang_data) {
            $query .= " AND id_pedagang = " . intval($my_pedagang_data->id);
        }
        $edit_data = $wpdb->get_row($wpdb->prepare($query, $id_produk));

        if ($edit_data) {
            $variasi_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_variasi WHERE id_produk = %d", $id_produk));
        } else {
            echo '<div class="notice notice-error"><p>Data produk tidak ditemukan.</p></div>';
            $is_edit = false;
        }
    }

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Produk</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-produk&action=new" class="page-title-action">Tambah Produk Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if($message): ?><div class="notice notice-<?php echo $msg_type; ?> is-dismissible"><p><?php echo $message; ?></p></div><?php endif; ?>

        <?php if($is_edit): ?>
            <form method="post" action="">
                <?php wp_nonce_field('dw_produk_save'); ?>
                <input type="hidden" name="action_produk" value="save">
                <?php if($edit_data): ?><input type="hidden" name="produk_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        
                        <!-- KOLOM KIRI (UTAMA) -->
                        <div id="post-body-content">
                            <!-- Judul -->
                            <div class="dw-input-group" style="margin-bottom: 20px;">
                                <input type="text" name="nama_produk" size="30" value="<?php echo esc_attr($edit_data->nama_produk ?? ''); ?>" id="title" placeholder="Nama Produk" required style="width:100%; padding:10px; font-size:20px;">
                            </div>

                            <!-- Deskripsi -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Deskripsi Produk</h2></div>
                                <div class="inside">
                                    <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>10, 'media_buttons'=>true]); ?>
                                </div>
                            </div>

                            <!-- Data Produk (General) -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Data Produk</h2></div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th><label>Harga (Rp)</label></th>
                                            <td><input type="number" name="harga" value="<?php echo esc_attr($edit_data->harga ?? 0); ?>" class="regular-text" required></td>
                                        </tr>
                                        <tr>
                                            <th><label>Stok</label></th>
                                            <td><input type="number" name="stok" value="<?php echo esc_attr($edit_data->stok ?? 0); ?>" class="regular-text" required></td>
                                        </tr>
                                        <tr>
                                            <th><label>Berat (gram)</label></th>
                                            <td><input type="number" name="berat_gram" value="<?php echo esc_attr($edit_data->berat_gram ?? 0); ?>" class="regular-text" required> <p class="description">Contoh: 1000 untuk 1kg</p></td>
                                        </tr>
                                        <tr>
                                            <th><label>Kondisi</label></th>
                                            <td>
                                                <select name="kondisi" class="regular-text">
                                                    <option value="baru" <?php selected($edit_data->kondisi ?? '', 'baru'); ?>>Baru</option>
                                                    <option value="bekas" <?php selected($edit_data->kondisi ?? '', 'bekas'); ?>>Bekas</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>Kategori</label></th>
                                            <td><input type="text" name="kategori" value="<?php echo esc_attr($edit_data->kategori ?? ''); ?>" class="regular-text" placeholder="Misal: Kerajinan, Makanan"></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Variasi Produk (Repeater) -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Variasi Produk</h2></div>
                                <div class="inside">
                                    <p class="description">Jika produk memiliki variasi (contoh: Ukuran S, M, L atau Warna Merah, Biru), tambahkan di sini.</p>
                                    
                                    <table class="widefat" id="variasi-table">
                                        <thead>
                                            <tr>
                                                <th>Nama Variasi (Warna/Ukuran)</th>
                                                <th>Harga (Rp)</th>
                                                <th>Stok</th>
                                                <th>SKU (Kode)</th>
                                                <th>Foto</th>
                                                <th style="width:50px;">Hapus</th>
                                            </tr>
                                        </thead>
                                        <tbody id="variasi-container">
                                            <?php if(!empty($variasi_data)): foreach($variasi_data as $idx => $var): ?>
                                                <tr class="variasi-row">
                                                    <td><input type="text" name="variasi_deskripsi[]" value="<?php echo esc_attr($var->deskripsi_variasi); ?>" style="width:100%" required></td>
                                                    <td><input type="number" name="variasi_harga[]" value="<?php echo esc_attr($var->harga_variasi); ?>" style="width:100%" required></td>
                                                    <td><input type="number" name="variasi_stok[]" value="<?php echo esc_attr($var->stok_variasi); ?>" style="width:100%"></td>
                                                    <td><input type="text" name="variasi_sku[]" value="<?php echo esc_attr($var->sku); ?>" style="width:100%"></td>
                                                    <td>
                                                        <div style="display:flex; gap:5px;">
                                                            <input type="text" name="variasi_foto[]" class="var-foto-url" value="<?php echo esc_url($var->foto); ?>" style="width:80%">
                                                            <button type="button" class="button btn-upload-var"><span class="dashicons dashicons-upload"></span></button>
                                                        </div>
                                                    </td>
                                                    <td><button type="button" class="button remove-variasi"><span class="dashicons dashicons-trash" style="color:red;"></span></button></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                    <button type="button" class="button button-secondary" id="add-variasi" style="margin-top:10px;">+ Tambah Variasi</button>
                                </div>
                            </div>

                            <!-- Galeri -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Galeri Produk</h2></div>
                                <div class="inside">
                                    <div id="galeri-preview" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                                        <?php 
                                        $galeri_urls = [];
                                        if (!empty($edit_data->galeri)) {
                                            $decoded = json_decode($edit_data->galeri, true);
                                            if (is_array($decoded)) {
                                                foreach($decoded as $url) {
                                                    $galeri_urls[] = $url;
                                                    echo '<div class="g-item" style="position:relative;width:80px;height:80px;"><img src="'.esc_url($url).'" style="width:100%;height:100%;object-fit:cover;border:1px solid #ccc;"><span class="rem-g" data-url="'.esc_attr($url).'" style="position:absolute;top:-5px;right:-5px;background:red;color:#fff;border-radius:50%;cursor:pointer;width:20px;height:20px;text-align:center;line-height:20px;">&times;</span></div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                    <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr(implode(',', $galeri_urls)); ?>">
                                    <button type="button" class="button" id="btn_galeri">Kelola Galeri</button>
                                </div>
                            </div>

                        </div>

                        <!-- KOLOM KANAN (SIDEBAR) -->
                        <div id="postbox-container-1" class="postbox-container">
                            
                            <!-- Publish -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Penerbitan</h2></div>
                                <div class="inside">
                                    <label>Status:</label>
                                    <select name="status" class="widefat" style="margin-top:5px; margin-bottom:10px;">
                                        <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                        <option value="nonaktif" <?php selected($edit_data->status ?? '', 'nonaktif'); ?>>Nonaktif</option>
                                        <option value="habis" <?php selected($edit_data->status ?? '', 'habis'); ?>>Habis</option>
                                        <option value="arsip" <?php selected($edit_data->status ?? '', 'arsip'); ?>>Arsip</option>
                                    </select>
                                    <input type="submit" class="button button-primary button-large" value="Simpan Produk" style="width:100%;">
                                </div>
                            </div>

                            <!-- Foto Utama -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Foto Utama</h2></div>
                                <div class="inside">
                                    <div style="background:#f1f1f1; padding:10px; text-align:center; margin-bottom:10px;">
                                        <img id="img_preview" src="<?php echo !empty($edit_data->foto_utama) ? esc_url($edit_data->foto_utama) : 'https://placehold.co/150'; ?>" style="max-width:100%; height:auto;">
                                    </div>
                                    <input type="hidden" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>">
                                    <button type="button" class="button" id="btn_foto_utama" style="width:100%;">Pilih Foto Utama</button>
                                </div>
                            </div>

                            <!-- Pemilik (Pedagang) -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Pedagang</h2></div>
                                <div class="inside">
                                    <?php if($is_super_admin): ?>
                                        <label>Pilih Pedagang:</label>
                                        <?php $pedagangs = $wpdb->get_results("SELECT id, nama_toko FROM $table_pedagang WHERE status_verifikasi='disetujui'"); ?>
                                        <select name="id_pedagang" class="widefat">
                                            <option value="">-- Pilih --</option>
                                            <?php foreach($pedagangs as $p): ?>
                                                <option value="<?php echo $p->id; ?>" <?php selected($edit_data->id_pedagang ?? '', $p->id); ?>><?php echo esc_html($p->nama_toko); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?php if($my_pedagang_data): ?>
                                            <input type="text" class="widefat" value="<?php echo esc_attr($my_pedagang_data->nama_toko); ?>" readonly disabled>
                                            <input type="hidden" name="id_pedagang" value="<?php echo esc_attr($my_pedagang_data->id); ?>">
                                        <?php else: ?>
                                            <p style="color:red;">Anda belum terdaftar sebagai pedagang.</p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </form>

            <!-- JAVASCRIPT: Uploader & Repeater -->
            <script>
            jQuery(document).ready(function($){
                // 1. Foto Utama
                $('#btn_foto_utama').click(function(e){
                    e.preventDefault();
                    var frame = wp.media({title:'Foto Utama Produk', multiple:false, library:{type:'image'}});
                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#foto_utama').val(attachment.url);
                        $('#img_preview').attr('src', attachment.url);
                    });
                    frame.open();
                });

                // 2. Galeri
                var gFrame;
                $('#btn_galeri').click(function(e){
                    e.preventDefault();
                    if(gFrame){ gFrame.open(); return; }
                    gFrame = wp.media({title:'Galeri Produk', multiple:true, library:{type:'image'}});
                    gFrame.on('select', function(){
                        var selection = gFrame.state().get('selection');
                        var urls = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : [];
                        selection.map(function(att){
                            att = att.toJSON();
                            if(urls.indexOf(att.url) === -1){
                                urls.push(att.url);
                                $('#galeri-preview').append('<div class="g-item" style="position:relative;width:80px;height:80px;"><img src="'+att.url+'" style="width:100%;height:100%;object-fit:cover;border:1px solid #ccc;"><span class="rem-g" data-url="'+att.url+'" style="position:absolute;top:-5px;right:-5px;background:red;color:#fff;border-radius:50%;cursor:pointer;width:20px;height:20px;text-align:center;line-height:20px;">&times;</span></div>');
                            }
                        });
                        $('#galeri_urls').val(urls.join(','));
                    });
                    gFrame.open();
                });
                $(document).on('click','.rem-g', function(){
                    var u = $(this).data('url');
                    var urls = $('#galeri_urls').val().split(',');
                    var idx = urls.indexOf(u);
                    if(idx > -1) urls.splice(idx,1);
                    $('#galeri_urls').val(urls.join(','));
                    $(this).parent().remove();
                });

                // 3. Variasi Repeater
                $('#add-variasi').click(function(){
                    var row = `<tr class="variasi-row">
                        <td><input type="text" name="variasi_deskripsi[]" placeholder="Contoh: Merah, XL" style="width:100%" required></td>
                        <td><input type="number" name="variasi_harga[]" placeholder="Harga" style="width:100%" required></td>
                        <td><input type="number" name="variasi_stok[]" value="0" style="width:100%"></td>
                        <td><input type="text" name="variasi_sku[]" style="width:100%"></td>
                        <td>
                            <div style="display:flex; gap:5px;">
                                <input type="text" name="variasi_foto[]" class="var-foto-url" style="width:80%" placeholder="URL Foto">
                                <button type="button" class="button btn-upload-var"><span class="dashicons dashicons-upload"></span></button>
                            </div>
                        </td>
                        <td><button type="button" class="button remove-variasi"><span class="dashicons dashicons-trash" style="color:red;"></span></button></td>
                    </tr>`;
                    $('#variasi-container').append(row);
                });

                $(document).on('click', '.remove-variasi', function(){
                    $(this).closest('tr').remove();
                });

                // Variasi Image Upload
                $(document).on('click', '.btn-upload-var', function(e){
                    e.preventDefault();
                    var btn = $(this);
                    var input = btn.siblings('.var-foto-url');
                    var vFrame = wp.media({title:'Foto Variasi', multiple:false, library:{type:'image'}});
                    vFrame.on('select', function(){
                        var att = vFrame.state().get('selection').first().toJSON();
                        input.val(att.url);
                    });
                    vFrame.open();
                });
            });
            </script>

        <?php else: ?>
            
            <!-- LIST VIEW -->
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php 
                    $p_sql = "SELECT p.*, pd.nama_toko FROM $table_produk p LEFT JOIN $table_pedagang pd ON p.id_pedagang = pd.id";
                    if(!$is_super_admin && $my_pedagang_data) {
                        $p_sql .= " WHERE p.id_pedagang = " . intval($my_pedagang_data->id);
                    }
                    $p_sql .= " ORDER BY p.id DESC";
                    $rows = $wpdb->get_results($p_sql);

                    if($rows): foreach($rows as $r):
                        $edit_url = "?page=dw-produk&action=edit&id={$r->id}";
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_produk); ?></a></strong>
                            <?php if($is_super_admin): ?><br><small>Toko: <?php echo esc_html($r->nama_toko); ?></small><?php endif; ?>
                        </td>
                        <td><?php echo esc_html($r->kategori); ?></td>
                        <td>Rp <?php echo number_format($r->harga); ?></td>
                        <td><?php echo number_format($r->stok); ?></td>
                        <td><?php echo ucfirst($r->status); ?></td>
                        <td><a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" style="text-align:center;">Belum ada produk.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}