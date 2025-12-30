<?php
/**
 * File Name: includes/admin-pages/page-desa.php
 * Description: CRUD Desa Wisata & Verifikasi dengan UI/UX Modern.
 * Matches DB Table: dw_desa
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// 1. Pastikan class API Address tersedia
$address_api_path = dirname(dirname(__FILE__)) . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

// 2. Pastikan Class Logic Referral tersedia
if ( ! class_exists( 'DW_Referral_Logic' ) ) {
    $logic_path = DW_CORE_PLUGIN_DIR . 'includes/class-dw-referral-logic.php';
    if (file_exists($logic_path)) require_once $logic_path;
}

/**
 * Render Halaman Manajemen Desa
 * FUNGSI UTAMA: dw_desa_page_render
 */
function dw_desa_page_render() {
    global $wpdb;
    
    // Definisi Nama Tabel
    $table_desa     = $wpdb->prefix . 'dw_desa';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_users    = $wpdb->users;
    
    // Enqueue Media Uploader WP
    wp_enqueue_media();
    
    $message = '';
    $message_type = '';

    // Action handling
    $action_view = isset($_GET['view']) ? $_GET['view'] : 'list';
    $active_tab  = isset($_GET['tab']) ? $_GET['tab'] : 'data_desa';
    $id_view     = isset($_GET['id']) ? intval($_GET['id']) : 0;

    /**
     * =========================================================================
     * 1. LOGIKA PHP (SAVE / UPDATE / DELETE / VERIFY)
     * =========================================================================
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // A. SIMPAN PENGATURAN HARGA
        if (isset($_POST['action_save_settings']) && check_admin_referer('dw_desa_settings_save')) {
            $settings = get_option('dw_settings', []);
            $settings['harga_premium_desa'] = absint($_POST['harga_premium_desa']);
            update_option('dw_settings', $settings);
            $message = 'Pengaturan harga berhasil disimpan.';
            $message_type = 'success';
        }

        // B. VERIFIKASI PREMIUM
        if (isset($_POST['action_verify_desa']) && check_admin_referer('dw_verify_desa')) {
            $desa_id = absint($_POST['desa_id']);
            $decision = sanitize_key($_POST['decision']); 
            
            if ($decision === 'approve') {
                $wpdb->update($table_desa, ['status_akses_verifikasi' => 'active', 'alasan_penolakan' => null], ['id' => $desa_id]);
                $message = 'Desa berhasil di-upgrade ke status PREMIUM.';
                $message_type = 'success';
            } else {
                $reason = sanitize_textarea_field($_POST['alasan_penolakan']);
                $wpdb->update($table_desa, ['status_akses_verifikasi' => 'locked', 'alasan_penolakan' => $reason], ['id' => $desa_id]);
                $message = 'Pengajuan Premium ditolak. Status dikembalikan ke Free (Locked).';
                $message_type = 'warning';
            }
        }

        // C. CRUD DESA UTAMA
        if (isset($_POST['action_desa']) && check_admin_referer('dw_desa_action')) {
            $action = sanitize_text_field($_POST['action_desa']);

            if ($action === 'delete' && !empty($_POST['desa_id'])) {
                $desa_id_to_delete = intval($_POST['desa_id']);
                $count_pedagang = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_pedagang} WHERE id_desa = %d", $desa_id_to_delete));
                
                if ($count_pedagang > 0) {
                    $message = "Gagal: Masih ada $count_pedagang Pedagang terdaftar di Desa ini."; 
                    $message_type = 'error';
                } else {
                    $deleted = $wpdb->delete($table_desa, ['id' => $desa_id_to_delete]);
                    if ($deleted) {
                        $message = "Desa berhasil dihapus."; $message_type = "success";
                    } else {
                        $message = "Gagal menghapus desa."; $message_type = "error";
                    }
                }
            
            } elseif ($action === 'save') {
                if (empty($_POST['nama_desa'])) {
                    $message = 'Gagal: Nama Desa wajib diisi.'; $message_type = 'error';
                } else {
                    $kode_referral = sanitize_text_field($_POST['kode_referral']);
                    if (empty($kode_referral) || $kode_referral == '(Otomatis)') {
                        if (class_exists('DW_Referral_Logic')) {
                            $logic = new DW_Referral_Logic();
                            $kode_referral = $logic->generate_referral_code('desa', sanitize_text_field($_POST['nama_desa']));
                        } else {
                            $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $_POST['nama_desa']), 0, 3));
                            $kode_referral = 'DESA-' . $clean . '-' . rand(100,999);
                        }
                    }

                    $data = [
                        'id_user_desa'            => intval($_POST['id_user_desa']),
                        'nama_desa'               => sanitize_text_field($_POST['nama_desa']),
                        'slug_desa'               => sanitize_title($_POST['nama_desa']),
                        'kode_referral'           => $kode_referral,
                        'deskripsi'               => wp_kses_post($_POST['deskripsi']),
                        'foto'                    => esc_url_raw($_POST['foto_desa']),
                        'foto_sampul'             => esc_url_raw($_POST['foto_sampul']),
                        'nama_bank_desa'          => sanitize_text_field($_POST['nama_bank_desa']),
                        'no_rekening_desa'        => sanitize_text_field($_POST['no_rekening_desa']),
                        'atas_nama_rekening_desa' => sanitize_text_field($_POST['atas_nama_rekening_desa']),
                        'qris_image_url_desa'     => esc_url_raw($_POST['qris_image_url_desa']),
                        'status'                  => sanitize_text_field($_POST['status']),
                        'status_akses_verifikasi' => sanitize_text_field($_POST['status_akses_verifikasi']),
                        'bukti_bayar_akses'       => esc_url_raw($_POST['bukti_bayar_akses']),
                        'api_provinsi_id'         => sanitize_text_field($_POST['api_provinsi_id']),
                        'api_kabupaten_id'        => sanitize_text_field($_POST['api_kabupaten_id']),
                        'api_kecamatan_id'        => sanitize_text_field($_POST['api_kecamatan_id']),
                        'api_kelurahan_id'        => sanitize_text_field($_POST['api_kelurahan_id']),
                        'provinsi'                => sanitize_text_field($_POST['provinsi_text']),
                        'kabupaten'               => sanitize_text_field($_POST['kabupaten_text']),
                        'kecamatan'               => sanitize_text_field($_POST['kecamatan_text']),
                        'kelurahan'               => sanitize_text_field($_POST['kelurahan_text']),
                        'alamat_lengkap'          => sanitize_textarea_field($_POST['alamat_lengkap']),
                        'kode_pos'                => sanitize_text_field($_POST['kode_pos']),
                        'updated_at'              => current_time('mysql')
                    ];

                    if (!empty($_POST['desa_id'])) {
                        $wpdb->update($table_desa, $data, ['id' => intval($_POST['desa_id'])]);
                        $message = 'Data Desa berhasil diperbarui.'; 
                    } else {
                        $data['created_at'] = current_time('mysql');
                        $data['total_pendapatan'] = 0;
                        $data['saldo_komisi'] = 0;
                        $wpdb->insert($table_desa, $data);
                        $message = 'Desa baru berhasil ditambahkan.'; 
                    }
                    $message_type = 'success';
                }
            }
        }
    }

    // --- Data Preparation ---
    $is_edit = ($action_view === 'edit' || $action_view === 'add');
    $edit_data = null;
    $default_data = (object) [
        'id' => 0, 'id_user_desa' => 0, 'nama_desa' => '', 'deskripsi' => '',
        'kode_referral' => '', 'status' => 'aktif', 
        'foto' => '', 'foto_sampul' => '',
        'status_akses_verifikasi' => 'locked', 'bukti_bayar_akses' => '',
        'api_provinsi_id'=>'', 'api_kabupaten_id'=>'', 'api_kecamatan_id'=>'', 'api_kelurahan_id'=>'',
        'provinsi'=>'', 'kabupaten'=>'', 'kecamatan'=>'', 'kelurahan'=>'',
        'alamat_lengkap'=>'', 'kode_pos'=>'',
        'nama_bank_desa'=>'', 'no_rekening_desa'=>'', 'atas_nama_rekening_desa'=>'', 'qris_image_url_desa'=>''
    ];

    if ($action_view === 'edit' && $id_view > 0) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id = %d", $id_view));
    }
    if (!$edit_data) $edit_data = $default_data;

    $link_daftar_pedagang = home_url( '/register/?ref=' . ($edit_data->kode_referral ?: 'DESA') );
    $users = get_users(['orderby' => 'display_name', 'role__in' => ['subscriber', 'administrator', 'editor', 'author', 'admin_desa']]);

    // Counter untuk badge
    $count_verify = $wpdb->get_var("SELECT COUNT(*) FROM $table_desa WHERE status_akses_verifikasi = 'pending'");

    /**
     * =========================================================================
     * 3. UI VIEW MODERN
     * =========================================================================
     */
    ?>
    <style>
        /* Modern Admin CSS Variables */
        :root {
            --dw-primary: #2563eb; /* Biru yang lebih kuat */
            --dw-primary-dark: #1d4ed8; 
            --dw-success: #16a34a; /* Hijau lebih tajam */
            --dw-warning: #d97706; /* Kuning/Oranye lebih mudah dibaca */
            --dw-danger: #dc2626; 
            --dw-gray-50: #f8fafc;
            --dw-gray-100: #f1f5f9; 
            --dw-gray-200: #e2e8f0; 
            --dw-gray-300: #cbd5e1;
            --dw-gray-700: #334155; 
            --dw-gray-800: #1e293b;
            --dw-radius: 8px; 
            --dw-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dw-container { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
        
        /* Modern Tabs - Dengan Ikon */
        .dw-modern-tabs { display: flex; gap: 20px; border-bottom: 1px solid var(--dw-gray-200); padding-bottom: 0; margin-bottom: 25px; }
        .dw-modern-tab { 
            text-decoration: none; color: var(--dw-gray-700); padding: 12px 0; font-weight: 500; font-size: 14px; 
            border-bottom: 2px solid transparent; transition: all 0.2s; position: relative;
            display: flex; align-items: center; gap: 8px; /* Jarak ikon dan teks */
        }
        .dw-modern-tab .dashicons { font-size: 18px; line-height: 1; color: #64748b; }
        .dw-modern-tab:hover, .dw-modern-tab.active { color: var(--dw-primary); }
        .dw-modern-tab.active { border-bottom-color: var(--dw-primary); }
        .dw-modern-tab.active .dashicons { color: var(--dw-primary); }
        .dw-badge-notify { 
            background: var(--dw-danger); color: white; font-size: 10px; padding: 2px 6px; 
            border-radius: 99px; margin-left: 5px; font-weight: 700;
        }

        /* Modern Cards */
        .dw-modern-card { background: white; border-radius: var(--dw-radius); box-shadow: var(--dw-shadow); padding: 25px; margin-bottom: 20px; border: 1px solid var(--dw-gray-200); }
        .dw-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--dw-gray-100); }
        .dw-card-title { font-size: 18px; font-weight: 700; color: var(--dw-gray-800); margin: 0; display:flex; align-items:center; gap:8px; }

        /* Form Elements */
        .dw-form-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
        .dw-form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: var(--dw-gray-700); font-size: 13px; }
        .dw-input { 
            width: 100%; padding: 10px 12px; border: 1px solid var(--dw-gray-300); border-radius: 6px; font-size: 14px; 
            transition: border-color 0.2s, box-shadow 0.2s; 
        }
        .dw-input:focus { border-color: var(--dw-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); outline: none; }
        
        /* Stats Cards - ICON LEBIH JELAS */
        .dw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .dw-stat-box { 
            background: white; padding: 20px; border-radius: var(--dw-radius); border: 1px solid var(--dw-gray-200); 
            display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dw-stat-box:hover { transform: translateY(-2px); box-shadow: var(--dw-shadow); }
        
        .dw-stat-icon { 
            width: 54px; height: 54px; /* Icon diperbesar */
            border-radius: 12px; display: flex; align-items: center; justify-content: center; 
            font-size: 28px; /* Dashicon lebih besar */
            flex-shrink: 0;
        }
        /* Warna Kontras Tinggi */
        .dw-stat-icon.blue { background: #e0f2fe; color: #0284c7; } /* Sky 100 / Sky 600 */
        .dw-stat-icon.green { background: #dcfce7; color: #16a34a; } /* Green 100 / Green 600 */
        .dw-stat-icon.yellow { background: #fef9c3; color: #ca8a04; } /* Yellow 100 / Yellow 600 */
        
        .dw-stat-content h4 { margin: 0; font-size: 24px; font-weight: 800; color: var(--dw-gray-800); line-height:1.2; }
        .dw-stat-content span { font-size: 13px; font-weight: 600; color: var(--dw-gray-700); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Table */
        .dw-table-wrapper { overflow-x: auto; border-radius: var(--dw-radius); border: 1px solid var(--dw-gray-200); }
        .dw-table { width: 100%; border-collapse: collapse; background: white; }
        .dw-table th { background: var(--dw-gray-50); text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--dw-gray-700); border-bottom: 1px solid var(--dw-gray-200); }
        .dw-table td { padding: 16px 16px; border-bottom: 1px solid var(--dw-gray-200); font-size: 14px; vertical-align: middle; color: var(--dw-gray-700); }
        .dw-table tr:last-child td { border-bottom: none; }
        .dw-table tr:hover { background: #f8fafc; }

        /* Status Pills */
        .dw-pill { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px; }
        .dw-pill.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .dw-pill.warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
        .dw-pill.gray { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .dw-pill.blue { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

        /* Buttons */
        .dw-btn { padding: 9px 18px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border: 1px solid transparent; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .dw-btn-primary { background: var(--dw-primary); color: white; border-color: var(--dw-primary); }
        .dw-btn-primary:hover { background: var(--dw-primary-dark); color: white; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
        .dw-btn-outline { background: white; border-color: var(--dw-gray-300); color: var(--dw-gray-700); }
        .dw-btn-outline:hover { background: var(--dw-gray-50); border-color: var(--dw-gray-400); color: var(--dw-gray-800); }
        .dw-btn-sm { padding: 6px 12px; font-size: 12px; }
        .dw-btn-danger { background: white; border-color: #fca5a5; color: var(--dw-danger); }
        .dw-btn-danger:hover { background: #fef2f2; border-color: var(--dw-danger); }

        /* Layout for Edit Page */
        .dw-edit-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        @media (max-width: 960px) { .dw-edit-layout { grid-template-columns: 1fr; } }

        /* Image Preview */
        .dw-img-preview { width: 100%; height: 160px; background: #f8fafc; border: 2px dashed var(--dw-gray-300); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 12px; transition: border-color 0.2s; }
        .dw-img-preview:hover { border-color: var(--dw-primary); }
        .dw-img-preview img { width: 100%; height: 100%; object-fit: cover; }
        .dw-img-preview.empty::after { content: 'Tidak ada gambar'; color: #94a3b8; font-size: 13px; font-weight: 500; display: flex; flex-direction: column; align-items: center; gap: 5px; }
        .dw-img-preview.empty::before { content: '\f128'; font-family: dashicons; font-size: 32px; color: #cbd5e1; }
    </style>

    <div class="wrap dw-container">
        <!-- HEADER -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h1 class="wp-heading-inline" style="font-size: 28px; font-weight: 800; color: #0f172a; margin-right: 15px;">Manajemen Desa Wisata</h1>
                <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">Kelola data induk desa, verifikasi status premium, dan pengaturan harga.</p>
            </div>
            <?php if (!$is_edit && $active_tab == 'data_desa'): ?>
                <a href="?page=dw-desa&tab=data_desa&view=add" class="dw-btn dw-btn-primary">
                    <span class="dashicons dashicons-plus-alt2" style="font-size: 18px;"></span> Tambah Desa
                </a>
            <?php endif; ?>
        </div>

        <!-- MODERN TABS -->
        <div class="dw-modern-tabs">
            <a href="?page=dw-desa&tab=data_desa" class="dw-modern-tab <?php echo $active_tab == 'data_desa' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-admin-home"></span> Data Desa
            </a>
            <a href="?page=dw-desa&tab=verifikasi" class="dw-modern-tab <?php echo $active_tab == 'verifikasi' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-shield"></span> Verifikasi Premium
                <?php if($count_verify > 0) echo '<span class="dw-badge-notify">'.$count_verify.'</span>'; ?>
            </a>
            <a href="?page=dw-desa&tab=pengaturan" class="dw-modern-tab <?php echo $active_tab == 'pengaturan' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-money-alt"></span> Pengaturan Harga
            </a>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if ($message): ?>
            <div style="margin-bottom: 25px; padding: 16px; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); background: <?php echo $message_type == 'success' ? '#f0fdf4; color: #166534; border: 1px solid #bbf7d0;' : '#fef2f2; color: #991b1b; border: 1px solid #fecaca;'; ?>">
                <span class="dashicons dashicons-<?php echo $message_type == 'success' ? 'yes' : 'warning'; ?>" style="font-size: 24px; width: 24px; height: 24px;"></span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- CONTENT: TAB DATA DESA -->
        <?php if($active_tab == 'data_desa'): ?>
            <?php if (!$is_edit): ?>
                
                <!-- DASHBOARD STATS (MINI) -->
                <div class="dw-stats-grid">
                    <?php 
                        $total_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
                        $total_premium = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa WHERE status_akses_verifikasi = 'active'");
                        $total_pending = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa WHERE status_akses_verifikasi = 'pending'");
                    ?>
                    <div class="dw-stat-box">
                        <div class="dw-stat-icon blue"><span class="dashicons dashicons-admin-site-alt3"></span></div>
                        <div class="dw-stat-content"><h4><?php echo $total_desa; ?></h4><span>Total Desa</span></div>
                    </div>
                    <div class="dw-stat-box">
                        <div class="dw-stat-icon green"><span class="dashicons dashicons-awards"></span></div>
                        <div class="dw-stat-content"><h4><?php echo $total_premium; ?></h4><span>Premium</span></div>
                    </div>
                    <div class="dw-stat-box">
                        <div class="dw-stat-icon yellow"><span class="dashicons dashicons-hourglass"></span></div>
                        <div class="dw-stat-content"><h4><?php echo $total_pending; ?></h4><span>Perlu Verifikasi</span></div>
                    </div>
                </div>

                <!-- TABLE CARD -->
                <div class="dw-modern-card" style="padding: 0; overflow: hidden;">
                    <!-- Filter/Search Bar -->
                    <div style="padding: 16px 24px; border-bottom: 1px solid var(--dw-gray-200); background: var(--dw-gray-50); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--dw-gray-700);">Daftar Desa Wisata</h3>
                        <form method="get" style="display: flex; gap: 10px; width: 100%; max-width: 350px;">
                            <input type="hidden" name="page" value="dw-desa">
                            <input type="text" name="s" placeholder="Cari nama desa..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" class="dw-input" style="background: white;">
                            <button type="submit" class="dw-btn dw-btn-outline">Cari</button>
                        </form>
                    </div>

                    <?php
                    $search_q = isset($_GET['s']) ? esc_sql($_GET['s']) : '';
                    $sql = "SELECT d.*, u.display_name as admin_name FROM {$table_desa} d LEFT JOIN {$table_users} u ON d.id_user_desa = u.ID WHERE 1=1 ";
                    if($search_q) $sql .= " AND (d.nama_desa LIKE '%$search_q%')";
                    $sql .= " ORDER BY d.created_at DESC";
                    $results = $wpdb->get_results($sql);
                    ?>

                    <div class="dw-table-wrapper" style="border:none; border-radius:0;">
                        <table class="dw-table">
                            <thead>
                                <tr>
                                    <th width="80">Logo</th>
                                    <th>Informasi Desa</th>
                                    <th>Admin Pengelola</th>
                                    <th>Lokasi</th>
                                    <th>Membership</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($results): foreach($results as $r): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $r->foto ? esc_url($r->foto) : 'https://via.placeholder.com/60?text=IMG'; ?>" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid var(--dw-gray-200);">
                                    </td>
                                    <td>
                                        <strong style="color: var(--dw-gray-800); font-size: 15px; display:block; margin-bottom: 4px;"><?php echo esc_html($r->nama_desa); ?></strong>
                                        <span style="font-size: 12px; color: var(--dw-gray-500); background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">Ref: <?php echo esc_html($r->kode_referral); ?></span>
                                    </td>
                                    <td>
                                        <?php if($r->admin_name): ?>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span class="dashicons dashicons-admin-users" style="font-size: 16px; width: 16px; height: 16px; color: var(--dw-gray-500);"></span>
                                                <?php echo esc_html($r->admin_name); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:#ef4444; font-size:13px; font-weight:500;">Belum ada admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; font-weight: 500;"><?php echo esc_html($r->kecamatan); ?></div>
                                        <div style="font-size: 12px; color: var(--dw-gray-500);"><?php echo esc_html($r->kabupaten); ?></div>
                                    </td>
                                    <td>
                                        <?php if($r->status_akses_verifikasi == 'active'): ?><span class="dw-pill success">Premium</span>
                                        <?php elseif($r->status_akses_verifikasi == 'pending'): ?><span class="dw-pill warning">Pending</span>
                                        <?php else: ?><span class="dw-pill gray">Free</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($r->status == 'aktif'): ?><span class="dw-pill blue">Aktif</span>
                                        <?php else: ?><span class="dw-pill gray">Pending</span><?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                            <a href="?page=dw-desa&tab=data_desa&view=edit&id=<?php echo $r->id; ?>" class="dw-btn dw-btn-outline dw-btn-sm" title="Edit Data"><span class="dashicons dashicons-edit"></span></a>
                                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Hapus Desa ini beserta semua data terkait?');">
                                                <input type="hidden" name="action_desa" value="delete"><input type="hidden" name="desa_id" value="<?php echo $r->id; ?>">
                                                <?php wp_nonce_field('dw_desa_action'); ?>
                                                <button type="submit" class="dw-btn dw-btn-danger dw-btn-sm" title="Hapus Permanen"><span class="dashicons dashicons-trash"></span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7" style="text-align:center; padding: 60px; color: var(--dw-gray-500);">
                                        <span class="dashicons dashicons-info" style="font-size: 40px; width: 40px; height: 40px; color: var(--dw-gray-300); margin-bottom: 10px;"></span><br>
                                        Belum ada data desa ditemukan.
                                    </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- FORM EDIT / ADD -->
                <form method="post" id="form-desa">
                    <?php wp_nonce_field('dw_desa_action'); ?>
                    <input type="hidden" name="action_desa" value="save">
                    <?php if ($id_view > 0): ?><input type="hidden" name="desa_id" value="<?php echo $id_view; ?>"><?php endif; ?>

                    <div style="margin-bottom: 25px;">
                        <a href="?page=dw-desa&tab=data_desa" class="dw-btn dw-btn-outline"><span class="dashicons dashicons-arrow-left-alt"></span> Kembali ke Daftar</a>
                    </div>

                    <div class="dw-edit-layout">
                        <!-- LEFT COLUMN: MAIN CONTENT -->
                        <div class="dw-main-col">
                            
                            <!-- 1. GENERAL INFO -->
                            <div class="dw-modern-card">
                                <div class="dw-card-header"><h3 class="dw-card-title"><span class="dashicons dashicons-info"></span> Informasi Umum</h3></div>
                                <div class="dw-form-grid">
                                    <div class="dw-form-group">
                                        <label>Nama Desa Wisata <span style="color:var(--dw-danger)">*</span></label>
                                        <input type="text" name="nama_desa" class="dw-input" style="font-size: 16px; font-weight: 600; padding: 12px;" value="<?php echo esc_attr($edit_data->nama_desa); ?>" placeholder="Contoh: Desa Wisata Pujon Kidul" required>
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Deskripsi Profil</label>
                                        <?php wp_editor($edit_data->deskripsi, 'deskripsi', ['textarea_rows' => 10, 'media_buttons' => false, 'editor_class' => 'dw-input']); ?>
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Admin Pengelola (User WordPress)</label>
                                        <select name="id_user_desa" class="dw-input select2">
                                            <option value="">-- Pilih User --</option>
                                            <?php foreach($users as $u) echo '<option value="'.$u->ID.'" '.selected($edit_data->id_user_desa, $u->ID, false).'>'.$u->display_name.' ('.$u->user_email.')</option>'; ?>
                                        </select>
                                        <p class="description" style="margin-top: 5px; font-size: 12px; color: var(--dw-gray-500);">User ini akan memiliki akses login ke dashboard desa.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. LOCATION -->
                            <div class="dw-modern-card">
                                <div class="dw-card-header"><h3 class="dw-card-title"><span class="dashicons dashicons-location"></span> Lokasi Administratif</h3></div>
                                <div class="dw-form-grid" style="grid-template-columns: 1fr 1fr;">
                                    <div class="dw-form-group">
                                        <label>Provinsi</label>
                                        <select name="api_provinsi_id" class="dw-region-prov dw-input" data-current="<?php echo esc_attr($edit_data->api_provinsi_id); ?>"><option value="">Memuat...</option></select>
                                        <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Kota/Kabupaten</label>
                                        <select name="api_kabupaten_id" class="dw-region-kota dw-input" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id); ?>"><option value="">-- Pilih Provinsi --</option></select>
                                        <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Kecamatan</label>
                                        <select name="api_kecamatan_id" class="dw-region-kec dw-input" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id); ?>"><option value="">-- Pilih Kota --</option></select>
                                        <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Kelurahan/Desa</label>
                                        <select name="api_kelurahan_id" class="dw-region-desa dw-input" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id); ?>"><option value="">-- Pilih Kecamatan --</option></select>
                                        <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan); ?>">
                                    </div>
                                </div>
                                <div class="dw-form-group" style="margin-top: 15px;">
                                    <label>Alamat Lengkap</label>
                                    <textarea name="alamat_lengkap" class="dw-input" rows="2" placeholder="Nama jalan, RT/RW, Patokan..."><?php echo esc_textarea($edit_data->alamat_lengkap); ?></textarea>
                                </div>
                                <div class="dw-form-group" style="margin-top: 15px; width: 50%;">
                                    <label>Kode Pos</label>
                                    <input type="text" name="kode_pos" class="dw-input" value="<?php echo esc_attr($edit_data->kode_pos); ?>">
                                </div>
                            </div>

                            <!-- 3. FINANCE -->
                            <div class="dw-modern-card">
                                <div class="dw-card-header"><h3 class="dw-card-title"><span class="dashicons dashicons-money-alt"></span> Data Keuangan</h3></div>
                                <div class="dw-form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                                    <div class="dw-form-group"><label>Nama Bank</label><input type="text" name="nama_bank_desa" class="dw-input" value="<?php echo esc_attr($edit_data->nama_bank_desa); ?>" placeholder="Misal: BRI"></div>
                                    <div class="dw-form-group"><label>No. Rekening</label><input type="text" name="no_rekening_desa" class="dw-input" value="<?php echo esc_attr($edit_data->no_rekening_desa); ?>"></div>
                                    <div class="dw-form-group"><label>Atas Nama</label><input type="text" name="atas_nama_rekening_desa" class="dw-input" value="<?php echo esc_attr($edit_data->atas_nama_rekening_desa); ?>"></div>
                                </div>
                                <div class="dw-form-group" style="margin-top: 15px;">
                                    <label>Gambar QRIS (Opsional)</label>
                                    <div style="display: flex; gap: 20px; align-items: start;">
                                        <div class="dw-img-preview <?php echo !$edit_data->qris_image_url_desa ? 'empty' : ''; ?>" style="width: 140px; height: 140px;">
                                            <?php if($edit_data->qris_image_url_desa) echo '<img src="'.esc_url($edit_data->qris_image_url_desa).'">'; ?>
                                        </div>
                                        <div style="flex:1;">
                                            <input type="hidden" name="qris_image_url_desa" id="qris_desa" value="<?php echo esc_url($edit_data->qris_image_url_desa); ?>">
                                            <button type="button" class="dw-btn dw-btn-outline btn_upload_media" data-target="#qris_desa">Upload QRIS</button>
                                            <p class="description" style="margin-top:10px;">Gambar QRIS akan digunakan untuk pembayaran digital oleh wisatawan.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- RIGHT COLUMN: SIDEBAR -->
                        <div class="dw-sidebar-col">
                            
                            <!-- PUBLISH BOX -->
                            <div class="dw-modern-card">
                                <div class="dw-card-header"><h3 class="dw-card-title">Publikasi</h3></div>
                                <div class="dw-form-group">
                                    <label>Status</label>
                                    <select name="status" class="dw-input">
                                        <option value="aktif" <?php selected($edit_data->status, 'aktif'); ?>>Aktif</option>
                                        <option value="pending" <?php selected($edit_data->status, 'pending'); ?>>Pending</option>
                                    </select>
                                </div>
                                <button type="submit" class="dw-btn dw-btn-primary" style="width: 100%; justify-content: center; margin-top: 15px;">
                                    <span class="dashicons dashicons-saved"></span> Simpan Perubahan
                                </button>
                            </div>

                            <!-- MEDIA BOX -->
                            <div class="dw-modern-card">
                                <div class="dw-card-header"><h3 class="dw-card-title">Media Desa</h3></div>
                                
                                <div class="dw-form-group">
                                    <label>Logo Desa</label>
                                    <div class="dw-img-preview <?php echo !$edit_data->foto ? 'empty' : ''; ?>">
                                        <?php if($edit_data->foto) echo '<img src="'.esc_url($edit_data->foto).'">'; ?>
                                    </div>
                                    <input type="hidden" name="foto_desa" id="foto_desa" value="<?php echo esc_url($edit_data->foto); ?>">
                                    <button type="button" class="dw-btn dw-btn-outline dw-btn-sm btn_upload_media" style="width:100%; justify-content:center;" data-target="#foto_desa">Pilih Logo</button>
                                </div>

                                <div class="dw-form-group" style="margin-top: 25px;">
                                    <label>Foto Sampul (Cover)</label>
                                    <div class="dw-img-preview <?php echo !$edit_data->foto_sampul ? 'empty' : ''; ?>">
                                        <?php if($edit_data->foto_sampul) echo '<img src="'.esc_url($edit_data->foto_sampul).'">'; ?>
                                    </div>
                                    <input type="hidden" name="foto_sampul" id="foto_sampul" value="<?php echo esc_url($edit_data->foto_sampul); ?>">
                                    <button type="button" class="dw-btn dw-btn-outline dw-btn-sm btn_upload_media" style="width:100%; justify-content:center;" data-target="#foto_sampul">Pilih Sampul</button>
                                </div>
                            </div>

                            <!-- REFERRAL BOX -->
                            <div class="dw-modern-card">
                                <div class="dw-card-header"><h3 class="dw-card-title">Referral</h3></div>
                                <div class="dw-form-group">
                                    <label>Kode Desa</label>
                                    <input type="text" name="kode_referral" class="dw-input dw-input-code" value="<?php echo esc_attr($edit_data->kode_referral); ?>" placeholder="(Auto Generate)">
                                </div>
                                <div class="dw-form-group">
                                    <label>Link Pendaftaran</label>
                                    <div style="display:flex; gap:5px;">
                                        <input type="text" class="dw-input" style="font-size:11px; background:#f9fafb;" value="<?php echo esc_url($link_daftar_pedagang); ?>" readonly>
                                        <button type="button" class="dw-btn dw-btn-outline dw-btn-sm dw-copy-btn"><span class="dashicons dashicons-clipboard"></span></button>
                                    </div>
                                    <p class="description" style="margin-top:5px; font-size:11px;">Bagikan link ini kepada calon pedagang/UMKM di desa ini.</p>
                                </div>
                            </div>

                            <!-- MEMBERSHIP BOX -->
                            <div class="dw-modern-card" style="border-left: 4px solid var(--dw-primary);">
                                <div class="dw-card-header"><h3 class="dw-card-title"><span class="dashicons dashicons-shield" style="color:var(--dw-primary)"></span> Membership</h3></div>
                                <div class="dw-form-group">
                                    <label>Status Premium</label>
                                    <select name="status_akses_verifikasi" class="dw-input">
                                        <option value="locked" <?php selected($edit_data->status_akses_verifikasi, 'locked'); ?>>Free (Locked)</option>
                                        <option value="pending" <?php selected($edit_data->status_akses_verifikasi, 'pending'); ?>>Pending</option>
                                        <option value="active" <?php selected($edit_data->status_akses_verifikasi, 'active'); ?>>Premium</option>
                                    </select>
                                </div>
                                <div class="dw-form-group">
                                    <label>Bukti Bayar</label>
                                    <?php if($edit_data->bukti_bayar_akses): ?>
                                        <div style="margin-bottom:10px;">
                                            <a href="<?php echo esc_url($edit_data->bukti_bayar_akses); ?>" target="_blank" style="display:inline-flex; align-items:center; gap:5px; font-size:12px; color:var(--dw-primary); text-decoration:none;">
                                                <span class="dashicons dashicons-media-document"></span> Lihat File Bukti
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <input type="hidden" name="bukti_bayar_akses" id="bukti_bayar" value="<?php echo esc_url($edit_data->bukti_bayar_akses); ?>">
                                    <button type="button" class="dw-btn dw-btn-outline dw-btn-sm btn_upload_media" style="width:100%; justify-content:center;" data-target="#bukti_bayar">Upload Bukti Manual</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </form>
            <?php endif; ?>

        <!-- CONTENT: TAB VERIFIKASI -->
        <?php elseif($active_tab == 'verifikasi'): 
            $pending_verif = $wpdb->get_results("SELECT * FROM $table_desa WHERE status_akses_verifikasi = 'pending' ORDER BY updated_at ASC");
        ?>
            <div class="dw-modern-card">
                <div class="dw-card-header"><h3 class="dw-card-title"><span class="dashicons dashicons-list-view"></span> Antrean Verifikasi Upgrade Premium</h3></div>
                
                <?php if(empty($pending_verif)): ?>
                    <div style="padding: 50px 20px; text-align: center; color: var(--dw-gray-500);">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 48px; width:48px; height:48px; color: var(--dw-success); margin-bottom: 15px;"></span>
                        <p style="font-size: 16px; margin: 0;">Tidak ada permintaan verifikasi saat ini. Semua aman!</p>
                    </div>
                <?php else: foreach($pending_verif as $p): ?>
                    <div style="padding: 20px; border-bottom: 1px solid var(--dw-gray-200); display: flex; gap: 20px; align-items: start;">
                        <!-- Thumbnail Bukti -->
                        <div style="width: 120px; height: 120px; background: #f9fafb; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--dw-gray-200); position:relative;">
                            <?php if($p->bukti_bayar_akses): ?>
                                <a href="<?php echo esc_url($p->bukti_bayar_akses); ?>" target="_blank" style="display:block; width:100%; height:100%;">
                                    <img src="<?php echo esc_url($p->bukti_bayar_akses); ?>" style="width:100%; height:100%; object-fit:cover;">
                                    <span style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.5); color:white; font-size:10px; text-align:center; padding:2px;">Klik Zoom</span>
                                </a>
                            <?php else: ?><span class="dashicons dashicons-format-image" style="font-size:30px; color:#ccc;"></span><?php endif; ?>
                        </div>
                        
                        <div style="flex: 1;">
                            <div style="display:flex; justify-content:space-between;">
                                <h4 style="margin: 0 0 5px; font-size: 18px;"><?php echo esc_html($p->nama_desa); ?></h4>
                                <span class="dw-pill warning">Menunggu</span>
                            </div>
                            <p style="margin: 0 0 15px; font-size: 14px; color: var(--dw-gray-700);">Lokasi: <?php echo esc_html($p->kecamatan . ', ' . $p->kabupaten); ?></p>
                            
                            <div style="display: flex; gap: 10px;">
                                <form method="post">
                                    <?php wp_nonce_field('dw_verify_desa'); ?>
                                    <input type="hidden" name="action_verify_desa" value="1"><input type="hidden" name="desa_id" value="<?php echo $p->id; ?>"><input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="dw-btn dw-btn-primary dw-btn-sm"><span class="dashicons dashicons-yes"></span> Setujui Premium</button>
                                </form>
                                <button type="button" class="dw-btn dw-btn-outline dw-btn-sm" onclick="jQuery('#reject-<?php echo $p->id; ?>').toggle();"><span class="dashicons dashicons-no"></span> Tolak</button>
                            </div>
                            
                            <div id="reject-<?php echo $p->id; ?>" style="display:none; margin-top:15px; background: #fff1f2; padding: 15px; border-radius: 6px; border: 1px solid #fecaca;">
                                <form method="post" style="display: flex; gap: 10px; align-items:center;">
                                    <?php wp_nonce_field('dw_verify_desa'); ?>
                                    <input type="hidden" name="action_verify_desa" value="1"><input type="hidden" name="desa_id" value="<?php echo $p->id; ?>"><input type="hidden" name="decision" value="reject">
                                    <input type="text" name="alasan_penolakan" class="dw-input" placeholder="Tulis alasan penolakan..." required style="padding: 6px 12px; font-size: 13px;">
                                    <button type="submit" class="dw-btn dw-btn-danger dw-btn-sm">Kirim Penolakan</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

        <!-- CONTENT: TAB PENGATURAN -->
        <?php elseif($active_tab == 'pengaturan'): 
            $settings = get_option('dw_settings', []);
            $harga = isset($settings['harga_premium_desa']) ? $settings['harga_premium_desa'] : 0;
        ?>
            <div class="dw-modern-card" style="max-width: 600px;">
                <div class="dw-card-header"><h3 class="dw-card-title"><span class="dashicons dashicons-money-alt"></span> Pengaturan Harga</h3></div>
                <form method="post">
                    <?php wp_nonce_field('dw_desa_settings_save'); ?>
                    <input type="hidden" name="action_save_settings" value="1">
                    <div class="dw-form-group" style="margin-bottom: 25px;">
                        <label>Harga Upgrade Premium (Rp)</label>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-size:18px; font-weight:600; color:var(--dw-gray-500);">Rp</span>
                            <input type="number" name="harga_premium_desa" class="dw-input" value="<?php echo esc_attr($harga); ?>" style="font-size: 18px; font-weight: 600; max-width: 250px;">
                        </div>
                        <p style="font-size: 13px; color: var(--dw-gray-500); margin-top: 8px;">Biaya yang harus dibayarkan oleh Admin Desa untuk mendapatkan status Premium dan fitur lengkap.</p>
                    </div>
                    <button type="submit" class="dw-btn dw-btn-primary">
                        <span class="dashicons dashicons-saved"></span> Simpan Pengaturan
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <script>
    jQuery(document).ready(function($){
        // Tab UI & Media Uploader Logic (Keep existing logic, UI updated via CSS)
        $('.dw-copy-btn').click(function(e){
            e.preventDefault(); var t = $(this).prev('input'); t.select(); document.execCommand("copy"); alert("Link tersalin!");
        });

        $(document).on('click', '.btn_upload_media', function(e){
            e.preventDefault(); var btn = $(this), target = btn.data('target');
            var frame = wp.media({ title: 'Pilih Gambar', multiple: false }).on('select', function(){
                var url = frame.state().get('selection').first().toJSON().url;
                $(target).val(url);
                $(target).prev('.dw-img-preview').html('<img src="'+url+'">').removeClass('empty');
            }).open();
        });

        // API Wilayah (Waterfall)
        function loadRegion(type, pid, target, selId) {
            var act = '';
            if(type==='prov') act='dw_fetch_provinces'; else if(type==='kota') act='dw_fetch_regencies';
            else if(type==='kec') act='dw_fetch_districts'; else if(type==='desa') act='dw_fetch_villages';
            if(type!=='prov' && !pid) return;

            $.get(ajaxurl, { action: act, province_id: pid, regency_id: pid, district_id: pid, nonce: '<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
                if(res.success){
                    target.empty().append('<option value="">Pilih...</option>');
                    $.each(res.data, function(i,v){ target.append('<option value="'+v.id+'" '+(String(v.id)===String(selId)?'selected':'')+'>'+v.name+'</option>'); });
                    if(selId) { target.val(selId); target.trigger('change.dw_waterfall'); }
                }
            });
        }

        // Waterfall Handlers
        $('.dw-region-prov').on('change', function(){ $('.dw-text-prov').val($(this).find('option:selected').text()); loadRegion('kota', $(this).val(), $('.dw-region-kota'), null); });
        $('.dw-region-kota').on('change', function(){ $('.dw-text-kota').val($(this).find('option:selected').text()); loadRegion('kec', $(this).val(), $('.dw-region-kec'), null); });
        $('.dw-region-kec').on('change', function(){ $('.dw-text-kec').val($(this).find('option:selected').text()); loadRegion('desa', $(this).val(), $('.dw-region-desa'), null); });
        $('.dw-region-desa').on('change', function(){ $('.dw-text-desa').val($(this).find('option:selected').text()); });

        // Auto Load on Edit
        $('.dw-region-prov').on('change.dw_waterfall', function(){ loadRegion('kota', $(this).val(), $('.dw-region-kota'), $('.dw-region-kota').data('current')); });
        $('.dw-region-kota').on('change.dw_waterfall', function(){ loadRegion('kec', $(this).val(), $('.dw-region-kec'), $('.dw-region-kec').data('current')); });
        $('.dw-region-kec').on('change.dw_waterfall', function(){ loadRegion('desa', $(this).val(), $('.dw-region-desa'), $('.dw-region-desa').data('current')); });

        // Init
        loadRegion('prov', null, $('.dw-region-prov'), $('.dw-region-prov').data('current'));
        if($.fn.select2) { $('.select2').select2({ width:'100%' }); }
    });
    </script>
    <?php
}