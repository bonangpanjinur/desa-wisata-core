<?php
/**
 * File Name:   includes/admin-pages/page-verifikator-list.php
 * Description: Dashboard Manajemen Verifikator UMKM v3.6.
 * Fitur: Daftar verifikator, statistik performa, dan tambah verifikator baru.
 */

if (!defined('ABSPATH')) exit;

// Nonce untuk keamanan AJAX
$nonce = wp_create_nonce('dw_admin_nonce');

// Ambil semua user WordPress untuk dipilih sebagai Verifikator
$wp_users = get_users(array('fields' => array('ID', 'display_name', 'user_email')));
?>

<style>
    /* Layout Utama */
    .v-container { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
    .v-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    
    /* Stats Cards */
    .v-card-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .v-stat { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; transition: transform 0.2s; }
    .v-stat:hover { transform: translateY(-5px); }
    .v-stat h3 { margin: 0; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
    .v-stat p { margin: 10px 0 0 0; font-size: 28px; font-weight: 800; color: #1e293b; }
    
    /* Table Styling */
    .v-table-card { background: #fff; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .v-table { width: 100%; border-collapse: collapse; text-align: left; }
    .v-table th { background: #f8fafc; padding: 15px 20px; font-size: 13px; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
    .v-table td { padding: 18px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
    .v-table tr:last-child td { border-bottom: none; }
    
    /* Badges */
    .v-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
    .badge-aktif { background: #dcfce7; color: #15803d; }
    .badge-pending { background: #fef3c7; color: #92400e; }

    /* Modal Styling */
    .v-modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
    .v-modal-content { background: #fff; margin: 5% auto; border-radius: 20px; width: 500px; max-width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden; animation: vFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes vFadeIn { from {opacity: 0; transform: scale(0.95) translateY(-20px);} to {opacity: 1; transform: scale(1) translateY(0);} }
    
    .v-modal-header { padding: 20px 25px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
    .v-modal-header h2 { margin: 0; font-size: 18px; color: #111827; }
    .v-modal-close { cursor: pointer; color: #9ca3af; transition: color 0.2s; font-size: 24px; line-height: 1; }
    .v-modal-close:hover { color: #ef4444; }
    
    .v-modal-body { padding: 25px; }
    .v-modal-footer { padding: 15px 25px; background: #f9fafb; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; }

    /* Form Elements */
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #374151; }
    .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #2563eb; ring: 2px rgba(37, 99, 235, 0.1); }
</style>

<div class="wrap v-container">
    <!-- Header -->
    <div class="v-header">
        <div>
            <h1 style="font-weight: 800; font-size: 26px; color: #1e293b; margin: 0;">Manajemen Verifikator UMKM</h1>
            <p style="color: #64748b; margin-top: 5px;">Kelola profil, wilayah kerja, dan komisi pendapatan tim verifikator.</p>
        </div>
        <button class="button button-primary" style="height: 42px; padding: 0 24px; border-radius: 10px; font-weight: 600;" id="btn-add-v">
            <span class="dashicons dashicons-plus" style="margin-top: 8px;"></span> Tambah Verifikator
        </button>
    </div>

    <!-- Stats Section -->
    <div class="v-card-stats">
        <div class="v-stat">
            <h3>Total Tim Verifikator</h3>
            <p id="v-total">0</p>
        </div>
        <div class="v-stat" style="border-top: 4px solid #3b82f6;">
            <h3>Total Verifikasi Selesai</h3>
            <p id="v-docs">0</p>
        </div>
        <div class="v-stat" style="border-top: 4px solid #10b981;">
            <h3>Akumulasi Saldo Komisi</h3>
            <p id="v-money">Rp 0</p>
        </div>
    </div>

    <!-- Table Section -->
    <div class="v-table-card">
        <table class="v-table">
            <thead>
                <tr>
                    <th>Nama Verifikator</th>
                    <th>Wilayah Kerja</th>
                    <th>Performa</th>
                    <th>Saldo Saat Ini</th>
                    <th>Status</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody id="v-list-body">
                <tr><td colspan="6" style="text-align:center; padding:60px; color: #94a3b8;">Sedang memuat data tim...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Verifikator -->
<div id="modal-v" class="v-modal">
    <div class="v-modal-content">
        <div class="v-modal-header">
            <h2>Tambah Verifikator Baru</h2>
            <span class="v-modal-close" id="close-modal-v">&times;</span>
        </div>
        <form id="form-v">
            <div class="v-modal-body">
                <div class="form-group">
                    <label>Pilih Akun User WordPress</label>
                    <select name="user_id" id="v_user_select" required>
                        <option value="">-- Pilih User --</option>
                        <?php foreach($wp_users as $u): ?>
                        <option value="<?php echo $u->ID; ?>"><?php echo $u->display_name; ?> (<?php echo $u->user_email; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap (Sesuai KTP)</label>
                    <input type="text" name="nama" id="v_nama" placeholder="Contoh: Budi Santoso" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>NIK</label>
                        <input type="text" name="nik" placeholder="16 Digit NIK" required>
                    </div>
                    <div class="form-group">
                        <label>Nomor WhatsApp</label>
                        <input type="text" name="wa" placeholder="628xxxx" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Penugasan Wilayah (Kota/Kabupaten)</label>
                    <input type="text" name="kota" placeholder="Contoh: Bantul" required>
                </div>
            </div>
            <div class="v-modal-footer">
                <button type="button" class="button" id="btn-cancel-v">Batal</button>
                <button type="submit" class="button button-primary" id="btn-submit-v">Daftarkan Verifikator</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo $nonce; ?>';

    // Fungsi Load Data Verifikator
    function loadVerifikators() {
        $.post(ajaxurl, { 
            action: 'dw_get_verifikator_list', 
            nonce: nonce 
        }, function(res) {
            if(res.success) {
                let html = '';
                let totalV = 0; 
                let totalMoney = 0;
                
                if(res.data.length > 0) {
                    res.data.forEach(v => {
                        totalV += parseInt(v.total);
                        // Parsing saldo (hapus format rupiah untuk hitung total)
                        let balanceRaw = parseInt(v.balance.replace(/[^0-9]/g, ''));
                        totalMoney += isNaN(balanceRaw) ? 0 : balanceRaw;

                        html += `
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:32px; height:32px; border-radius:50%; background:#eff6ff; display:flex; align-items:center; justify-content:center; color:#2563eb; font-weight:bold; font-size:12px;">${v.name.charAt(0)}</div>
                                    <strong>${v.name}</strong>
                                </div>
                            </td>
                            <td><span style="font-size:13px; color:#64748b;">${v.location}</span></td>
                            <td><span style="font-weight:700; color:#2563eb;">${v.total} UMKM</span></td>
                            <td><span style="font-weight:700; color:#059669;">${v.balance}</span></td>
                            <td><span class="v-badge badge-aktif">${v.status}</span></td>
                            <td style="text-align: right;">
                                <a href="https://wa.me/${v.wa}" target="_blank" class="button" title="Chat WhatsApp">Hubungi</a>
                            </td>
                        </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="6" style="text-align:center; padding:50px;">Belum ada verifikator terdaftar.</td></tr>';
                }

                $('#v-list-body').html(html);
                $('#v-total').text(res.data.length);
                $('#v-docs').text(totalV);
                $('#v-money').text('Rp ' + totalMoney.toLocaleString('id-ID'));
            }
        });
    }

    // Modal Interaction
    $('#btn-add-v').on('click', function(e) {
        e.preventDefault();
        $('#modal-v').fadeIn(200);
    });

    $('#close-modal-v, #btn-cancel-v').on('click', function() {
        $('#modal-v').fadeOut(200);
    });

    // Close on backdrop click
    $(window).on('click', function(e) {
        if ($(e.target).is('#modal-v')) {
            $('#modal-v').fadeOut(200);
        }
    });

    // Handle User Selection to auto-fill name
    $('#v_user_select').on('change', function() {
        const name = $(this).find('option:selected').text().split('(')[0].trim();
        if($(this).val() !== "") $('#v_nama').val(name);
    });

    // Submit Form via AJAX
    $('#form-v').submit(function(e) {
        e.preventDefault();
        const $btn = $('#btn-submit-v');
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Menyimpan...');

        const formData = $(this).serialize() + '&action=dw_add_verifikator&nonce=' + nonce;
        
        $.post(ajaxurl, formData, function(res) {
            if(res.success) {
                alert('Berhasil: ' + res.data);
                $('#modal-v').fadeOut(200);
                $('#form-v')[0].reset();
                loadVerifikators();
            } else {
                alert('Error: ' + res.data);
            }
            $btn.prop('disabled', false).text(originalText);
        });
    });

    // Initial Load
    loadVerifikators();
});
</script>