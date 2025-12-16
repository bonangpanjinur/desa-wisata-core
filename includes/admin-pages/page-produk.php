<?php
/**
 * File Name:   page-produk.php
 * Description: Manajemen Produk Lengkap (Termasuk Galeri & Variasi).
 * * UPDATE UI:
 * - Mengubah input Kategori menjadi Dropdown yang mengambil data dari Taxonomy.
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. HANDLER: SIMPAN & HAPUS
 */
function dw_produk_form_handler() {
    // 1. Cek Action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        check_admin_referer('dw_del_prod_nonce'); // Cek nonce URL
        dw_handle_delete_produk(intval($_GET['id']));
    }

    if (!isset($_POST['dw_submit_produk'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_prod_save')) wp_die('Security check failed.');
    
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_variasi  = $wpdb->prefix . 'dw_produk_variasi';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    // A. Validasi Pedagang
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

    // B. Proses Galeri (Array -> JSON)
    $galeri_json = '[]';
    if (!empty($_POST['galeri_urls'])) {
        $galeri_array = array_filter(explode(',', $_POST['galeri_urls']));
        $galeri_json = json_encode(array_values($galeri_array));
    }

    // C. Data Utama Produk
    $data = [
        'id_pedagang'  => $id_pedagang_input,
        'nama_produk'  => sanitize_text_field($_POST['nama_produk']),
        'slug'         => sanitize_title($_POST['nama_produk']),
        'deskripsi'    => wp_kses_post($_POST['deskripsi']),
        'harga'        => floatval($_POST['harga']),
        'stok'         => intval($_POST['stok']),
        'berat_gram'   => intval($_POST['berat_gram']),
        'kondisi'      => sanitize_key($_POST['kondisi']),
        'kategori'     => sanitize_text_field($_POST['kategori']), // Ini mengambil nilai dari Select
        'foto_utama'   => esc_url_raw($_POST['foto_utama']),
        'galeri'       => $galeri_json, // Field Galeri
        'status'       => sanitize_text_field($_POST['status']),
        'updated_at'   => current_time('mysql')
    ];

    $produk_id = isset($_POST['produk_id']) ? intval($_POST['produk_id']) : 0;
    $notif_msg = '';

    // D. Simpan Produk Utama
    if ($produk_id > 0) {
        // Cek kepemilikan untuk keamanan
        if (!$is_super_admin) {
            $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_produk WHERE id=%d AND id_pedagang=%d", $produk_id, $id_pedagang_input));
            if (!$check) wp_die('Dilarang mengedit produk toko lain.');
        }
        $wpdb->update($table_produk, $data, ['id' => $produk_id]);
        $notif_msg = 'Produk berhasil diperbarui.';
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table_produk, $data);
        $produk_id = $wpdb->insert_id;
        $notif_msg = 'Produk baru berhasil ditambahkan.';
    }

    // E. Simpan Variasi (Hapus Lama -> Insert Baru)
    // Ini cara paling aman untuk memastikan sinkronisasi data variasi
    if ($produk_id) {
        // 1. Hapus variasi lama
        $wpdb->delete($table_variasi, ['id_produk' => $produk_id]);

        // 2. Insert variasi baru jika ada
        if (!empty($_POST['var_nama']) && is_array($_POST['var_nama'])) {
            $count = count($_POST['var_nama']);
            for ($i = 0; $i < $count; $i++) {
                if (empty($_POST['var_nama'][$i])) continue; // Skip jika nama kosong

                $var_data = [
                    'id_produk'         => $produk_id,
                    'deskripsi_variasi' => sanitize_text_field($_POST['var_nama'][$i]), // Misal: "Merah, XL"
                    'harga_variasi'     => floatval($_POST['var_harga'][$i]),
                    'stok_variasi'      => intval($_POST['var_stok'][$i]),
                    'sku'               => sanitize_text_field($_POST['var_sku'][$i]),
                    'foto'              => esc_url_raw($_POST['var_foto'][$i]),
                    'is_default'        => ($i === 0) ? 1 : 0 // Baris pertama default
                ];
                $wpdb->insert($table_variasi, $var_data);
            }
        }
    }

    dw_add_notice($notif_msg, 'success');
    wp_redirect(admin_url('admin.php?page=dw-produk'));
    exit;
}
add_action('admin_init', 'dw_produk_form_handler');

function dw_handle_delete_produk($id) {
    global $wpdb;
    $current_user_id = get_current_user_id();
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');

    // Cek kepemilikan sebelum hapus
    if (!$is_super_admin) {
        $my_pedagang_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $current_user_id));
        $is_owner = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_produk WHERE id = %d AND id_pedagang = %d", $id, $my_pedagang_id));
        if (!$is_owner) wp_die('Akses Ditolak.');
    }

    // Hapus Variasi Dulu (Foreign Key Logic)
    $wpdb->delete("{$wpdb->prefix}dw_produk_variasi", ['id_produk' => $id]);
    // Hapus Produk
    $wpdb->delete("{$wpdb->prefix}dw_produk", ['id' => $id]);

    dw_add_notice('Produk dan variasinya berhasil dihapus.', 'success');
    wp_redirect(admin_url('admin.php?page=dw-produk')); exit;
}

