<?php
/**
 * File Name:   includes/admin-pages/page-pedagang.php
 * Description: CRUD Pedagang utuh dengan UI Modern, Relasi Otomatis, dan Logika Komisi.
 */

if (!defined('ABSPATH')) exit;

function dw_pedagang_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang'; // Mengasumsikan nama tabel kustom atau sesuaikan dengan struktur Anda
    $message = '';
    $message_type = '';

    // --- LOGIC: SAVE / UPDATE / DELETE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pedagang'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_pedagang_action')) {
            echo '<div class="notice notice-error"><p>Keamanan tidak valid.</p></div>'; return;
        }

        $action = sanitize_text_field($_POST['action_pedagang']);

        // DELETE
        if ($action === 'delete' && !empty($_POST['pedagang_id'])) {
            $deleted = $wpdb->delete($table_name, ['id' => intval($_POST['pedagang_id'])]);
            if ($deleted !== false) {
                $message = 'Data pedagang berhasil dihapus.'; $message_type = 'success';
            } else {
                $message = 'Gagal menghapus pedagang.'; $message_type = 'error';
            }
        
        // SAVE / UPDATE
        } elseif ($action === 'save') {
            $nama_toko = sanitize_text_field($_POST['nama_toko']);
            $id_user = intval($_POST['id_user_pedagang']);
            $kelurahan_id = sanitize_text_field($_POST['pedagang_nama_id']); // ID Kelurahan dari API Wilayah
            
            // LOGIKA RELASI OTOMATIS: Cari Desa Wisata di Kelurahan yang sama
            $desa_terkait = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}dw_desa WHERE api_kelurahan_id = %s LIMIT 1",
                $kelurahan_id
            ));

            $id_desa = $desa_terkait ? $desa_terkait->id : 0;
            $is_independent = ($id_desa == 0) ? 1 : 0;

            // Logika Penentu Verifikator (Siapa yang meng-ACC)
            // Jika ini update dan status berubah jadi aktif, kita catat siapa yang melakukannya
            $status_sekarang = sanitize_text_field($_POST['status_akun']);
            $approved_by = '';
            if ($status_sekarang === 'aktif') {
                $current_user = wp_get_current_user();
                $approved_by = in_array('administrator', (array) $current_user->roles) ? 'admin' : 'desa';
            }

            $data = [
                'id_user_pedagang' => $id_user,
                'id_desa'          => $id_desa, // Relasi Otomatis
                'is_independent'   => $is_independent,
                'nama_toko'        => $nama_toko,
                'deskripsi_toko'   => wp_kses_post($_POST['deskripsi_toko']),
                'foto_toko'        => esc_url_raw($_POST['foto_toko_url']),
                'status_akun'      => $status_sekarang,
                'approved_by'      => $approved_by, // Menyimpan siapa yang meng-ACC untuk sistem komisi
                
                // WILAYAH (API ID)
                'api_provinsi_id'  => sanitize_text_field($_POST['pedagang_prov']),
                'api_kabupaten_id' => sanitize_text_field($_POST['pedagang_kota']),
                'api_kecamatan_id' => sanitize_text_field($_POST['pedagang_kec']),
                'api_kelurahan_id' => $kelurahan_id,
                
                // NAMA WILAYAH (TEXT)
                'provinsi'         => sanitize_text_field($_POST['provinsi_text']),
                'kabupaten'        => sanitize_text_field($_POST['kabupaten_text']),
                'kecamatan'        => sanitize_text_field($_POST['kecamatan_text']),
                'kelurahan'        => sanitize_text_field($_POST['kelurahan_text']),
                
                'alamat_lengkap'   => sanitize_textarea_field($_POST['pedagang_detail']),
                'updated_at'       => current_time('mysql')
            ];

            if (!empty($_POST['pedagang_id'])) {
                $wpdb->update($table_name, $data, ['id' => intval($_POST['pedagang_id'])]);
                $message = 'Data pedagang diperbarui.'; $message_type = 'success';
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table_name, $data);
                $message = 'Pedagang baru berhasil ditambahkan.'; $message_type = 'success';
            }
        }
    }

    // --- PREPARE DATA FOR VIEW ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }
    
    $users = get_users(['role__in' => ['administrator', 'pedagang', 'subscriber']]);

    // Statistik
    $total_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
    $independent_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE is_independent = 1");
    ?>

    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Pedagang & Toko</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-pedagang&action=new" class="page-title-action">Tambah Pedagang</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if (!$is_edit): ?>
            <!-- === DASHBOARD STATS === -->
            <div class="dw-stats-grid">
                <div class="dw-stat-card border-blue">
                    <div class="dw-stat-icon bg-blue"><span class="dashicons dashicons-store"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Total Pedagang</span>
                        <span class="dw-stat-value"><?php echo number_format($total_pedagang); ?></span>
                    </div>
                </div>
                <div class="dw-stat-card border-orange">
                    <div class="dw-stat-icon bg-orange"><span class="dashicons dashicons-admin-users"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Pedagang Independen</span>
                        <span class="dw-stat-value"><?php echo number_format($independent_count); ?></span>
                    </div>
                </div>
                <div class="dw-stat-card border-green">
                    <div class="dw-stat-icon bg-green"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Sistem Komisi</span>
                        <span class="dw-stat-value">Aktif (Otomatis)</span>
                    </div>
                </div>
            </div>

            <div class="dw-info-banner">
                <div class="dw-info-icon"><span class="dashicons dashicons-info-outline"></span></div>
                <div class="dw-info-text">
                    <strong>Ketentuan Relasi & Komisi:</strong>
                    <p style="margin:5px 0;">Sistem akan otomatis menghubungkan pedagang ke Desa Wisata jika Kelurahan sama. Komisi hanya masuk ke kas Desa jika Admin Desa yang menyetujui (ACC) pendaftaran.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <!-- === FORM EDIT/TAMBAH UTUH === -->
            <div class="card dw-form-card">
                <form method="post" id="dw-pedagang-form">
                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                    <input type="hidden" name="action_pedagang" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr><th scope="row" colspan="2"><h3 class="dw-section-title">Informasi Akun & Toko</h3></th></tr>
                        <tr>
                            <th><label>Pemilik Akun</label></th>
                            <td>
                                <select name="id_user_pedagang" class="regular-text" required>
                                    <option value="">-- Pilih User --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user_pedagang : '', $user->ID); ?>>
                                            <?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr><th>Nama Toko / Usaha</th><td><input name="nama_toko" type="text" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>Deskripsi Toko</th><td><?php wp_editor($edit_data->deskripsi_toko ?? '', 'deskripsi_toko', ['textarea_rows'=>4, 'media_buttons'=>false]); ?></td></tr>
                        <tr><th>Foto Toko</th><td>
                            <input type="text" name="foto_toko_url" id="foto_toko_url" value="<?php echo esc_attr($edit_data->foto_toko ?? ''); ?>" class="regular-text">
                            <button type="button" class="button" id="btn_upload_toko">Unggah Foto</button>
                            <img id="preview_toko" src="<?php echo !empty($edit_data->foto_toko) ? esc_url($edit_data->foto_toko) : ''; ?>" style="max-height:80px; display:<?php echo !empty($edit_data->foto_toko) ? 'block' : 'none'; ?>; margin-top:10px; border-radius:4px;">
                        </td></tr>

                        <tr><th scope="row" colspan="2"><h3 class="dw-section-title">Lokasi Usaha (Relasi Wilayah)</h3></th></tr>
                        <tr>
                            <th>Wilayah Administratif</th>
                            <td>
                                <div class="dw-region-grid">
                                    <select name="pedagang_prov" class="dw-region-prov regular-text" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>" disabled><option value="">Provinsi...</option></select>
                                    <select name="pedagang_kota" class="dw-region-kota regular-text" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>" disabled><option value="">Kota/Kab...</option></select>
                                    <select name="pedagang_kec" class="dw-region-kec regular-text" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>" disabled><option value="">Kecamatan...</option></select>
                                    <select name="pedagang_nama_id" class="dw-region-desa regular-text" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>" disabled><option value="">Kelurahan...</option></select>
                                </div>
                                <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>">
                                <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten ?? ''); ?>">
                                <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan ?? ''); ?>">
                                <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan ?? ''); ?>">
                            </td>
                        </tr>
                        <tr><th>Alamat Lengkap</th><td><textarea name="pedagang_detail" class="large-text" rows="2"><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea></td></tr>
                        
                        <tr><th scope="row" colspan="2"><h3 class="dw-section-title">Status & Verifikasi</h3></th></tr>
                        <tr><th>Status Akun</th><td>
                            <select name="status_akun" class="regular-text">
                                <option value="pending" <?php selected($edit_data->status_akun ?? '', 'pending'); ?>>Pending (Butuh Verifikasi)</option>
                                <option value="aktif" <?php selected($edit_data->status_akun ?? '', 'aktif'); ?>>Aktif / Terverifikasi</option>
                                <option value="suspend" <?php selected($edit_data->status_akun ?? '', 'suspend'); ?>>Suspend / Nonaktif</option>
                            </select>
                            <?php if(!empty($edit_data->approved_by)): ?>
                                <p class="description">Diverifikasi oleh: <strong><?php echo ucfirst($edit_data->approved_by); ?></strong></p>
                            <?php endif; ?>
                        </td></tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button button-primary button-large" value="Simpan Data Pedagang">
                        <a href="?page=dw-pedagang" class="button">Batal</a>
                    </p>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($){
                $('#btn_upload_toko').click(function(e){
                    e.preventDefault();
                    var frame = wp.media({ title: 'Pilih Foto Toko', multiple: false, library: { type: 'image' } });
                    frame.on('select', function(){
                        var url = frame.state().get('selection').first().toJSON().url;
                        $('#foto_toko_url').val(url);
                        $('#preview_toko').attr('src', url).show();
                    });
                    frame.open();
                });
            });
            </script>

        <?php else: ?>
            <!-- === TABEL LIST PEDAGANG UTUH === -->
            <?php 
                $per_page = 15;
                $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $offset = ($paged - 1) * $per_page;
                $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

                $where_sql = "WHERE 1=1";
                if (!empty($search)) {
                    $where_sql .= $wpdb->prepare(" AND (nama_toko LIKE %s OR kelurahan LIKE %s)", "%$search%", "%$search%");
                }

                $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_sql");
                $total_pages = ceil($total_items / $per_page);

                $sql = "SELECT p.*, d.nama_desa, u.display_name as owner_name 
                        FROM $table_name p
                        LEFT JOIN {$wpdb->prefix}dw_desa d ON p.id_desa = d.id
                        LEFT JOIN {$wpdb->users} u ON p.id_user_pedagang = u.ID
                        $where_sql ORDER BY p.created_at DESC LIMIT %d OFFSET %d";
                
                $rows = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));
            ?>

            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="dw-pedagang">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Toko atau Wilayah...">
                        <input type="submit" class="button" value="Cari">
                    </p>
                </form>
            </div>

            <div class="dw-card-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="50">Foto</th>
                            <th>Nama Toko / Owner</th>
                            <th>Lokasi / Relasi</th>
                            <th>Status Akun</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r): $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}"; ?>
                        <tr>
                            <td><img src="<?php echo esc_url($r->foto_toko ? $r->foto_toko : 'https://placehold.co/50'); ?>" style="width:40px; height:40px; border-radius:4px; object-fit:cover;"></td>
                            <td>
                                <strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_toko); ?></a></strong><br>
                                <small>Owner: <?php echo esc_html($r->owner_name); ?></small>
                            </td>
                            <td>
                                <small><?php echo esc_html($r->kelurahan); ?></small><br>
                                <?php if($r->id_desa > 0): ?>
                                    <span class="dw-relasi-tag linked"><span class="dashicons dashicons-location-alt"></span> Terhubung: <?php echo esc_html($r->nama_desa); ?></span>
                                <?php else: ?>
                                    <span class="dw-relasi-tag independent">Independen</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="dw-badge-status <?php echo $r->status_akun; ?>"><?php echo ucfirst($r->status_akun); ?></span>
                                <?php if($r->approved_by): ?>
                                    <div class="dw-verifier">By: <?php echo ucfirst($r->approved_by); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus pedagang ini?');">
                                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                                    <input type="hidden" name="action_pedagang" value="delete">
                                    <input type="hidden" name="pedagang_id" value="<?php echo $r->id; ?>">
                                    <button type="submit" class="button button-small" style="color:#d63638;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5">Belum ada data pedagang.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom"><div class="tablenav-pages"><?php echo paginate_links(['total'=>$total_pages, 'current'=>$paged]); ?></div></div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <style>
        .dw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .dw-stat-card { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; border-left: 4px solid #ccd0d4; }
        .dw-stat-icon { width: 40px; height: 40px; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-right: 12px; }
        .dw-stat-icon .dashicons { color: #fff; font-size: 20px; }
        .dw-stat-label { display: block; font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .dw-stat-value { display: block; font-size: 18px; font-weight: 700; color: #1e293b; }
        
        .bg-blue { background: #2271b1; } .bg-green { background: #00a32a; } .bg-orange { background: #dba617; }
        .border-blue { border-left-color: #2271b1; } .border-green { border-left-color: #00a32a; } .border-orange { border-left-color: #dba617; }

        .dw-info-banner { background: #f0f7ff; border-left: 4px solid #2271b1; padding: 12px 15px; margin: 15px 0; border-radius: 4px; display: flex; align-items: flex-start; }
        .dw-info-icon { margin-right: 12px; color: #2271b1; }
        .dw-info-text { font-size: 13px; color: #1e293b; line-height: 1.4; }

        .dw-form-card { padding: 20px; border-radius: 8px; margin-top: 20px; }
        .dw-section-title { margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #eee; font-size: 16px; color: #1d2327; }
        .dw-region-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; max-width: 500px; margin-bottom: 5px; }

        .dw-badge-status { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .dw-badge-status.aktif { background: #e7f9ed; color: #184a2c; }
        .dw-badge-status.pending { background: #fff7ed; color: #7c2d12; }
        .dw-badge-status.suspend { background: #f1f5f9; color: #475569; }

        .dw-relasi-tag { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; margin-top: 4px; padding: 2px 6px; border-radius: 4px; }
        .dw-relasi-tag.linked { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .dw-relasi-tag.independent { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        
        .dw-verifier { font-size: 9px; color: #94a3b8; margin-top: 2px; }
        .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; overflow:hidden; }
    </style>
    <?php
}