<?php
/**
 * File Name: includes/admin-pages/page-pedagang.php
 * Description: Manajemen Pedagang dengan UI/UX Modern (Refactored Design, Logic Preserved)
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// 1. Pastikan class API Address tersedia
$address_api_path = dirname(dirname(__FILE__)) . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

// Include UI components if needed
$ui_path = dirname(dirname(__FILE__)) . '/admin-ui-components.php';
if (file_exists($ui_path)) {
    require_once $ui_path;
}

/**
 * Render Halaman Manajemen Pedagang
 * FUNGSI UTAMA: dw_pedagang_page_render
 */
if (!function_exists('dw_pedagang_page_render')) {
    function dw_pedagang_page_render() {
        // Pastikan Media Uploader WordPress tersedia
        if ( ! did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_pedagang';
        $table_desa = $wpdb->prefix . 'dw_desa';
        $table_verifikator = $wpdb->prefix . 'dw_verifikator';
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

                // 2. Relasi Referral / Verifikator
                $terdaftar_melalui_kode = sanitize_text_field($_POST['terdaftar_melalui_kode']);
                $id_verifikator = 0; 

                if (!empty($terdaftar_melalui_kode)) {
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

                if (isset($_POST['status_pendaftaran']) && $_POST['status_pendaftaran'] === 'disetujui') {
                    $is_verified = 1;
                    if (!empty($_POST['pedagang_id'])) {
                        $old_data = $wpdb->get_row($wpdb->prepare("SELECT verified_at, approved_by FROM $table_name WHERE id = %d", intval($_POST['pedagang_id'])));
                        $verified_at = ($old_data && $old_data->verified_at) ? $old_data->verified_at : current_time('mysql');
                        $approved_by = ($old_data) ? $old_data->approved_by : '';
                    } else {
                        $verified_at = current_time('mysql');
                    }

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

                // Struktur Data Zona Ojek + Blacklist
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
                    ],
                    // NEW: Blacklist Ongkir (Tidak tercover)
                    'blacklist' => [
                        'desa_ids' => $safe_array_map($_POST['ojek_blacklist_desa_ids'] ?? null),
                        'kecamatan_ids' => $safe_array_map($_POST['ojek_blacklist_kec_ids'] ?? null)
                    ]
                ];

                // DATA UTAMA
                $data = [
                    'id_user'          => $id_user,
                    'id_desa'          => $id_desa, 
                    'id_verifikator'   => $id_verifikator, 
                    'is_independent'   => $is_independent,
                    'nama_toko'        => $nama_toko,
                    'slug_toko'        => sanitize_title($nama_toko),
                    'nama_pemilik'     => sanitize_text_field($_POST['nama_pemilik'] ?? ''),
                    'nomor_wa'         => sanitize_text_field($_POST['nomor_wa'] ?? ''),
                    'jam_buka'         => sanitize_text_field($_POST['jam_buka'] ?? ''),
                    'jam_tutup'        => sanitize_text_field($_POST['jam_tutup'] ?? ''),
                    'alamat_lengkap'   => sanitize_textarea_field($_POST['pedagang_detail'] ?? ''),
                    'url_gmaps'        => esc_url_raw($_POST['url_gmaps'] ?? ''),
                    
                    'terdaftar_melalui_kode' => $terdaftar_melalui_kode,
                    'nik'              => sanitize_text_field($_POST['nik'] ?? ''),
                    'url_ktp'          => esc_url_raw($_POST['url_ktp'] ?? ''),
                    'foto_profil'      => esc_url_raw($_POST['foto_profil'] ?? ''),
                    'foto_sampul'      => esc_url_raw($_POST['foto_sampul'] ?? ''),
                    'no_rekening'      => sanitize_text_field($_POST['no_rekening'] ?? ''),
                    'nama_bank'        => sanitize_text_field($_POST['nama_bank'] ?? ''),
                    'atas_nama_rekening' => sanitize_text_field($_POST['atas_nama_rekening'] ?? ''),
                    'qris_image_url'   => esc_url_raw($_POST['qris_image_url'] ?? ''),
                    
                    'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran'] ?? ''),
                    'status_akun'        => $status_sekarang,
                    'is_verified'        => $is_verified,
                    'verified_at'        => $verified_at,
                    'approved_by'        => $approved_by,
                    'sisa_transaksi'     => intval($_POST['sisa_transaksi']),
                    
                    'api_provinsi_id'    => sanitize_text_field($_POST['pedagang_prov']),
                    'api_kabupaten_id'   => sanitize_text_field($_POST['pedagang_kota']),
                    'api_kecamatan_id'   => sanitize_text_field($_POST['pedagang_kec']),
                    'api_kelurahan_id'   => $kelurahan_id,
                    'provinsi_nama'      => sanitize_text_field($_POST['provinsi_text']),
                    'kabupaten_nama'     => sanitize_text_field($_POST['kabupaten_text']),
                    'kecamatan_nama'     => sanitize_text_field($_POST['kecamatan_text']),
                    'kelurahan_nama'     => sanitize_text_field($_POST['kelurahan_text']),
                    'kode_pos'           => sanitize_text_field($_POST['kode_pos']),

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
                    $data['kode_referral_saya'] = strtoupper(substr(md5(uniqid($nama_toko, true)), 0, 8));
                    $data['created_at'] = current_time('mysql');
                    $wpdb->insert($table_name, $data);
                    $message = 'Pedagang baru berhasil ditambahkan.'; $message_type = 'success';
                }
            }
        }

        // --- PREPARE DATA VIEW ---
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        if (isset($_GET['page']) && $_GET['page'] == 'dw-pedagang-new') {
            $action = 'edit'; 
        }

        $edit_data = null;
        $ojek_zona = null; 

        if ($action === 'edit' && !empty($_GET['id'])) {
            $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
             if($edit_data && !empty($edit_data->shipping_ojek_lokal_zona)) {
                $ojek_zona = json_decode($edit_data->shipping_ojek_lokal_zona, true);
            }
        }
        
        // Default ojek zona structure
        if (!$ojek_zona) {
            $ojek_zona = [
                'satu_kecamatan' => ['dekat' => [], 'jauh' => []],
                'beda_kecamatan' => ['dekat' => [], 'jauh' => []],
                'blacklist' => ['desa_ids' => [], 'kecamatan_ids' => []] // Default empty blacklist
            ];
        }
        // Ensure blacklist key exists for backward compatibility
        if(!isset($ojek_zona['blacklist'])) $ojek_zona['blacklist'] = ['desa_ids' => [], 'kecamatan_ids' => []];

        $users = get_users(['role__in' => ['administrator', 'pedagang', 'subscriber', 'customer']]);
        
        // Statistik Dashboard
        $total_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
        $independent_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE is_independent = 1");
        $with_desa_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE is_independent = 0");
        $total_transaksi = $wpdb->get_var("SELECT SUM(sisa_transaksi) FROM $table_name");
        $total_transaksi = $total_transaksi ? number_format($total_transaksi) : '0';

        ?>

        <!-- WRAPPER UTAMA -->
        <div class="wrap dw-admin-wrapper">
            
            <!-- HEADER -->
            <div class="dw-page-header">
                <div class="dw-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p class="dw-subtitle">Kelola daftar toko, pedagang, dan pengaturan wilayah.</p>
                </div>
                <div class="dw-header-actions">
                    <?php if ($action === 'list'): ?>
                        <a href="?page=dw-pedagang&action=edit" class="dw-button dw-button-primary">
                            <span class="dashicons dashicons-plus-alt2" style="margin-right:5px;"></span> Tambah Pedagang
                        </a>
                    <?php else: ?>
                        <a href="?page=dw-pedagang" class="dw-button dw-button-secondary">
                            <span class="dashicons dashicons-arrow-left-alt" style="margin-right:5px;"></span> Kembali
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

            <!-- BODY CONTENT -->
            <div class="dw-content-body">
                
                <?php if ($action === 'list'): ?>
                    
                    <!-- STATS GRID -->
                    <div class="dw-stats-grid">
                        <div class="dw-stat-card">
                            <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-groups"></span></div>
                            <h4 class="dw-stat-value"><?php echo number_format($total_pedagang); ?></h4>
                            <span class="dw-stat-label">Total Pedagang</span>
                        </div>
                        <div class="dw-stat-card">
                            <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-admin-home"></span></div>
                            <h4 class="dw-stat-value"><?php echo number_format($with_desa_count); ?></h4>
                            <span class="dw-stat-label">Mitra Desa</span>
                        </div>
                        <div class="dw-stat-card">
                            <div class="dw-stat-icon-wrapper bg-orange"><span class="dashicons dashicons-admin-users"></span></div>
                            <h4 class="dw-stat-value"><?php echo number_format($independent_count); ?></h4>
                            <span class="dw-stat-label">Independen</span>
                        </div>
                        <div class="dw-stat-card">
                            <div class="dw-stat-icon-wrapper bg-purple"><span class="dashicons dashicons-chart-pie"></span></div>
                            <h4 class="dw-stat-value"><?php echo $total_transaksi; ?></h4>
                            <span class="dw-stat-label">Total Kuota Transaksi</span>
                        </div>
                    </div>

                    <!-- VIEW: LIST TABLE -->
                    <div class="dw-card">
                        <div class="dw-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                            <h3 class="card-heading">Data Pedagang</h3>
                            <form id="dw-pedagang-search" method="get" style="display:flex; gap:10px;">
                                <input type="hidden" name="page" value="dw-pedagang" />
                                <input type="text" name="s" class="dw-input" placeholder="Cari pedagang..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" style="width:200px;">
                                <button class="dw-button dw-button-secondary">Cari</button>
                            </form>
                        </div>
                        <div class="dw-card-body" style="padding:0;">
                            <div class="dw-table-wrapper" style="border:none; border-radius:0;">
                                <table class="dw-modern-table" id="table-pedagang">
                                    <thead>
                                        <tr>
                                            <th style="width:60px;">ID</th>
                                            <th>Nama Toko</th>
                                            <th>Pemilik</th>
                                            <th>Wilayah</th>
                                            <th>Desa Asal</th>
                                            <th>Status Akun</th>
                                            <th>Verifikasi</th>
                                            <th style="width:150px; text-align:right;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $search_q = isset($_GET['s']) ? esc_sql($_GET['s']) : '';
                                        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                                        $limit = 10;
                                        $offset = ($paged - 1) * $limit;
                                        
                                        // JOIN Desa by API Kelurahan ID (Logic perbaikan dari sebelumnya)
                                        $sql_list = "SELECT p.*, d.nama_desa 
                                                     FROM $table_name p 
                                                     LEFT JOIN $table_desa d ON p.api_kelurahan_id = d.api_kelurahan_id 
                                                     WHERE 1=1";
                                        
                                        if($search_q) {
                                            $sql_list .= " AND (p.nama_toko LIKE '%$search_q%' OR p.nama_pemilik LIKE '%$search_q%')";
                                        }
                                        $sql_list .= " ORDER BY p.id DESC LIMIT $offset, $limit";
                                        
                                        $rows = $wpdb->get_results($sql_list);
                                        
                                        $total_items_sql = "SELECT COUNT(*) FROM $table_name p WHERE 1=1";
                                        if($search_q) $total_items_sql .= " AND (p.nama_toko LIKE '%$search_q%' OR p.nama_pemilik LIKE '%$search_q%')";
                                        $total_items = $wpdb->get_var($total_items_sql);
                                        $total_pages = ceil($total_items / $limit);
                                        
                                        if ($rows): foreach($rows as $r): 
                                            $user_info = get_userdata($r->id_user);
                                            $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}";
                                        ?>
                                            <tr>
                                                <td><span style="color:#94a3b8; font-weight:600;">#<?php echo $r->id; ?></span></td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:10px;">
                                                        <?php if($r->foto_profil): ?>
                                                            <img src="<?php echo esc_url($r->foto_profil); ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                                                        <?php else: ?>
                                                            <div style="width:32px; height:32px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center;">
                                                                <span class="dashicons dashicons-store" style="font-size:16px; color:#94a3b8;"></span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <strong style="color:var(--dw-text-dark); font-size:14px;">
                                                            <a href="<?php echo $edit_url; ?>" style="color:inherit; text-decoration:none;"><?php echo esc_html($r->nama_toko); ?></a>
                                                        </strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-weight:500; color:var(--dw-text-dark);"><?php echo $user_info ? $user_info->display_name : 'N/A'; ?></div>
                                                    <div style="font-size:12px; color:#646970;"><?php echo $user_info ? $user_info->user_email : ''; ?></div>
                                                </td>
                                                <td>
                                                    <div style="font-size:13px; font-weight:500;"><?php echo esc_html($r->kecamatan_nama); ?></div>
                                                    <div style="font-size:11px; color:#64748b;"><?php echo esc_html($r->kabupaten_nama); ?></div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($r->nama_desa)): ?>
                                                        <span class="dw-badge status-info" style="font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                                                            <span class="dashicons dashicons-admin-home" style="font-size:14px;"></span> <?php echo esc_html($r->nama_desa); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="dw-badge status-neutral">Independent</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_class = 'neutral';
                                                    if($r->status_akun === 'aktif') $status_class = 'success';
                                                    if($r->status_akun === 'nonaktif') $status_class = 'danger';
                                                    if($r->status_akun === 'suspend') $status_class = 'warning';
                                                    ?>
                                                    <span class="dw-badge status-<?php echo $status_class; ?>">
                                                        <?php echo ucfirst($r->status_akun); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $verif_class = 'warning';
                                                    if($r->status_pendaftaran === 'disetujui') $verif_class = 'success';
                                                    if($r->status_pendaftaran === 'ditolak') $verif_class = 'danger';
                                                    ?>
                                                    <span class="dw-badge status-<?php echo $verif_class; ?>">
                                                        <?php echo ucfirst($r->status_pendaftaran); ?>
                                                    </span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                                        <a href="<?php echo $edit_url; ?>" class="dw-button dw-button-secondary" style="padding: 6px 10px;" title="Edit Data">
                                                            <span class="dashicons dashicons-edit" style="margin:0;"></span>
                                                        </a>
                                                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus pedagang ini?');">
                                                            <?php wp_nonce_field('dw_pedagang_action'); ?>
                                                            <input type="hidden" name="action_pedagang" value="delete">
                                                            <input type="hidden" name="pedagang_id" value="<?php echo $r->id; ?>">
                                                            <button type="submit" class="dw-button" style="padding: 6px 10px; color:#ef4444; border:1px solid #fee2e2; background:#fef2f2; cursor:pointer;" title="Hapus">
                                                                <span class="dashicons dashicons-trash" style="margin:0;"></span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr><td colspan="8" style="text-align:center; padding:50px; color:#94a3b8;">Belum ada data pedagang.</td></tr>
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
                    <!-- === VIEW: ADD / EDIT FORM === -->
                    <form method="post" class="dw-form-grid">
                        <?php wp_nonce_field('dw_pedagang_action'); ?>
                        <input type="hidden" name="action_pedagang" value="save">
                        <input type="hidden" name="pedagang_id" value="<?php echo $edit_data->id ?? ''; ?>">

                        <div class="dw-dashboard-split">
                            <!-- LEFT COLUMN: MAIN CONTENT -->
                            <div class="dw-content">
                                <!-- TABS NAVIGATION -->
                                <div class="dw-card" style="padding:0; overflow:hidden; margin-bottom:20px;">
                                    <div class="dw-form-tabs" style="display:flex; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                                        <div class="dw-form-tab active" data-target="tab-umum" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                                            <span class="dashicons dashicons-admin-users"></span> Umum
                                        </div>
                                        <div class="dw-form-tab" data-target="tab-lokasi" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                                            <span class="dashicons dashicons-location"></span> Lokasi
                                        </div>
                                        <div class="dw-form-tab" data-target="tab-visual" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                                            <span class="dashicons dashicons-format-image"></span> Visual
                                        </div>
                                        <div class="dw-form-tab" data-target="tab-keuangan" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                                            <span class="dashicons dashicons-money-alt"></span> Keuangan
                                        </div>
                                        <div class="dw-form-tab" data-target="tab-pengaturan" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                                            <span class="dashicons dashicons-admin-generic"></span> Pengaturan
                                        </div>
                                    </div>

                                    <div class="dw-card-body">
                                        
                                        <!-- TAB: UMUM -->
                                        <div id="tab-umum" class="dw-tab-pane active">
                                            <div class="dw-form-group">
                                                <label class="dw-label">Nama Toko <span style="color:red;">*</span></label>
                                                <input name="nama_toko" type="text" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="dw-input" style="font-size:16px; padding:10px;" required>
                                            </div>
                                            
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">User Pemilik (WP User) <span style="color:red;">*</span></label>
                                                    <select name="id_user_pedagang" class="dw-input select2">
                                                        <?php foreach($users as $u): ?>
                                                            <option value="<?php echo $u->ID; ?>" <?php selected($edit_data->id_user ?? '', $u->ID); ?>><?php echo $u->display_name; ?> (<?php echo $u->user_email; ?>)</option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Nama Pemilik (Sesuai KTP)</label>
                                                    <input name="nama_pemilik" type="text" value="<?php echo esc_attr($edit_data->nama_pemilik ?? ''); ?>" class="dw-input">
                                                </div>
                                            </div>

                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Nomor WhatsApp</label>
                                                    <input name="nomor_wa" type="text" value="<?php echo esc_attr($edit_data->nomor_wa ?? ''); ?>" class="dw-input" placeholder="62812xxx">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">NIK Pemilik</label>
                                                    <input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="dw-input" placeholder="16 Digit NIK">
                                                </div>
                                            </div>
                                            
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Jam Buka</label>
                                                    <input name="jam_buka" type="time" value="<?php echo esc_attr($edit_data->jam_buka ?? ''); ?>" class="dw-input">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Jam Tutup</label>
                                                    <input name="jam_tutup" type="time" value="<?php echo esc_attr($edit_data->jam_tutup ?? ''); ?>" class="dw-input">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB: LOKASI -->
                                        <div id="tab-lokasi" class="dw-tab-pane" style="display:none;">
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Provinsi</label>
                                                    <select name="pedagang_prov" class="dw-input dw-region-prov" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>">
                                                        <option value="">Pilih Provinsi...</option>
                                                        <?php if(class_exists('DW_Address_API')): $provs = DW_Address_API::get_provinces(); foreach($provs as $v): ?>
                                                            <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_provinsi_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                                        <?php endforeach; endif; ?>
                                                    </select>
                                                    <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi_nama ?? ''); ?>">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kota/Kabupaten</label>
                                                    <select name="pedagang_kota" class="dw-input dw-region-kota" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>">
                                                        <option value="">Pilih Kota...</option>
                                                        <?php if($edit_data && !empty($edit_data->api_provinsi_id) && class_exists('DW_Address_API')): $cities = DW_Address_API::get_cities($edit_data->api_provinsi_id); foreach($cities as $v): ?>
                                                            <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kabupaten_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                                        <?php endforeach; endif; ?>
                                                    </select>
                                                    <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten_nama ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kecamatan</label>
                                                    <select name="pedagang_kec" class="dw-input dw-region-kec" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>">
                                                        <option value="">Pilih Kecamatan...</option>
                                                        <?php if($edit_data && !empty($edit_data->api_kabupaten_id) && class_exists('DW_Address_API')): $districts = DW_Address_API::get_subdistricts($edit_data->api_kabupaten_id); foreach($districts as $v): ?>
                                                            <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kecamatan_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                                        <?php endforeach; endif; ?>
                                                    </select>
                                                    <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan_nama ?? ''); ?>">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kelurahan/Desa</label>
                                                    <select name="pedagang_nama_id" class="dw-input dw-region-desa" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>">
                                                        <option value="">Pilih Kelurahan...</option>
                                                        <?php if($edit_data && !empty($edit_data->api_kecamatan_id) && class_exists('DW_Address_API')): $villages = DW_Address_API::get_villages($edit_data->api_kecamatan_id); foreach($villages as $v): ?>
                                                            <option value="<?php echo $v['id']; ?>" <?php selected($edit_data->api_kelurahan_id ?? '', $v['id']); ?>><?php echo $v['name']; ?></option>
                                                        <?php endforeach; endif; ?>
                                                    </select>
                                                    <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan_nama ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">Alamat Lengkap</label>
                                                <textarea name="pedagang_detail" class="dw-input" rows="3" placeholder="Nama jalan, nomor rumah, RT/RW..."><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                                            </div>
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Kode Pos</label>
                                                    <input name="kode_pos" id="inp_kode_pos" type="text" value="<?php echo esc_attr($edit_data->kode_pos ?? ''); ?>" class="dw-input" placeholder="Contoh: 12345">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">URL Google Maps</label>
                                                    <input name="url_gmaps" type="text" value="<?php echo esc_attr($edit_data->url_gmaps ?? ''); ?>" class="dw-input" placeholder="https://goo.gl/maps/...">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB: VISUAL -->
                                        <div id="tab-visual" class="dw-tab-pane" style="display:none;">
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Foto Profil Toko</label>
                                                    <div style="margin-bottom:12px; text-align:center; background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
                                                        <img id="prev_foto_profil" src="<?php echo esc_url($edit_data->foto_profil ?? 'https://placehold.co/150x150?text=Profil'); ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:4px solid #fff; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                                                    </div>
                                                    <div style="display:flex; gap:10px;">
                                                        <input type="text" name="foto_profil" id="foto_profil" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>" class="dw-input" readonly>
                                                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_profil" data-preview="#prev_foto_profil">Upload</button>
                                                    </div>
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Foto Sampul Toko</label>
                                                    <div style="margin-bottom:12px; background:#f8fafc; padding:10px; border-radius:12px; border:1px solid #e2e8f0;">
                                                        <img id="prev_foto_sampul" src="<?php echo esc_url($edit_data->foto_sampul ?? 'https://placehold.co/600x200?text=Sampul+Toko'); ?>" style="width:100%; height:100px; object-fit:cover; border-radius:8px;">
                                                    </div>
                                                    <div style="display:flex; gap:10px;">
                                                        <input type="text" name="foto_sampul" id="foto_sampul" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>" class="dw-input" readonly>
                                                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_sampul" data-preview="#prev_foto_sampul">Upload</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">NIK Pemilik</label>
                                                    <input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="dw-input" placeholder="Masukkan 16 digit NIK">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Foto KTP</label>
                                                    <div style="display:flex; gap:10px; align-items:center;">
                                                        <div style="width:60px; height:40px; border-radius:4px; overflow:hidden; border:1px solid #e2e8f0;">
                                                            <img id="prev_url_ktp" src="<?php echo esc_url($edit_data->url_ktp ?? 'https://placehold.co/60x40?text=KTP'); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                        </div>
                                                        <input type="text" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>" class="dw-input" readonly>
                                                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="url_ktp" data-preview="#prev_url_ktp">Upload</button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="dw-form-group">
                                                <label class="dw-label">Galeri Toko (Foto Tambahan)</label>
                                                <!-- Simple Gallery Logic Placeholder -->
                                                <p class="dw-help-text">Gunakan fitur galeri produk untuk detail lebih lanjut.</p>
                                                <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr($edit_data->galeri ?? ''); ?>">
                                            </div>
                                        </div>

                                        <!-- TAB: KEUANGAN -->
                                        <div id="tab-keuangan" class="dw-tab-pane" style="display:none;">
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Nama Bank</label>
                                                    <input name="nama_bank" type="text" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="dw-input" placeholder="Contoh: BCA, Mandiri">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Nomor Rekening</label>
                                                    <input name="no_rekening" type="text" value="<?php echo esc_attr($edit_data->no_rekening ?? ''); ?>" class="dw-input">
                                                </div>
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">Atas Nama Rekening</label>
                                                <input name="atas_nama_rekening" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening ?? ''); ?>" class="dw-input">
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">QRIS Pembayaran</label>
                                                <div style="display:flex; gap:10px;">
                                                    <div style="width:80px; height:80px; border:1px solid #ddd; padding:5px;">
                                                        <img id="prev_qris_image_url" src="<?php echo esc_url($edit_data->qris_image_url ?? 'https://placehold.co/150x150?text=QRIS'); ?>" style="width:100%; height:100%; object-fit:contain;">
                                                    </div>
                                                    <div style="flex:1;">
                                                        <input type="text" name="qris_image_url" id="qris_image_url" value="<?php echo esc_attr($edit_data->qris_image_url ?? ''); ?>" class="dw-input" readonly>
                                                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="qris_image_url" data-preview="#prev_qris_image_url" style="margin-top:5px;">Upload QRIS</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB: PENGATURAN -->
                                        <div id="tab-pengaturan" class="dw-tab-pane" style="display:none;">
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Sisa Kuota Transaksi</label>
                                                    <input name="sisa_transaksi" type="number" value="<?php echo esc_attr($edit_data->sisa_transaksi ?? '0'); ?>" class="dw-input">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Terdaftar Melalui Kode Referral</label>
                                                    <input name="terdaftar_melalui_kode" type="text" value="<?php echo esc_attr($edit_data->terdaftar_melalui_kode ?? ''); ?>" class="dw-input">
                                                </div>
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">Kode Referral Toko Saya</label>
                                                <input type="text" value="<?php echo esc_attr($edit_data->kode_referral_saya ?? '-'); ?>" class="dw-input" readonly>
                                            </div>
                                            
                                            <hr style="margin:20px 0; border:0; border-top:1px solid #e2e8f0;">
                                            <h4 class="dw-label" style="font-size:14px; margin-bottom:15px;">Pengaturan Ongkir</h4>
                                            
                                            <div class="dw-grid-2-col">
                                                <div class="dw-form-group">
                                                    <label style="display:flex; align-items:center; gap:10px;">
                                                        <input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked($edit_data->shipping_nasional_aktif ?? 0, 1); ?>>
                                                        Aktifkan Pengiriman Nasional (Flat)
                                                    </label>
                                                    <input name="shipping_nasional_harga" type="number" value="<?php echo esc_attr($edit_data->shipping_nasional_harga ?? '0'); ?>" class="dw-input" placeholder="Biaya Flat (Rp)" style="margin-top:5px;">
                                                </div>
                                                <div class="dw-form-group">
                                                    <label style="display:flex; align-items:center; gap:10px;">
                                                        <input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked($edit_data->shipping_ojek_lokal_aktif ?? 0, 1); ?>>
                                                        Aktifkan Ojek Lokal
                                                    </label>
                                                    
                                                    <!-- OJEK LOKAL INPUTS (UPDATED to SELECT2 List Desa/Kecamatan + Blacklist) -->
                                                    <div style="margin-top:10px; background:#f9f9f9; padding:15px; border-radius:4px; border:1px solid #e2e8f0;">
                                                        
                                                        <!-- Zona 1: Satu Kecamatan -->
                                                        <strong style="display:block; margin-bottom:10px; color:var(--dw-primary);">Zona 1: Satu Kecamatan</strong>
                                                        <div class="dw-grid-2-col">
                                                            <div>
                                                                <label style="font-size:12px;">Tarif Dekat (Rp)</label>
                                                                <input type="number" name="ojek_dekat_harga" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['dekat']['harga'] ?? 0); ?>" class="dw-input">
                                                                <label style="font-size:12px; margin-top:5px; display:block;">Pilih Desa (Dekat)</label>
                                                                <select name="ojek_dekat_desa_ids[]" class="dw-input select2-villages" multiple="multiple">
                                                                    <?php if(!empty($ojek_zona['satu_kecamatan']['dekat']['desa_ids'])): 
                                                                        foreach($ojek_zona['satu_kecamatan']['dekat']['desa_ids'] as $vid) echo "<option value='$vid' selected>$vid</option>"; 
                                                                    endif; ?>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label style="font-size:12px;">Tarif Jauh (Rp)</label>
                                                                <input type="number" name="ojek_jauh_harga" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['jauh']['harga'] ?? 0); ?>" class="dw-input">
                                                                <label style="font-size:12px; margin-top:5px; display:block;">Pilih Desa (Jauh)</label>
                                                                <select name="ojek_jauh_desa_ids[]" class="dw-input select2-villages" multiple="multiple">
                                                                    <?php if(!empty($ojek_zona['satu_kecamatan']['jauh']['desa_ids'])): 
                                                                        foreach($ojek_zona['satu_kecamatan']['jauh']['desa_ids'] as $vid) echo "<option value='$vid' selected>$vid</option>"; 
                                                                    endif; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div style="border-top:1px dashed #ddd; margin:15px 0;"></div>

                                                        <!-- Zona 2: Beda Kecamatan -->
                                                        <strong style="display:block; margin-bottom:10px; color:var(--dw-primary);">Zona 2: Beda Kecamatan</strong>
                                                        <div class="dw-grid-2-col">
                                                            <div>
                                                                <label style="font-size:12px;">Tarif Dekat (Rp)</label>
                                                                <input type="number" name="ojek_beda_kec_dekat_harga" value="<?php echo esc_attr($ojek_zona['beda_kecamatan']['dekat']['harga'] ?? 0); ?>" class="dw-input">
                                                                <label style="font-size:12px; margin-top:5px; display:block;">Pilih Kecamatan (Dekat)</label>
                                                                <select name="ojek_beda_kec_dekat_ids[]" class="dw-input select2-districts" multiple="multiple">
                                                                    <?php if(!empty($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'])): 
                                                                        foreach($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'] as $kid) echo "<option value='$kid' selected>$kid</option>"; 
                                                                    endif; ?>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label style="font-size:12px;">Tarif Jauh (Rp)</label>
                                                                <input type="number" name="ojek_beda_kec_jauh_harga" value="<?php echo esc_attr($ojek_zona['beda_kecamatan']['jauh']['harga'] ?? 0); ?>" class="dw-input">
                                                                <label style="font-size:12px; margin-top:5px; display:block;">Pilih Kecamatan (Jauh)</label>
                                                                <select name="ojek_beda_kec_jauh_ids[]" class="dw-input select2-districts" multiple="multiple">
                                                                    <?php if(!empty($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'])): 
                                                                        foreach($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'] as $kid) echo "<option value='$kid' selected>$kid</option>"; 
                                                                    endif; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div style="border-top:1px dashed #ddd; margin:15px 0;"></div>
                                                        
                                                        <!-- Blacklist Zone -->
                                                        <strong style="display:block; margin-bottom:10px; color:#d63638;">Zona Blacklist (Tidak Tercover)</strong>
                                                        <p class="dw-help-text" style="color: #646970; font-size:12px; margin-bottom:10px;">Area ini akan dialihkan ke kurir ekspedisi di frontend.</p>
                                                        <div class="dw-grid-2-col">
                                                            <div>
                                                                <label style="font-size:12px;">Blacklist Desa (Satu Kecamatan)</label>
                                                                <select name="ojek_blacklist_desa_ids[]" class="dw-input select2-villages" multiple="multiple">
                                                                     <?php if(!empty($ojek_zona['blacklist']['desa_ids'])): 
                                                                        foreach($ojek_zona['blacklist']['desa_ids'] as $vid) echo "<option value='$vid' selected>$vid</option>"; 
                                                                    endif; ?>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label style="font-size:12px;">Blacklist Kecamatan (Luar Kecamatan)</label>
                                                                <select name="ojek_blacklist_kec_ids[]" class="dw-input select2-districts" multiple="multiple">
                                                                     <?php if(!empty($ojek_zona['blacklist']['kecamatan_ids'])): 
                                                                        foreach($ojek_zona['blacklist']['kecamatan_ids'] as $kid) echo "<option value='$kid' selected>$kid</option>"; 
                                                                    endif; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                            <div class="dw-form-group">
                                                <label style="display:flex; align-items:center; gap:10px;">
                                                    <input type="checkbox" name="allow_pesan_di_tempat" value="1" <?php checked($edit_data->allow_pesan_di_tempat ?? 0, 1); ?>>
                                                    Izinkan Pesan di Tempat (Ambil Sendiri)
                                                </label>
                                            </div>
                                        </div>

                                        <div style="margin-top:20px; text-align:right;">
                                            <a href="?page=dw-pedagang" class="dw-button dw-button-secondary">Batal</a>
                                            <button type="submit" class="dw-button dw-button-primary">Simpan Data</button>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Sidebar (Status & Foto Utama) -->
                            <div class="dw-sidebar">
                                
                                <div class="dw-card">
                                    <div class="dw-card-header"><h3 class="card-heading">Status Akun</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Status Keaktifan</label>
                                            <select name="status_akun" class="dw-input">
                                                <option value="aktif" <?php selected($edit_data->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                                                <option value="nonaktif" <?php selected($edit_data->status_akun ?? '', 'nonaktif'); ?>>Non-Aktif</option>
                                                <option value="suspend" <?php selected($edit_data->status_akun ?? '', 'suspend'); ?>>Suspend</option>
                                            </select>
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Status Pendaftaran</label>
                                            <select name="status_pendaftaran" class="dw-input">
                                                <option value="disetujui" <?php selected($edit_data->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                                                <option value="menunggu" <?php selected($edit_data->status_pendaftaran ?? '', 'menunggu'); ?>>Menunggu</option>
                                                <option value="ditolak" <?php selected($edit_data->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="dw-card">
                                    <div class="dw-card-header"><h3 class="card-heading">Foto Identitas</h3></div>
                                    <div class="dw-card-body">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Foto Profil Toko</label>
                                            <div style="text-align:center; margin-bottom:10px; background:#f8fafc; padding:15px; border-radius:8px;">
                                                <img id="prev_foto_profil" src="<?php echo esc_url($edit_data->foto_profil ?? 'https://placehold.co/100x100?text=Profil'); ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                                            </div>
                                            <input type="hidden" name="foto_profil" id="foto_profil" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>">
                                            <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_profil" data-preview="#prev_foto_profil" style="width:100%;">Upload Profil</button>
                                        </div>

                                        <div class="dw-form-group">
                                            <label class="dw-label">Foto Sampul Toko</label>
                                            <div style="text-align:center; margin-bottom:10px; background:#f8fafc; padding:10px; border-radius:8px;">
                                                <img id="prev_foto_sampul" src="<?php echo esc_url($edit_data->foto_sampul ?? 'https://placehold.co/300x150?text=Sampul'); ?>" style="width:100%; height:100px; object-fit:cover; border-radius:6px;">
                                            </div>
                                            <input type="hidden" name="foto_sampul" id="foto_sampul" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>">
                                            <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_sampul" data-preview="#prev_foto_sampul" style="width:100%;">Upload Sampul</button>
                                        </div>

                                        <div class="dw-form-group">
                                            <label class="dw-label">Foto KTP</label>
                                            <div style="text-align:center; margin-bottom:10px; background:#f8fafc; padding:10px; border-radius:8px;">
                                                <img id="prev_url_ktp" src="<?php echo esc_url($edit_data->url_ktp ?? 'https://placehold.co/300x150?text=KTP'); ?>" style="width:100%; height:80px; object-fit:cover; border-radius:6px;">
                                            </div>
                                            <input type="hidden" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>">
                                            <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="url_ktp" data-preview="#prev_url_ktp" style="width:100%;">Upload KTP</button>
                                        </div>
                                    </div>
                                </div>

                            </div> <!-- End Right Column -->

                        </div>
                    </form>

                    <script>
                    jQuery(document).ready(function($){
                        // Tab Logic
                        $('.dw-form-tab').click(function(){
                            $('.dw-form-tab').removeClass('active').css('border-bottom', 'none');
                            $(this).addClass('active').css('border-bottom', '3px solid var(--dw-primary)');
                            $('.dw-tab-pane').hide();
                            $('#'+$(this).data('target')).show();
                        });
                        
                        // Set default active tab style
                        $('.dw-form-tab.active').css('border-bottom', '3px solid var(--dw-primary)');

                        // Media Upload
                        $('.btn_upload').click(function(e){
                            e.preventDefault(); 
                            var btn = $(this), target = btn.data('target'), preview = btn.data('preview');
                            var frame = wp.media({title:'Pilih Gambar', multiple:false}).on('select', function(){
                                var url = frame.state().get('selection').first().toJSON().url;
                                $('#'+target).val(url); 
                                if(preview) $(preview).attr('src', url);
                            }).open();
                        });

                        // Gallery Logic (Simple)
                        var galleryFrame;
                        $('#btn_galeri').click(function(e){
                            e.preventDefault();
                            if (galleryFrame) { galleryFrame.open(); return; }
                            galleryFrame = wp.media({ title: 'Pilih Foto Galeri', multiple: true, library: { type: 'image' } });
                            galleryFrame.on('select', function(){
                                var selections = galleryFrame.state().get('selection');
                                var urls = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : [];
                                selections.map(function(attachment){
                                    var att = attachment.toJSON();
                                    if (urls.indexOf(att.url) === -1) {
                                        urls.push(att.url);
                                        $('#galeri-container').append('<div class="g-item" style="position:relative;"><img src="'+att.url+'" style="width:100px; height:100px; object-fit:cover; border-radius:8px; border:1px solid #ddd;"><span class="rem-g" data-url="'+att.url+'" style="position:absolute; top:-5px; right:-5px; background:red; color:#fff; border-radius:50%; width:20px; height:20px; text-align:center; line-height:20px; cursor:pointer;">&times;</span></div>');
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

                        // Init Region if Edit
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

                        // Init Select2 for Ongkir
                        if($.fn.select2) { 
                            $('.select2').select2({width:'100%'}); 
                            $('.select2-districts').select2({ width: '100%', placeholder: 'Pilih Kecamatan' });
                            $('.select2-villages').select2({ width: '100%', placeholder: 'Pilih Desa' });
                        }
                        
                        // ONGKIR: Load Dynamic Options based on selected Kabupaten/Kecamatan
                         function loadOngkirOptions() {
                            var kecId = $('select[name="pedagang_kec"]').val();
                            var kabId = $('select[name="pedagang_kota"]').val();
                            
                            // Load Desa for Zona 1 (Satu Kecamatan)
                            if(kecId) {
                                $.get(ajaxurl, { action:'dw_fetch_villages', district_id:kecId, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
                                    if(res.success) {
                                        var $sel = $('.select2-villages');
                                        
                                        // Store selected values before empty
                                        // Note: select2-villages applies to multiple selects, so we iterate
                                        $sel.each(function(){
                                            var $this = $(this);
                                            var savedVals = $this.val() || [];
                                            // Only empty options if not already populated with correct data?
                                            // Simpler approach: Re-populate and re-select based on existing value in HTML (which is preserved if not wiped)
                                            // BUT select2 dynamic load is tricky. Better re-populate all.
                                            
                                            // Save current selections from DOM if available
                                            var currentSelections = [];
                                            $this.find('option:selected').each(function(){
                                                currentSelections.push($(this).val());
                                            });

                                            $this.empty();
                                            $.each(res.data, function(i,v){
                                                // Check if value was selected (either from DB initial load or user action)
                                                var isSelected = currentSelections.includes(v.id) ? 'selected' : '';
                                                $this.append(`<option value="${v.id}" ${isSelected}>${v.name}</option>`);
                                            });
                                            $this.trigger('change.select2'); // Notify select2 of updates
                                        });
                                    }
                                });
                            }

                            // Load Kecamatan for Zona 2 (Satu Kabupaten)
                            if(kabId) {
                                $.get(ajaxurl, { action:'dw_fetch_districts', regency_id:kabId, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
                                    if(res.success) {
                                        var $sel = $('.select2-districts');
                                         $sel.each(function(){
                                            var $this = $(this);
                                            var currentSelections = [];
                                            $this.find('option:selected').each(function(){
                                                currentSelections.push($(this).val());
                                            });

                                            $this.empty();
                                            $.each(res.data, function(i,v){
                                                var isSelected = currentSelections.includes(v.id) ? 'selected' : '';
                                                $this.append(`<option value="${v.id}" ${isSelected}>${v.name}</option>`);
                                            });
                                            $this.trigger('change.select2');
                                        });
                                    }
                                });
                            }
                        }

                        // Trigger load ongkir options when region changes
                        $('select[name="pedagang_kec"], select[name="pedagang_kota"]').on('change', function(){
                             setTimeout(loadOngkirOptions, 800);
                        });
                        
                        // Trigger on init if edit mode
                        if($('select[name="pedagang_kec"]').data('current')) {
                             setTimeout(loadOngkirOptions, 1000);
                        }

                        // --- LOGIKA EKSKLUSI PILIHAN (Mutual Exclusion) ---
                        
                        // 1. Desa (Zona 1 & Blacklist)
                        // Group: Dekat, Jauh, Blacklist
                        var desaGroups = ['select[name="ojek_dekat_desa_ids[]"]', 'select[name="ojek_jauh_desa_ids[]"]', 'select[name="ojek_blacklist_desa_ids[]"]'];
                        
                        $(desaGroups.join(',')).on('change', function (e) {
                            var allSelected = [];
                            
                            // Collect all selected values across groups
                            desaGroups.forEach(function(selector) {
                                var vals = $(selector).val() || [];
                                allSelected = allSelected.concat(vals);
                            });

                            // Disable selected values in other groups
                            desaGroups.forEach(function(selector) {
                                var $sel = $(selector);
                                var myVal = $sel.val() || [];
                                
                                $sel.find('option').each(function(){
                                    var optVal = $(this).val();
                                    // If selected somewhere else (not here), disable it
                                    if(allSelected.includes(optVal) && !myVal.includes(optVal)) {
                                        $(this).prop('disabled', true);
                                    } else {
                                        $(this).prop('disabled', false);
                                    }
                                });
                                // Refresh Select2 to show disabled state (Select2 might need full redraw or specific option update)
                                // Note: Standard Select2 doesn't always live-update disabled options well without destroy/re-init or specific plugin.
                                // Fallback: Just let backend validation handle if JS fails visual update.
                            });
                        });


                        // 2. Kecamatan (Zona 2 & Blacklist)
                         var kecGroups = ['select[name="ojek_beda_kec_dekat_ids[]"]', 'select[name="ojek_beda_kec_jauh_ids[]"]', 'select[name="ojek_blacklist_kec_ids[]"]'];
                         $(kecGroups.join(',')).on('change', function (e) {
                             var allSelected = [];
                             kecGroups.forEach(function(selector) {
                                 allSelected = allSelected.concat($(selector).val() || []);
                             });

                             kecGroups.forEach(function(selector) {
                                 var $sel = $(selector);
                                 var myVal = $sel.val() || [];
                                 $sel.find('option').each(function(){
                                     var optVal = $(this).val();
                                     if(allSelected.includes(optVal) && !myVal.includes(optVal)) {
                                         $(this).prop('disabled', true);
                                     } else {
                                         $(this).prop('disabled', false);
                                     }
                                 });
                             });
                         });

                    });
                    </script>
                <?php endif; ?>
                
            </div>
        </div>
        <?php
    }
}