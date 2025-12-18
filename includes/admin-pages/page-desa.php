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
            $desa_id_to_delete = intval($_POST['desa_id']);

            // Set related merchants to independent before deleting the village
            $wpdb->update(
                $wpdb->prefix . 'dw_pedagang',
                ['id_desa' => 0, 'is_independent' => 1],
                ['id_desa' => $desa_id_to_delete]
            );

            $deleted = $wpdb->delete($table_name, ['id' => $desa_id_to_delete]);
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
    $total_pedagang_independent = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_pedagang WHERE id_desa = 0");
    $total_pedagang_with_desa = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}dw_pedagang WHERE id_desa > 0");
    $total_komisi = $wpdb->get_var("SELECT SUM(persentase_komisi_penjualan) FROM $table_name");
    $total_komisi = $total_komisi ? number_format($total_komisi, 2) : '0.00';
    ?>

    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-desa&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if (!$is_edit): ?>
            <!-- === STATS CARDS (IMPROVED UI) === -->
            <div class="dw-stats-grid">
                <div class="dw-stat-card border-blue">
                    <div class="dw-stat-icon bg-blue"><span class="dashicons dashicons-admin-site-alt3"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Total Desa Wisata</span>
                        <span class="dw-stat-value"><?php echo number_format($total_desa); ?></span>
                        <span class="dw-stat-desc">Desa Terdaftar</span>
                    </div>
                </div>
                <div class="dw-stat-card border-green">
                    <div class="dw-stat-icon bg-green"><span class="dashicons dashicons-store"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Total Pedagang</span>
                        <span class="dw-stat-value"><?php echo number_format($total_pedagang); ?></span>
                        <span class="dw-stat-desc">Pedagang Terdaftar</span>
                    </div>
                </div>
                <div class="dw-stat-card border-purple">
                    <div class="dw-stat-icon bg-purple"><span class="dashicons dashicons-groups"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Pedagang Terhubung</span>
                        <span class="dw-stat-value"><?php echo number_format($total_pedagang_with_desa); ?></span>
                        <span class="dw-stat-desc">Dengan Desa Wisata</span>
                    </div>
                </div>
                <div class="dw-stat-card border-orange">
                    <div class="dw-stat-icon bg-orange"><span class="dashicons dashicons-admin-users"></span></div>
                    <div class="dw-stat-content">
                        <span class="dw-stat-label">Pedagang Independen</span>
                        <span class="dw-stat-value"><?php echo number_format($total_pedagang_independent); ?></span>
                        <span class="dw-stat-desc">Belum Terhubung</span>
                    </div>
                </div>
            </div>

            <!-- === INFO BANNER === -->
            <div class="dw-info-banner">
                <div class="dw-info-icon"><span class="dashicons dashicons-info-outline"></span></div>
                <div class="dw-info-text">
                    <strong>Sistem Relasi Otomatis:</strong>
                    <p style="margin:5px 0;">Pedagang akan otomatis terhubung ke Desa Wisata jika alamat kelurahan sama. Komisi hanya masuk ke kas Desa jika Admin Desa yang menyetujui (ACC) pendaftaran pedagang.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <!-- === FORM EDIT/TAMBAH (IMPROVED UI) === -->
            <div class="card dw-form-card">
                <form method="post" id="dw-desa-form">
                    <?php wp_nonce_field('dw_desa_action'); ?>
                    <input type="hidden" name="action_desa" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="desa_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <div class="dw-form-section">
                        <h3 class="dw-section-title"><span class="dashicons dashicons-admin-site-alt3"></span> Informasi Dasar Desa</h3>
                        <table class="form-table">
                            <tr>
                                <th><label>Akun Pengelola <span class="required">*</span></label></th>
                                <td>
                                    <select name="id_user_desa" class="regular-text" required>
                                        <option value="">-- Pilih User --</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user_desa : '', $user->ID); ?>>
                                                <?php echo $user->display_name; ?> (<?php echo $user->user_login; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Pilih user yang akan mengelola desa wisata ini.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Nama Desa <span class="required">*</span></label></th>
                                <td>
                                    <input name="nama_desa" type="text" value="<?php echo esc_attr($edit_data->nama_desa ?? ''); ?>" class="regular-text" required placeholder="Contoh: Desa Wisata Panglipuran">
                                    <p class="description">Nama lengkap desa wisata yang akan ditampilkan di platform.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Deskripsi</label></th>
                                <td>
                                    <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>8, 'media_buttons'=>false]); ?>
                                    <p class="description">Jelaskan tentang desa wisata ini, atraksi, dan keunikan yang dimiliki.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Foto Sampul</label></th>
                                <td>
                                    <input type="text" name="foto_desa_url" id="foto_desa_url" value="<?php echo esc_attr($edit_data->foto ?? ''); ?>" class="regular-text">
                                    <button type="button" class="button" id="btn_upload_foto">Pilih Gambar</button>
                                    <div class="dw-preview-container">
                                        <img id="preview_foto_desa" src="<?php echo !empty($edit_data->foto) ? esc_url($edit_data->foto) : ''; ?>" style="max-height:150px; <?php echo empty($edit_data->foto) ? 'display:none;' : ''; ?> margin-top:10px; border-radius:8px; border:2px solid #e5e7eb;">
                                    </div>
                                    <p class="description">Ukuran rekomendasi: 1200x600px (landscape).</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="dw-form-section">
                        <h3 class="dw-section-title"><span class="dashicons dashicons-location-alt"></span> Lokasi Administratif</h3>
                        <table class="form-table">
                            <tr>
                                <th><label>Wilayah <span class="required">*</span></label></th>
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
                                    <p class="description"><strong>Penting:</strong> Pedagang di kelurahan yang sama akan terhubung otomatis ke desa ini.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Alamat Detail</label></th>
                                <td>
                                    <textarea name="desa_detail" class="large-text" rows="3" placeholder="Jalan, No Rumah, RT/RW, Kode Pos"><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                                    <p class="description">Alamat lengkap desa wisata untuk informasi kontak.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="dw-form-section">
                        <h3 class="dw-section-title"><span class="dashicons dashicons-money-alt"></span> Keuangan & Komisi</h3>
                        <table class="form-table">
                            <tr>
                                <th><label>Komisi Desa (%) <span class="required">*</span></label></th>
                                <td>
                                    <input type="number" name="persentase_komisi" step="0.1" value="<?php echo esc_attr($edit_data->persentase_komisi_penjualan ?? '0'); ?>" class="small-text" min="0" max="100"> %
                                    <p class="description"><strong>Catatan:</strong> Komisi hanya berlaku jika Desa yang meng-ACC pendaftaran pedagang. Jika Admin Pusat yang ACC, komisi tidak masuk ke desa.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Data Bank</label></th>
                                <td>
                                    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                                        <input name="nama_bank_desa" type="text" value="<?php echo esc_attr($edit_data->nama_bank_desa ?? ''); ?>" placeholder="Nama Bank" class="widefat">
                                        <input name="no_rekening_desa" type="text" value="<?php echo esc_attr($edit_data->no_rekening_desa ?? ''); ?>" placeholder="No. Rekening" class="widefat">
                                    </div>
                                    <input name="atas_nama_rekening_desa" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening_desa ?? ''); ?>" placeholder="Atas Nama" class="widefat" style="margin-top:5px;">
                                    <p class="description">Data bank untuk pencairan komisi dari transaksi pedagang.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>QRIS (Opsional)</label></th>
                                <td>
                                    <input type="text" name="qris_image_url_desa" value="<?php echo esc_attr($edit_data->qris_image_url_desa ?? ''); ?>" class="regular-text">
                                    <p class="description">URL gambar QRIS untuk pembayaran langsung.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="dw-form-section">
                        <h3 class="dw-section-title"><span class="dashicons dashicons-visibility"></span> Status & Publikasi</h3>
                        <table class="form-table">
                            <tr>
                                <th><label>Status Aktif</label></th>
                                <td>
                                    <select name="status">
                                        <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                        <option value="pending" <?php selected($edit_data->status ?? '', 'pending'); ?>>Pending</option>
                                    </select>
                                    <p class="description">Status desa wisata. Jika aktif, desa akan ditampilkan di platform.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p class="submit">
                        <input type="submit" class="button button-primary button-large" value="<?php echo $edit_data ? 'Perbarui Data Desa' : 'Simpan Data Desa Baru'; ?>">
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
            <!-- === TABEL LIST DESA (IMPROVED UI) === -->
            <?php 
                $per_page = 15;
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
                        (SELECT SUM(p.sisa_transaksi) FROM {$wpdb->prefix}dw_pedagang p WHERE p.id_desa = d.id) as total_transaksi,
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
                            <th width="25%">Nama Desa & Admin</th>
                            <th width="20%">Wilayah</th>
                            <th width="18%">Relasi Pedagang</th>
                            <th width="12%">Total Transaksi</th>
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
                                <small style="color:#666;"><span class="dashicons dashicons-admin-users" style="font-size:12px;"></span> <?php echo esc_html($r->admin_name); ?></small>
                            </td>
                            <td>
                                <small><?php echo esc_html($r->kelurahan); ?></small><br>
                                <small style="color:#666;"><?php echo esc_html($r->kecamatan); ?></small><br>
                                <small style="color:#999;"><?php echo esc_html($r->kabupaten); ?></small>
                            </td>
                            <td>
                                <div class="dw-stat-pill"><span class="dashicons dashicons-store" style="font-size:14px; width:14px; height:14px;"></span> <?php echo $r->count_pedagang; ?> Pedagang</div>
                                <div class="dw-stat-pill" style="margin-top:5px;"><span class="dashicons dashicons-chart-bar" style="font-size:14px; width:14px; height:14px;"></span> <?php echo $r->total_transaksi ? number_format($r->total_transaksi) : '0'; ?> Transaksi</div>
                            </td>
                            <td>
                                <div class="dw-stat-pill" style="background:#e7f9ed; color:#184a2c;"><span class="dashicons dashicons-money-alt" style="font-size:14px; width:14px; height:14px;"></span> <?php echo $r->persentase_komisi_penjualan; ?>%</div>
                                <div class="dw-stat-pill" style="margin-top:5px; background:#fff7ed; color:#7c2d12;"><span class="dashicons dashicons-bank" style="font-size:14px; width:14px; height:14px;"></span> <?php echo esc_html($r->nama_bank_desa); ?></div>
                            </td>
                            <td><span class="dw-badge dw-badge-<?php echo $r->status; ?>"><?php echo ucfirst($r->status); ?></span></td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus desa ini? Data pedagang yang terhubung akan menjadi independen.');">
                                    <?php wp_nonce_field('dw_desa_action'); ?>
                                    <input type="hidden" name="action_desa" value="delete">
                                    <input type="hidden" name="desa_id" value="<?php echo $r->id; ?>">
                                    <button type="submit" class="button button-small" style="color:#d63638; border-color:#d63638;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="7">Belum ada data desa terdaftar.</td></tr>
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
        .dw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin: 20px 0; }
        .dw-stat-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; align-items: center; border-left: 5px solid #ccd0d4; transition: transform 0.2s; }
        .dw-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .dw-stat-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 18px; flex-shrink: 0; }
        .dw-stat-icon .dashicons { color: #fff; font-size: 28px; }
        .dw-stat-label { display: block; font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .dw-stat-value { display: block; font-size: 28px; font-weight: 700; color: #1e293b; line-height: 1; }
        .dw-stat-desc { display: block; font-size: 12px; color: #94a3b8; margin-top: 5px; }
        
        .bg-blue { background: #2271b1; } .bg-green { background: #00a32a; } .bg-purple { background: #7c3aed; } .bg-orange { background: #dba617; }
        .border-blue { border-left-color: #2271b1; } .border-green { border-left-color: #00a32a; } .border-purple { border-left-color: #7c3aed; } .border-orange { border-left-color: #dba617; }

        .dw-info-banner { background: #eff6ff; border-left: 4px solid #2271b1; padding: 18px 20px; border-radius: 8px; margin: 20px 0; display: flex; align-items: flex-start; gap: 15px; }
        .dw-info-icon { width: 40px; height: 40px; background: #2271b1; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .dw-info-icon .dashicons { color: #fff; font-size: 20px; }
        .dw-info-text { flex: 1; }
        .dw-info-text strong { color: #1e293b; font-size: 15px; }
        .dw-info-text p { color: #334155; font-size: 14px; margin: 8px 0 0; }

        .dw-form-card { padding: 30px; max-width: 1000px; margin-top: 20px; border-radius: 12px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .dw-form-section { margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #e5e7eb; }
        .dw-form-section:last-child { border-bottom: none; }
        .dw-section-title { margin: 0 0 20px 0; padding-bottom: 12px; border-bottom: 2px solid #2271b1; color: #1d2327; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .dw-section-title .dashicons { color: #2271b1; font-size: 22px; }
        .dw-thumb-desa { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 2px solid #e5e7eb; }
        .dw-badge { padding: 6px 14px; border-radius: 16px; font-size: 12px; font-weight: 600; display: inline-block; }
        .dw-badge-aktif { background: #e7f9ed; color: #184a2c; }
        .dw-badge-pending { background: #fff7ed; color: #7c2d12; }
        .dw-stat-pill { background: #f0f0f1; padding: 6px 12px; border-radius: 8px; font-size: 12px; color: #50575e; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
        .dw-region-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; max-width: 500px; }
        .required { color: #dc3545; font-weight: bold; }
        .description { font-size: 13px; color: #64748b; margin-top: 5px; }
        .form-table th { font-weight: 600; }
        .form-table td { padding: 15px 10px; }
        .submit { padding: 20px 0; border-top: 2px solid #e5e7eb; margin-top: 20px; }
        .button-large { padding: 12px 24px; font-size: 15px; }
        .row-actions { margin-top: 8px; }
        .row-actions span { margin-right: 10px; }
        .row-actions a { color: #2271b1; text-decoration: none; }
        .row-actions a:hover { text-decoration: underline; }
    </style>
    <?php
}
