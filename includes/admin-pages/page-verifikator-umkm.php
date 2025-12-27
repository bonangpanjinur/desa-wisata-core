<?php
if (!defined('ABSPATH')) exit;

/**
 * UI Verifikator UMKM yang Disempurnakan
 */
?>

<style>
    /* Modern Dashboard Styling */
    .dw-admin-wrap {
        margin: 20px 20px 0 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    /* Stats Cards */
    .dw-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .dw-stat-card {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border-left: 5px solid #ddd;
        transition: transform 0.2s;
    }

    .dw-stat-card:hover { transform: translateY(-3px); }
    .dw-stat-card.pending { border-left-color: #f59e0b; }
    .dw-stat-card.approved { border-left-color: #10b981; }
    .dw-stat-card.rejected { border-left-color: #ef4444; }

    .dw-stat-label { color: #6b7280; font-size: 0.875rem; font-weight: 500; text-transform: uppercase; }
    .dw-stat-value { color: #111827; font-size: 1.875rem; font-weight: 700; margin-top: 5px; }

    /* Modern Table Container */
    .dw-table-container {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .dw-custom-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .dw-custom-table th {
        background: #f9fafb;
        padding: 15px 20px;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
    }

    .dw-custom-table td {
        padding: 15px 20px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    /* Status Badges */
    .dw-badge {
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
    }

    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-approved { background: #d1fae5; color: #065f46; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }

    /* Action Buttons */
    .dw-btn {
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        font-weight: 500;
    }

    .btn-view { background: #eff6ff; color: #2563eb; }
    .btn-view:hover { background: #dbeafe; }
    .btn-approve { background: #10b981; color: white; }
    .btn-approve:hover { background: #059669; }
    .btn-reject { background: #ef4444; color: white; }
    .btn-reject:hover { background: #dc2626; }

    /* Search Bar Overlay */
    .dw-filter-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .dw-search-input {
        padding: 10px 15px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        width: 300px;
    }

    /* UMKM Profile Mini */
    .umkm-profile {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .umkm-logo {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #f3f4f6;
        object-fit: cover;
    }

    .umkm-info-main { font-weight: 600; color: #111827; display: block; }
    .umkm-info-sub { color: #6b7280; font-size: 0.75rem; }
</style>

<div class="wrap dw-admin-wrap">
    <h1 class="wp-heading-inline">Verifikasi UMKM Desa</h1>
    <hr class="wp-header-end">

    <!-- Summary Stats -->
    <div class="dw-stats-grid">
        <div class="dw-stat-card pending">
            <div class="dw-stat-label">Menunggu Verifikasi</div>
            <div class="dw-stat-value">12</div>
        </div>
        <div class="dw-stat-card approved">
            <div class="dw-stat-label">UMKM Terverifikasi</div>
            <div class="dw-stat-value">48</div>
        </div>
        <div class="dw-stat-card rejected">
            <div class="dw-stat-label">Ditolak</div>
            <div class="dw-stat-value">3</div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="dw-filter-bar">
        <input type="text" class="dw-search-input" placeholder="Cari nama UMKM atau pemilik...">
        <div class="dw-actions">
            <select class="dw-search-input" style="width: auto;">
                <option>Semua Kategori</option>
                <option>Kuliner</option>
                <option>Kerajinan</option>
                <option>Jasa</option>
            </select>
        </div>
    </div>

    <!-- Modern List Table -->
    <div class="dw-table-container">
        <table class="dw-custom-table">
            <thead>
                <tr>
                    <th>UMKM & Pemilik</th>
                    <th>Kategori</th>
                    <th>Tanggal Daftar</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <!-- Contoh Item UMKM -->
                <tr>
                    <td>
                        <div class="umkm-profile">
                            <img src="https://ui-avatars.com/api/?name=Warung+Sate&background=random" class="umkm-logo" alt="Logo">
                            <div>
                                <span class="umkm-info-main">Warung Sate Barokah</span>
                                <span class="umkm-info-sub">Oleh: Ahmad Subarjo</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="umkm-info-main">Kuliner</span></td>
                    <td>24 Des 2025</td>
                    <td><span class="dw-badge badge-pending">Menunggu</span></td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <a href="#" class="dw-btn btn-view" title="Detail">
                                <span class="dashicons dashicons-visibility"></span> Periksa
                            </a>
                            <button class="dw-btn btn-approve" title="Terima">
                                <span class="dashicons dashicons-yes"></span>
                            </button>
                            <button class="dw-btn btn-reject" title="Tolak">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td>
                        <div class="umkm-profile">
                            <img src="https://ui-avatars.com/api/?name=Anyaman+Bambu&background=random" class="umkm-logo" alt="Logo">
                            <div>
                                <span class="umkm-info-main">Kerajinan Anyaman Bambu</span>
                                <span class="umkm-info-sub">Oleh: Siti Aminah</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="umkm-info-main">Kerajinan</span></td>
                    <td>22 Des 2025</td>
                    <td><span class="dw-badge badge-approved">Terverifikasi</span></td>
                    <td>
                        <a href="#" class="dw-btn btn-view">
                            <span class="dashicons dashicons-visibility"></span> Detail
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination Placeholder -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">2 item</span>
            <span class="pagination-links">
                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                <span class="paging-input">1 dari <span class="total-pages">1</span></span>
                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
            </span>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // Logika interaksi sederhana (contoh)
        $('.btn-approve').on('click', function() {
            if(confirm('Apakah Anda yakin ingin memverifikasi UMKM ini?')) {
                // Tambahkan AJAX call di sini
                $(this).closest('tr').fadeOut();
            }
        });

        $('.btn-reject').on('click', function() {
            let alasan = prompt('Alasan penolakan:');
            if(alasan) {
                // Tambahkan AJAX call di sini
                $(this).closest('tr').fadeOut();
            }
        });
    });
</script>