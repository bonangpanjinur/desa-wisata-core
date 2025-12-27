<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('dw_admin_nonce');
?>

<style>
    .v-container { margin: 20px 20px 0 0; }
    .v-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .v-card { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #f1f5f9; }
    .v-card h3 { margin: 0; color: #64748b; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .v-card p { margin: 10px 0 0 0; font-size: 1.875rem; font-weight: 800; color: #1e293b; }
    
    .v-table-wrap { background: #fff; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .v-table { width: 100%; border-collapse: collapse; }
    .v-table th { background: #f8fafc; padding: 15px 20px; text-align: left; font-weight: 600; color: #475569; font-size: 13px; }
    .v-table td { padding: 18px 20px; border-top: 1px solid #f1f5f9; vertical-align: middle; }
    
    .v-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .badge-aktif { background: #dcfce7; color: #15803d; }
    .badge-pending { background: #fef3c7; color: #92400e; }

    .btn-wa { background: #22c55e; color: white; padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
</style>

<div class="wrap v-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="font-weight: 800; font-size: 24px;">Manajemen Verifikator UMKM</h1>
            <p style="color: #64748b;">Pantau kinerja dan pendapatan komisi agen verifikator wilayah.</p>
        </div>
        <button class="button button-primary" style="height: 40px; padding: 0 20px; border-radius: 8px;">+ Tambah Verifikator</button>
    </div>

    <div class="v-stats">
        <div class="v-card">
            <h3>Total Verifikator</h3>
            <p id="stat-total">0</p>
        </div>
        <div class="v-card" style="border-top: 4px solid #10b981;">
            <h3>Total UMKM Terverifikasi</h3>
            <p id="stat-verified">0</p>
        </div>
        <div class="v-card" style="border-top: 4px solid #3b82f6;">
            <h3>Total Komisi Terbayar</h3>
            <p id="stat-payout">Rp 0</p>
        </div>
    </div>

    <div class="v-table-wrap">
        <table class="v-table">
            <thead>
                <tr>
                    <th>Nama Verifikator</th>
                    <th>Wilayah Kerja</th>
                    <th style="text-align: center;">Total Verifikasi</th>
                    <th>Pendapatan Komisi</th>
                    <th>Status</th>
                    <th>Kontak</th>
                </tr>
            </thead>
            <tbody id="verifikator-body">
                <tr><td colspan="6" style="text-align:center; padding:50px;">Memuat data verifikator...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function loadVerifikator() {
        $.post(ajaxurl, {
            action: 'dw_get_verifikator_list',
            nonce: '<?php echo $nonce; ?>'
        }, function(res) {
            if (res.success) {
                let html = '';
                let totalV = 0;
                res.data.forEach(v => {
                    totalV += v.total_docs;
                    html += `
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:35px; height:35px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; color:#64748b;">${v.name.charAt(0)}</div>
                                <strong>${v.name}</strong>
                            </div>
                        </td>
                        <td style="color:#64748b; font-size:13px;">${v.location}</td>
                        <td style="text-align:center;"><span style="font-weight:700; color:#2563eb;">${v.total_docs} UMKM</span></td>
                        <td style="font-weight:700; color:#059669;">${v.income}</td>
                        <td><span class="v-badge badge-${v.status}">${v.status.toUpperCase()}</span></td>
                        <td>
                            <a href="https://wa.me/${v.wa}" class="btn-wa" target="_blank">
                                <span class="dashicons dashicons-whatsapp"></span> WhatsApp
                            </a>
                        </td>
                    </tr>`;
                });
                $('#verifikator-body').html(html);
                $('#stat-total').text(res.data.length);
                $('#stat-verified').text(totalV);
            }
        });
    }
    loadVerifikator();
});
</script>