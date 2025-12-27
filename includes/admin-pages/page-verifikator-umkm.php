<?php
if (!defined('ABSPATH')) exit;

/**
 * UI Verifikator Akun (Pedagang & Desa)
 */
$nonce = wp_create_nonce('dw_admin_nonce');
?>

<style>
    .dw-admin-wrap { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    
    /* Filter Bar */
    .dw-header-flex { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
    .dw-filter-group { display: flex; gap: 15px; background: #fff; padding: 10px 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .dw-filter-item label { display: block; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #94a3b8; margin-bottom: 5px; }
    .dw-filter-item select { border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 10px; min-width: 150px; }

    /* Table Styling */
    .dw-main-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; }
    .dw-table { width: 100%; border-collapse: collapse; }
    .dw-table th { background: #f8fafc; padding: 15px 20px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
    .dw-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    
    /* Type Badges */
    .role-badge { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .role-desa { background: #e0f2fe; color: #0369a1; }
    .role-pedagang { background: #fef3c7; color: #92400e; }

    /* Status Badges */
    .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .status-pending { background: #f1f5f9; color: #475569; }
    .status-approved { background: #dcfce7; color: #15803d; }
    .status-rejected { background: #fee2e2; color: #b91c1c; }

    .btn-action { padding: 8px 14px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; font-weight: 600; transition: 0.2s; }
    .btn-approve { background: #10b981; color: #fff; }
    .btn-approve:hover { background: #059669; }
    .btn-reject { background: #ef4444; color: #fff; }
</style>

<div class="wrap dw-admin-wrap">
    <div class="dw-header-flex">
        <div>
            <h1 style="margin:0;">Verifikasi Akun Sistem</h1>
            <p style="color:#64748b; margin:5px 0 0 0;">Kelola pendaftaran akun Pedagang UMKM dan Admin Desa.</p>
        </div>
        
        <div class="dw-filter-group">
            <div class="dw-filter-item">
                <label>Status</label>
                <select id="filter-status">
                    <option value="pending">Menunggu</option>
                    <option value="approved">Aktif</option>
                    <option value="rejected">Ditolak</option>
                </select>
            </div>
            <div class="dw-filter-item">
                <label>Tipe Akun</label>
                <select id="filter-role">
                    <option value="">Semua Akun</option>
                    <option value="pedagang">Pedagang UMKM</option>
                    <option value="admin_desa">Admin Desa</option>
                </select>
            </div>
        </div>
    </div>

    <div class="dw-main-card">
        <table class="dw-table">
            <thead>
                <tr>
                    <th>Akun & Pemilik</th>
                    <th>Tipe</th>
                    <th>Lokasi</th>
                    <th>Terdaftar</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="account-list">
                <tr><td colspan="6" style="text-align:center; padding:50px; color:#94a3b8;">Memuat data akun...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo $nonce; ?>';

    function loadAccounts() {
        const status = $('#filter-status').val();
        const role = $('#filter-role').val();
        
        $('#account-list').html('<tr><td colspan="6" style="text-align:center; padding:50px;">Sedang mengambil data...</td></tr>');

        $.post(ajaxurl, {
            action: 'dw_get_umkm_list',
            status: status,
            role: role,
            nonce: nonce
        }, function(res) {
            if (res.success) {
                let html = '';
                if (res.data.length === 0) {
                    html = '<tr><td colspan="6" style="text-align:center; padding:50px;">Tidak ada pendaftaran ditemukan.</td></tr>';
                } else {
                    res.data.forEach(function(item) {
                        html += `
                        <tr id="row-${item.id}">
                            <td>
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <img src="${item.logo}" style="width:38px; border-radius:8px; border:1px solid #e2e8f0;">
                                    <div>
                                        <strong style="display:block; color:#1e293b;">${item.name}</strong>
                                        <small style="color:#64748b;">${item.owner}</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="role-badge role-${item.role.toLowerCase()}">${item.role}</span></td>
                            <td style="font-size:12px; color:#475569; max-width:200px;">${item.location}</td>
                            <td style="font-size:12px;">${item.date}</td>
                            <td><span class="status-badge status-${item.status}">${item.status.toUpperCase()}</span></td>
                            <td>
                                ${item.status === 'pending' ? `
                                    <div style="display:flex; gap:5px;">
                                        <button class="btn-action btn-approve" data-id="${item.id}" title="Setujui Akun">Setuju</button>
                                        <button class="btn-action btn-reject" data-id="${item.id}" title="Tolak Akun">Tolak</button>
                                    </div>
                                ` : `<button class="btn-action" style="background:#f1f5f9; color:#475569;">Detail</button>`}
                            </td>
                        </tr>`;
                    });
                }
                $('#account-list').html(html);
            }
        });
    }

    // Event Listeners
    $('#filter-status, #filter-role').on('change', loadAccounts);
    loadAccounts();

    // Action Approve
    $(document).on('click', '.btn-approve', function() {
        const id = $(this).data('id');
        if(!confirm('Aktifkan akun ini?')) return;

        $.post(ajaxurl, {
            action: 'dw_process_umkm_verification',
            user_id: id,
            type: 'approve',
            nonce: nonce
        }, function(res) {
            if(res.success) {
                $(`#row-${id}`).css('background', '#f0fdf4').fadeOut(500);
            }
        });
    });

    // Action Reject
    $(document).on('click', '.btn-reject', function() {
        const id = $(this).data('id');
        const reason = prompt('Alasan penolakan:');
        if(!reason) return;

        $.post(ajaxurl, {
            action: 'dw_process_umkm_verification',
            user_id: id,
            type: 'reject',
            reason: reason,
            nonce: nonce
        }, function(res) {
            if(res.success) {
                $(`#row-${id}`).css('background', '#fef2f2').fadeOut(500);
            }
        });
    });
});
</script>