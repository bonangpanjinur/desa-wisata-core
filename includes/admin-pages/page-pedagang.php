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
            echo '<div class="notice notice-error is-dismissible"><p>Keamanan tidak valid (Nonce Failed).</p></div>'; 
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
                
                // PENGIRIMAN
                'shipping_ojek_lokal_aktif' => $shipping_ojek,
                'shipping_ojek_lokal_zona'  => json_encode($ojek_zona_data),
                'shipping_nasional_aktif'   => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,
                'shipping_nasional_harga'   => floatval($_POST['shipping_nasional_harga']), // New Field
                'allow_pesan_di_tempat'     => isset($_POST['allow_pesan_di_tempat']) ? 1 : 0,
                
                // WILAYAH
                'api_provinsi_id'  => sanitize_text_field($_POST['pedagang_prov']),
                'api_kabupaten_id' => sanitize_text_field($_POST['pedagang_kota']),
                'api_kecamatan_id' => sanitize_text_field($_POST['pedagang_kec']),
                'api_kelurahan_id' => $kelurahan_id,
                'provinsi_nama'    => sanitize_text_field($_POST['provinsi_text']),
                'kabupaten_nama'   => sanitize_text_field($_POST['kabupaten_text']),
                'kecamatan_nama'   => sanitize_text_field($_POST['kecamatan_text']),
                'kelurahan_nama'   => sanitize_text_field($_POST['kelurahan_text']),
                'kode_pos'         => sanitize_text_field($_POST['kode_pos']), // New Field
                
                'updated_at'       => current_time('mysql')
            ];

            if (!empty($_POST['pedagang_id'])) {
                $result = $wpdb->update($table_name, $data, ['id' => intval($_POST['pedagang_id'])]);
                $message = 'Data pedagang berhasil diperbarui.'; $message_type = 'success';
            } else {
                $data['created_at'] = current_time('mysql');
                // Generate Referral Code for New Store (Simple Logic)
                $data['kode_referral_saya'] = 'REF' . strtoupper(substr($nama_toko, 0, 3)) . rand(1000, 9999);
                
                $result = $wpdb->insert($table_name, $data);
                $message = 'Pedagang baru berhasil ditambahkan.'; $message_type = 'success';
            }
        }
    }

    // --- PREPARE DATA ---
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
            'satu_kecamatan' => ['dekat' => [], 'jauh' => []],
            'beda_kecamatan' => ['dekat' => [], 'jauh' => []]
        ];
    }
    
    $users = get_users(['role__in' => ['administrator', 'pedagang', 'subscriber']]);

    // Statistik Dashboard
    $total_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
    $with_desa_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE id_desa > 0");
    $independent_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE id_desa = 0");
    $total_transaksi = $wpdb->get_var("SELECT SUM(sisa_transaksi) FROM $table_name");

    ?>
    <style>
        :root { --dw-primary: #2271b1; --dw-success: #00ba37; --dw-warning: #f5a623; --dw-danger: #d63638; --dw-border: #c3c4c7; --dw-bg: #f0f0f1; }
        .dw-admin-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; margin: 20px 20px 0 0; }
        .dw-card { background: #fff; border: 1px solid var(--dw-border); border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; overflow: hidden; }
        .dw-card-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .dw-card-header h3 { margin: 0; font-size: 16px; font-weight: 600; color: #1d2327; }
        .dw-card-body { padding: 20px; }
        .dw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .dw-stat-card { background: #fff; border: 1px solid var(--dw-border); border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 15px; }
        .dw-stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .dw-stat-icon.blue { background: #e7f3ff; color: #2271b1; }
        .dw-stat-icon.green { background: #e6f6eb; color: #00ba37; }
        .dw-stat-icon.orange { background: #fff4e5; color: #f5a623; }
        .dw-stat-icon.purple { background: #f3e8fd; color: #8a3ab9; }
        .dw-stat-info .dw-stat-label { display: block; font-size: 12px; color: #646970; margin-bottom: 2px; }
        .dw-stat-info .dw-stat-number { display: block; font-size: 20px; font-weight: 700; color: #1d2327; }
        .dw-btn-primary { background: var(--dw-primary); color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .dw-btn-primary:hover { background: #135e96; color: #fff; }
        .dw-form-group { margin-bottom: 20px; }
        .dw-form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; }
        .dw-form-control { width: 100%; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 6px; font-size: 14px; }
        .dw-form-control:focus { border-color: var(--dw-primary); box-shadow: 0 0 0 1px var(--dw-primary); outline: none; }
        .dw-row { display: flex; gap: 20px; margin-bottom: 0; }
        .dw-col-6 { flex: 1; }
        .dw-col-4 { width: 33.33%; }
        .dw-col-8 { width: 66.66%; }
        .dw-tabs-nav { display: flex; gap: 5px; margin-bottom: -1px; border-bottom: 1px solid var(--dw-border); padding: 0 20px; }
        .dw-tab-link { padding: 12px 20px; cursor: pointer; border: 1px solid transparent; border-bottom: none; border-radius: 6px 6px 0 0; font-weight: 600; color: #646970; transition: 0.2s; }
        .dw-tab-link.active { background: #fff; border-color: var(--dw-border); color: var(--dw-primary); }
        .dw-tab-pane { display: none; padding: 25px 0; }
        .dw-tab-pane.active { display: block; }
        .dw-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-aktif { background: #e6f6eb; color: #00ba37; }
        .badge-nonaktif { background: #fcf0f1; color: #d63638; }
        .badge-pending { background: #fff4e5; color: #f5a623; }
        .dw-toggle-switch { position: relative; display: inline-flex; align-items: center; cursor: pointer; gap: 10px; }
        .dw-toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: relative; display: inline-block; width: 40px; height: 20px; background-color: #ccc; transition: .4s; border-radius: 20px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--dw-primary); }
        input:checked + .slider:before { transform: translateX(20px); }
    </style>

    <div class="wrap dw-admin-wrap">
        <div class="dw-header-action">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-store" style="font-size: 32px; width: 32px; height: 32px; color: var(--dw-primary);"></span>
                <?php _e('Manajemen Pedagang', 'desa-wisata-core'); ?>
            </h1>
            <?php if(!$is_edit): ?>
                <a href="<?php echo esc_url(add_query_arg(array('action' => 'new'), admin_url('admin.php?page=dw-pedagang'))); ?>" class="dw-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php _e('Tambah Pedagang', 'desa-wisata-core'); ?>
                </a>
            <?php endif; ?>
        </div>

        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="margin-top: 20px; border-radius: 4px;">
                <p><strong><?php echo $message; ?></strong></p>
            </div>
        <?php endif; ?>

        <?php if (!$is_edit): ?>
            <div class="dw-stats-grid">
                <div class="dw-stat-card">
                    <div class="dw-stat-icon blue"><span class="dashicons dashicons-groups"></span></div>
                    <div class="dw-stat-info">
                        <span class="dw-stat-label">Total Pedagang</span>
                        <span class="dw-stat-number"><?php echo number_format($total_pedagang); ?></span>
                    </div>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon green"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="dw-stat-info">
                        <span class="dw-stat-label">Mitra Desa</span>
                        <span class="dw-stat-number"><?php echo number_format($with_desa_count); ?></span>
                    </div>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon orange"><span class="dashicons dashicons-admin-users"></span></div>
                    <div class="dw-stat-info">
                        <span class="dw-stat-label">Independen</span>
                        <span class="dw-stat-number"><?php echo number_format($independent_count); ?></span>
                    </div>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon purple"><span class="dashicons dashicons-chart-pie"></span></div>
                    <div class="dw-stat-info">
                        <span class="dw-stat-label">Total Kuota</span>
                        <span class="dw-stat-number"><?php echo $total_transaksi; ?></span>
                    </div>
                </div>
            </div>

            <div class="dw-card">
                <div class="dw-card-header">
                    <div>
                        <h3><?php _e('Daftar Pedagang', 'desa-wisata-core'); ?></h3>
                        <p class="description">Kelola data toko, lokasi, dan status keaktifan pedagang.</p>
                    </div>
                </div>
                <div class="dw-card-body" style="padding: 0;">
                    <?php 
                        $per_page = 10;
                        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                        $offset = ($paged - 1) * $per_page;
                        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
                        $where_sql = "WHERE 1=1";
                        if (!empty($search)) {
                            $where_sql .= $wpdb->prepare(" AND (p.nama_toko LIKE %s OR p.kelurahan_nama LIKE %s)", "%$search%", "%$search%");
                        }
                        $sql = "SELECT p.*, d.nama_desa, u.display_name as owner_name, v.nama_lengkap as nama_verifikator
                                FROM $table_name p
                                LEFT JOIN $table_desa d ON p.id_desa = d.id
                                LEFT JOIN $table_users u ON p.id_user = u.ID
                                LEFT JOIN $table_verifikator v ON p.id_verifikator = v.id
                                $where_sql 
                                ORDER BY p.created_at DESC 
                                LIMIT $per_page OFFSET $offset";
                        $rows = $wpdb->get_results($sql);
                        $total_items = $wpdb->get_var("SELECT COUNT(p.id) FROM $table_name p $where_sql");
                        $total_pages = ceil($total_items / $per_page);
                    ?>
                    <div class="dw-table-toolbar" style="padding: 15px 20px;">
                        <form method="get">
                            <input type="hidden" name="page" value="dw-pedagang">
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Nama Toko / Kelurahan..." class="dw-form-control" style="display:inline-block; width:300px;">
                            <button type="submit" class="button button-primary">Cari</button>
                        </form>
                    </div>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="80">Foto</th>
                                <th>Info Toko</th>
                                <th>Lokasi & Afiliasi</th>
                                <th width="140">Status</th>
                                <th width="100">Kuota</th>
                                <th width="120" style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($rows): foreach($rows as $row): ?>
                            <tr>
                                <td><img src="<?php echo esc_url($row->foto_profil ?: 'https://placehold.co/60x60?text=Toko'); ?>" style="width:50px; height:50px; border-radius:8px; object-fit:cover;"></td>
                                <td>
                                    <strong><?php echo esc_html($row->nama_toko); ?></strong><br>
                                    <small><?php echo esc_html($row->nama_pemilik); ?> (<?php echo esc_html($row->owner_name); ?>)</small>
                                </td>
                                <td>
                                    <small><?php echo esc_html($row->kelurahan_nama ?: '-'); ?>, <?php echo esc_html($row->kecamatan_nama ?: '-'); ?></small><br>
                                    <small>Verifikator: <?php echo esc_html($row->nama_verifikator ?: '-'); ?></small>
                                </td>
                                <td>
                                    <span class="dw-badge badge-<?php echo $row->status_akun; ?>"><?php echo $row->status_akun; ?></span>
                                </td>
                                <td><strong><?php echo $row->sisa_transaksi; ?></strong></td>
                                <td style="text-align:right;">
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'id' => $row->id), admin_url('admin.php?page=dw-pedagang'))); ?>" class="button">Edit</a>
                                    <form method="post" style="display:inline-block;" onsubmit="return confirm('Hapus pedagang ini?');">
                                        <?php wp_nonce_field('dw_pedagang_action'); ?>
                                        <input type="hidden" name="action_pedagang" value="delete">
                                        <input type="hidden" name="pedagang_id" value="<?php echo $row->id; ?>">
                                        <button type="submit" class="button button-link-delete">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6">Belum ada data pedagang.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('dw_pedagang_action'); ?>
                <input type="hidden" name="action_pedagang" value="save">
                <input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id ?? ''; ?>">
                
                <div class="dw-tabs-nav">
                    <div class="dw-tab-link active" data-target="#tab-umum">Informasi Umum</div>
                    <div class="dw-tab-link" data-target="#tab-lokasi">Lokasi & Alamat</div>
                    <div class="dw-tab-link" data-target="#tab-visual">Visual & Legalitas</div>
                    <div class="dw-tab-link" data-target="#tab-keuangan">Keuangan</div>
                    <div class="dw-tab-link" data-target="#tab-pengaturan">Pengaturan & Ongkir</div>
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
                                        <select name="pedagang_prov" class="dw-form-control">
                                            <option value="">Pilih Provinsi...</option>
                                            <?php if(class_exists('DW_Address_API')): $provs = DW_Address_API::get_provinces(); foreach($provs as $v): ?>
                                                <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_provinsi_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" name="provinsi_text" value="<?php echo esc_attr($edit_data->provinsi_nama ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Kota/Kabupaten</label>
                                        <select name="pedagang_kota" class="dw-form-control">
                                            <option value="">Pilih Kota...</option>
                                            <?php if($edit_data && !empty($edit_data->api_provinsi_id) && class_exists('DW_Address_API')): $cities = DW_Address_API::get_cities($edit_data->api_provinsi_id); foreach($cities as $v): ?>
                                                <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kabupaten_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" name="kabupaten_text" value="<?php echo esc_attr($edit_data->kabupaten_nama ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Kecamatan</label>
                                        <select name="pedagang_kec" class="dw-form-control">
                                            <option value="">Pilih Kecamatan...</option>
                                            <?php if($edit_data && !empty($edit_data->api_kabupaten_id) && class_exists('DW_Address_API')): $districts = DW_Address_API::get_subdistricts($edit_data->api_kabupaten_id); foreach($districts as $v): ?>
                                                <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kecamatan_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" name="kecamatan_text" value="<?php echo esc_attr($edit_data->kecamatan_nama ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Kelurahan/Desa</label>
                                        <select name="pedagang_nama_id" class="dw-form-control">
                                            <option value="">Pilih Kelurahan...</option>
                                            <?php if($edit_data && !empty($edit_data->api_kecamatan_id) && class_exists('DW_Address_API')): $villages = DW_Address_API::get_villages($edit_data->api_kecamatan_id); foreach($villages as $v): ?>
                                                <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kelurahan_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" name="kelurahan_text" value="<?php echo esc_attr($edit_data->kelurahan_nama ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Alamat Lengkap</label>
                                <textarea name="pedagang_detail" class="dw-form-control" rows="3"><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                            </div>
                            <div class="dw-form-group">
                                <label>Kode Pos</label>
                                <input name="kode_pos" type="text" value="<?php echo esc_attr($edit_data->kode_pos ?? ''); ?>" class="dw-form-control">
                            </div>
                        </div>

                        <div id="tab-visual" class="dw-tab-pane">
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Foto Profil Toko</label>
                                        <div class="dw-media-uploader">
                                            <div style="margin-bottom:10px;">
                                                <img id="prev_foto_profil" src="<?php echo esc_url($edit_data->foto_profil ?? 'https://placehold.co/150x150?text=Profil'); ?>" style="max-width:150px; border-radius:12px; border:1px solid #ddd;">
                                            </div>
                                            <div style="display:flex; gap:10px;">
                                                <input type="text" name="foto_profil" id="foto_profil" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>" class="dw-form-control" readonly>
                                                <button type="button" class="button btn_upload" data-target="#foto_profil" data-preview="#prev_foto_profil">Pilih Foto</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Foto Sampul Toko</label>
                                        <div class="dw-media-uploader">
                                            <div style="margin-bottom:10px;">
                                                <img id="prev_foto_sampul" src="<?php echo esc_url($edit_data->foto_sampul ?? 'https://placehold.co/300x150?text=Sampul'); ?>" style="max-width:300px; border-radius:12px; border:1px solid #ddd;">
                                            </div>
                                            <div style="display:flex; gap:10px;">
                                                <input type="text" name="foto_sampul" id="foto_sampul" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>" class="dw-form-control" readonly>
                                                <button type="button" class="button btn_upload" data-target="#foto_sampul" data-preview="#prev_foto_sampul">Pilih Sampul</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>NIK Pemilik</label>
                                <input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="dw-form-control">
                            </div>
                            <div class="dw-form-group">
                                <label>Foto KTP</label>
                                <div class="dw-media-uploader">
                                    <div style="margin-bottom:10px;">
                                        <img id="prev_url_ktp" src="<?php echo esc_url($edit_data->url_ktp ?? 'https://placehold.co/200x120?text=KTP'); ?>" style="max-width:200px; border-radius:6px; border:1px solid #ddd;">
                                    </div>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>" class="dw-form-control" readonly>
                                        <button type="button" class="button btn_upload" data-target="#url_ktp" data-preview="#prev_url_ktp">Pilih File</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="tab-keuangan" class="dw-tab-pane">
                            <div class="dw-row">
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Nama Bank</label>
                                        <input name="nama_bank" type="text" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="dw-form-control">
                                    </div>
                                </div>
                                <div class="dw-col-6">
                                    <div class="dw-form-group">
                                        <label>Nama Pemilik</label>
                                        <input name="nama_pemilik" type="text" value="<?php echo esc_attr($edit_data->nama_pemilik ?? ''); ?>" class="dw-form-control">
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
                                <label>Atas Nama Rekening</label>
                                <input name="atas_nama_rekening" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening ?? ''); ?>" class="dw-form-control">
                            </div>
                            <div class="dw-form-group">
                                <label>QRIS Image URL</label>
                                <div class="dw-media-uploader">
                                    <div style="margin-bottom:10px;">
                                        <img id="prev_qris_image_url" src="<?php echo esc_url($edit_data->qris_image_url ?? 'https://placehold.co/150x150?text=QRIS'); ?>" style="max-width:150px; border-radius:6px; border:1px solid #ddd;">
                                    </div>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" name="qris_image_url" id="qris_image_url" value="<?php echo esc_attr($edit_data->qris_image_url ?? ''); ?>" class="dw-form-control" readonly>
                                        <button type="button" class="button btn_upload" data-target="#qris_image_url" data-preview="#prev_qris_image_url">Upload QRIS</button>
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

                // Region Logic (Simplified for this fix)
                $('select[name="pedagang_prov"]').change(function(){
                    var id = $(this).val();
                    var name = $(this).find('option:selected').text();
                    $('input[name="provinsi_text"]').val(name);
                    // In real app, trigger AJAX to load cities
                });
                // ... similar for other region selects

                // Media Upload Fix
                $(document).on('click', '.btn_upload', function(e){
                    e.preventDefault();
                    var target = $(this).data('target');
                    var preview = $(this).data('preview');
                    
                    if ( typeof wp === 'undefined' || ! wp.media ) { 
                        alert('Media uploader tidak tersedia.'); 
                        return; 
                    }
                    
                    var frame = wp.media({ 
                        title: 'Pilih Gambar', 
                        multiple: false, 
                        library: { type: 'image' } 
                    });
                    
                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        var url = attachment.url;
                        $(target).val(url);
                        if(preview && $(preview).length) {
                            $(preview).attr('src', url).show();
                        }
                    });
                    frame.open();
                });
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}
