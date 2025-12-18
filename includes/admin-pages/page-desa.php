<?php
/**
 * File Name:   includes/admin-pages/page-desa.php
 * Description: CRUD Desa Wisata dengan UI Modern, Tabbed Form, dan Query Statistik yang Akurat.
 */

if (!defined('ABSPATH')) exit;

function dw_desa_page_render() {
    global $wpdb;
    // Definisi nama tabel yang akurat sesuai skema database activation.php
    $table_desa     = $wpdb->prefix . 'dw_desa';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_wisata   = $wpdb->prefix . 'dw_wisata'; 
    $table_users    = $wpdb->users;
    
    $message = '';
    $message_type = '';

    // --- LOGIC: SAVE / UPDATE / DELETE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_desa'])) {
        
        // Validasi Nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_action')) {
            echo '<div class="notice notice-error"><p>Keamanan tidak valid (Nonce Failed).</p></div>'; return;
        }

        $action = sanitize_text_field($_POST['action_desa']);

        // DELETE
        if ($action === 'delete' && !empty($_POST['desa_id'])) {
            $desa_id_to_delete = intval($_POST['desa_id']);

            // Update pedagang terkait menjadi independen
            $wpdb->update(
                $table_pedagang,
                ['id_desa' => 0, 'is_independent' => 1],
                ['id_desa' => $desa_id_to_delete]
            );

            // Hapus data desa
            $deleted = $wpdb->delete($table_desa, ['id' => $desa_id_to_delete]);
            
            if ($deleted !== false) {
                $message = 'Data desa berhasil dihapus.'; $message_type = 'success';
            } else {
                $message = 'Gagal menghapus desa: ' . $wpdb->last_error; $message_type = 'error';
            }
        
        // SAVE / UPDATE
        } elseif ($action === 'save') {
            $nama_desa = sanitize_text_field($_POST['nama_desa']);
            $slug      = sanitize_title($nama_desa);
            
            $data = [
                'id_user_desa'                => intval($_POST['id_user_desa']),
                'nama_desa'                   => $nama_desa,
                'slug_desa'                   => $slug,
                'deskripsi'                   => wp_kses_post($_POST['deskripsi']),
                'foto'                        => esc_url_raw($_POST['foto_desa_url']),
                'status'                      => sanitize_text_field($_POST['status']),
                'persentase_komisi_penjualan' => isset($_POST['persentase_komisi']) ? floatval($_POST['persentase_komisi']) : 0,
                
                // DATA BANK
                'nama_bank_desa'              => sanitize_text_field($_POST['nama_bank_desa']),
                'no_rekening_desa'            => sanitize_text_field($_POST['no_rekening_desa']),
                'atas_nama_rekening_desa'     => sanitize_text_field($_POST['atas_nama_rekening_desa']),
                'qris_image_url_desa'         => esc_url_raw($_POST['qris_image_url_desa']),
                
                // WILAYAH API (ID)
                'api_provinsi_id'             => sanitize_text_field($_POST['desa_prov']),
                'api_kabupaten_id'            => sanitize_text_field($_POST['desa_kota']),
                'api_kecamatan_id'            => sanitize_text_field($_POST['desa_kec']),
                'api_kelurahan_id'            => sanitize_text_field($_POST['desa_nama_id']),
                
                // WILAYAH TEKS (Nama)
                'provinsi'                    => sanitize_text_field($_POST['provinsi_text']), 
                'kabupaten'                   => sanitize_text_field($_POST['kabupaten_text']),
                'kecamatan'                   => sanitize_text_field($_POST['kecamatan_text']),
                'kelurahan'                   => sanitize_text_field($_POST['kelurahan_text']),
                'alamat_lengkap'              => sanitize_textarea_field($_POST['desa_detail']),
                
                'updated_at'                  => current_time('mysql')
            ];

            if (!empty($_POST['desa_id'])) {
                // Update
                $result = $wpdb->update($table_desa, $data, ['id' => intval($_POST['desa_id'])]);
                if ($result !== false) {
                    $message = 'Data desa berhasil diperbarui.'; $message_type = 'success';
                } else {
                    $message = 'Gagal update database: ' . $wpdb->last_error; $message_type = 'error';
                }
            } else {
                // Insert Baru
                $data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table_desa, $data);
                if ($result !== false) {
                    // Trigger sinkronisasi otomatis pedagang
                    if(function_exists('dw_sync_independent_merchants_to_desa')) {
                        $desa_new_id = $wpdb->insert_id;
                        dw_sync_independent_merchants_to_desa($desa_new_id, $data['api_kelurahan_id']);
                    }
                    $message = 'Desa baru berhasil ditambahkan.'; $message_type = 'success';
                } else {
                    $message = 'Gagal menyimpan database: ' . $wpdb->last_error; $message_type = 'error';
                }
            }
        }
    }

    // --- PREPARE DATA ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id = %d", intval($_GET['id'])));
    }
    
    // Ambil list user untuk dropdown admin desa
    $users = get_users(['role__in' => ['administrator', 'admin_desa', 'editor']]);

    // Statistik Dashboard (Simple Counts)
    $total_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
    // Gunakan IFNULL agar tidak error jika tabel belum ada isinya
    $total_wisata_global = $wpdb->get_var("SELECT COUNT(id) FROM $table_wisata");
    $total_pedagang_terhubung = $wpdb->get_var("SELECT COUNT(id) FROM $table_pedagang WHERE id_desa > 0");
    ?>

    <div class="wrap dw-admin-wrapper">
        <div class="dw-header-bar">
            <h1 class="wp-heading-inline">
                <span class="dw-icon-box"><span class="dashicons dashicons-admin-home"></span></span> 
                Manajemen Desa Wisata
            </h1>
            <?php if(!$is_edit): ?>
                <a href="?page=dw-desa&action=new" class="dw-btn dw-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> Tambah Desa
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="dw-notice dw-notice-<?php echo $message_type; ?>">
                <div class="notice-icon"><span class="dashicons dashicons-<?php echo $message_type == 'success' ? 'yes' : 'warning'; ?>"></span></div>
                <div class="notice-content"><p><?php echo $message; ?></p></div>
                <button class="dw-notice-dismiss">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (!$is_edit): ?>
            <!-- STATS CARDS -->
            <div class="dw-stats-grid">
                <div class="dw-stat-card">
                    <div class="stat-content">
                        <span class="stat-label">Total Desa</span>
                        <h2 class="stat-value"><?php echo number_format($total_desa); ?></h2>
                        <span class="stat-desc text-blue">Terdaftar</span>
                    </div>
                    <div class="stat-icon bg-blue-light text-blue"><span class="dashicons dashicons-building"></span></div>
                </div>
                <div class="dw-stat-card">
                    <div class="stat-content">
                        <span class="stat-label">Total Wisata</span>
                        <h2 class="stat-value"><?php echo number_format($total_wisata_global); ?></h2>
                        <span class="stat-desc text-green">Destinasi</span>
                    </div>
                    <div class="stat-icon bg-green-light text-green"><span class="dashicons dashicons-palmtree"></span></div>
                </div>
                <div class="dw-stat-card">
                    <div class="stat-content">
                        <span class="stat-label">Mitra Pedagang</span>
                        <h2 class="stat-value"><?php echo number_format($total_pedagang_terhubung); ?></h2>
                        <span class="stat-desc text-orange">Terhubung</span>
                    </div>
                    <div class="stat-icon bg-orange-light text-orange"><span class="dashicons dashicons-store"></span></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <!-- FORM EDIT/TAMBAH -->
            <div class="dw-form-container">
                <form method="post" id="dw-desa-form">
                    <?php wp_nonce_field('dw_desa_action'); ?>
                    <input type="hidden" name="action_desa" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="desa_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <div class="dw-form-layout">
                        <!-- SIDEBAR NAV -->
                        <div class="dw-form-sidebar">
                            <div class="dw-nav-title">Navigasi Form</div>
                            <ul class="dw-tabs-nav">
                                <li class="active" data-tab="tab-info"><span class="dashicons dashicons-info"></span> Informasi Desa</li>
                                <li data-tab="tab-lokasi"><span class="dashicons dashicons-location"></span> Lokasi & Wilayah</li>
                                <li data-tab="tab-keuangan"><span class="dashicons dashicons-money-alt"></span> Keuangan</li>
                            </ul>
                        </div>

                        <!-- CONTENT AREA -->
                        <div class="dw-form-content">
                            
                            <!-- TAB 1: INFO -->
                            <div id="tab-info" class="dw-tab-pane active">
                                <div class="dw-card">
                                    <div class="dw-card-header">
                                        <h3>Informasi Dasar</h3>
                                        <p>Data utama profil desa wisata.</p>
                                    </div>
                                    <div class="dw-card-body">
                                        <div class="dw-form-group">
                                            <label>Akun Pengelola (Admin Desa) <span class="required">*</span></label>
                                            <select name="id_user_desa" class="dw-form-control select2" required>
                                                <option value="">-- Pilih User WordPress --</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user_desa : '', $user->ID); ?>>
                                                        <?php echo $user->display_name; ?> (<?php echo $user->user_login; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">User ini akan memiliki akses penuh mengelola desa ini.</p>
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Nama Desa Wisata <span class="required">*</span></label>
                                            <input name="nama_desa" type="text" value="<?php echo esc_attr($edit_data->nama_desa ?? ''); ?>" class="dw-form-control" required placeholder="Contoh: Desa Wisata Panglipuran">
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Deskripsi Singkat</label>
                                            <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>6, 'media_buttons'=>false, 'teeny'=>true]); ?>
                                        </div>
                                        
                                        <div class="dw-form-group">
                                            <label>Status Publikasi</label>
                                            <select name="status" class="dw-form-control" style="max-width:200px;">
                                                <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                                <option value="pending" <?php selected($edit_data->status ?? '', 'pending'); ?>>Pending</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Foto Sampul</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-form-group">
                                            <div class="dw-image-upload-box wide">
                                                <div class="preview-area">
                                                    <img id="preview_foto_desa" src="<?php echo !empty($edit_data->foto) ? esc_url($edit_data->foto) : 'https://placehold.co/600x300/f0f0f1/a7aaad?text=Foto+Sampul+Desa'; ?>">
                                                </div>
                                                <input type="hidden" name="foto_desa_url" id="foto_desa_url" value="<?php echo esc_attr($edit_data->foto ?? ''); ?>">
                                                <button type="button" class="dw-btn-upload btn_upload" data-target="#foto_desa_url" data-preview="#preview_foto_desa">
                                                    <span class="dashicons dashicons-camera"></span> Ganti Foto
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 2: LOKASI -->
                            <div id="tab-lokasi" class="dw-tab-pane">
                                <div class="dw-card">
                                    <div class="dw-card-header">
                                        <h3>Wilayah Administratif</h3>
                                        <p>Digunakan untuk relasi otomatis dengan pedagang di wilayah yang sama.</p>
                                    </div>
                                    <div class="dw-card-body">
                                        <div class="dw-grid-2">
                                            <div class="dw-form-group">
                                                <label>Provinsi</label>
                                                <select name="desa_prov" class="dw-region-prov dw-form-control" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>"><option value="">Memuat...</option></select>
                                            </div>
                                            <div class="dw-form-group">
                                                <label>Kota/Kabupaten</label>
                                                <select name="desa_kota" class="dw-region-kota dw-form-control" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>" disabled><option value="">Pilih Kota...</option></select>
                                            </div>
                                            <div class="dw-form-group">
                                                <label>Kecamatan</label>
                                                <select name="desa_kec" class="dw-region-kec dw-form-control" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>" disabled><option value="">Pilih Kecamatan...</option></select>
                                            </div>
                                            <div class="dw-form-group">
                                                <label>Kelurahan / Desa <span class="required">*</span></label>
                                                <select name="desa_nama_id" class="dw-region-desa dw-form-control" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>" disabled><option value="">Pilih Kelurahan...</option></select>
                                                <small class="text-blue">Penting: Pedagang di kelurahan ini otomatis terhubung.</small>
                                            </div>
                                        </div>
                                        
                                        <!-- Hidden Inputs untuk menyimpan Nama Wilayah -->
                                        <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>">
                                        <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten ?? ''); ?>">
                                        <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan ?? ''); ?>">
                                        <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan ?? ''); ?>">

                                        <hr class="dw-divider">

                                        <div class="dw-form-group">
                                            <label>Alamat Lengkap Kantor Desa</label>
                                            <textarea name="desa_detail" class="dw-form-control" rows="3" placeholder="Jalan, RT/RW..."><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 3: KEUANGAN -->
                            <div id="tab-keuangan" class="dw-tab-pane">
                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Pengaturan Komisi & Bank</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-form-group">
                                            <label>Persentase Komisi (%)</label>
                                            <input type="number" name="persentase_komisi" step="0.1" min="0" max="100" value="<?php echo esc_attr($edit_data->persentase_komisi_penjualan ?? '0'); ?>" class="dw-form-control" style="width:120px; display:inline-block;"> %
                                            <p class="description">Komisi yang diterima desa dari setiap transaksi pedagang yang terverifikasi.</p>
                                        </div>
                                        
                                        <hr class="dw-divider">
                                        
                                        <div class="dw-row">
                                            <div class="dw-col-4">
                                                <div class="dw-form-group">
                                                    <label>Nama Bank</label>
                                                    <input name="nama_bank_desa" type="text" value="<?php echo esc_attr($edit_data->nama_bank_desa ?? ''); ?>" class="dw-form-control" placeholder="BCA/BRI">
                                                </div>
                                            </div>
                                            <div class="dw-col-8">
                                                <div class="dw-form-group">
                                                    <label>Nomor Rekening</label>
                                                    <input name="no_rekening_desa" type="text" value="<?php echo esc_attr($edit_data->no_rekening_desa ?? ''); ?>" class="dw-form-control">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Atas Nama Rekening</label>
                                            <input name="atas_nama_rekening_desa" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening_desa ?? ''); ?>" class="dw-form-control">
                                        </div>
                                        
                                        <div class="dw-form-group">
                                            <label>QRIS (Opsional)</label>
                                            <div class="dw-file-input-wrapper">
                                                <div class="preview-mini">
                                                    <img id="preview_qris" src="<?php echo !empty($edit_data->qris_image_url_desa) ? esc_url($edit_data->qris_image_url_desa) : 'https://placehold.co/50?text=QR'; ?>">
                                                </div>
                                                <input type="text" name="qris_image_url_desa" id="qris_image_url_desa" value="<?php echo esc_attr($edit_data->qris_image_url_desa ?? ''); ?>" class="dw-form-control">
                                                <button type="button" class="button btn_upload" data-target="#qris_image_url_desa" data-preview="#preview_qris">Upload QRIS</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div> <!-- End Content -->
                    </div> <!-- End Layout -->

                    <div class="dw-form-footer">
                        <a href="?page=dw-desa" class="dw-btn dw-btn-text text-red">Batal</a>
                        <button type="submit" class="dw-btn dw-btn-primary">
                            <span class="dashicons dashicons-saved"></span> Simpan Data Desa
                        </button>
                    </div>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($){
                // Tab Navigation
                $('.dw-tabs-nav li').click(function(){
                    var tab_id = $(this).attr('data-tab');
                    $('.dw-tabs-nav li').removeClass('active');
                    $('.dw-tab-pane').removeClass('active');
                    $(this).addClass('active');
                    $("#"+tab_id).addClass('active');
                });

                // Media Upload
                $('.btn_upload').click(function(e){
                    e.preventDefault();
                    var target = $(this).data('target');
                    var preview = $(this).data('preview');
                    var frame = wp.media({ title: 'Pilih Gambar', multiple: false, library: { type: 'image' } });
                    frame.on('select', function(){
                        var url = frame.state().get('selection').first().toJSON().url;
                        $(target).val(url);
                        if(preview) $(preview).attr('src', url);
                    });
                    frame.open();
                });
            });
            </script>

        <?php else: ?>
            <!-- TABEL LIST DESA -->
            <?php 
                $per_page = 10;
                $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $offset = ($paged - 1) * $per_page;
                $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

                // QUERY CONSTRUCTION (FIXED)
                // Menggunakan alias 'd' untuk tabel dw_desa agar tidak ambigu
                $where_sql = "WHERE 1=1";
                if (!empty($search)) {
                    $where_sql .= $wpdb->prepare(" AND (d.nama_desa LIKE %s OR d.kabupaten LIKE %s)", "%$search%", "%$search%");
                }

                // SUB-QUERY FIX: Menghitung Wisata dan Pedagang dengan tepat
                // Menggunakan IFNULL untuk memastikan jika tabel lain kosong tetap return 0
                $sql = "SELECT d.*, 
                        (SELECT COUNT(w.id) FROM $table_wisata w WHERE w.id_desa = d.id) as count_wisata,
                        (SELECT COUNT(p.id) FROM $table_pedagang p WHERE p.id_desa = d.id) as count_pedagang,
                        u.display_name as admin_name
                        FROM $table_desa d
                        LEFT JOIN $table_users u ON d.id_user_desa = u.ID
                        $where_sql 
                        ORDER BY d.created_at DESC 
                        LIMIT $per_page OFFSET $offset";
                
                $rows = $wpdb->get_results($sql);
                $total_items = $wpdb->get_var("SELECT COUNT(d.id) FROM $table_desa d $where_sql");
                $total_pages = ceil($total_items / $per_page);
            ?>

            <div class="dw-toolbar">
                <form method="get" class="dw-search-box">
                    <input type="hidden" name="page" value="dw-desa">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Desa / Wilayah..." class="search-input">
                    <button type="submit" class="dw-btn dw-btn-light"><span class="dashicons dashicons-search"></span></button>
                </form>
            </div>

            <div class="dw-table-container">
                <table class="wp-list-table widefat fixed striped dw-modern-table">
                    <thead>
                        <tr>
                            <th width="70">Foto</th>
                            <th>Nama Desa & Pengelola</th>
                            <th>Wilayah Administratif</th>
                            <th width="140">Aset & Mitra</th>
                            <th width="100">Status</th>
                            <th width="120" style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r): $edit_url = "?page=dw-desa&action=edit&id={$r->id}"; ?>
                        <tr>
                            <td>
                                <div class="table-thumb">
                                    <img src="<?php echo esc_url($r->foto ? $r->foto : 'https://placehold.co/100x100/f0f0f1/ccc?text=IMG'); ?>">
                                </div>
                            </td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="row-title"><?php echo esc_html($r->nama_desa); ?></a>
                                <div class="row-meta">
                                    <span class="meta-item"><span class="dashicons dashicons-admin-users"></span> <?php echo esc_html($r->admin_name ? $r->admin_name : 'Belum ada admin'); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="location-text">
                                    <?php echo esc_html($r->kelurahan ? $r->kelurahan : '-'); ?>,
                                    <span class="sub-loc"><?php echo esc_html($r->kecamatan); ?></span>
                                    <span class="sub-loc text-muted"><?php echo esc_html($r->kabupaten); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="dw-stats-badges">
                                    <span class="stat-badge green" title="Total Objek Wisata">
                                        <span class="dashicons dashicons-palmtree"></span> <?php echo $r->count_wisata; ?>
                                    </span>
                                    <span class="stat-badge orange" title="Total Pedagang Mitra">
                                        <span class="dashicons dashicons-store"></span> <?php echo $r->count_pedagang; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php $status_class = ($r->status === 'aktif') ? 'active' : 'warning'; ?>
                                <span class="dw-status-pill <?php echo $status_class; ?>"><?php echo ucfirst($r->status); ?></span>
                            </td>
                            <td style="text-align:right;">
                                <div class="action-buttons">
                                    <a href="<?php echo $edit_url; ?>" class="btn-icon edit" title="Edit"><span class="dashicons dashicons-edit"></span></a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus desa ini? Data pedagang akan terlepas relasinya.');">
                                        <?php wp_nonce_field('dw_desa_action'); ?>
                                        <input type="hidden" name="action_desa" value="delete">
                                        <input type="hidden" name="desa_id" value="<?php echo $r->id; ?>">
                                        <button type="submit" class="btn-icon delete" title="Hapus"><span class="dashicons dashicons-trash"></span></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6" class="empty-state">Belum ada data desa wisata yang terdaftar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="dw-pagination">
                    <?php echo paginate_links(['total' => $total_pages, 'current' => $paged]); ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- STYLE UI/UX MODERN (Sama dengan Page Pedagang) -->
    <style>
        :root {
            --dw-primary: #2271b1; --dw-primary-hover: #135e96;
            --dw-bg: #f0f2f5; --dw-white: #ffffff;
            --dw-text: #3c434a; --dw-text-light: #646970;
            --dw-border: #dcdcde;
            --dw-success: #00a32a; --dw-warning: #dba617; --dw-danger: #d63638;
        }
        .dw-admin-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px 20px 0 0; }
        .dw-admin-wrapper * { box-sizing: border-box; }
        
        /* Header & Stats */
        .dw-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .dw-header-bar h1 { font-size: 24px; font-weight: 600; display: flex; align-items: center; gap: 10px; margin: 0; }
        .dw-icon-box { background: var(--dw-primary); color: #fff; padding: 5px; border-radius: 6px; display: flex; }
        
        .dw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .dw-stat-card { background: var(--dw-white); padding: 20px; border-radius: 8px; border: 1px solid var(--dw-border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; }
        .stat-label { font-size: 13px; color: var(--dw-text-light); display: block; margin-bottom: 5px; }
        .stat-value { font-size: 28px; margin: 0; line-height: 1; font-weight: 700; color: var(--dw-text); }
        .stat-desc { font-size: 11px; font-weight: 600; text-transform: uppercase; margin-top: 5px; display: block; }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        
        /* Colors */
        .text-blue { color: var(--dw-primary); } .bg-blue-light { background: #eef6fc; }
        .text-green { color: var(--dw-success); } .bg-green-light { background: #edfaef; }
        .text-orange { color: var(--dw-warning); } .bg-orange-light { background: #fcf6e6; }
        
        /* Form & Tabs */
        .dw-form-layout { display: flex; gap: 20px; align-items: flex-start; }
        .dw-form-sidebar { width: 240px; flex-shrink: 0; position: sticky; top: 40px; }
        .dw-form-content { flex: 1; min-width: 0; }
        .dw-nav-title { font-size: 12px; text-transform: uppercase; color: var(--dw-text-light); font-weight: 700; margin-bottom: 10px; padding-left: 10px; }
        .dw-tabs-nav { list-style: none; margin: 0; padding: 0; background: var(--dw-white); border-radius: 8px; border: 1px solid var(--dw-border); overflow: hidden; }
        .dw-tabs-nav li { padding: 12px 15px; border-bottom: 1px solid #f0f0f1; cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: var(--dw-text); transition: all 0.2s; }
        .dw-tabs-nav li:last-child { border-bottom: none; }
        .dw-tabs-nav li:hover { background: #f8f9fa; color: var(--dw-primary); }
        .dw-tabs-nav li.active { background: #eef6fc; color: var(--dw-primary); font-weight: 600; border-left: 3px solid var(--dw-primary); padding-left: 12px; }
        .dw-tab-pane { display: none; animation: fadeIn 0.3s ease; }
        .dw-tab-pane.active { display: block; }

        /* Form Elements */
        .dw-card { background: var(--dw-white); border: 1px solid var(--dw-border); border-radius: 8px; margin-bottom: 20px; }
        .dw-card-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f1; }
        .dw-card-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
        .dw-card-header p { margin: 2px 0 0; font-size: 12px; color: var(--dw-text-light); }
        .dw-card-body { padding: 20px; }
        .dw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .dw-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .dw-col-4 { width: 33.33%; } .dw-col-8 { width: 66.66%; }
        .dw-form-group { margin-bottom: 15px; }
        .dw-form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: var(--dw-text); }
        .dw-form-control { width: 100%; padding: 0 12px; height: 40px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; background: #fff; }
        textarea.dw-form-control { height: auto; padding: 10px; }
        
        /* Media Uploader Box */
        .dw-image-upload-box { position: relative; width: 150px; height: 150px; border-radius: 8px; overflow: hidden; border: 1px solid var(--dw-border); background: #f6f7f7; }
        .dw-image-upload-box.wide { width: 100%; height: 200px; }
        .preview-area img { width: 100%; height: 100%; object-fit: cover; }
        .dw-btn-upload { position: absolute; bottom: 10px; right: 10px; background: rgba(255,255,255,0.9); border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 5px; opacity: 0.8; transition: opacity 0.2s; }
        .dw-image-upload-box:hover .dw-btn-upload { opacity: 1; }
        .dw-file-input-wrapper { display: flex; gap: 10px; align-items: center; }
        .preview-mini img { width: 40px; height: 40px; border-radius: 4px; object-fit: cover; border: 1px solid #ddd; }

        /* Footer */
        .dw-form-footer { background: var(--dw-white); padding: 15px 25px; border-top: 1px solid var(--dw-border); position: fixed; bottom: 0; right: 0; left: 160px; z-index: 99; display: flex; justify-content: flex-end; align-items: center; gap: 15px; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }
        
        /* Table */
        .dw-table-toolbar { display: flex; justify-content: flex-end; margin-bottom: 15px; }
        .dw-search-box { display: flex; gap: 10px; }
        .search-input { width: 250px; padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px; }
        .dw-table-container { background: var(--dw-white); border-radius: 8px; border: 1px solid var(--dw-border); box-shadow: 0 1px 2px rgba(0,0,0,0.02); overflow: hidden; }
        .dw-modern-table thead th { background: #f8f9fa; color: var(--dw-text-light); font-weight: 600; text-transform: uppercase; font-size: 12px; border-bottom: 1px solid var(--dw-border); padding: 15px; }
        .dw-modern-table tbody td { padding: 15px; vertical-align: middle; color: var(--dw-text); border-bottom: 1px solid #f0f0f1; }
        .dw-modern-table tbody tr:hover { background: #fcfcfc; }
        .table-thumb img { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; border: 1px solid #eee; }
        .row-title { font-weight: 600; font-size: 14px; color: var(--dw-primary); text-decoration: none; }
        .row-meta { font-size: 12px; color: var(--dw-text-light); margin-top: 4px; }
        .location-text { font-size: 13px; line-height: 1.4; }
        .sub-loc { display: block; font-size: 11px; color: #8c8f94; }
        
        .dw-stats-badges { display: flex; gap: 8px; }
        .stat-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #f0f0f1; border: 1px solid #dcdcde; }
        .stat-badge.green { background: #edfaef; color: #00a32a; border-color: #c3e6cb; }
        .stat-badge.orange { background: #fff8e5; color: #996800; border-color: #faeac6; }
        
        .action-buttons { display: flex; gap: 5px; justify-content: flex-end; }
        .btn-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid var(--dw-border); background: #fff; color: var(--dw-text-light); transition: all 0.2s; cursor: pointer; }
        .btn-icon:hover { border-color: var(--dw-primary); color: var(--dw-primary); }
        .btn-icon.delete:hover { border-color: var(--dw-danger); color: var(--dw-danger); }
        
        .dw-btn { padding: 8px 16px; border-radius: 4px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; transition: all 0.2s; }
        .dw-btn-primary { background: var(--dw-primary); color: #fff; }
        .dw-btn-primary:hover { background: var(--dw-primary-hover); }
        .dw-btn-light { background: #fff; border: 1px solid #8c8f94; }
        .dw-btn-text { background: transparent; color: var(--dw-text-light); }
        .text-red { color: var(--dw-danger); }
        
        .dw-pagination { margin-top: 20px; text-align: right; }
        .dw-pagination .page-numbers { display: inline-block; padding: 6px 12px; margin-left: 4px; border: 1px solid #c3c4c7; background: #fff; color: #2271b1; text-decoration: none; border-radius: 4px; font-size: 13px; }
        .dw-pagination .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; font-weight: 600; }
        
        .dw-notice { padding: 10px 15px; border-left: 4px solid; background: #fff; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .dw-notice-success { border-color: #46b450; } .dw-notice-error { border-color: #d63638; }
        .dw-notice-dismiss { background: none; border: none; font-size: 20px; cursor: pointer; color: #646970; }

        @media(max-width: 960px) { 
            .dw-form-layout { flex-direction: column; } 
            .dw-form-sidebar { width: 100%; position: static; margin-bottom: 20px; }
            .dw-tabs-nav { display: flex; overflow-x: auto; white-space: nowrap; }
            .dw-grid-2 { grid-template-columns: 1fr; }
            .dw-form-footer { left: 0; }
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <script>
    jQuery(document).ready(function($){
        $('.dw-notice-dismiss').on('click', function(){ $(this).parent().fadeOut(); });
    });
    </script>
    <?php
}