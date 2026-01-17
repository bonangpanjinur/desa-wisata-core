<?php
/**
 * View: List Data Desa
 * Path: includes/views/admin/html-desa-list.php
 */

if (!defined('ABSPATH')) exit;

// Pastikan variabel utama ada (Fallback)
$active_tab = isset($active_tab) ? $active_tab : 'data_desa';

// -----------------------------------------------------------------------------
// TAB 1: DATA DESA
// -----------------------------------------------------------------------------
if ($active_tab == 'data_desa'): 
    
    // Pencarian
    $search_q = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $limit = 10;
    $offset = ($paged - 1) * $limit;

    global $wpdb;
    $table_desa = $wpdb->prefix . 'dw_desa';
    $table_users = $wpdb->users;

    // Query Data
    $sql = "SELECT d.*, u.display_name as admin_name 
            FROM {$table_desa} d 
            LEFT JOIN {$table_users} u ON d.id_user_desa = u.ID 
            WHERE 1=1 ";
    
    $args = [];
    if($search_q) {
        $sql .= " AND (d.nama_desa LIKE %s OR d.slug_desa LIKE %s)";
        $args[] = '%' . $wpdb->esc_like($search_q) . '%';
        $args[] = '%' . $wpdb->esc_like($search_q) . '%';
    }
    
    $sql .= " ORDER BY d.created_at DESC LIMIT %d, %d";
    $args[] = $offset;
    $args[] = $limit;
    
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
    
    // Hitung Total untuk Pagination
    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
    $total_pages = ceil($total_items / $limit);
    ?>
    
    <!-- STATS KEUANGAN -->
    <div class="dw-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div class="dw-stat-card" style="background: #fff; padding: 15px; border-left: 4px solid #2271b1; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; color: #666;">Total Desa</div>
            <div style="font-size: 20px; font-weight: bold;"><?php echo esc_html($total_desa ?? 0); ?></div>
        </div>
        <div class="dw-stat-card" style="background: #fff; padding: 15px; border-left: 4px solid #00a32a; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; color: #666;">Total Pendapatan</div>
            <div style="font-size: 20px; font-weight: bold; color: #00a32a;">
                Rp <?php echo number_format($total_pendapatan_all ?? 0, 0, ',', '.'); ?>
            </div>
        </div>
        <div class="dw-stat-card" style="background: #fff; padding: 15px; border-left: 4px solid #dba617; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; color: #666;">Saldo Mengendap</div>
            <div style="font-size: 20px; font-weight: bold; color: #dba617;">
                Rp <?php echo number_format($total_saldo_komisi_all ?? 0, 0, ',', '.'); ?>
            </div>
        </div>
    </div>

    <!-- TABEL DATA -->
    <div class="dw-card" style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <div class="dw-card-header" style="padding: 10px 15px; border-bottom: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Daftar Desa</h3>
            <form method="get">
                <input type="hidden" name="page" value="dw-desa">
                <input type="text" name="s" value="<?php echo esc_attr($search_q); ?>" placeholder="Cari desa...">
                <button class="button">Cari</button>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th width="60">Logo</th>
                    <th>Nama Desa</th>
                    <th>Admin</th>
                    <th>Saldo Wallet</th>
                    <th>Pendapatan</th>
                    <th>Status</th>
                    <th width="100">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if($rows): foreach($rows as $r): ?>
                <tr>
                    <td>#<?php echo $r->id; ?></td>
                    <td><img src="<?php echo $r->foto ? esc_url($r->foto) : 'https://via.placeholder.com/40'; ?>" width="40" height="40" style="border-radius:4px;"></td>
                    <td>
                        <strong><?php echo esc_html($r->nama_desa); ?></strong><br>
                        <small style="color:#666;"><?php echo esc_html($r->kabupaten); ?></small>
                    </td>
                    <td><?php echo esc_html($r->admin_name); ?></td>
                    
                    <!-- Wallet & Pendapatan -->
                    <td>
                        <?php 
                        $saldo = 0;
                        if (class_exists('DW_Wallet')) {
                            $saldo = DW_Wallet::get_balance($r->id_user_desa);
                        } else {
                            $saldo = isset($r->saldo_komisi) ? $r->saldo_komisi : 0;
                        }
                        ?>
                        <span style="color:#2271b1; font-weight:bold;">Rp <?php echo number_format($saldo, 0, ',', '.'); ?></span>
                    </td>
                    <td>
                        <span style="color:#555;">Rp <?php echo number_format($r->total_pendapatan, 0, ',', '.'); ?></span>
                    </td>

                    <td>
                        <?php if($r->status == 'aktif'): ?>
                            <span style="background:#d1e7dd; color:#0f5132; padding:2px 6px; border-radius:4px; font-weight:bold; font-size:11px;">Aktif</span>
                        <?php else: ?>
                            <span style="background:#f8d7da; color:#721c24; padding:2px 6px; border-radius:4px; font-weight:bold; font-size:11px;">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=dw-desa&tab=data_desa&view=edit&id=<?php echo $r->id; ?>" class="button button-small">Edit</a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8" style="text-align:center;">Belum ada data desa.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo paginate_links(['total' => $total_pages, 'current' => $paged]); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php 
// -----------------------------------------------------------------------------
// TAB 2: VERIFIKASI PREMIUM
// -----------------------------------------------------------------------------
elseif ($active_tab == 'verifikasi'): 
    global $wpdb;
    $pending_verif = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dw_desa WHERE status_akses_verifikasi = 'pending'");
?>
    <div class="card">
        <h3>Verifikasi Premium</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Nama Desa</th><th>Bukti</th><th>Tanggal</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php if($pending_verif): foreach($pending_verif as $p): ?>
                <tr>
                    <td><?php echo esc_html($p->nama_desa); ?></td>
                    <td><a href="<?php echo esc_url($p->bukti_bayar_akses); ?>" target="_blank">Lihat Bukti</a></td>
                    <td><?php echo $p->updated_at; ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('dw_verify_desa'); ?>
                            <input type="hidden" name="action_verify_desa" value="1">
                            <input type="hidden" name="desa_id" value="<?php echo $p->id; ?>">
                            <button type="submit" name="decision" value="approve" class="button button-primary button-small">Terima</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="4">Tidak ada permintaan verifikasi.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php 
// -----------------------------------------------------------------------------
// TAB 3: PENGATURAN
// -----------------------------------------------------------------------------
elseif ($active_tab == 'pengaturan'): 
?>
    <div class="card" style="max-width: 500px;">
        <h3>Pengaturan Harga Premium</h3>
        <form method="post">
            <?php wp_nonce_field('dw_desa_settings_save'); ?>
            <input type="hidden" name="action_save_settings" value="1">
            <p>
                <label>Biaya Upgrade (Rp)</label><br>
                <input type="number" name="harga_premium_desa" value="<?php echo esc_attr($harga); ?>" class="regular-text">
            </p>
            <button type="submit" class="button button-primary">Simpan</button>
        </form>
    </div>
<?php endif; ?>