function dw_add_notice($msg, $type) {
    add_settings_error('dw_produk_msg', 'dw_msg', $msg, $type);
    set_transient('settings_errors', get_settings_errors(), 30);
}

/**
 * 2. RENDER HALAMAN
 */
function dw_produk_page_info_render() {
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_variasi  = $wpdb->prefix . 'dw_produk_variasi';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_toko FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    $action = $_GET['action'] ?? 'list';
    $is_edit = ($action == 'new' || $action == 'edit');
    $edit_data = null;
    $variasi_list = [];

    if ($action == 'edit' && isset($_GET['id'])) {
        $produk_id = intval($_GET['id']);
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_produk WHERE id=%d", $produk_id));
        
        if ($edit_data) {
            // Ambil Data Variasi
            $variasi_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_variasi WHERE id_produk = %d ORDER BY id ASC", $produk_id));
        } else { 
            echo '<div class="notice notice-error"><p>Produk tidak ditemukan.</p></div>'; return; 
        }
    }

    // Ambil Kategori Produk dari Taxonomy
    $kategori_terms = get_terms([
        'taxonomy' => 'kategori_produk',
        'hide_empty' => false,
    ]);

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
            <form method="post" action="">
                <input type="hidden" name="dw_submit_produk" value="1">
                <?php wp_nonce_field('dw_prod_save'); ?>
                <?php if($edit_data): ?><input type="hidden" name="produk_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        
                        <!-- KOLOM KIRI (UTAMA) -->
                        <div id="post-body-content">
                            <div class="dw-input-group" style="margin-bottom: 20px;">
                                <input type="text" name="nama_produk" size="30" value="<?php echo esc_attr($edit_data->nama_produk ?? ''); ?>" id="title" placeholder="Nama Produk" required style="width:100%; padding:10px; font-size:20px;">
                            </div>

                            <!-- Deskripsi -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Deskripsi Produk</h2></div>
                                <div class="inside">
                                    <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>8, 'media_buttons'=>true]); ?>
                                </div>
                            </div>

                            <!-- Galeri Produk -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Galeri Produk</h2></div>
                                <div class="inside">
                                    <p class="description">Upload beberapa foto untuk produk ini.</p>
                                    <div id="galeri-container" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
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

                            <!-- Variasi Produk -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Variasi Produk (Ukuran/Warna)</h2></div>
                                <div class="inside">
                                    <p class="description">Jika produk memiliki variasi, tambahkan di sini. Kosongkan jika produk tunggal.</p>
                                    <table class="widefat" id="tbl-variasi">
                                        <thead>
                                            <tr>
                                                <th>Nama Variasi</th>
                                                <th>Harga (Rp)</th>
                                                <th>Stok</th>
                                                <th>SKU/Kode</th>
                                                <th width="50">Hapus</th>
                                            </tr>
                                        </thead>
                                        <tbody id="variasi-rows">
                                            <?php if($variasi_list): foreach($variasi_list as $var): ?>
                                                <tr>
                                                    <td><input type="text" name="var_nama[]" value="<?php echo esc_attr($var->deskripsi_variasi); ?>" style="width:100%"></td>
                                                    <td><input type="number" name="var_harga[]" value="<?php echo esc_attr($var->harga_variasi); ?>" style="width:100%"></td>
                                                    <td><input type="number" name="var_stok[]" value="<?php echo esc_attr($var->stok_variasi); ?>" style="width:100%"></td>
                                                    <td>
                                                        <input type="text" name="var_sku[]" value="<?php echo esc_attr($var->sku); ?>" style="width:100%">
                                                        <input type="hidden" name="var_foto[]" value="<?php echo esc_attr($var->foto); ?>">
                                                    </td>
                                                    <td><button type="button" class="button btn-del-var"><span class="dashicons dashicons-trash"></span></button></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                    <div style="margin-top:10px;">
                                        <button type="button" class="button button-secondary" id="btn-add-var">+ Tambah Variasi</button>
                                    </div>
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
                                    <select name="status" class="widefat" style="margin-bottom:10px;">
                                        <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                        <option value="habis" <?php selected($edit_data->status ?? '', 'habis'); ?>>Habis</option>
                                        <option value="arsip" <?php selected($edit_data->status ?? '', 'arsip'); ?>>Arsip</option>
                                    </select>
                                    <input type="submit" class="button button-primary button-large" value="Simpan Produk" style="width:100%;">
                                    <a href="?page=dw-produk" class="button" style="width:100%; margin-top:5px; text-align:center;">Batal</a>
                                </div>
                            </div>

                            <!-- Data Produk -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Data Dasar</h2></div>
                                <div class="inside">
                                    <p>
                                        <label>Harga Utama (Rp):</label>
                                        <input name="harga" type="number" value="<?php echo esc_attr($edit_data->harga ?? 0); ?>" class="widefat" required>
                                    </p>
                                    <p>
                                        <label>Stok Utama:</label>
                                        <input name="stok" type="number" value="<?php echo esc_attr($edit_data->stok ?? 0); ?>" class="widefat" required>
                                    </p>
                                    <p>
                                        <label>Berat (Gram):</label>
                                        <input name="berat_gram" type="number" value="<?php echo esc_attr($edit_data->berat_gram ?? 0); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Kondisi:</label>
                                        <select name="kondisi" class="widefat">
                                            <option value="baru" <?php selected($edit_data ? $edit_data->kondisi : 'baru', 'baru'); ?>>Baru</option>
                                            <option value="bekas" <?php selected($edit_data ? $edit_data->kondisi : '', 'bekas'); ?>>Bekas</option>
                                        </select>
                                    </p>
                                    <p>
                                        <label>Kategori:</label>
                                        <select name="kategori" class="widefat">
                                            <option value="">-- Pilih Kategori --</option>
                                            <?php if (!empty($kategori_terms) && !is_wp_error($kategori_terms)) : ?>
                                                <?php foreach ($kategori_terms as $term) : ?>
                                                    <option value="<?php echo esc_attr($term->name); ?>" <?php selected(($edit_data && isset($edit_data->kategori)) ? $edit_data->kategori : '', $term->name); ?>>
                                                        <?php echo esc_html($term->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <p class="description"><a href="edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk" target="_blank">+ Kelola Kategori</a></p>
                                    </p>
                                </div>
                            </div>

                            <!-- Foto Utama -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Foto Utama</h2></div>
                                <div class="inside">
                                    <img id="img_prev_prod" src="<?php echo !empty($edit_data->foto_utama) ? esc_url($edit_data->foto_utama) : 'https://placehold.co/150?text=Produk'; ?>" style="width:100%; height:auto; margin-bottom:10px;">
                                    <input type="hidden" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>">
                                    <button type="button" class="button" id="btn_upl" style="width:100%;">Set Foto Utama</button>
                                </div>
                            </div>

                            <!-- Pemilik -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Pemilik Toko</h2></div>
                                <div class="inside">
                                    <?php if ($is_super_admin): ?>
                                        <?php $list_pedagang = $wpdb->get_results("SELECT id, nama_toko FROM $table_pedagang WHERE status_akun='aktif'"); ?>
                                        <select name="id_pedagang" class="widefat">
                                            <?php foreach($list_pedagang as $p): ?>
                                                <option value="<?php echo $p->id; ?>" <?php selected($edit_data ? $edit_data->id_pedagang : '', $p->id); ?>>
                                                    <?php echo esc_html($p->nama_toko); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" class="widefat" value="<?php echo esc_attr($my_pedagang_data->nama_toko ?? '-'); ?>" readonly disabled>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </form>
            
            <script>
            jQuery(document).ready(function($){
                // 1. Single Image Uploader
                $('#btn_upl').click(function(e){
                    e.preventDefault(); var frame = wp.media({title:'Foto Produk', multiple:false});
                    frame.on('select', function(){ 
                        var url = frame.state().get('selection').first().toJSON().url;
                        $('#foto_utama').val(url); $('#img_prev_prod').attr('src', url);
                    }); frame.open();
                });

                // 2. Galeri (Multiple)
                var gFrame;
                $('#btn_galeri').click(function(e){
                    e.preventDefault(); if(gFrame){gFrame.open();return;}
                    gFrame = wp.media({title:'Galeri Produk', multiple:true});
                    gFrame.on('select', function(){
                        var selection = gFrame.state().get('selection');
                        var urls = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : [];
                        selection.map(function(att){
                            att = att.toJSON();
                            if(urls.indexOf(att.url) === -1){
                                urls.push(att.url);
                                $('#galeri-container').append('<div class="g-item" style="position:relative;width:80px;height:80px;"><img src="'+att.url+'" style="width:100%;height:100%;object-fit:cover;border:1px solid #ccc;"><span class="rem-g" data-url="'+att.url+'" style="position:absolute;top:-5px;right:-5px;background:red;color:#fff;border-radius:50%;cursor:pointer;width:20px;height:20px;text-align:center;line-height:20px;">&times;</span></div>');
                            }
                        });
                        $('#galeri_urls').val(urls.join(','));
                    });
                    gFrame.open();
                });
                $(document).on('click','.rem-g', function(){
                    var u = $(this).data('url');
                    var urls = $('#galeri_urls').val().split(',');
                    var i = urls.indexOf(u); if(i > -1) urls.splice(i,1);
                    $('#galeri_urls').val(urls.join(',')); $(this).parent().remove();
                });

                // 3. Variasi Repeater
                $('#btn-add-var').click(function(){
                    var row = '<tr>'+
                        '<td><input type="text" name="var_nama[]" placeholder="Contoh: Merah, XL" style="width:100%"></td>'+
                        '<td><input type="number" name="var_harga[]" placeholder="Harga" style="width:100%"></td>'+
                        '<td><input type="number" name="var_stok[]" placeholder="Stok" style="width:100%"></td>'+
                        '<td><input type="text" name="var_sku[]" placeholder="SKU" style="width:100%"><input type="hidden" name="var_foto[]"></td>'+
                        '<td><button type="button" class="button btn-del-var"><span class="dashicons dashicons-trash"></span></button></td>'+
                    '</tr>';
                    $('#variasi-rows').append(row);
                });
                $(document).on('click', '.btn-del-var', function(){ $(this).closest('tr').remove(); });
            });
            </script>

        <?php else: ?>
            <!-- === TABEL LIST === -->
            <style>
                .dw-thumb-prod { width:50px; height:50px; border-radius:4px; object-fit:cover; border:1px solid #eee; background:#f9f9f9; }
                .dw-badge { display:inline-block; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600; }
                .badge-aktif { background:#dcfce7; color:#166534; }
                .badge-habis { background:#fee2e2; color:#991b1b; }
            </style>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="70">Gambar</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Toko</th>
                        <th>Desa</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th>Aksi</th>
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
                        $del_url = wp_nonce_url("?page=dw-produk&action=delete&id={$r->id}", 'dw_del_prod_nonce');
                        $img = !empty($r->foto_utama) ? $r->foto_utama : 'https://placehold.co/100x100?text=Produk';
                        $status_class = ($r->status == 'aktif') ? 'badge-aktif' : 'badge-habis';
                    ?>
                    <tr>
                        <td><img src="<?php echo esc_url($img); ?>" class="dw-thumb-prod"></td>
                        <td><strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_produk); ?></a></strong></td>
                        <td><small><?php echo esc_html($r->kategori); ?></small></td>
                        <td><?php echo esc_html($r->nama_toko); ?></td>
                        <td><?php echo esc_html($r->nama_desa ? $r->nama_desa : '-'); ?></td>
                        <td><strong>Rp <?php echo number_format($r->harga, 0, ',', '.'); ?></strong></td>
                        <td><?php echo $r->stok; ?></td>
                        <td><span class="dw-badge <?php echo $status_class; ?>"><?php echo ucfirst($r->status); ?></span></td>
                        <td>
                            <a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a>
                            <a href="<?php echo $del_url; ?>" class="button button-small" onclick="return confirm('Hapus produk ini?');" style="color:#b32d2e;">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:20px;">Belum ada data produk.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}