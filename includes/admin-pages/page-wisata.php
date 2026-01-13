<?php
/**
 * File Name: includes/admin-pages/page-wisata.php
 * Description: Manajemen Objek Wisata (CRUD Modern UI).
 * Matches DB Table: dw_wisata
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// Include UI components
$ui_path = dirname(dirname(__FILE__)) . '/admin-ui-components.php';
if (file_exists($ui_path)) require_once $ui_path;

function dw_wisata_page_render() {
    // Enqueue Media
    if ( ! did_action( 'wp_enqueue_media' ) ) {
        wp_enqueue_media();
    }

    global $wpdb;
    $table_wisata = $wpdb->prefix . 'dw_wisata'; 
    $table_desa   = $wpdb->prefix . 'dw_desa';
    
    // Cek apakah tabel wisata ada
    if($wpdb->get_var("SHOW TABLES LIKE '$table_wisata'") != $table_wisata) {
        echo '<div class="notice notice-error"><p>Tabel database <code>'.$table_wisata.'</code> tidak ditemukan. Silakan aktifkan ulang plugin.</p></div>';
        return;
    }

    $message = '';
    $message_type = '';

    // --- LOGIC: CRUD ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wisata'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_wisata_action')) {
            $message = 'Security Check Failed.'; $message_type = 'error';
        } else {
            $act = $_POST['action_wisata'];
            
            // DELETE
            if ($act === 'delete' && !empty($_POST['wisata_id'])) {
                $deleted = $wpdb->delete($table_wisata, ['id' => intval($_POST['wisata_id'])]);
                if ($deleted !== false) {
                    $message = 'Data wisata berhasil dihapus.'; $message_type = 'success';
                } else {
                    $message = 'Gagal menghapus data: ' . $wpdb->last_error; $message_type = 'error';
                }
            } 
            
            // SAVE
            elseif ($act === 'save') {
                // Handle Slug (Prioritas input manual, fallback ke nama wisata)
                $slug = !empty($_POST['slug']) ? sanitize_title($_POST['slug']) : sanitize_title($_POST['nama_wisata']);

                $data = [
                    'id_desa'         => intval($_POST['id_desa']),
                    'nama_wisata'     => sanitize_text_field($_POST['nama_wisata']),
                    'slug'            => $slug,
                    'kategori'        => sanitize_text_field($_POST['kategori']),
                    'deskripsi'       => wp_kses_post($_POST['deskripsi']),
                    'harga_tiket'     => floatval($_POST['harga_tiket']),
                    'jam_buka'        => sanitize_text_field($_POST['jam_buka']),
                    'jam_tutup'       => sanitize_text_field($_POST['jam_tutup']),
                    'fasilitas'       => sanitize_textarea_field($_POST['fasilitas']),
                    'kontak_pengelola'=> sanitize_text_field($_POST['kontak_pengelola']),
                    // FIX: Menggunakan lokasi_maps karena kolom 'lokasi' tidak ada di DB
                    'lokasi_maps'     => esc_url_raw($_POST['lokasi_maps']),
                    'video_url'       => esc_url_raw($_POST['video_url']),
                    'foto_utama'      => esc_url_raw($_POST['foto_utama']),
                    'galeri'          => !empty($_POST['galeri_urls']) ? json_encode(array_filter(explode(',', wp_unslash($_POST['galeri_urls'])))) : '[]',
                    'status'          => sanitize_text_field($_POST['status']),
                    'updated_at'      => current_time('mysql')
                ];

                if (!empty($_POST['wisata_id'])) {
                    $result = $wpdb->update($table_wisata, $data, ['id' => intval($_POST['wisata_id'])]);
                    if ($result !== false) {
                        $message = 'Wisata berhasil diperbarui.'; $message_type = 'success';
                    } else {
                        $message = 'Gagal memperbarui data: ' . $wpdb->last_error; $message_type = 'error';
                    }
                } else {
                    $data['created_at'] = current_time('mysql');
                    $result = $wpdb->insert($table_wisata, $data);
                    if ($result !== false) {
                        $message = 'Wisata baru berhasil ditambahkan.'; $message_type = 'success';
                    } else {
                        $message = 'Gagal menambahkan data: ' . $wpdb->last_error; $message_type = 'error';
                    }
                }
            }
        }
    }

    // --- VIEW LOGIC ---
    $view = isset($_GET['action']) ? $_GET['action'] : 'list';
    $edit_data = null;

    if (($view === 'edit' || $view === 'new') && !empty($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_wisata WHERE id = %d", intval($_GET['id'])));
    }
    
    // Ambil list desa untuk dropdown
    $list_desa = $wpdb->get_results("SELECT id, nama_desa FROM $table_desa WHERE status = 'active' OR status = 'aktif' ORDER BY nama_desa ASC");
    
    // Stats for list view
    $total_wisata = $wpdb->get_var("SELECT COUNT(id) FROM $table_wisata");
    $active_wisata = $wpdb->get_var("SELECT COUNT(id) FROM $table_wisata WHERE status = 'aktif'");
    
    // Ambil Kategori (jika pakai tax wp) - Optional
    $kategori_terms = get_terms(['taxonomy' => 'kategori_wisata', 'hide_empty' => false]);
    ?>

    <div class="wrap dw-admin-wrapper">
        <!-- HEADER -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p class="dw-subtitle">Kelola destinasi dan objek wisata desa.</p>
            </div>
            <div class="dw-header-actions">
                <?php if ($view === 'list'): ?>
                    <a href="?page=dw-wisata&action=new" class="dw-button dw-button-primary">
                        <span class="dashicons dashicons-plus-alt2"></span> Tambah Wisata
                    </a>
                <?php else: ?>
                    <a href="?page=dw-wisata" class="dw-button dw-button-secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span> Kembali
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="margin-top:20px;">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <div class="dw-content-body">
            
            <?php if ($view === 'list'): ?>
                
                <!-- STATS -->
                <div class="dw-stats-grid">
                    <div class="dw-stat-card">
                        <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-location-alt"></span></div>
                        <h4 class="dw-stat-value"><?php echo $total_wisata; ?></h4>
                        <span class="dw-stat-label">Total Wisata</span>
                    </div>
                    <div class="dw-stat-card">
                        <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-yes"></span></div>
                        <h4 class="dw-stat-value"><?php echo $active_wisata; ?></h4>
                        <span class="dw-stat-label">Aktif</span>
                    </div>
                </div>

                <!-- VIEW LIST -->
                <div class="dw-card">
                    <div class="dw-card-header">
                        <h3 class="card-heading">Daftar Objek Wisata</h3>
                        <form method="get" style="display:flex; gap:10px;">
                            <input type="hidden" name="page" value="dw-wisata">
                            <input type="text" name="s" class="dw-input" placeholder="Cari wisata..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                            <button class="dw-button dw-button-secondary">Cari</button>
                        </form>
                    </div>
                    <div class="dw-card-body" style="padding:0;">
                        <div class="dw-table-wrapper" style="border:none; border-radius:0;">
                            <table class="dw-modern-table">
                                <thead>
                                    <tr>
                                        <th width="80">Foto</th>
                                        <th>Nama Wisata</th>
                                        <th>Desa Pengelola</th>
                                        <th>Harga Tiket</th>
                                        <th>Status</th>
                                        <th style="text-align:right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $search_q = isset($_GET['s']) ? esc_sql($_GET['s']) : '';
                                    $sql = "SELECT w.*, d.nama_desa FROM $table_wisata w LEFT JOIN $table_desa d ON w.id_desa = d.id WHERE 1=1";
                                    if ($search_q) $sql .= " AND w.nama_wisata LIKE '%$search_q%'";
                                    $sql .= " ORDER BY w.id DESC";
                                    
                                    $rows = $wpdb->get_results($sql);
                                    
                                    if ($rows): foreach ($rows as $r): ?>
                                        <tr>
                                            <td>
                                                <img src="<?php echo $r->foto_utama ? esc_url($r->foto_utama) : 'https://placehold.co/100x100?text=IMG'; ?>" style="width:60px; height:40px; object-fit:cover; border-radius:4px;">
                                            </td>
                                            <td>
                                                <strong><?php echo esc_html($r->nama_wisata); ?></strong>
                                            </td>
                                            <td>
                                                <?php if($r->nama_desa): ?>
                                                    <span style="color:var(--dw-brand-blue); font-weight:500;"><?php echo esc_html($r->nama_desa); ?></span>
                                                <?php else: ?>
                                                    <span class="dw-text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>Rp <?php echo number_format($r->harga_tiket, 0, ',', '.'); ?></td>
                                            <td>
                                                <?php if($r->status == 'aktif'): ?>
                                                    <span class="dw-badge status-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="dw-badge status-warning">Draft</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:flex; gap:5px; justify-content:flex-end;">
                                                    <a href="?page=dw-wisata&action=edit&id=<?php echo $r->id; ?>" class="dw-button dw-button-secondary" style="padding:4px 8px; font-size:12px;">Edit</a>
                                                    <form method="post" onsubmit="return confirm('Hapus wisata ini?');">
                                                        <?php wp_nonce_field('dw_wisata_action'); ?>
                                                        <input type="hidden" name="action_wisata" value="delete">
                                                        <input type="hidden" name="wisata_id" value="<?php echo $r->id; ?>">
                                                        <button class="dw-button" style="padding:4px 8px; font-size:12px; color:#d63638; border:none; background:none; cursor:pointer;">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="6" style="text-align:center; padding:30px;">Belum ada data wisata.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- VIEW FORM (ADD/EDIT) -->
                <form method="post">
                    <?php wp_nonce_field('dw_wisata_action'); ?>
                    <input type="hidden" name="action_wisata" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="wisata_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <div class="dw-grid-2-col">
                        <!-- Left Column -->
                        <div class="dw-content">
                            <div class="dw-card">
                                <div class="dw-card-header"><h3 class="card-heading">Informasi Wisata</h3></div>
                                <div class="dw-card-body">
                                    <div class="dw-form-group">
                                        <label class="dw-label">Nama Wisata <span style="color:red">*</span></label>
                                        <input type="text" name="nama_wisata" class="dw-input" value="<?php echo esc_attr($edit_data->nama_wisata ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="dw-form-group">
                                        <label class="dw-label">Permalink (Slug)</label>
                                        <input type="text" name="slug" class="dw-input" value="<?php echo esc_attr($edit_data->slug ?? ''); ?>" placeholder="Biarkan kosong untuk generate otomatis">
                                        <p class="dw-help-text">URL ramah mesin pencari, misal: pantai-indah-kuta</p>
                                    </div>

                                    <div class="dw-form-group">
                                        <label class="dw-label">Kategori</label>
                                        <input type="text" name="kategori" class="dw-input" value="<?php echo esc_attr($edit_data->kategori ?? ''); ?>" placeholder="Alam, Budaya, Kuliner...">
                                    </div>

                                    <div class="dw-form-group">
                                        <label class="dw-label">Deskripsi</label>
                                        <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows' => 10, 'media_buttons' => false, 'editor_class' => 'dw-input']); ?>
                                    </div>
                                    
                                    <div class="dw-grid-2-col">
                                        <div class="dw-form-group">
                                            <label class="dw-label">URL Google Maps</label>
                                            <input type="url" name="lokasi_maps" class="dw-input" value="<?php echo esc_attr($edit_data->lokasi_maps ?? ''); ?>" placeholder="https://maps.google.com/...">
                                            <p class="dw-help-text">Masukkan link Google Maps lokasi wisata.</p>
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Fasilitas</label>
                                            <textarea name="fasilitas" class="dw-input" rows="4" placeholder="Parkir, Toilet, Mushola, WiFi..."><?php echo esc_textarea($edit_data->fasilitas ?? ''); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="dw-form-group">
                                        <label class="dw-label">Kontak Pengelola</label>
                                        <input type="text" name="kontak_pengelola" class="dw-input" value="<?php echo esc_attr($edit_data->kontak_pengelola ?? ''); ?>" placeholder="Nomor WA / Telp">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="dw-card">
                                <div class="dw-card-header"><h3 class="card-heading">Galeri & Video</h3></div>
                                <div class="dw-card-body">
                                    <div class="dw-form-group">
                                        <label class="dw-label">Video URL (YouTube)</label>
                                        <input type="url" name="video_url" class="dw-input" value="<?php echo esc_url($edit_data->video_url ?? ''); ?>" placeholder="https://youtube.com/watch?v=...">
                                    </div>

                                    <label class="dw-label">Galeri Foto</label>
                                    <div id="galeri-container" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                                        <?php 
                                        $galeri_urls = [];
                                        if (!empty($edit_data->galeri)) {
                                            $decoded = json_decode($edit_data->galeri, true);
                                            if (is_array($decoded)) {
                                                foreach($decoded as $url) {
                                                    $galeri_urls[] = $url;
                                                    echo '<div style="position:relative; width:80px; height:80px;"><img src="'.esc_url($url).'" style="width:100%; height:100%; object-fit:cover; border-radius:4px;"><span class="rem-g" data-url="'.esc_attr($url).'" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:18px; height:18px; text-align:center; font-size:12px; cursor:pointer; line-height:18px;">x</span></div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                    <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr(implode(',', $galeri_urls)); ?>">
                                    <button type="button" class="dw-button dw-button-secondary" id="btn_galeri">Tambah Foto Galeri</button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="dw-sidebar">
                            <div class="dw-card">
                                <div class="dw-card-header"><h3 class="card-heading">Status & Penerbitan</h3></div>
                                <div class="dw-card-body">
                                    <div class="dw-form-group">
                                        <label class="dw-label">Status</label>
                                        <select name="status" class="dw-input">
                                            <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                            <option value="nonaktif" <?php selected($edit_data->status ?? '', 'nonaktif'); ?>>Nonaktif</option>
                                        </select>
                                    </div>
                                    <div class="dw-form-group">
                                        <label class="dw-label">Desa Pengelola</label>
                                        <select name="id_desa" class="dw-input select2" required>
                                            <option value="">-- Pilih Desa --</option>
                                            <?php foreach ($list_desa as $d): ?>
                                                <option value="<?php echo $d->id; ?>" <?php selected($edit_data->id_desa ?? '', $d->id); ?>><?php echo esc_html($d->nama_desa); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button class="dw-button dw-button-primary" style="width:100%; justify-content:center;">Simpan Wisata</button>
                                </div>
                            </div>

                            <div class="dw-card">
                                <div class="dw-card-header"><h3 class="card-heading">Harga & Operasional</h3></div>
                                <div class="dw-card-body">
                                    <div class="dw-form-group">
                                        <label class="dw-label">Harga Tiket (Rp)</label>
                                        <input type="number" name="harga_tiket" class="dw-input" value="<?php echo esc_attr($edit_data->harga_tiket ?? 0); ?>">
                                    </div>
                                    <div class="dw-grid-2-col">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Jam Buka</label>
                                            <input type="time" name="jam_buka" class="dw-input" value="<?php echo esc_attr($edit_data->jam_buka ?? ''); ?>">
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Jam Tutup</label>
                                            <input type="time" name="jam_tutup" class="dw-input" value="<?php echo esc_attr($edit_data->jam_tutup ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="dw-card">
                                <div class="dw-card-header"><h3 class="card-heading">Foto Utama</h3></div>
                                <div class="dw-card-body">
                                    <div class="dw-form-group">
                                        <div style="margin-bottom:10px; background:#f9fafb; padding:10px; border-radius:6px; text-align:center;">
                                            <img id="prev_foto_utama" src="<?php echo !empty($edit_data->foto_utama) ? esc_url($edit_data->foto_utama) : 'https://placehold.co/300x150?text=Cover'; ?>" style="max-width:100%; height:auto; border-radius:4px;">
                                        </div>
                                        <input type="hidden" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>">
                                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_utama" data-preview="#prev_foto_utama" style="width:100%;">Upload Cover</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <script>
                jQuery(document).ready(function($){
                    if($.fn.select2) { $('.select2').select2({width:'100%'}); }

                    // Single Image Upload
                    $('.btn_upload').click(function(e){
                        e.preventDefault(); var btn = $(this), target = btn.data('target'), preview = btn.data('preview');
                        var frame = wp.media({title:'Pilih Gambar', multiple:false}).on('select', function(){
                            var url = frame.state().get('selection').first().toJSON().url;
                            $('#'+target).val(url); if(preview) $(preview).attr('src', url);
                        }).open();
                    });

                    // Gallery
                    var gFrame;
                    $('#btn_galeri').click(function(e){
                        e.preventDefault();
                        if (gFrame) { gFrame.open(); return; }
                        gFrame = wp.media({ title: 'Pilih Foto Galeri', multiple: true, library: { type: 'image' } });
                        gFrame.on('select', function(){
                            var selections = gFrame.state().get('selection');
                            var urls = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : [];
                            selections.map(function(att){
                                var u = att.toJSON().url;
                                if (urls.indexOf(u) === -1) {
                                    urls.push(u);
                                    $('#galeri-container').append('<div style="position:relative; width:80px; height:80px;"><img src="'+u+'" style="width:100%; height:100%; object-fit:cover; border-radius:4px;"><span class="rem-g" data-url="'+u+'" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:18px; height:18px; text-align:center; font-size:12px; cursor:pointer; line-height:18px;">x</span></div>');
                                }
                            });
                            $('#galeri_urls').val(urls.join(','));
                        });
                        gFrame.open();
                    });

                    $(document).on('click', '.rem-g', function(){
                        var u = $(this).data('url');
                        var urls = $('#galeri_urls').val().split(',');
                        urls = urls.filter(item => item !== u);
                        $('#galeri_urls').val(urls.join(','));
                        $(this).parent().remove();
                    });
                });
                </script>
            <?php endif; ?>

        </div>
    </div>
<?php
}