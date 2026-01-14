<?php
/**
 * File: includes/admin-pages/page-verifikator-list.php
 * * Admin Page: Dashboard Manajemen Verifikator UMKM
 * * Menampilkan list, statistik, dan modal CRUD verifikator.
 */

defined( 'ABSPATH' ) || exit;

// Include UI components
if ( defined( 'DW_CORE_PLUGIN_DIR' ) ) {
    require_once DW_CORE_PLUGIN_DIR . 'includes/admin-ui-components.php';
} else {
    require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/admin-ui-components.php';
}

function dw_render_page_verifikator_list() {
    global $wpdb;
    // FIX: Sesuaikan nama tabel dengan activation.php (prefix 'dw_')
    $table_v = $wpdb->prefix . 'dw_verifikator'; 
    $table_p = $wpdb->prefix . 'dw_pedagang'; 

    // --- 1. HANDLE FORM SUBMISSION (ADD/EDIT/DELETE) ---
    if ( isset($_POST['dw_action']) ) {
        
        // A. Security Check
        if ( ! isset($_POST['dw_verifikator_nonce']) || ! wp_verify_nonce($_POST['dw_verifikator_nonce'], 'dw_save_verifikator_action') ) {
            wp_die('Security Check Failed. Harap refresh halaman.');
        }

        if ( ! current_user_can('manage_options') ) {
            wp_die('Akses ditolak. Anda tidak memiliki izin.');
        }

        $action_type = sanitize_text_field($_POST['dw_action']);
        
        // DELETE LOGIC
        if ($action_type == 'delete') {
            $id_del = intval($_POST['id']);
            $wpdb->delete($table_v, ['id' => $id_del]);
            wp_redirect(add_query_arg(['msg' => 'success_delete'], admin_url('admin.php?page=dw-verifikator-list')));
            exit;
        }

        // B. Persiapan Data (Sesuai Schema Database)
        $data = [
            'id_user'           => intval($_POST['user_id']),
            'nama_lengkap'      => sanitize_text_field($_POST['nama_lengkap']),
            'nik'               => sanitize_text_field($_POST['nik']),
            'kode_referral'     => strtoupper(sanitize_text_field($_POST['kode_referral'])),
            'nomor_wa'          => sanitize_text_field($_POST['nomor_wa']),
            'alamat_lengkap'    => sanitize_textarea_field($_POST['alamat_lengkap']),
            'kode_pos'          => sanitize_text_field($_POST['kode_pos']),
            
            // Data Wilayah (Nama Text)
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

        // C. Validasi Input Wajib
        if ( empty($data['nama_lengkap']) || empty($data['kode_referral']) || empty($data['id_user']) || empty($data['nik']) || empty($data['nomor_wa']) ) {
            wp_redirect(add_query_arg(['msg' => 'error_empty'], admin_url('admin.php?page=dw-verifikator-list')));
            exit;
        }

        // D. VALIDASI ROLE
        $check_user = get_userdata($data['id_user']);
        if ( ! $check_user || ! in_array( 'verifikator_umkm', (array) $check_user->roles ) ) {
            wp_redirect(add_query_arg(['msg' => 'error_role'], admin_url('admin.php?page=dw-verifikator-list')));
            exit;
        }

        // E. LOGIKA SIMPAN (ADD)
        if ( $action_type == 'add' ) {
            $data['created_at'] = current_time('mysql');
            
            // Default value keuangan saat create
            $data['total_verifikasi_sukses'] = 0;
            $data['total_pendapatan_komisi'] = 0;
            $data['saldo_saat_ini']          = 0;

            // Cek Duplikat User ID
            $exist_user = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE id_user = %d", $data['id_user']));
            // Cek Duplikat Referral
            $exist_ref  = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE kode_referral = %s", $data['kode_referral']));

            if($exist_user) {
                wp_redirect(add_query_arg(['msg' => 'error_exist_user'], admin_url('admin.php?page=dw-verifikator-list')));
            } elseif($exist_ref) {
                wp_redirect(add_query_arg(['msg' => 'error_exist_ref'], admin_url('admin.php?page=dw-verifikator-list')));
            } else {
                $result = $wpdb->insert($table_v, $data);
                if($result === false) {
                    wp_die('Database Error (Insert): ' . $wpdb->last_error);
                }
                wp_redirect(add_query_arg(['msg' => 'success_add'], admin_url('admin.php?page=dw-verifikator-list')));
            }
        } 
        // F. LOGIKA UPDATE (EDIT)
        elseif ( $action_type == 'edit' ) {
            $id = intval($_POST['verifikator_id']);
            if ( ! $id ) wp_die('ID Invalid');

            // Cek Duplikat (kecuali diri sendiri)
            $exist_user = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE id_user = %d AND id != %d", $data['id_user'], $id));
            $exist_ref  = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE kode_referral = %s AND id != %d", $data['kode_referral'], $id));
            
            if($exist_user) {
                wp_redirect(add_query_arg(['msg' => 'error_exist_user'], admin_url('admin.php?page=dw-verifikator-list')));
            } elseif($exist_ref) {
                wp_redirect(add_query_arg(['msg' => 'error_exist_ref'], admin_url('admin.php?page=dw-verifikator-list')));
            } else {
                $result = $wpdb->update($table_v, $data, ['id' => $id]);
                if($result === false) {
                    wp_die('Database Error (Update): ' . $wpdb->last_error);
                }
                wp_redirect(add_query_arg(['msg' => 'success_edit'], admin_url('admin.php?page=dw-verifikator-list')));
            }
        }
        exit;
    }

    // --- 2. QUERY DATA ---

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_p'") === $table_p;

    $total_v = $wpdb->get_var("SELECT COUNT(id) FROM $table_v WHERE status = 'aktif'");
    $pending_v = $wpdb->get_var("SELECT COUNT(id) FROM $table_v WHERE status = 'pending'");
    $umkm_verified_global = $wpdb->get_var("SELECT SUM(total_verifikasi_sukses) FROM $table_v");
    $total_linked_global = $table_exists ? $wpdb->get_var("SELECT COUNT(id) FROM $table_p WHERE id_verifikator > 0") : 0;
    $total_saldo_global = $wpdb->get_var("SELECT SUM(saldo_saat_ini) FROM $table_v");

    $verifikators = $wpdb->get_results("
        SELECT v.*
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
    ?>

    <div class="wrap dw-admin-wrapper">
        
        <!-- HEADER -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p class="dw-subtitle">Manajemen Petugas Lapangan & Verifikator UMKM.</p>
            </div>
            <div class="dw-header-actions">
                <button type="button" class="button button-primary" onclick="openModal('add')">
                    <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span> Verifikator Baru
                </button>
            </div>
        </div>

        <div class="dw-content-body">
            
            <!-- NOTIFIKASI -->
            <?php 
            if(isset($_GET['msg'])) {
                $notices = [
                    'success_add' => ['type' => 'success', 'msg' => 'Verifikator berhasil ditambahkan.'],
                    'success_edit' => ['type' => 'success', 'msg' => 'Data Verifikator berhasil diperbarui.'],
                    'success_delete' => ['type' => 'success', 'msg' => 'Data Verifikator berhasil dihapus.'],
                    'error_exist_user' => ['type' => 'error', 'msg' => 'User WordPress ini sudah terdaftar sebagai verifikator.'],
                    'error_role' => ['type' => 'error', 'msg' => 'User yang dipilih tidak memiliki role verifikator_umkm.'],
                    'error_exist_ref' => ['type' => 'error', 'msg' => 'Kode Referral sudah digunakan.'],
                    'error_empty' => ['type' => 'error', 'msg' => 'Semua kolom wajib harus diisi.'],
                ];
                if(isset($notices[$_GET['msg']])) {
                    $n = $notices[$_GET['msg']];
                    echo '<div class="notice notice-'.$n['type'].' is-dismissible"><p>'.$n['msg'].'</p></div>';
                }
            }
            ?>

            <!-- STATS GRID -->
            <div class="dw-stats-grid" style="margin-bottom: 25px;">
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-groups"></span></div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($total_v); ?></h3>
                        <p class="dw-stat-label">Total Verifikator</p>
                    </div>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-purple"><span class="dashicons dashicons-store"></span></div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($total_linked_global); ?></h3>
                        <p class="dw-stat-label">UMKM Terhubung</p>
                    </div>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value"><?php echo number_format($umkm_verified_global); ?></h3>
                        <p class="dw-stat-label">Verifikasi Sukses</p>
                    </div>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-teal"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="dw-stat-content">
                        <h3 class="dw-stat-value">Rp <?php echo number_format($total_saldo_global, 0, ',', '.'); ?></h3>
                        <p class="dw-stat-label">Total Saldo Member</p>
                    </div>
                </div>
            </div>

            <!-- TABLE CARD -->
            <div class="dw-card">
                <div class="dw-card-header" style="border-bottom: 1px solid #f0f0f1; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0;">Daftar Verifikator</h3>
                    <input type="text" id="liveSearch" placeholder="Cari nama, NIK, atau kode..." style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; width: 250px;">
                </div>
                <div class="dw-card-body" style="padding: 0;">
                    <table class="wp-list-table widefat fixed striped" id="vTable" style="border: none;">
                        <thead>
                            <tr>
                                <th style="padding-left: 20px;">Verifikator</th>
                                <th>Wilayah Kerja</th>
                                <th>Keuangan (Dompet)</th>
                                <th>Statistik UMKM</th>
                                <th>Status</th>
                                <th style="text-align:right; padding-right: 20px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($verifikators)): ?>
                                <tr><td colspan="6" style="text-align:center; padding: 40px; color:#646970;">Belum ada data verifikator.</td></tr>
                            <?php else: foreach($verifikators as $v): 
                                $u_info = get_userdata($v->id_user);
                                $has_role = $u_info && in_array('verifikator_umkm', (array)$u_info->roles);
                                $role_warning = $has_role ? '' : '<span style="color:red; font-size:10px; display:block;">(Role User Hilang/Salah)</span>';
                                
                                $u_name = $u_info ? $u_info->display_name : 'User Dihapus';
                                $u_email = $u_info ? $u_info->user_email : '-';

                                $linked_count = 0;
                                if($table_exists) {
                                    $linked_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_p WHERE id_verifikator = %d", $v->id));
                                }

                                // Prepare JSON for JS Edit
                                $data_js = [
                                    'id' => $v->id,
                                    'id_user' => $v->id_user,
                                    'nama_lengkap' => $v->nama_lengkap,
                                    'nik' => $v->nik,
                                    'kode_referral' => $v->kode_referral,
                                    'nomor_wa' => $v->nomor_wa,
                                    'alamat_lengkap' => $v->alamat_lengkap,
                                    'kode_pos' => $v->kode_pos,
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
                                
                                $status_badge = '';
                                if($v->status == 'aktif') $status_badge = '<span class="dw-badge status-success">Aktif</span>';
                                elseif($v->status == 'pending') $status_badge = '<span class="dw-badge status-warning">Pending</span>';
                                else $status_badge = '<span class="dw-badge status-neutral">Nonaktif</span>';
                            ?>
                            <tr>
                                <td style="padding-left: 20px;">
                                    <strong><?php echo esc_html($v->nama_lengkap); ?></strong>
                                    <div style="font-size:12px; color:#646970; margin-top:4px;">
                                        <?php echo esc_html($u_email); ?>
                                        <?php echo $role_warning; ?>
                                    </div>
                                    <div style="margin-top: 4px;">
                                        <code style="background: #f0f0f1; padding: 2px 5px; border-radius: 3px; font-size: 11px;" title="Kode Referral"><?php echo esc_html($v->kode_referral); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($v->kabupaten); ?></strong><br>
                                    <span style="font-size: 12px; color: #646970;">
                                        <?php echo esc_html($v->kecamatan); ?>, <?php echo esc_html($v->kelurahan); ?><br>
                                        <?php if($v->kode_pos) echo 'Kode Pos: ' . esc_html($v->kode_pos); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color:#10b981; font-weight:600;">Rp <?php echo number_format($v->saldo_saat_ini, 0, ',', '.'); ?></span><br>
                                    <span style="font-size:11px; color:#646970;">Total Komisi: Rp <?php echo number_format($v->total_pendapatan_komisi, 0, ',', '.'); ?></span>
                                </td>
                                <td>
                                    <div style="font-size: 12px;">
                                        Terhubung: <strong><?php echo number_format($linked_count); ?></strong><br>
                                        Verified: <strong><?php echo number_format($v->total_verifikasi_sukses); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo $status_badge; ?></td>
                                <td style="text-align:right; padding-right: 20px;">
                                    <button class="button button-small" onclick='openModal("edit", <?php echo $json; ?>)'>Edit</button>
                                    
                                    <form method="post" action="" style="display:inline-block; margin-left: 2px;" onsubmit="return confirm('Hapus Verifikator ini? Data user WP tidak akan terhapus.');">
                                        <?php wp_nonce_field('dw_save_verifikator_action', 'dw_verifikator_nonce'); ?>
                                        <input type="hidden" name="dw_action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $v->id; ?>">
                                        <button type="submit" class="button button-small" style="color: #b32d2e; border-color: #b32d2e;">&times;</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

    <!-- MODAL FORM -->
    <div id="vModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; width:90%; max-width:600px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.2); overflow:hidden; display:flex; flex-direction:column; max-height:90vh;">
            <div style="padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                <h2 id="modalTitle" style="margin:0; font-size:18px;">Tambah Verifikator</h2>
                <span onclick="closeModal()" style="cursor:pointer; font-size:20px; color:#646970;">&times;</span>
            </div>
            
            <div style="padding:20px; overflow-y:auto; flex:1;">
                <form method="post" action="" id="vForm">
                    <?php wp_nonce_field('dw_save_verifikator_action', 'dw_verifikator_nonce'); ?>
                    <input type="hidden" name="dw_action" id="vAction" value="add">
                    <input type="hidden" name="verifikator_id" id="vId" value="">

                    <!-- USER SELECTION -->
                    <div class="dw-form-group">
                        <label class="dw-label">Hubungkan Akun WordPress <span style="color:red">*</span></label>
                        <select name="user_id" id="vUser" class="dw-input" required>
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
                        <small class="dw-help-text">Hanya user role <code>verifikator_umkm</code> yang muncul disini.</small>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div class="dw-form-group">
                            <label class="dw-label">Nama Lengkap (KTP) <span style="color:red">*</span></label>
                            <input type="text" name="nama_lengkap" id="vNama" class="dw-input" required>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kode Referral <span style="color:red">*</span></label>
                            <div style="display:flex; gap:5px;">
                                <input type="text" name="kode_referral" id="vRef" class="dw-input" required placeholder="VER-XXXX">
                                <button type="button" class="button" id="btnGenRef" title="Generate"><span class="dashicons dashicons-randomize" style="margin-top:4px;"></span></button>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div class="dw-form-group">
                            <label class="dw-label">NIK (16 Digit) <span style="color:red">*</span></label>
                            <input type="text" name="nik" id="vNik" class="dw-input" maxlength="16" required>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Nomor WhatsApp <span style="color:red">*</span></label>
                            <input type="text" name="nomor_wa" id="vWa" class="dw-input" placeholder="08..." required>
                        </div>
                    </div>

                    <h4 style="margin:20px 0 10px; border-bottom:1px solid #eee; padding-bottom:5px;">Wilayah Kerja</h4>

                    <input type="hidden" name="provinsi" id="txtProv">
                    <input type="hidden" name="kabupaten" id="txtKab">
                    <input type="hidden" name="kecamatan" id="txtKec">
                    <input type="hidden" name="kelurahan" id="txtKel">

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div class="dw-form-group">
                            <label class="dw-label">Provinsi</label>
                            <select name="api_provinsi_id" id="selProv" class="dw-input"><option value="">Memuat...</option></select>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kabupaten/Kota</label>
                            <select name="api_kabupaten_id" id="selKab" class="dw-input" disabled><option value="">-- Pilih Provinsi --</option></select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div class="dw-form-group">
                            <label class="dw-label">Kecamatan</label>
                            <select name="api_kecamatan_id" id="selKec" class="dw-input" disabled><option value="">-- Pilih Kota --</option></select>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kelurahan/Desa</label>
                            <select name="api_kelurahan_id" id="selKel" class="dw-input" disabled><option value="">-- Pilih Kecamatan --</option></select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:15px;">
                        <div class="dw-form-group">
                            <label class="dw-label">Alamat Lengkap</label>
                            <textarea name="alamat_lengkap" id="vAlamat" class="dw-input" rows="2" placeholder="Jalan, RT/RW..."></textarea>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kode Pos</label>
                            <input type="text" name="kode_pos" id="vKodePos" class="dw-input" placeholder="12345">
                        </div>
                    </div>

                    <div class="dw-form-group">
                        <label class="dw-label">Status Akun</label>
                        <select name="status" id="vStatus" class="dw-input">
                            <option value="aktif">Aktif</option>
                            <option value="pending">Pending</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>

                    <div style="margin-top:20px; text-align:right; border-top:1px solid #eee; padding-top:15px;">
                        <button type="button" class="button button-secondary" onclick="closeModal()" style="margin-right:10px;">Batal</button>
                        <button type="submit" id="btnSubmit" class="button button-primary">Simpan Data</button>
                    </div>
                </form>
            </div>
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
            $('#vUser option').prop('disabled', false).show(); 

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
                $('#vKodePos').val(data.kode_pos);
                $('#vStatus').val(data.status);
                
                // Logic User Select (Edit)
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

            $.get(ajaxUrl, dataParams, function(res){
                target.find('option:first').text(originalText); 
                
                var data = (typeof res === 'string') ? JSON.parse(res) : res;
                
                if(data.success){
                    target.empty().append('<option value="">-- Pilih --</option>');
                    $.each(data.data, function(i,v){
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

        // Change Handlers
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
    </script>
    <?php
}