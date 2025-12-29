<?php
/**
 * File Name:   includes/admin-pages/page-verifikator-list.php
 * Description: Dashboard Manajemen Verifikator UMKM v3.7 (Premium UI & Database Connected).
 * Fitur: 
 * 1. UI Modern dengan Kartu Statistik Real-time.
 * 2. Tabel Data Verifikator yang terhubung ke tabel `dw_verifikator`.
 * 3. Modal AJAX untuk Tambah/Edit tanpa reload.
 * 4. Integrasi penuh dengan API Wilayah (Waterfall Loading).
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
$verifikator_table = $wpdb->prefix . 'dw_verifikator';

// Nonce untuk keamanan AJAX
$nonce = wp_create_nonce('dw_admin_nonce');
$region_nonce = wp_create_nonce('dw_region_nonce'); // Nonce khusus wilayah

/**
 * Filter User: 
 * Ambil daftar user WordPress yang memiliki role 'verifikator_umkm'
 * untuk dihubungkan dengan profil verifikator.
 */
$args = array(
    'role'    => 'verifikator_umkm',
    'fields'  => array('ID', 'display_name', 'user_email')
);
$wp_users = get_users($args);
?>

<style>
    /* Premium Layout & Variables */
    :root {
        --dw-primary: #2563eb;
        --dw-primary-dark: #1d4ed8;
        --dw-success: #10b981;
        --dw-bg: #f8fafc;
        --dw-border: #e2e8f0;
        --dw-text-main: #1e293b;
        --dw-text-muted: #64748b;
    }

    .v-container { 
        margin: 20px 20px 0 0; 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--dw-text-main);
    }

    /* Header Styling */
    .v-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-end; 
        margin-bottom: 30px; 
    }

    .v-header-title h1 { 
        font-weight: 800; 
        font-size: 28px; 
        color: var(--dw-text-main); 
        margin: 0;
        letter-spacing: -0.02em;
    }

    .v-header-title p { 
        color: var(--dw-text-muted); 
        margin: 5px 0 0 0; 
        font-size: 14px;
    }

    /* Stats Grid */
    .v-card-stats { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
        gap: 20px; 
        margin-bottom: 35px; 
    }

    .v-stat { 
        background: #fff; 
        padding: 24px; 
        border-radius: 20px; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 10px 15px -3px rgba(0,0,0,0.03); 
        border: 1px solid var(--dw-border);
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .v-stat::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 4px; height: 100%;
        background: var(--dw-border);
    }

    .v-stat.active-blue::before { background: var(--dw-primary); }
    .v-stat.active-green::before { background: var(--dw-success); }

    .v-stat h3 { 
        margin: 0; 
        color: var(--dw-text-muted); 
        font-size: 12px; 
        text-transform: uppercase; 
        font-weight: 700; 
        letter-spacing: 0.1em;
    }

    .v-stat p { 
        margin: 8px 0 0 0; 
        font-size: 32px; 
        font-weight: 800; 
        color: var(--dw-text-main);
        line-height: 1;
    }

    /* Modern Table */
    .v-table-card { 
        background: #fff; 
        border-radius: 20px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        border: 1px solid var(--dw-border); 
        overflow: hidden;
    }

    .v-table { width: 100%; border-collapse: collapse; text-align: left; }
    .v-table th { 
        background: #fcfdfe; 
        padding: 18px 24px; 
        font-size: 13px; 
        font-weight: 600; 
        color: var(--dw-text-muted); 
        border-bottom: 1px solid var(--dw-border);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .v-table td { 
        padding: 20px 24px; 
        border-bottom: 1px solid #f8fafc; 
        vertical-align: middle; 
        font-size: 14px;
    }

    .v-table tr:hover td { background: #fbfcfe; }
    
    /* User Profile In Table */
    .user-info { display: flex; align-items: center; gap: 14px; }
    .user-avatar { 
        width: 40px; height: 40px; 
        border-radius: 12px; 
        background: #eff6ff; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        color: var(--dw-primary); 
        font-weight: 700;
        border: 1px solid #dbeafe;
    }

    /* Badges */
    .v-badge { 
        padding: 6px 14px; 
        border-radius: 10px; 
        font-size: 11px; 
        font-weight: 700; 
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .badge-aktif { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
    .badge-pending { background: #fffbeb; color: #92400e; border: 1px solid #fef3c7; }
    .badge-nonaktif { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

    /* Referral Code Badge */
    .ref-code-badge {
        background: #f1f5f9;
        color: #475569;
        font-family: monospace;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 700;
        border: 1px dashed #cbd5e1;
        cursor: copy;
    }

    /* Button Styling */
    .btn-premium {
        background: var(--dw-primary);
        color: white !important;
        border: none;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
    }

    .btn-premium:hover {
        background: var(--dw-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
    }

    /* Modal Design */
    .v-modal { 
        display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; 
        background: rgba(15, 23, 42, 0.6); 
        backdrop-filter: blur(8px); 
    }

    .v-modal-content { 
        background: #fff; margin: 2% auto; border-radius: 24px; 
        width: 600px; max-width: 95%; 
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
        overflow: hidden; 
        animation: vSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    @keyframes vSlideUp { from {opacity: 0; transform: translateY(30px) scale(0.98);} to {opacity: 1; transform: translateY(0) scale(1);} }
    
    .v-modal-header { 
        padding: 24px 30px; 
        background: #fff; 
        border-bottom: 1px solid var(--dw-border); 
        display: flex; justify-content: space-between; align-items: center; 
        flex-shrink: 0;
    }

    .v-modal-header h2 { margin: 0; font-size: 20px; font-weight: 800; color: var(--dw-text-main); }
    
    .v-modal-body { 
        padding: 30px; 
        overflow-y: auto; 
        flex-grow: 1;
    }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: var(--dw-text-main); }
    .form-group input, .form-group select, .form-group textarea { 
        width: 100%; padding: 12px 16px; border: 1.5px solid var(--dw-border); 
        border-radius: 12px; font-size: 14px; transition: all 0.2s; 
        background-color: #fff;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
        outline: none; border-color: var(--dw-primary); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); 
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .v-modal-footer { 
        padding: 20px 30px; background: #f8fafc; border-top: 1px solid var(--dw-border); 
        display: flex; justify-content: flex-end; gap: 12px; 
        flex-shrink: 0;
    }

    .empty-state { padding: 80px 0; text-align: center; color: var(--dw-text-muted); }
    .empty-state span { font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.3; }

    .btn-action-edit {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid var(--dw-border);
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-action-edit:hover {
        background: #e2e8f0;
        color: var(--dw-text-main);
    }
</style>

<div class="wrap v-container">
    <!-- Header Premium -->
    <div class="v-header">
        <div class="v-header-title">
            <h1>Verifikator Terdaftar</h1>
            <p>Kelola profil agen verifikasi wilayah, pantau akumulasi komisi, dan kelola Kode Referral.</p>
        </div>
        <button class="btn-premium" id="btn-add-v">
            <span class="dashicons dashicons-plus-alt2"></span> Tambah Verifikator
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="v-card-stats">
        <div class="v-stat">
            <h3>Total Verifikator</h3>
            <p id="v-total">0</p>
        </div>
        <div class="active-blue v-stat">
            <h3>UMKM Terverifikasi</h3>
            <p id="v-docs">0</p>
        </div>
        <div class="active-green v-stat">
            <h3>Saldo Komisi Tersedia</h3>
            <p id="v-money">Rp 0</p>
        </div>
    </div>

    <!-- Main List Table -->
    <div class="v-table-card">
        <table class="v-table">
            <thead>
                <tr>
                    <th>Informasi Verifikator</th>
                    <th>Kode Referral</th>
                    <th>Penugasan Wilayah</th>
                    <th>Penyelesaian</th>
                    <th>Akumulasi Saldo</th>
                    <th>Status</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody id="v-list-body">
                <tr><td colspan="7" style="text-align:center; padding:80px; color: var(--dw-text-muted);">
                    <div class="empty-state">
                        <span class="dashicons dashicons-update spin"></span>
                        <p>Sinkronisasi data verifikator...</p>
                    </div>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Terpadu (Tambah & Edit) -->
<div id="modal-v" class="v-modal">
    <div class="v-modal-content">
        <div class="v-modal-header">
            <h2 id="modal-title">Daftarkan Verifikator</h2>
            <span class="dashicons dashicons-no-alt" id="close-modal-v" style="cursor:pointer; color: var(--dw-text-muted);"></span>
        </div>
        <form id="form-v" style="display: contents;">
            <input type="hidden" name="id" id="v_id" value="">
            <input type="hidden" name="method" id="v_method" value="add">
            
            <div class="v-modal-body">
                <h4 style="margin: 0 0 15px 0; color: var(--dw-primary); font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em;">Informasi Akun & Personal</h4>
                
                <!-- Pilihan User WordPress -->
                <div class="form-group" id="user-select-group">
                    <label>Pilih Akun User WordPress (Role Verifikator Saja)</label>
                    <select name="user_id" id="v_user_select" required>
                        <option value="">-- Pilih User Verifikator --</option>
                        <?php foreach($wp_users as $u): ?>
                            <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_email); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" style="font-size:12px; margin-top:5px;">User harus dibuat terlebih dahulu di menu Users > Add New.</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nama Lengkap (Identitas Resmi)</label>
                        <input type="text" name="nama_lengkap" id="v_nama" placeholder="Budi Santoso, S.E." required>
                    </div>
                    <div class="form-group">
                        <label>Kode Referral (Unik)</label>
                        <input type="text" name="kode_referral" id="v_referral" placeholder="Contoh: BANTUL01" required style="text-transform: uppercase;">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>NIK (16 Digit)</label>
                        <input type="text" name="nik" id="v_nik" placeholder="Contoh: 330101XXXXXXXXXX" maxlength="16" required>
                    </div>
                    <div class="form-group">
                        <label>WhatsApp Aktif</label>
                        <input type="text" name="nomor_wa" id="v_wa" placeholder="Contoh: 08123456789" required>
                    </div>
                </div>

                <h4 style="margin: 25px 0 15px 0; color: var(--dw-primary); font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em;">Cakupan Wilayah Kerja</h4>
                
                <!-- Waterfall Location Selects (Sama dengan Page Pedagang) -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Provinsi</label>
                        <select name="api_provinsi_id" id="sel-prov" required>
                            <option value="">Memuat...</option>
                        </select>
                        <input type="hidden" name="provinsi" id="txt-prov">
                    </div>
                    <div class="form-group">
                        <label>Kabupaten/Kota</label>
                        <select name="api_kabupaten_id" id="sel-kab" disabled required>
                            <option value="">-- Pilih Provinsi Dulu --</option>
                        </select>
                        <input type="hidden" name="kabupaten" id="txt-kab">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Kecamatan</label>
                        <select name="api_kecamatan_id" id="sel-kec" disabled required>
                            <option value="">-- Pilih Kota Dulu --</option>
                        </select>
                        <input type="hidden" name="kecamatan" id="txt-kec">
                    </div>
                    <div class="form-group">
                        <label>Kelurahan/Desa</label>
                        <select name="api_kelurahan_id" id="sel-kel" disabled required>
                            <option value="">-- Pilih Kecamatan Dulu --</option>
                        </select>
                        <input type="hidden" name="kelurahan" id="txt-kel">
                    </div>
                </div>

                <div class="form-group">
                    <label>Alamat Lengkap Domisili</label>
                    <textarea name="alamat_lengkap" id="v_alamat" rows="3" placeholder="Jl. Mawar No. 123, RT 01/RW 02..." required></textarea>
                </div>
            </div>
            <div class="v-modal-footer">
                <button type="button" class="button-link" id="btn-cancel-v" style="color: var(--dw-text-muted); text-decoration: none; font-weight: 600; cursor: pointer;">Batal</button>
                <button type="submit" class="btn-premium" id="btn-submit-v">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo $nonce; ?>';
    const regionNonce = '<?php echo $region_nonce; ?>';
    let allVerifikators = [];

    // 1. Inisialisasi Data Verifikator
    function loadVerifikators() {
        $.post(ajaxurl, { 
            action: 'dw_get_verifikator_list', 
            nonce: nonce 
        }, function(res) {
            if(res.success) {
                allVerifikators = res.data;
                let html = '';
                let totalV = 0; 
                let totalMoney = 0;
                
                if(res.data && res.data.length > 0) {
                    res.data.forEach((v, index) => {
                        totalV += parseInt(v.total || 0);
                        let balanceRaw = 0;
                        if(v.balance) {
                            balanceRaw = parseInt(v.balance.replace(/[^0-9]/g, '')) || 0;
                        }
                        totalMoney += balanceRaw;

                        // Tentukan class badge status
                        let statusClass = 'badge-aktif';
                        if(v.status === 'pending') statusClass = 'badge-pending';
                        if(v.status === 'nonaktif') statusClass = 'badge-nonaktif';

                        html += `
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">${v.name.charAt(0)}</div>
                                    <div>
                                        <div style="font-weight:700; color:var(--dw-text-main);">${v.name}</div>
                                        <div style="font-size:12px; color:var(--dw-text-muted);">NIK: ${v.nik || '-'}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="ref-code-badge" title="Klik untuk salin">${v.kode_referral || 'N/A'}</span></td>
                            <td><span style="font-weight:500;">${v.location}</span></td>
                            <td><span style="font-weight:700; color:var(--dw-primary);">${v.total} Dokumen</span></td>
                            <td><span style="font-weight:700; color:var(--dw-success);">${v.balance}</span></td>
                            <td><span class="v-badge ${statusClass}">${v.status ? v.status.toUpperCase() : 'AKTIF'}</span></td>
                            <td style="text-align: right;">
                                <div style="display:flex; justify-content:flex-end; gap:8px;">
                                    <button class="btn-action-edit" data-index="${index}">Edit</button>
                                    <a href="https://wa.me/${v.wa}" target="_blank" class="button" style="border-radius:8px;">Hubungi</a>
                                </div>
                            </td>
                        </tr>`;
                    });
                } else {
                    html = `<tr><td colspan="7">
                        <div class="empty-state">
                            <span class="dashicons dashicons-groups"></span>
                            <p>Belum ada tim verifikator yang ditugaskan.</p>
                        </div>
                    </td></tr>`;
                }

                $('#v-list-body').html(html);
                $('#v-total').text(res.data ? res.data.length : 0);
                $('#v-docs').text(totalV);
                $('#v-money').text('Rp ' + totalMoney.toLocaleString('id-ID'));
            }
        });
    }

    // Copy to clipboard logic
    $(document).on('click', '.ref-code-badge', function() {
        const code = $(this).text();
        const temp = $("<input>");
        $("body").append(temp);
        temp.val(code).select();
        document.execCommand("copy");
        temp.remove();
        alert('Kode Referral ' + code + ' disalin!');
    });

    // 2. Logika Wilayah Dinamis (Waterfall - Consistent with Page Pedagang)
    function loadRegion(type, pid, target, selId) {
        // Validasi ID Induk
        if(type !== 'prov' && (!pid || pid === "")) return;

        var act = '';
        if(type === 'prov') act = 'dw_fetch_provinces';
        else if(type === 'kota') act = 'dw_fetch_regencies';
        else if(type === 'kec') act = 'dw_fetch_districts';
        else if(type === 'desa') act = 'dw_fetch_villages';

        // Siapkan target
        target.prop('disabled', true).html('<option>Memuat...</option>');

        var data = { 
            action: act, 
            nonce: regionNonce 
        };
        if(type==='kota') data.province_id = pid;
        if(type==='kec') data.regency_id = pid;
        if(type==='desa') data.district_id = pid;

        $.get(ajaxurl, data, function(res){
            if(res.success){
                target.empty().append('<option value="">-- Pilih --</option>');
                $.each(res.data, function(i,v){
                    var isSelected = (String(v.id) === String(selId));
                    target.append('<option value="'+v.id+'" data-nama="'+v.name+'" '+(isSelected?'selected':'')+'>'+v.name+'</option>');
                });
                target.prop('disabled', false);
                
                // Trigger change jika ada pre-selected value (untuk load anak)
                if(selId) {
                    target.val(selId);
                    target.trigger('change');
                }
            } else {
                target.html('<option value="">Gagal memuat</option>');
            }
        });
    }

    // Reset Form
    function resetForm() {
        $('#v_method').val('add');
        $('#v_id').val('');
        $('#modal-title').text('Daftarkan Verifikator');
        $('#user-select-group').show();
        $('#v_user_select').prop('required', true).val('');
        $('#form-v')[0].reset();
        
        // Reset wilayah
        $('#sel-kab, #sel-kec, #sel-kel').prop('disabled', true).html('<option value="">-- Pilih --</option>');
        
        // Load ulang provinsi jika kosong
        if($('#sel-prov option').length <= 1) {
            loadRegion('prov', null, $('#sel-prov'), null);
        }
    }

    // Open Modal Add
    $('#btn-add-v').on('click', function() {
        resetForm();
        $('#modal-v').fadeIn(300);
    });

    // Handle Edit Click
    $(document).on('click', '.btn-action-edit', function() {
        const idx = $(this).data('index');
        const v = allVerifikators[idx];

        $('#v_method').val('edit');
        $('#v_id').val(v.id);
        $('#modal-title').text('Edit Verifikator: ' + v.name);
        $('#user-select-group').hide(); 
        $('#v_user_select').prop('required', false);

        // Populate Basic Data
        $('#v_nama').val(v.name);
        $('#v_referral').val(v.kode_referral || '');
        $('#v_nik').val(v.nik || '');
        $('#v_wa').val(v.wa || '');
        $('#v_alamat').val(v.alamat_lengkap || '');

        $('#modal-v').fadeIn(300);

        // Populate Wilayah (Waterfall Trigger)
        // Set Provinsi dulu, event on change akan handle sisanya jika kita passing selectId ke fungsi
        // Namun karena event change kita custom, kita load manual berurutan
        
        // Reset dulu
        $('#sel-kab, #sel-kec, #sel-kel').empty().prop('disabled', true);

        // Load Prov
        loadRegion('prov', null, $('#sel-prov'), v.api_provinsi_id);
        $('#txt-prov').val(v.provinsi); // Set text hidden (asumsi v.provinsi ada di respon)

        // Kita perlu timeout sedikit atau logika callback untuk load anak-anaknya
        // Cara simpel: load semua level secara parallel karena kita punya ID induknya dari data verifikator
        if(v.api_provinsi_id) {
            loadRegion('kota', v.api_provinsi_id, $('#sel-kab'), v.api_kabupaten_id);
            $('#txt-kab').val(v.kabupaten);
        }
        if(v.api_kabupaten_id) {
            loadRegion('kec', v.api_kabupaten_id, $('#sel-kec'), v.api_kecamatan_id);
            $('#txt-kec').val(v.kecamatan);
        }
        if(v.api_kecamatan_id) {
            loadRegion('desa', v.api_kecamatan_id, $('#sel-kel'), v.api_kelurahan_id);
            $('#txt-kel').val(v.kelurahan);
        }
    });

    // Wilayah Change Handlers (Menyimpan Nama & Load Anak)
    $('#sel-prov').on('change', function() {
        const id = $(this).val();
        $('#txt-prov').val($(this).find(':selected').data('nama'));
        $('#sel-kab, #sel-kec, #sel-kel').prop('disabled', true).html('<option value="">-- Pilih --</option>');
        if(id) loadRegion('kota', id, $('#sel-kab'), null);
    });

    $('#sel-kab').on('change', function() {
        const id = $(this).val();
        $('#txt-kab').val($(this).find(':selected').data('nama'));
        $('#sel-kec, #sel-kel').prop('disabled', true).html('<option value="">-- Pilih --</option>');
        if(id) loadRegion('kec', id, $('#sel-kec'), null);
    });

    $('#sel-kec').on('change', function() {
        const id = $(this).val();
        $('#txt-kec').val($(this).find(':selected').data('nama'));
        $('#sel-kel').prop('disabled', true).html('<option value="">-- Pilih --</option>');
        if(id) loadRegion('desa', id, $('#sel-kel'), null);
    });

    $('#sel-kel').on('change', function() {
        $('#txt-kel').val($(this).find(':selected').data('nama'));
    });

    // Modal close
    $('#close-modal-v, #btn-cancel-v').on('click', () => $('#modal-v').fadeOut(200));

    // Auto-fill Nama dari Select User
    $('#v_user_select').on('change', function() {
        if($(this).val() !== "") {
            const nameOnly = $(this).find('option:selected').text().split('(')[0].trim();
            $('#v_nama').val(nameOnly);
        }
    });

    // Handle AJAX Save (Add/Edit)
    $('#form-v').submit(function(e) {
        e.preventDefault();
        const $btn = $('#btn-submit-v');
        const method = $('#v_method').val();
        const action = (method === 'add') ? 'dw_add_verifikator' : 'dw_update_verifikator';
        
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Menyimpan...');

        $.post(ajaxurl, $(this).serialize() + '&action=' + action + '&nonce=' + nonce, function(res) {
            if(res.success) {
                $('#modal-v').fadeOut(200);
                alert(res.data || 'Berhasil disimpan!');
                loadVerifikators();
                if(method === 'add') {
                    // Reset dan reload agar data fresh
                    location.reload(); 
                }
                $btn.prop('disabled', false).text('Simpan Perubahan');
            } else {
                alert('Gagal: ' + (res.data || 'Terjadi kesalahan sistem'));
                $btn.prop('disabled', false).text('Simpan Perubahan');
            }
        }).fail(function() {
            alert('Terjadi kesalahan koneksi server.');
            $btn.prop('disabled', false).text('Simpan Perubahan');
        });
    });

    // Initial Load
    loadVerifikators();
});
</script>