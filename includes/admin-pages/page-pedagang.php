<?php
/**
 * File Name: includes/admin-pages/page-pedagang.php
 * Description: Manajemen Pedagang dengan UI/UX Modern (Updated for New Schema)
 */

defined('ABSPATH') || exit;

// 1. Pastikan class API Address tersedia
$address_api_path = dirname(dirname(__FILE__)) . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

function dw_pedagang_page_render() {
    // Pastikan Media Uploader WordPress tersedia
    if ( ! did_action( 'wp_enqueue_media' ) ) {
        wp_enqueue_media();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';
    $table_desa = $wpdb->prefix . 'dw_desa';
    $table_verifikator = $wpdb->prefix . 'dw_verifikator'; // Referensi tabel verifikator
    $table_users = $wpdb->users;
    
    $message = '';
    $message_type = '';

    // --- LOGIC: SAVE / UPDATE / DELETE ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pedagang'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_pedagang_action')) {
            echo '<?php echo dw_admin_render_alert('Keamanan tidak valid (Nonce Failed).', 'error'); ?>'; 
            return;
        }

        $action = sanitize_text_field($_POST['action_pedagang']);

        // DELETE
        if ($action === 'delete' && !empty($_POST['pedagang_id'])) {
            $deleted = $wpdb->delete($table_name, ['id' => intval($_POST['pedagang_id'])]);
            if ($deleted !== false) {
                $message = 'Data pedagang berhasil dihapus.'; $message_type = 'success';
            } else {
                $message = 'Gagal menghapus pedagang. Error: ' . $wpdb->last_error; $message_type = 'error';
            }
        
        // SAVE / UPDATE
        } elseif ($action === 'save') {
            $nama_toko = sanitize_text_field($_POST['nama_toko']);
            $id_user = intval($_POST['id_user_pedagang']);
            $kelurahan_id = sanitize_text_field($_POST['pedagang_nama_id']); 
            
            // 1. Relasi Daerah (Otomatis berdasarkan Kelurahan)
            $desa_terkait = $wpdb->get_row($wpdb->prepare(
                "SELECT id, nama_desa FROM $table_desa WHERE api_kelurahan_id = %s LIMIT 1",
                $kelurahan_id
            ));
            $id_desa = $desa_terkait ? $desa_terkait->id : 0;
            $is_independent = ($id_desa == 0) ? 1 : 0;

            // 2. Relasi Referral / Verifikator (Update ID jika kode berubah)
            // Input manual dari admin
            $terdaftar_melalui_kode = sanitize_text_field($_POST['terdaftar_melalui_kode']);
            $id_verifikator = 0; // Default 0

            if (!empty($terdaftar_melalui_kode)) {
                // Cari ID verifikator berdasarkan kode referral yang diinput manual
                $verifikator = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table_verifikator WHERE kode_referral = %s LIMIT 1",
                    $terdaftar_melalui_kode
                ));
                if ($verifikator) {
                    $id_verifikator = $verifikator->id;
                }
            }

            // Status & Verifikasi
            $status_sekarang = sanitize_text_field($_POST['status_akun']);
            $approved_by = '';
            $verified_at = null;
            $is_verified = 0;

            // Jika status pendaftaran disetujui, set verified
            if ($_POST['status_pendaftaran'] === 'disetujui') {
                $is_verified = 1;
                // Ambil data lama untuk cek apakah sudah verified sebelumnya
                if (!empty($_POST['pedagang_id'])) {
                    $old_data = $wpdb->get_row($wpdb->prepare("SELECT verified_at, approved_by FROM $table_name WHERE id = %d", intval($_POST['pedagang_id'])));
                    $verified_at = $old_data->verified_at ? $old_data->verified_at : current_time('mysql');
                    $approved_by = $old_data->approved_by;
                } else {
                    $verified_at = current_time('mysql');
                }

                // Set approved by jika belum ada
                if (empty($approved_by)) {
                    $current_user = wp_get_current_user();
                    $approved_by = in_array('administrator', (array) $current_user->roles) ? 'admin' : 'desa';
                }
            }

            // --- LOGIC ONGKIR LOKAL (JSON) ---
            $shipping_ojek = isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0;
            
            $safe_array_map = function($input) {
                return isset($input) && is_array($input) ? array_map('sanitize_text_field', $input) : [];
            };

            $ojek_zona_data = [
                'satu_kecamatan' => [
                    'dekat' => [
                        'harga' => floatval($_POST['ojek_dekat_harga'] ?? 0),
                        'desa_ids' => $safe_array_map($_POST['ojek_dekat_desa_ids'] ?? null)
                    ],
                    'jauh' => [
                        'harga' => floatval($_POST['ojek_jauh_harga'] ?? 0),
                        'desa_ids' => $safe_array_map($_POST['ojek_jauh_desa_ids'] ?? null)
                    ]
                ],
                'beda_kecamatan' => [
                    'dekat' => [
                        'harga' => floatval($_POST['ojek_beda_kec_dekat_harga'] ?? 0),
                        'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_dekat_ids'] ?? null)
                    ],
                    'jauh' => [
                        'harga' => floatval($_POST['ojek_beda_kec_jauh_harga'] ?? 0),
                        'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_jauh_ids'] ?? null)
                    ]
                ]
            ];

            // DATA UTAMA
            $data = [
                'id_user'          => $id_user,
                'id_desa'          => $id_desa, // Set Relasi Wilayah
                'id_verifikator'   => $id_verifikator, // Update Relasi Verifikator
                'is_independent'   => $is_independent,
                'nama_toko'        => $nama_toko,
                'slug_toko'        => sanitize_title($nama_toko),
                'nama_pemilik'     => sanitize_text_field($_POST['nama_pemilik'] ?? ''),
                'nomor_wa'         => sanitize_text_field($_POST['nomor_wa'] ?? ''),
                'jam_buka'         => sanitize_text_field($_POST['jam_buka'] ?? ''),
                'jam_tutup'        => sanitize_text_field($_POST['jam_tutup'] ?? ''),
                'alamat_lengkap'   => sanitize_textarea_field($_POST['pedagang_detail'] ?? ''),
                'url_gmaps'        => esc_url_raw($_POST['url_gmaps'] ?? ''),
                
                // REFERRAL (Simpan kode manual)
                'terdaftar_melalui_kode' => $terdaftar_melalui_kode,

                // LEGALITAS & PROFIL
                'nik'              => sanitize_text_field($_POST['nik'] ?? ''),
                'url_ktp'          => esc_url_raw($_POST['url_ktp'] ?? ''),
                'foto_profil'      => esc_url_raw($_POST['foto_profil'] ?? ''),
                'foto_sampul'      => esc_url_raw($_POST['foto_sampul'] ?? ''),
                
                // KEUANGAN
                'no_rekening'        => sanitize_text_field($_POST['no_rekening'] ?? ''),
                'nama_bank'          => sanitize_text_field($_POST['nama_bank'] ?? ''),
                'atas_nama_rekening' => sanitize_text_field($_POST['atas_nama_rekening'] ?? ''),
                'qris_image_url'     => esc_url_raw($_POST['qris_image_url'] ?? ''),
                
                // STATUS & SISTEM
                'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran'] ?? ''),
                'status_akun'        => $status_sekarang,
                'is_verified'        => $is_verified,
                'verified_at'        => $verified_at,
                'approved_by'        => $approved_by,
                'sisa_transaksi'     => intval($_POST['sisa_transaksi']),
                
                // WILAYAH API (Untuk mempermudah edit)
                'api_provinsi_id'    => sanitize_text_field($_POST['pedagang_prov']),
                'api_kabupaten_id'   => sanitize_text_field($_POST['pedagang_kota']),
                'api_kecamatan_id'   => sanitize_text_field($_POST['pedagang_kec']),
                'api_kelurahan_id'   => $kelurahan_id,
                'provinsi_nama'      => sanitize_text_field($_POST['provinsi_text']),
                'kabupaten_nama'     => sanitize_text_field($_POST['kabupaten_text']),
                'kecamatan_nama'     => sanitize_text_field($_POST['kecamatan_text']),
                'kelurahan_nama'     => sanitize_text_field($_POST['kelurahan_text']),
                'kode_pos'           => sanitize_text_field($_POST['kode_pos']),

                // ONGKIR
                'shipping_nasional_aktif' => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,
                'shipping_nasional_harga' => floatval($_POST['shipping_nasional_harga']),
                'shipping_ojek_lokal_aktif' => $shipping_ojek,
                'shipping_ojek_lokal_zona'  => json_encode($ojek_zona_data),
                'allow_pesan_di_tempat'     => isset($_POST['allow_pesan_di_tempat']) ? 1 : 0,
                'galeri'                    => !empty($_POST['galeri_urls']) ? json_encode(array_filter(explode(',', wp_unslash($_POST['galeri_urls'])))) : '[]',
            ];

            if (!empty($_POST['pedagang_id'])) {
                $wpdb->update($table_name, $data, ['id' => intval($_POST['pedagang_id'])]);
                $message = 'Data pedagang berhasil diperbarui.'; $message_type = 'success';
            } else {
                // Generate Kode Referral Baru untuk Pedagang Baru
                $data['kode_referral_saya'] = strtoupper(substr(md5(uniqid($nama_toko, true)), 0, 8));
                $wpdb->insert($table_name, $data);
                $message = 'Pedagang baru berhasil ditambahkan.'; $message_type = 'success';
            }
        }
    }

    // --- VIEW LOGIC ---
    $action = $_GET['action'] ?? 'list';
    $edit_data = null;
    if ($action === 'edit' && !empty($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }

    $users = get_users(['role__in' => ['administrator', 'pedagang', 'subscriber', 'customer']]);
    ?>

    

    <div class="wrap dw-admin-wrap">
        <div class="dw-admin-header">
            <div class="dw-header-title">
                <span class="dashicons dashicons-store"></span>
                <h1>Manajemen Toko & Pedagang</h1>
            </div>
            <div class="dw-header-actions">
                <?php if ($action === 'list'): ?>
                    <a href="?page=dw-pedagang&action=edit" class="dw-btn-primary">
                        <span class="dashicons dashicons-plus"></span> Tambah Pedagang Baru
                    </a>
                <?php else: ?>
                    <a href="?page=dw-pedagang" class="dw-btn-secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span> Kembali ke Daftar
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <div class="dw-card">
                <div class="dw-card-header">
                    <h3>Daftar Toko & Pedagang</h3>
                    <div class="dw-card-tools">
                        <input type="text" id="dw-search-pedagang" class="dw-form-control" placeholder="Cari toko atau pemilik..." style="width: 250px;">
                    </div>
                </div>
                <div class="dw-card-body" style="padding:0;">
                    <table class="wp-list-table widefat fixed striped" id="table-pedagang">
                        <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Nama Toko</th>
                                <th>Pemilik</th>
                                <th>Wilayah</th>
                                <th>Status Akun</th>
                                <th>Verifikasi</th>
                                <th style="width:150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
                            if ($items): foreach($items as $item): 
                                $user_info = get_userdata($item->id_user);
                            ?>
                                <tr>
                                    <td><span style="color:#94a3b8; font-weight:600;">#<?php echo $item->id; ?></span></td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <?php if($item->foto_profil): ?>
                                                <img src="<?php echo esc_url($item->foto_profil); ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                                            <?php else: ?>
                                                <div style="width:32px; height:32px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center;">
                                                    <span class="dashicons dashicons-store" style="font-size:16px; color:#94a3b8;"></span>
                                                </div>
                                            <?php endif; ?>
                                            <strong><?php echo esc_html($item->nama_toko); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:500;"><?php echo $user_info ? $user_info->display_name : 'N/A'; ?></div>
                                        <div style="font-size:12px; color:#64748b;"><?php echo $user_info ? $user_info->user_email : ''; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size:13px;"><span class="dashicons dashicons-location" style="font-size:14px; width:14px; height:14px; color:#94a3b8;"></span> <?php echo esc_html($item->kecamatan_nama); ?></div>
                                        <div style="font-size:11px; color:#64748b; margin-left:18px;"><?php echo esc_html($item->kabupaten_nama); ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'info';
                                        if($item->status_akun === 'aktif') $status_class = 'success';
                                        if($item->status_akun === 'nonaktif') $status_class = 'danger';
                                        if($item->status_akun === 'suspend') $status_class = 'warning';
                                        ?>
                                        <span class="dw-badge dw-badge-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($item->status_akun); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $verif_class = 'warning';
                                        if($item->status_pendaftaran === 'disetujui') $verif_class = 'success';
                                        if($item->status_pendaftaran === 'ditolak') $verif_class = 'danger';
                                        ?>
                                        <span class="dw-badge dw-badge-<?php echo $verif_class; ?>">
                                            <?php echo ucfirst($item->status_pendaftaran); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:5px;">
                                            <a href="?page=dw-pedagang&action=edit&id=<?php echo $item->id; ?>" class="button button-small" title="Edit Data">
                                                <span class="dashicons dashicons-edit" style="font-size:16px; margin-top:2px;"></span>
                                            </a>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Hapus pedagang ini?');">
                                                <?php wp_nonce_field('dw_pedagang_action'); ?>
                                                <input type="hidden" name="action_pedagang" value="delete">
                                                <input type="hidden" name="pedagang_id" value="<?php echo $item->id; ?>">
                                                <button type="submit" class="button button-small button-link-delete" title="Hapus">
                                                    <span class="dashicons dashicons-trash" style="font-size:16px; margin-top:2px;"></span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">Belum ada data pedagang.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($){
                $("#dw-search-pedagang").on("keyup", function() {
                    var value = $(this).val().toLowerCase();
                    $("#table-pedagang tbody tr").filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                    });
                });
            });
            </script>
        <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('dw_pedagang_action'); ?>
                <input type="hidden" name="action_pedagang" value="save">
                <input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id ?? ''; ?>">
                
                <div class="dw-tabs-nav">
                    <div class="dw-tab-link active" data-target="#tab-umum">
                        <span class="dashicons dashicons-admin-users"></span> Informasi Umum
                    </div>
                    <div class="dw-tab-link" data-target="#tab-lokasi">
                        <span class="dashicons dashicons-location"></span> Lokasi & Alamat
                    </div>
                    <div class="dw-tab-link" data-target="#tab-visual">
                        <span class="dashicons dashicons-format-image"></span> Visual & Legalitas
                    </div>
                    <div class="dw-tab-link" data-target="#tab-keuangan">
                        <span class="dashicons dashicons-money-alt"></span> Keuangan
                    </div>
                    <div class="dw-tab-link" data-target="#tab-pengaturan">
                        <span class="dashicons dashicons-admin-generic"></span> Pengaturan & Ongkir
                    </div>
                </div>

                <div class="dw-card" style="border-top:none; border-radius:0 0 8px 8px;">
                    <div class="dw-card-body">
                        <div id="tab-umum" class="dw-tab-pane active">
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>User Pemilik (WP User) <span style="color:red;">*</span></label>
                                        <select name="id_user_pedagang" class="dw-form-control">
                                            <?php foreach($users as $u): ?>
                                                <option value="<?php echo $u->ID; ?>" <?php selected($edit_data->id_user ?? '', $u->ID); ?>><?php echo $u->display_name; ?> (<?php echo $u->user_email; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Nomor WhatsApp</label>
                                        <input name="nomor_wa" type="text" value="<?php echo esc_attr($edit_data->nomor_wa ?? ''); ?>" class="dw-form-control" placeholder="62812xxx">
                                    </div>
                                </div>
                            </div>
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Jam Buka</label>
                                        <input name="jam_buka" type="time" value="<?php echo esc_attr($edit_data->jam_buka ?? ''); ?>" class="dw-form-control">
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Jam Tutup</label>
                                        <input name="jam_tutup" type="time" value="<?php echo esc_attr($edit_data->jam_tutup ?? ''); ?>" class="dw-form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Nama Toko <span style="color:red;">*</span></label>
                                <input name="nama_toko" type="text" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="dw-form-control" required>
                            </div>
                            <div class="dw-form-group">
                                <label>Nama Pemilik</label>
                                <input name="nama_pemilik" type="text" value="<?php echo esc_attr($edit_data->nama_pemilik ?? ''); ?>" class="dw-form-control">
                            </div>
                        </div>

                        <div id="tab-lokasi" class="dw-tab-pane">
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Provinsi</label>
                                        <select name="pedagang_prov" class="dw-form-control dw-region-prov" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>">
                                            <option value="">Pilih Provinsi...</option>
                                            <?php if(class_exists('DW_Address_API')): $provs = DW_Address_API::get_provinces(); foreach($provs as $v): ?>
                                                <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_provinsi_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi_nama ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Kota/Kabupaten</label>
                                        <select name="pedagang_kota" class="dw-form-control dw-region-kota" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>">
                                            <option value="">Pilih Kota...</option>
                                            <?php if($edit_data && !empty($edit_data->api_provinsi_id) && class_exists('DW_Address_API')): $cities = DW_Address_API::get_cities($edit_data->api_provinsi_id); foreach($cities as $v): ?>
                                                <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kabupaten_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten_nama ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Kecamatan</label>
                                        <select name="pedagang_kec" class="dw-form-control dw-region-kec" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>">
                                            <option value="">Pilih Kecamatan...</option>
                                            <?php if($edit_data && !empty($edit_data->api_kabupaten_id) && class_exists('DW_Address_API')): $districts = DW_Address_API::get_subdistricts($edit_data->api_kabupaten_id); foreach($districts as $v): ?>
                                                <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kecamatan_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan_nama ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Kelurahan/Desa</label>
                                        <select name="pedagang_nama_id" class="dw-form-control dw-region-desa" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>">
                                            <option value="">Pilih Kelurahan...</option>
                                            <?php if($edit_data && !empty($edit_data->api_kecamatan_id) && class_exists('DW_Address_API')): $villages = DW_Address_API::get_villages($edit_data->api_kecamatan_id); foreach($villages as $v): ?>
                                                <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kelurahan_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan_nama ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Alamat Lengkap</label>
                                <textarea name="pedagang_detail" class="dw-form-control" rows="3" placeholder="Nama jalan, nomor rumah, RT/RW..."><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                            </div>
                            <div class="dw-form-group">
                                <label>Kode Pos</label>
                                <input name="kode_pos" id="inp_kode_pos" type="text" value="<?php echo esc_attr($edit_data->kode_pos ?? ''); ?>" class="dw-form-control" placeholder="Contoh: 12345">
                            </div>
                            <div class="dw-form-group">
                                <label>URL Google Maps</label>
                                <input name="url_gmaps" type="text" value="<?php echo esc_attr($edit_data->url_gmaps ?? ''); ?>" class="dw-form-control" placeholder="https://goo.gl/maps/...">
                            </div>
                        </div>

                        <div id="tab-visual" class="dw-tab-pane">
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Foto Profil Toko</label>
                                        <div class="dw-media-uploader">
                                            <div style="margin-bottom:12px; text-align:center; background:#f8fafc; padding:20px; border-radius:12px; border:2px dashed #e2e8f0;">
                                                <img id="prev_foto_profil" src="<?php echo esc_url($edit_data->foto_profil ?? 'https://placehold.co/150x150?text=Profil'); ?>" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:4px solid #fff; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                                            </div>
                                            <div style="display:flex; gap:10px;">
                                                <input type="text" name="foto_profil" id="foto_profil" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>" class="dw-form-control" readonly placeholder="URL Foto Profil">
                                                <button type="button" class="dw-btn-secondary btn_upload" data-target="foto_profil" data-preview="#prev_foto_profil" style="padding:8px 15px;">
                                                    <span class="dashicons dashicons-upload" style="margin-top:4px;"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dw-form-group">
                                        <label>NIK Pemilik</label>
                                        <input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="dw-form-control" placeholder="Masukkan 16 digit NIK">
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Foto Sampul Toko</label>
                                        <div class="dw-media-uploader">
                                            <div style="margin-bottom:12px; background:#f8fafc; padding:10px; border-radius:12px; border:2px dashed #e2e8f0;">
                                                <img id="prev_foto_sampul" src="<?php echo esc_url($edit_data->foto_sampul ?? 'https://placehold.co/600x200?text=Sampul+Toko'); ?>" style="width:100%; height:140px; object-fit:cover; border-radius:8px;">
                                            </div>
                                            <div style="display:flex; gap:10px;">
                                                <input type="text" name="foto_sampul" id="foto_sampul" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>" class="dw-form-control" readonly placeholder="URL Foto Sampul">
                                                <button type="button" class="dw-btn-secondary btn_upload" data-target="foto_sampul" data-preview="#prev_foto_sampul" style="padding:8px 15px;">
                                                    <span class="dashicons dashicons-upload" style="margin-top:4px;"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Foto KTP</label>
                                        <div class="dw-media-uploader">
                                            <div style="display:flex; gap:10px; align-items:center;">
                                                <div style="width:60px; height:40px; border-radius:4px; overflow:hidden; border:1px solid #e2e8f0; background:#f1f5f9;">
                                                    <img id="prev_url_ktp" src="<?php echo esc_url($edit_data->url_ktp ?? 'https://placehold.co/60x40?text=KTP'); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                </div>
                                                <input type="text" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>" class="dw-form-control" readonly placeholder="URL Foto KTP">
                                                <button type="button" class="dw-btn-secondary btn_upload" data-target="url_ktp" data-preview="#prev_url_ktp" style="padding:8px 15px;">
                                                    <span class="dashicons dashicons-upload" style="margin-top:4px;"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="dw-row" style="margin-top:20px; padding-top:20px; border-top:1px solid #eee;">
                                <div class="dw-col-12">
                                    <div class="dw-form-group">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                            <label style="margin-bottom:0;">Galeri Toko (Foto Tambahan)</label>
                                            <button type="button" class="dw-btn-secondary" id="btn_galeri" style="font-size:13px; padding:6px 12px;">
                                                <span class="dashicons dashicons-images-alt2" style="font-size:16px; margin-top:4px;"></span> Tambah Foto Galeri
                                            </button>
                                        </div>
                                        <div id="galeri-container">
                                            <?php 
                                            $galeri_urls = [];
                                            if (!empty($edit_data->galeri)) {
                                                $decoded = is_string($edit_data->galeri) ? json_decode($edit_data->galeri, true) : $edit_data->galeri;
                                                if (is_array($decoded)) {
                                                    foreach($decoded as $url) {
                                                        if($url) {
                                                            $galeri_urls[] = $url;
                                                            echo '<div class="g-item">';
                                                            echo '<img src="'.esc_url($url).'">';
                                                            echo '<span class="rem-g" data-url="'.esc_attr($url).'">&times;</span>';
                                                            echo '</div>';
                                                        }
                                                    }
                                                }
                                            }
                                            ?>
                                        </div>
                                        <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr(implode(',', $galeri_urls)); ?>">

                                        <p class="dw-help-text">Pilih beberapa foto untuk ditampilkan di halaman profil toko.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="tab-keuangan" class="dw-tab-pane">
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Nama Bank</label>
                                        <input name="nama_bank" type="text" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="dw-form-control" placeholder="Contoh: BCA, Mandiri, BRI">
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Nomor Rekening</label>
                                        <input name="no_rekening" type="text" value="<?php echo esc_attr($edit_data->no_rekening ?? ''); ?>" class="dw-form-control" placeholder="Masukkan nomor rekening">
                                    </div>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Atas Nama Rekening</label>
                                <input name="atas_nama_rekening" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening ?? ''); ?>" class="dw-form-control" placeholder="Nama sesuai buku tabungan">
                            </div>
                            <div class="dw-form-group">
                                <label>QRIS Pembayaran</label>
                                <div class="dw-media-uploader">
                                    <div style="display:flex; gap:20px; align-items:flex-start; background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
                                        <div style="width:120px; height:120px; background:#fff; padding:10px; border-radius:8px; border:1px solid #ddd;">
                                            <img id="prev_qris_image_url" src="<?php echo esc_url($edit_data->qris_image_url ?? 'https://placehold.co/150x150?text=QRIS'); ?>" style="width:100%; height:100%; object-fit:contain;">
                                        </div>
                                        <div style="flex:1;">
                                            <p class="dw-help-text" style="margin-bottom:10px;">Upload gambar QRIS toko untuk mempermudah transaksi non-tunai.</p>
                                            <div style="display:flex; gap:10px;">
                                                <input type="text" name="qris_image_url" id="qris_image_url" value="<?php echo esc_attr($edit_data->qris_image_url ?? ''); ?>" class="dw-form-control" readonly placeholder="URL Gambar QRIS">
                                                <button type="button" class="dw-btn-secondary btn_upload" data-target="qris_image_url" data-preview="#prev_qris_image_url">
                                                    <span class="dashicons dashicons-upload" style="margin-top:4px;"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="tab-pengaturan" class="dw-tab-pane">
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Status Akun</label>
                                        <select name="status_akun" class="dw-form-control">
                                            <option value="aktif" <?php selected($edit_data->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                                            <option value="nonaktif" <?php selected($edit_data->status_akun ?? '', 'nonaktif'); ?>>Non-Aktif</option>
                                            <option value="suspend" <?php selected($edit_data->status_akun ?? '', 'suspend'); ?>>Suspend</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Status Pendaftaran</label>
                                        <select name="status_pendaftaran" class="dw-form-control">
                                            <option value="disetujui" <?php selected($edit_data->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                                            <option value="menunggu" <?php selected($edit_data->status_pendaftaran ?? '', 'menunggu'); ?>>Menunggu</option>
                                            <option value="ditolak" <?php selected($edit_data->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Sisa Kuota Transaksi</label>
                                <input name="sisa_transaksi" type="number" value="<?php echo esc_attr($edit_data->sisa_transaksi ?? '0'); ?>" class="dw-form-control">
                            </div>
                            <div class="dw-form-group">
                                <label>Terdaftar Melalui Kode Referral</label>
                                <input name="terdaftar_melalui_kode" type="text" value="<?php echo esc_attr($edit_data->terdaftar_melalui_kode ?? ''); ?>" class="dw-form-control">
                            </div>
                            <div class="dw-form-group">
                                <label>Kode Referral Toko Saya</label>
                                <input type="text" value="<?php echo esc_attr($edit_data->kode_referral_saya ?? '-'); ?>" class="dw-form-control" readonly>
                            </div>
                            
                            <div style="margin-top:30px; padding-top:20px; border-top:1px solid #eee;">
                                <h4>Pengaturan Ongkir</h4>
                                <div class="dw-row">
                                    <div class="dw-col-6">
                                        <div class="dw-form-group">
                                            <label class="dw-toggle-switch">
                                                <input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked($edit_data->shipping_nasional_aktif ?? 0, 1); ?>>
                                                <span class="slider"></span>
                                                <span>Aktifkan Pengiriman Nasional (Flat Rate)</span>
                                            </label>
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Biaya Pengiriman Nasional (Rp)</label>
                                            <input name="shipping_nasional_harga" type="number" value="<?php echo esc_attr($edit_data->shipping_nasional_harga ?? '0'); ?>" class="dw-form-control">
                                        </div>
                                    </div>
                                    <div class="dw-col-6">
                                        <div class="dw-form-group">
                                            <label class="dw-toggle-switch">
                                                <input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked($edit_data->shipping_ojek_lokal_aktif ?? 0, 1); ?>>
                                                <span class="slider"></span>
                                                <span>Aktifkan Ojek Lokal</span>
                                            </label>
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Status Ojek Lokal</label>
                                            <p class="dw-help-text">Jika diaktifkan, pembeli dapat memilih pengiriman menggunakan ojek desa yang terdaftar.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="dw-form-group" style="margin-top:15px;">
                                    <label class="dw-toggle-switch">
                                        <input type="checkbox" name="allow_pesan_di_tempat" value="1" <?php checked($edit_data->allow_pesan_di_tempat ?? 0, 1); ?>>
                                        <span class="slider"></span>
                                        <span>Izinkan Pesan di Tempat (Ambil Sendiri)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dw-card-footer" style="padding: 20px; background: #f9f9f9; border-top: 1px solid #eee; text-align: right;">
                        <a href="<?php echo admin_url('admin.php?page=dw-pedagang'); ?>" class="button">Batal</a>
                        <button type="submit" class="dw-btn-primary">Simpan Data Pedagang</button>
                    </div>
                </div>
            </form>

            <script>
            jQuery(document).ready(function($){
                // Tab Logic
                $('.dw-tab-link').click(function(){
                    $('.dw-tab-link').removeClass('active');
                    $(this).addClass('active');
                    $('.dw-tab-pane').removeClass('active');
                    $($(this).data('target')).addClass('active');
                });

	                // Media Upload Fix
	                var mediaFrames = {};
	                $(document).on('click', '.btn_upload', function(e){
	                    e.preventDefault();
	                    var $btn = $(this);
	                    var target = '#' + $btn.data('target');
	                    var preview = $btn.data('preview');
	                    
	                    if ( typeof wp === 'undefined' || ! wp.media ) { 
	                        alert('Media uploader tidak tersedia.'); 
	                        return; 
	                    }
	                    
		                    // Jika frame sudah ada, buka saja
		                    if (mediaFrames[target]) {
		                        mediaFrames[target].open();
		                        return;
		                    }

		                    // Buat frame baru
		                    mediaFrames[target] = wp.media({ 
		                        title: 'Pilih Gambar', 
		                        multiple: false, 
		                        library: { type: 'image' } 
		                    });
		                    
		                    // Handler saat gambar dipilih
		                    mediaFrames[target].on('select', function(){
		                        var attachment = mediaFrames[target].state().get('selection').first().toJSON();
		                        var url = attachment.url;
		                        $(target).val(url);
		                        if(preview && $(preview).length) {
		                            $(preview).attr('src', url).show();
		                        }
		                    });

		                    // Buka frame
		                    mediaFrames[target].open();
	                });

	                // Gallery Logic
	                var galleryFrame;
	                $('#btn_galeri').click(function(e){
	                    e.preventDefault();
	                    if (galleryFrame) {
	                        galleryFrame.open();
	                        return;
	                    }
	                    galleryFrame = wp.media({
	                        title: 'Pilih Foto Galeri',
	                        multiple: true,
	                        library: { type: 'image' }
	                    });
	                    galleryFrame.on('select', function(){
	                        var selections = galleryFrame.state().get('selection');
	                        var urls = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : [];
	                        
	                        selections.map(function(attachment){
	                            var att = attachment.toJSON();
	                            if (urls.indexOf(att.url) === -1) {
	                                urls.push(att.url);
	                                $('#galeri-container').append('<div class="g-item" style="position:relative; width:100px; height:100px; border-radius:8px; overflow:hidden; border:1px solid #ddd;"><img src="'+att.url+'" style="width:100px; height:100px; object-fit:cover;"><span class="rem-g" data-url="'+att.url+'" style="position:absolute; top:5px; right:5px; background:rgba(255,0,0,0.7); color:white; width:20px; height:20px; border-radius:50%; text-align:center; line-height:18px; cursor:pointer; font-weight:bold;">&times;</span></div>');
	                            }
	                        });
	                        $('#galeri_urls').val(urls.join(','));
	                    });
	                    galleryFrame.open();
	                });

	                $(document).on('click', '.rem-g', function(){
	                    var url = $(this).data('url');
	                    var urls = $('#galeri_urls').val().split(',');
	                    urls = urls.filter(function(item){ return item !== url; });
	                    $('#galeri_urls').val(urls.join(',')); 
	                    $(this).parent().remove();
	                });

	                // Region API Logic
	                function loadRegion(type, pid, target, selId) {
	                    var act = type==='prov'?'dw_fetch_provinces':(type==='kota'?'dw_fetch_regencies':(type==='kec'?'dw_fetch_districts':'dw_fetch_villages'));
	                    if(type!=='prov' && !pid) return;
	                    $.get(ajaxurl, { action:act, province_id:pid, regency_id:pid, district_id:pid, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
	                        if(res.success) {
	                            target.empty().append('<option value="">Pilih...</option>');
	                            $.each(res.data, function(i,v){ 
	                                let pos = v.postal_code || '';
	                                target.append(`<option value="${v.id}" data-nama="${v.name}" data-pos="${pos}" ${(String(v.id)===String(selId)?'selected':'')}>${v.name}</option>`); 
	                            });
	                        }
	                    });
	                }
	                
	                $('.dw-region-prov').change(function(){ $('.dw-text-prov').val($(this).find('option:selected').text()); loadRegion('kota', $(this).val(), $('.dw-region-kota'), null); });
	                $('.dw-region-kota').change(function(){ $('.dw-text-kota').val($(this).find('option:selected').text()); loadRegion('kec', $(this).val(), $('.dw-region-kec'), null); });
	                $('.dw-region-kec').change(function(){ $('.dw-text-kec').val($(this).find('option:selected').text()); loadRegion('desa', $(this).val(), $('.dw-region-desa'), null); });
	                $('.dw-region-desa').change(function(){ 
	                    $('.dw-text-desa').val($(this).find('option:selected').text()); 
	                    let pos = $(this).find('option:selected').attr('data-pos');
	                    if(pos) $('#inp_kode_pos').val(pos);
	                });

	                // Init Address Waterfall if editing
	                var p = $('.dw-region-prov').data('current'); 
	                if(p) {
	                    // We don't trigger change to avoid resetting values, but we need to ensure options are loaded if they are empty
	                    // However, the PHP already renders the options for the current selection. 
	                    // This AJAX is mainly for when the user CHANGES the selection.
	                }
	            });
	            </script>
        <?php endif; ?>
    </div>
    <?php
}
