<?php
/**
 * File Name:   includes/admin-pages/page-desa.php
 * Description: CRUD Desa dengan UI Modern, Integrasi Wilayah, dan Statistik Relasi.
 */

if (!defined('ABSPATH')) exit;

function dw_desa_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_desa';
    $message = '';
    $message_type = '';

    // --- LOGIC: SAVE / UPDATE / DELETE (Original Logic Preserved) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_desa'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_action')) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>'; return;
        }

        $action = sanitize_text_field($_POST['action_desa']);

        if ($action === 'delete' && !empty($_POST['desa_id'])) {
            $deleted = $wpdb->delete($table_name, ['id' => intval($_POST['desa_id'])]);
            if ($deleted !== false) {
                $message = 'Data desa berhasil dihapus.'; $message_type = 'success';
            } else {
                $message = 'Gagal menghapus desa: ' . $wpdb->last_error; $message_type = 'error';
            }
        } elseif ($action === 'save') {
            $nama_desa = sanitize_text_field($_POST['nama_desa']);
            $slug = sanitize_title($nama_desa);
            
            $data = [
                'id_user_desa' => intval($_POST['id_user_desa']),
                'nama_desa'    => $nama_desa,
                'slug_desa'    => $slug,
                'deskripsi'    => wp_kses_post($_POST['deskripsi']),
                'foto'         => esc_url_raw($_POST['foto_desa_url']),
                'status'       => sanitize_text_field($_POST['status']),
                'persentase_komisi_penjualan' => isset($_POST['persentase_komisi']) ? floatval($_POST['persentase_komisi']) : 0,
                'nama_bank_desa'              => sanitize_text_field($_POST['nama_bank_desa']),
                'no_rekening_desa'            => sanitize_text_field($_POST['no_rekening_desa']),
                'atas_nama_rekening_desa'     => sanitize_text_field($_POST['atas_nama_rekening_desa']),
                'qris_image_url_desa'         => esc_url_raw($_POST['qris_image_url_desa']),
                'api_provinsi_id'  => sanitize_text_field($_POST['desa_prov']),
                'api_kabupaten_id' => sanitize_text_field($_POST['desa_kota']),
                'api_kecamatan_id' => sanitize_text_field($_POST['desa_kec']),
                'api_kelurahan_id' => sanitize_text_field($_POST['desa_nama_id']),
                'provinsi'  => sanitize_text_field($_POST['provinsi_text']), 
                'kabupaten' => sanitize_text_field($_POST['kabupaten_text']),
                'kecamatan' => sanitize_text_field($_POST['kecamatan_text']),
                'kelurahan' => sanitize_text_field($_POST['kelurahan_text']),
                'alamat_lengkap' => sanitize_textarea_field($_POST['desa_detail']),
                'updated_at'   => current_time('mysql')
            ];

            if (!empty($_POST['desa_id'])) {
                $wpdb->update($table_name, $data, ['id' => intval($_POST['desa_id'])]);
                $message = 'Data desa diperbarui.'; $message_type = 'success';
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table_name, $data);
                $desa_new_id = $wpdb->insert_id;
                // Trigger sinkronisasi pedagang independen setelah desa baru disimpan
                if(function_exists('dw_sync_independent_merchants_to_desa')) {
                    dw_sync_independent_merchants_to_desa($desa_new_id, $data['api_kelurahan_id']);
                }
                $message = 'Desa baru ditambahkan.'; $message_type = 'success';
            }
        }
    }

    // --- PREPARE DATA ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }
    $users = get_users(['role__in' => ['administrator', 'admin_desa', 'editor']]);

    // Statistik untuk Dashboard Utama
    $total_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
    $total_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_pedagang");
    ?>

    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-desa&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if (!$is_edit): ?>
            <!-- === STATS CARDS (NEW UI) === -->
            <div class="dw-stats-grid">
                <div class="dw-stat-card border-blue">
                    <div class="dw-stat-icon bg-blue"><span class="dashicons dashicons-admin-site-alt3"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Total Desa</span>
                        <span class="dw-stat-value"><?php echo number_format($total_desa); ?></span>
                    </div>
                </div>
                <div class="dw-stat-card border-green">
                    <div class="dw-stat-icon bg-green"><span class="dashicons dashicons-groups"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Total Pedagang</span>
                        <span class="dw-stat-value"><?php echo number_format($total_pedagang); ?></span>
                    </div>
                </div>
                <div class="dw-stat-card border-orange">
                    <div class="dw-stat-icon bg-orange"><span class="dashicons dashicons-awards"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Sistem Relasi</span>
                        <span class="dw-stat-value">Otomatis Wilayah</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <!-- === FORM EDIT/TAMBAH (Original Form Intact) === -->
            <div class="card dw-form-card">
                <form method="post" id="dw-desa-form">
                    <?php wp_nonce_field('dw_desa_action'); ?>
                    <input type="hidden" name="action_desa" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="desa_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr><th scope="row" colspan="2"><h3 class="dw-section-title">Informasi Dasar</h3></th></tr>
                        <tr>
                            <th><label>Akun Pengelola</label></th>
                            <td>
                                <select name="id_user_desa" class="regular-text" required>
                                    <option value="">-- Pilih User --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user_desa : '', $user->ID); ?>>
                                            <?php echo $user->display_name; ?> (<?php echo $user->user_login; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr><th>Nama Desa</th><td><input name="nama_desa" type="text" value="<?php echo esc_attr($edit_data->nama_desa ?? ''); ?>" class="regular-text" required placeholder="Contoh: Desa Wisata Panglipuran"></td></tr>
                        <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>5, 'media_buttons'=>false]); ?></td></tr>
                        <tr><th>Foto Sampul</th><td>
                            <input type="text" name="foto_desa_url" id="foto_desa_url" value="<?php echo esc_attr($edit_data->foto ?? ''); ?>" class="regular-text">
                            <button type="button" class="button" id="btn_upload_foto">Pilih Gambar</button>
                            <div class="dw-preview-container">
                                <img id="preview_foto_desa" src="<?php echo !empty($edit_data->foto) ? esc_url($edit_data->foto) : ''; ?>" style="max-height:100px; <?php echo empty($edit_data->foto) ? 'display:none;' : ''; ?> margin-top:10px; border-radius:4px; border:1px solid #ddd;">
                            </div>
                        </td></tr>

                        <tr><th scope="row" colspan="2"><h3 class="dw-section-title">Lokasi Administratif</h3></th></tr>
                        <tr>
                            <th>Wilayah</th>
                            <td>
                                <div class="dw-region-grid">
                                    <select name="desa_prov" class="dw-region-prov regular-text" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>" disabled><option value="">Memuat Provinsi...</option></select>
                                    <select name="desa_kota" class="dw-region-kota regular-text" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>" disabled><option value="">Pilih Kota...</option></select>
                                    <select name="desa_kec" class="dw-region-kec regular-text" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>" disabled><option value="">Pilih Kecamatan...</option></select>
                                    <select name="desa_nama_id" class="dw-region-desa regular-text" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>" disabled><option value="">Pilih Kelurahan...</option></select>
                                </div>
                                <!-- Hidden inputs for text values -->
                                <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>">
                                <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten ?? ''); ?>">
                                <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan ?? ''); ?>">
                                <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan ?? ''); ?>">
                                <p class="description">Pedagang di kelurahan yang sama akan terhubung otomatis.</p>
                            </td>
                        </tr>
                        <tr><th>Alamat Detail</th><td><textarea name="desa_detail" class="large-text" rows="2" placeholder="Jalan, No Rumah, RT/RW"><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea></td></tr>

                        <tr><th scope="row" colspan="2"><h3 class="dw-section-title">Keuangan & Komisi</h3></th></tr>
                        <tr><th>Komisi Desa (%)</th><td>
                            <input type="number" name="persentase_komisi" step="0.1" value="<?php echo esc_attr($edit_data->persentase_komisi_penjualan ?? '0'); ?>" class="small-text"> %
                            <p class="description">Hanya berlaku jika Desa yang meng-ACC pendaftaran pedagang.</p>
                        </td></tr>
                        <tr><th>Data Bank</th><td>
                            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                                <input name="nama_bank_desa" type="text" value="<?php echo esc_attr($edit_data->nama_bank_desa ?? ''); ?>" placeholder="Nama Bank" class="widefat">
                                <input name="no_rekening_desa" type="text" value="<?php echo esc_attr($edit_data->no_rekening_desa ?? ''); ?>" placeholder="No. Rekening" class="widefat">
                            </div>
                            <input name="atas_nama_rekening_desa" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening_desa ?? ''); ?>" placeholder="Atas Nama" class="widefat" style="margin-top:5px;">
                        </td></tr>
                        <tr><th>Status Aktif</th><td>
                            <select name="status">
                                <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                <option value="pending" <?php selected($edit_data->status ?? '', 'pending'); ?>>Pending</option>
                            </select>
                        </td></tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button button-primary button-large" value="Simpan Data Desa">
                        <a href="?page=dw-desa" class="button">Kembali</a>
                    </p>
                </form>
            </div>
            <script>
            jQuery(document).ready(function($){
                // Media Upload
                $('#btn_upload_foto').click(function(e){
                    e.preventDefault();
                    var frame = wp.media({ title: 'Pilih Foto Desa', multiple: false, library: { type: 'image' } });
                    frame.on('select', function(){
                        var url = frame.state().get('selection').first().toJSON().url;
                        $('#foto_desa_url').val(url);
                        $('#preview_foto_desa').attr('src', url).show();
                    });
                    frame.open();
                });
            });
            </script>

        <?php else: ?>
            <!-- === TABEL LIST DESA (Original Table with Improved UI) === -->
            <?php 
                $per_page = 10;
                $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $offset = ($paged - 1) * $per_page;
                $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

                $where_sql = "WHERE 1=1";
                if (!empty($search)) {
                    $where_sql .= $wpdb->prepare(" AND (nama_desa LIKE %s OR kabupaten LIKE %s OR kelurahan LIKE %s)", "%$search%", "%$search%", "%$search%");
                }

                $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_sql");
                $total_pages = ceil($total_items / $per_page);

                $sql = "SELECT d.*, 
                        (SELECT COUNT(p.id) FROM {$wpdb->prefix}dw_pedagang p WHERE p.id_desa = d.id) as count_pedagang,
                        u.display_name as admin_name
                        FROM $table_name d
                        LEFT JOIN {$wpdb->users} u ON d.id_user_desa = u.ID
                        $where_sql ORDER BY d.created_at DESC LIMIT %d OFFSET %d";
                
                $rows = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));
            ?>

            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="dw-desa">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Desa/Wilayah...">
                        <input type="submit" class="button" value="Cari">
                    </p>
                </form>
            </div>

            <div class="dw-card-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="60">Foto</th>
                            <th width="30%">Nama Desa</th>
                            <th width="20%">Wilayah</th>
                            <th width="15%">Relasi Pedagang</th>
                            <th width="10%">Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r): $edit_url = "?page=dw-desa&action=edit&id={$r->id}"; ?>
                        <tr>
                            <td><img src="<?php echo esc_url($r->foto ? $r->foto : 'https://placehold.co/50'); ?>" class="dw-thumb-desa"></td>
                            <td>
                                <strong><a href="<?php echo $edit_url; ?>" class="row-title"><?php echo esc_html($r->nama_desa); ?></a></strong>
                                <div class="row-actions"><span class="edit"><a href="<?php echo $edit_url; ?>">Edit Detail</a></span></div>
                                <small style="color:#666;">Admin: <?php echo esc_html($r->admin_name); ?></small>
                            </td>
                            <td><small><?php echo esc_html($r->kelurahan); ?>, <?php echo esc_html($r->kecamatan); ?>, <?php echo esc_html($r->kabupaten); ?></small></td>
                            <td>
                                <span class="dw-stat-pill"><span class="dashicons dashicons-store" style="font-size:14px; width:14px; height:14px;"></span> <?php echo $r->count_pedagang; ?> Pedagang</span>
                            </td>
                            <td><span class="dw-badge dw-badge-<?php echo $r->status; ?>"><?php echo ucfirst($r->status); ?></span></td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus desa ini?');">
                                    <?php wp_nonce_field('dw_desa_action'); ?>
                                    <input type="hidden" name="action_desa" value="delete">
                                    <input type="hidden" name="desa_id" value="<?php echo $r->id; ?>">
                                    <button type="submit" class="button button-small" style="color:#d63638; border-color:#d63638;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6">Belum ada data desa terdaftar.</td></tr>
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
        .dw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .dw-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; border-left: 4px solid #ccd0d4; }
        .dw-stat-icon { width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 15px; }
        .dw-stat-icon .dashicons { color: #fff; font-size: 24px; }
        .dw-stat-label { display: block; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .dw-stat-value { display: block; font-size: 20px; font-weight: 700; color: #1e293b; }
        
        .bg-blue { background: #2271b1; } .bg-green { background: #00a32a; } .bg-orange { background: #dba617; }
        .border-blue { border-left-color: #2271b1; } .border-green { border-left-color: #00a32a; } .border-orange { border-left-color: #dba617; }

        .dw-form-card { padding: 25px; max-width: 1000px; margin-top: 20px; border-radius: 8px; }
        .dw-section-title { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; color: #1d2327; }
        .dw-thumb-desa { width: 50px; height: 50px; border-radius: 6px; object-fit: cover; border: 1px solid #ddd; }
        .dw-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .dw-badge-aktif { background: #e7f9ed; color: #184a2c; }
        .dw-badge-pending { background: #fff7ed; color: #7c2d12; }
        .dw-stat-pill { background: #f0f0f1; padding: 4px 8px; border-radius: 4px; font-size: 11px; color: #50575e; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
        .dw-region-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-width: 500px; }
    </style>
    <?php
}