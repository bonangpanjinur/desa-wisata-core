<?php
/**
 * File Name:   page-wisata.php
 * Description: CRUD Wisata dengan Tampilan Modern (Grid Layout) & Field Lengkap.
 * * UPDATE:
 * - Layout diubah menjadi 2 kolom (seperti halaman Produk/Post).
 * - Penambahan field: Fasilitas, Kontak, Maps, dan Galeri Foto (Multiple Upload).
 * - Penyimpanan data Galeri dalam format JSON.
 */

if (!defined('ABSPATH')) exit;

function dw_wisata_page_render() {
    global $wpdb;
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_desa   = $wpdb->prefix . 'dw_desa';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Cari Desa yang dikelola user ini
    $my_desa_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM $table_desa WHERE id_user_desa = %d", $current_user_id));

    $message = ''; $msg_type = '';

    // --- HANDLE ACTION SAVE/UPDATE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wisata'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_wisata_save')) {
            echo '<div class="notice notice-error"><p>Security Fail.</p></div>'; return;
        }

        // Tentukan ID Desa
        $id_desa_input = 0;
        if ($is_super_admin) {
            $id_desa_input = intval($_POST['id_desa']);
        } else {
            $id_desa_input = $my_desa_data ? intval($my_desa_data->id) : 0;
        }

        if ($id_desa_input === 0) {
            echo '<div class="notice notice-error"><p>Error: Akun Anda tidak terhubung dengan Desa manapun.</p></div>'; return;
        }

        // Proses Galeri (Array URL -> JSON)
        $galeri_json = '[]';
        if (!empty($_POST['galeri_urls'])) {
            // Input berupa string dipisahkan koma, kita ubah jadi array lalu encode ke JSON
            $galeri_array = array_filter(explode(',', $_POST['galeri_urls']));
            $galeri_json = json_encode(array_values($galeri_array));
        }

        $nama = sanitize_text_field($_POST['nama_wisata']);
        
        $data = [
            'id_desa'      => $id_desa_input,
            'nama_wisata'  => $nama,
            'slug'         => sanitize_title($nama),
            'deskripsi'    => wp_kses_post($_POST['deskripsi']),
            'harga_tiket'  => floatval($_POST['harga_tiket']),
            'jam_buka'     => sanitize_text_field($_POST['jam_buka']),
            'fasilitas'    => sanitize_textarea_field($_POST['fasilitas']), // New Field
            'kontak_pengelola' => sanitize_text_field($_POST['kontak_pengelola']), // New Field
            'lokasi_maps'  => esc_url_raw($_POST['lokasi_maps']), // New Field
            'foto_utama'   => esc_url_raw($_POST['foto_utama']),
            'galeri'       => $galeri_json, // New Field (JSON)
            'status'       => sanitize_text_field($_POST['status']),
            'updated_at'   => current_time('mysql')
        ];

        if (!empty($_POST['wisata_id'])) {
            // Security Extra: Pastikan hanya edit punya sendiri (kecuali super admin)
            if (!$is_super_admin) {
                $check_owner = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_wisata WHERE id = %d AND id_desa = %d", intval($_POST['wisata_id']), $id_desa_input));
                if (!$check_owner) {
                    echo '<div class="notice notice-error"><p>Dilarang mengedit wisata desa lain!</p></div>'; return;
                }
            }

            $wpdb->update($table_wisata, $data, ['id' => intval($_POST['wisata_id'])]);
            $message = 'Data Wisata berhasil diperbarui.'; $msg_type = 'success';
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table_wisata, $data);
            $message = 'Objek Wisata baru berhasil ditambahkan.'; $msg_type = 'success';
        }
    }

    // --- VIEW LOGIC ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit');
    $edit_data = null;
    
    if ($is_edit && isset($_GET['id'])) {
        $query_edit = "SELECT * FROM $table_wisata WHERE id = %d";
        if (!$is_super_admin && $my_desa_data) {
            $query_edit .= " AND id_desa = " . intval($my_desa_data->id);
        }
        $edit_data = $wpdb->get_row($wpdb->prepare($query_edit, intval($_GET['id'])));
        
        if (isset($_GET['id']) && !$edit_data) {
            echo '<div class="notice notice-error"><p>Data tidak ditemukan atau akses ditolak.</p></div>';
            $is_edit = false;
        }
    }

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Objek Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-wisata&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if($message): ?><div class="notice notice-<?php echo $msg_type; ?> is-dismissible"><p><?php echo $message; ?></p></div><?php endif; ?>

        <?php if($is_edit): ?>
            <!-- FORM INPUT DENGAN LAYOUT POSTBOX (GRID) -->
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
                                <input type="text" name="nama_wisata" size="30" value="<?php echo esc_attr($edit_data->nama_wisata ?? ''); ?>" id="title" spellcheck="true" autocomplete="off" placeholder="Masukkan nama objek wisata di sini" required style="width:100%; padding:10px; font-size:20px;">
                            </div>

                            <!-- Deskripsi -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Deskripsi Wisata</h2></div>
                                <div class="inside">
                                    <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>10, 'media_buttons'=>true]); ?>
                                </div>
                            </div>

                            <!-- Fasilitas -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Fasilitas & Wahana</h2></div>
                                <div class="inside">
                                    <p class="description">Jelaskan fasilitas yang tersedia (Toilet, Mushola, Parkir, WiFi, dll).</p>
                                    <textarea name="fasilitas" class="large-text" rows="4"><?php echo esc_textarea($edit_data->fasilitas ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Galeri Foto -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Galeri Foto</h2></div>
                                <div class="inside">
                                    <p class="description">Upload foto-foto pendukung untuk slider galeri (Bisa pilih lebih dari satu).</p>
                                    
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
                                    <button type="button" class="button" id="btn_upload_galeri"><span class="dashicons dashicons-images-alt2"></span> Tambah Foto Galeri</button>
                                </div>
                            </div>
                        </div>

                        <!-- KOLOM KANAN (SIDEBAR / PENGATURAN) -->
                        <div id="postbox-container-1" class="postbox-container">
                            
                            <!-- Status & Publish -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Status & Penerbitan</h2></div>
                                <div class="inside">
                                    <p>
                                        <label><strong>Status Wisata:</strong></label>
                                        <select name="status" class="widefat" style="margin-top:5px;">
                                            <option value="aktif" <?php selected($edit_data ? $edit_data->status : '', 'aktif'); ?>>🟢 Aktif</option>
                                            <option value="nonaktif" <?php selected($edit_data ? $edit_data->status : '', 'nonaktif'); ?>>🔴 Nonaktif</option>
                                        </select>
                                    </p>
                                    <div id="major-publishing-actions">
                                        <div id="publishing-action">
                                            <input type="submit" class="button button-primary button-large" style="width:100%;" value="Simpan Data">
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pemilik Desa -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Lokasi Desa</h2></div>
                                <div class="inside">
                                    <?php if ($is_super_admin): ?>
                                        <label>Pilih Desa:</label>
                                        <?php $list_desa = $wpdb->get_results("SELECT id, nama_desa FROM $table_desa WHERE status='aktif'"); ?>
                                        <select name="id_desa" required class="widefat">
                                            <option value="">-- Pilih Desa --</option>
                                            <?php foreach($list_desa as $d): ?>
                                                <option value="<?php echo $d->id; ?>" <?php selected($edit_data ? $edit_data->id_desa : '', $d->id); ?>>
                                                    <?php echo esc_html($d->nama_desa); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?php if ($my_desa_data): ?>
                                            <input type="text" class="widefat" value="<?php echo esc_attr($my_desa_data->nama_desa); ?>" readonly style="background:#f0f0f1; color:#555;">
                                            <input type="hidden" name="id_desa" value="<?php echo esc_attr($my_desa_data->id); ?>">
                                        <?php else: ?>
                                            <div class="notice notice-error inline"><p>Akun Anda belum di-assign ke Desa manapun.</p></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Foto Utama -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Foto Utama</h2></div>
                                <div class="inside">
                                    <div style="margin-bottom:10px; text-align:center; background:#f0f0f1; padding:10px; border-radius:4px;">
                                        <img id="preview_foto_utama" src="<?php echo !empty($edit_data->foto_utama) ? esc_url($edit_data->foto_utama) : 'https://placehold.co/300x200?text=No+Image'; ?>" style="max-width:100%; height:auto; display:block; margin:0 auto;">
                                    </div>
                                    <input type="text" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>" class="widefat" placeholder="URL Gambar">
                                    <button type="button" class="button" id="btn_upload_utama" style="width:100%; margin-top:5px;">Set Foto Utama</button>
                                </div>
                            </div>

                            <!-- Informasi Tiket -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Informasi Tiket</h2></div>
                                <div class="inside">
                                    <p>
                                        <label>Harga Tiket (Rp):</label>
                                        <input name="harga_tiket" type="number" value="<?php echo esc_attr($edit_data->harga_tiket ?? 0); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Jam Operasional:</label>
                                        <input name="jam_buka" type="text" value="<?php echo esc_attr($edit_data->jam_buka ?? '08:00 - 17:00'); ?>" class="widefat" placeholder="Contoh: 08:00 - 17:00">
                                    </p>
                                </div>
                            </div>

                            <!-- Kontak & Lokasi -->
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Kontak & Peta</h2></div>
                                <div class="inside">
                                    <p>
                                        <label>Kontak Pengelola (WA):</label>
                                        <input name="kontak_pengelola" type="text" value="<?php echo esc_attr($edit_data->kontak_pengelola ?? ''); ?>" class="widefat" placeholder="0812...">
                                    </p>
                                    <p>
                                        <label>Link Google Maps:</label>
                                        <input name="lokasi_maps" type="url" value="<?php echo esc_url($edit_data->lokasi_maps ?? ''); ?>" class="widefat" placeholder="https://maps.google.com/...">
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </form>

            <!-- SCRIPTS FOR UPLOADER -->
            <script>
            jQuery(document).ready(function($){
                
                // 1. Single Image Uploader (Foto Utama)
                $('#btn_upload_utama').click(function(e){
                    e.preventDefault();
                    var frame = wp.media({title:'Pilih Foto Utama', multiple:false, library:{type:'image'}});
                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#foto_utama').val(attachment.url);
                        $('#preview_foto_utama').attr('src', attachment.url);
                    });
                    frame.open();
                });

                // 2. Multiple Image Uploader (Galeri)
                var galeriFrame;
                $('#btn_upload_galeri').click(function(e){
                    e.preventDefault();
                    
                    if (galeriFrame) { galeriFrame.open(); return; }

                    galeriFrame = wp.media({
                        title: 'Pilih Foto Galeri',
                        button: { text: 'Tambahkan ke Galeri' },
                        multiple: true,
                        library: { type: 'image' }
                    });

                    galeriFrame.on('select', function(){
                        var selection = galeriFrame.state().get('selection');
                        var currentUrls = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : [];

                        selection.map(function(attachment){
                            attachment = attachment.toJSON();
                            if(currentUrls.indexOf(attachment.url) === -1) {
                                currentUrls.push(attachment.url);
                                // Append preview
                                $('#galeri-preview-container').append(
                                    '<div class="galeri-item" style="position:relative; width:100px; height:100px;">' +
                                    '<img src="'+attachment.url+'" style="width:100%; height:100%; object-fit:cover; border-radius:4px; border:1px solid #ddd;">' +
                                    '<span class="remove-galeri" data-url="'+attachment.url+'" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:20px; height:20px; text-align:center; line-height:20px; cursor:pointer; font-size:12px;">&times;</span>' +
                                    '</div>'
                                );
                            }
                        });
                        
                        $('#galeri_urls').val(currentUrls.join(','));
                    });

                    galeriFrame.open();
                });

                // Remove Gallery Item
                $(document).on('click', '.remove-galeri', function(){
                    var urlToRemove = $(this).data('url');
                    var currentUrls = $('#galeri_urls').val().split(',');
                    
                    // Remove from array
                    var index = currentUrls.indexOf(urlToRemove);
                    if (index > -1) {
                        currentUrls.splice(index, 1);
                    }
                    
                    // Update input and remove DOM
                    $('#galeri_urls').val(currentUrls.join(','));
                    $(this).parent('.galeri-item').remove();
                });

            });
            </script>

        <?php else: ?>
            
            <!-- LIST TABLE VIEW (Tidak Berubah) -->
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Nama Wisata</th><th>Asal Desa</th><th>Harga Tiket</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php 
                    $sql_list = "SELECT w.*, d.nama_desa 
                                 FROM $table_wisata w 
                                 LEFT JOIN $table_desa d ON w.id_desa = d.id";
                    
                    if (!$is_super_admin && $my_desa_data) {
                        $sql_list .= " WHERE w.id_desa = " . intval($my_desa_data->id);
                    } elseif (!$is_super_admin && !$my_desa_data) {
                        $sql_list .= " WHERE 1=0";
                    }
                    
                    $sql_list .= " ORDER BY w.id DESC";
                    $rows = $wpdb->get_results($sql_list);
                    
                    if(empty($rows)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:20px;">Belum ada data wisata.</td></tr>
                    <?php else: foreach($rows as $r): 
                        $edit_url = "?page=dw-wisata&action=edit&id={$r->id}";
                        $desa_html = !empty($r->nama_desa) ? '<span class="dashicons dashicons-location" style="color:#2271b1;"></span> '.esc_html($r->nama_desa) : '-';
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_wisata); ?></a></strong>
                            <br>
                            <?php if($r->foto_utama): ?>
                                <img src="<?php echo esc_url($r->foto_utama); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:4px; margin-top:5px;">
                            <?php endif; ?>
                        </td>
                        <td><?php echo $desa_html; ?></td>
                        <td>Rp <?php echo number_format($r->harga_tiket); ?></td>
                        <td>
                            <?php 
                            $st_class = ($r->status == 'aktif') ? 'color:green;font-weight:bold;' : 'color:grey;';
                            echo '<span style="'.$st_class.'">'.ucfirst($r->status).'</span>'; 
                            ?>
                        </td>
                        <td><a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}