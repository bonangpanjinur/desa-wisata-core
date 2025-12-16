<?php
/**
 * File Name:   includes/admin-pages/page-pedagang.php
 * Description: Manajemen Toko / Pedagang dengan Tampilan Tabel Modern (Card Style).
 * Adapted from: page-desa.php
 */

if (!defined('ABSPATH')) exit;

// Pastikan Media Enqueue diload untuk upload foto
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_media();
});

function dw_pedagang_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';
    $message = '';
    $message_type = '';

    // --- 1. LOGIC: SAVE / UPDATE / DELETE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pedagang'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_pedagang_action')) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>'; return;
        }

        $action = sanitize_text_field($_POST['action_pedagang']);

        // DELETE
        if ($action === 'delete' && !empty($_POST['pedagang_id'])) {
            $deleted = $wpdb->delete($table_name, ['id' => intval($_POST['pedagang_id'])]);
            if ($deleted !== false) {
                $message = 'Data pedagang berhasil dihapus.'; $message_type = 'success';
            } else {
                $message = 'Gagal menghapus pedagang: ' . $wpdb->last_error; $message_type = 'error';
            }
        
        // SAVE / UPDATE
        } elseif ($action === 'save') {
            
            $input = [
                'user_id'           => get_current_user_id(),
                'nama_pedagang'     => sanitize_text_field($_POST['nama_pedagang']),
                'email'             => sanitize_email($_POST['email']),
                'telepon'           => sanitize_text_field($_POST['telepon']),
                'nik'               => sanitize_text_field($_POST['nik']),
                'nama_toko'         => sanitize_text_field($_POST['nama_toko']),
                'deskripsi_toko'    => wp_kses_post($_POST['deskripsi_toko']),
                'foto_toko'         => esc_url_raw($_POST['foto_toko_url']),
                'status_verifikasi' => sanitize_text_field($_POST['status_verifikasi']),
                
                // WILAYAH (Simpan Nama Wilayah dari Hidden Input)
                // Select ID dipakai untuk memicu JS, tapi yang disimpan ke DB adalah Namanya
                // (Sesuaikan dengan struktur DB dw_pedagang Anda)
                'provinsi'          => sanitize_text_field($_POST['provinsi_nama']),
                'kota'              => sanitize_text_field($_POST['kabupaten_nama']),
                'kecamatan'         => sanitize_text_field($_POST['kecamatan_nama']),
                'desa'              => sanitize_text_field($_POST['desa_nama']),
                'alamat_lengkap'    => sanitize_textarea_field($_POST['alamat_lengkap']),
                
                // BANK
                'nama_bank'         => sanitize_text_field($_POST['nama_bank']),
                'no_rekening'       => sanitize_text_field($_POST['no_rekening']),
                
                'updated_at'        => current_time('mysql')
            ];

            // Validasi Dasar
            if (empty($input['nama_pedagang']) || empty($input['nama_toko'])) {
                $message = 'Nama Pedagang dan Nama Toko wajib diisi.'; 
                $message_type = 'error';
            } else {
                if (!empty($_POST['pedagang_id'])) {
                    // UPDATE
                    $result = $wpdb->update($table_name, $input, ['id' => intval($_POST['pedagang_id'])]);
                    $message = 'Data pedagang diperbarui.'; $message_type = 'success';
                } else {
                    // INSERT
                    // Cek Email
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE email = %s", $input['email']));
                    if ($exists) {
                        $message = 'Email sudah terdaftar.'; $message_type = 'error';
                    } else {
                        $input['created_at'] = current_time('mysql');
                        $result = $wpdb->insert($table_name, $input);
                        $message = 'Pedagang baru ditambahkan.'; $message_type = 'success';
                    }
                }
            }
        }
    }

    // --- 2. PREPARE DATA FOR VIEW ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }

    // --- LOGIKA API WILAYAH (Menggunakan fungsi yang sama dengan page-desa.php) ---
    // Kita coba mapping ID wilayah jika tersimpan (optional, jika DB pedagang menyimpan ID)
    // Jika tidak menyimpan ID, dropdown akan reset, tapi nama tetap tersimpan.
    // Asumsi: Kita gunakan logic standar plugin ini.
    
    $provinsi_list  = function_exists('dw_get_api_provinsi') ? dw_get_api_provinsi() : [];
    // Load child data only if ID exists (biasanya perlu extra logic jika DB cuma simpan nama)
    // Untuk simplifikasi UI Edit, user mungkin perlu memilih ulang wilayah jika ingin mengubah,
    // atau kita biarkan kosong dulu.
    
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Toko & Pedagang</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-pedagang&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            
            <!-- === FORM VIEW (ADD / EDIT) === -->
            <div class="card" style="padding: 20px; max-width: 100%; margin-top: 20px;">
                <form method="post">
                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                    <input type="hidden" name="action_pedagang" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr valign="top"><th scope="row"><h3>Informasi Toko</h3></th><td></td></tr>
                        
                        <tr>
                            <th>Nama Toko / Usaha</th>
                            <td><input name="nama_toko" type="text" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="regular-text" required placeholder="Contoh: Keripik Singkong Barokah"></td>
                        </tr>
                        <tr>
                            <th>Deskripsi</th>
                            <td><?php wp_editor($edit_data->deskripsi_toko ?? '', 'deskripsi_toko', ['textarea_rows'=>4, 'media_buttons'=>false]); ?></td>
                        </tr>
                        <tr>
                            <th>Foto Toko</th>
                            <td>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <div style="flex-grow:0;">
                                        <input type="text" name="foto_toko_url" id="foto_toko_url" value="<?php echo esc_attr($edit_data->foto_toko ?? ''); ?>" class="regular-text">
                                        <button type="button" class="button" id="btn_upload_foto">Upload Foto</button>
                                    </div>
                                    <img id="preview_foto_toko" src="<?php echo !empty($edit_data->foto_toko) ? esc_url($edit_data->foto_toko) : 'https://placehold.co/100x100?text=No+Img'; ?>" style="height:60px; width:auto; border-radius:4px; border:1px solid #ddd;">
                                </div>
                            </td>
                        </tr>

                        <tr valign="top"><th scope="row"><h3 style="margin-top:20px;">Data Pemilik</h3></th><td><hr></td></tr>
                        
                        <tr>
                            <th>Nama Lengkap (KTP)</th>
                            <td><input name="nama_pedagang" type="text" value="<?php echo esc_attr($edit_data->nama_pedagang ?? ''); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th>NIK</th>
                            <td><input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><input name="email" type="email" value="<?php echo esc_attr($edit_data->email ?? ''); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th>No. Telepon / WA</th>
                            <td><input name="telepon" type="text" value="<?php echo esc_attr($edit_data->telepon ?? ''); ?>" class="regular-text"></td>
                        </tr>

                        <tr valign="top"><th scope="row"><h3 style="margin-top:20px;">Lokasi & Alamat</h3></th><td><hr></td></tr>
                        
                        <!-- PENGGUNAAN CLASS dw-*-select AGAR TERBACA OLEH admin-scripts.js -->
                        <tr>
                            <th>Provinsi</th>
                            <td>
                                <select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select regular-text">
                                    <option value="">-- Pilih Provinsi --</option>
                                    <?php foreach ($provinsi_list as $prov) : ?>
                                        <option value="<?php echo esc_attr($prov['code']); ?>"><?php echo esc_html($prov['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Pilih ulang jika ingin mengubah alamat.</p>
                                <!-- Tampilkan alamat tersimpan -->
                                <?php if(!empty($edit_data->provinsi)): ?>
                                    <p><em>Tersimpan: <?php echo esc_html($edit_data->provinsi . ', ' . $edit_data->kota . ', ' . $edit_data->kecamatan . ', ' . $edit_data->desa); ?></em></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Kabupaten/Kota</th>
                            <td><select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select regular-text" disabled><option value="">-- Pilih Kabupaten --</option></select></td>
                        </tr>
                        <tr>
                            <th>Kecamatan</th>
                            <td><select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select regular-text" disabled><option value="">-- Pilih Kecamatan --</option></select></td>
                        </tr>
                        <tr>
                            <th>Kelurahan/Desa</th>
                            <td><select name="kelurahan_id" id="dw_desa" class="dw-desa-select regular-text" disabled><option value="">-- Pilih Kelurahan --</option></select></td>
                        </tr>
                        <tr>
                            <th>Detail Alamat</th>
                            <td><textarea name="alamat_lengkap" class="large-text" rows="2" placeholder="Nama Jalan, RT/RW..."><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea></td>
                        </tr>

                        <!-- HIDDEN INPUTS UNTUK NAMA WILAYAH (Target admin-scripts.js) -->
                        <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr($edit_data->provinsi ?? ''); ?>">
                        <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr($edit_data->kota ?? ''); ?>">
                        <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr($edit_data->kecamatan ?? ''); ?>">
                        <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr($edit_data->desa ?? ''); ?>">


                        <tr valign="top"><th scope="row"><h3 style="margin-top:20px;">Bank & Status</h3></th><td><hr></td></tr>
                        <tr>
                            <th>Nama Bank</th>
                            <td><input name="nama_bank" type="text" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="regular-text" placeholder="BCA / BRI"></td>
                        </tr>
                        <tr>
                            <th>No. Rekening</th>
                            <td><input name="no_rekening" type="text" value="<?php echo esc_attr($edit_data->no_rekening ?? ''); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Status Verifikasi</th>
                            <td>
                                <select name="status_verifikasi">
                                    <option value="pending" <?php selected($edit_data ? $edit_data->status_verifikasi : '', 'pending'); ?>>Menunggu Verifikasi</option>
                                    <option value="verified" <?php selected($edit_data ? $edit_data->status_verifikasi : '', 'verified'); ?>>Terverifikasi</option>
                                    <option value="rejected" <?php selected($edit_data ? $edit_data->status_verifikasi : '', 'rejected'); ?>>Ditolak / Suspend</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Simpan Data Pedagang">
                        <a href="?page=dw-pedagang" class="button">Batal</a>
                    </p>
                </form>
            </div>

            <!-- Script Upload Foto -->
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
                dw_setup_uploader('#btn_upload_foto', '#foto_toko_url', '#preview_foto_toko');
            });
            </script>

        <?php else: ?>

            <!-- === TABEL LIST PEDAGANG MODERN (MANUAL RENDER) === -->
            <?php 
                // 1. Pagination & Search Setup
                $per_page = 10;
                $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $offset = ($paged - 1) * $per_page;
                $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

                // 2. Query Data
                $where_sql = "WHERE 1=1";
                if (!empty($search)) {
                    $where_sql .= $wpdb->prepare(" AND (nama_pedagang LIKE %s OR nama_toko LIKE %s OR kota LIKE %s)", "%$search%", "%$search%", "%$search%");
                }

                // Total Items
                $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where_sql");
                $total_pages = ceil($total_items / $per_page);

                // Main Query
                $sql = "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
                $rows = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));
            ?>

            <!-- Toolbar: Search -->
            <div class="tablenav top" style="display:flex; justify-content:flex-end; margin-bottom:15px;">
                <form method="get">
                    <input type="hidden" name="page" value="dw-pedagang">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Toko / Nama...">
                        <input type="submit" class="button" value="Cari">
                    </p>
                </form>
            </div>

            <!-- Styles (Inline for simplicity like page-desa.php) -->
            <style>
                .dw-card-table { background:#fff; border:1px solid #c3c4c7; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04); overflow:hidden; }
                .dw-thumb-toko { width:60px; height:60px; border-radius:4px; object-fit:cover; border:1px solid #eee; background:#f9f9f9; }
                .dw-badge { display:inline-block; padding:4px 8px; border-radius:12px; font-size:11px; font-weight:600; line-height:1; }
                .dw-badge-verified { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
                .dw-badge-pending { background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
                .dw-badge-rejected { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
                
                .dw-contact-info { font-size:12px; color:#64748b; margin-top:2px; }
                .dw-contact-info .dashicons { font-size:14px; width:16px; height:16px; vertical-align:middle; }
                
                .dw-pagination { margin-top: 15px; text-align: right; }
                .dw-pagination .page-numbers { padding: 5px 10px; border: 1px solid #ccc; text-decoration: none; background: #fff; border-radius: 3px; margin-left: 2px; }
                .dw-pagination .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; }
            </style>

            <div class="dw-card-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="80px" style="text-align:center;">Foto</th>
                            <th width="25%">Informasi Toko</th>
                            <th width="20%">Pemilik & Kontak</th>
                            <th width="20%">Lokasi</th>
                            <th width="15%">Status</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r): 
                            $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}";
                            $img_src = !empty($r->foto_toko) ? $r->foto_toko : 'https://placehold.co/100x100/f1f5f9/64748b?text=Toko';
                            
                            $status_label = ucfirst($r->status_verifikasi);
                            $badge_class = 'dw-badge-pending';
                            if($r->status_verifikasi == 'verified') $badge_class = 'dw-badge-verified';
                            if($r->status_verifikasi == 'rejected') $badge_class = 'dw-badge-rejected';
                        ?>
                        <tr>
                            <td style="text-align:center; vertical-align:middle;">
                                <img src="<?php echo esc_url($img_src); ?>" class="dw-thumb-toko">
                            </td>
                            <td>
                                <strong><a href="<?php echo $edit_url; ?>" style="font-size:14px;"><?php echo esc_html($r->nama_toko); ?></a></strong>
                                <br><small style="color:#888;">Joined: <?php echo date('d M Y', strtotime($r->created_at)); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($r->nama_pedagang); ?></strong>
                                <div class="dw-contact-info">
                                    <span class="dashicons dashicons-email"></span> <?php echo esc_html($r->email); ?><br>
                                    <span class="dashicons dashicons-phone"></span> <?php echo esc_html($r->telepon ? $r->telepon : '-'); ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:600;"><?php echo esc_html($r->kota ? $r->kota : '-'); ?></div>
                                <div style="font-size:12px; color:#64748b;">
                                    <?php echo esc_html($r->kecamatan); ?>, <?php echo esc_html($r->desa); ?>
                                </div>
                            </td>
                            <td>
                                <span class="dw-badge <?php echo $badge_class; ?>"><?php echo $status_label; ?></span>
                            </td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small button-primary">Edit</a>
                                <form method="post" action="" style="display:inline-block;" onsubmit="return confirm('Hapus Pedagang <?php echo esc_js($r->nama_toko); ?>?');">
                                    <input type="hidden" name="action_pedagang" value="delete">
                                    <input type="hidden" name="pedagang_id" value="<?php echo $r->id; ?>">
                                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                                    <button type="submit" class="button button-small" style="color:#b32d2e; border-color:#b32d2e; background:transparent;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px; color:#777;">Belum ada data pedagang.</td></tr>
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