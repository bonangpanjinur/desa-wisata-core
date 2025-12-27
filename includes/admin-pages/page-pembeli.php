<?php
/**
 * File Name:   includes/admin-pages/page-pembeli.php
 * Description: Dashboard Manajemen Pengguna Pembeli (Wisatawan) v3.6.
 * Fitur: Daftar pembeli, pencarian, statistik aktivitas, dan modal detail.
 */

if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('dw_admin_nonce');
?>

<style>
    /* Dasar Layout */
    .b-container { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
    .b-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
    
    /* Stats Cards */
    .b-card-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .b-stat { background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; }
    .b-stat h3 { margin: 0; font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
    .b-stat p { margin: 10px 0 0 0; font-size: 26px; font-weight: 800; color: #1e293b; }
    
    /* Filter Bar */
    .b-filter-bar { display: flex; gap: 15px; margin-bottom: 20px; }
    .b-search-input { flex-grow: 1; padding: 12px 20px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .b-search-input:focus { outline: none; border-color: #2563eb; ring: 2px rgba(37, 99, 235, 0.1); }
    
    /* Table Styling */
    .b-table-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .b-table { width: 100%; border-collapse: collapse; text-align: left; }
    .b-table th { background: #f8fafc; padding: 18px 20px; font-size: 13px; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
    .b-table td { padding: 18px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
    .b-table tr:hover td { background: #fdfdfd; }
    
    /* Badges & Avatars */
    .b-avatar { width: 38px; height: 38px; border-radius: 10px; background: #f1f5f9; object-fit: cover; }
    .b-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .badge-aktif { background: #dcfce7; color: #15803d; }
    
    /* Modal Styling */
    .b-modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
    .b-modal-content { background: #fff; margin: 5% auto; border-radius: 20px; width: 500px; max-width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4); overflow: hidden; animation: bFadeIn 0.3s ease; }
    @keyframes bFadeIn { from {opacity: 0; transform: translateY(-10px);} to {opacity: 1; transform: translateY(0);} }
    .b-modal-header { padding: 20px 25px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: #f9fafb; }
    .b-modal-body { padding: 25px; }
    .b-modal-footer { padding: 15px 25px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; background: #f9fafb; }

    .btn-detail { background: #eff6ff; color: #2563eb; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; transition: 0.2s; }
    .btn-detail:hover { background: #dbeafe; }
</style>

<div class="wrap b-container">
    <div class="b-header">
        <div>
            <h1 style="font-weight: 800; font-size: 26px; color: #1e293b; margin: 0;">Manajemen Pengguna Pembeli</h1>
            <p style="color: #64748b; margin-top: 5px;">Kelola database wisatawan dan pelanggan Desa Wisata Anda.</p>
        </div>
    </div>

    <!-- Statistik Aktivitas -->
    <div class="b-card-stats">
        <div class="b-stat">
            <h3>Total Database Pembeli</h3>
            <p id="total-buyers">0</p>
        </div>
        <div class="b-stat" style="border-top: 4px solid #3b82f6;">
            <h3>Pembeli Aktif (Bulan Ini)</h3>
            <p id="active-month">0</p>
        </div>
        <div class="b-stat" style="border-top: 4px solid #10b981;">
            <h3>Total Order Berhasil</h3>
            <p id="total-orders">0</p>
        </div>
    </div>

    <!-- Filter & Cari -->
    <div class="b-filter-bar">
        <input type="text" id="buyer-search" class="b-search-input" placeholder="Cari nama pembeli atau alamat email...">
        <select class="b-search-input" style="flex-grow: 0; min-width: 180px;">
            <option value="">Semua Lokasi</option>
            <option value="lokal">Dalam Desa</option>
            <option value="luar">Wisatawan Luar</option>
        </select>
    </div>

    <!-- Table Container -->
    <div class="b-table-card">
        <table class="b-table">
            <thead>
                <tr>
                    <th>Informasi Profil</th>
                    <th>Kontak & Email</th>
                    <th>Lokasi Utama</th>
                    <th>Frekuensi Order</th>
                    <th>Tanggal Join</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="buyer-list-body">
                <tr><td colspan="6" style="text-align:center; padding:60px; color:#94a3b8;">Sedang menarik data pembeli...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Detail Pembeli -->
<div id="modal-buyer" class="b-modal">
    <div class="b-modal-content">
        <div class="b-modal-header">
            <h2 id="modal-name" style="margin:0; font-size:18px;">Detail Pengguna</h2>
            <span style="cursor:pointer; font-size:24px;" onclick="jQuery('#modal-buyer').fadeOut(200)">&times;</span>
        </div>
        <div class="b-modal-body">
            <div style="display:flex; gap:20px; align-items:center; margin-bottom:20px;">
                <img id="modal-img" src="" class="b-avatar" style="width:80px; height:80px;">
                <div>
                    <h3 id="modal-display-name" style="margin:0;">-</h3>
                    <p id="modal-email" style="color:#64748b; margin:5px 0;">-</p>
                    <span class="b-badge badge-aktif">Akun Terverifikasi</span>
                </div>
            </div>
            <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div>
                    <label style="font-size:11px; font-weight:bold; color:#94a3b8; text-transform:uppercase;">Total Belanja</label>
                    <p id="modal-total-order" style="font-weight:700; margin:5px 0;">0 Pesanan</p>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:bold; color:#94a3b8; text-transform:uppercase;">Lokasi Terdaftar</label>
                    <p id="modal-location" style="font-weight:700; margin:5px 0;">-</p>
                </div>
            </div>
        </div>
        <div class="b-modal-footer">
            <button class="button" onclick="jQuery('#modal-buyer').fadeOut(200)">Tutup</button>
            <button class="button button-primary" onclick="alert('Fitur Reset Password segera hadir')">Reset Password</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo $nonce; ?>';
    let allData = [];

    function loadBuyers() {
        $.post(ajaxurl, {
            action: 'dw_get_buyer_list',
            nonce: nonce
        }, function(res) {
            if(res.success) {
                allData = res.data;
                renderTable(allData);
                
                // Update Statistik
                $('#total-buyers').text(res.data.length);
                let totalOrders = res.data.reduce((acc, b) => acc + parseInt(b.orders), 0);
                $('#total-orders').text(totalOrders);
                $('#active-month').text(Math.round(res.data.length * 0.7)); // Simulasi data aktif
            }
        });
    }

    function renderTable(data) {
        let html = '';
        if(data.length === 0) {
            html = '<tr><td colspan="6" style="text-align:center; padding:50px;">Data pembeli tidak ditemukan.</td></tr>';
        } else {
            data.forEach((b, index) => {
                html += `
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <img src="${b.logo}" class="b-avatar">
                            <strong>${b.name}</strong>
                        </div>
                    </td>
                    <td style="color:#64748b; font-size:13px;">${b.email}</td>
                    <td style="font-size:13px;">${b.location || '-'}</td>
                    <td><span style="font-weight:bold; color:#2563eb;">${b.orders} Pesanan</span></td>
                    <td style="font-size:13px; color:#94a3b8;">${b.date}</td>
                    <td><button class="btn-detail" data-index="${index}">Lihat Detail</button></td>
                </tr>`;
            });
        }
        $('#buyer-list-body').html(html);
    }

    // Search Logic
    $('#buyer-search').on('keyup', function() {
        let val = $(this).val().toLowerCase();
        let filtered = allData.filter(b => 
            b.name.toLowerCase().includes(val) || 
            b.email.toLowerCase().includes(val)
        );
        renderTable(filtered);
    });

    // Detail Modal Logic
    $(document).on('click', '.btn-detail', function() {
        let idx = $(this).data('index');
        let b = allData[idx];
        
        $('#modal-name').text('Detail: ' + b.name);
        $('#modal-display-name').text(b.name);
        $('#modal-email').text(b.email);
        $('#modal-total-order').text(b.orders + ' Pesanan Selesai');
        $('#modal-location').text(b.location || 'Lokasi tidak diset');
        $('#modal-img').attr('src', b.logo);
        
        $('#modal-buyer').fadeIn(200);
    });

    loadBuyers();
});
</script>