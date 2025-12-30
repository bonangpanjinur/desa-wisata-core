<?php
/**
 * File Name:   includes/admin-pages/page-verifikator-list.php
 * Description: Dashboard Manajemen Verifikator UMKM (Premium UI/UX).
 * Version:     4.0 (Enhanced Stats: Total Connected vs Verified)
 * * Fitur Utama:
 * 1. CRUD Verifikator (Server-side Processing).
 * 2. Modal Form dengan Sticky Header/Footer & Scrollable Body.
 * 3. Integrasi API Wilayah (Waterfall Selection).
 * 4. Generator Kode Referral Otomatis.
 * 5. Statistik Lengkap (Total Terhubung vs Total ACC).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_v = $wpdb->prefix . 'dw_verifikator';
$table_p = $wpdb->prefix . 'dw_pedagang';

// --- 1. HANDLE FORM SUBMISSION (ADD/EDIT) VIA PHP ---
// Kita gunakan PHP POST self-processing agar lebih stabil daripada AJAX untuk penyimpanan data kritis.
if ( isset($_POST['dw_action']) && check_admin_referer('dw_save_verifikator_action', 'dw_verifikator_nonce') ) {
    
    $action_type = sanitize_text_field($_POST['dw_action']);
    
    // Persiapan Data (Sanitasi Lengkap)
    $data = [
        'id_user'           => intval($_POST['user_id']),
        'nama_lengkap'      => sanitize_text_field($_POST['nama_lengkap']),
        'nik'               => sanitize_text_field($_POST['nik']),
        'kode_referral'     => strtoupper(sanitize_text_field($_POST['kode_referral'])),
        'nomor_wa'          => sanitize_text_field($_POST['nomor_wa']),
        'alamat_lengkap'    => sanitize_textarea_field($_POST['alamat_lengkap']),
        
        // Simpan ID Wilayah & Nama Teks (untuk kemudahan display)
        'provinsi'          => sanitize_text_field($_POST['provinsi']),
        'kabupaten'         => sanitize_text_field($_POST['kabupaten']),
        'kecamatan'         => sanitize_text_field($_POST['kecamatan']),
        'kelurahan'         => sanitize_text_field($_POST['kelurahan']),
        
        'api_provinsi_id'   => sanitize_text_field($_POST['api_provinsi_id']),
        'api_kabupaten_id'  => sanitize_text_field($_POST['api_kabupaten_id']),
        'api_kecamatan_id'  => sanitize_text_field($_POST['api_kecamatan_id']),
        'api_kelurahan_id'  => sanitize_text_field($_POST['api_kelurahan_id']),
        
        'status'            => sanitize_text_field($_POST['status']),
        'updated_at'        => current_time('mysql')
    ];

    // Validasi
    if ( empty($data['nama_lengkap']) || empty($data['kode_referral']) || empty($data['nik']) ) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Gagal:</strong> Nama Lengkap, NIK, dan Kode Referral wajib diisi.</p></div>';
    } else {
        if ( $action_type == 'add' ) {
            $data['created_at'] = current_time('mysql');
            
            // Cek Duplikat
            $exist_user = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE id_user = %d", $data['id_user']));
            $exist_ref  = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE kode_referral = %s", $data['kode_referral']));

            if($exist_user) {
                echo '<div class="notice notice-error is-dismissible"><p>User ini sudah terdaftar sebagai verifikator.</p></div>';
            } elseif($exist_ref) {
                echo '<div class="notice notice-error is-dismissible"><p>Kode Referral sudah digunakan. Silakan generate yang baru.</p></div>';
            } else {
                $inserted = $wpdb->insert($table_v, $data);
                if ( $inserted ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Verifikator baru berhasil ditambahkan.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Database Error: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
        } elseif ( $action_type == 'edit' ) {
            $id = intval($_POST['verifikator_id']);
            // Cek kode referral milik orang lain
            $exist_ref = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_v WHERE kode_referral = %s AND id != %d", $data['kode_referral'], $id));
            
            if($exist_ref) {
                echo '<div class="notice notice-error is-dismissible"><p>Kode Referral bentrok dengan verifikator lain.</p></div>';
            } else {
                $updated = $wpdb->update($table_v, $data, ['id' => $id]);
                echo '<div class="notice notice-success is-dismissible"><p>Data Verifikator berhasil diperbarui.</p></div>';
            }
        }
    }
}

// --- 2. GET DATA (SERVER SIDE RENDER) ---
// Global Stats
$total_v = $wpdb->get_var("SELECT COUNT(id) FROM $table_v WHERE status = 'aktif'");
$pending_v = $wpdb->get_var("SELECT COUNT(id) FROM $table_v WHERE status = 'pending'");
$umkm_verified_global = $wpdb->get_var("SELECT SUM(total_verifikasi_sukses) FROM $table_v");
$total_saldo = $wpdb->get_var("SELECT SUM(saldo_saat_ini) FROM $table_v");

// Hitung total pedagang yang terhubung (menggunakan kode referral verifikator mana saja)
// Kita hitung dari tabel pedagang yang memiliki id_verifikator != 0
$total_linked_global = $wpdb->get_var("SELECT COUNT(id) FROM $table_p WHERE id_verifikator > 0");

// Ambil Data Verifikator dengan JOIN Subquery untuk menghitung pedagang yang terhubung per orang
// Logic: Kita hitung berapa pedagang di tabel dw_pedagang yang id_verifikator-nya = id verifikator ini.
$verifikators = $wpdb->get_results("
    SELECT v.*, 
    (SELECT COUNT(p.id) FROM $table_p p WHERE p.id_verifikator = v.id) as linked_count
    FROM $table_v v 
    ORDER BY v.created_at DESC
");

$wp_users = get_users(['role' => 'verifikator_umkm']);
$region_nonce = wp_create_nonce('dw_region_nonce'); 
?>

<!-- STYLE CSS (SCOPED) -->
<style>
    :root {
        --v-primary: #2563eb; --v-primary-dark: #1d4ed8;
        --v-success: #10b981; --v-warning: #f59e0b; --v-danger: #ef4444;
        --v-bg: #f8fafc; --v-border: #e2e8f0;
        --v-text: #1e293b; --v-muted: #64748b;
    }
    .v-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--v-text); margin: 20px 20px 0 0; }
    
    /* Stats Cards */
    .v-grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .v-card { background: #fff; border: 1px solid var(--v-border); border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; flex-direction: column; }
    .v-card h3 { margin: 0 0 5px 0; font-size: 11px; text-transform: uppercase; color: var(--v-muted); letter-spacing: 0.5px; }
    .v-card .val { font-size: 24px; font-weight: 700; color: var(--v-text); }
    .v-card.highlight { border-left: 4px solid var(--v-primary); }
    
    /* Header & Actions */
    .v-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .v-title h1 { margin: 0; font-size: 24px; font-weight: 700; }
    .v-search { padding: 8px 12px; border: 1px solid var(--v-border); border-radius: 8px; width: 300px; font-size: 14px; }
    
    /* Button */
    .v-btn { background: var(--v-primary); color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: 0.2s; }
    .v-btn:hover { background: var(--v-primary-dark); color: #fff; }
    .v-btn-sec { background: #fff; border: 1px solid var(--v-border); color: var(--v-muted); }
    .v-btn-sec:hover { background: var(--v-bg); color: var(--v-text); }

    /* Table */
    .v-table-container { background: #fff; border-radius: 12px; border: 1px solid var(--v-border); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .v-table { width: 100%; border-collapse: collapse; }
    .v-table th { background: #f8fafc; padding: 15px 20px; text-align: left; font-size: 12px; font-weight: 600; color: var(--v-muted); text-transform: uppercase; border-bottom: 1px solid var(--v-border); }
    .v-table td { padding: 15px 20px; border-bottom: 1px solid var(--v-border); vertical-align: middle; font-size: 14px; }
    .v-table tr:last-child td { border-bottom: none; }
    
    /* Badges & Counters */
    .v-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .vb-active { background: #dcfce7; color: #166534; }
    .vb-pending { background: #fef3c7; color: #92400e; }
    .vb-inactive { background: #fee2e2; color: #991b1b; }
    
    .v-code { font-family: monospace; background: var(--v-bg); padding: 4px 8px; border-radius: 4px; font-weight: 600; border: 1px solid var(--v-border); cursor: copy; }
    
    .v-stats-row { display: flex; align-items: center; gap: 15px; font-size: 13px; }
    .stat-item strong { display: block; font-size: 16px; color: var(--v-text); }
    .stat-item span { color: var(--v-muted); font-size: 11px; text-transform: uppercase; }
    .text-success { color: var(--v-success) !important; }
    .text-blue { color: var(--v-primary) !important; }

    /* --- MODAL (Fixed UX) --- */
    .v-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 99999; backdrop-filter: blur(2px); align-items: center; justify-content: center; }
    
    /* Struktur Modal agar Scrollable tapi Header/Footer tetap */
    .v-modal-box { 
        background: #fff; width: 650px; max-width: 95%; height: auto; max-height: 90vh; 
        border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); 
        display: flex; flex-direction: column; overflow: hidden;
        animation: vSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    @keyframes vSlideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

    .v-modal-header { padding: 20px 24px; border-bottom: 1px solid var(--v-border); display: flex; justify-content: space-between; align-items: center; background: #fff; flex-shrink: 0; }
    .v-modal-header h2 { margin: 0; font-size: 18px; color: var(--v-text); }
    .v-close { font-size: 20px; cursor: pointer; color: var(--v-muted); }
    
    /* Body Scrollable */
    .v-modal-body { padding: 24px; overflow-y: auto; flex-grow: 1; background: #fff; }
    
    .v-modal-footer { padding: 16px 24px; border-top: 1px solid var(--v-border); background: var(--v-bg); display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; }

    /* Form Styles */
    .v-form-group { margin-bottom: 15px; }
    .v-form-group label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--v-text); }
    .v-input, .v-select, .v-textarea { width: 100%; padding: 10px; border: 1px solid var(--v-border); border-radius: 8px; font-size: 14px; box-sizing: border-box; transition: 0.2s; }
    .v-input:focus { outline: none; border-color: var(--v-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
    .v-row { display: flex; gap: 15px; }
    .v-col { flex: 1; }
    
    /* Input Group */
    .v-input-group { display: flex; }
    .v-input-group input { border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none; font-weight: 700; color: var(--v-primary); text-transform: uppercase; }
    .v-input-group button { border-top-left-radius: 0; border-bottom-left-radius: 0; background: var(--v-bg); border: 1px solid var(--v-border); padding: 0 15px; cursor: pointer; font-weight: 600; font-size: 13px; color: var(--v-muted); }
    .v-input-group button:hover { background: #e2e8f0; color: var(--v-text); }
</style>

<div class="wrap v-wrap">
    
    <!-- HEADER -->
    <div class="v-header">
        <div class="v-title">
            <h1>Verifikator & Petugas Lapangan</h1>
            <p style="margin:5px 0 0; color:var(--v-muted);">Manajemen data, kode referral, dan monitoring UMKM terhubung.</p>
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
            <h3>UMKM Terverifikasi (ACC)</h3>
            <span class="val"><?php echo number_format($umkm_verified_global); ?></span>
        </div>
        <div class="v-card" style="border-left: 4px solid var(--v-warning);">
            <h3>Total Komisi Cair</h3>
            <span class="val">Rp <?php echo number_format($total_saldo, 0, ',', '.'); ?></span>
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
                    <th>Komisi</th>
                    <th>Status</th>
                    <th style="text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($verifikators)): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 40px; color:var(--v-muted);">Belum ada data verifikator.</td></tr>
                <?php else: foreach($verifikators as $v): 
                    $json = htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8');
                    $status_cls = ($v->status == 'aktif') ? 'vb-active' : (($v->status == 'pending') ? 'vb-pending' : 'vb-inactive');
                    
                    // Hitung rasio
                    $acc_count = (int)$v->total_verifikasi_sukses;
                    $link_count = (int)$v->linked_count;
                ?>
                <tr>
                    <td style="width: 25%;">
                        <div style="font-weight:600; color:var(--v-text); font-size:15px;"><?php echo esc_html($v->nama_lengkap); ?></div>
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
                                <span class="text-blue">Terhubung</span>
                                <strong class="text-blue"><?php echo number_format($link_count); ?></strong>
                            </div>
                            <div style="height:25px; width:1px; background:var(--v-border);"></div>
                            <div class="stat-item">
                                <span class="text-success">ACC / Verif</span>
                                <strong class="text-success"><?php echo number_format($acc_count); ?></strong>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong style="color:var(--v-warning);">Rp <?php echo number_format($v->saldo_saat_ini, 0, ',', '.'); ?></strong>
                    </td>
                    <td><span class="v-badge <?php echo $status_cls; ?>"><?php echo ucfirst($v->status); ?></span></td>
                    <td style="text-align:right;">
                        <button class="v-btn v-btn-sec" style="padding:6px 12px; font-size:12px;" onclick='openModal("edit", <?php echo $json; ?>)'>Edit</button>
                        <?php if($v->nomor_wa): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $v->nomor_wa); ?>" target="_blank" class="v-btn v-btn-sec" style="padding:6px 10px; border-color:#dcfce7; background:#f0fdf4; color:#166534;" title="Chat WA"><span class="dashicons dashicons-whatsapp"></span></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- MODAL FORM (Improved UX) -->
<div id="vModal" class="v-modal-overlay">
    <div class="v-modal-box">
        <div class="v-modal-header">
            <h2 id="modalTitle">Tambah Verifikator</h2>
            <span class="dashicons dashicons-no-alt v-close" onclick="closeModal()"></span>
        </div>
        
        <form method="post" action="" id="vForm" style="display:flex; flex-direction:column; flex:1; overflow:hidden;">
            <?php wp_nonce_field('dw_save_verifikator_action', 'dw_verifikator_nonce'); ?>
            <input type="hidden" name="dw_action" id="vAction" value="add">
            <input type="hidden" name="verifikator_id" id="vId" value="">

            <div class="v-modal-body">
                
                <h4 style="margin:0 0 15px; color:var(--v-primary); border-bottom:1px dashed #e2e8f0; padding-bottom:5px;">Data Akun & Pribadi</h4>
                
                <div class="v-form-group" id="userSelectBox">
                    <label>Hubungkan Akun WordPress</label>
                    <select name="user_id" id="vUser" class="v-select" required>
                        <option value="">-- Pilih User (Role: Verifikator) --</option>
                        <?php foreach($wp_users as $u): ?>
                            <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_email); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:var(--v-muted);">Hanya user dengan role <code>verifikator_umkm</code> yang muncul.</small>
                </div>

                <div class="v-row">
                    <div class="v-col v-form-group">
                        <label>Nama Lengkap (Sesuai KTP)</label>
                        <input type="text" name="nama_lengkap" id="vNama" class="v-input" required>
                    </div>
                    <div class="v-col v-form-group">
                        <label>Kode Referral</label>
                        <div class="v-input-group">
                            <input type="text" name="kode_referral" id="vRef" class="v-input" required>
                            <button type="button" id="btnGenRef" title="Generate Random"><span class="dashicons dashicons-randomize"></span></button>
                        </div>
                    </div>
                </div>

                <div class="v-row">
                    <div class="v-col v-form-group">
                        <label>NIK (16 Digit)</label>
                        <input type="text" name="nik" id="vNik" class="v-input" maxlength="16" required>
                    </div>
                    <div class="v-col v-form-group">
                        <label>Nomor WhatsApp</label>
                        <input type="text" name="nomor_wa" id="vWa" class="v-input" required>
                    </div>
                </div>

                <h4 style="margin:20px 0 15px; color:var(--v-primary); border-bottom:1px dashed #e2e8f0; padding-bottom:5px;">Wilayah Kerja</h4>

                <!-- Waterfall Address -->
                <div class="v-row">
                    <div class="v-col v-form-group">
                        <label>Provinsi</label>
                        <select name="api_provinsi_id" id="selProv" class="v-select" required><option value="">Memuat...</option></select>
                        <input type="hidden" name="provinsi" id="txtProv">
                    </div>
                    <div class="v-col v-form-group">
                        <label>Kabupaten/Kota</label>
                        <select name="api_kabupaten_id" id="selKab" class="v-select" disabled required><option value="">-- Pilih Provinsi --</option></select>
                        <input type="hidden" name="kabupaten" id="txtKab">
                    </div>
                </div>

                <div class="v-row">
                    <div class="v-col v-form-group">
                        <label>Kecamatan</label>
                        <select name="api_kecamatan_id" id="selKec" class="v-select" disabled required><option value="">-- Pilih Kota --</option></select>
                        <input type="hidden" name="kecamatan" id="txtKec">
                    </div>
                    <div class="v-col v-form-group">
                        <label>Kelurahan/Desa</label>
                        <select name="api_kelurahan_id" id="selKel" class="v-select" disabled required><option value="">-- Pilih Kecamatan --</option></select>
                        <input type="hidden" name="kelurahan" id="txtKel">
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
                <button type="submit" class="v-btn">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
    const regionNonce = '<?php echo $region_nonce; ?>';

    // --- 1. MODAL CONTROLS ---
    window.openModal = function(mode, data = null) {
        $('#vModal').css('display', 'flex').hide().fadeIn(200);
        $('#vForm')[0].reset();
        
        if(mode == 'add') {
            $('#modalTitle').text('Tambah Verifikator Baru');
            $('#vAction').val('add');
            $('#vId').val('');
            $('#userSelectBox').show();
            $('#vUser').prop('required', true);
            // Reset region
            resetRegion();
        } else {
            $('#modalTitle').text('Edit Verifikator');
            $('#vAction').val('edit');
            $('#vId').val(data.id);
            
            // Populate
            $('#vNama').val(data.nama_lengkap);
            $('#vRef').val(data.kode_referral);
            $('#vNik').val(data.nik);
            $('#vWa').val(data.nomor_wa);
            $('#vAlamat').val(data.alamat_lengkap);
            $('#vStatus').val(data.status);
            
            // User ID cannot be changed in edit to prevent mismatch
            $('#userSelectBox').hide(); 
            $('#vUser').prop('required', false);

            // Populate Region Manually
            loadRegion('prov', null, $('#selProv'), data.api_provinsi_id);
            $('#txtProv').val(data.provinsi);
            
            if(data.api_provinsi_id) {
                loadRegion('kota', data.api_provinsi_id, $('#selKab'), data.api_kabupaten_id);
                $('#txtKab').val(data.kabupaten);
            }
            if(data.api_kabupaten_id) {
                loadRegion('kec', data.api_kabupaten_id, $('#selKec'), data.api_kecamatan_id);
                $('#txtKec').val(data.kecamatan);
            }
            if(data.api_kecamatan_id) {
                loadRegion('desa', data.api_kecamatan_id, $('#selKel'), data.api_kelurahan_id);
                $('#txtKel').val(data.kelurahan);
            }
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

    // --- 4. AUTO FILL NAME FROM USER ---
    $('#vUser').on('change', function() {
        if($(this).val()) {
            let txt = $(this).find('option:selected').text();
            let name = txt.split('(')[0].trim();
            if($('#vNama').val() === '') $('#vNama').val(name);
        }
    });

    // --- 5. WATERFALL REGION LOGIC ---
    function resetRegion() {
        $('#selKab, #selKec, #selKel').empty().prop('disabled', true).append('<option value="">-- Pilih --</option>');
        loadRegion('prov', null, $('#selProv'), null);
    }

    function loadRegion(type, pid, target, selId) {
        if(type !== 'prov' && (!pid || pid === "")) return;
        
        var act = '';
        if(type === 'prov') act = 'dw_fetch_provinces';
        else if(type === 'kota') act = 'dw_fetch_regencies';
        else if(type === 'kec') act = 'dw_fetch_districts';
        else if(type === 'desa') act = 'dw_fetch_villages';

        // Add loading indicator
        var originalText = target.find('option:first').text();
        target.find('option:first').text('Memuat...');

        $.get(ajaxUrl, { action: act, nonce: regionNonce, province_id: pid, regency_id: pid, district_id: pid }, function(res){
            target.find('option:first').text(originalText); // Restore
            if(res.success){
                target.empty().append('<option value="">-- Pilih --</option>');
                $.each(res.data, function(i,v){
                    let isSel = (String(v.id) === String(selId));
                    target.append(`<option value="${v.id}" data-nama="${v.name}" ${isSel?'selected':''}>${v.name}</option>`);
                });
                target.prop('disabled', false);
                if(selId) target.val(selId);
            }
        });
    }

    // Change Handlers
    $('#selProv').change(function(){
        $('#txtProv').val($(this).find(':selected').data('nama'));
        $('#selKab, #selKec, #selKel').empty().prop('disabled', true);
        loadRegion('kota', $(this).val(), $('#selKab'), null);
    });
    $('#selKab').change(function(){
        $('#txtKab').val($(this).find(':selected').data('nama'));
        $('#selKec, #selKel').empty().prop('disabled', true);
        loadRegion('kec', $(this).val(), $('#selKec'), null);
    });
    $('#selKec').change(function(){
        $('#txtKec').val($(this).find(':selected').data('nama'));
        $('#selKel').empty().prop('disabled', true);
        loadRegion('desa', $(this).val(), $('#selKel'), null);
    });
    $('#selKel').change(function(){
        $('#txtKel').val($(this).find(':selected').data('nama'));
    });

    // Init Load
    loadRegion('prov', null, $('#selProv'), null);
});

// Copy Helper
function copyCode(txt) {
    navigator.clipboard.writeText(txt).then(() => alert('Kode disalin: ' + txt));
}
</script>