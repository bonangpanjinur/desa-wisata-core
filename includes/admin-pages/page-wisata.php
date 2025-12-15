<?php
/**
 * File Name:   page-wisata.php
 * Description: CRUD Wisata Lengkap (Search, Pagination, Slug, Galeri, Fasilitas).
 */

if (!defined('ABSPATH')) exit;

function dw_wisata_page_render() {
    global $wpdb;
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_desa   = $wpdb->prefix . 'dw_desa';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Cari Desa yang dikelola user ini (untuk validasi akses)
    $my_desa_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM $table_desa WHERE id_user_desa = %d", $current_user_id));

    $message = ''; $msg_type = '';

    // --- 1. HANDLE ACTIONS (SAVE & DELETE) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wisata'])) {
        
        // Cek Keamanan
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_wisata_save')) {
            echo '<div class="notice notice-error"><p>Security Check Failed.</p></div>'; return;
        }

        // Tentukan ID Desa
        $id_desa_input = 0;
        if ($is_super_admin) {
            $id_desa_input = isset($_POST['id_desa']) ? intval($_POST['id_desa']) : 0;
        } else {
            $id_desa_input = $my_desa_data ? intval($my_desa_data->id) : 0;
        }

        // --- A. DELETE ---
        if ($_POST['action_wisata'] === 'delete') {
            $del_id = intval($_POST['wisata_id']);
            
            // Validasi Kepemilikan
            if (!$is_super_admin) {
                $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_wisata WHERE id = %d AND id_desa = %d", $del_id, $id_desa_input));
                if (!$check) {
                    echo '<div class="notice notice-error"><p>Akses Ditolak: Anda tidak berhak menghapus data ini.</p></div>'; return;
                }
            }

            $wpdb->delete($table_wisata, ['id' => $del_id]);
            $message = 'Data Wisata berhasil dihapus permanen.'; 
            $msg_type = 'success';
        
        // --- B. SAVE / UPDATE ---
        } elseif ($_POST['action_wisata'] === 'save') {
            
            if ($id_desa_input === 0) {
                echo '<div class="notice notice-error"><p>Error: Akun Anda tidak terhubung dengan Desa manapun.</p></div>'; return;
            }

            // Proses Galeri JSON
            $galeri_json = '[]';
            if (!empty($_POST['galeri_urls'])) {
                $galeri_array = array_filter(explode(',', $_POST['galeri_urls']));
                $galeri_json = json_encode(array_values($galeri_array));
            }

            $nama = sanitize_text_field($_POST['nama_wisata']);
            
            // Logic Slug: Jika diedit manual pakai itu, jika kosong generate dari nama
            $slug = sanitize_title($_POST['slug']);
            if (empty($slug)) {
                $slug = sanitize_title($nama);
            }

            $data = [
                'id_desa'      => $id_desa_input,
                'nama_wisata'  => $nama,
                'slug'         => $slug,
                'deskripsi'    => wp_kses_post($_POST['deskripsi']),
                'harga_tiket'  => floatval($_POST['harga_tiket']),
                'jam_buka'     => sanitize_text_field($_POST['jam_buka']),
                'fasilitas'    => isset($_POST['fasilitas']) ? sanitize_textarea_field($_POST['fasilitas']) : '',
                'kontak_pengelola' => isset($_POST['kontak_pengelola']) ? sanitize_text_field($_POST['kontak_pengelola']) : '',
                'lokasi_maps'  => isset($_POST['lokasi_maps']) ? esc_url_raw($_POST['lokasi_maps']) : '',
                'foto_utama'   => esc_url_raw($_POST['foto_utama']),
                'galeri'       => $galeri_json,
                'status'       => sanitize_text_field($_POST['status']),
                'updated_at'   => current_time('mysql')
            ];

            if (!empty($_POST['wisata_id'])) {
                // Update
                $wid = intval($_POST['wisata_id']);
                
                if (!$is_super_admin) {
                    $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_wisata WHERE id=%d AND id_desa=%d", $wid, $id_desa_input));
                    if(!$check) { echo '<div class="notice notice-error"><p>Akses Ditolak.</p></div>'; return; }
                }

                $wpdb->update($table_wisata, $data, ['id' => $wid]);
                $message = 'Data Wisata berhasil diperbarui.'; 
            } else {
                // Insert
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table_wisata, $data);
                $message = 'Objek Wisata baru berhasil ditambahkan.';
            }
            $msg_type = 'success';
        }
    }

    // --- 2. VIEW CONTROLLER (EDIT vs LIST) ---
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $is_edit = ($action == 'new' || $action == 'edit');
    $edit_data = null;
    
    // Ambil Data untuk Edit
    if ($action == 'edit' && isset($_GET['id'])) {
        $sql_edit = "SELECT * FROM $table_wisata WHERE id = %d";
        if (!$is_super_admin && $my_desa_data) {
            $sql_edit .= " AND id_desa = " . intval($my_desa_data->id);
        }
        $edit_data = $wpdb->get_row($wpdb->prepare($sql_edit, intval($_GET['id'])));
        if (!$edit_data) {
            echo '<div class="notice notice-error"><p>Data tidak ditemukan atau Anda tidak memiliki akses.</p></div>'; 
            $is_edit = false; // Fallback ke list
        }
    }

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Objek Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-wisata&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if($message): ?><div class="notice notice-<?php echo $msg_type; ?> is-dismissible"><p><?php echo $message; ?></p></div><?php endif; ?>

        <?php if($is_edit): ?>
            <!-- === FORM VIEW (EDIT/ADD) === -->
            <form method="post" action="">
                <?php wp_nonce_field('dw_wisata_save'); ?>
                <input type="hidden" name="action_wisata" value="save">
                <?php if($edit_data): ?><input type="hidden" name="wisata_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        
                        <!-- KOLOM KIRI (KONTEN UTAMA) -->
                        <div id="post-body-content">
                            <!-- Title -->
                            <div class="dw-input-group" style="margin-bottom: 20px;">
                                <input type="text" name="nama_wisata" size="30" value="<?php echo esc_attr($edit_data->nama_wisata ?? ''); ?>" id="title" placeholder="Masukkan nama objek wisata" required style="width:100%; padding:10px; font-size:20px; border:1px solid #ddd;">
                                
                                <!-- Slug Edit (Fitur Tambahan) -->
                                <?php if($edit_data): ?>
                                <div style="margin-top:5px; font-size:13px; color:#666;">
                                    Permalink/Slug: <input type="text" name="slug" value="<?php echo esc_attr($edit_data->slug); ?>" style="font-size:12px; padding:2px 5px; width:300px; border:none; background:#f9f9f9; border-bottom:1px dashed #ccc;">
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Deskripsi -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Deskripsi Lengkap</h2></div>
                                <div class="inside">
                                    <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>10, 'media_buttons'=>true]); ?>
                                </div>
                            </div>

                            <!-- Fasilitas -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Fasilitas & Wahana</h2></div>
                                <div class="inside">
                                    <p class="description">Sebutkan fasilitas (Parkir, Toilet, Mushola, WiFi, dll).</p>
                                    <textarea name="fasilitas" class="large-text" rows="4"><?php echo esc_textarea($edit_data->fasilitas ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Galeri Foto -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Galeri Foto</h2></div>
                                <div class="inside">
                                    <div id="galeri-preview-container" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                                        <?php 
                                        $galeri_urls = [];
                                        if (!empty($edit_data->galeri)) {
                                            $galeri_decoded = json_decode($edit_data->galeri, true);
                                            if (is_array($galeri_decoded)) {
                                                foreach($galeri_decoded as $url) {
                                                    $galeri_urls[] = $url;
                                                    echo '<div class="galeri-item" style="position:relative;width:100px;height:100px;">
                                                            <img src="'.esc_url($url).'" style="width:100%;height:100%;object-fit:cover;border-radius:4px;border:1px solid #ddd;">
                                                            <span class="remove-galeri" data-url="'.esc_attr($url).'" style="position:absolute;top:-5px;right:-5px;background:#d63638;color:white;border-radius:50%;width:20px;height:20px;text-align:center;line-height:20px;cursor:pointer;font-size:14px;">&times;</span>
                                                          </div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                    <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr(implode(',', $galeri_urls)); ?>">
                                    <button type="button" class="button" id="btn_upload_galeri"><span class="dashicons dashicons-images-alt2"></span> Tambah Foto Galeri</button>
                                </div>
                            </div>
                        </div>

                        <!-- KOLOM KANAN (SIDEBAR) -->
                        <div id="postbox-container-1" class="postbox-container">
                            
                            <!-- Panel Penerbitan -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Penerbitan</h2></div>
                                <div class="inside">
                                    <p>
                                        <label><strong>Status:</strong></label>
                                        <select name="status" class="widefat" style="margin-top:5px;">
                                            <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif (Tampil)</option>
                                            <option value="nonaktif" <?php selected($edit_data->status ?? '', 'nonaktif'); ?>>Nonaktif (Sembunyi)</option>
                                        </select>
                                    </p>
                                    <!-- Statistik Read Only -->
                                    <?php if($edit_data): ?>
                                    <div style="background:#f0f0f1; padding:10px; margin-bottom:10px; font-size:12px; border-radius:4px;">
                                        <strong>Rating:</strong> <?php echo esc_html($edit_data->rating_avg); ?> / 5.0<br>
                                        <strong>Ulasan:</strong> <?php echo esc_html($edit_data->total_ulasan); ?> review<br>
                                        <strong>Dilihat:</strong> - kali
                                    </div>
                                    <?php endif; ?>

                                    <div id="major-publishing-actions" style="display:flex; gap:10px; margin-top:10px;">
                                        <input type="submit" class="button button-primary button-large" style="flex:1;" value="Simpan">
                                        <a href="?page=dw-wisata" class="button button-large">Batal</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Panel Desa -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Desa & Lokasi</h2></div>
                                <div class="inside">
                                    <?php if ($is_super_admin): ?>
                                        <label>Pilih Desa:</label>
                                        <select name="id_desa" class="widefat" required>
                                            <option value="">-- Pilih Desa --</option>
                                            <?php $ds = $wpdb->get_results("SELECT id, nama_desa FROM $table_desa WHERE status='aktif'"); 
                                            foreach($ds as $d) echo "<option value='{$d->id}' ".selected($edit_data->id_desa??'', $d->id, false).">{$d->nama_desa}</option>"; ?>
                                        </select>
                                    <?php else: ?>
                                        <label>Desa:</label>
                                        <input type="text" class="widefat" value="<?php echo esc_attr($my_desa_data->nama_desa ?? 'Belum Terdaftar'); ?>" readonly disabled style="background:#f0f0f1;">
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Foto Utama -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Foto Utama</h2></div>
                                <div class="inside">
                                    <div style="text-align:center; background:#f0f0f1; padding:10px; margin-bottom:10px; border-radius:4px;">
                                        <img id="preview_foto_utama" src="<?php echo !empty($edit_data->foto_utama) ? esc_url($edit_data->foto_utama) : 'https://placehold.co/300x200?text=No+Image'; ?>" style="max-width:100%; height:auto;">
                                    </div>
                                    <input type="text" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>" class="widefat" placeholder="URL Gambar">
                                    <button type="button" class="button" id="btn_upload_utama" style="width:100%; margin-top:5px;">Pilih Gambar Utama</button>
                                </div>
                            </div>

                            <!-- Info Tiket & Kontak -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Info & Kontak</h2></div>
                                <div class="inside">
                                    <p>
                                        <label>Harga Tiket (Rp):</label>
                                        <input name="harga_tiket" type="number" value="<?php echo esc_attr($edit_data->harga_tiket ?? 0); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Jam Buka:</label>
                                        <input name="jam_buka" type="text" value="<?php echo esc_attr($edit_data->jam_buka ?? '08:00 - 17:00'); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Kontak (WA):</label>
                                        <input name="kontak_pengelola" type="text" value="<?php echo esc_attr($edit_data->kontak_pengelola ?? ''); ?>" class="widefat" placeholder="0812...">
                                    </p>
                                    <p>
                                        <label>Link Google Maps:</label>
                                        <input name="lokasi_maps" type="url" value="<?php echo esc_url($edit_data->lokasi_maps ?? ''); ?>" class="widefat" placeholder="https://maps.google.com/...">
                                        <?php if(!empty($edit_data->lokasi_maps)): ?>
                                            <a href="<?php echo esc_url($edit_data->lokasi_maps); ?>" target="_blank" style="font-size:12px; text-decoration:none;">&nearr; Buka Peta</a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </form>

            <!-- JS Uploader -->
            <script>
            jQuery(document).ready(function($){
                // Single Image
                $('#btn_upload_utama').click(function(e){ e.preventDefault(); var frame = wp.media({title:'Foto Utama', multiple:false, library:{type:'image'}}); frame.on('select', function(){ var u = frame.state().get('selection').first().toJSON().url; $('#foto_utama').val(u); $('#preview_foto_utama').attr('src', u); }); frame.open(); });
                // Gallery
                var gFrame;
                $('#btn_upload_galeri').click(function(e){ e.preventDefault(); if(gFrame){gFrame.open();return;} gFrame = wp.media({title:'Galeri', multiple:true, library:{type:'image'}}); gFrame.on('select', function(){ var s = gFrame.state().get('selection'); var cur = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : []; s.map(function(at){ at=at.toJSON(); if(cur.indexOf(at.url)===-1){ cur.push(at.url); $('#galeri-preview-container').append('<div class="galeri-item" style="position:relative;width:100px;height:100px;"><img src="'+at.url+'" style="width:100%;height:100%;object-fit:cover;border-radius:4px;"><span class="remove-galeri" data-url="'+at.url+'" style="position:absolute;top:-5px;right:-5px;background:#d63638;color:white;border-radius:50%;width:20px;height:20px;text-align:center;line-height:20px;cursor:pointer;">&times;</span></div>'); } }); $('#galeri_urls').val(cur.join(',')); }); gFrame.open(); });
                $(document).on('click', '.remove-galeri', function(){ var u = $(this).data('url'); var cur = $('#galeri_urls').val().split(','); var i = cur.indexOf(u); if(i > -1) cur.splice(i,1); $('#galeri_urls').val(cur.join(',')); $(this).closest('.galeri-item').remove(); });
            });
            </script>

        <?php else: ?>
            
            <!-- === LIST TABLE VIEW (SEARCH & PAGINATION ADDED) === -->
            <?php
            // 1. Setup Pagination & Search
            $paged   = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
            $search  = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            $limit   = 10;
            $offset  = ($paged - 1) * $limit;
            
            // 2. Build Query
            $where = "WHERE 1=1";
            if (!$is_super_admin && $my_desa_data) {
                $where .= " AND w.id_desa = " . intval($my_desa_data->id);
            }
            if (!empty($search)) {
                $where .= " AND (w.nama_wisata LIKE '%{$search}%' OR w.deskripsi LIKE '%{$search}%')";
            }

            // Hitung Total Data (untuk pagination)
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_wisata w $where");
            $total_pages = ceil($total_items / $limit);

            // Ambil Data
            $sql = "SELECT w.*, d.nama_desa 
                    FROM $table_wisata w 
                    LEFT JOIN $table_desa d ON w.id_desa = d.id 
                    $where 
                    ORDER BY w.id DESC 
                    LIMIT $limit OFFSET $offset";
            $rows = $wpdb->get_results($sql);
            ?>

            <!-- Search Box -->
            <form method="get" action="">
                <input type="hidden" name="page" value="dw-wisata">
                <p class="search-box" style="margin-bottom:10px;">
                    <label class="screen-reader-text" for="post-search-input">Cari Wisata:</label>
                    <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search); ?>">
                    <input type="submit" id="search-submit" class="button" value="Cari Wisata">
                </p>
            </form>

            <!-- Table -->
            <div class="card" style="padding:0; overflow:hidden; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th width="70">Foto</th><th>Nama Wisata</th><th>Desa</th><th>Harga Tiket</th><th>Statistik</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r):
                            $edit_link = "?page=dw-wisata&action=edit&id={$r->id}";
                            $img_src = !empty($r->foto_utama) ? $r->foto_utama : 'https://placehold.co/100x70?text=No+Img';
                        ?>
                        <tr>
                            <td><img src="<?php echo esc_url($img_src); ?>" style="width:60px; height:40px; object-fit:cover; border-radius:3px; background:#eee;"></td>
                            <td>
                                <strong><a href="<?php echo $edit_link; ?>"><?php echo esc_html($r->nama_wisata); ?></a></strong>
                                <br><small style="color:#666;">Slug: <?php echo esc_html($r->slug); ?></small>
                            </td>
                            <td><?php echo esc_html($r->nama_desa); ?></td>
                            <td>Rp <?php echo number_format($r->harga_tiket, 0, ',', '.'); ?></td>
                            <td>
                                <span class="dashicons dashicons-star-filled" style="font-size:14px; color:orange;"></span> <?php echo $r->rating_avg; ?>
                                <span style="color:#999;">(<?php echo $r->total_ulasan; ?> ulasan)</span>
                            </td>
                            <td>
                                <?php echo ($r->status == 'aktif') 
                                    ? '<span style="background:#dcfce7; color:#166534; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold;">Aktif</span>' 
                                    : '<span style="background:#fee2e2; color:#991b1b; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold;">Nonaktif</span>'; ?>
                            </td>
                            <td>
                                <a href="<?php echo $edit_link; ?>" class="button button-small button-primary">Edit</a>
                                <form method="post" action="" style="display:inline-block;" onsubmit="return confirm('Hapus permanen?');">
                                    <input type="hidden" name="action_wisata" value="delete">
                                    <input type="hidden" name="wisata_id" value="<?php echo $r->id; ?>">
                                    <?php wp_nonce_field('dw_wisata_save'); ?>
                                    <button type="submit" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px;">Tidak ada data ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $total_items; ?> item</span>
                    <?php
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    if ($page_links) {
                        echo '<span class="pagination-links">' . $page_links . '</span>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}