<?php
/**
 * View: List Desa
 * Path: includes/views/admin/html-desa-list.php
 * Description: Template HTML untuk statistik, tabel desa, verifikasi, dan pengaturan.
 * Version: 2.5.0
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- TAB 1: DATA DESA -->
<?php if($active_tab == 'data_desa'): ?>
    
    <!-- STATS GRID -->
    <div class="dw-stats-grid">
        <div class="dw-stat-card">
            <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-admin-site-alt3"></span></div>
            <div class="dw-stat-info">
                <span class="dw-stat-label">Total Desa</span>
                <h4 class="dw-stat-value"><?php echo esc_html($total_desa); ?></h4>
            </div>
        </div>
        <div class="dw-stat-card">
            <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-awards"></span></div>
            <div class="dw-stat-info">
                <span class="dw-stat-label">Aktif</span>
                <h4 class="dw-stat-value"><?php echo esc_html($active_count); ?></h4>
            </div>
        </div>
        <div class="dw-stat-card">
            <div class="dw-stat-icon-wrapper bg-purple"><span class="dashicons dashicons-chart-area"></span></div>
            <div class="dw-stat-info">
                <span class="dw-stat-label">Total Pendapatan</span>
                <h4 class="dw-stat-value">Rp <?php echo esc_html(number_format($total_pendapatan_all, 0, ',', '.')); ?></h4>
            </div>
        </div>
        <div class="dw-stat-card">
            <div class="dw-stat-icon-wrapper bg-orange"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="dw-stat-info">
                <span class="dw-stat-label">Saldo Mengendap</span>
                <h4 class="dw-stat-value">Rp <?php echo esc_html(number_format($total_saldo_komisi_all, 0, ',', '.')); ?></h4>
            </div>
        </div>
    </div>

    <div class="dw-card">
        <div class="dw-card-header">
            <h3 class="card-heading">Daftar Desa Wisata</h3>
            <form method="get" style="display:flex; gap:10px;">
                <input type="hidden" name="page" value="dw-desa">
                <input type="text" name="s" placeholder="Cari nama desa..." class="dw-input" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" style="width: 200px;">
                <button class="dw-button dw-button-secondary">Cari</button>
            </form>
        </div>
        <div class="dw-card-body" style="padding:0;">
            <?php
            $search_q = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $limit = 10;
            $offset = ($paged - 1) * $limit;

            global $wpdb;
            $table_desa = $wpdb->prefix . 'dw_desa'; // Ensure var available
            $table_users = $wpdb->users;

            // SECURE QUERY with Prepared Statement
            $sql = "SELECT d.*, u.display_name as admin_name FROM {$table_desa} d LEFT JOIN {$table_users} u ON d.id_user_desa = u.ID WHERE 1=1 ";
            $prepare_args = [];

            if($search_q) {
                $sql .= " AND (d.nama_desa LIKE %s)";
                $prepare_args[] = '%' . $wpdb->esc_like($search_q) . '%';
            }
            $sql .= " ORDER BY d.created_at DESC LIMIT %d, %d";
            $prepare_args[] = $offset;
            $prepare_args[] = $limit;
            
            $rows = $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
            
            // Count query
            $count_sql = "SELECT COUNT(id) FROM $table_desa";
            $total_items = $wpdb->get_var($count_sql);
            $total_pages = ceil($total_items / $limit);
            ?>

            <div class="dw-table-wrapper" style="border:none; border-radius:0;">
                <table class="dw-modern-table">
                    <thead>
                        <tr>
                            <th width="60">Logo</th>
                            <th>Nama Desa</th>
                            <th>Lokasi</th>
                            <th>Admin</th>
                            <th>Keuangan</th>
                            <th>Status</th>
                            <th>Premium</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($rows): foreach($rows as $r): ?>
                        <tr>
                            <td><img src="<?php echo $r->foto ? esc_url($r->foto) : 'https://via.placeholder.com/60'; ?>" style="width:40px; height:40px; border-radius:6px; object-fit:cover;"></td>
                            <td>
                                <strong><a href="?page=dw-desa&tab=data_desa&view=edit&id=<?php echo esc_attr($r->id); ?>" class="dw-btn-link"><?php echo esc_html($r->nama_desa); ?></a></strong>
                                <div class="dw-text-muted" style="font-size:12px; color:#646970;">Ref: <?php echo esc_html($r->kode_referral); ?></div>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:500;"><?php echo esc_html($r->kecamatan); ?></div>
                                <div style="font-size:11px; color:#64748b;"><?php echo esc_html($r->kabupaten); ?></div>
                            </td>
                            <td><?php echo esc_html($r->admin_name ?: '-'); ?></td>
                            <td>
                                <div style="font-size:11px;">Total: <strong>Rp <?php echo number_format($r->total_pendapatan, 0, ',', '.'); ?></strong></div>
                                <div style="font-size:11px; color:#d63638;">Sisa: <strong>Rp <?php echo number_format($r->saldo_komisi, 0, ',', '.'); ?></strong></div>
                            </td>
                            <td>
                                <?php if($r->status == 'aktif'): ?><span class="dw-badge status-success">Aktif</span>
                                <?php else: ?><span class="dw-badge status-warning">Pending</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if($r->status_akses_verifikasi == 'active'): ?><span class="dw-badge status-success">Ya</span>
                                <?php elseif($r->status_akses_verifikasi == 'pending'): ?><span class="dw-badge status-warning">Pending</span>
                                <?php else: ?><span class="dw-badge status-neutral">Tidak</span><?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex; gap:6px; justify-content:flex-end;">
                                    <a href="?page=dw-desa&tab=data_desa&view=edit&id=<?php echo esc_attr($r->id); ?>" class="dw-button dw-button-secondary" style="padding: 4px 10px; font-size: 12px; min-height:unset;">Edit</a>
                                    <form method="post" style="display:inline-block;" onsubmit="return confirm('Yakin hapus desa ini? Data pedagang harus kosong.');">
                                        <?php wp_nonce_field('dw_desa_action'); ?>
                                        <input type="hidden" name="action_desa" value="delete">
                                        <input type="hidden" name="desa_id" value="<?php echo esc_attr($r->id); ?>">
                                        <button class="dw-button" style="padding: 4px 8px; font-size: 12px; color:#d63638; border:none; background:none; cursor:pointer; min-height:unset;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px;">Belum ada data desa.</td></tr>
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

<?php elseif($active_tab == 'verifikasi'): 
    global $wpdb;
    $table_desa = $wpdb->prefix . 'dw_desa';
    $pending_verif = $wpdb->get_results("SELECT * FROM $table_desa WHERE status_akses_verifikasi = 'pending' ORDER BY updated_at ASC");
?>
    <!-- TAB 2: VERIFIKASI -->
    <div class="dw-card">
        <div class="dw-card-header"><h3 class="card-heading">Antrean Verifikasi Upgrade Premium</h3></div>
        <div class="dw-card-body">
            <?php if(empty($pending_verif)): ?>
                <div style="text-align:center; padding:40px; color:#64748b;">
                    <span class="dashicons dashicons-yes-alt" style="font-size:40px; width:40px; height:40px; color:var(--dw-success); margin-bottom:10px;"></span>
                    <p>Tidak ada permintaan verifikasi saat ini.</p>
                </div>
            <?php else: foreach($pending_verif as $p): ?>
                <div style="border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px; display:flex; gap:24px; align-items:flex-start; background:#fff;">
                    <div style="width:120px; height:120px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <?php if($p->bukti_bayar_akses): ?>
                                <a href="<?php echo esc_url($p->bukti_bayar_akses); ?>" target="_blank"><img src="<?php echo esc_url($p->bukti_bayar_akses); ?>" style="width:100%; height:100%; object-fit:cover;"></a>
                            <?php else: ?><span class="dashicons dashicons-format-image" style="color:#cbd5e1; font-size:32px;"></span><?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <div>
                                <h4 style="margin:0 0 4px 0; font-size:16px; color:var(--dw-text-dark);"><?php echo esc_html($p->nama_desa); ?></h4>
                                <span class="dw-badge status-warning">Menunggu Konfirmasi</span>
                            </div>
                            <div style="text-align:right;">
                                <small style="color:#64748b;">Diajukan pada:</small><br>
                                <strong style="font-size:13px;"><?php echo date('d M Y, H:i', strtotime($p->updated_at)); ?></strong>
                            </div>
                        </div>
                        <p style="margin:0 0 16px; color:#64748b; font-size:13px; line-height:1.5;">
                            <strong>Lokasi:</strong> <?php echo esc_html($p->kecamatan.', '.$p->kabupaten); ?>
                        </p>
                        
                        <div style="display:flex; gap:12px; align-items:center;">
                            <form method="post">
                                <?php wp_nonce_field('dw_verify_desa'); ?>
                                <input type="hidden" name="action_verify_desa" value="1">
                                <input type="hidden" name="desa_id" value="<?php echo esc_attr($p->id); ?>">
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="dw-button dw-button-primary"><span class="dashicons dashicons-yes" style="margin-right:5px;"></span> Setujui Premium</button>
                            </form>
                            <button type="button" class="dw-button dw-button-secondary" onclick="jQuery('#reject-box-<?php echo esc_attr($p->id); ?>').toggle();" style="color:#ef4444; border-color:#fecaca; background:#fef2f2;">Tolak</button>
                        </div>
                        
                        <div id="reject-box-<?php echo esc_attr($p->id); ?>" style="display:none; margin-top:15px; background:#fff1f2; padding:15px; border-radius:8px; border:1px solid #fecaca;">
                            <form method="post" style="display:flex; gap:10px;">
                                <?php wp_nonce_field('dw_verify_desa'); ?>
                                <input type="hidden" name="action_verify_desa" value="1">
                                <input type="hidden" name="desa_id" value="<?php echo esc_attr($p->id); ?>">
                                <input type="hidden" name="decision" value="reject">
                                <input type="text" name="alasan_penolakan" class="dw-input" placeholder="Tulis alasan penolakan..." required style="padding:8px;">
                                <button type="submit" class="dw-button" style="background:#ef4444; color:#fff; border:none; padding:0 20px;">Kirim Penolakan</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

<?php elseif($active_tab == 'pengaturan'): ?>
    <!-- TAB 3: PENGATURAN -->
    <div class="dw-card" style="max-width:500px;">
        <div class="dw-card-header"><h3 class="card-heading">Pengaturan Harga Premium</h3></div>
        <div class="dw-card-body">
            <form method="post">
                <?php wp_nonce_field('dw_desa_settings_save'); ?>
                <input type="hidden" name="action_save_settings" value="1">
                <div class="dw-form-group">
                    <label class="dw-label">Biaya Upgrade (Rp)</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-weight:bold; color:#64748b; font-size:18px;">Rp</span>
                        <input type="number" name="harga_premium_desa" class="dw-input" value="<?php echo esc_attr($harga); ?>" style="font-size:18px; font-weight:bold; padding:12px;">
                    </div>
                    <p class="dw-help-text">Biaya yang harus dibayar admin desa untuk mendapatkan fitur premium (Verifikasi).</p>
                </div>
                <button type="submit" class="dw-button dw-button-primary">Simpan Pengaturan</button>
            </form>
        </div>
    </div>
<?php endif; ?>