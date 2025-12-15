<?php
/**
 * File Name:   includes/admin-pages/page-desa.php
 * Description: CRUD Desa dengan Tampilan Tabel Modern (Card Style).
 * * [UPDATED]
 * - Tampilan tabel diubah menjadi gaya Card modern.
 * - Menambahkan thumbnail foto desa.
 * - Badge hitungan (Wisata & Pedagang) lebih cantik.
 * - Fitur Search & Pagination manual yang stabil.
 */

if (!defined('ABSPATH')) exit;

function dw_desa_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_desa';
    $message = '';
    $message_type = '';

    // --- LOGIC: SAVE / UPDATE / DELETE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_desa'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_action')) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>'; return;
        }

        $action = sanitize_text_field($_POST['action_desa']);

        // DELETE
        if ($action === 'delete' && !empty($_POST['desa_id'])) {
            // Hapus relasi jika perlu (misal set id_desa=0 di pedagang/wisata) - Opsional
            $deleted = $wpdb->delete($table_name, ['id' => intval($_POST['desa_id'])]);
            if ($deleted !== false) {
                $message = 'Data desa berhasil dihapus.'; $message_type = 'success';
            } else {
                $message = 'Gagal menghapus desa: ' . $wpdb->last_error; $message_type = 'error';
            }
        
        // SAVE / UPDATE
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
                
                // KEUANGAN
                'persentase_komisi_penjualan' => isset($_POST['persentase_komisi']) ? floatval($_POST['persentase_komisi']) : 0,
                'nama_bank_desa'              => sanitize_text_field($_POST['nama_bank_desa']),
                'no_rekening_desa'            => sanitize_text_field($_POST['no_rekening_desa']),
                'atas_nama_rekening_desa'     => sanitize_text_field($_POST['atas_nama_rekening_desa']),
                'qris_image_url_desa'         => esc_url_raw($_POST['qris_image_url_desa']),

                // WILAYAH API
                'api_provinsi_id' => sanitize_text_field($_POST['provinsi_id']),
                'api_kabupaten_id'=> sanitize_text_field($_POST['kabupaten_id']),
                'api_kecamatan_id'=> sanitize_text_field($_POST['kecamatan_id']),
                'api_kelurahan_id'=> sanitize_text_field($_POST['kelurahan_id']),
                
                // NAMA WILAYAH
                'provinsi'     => sanitize_text_field($_POST['provinsi_nama']), 
                'kabupaten'    => sanitize_text_field($_POST['kabupaten_nama']),
                'kecamatan'    => sanitize_text_field($_POST['kecamatan_nama']),
                'kelurahan'    => sanitize_text_field($_POST['desa_nama']),
                
                'alamat_lengkap' => sanitize_textarea_field($_POST['alamat_lengkap']),
                'updated_at'   => current_time('mysql')
            ];

            if (!empty($_POST['desa_id'])) {
                $result = $wpdb->update($table_name, $data, ['id' => intval($_POST['desa_id'])]);
                $message = 'Data desa diperbarui.'; $message_type = 'success';
            } else {
                $data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table_name, $data);
                $message = 'Desa baru ditambahkan.'; $message_type = 'success';
            }
        }
    }

    // --- PREPARE DATA FOR VIEW ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }
    
    // User List untuk Dropdown
    $users = get_users(['role__in' => ['administrator', 'admin_desa', 'editor']]);

    // --- LOGIKA API WILAYAH (Server Side Pre-fill) ---
    $provinsi_id    = $edit_data->api_provinsi_id ?? '';
    $kabupaten_id   = $edit_data->api_kabupaten_id ?? '';
    $kecamatan_id   = $edit_data->api_kecamatan_id ?? '';
    $kelurahan_id   = $edit_data->api_kelurahan_id ?? '';

    $provinsi_list  = function_exists('dw_get_api_provinsi') ? dw_get_api_provinsi() : [];
    $kabupaten_list = (!empty($provinsi_id) && function_exists('dw_get_api_kabupaten')) ? dw_get_api_kabupaten($provinsi_id) : [];
    $kecamatan_list = (!empty($kabupaten_id) && function_exists('dw_get_api_kecamatan')) ? dw_get_api_kecamatan($kabupaten_id) : [];
    $desa_list_api  = (!empty($kecamatan_id) && function_exists('dw_get_api_desa')) ? dw_get_api_desa($kecamatan_id) : [];

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Desa Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-desa&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <!-- FORM VIEW (SAMA SEPERTI SEBELUMNYA) -->
            <div class="card" style="padding: 20px; max-width: 100%; margin-top: 20px;">
                <form method="post">
                    <?php wp_nonce_field('dw_desa_action'); ?>
                    <input type="hidden" name="action_desa" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="desa_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr valign="top"><th scope="row"><h3>Informasi Umum</h3></th><td></td></tr>
                        <tr>
                            <th><label>Akun Admin Desa</label></th>
                            <td>
                                <select name="id_user_desa" class="regular-text" required>
                                    <option value="">-- Pilih User WordPress --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user_desa : '', $user->ID); ?>>
                                            <?php echo $user->display_name; ?> (<?php echo $user->user_login; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">User ini yang akan mengelola data desa.</p>
                            </td>
                        </tr>
                        <tr><th>Nama Desa</th><td><input name="nama_desa" type="text" value="<?php echo esc_attr($edit_data->nama_desa ?? ''); ?>" class="regular-text" required placeholder="Contoh: Desa Wisata Pujon Kidul"></td></tr>
                        <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>5, 'media_buttons'=>false]); ?></td></tr>
                        
                        <tr><th>Foto Sampul Desa</th><td>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <div style="flex-grow:0;">
                                    <input type="text" name="foto_desa_url" id="foto_desa_url" value="<?php echo esc_attr($edit_data->foto ?? ''); ?>" class="regular-text">
                                    <button type="button" class="button" id="btn_upload_foto">Upload Foto</button>
                                </div>
                                <img id="preview_foto_desa" src="<?php echo !empty($edit_data->foto) ? esc_url($edit_data->foto) : 'https://placehold.co/100x60?text=No+Img'; ?>" style="height:60px; width:auto; border-radius:4px; border:1px solid #ddd;">
                            </div>
                        </td></tr>

                        <tr valign="top"><th scope="row"><h3 style="margin-top:20px;">Keuangan & Komisi</h3></th><td><hr></td></tr>
                        <tr><th>Komisi Penjualan (%)</th><td><input type="number" name="persentase_komisi" step="0.01" min="0" max="100" value="<?php echo esc_attr($edit_data->persentase_komisi_penjualan ?? '0'); ?>" class="small-text"> %</td></tr>
                        <tr><th>Nama Bank</th><td><input name="nama_bank_desa" type="text" value="<?php echo esc_attr($edit_data->nama_bank_desa ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th>Nomor Rekening</th><td><input name="no_rekening_desa" type="text" value="<?php echo esc_attr($edit_data->no_rekening_desa ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th>Atas Nama</th><td><input name="atas_nama_rekening_desa" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening_desa ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th>QRIS Desa</th><td>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <input type="text" name="qris_image_url_desa" id="qris_image_url_desa" value="<?php echo esc_attr($edit_data->qris_image_url_desa ?? ''); ?>" class="regular-text">
                                <button type="button" class="button" id="btn_upload_qris">Upload QRIS</button>
                            </div>
                        </td></tr>

                        <tr valign="top"><th scope="row"><h3 style="margin-top:20px;">Lokasi & Alamat</h3></th><td><hr></td></tr>
                        <!-- FORM SELECT2 WILAYAH SAMA SEPERTI SEBELUMNYA -->
                        <tr><th>Provinsi</th><td><select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select regular-text" required><option value="">-- Pilih Provinsi --</option><?php foreach ($provinsi_list as $prov) : ?><option value="<?php echo esc_attr($prov['code']); ?>" <?php selected($provinsi_id, $prov['code']); ?>><?php echo esc_html($prov['name']); ?></option><?php endforeach; ?></select></td></tr>
                        <tr><th>Kabupaten/Kota</th><td><select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select regular-text" <?php disabled(empty($kabupaten_list)); ?> required><option value="">-- Pilih Kabupaten --</option><?php foreach ($kabupaten_list as $kab) : ?><option value="<?php echo esc_attr($kab['code']); ?>" <?php selected($kabupaten_id, $kab['code']); ?>><?php echo esc_html($kab['name']); ?></option><?php endforeach; ?></select></td></tr>
                        <tr><th>Kecamatan</th><td><select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select regular-text" <?php disabled(empty($kecamatan_list)); ?> required><option value="">-- Pilih Kecamatan --</option><?php foreach ($kecamatan_list as $kec) : ?><option value="<?php echo esc_attr($kec['code']); ?>" <?php selected($kecamatan_id, $kec['code']); ?>><?php echo esc_html($kec['name']); ?></option><?php endforeach; ?></select></td></tr>
                        <tr><th>Kelurahan/Desa</th><td><select name="kelurahan_id" id="dw_desa" class="dw-desa-select regular-text" <?php disabled(empty($desa_list_api)); ?> required><option value="">-- Pilih Kelurahan --</option><?php foreach ($desa_list_api as $desa) : ?><option value="<?php echo esc_attr($desa['code']); ?>" <?php selected($kelurahan_id, $desa['code']); ?>><?php echo esc_html($desa['name']); ?></option><?php endforeach; ?></select></td></tr>
                        <tr><th>Detail Alamat</th><td><textarea name="alamat_lengkap" class="large-text" rows="2" placeholder="Nama Jalan, RT/RW..."><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea></td></tr>

                        <tr><th>Status</th><td>
                            <select name="status">
                                <option value="aktif" <?php selected($edit_data ? $edit_data->status : '', 'aktif'); ?>>Aktif</option>
                                <option value="pending" <?php selected($edit_data ? $edit_data->status : '', 'pending'); ?>>Pending</option>
                            </select>
                        </td></tr>
                    </table>

                    <!-- HIDDEN INPUTS NAMA WILAYAH -->
                    <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>">
                    <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr($edit_data->kabupaten ?? ''); ?>">
                    <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr($edit_data->kecamatan ?? ''); ?>">
                    <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr($edit_data->kelurahan ?? ''); ?>">

                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Simpan Data Desa">
                        <a href="?page=dw-desa" class="button">Batal</a>
                    </p>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($){
                function dw_setup_uploader(btnId, inputId, imgId) {
                    $(btnId).click(function(e){
                        e.preventDefault();
                        var frame = wp.media({ title: 'Pilih Gambar', multiple: false, library: { type: 'image' } });
                        frame.on('select', function(){
                            var url = frame.state().get('selection').first().toJSON().url;
                            $(inputId).val(url);
                            if(imgId) $(imgId).attr('src', url);
                        });
                        frame.open();
                    });
                }
                dw_setup_uploader('#btn_upload_foto', '#foto_desa_url', '#preview_foto_desa');
                dw_setup_uploader('#btn_upload_qris', '#qris_image_url_desa', null);
            });
            </script>

        <?php else: ?>
            
            <!-- === TABEL LIST DESA MODERN (MANUAL RENDER) === -->
            <?php 
                // 1. Pagination & Search Setup
                $per_page = 10;
                $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $offset = ($paged - 1) * $per_page;
                $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

                // 2. Query Data dengan Counts
                $table_desa = $wpdb->prefix . 'dw_desa';
                $table_wisata = $wpdb->prefix . 'dw_wisata';
                $table_pedagang = $wpdb->prefix . 'dw_pedagang';

                $where_sql = "WHERE 1=1";
                if (!empty($search)) {
                    $where_sql .= $wpdb->prepare(" AND (nama_desa LIKE %s OR kabupaten LIKE %s)", "%$search%", "%$search%");
                }

                // Hitung Total
                $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa $where_sql");
                $total_pages = ceil($total_items / $per_page);

                // Query Utama
                $sql = "SELECT d.*, 
                        (SELECT COUNT(w.id) FROM $table_wisata w WHERE w.id_desa = d.id AND w.status != 'trash') as count_wisata,
                        (SELECT COUNT(p.id) FROM $table_pedagang p WHERE p.id_desa = d.id AND p.status_akun != 'suspend') as count_pedagang,
                        u.display_name as admin_name
                        FROM $table_desa d
                        LEFT JOIN {$wpdb->users} u ON d.id_user_desa = u.ID
                        $where_sql
                        ORDER BY d.created_at DESC
                        LIMIT %d OFFSET %d";
                
                $rows = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));
            ?>

            <!-- Toolbar: Search -->
            <div class="tablenav top" style="display:flex; justify-content:flex-end; margin-bottom:15px;">
                <form method="get">
                    <input type="hidden" name="page" value="dw-desa">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Nama Desa...">
                        <input type="submit" class="button" value="Cari">
                    </p>
                </form>
            </div>

            <!-- Styles -->
            <style>
                .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); overflow:hidden; }
                .dw-thumb-desa { width:60px; height:60px; border-radius:4px; object-fit:cover; border:1px solid #eee; background:#f9f9f9; }
                .dw-badge { display:inline-block; padding:4px 8px; border-radius:12px; font-size:11px; font-weight:600; line-height:1; }
                .dw-badge-active { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
                .dw-badge-pending { background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
                
                .dw-stat-pill { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; margin-right:5px; border:1px solid transparent; }
                .stat-wisata { background:#e0f2f1; color:#00695c; border-color:#b2dfdb; }
                .stat-toko { background:#fff3e0; color:#e65100; border-color:#ffe0b2; }
                
                .dw-location { font-size:12px; color:#64748b; display:flex; align-items:center; gap:4px; margin-top:2px; }
                .dw-pagination { margin-top: 15px; text-align: right; }
                .dw-pagination .page-numbers { padding: 5px 10px; border: 1px solid #ccc; text-decoration: none; background: #fff; border-radius: 3px; margin-left: 2px; }
                .dw-pagination .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; }
            </style>

            <div class="dw-card-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="80px" style="text-align:center;">Foto</th>
                            <th width="25%">Nama Desa</th>
                            <th width="20%">Lokasi</th>
                            <th width="15%">Statistik</th>
                            <th width="15%">Admin</th>
                            <th width="10%">Status</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r): 
                            $edit_url = "?page=dw-desa&action=edit&id={$r->id}";
                            $img_src = !empty($r->foto) ? $r->foto : 'https://placehold.co/100x100/e2e8f0/64748b?text=Desa';
                            
                            // Badge Status
                            $status_html = ($r->status == 'aktif') 
                                ? '<span class="dw-badge dw-badge-active">Aktif</span>' 
                                : '<span class="dw-badge dw-badge-pending">Pending</span>';
                        ?>
                        <tr>
                            <td style="text-align:center; vertical-align:middle;">
                                <img src="<?php echo esc_url($img_src); ?>" class="dw-thumb-desa">
                            </td>
                            <td>
                                <strong><a href="<?php echo $edit_url; ?>" style="font-size:14px;"><?php echo esc_html($r->nama_desa); ?></a></strong>
                                <br><small style="color:#888;">@<?php echo esc_html($r->slug_desa); ?></small>
                            </td>
                            <td>
                                <div class="dw-location" title="Kecamatan">
                                    <span class="dashicons dashicons-location"></span> <?php echo esc_html($r->kecamatan ? $r->kecamatan : '-'); ?>
                                </div>
                                <div style="font-size:11px; color:#94a3b8; margin-left:20px;">
                                    <?php echo esc_html($r->kabupaten); ?>
                                </div>
                            </td>
                            <td>
                                <div style="margin-bottom:4px;">
                                    <span class="dw-stat-pill stat-wisata" title="Jumlah Objek Wisata">
                                        <span class="dashicons dashicons-palmtree" style="font-size:12px;"></span> <?php echo $r->count_wisata; ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="dw-stat-pill stat-toko" title="Jumlah Toko/Pedagang">
                                        <span class="dashicons dashicons-store" style="font-size:12px;"></span> <?php echo $r->count_pedagang; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="dashicons dashicons-admin-users" style="color:#aaa; font-size:14px;"></span> 
                                <?php echo esc_html($r->admin_name ? $r->admin_name : 'Belum Ada'); ?>
                            </td>
                            <td><?php echo $status_html; ?></td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small button-primary">Edit</a>
                                <form method="post" action="" style="display:inline-block;" onsubmit="return confirm('Hapus Desa <?php echo esc_js($r->nama_desa); ?>? Data terkait (pedagang/wisata) mungkin akan kehilangan relasi.');">
                                    <input type="hidden" name="action_desa" value="delete">
                                    <input type="hidden" name="desa_id" value="<?php echo $r->id; ?>">
                                    <?php wp_nonce_field('dw_desa_action'); ?>
                                    <button type="submit" class="button button-small" style="color:#b32d2e; border-color:#b32d2e; background:transparent;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#777;">Belum ada data desa wisata. Silakan tambah baru.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="dw-pagination">
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
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}
?>