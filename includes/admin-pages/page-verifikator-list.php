<?php
/**
 * File Name:   includes/admin-pages/page-verifikator-list.php
 * Description: Dashboard Manajemen Verifikator UMKM (Premium UI/UX).
 * Version:     5.4 (Fix "Sorry you are not allowed" & Dynamic Action URL)
 * * Update Notes:
 * - FIX CRITICAL: Form Action sekarang dinamis mengambil $_GET['page'].
 * - Mencegah error akses ditolak karena salah slug atau konflik URL.
 * - Memastikan data tersimpan dengan aman.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_v = $wpdb->prefix . 'dw_verifikator';
$table_p = $wpdb->prefix . 'dw_pedagang';

// --- 1. HANDLE FORM SUBMISSION (ADD/EDIT) ---
if ( isset($_POST['dw_action']) ) {
    
    // A. Security Check
    if ( ! isset($_POST['dw_verifikator_nonce']) || ! wp_verify_nonce($_POST['dw_verifikator_nonce'], 'dw_save_verifikator_action') ) {
        wp_die('Security Check Failed. Harap refresh halaman.');
    }

    if ( ! current_user_can('manage_options') ) {
        wp_die('Akses ditolak. Anda tidak memiliki izin.');
    }

    $action_type = sanitize_text_field($_POST['dw_action']);
    
    // Redirect ke halaman yang sama tapi BERSIH (tanpa action=edit)
    $current_page_slug = sanitize_text_field($_GET['page']); // Ambil slug halaman saat ini
    $redirect_url = admin_url('admin.php?page=' . $current_page_slug);
    
    // B. Persiapan Data (Sesuai Schema Database activation.php)
    $data = [
        'id_user'           => intval($_POST['user_id']),
        'nama_lengkap'      => sanitize_text_field($_POST['nama_lengkap']),
        'nik'               => sanitize_text_field($_POST['nik']),
        'kode_referral'     => strtoupper(sanitize_text_field($_POST['kode_referral'])),
        'nomor_wa'          => sanitize_text_field($_POST['nomor_wa']),
        'alamat_lengkap'    => sanitize_textarea_field($_POST['alamat_lengkap']),
        
        // Data Wilayah (Nama)
        'provinsi'          => sanitize_text_field($_POST['provinsi']),
        'kabupaten'         => sanitize_text_field($_POST['kabupaten']),
        'kecamatan'         => sanitize_text_field($_POST['kecamatan']),
        'kelurahan'         => sanitize_text_field($_POST['kelurahan']),

        // Data Wilayah (ID API)
        'api_provinsi_id'   => sanitize_text_field($_POST['api_provinsi_id']),
        'api_kabupaten_id'  => sanitize_text_field($_POST['api_kabupaten_id']),
        'api_kecamatan_id'  => sanitize_text_field($_POST['api_kecamatan_id']),
        'api_kelurahan_id'  => sanitize_text_field($_POST['api_kelurahan_id']),
        
        'status'            => sanitize_text_field($_POST['status']),
        'updated_at'        => current_time('mysql')
    ];

    // Validasi Wajib
    if ( empty($data['nama_lengkap']) || empty($data['kode_referral']) || empty($data['id_user']) || empty($data['nik']) || empty($data['nomor_wa']) ) {
        wp_redirect(add_query_arg(['msg' => 'error_empty'], $redirect_url));
        exit;
    }

    // C. LOGIKA SIMPAN (ADD)
    if ( $action_type == 'add' ) {
        $data['created_at'] = current_time('mysql');
        
        // Cek Duplikat User
        $exist_user = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE id_user = %d", $data['id_user']));
        // Cek Duplikat Referral
        $exist_ref  = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE kode_referral = %s", $data['kode_referral']));

        if($exist_user) {
            wp_redirect(add_query_arg(['msg' => 'error_exist_user'], $redirect_url));
        } elseif($exist_ref) {
            wp_redirect(add_query_arg(['msg' => 'error_exist_ref'], $redirect_url));
        } else {
            $result = $wpdb->insert($table_v, $data);
            if($result === false) {
                wp_die('Database Error (Insert): ' . $wpdb->last_error);
            }
            wp_redirect(add_query_arg(['msg' => 'success_add'], $redirect_url));
        }
    } 
    // D. LOGIKA UPDATE (EDIT)
    elseif ( $action_type == 'edit' ) {
        $id = intval($_POST['verifikator_id']);
        if ( ! $id ) wp_die('ID Invalid');

        // Cek Duplikat (kecuali diri sendiri)
        $exist_user = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE id_user = %d AND id != %d", $data['id_user'], $id));
        $exist_ref  = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE kode_referral = %s AND id != %d", $data['kode_referral'], $id));
        
        if($exist_user) {
            wp_redirect(add_query_arg(['msg' => 'error_exist_user'], $redirect_url));
        } elseif($exist_ref) {
            wp_redirect(add_query_arg(['msg' => 'error_exist_ref'], $redirect_url));
        } else {
            $result = $wpdb->update($table_v, $data, ['id' => $id]);
            if($result === false) {
                wp_die('Database Error (Update): ' . $wpdb->last_error);
            }
            wp_redirect(add_query_arg(['msg' => 'success_edit'], $redirect_url));
        }
    }
    exit;
}

// --- 2. QUERY DATA ---

$total_v = $wpdb->get_var("SELECT COUNT(id) FROM $table_v WHERE status = 'aktif'");
$pending_v = $wpdb->get_var("SELECT COUNT(id) FROM $table_v WHERE status = 'pending'");
$total_linked_global = $wpdb->get_var("SELECT COUNT(id) FROM $table_p WHERE id_verifikator > 0");
$umkm_verified_global = $wpdb->get_var("SELECT SUM(total_verifikasi_sukses) FROM $table_v");

$verifikators = $wpdb->get_results("
    SELECT v.*, 
    (SELECT COUNT(p.id) FROM $table_p p WHERE p.id_verifikator = v.id) as linked_count
    FROM $table_v v 
    ORDER BY v.created_at DESC
");

// --- 3. FILTER USER ---
$wp_users = get_users([
    'role'    => 'verifikator_umkm', 
    'orderby' => 'display_name'
]);

$used_user_ids = $wpdb->get_col("SELECT id_user FROM $table_v");
if(!$used_user_ids) $used_user_ids = [];

$region_nonce = wp_create_nonce('dw_region_nonce'); 

// --- GET DYNAMIC PAGE SLUG ---
// Ini kunci perbaikan error "Sorry, you are not allowed..."
// Kita ambil slug halaman dari URL saat ini agar form action selalu benar.
$current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dw-verifikator';
?>

<!-- STYLE CSS -->
<style>
    :root { --v-primary: #2271b1; --v-success: #00ba37; --v-warning: #f5a623; --v-danger: #d63638; --v-border: #c3c4c7; --v-text: #3c434a; --v-muted: #646970; }
    .v-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--v-text); margin: 20px 20px 0 0; }
    
    /* Stats */
    .v-grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .v-card { background: #fff; border: 1px solid var(--v-border); border-radius: 4px; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; flex-direction: column; }
    .v-card h3 { margin: 0 0 5px 0; font-size: 12px; text-transform: uppercase; color: var(--v-muted); font-weight: 600; }
    .v-card .val { font-size: 24px; font-weight: 500; color: var(--v-text); line-height: 1.2; }
    .v-card.highlight { border-left: 4px solid var(--v-primary); }
    
    /* Header */
    .v-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .v-title h1 { margin: 0; font-size: 24px; font-weight: 600; display: inline-block; }
    .v-search { padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px; width: 250px; font-size: 14px; }
    .v-btn { background: var(--v-primary); color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: 0.1s; vertical-align: middle; }
    .v-btn:hover { background: #135e96; color: #fff; }
    .v-btn-sec { background: #f6f7f7; border: 1px solid var(--v-primary); color: var(--v-primary); }
    
    /* Table */
    .v-table-container { background: #fff; border: 1px solid var(--v-border); box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
    .v-table { width: 100%; border-collapse: collapse; }
    .v-table th { background: #fff; padding: 15px; text-align: left; font-size: 14px; font-weight: 600; color: var(--v-text); border-bottom: 1px solid var(--v-border); }
    .v-table td { padding: 12px 15px; border-bottom: 1px solid #f0f0f1; vertical-align: top; font-size: 13px; }
    .v-table tr:hover { background-color: #fcfcfc; }
    .v-badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; border: 1px solid transparent; }
    .vb-active { background: #edfaef; color: #00ba37; border-color: #00ba37; }
    .vb-pending { background: #fcf9e8; color: #f5a623; border-color: #f5a623; }
    .vb-inactive { background: #fbeaea; color: #d63638; border-color: #d63638; }
    .v-code { font-family: monospace; background: #f0f0f1; padding: 3px 6px; border-radius: 3px; font-weight: 600; font-size: 12px; cursor: pointer; border: 1px solid #dcdcde; display: inline-block; margin-top: 3px; }
    .v-stats-row { display: flex; align-items: center; gap: 15px; font-size: 12px; margin-top: 5px; }
    
    /* Modal */
    .v-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
    .v-modal-box { background: #fff; width: 700px; max-width: 95%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 5px 15px rgba(0,0,0,0.3); border-radius: 4px; animation: popIn 0.2s ease-out; }
    @keyframes popIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .v-modal-header { padding: 15px 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background: #fff; border-radius: 4px 4px 0 0; }
    .v-modal-header h2 { margin: 0; font-size: 18px; color: #23282d; }
    .v-close { font-size: 20px; cursor: pointer; color: #666; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
    .v-close:hover { color: #d63638; }
    .v-modal-body { padding: 20px; overflow-y: auto; flex: 1; background: #fff; }
    .v-modal-footer { padding: 15px 20px; border-top: 1px solid #ddd; background: #f6f7f7; text-align: right; border-radius: 0 0 4px 4px; display: flex; justify-content: flex-end; gap: 10px; }
    
    /* Form */
    .v-form-group { margin-bottom: 15px; }
    .v-form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #23282d; }
    .v-input, .v-select, .v-textarea { width: 100%; padding: 0 8px; height: 36px; line-height: 1.5; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; color: #2c3338; }
    .v-textarea { height: auto; padding: 8px; }
    .v-input:focus, .v-select:focus, .v-textarea:focus { border-color: var(--v-primary); box-shadow: 0 0 0 1px var(--v-primary); outline: none; }
    .v-row { display: flex; gap: 20px; }
    .v-col { flex: 1; }
    .v-input-group { display: flex; }
    .v-input-group input { border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none; }
    .v-input-group button { border-top-left-radius: 0; border-bottom-left-radius: 0; border: 1px solid #8c8f94; background: #f0f0f1; cursor: pointer; padding: 0 10px; color: #50575e; }
</style>

<div class="wrap v-wrap">
    
    <!-- NOTIFIKASI -->
    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg'] == 'success_add'): ?>
            <div class="notice notice-success is-dismissible"><p><strong>Sukses:</strong> Verifikator berhasil ditambahkan.</p></div>
        <?php elseif($_GET['msg'] == 'success_edit'): ?>
            <div class="notice notice-success is-dismissible"><p><strong>Sukses:</strong> Data Verifikator berhasil diperbarui.</p></div>
        <?php elseif($_GET['msg'] == 'error_exist_user'): ?>
            <div class="notice notice-error is-dismissible"><p><strong>Gagal:</strong> User WordPress ini sudah terdaftar sebagai verifikator.</p></div>
        <?php elseif($_GET['msg'] == 'error_exist_ref'): ?>
            <div class="notice notice-error is-dismissible"><p><strong>Gagal:</strong> Kode Referral sudah digunakan.</p></div>
        <?php elseif($_GET['msg'] == 'error_empty'): ?>
            <div class="notice notice-error is-dismissible"><p><strong>Gagal:</strong> Semua kolom wajib harus diisi.</p></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="v-header">
        <div class="v-title">
            <h1>Verifikator & Petugas Lapangan</h1>
        </div>
        <div>
            <input type="text" id="liveSearch" class="v-search" placeholder="Cari nama, NIK, atau kode...">
            <button class="v-btn" onclick="openModal('add')"><span class="dashicons dashicons-plus-alt2"></span> Verifikator Baru</button>
        </div>
    </div>

    <!-- STATS -->
    <div class="v-grid-stats">
        <div class="v-card highlight">
            <h3>Total Verifikator</h3>
            <span class="val"><?php echo number_format($total_v); ?></span>
        </div>
        <div class="v-card">
            <h3>Total UMKM Terhubung</h3>
            <span class="val"><?php echo number_format($total_linked_global); ?></span>
        </div>
        <div class="v-card" style="border-left: 4px solid var(--v-success);">
            <h3>Verifikasi Sukses</h3>
            <span class="val"><?php echo number_format($umkm_verified_global); ?></span>
        </div>
        <div class="v-card" style="border-left: 4px solid var(--v-warning);">
            <h3>Status Pending</h3>
            <span class="val"><?php echo number_format($pending_v); ?></span>
        </div>
    </div>

    <!-- TABLE -->
    <div class="v-table-container">
        <table class="v-table" id="vTable">
            <thead>
                <tr>
                    <th>Verifikator</th>
                    <th>Wilayah Kerja</th>
                    <th>Statistik UMKM</th>
                    <th>Status</th>
                    <th style="text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($verifikators)): ?>
                    <tr><td colspan="5" style="text-align:center; padding: 40px; color:var(--v-muted);">Belum ada data verifikator.</td></tr>
                <?php else: foreach($verifikators as $v): 
                    // Ambil Info User WP
                    $u_info = get_userdata($v->id_user);
                    $u_name = $u_info ? $u_info->display_name : 'User Dihapus';
                    $u_login = $u_info ? $u_info->user_login : '-';
                    $u_email = $u_info ? $u_info->user_email : '-';

                    // Prepare JSON for JS Edit
                    $data_js = [
                        'id' => $v->id,
                        'id_user' => $v->id_user,
                        'nama_lengkap' => $v->nama_lengkap,
                        'nik' => $v->nik,
                        'kode_referral' => $v->kode_referral,
                        'nomor_wa' => $v->nomor_wa,
                        'alamat_lengkap' => $v->alamat_lengkap,
                        'status' => $v->status,
                        'provinsi' => $v->provinsi,
                        'kabupaten' => $v->kabupaten,
                        'kecamatan' => $v->kecamatan,
                        'kelurahan' => $v->kelurahan,
                        'api_provinsi_id' => $v->api_provinsi_id,
                        'api_kabupaten_id' => $v->api_kabupaten_id,
                        'api_kecamatan_id' => $v->api_kecamatan_id,
                        'api_kelurahan_id' => $v->api_kelurahan_id,
                    ];
                    $json = htmlspecialchars(json_encode($data_js), ENT_QUOTES, 'UTF-8');
                    
                    $status_cls = ($v->status == 'aktif') ? 'vb-active' : (($v->status == 'pending') ? 'vb-pending' : 'vb-inactive');
                    $status_lbl = ucfirst($v->status);
                ?>
                <tr>
                    <td style="width: 30%;">
                        <div style="font-weight:600; color:var(--v-primary); font-size:14px;"><?php echo esc_html($v->nama_lengkap); ?></div>
                        
                        <!-- INFO USER WP -->
                        <div style="font-size:11px; color:#50575e; margin:2px 0 4px; display:flex; align-items:center; gap:4px;">
                            <span class="dashicons dashicons-admin-users" style="font-size:12px;width:12px;height:12px;"></span>
                            <span title="Username WP"><?php echo esc_html($u_login); ?></span>
                            <span style="color:#ccc;">|</span>
                            <span title="Email WP"><?php echo esc_html($u_email); ?></span>
                        </div>

                        <div style="font-size:12px; color:var(--v-muted); margin-bottom:4px;">NIK: <?php echo esc_html($v->nik); ?></div>
                        <span class="v-code" onclick="copyCode('<?php echo esc_js($v->kode_referral); ?>')" title="Klik Salin Kode">
                            <span class="dashicons dashicons-tag" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span> <?php echo esc_html($v->kode_referral); ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo esc_html($v->kabupaten); ?></strong><br>
                        <small style="color:var(--v-muted);"><?php echo esc_html($v->kecamatan); ?>, <?php echo esc_html($v->kelurahan); ?></small>
                    </td>
                    <td>
                        <div class="v-stats-row">
                            <div class="stat-item">
                                <span style="color:var(--v-primary)">Terhubung</span>
                                <strong style="color:var(--v-primary)"><?php echo number_format($v->linked_count); ?></strong>
                            </div>
                            <div style="height:25px; width:1px; background:var(--v-border);"></div>
                            <div class="stat-item">
                                <span style="color:var(--v-success)">Verified</span>
                                <strong style="color:var(--v-success)"><?php echo number_format($v->total_verifikasi_sukses); ?></strong>
                            </div>
                        </div>
                    </td>
                    <td><span class="v-badge <?php echo $status_cls; ?>"><?php echo $status_lbl; ?></span></td>
                    <td style="text-align:right;">
                        <button class="v-btn v-btn-sec" style="padding:4px 10px; font-size:12px;" onclick='openModal("edit", <?php echo $json; ?>)'>Edit</button>
                        <?php if($v->nomor_wa): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $v->nomor_wa); ?>" target="_blank" class="v-btn v-btn-sec" style="padding:4px 8px; border-color:#dcfce7; background:#f0fdf4; color:#166534;" title="Chat WA"><span class="dashicons dashicons-whatsapp"></span></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- MODAL FORM -->
<div id="vModal" class="v-modal-overlay">
    <div class="v-modal-box">
        <div class="v-modal-header">
            <h2 id="modalTitle">Tambah Verifikator</h2>
            <div class="v-close" onclick="closeModal()"><span class="dashicons dashicons-no-alt"></span></div>
        </div>
        
        <!-- Form Action Dinamis Mengikuti Page Slug -->
        <form method="post" action="<?php echo admin_url('admin.php?page=' . $current_page_slug); ?>" id="vForm" style="display:flex; flex-direction:column; flex:1; overflow:hidden;">
            <?php wp_nonce_field('dw_save_verifikator_action', 'dw_verifikator_nonce'); ?>
            <input type="hidden" name="dw_action" id="vAction" value="add">
            <input type="hidden" name="verifikator_id" id="vId" value="">

            <div class="v-modal-body">
                
                <h4 style="margin:0 0 15px; color:var(--v-primary); border-bottom:1px solid #e2e8f0; padding-bottom:8px;">Data Akun & Pribadi</h4>
                
                <!-- USER SELECTION (FILTERED) -->
                <div class="v-form-group" id="userSelectBox">
                    <label>Hubungkan Akun WordPress <span style="color:red">*</span></label>
                    <select name="user_id" id="vUser" class="v-select" required>
                        <option value="">-- Pilih User (Role: verifikator_umkm) --</option>
                        <?php 
                        if($wp_users):
                            foreach($wp_users as $u): 
                                $is_used = in_array($u->ID, $used_user_ids);
                                $used_attr = $is_used ? '1' : '0';
                                $lbl_extra = $is_used ? ' (Sudah Terdaftar)' : '';
                        ?>
                            <option value="<?php echo $u->ID; ?>" data-used="<?php echo $used_attr; ?>">
                                <?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_email); ?>)<?php echo $lbl_extra; ?>
                            </option>
                        <?php 
                            endforeach; 
                        else:
                        ?>
                            <option value="" disabled>Tidak ada user dengan role 'verifikator_umkm'</option>
                        <?php endif; ?>
                    </select>
                    <small style="color:var(--v-muted); display:block; margin-top:5px;">Hanya user role <code>verifikator_umkm</code>. User yang sudah punya data tidak bisa dipilih (kecuali edit).</small>
                </div>

                <div class="v-row">
                    <div class="v-col v-form-group">
                        <label>Nama Lengkap (Sesuai KTP) <span style="color:red">*</span></label>
                        <input type="text" name="nama_lengkap" id="vNama" class="v-input" required>
                    </div>
                    <div class="v-col v-form-group">
                        <label>Kode Referral <span style="color:red">*</span></label>
                        <div class="v-input-group">
                            <input type="text" name="kode_referral" id="vRef" class="v-input" required placeholder="Contoh: VER-A1B2">
                            <button type="button" id="btnGenRef" title="Generate Random"><span class="dashicons dashicons-randomize"></span></button>
                        </div>
                    </div>
                </div>

                <div class="v-row">
                    <div class="v-col v-form-group">
                        <label>NIK (16 Digit) <span style="color:red">*</span></label>
                        <input type="text" name="nik" id="vNik" class="v-input" maxlength="16" required>
                    </div>
                    <div class="v-col v-form-group">
                        <label>Nomor WhatsApp <span style="color:red">*</span></label>
                        <input type="text" name="nomor_wa" id="vWa" class="v-input" placeholder="08..." required>
                    </div>
                </div>

                <h4 style="margin:20px 0 15px; color:var(--v-primary); border-bottom:1px solid #e2e8f0; padding-bottom:8px;">Wilayah Kerja</h4>

                <!-- Hidden inputs for saving Text Names -->
                <input type="hidden" name="provinsi" id="txtProv">
                <input type="hidden" name="kabupaten" id="txtKab">
                <input type="hidden" name="kecamatan" id="txtKec">
                <input type="hidden" name="kelurahan" id="txtKel">

                <div class="v-row">
                    <div class="v-col v-form-group">
                        <label>Provinsi</label>
                        <!-- Select saves ID (api_provinsi_id) -->
                        <select name="api_provinsi_id" id="selProv" class="v-select"><option value="">Memuat...</option></select>
                    </div>
                    <div class="v-col v-form-group">
                        <label>Kabupaten/Kota</label>
                        <select name="api_kabupaten_id" id="selKab" class="v-select" disabled><option value="">-- Pilih Provinsi --</option></select>
                    </div>
                </div>

                <div class="v-row">
                    <div class="v-col v-form-group">
                        <label>Kecamatan</label>
                        <select name="api_kecamatan_id" id="selKec" class="v-select" disabled><option value="">-- Pilih Kota --</option></select>
                    </div>
                    <div class="v-col v-form-group">
                        <label>Kelurahan/Desa</label>
                        <select name="api_kelurahan_id" id="selKel" class="v-select" disabled><option value="">-- Pilih Kecamatan --</option></select>
                    </div>
                </div>

                <div class="v-form-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat_lengkap" id="vAlamat" class="v-textarea" rows="2" placeholder="Nama Jalan, RT/RW, No. Rumah..."></textarea>
                </div>

                <div class="v-form-group">
                    <label>Status Akun</label>
                    <select name="status" id="vStatus" class="v-select">
                        <option value="aktif">Aktif</option>
                        <option value="pending">Pending</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

            </div>

            <div class="v-modal-footer">
                <button type="button" class="v-btn v-btn-sec" onclick="closeModal()">Batal</button>
                <button type="submit" id="btnSubmit" class="v-btn">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
    const regionNonce = '<?php echo $region_nonce; ?>';

    // --- 1. MODAL & CRUD LOGIC ---
    window.openModal = function(mode, data = null) {
        $('#vModal').css('display', 'flex').hide().fadeIn(200);
        $('#vForm')[0].reset();
        $('#vUser option').prop('disabled', false).show(); // Reset options visibility

        if(mode == 'add') {
            $('#modalTitle').text('Tambah Verifikator Baru');
            $('#vAction').val('add');
            $('#vId').val('');
            $('#btnSubmit').text('Simpan Data');
            
            // Logic: Hide user yang sudah terpakai
            $('#vUser option[data-used="1"]').prop('disabled', true).hide();
            
            // Auto select pertama yang available
            if($('#vUser option:selected').is(':disabled')) {
                $('#vUser').val($('#vUser option:not(:disabled):first').val());
            }

            // Reset regions
            resetRegion();
        } else {
            $('#modalTitle').text('Edit Data Verifikator');
            $('#vAction').val('edit');
            $('#vId').val(data.id);
            $('#btnSubmit').text('Update Data');
            
            // Populate Fields
            $('#vNama').val(data.nama_lengkap);
            $('#vRef').val(data.kode_referral);
            $('#vNik').val(data.nik);
            $('#vWa').val(data.nomor_wa);
            $('#vAlamat').val(data.alamat_lengkap);
            $('#vStatus').val(data.status);
            
            // Logic User Select (Edit): Disable others, keep self
            var currentUserId = data.id_user;
            $('#vUser option').each(function(){
                var uid = $(this).val();
                var isUsed = $(this).data('used') == 1;
                if(isUsed && uid != currentUserId) {
                    $(this).prop('disabled', true).hide();
                }
            });
            $('#vUser').val(currentUserId);

            // Populate Hidden Names
            $('#txtProv').val(data.provinsi);
            $('#txtKab').val(data.kabupaten);
            $('#txtKec').val(data.kecamatan);
            $('#txtKel').val(data.kelurahan);

            // Trigger Cascading Load by ID
            // Load Prov, then check ID, then Load Kab...
            loadRegion('prov', null, $('#selProv'), data.api_provinsi_id, function(provId){
                if(provId) {
                    loadRegion('kota', provId, $('#selKab'), data.api_kabupaten_id, function(kabId){
                        if(kabId) {
                            loadRegion('kec', kabId, $('#selKec'), data.api_kecamatan_id, function(kecId){
                                if(kecId) {
                                    loadRegion('desa', kecId, $('#selKel'), data.api_kelurahan_id, null);
                                }
                            });
                        }
                    });
                }
            });
        }
    }

    window.closeModal = function() {
        $('#vModal').fadeOut(200);
    }

    // --- 2. LIVE SEARCH ---
    $('#liveSearch').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $("#vTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // --- 3. REFERRAL GENERATOR ---
    $('#btnGenRef').on('click', function() {
        let name = $('#vNama').val().replace(/[^a-zA-Z]/g, '').toUpperCase();
        let prefix = name.length >= 3 ? name.substring(0, 3) : (name || 'VER');
        let rand = Math.random().toString(36).substring(2, 7).toUpperCase();
        $('#vRef').val(prefix + '-' + rand);
    });

    // --- 4. AUTO FILL NAME ---
    $('#vUser').on('change', function() {
        if($(this).val()) {
            let txt = $(this).find('option:selected').text();
            let name = txt.split('(')[0].trim();
            if($('#vNama').val() === '') $('#vNama').val(name);
        }
    });

    // --- 5. REGION LOGIC (ID Based & Fixed Endpoint) ---
    function resetRegion() {
        $('#selKab, #selKec, #selKel').empty().prop('disabled', true).append('<option value="">-- Pilih --</option>');
        $('#txtProv, #txtKab, #txtKec, #txtKel').val('');
        loadRegion('prov', null, $('#selProv'), null, null);
    }

    function loadRegion(type, pid, target, selectedId, callback) {
        if(type !== 'prov' && (!pid || pid === "")) return;
        
        var actionName = '';
        var dataParams = { nonce: regionNonce };

        // Mapping Action & Parameter Name sesuai address-api.php
        if(type === 'prov') {
            actionName = 'dw_fetch_provinces';
        } else if(type === 'kota') {
            actionName = 'dw_fetch_regencies';
            dataParams.province_id = pid;
        } else if(type === 'kec') {
            actionName = 'dw_fetch_districts';
            dataParams.regency_id = pid;
        } else if(type === 'desa') {
            actionName = 'dw_fetch_villages';
            dataParams.district_id = pid;
        }
        
        dataParams.action = actionName;

        var originalText = target.find('option:first').text();
        target.find('option:first').text('Memuat...');

        // Gunakan $.get karena address-api.php menggunakan $_GET
        $.get(ajaxUrl, dataParams, function(res){
            target.find('option:first').text(originalText); 
            
            var data = (typeof res === 'string') ? JSON.parse(res) : res;
            
            if(data.success){
                target.empty().append('<option value="">-- Pilih --</option>');
                $.each(data.data, function(i,v){
                    // Match by ID (String Comparison)
                    let isSel = (selectedId && String(v.id) === String(selectedId));
                    target.append(`<option value="${v.id}" data-nama="${v.name}" ${isSel?'selected':''}>${v.name}</option>`);
                });
                target.prop('disabled', false);
                
                if(selectedId) {
                    target.val(selectedId);
                }
                
                if(callback) callback(selectedId);
            }
        });
    }

    // Change Handlers (Update Hidden Name Input & Load Child)
    $('#selProv').change(function(){
        $('#txtProv').val($(this).find(':selected').data('nama'));
        $('#selKab, #selKec, #selKel').empty().prop('disabled', true);
        loadRegion('kota', $(this).val(), $('#selKab'), null, null);
    });
    $('#selKab').change(function(){
        $('#txtKab').val($(this).find(':selected').data('nama'));
        $('#selKec, #selKel').empty().prop('disabled', true);
        loadRegion('kec', $(this).val(), $('#selKec'), null, null);
    });
    $('#selKec').change(function(){
        $('#txtKec').val($(this).find(':selected').data('nama'));
        $('#selKel').empty().prop('disabled', true);
        loadRegion('desa', $(this).val(), $('#selKel'), null, null);
    });
    $('#selKel').change(function(){
        $('#txtKel').val($(this).find(':selected').data('nama'));
    });

    // Initial Load
    loadRegion('prov', null, $('#selProv'), null, null);
});

window.copyCode = function(txt) {
    navigator.clipboard.writeText(txt).then(() => alert('Kode disalin: ' + txt));
}
</script>