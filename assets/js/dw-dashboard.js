/**
 * File: assets/js/dw-dashboard.js
 * Description: Menangani logika interaktif dashboard frontend (Stats Loading, Table Refresh).
 * Version: 2.8.0
 */

jQuery(document).ready(function($) {
    
    // Cek apakah kita berada di halaman dashboard plugin
    if ($('.dw-frontend-wrapper').length === 0) return;

    /**
     * Fungsi 1: Load Statistik Dashboard
     */
    function loadDashboardStats() {
        // Tampilkan skeleton pada nilai
        $('.dw-stat-value').addClass('dw-skeleton');

        $.ajax({
            url: dw_dashboard_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'dw_get_dashboard_stats',
                nonce: dw_dashboard_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update UI dengan data asli
                    $('#stat-sales').text(response.data.sales);
                    $('#stat-orders').text(response.data.orders);
                    $('#stat-products').text(response.data.products);
                }
            },
            complete: function() {
                // Hapus skeleton class
                $('.dw-stat-value').removeClass('dw-skeleton');
            }
        });
    }

    /**
     * Fungsi 2: Load Data Transaksi Terbaru
     */
    function loadRecentTransactions() {
        var tableBody = $('#dw-transaction-table tbody');
        
        // Jangan timpa jika tabel kosong/pertama load, biarkan skeleton HTML bawaan
        if (tableBody.find('.no-data').length > 0) return; 

        $.ajax({
            url: dw_dashboard_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'dw_get_recent_transactions',
                nonce: dw_dashboard_vars.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    tableBody.html(response.data.html);
                } else if (response.success && !response.data.html) {
                    tableBody.html('<tr><td colspan="5" class="no-data" style="text-align:center; padding: 20px;">Belum ada transaksi.</td></tr>');
                }
            }
        });
    }

    // --- Inisialisasi ---
    
    // Load data pertama kali
    loadDashboardStats();
    loadRecentTransactions();

    // Opsional: Auto-refresh data setiap 60 detik
    setInterval(function() {
        // Silent refresh (tanpa loading state yang mengganggu)
        $.ajax({
            url: dw_dashboard_vars.ajax_url,
            type: 'POST',
            data: { action: 'dw_get_dashboard_stats', nonce: dw_dashboard_vars.nonce },
            success: function(res) { 
                if(res.success) {
                    $('#stat-sales').text(res.data.sales);
                    $('#stat-orders').text(res.data.orders);
                    $('#stat-products').text(res.data.products);
                }
            }
        });
    }, 60000);

    // Expose fungsi refresh ke global agar bisa dipanggil oleh Notifikasi Poller
    window.dwRefreshOjekList = function() {
        loadDashboardStats(); // Refresh stats juga
        loadRecentTransactions(); // Refresh tabel
        console.log('Dashboard refreshed by Notification Poller');
    };

});