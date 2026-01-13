<?php
/**
 * File Name: includes/admin-pages/page-desa.php
 * Description: CRUD Desa Wisata & Verifikasi dengan UI/UX Modern.
 * Matches DB Table: dw_desa
 * Version: 7.1 (Tab UI/UX Refinement)
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// --- 1. FIX PATHS & INCLUDES ---
$current_dir = dirname(__FILE__); 
$includes_dir = dirname($current_dir);

// Include UI components
$ui_path = $includes_dir . '/admin-ui-components.php';
if (file_exists($ui_path)) {
    require_once $ui_path;
}

// Pastikan class API Address tersedia
$address_api_path = $includes_dir . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

// Pastikan Class Logic Referral tersedia
if ( ! class_exists( 'DW_Referral_Logic' ) ) {
    $logic_path = $includes_dir . '/class-dw-referral-logic.php';
    if (file_exists($logic_path)) require_once $logic_path;
}

/**
 * Render Halaman Manajemen Desa
 * FUNGSI UTAMA: dw_desa_page_render
 */
if (!function_exists('dw_desa_page_render')) {
    function dw_desa_page_render() {
        global $wpdb;
        
        // Definisi Nama Tabel
        $table_desa     = $wpdb->prefix . 'dw_desa';
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        $table_users    = $wpdb->users;
        
        // Enqueue Media Uploader WP
        if ( ! did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }
        
        $message = '';
        $message_type = '';

        // Action handling from URL
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
                    $message = 'Desa berhasil di-upgrade ke status PREMIUM (Active).';
                    $message_type = 'success';
                } else {
                    $reason = sanitize_textarea_field($_POST['alasan_penolakan']);
                    $wpdb->update($table_desa, ['status_akses_verifikasi' => 'locked', 'alasan_penolakan' => $reason], ['id' => $desa_id]);
                    $message = 'Pengajuan Premium ditolak. Status dikembalikan ke Free (Locked).';
                    $message_type = 'warning';
                }
            }

            // C. DELETE DESA
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
                    
                    // --- GENERATE REFERRAL CODE ---
                    $kode_referral = sanitize_text_field($_POST['kode_referral']);
                    
                    // Jika kosong, generate otomatis
                    if (empty($kode_referral)) {
                        $prov_txt = sanitize_text_field($_POST['provinsi_text']);
                        $kab_txt  = sanitize_text_field($_POST['kabupaten_text']);
                        $kel_txt  = sanitize_text_field($_POST['kelurahan_text']);
                        $nama_desa_input = sanitize_text_field($_POST['nama_desa']);

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

                    // MAPPING DATA
                    $data = [
                        'id_user_desa'            => intval($_POST['id_user_desa']),
                        'nama_desa'               => sanitize_text_field($_POST['nama_desa']),
                        'slug_desa'               => sanitize_title($_POST['nama_desa']),
                        'kode_referral'           => $kode_referral,
                        'deskripsi'               => wp_kses_post($_POST['deskripsi']),
                        'nomor_wa'                => sanitize_text_field($_POST['nomor_wa']), 
                        'jam_buka'                => sanitize_text_field($_POST['jam_buka']),
                        'jam_tutup'               => sanitize_text_field($_POST['jam_tutup']),
                        'foto'                    => esc_url_raw($_POST['foto_url']),
                        'foto_sampul'             => esc_url_raw($_POST['foto_sampul_url']),
                        'status'                  => sanitize_text_field($_POST['status']), 
                        'no_rekening_desa'        => sanitize_text_field($_POST['no_rekening_desa']),
                        'nama_bank_desa'          => sanitize_text_field($_POST['nama_bank_desa']),
                        'atas_nama_rekening_desa' => sanitize_text_field($_POST['atas_nama_rekening_desa']),
                        'qris_image_url_desa'     => esc_url_raw($_POST['qris_url']),
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
        
        // --- DATA PREPARATION ---
        $is_edit = ($action_view === 'edit' || $action_view === 'add');
        $edit_data = null;
        
        $default_data = (object) [
            'id' => 0, 'id_user_desa' => 0, 'nama_desa' => '', 'deskripsi' => '',
            'kode_referral' => '', 
            'nomor_wa' => '', 'status' => 'pending', 
            'foto' => '', 'foto_sampul' => '',
            'status_akses_verifikasi' => 'locked', 
            'bukti_bayar_akses' => '', 'alasan_penolakan' => '',
            'api_provinsi_id'=>'', 'api_kabupaten_id'=>'', 'api_kecamatan_id'=>'', 'api_kelurahan_id'=>'',
            'provinsi'=>'', 'kabupaten'=>'', 'kecamatan'=>'', 'kelurahan'=>'',
            'alamat_lengkap'=>'', 'kode_pos'=>'',
            'nama_bank_desa'=>'', 'no_rekening_desa'=>'', 'atas_nama_rekening_desa'=>'', 'qris_image_url_desa'=>'',
            'jam_buka' => '', 'jam_tutup' => '',
            'total_pendapatan' => 0, 'saldo_komisi' => 0
        ];

        if ($action_view === 'edit' && $id_view > 0) {
            $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id = %d", $id_view));
        }
        if (!$edit_data) $edit_data = $default_data;

        // User list for dropdown
        $used_user_ids = $wpdb->get_col("SELECT id_user_desa FROM $table_desa");
        if (!$used_user_ids) $used_user_ids = [];
        $users = get_users(['orderby' => 'display_name', 'role' => 'desa_admin']);

        // Stats
        $count_verify = $wpdb->get_var("SELECT COUNT(*) FROM $table_desa WHERE status_akses_verifikasi = 'pending'");
        $total_pendapatan_all = $wpdb->get_var("SELECT SUM(total_pendapatan) FROM $table_desa") ?: 0;
        $total_saldo_komisi_all = $wpdb->get_var("SELECT SUM(saldo_komisi) FROM $table_desa") ?: 0;

        $total_desa = 0; $active_count = 0; 
        if (!$is_edit) {
            $total_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
            $active_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa WHERE status = 'aktif'");
        }

        ?>

        <!-- INLINE CSS STYLE FOR TABS (PREMIUM UI) -->
        <style>
            /* Custom Tab Style */
            .dw-tabs-wrapper {
                margin-bottom: 25px;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                gap: 5px;
            }
            .dw-tab-link {
                text-decoration: none;
                color: #64748b;
                padding: 12px 20px;
                font-weight: 600;
                font-size: 14px;
                border-bottom: 2px solid transparent;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s ease;
            }
            .dw-tab-link:hover {
                color: #1e40af;
                background-color: #f8fafc;
                border-radius: 6px 6px 0 0;
            }
            .dw-tab-link.active {
                color: #1e40af;
                border-bottom: 2px solid #1e40af;
            }
            .dw-tab-link .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
            .dw-badge-notify {
                background: #ef4444;
                color: white;
                font-size: 10px;
                padding: 2px 6px;
                border-radius: 12px;
                margin-left: 5px;
                line-height: 1;
            }
            /* Stats Box Tweaks */
            .dw-stats-grid { margin-top: 0; }
        </style>

        <!-- WRAPPER UTAMA -->
        <div class="wrap dw-admin-wrapper">
            
            <!-- HEADER -->
            <div class="dw-page-header">
                <div class="dw-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p class="dw-subtitle">Kelola daftar desa wisata, verifikasi akun, dan pengaturan.</p>
                </div>
                <div class="dw-header-actions">
                    <?php if (!$is_edit && $active_tab == 'data_desa'): ?>
                        <a href="?page=dw-desa&tab=data_desa&view=add" class="dw-button dw-button-primary">
                            <span class="dashicons dashicons-plus-alt2"></span> Tambah Desa
                        </a>
                    <?php elseif($is_edit): ?>
                         <a href="?page=dw-desa" class="dw-button dw-button-secondary">
                            <span class="dashicons dashicons-arrow-left-alt"></span> Kembali
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NOTIFIKASI -->
            <?php if ($message): ?>
                <div style="margin-bottom: 20px; padding: 12px; border-radius: 6px; font-weight: 500; display: flex; align-items: center; gap: 10px; border: 1px solid transparent;
                    <?php echo $message_type == 'success' ? 'background:#f0fdf4; color:#166534; border-color:#bbf7d0;' : 'background:#fef2f2; color:#991b1b; border-color:#fecaca;'; ?>">
                    <span class="dashicons dashicons-<?php echo $message_type == 'success' ? 'yes' : 'warning'; ?>"></span>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- TABS (PREMIUM DESIGN) -->
            <?php if(!$is_edit): ?>
            <div class="dw-tabs-wrapper">
                <a href="?page=dw-desa&tab=data_desa" class="dw-tab-link <?php echo $active_tab == 'data_desa' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span> Data Desa
                </a>
                <a href="?page=dw-desa&tab=verifikasi" class="dw-tab-link <?php echo $active_tab == 'verifikasi' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-shield"></span> Verifikasi Premium
                    <?php if($count_verify > 0) echo '<span class="dw-badge-notify">' . $count_verify . '</span>'; ?>
                </a>
                <a href="?page=dw-desa&tab=pengaturan" class="dw-tab-link <?php echo $active_tab == 'pengaturan' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-money-alt"></span> Pengaturan Harga
                </a>
            </div>
            <?php endif; ?>

            <!-- CONTENT BODY -->
            <div class="dw-content-body">

                <!-- TAB 1: DATA DESA -->
                <?php if($active_tab == 'data_desa'): ?>
                    
                    <!-- VIEW: LIST -->
                    <?php if (!$is_edit): ?>
                        
                        <!-- STATS GRID -->
                        <div class="dw-stats-grid">
                            <div class="dw-stat-card">
                                <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-admin-site-alt3"></span></div>
                                <div class="dw-stat-info">
                                    <span class="dw-stat-label">Total Desa</span>
                                    <h4 class="dw-stat-value"><?php echo $total_desa; ?></h4>
                                </div>
                            </div>
                            <div class="dw-stat-card">
                                <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-awards"></span></div>
                                <div class="dw-stat-info">
                                    <span class="dw-stat-label">Aktif</span>
                                    <h4 class="dw-stat-value"><?php echo $active_count; ?></h4>
                                </div>
                            </div>
                            <div class="dw-stat-card">
                                <div class="dw-stat-icon-wrapper bg-purple"><span class="dashicons dashicons-chart-area"></span></div>
                                <div class="dw-stat-info">
                                    <span class="dw-stat-label">Total Pendapatan</span>
                                    <h4 class="dw-stat-value">Rp <?php echo number_format($total_pendapatan_all, 0, ',', '.'); ?></h4>
                                </div>
                            </div>
                            <div class="dw-stat-card">
                                <div class="dw-stat-icon-wrapper bg-orange"><span class="dashicons dashicons-money-alt"></span></div>
                                <div class="dw-stat-info">
                                    <span class="dw-stat-label">Saldo Mengendap</span>
                                    <h4 class="dw-stat-value">Rp <?php echo number_format($total_saldo_komisi_all, 0, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>

                        <div class="dw-card">
                            <div class="dw-card-header">
                                <h3 class="card-heading">Daftar Desa Wisata</h3>
                                <form method="get" style="display:flex; gap:10px;">
                                    <input type="hidden" name="page" value="dw-desa">
                                    <input type="text" name="s" placeholder="Cari nama desa..." class="dw-input" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" style="width: 200px;">
                                    <button class="dw-button dw-button-secondary">Cari</button>
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

                                <div class="dw-table-wrapper" style="border:none; border-radius:0;">
                                    <table class="dw-modern-table">
                                        <thead>
                                            <tr>
                                                <th width="60">Logo</th>
                                                <th>Nama Desa</th>
                                                <th>Lokasi</th>
                                                <th>Admin</th>
                                                <th>Keuangan</th>
                                                <th>Status</th>
                                                <th>Premium</th>
                                                <th style="text-align:right;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($rows): foreach($rows as $r): ?>
                                            <tr>
                                                <td><img src="<?php echo $r->foto ? esc_url($r->foto) : 'https://via.placeholder.com/60'; ?>" style="width:40px; height:40px; border-radius:6px; object-fit:cover;"></td>
                                                <td>
                                                    <strong><a href="?page=dw-desa&tab=data_desa&view=edit&id=<?php echo $r->id; ?>" class="dw-btn-link"><?php echo esc_html($r->nama_desa); ?></a></strong>
                                                    <div class="dw-text-muted" style="font-size:12px; color:#646970;">Ref: <?php echo esc_html($r->kode_referral); ?></div>
                                                </td>
                                                <td>
                                                    <div style="font-size:13px; font-weight:500;"><?php echo esc_html($r->kecamatan); ?></div>
                                                    <div style="font-size:11px; color:#64748b;"><?php echo esc_html($r->kabupaten); ?></div>
                                                </td>
                                                <td><?php echo esc_html($r->admin_name ?: '-'); ?></td>
                                                <td>
                                                    <div style="font-size:11px;">Total: <strong>Rp <?php echo number_format($r->total_pendapatan, 0, ',', '.'); ?></strong></div>
                                                    <div style="font-size:11px; color:#d63638;">Sisa: <strong>Rp <?php echo number_format($r->saldo_komisi, 0, ',', '.'); ?></strong></div>
                                                </td>
                                                <td>
                                                    <?php if($r->status == 'aktif'): ?><span class="dw-badge status-success">Aktif</span>
                                                    <?php else: ?><span class="dw-badge status-warning">Pending</span><?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if($r->status_akses_verifikasi == 'active'): ?><span class="dw-badge status-success">Ya</span>
                                                    <?php elseif($r->status_akses_verifikasi == 'pending'): ?><span class="dw-badge status-warning">Pending</span>
                                                    <?php else: ?><span class="dw-badge status-neutral">Tidak</span><?php endif; ?>
                                                </td>
                                                <td style="text-align:right;">
                                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                                        <a href="?page=dw-desa&tab=data_desa&view=edit&id=<?php echo $r->id; ?>" class="dw-button dw-button-secondary" style="padding: 4px 10px; font-size: 12px; min-height:unset;">Edit</a>
                                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('Yakin hapus desa ini? Data pedagang harus kosong.');">
                                                            <?php wp_nonce_field('dw_desa_action'); ?>
                                                            <input type="hidden" name="action_desa" value="delete">
                                                            <input type="hidden" name="desa_id" value="<?php echo $r->id; ?>">
                                                            <button class="dw-button" style="padding: 4px 8px; font-size: 12px; color:#d63638; border:none; background:none; cursor:pointer; min-height:unset;">Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; else: ?>
                                                <tr><td colspan="8" style="text-align:center; padding:30px;">Belum ada data desa.</td></tr>
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
                        </div>

                    <!-- VIEW: ADD / EDIT -->
                    <?php else: ?>
                        <form method="post" class="dw-form-grid">
                            <?php wp_nonce_field('dw_save_desa_nonce'); ?>
                            <input type="hidden" name="dw_action" value="save_desa">
                            <?php if($edit_data->id > 0): ?><input type="hidden" name="desa_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                            <div class="dw-grid-2-col">
                                
                                <!-- Left Column: Main Content -->
                                <div class="dw-content">
                                    <div class="dw-card">
                                        <div class="dw-card-header"><h3 class="card-heading">Informasi Utama</h3></div>
                                        <div class="dw-card-body">
                                            <div class="dw-form-group">
                                                <label class="dw-label">Admin Pengelola (User WP)</label>
                                                <select name="id_user_desa" class="dw-input select2" required>
                                                    <option value="">-- Pilih User --</option>
                                                    <?php 
                                                    foreach($users as $u) {
                                                        $is_selected = ($edit_data->id_user_desa == $u->ID);
                                                        
                                                        // Cek apakah user sudah terpakai
                                                        $is_used = array_key_exists($u->ID, $user_village_map);
                                                        $village_name = $is_used ? $user_village_map[$u->ID] : '';
                                                        
                                                        // Tampilkan user yang dipilih ATAU belum terpakai
                                                        if ($is_selected || !$is_used) {
                                                            echo '<option value="'.$u->ID.'" '.selected($edit_data->id_user_desa, $u->ID, false).'>'.$u->display_name.' ('.$u->user_email.')</option>';
                                                        } else {
                                                            // Opsi: Tampilkan tapi disable untuk info
                                                            echo '<option value="'.$u->ID.'" disabled style="color:#a0a0a0;">'.$u->display_name.' (Terhubung: '.$village_name.')</option>';
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                                <div class="dw-help-text">Menampilkan user dengan role 'Admin Desa' atau 'Administrator'. User yang sudah terhubung dengan desa lain tidak dapat dipilih.</div>
                                            </div>
                                            
                                            <div class="dw-form-group">
                                                <label class="dw-label">Nama Desa Wisata</label>
                                                <input type="text" name="nama_desa" id="inp_nama_desa" class="dw-input" value="<?php echo esc_attr($edit_data->nama_desa); ?>" required>
                                            </div>

                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Nomor WhatsApp</label>
                                                    <input type="text" name="nomor_wa" class="dw-input" value="<?php echo esc_attr($edit_data->nomor_wa); ?>" placeholder="08xxxxxxxxxx">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kode Referral</label>
                                                    <div style="display:flex; gap:10px;">
                                                        <input type="text" name="kode_referral" id="inp_kode_ref" class="dw-input" value="<?php echo esc_attr($edit_data->kode_referral); ?>" placeholder="Generate Otomatis" readonly>
                                                        <button type="button" id="btnGenRef" class="dw-button dw-button-secondary" title="Generate Otomatis"><span class="dashicons dashicons-randomize"></span></button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Jam Buka</label>
                                                    <input type="time" name="jam_buka" class="dw-input" value="<?php echo esc_attr($edit_data->jam_buka); ?>">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Jam Tutup</label>
                                                    <input type="time" name="jam_tutup" class="dw-input" value="<?php echo esc_attr($edit_data->jam_tutup); ?>">
                                                </div>
                                            </div>

                                            <div class="dw-form-group">
                                                <label class="dw-label">Deskripsi</label>
                                                <?php wp_editor($edit_data->deskripsi, 'deskripsi', ['textarea_rows' => 5, 'media_buttons' => false, 'editor_class' => 'dw-input']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dw-card">
                                        <div class="dw-card-header"><h3 class="card-heading">Lokasi & Wilayah</h3></div>
                                        <div class="dw-card-body">
                                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Provinsi</label>
                                                    <select name="api_provinsi_id" class="dw-input dw-region-prov" data-current="<?php echo esc_attr($edit_data->api_provinsi_id); ?>"><option value="">Loading...</option></select>
                                                    <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi); ?>">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kabupaten</label>
                                                    <select name="api_kabupaten_id" class="dw-input dw-region-kota" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id); ?>"><option value="">Pilih Provinsi Dulu</option></select>
                                                    <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten); ?>">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kecamatan</label>
                                                    <select name="api_kecamatan_id" class="dw-input dw-region-kec" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id); ?>"><option value="">Pilih Kabupaten Dulu</option></select>
                                                    <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan); ?>">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kelurahan</label>
                                                    <select name="api_kelurahan_id" class="dw-input dw-region-desa" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id); ?>"><option value="">Pilih Kecamatan Dulu</option></select>
                                                    <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan); ?>">
                                                </div>
                                            </div>
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Alamat Lengkap</label>
                                                    <textarea name="alamat_lengkap" class="dw-input" rows="2"><?php echo esc_textarea($edit_data->alamat_lengkap); ?></textarea>
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kode Pos</label>
                                                    <input type="text" name="kode_pos" id="inp_kode_pos" class="dw-input" value="<?php echo esc_attr($edit_data->kode_pos); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dw-card">
                                        <div class="dw-card-header"><h3 class="card-heading">Rekening Pencairan Komisi</h3></div>
                                        <div class="dw-card-body">
                                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Nama Bank</label>
                                                    <input type="text" name="nama_bank_desa" class="dw-input" value="<?php echo esc_attr($edit_data->nama_bank_desa); ?>">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">No. Rekening</label>
                                                    <input type="text" name="no_rekening_desa" class="dw-input" value="<?php echo esc_attr($edit_data->no_rekening_desa); ?>">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Atas Nama</label>
                                                    <input type="text" name="atas_nama_rekening_desa" class="dw-input" value="<?php echo esc_attr($edit_data->atas_nama_rekening_desa); ?>">
                                                </div>
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">QRIS (Opsional)</label>
                                                <div style="display:flex; gap:10px;">
                                                    <input type="text" name="qris_url" id="qris_url" class="dw-input" value="<?php echo esc_attr($edit_data->qris_image_url_desa); ?>" readonly>
                                                    <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="#qris_url">Upload</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="dw-card">
                                        <div class="dw-card-header"><h3 class="card-heading">Sampul Halaman</h3></div>
                                        <div class="dw-card-body">
                                            <div class="dw-form-group">
                                                <div style="margin-bottom:10px;">
                                                    <img src="<?php echo !empty($edit_data->foto_sampul) ? esc_url($edit_data->foto_sampul) : 'https://via.placeholder.com/600x200'; ?>" class="img-preview" id="preview_sampul" style="max-width:100%; height:auto;">
                                                </div>
                                                <input type="hidden" name="foto_sampul_url" id="foto_sampul_url" value="<?php echo esc_attr($edit_data->foto_sampul); ?>">
                                                <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="#foto_sampul_url" data-preview="#preview_sampul">Upload Foto Sampul</button>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Right Column: Sidebar -->
                                <div class="dw-sidebar">
                                    <div class="dw-card">
                                        <div class="dw-card-header"><h3 class="card-heading">Media & Status</h3></div>
                                        <div class="dw-card-body">
                                            <div class="dw-form-group">
                                                <label class="dw-label">Logo Desa</label>
                                                <div style="margin-bottom:10px; background:#f0f2f5; padding:10px; border-radius:8px; text-align:center;">
                                                    <img src="<?php echo !empty($edit_data->foto) ? esc_url($edit_data->foto) : 'https://via.placeholder.com/150'; ?>" class="img-preview" id="preview_foto" style="max-width:100%; height:auto; border-radius:4px;">
                                                </div>
                                                <input type="hidden" name="foto_url" id="foto_url" value="<?php echo esc_attr($edit_data->foto); ?>">
                                                <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="#foto_url" data-preview="#preview_foto" style="width:100%;">Upload Logo</button>
                                            </div>
                                            
                                            <div class="dw-form-group">
                                                <label class="dw-label">Status Publikasi</label>
                                                <select name="status" class="dw-input">
                                                    <option value="aktif" <?php selected($edit_data->status, 'aktif'); ?>>Aktif (Publik)</option>
                                                    <option value="pending" <?php selected($edit_data->status, 'pending'); ?>>Pending (Menunggu)</option>
                                                </select>
                                            </div>

                                            <div class="dw-form-group">
                                                <label class="dw-label">Status Premium (Verifikasi)</label>
                                                <select name="status_akses_verifikasi" class="dw-input">
                                                    <option value="locked" <?php selected($edit_data->status_akses_verifikasi, 'locked'); ?>>Locked (Free)</option>
                                                    <option value="pending" <?php selected($edit_data->status_akses_verifikasi, 'pending'); ?>>Pending Review</option>
                                                    <option value="active" <?php selected($edit_data->status_akses_verifikasi, 'active'); ?>>Active (Premium)</option>
                                                </select>
                                            </div>

                                            <?php if($edit_data->id > 0): ?>
                                            <div class="dw-form-group" style="padding:15px; background:#f0f9ff; border-radius:8px; border:1px solid #bae6fd;">
                                                <label style="color:#0369a1; font-weight:600; font-size:13px; display:block; margin-bottom:8px;">Info Keuangan (Read-only)</label>
                                                <div style="font-size:12px; margin-bottom:4px; display:flex; justify-content:space-between;">
                                                    <span>Pendapatan:</span>
                                                    <strong>Rp <?php echo number_format($edit_data->total_pendapatan, 0, ',', '.'); ?></strong>
                                                </div>
                                                <div style="font-size:12px; display:flex; justify-content:space-between;">
                                                    <span>Saldo:</span>
                                                    <strong style="color:#b45309;">Rp <?php echo number_format($edit_data->saldo_komisi, 0, ',', '.'); ?></strong>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <hr style="margin: 20px 0; border:0; border-top:1px solid #e2e8f0;">
                                            
                                            <button type="submit" class="dw-button dw-button-primary" style="width:100%; margin-bottom:10px;">Simpan Data</button>
                                            <a href="?page=dw-desa" class="dw-button dw-button-secondary" style="width:100%; text-align:center;">Batal</a>
                                        </div>
                                    </div>

                                    <div class="dw-card">
                                        <div class="dw-card-header"><h3 class="card-heading">Bukti Bayar</h3></div>
                                        <div class="dw-card-body">
                                            <div class="dw-form-group">
                                                <div style="margin-bottom:10px; background:#f0f2f5; padding:10px; border-radius:8px; text-align:center;">
                                                    <img src="<?php echo !empty($edit_data->bukti_bayar_akses) ? esc_url($edit_data->bukti_bayar_akses) : 'https://via.placeholder.com/150?text=No+Image'; ?>" class="img-preview" id="preview_bukti" style="max-width:100%; height:auto;">
                                                </div>
                                                <input type="hidden" name="bukti_bayar_akses_url" id="bukti_bayar_akses_url" value="<?php echo esc_attr($edit_data->bukti_bayar_akses); ?>">
                                                <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="#bukti_bayar_akses_url" data-preview="#preview_bukti" style="width:100%;">Upload Bukti</button>
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">Alasan Penolakan (Jika ada)</label>
                                                <textarea name="alasan_penolakan" class="dw-input" rows="2" placeholder="Tulis alasan jika menolak..."><?php echo esc_textarea($edit_data->alasan_penolakan); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                
                <?php elseif($active_tab == 'verifikasi'): 
                    $pending_verif = $wpdb->get_results("SELECT * FROM $table_desa WHERE status_akses_verifikasi = 'pending' ORDER BY updated_at ASC");
                ?>
                    <!-- TAB 2: VERIFIKASI -->
                    <div class="dw-card">
                        <div class="dw-card-header"><h3 class="card-heading">Antrean Verifikasi Upgrade Premium</h3></div>
                        <div class="dw-card-body">
                            <?php if(empty($pending_verif)): ?>
                                <div style="text-align:center; padding:40px; color:#64748b;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size:40px; width:40px; height:40px; color:var(--dw-success); margin-bottom:10px;"></span>
                                    <p>Tidak ada permintaan verifikasi saat ini.</p>
                                </div>
                            <?php else: foreach($pending_verif as $p): ?>
                                <div style="border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px; display:flex; gap:24px; align-items:flex-start; background:#fff;">
                                    <div style="width:120px; height:120px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                        <?php if($p->bukti_bayar_akses): ?>
                                            <a href="<?php echo esc_url($p->bukti_bayar_akses); ?>" target="_blank"><img src="<?php echo esc_url($p->bukti_bayar_akses); ?>" style="width:100%; height:100%; object-fit:cover;"></a>
                                        <?php else: ?><span class="dashicons dashicons-format-image" style="color:#cbd5e1; font-size:32px;"></span><?php endif; ?>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                            <div>
                                                <h4 style="margin:0 0 4px 0; font-size:16px; color:var(--dw-text-dark);"><?php echo esc_html($p->nama_desa); ?></h4>
                                                <span class="dw-badge status-warning">Menunggu Konfirmasi</span>
                                            </div>
                                            <div style="text-align:right;">
                                                <small style="color:#64748b;">Diajukan pada:</small><br>
                                                <strong style="font-size:13px;"><?php echo date('d M Y, H:i', strtotime($p->updated_at)); ?></strong>
                                            </div>
                                        </div>
                                        <p style="margin:0 0 16px; color:#64748b; font-size:13px; line-height:1.5;">
                                            <strong>Lokasi:</strong> <?php echo esc_html($p->kecamatan.', '.$p->kabupaten); ?>
                                        </p>
                                        
                                        <div style="display:flex; gap:12px; align-items:center;">
                                            <form method="post">
                                                <?php wp_nonce_field('dw_verify_desa'); ?>
                                                <input type="hidden" name="action_verify_desa" value="1">
                                                <input type="hidden" name="desa_id" value="<?php echo $p->id; ?>">
                                                <input type="hidden" name="decision" value="approve">
                                                <button type="submit" class="dw-button dw-button-primary"><span class="dashicons dashicons-yes" style="margin-right:5px;"></span> Setujui Premium</button>
                                            </form>
                                            <button type="button" class="dw-button dw-button-secondary" onclick="jQuery('#reject-box-<?php echo $p->id; ?>').toggle();" style="color:#ef4444; border-color:#fecaca; background:#fef2f2;">Tolak</button>
                                        </div>
                                        
                                        <div id="reject-box-<?php echo $p->id; ?>" style="display:none; margin-top:15px; background:#fff1f2; padding:15px; border-radius:8px; border:1px solid #fecaca;">
                                            <form method="post" style="display:flex; gap:10px;">
                                                <?php wp_nonce_field('dw_verify_desa'); ?>
                                                <input type="hidden" name="action_verify_desa" value="1">
                                                <input type="hidden" name="desa_id" value="<?php echo $p->id; ?>">
                                                <input type="hidden" name="decision" value="reject">
                                                <input type="text" name="alasan_penolakan" class="dw-input" placeholder="Tulis alasan penolakan..." required style="padding:8px;">
                                                <button type="submit" class="dw-button" style="background:#ef4444; color:#fff; border:none; padding:0 20px;">Kirim Penolakan</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                
                <?php elseif($active_tab == 'pengaturan'): 
                    $settings = get_option('dw_settings', []);
                    $harga = isset($settings['harga_premium_desa']) ? $settings['harga_premium_desa'] : 0;
                ?>
                    <!-- TAB 3: PENGATURAN -->
                    <div class="dw-card" style="max-width:500px;">
                        <div class="dw-card-header"><h3 class="card-heading">Pengaturan Harga Premium</h3></div>
                        <div class="dw-card-body">
                            <form method="post">
                                <?php wp_nonce_field('dw_desa_settings_save'); ?>
                                <input type="hidden" name="action_save_settings" value="1">
                                <div class="dw-form-group">
                                    <label class="dw-label">Biaya Upgrade (Rp)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span style="font-weight:bold; color:#64748b; font-size:18px;">Rp</span>
                                        <input type="number" name="harga_premium_desa" class="dw-input" value="<?php echo esc_attr($harga); ?>" style="font-size:18px; font-weight:bold; padding:12px;">
                                    </div>
                                    <p class="dw-help-text">Biaya yang harus dibayar admin desa untuk mendapatkan fitur premium (Verifikasi).</p>
                                </div>
                                <button type="submit" class="dw-button dw-button-primary">Simpan Pengaturan</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </div> <!-- End dw-content-body -->

        </div>

    <!-- Scripts -->
    <script>
    jQuery(document).ready(function($){
        // Upload Media
        $('.btn_upload').click(function(e){
            e.preventDefault(); var btn = $(this), target = btn.data('target'), preview = btn.data('preview');
            var frame = wp.media({title:'Pilih Gambar', multiple:false}).on('select', function(){
                var url = frame.state().get('selection').first().toJSON().url;
                $(target).val(url); 
                if(preview) $(preview).attr('src', url).show();
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
                        target.append(`<option value="${v.id}" data-nama="${v.name}" data-pos="${pos}" ${(String(v.id)===String(selId)?'selected':'')}>${v.name}</option>`); 
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
        // Manual trigger for edit mode waterfall (simple version)
        var p = $('.dw-region-prov').data('current'); 
        if(p) {
            loadRegion('kota', p, $('.dw-region-kota'), $('.dw-region-kota').data('current'));
            var k = $('.dw-region-kota').data('current'); 
            if(k) {
                loadRegion('kec', k, $('.dw-region-kec'), $('.dw-region-kec').data('current'));
                var c = $('.dw-region-kec').data('current'); 
                if(c) loadRegion('desa', c, $('.dw-region-desa'), $('.dw-region-desa').data('current'));
            }
        }

        if($.fn.select2) $('.select2').select2({width:'100%'});
    });
    </script>
    <?php
    }
}