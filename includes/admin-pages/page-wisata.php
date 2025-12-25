<?php
/**
 * File Name:   page-wisata.php
 * Description: CRUD Wisata dengan Tampilan Modern (Premium UI).
 * * UPDATE FIX (UI/UX):
 * - Memperbaiki handling error "Quota Penuh" agar tidak blank page.
 * - Form tetap muncul setelah error, dan data input tidak hilang (preservasi data).
 * - Penambahan tombol "Kembali".
 */

if (!defined('ABSPATH')) exit;

function dw_wisata_page_render() {
    // --- INTEGRASI FITUR PREMIUM: MEDIA UPLOADER ---
    if ( ! did_action( 'wp_enqueue_media' ) ) {
        wp_enqueue_media();
    }

    global $wpdb;
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_desa   = $wpdb->prefix . 'dw_desa';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Cari Desa yang dikelola user ini
    $my_desa_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa, status_akses_verifikasi FROM $table_desa WHERE id_user_desa = %d", $current_user_id));

    $message = ''; $msg_type = '';

    // --- 1. HANDLE ACTIONS (SAVE & DELETE) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wisata'])) {
        
        // Cek Keamanan
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_wisata_save')) {
            echo '<div class="notice notice-error"><p>Security Check Failed.</p></div>'; return;
        }

        // Tentukan ID Desa (Target Desa)
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
            $message = 'Objek wisata berhasil dihapus.'; 
            $msg_type = 'success';
        
        // --- B. SAVE / UPDATE ---
        } elseif ($_POST['action_wisata'] === 'save') {
            
            if ($id_desa_input === 0) {
                $message = 'Error: Akun Anda tidak terhubung dengan Desa manapun.'; $msg_type = 'error';
            } else {
                // Flag untuk menandakan apakah proses simpan boleh lanjut
                $should_save = true;

                // ============================================================
                // LOGIKA VALIDASI STRICT (INSERT & EDIT)
                // ============================================================
                
                // 1. Ambil status desa tujuan dari DB
                $desa_status_check = $wpdb->get_row($wpdb->prepare("SELECT status_akses_verifikasi FROM $table_desa WHERE id = %d", $id_desa_input));
                
                // 2. Jika status desa tujuan BUKAN active (artinya Free/Locked/Pending)
                if ($desa_status_check && $desa_status_check->status_akses_verifikasi !== 'active') {
                    
                    // ID Wisata yang sedang diproses (0 jika Insert Baru, ID jika Edit)
                    $current_process_id = !empty($_POST['wisata_id']) ? intval($_POST['wisata_id']) : 0;

                    // 3. Hitung jumlah wisata LAIN di desa tujuan
                    $count_existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(id) FROM $table_wisata WHERE id_desa = %d AND id != %d", 
                        $id_desa_input, 
                        $current_process_id
                    ));
                    
                    // 4. Cek Kuota (Maksimal 2)
                    if ($count_existing >= 2) {
                        $message = '<strong>GAGAL MENYIMPAN: Kuota Penuh!</strong><br>Desa tujuan berstatus FREE/LOCKED dan sudah memiliki 2 Objek Wisata. Anda tidak bisa menambah atau memindahkan wisata ke desa ini. Silakan Upgrade ke PREMIUM.';
                        $msg_type = 'error';
                        $should_save = false; // Batalkan penyimpanan DB
                    }
                }
                // ============================================================

                if ($should_save) {
                    // Proses Galeri JSON
                    $galeri_json = '[]';
                    if (!empty($_POST['galeri_urls'])) {
                        $galeri_array = array_filter(explode(',', $_POST['galeri_urls']));
                        $galeri_json = json_encode(array_values($galeri_array));
                    }

                    $nama = sanitize_text_field($_POST['nama_wisata']);
                    $slug = sanitize_title($_POST['slug']);
                    if (empty($slug)) { $slug = sanitize_title($nama); }

                    $data = [
                        'id_desa'      => $id_desa_input,
                        'nama_wisata'  => $nama,
                        'slug'         => $slug,
                        'kategori'     => sanitize_text_field($_POST['kategori']),
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
                        
                        $check_own = true;
                        if (!$is_super_admin) {
                            $check_own = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_wisata WHERE id=%d AND id_desa=%d", $wid, $id_desa_input));
                        }

                        if($check_own) { 
                            $wpdb->update($table_wisata, $data, ['id' => $wid]);
                            $message = 'Data Wisata berhasil diperbarui.'; 
                            $msg_type = 'success';
                        } else {
                            $message = 'Akses Ditolak: Data tidak valid.'; $msg_type = 'error';
                        }
                    } else {
                        // Insert
                        $data['created_at'] = current_time('mysql');
                        $wpdb->insert($table_wisata, $data);
                        $message = 'Objek Wisata baru berhasil ditambahkan.';
                        $msg_type = 'success';
                    }
                }
            }
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
            $message = 'Data tidak ditemukan atau Anda tidak memiliki akses.'; $msg_type = 'error';
            $is_edit = false; // Fallback ke list
        }
    }

    // Ambil Daftar Kategori Wisata (Taxonomy WP)
    $kategori_terms = get_terms([
        'taxonomy' => 'kategori_wisata',
        'hide_empty' => false,
    ]);

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Objek Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-wisata&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if($message): ?>
            <div class="notice notice-<?php echo $msg_type; ?> is-dismissible" style="margin-left:0; margin-bottom:20px;">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <?php if($is_edit): ?>
            <!-- === FORM VIEW (EDIT/ADD) === -->
            <?php
            // Logic Preservasi Data (Agar input tidak hilang saat error)
            $use_post = ($msg_type === 'error');
            
            $val_id       = ($edit_data) ? $edit_data->id : '';
            $val_nama     = $use_post ? stripslashes($_POST['nama_wisata']) : ($edit_data->nama_wisata ?? '');
            $val_slug     = $use_post ? stripslashes($_POST['slug']) : ($edit_data->slug ?? '');
            $val_desc     = $use_post ? stripslashes($_POST['deskripsi']) : ($edit_data->deskripsi ?? '');
            $val_fasilitas= $use_post ? stripslashes($_POST['fasilitas']) : ($edit_data->fasilitas ?? '');
            $val_kategori = $use_post ? $_POST['kategori'] : ($edit_data->kategori ?? '');
            $val_foto     = $use_post ? $_POST['foto_utama'] : ($edit_data->foto_utama ?? '');
            $val_harga    = $use_post ? $_POST['harga_tiket'] : ($edit_data->harga_tiket ?? 0);
            $val_jam      = $use_post ? $_POST['jam_buka'] : ($edit_data->jam_buka ?? '');
            $val_kontak   = $use_post ? $_POST['kontak_pengelola'] : ($edit_data->kontak_pengelola ?? '');
            $val_maps     = $use_post ? $_POST['lokasi_maps'] : ($edit_data->lokasi_maps ?? '');
            $val_status   = $use_post ? $_POST['status'] : ($edit_data->status ?? 'aktif');
            $val_galeri   = $use_post ? $_POST['galeri_urls'] : ($edit_data->galeri ?? '');
            $val_iddesa   = $use_post ? $_POST['id_desa'] : ($edit_data->id_desa ?? '');
            ?>

            <form method="post" action="">
                <?php wp_nonce_field('dw_wisata_save'); ?>
                <input type="hidden" name="action_wisata" value="save">
                <?php if($val_id): ?><input type="hidden" name="wisata_id" value="<?php echo esc_attr($val_id); ?>"><?php endif; ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        
                        <!-- KOLOM KIRI (KONTEN UTAMA) -->
                        <div id="post-body-content">
                            <!-- Title -->
                            <div class="dw-input-group" style="margin-bottom: 20px;">
                                <input type="text" name="nama_wisata" size="30" value="<?php echo esc_attr($val_nama); ?>" id="title" placeholder="Nama objek wisata (misal: Air Terjun Bidadari)" required style="width:100%; padding:10px; font-size:20px; border:1px solid #ddd;">
                                
                                <!-- Slug Edit -->
                                <?php if($edit_data): ?>
                                <div style="margin-top:5px; font-size:13px; color:#666;">
                                    Permalink: <input type="text" name="slug" value="<?php echo esc_attr($val_slug); ?>" style="font-size:12px; padding:2px 5px; width:300px; border:none; background:#f9f9f9; border-bottom:1px dashed #ccc;">
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Deskripsi -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Deskripsi Lengkap</h2></div>
                                <div class="inside">
                                    <?php wp_editor($val_desc, 'deskripsi', ['textarea_rows'=>10, 'media_buttons'=>true]); ?>
                                </div>
                            </div>

                            <!-- Fasilitas -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Fasilitas & Wahana</h2></div>
                                <div class="inside">
                                    <p class="description">Sebutkan fasilitas (Parkir, Toilet, Mushola, WiFi, dll).</p>
                                    <textarea name="fasilitas" class="large-text" rows="4"><?php echo esc_textarea($val_fasilitas); ?></textarea>
                                </div>
                            </div>

                            <!-- Galeri Foto -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Galeri Foto</h2></div>
                                <div class="inside">
                                    <div id="galeri-preview-container" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                                        <?php 
                                        if (!empty($val_galeri)) {
                                            $galeri_decoded = json_decode(stripslashes($val_galeri), true);
                                            if (is_array($galeri_decoded)) {
                                                foreach($galeri_decoded as $url) {
                                                    echo '<div class="galeri-item" style="position:relative;width:100px;height:100px;">
                                                            <img src="'.esc_url($url).'" style="width:100%;height:100%;object-fit:cover;border-radius:4px;border:1px solid #ddd;">
                                                            <span class="remove-galeri" data-url="'.esc_attr($url).'" style="position:absolute;top:-5px;right:-5px;background:#d63638;color:white;border-radius:50%;width:20px;height:20px;text-align:center;line-height:20px;cursor:pointer;font-size:14px;">&times;</span>
                                                          </div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                    <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr(stripslashes($val_galeri)); ?>">
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
                                            <option value="aktif" <?php selected($val_status, 'aktif'); ?>>Aktif (Tampil)</option>
                                            <option value="nonaktif" <?php selected($val_status, 'nonaktif'); ?>>Nonaktif (Sembunyi)</option>
                                        </select>
                                    </p>
                                    <!-- Statistik Read Only -->
                                    <?php if($edit_data): ?>
                                    <div style="background:#f0f0f1; padding:10px; margin-bottom:10px; font-size:12px; border-radius:4px;">
                                        <strong>Rating:</strong> <span style="color:orange;">★</span> <?php echo esc_html($edit_data->rating_avg); ?> / 5.0<br>
                                        <strong>Ulasan:</strong> <?php echo esc_html($edit_data->total_ulasan); ?> review
                                    </div>
                                    <?php endif; ?>

                                    <div id="major-publishing-actions" style="display:flex; gap:10px; margin-top:10px;">
                                        <input type="submit" class="button button-primary button-large" style="flex:1;" value="Simpan">
                                        <a href="?page=dw-wisata" class="button button-large">Kembali</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Panel Kategori -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Kategori Wisata</h2></div>
                                <div class="inside">
                                    <label for="kategori">Pilih Kategori:</label>
                                    <select name="kategori" id="kategori" class="widefat" style="margin-top:5px;">
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php if (!empty($kategori_terms) && !is_wp_error($kategori_terms)) : ?>
                                            <?php foreach ($kategori_terms as $term) : ?>
                                                <option value="<?php echo esc_attr($term->name); ?>" <?php selected($val_kategori, $term->name); ?>>
                                                    <?php echo esc_html($term->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <p class="description">
                                        <a href="edit-tags.php?taxonomy=kategori_wisata&post_type=dw_wisata" target="_blank">+ Kelola Kategori</a>
                                    </p>
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
                                            foreach($ds as $d) echo "<option value='{$d->id}' ".selected($val_iddesa, $d->id, false).">{$d->nama_desa}</option>"; ?>
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
                                        <img id="preview_foto_utama" src="<?php echo !empty($val_foto) ? esc_url($val_foto) : 'https://placehold.co/300x200?text=No+Image'; ?>" style="max-width:100%; height:auto;">
                                    </div>
                                    <input type="text" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($val_foto); ?>" class="widefat" placeholder="URL Gambar">
                                    <button type="button" class="button" id="btn_upload_utama" style="width:100%; margin-top:5px;">Pilih Gambar Utama</button>
                                </div>
                            </div>

                            <!-- Info Tiket & Kontak -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Info & Kontak</h2></div>
                                <div class="inside">
                                    <p>
                                        <label>Harga Tiket (Rp):</label>
                                        <input name="harga_tiket" type="number" value="<?php echo esc_attr($val_harga); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Jam Buka:</label>
                                        <input name="jam_buka" type="text" value="<?php echo esc_attr($val_jam); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Kontak (WA):</label>
                                        <input name="kontak_pengelola" type="text" value="<?php echo esc_attr($val_kontak); ?>" class="widefat" placeholder="0812...">
                                    </p>
                                    <p>
                                        <label>Link Google Maps:</label>
                                        <input name="lokasi_maps" type="url" value="<?php echo esc_url($val_maps); ?>" class="widefat" placeholder="https://maps.google.com/...">
                                        <?php if(!empty($val_maps)): ?>
                                            <a href="<?php echo esc_url($val_maps); ?>" target="_blank" style="font-size:12px; text-decoration:none;">&nearr; Buka Peta</a>
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
            
            <!-- === LIST TABLE VIEW MODERN (CARD STYLE - PREMIUM UI) === -->
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
            <div class="tablenav top" style="display:flex; justify-content:flex-end; margin-bottom:15px;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="dw-wisata">
                    <p class="search-box">
                        <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Wisata...">
                        <input type="submit" id="search-submit" class="button" value="Cari">
                    </p>
                </form>
            </div>

            <!-- Styles untuk Tabel Cantik -->
            <style>
                .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); overflow:hidden; }
                .dw-thumb-wisata { width:60px; height:60px; border-radius:6px; object-fit:cover; border:1px solid #eee; background:#f9f9f9; display:block; }
                
                .dw-badge { display:inline-block; padding:4px 8px; border-radius:12px; font-size:11px; font-weight:600; line-height:1; }
                .dw-badge-active { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
                .dw-badge-nonaktif { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
                
                .dw-price-tag { font-weight:700; color:#1e293b; font-size:13px; }
                .dw-price-free { color:#166534; background:#dcfce7; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600; }
                
                .dw-rating-pill { background:#fffbeb; color:#b45309; border:1px solid #fcd34d; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600; display:inline-flex; align-items:center; gap:3px; }
                .dw-category-tag { background:#f1f5f9; color:#475569; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:500; border:1px solid #e2e8f0; }
                
                .column-foto { width: 80px; text-align:center; }
            </style>

            <!-- Table -->
            <div class="dw-card-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-foto">Foto</th>
                            <th width="20%">Nama Wisata</th>
                            <th width="15%">Kategori</th>
                            <th width="15%">Desa & Lokasi</th>
                            <th width="10%">Harga Tiket</th>
                            <th width="15%">Statistik</th>
                            <th width="10%">Status</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r):
                            $edit_link = "?page=dw-wisata&action=edit&id={$r->id}";
                            $img_src = !empty($r->foto_utama) ? $r->foto_utama : 'https://placehold.co/100x100/e2e8f0/64748b?text=Wisata';
                            
                            // Badge Status
                            $status_class = ($r->status == 'aktif') ? 'dw-badge-active' : 'dw-badge-nonaktif';
                            
                            // Harga
                            $harga_html = ($r->harga_tiket > 0) 
                                ? '<span class="dw-price-tag">Rp ' . number_format($r->harga_tiket, 0, ',', '.') . '</span>' 
                                : '<span class="dw-price-free">GRATIS</span>';
                        ?>
                        <tr>
                            <td class="column-foto" style="vertical-align:middle;">
                                <img src="<?php echo esc_url($img_src); ?>" class="dw-thumb-wisata">
                            </td>
                            <td>
                                <strong><a href="<?php echo $edit_link; ?>" style="font-size:14px;"><?php echo esc_html($r->nama_wisata); ?></a></strong>
                                <br><small style="color:#64748b;">/<?php echo esc_html($r->slug); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($r->kategori)): ?>
                                    <span class="dw-category-tag"><?php echo esc_html($r->kategori); ?></span>
                                <?php else: ?>
                                    <span style="color:#aaa;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600; color:#2271b1;"><span class="dashicons dashicons-location"></span> <?php echo esc_html($r->nama_desa); ?></div>
                                <?php if($r->lokasi_maps): ?>
                                    <div style="margin-top:2px;">
                                        <a href="<?php echo esc_url($r->lokasi_maps); ?>" target="_blank" style="font-size:11px; text-decoration:none; color:#64748b;">
                                            <span class="dashicons dashicons-map"></span> Lihat Peta
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $harga_html; ?></td>
                            <td>
                                <div style="margin-bottom:4px;">
                                    <span class="dw-rating-pill" title="Rating Rata-rata">
                                        <span class="dashicons dashicons-star-filled" style="font-size:12px;"></span> <?php echo $r->rating_avg; ?>
                                    </span>
                                </div>
                                <small style="color:#64748b;"><?php echo $r->total_ulasan; ?> Ulasan</small>
                            </td>
                            <td>
                                <span class="dw-badge <?php echo $status_class; ?>"><?php echo ucfirst($r->status); ?></span>
                            </td>
                            <td>
                                <a href="<?php echo $edit_link; ?>" class="button button-small button-primary" style="margin-bottom:4px;">Edit</a>
                                <form method="post" action="" style="display:inline-block;" onsubmit="return confirm('Yakin ingin menghapus wisata <?php echo esc_js($r->nama_wisata); ?>?');">
                                    <input type="hidden" name="action_wisata" value="delete">
                                    <input type="hidden" name="wisata_id" value="<?php echo $r->id; ?>">
                                    <?php wp_nonce_field('dw_wisata_save'); ?>
                                    <button type="submit" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#777;">Belum ada data objek wisata. Silakan tambah baru.</td></tr>
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
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    ?>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}
?>