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
            
            // Relasi Otomatis ke Desa berdasarkan Kelurahan
            $desa_terkait = $wpdb->get_row($wpdb->prepare(
                "SELECT id, nama_desa FROM $table_desa WHERE api_kelurahan_id = %s LIMIT 1",
                $kelurahan_id
            ));
            $id_desa = $desa_terkait ? $desa_terkait->id : 0;
            $is_independent = ($id_desa == 0) ? 1 : 0;

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
                        'harga' => floatval($_POST['ojek_dekat_harga']),
                        'desa_ids' => $safe_array_map($_POST['ojek_dekat_desa_ids'] ?? null)
                    ],
                    'jauh' => [
                        'harga' => floatval($_POST['ojek_jauh_harga']),
                        'desa_ids' => $safe_array_map($_POST['ojek_jauh_desa_ids'] ?? null)
                    ]
                ],
                'beda_kecamatan' => [
                    'dekat' => [
                        'harga' => floatval($_POST['ojek_beda_kec_dekat_harga']),
                        'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_dekat_ids'] ?? null)
                    ],
                    'jauh' => [
                        'harga' => floatval($_POST['ojek_beda_kec_jauh_harga']),
                        'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_jauh_ids'] ?? null)
                    ]
                ]
            ];

            // DATA UTAMA
            $data = [
                'id_user'          => $id_user,
                'id_desa'          => $id_desa,
                'is_independent'   => $is_independent,
                'nama_toko'        => $nama_toko,
                'slug_toko'        => sanitize_title($nama_toko),
                'nama_pemilik'     => sanitize_text_field($_POST['nama_pemilik']),
                'nomor_wa'         => sanitize_text_field($_POST['nomor_wa']),
                'alamat_lengkap'   => sanitize_textarea_field($_POST['pedagang_detail']),
                'url_gmaps'        => esc_url_raw($_POST['url_gmaps']),
                
                // LEGALITAS & PROFIL
                'nik'              => sanitize_text_field($_POST['nik']),
                'url_ktp'          => esc_url_raw($_POST['url_ktp']),
                'foto_profil'      => esc_url_raw($_POST['foto_profil']),
                'foto_sampul'      => esc_url_raw($_POST['foto_sampul']),
                
                // KEUANGAN
                'no_rekening'        => sanitize_text_field($_POST['no_rekening']),
                'nama_bank'          => sanitize_text_field($_POST['nama_bank']),
                'atas_nama_rekening' => sanitize_text_field($_POST['atas_nama_rekening']),
                'qris_image_url'     => esc_url_raw($_POST['qris_image_url']),
                
                // STATUS & SISTEM
                'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran']),
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
    $independent_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE is_independent = 1");
    $with_desa_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE is_independent = 0");
    $total_transaksi = $wpdb->get_var("SELECT SUM(sisa_transaksi) FROM $table_name");
    $total_transaksi = $total_transaksi ? number_format($total_transaksi) : '0';
    ?>

    <!-- CSS STYLING -->
    <style>
        :root {
            --dw-primary: #2271b1;
            --dw-primary-hover: #135e96;
            --dw-secondary: #f0f6fc;
            --dw-success: #00a32a;
            --dw-warning: #dba617;
            --dw-danger: #d63638;
            --dw-text-dark: #1d2327;
            --dw-text-gray: #646970;
            --dw-border: #dcdcde;
            --dw-card-bg: #ffffff;
            --dw-bg-body: #f0f0f1;
        }

        .dw-admin-wrap {
            max-width: 1200px;
            margin: 20px 20px 0 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }

        /* Header */
        .dw-header-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .dw-header-action h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: var(--dw-text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Buttons */
        .dw-btn-primary {
            background: var(--dw-primary);
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .dw-btn-primary:hover {
            background: var(--dw-primary-hover);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 113, 177, 0.2);
        }

        /* Stats Grid */
        .dw-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .dw-stat-card {
            background: var(--dw-card-bg);
            border: 1px solid var(--dw-border);
            border-radius: 12px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dw-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .dw-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .dw-stat-icon.blue { background: #eef6fc; color: var(--dw-primary); }
        .dw-stat-icon.green { background: #e7f6e9; color: var(--dw-success); }
        .dw-stat-icon.orange { background: #fff8e6; color: var(--dw-warning); }
        .dw-stat-icon.purple { background: #f6f0ff; color: #8e44ad; }
        
        .dw-stat-info { display: flex; flex-direction: column; }
        .dw-stat-label { font-size: 13px; color: var(--dw-text-gray); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 4px; }
        .dw-stat-number { font-size: 24px; font-weight: 700; color: var(--dw-text-dark); line-height: 1.2; }

        /* Card System */
        .dw-card {
            background: var(--dw-card-bg);
            border: 1px solid var(--dw-border);
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.03);
            margin-bottom: 25px;
            overflow: hidden;
        }
        .dw-card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--dw-bg-body);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
        }
        .dw-card-header h3 { margin: 0; font-size: 18px; font-weight: 600; color: var(--dw-text-dark); }
        .dw-card-header .description { margin: 5px 0 0; color: var(--dw-text-gray); }
        .dw-card-body { padding: 25px; }

        /* Form Layout */
        .dw-form-container { display: block; }
        .dw-form-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            align-items: start;
        }
        .dw-form-sidebar { position: sticky; top: 50px; }
        .dw-form-content { min-width: 0; }
        
        /* Tabs Navigation */
        .dw-tabs-nav { list-style: none; padding: 0; margin: 0; }
        .dw-tabs-nav li {
            padding: 14px 20px;
            margin-bottom: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--dw-text-gray);
            transition: all 0.2s;
            border-radius: 8px;
            font-weight: 500;
        }
        .dw-tabs-nav li:hover { background: var(--dw-bg-body); color: var(--dw-primary); }
        .dw-tabs-nav li.active {
            background: var(--dw-primary);
            color: #fff;
            box-shadow: 0 4px 10px rgba(34, 113, 177, 0.25);
        }
        .dw-tab-pane { display: none; }
        .dw-tab-pane.active { display: block; animation: fadeIn 0.3s ease-out; }
        
        /* Form Elements */
        .dw-form-group { margin-bottom: 20px; }
        .dw-form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dw-text-dark); font-size: 14px; }
        .dw-form-control {
            width: 100%;
            height: 42px;
            border: 1px solid var(--dw-border);
            border-radius: 6px;
            padding: 0 12px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            color: var(--dw-text-dark);
        }
        .dw-form-control:focus {
            border-color: var(--dw-primary);
            box-shadow: 0 0 0 1px var(--dw-primary);
            outline: none;
        }
        textarea.dw-form-control { height: auto; padding: 12px; min-height: 100px; resize: vertical; }
        .dw-form-control[readonly] { background-color: #f8f9fa; color: #7f8c8d; cursor: not-allowed; }
        
        .dw-row { display: flex; gap: 20px; margin-bottom: 0; }
        .dw-col-6 { flex: 0 0 calc(50% - 10px); }
        .dw-col-4 { flex: 0 0 calc(33.333% - 13.33px); }
        .dw-col-8 { flex: 0 0 calc(66.666% - 6.66px); }

        /* Media Upload */
        .dw-media-uploader {
            border: 2px dashed var(--dw-border);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            background: #fafafa;
        }
        .dw-media-preview-small img { border: 3px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        /* Status Badges */
        .dw-status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            line-height: 1;
        }
        .dw-status-verified { background: #e7f6e9; color: var(--dw-success); border: 1px solid #c3e6cb; }
        .dw-status-pending { background: #fff8e6; color: var(--dw-warning); border: 1px solid #ffeeba; }
        .dw-status-rejected { background: #fbeaea; color: var(--dw-danger); border: 1px solid #f5c6cb; }
        .dw-status-gray { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }

        /* Toggle Switch */
        .dw-toggle-switch { position: relative; display: inline-flex; align-items: center; gap: 12px; cursor: pointer; margin-right: 0; width: 100%; padding: 10px; border: 1px solid var(--dw-border); border-radius: 8px; transition: background 0.2s; }
        .dw-toggle-switch:hover { background: #f9f9f9; }
        .dw-toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .slider { position: relative; width: 46px; height: 26px; background-color: #ccc; transition: .4s; border-radius: 34px; flex-shrink: 0; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: var(--dw-primary); }
        input:checked + .slider:before { transform: translateX(20px); }
        .label-text { font-weight: 600; font-size: 14px; color: var(--dw-text-dark); }

        /* Table Enhancement */
        .dw-table-toolbar { padding: 20px; background: #fff; border-bottom: 1px solid var(--dw-border); display: flex; justify-content: flex-end; gap: 10px; }
        .dw-modern-table { border: none !important; box-shadow: none !important; width: 100%; border-collapse: separate; border-spacing: 0; }
        .dw-modern-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid var(--dw-border);
            padding: 15px;
            font-weight: 600;
            color: var(--dw-text-gray);
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .dw-modern-table td { padding: 15px; vertical-align: middle; border-bottom: 1px solid var(--dw-bg-body); color: var(--dw-text-dark); }
        .dw-modern-table tbody tr:last-child td { border-bottom: none; }
        .dw-modern-table tbody tr:hover td { background: #fafafa; }
        .dw-thumb-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            overflow: hidden;
            background: var(--dw-bg-body);
            border: 1px solid var(--dw-border);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dw-thumb-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        
        .row-title { font-size: 15px; font-weight: 600; color: var(--dw-primary); text-decoration: none; }
        .row-title:hover { color: var(--dw-primary-hover); text-decoration: underline; }

        /* Actions */
        .dw-actions { opacity: 0; transition: opacity 0.2s; }
        .dw-modern-table tr:hover .dw-actions { opacity: 1; }
        .button.button-small { padding: 0 10px; line-height: 26px; height: 28px; font-size: 12px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Responsive */
        @media (max-width: 960px) {
            .dw-form-layout { grid-template-columns: 1fr; gap: 20px; }
            .dw-form-sidebar { position: static; width: 100%; }
            .dw-tabs-nav { display: flex; overflow-x: auto; gap: 10px; padding-bottom: 5px; }
            .dw-tabs-nav li { flex-shrink: 0; margin-bottom: 0; }
            .dw-row { flex-wrap: wrap; }
            .dw-col-6, .dw-col-4, .dw-col-8 { flex: 100%; }
        }
    </style>

    <div class="wrap dw-admin-wrap">
        <!-- HEADER -->
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

        <!-- NOTIFIKASI -->
        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="margin-top: 20px; border-radius: 4px;">
                <p><strong><?php echo $message; ?></strong></p>
            </div>
        <?php endif; ?>

        <?php if (!$is_edit): ?>
            <!-- STATS CARDS GRID -->
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

            <!-- TABLE VIEW -->
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

                        $sql = "SELECT p.*, d.nama_desa, u.display_name as owner_name 
                                FROM $table_name p
                                LEFT JOIN $table_desa d ON p.id_desa = d.id
                                LEFT JOIN $table_users u ON p.id_user = u.ID
                                $where_sql 
                                ORDER BY p.created_at DESC 
                                LIMIT $per_page OFFSET $offset";
                        
                        $rows = $wpdb->get_results($sql);
                        $total_items = $wpdb->get_var("SELECT COUNT(p.id) FROM $table_name p $where_sql");
                        $total_pages = ceil($total_items / $per_page);
                    ?>

                    <div class="dw-table-toolbar">
                        <form method="get" class="dw-search-box">
                            <input type="hidden" name="page" value="dw-pedagang">
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Nama Toko / Kelurahan..." class="dw-form-control" style="display:inline-block; width:300px;">
                            <button type="submit" class="button button-primary" style="height: 42px; line-height: 1;"><span class="dashicons dashicons-search" style="margin-top:4px;"></span> Cari</button>
                        </form>
                    </div>

                    <table class="wp-list-table widefat fixed striped dw-modern-table">
                        <thead>
                            <tr>
                                <th width="80">Foto</th>
                                <th>Info Toko</th>
                                <th>Lokasi</th>
                                <th width="140">Status</th>
                                <th width="100">Kuota</th>
                                <th width="120" style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($rows): foreach($rows as $r): $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}"; ?>
                            <tr>
                                <td class="column-thumb">
                                    <div class="dw-thumb-wrapper">
                                        <img src="<?php echo esc_url($r->foto_sampul ? $r->foto_sampul : 'https://placehold.co/100x100?text=Toko'); ?>" alt="Foto">
                                    </div>
                                </td>
                                <td>
                                    <strong><a href="<?php echo $edit_url; ?>" class="row-title"><?php echo esc_html($r->nama_toko); ?></a></strong>
                                    <div class="dw-meta-info" style="color:var(--dw-text-gray); font-size:13px; margin-top:4px;">
                                        <span class="dashicons dashicons-id-alt" style="font-size:16px; vertical-align:middle; color:#ccc;"></span> <?php echo esc_html($r->nama_pemilik); ?>
                                    </div>
                                    <?php if($r->rating_toko > 0): ?>
                                    <div style="font-size:12px; color:var(--dw-warning); margin-top:4px; font-weight:600;">
                                        <span class="dashicons dashicons-star-filled" style="font-size:14px; width:14px; height:14px;"></span> <?php echo $r->rating_toko; ?> <span style="color:var(--dw-text-gray); font-weight:400;">(<?php echo $r->total_ulasan_toko; ?> ulasan)</span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dw-location-info" style="line-height: 1.4;">
                                        <strong><?php echo esc_html($r->kelurahan_nama ? $r->kelurahan_nama : '-'); ?></strong><br>
                                        <span class="muted" style="color:var(--dw-text-gray); font-size:12px;"><?php echo esc_html($r->kecamatan_nama); ?></span>
                                    </div>
                                    <?php if($r->id_desa > 0): ?>
                                        <span class="dw-status-badge dw-status-verified" style="margin-top:6px; font-size: 10px;">Desa <?php echo esc_html($r->nama_desa); ?></span>
                                    <?php else: ?>
                                        <span class="dw-status-badge dw-status-pending" style="margin-top:6px; font-size: 10px;">Independen</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $status_class = 'dw-status-gray';
                                        if($r->status_akun == 'aktif') $status_class = 'dw-status-verified';
                                        elseif($r->status_akun == 'suspend') $status_class = 'dw-status-rejected';
                                        elseif($r->status_akun == 'nonaktif_habis_kuota') $status_class = 'dw-status-rejected';
                                        
                                        $status_label = ucfirst(str_replace('_', ' ', $r->status_akun));
                                    ?>
                                    <div style="display:flex; flex-direction:column; gap:5px; align-items:flex-start;">
                                        <span class="dw-status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                                        <?php if($r->is_verified): ?>
                                            <span style="font-size:11px; color:var(--dw-success); display:flex; align-items:center; gap:2px;">
                                                <span class="dashicons dashicons-saved" style="font-size:14px;"></span> Verified
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color:var(--dw-primary); font-size:15px;"><?php echo number_format($r->sisa_transaksi); ?></strong>
                                    <div style="font-size:11px; color:var(--dw-text-gray);">Transaksi</div>
                                </td>
                                <td style="text-align:right;">
                                    <div class="dw-actions">
                                        <a href="<?php echo $edit_url; ?>" class="button button-small" title="Edit"><span class="dashicons dashicons-edit"></span></a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus permanen data ini?');">
                                            <?php wp_nonce_field('dw_pedagang_action'); ?>
                                            <input type="hidden" name="action_pedagang" value="delete">
                                            <input type="hidden" name="pedagang_id" value="<?php echo $r->id; ?>">
                                            <button type="submit" class="button button-small button-link-delete" title="Hapus"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--dw-text-gray);">Belum ada data pedagang yang ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <?php echo paginate_links(['total' => $total_pages, 'current' => $paged, 'base' => add_query_arg('paged', '%#%')]); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- FORM EDIT / TAMBAH -->
            <div class="dw-form-container">
                <form method="post" id="dw-pedagang-form">
                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                    <input type="hidden" name="action_pedagang" value="save">
                    <?php if ($edit_data): ?><input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <div class="dw-form-layout">
                        <!-- SIDEBAR NAVIGATION -->
                        <div class="dw-form-sidebar">
                            <div class="dw-card">
                                <div class="dw-nav-title" style="padding:20px; border-bottom:1px solid var(--dw-bg-body); font-weight:700; color:var(--dw-text-dark); font-size:16px;">Navigasi Form</div>
                                <ul class="dw-tabs-nav" style="padding: 10px;">
                                    <li class="dw-tab-trigger active" data-target="tab-umum"><span class="dashicons dashicons-store"></span> Informasi Toko</li>
                                    <li class="dw-tab-trigger" data-target="tab-lokasi"><span class="dashicons dashicons-location"></span> Lokasi & Wilayah</li>
                                    <li class="dw-tab-trigger" data-target="tab-legalitas"><span class="dashicons dashicons-id-alt"></span> Legalitas</li>
                                    <li class="dw-tab-trigger" data-target="tab-keuangan"><span class="dashicons dashicons-money-alt"></span> Keuangan</li>
                                    <li class="dw-tab-trigger" data-target="tab-pengaturan"><span class="dashicons dashicons-admin-settings"></span> Pengaturan & Ongkir</li>
                                </ul>
                            </div>
                            
                            <div class="dw-card" style="padding:20px; text-align:center;">
                                <button type="submit" class="dw-btn-primary" style="width:100%; justify-content:center; margin-bottom:12px; padding: 12px;">
                                    <span class="dashicons dashicons-saved"></span> Simpan Data
                                </button>
                                <a href="?page=dw-pedagang" class="button" style="width:100%; height: 38px; line-height: 36px;">Batal / Kembali</a>
                            </div>
                        </div>

                        <!-- CONTENT AREA -->
                        <div class="dw-form-content">
                            
                            <!-- TAB 1: UMUM -->
                            <div id="tab-umum" class="dw-tab-pane active">
                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Informasi Dasar</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-row">
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Akun Pengguna <span style="color:red;">*</span></label>
                                                    <select name="id_user_pedagang" class="dw-form-control select2" required>
                                                        <option value="">-- Pilih User --</option>
                                                        <?php foreach ($users as $user): ?>
                                                            <option value="<?php echo $user->ID; ?>" <?php selected($edit_data ? $edit_data->id_user : '', $user->ID); ?>>
                                                                <?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Nomor WhatsApp <span style="color:red;">*</span></label>
                                                    <input name="nomor_wa" type="text" value="<?php echo esc_attr($edit_data->nomor_wa ?? ''); ?>" class="dw-form-control" required placeholder="08xxxxxxxxxx">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dw-row">
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Nama Toko <span style="color:red;">*</span></label>
                                                    <input name="nama_toko" type="text" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="dw-form-control" required>
                                                </div>
                                            </div>
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Nama Pemilik <span style="color:red;">*</span></label>
                                                    <input name="nama_pemilik" type="text" value="<?php echo esc_attr($edit_data->nama_pemilik ?? ''); ?>" class="dw-form-control" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Visual Toko</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-row">
                                            <div class="dw-col-4">
                                                <div class="dw-form-group">
                                                    <label>Foto Profil</label>
                                                    <div class="dw-media-uploader">
                                                        <div class="dw-media-preview-small" style="margin-bottom:15px;">
                                                            <img id="preview_profil" src="<?php echo !empty($edit_data->foto_profil) ? esc_url($edit_data->foto_profil) : 'https://placehold.co/150x150?text=Profil'; ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:4px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                                        </div>
                                                        <input type="text" name="foto_profil" id="foto_profil" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>" class="dw-form-control" readonly style="margin-bottom:10px; font-size: 12px;">
                                                        <button type="button" class="button btn_upload" data-target="#foto_profil" data-preview="#preview_profil"><span class="dashicons dashicons-upload" style="vertical-align:middle; font-size: 16px;"></span> Upload Foto</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="dw-col-8">
                                                <div class="dw-form-group">
                                                    <label>Foto Sampul</label>
                                                    <div class="dw-media-uploader">
                                                        <div class="dw-media-preview" style="margin-bottom:15px;">
                                                            <img id="preview_sampul" src="<?php echo !empty($edit_data->foto_sampul) ? esc_url($edit_data->foto_sampul) : 'https://placehold.co/600x300?text=Sampul'; ?>" style="width:100%; height:200px; object-fit:cover; border-radius:8px; border:1px solid #ddd;">
                                                        </div>
                                                        <input type="text" name="foto_sampul" id="foto_sampul" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>" class="dw-form-control" readonly style="margin-bottom:10px; font-size: 12px;">
                                                        <button type="button" class="button btn_upload" data-target="#foto_sampul" data-preview="#preview_sampul"><span class="dashicons dashicons-upload" style="vertical-align:middle; font-size: 16px;"></span> Upload Sampul</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- DATA STATISTIK & REFERRAL (READ ONLY) -->
                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Data Statistik & Referral</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-row">
                                            <div class="dw-col-4">
                                                <div class="dw-form-group">
                                                    <label>Kode Referral Saya</label>
                                                    <input type="text" value="<?php echo esc_attr($edit_data->kode_referral_saya ?? '-'); ?>" class="dw-form-control" readonly style="font-family:monospace; font-weight:700; color:var(--dw-primary);">
                                                </div>
                                            </div>
                                            <div class="dw-col-4">
                                                <div class="dw-form-group">
                                                    <label>Terdaftar Melalui</label>
                                                    <input type="text" value="<?php echo esc_attr($edit_data->terdaftar_melalui_kode ?? '-'); ?>" class="dw-form-control" readonly>
                                                </div>
                                            </div>
                                            <div class="dw-col-4">
                                                <div class="dw-form-group">
                                                    <label>Total Referral Pembeli</label>
                                                    <input type="text" value="<?php echo esc_attr($edit_data->total_referral_pembeli ?? '0'); ?>" class="dw-form-control" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dw-row">
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Rating Toko</label>
                                                    <div style="display:flex; align-items:center; gap:10px;">
                                                        <input type="text" value="<?php echo esc_attr($edit_data->rating_toko ?? '0.00'); ?>" class="dw-form-control" readonly style="width:80px;">
                                                        <div style="color:var(--dw-warning);">
                                                            <?php 
                                                                $rating = floatval($edit_data->rating_toko ?? 0);
                                                                for($i=1; $i<=5; $i++) {
                                                                    if($i <= $rating) echo '<span class="dashicons dashicons-star-filled"></span>';
                                                                    elseif($i - 0.5 <= $rating) echo '<span class="dashicons dashicons-star-half"></span>';
                                                                    else echo '<span class="dashicons dashicons-star-empty"></span>';
                                                                }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Total Ulasan</label>
                                                    <input type="text" value="<?php echo esc_attr($edit_data->total_ulasan_toko ?? '0'); ?>" class="dw-form-control" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 2: LOKASI (DIPERBAIKI) -->
                            <div id="tab-lokasi" class="dw-tab-pane">
                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Alamat & Wilayah</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-row">
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Provinsi</label>
                                                    <!-- Load Provinsi via PHP -->
                                                    <select name="pedagang_prov" class="dw-region-prov dw-form-control" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>">
                                                        <option value="">Pilih Provinsi...</option>
                                                        <?php
                                                        if (class_exists('DW_Address_API')) {
                                                            $provs = DW_Address_API::get_provinces();
                                                            foreach($provs as $v) {
                                                                $id = $v['id'];
                                                                $name = $v['name'];
                                                                $selected = ($edit_data && $edit_data->api_provinsi_id == $id) ? 'selected' : '';
                                                                echo "<option value='$id' $selected>$name</option>";
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Kota/Kabupaten</label>
                                                    <!-- Load Kota via PHP jika Edit Mode -->
                                                    <select name="pedagang_kota" class="dw-region-kota dw-form-control" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>">
                                                        <option value="">Pilih Kota...</option>
                                                        <?php
                                                        if ($edit_data && !empty($edit_data->api_provinsi_id) && class_exists('DW_Address_API')) {
                                                            $cities = DW_Address_API::get_cities($edit_data->api_provinsi_id);
                                                            foreach($cities as $v) {
                                                                $id = $v['id'];
                                                                $name = $v['name'];
                                                                $selected = ($edit_data->api_kabupaten_id == $id) ? 'selected' : '';
                                                                echo "<option value='$id' $selected>$name</option>";
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dw-row">
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Kecamatan</label>
                                                    <!-- Load Kecamatan via PHP jika Edit Mode -->
                                                    <select name="pedagang_kec" class="dw-region-kec dw-form-control" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>">
                                                        <option value="">Pilih Kecamatan...</option>
                                                        <?php
                                                        if ($edit_data && !empty($edit_data->api_kabupaten_id) && class_exists('DW_Address_API')) {
                                                            $dists = DW_Address_API::get_subdistricts($edit_data->api_kabupaten_id);
                                                            foreach($dists as $v) {
                                                                $id = $v['id'];
                                                                $name = $v['name'];
                                                                $selected = ($edit_data->api_kecamatan_id == $id) ? 'selected' : '';
                                                                echo "<option value='$id' $selected>$name</option>";
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Kelurahan / Desa <span style="color:red;">*</span></label>
                                                    <!-- Load Desa via PHP jika Edit Mode -->
                                                    <select name="pedagang_nama_id" class="dw-region-desa dw-form-control" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>">
                                                        <option value="">Pilih Kelurahan...</option>
                                                        <?php
                                                        if ($edit_data && !empty($edit_data->api_kecamatan_id) && class_exists('DW_Address_API')) {
                                                            $vills = DW_Address_API::get_villages($edit_data->api_kecamatan_id);
                                                            foreach($vills as $v) {
                                                                $id = $v['id'];
                                                                $name = $v['name'];
                                                                $selected = ($edit_data->api_kelurahan_id == $id) ? 'selected' : '';
                                                                echo "<option value='$id' $selected>$name</option>";
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Hidden Inputs for Names -->
                                        <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi_nama ?? ''); ?>">
                                        <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten_nama ?? ''); ?>">
                                        <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan_nama ?? ''); ?>">
                                        <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan_nama ?? ''); ?>">

                                        <div class="dw-form-group">
                                            <label>Kode Pos</label>
                                            <input name="kode_pos" type="text" value="<?php echo esc_attr($edit_data->kode_pos ?? ''); ?>" class="dw-form-control" maxlength="10" placeholder="Contoh: 12345">
                                        </div>

                                        <div class="dw-form-group">
                                            <label>Alamat Lengkap</label>
                                            <textarea name="pedagang_detail" class="dw-form-control" rows="3" placeholder="Nama jalan, RT/RW, nomor rumah..."><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                                        </div>
                                        <div class="dw-form-group">
                                            <label>URL Google Maps</label>
                                            <input name="url_gmaps" type="url" value="<?php echo esc_attr($edit_data->url_gmaps ?? ''); ?>" class="dw-form-control" placeholder="https://maps.google.com/...">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 3: LEGALITAS -->
                            <div id="tab-legalitas" class="dw-tab-pane">
                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Data Identitas</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-form-group">
                                            <label>NIK</label>
                                            <input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="dw-form-control">
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Foto KTP</label>
                                            <div class="dw-media-uploader">
                                                <?php if(!empty($edit_data->url_ktp)): ?>
                                                    <div style="margin-bottom:10px;">
                                                        <img src="<?php echo esc_url($edit_data->url_ktp); ?>" style="max-width:200px; border-radius:6px; border:1px solid #ddd;">
                                                    </div>
                                                <?php endif; ?>
                                                <div style="display:flex; gap:10px;">
                                                    <input type="text" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>" class="dw-form-control" readonly placeholder="URL File KTP">
                                                    <button type="button" class="button btn_upload" data-target="#url_ktp">Pilih File</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 4: KEUANGAN -->
                            <div id="tab-keuangan" class="dw-tab-pane">
                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Rekening & Pembayaran</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-row">
                                            <div class="dw-col-4">
                                                <div class="dw-form-group">
                                                    <label>Nama Bank</label>
                                                    <input name="nama_bank" type="text" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="dw-form-control" placeholder="Contoh: BCA">
                                                </div>
                                            </div>
                                            <div class="dw-col-8">
                                                <div class="dw-form-group">
                                                    <label>Nomor Rekening</label>
                                                    <input name="no_rekening" type="text" value="<?php echo esc_attr($edit_data->no_rekening ?? ''); ?>" class="dw-form-control">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Atas Nama</label>
                                            <input name="atas_nama_rekening" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening ?? ''); ?>" class="dw-form-control">
                                        </div>
                                        <div class="dw-form-group">
                                            <label>QRIS Image URL</label>
                                            <div class="dw-media-uploader">
                                                <?php if(!empty($edit_data->qris_image_url)): ?>
                                                    <div style="margin-bottom:10px;">
                                                        <img src="<?php echo esc_url($edit_data->qris_image_url); ?>" style="max-width:150px; border-radius:6px; border:1px solid #ddd;">
                                                    </div>
                                                <?php endif; ?>
                                                <div style="display:flex; gap:10px;">
                                                    <input type="text" name="qris_image_url" id="qris_image_url" value="<?php echo esc_attr($edit_data->qris_image_url ?? ''); ?>" class="dw-form-control" readonly>
                                                    <button type="button" class="button btn_upload" data-target="#qris_image_url">Upload QRIS</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 5: PENGATURAN (TERMASUK ONGKIR) -->
                            <div id="tab-pengaturan" class="dw-tab-pane">
                                <div class="dw-card">
                                    <div class="dw-card-header"><h3>Status Akun</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-row">
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Status Keaktifan</label>
                                                    <select name="status_akun" class="dw-form-control">
                                                        <option value="nonaktif" <?php selected($edit_data->status_akun ?? '', 'nonaktif'); ?>>Non-Aktif</option>
                                                        <option value="aktif" <?php selected($edit_data->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                                                        <option value="suspend" <?php selected($edit_data->status_akun ?? '', 'suspend'); ?>>Suspend</option>
                                                        <option value="nonaktif_habis_kuota" <?php selected($edit_data->status_akun ?? '', 'nonaktif_habis_kuota'); ?>>Non-Aktif (Habis Kuota)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="dw-col-6">
                                                <div class="dw-form-group">
                                                    <label>Status Pendaftaran</label>
                                                    <select name="status_pendaftaran" class="dw-form-control">
                                                        <option value="menunggu_desa" <?php selected($edit_data->status_pendaftaran ?? '', 'menunggu_desa'); ?>>Menunggu Desa</option>
                                                        <option value="menunggu" <?php selected($edit_data->status_pendaftaran ?? '', 'menunggu'); ?>>Menunggu Review</option>
                                                        <option value="disetujui" <?php selected($edit_data->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                                                        <option value="ditolak" <?php selected($edit_data->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Sisa Kuota Transaksi</label>
                                            <input name="sisa_transaksi" type="number" value="<?php echo esc_attr($edit_data->sisa_transaksi ?? '0'); ?>" class="dw-form-control">
                                            <p class="description" style="font-size:12px; margin-top:5px;">Jumlah transaksi tersisa yang dapat dilakukan oleh pedagang.</p>
                                        </div>
                                        <div class="dw-form-group">
                                            <label>Diverifikasi Oleh</label>
                                            <input type="text" value="<?php echo esc_attr($edit_data->approved_by ?? '-'); ?>" class="dw-form-control" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- CARD ONGKIR LOKAL -->
                                <div class="dw-card">
                                    <div class="dw-card-header">
                                        <h3><span class="dashicons dashicons-location-alt" style="color:var(--dw-primary);"></span> Pengaturan Ongkir</h3>
                                        <p class="description">Atur tarif pengiriman.</p>
                                    </div>
                                    <div class="dw-card-body">
                                        <div style="margin-bottom: 25px; background: #f9f9f9; padding: 15px; border-radius: 8px;">
                                            <h4 style="margin-top:0;">Opsi Dasar</h4>
                                            <label class="dw-toggle-switch">
                                                <input type="checkbox" name="allow_pesan_di_tempat" value="1" <?php checked($edit_data->allow_pesan_di_tempat ?? 0, 1); ?>>
                                                <span class="slider round"></span>
                                                <span class="label-text">Izinkan Pesan di Tempat (Ambil Sendiri / Makan di Tempat)</span>
                                            </label>
                                        </div>

                                        <div class="dw-divider" style="border-top:1px solid var(--dw-border); margin:20px 0;"></div>

                                        <div style="margin-bottom: 25px;">
                                            <h4 style="margin-bottom: 15px;">Pengiriman Nasional</h4>
                                            <div class="dw-row">
                                                <div class="dw-col-6">
                                                    <label class="dw-toggle-switch">
                                                        <input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked($edit_data->shipping_nasional_aktif ?? 0, 1); ?>>
                                                        <span class="slider round"></span>
                                                        <span class="label-text">Aktifkan Pengiriman Nasional</span>
                                                    </label>
                                                </div>
                                                <div class="dw-col-6">
                                                    <label>Biaya Flat Rate (Rp)</label>
                                                    <input type="number" name="shipping_nasional_harga" class="dw-form-control" value="<?php echo esc_attr($edit_data->shipping_nasional_harga ?? '0'); ?>">
                                                    <p class="description">Biaya ongkir rata untuk seluruh Indonesia (jika aktif).</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="dw-divider" style="border-top:1px solid var(--dw-border); margin:20px 0;"></div>

                                        <div>
                                            <h4 style="margin-bottom: 15px;">Pengiriman Lokal (Ojek Desa)</h4>
                                            <label class="dw-toggle-switch" style="margin-bottom: 20px;">
                                                <input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked($edit_data->shipping_ojek_lokal_aktif ?? 0, 1); ?>>
                                                <span class="slider round"></span>
                                                <span class="label-text">Aktifkan Fitur Ojek Lokal</span>
                                            </label>
                                            
                                            <div class="dw-ongkir-settings" style="background: #fafafa; border: 1px solid var(--dw-border); border-radius: 8px; padding: 20px;">
                                                <h4 class="dw-zone-title" style="color:var(--dw-primary); border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Zona 1: Satu Kecamatan (Desa)</h4>
                                                
                                                <!-- Sub Zona Dekat -->
                                                <div class="dw-ongkir-subzone" style="margin-bottom: 20px;">
                                                    <label class="subzone-label" style="font-weight:600; display:block; margin-bottom:8px;">Desa Area Dekat</label>
                                                    <div class="dw-row">
                                                        <div class="dw-col-4">
                                                            <label style="font-size:12px;">Tarif (Rp)</label>
                                                            <input type="number" name="ojek_dekat_harga" class="dw-form-control" placeholder="5000" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['dekat']['harga'] ?? ''); ?>">
                                                        </div>
                                                        <div class="dw-col-8">
                                                            <label style="font-size:12px;">Pilih Desa</label>
                                                            <select name="ojek_dekat_desa_ids[]" class="dw-form-control select2-villages" multiple="multiple">
                                                                <?php 
                                                                if(!empty($ojek_zona['satu_kecamatan']['dekat']['desa_ids'])){
                                                                    foreach($ojek_zona['satu_kecamatan']['dekat']['desa_ids'] as $vid){
                                                                        echo "<option value='$vid' selected>$vid</option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Sub Zona Jauh -->
                                                <div class="dw-ongkir-subzone">
                                                    <label class="subzone-label" style="font-weight:600; display:block; margin-bottom:8px;">Desa Area Jauh</label>
                                                    <div class="dw-row">
                                                        <div class="dw-col-4">
                                                            <label style="font-size:12px;">Tarif (Rp)</label>
                                                            <input type="number" name="ojek_jauh_harga" class="dw-form-control" placeholder="10000" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['jauh']['harga'] ?? ''); ?>">
                                                        </div>
                                                        <div class="dw-col-8">
                                                            <label style="font-size:12px;">Pilih Desa</label>
                                                            <select name="ojek_jauh_desa_ids[]" class="dw-form-control select2-villages" multiple="multiple">
                                                                <?php 
                                                                if(!empty($ojek_zona['satu_kecamatan']['jauh']['desa_ids'])){
                                                                    foreach($ojek_zona['satu_kecamatan']['jauh']['desa_ids'] as $vid){
                                                                        echo "<option value='$vid' selected>$vid</option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="dw-divider" style="margin: 20px 0; border-top:1px dashed #ddd;"></div>

                                                <h4 class="dw-zone-title" style="color:var(--dw-primary); border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Zona 2: Beda Kecamatan (Satu Kabupaten)</h4>
                                                
                                                <!-- Sub Zona Dekat (Kecamatan) -->
                                                <div class="dw-ongkir-subzone" style="margin-bottom: 20px;">
                                                    <label class="subzone-label" style="font-weight:600; display:block; margin-bottom:8px;">Kecamatan Dekat</label>
                                                    <div class="dw-row">
                                                        <div class="dw-col-4">
                                                            <label style="font-size:12px;">Tarif (Rp)</label>
                                                            <input type="number" name="ojek_beda_kec_dekat_harga" class="dw-form-control" placeholder="15000" value="<?php echo esc_attr($ojek_zona['beda_kecamatan']['dekat']['harga'] ?? ''); ?>">
                                                        </div>
                                                        <div class="dw-col-8">
                                                            <label style="font-size:12px;">Pilih Kecamatan</label>
                                                            <select name="ojek_beda_kec_dekat_ids[]" class="dw-form-control select2-districts" multiple="multiple">
                                                                <?php 
                                                                if(!empty($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'])){
                                                                    foreach($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'] as $kid){
                                                                        echo "<option value='$kid' selected>$kid</option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Sub Zona Jauh (Kecamatan) -->
                                                <div class="dw-ongkir-subzone">
                                                    <label class="subzone-label" style="font-weight:600; display:block; margin-bottom:8px;">Kecamatan Jauh</label>
                                                    <div class="dw-row">
                                                        <div class="dw-col-4">
                                                            <label style="font-size:12px;">Tarif (Rp)</label>
                                                            <input type="number" name="ojek_beda_kec_jauh_harga" class="dw-form-control" placeholder="25000" value="<?php echo esc_attr($ojek_zona['beda_kecamatan']['jauh']['harga'] ?? ''); ?>">
                                                        </div>
                                                        <div class="dw-col-8">
                                                            <label style="font-size:12px;">Pilih Kecamatan</label>
                                                            <select name="ojek_beda_kec_jauh_ids[]" class="dw-form-control select2-districts" multiple="multiple">
                                                                <?php 
                                                                if(!empty($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'])){
                                                                    foreach($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'] as $kid){
                                                                        echo "<option value='$kid' selected>$kid</option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div> <!-- End Content -->
                    </div> <!-- End Layout -->
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($){
                
                // --- 1. TAB NAVIGATION HANDLER ---
                $(document).on('click', '.dw-tab-trigger', function(e) {
                    e.preventDefault();
                    var target = $(this).data('target');
                    $('.dw-tab-trigger').removeClass('active');
                    $(this).addClass('active');
                    $('.dw-tab-pane').removeClass('active').hide();
                    $('#' + target).fadeIn(200).addClass('active');
                    if(target === 'tab-pengaturan') {
                        if ($.fn.select2) {
                            $('.select2-districts').select2({ width: '100%', placeholder: 'Pilih Kecamatan' });
                            $('.select2-villages').select2({ width: '100%', placeholder: 'Pilih Desa' });
                        }
                    }
                });

                // --- 2. INIT SELECT2 ---
                if ($.fn.select2) {
                    $('.select2').select2({ width: '100%' });
                    $('.select2-districts').select2({ width: '100%', placeholder: 'Pilih Kecamatan' });
                    $('.select2-villages').select2({ width: '100%', placeholder: 'Pilih Desa' });
                }

                // --- 3. LOGIKA EKSKLUSI DESA (DEKAT vs JAUH) VIA SELECT2 ---
                function syncDesaExclusion() {
                    var $dekat = $('select[name="ojek_dekat_desa_ids[]"]');
                    var $jauh = $('select[name="ojek_jauh_desa_ids[]"]');
                    
                    var valDekat = $dekat.val() || [];
                    var valJauh = $jauh.val() || [];
                    
                    // Disable opsi di JAUH yang sudah dipilih di DEKAT
                    $jauh.find('option').each(function(){
                        if(valDekat.includes($(this).val())) {
                            $(this).prop('disabled', true);
                        } else {
                            $(this).prop('disabled', false);
                        }
                    });

                    // Disable opsi di DEKAT yang sudah dipilih di JAUH
                    $dekat.find('option').each(function(){
                        if(valJauh.includes($(this).val())) {
                            $(this).prop('disabled', true);
                        } else {
                            $(this).prop('disabled', false);
                        }
                    });
                }

                // Trigger saat ada perubahan di Desa Dekat ATAU Jauh
                $('select[name="ojek_dekat_desa_ids[]"], select[name="ojek_jauh_desa_ids[]"]').on('select2:select select2:unselect', function (e) {
                    syncDesaExclusion();
                });

                // --- 4. LOGIKA EKSKLUSI KECAMATAN (DEKAT vs JAUH) ---
                function syncKecamatanExclusion() {
                    var $dekat = $('select[name="ojek_beda_kec_dekat_ids[]"]');
                    var $jauh = $('select[name="ojek_beda_kec_jauh_ids[]"]');
                    
                    var valDekat = $dekat.val() || [];
                    var valJauh = $jauh.val() || [];
                    
                    // Disable opsi di JAUH yang sudah dipilih di DEKAT
                    $jauh.find('option').each(function(){
                        if(valDekat.includes($(this).val())) {
                            $(this).prop('disabled', true);
                        } else {
                            $(this).prop('disabled', false);
                        }
                    });

                    // Disable opsi di DEKAT yang sudah dipilih di JAUH
                    $dekat.find('option').each(function(){
                        if(valJauh.includes($(this).val())) {
                            $(this).prop('disabled', true);
                        } else {
                            $(this).prop('disabled', false);
                        }
                    });
                }

                $('select[name="ojek_beda_kec_dekat_ids[]"], select[name="ojek_beda_kec_jauh_ids[]"]').on('select2:select select2:unselect', function (e) {
                    syncKecamatanExclusion();
                });

                // --- 5. HELPER LOAD AJAX OPTION (UPDATED FOR YOUR API) ---
                function loadRegionOptions(action, parentId, $targetEl, selectedId = null, placeholder = 'Pilih...') {
                    $targetEl.html('<option value="">Memuat...</option>').prop('disabled', true);
                    
                    // Gunakan action yang sesuai dengan address-api.php
                    var ajaxAction = '';
                    var data = { nonce: '<?php echo wp_create_nonce("dw_region_nonce"); ?>' };
                    
                    if(action === 'dw_get_cities') { 
                        ajaxAction = 'dw_fetch_regencies'; 
                        data.province_id = parentId;
                    }
                    if(action === 'dw_get_districts') { 
                        ajaxAction = 'dw_fetch_districts'; 
                        data.regency_id = parentId;
                    }
                    if(action === 'dw_get_villages') { 
                        ajaxAction = 'dw_fetch_villages';
                        data.district_id = parentId;
                    }
                    if(action === 'dw_get_provinces') {
                        ajaxAction = 'dw_fetch_provinces';
                    }

                    data.action = ajaxAction;

                    $.ajax({
                        url: ajaxurl,
                        type: 'GET',
                        dataType: 'json',
                        data: data,
                        success: function(res) {
                            $targetEl.empty().prop('disabled', false);
                            $targetEl.append('<option value="">' + placeholder + '</option>');
                            
                            if (res.success) {
                                var items = res.data;
                                if (items && items.data) items = items.data;
                                if (items && items.results) items = items.results;
                                
                                if (items && Array.isArray(items)) {
                                    var count = 0;
                                    $.each(items, function(i, item) {
                                        var val = item.id || item.code;
                                        var txt = item.name || item.nama;
                                        
                                        if(val && txt) {
                                            var isSelected = (selectedId && String(val) === String(selectedId)) ? 'selected' : '';
                                            $targetEl.append('<option value="' + val + '" ' + isSelected + '>' + txt + '</option>');
                                            count++;
                                        }
                                    });
                                    
                                    if(count === 0) {
                                        $targetEl.append('<option value="">Data kosong</option>');
                                    }
                                } else {
                                    $targetEl.append('<option value="">Data tidak ditemukan</option>');
                                }
                                
                                $targetEl.trigger('change');
                            } else {
                                 console.warn('API Response False:', res);
                                 $targetEl.html('<option value="">Gagal memuat data</option>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            $targetEl.html('<option value="">Error Jaringan</option>');
                        }
                    });
                }

                // --- 6. CASCADING ADDRESS LOGIC ---

                // A. Load Provinsi on Load
                var $selProv = $('select[name="pedagang_prov"]');
                var curProv = $selProv.data('current');
                if($selProv.find('option').length <= 1) {
                     loadRegionOptions('dw_get_provinces', null, $selProv, curProv, 'Pilih Provinsi');
                }

                // B. Event Provinsi -> Kota
                $(document).on('change', 'select[name="pedagang_prov"]', function() {
                    var provId = $(this).val();
                    var $selKota = $('select[name="pedagang_kota"]');
                    var curKota = $selKota.data('current');
                    
                    $selKota.empty().prop('disabled', true);
                    $('select[name="pedagang_kec"]').empty().prop('disabled', true);
                    $('select[name="pedagang_nama_id"]').empty().prop('disabled', true);
                    
                    $('input[name="provinsi_text"]').val($(this).find('option:selected').text());

                    // Reset Ongkir: Disable dulu
                    $('select[name="ojek_dekat_desa_ids[]"], select[name="ojek_jauh_desa_ids[]"]').prop('disabled', true).empty();
                    $('select[name="ojek_beda_kec_dekat_ids[]"], select[name="ojek_beda_kec_jauh_ids[]"]').prop('disabled', true).empty();

                    if(provId) {
                        if(String(provId) !== String($selProv.data('last-loaded'))) {
                            curKota = null;
                        }
                        loadRegionOptions('dw_get_cities', provId, $selKota, curKota, 'Pilih Kota/Kabupaten');
                    }
                    $selProv.data('last-loaded', provId);
                });

                // C. Event Kota -> Kecamatan
                $(document).on('change', 'select[name="pedagang_kota"]', function() {
                    var cityId = $(this).val();
                    var $selKec = $('select[name="pedagang_kec"]');
                    var curKec = $selKec.data('current');

                    $('input[name="kabupaten_text"]').val($(this).find('option:selected').text());
                    
                    $selKec.empty().prop('disabled', true);
                    $('select[name="pedagang_nama_id"]').empty().prop('disabled', true);

                    // Update Ongkir Kota/Kabupaten Options
                    $('select[name="ojek_beda_kec_dekat_ids[]"], select[name="ojek_beda_kec_jauh_ids[]"]').data('loaded-parent', null);
                    loadOngkirOptions(true);

                    if(cityId) {
                         if(String(cityId) !== String($('select[name="pedagang_kota"]').data('last-loaded'))) {
                            curKec = null;
                        }
                        loadRegionOptions('dw_get_districts', cityId, $selKec, curKec, 'Pilih Kecamatan');
                    }
                    $('select[name="pedagang_kota"]').data('last-loaded', cityId);
                });

                // D. Event Kecamatan -> Desa
                $(document).on('change', 'select[name="pedagang_kec"]', function() {
                    var kecId = $(this).val();
                    var $selDesa = $('select[name="pedagang_nama_id"]');
                    var curDesa = $selDesa.data('current');

                    $('input[name="kecamatan_text"]').val($(this).find('option:selected').text());

                    $selDesa.empty().prop('disabled', true);

                    // Update Ongkir Desa Checkboxes
                    // Karena sekarang pakai Select2, kita reset & disable dulu
                    $('select[name="ojek_dekat_desa_ids[]"], select[name="ojek_jauh_desa_ids[]"]').data('loaded-parent', null);
                    loadOngkirOptions(true);

                    if(kecId) {
                         if(String(kecId) !== String($('select[name="pedagang_kec"]').data('last-loaded'))) {
                            curDesa = null;
                        }
                        loadRegionOptions('dw_get_villages', kecId, $selDesa, curDesa, 'Pilih Kelurahan/Desa');
                    }
                    $('select[name="pedagang_kec"]').data('last-loaded', kecId);
                });
                
                // E. Update Desa Text
                $(document).on('change', 'select[name="pedagang_nama_id"]', function() {
                      $('input[name="kelurahan_text"]').val($(this).find('option:selected').text());
                });


                // --- 7. ONGKIR LOGIC (SELECT2 VERSION) ---
                function loadOngkirOptions(force = false) {
                    var kecId = $('select[name="pedagang_kec"]').val();
                    var kabId = $('select[name="pedagang_kota"]').val();
                    
                    if(!kecId) kecId = $('select[name="pedagang_kec"]').data('current');
                    if(!kabId) kabId = $('select[name="pedagang_kota"]').data('current');

                    // A. Handle Villages (Select2)
                    var $villageSelects = $('select[name="ojek_dekat_desa_ids[]"], select[name="ojek_jauh_desa_ids[]"]');
                    var lastKecId = $villageSelects.data('loaded-parent');

                    if (kecId && (force || kecId != lastKecId)) {
                        $villageSelects.prop('disabled', true); // Disable while loading
                        
                        var data = { 
                            action: 'dw_fetch_villages', 
                            district_id: kecId,
                            nonce: '<?php echo wp_create_nonce("dw_region_nonce"); ?>' 
                        };

                        $.ajax({
                            url: ajaxurl,
                            type: 'GET',
                            dataType: 'json',
                            data: data,
                            success: function(res) {
                                $villageSelects.prop('disabled', false); // Enable back
                                
                                if(res.success) {
                                    var villages = res.data;
                                    if (villages && Array.isArray(villages)) {
                                        // Update kedua dropdown (Dekat & Jauh)
                                        $villageSelects.each(function(){
                                            var $sel = $(this);
                                            var currentVal = $sel.val() || [];
                                            // Jangan kosongkan jika kita ingin pertahankan nilai lama, tapi karena context wilayah berubah,
                                            // biasanya kita ingin reset option listnya.
                                            // Value lama akan hilang jika ID option tidak ada di list baru.
                                            $sel.empty(); 

                                            $.each(villages, function(i, v){
                                                var val = v.id || v.code;
                                                var txt = v.name || v.nama;
                                                
                                                if(val && txt) {
                                                    // Cek apakah value ini ada di currentVal (saved value)
                                                    var isSelected = currentVal.includes(val);
                                                    $sel.append(new Option(txt, val, isSelected, isSelected));
                                                }
                                            });
                                            $sel.trigger('change'); // Update Select2 UI
                                        });
                                        
                                        $villageSelects.data('loaded-parent', kecId);

                                        // Jalankan Sync setelah options terisi
                                        setTimeout(syncDesaExclusion, 500);

                                    }
                                }
                            }
                        });

                    } else if (!kecId) {
                        $villageSelects.empty().prop('disabled', true).trigger('change');
                        $villageSelects.data('loaded-parent', '');
                    }

                    // B. Handle Districts (Select2)
                    var $districtSelects = $('select[name="ojek_beda_kec_dekat_ids[]"], select[name="ojek_beda_kec_jauh_ids[]"]');
                    var lastKabId = $districtSelects.data('loaded-parent');

                    if (kabId && (force || kabId != lastKabId)) {
                        $districtSelects.prop('disabled', true);
                        
                        var data = { 
                            action: 'dw_fetch_districts', 
                            regency_id: kabId,
                            nonce: '<?php echo wp_create_nonce("dw_region_nonce"); ?>' 
                        };

                        $.ajax({
                            url: ajaxurl,
                            type: 'GET', 
                            dataType: 'json',
                            data: data,
                            success: function(res) {
                                $districtSelects.prop('disabled', false);
                                if(res.success) {
                                    var districts = res.data;
                                    if (districts && Array.isArray(districts)) {
                                        $districtSelects.each(function(){
                                            var $sel = $(this);
                                            var currentVal = $sel.val() || [];
                                            $sel.empty();
                                            $.each(districts, function(i, d){
                                                var val = d.id || d.code;
                                                var txt = d.name || d.nama;

                                                if(val && txt) {
                                                    var isSelected = currentVal.includes(val);
                                                    $sel.append(new Option(txt, val, isSelected, isSelected));
                                                }
                                            });
                                            $sel.trigger('change');
                                            $sel.data('loaded-parent', kabId);
                                        });
                                        
                                        setTimeout(syncKecamatanExclusion, 500);
                                    }
                                }
                            }
                        });
                    } else if (!kabId) {
                        $districtSelects.empty().prop('disabled', true).trigger('change');
                        $districtSelects.data('loaded-parent', '');
                    }
                }

                // Initial Load (jika Edit)
                if ($('select[name="pedagang_kec"]').data('current') || $('select[name="pedagang_kota"]').data('current')) {
                     loadOngkirOptions();
                }

                // Media Upload
                $(document).on('click', '.btn_upload', function(e){
                    e.preventDefault();
                    var target = $(this).data('target');
                    var preview = $(this).data('preview');
                    if ( typeof wp === 'undefined' || ! wp.media ) { alert('Media uploader error'); return; }
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
        <?php endif; ?>
    </div>
    <?php
}
?>