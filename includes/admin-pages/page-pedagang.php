<?php
/**
 * File Name:   includes/admin-pages/page-pedagang.php
 * Description: Manajemen Toko / Pedagang dengan Integrasi Wilayah.id dan Auto Relasi Desa.
 */

if (!defined('ABSPATH')) exit;

// Pastikan Media Enqueue diload untuk upload foto
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_media();
});

function dw_pedagang_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';
    $table_desa = $wpdb->prefix . 'dw_desa';
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
            
            $nama_toko = sanitize_text_field($_POST['nama_toko']);
            $slug_toko = sanitize_title($nama_toko);

            // Sanitasi Checkbox
            $ojek_aktif = isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0;
            $nasional_aktif = isset($_POST['shipping_nasional_aktif']) ? 1 : 0;
            $pesan_di_tempat = isset($_POST['allow_pesan_di_tempat']) ? 1 : 0;
            
            // --- LOGIKA OTOMATISASI RELASI DESA ---
            $kelurahan_id = sanitize_text_field($_POST['m_desa']); // ID API Kelurahan
            $determined_id_desa = 0; 

            if (!empty($kelurahan_id)) {
                $found_desa = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_desa WHERE api_kelurahan_id = %s LIMIT 1", 
                    $kelurahan_id
                ));
                if ($found_desa) {
                    $determined_id_desa = $found_desa;
                }
            }

            $input = [
                'id_user'           => intval($_POST['id_user']),
                'id_desa'           => $determined_id_desa,
                'nama_toko'         => $nama_toko,
                'slug_toko'         => $slug_toko,
                'nama_pemilik'      => sanitize_text_field($_POST['nama_pemilik']),
                'nomor_wa'          => sanitize_text_field($_POST['nomor_wa']),
                'nik'               => sanitize_text_field($_POST['nik']),
                
                'foto_profil'       => esc_url_raw($_POST['foto_profil_url']),
                'foto_sampul'       => esc_url_raw($_POST['foto_sampul_url']),
                'url_ktp'           => esc_url_raw($_POST['url_ktp']),
                'url_gmaps'         => esc_url_raw($_POST['url_gmaps']),
                
                'nama_bank'         => sanitize_text_field($_POST['nama_bank']),
                'no_rekening'       => sanitize_text_field($_POST['no_rekening']),
                'atas_nama_rekening'=> sanitize_text_field($_POST['atas_nama_rekening']),
                'qris_image_url'    => esc_url_raw($_POST['qris_image_url']),
                
                'status_pendaftaran'=> sanitize_text_field($_POST['status_pendaftaran']),
                'status_akun'       => sanitize_text_field($_POST['status_akun']),
                'rating_toko'       => floatval($_POST['rating_toko']),
                'sisa_transaksi'    => intval($_POST['sisa_transaksi']),
                
                'shipping_ojek_lokal_aktif' => $ojek_aktif,
                'shipping_ojek_lokal_zona'  => sanitize_textarea_field($_POST['shipping_ojek_lokal_zona']),
                'shipping_nasional_aktif'   => $nasional_aktif,
                'shipping_nasional_harga'   => floatval($_POST['shipping_nasional_harga']),
                'allow_pesan_di_tempat'     => $pesan_di_tempat,

                // Wilayah API (ID)
                'api_provinsi_id'   => sanitize_text_field($_POST['m_prov']),
                'api_kabupaten_id'  => sanitize_text_field($_POST['m_kota']),
                'api_kecamatan_id'  => sanitize_text_field($_POST['m_kec']),
                'api_kelurahan_id'  => $kelurahan_id,
                
                // Wilayah Nama (Text)
                'provinsi_nama'     => sanitize_text_field($_POST['provinsi_nama']),
                'kabupaten_nama'    => sanitize_text_field($_POST['kabupaten_nama']),
                'kecamatan_nama'    => sanitize_text_field($_POST['kecamatan_nama']),
                'kelurahan_nama'    => sanitize_text_field($_POST['kelurahan_nama']),
                'alamat_lengkap'    => sanitize_textarea_field($_POST['alamat_lengkap']),
                
                'updated_at'        => current_time('mysql')
            ];

            if (!empty($_POST['pedagang_id'])) {
                $wpdb->update($table_name, $input, ['id' => intval($_POST['pedagang_id'])]);
                $message = 'Data pedagang diperbarui. ' . ($determined_id_desa ? '(Terhubung ke Desa Wisata)' : '(Independen)');
                $message_type = 'success';
            } else {
                $input['created_at'] = current_time('mysql');
                $wpdb->insert($table_name, $input);
                $message = 'Pedagang baru berhasil ditambahkan.';
                $message_type = 'success';
            }
        }
    }

    // --- 2. PREPARE DATA FOR VIEW ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    $nama_desa_terkait = 'Independen';

    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
        if ($edit_data && $edit_data->id_desa) {
            $nama_desa_terkait = $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM $table_desa WHERE id = %d", $edit_data->id_desa));
        }
    }

    $users = get_users(['role__in' => ['subscriber', 'customer', 'pedagang', 'administrator']]);
    ?>

    <style>
        .dw-tab-nav { display:flex; border-bottom:1px solid #c3c4c7; margin-bottom:20px; gap:5px; }
        .dw-tab-btn { padding:10px 20px; border:1px solid transparent; border-bottom:none; cursor:pointer; background:#f0f0f1; font-weight:600; text-decoration:none; color:#3c434a; border-radius:4px 4px 0 0; }
        .dw-tab-btn.active { background:#fff; border-color:#c3c4c7; border-bottom-color:#fff; color:#2271b1; margin-bottom:-1px; }
        .dw-tab-content { display:none; }
        .dw-tab-content.active { display:block; }
        .dw-readonly-box { background: #f0f0f1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; color: #555; }
        .dw-thumb-preview { height:80px; width:80px; object-fit:cover; border-radius:4px; border:1px solid #ddd; background:#f9f9f9; }
    </style>

    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Toko & Pedagang</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-pedagang&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <div class="card" style="padding: 0; max-width: 100%; margin-top: 20px; overflow:hidden;">
                <form method="post" id="form-pedagang">
                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                    <input type="hidden" name="action_pedagang" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <div style="background:#f6f7f7; padding:15px 20px 0 20px; border-bottom:1px solid #dfdfdf;">
                        <div class="dw-tab-nav">
                            <a href="#tab-info" class="dw-tab-btn active">Informasi Utama</a>
                            <a href="#tab-lokasi" class="dw-tab-btn">Lokasi & Alamat</a>
                            <a href="#tab-keuangan" class="dw-tab-btn">Keuangan & Legalitas</a>
                            <a href="#tab-pengiriman" class="dw-tab-btn">Pengiriman & Status</a>
                        </div>
                    </div>

                    <div style="padding:20px;">
                        <!-- TAB 1: INFO UTAMA -->
                        <div id="tab-info" class="dw-tab-content active">
                            <table class="form-table">
                                <tr>
                                    <th>Akun User WP</th>
                                    <td>
                                        <select name="id_user" class="regular-text" required>
                                            <option value="">-- Pilih User --</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo $u->ID; ?>" <?php selected($edit_data ? $edit_data->id_user : '', $u->ID); ?>><?php echo $u->display_name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr><th>Nama Toko</th><td><input name="nama_toko" type="text" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="regular-text" required></td></tr>
                                <tr><th>Nama Pemilik</th><td><input name="nama_pemilik" type="text" value="<?php echo esc_attr($edit_data->nama_pemilik ?? ''); ?>" class="regular-text" required></td></tr>
                                <tr><th>WhatsApp</th><td><input name="nomor_wa" type="text" value="<?php echo esc_attr($edit_data->nomor_wa ?? ''); ?>" class="regular-text"></td></tr>
                                <tr>
                                    <th>Logo & Banner</th>
                                    <td>
                                        <div style="display:flex; gap:20px;">
                                            <div>
                                                <input type="hidden" name="foto_profil_url" id="foto_profil_url" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>">
                                                <img id="preview_profil" src="<?php echo esc_url($edit_data->foto_profil ?? 'https://placehold.co/80x80?text=Logo'); ?>" class="dw-thumb-preview" style="border-radius:50%;">
                                                <br><button type="button" class="button button-small" id="btn_upload_profil">Ganti Logo</button>
                                            </div>
                                            <div>
                                                <input type="hidden" name="foto_sampul_url" id="foto_sampul_url" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>">
                                                <img id="preview_sampul" src="<?php echo esc_url($edit_data->foto_sampul ?? 'https://placehold.co/160x80?text=Banner'); ?>" class="dw-thumb-preview" style="width:160px;">
                                                <br><button type="button" class="button button-small" id="btn_upload_sampul">Ganti Banner</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- TAB 2: LOKASI (WILAYAH.ID) -->
                        <div id="tab-lokasi" class="dw-tab-content">
                            <table class="form-table">
                                <tr>
                                    <th>Relasi Desa</th>
                                    <td><div class="dw-readonly-box"><strong><?php echo esc_html($nama_desa_terkait); ?></strong></div></td>
                                </tr>
                                <tr>
                                    <th>Provinsi</th>
                                    <td>
                                        <select name="m_prov" class="dw-region-prov regular-text" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>" disabled><option value="">Memuat...</option></select>
                                        <input type="hidden" name="provinsi_nama" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi_nama ?? ''); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Kabupaten/Kota</th>
                                    <td>
                                        <select name="m_kota" class="dw-region-kota regular-text" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>" disabled><option value="">-- Pilih Provinsi --</option></select>
                                        <input type="hidden" name="kabupaten_nama" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten_nama ?? ''); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Kecamatan</th>
                                    <td>
                                        <select name="m_kec" class="dw-region-kec regular-text" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>" disabled><option value="">-- Pilih Kabupaten --</option></select>
                                        <input type="hidden" name="kecamatan_nama" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan_nama ?? ''); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Desa/Kelurahan</th>
                                    <td>
                                        <select name="m_desa" class="dw-region-desa regular-text" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>" disabled><option value="">-- Pilih Kecamatan --</option></select>
                                        <input type="hidden" name="kelurahan_nama" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan_nama ?? ''); ?>">
                                    </td>
                                </tr>
                                <tr><th>Alamat Lengkap</th><td><textarea name="alamat_lengkap" class="large-text" rows="2"><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea></td></tr>
                                <tr><th>Google Maps</th><td><input name="url_gmaps" type="url" value="<?php echo esc_attr($edit_data->url_gmaps ?? ''); ?>" class="large-text"></td></tr>
                            </table>
                        </div>

                        <!-- TAB 3: KEUANGAN -->
                        <div id="tab-keuangan" class="dw-tab-content">
                            <table class="form-table">
                                <tr><th>NIK Pemilik</th><td><input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="regular-text"></td></tr>
                                <tr><th>Nama Bank</th><td><input name="nama_bank" type="text" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="regular-text"></td></tr>
                                <tr><th>No. Rekening</th><td><input name="no_rekening" type="text" value="<?php echo esc_attr($edit_data->no_rekening ?? ''); ?>" class="regular-text"></td></tr>
                                <tr><th>Atas Nama</th><td><input name="atas_nama_rekening" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening ?? ''); ?>" class="regular-text"></td></tr>
                                <tr>
                                    <th>Dokumen (KTP/QRIS)</th>
                                    <td>
                                        <input type="text" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>" class="regular-text" placeholder="URL KTP">
                                        <button type="button" class="button button-small" id="btn_upload_ktp">Upload KTP</button>
                                        <br><br>
                                        <input type="text" name="qris_image_url" id="qris_image_url" value="<?php echo esc_attr($edit_data->qris_image_url ?? ''); ?>" class="regular-text" placeholder="URL QRIS">
                                        <button type="button" class="button button-small" id="btn_upload_qris">Upload QRIS</button>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- TAB 4: STATUS -->
                        <div id="tab-pengiriman" class="dw-tab-content">
                            <table class="form-table">
                                <tr>
                                    <th>Status Verifikasi</th>
                                    <td>
                                        <select name="status_pendaftaran">
                                            <option value="menunggu_desa" <?php selected($edit_data->status_pendaftaran ?? '', 'menunggu_desa'); ?>>Menunggu Desa</option>
                                            <option value="menunggu" <?php selected($edit_data->status_pendaftaran ?? '', 'menunggu'); ?>>Menunggu Admin</option>
                                            <option value="disetujui" <?php selected($edit_data->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                                            <option value="ditolak" <?php selected($edit_data->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status Akun</th>
                                    <td>
                                        <select name="status_akun">
                                            <option value="aktif" <?php selected($edit_data->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                                            <option value="nonaktif" <?php selected($edit_data->status_akun ?? '', 'nonaktif'); ?>>Non-Aktif</option>
                                            <option value="suspend" <?php selected($edit_data->status_akun ?? '', 'suspend'); ?>>Suspend</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Pengiriman</th>
                                    <td>
                                        <label><input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked($edit_data->shipping_ojek_lokal_aktif ?? 0); ?>> Ojek Lokal</label><br>
                                        <label><input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked($edit_data->shipping_nasional_aktif ?? 0); ?>> Nasional</label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div style="background:#f6f7f7; padding:15px 20px; border-top:1px solid #dfdfdf; text-align:right;">
                        <input type="submit" class="button button-primary button-large" value="Simpan Data Pedagang">
                        <a href="?page=dw-pedagang" class="button button-large">Batal</a>
                    </div>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($){
                $('.dw-tab-btn').click(function(e){
                    e.preventDefault();
                    $('.dw-tab-btn, .dw-tab-content').removeClass('active');
                    $(this).addClass('active');
                    $($(this).attr('href')).addClass('active');
                });

                function dw_uploader(btn, input, preview) {
                    $(btn).click(function(e){
                        e.preventDefault();
                        var frame = wp.media({ title: 'Pilih Gambar', multiple: false });
                        frame.on('select', function(){
                            var url = frame.state().get('selection').first().toJSON().url;
                            $(input).val(url);
                            if(preview) $(preview).attr('src', url);
                        });
                        frame.open();
                    });
                }
                dw_uploader('#btn_upload_profil', '#foto_profil_url', '#preview_profil');
                dw_uploader('#btn_upload_sampul', '#foto_sampul_url', '#preview_sampul');
                dw_uploader('#btn_upload_ktp', '#url_ktp', null);
                dw_uploader('#btn_upload_qris', '#qris_image_url', null);
            });
            </script>

        <?php else: ?>
            <!-- LIST VIEW -->
            <?php 
                $per_page = 10;
                $paged = max(1, intval($_GET['paged'] ?? 1));
                $offset = ($paged - 1) * $per_page;
                $search = sanitize_text_field($_GET['s'] ?? '');
                
                $where = "WHERE 1=1";
                if($search) $where .= $wpdb->prepare(" AND (nama_toko LIKE %s OR nama_pemilik LIKE %s)", "%$search%", "%$search%");
                
                $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where");
                $rows = $wpdb->get_results($wpdb->prepare("SELECT p.*, d.nama_desa FROM $table_name p LEFT JOIN $table_desa d ON p.id_desa = d.id $where ORDER BY p.created_at DESC LIMIT %d OFFSET %d", $per_page, $offset));
            ?>
            
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="dw-pedagang">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>">
                        <input type="submit" class="button" value="Cari">
                    </p>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="60">Logo</th>
                        <th>Info Toko</th>
                        <th>Wilayah</th>
                        <th>Relasi Desa</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($rows): foreach($rows as $r): $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}"; ?>
                    <tr>
                        <td><img src="<?php echo esc_url($r->foto_profil ?: 'https://placehold.co/40'); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"></td>
                        <td><strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_toko); ?></a></strong><br><small><?php echo esc_html($r->nama_pemilik); ?></small></td>
                        <td><small><?php echo esc_html($r->kecamatan_nama); ?>, <?php echo esc_html($r->kabupaten_nama); ?></small></td>
                        <td><?php echo $r->nama_desa ? '<strong>'.esc_html($r->nama_desa).'</strong>' : '<span style="color:#999;">Independen</span>'; ?></td>
                        <td><?php echo ucfirst($r->status_akun); ?></td>
                        <td>
                            <a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('dw_pedagang_action'); ?>
                                <input type="hidden" name="action_pedagang" value="delete">
                                <input type="hidden" name="pedagang_id" value="<?php echo $r->id; ?>">
                                <button type="submit" class="button button-small" onclick="return confirm('Hapus?')" style="color:red;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6">Belum ada data pedagang.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if($total > $per_page): ?>
            <div class="tablenav bottom"><div class="tablenav-pages"><?php echo paginate_links(['total'=>ceil($total/$per_page), 'current'=>$paged]); ?></div></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}