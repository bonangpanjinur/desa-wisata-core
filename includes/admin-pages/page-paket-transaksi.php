<?php
/**
 * File Name:   page-paket-transaksi.php
 * Description: CRUD Paket dengan Tampilan Pricing Cards & Fungsi Hapus Lengkap.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler Form & Aksi (Simpan & Hapus)
 */
function dw_paket_form_handler() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_paket_transaksi';

    // 1. Handle DELETE (via GET Link)
    if (isset($_GET['action']) && $_GET['action'] === 'delete_paket' && isset($_GET['id'])) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_paket_nonce')) {
            wp_die('Security Check Failed');
        }
        
        $wpdb->delete($table_name, ['id' => intval($_GET['id'])]);
        
        add_settings_error('dw_paket_notices', 'deleted', 'Paket berhasil dihapus.', 'success');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=dw-paket-transaksi')); 
        exit;
    }

    // 2. Handle SAVE (via POST Form)
    if (isset($_POST['dw_submit_paket'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_save_paket_nonce')) {
            wp_die('Security check failed.');
        }
        
        $data = [
            'nama_paket' => sanitize_text_field($_POST['nama_paket']),
            'deskripsi' => sanitize_textarea_field($_POST['deskripsi']),
            'harga' => floatval($_POST['harga']),
            'jumlah_transaksi' => intval($_POST['jumlah_transaksi']),
            'persentase_komisi_desa' => floatval($_POST['persentase_komisi_desa']),
            'status' => sanitize_key($_POST['status']),
        ];
        
        if (isset($_POST['id']) && $_POST['id'] > 0) {
            $wpdb->update($table_name, $data, ['id'=>$_POST['id']]);
            add_settings_error('dw_paket_notices', 'upd', 'Paket berhasil diperbarui.', 'success');
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table_name, $data);
            add_settings_error('dw_paket_notices', 'add', 'Paket baru berhasil dibuat.', 'success');
        }
        
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=dw-paket-transaksi')); 
        exit;
    }
}
add_action('admin_init', 'dw_paket_form_handler');

// Render
function dw_paket_transaksi_page_render() {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'add' || ($action === 'edit_paket' && isset($_GET['id']))) {
        dw_paket_form_render(isset($_GET['id']) ? absint($_GET['id']) : 0);
        return;
    }

    global $wpdb;
    $packages = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dw_paket_transaksi ORDER BY harga ASC");
    $e = get_transient('settings_errors'); if($e){ settings_errors('dw_paket_notices'); delete_transient('settings_errors'); }
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Paket Berlangganan</h1>
        <a href="?page=dw-paket-transaksi&action=add" class="page-title-action">Buat Paket Baru</a>
        <hr class="wp-header-end">
        
        <style>
            .dw-pricing-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-top: 30px; }
            .dw-pricing-card { background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); position: relative; }
            .dw-pricing-card:hover { transform: translateY(-5px); transition: transform 0.2s; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
            
            .dw-pkg-header { background: #f8fafc; padding: 20px; text-align: center; border-bottom: 1px solid #e2e8f0; }
            .dw-pkg-name { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0 0 5px; }
            .dw-pkg-desc { font-size: 13px; color: #64748b; margin: 0; min-height: 40px; }
            
            .dw-pkg-price { padding: 25px 20px; text-align: center; color: #2271b1; }
            .dw-pkg-amount { font-size: 32px; font-weight: 800; }
            
            .dw-pkg-features { padding: 0 20px 20px; text-align: center; }
            .dw-feature-item { margin-bottom: 8px; color: #334155; font-size: 14px; }
            
            .dw-pkg-footer { padding: 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
            .badge-inactive { position: absolute; top: 10px; right: 10px; background: #ef4444; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        </style>

        <div class="dw-pricing-grid">
            <?php if(empty($packages)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: #fff;">Belum ada paket. Silakan buat baru.</div>
            <?php else: foreach($packages as $pkg): ?>
                <div class="dw-pricing-card">
                    <?php if($pkg->status !== 'aktif'): ?><span class="badge-inactive">Nonaktif</span><?php endif; ?>
                    
                    <div class="dw-pkg-header">
                        <h3 class="dw-pkg-name"><?php echo esc_html($pkg->nama_paket); ?></h3>
                        <p class="dw-pkg-desc"><?php echo esc_html($pkg->deskripsi); ?></p>
                    </div>
                    
                    <div class="dw-pkg-price">
                        <span class="dw-pkg-amount">Rp <?php echo number_format($pkg->harga, 0, ',', '.'); ?></span>
                    </div>
                    
                    <div class="dw-pkg-features">
                        <div class="dw-feature-item">
                            <span class="dashicons dashicons-cart"></span> <strong><?php echo number_format($pkg->jumlah_transaksi); ?></strong> Kuota Transaksi
                        </div>
                        <div class="dw-feature-item" style="color:#64748b;">Komisi Desa: <?php echo $pkg->persentase_komisi_desa; ?>%</div>
                    </div>
                    
                    <div class="dw-pkg-footer">
                        <a href="?page=dw-paket-transaksi&action=edit_paket&id=<?php echo $pkg->id; ?>" class="button button-secondary">Edit</a>
                        <!-- TOMBOL HAPUS -->
                        <a href="<?php echo wp_nonce_url("admin.php?page=dw-paket-transaksi&action=delete_paket&id={$pkg->id}", 'dw_delete_paket_nonce'); ?>" class="button" style="color: #b32d2e; border-color: #b32d2e;" onclick="return confirm('Hapus paket ini selamanya?');">Hapus</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php
}

function dw_paket_form_render($id) {
    global $wpdb;
    $item = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_paket_transaksi WHERE id=%d", $id)) : null;
    ?>
    <div class="wrap dw-wrap">
        <h1><?php echo $id ? 'Edit Paket' : 'Buat Paket Baru'; ?></h1>
        <div class="card" style="padding: 30px; max-width: 600px; margin-top: 20px;">
            <form method="post" action="admin.php?page=dw-paket-transaksi">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="dw_submit_paket" value="1">
                <?php wp_nonce_field('dw_save_paket_nonce'); ?>
                
                <div class="dw-input-group" style="margin-bottom: 20px;">
                    <label>Nama Paket</label>
                    <input type="text" name="nama_paket" value="<?php echo esc_attr($item->nama_paket??''); ?>" class="large-text" required>
                </div>
                
                <div class="dw-input-group" style="margin-bottom: 20px;">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" class="large-text" rows="2"><?php echo esc_textarea($item->deskripsi??''); ?></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div><label>Harga (Rp)</label><input type="number" name="harga" value="<?php echo esc_attr($item->harga??''); ?>" class="regular-text" style="width:100%;" required></div>
                    <div><label>Kuota Transaksi</label><input type="number" name="jumlah_transaksi" value="<?php echo esc_attr($item->jumlah_transaksi??'100'); ?>" class="regular-text" style="width:100%;" required></div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top:15px;">
                    <div><label>Komisi Desa (%)</label><input type="number" step="0.1" name="persentase_komisi_desa" value="<?php echo esc_attr($item->persentase_komisi_desa??'20'); ?>" class="regular-text" style="width:100%;"></div>
                    <div><label>Status</label>
                        <select name="status" style="width:100%;">
                            <option value="aktif" <?php selected($item->status??'','aktif'); ?>>Aktif</option>
                            <option value="nonaktif" <?php selected($item->status??'','nonaktif'); ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                    <button type="submit" class="button button-primary button-large">Simpan Paket</button>
                    <a href="?page=dw-paket-transaksi" class="button button-large">Batal</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}