<?php
/**
 * File Name:   page-wisata.php
 * Description: CRUD Wisata dengan Tampilan Modern (Grid Layout 2 Kolom).
 * * PERBAIKAN TOTAL:
 * - Form input dibuat hardcoded HTML agar pasti muncul (bypass meta-box dependency).
 * - Layout 2 kolom (Kiri: Konten Utama, Kanan: Sidebar/Publish).
 * - Field baru: Fasilitas, Kontak, Maps, Galeri (JSON).
 */

if (!defined('ABSPATH')) exit;

function dw_wisata_page_render() {
    global $wpdb;
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_desa   = $wpdb->prefix . 'dw_desa';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Cari Desa yang dikelola user ini (untuk validasi)
    $my_desa_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM $table_desa WHERE id_user_desa = %d", $current_user_id));

    $message = ''; $msg_type = '';

    // --- 1. HANDLE SAVE DATA (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wisata']) && $_POST['action_wisata'] == 'save') {
        
        // Verifikasi Nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_wisata_save')) {
            echo '<div class="notice notice-error"><p>Security Fail: Invalid Nonce.</p></div>'; 
            return;
        }

        // Tentukan ID Desa
        $id_desa_input = 0;
        if ($is_super_admin) {
            $id_desa_input = isset($_POST['id_desa']) ? intval($_POST['id_desa']) : 0;
        } else {
            $id_desa_input = $my_desa_data ? intval($my_desa_data->id) : 0;
        }

        if ($id_desa_input === 0) {
            echo '<div class="notice notice-error"><p>Error: Desa tidak valid atau Anda tidak memiliki akses desa.</p></div>'; 
            return;
        }

        // Proses Data Input
        $nama = sanitize_text_field($_POST['nama_wisata']);
        
        // Proses Galeri (String dipisah koma -> JSON)
        $galeri_json = '[]';
        if (!empty($_POST['galeri_urls'])) {
            $galeri_array = array_filter(explode(',', $_POST['galeri_urls']));
            // Bersihkan URL
            $galeri_clean = array_map('esc_url_raw', $galeri_array);
            $galeri_json = json_encode(array_values($galeri_clean));
        }

        $data_db = [
            'id_desa'      => $id_desa_input,
            'nama_wisata'  => $nama,
            'slug'         => sanitize_title($nama),
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

        // LOGIKA UPDATE vs INSERT
        if (!empty($_POST['wisata_id'])) {
            $wisata_id = intval($_POST['wisata_id']);
            
            // Cek kepemilikan sebelum update
            if (!$is_super_admin) {
                $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_wisata WHERE id = %d AND id_desa = %d", $wisata_id, $id_desa_input));
                if (!$check) {
                    echo '<div class="notice notice-error"><p>Akses Ditolak: Anda tidak berhak mengedit wisata ini.</p></div>'; return;
                }
            }

            $wpdb->update($table_wisata, $data_db, ['id' => $wisata_id]);
            $message = 'Data Wisata berhasil diperbarui.'; $msg_type = 'success';
        } else {
            $data_db['created_at'] = current_time('mysql');
            $wpdb->insert($table_wisata, $data_db);
            $message = 'Objek Wisata berhasil ditambahkan.'; $msg_type = 'success';
        }
    }

    // --- 2. VIEW LOGIC (EDIT vs LIST) ---
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $is_edit = ($action == 'new' || $action == 'edit');
    $edit_data = null;
    
    // Ambil data jika mode edit
    if ($action == 'edit' && isset($_GET['id'])) {
        $id_edit = intval($_GET['id']);
        $sql_edit = "SELECT * FROM $table_wisata WHERE id = %d";
        
        if (!$is_super_admin && $my_desa_data) {
            $sql_edit .= " AND id_desa = " . intval($my_desa_data->id);
        }
        
        $edit_data = $wpdb->get_row($wpdb->prepare($sql_edit, $id_edit));
        
        if (!$edit_data) {
            echo '<div class="notice notice-error"><p>Data tidak ditemukan.</p></div>';
            $is_edit = false; // Kembali ke list
        }
    }

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Objek Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-wisata&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if($message): ?><div class="notice notice-<?php echo $msg_type; ?> is-dismissible"><p><?php echo $message; ?></p></div><?php endif; ?>

        <?php if($is_edit): ?>
            <!-- FORM EDIT / TAMBAH (LAYOUT 2 KOLOM) -->
            <form method="post" action="">
                <?php wp_nonce_field('dw_wisata_save'); ?>
                <input type="hidden" name="action_wisata" value="save">
                <?php if($edit_data): ?><input type="hidden" name="wisata_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        
                        <!-- === KOLOM KIRI (KONTEN UTAMA) === -->
                        <div id="post-body-content">
                            <!-- Judul -->
                            <div class="dw-input-group" style="margin-bottom: 20px;">
                                <input type="text" name="nama_wisata" size="30" value="<?php echo esc_attr($edit_data->nama_wisata ?? ''); ?>" id="title" placeholder="Masukkan nama objek wisata di sini" required style="width:100%; padding:10px; font-size:20px; border:1px solid #ddd;">
                            </div>

                            <!-- Deskripsi -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Deskripsi Wisata</h2></div>
                                <div class="inside">
                                    <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>10, 'media_buttons'=>true]); ?>
                                </div>
                            </div>

                            <!-- Fasilitas (FIELD BARU) -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Fasilitas & Wahana</h2></div>
                                <div class="inside">
                                    <p class="description">Jelaskan fasilitas yang tersedia (Toilet, Mushola, Parkir, WiFi, dll).</p>
                                    <textarea name="fasilitas" class="large-text" rows="4"><?php echo esc_textarea($edit_data->fasilitas ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Galeri Foto (FIELD BARU) -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Galeri Foto</h2></div>
                                <div class="inside">
                                    <p class="description">Upload foto-foto pendukung untuk slider galeri.</p>
                                    
                                    <div id="galeri-preview-container" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                                        <?php 
                                        $galeri_urls = [];
                                        if (!empty($edit_data->galeri)) {
                                            $galeri_decoded = json_decode($edit_data->galeri, true);
                                            if (is_array($galeri_decoded)) {
                                                foreach($galeri_decoded as $url) {
                                                    $galeri_urls[] = $url;
                                                    echo '<div class="galeri-item" style="position:relative; width:100px; height:100px;">
                                                            <img src="'.esc_url($url).'" style="width:100%; height:100%; object-fit:cover; border-radius:4px; border:1px solid #ddd;">
                                                            <span class="remove-galeri" data-url="'.esc_attr($url).'" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:20px; height:20px; text-align:center; line-height:20px; cursor:pointer; font-size:12px;">&times;</span>
                                                          </div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                    
                                    <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr(implode(',', $galeri_urls)); ?>">
                                    <button type="button" class="button" id="btn_upload_galeri">
                                        <span class="dashicons dashicons-images-alt2"></span> Tambah Foto Galeri
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- === KOLOM KANAN (SIDEBAR) === -->
                        <div id="postbox-container-1" class="postbox-container">
                            
                            <!-- Status Publish -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Status & Penerbitan</h2></div>
                                <div class="inside">
                                    <p>
                                        <label><strong>Status:</strong></label>
                                        <select name="status" class="widefat" style="margin-top:5px;">
                                            <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                            <option value="nonaktif" <?php selected($edit_data->status ?? '', 'nonaktif'); ?>>Nonaktif</option>
                                        </select>
                                    </p>
                                    <div id="major-publishing-actions">
                                        <input type="submit" class="button button-primary button-large" style="width:100%;" value="Simpan Perubahan">
                                    </div>
                                </div>
                            </div>

                            <!-- Pemilihan Desa -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Lokasi Desa</h2></div>
                                <div class="inside">
                                    <?php if ($is_super_admin): ?>
                                        <label>Pilih Desa:</label>
                                        <?php $desa_list = $wpdb->get_results("SELECT id, nama_desa FROM $table_desa WHERE status='aktif'"); ?>
                                        <select name="id_desa" class="widefat">
                                            <option value="">-- Pilih --</option>
                                            <?php foreach($desa_list as $d): ?>
                                                <option value="<?php echo $d->id; ?>" <?php selected($edit_data->id_desa ?? '', $d->id); ?>>
                                                    <?php echo esc_html($d->nama_desa); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?php if ($my_desa_data): ?>
                                            <input type="text" class="widefat" value="<?php echo esc_attr($my_desa_data->nama_desa); ?>" readonly disabled>
                                            <input type="hidden" name="id_desa" value="<?php echo esc_attr($my_desa_data->id); ?>">
                                        <?php else: ?>
                                            <p style="color:red;">Anda belum terhubung ke desa.</p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Foto Utama -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Foto Utama</h2></div>
                                <div class="inside">
                                    <div style="background:#f0f0f1; padding:10px; text-align:center; margin-bottom:10px;">
                                        <img id="preview_foto_utama" src="<?php echo !empty($edit_data->foto_utama) ? esc_url($edit_data->foto_utama) : 'https://placehold.co/300x200?text=No+Image'; ?>" style="max-width:100%; height:auto;">
                                    </div>
                                    <input type="hidden" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>">
                                    <button type="button" class="button" id="btn_upload_utama" style="width:100%;">Pilih Foto Utama</button>
                                </div>
                            </div>

                            <!-- Tiket -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Informasi Tiket</h2></div>
                                <div class="inside">
                                    <p>
                                        <label>Harga Tiket (Rp):</label>
                                        <input name="harga_tiket" type="number" value="<?php echo esc_attr($edit_data->harga_tiket ?? 0); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Jam Operasional:</label>
                                        <input name="jam_buka" type="text" value="<?php echo esc_attr($edit_data->jam_buka ?? '08:00 - 17:00'); ?>" class="widefat">
                                    </p>
                                </div>
                            </div>

                            <!-- Kontak & Maps (FIELD BARU) -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Kontak & Peta</h2></div>
                                <div class="inside">
                                    <p>
                                        <label>Kontak (WA):</label>
                                        <input name="kontak_pengelola" type="text" value="<?php echo esc_attr($edit_data->kontak_pengelola ?? ''); ?>" class="widefat" placeholder="628...">
                                    </p>
                                    <p>
                                        <label>Link Google Maps:</label>
                                        <input name="lokasi_maps" type="url" value="<?php echo esc_url($edit_data->lokasi_maps ?? ''); ?>" class="widefat" placeholder="https://goo.gl/maps/...">
                                    </p>
                                </div>
                            </div>

                        </div> <!-- End Sidebar -->
                    </div> <!-- End Columns -->
                </div> <!-- End Poststuff -->
            </form>

            <!-- JS Uploader -->
            <script>
            jQuery(document).ready(function($){
                // Foto Utama
                $('#btn_upload_utama').click(function(e){
                    e.preventDefault();
                    var frame = wp.media({title:'Foto Utama', multiple:false, library:{type:'image'}});
                    frame.on('select', function(){
                        var att = frame.state().get('selection').first().toJSON();
                        $('#foto_utama').val(att.url);
                        $('#preview_foto_utama').attr('src', att.url);
                    });
                    frame.open();
                });

                // Galeri (Multiple)
                var gFrame;
                $('#btn_upload_galeri').click(function(e){
                    e.preventDefault();
                    if(gFrame) { gFrame.open(); return; }
                    gFrame = wp.media({title:'Galeri Foto', multiple:true, library:{type:'image'}});
                    gFrame.on('select', function(){
                        var selection = gFrame.state().get('selection');
                        var urls = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : [];
                        
                        selection.map(function(att){
                            att = att.toJSON();
                            if(urls.indexOf(att.url) === -1){
                                urls.push(att.url);
                                $('#galeri-preview-container').append(
                                    '<div class="galeri-item" style="position:relative; width:100px; height:100px;">'+
                                    '<img src="'+att.url+'" style="width:100%;height:100%;object-fit:cover;">'+
                                    '<span class="remove-galeri" data-url="'+att.url+'" style="position:absolute;top:0;right:0;background:red;color:white;cursor:pointer;padding:0 5px;">&times;</span>'+
                                    '</div>'
                                );
                            }
                        });
                        $('#galeri_urls').val(urls.join(','));
                    });
                    gFrame.open();
                });

                $(document).on('click', '.remove-galeri', function(){
                    var u = $(this).data('url');
                    var urls = $('#galeri_urls').val().split(',');
                    var idx = urls.indexOf(u);
                    if(idx > -1) urls.splice(idx,1);
                    $('#galeri_urls').val(urls.join(','));
                    $(this).closest('.galeri-item').remove();
                });
            });
            </script>

        <?php else: ?>
            
            <!-- LIST TABLE VIEW -->
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Nama Wisata</th><th>Desa</th><th>Harga</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php 
                    $sql = "SELECT w.*, d.nama_desa FROM $table_wisata w LEFT JOIN $table_desa d ON w.id_desa = d.id";
                    if (!$is_super_admin && $my_desa_data) {
                        $sql .= " WHERE w.id_desa = " . intval($my_desa_data->id);
                    }
                    $sql .= " ORDER BY w.id DESC";
                    $rows = $wpdb->get_results($sql);
                    
                    if($rows): foreach($rows as $r):
                        $edit_link = "?page=dw-wisata&action=edit&id={$r->id}";
                    ?>
                    <tr>
                        <td><strong><a href="<?php echo $edit_link; ?>"><?php echo esc_html($r->nama_wisata); ?></a></strong></td>
                        <td><?php echo esc_html($r->nama_desa); ?></td>
                        <td>Rp <?php echo number_format($r->harga_tiket); ?></td>
                        <td><?php echo ucfirst($r->status); ?></td>
                        <td><a href="<?php echo $edit_link; ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5">Belum ada data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php endif; ?>
    </div>
    <?php
}