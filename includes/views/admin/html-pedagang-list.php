<?php
/**
 * View: List Pedagang
 * Path: includes/views/admin/html-pedagang-list.php
 * Description: Template HTML untuk menampilkan statistik dan tabel data pedagang.
 * Version: 2.5.0
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- STATS GRID -->
<div class="dw-stats-grid">
    <div class="dw-stat-card">
        <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-groups"></span></div>
        <h4 class="dw-stat-value"><?php echo esc_html(number_format($total_pedagang)); ?></h4>
        <span class="dw-stat-label">Total Pedagang</span>
    </div>
    <div class="dw-stat-card">
        <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-admin-home"></span></div>
        <h4 class="dw-stat-value"><?php echo esc_html(number_format($with_desa_count)); ?></h4>
        <span class="dw-stat-label">Mitra Desa</span>
    </div>
    <div class="dw-stat-card">
        <div class="dw-stat-icon-wrapper bg-orange"><span class="dashicons dashicons-admin-users"></span></div>
        <h4 class="dw-stat-value"><?php echo esc_html(number_format($independent_count)); ?></h4>
        <span class="dw-stat-label">Independen</span>
    </div>
    <div class="dw-stat-card">
        <div class="dw-stat-icon-wrapper bg-purple"><span class="dashicons dashicons-chart-pie"></span></div>
        <h4 class="dw-stat-value"><?php echo esc_html($total_transaksi); ?></h4>
        <span class="dw-stat-label">Total Kuota Transaksi</span>
    </div>
</div>

<!-- VIEW: LIST TABLE -->
<div class="dw-card">
    <div class="dw-card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-heading"><?php echo esc_html__('Data Pedagang', 'desa-wisata-core'); ?></h3>
        <form id="dw-pedagang-search" method="get" style="display:flex; gap:10px;">
            <input type="hidden" name="page" value="dw-pedagang" />
            <input type="text" name="s" class="dw-input" placeholder="Cari pedagang..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" style="width:200px;">
            <button class="dw-button dw-button-secondary"><?php echo esc_html__('Cari', 'desa-wisata-core'); ?></button>
        </form>
    </div>
    <div class="dw-card-body" style="padding:0;">
        <div class="dw-table-wrapper" style="border:none; border-radius:0;">
            <table class="dw-modern-table" id="table-pedagang">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Nama Toko</th>
                        <th>Pemilik</th>
                        <th>Wilayah</th>
                        <th>Desa Asal</th>
                        <th>Status Akun</th>
                        <th>Verifikasi</th>
                        <th style="width:150px; text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $search_q = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
                    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                    $limit = 10;
                    $offset = ($paged - 1) * $limit;
                    
                    global $wpdb;
                    // QUERY AMAN MENGGUNAKAN PREPARE
                    $sql_list = "SELECT p.*, d.nama_desa 
                                    FROM $table_name p 
                                    LEFT JOIN $table_desa d ON p.api_kelurahan_id = d.api_kelurahan_id 
                                    WHERE 1=1";
                    
                    if($search_q) {
                        $like_query = '%' . $wpdb->esc_like($search_q) . '%';
                        $sql_list .= $wpdb->prepare(" AND (p.nama_toko LIKE %s OR p.nama_pemilik LIKE %s)", $like_query, $like_query);
                    }
                    $sql_list .= $wpdb->prepare(" ORDER BY p.id DESC LIMIT %d, %d", $offset, $limit);
                    
                    $rows = $wpdb->get_results($sql_list);
                    
                    // COUNT QUERY
                    $total_items_sql = "SELECT COUNT(*) FROM $table_name p WHERE 1=1";
                    if($search_q) {
                        $like_query = '%' . $wpdb->esc_like($search_q) . '%';
                        $total_items_sql .= $wpdb->prepare(" AND (p.nama_toko LIKE %s OR p.nama_pemilik LIKE %s)", $like_query, $like_query);
                    }
                    $total_items = $wpdb->get_var($total_items_sql);
                    $total_pages = ceil($total_items / $limit);
                    
                    if ($rows): foreach($rows as $r): 
                        $user_info = get_userdata($r->id_user);
                        $edit_url = "?page=dw-pedagang&action=edit&id={$r->id}";
                    ?>
                        <tr>
                            <td><span style="color:#94a3b8; font-weight:600;">#<?php echo esc_html($r->id); ?></span></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <?php if($r->foto_profil): ?>
                                        <img src="<?php echo esc_url($r->foto_profil); ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                                    <?php else: ?>
                                        <div style="width:32px; height:32px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center;">
                                            <span class="dashicons dashicons-store" style="font-size:16px; color:#94a3b8;"></span>
                                        </div>
                                    <?php endif; ?>
                                    <strong style="color:var(--dw-text-dark); font-size:14px;">
                                        <a href="<?php echo esc_url($edit_url); ?>" style="color:inherit; text-decoration:none;"><?php echo esc_html($r->nama_toko); ?></a>
                                    </strong>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:500; color:var(--dw-text-dark);"><?php echo $user_info ? esc_html($user_info->display_name) : 'N/A'; ?></div>
                                <div style="font-size:12px; color:#646970;"><?php echo $user_info ? esc_html($user_info->user_email) : ''; ?></div>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:500;"><?php echo esc_html($r->kecamatan_nama); ?></div>
                                <div style="font-size:11px; color:#64748b;"><?php echo esc_html($r->kabupaten_nama); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($r->nama_desa)): ?>
                                    <span class="dw-badge status-info" style="font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                                        <span class="dashicons dashicons-admin-home" style="font-size:14px;"></span> <?php echo esc_html($r->nama_desa); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="dw-badge status-neutral">Independent</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $status_class = 'neutral';
                                if($r->status_akun === 'aktif') $status_class = 'success';
                                if($r->status_akun === 'nonaktif') $status_class = 'danger';
                                if($r->status_akun === 'suspend') $status_class = 'warning';
                                ?>
                                <span class="dw-badge status-<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($r->status_akun)); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $verif_class = 'warning';
                                if($r->status_pendaftaran === 'disetujui') $verif_class = 'success';
                                if($r->status_pendaftaran === 'ditolak') $verif_class = 'danger';
                                ?>
                                <span class="dw-badge status-<?php echo esc_attr($verif_class); ?>">
                                    <?php echo esc_html(ucfirst($r->status_pendaftaran)); ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex; gap:6px; justify-content:flex-end;">
                                    <a href="<?php echo esc_url($edit_url); ?>" class="dw-button dw-button-secondary" style="padding: 6px 10px;" title="Edit Data">
                                        <span class="dashicons dashicons-edit" style="margin:0;"></span>
                                    </a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus pedagang ini?');">
                                        <?php wp_nonce_field('dw_pedagang_action'); ?>
                                        <input type="hidden" name="action_pedagang" value="delete">
                                        <input type="hidden" name="pedagang_id" value="<?php echo esc_attr($r->id); ?>">
                                        <button type="submit" class="dw-button" style="padding: 6px 10px; color:#ef4444; border:1px solid #fee2e2; background:#fef2f2; cursor:pointer;" title="Hapus">
                                            <span class="dashicons dashicons-trash" style="margin:0;"></span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:50px; color:#94a3b8;"><?php echo esc_html__('Belum ada data pedagang.', 'desa-wisata-core'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if($total_pages > 1): ?>
                <div style="padding:15px; text-align:right; border-top:1px solid #e2e8f0;">
                    <?php echo paginate_links(['total' => $total_pages, 'current' => $paged]); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($){
    $("#dw-search-pedagang").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#table-pedagang tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>