<?php
/**
 * File Name: includes/admin-pages/page-desa.php
 * Description: CRUD Desa Wisata & Verifikasi dengan UI/UX Modern.
 * Matches DB Table: dw_desa
 * Version: 6.5 (Updated Referral Generator Format)
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
    $logic_path = defined('DW_CORE_PLUGIN_DIR') ? DW_CORE_PLUGIN_DIR . 'includes/class-dw-referral-logic.php' : dirname(dirname(__FILE__)) . '/class-dw-referral-logic.php';
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

        // B. VERIFIKASI PREMIUM (APPROVE / REJECT VIA TAB VERIFIKASI)
        if (isset($_POST['action_verify_desa']) && check_admin_referer('dw_verify_desa')) {
            $desa_id = absint($_POST['desa_id']);
            $decision = sanitize_key($_POST['decision']); 
            
            if ($decision === 'approve') {
                $wpdb->update($table_desa, ['status_akses_verifikasi' => 'active', 'alasan_penolakan' => null], ['id' => $desa_id]);
                $message = 'Desa berhasil di-upgrade ke status PREMIUM (Active).';
                $message_type = 'success';
            } else {
                $reason = sanitize_textarea_field($_POST['alasan_penolakan']);
                $wpdb->update($table_desa, ['status_akses_verifikasi' => 'locked', 'alasan_penolakan' => $reason], ['id' => $desa_id]);
                $message = 'Pengajuan Premium ditolak. Status dikembalikan ke Free (Locked).';
                $message_type = 'warning';
            }
        }

        // C. DELETE DESA (Action: delete)
        if (isset($_POST['action_desa']) && $_POST['action_desa'] === 'delete' && check_admin_referer('dw_desa_action')) {
            if (!empty($_POST['desa_id'])) {
                $desa_id_to_delete = intval($_POST['desa_id']);
                
                // Cek apakah masih ada pedagang terdaftar
                $count_pedagang = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_pedagang} WHERE id_desa = %d", $desa_id_to_delete));
                
                if ($count_pedagang > 0) {
                    $message = "Gagal: Masih ada <strong>$count_pedagang Pedagang</strong> terdaftar di Desa ini. Silakan pindahkan atau hapus pedagang terlebih dahulu."; 
                    $message_type = 'error';
                } else {
                    $deleted = $wpdb->delete($table_desa, ['id' => $desa_id_to_delete]);
                    if ($deleted) {
                        $message = "Desa berhasil dihapus secara permanen."; $message_type = "success";
                    } else {
                        $message = "Gagal menghapus desa."; $message_type = "error";
                    }
                }
            }
        }
        
        // D. SAVE DESA (INSERT / UPDATE)
        if (isset($_POST['dw_action']) && $_POST['dw_action'] === 'save_desa' && check_admin_referer('dw_save_desa_nonce')) {
            if (empty($_POST['nama_desa'])) {
                $message = 'Gagal: Nama Desa wajib diisi.'; $message_type = 'error';
            } else {
                $id_desa_save = isset($_POST['desa_id']) ? intval($_POST['desa_id']) : 0;
                
                // --- GENERATE REFERRAL CODE (BACKEND FALLBACK) ---
                $kode_referral = sanitize_text_field($_POST['kode_referral']);
                
                // Jika kosong, generate otomatis
                if (empty($kode_referral)) {
                    $prov_txt = sanitize_text_field($_POST['provinsi_text']);
                    $kab_txt  = sanitize_text_field($_POST['kabupaten_text']);
                    $kel_txt  = sanitize_text_field($_POST['kelurahan_text']);
                    $nama_desa_input = sanitize_text_field($_POST['nama_desa']);

                    // Logic Baru: PRO-KAB-DES-XXXX
                    // Helper function internal
                    $get_region_code = function($text, $type = '') {
                        if (empty($text)) return 'XXX';
                        $clean = trim(strtolower($text));
                        $clean = preg_replace('/^(provinsi|kabupaten|kota|desa|kelurahan)\s+/', '', $clean);
                        
                        // Mapping Khusus
                        if ($type === 'province') {
                            if ($clean == 'jawa barat') return 'JAB';
                            if ($clean == 'jawa tengah') return 'JTG';
                            if ($clean == 'jawa timur') return 'JTM';
                            if (strpos($clean, 'jakarta') !== false) return 'DKI';
                            if (strpos($clean, 'yogyakarta') !== false) return 'DIY';
                        }
                        
                        $clean_no_space = str_replace(' ', '', $clean);
                        return strtoupper(substr($clean_no_space, 0, 3));
                    };

                    $c_prov = $get_region_code($prov_txt, 'province');
                    $c_kab  = $get_region_code($kab_txt);
                    // Gunakan nama desa input jika kelurahan kosong
                    $c_des  = $get_region_code(!empty($kel_txt) ? $kel_txt : $nama_desa_input);
                    $rand   = rand(1000, 9999);

                    $kode_referral = "$c_prov-$c_kab-$c_des-$rand";

                    // Cek Unik
                    $is_exist = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE kode_referral = %s AND id != %d", $kode_referral, $id_desa_save));
                    while($is_exist) {
                        $rand = rand(1000, 9999);
                        $kode_referral = "$c_prov-$c_kab-$c_des-$rand";
                        $is_exist = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE kode_referral = %s AND id != %d", $kode_referral, $id_desa_save));
                    }
                } else {
                    $kode_referral = strtoupper($kode_referral);
                }

                // MAPPING DATA SESUAI TABEL DATABASE
                $data = [
                    'id_user_desa'            => intval($_POST['id_user_desa']),
                    'nama_desa'               => sanitize_text_field($_POST['nama_desa']),
                    'slug_desa'               => sanitize_title($_POST['nama_desa']),
                    'kode_referral'           => $kode_referral,
                    'deskripsi'               => wp_kses_post($_POST['deskripsi']),
                    'foto'                    => esc_url_raw($_POST['foto_url']),
                    'foto_sampul'             => esc_url_raw($_POST['foto_sampul_url']),
                    
                    // Status Publikasi
                    'status'                  => sanitize_text_field($_POST['status']), 
                    
                    // Bank Info
                    'no_rekening_desa'        => sanitize_text_field($_POST['no_rekening_desa']),
                    'nama_bank_desa'          => sanitize_text_field($_POST['nama_bank_desa']),
                    'atas_nama_rekening_desa' => sanitize_text_field($_POST['atas_nama_rekening_desa']),
                    'qris_image_url_desa'     => esc_url_raw($_POST['qris_url']),
                    
                    // Wilayah & Alamat (Simpan Nama & ID API)
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
                    
                    // Status Verifikasi Premium
                    'status_akses_verifikasi' => sanitize_text_field($_POST['status_akses_verifikasi']),
                    'bukti_bayar_akses'       => esc_url_raw($_POST['bukti_bayar_akses_url']),
                    'alasan_penolakan'        => sanitize_textarea_field($_POST['alasan_penolakan']),

                    'updated_at'              => current_time('mysql')
                ];
                
                if ($id_desa_save > 0) {
                    $wpdb->update($table_desa, $data, ['id' => $id_desa_save]);
                    $message = 'Data Desa berhasil diperbarui.'; 
                } else {
                    $data['created_at'] = current_time('mysql');
                    $data['total_pendapatan'] = 0;
                    $data['saldo_komisi'] = 0;
                    $wpdb->insert($table_desa, $data);
                    $message = 'Desa baru berhasil ditambahkan.'; 
                }
                $message_type = 'success';
                
                if ($id_desa_save == 0) $action_view = 'list';
            }
        }
    }
    
    // --- Data Preparation (Edit/Add) ---
    $is_edit = ($action_view === 'edit' || $action_view === 'add');
    $edit_data = null;
    
    // Objek Default
    $default_data = (object) [
        'id' => 0, 'id_user_desa' => 0, 'nama_desa' => '', 'deskripsi' => '',
        'kode_referral' => '', 
        'status' => 'pending', // Default status publikasi
        'foto' => '', 'foto_sampul' => '',
        'status_akses_verifikasi' => 'locked', // Default status verifikasi
        'bukti_bayar_akses' => '', 'alasan_penolakan' => '',
        'api_provinsi_id'=>'', 'api_kabupaten_id'=>'', 'api_kecamatan_id'=>'', 'api_kelurahan_id'=>'',
        'provinsi'=>'', 'kabupaten'=>'', 'kecamatan'=>'', 'kelurahan'=>'',
        'alamat_lengkap'=>'', 'kode_pos'=>'',
        'nama_bank_desa'=>'', 'no_rekening_desa'=>'', 'atas_nama_rekening_desa'=>'', 'qris_image_url_desa'=>'',
        'total_pendapatan' => 0, 'saldo_komisi' => 0
    ];

    if ($action_view === 'edit' && $id_view > 0) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id = %d", $id_view));
    }
    if (!$edit_data) $edit_data = $default_data;

    // --- USER FILTER LOGIC ---
    $used_user_ids = $wpdb->get_col("SELECT id_user_desa FROM $table_desa");
    if (!$used_user_ids) $used_user_ids = [];

    $users = get_users([
        'orderby' => 'display_name', 
        'role'    => 'admin_desa'
    ]);

    // --- USED REGION LOGIC (PREVENT DUPLICATE VILLAGE) ---
    $used_kelurahan_ids = $wpdb->get_col("SELECT api_kelurahan_id FROM $table_desa WHERE api_kelurahan_id != ''");
    if ($action_view === 'edit' && !empty($edit_data->api_kelurahan_id)) {
        $used_kelurahan_ids = array_diff($used_kelurahan_ids, [$edit_data->api_kelurahan_id]);
    }
    $used_kelurahan_ids = array_values($used_kelurahan_ids);

    $count_verify = $wpdb->get_var("SELECT COUNT(*) FROM $table_desa WHERE status_akses_verifikasi = 'pending'");
    $total_pendapatan_all = $wpdb->get_var("SELECT SUM(total_pendapatan) FROM $table_desa") ?: 0;
    $total_saldo_komisi_all = $wpdb->get_var("SELECT SUM(saldo_komisi) FROM $table_desa") ?: 0;

    $total_desa = 0; $active_count = 0; $total_pending = 0;
    if (!$is_edit) {
        $total_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
        $active_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa WHERE status = 'aktif'");
        $total_pending = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa WHERE status_akses_verifikasi = 'pending'");
    }

    ?>
    <!-- CSS Styles -->
    <style>
        :root { --dw-primary: #2271b1; --dw-primary-dark: #135e96; --dw-success: #00a32a; --dw-warning: #dba617; --dw-danger: #d63638; --dw-gray-50: #f8fafc; --dw-gray-200: #e2e8f0; --dw-gray-700: #334155; --dw-radius: 6px; --dw-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .dw-container { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .dw-modern-tabs { display: flex; gap: 5px; border-bottom: 1px solid var(--dw-gray-200); margin-bottom: 20px; }
        .dw-modern-tab { text-decoration: none; color: var(--dw-gray-700); padding: 10px 15px; font-weight: 500; font-size: 14px; border: 1px solid transparent; border-bottom: none; border-radius: var(--dw-radius) var(--dw-radius) 0 0; display: flex; align-items: center; gap: 6px; background: #fff; }
        .dw-modern-tab:hover { background: var(--dw-gray-50); }
        .dw-modern-tab.active { border-color: var(--dw-gray-200); border-bottom-color: #fff; color: var(--dw-primary); font-weight: 600; margin-bottom: -1px; }
        .dw-badge-notify { background: var(--dw-danger); color: white; font-size: 10px; padding: 1px 6px; border-radius: 10px; }
        .dw-modern-card { background: white; border: 1px solid var(--dw-gray-200); border-radius: var(--dw-radius); box-shadow: var(--dw-shadow); padding: 0; margin-bottom: 20px; overflow: hidden; }
        .dw-card-header { padding: 15px 20px; border-bottom: 1px solid var(--dw-gray-200); background: #fff; display: flex; justify-content: space-between; align-items: center; }
        .dw-card-title { font-size: 16px; font-weight: 600; margin: 0; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        .dw-card-body { padding: 20px; }
        .dw-form-grid { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
        .dw-form-group { margin-bottom: 15px; }
        .dw-form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dw-gray-700); font-size: 13px; }
        .dw-input { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; }
        .dw-input:focus { border-color: var(--dw-primary); box-shadow: 0 0 0 1px var(--dw-primary); outline: none; }
        .dw-input-group { display: flex; }
        .dw-input-group input { border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none; }
        .dw-input-group button { border-top-left-radius: 0; border-bottom-left-radius: 0; border: 1px solid #cbd5e1; background: #f1f5f9; color: var(--dw-gray-700); padding: 0 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .dw-btn { padding: 8px 16px; border-radius: 4px; font-weight: 500; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid transparent; }
        .dw-btn-primary { background: var(--dw-primary); color: white; border-color: var(--dw-primary); }
        .dw-btn-outline { background: white; border-color: #cbd5e1; color: var(--dw-gray-700); }
        .dw-btn-danger { background: #fff; border-color: #fca5a5; color: var(--dw-danger); }
        .dw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .dw-stat-box { background: white; padding: 15px; border-radius: var(--dw-radius); border: 1px solid var(--dw-gray-200); display: flex; align-items: center; gap: 12px; }
        .dw-stat-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .dw-stat-icon.blue { background: #e0f2fe; color: #0284c7; }
        .dw-stat-icon.green { background: #dcfce7; color: #16a34a; }
        .dw-stat-icon.yellow { background: #fef9c3; color: #ca8a04; }
        .dw-stat-icon.purple { background: #f3e8ff; color: #9333ea; }
        .dw-stat-content h4 { margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; }
        .dw-stat-content span { font-size: 12px; color: #64748b; }
        .dw-table { width: 100%; border-collapse: collapse; }
        .dw-table th { text-align: left; padding: 12px 15px; background: var(--dw-gray-50); border-bottom: 1px solid var(--dw-gray-200); font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; }
        .dw-table td { padding: 12px 15px; border-bottom: 1px solid var(--dw-gray-200); vertical-align: middle; color: #334155; }
        .dw-table tr:hover { background: var(--dw-gray-50); }
        .dw-pill { padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
        .dw-pill.success { background: #dcfce7; color: #166534; }
        .dw-pill.warning { background: #fef9c3; color: #854d0e; }
        .dw-pill.gray { background: #f1f5f9; color: #475569; }
        .dw-pill.blue { background: #dbeafe; color: #1e40af; }
        .img-preview { width: 100%; height: 140px; background: var(--dw-gray-50); border: 2px dashed #cbd5e1; border-radius: 6px; object-fit: cover; margin-bottom: 10px; display: block; }
        .dw-text-muted { color: #64748b; font-size: 12px; }
        @media(max-width: 900px) { .dw-form-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="wrap dw-container">
        
        <!-- HEADER -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 class="wp-heading-inline" style="font-size: 24px; display:flex; align-items:center; gap:10px;">
                <span class="dashicons dashicons-admin-home" style="color:var(--dw-primary);"></span> Manajemen Desa Wisata
            </h1>
            <?php if (!$is_edit && $active_tab == 'data_desa'): ?>
                <a href="?page=dw-desa&tab=data_desa&view=add" class="dw-btn dw-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> Tambah Desa
                </a>
            <?php endif; ?>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if ($message): ?>
            <div style="margin-bottom: 20px; padding: 12px; border-radius: 6px; font-weight: 500; display: flex; align-items: center; gap: 10px; border: 1px solid transparent;
                <?php echo $message_type == 'success' ? 'background:#f0fdf4; color:#166534; border-color:#bbf7d0;' : 'background:#fef2f2; color:#991b1b; border-color:#fecaca;'; ?>">
                <span class="dashicons dashicons-<?php echo $message_type == 'success' ? 'yes' : 'warning'; ?>"></span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="dw-modern-tabs">
            <a href="?page=dw-desa&tab=data_desa" class="dw-modern-tab <?php echo $active_tab == 'data_desa' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-list-view"></span> Data Desa
            </a>
            <a href="?page=dw-desa&tab=verifikasi" class="dw-modern-tab <?php echo $active_tab == 'verifikasi' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-shield"></span> Verifikasi Premium
                <?php if($count_verify > 0) echo '<span class="dw-badge-notify">'.$count_verify.'</span>'; ?>
            </a>
            <a href="?page=dw-desa&tab=pengaturan" class="dw-modern-tab <?php echo $active_tab == 'pengaturan' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-money-alt"></span> Pengaturan Harga
            </a>
        </div>

        <!-- CONTENT: TAB DATA DESA -->
        <?php if($active_tab == 'data_desa'): ?>
            <?php if (!$is_edit): ?>
                
                <!-- STATS & TABLE -->
                <div class="dw-stats-grid">
                    <div class="dw-stat-box">
                        <div class="dw-stat-icon blue"><span class="dashicons dashicons-admin-site-alt3"></span></div>
                        <div class="dw-stat-content"><h4><?php echo $total_desa; ?></h4><span>Total Desa</span></div>
                    </div>
                    <div class="dw-stat-box">
                        <div class="dw-stat-icon green"><span class="dashicons dashicons-awards"></span></div>
                        <div class="dw-stat-content"><h4><?php echo $active_count; ?></h4><span>Aktif</span></div>
                    </div>
                    <div class="dw-stat-box">
                        <div class="dw-stat-icon purple"><span class="dashicons dashicons-chart-area"></span></div>
                        <div class="dw-stat-content">
                            <h4 style="font-size:16px;">Rp <?php echo number_format($total_pendapatan_all, 0, ',', '.'); ?></h4>
                            <span>Total Pendapatan</span>
                        </div>
                    </div>
                    <div class="dw-stat-box">
                        <div class="dw-stat-icon yellow"><span class="dashicons dashicons-money-alt"></span></div>
                        <div class="dw-stat-content">
                            <h4 style="font-size:16px;">Rp <?php echo number_format($total_saldo_komisi_all, 0, ',', '.'); ?></h4>
                            <span>Saldo Mengendap</span>
                        </div>
                    </div>
                </div>

                <div class="dw-modern-card">
                    <div class="dw-card-header">
                        <h3 class="dw-card-title">Daftar Desa Wisata</h3>
                        <form method="get" style="display:flex; gap:10px;">
                            <input type="hidden" name="page" value="dw-desa">
                            <input type="text" name="s" placeholder="Cari nama desa..." class="dw-input" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" style="width: 200px;">
                            <button class="dw-btn dw-btn-outline">Cari</button>
                        </form>
                    </div>
                    <div class="dw-card-body" style="padding:0;">
                        <?php
                        $search_q = isset($_GET['s']) ? esc_sql($_GET['s']) : '';
                        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                        $limit = 10;
                        $offset = ($paged - 1) * $limit;

                        $sql = "SELECT d.*, u.display_name as admin_name FROM {$table_desa} d LEFT JOIN {$table_users} u ON d.id_user_desa = u.ID WHERE 1=1 ";
                        if($search_q) $sql .= " AND (d.nama_desa LIKE '%$search_q%')";
                        $sql .= " ORDER BY d.created_at DESC LIMIT $offset, $limit";
                        
                        $rows = $wpdb->get_results($sql);
                        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
                        $total_pages = ceil($total_items / $limit);
                        ?>

                        <table class="dw-table">
                            <thead>
                                <tr>
                                    <th width="60">Logo</th>
                                    <th>Nama Desa</th>
                                    <th>Lokasi</th>
                                    <th>Admin</th>
                                    <th>Keuangan</th>
                                    <th>Status Publikasi</th>
                                    <th>Status Akses</th>
                                    <th style="text-align:right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($rows): foreach($rows as $r): ?>
                                <tr>
                                    <td><img src="<?php echo $r->foto ? esc_url($r->foto) : 'https://via.placeholder.com/60'; ?>" style="width:40px; height:40px; border-radius:6px; object-fit:cover;"></td>
                                    <td>
                                        <strong><a href="?page=dw-desa&tab=data_desa&view=edit&id=<?php echo $r->id; ?>"><?php echo esc_html($r->nama_desa); ?></a></strong>
                                        <div class="dw-text-muted">Ref: <?php echo esc_html($r->kode_referral); ?></div>
                                    </td>
                                    <td><?php echo esc_html($r->kecamatan . ', ' . $r->kabupaten); ?></td>
                                    <td><?php echo esc_html($r->admin_name ?: '-'); ?></td>
                                    <td>
                                        <div style="font-size:11px;">Total: <strong>Rp <?php echo number_format($r->total_pendapatan, 0, ',', '.'); ?></strong></div>
                                        <div style="font-size:11px; color:var(--dw-warning);">Sisa: <strong>Rp <?php echo number_format($r->saldo_komisi, 0, ',', '.'); ?></strong></div>
                                    </td>
                                    <td>
                                        <?php if($r->status == 'aktif'): ?><span class="dw-pill success">Aktif</span>
                                        <?php else: ?><span class="dw-pill warning">Pending</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($r->status_akses_verifikasi == 'active'): ?><span class="dw-pill success">Premium</span>
                                        <?php elseif($r->status_akses_verifikasi == 'pending'): ?><span class="dw-pill warning">Pending</span>
                                        <?php else: ?><span class="dw-pill gray">Free</span><?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="?page=dw-desa&tab=data_desa&view=edit&id=<?php echo $r->id; ?>" class="dw-btn dw-btn-outline dw-btn-sm" title="Edit"><span class="dashicons dashicons-edit"></span></a>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('Yakin hapus desa ini? Data pedagang harus kosong.');">
                                            <?php wp_nonce_field('dw_desa_action'); ?>
                                            <input type="hidden" name="action_desa" value="delete">
                                            <input type="hidden" name="desa_id" value="<?php echo $r->id; ?>">
                                            <button class="dw-btn dw-btn-danger dw-btn-sm" title="Hapus"><span class="dashicons dashicons-trash"></span></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7" style="text-align:center; padding:30px;">Belum ada data desa.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <?php if($total_pages > 1): ?>
                            <div style="padding:15px; text-align:right; border-top:1px solid #e2e8f0;">
                                <?php echo paginate_links(['total' => $total_pages, 'current' => $paged]); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- FORM ADD / EDIT -->
                <form method="post" class="dw-form-grid">
                    <?php wp_nonce_field('dw_save_desa_nonce'); ?>
                    <input type="hidden" name="dw_action" value="save_desa">
                    <?php if($edit_data->id > 0): ?><input type="hidden" name="desa_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <!-- Sidebar -->
                    <div class="dw-sidebar">
                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Media & Status</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <label>Logo Desa</label>
                                    <img src="<?php echo !empty($edit_data->foto) ? esc_url($edit_data->foto) : ''; ?>" class="img-preview" id="preview_foto">
                                    <input type="hidden" name="foto_url" id="foto_url" value="<?php echo esc_attr($edit_data->foto); ?>">
                                    <button type="button" class="dw-btn dw-btn-outline dw-btn-sm btn_upload" data-target="#foto_url" data-preview="#preview_foto" style="width:100%; justify-content:center;">Upload Logo</button>
                                </div>
                                
                                <div class="dw-form-group">
                                    <label>Status Publikasi</label>
                                    <select name="status" class="dw-input">
                                        <option value="aktif" <?php selected($edit_data->status, 'aktif'); ?>>Aktif (Publik)</option>
                                        <option value="pending" <?php selected($edit_data->status, 'pending'); ?>>Pending (Menunggu)</option>
                                    </select>
                                </div>

                                <div class="dw-form-group">
                                    <label>Status Premium (Verifikasi)</label>
                                    <select name="status_akses_verifikasi" class="dw-input">
                                        <option value="locked" <?php selected($edit_data->status_akses_verifikasi, 'locked'); ?>>Locked (Free)</option>
                                        <option value="pending" <?php selected($edit_data->status_akses_verifikasi, 'pending'); ?>>Pending Review</option>
                                        <option value="active" <?php selected($edit_data->status_akses_verifikasi, 'active'); ?>>Active (Premium)</option>
                                    </select>
                                </div>

                                <div class="dw-form-group">
                                    <label>Bukti Bayar Akses</label>
                                    <img src="<?php echo !empty($edit_data->bukti_bayar_akses) ? esc_url($edit_data->bukti_bayar_akses) : ''; ?>" class="img-preview" id="preview_bukti" style="height:100px;">
                                    <input type="hidden" name="bukti_bayar_akses_url" id="bukti_bayar_akses_url" value="<?php echo esc_attr($edit_data->bukti_bayar_akses); ?>">
                                    <button type="button" class="dw-btn dw-btn-outline dw-btn-sm btn_upload" data-target="#bukti_bayar_akses_url" data-preview="#preview_bukti" style="width:100%; justify-content:center;">Upload Bukti</button>
                                </div>

                                <div class="dw-form-group">
                                    <label>Alasan Penolakan (Jika ada)</label>
                                    <textarea name="alasan_penolakan" class="dw-input" rows="2"><?php echo esc_textarea($edit_data->alasan_penolakan); ?></textarea>
                                </div>

                                <?php if($edit_data->id > 0): ?>
                                <div class="dw-form-group" style="padding:10px; background:#f8fafc; border-radius:4px; border:1px solid #e2e8f0;">
                                    <label style="color:var(--dw-primary);">Info Keuangan (Read-only)</label>
                                    <div style="font-size:12px; margin-bottom:4px;">Total Pendapatan: <strong>Rp <?php echo number_format($edit_data->total_pendapatan, 0, ',', '.'); ?></strong></div>
                                    <div style="font-size:12px;">Saldo Mengendap: <strong style="color:var(--dw-warning);">Rp <?php echo number_format($edit_data->saldo_komisi, 0, ',', '.'); ?></strong></div>
                                </div>
                                <?php endif; ?>
                                <button type="submit" class="dw-btn dw-btn-primary" style="width:100%; justify-content:center;">Simpan Data</button>
                                <a href="?page=dw-desa" class="dw-btn dw-btn-outline" style="width:100%; justify-content:center; margin-top:10px;">Batal</a>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="dw-content">
                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Informasi Utama</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <label>Admin Pengelola (User WP)</label>
                                    <select name="id_user_desa" class="dw-input select2" required>
                                        <option value="">-- Pilih User --</option>
                                        <?php 
                                        foreach($users as $u) {
                                            $is_selected = ($edit_data->id_user_desa == $u->ID);
                                            // Check if user is already used by another desa (unless it's the current one)
                                            $is_used = in_array($u->ID, $used_user_ids) && !$is_selected;
                                            
                                            // Hide if used, or show if current or unused
                                            if (!$is_used) {
                                                echo '<option value="'.$u->ID.'" '.selected($edit_data->id_user_desa, $u->ID, false).'>'.$u->display_name.' ('.$u->user_email.')</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <div class="dw-text-muted" style="margin-top:5px;">Hanya menampilkan user dengan role 'Admin Desa' yang belum memiliki desa.</div>
                                </div>
                                <div class="dw-form-group">
                                    <label>Nama Desa Wisata</label>
                                    <input type="text" name="nama_desa" id="inp_nama_desa" class="dw-input" value="<?php echo esc_attr($edit_data->nama_desa); ?>" required>
                                </div>
                                <div class="dw-form-group">
                                    <label>Kode Referral (Otomatis)</label>
                                    <div class="dw-input-group">
                                        <input type="text" name="kode_referral" id="inp_kode_ref" class="dw-input" value="<?php echo esc_attr($edit_data->kode_referral); ?>" placeholder="Otomatis (Isi Wilayah Terlebih Dahulu)" readonly>
                                        <button type="button" id="btnGenRef" title="Generate Otomatis"><span class="dashicons dashicons-randomize"></span></button>
                                    </div>
                                    <small class="dw-text-muted">Format: [3 PRO]-[3 KAB]-[3 DES]-[4 ACAK]. Klik tombol dadu setelah mengisi data wilayah.</small>
                                </div>
                                <div class="dw-form-group">
                                    <label>Deskripsi</label>
                                    <?php wp_editor($edit_data->deskripsi, 'deskripsi', ['textarea_rows' => 5, 'media_buttons' => false, 'editor_class' => 'dw-input']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Lokasi & Wilayah</h3></div>
                            <div class="dw-card-body">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                                    <div class="dw-form-group">
                                        <label>Provinsi</label>
                                        <select name="api_provinsi_id" class="dw-input dw-region-prov" data-current="<?php echo esc_attr($edit_data->api_provinsi_id); ?>"><option value="">Loading...</option></select>
                                        <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Kabupaten</label>
                                        <select name="api_kabupaten_id" class="dw-input dw-region-kota" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id); ?>"><option value="">Pilih Provinsi Dulu</option></select>
                                        <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Kecamatan</label>
                                        <select name="api_kecamatan_id" class="dw-input dw-region-kec" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id); ?>"><option value="">Pilih Kabupaten Dulu</option></select>
                                        <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Kelurahan</label>
                                        <select name="api_kelurahan_id" class="dw-input dw-region-desa" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id); ?>"><option value="">Pilih Kecamatan Dulu</option></select>
                                        <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan); ?>">
                                    </div>
                                </div>
                                <div class="dw-form-group">
                                    <label>Alamat Lengkap</label>
                                    <textarea name="alamat_lengkap" class="dw-input" rows="2"><?php echo esc_textarea($edit_data->alamat_lengkap); ?></textarea>
                                </div>
                                <div class="dw-form-group" style="width: 50%;">
                                    <label>Kode Pos</label>
                                    <input type="text" name="kode_pos" id="inp_kode_pos" class="dw-input" value="<?php echo esc_attr($edit_data->kode_pos); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Rekening Pencairan Komisi</h3></div>
                            <div class="dw-card-body">
                                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                                    <div class="dw-form-group">
                                        <label>Nama Bank</label>
                                        <input type="text" name="nama_bank_desa" class="dw-input" value="<?php echo esc_attr($edit_data->nama_bank_desa); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>No. Rekening</label>
                                        <input type="text" name="no_rekening_desa" class="dw-input" value="<?php echo esc_attr($edit_data->no_rekening_desa); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Atas Nama</label>
                                        <input type="text" name="atas_nama_rekening_desa" class="dw-input" value="<?php echo esc_attr($edit_data->atas_nama_rekening_desa); ?>">
                                    </div>
                                </div>
                                <div class="dw-form-group">
                                    <label>QRIS (Opsional)</label>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" name="qris_url" id="qris_url" class="dw-input" value="<?php echo esc_attr($edit_data->qris_image_url_desa); ?>" readonly>
                                        <button type="button" class="dw-btn dw-btn-outline btn_upload" data-target="#qris_url">Upload</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Sampul Halaman</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <img src="<?php echo !empty($edit_data->foto_sampul) ? esc_url($edit_data->foto_sampul) : ''; ?>" class="img-preview" id="preview_sampul" style="height:200px;">
                                    <input type="hidden" name="foto_sampul_url" id="foto_sampul_url" value="<?php echo esc_attr($edit_data->foto_sampul); ?>">
                                    <button type="button" class="dw-btn dw-btn-outline btn_upload" data-target="#foto_sampul_url" data-preview="#preview_sampul">Upload Foto Sampul</button>
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
                <div class="dw-card-header"><h3 class="dw-card-title">Antrean Verifikasi Upgrade Premium</h3></div>
                <div class="dw-card-body">
                    <?php if(empty($pending_verif)): ?>
                        <div style="text-align:center; padding:40px; color:#64748b;">
                            <span class="dashicons dashicons-yes-alt" style="font-size:40px; width:40px; height:40px; color:var(--dw-success); margin-bottom:10px;"></span>
                            <p>Tidak ada permintaan verifikasi saat ini.</p>
                        </div>
                    <?php else: foreach($pending_verif as $p): ?>
                        <div style="border:1px solid #e2e8f0; border-radius:6px; padding:15px; margin-bottom:15px; display:flex; gap:20px; align-items:flex-start;">
                            <!-- Bukti -->
                            <div style="width:120px; height:120px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                                <?php if($p->bukti_bayar_akses): ?>
                                    <a href="<?php echo esc_url($p->bukti_bayar_akses); ?>" target="_blank"><img src="<?php echo esc_url($p->bukti_bayar_akses); ?>" style="width:100%; height:100%; object-fit:cover;"></a>
                                <?php else: ?><span class="dashicons dashicons-format-image" style="color:#cbd5e1; font-size:32px;"></span><?php endif; ?>
                            </div>
                            <!-- Detail -->
                            <div style="flex:1;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                    <h4 style="margin:0; font-size:16px;"><?php echo esc_html($p->nama_desa); ?></h4>
                                    <span class="dw-pill warning">Pending</span>
                                </div>
                                <p style="margin:0 0 15px; color:#64748b; font-size:13px;">Lokasi: <?php echo esc_html($p->kecamatan.', '.$p->kabupaten); ?></p>
                                
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <form method="post">
                                        <?php wp_nonce_field('dw_verify_desa'); ?>
                                        <input type="hidden" name="action_verify_desa" value="1">
                                        <input type="hidden" name="desa_id" value="<?php echo $p->id; ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <button type="submit" class="dw-btn dw-btn-primary dw-btn-sm">Setujui Premium</button>
                                    </form>
                                    <button type="button" class="dw-btn dw-btn-outline dw-btn-sm" onclick="jQuery('#reject-box-<?php echo $p->id; ?>').toggle();">Tolak</button>
                                </div>
                                <!-- Reject Form -->
                                <div id="reject-box-<?php echo $p->id; ?>" style="display:none; margin-top:10px; background:#fef2f2; padding:10px; border-radius:4px; border:1px solid #fecaca;">
                                    <form method="post" style="display:flex; gap:10px;">
                                        <?php wp_nonce_field('dw_verify_desa'); ?>
                                        <input type="hidden" name="action_verify_desa" value="1">
                                        <input type="hidden" name="desa_id" value="<?php echo $p->id; ?>">
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="text" name="alasan_penolakan" class="dw-input" placeholder="Alasan penolakan..." required style="padding:6px;">
                                        <button type="submit" class="dw-btn dw-btn-danger dw-btn-sm">Kirim</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        <!-- CONTENT: TAB PENGATURAN -->
        <?php elseif($active_tab == 'pengaturan'): 
            $settings = get_option('dw_settings', []);
            $harga = isset($settings['harga_premium_desa']) ? $settings['harga_premium_desa'] : 0;
        ?>
            <div class="dw-modern-card" style="max-width:500px;">
                <div class="dw-card-header"><h3 class="dw-card-title">Pengaturan Harga Premium</h3></div>
                <div class="dw-card-body">
                    <form method="post">
                        <?php wp_nonce_field('dw_desa_settings_save'); ?>
                        <input type="hidden" name="action_save_settings" value="1">
                        <div class="dw-form-group">
                            <label>Biaya Upgrade (Rp)</label>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span style="font-weight:bold; color:#64748b;">Rp</span>
                                <input type="number" name="harga_premium_desa" class="dw-input" value="<?php echo esc_attr($harga); ?>" style="font-size:16px; font-weight:bold;">
                            </div>
                            <p class="dw-text-muted" style="margin-top:5px;">Biaya yang harus dibayar admin desa untuk fitur premium.</p>
                        </div>
                        <button type="submit" class="dw-btn dw-btn-primary">Simpan Pengaturan</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Scripts -->
    <script>
    jQuery(document).ready(function($){
        // Upload Media
        $('.btn_upload').click(function(e){
            e.preventDefault(); var btn = $(this), target = btn.data('target'), preview = btn.data('preview');
            var frame = wp.media({title:'Pilih Gambar', multiple:false}).on('select', function(){
                var url = frame.state().get('selection').first().toJSON().url;
                $(target).val(url); $(preview).attr('src', url).show();
            }).open();
        });

        // GENERATOR KODE REFERRAL (JS)
        $('#btnGenRef').click(function(e){
            e.preventDefault();
            
            // Cek apakah input sudah terisi (Mode Edit) - Jika ada, minta konfirmasi
            var currentVal = $('#inp_kode_ref').val();
            if(currentVal && !confirm('Kode Referral sudah terisi: "' + currentVal + '". Apakah Anda yakin ingin membuat ulang? Kode lama akan hilang.')){
                return;
            }

            var prov = $('.dw-text-prov').val() || '';
            var kab  = $('.dw-text-kota').val() || '';
            var kel  = $('.dw-text-desa').val() || '';
            var namaDesa = $('#inp_nama_desa').val() || '';
            
            if(!namaDesa && !kel){
                alert('Gagal Generate: Harap isi Nama Desa atau pilih Wilayah (Kelurahan) terlebih dahulu.');
                return;
            }

            // Helper Logic: 3 Huruf (Smart Mapping)
            function getCode(text, type) {
                if(!text) return 'XXX';
                var clean = text.toLowerCase().trim();
                // Hapus awalan umum
                clean = clean.replace(/^(provinsi|kabupaten|kota|desa|kelurahan)\s+/g, '');
                
                // Mapping Khusus (sama dengan PHP)
                if(type === 'prov') {
                    if(clean === 'jawa barat') return 'JAB';
                    if(clean === 'jawa tengah') return 'JTG';
                    if(clean === 'jawa timur') return 'JTM';
                    if(clean.includes('jakarta')) return 'DKI';
                    if(clean.includes('yogyakarta')) return 'DIY';
                }
                
                // Remove spaces and take first 3 chars
                return clean.replace(/\s/g, '').substring(0,3).toUpperCase();
            }
            
            // Prioritas: Wilayah -> Nama Desa
            var cProv = getCode(prov, 'prov');
            var cKab  = getCode(kab);
            var cDes  = getCode(kel ? kel : namaDesa); // Fallback ke nama desa jika kelurahan blm dipilih
            
            // Generate 4 digit acak
            var rand = Math.floor(1000 + Math.random() * 9000); // 1000-9999
            
            $('#inp_kode_ref').val(cProv + '-' + cKab + '-' + cDes + '-' + rand);
        });
        
        // Pass PHP array to JS
        var usedKelurahan = <?php echo json_encode($used_kelurahan_ids); ?>;
        // Ensure strings
        usedKelurahan = usedKelurahan.map(String);

        // Region API
        function loadRegion(type, pid, target, selId) {
            var act = type==='prov'?'dw_fetch_provinces':(type==='kota'?'dw_fetch_regencies':(type==='kec'?'dw_fetch_districts':'dw_fetch_villages'));
            if(type!=='prov' && !pid) return;
            $.get(ajaxurl, { action:act, province_id:pid, regency_id:pid, district_id:pid, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
                if(res.success) {
                    target.empty().append('<option value="">Pilih...</option>');
                    $.each(res.data, function(i,v){ 
                        // Tambahkan data-pos jika ada (untuk kelurahan)
                        let pos = v.postal_code || '';
                        
                        let isDisabled = '';
                        let suffix = '';
                        
                        // Check if duplicate village
                        if (type === 'desa' && usedKelurahan.includes(String(v.id))) {
                            isDisabled = 'disabled';
                            suffix = ' (Sudah Terdaftar)';
                        }
                        
                        target.append(`<option value="${v.id}" data-nama="${v.name}" data-pos="${pos}" ${(String(v.id)===String(selId)?'selected':'')} ${isDisabled}>${v.name}${suffix}</option>`); 
                    });
                }
            });
        }
        
        $('.dw-region-prov').change(function(){ $('.dw-text-prov').val($(this).find('option:selected').text()); loadRegion('kota', $(this).val(), $('.dw-region-kota'), null); });
        $('.dw-region-kota').change(function(){ $('.dw-text-kota').val($(this).find('option:selected').text()); loadRegion('kec', $(this).val(), $('.dw-region-kec'), null); });
        $('.dw-region-kec').change(function(){ $('.dw-text-kec').val($(this).find('option:selected').text()); loadRegion('desa', $(this).val(), $('.dw-region-desa'), null); });
        
        // Auto Fill Postal Code on Village Change
        $('.dw-region-desa').change(function(){ 
            $('.dw-text-desa').val($(this).find('option:selected').text()); 
            // Use attribute to get correct postal code
            let pos = $(this).find('option:selected').attr('data-pos');
            if(pos) {
                $('#inp_kode_pos').val(pos);
            }
        });

        // Init
        loadRegion('prov', null, $('.dw-region-prov'), $('.dw-region-prov').data('current'));
        $('.dw-region-prov').trigger('change'); 
        
        // Manual trigger for edit mode waterfall (simple version)
        var p = $('.dw-region-prov').data('current'); if(p) loadRegion('kota', p, $('.dw-region-kota'), $('.dw-region-kota').data('current'));
        var k = $('.dw-region-kota').data('current'); if(k) loadRegion('kec', k, $('.dw-region-kec'), $('.dw-region-kec').data('current'));
        var c = $('.dw-region-kec').data('current'); if(c) loadRegion('desa', c, $('.dw-region-desa'), $('.dw-region-desa').data('current'));

        if($.fn.select2) $('.select2').select2({width:'100%'});
    });
    </script>
    <?php
}