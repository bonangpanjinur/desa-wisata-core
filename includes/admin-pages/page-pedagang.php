<?php
/**
 * File Name: includes/admin-pages/page-pedagang.php
 * Description: Manajemen Pedagang dengan UI/UX Modern v3.7 (Utuh & Sempurna).
 * UPDATE: Integrasi Penuh Referral, Relasi Desa, Address API, Audit Verifikasi, Kode Pos, & Logika Zona Ongkir JSON.
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// 1. Pastikan class API Address tersedia untuk pengisian wilayah otomatis
$address_api_path = dirname(dirname(__FILE__)) . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

/**
 * Render Halaman Manajemen Pedagang
 */
function dw_pedagang_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';
    $table_desa = $wpdb->prefix . 'dw_desa';
    $table_verifikator = $wpdb->prefix . 'dw_verifikator';
    $table_users = $wpdb->users;
    
    $message = '';
    $message_type = '';

    /**
     * =========================================================================
     * 1. LOGIKA PEMROSESAN (SIMPAN / UPDATE / HAPUS)
     * =========================================================================
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pedagang'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_pedagang_action')) {
            echo '<div class="notice notice-error is-dismissible"><p>Keamanan tidak valid (Nonce Failed). Silakan refresh halaman.</p></div>'; 
            return;
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
            if (empty($_POST['nama_toko']) || empty($_POST['id_user_pedagang'])) {
                $message = 'Gagal: Nama Toko dan Akun Pengguna wajib diisi.';
                $message_type = 'error';
            } else {
                $safe_array_map = function($input) {
                    if (isset($input) && is_array($input)) {
                        return array_map('sanitize_text_field', wp_unslash($input));
                    }
                    return [];
                };

                $status_sekarang = sanitize_text_field($_POST['status_akun']);
                $current_user_id = get_current_user_id();
                
                // --- Audit Verifikasi (v3.7) ---
                $verifier_role = null;
                $verified_by_id = null;
                $verified_at = null;
                $is_verified = 0;

                if ($status_sekarang === 'aktif') {
                    $is_verified = 1;
                    $verified_by_id = $current_user_id;
                    $verified_at = current_time('mysql');
                    
                    if (current_user_can('manage_options')) {
                        $verifier_role = 'admin';
                    } elseif (current_user_can('admin_desa')) {
                        $verifier_role = 'desa';
                    } else {
                        $v_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_verifikator WHERE id_user = %d", $current_user_id));
                        $verifier_role = $v_id ? 'verifikator_umkm' : 'admin';
                    }
                }

                // --- Relasi Desa Otomatis ---
                $kelurahan_id = sanitize_text_field($_POST['pedagang_nama_id']);
                $desa_terkait = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM $table_desa WHERE api_kelurahan_id = %s LIMIT 1", $kelurahan_id));
                $id_desa = $desa_terkait ? intval($desa_terkait->id) : 0;

                // --- LOGIKA ONGKIR LOKAL (JSON BUILDER) ---
                $ojek_zona_data = [
                    'satu_kecamatan' => [
                        'dekat' => [
                            'harga'    => floatval($_POST['ojek_dekat_harga']),
                            'desa_ids' => $safe_array_map($_POST['ojek_dekat_desa_ids'] ?? null)
                        ],
                        'jauh' => [
                            'harga'    => floatval($_POST['ojek_jauh_harga']),
                            'desa_ids' => $safe_array_map($_POST['ojek_jauh_desa_ids'] ?? null)
                        ]
                    ],
                    'beda_kecamatan' => [
                        'dekat' => [
                            'harga'         => floatval($_POST['ojek_beda_kec_dekat_harga']),
                            'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_dekat_ids'] ?? null)
                        ],
                        'jauh' => [
                            'harga'         => floatval($_POST['ojek_beda_kec_jauh_harga']),
                            'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_jauh_ids'] ?? null)
                        ]
                    ]
                ];

                $data = [
                    'id_user'                => intval($_POST['id_user_pedagang']),
                    'id_desa'                => $id_desa,
                    'is_independent'         => ($id_desa === 0) ? 1 : 0,
                    'nama_toko'              => sanitize_text_field(wp_unslash($_POST['nama_toko'])),
                    'slug_toko'              => sanitize_title($_POST['nama_toko']),
                    'nama_pemilik'           => sanitize_text_field(wp_unslash($_POST['nama_pemilik'])),
                    'nomor_wa'               => sanitize_text_field(wp_unslash($_POST['nomor_wa'])),
                    'alamat_lengkap'         => sanitize_textarea_field(wp_unslash($_POST['pedagang_detail'])),
                    'url_gmaps'              => esc_url_raw($_POST['url_gmaps']),
                    'nik'                    => sanitize_text_field(wp_unslash($_POST['nik'])),
                    'url_ktp'                => esc_url_raw($_POST['url_ktp']),
                    'foto_profil'            => esc_url_raw($_POST['foto_profil']),
                    'foto_sampul'            => esc_url_raw($_POST['foto_sampul']),
                    'no_rekening'            => sanitize_text_field($_POST['no_rekening']),
                    'nama_bank'              => sanitize_text_field($_POST['nama_bank']),
                    'atas_nama_rekening'     => sanitize_text_field($_POST['atas_nama_rekening']),
                    'qris_image_url'         => esc_url_raw($_POST['qris_image_url']),
                    'status_pendaftaran'     => sanitize_text_field($_POST['status_pendaftaran']),
                    'status_akun'            => $status_sekarang,
                    'kode_referal_digunakan' => strtoupper(sanitize_text_field($_POST['kode_referal'])),
                    'sisa_transaksi'         => intval($_POST['sisa_transaksi']),
                    'shipping_ojek_lokal_aktif' => isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0,
                    'shipping_ojek_lokal_zona'  => json_encode($ojek_zona_data),
                    'shipping_nasional_aktif'   => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,
                    'allow_pesan_di_tempat'     => isset($_POST['allow_pesan_di_tempat']) ? 1 : 0,
                    'api_provinsi_id'        => sanitize_text_field($_POST['pedagang_prov']),
                    'api_kabupaten_id'       => sanitize_text_field($_POST['pedagang_kota']),
                    'api_kecamatan_id'       => sanitize_text_field($_POST['pedagang_kec']),
                    'api_kelurahan_id'       => $kelurahan_id,
                    'provinsi_nama'          => sanitize_text_field($_POST['provinsi_text']),
                    'kabupaten_nama'         => sanitize_text_field($_POST['kabupaten_text']),
                    'kecamatan_nama'         => sanitize_text_field($_POST['kecamatan_text']),
                    'kelurahan_nama'         => sanitize_text_field($_POST['kelurahan_text']),
                    'kode_pos'               => sanitize_text_field($_POST['kode_pos']),
                    'updated_at'             => current_time('mysql')
                ];

                if ($is_verified) {
                    $data['verified_by_id'] = $verified_by_id;
                    $data['verifier_role']  = $verifier_role;
                    $data['verified_at']    = $verified_at;
                    $data['is_verified']    = 1;
                }

                if (!empty($_POST['pedagang_id'])) {
                    $wpdb->update($table_name, $data, ['id' => intval($_POST['pedagang_id'])]);
                    $message = 'Berhasil memperbarui data pedagang.'; $message_type = 'success';
                } else {
                    $data['created_at'] = current_time('mysql');
                    $wpdb->insert($table_name, $data);
                    $message = 'Berhasil menambahkan pedagang baru.'; $message_type = 'success';
                }
            }
        }
    }

    /**
     * =========================================================================
     * 2. DATA PREPARATION
     * =========================================================================
     */
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new');
    $edit_data = null;
    $ojek_zona = null;

    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
        if($edit_data && !empty($edit_data->shipping_ojek_lokal_zona)) {
            $ojek_zona = json_decode($edit_data->shipping_ojek_lokal_zona, true);
        }
    }
    
    if (!$ojek_zona) {
        $ojek_zona = [
            'satu_kecamatan' => ['dekat' => ['harga' => 0, 'desa_ids' => []], 'jauh' => ['harga' => 0, 'desa_ids' => []]],
            'beda_kecamatan' => ['dekat' => ['harga' => 0, 'kecamatan_ids' => []], 'jauh' => ['harga' => 0, 'kecamatan_ids' => []]]
        ];
    }

    $users = get_users(['orderby' => 'display_name']);

    /**
     * =========================================================================
     * 3. UI VIEW
     * =========================================================================
     */
    ?>
    <style>
        .dw-admin-wrap { max-width: 1200px; margin: 20px 20px 0 0; }
        .dw-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); overflow: hidden; }
        .dw-card-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .dw-card-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #1d2327; }
        
        .badge-referral { background: #fbeaea; color: #d63638; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; border: 1px solid #f5d2d2; }
        .badge-verifier { background: #e7f6e9; color: #00a32a; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; border: 1px solid #c6e1c6; }
        
        .dw-tabs-layout { display: flex; min-height: 600px; }
        .dw-tabs-nav { width: 230px; background: #f8fafc; border-right: 1px solid #e2e8f0; list-style: none; padding: 15px 0; margin: 0; flex-shrink: 0; }
        .dw-tab-trigger { padding: 14px 25px; cursor: pointer; color: #64748b; font-weight: 600; font-size: 13px; transition: 0.2s; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; }
        .dw-tab-trigger:hover { background: #fff; color: #2271b1; }
        .dw-tab-trigger.active { background: #fff; color: #2563eb; border-left: 4px solid #2563eb; }
        .dw-tab-content { flex: 1; padding: 30px; background: #fff; }
        .dw-tab-pane { display: none; }
        .dw-tab-pane.active { display: block; }
        
        .dw-form-group { margin-bottom: 18px; }
        .dw-form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #1d2327; font-size: 13px; }
        .dw-input { width: 100%; border: 1px solid #8c8f94; border-radius: 4px; padding: 8px 12px; font-size: 14px; }
        .required { color: #d63638; }

        /* Ongkir Zone Specific */
        .dw-zone-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .dw-zone-title { margin: 0 0 15px 0; font-size: 14px; color: #111827; border-bottom: 2px solid #2563eb; display: inline-block; padding-bottom: 4px; }
        .subzone-label { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 10px; display: block; }
        
        .table-p thead th { background: #f8fafc; padding: 12px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px; }

        .dw-toggle-switch { position: relative; display: inline-flex; align-items: center; gap: 10px; cursor: pointer; }
        .dw-toggle-switch input { display: none; }
        .slider { position: relative; width: 40px; height: 20px; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #2271b1; }
        input:checked + .slider:before { transform: translateX(20px); }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <div class="wrap dw-admin-wrap">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-store" style="font-size: 28px; width: 28px; height: 28px; margin-right: 12px; vertical-align: middle;"></span>
            <?php echo $is_edit ? 'Kelola Detail Pedagang' : 'Manajemen Pedagang UMKM'; ?>
        </h1>
        <?php if(!$is_edit): ?>
            <a href="?page=dw-pedagang&action=new" class="page-title-action">Tambah Pedagang</a>
        <?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if (!$is_edit): ?>
            <!-- ================= TABLE VIEW ================= -->
            <div class="dw-card">
                <table class="wp-list-table widefat fixed striped table-p">
                    <thead>
                        <tr>
                            <th width="260">Toko & Pemilik</th>
                            <th>Referral / Verifikator</th>
                            <th>Wilayah & Desa</th>
                            <th width="140">Status & Audit</th>
                            <th width="90">Sisa Kuota</th>
                            <th width="100" style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = $wpdb->get_results("
                            SELECT p.*, v.nama_lengkap as verifikator_nama, d.nama_desa as mitra_desa_nama
                            FROM $table_name p 
                            LEFT JOIN $table_verifikator v ON p.kode_referal_digunakan = v.kode_referal 
                            LEFT JOIN $table_desa d ON p.id_desa = d.id
                            ORDER BY p.created_at DESC LIMIT 100
                        ");
                        if($rows): foreach($rows as $r): 
                            $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}";
                        ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_toko); ?></a></strong><br>
                                <small><?php echo esc_html($r->nama_pemilik); ?> • <?php echo $r->nomor_wa; ?></small>
                            </td>
                            <td>
                                <?php if($r->kode_referal_digunakan): ?>
                                    <span class="badge-referral"><?php echo $r->kode_referal_digunakan; ?></span>
                                    <div style="font-size:11px; margin-top:5px; color:#666;">
                                        Oleh: <strong><?php echo $r->verifikator_nama ? esc_html($r->verifikator_nama) : 'Kode Tidak Valid'; ?></strong>
                                    </div>
                                <?php else: ?>
                                    <small style="color:#999;">Tanpa Referral</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:12px;">
                                    <strong><?php echo esc_html($r->kelurahan_nama); ?></strong>, <?php echo esc_html($r->kecamatan_nama); ?><br>
                                    <?php if($r->id_desa > 0): ?>
                                        <span style="color:#2271b1; font-weight:600;">Mitra: <?php echo esc_html($r->mitra_desa_nama); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-<?php echo $r->status_akun; ?>"><?php echo strtoupper($r->status_akun); ?></span>
                                <?php if($r->verifier_role): ?>
                                    <div style="margin-top:5px;"><span class="badge-verifier">Verified By <?php echo strtoupper($r->verifier_role); ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo number_format($r->sisa_transaksi); ?></strong></td>
                            <td style="text-align:right;">
                                <a href="<?php echo $edit_url; ?>" class="button button-small">Kelola</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus permanen?');">
                                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                                    <input type="hidden" name="action_pedagang" value="delete">
                                    <input type="hidden" name="pedagang_id" value="<?php echo $r->id; ?>">
                                    <button type="submit" class="button button-small"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px;">Belum ada data pedagang.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- ================= FORM EDIT (VERTICAL TABS) ================= -->
            <form method="post" id="dw-pedagang-form">
                <?php wp_nonce_field('dw_pedagang_action'); ?>
                <input type="hidden" name="action_pedagang" value="save">
                <?php if ($edit_data): ?><input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                <div class="dw-card dw-tabs-layout">
                    <!-- Navigasi Samping -->
                    <ul class="dw-tabs-nav">
                        <li class="dw-tab-trigger active" data-target="tab-identitas"><span class="dashicons dashicons-store"></span> Identitas Toko</li>
                        <li class="dw-tab-trigger" data-target="tab-wilayah"><span class="dashicons dashicons-location"></span> Wilayah & Desa</li>
                        <li class="dw-tab-trigger" data-target="tab-referral"><span class="dashicons dashicons-awards"></span> Referral & Status</li>
                        <li class="dw-tab-trigger" data-target="tab-ongkir"><span class="dashicons dashicons-location-alt"></span> Ongkir Lokal</li>
                        <li class="dw-tab-trigger" data-target="tab-legalitas"><span class="dashicons dashicons-id-alt"></span> Legalitas & Media</li>
                        <li class="dw-tab-trigger" data-target="tab-keuangan"><span class="dashicons dashicons-money-alt"></span> Keuangan & QRIS</li>
                        <li style="padding:20px; border:none; margin-top:auto;">
                            <button type="submit" class="button button-primary button-large" style="width:100%; height:45px;">Simpan Data</button>
                            <a href="?page=dw-pedagang" class="button button-large" style="width:100%; margin-top:10px; text-align:center;">Batal</a>
                        </li>
                    </ul>

                    <!-- Konten Tab -->
                    <div class="dw-tab-content">
                        <!-- Identitas -->
                        <div id="tab-identitas" class="dw-tab-pane active">
                            <h3>Informasi Toko UMKM</h3>
                            <div class="dw-form-group">
                                <label>Akun WordPress Pemilik <span class="required">*</span></label>
                                <select name="id_user_pedagang" class="dw-input select2">
                                    <?php foreach($users as $u) echo '<option value="'.$u->ID.'" '.selected($edit_data->id_user ?? 0, $u->ID, false).'>'.$u->display_name.' ('.$u->user_email.')</option>'; ?>
                                </select>
                            </div>
                            <div style="display:flex; gap:15px;">
                                <div class="dw-form-group" style="flex:1;">
                                    <label>Nama Toko</label>
                                    <input type="text" name="nama_toko" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="dw-input">
                                </div>
                                <div class="dw-form-group" style="flex:1;">
                                    <label>Nama Pemilik</label>
                                    <input type="text" name="nama_pemilik" value="<?php echo esc_attr($edit_data->nama_pemilik ?? ''); ?>" class="dw-input">
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Nomor WhatsApp</label>
                                <input type="text" name="nomor_wa" value="<?php echo esc_attr($edit_data->nomor_wa ?? ''); ?>" class="dw-input">
                            </div>
                        </div>

                        <!-- Wilayah & Desa (CASCADING API) -->
                        <div id="tab-wilayah" class="dw-tab-pane">
                            <h3>Lokasi & Relasi Administratif</h3>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                <div class="dw-form-group">
                                    <label>Provinsi</label>
                                    <select name="pedagang_prov" class="dw-region-prov dw-input" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>">
                                        <option value="">Pilih Provinsi...</option>
                                        <?php 
                                        if (class_exists('DW_Address_API')) {
                                            foreach(DW_Address_API::get_provinces() as $p) echo '<option value="'.$p['id'].'" '.selected($edit_data->api_provinsi_id ?? '', $p['id'], false).'>'.$p['name'].'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="dw-form-group">
                                    <label>Kota / Kabupaten</label>
                                    <select name="pedagang_kota" class="dw-region-kota dw-input" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>"></select>
                                </div>
                                <div class="dw-form-group">
                                    <label>Kecamatan</label>
                                    <select name="pedagang_kec" class="dw-region-kec dw-input" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>"></select>
                                </div>
                                <div class="dw-form-group">
                                    <label>Kelurahan / Desa <span class="required">*</span></label>
                                    <select name="pedagang_nama_id" class="dw-region-desa dw-input" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>"></select>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                <div class="dw-form-group">
                                    <label>Kode Pos</label>
                                    <input type="text" name="kode_pos" value="<?php echo esc_attr($edit_data->kode_pos ?? ''); ?>" class="dw-input" placeholder="Misal: 40123">
                                </div>
                            </div>

                            <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi_nama ?? ''); ?>">
                            <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten_nama ?? ''); ?>">
                            <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan_nama ?? ''); ?>">
                            <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan_nama ?? ''); ?>">

                            <div class="dw-form-group">
                                <label>Alamat Lengkap Detail</label>
                                <textarea name="pedagang_detail" class="dw-input" rows="4"><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                            </div>
                            <div class="dw-form-group">
                                <label>URL Google Maps</label>
                                <input type="url" name="url_gmaps" value="<?php echo esc_attr($edit_data->url_gmaps ?? ''); ?>" class="dw-input">
                            </div>
                        </div>

                        <!-- Referral & Status -->
                        <div id="tab-referral" class="dw-tab-pane">
                            <h3>Status Akun & Referral</h3>
                            <div style="background:#fff1f2; padding:25px; border-radius:12px; border:1px dashed #fecdd3; margin-bottom:25px;">
                                <label style="font-weight:700; color:#991b1b; display:block; margin-bottom:10px;">KODE REFERRAL VERIFIKATOR UMKM</label>
                                <input type="text" name="kode_referal" value="<?php echo esc_attr($edit_data->kode_referal_digunakan ?? ''); ?>" class="dw-input" style="text-transform:uppercase; font-size:22px; font-weight:700; letter-spacing:4px; text-align:center; color:#e11d48;" placeholder="MISAL: BDG01">
                            </div>

                            <div style="display:flex; gap:20px;">
                                <div class="dw-form-group" style="flex:1;">
                                    <label>Status Akun</label>
                                    <select name="status_akun" class="dw-input" style="font-weight:700;">
                                        <option value="nonaktif" <?php selected($edit_data->status_akun ?? '', 'nonaktif'); ?>>NONAKTIF</option>
                                        <option value="aktif" <?php selected($edit_data->status_akun ?? '', 'aktif'); ?>>AKTIF (SUDAH DIVERIFIKASI)</option>
                                        <option value="suspend" <?php selected($edit_data->status_akun ?? '', 'suspend'); ?>>SUSPEND (BLOKIR)</option>
                                    </select>
                                </div>
                                <div class="dw-form-group" style="flex:1;">
                                    <label>Kuota Transaksi</label>
                                    <input type="number" name="sisa_transaksi" value="<?php echo esc_attr($edit_data->sisa_transaksi ?? 0); ?>" class="dw-input">
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Status Alur Pendaftaran</label>
                                <select name="status_pendaftaran" class="dw-input">
                                    <option value="menunggu_desa" <?php selected($edit_data->status_pendaftaran ?? '', 'menunggu_desa'); ?>>Menunggu Verifikasi Desa</option>
                                    <option value="disetujui" <?php selected($edit_data->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                                    <option value="ditolak" <?php selected($edit_data->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ongkir Lokal (LOGIKA ZONA KOMPLEKS) -->
                        <div id="tab-ongkir" class="dw-tab-pane">
                            <h3>Pengaturan Ongkir Ojek Lokal</h3>
                            <div style="padding:15px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                                <label class="dw-toggle-switch">
                                    <input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked($edit_data->shipping_ojek_lokal_aktif ?? 0, 1); ?>>
                                    <span class="slider"></span>
                                    <span class="label-text" style="font-weight:700;">Aktifkan Layanan Ojek Lokal</span>
                                </label>
                            </div>
                            
                            <!-- Zona 1: Satu Kecamatan -->
                            <div class="dw-zone-card">
                                <h4 class="dw-zone-title">Zona 1: Dalam Satu Kecamatan</h4>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                    <div>
                                        <span class="subzone-label">Desa Area Dekat</span>
                                        <label>Tarif (Rp)</label>
                                        <input type="number" name="ojek_dekat_harga" value="<?php echo $ojek_zona['satu_kecamatan']['dekat']['harga'] ?? ''; ?>" class="dw-input" style="margin-bottom:10px;">
                                        <label>Pilih Desa</label>
                                        <select name="ojek_dekat_desa_ids[]" class="dw-input select2-villages" multiple="multiple">
                                            <?php if(!empty($ojek_zona['satu_kecamatan']['dekat']['desa_ids'])){
                                                foreach($ojek_zona['satu_kecamatan']['dekat']['desa_ids'] as $vid) echo "<option value='$vid' selected>$vid</option>";
                                            } ?>
                                        </select>
                                    </div>
                                    <div>
                                        <span class="subzone-label">Desa Area Jauh</span>
                                        <label>Tarif (Rp)</label>
                                        <input type="number" name="ojek_jauh_harga" value="<?php echo $ojek_zona['satu_kecamatan']['jauh']['harga'] ?? ''; ?>" class="dw-input" style="margin-bottom:10px;">
                                        <label>Pilih Desa</label>
                                        <select name="ojek_jauh_desa_ids[]" class="dw-input select2-villages" multiple="multiple">
                                            <?php if(!empty($ojek_zona['satu_kecamatan']['jauh']['desa_ids'])){
                                                foreach($ojek_zona['satu_kecamatan']['jauh']['desa_ids'] as $vid) echo "<option value='$vid' selected>$vid</option>";
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Zona 2: Luar Kecamatan -->
                            <div class="dw-zone-card">
                                <h4 class="dw-zone-title">Zona 2: Luar Kecamatan (Kabupaten Sama)</h4>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                    <div>
                                        <span class="subzone-label">Kecamatan Dekat</span>
                                        <label>Tarif (Rp)</label>
                                        <input type="number" name="ojek_beda_kec_dekat_harga" value="<?php echo $ojek_zona['beda_kecamatan']['dekat']['harga'] ?? ''; ?>" class="dw-input" style="margin-bottom:10px;">
                                        <label>Pilih Kecamatan</label>
                                        <select name="ojek_beda_kec_dekat_ids[]" class="dw-input select2-districts" multiple="multiple">
                                            <?php if(!empty($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'])){
                                                foreach($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'] as $kid) echo "<option value='$kid' selected>$kid</option>";
                                            } ?>
                                        </select>
                                    </div>
                                    <div>
                                        <span class="subzone-label">Kecamatan Jauh</span>
                                        <label>Tarif (Rp)</label>
                                        <input type="number" name="ojek_beda_kec_jauh_harga" value="<?php echo $ojek_zona['beda_kecamatan']['jauh']['harga'] ?? ''; ?>" class="dw-input" style="margin-bottom:10px;">
                                        <label>Pilih Kecamatan</label>
                                        <select name="ojek_beda_kec_jauh_ids[]" class="dw-input select2-districts" multiple="multiple">
                                            <?php if(!empty($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'])){
                                                foreach($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'] as $kid) echo "<option value='$kid' selected>$kid</option>";
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Media -->
                        <div id="tab-legalitas" class="dw-tab-pane">
                            <h3>Identitas & Media</h3>
                            <div class="dw-form-group">
                                <label>NIK Pemilik</label>
                                <input type="text" name="nik" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="dw-input" maxlength="16">
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                <div class="dw-form-group">
                                    <label>Foto Profil / Logo</label>
                                    <img id="prev_profil" src="<?php echo esc_url($edit_data->foto_profil ?? ''); ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover; display:block; margin-bottom:10px; border:1px solid #ddd;">
                                    <input type="hidden" name="foto_profil" id="f_profil" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>">
                                    <button type="button" class="button btn_upload" data-target="#f_profil" data-preview="#prev_profil">Pilih Gambar</button>
                                </div>
                                <div class="dw-form-group">
                                    <label>Foto Sampul Toko</label>
                                    <img id="prev_sampul" src="<?php echo esc_url($edit_data->foto_sampul ?? ''); ?>" style="width:100%; height:80px; border-radius:8px; object-fit:cover; display:block; margin-bottom:10px; border:1px solid #ddd;">
                                    <input type="hidden" name="foto_sampul" id="f_sampul" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>">
                                    <button type="button" class="button btn_upload" data-target="#f_sampul" data-preview="#prev_sampul">Pilih Gambar</button>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Foto KTP (Legalitas)</label>
                                <input type="text" name="url_ktp" id="f_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>" class="dw-input" readonly>
                                <button type="button" class="button btn_upload" data-target="#f_ktp">Pilih File KTP</button>
                            </div>
                        </div>

                        <!-- Keuangan -->
                        <div id="tab-keuangan" class="dw-tab-pane">
                            <h3>Informasi Pembayaran</h3>
                            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:15px;">
                                <div class="dw-form-group">
                                    <label>Nama Bank</label>
                                    <input type="text" name="nama_bank" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="dw-input">
                                </div>
                                <div class="dw-form-group">
                                    <label>Nomor Rekening</label>
                                    <input type="text" name="no_rekening" value="<?php echo esc_attr($edit_data->no_rekening ?? ''); ?>" class="dw-input">
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Atas Nama Rekening</label>
                                <input type="text" name="atas_nama_rekening" value="<?php echo esc_attr($edit_data->atas_nama_rekening ?? ''); ?>" class="dw-input">
                            </div>
                            <div class="dw-form-group">
                                <label>URL Gambar QRIS</label>
                                <input type="text" name="qris_image_url" id="f_qris" value="<?php echo esc_attr($edit_data->qris_image_url ?? ''); ?>" class="dw-input">
                                <button type="button" class="button btn_upload" data-target="#f_qris" style="margin-top:5px;">Upload QRIS</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($){
        // A. Tab Switcher
        $('.dw-tab-trigger').on('click', function(){
            $('.dw-tab-trigger').removeClass('active');
            $(this).addClass('active');
            $('.dw-tab-pane').removeClass('active');
            $('#' + $(this).data('target')).addClass('active');
            if($(this).data('target') === 'tab-ongkir') {
                $('.select2-villages, .select2-districts').select2({ width: '100%', placeholder: 'Pilih Wilayah' });
            }
        });

        // B. Media Uploader
        $(document).on('click', '.btn_upload', function(e){
            e.preventDefault();
            var button = $(this), target = button.data('target'), preview = button.data('preview');
            var frame = wp.media({ title: 'Pilih Media', multiple: false, library: { type: 'image' } });
            frame.on('select', function(){
                var url = frame.state().get('selection').first().toJSON().url;
                $(target).val(url);
                if(preview) $(preview).attr('src', url);
            }).open();
        });

        // C. Cascading Address Logic (Integrasi Address API)
        function loadRegion(type, parentId, $target, selectedId) {
            $target.html('<option value="">Memuat...</option>').prop('disabled', true);
            var action = (type==='kota')?'dw_fetch_regencies':((type==='kec')?'dw_fetch_districts':'dw_fetch_villages');
            var data = { action: action, nonce: '<?php echo wp_create_nonce("dw_region_nonce"); ?>' };
            if(type==='kota') data.province_id = parentId;
            if(type==='kec') data.regency_id = parentId;
            if(type==='desa') data.district_id = parentId;

            $.get(ajaxurl, data, function(res){
                $target.html('<option value="">Pilih...</option>').prop('disabled', false);
                if(res.success && res.data) {
                    $.each(res.data, function(i, v){
                        $target.append('<option value="'+v.id+'" '+(v.id==selectedId?'selected':'')+'>'+v.name+'</option>');
                    });
                    $target.trigger('change');
                }
            });
        }

        $('.dw-region-prov').on('change', function(){
            $('.dw-text-prov').val($(this).find('option:selected').text());
            loadRegion('kota', $(this).val(), $('.dw-region-kota'), $('.dw-region-kota').data('current'));
        });
        $('.dw-region-kota').on('change', function(){
            $('.dw-text-kota').val($(this).find('option:selected').text());
            loadRegion('kec', $(this).val(), $('.dw-region-kec'), $('.dw-region-kec').data('current'));
            loadOngkirOptions(true); 
        });
        $('.dw-region-kec').on('change', function(){
            $('.dw-text-kec').val($(this).find('option:selected').text());
            loadRegion('desa', $(this).val(), $('.dw-region-desa'), $('.dw-region-desa').data('current'));
            loadOngkirOptions(true); 
        });
        $('.dw-region-desa').on('change', function(){
            $('.dw-text-desa').val($(this).find('option:selected').text());
        });

        // D. Ongkir Zone Logic (Village & District Loading)
        function loadOngkirOptions(force = false) {
            var kecId = $('select[name="pedagang_kec"]').val() || $('select[name="pedagang_kec"]').data('current');
            var kabId = $('select[name="pedagang_kota"]').val() || $('select[name="pedagang_kota"]').data('current');
            var $vSels = $('.select2-villages'), $dSels = $('.select2-districts');

            if (kecId && (force || kecId != $vSels.data('p'))) {
                $.get(ajaxurl, { action:'dw_fetch_villages', district_id:kecId, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
                    if(res.success){
                        $vSels.each(function(){
                            var $s = $(this), val = $s.val() || []; $s.empty();
                            $.each(res.data, function(i,v){ $s.append(new Option(v.name, v.id, val.includes(v.id.toString()), val.includes(v.id.toString()))); });
                            $s.trigger('change').data('p', kecId);
                        });
                        syncDesaExclusion();
                    }
                });
            }
            if (kabId && (force || kabId != $dSels.data('p'))) {
                $.get(ajaxurl, { action:'dw_fetch_districts', regency_id:kabId, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
                    if(res.success){
                        $dSels.each(function(){
                            var $s = $(this), val = $s.val() || []; $s.empty();
                            $.each(res.data, function(i,v){ $s.append(new Option(v.name, v.id, val.includes(v.id.toString()), val.includes(v.id.toString()))); });
                            $s.trigger('change').data('p', kabId);
                        });
                        syncKecamatanExclusion();
                    }
                });
            }
        }

        function syncDesaExclusion() {
            var vNear = $('select[name="ojek_dekat_desa_ids[]"]').val() || [], vFar = $('select[name="ojek_jauh_desa_ids[]"]').val() || [];
            $('select[name="ojek_jauh_desa_ids[]"] option').each(function(){ $(this).prop('disabled', vNear.includes($(this).val())); });
            $('select[name="ojek_dekat_desa_ids[]"] option').each(function(){ $(this).prop('disabled', vFar.includes($(this).val())); });
        }
        function syncKecamatanExclusion() {
            var vNear = $('select[name="ojek_beda_kec_dekat_ids[]"]').val() || [], vFar = $('select[name="ojek_beda_kec_jauh_ids[]"]').val() || [];
            $('select[name="ojek_beda_kec_jauh_ids[]"] option').each(function(){ $(this).prop('disabled', vNear.includes($(this).val())); });
            $('select[name="ojek_beda_kec_dekat_ids[]"] option').each(function(){ $(this).prop('disabled', vFar.includes($(this).val())); });
        }

        $('.select2-villages').on('change', syncDesaExclusion);
        $('.select2-districts').on('change', syncKecamatanExclusion);

        // Initial Load for Edit Mode
        if($('.dw-region-prov').val()) $('.dw-region-prov').trigger('change');
    });
    </script>
    <?php
}