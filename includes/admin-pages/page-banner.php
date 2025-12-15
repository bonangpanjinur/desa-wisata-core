<?php
/**
 * File: includes/admin-pages/page-banner.php
 * Description: Manajemen Banner Slider dengan Tampilan Visual.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler (Simpan/Hapus) - Disederhanakan untuk fokus render
function dw_banner_init() {
    if(isset($_POST['dw_submit_banner']) && check_admin_referer('dw_save_banner_nonce')) {
        global $wpdb;
        $data = [
            'judul' => sanitize_text_field($_POST['judul']),
            'gambar' => esc_url_raw($_POST['gambar']),
            'link' => esc_url_raw($_POST['link']),
            'status' => sanitize_key($_POST['status']),
            'prioritas' => intval($_POST['prioritas'])
        ];
        if(!empty($_POST['id'])) {
            $wpdb->update("{$wpdb->prefix}dw_banner", $data, ['id'=>intval($_POST['id'])]);
            add_settings_error('dw_banner_msg', 'upd', 'Banner diperbarui.', 'success');
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert("{$wpdb->prefix}dw_banner", $data);
            add_settings_error('dw_banner_msg', 'add', 'Banner ditambahkan.', 'success');
        }
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=dw-banner')); exit;
    }
    if(isset($_GET['action']) && $_GET['action']=='delete' && isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'dw_del_banner')) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}dw_banner", ['id'=>intval($_GET['id'])]);
        add_settings_error('dw_banner_msg', 'del', 'Banner dihapus.', 'success');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=dw-banner')); exit;
    }
}
add_action('admin_init', 'dw_banner_init');

function dw_banner_page_render() {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'add' || $action === 'edit') { dw_banner_form_render(isset($_GET['id']) ? absint($_GET['id']) : 0); return; }
    
    if(!class_exists('DW_Banner_List_Table')) require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-banner-list-table.php';
    $table = new DW_Banner_List_Table();
    $table->prepare_items();
    
    // Stats
    global $wpdb;
    $active = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dw_banner WHERE status='aktif'");
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dw_banner");
    
    $e = get_transient('settings_errors'); if($e) { settings_errors('dw_banner_msg'); delete_transient('settings_errors'); }
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Banner & Slider</h1>
        <a href="?page=dw-banner&action=add" class="page-title-action">Tambah Banner</a>
        <hr class="wp-header-end">
        
        <style>
            .dw-banner-stats { display: flex; gap: 15px; margin: 20px 0; }
            .dw-stat-pill { background: #fff; border: 1px solid #c3c4c7; padding: 5px 15px; border-radius: 20px; font-weight: 600; font-size: 13px; display: flex; align-items: center; }
            .dw-stat-pill .count { background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-size: 12px; }
            .dw-card-table { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; margin-top: 20px; padding: 0; }
        </style>

        <div class="dw-banner-stats">
            <div class="dw-stat-pill">Total Banner <span class="count"><?php echo $active; ?> / <?php echo $total; ?> Aktif</span></div>
        </div>
        
        <div class="dw-card-table">
            <form method="post">
                <input type="hidden" name="page" value="dw-banner">
                <?php $table->display(); ?>
            </form>
        </div>
    </div>
    <?php
}

function dw_banner_form_render($id) {
    global $wpdb;
    $item = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_banner WHERE id=%d", $id)) : null;
    ?>
    <div class="wrap dw-wrap">
        <h1><?php echo $id ? 'Edit Banner' : 'Tambah Banner Baru'; ?></h1>
        
        <div class="card" style="padding: 30px; max-width: 800px; margin-top: 20px;">
            <form method="post">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="dw_submit_banner" value="1">
                <?php wp_nonce_field('dw_save_banner_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th style="width: 150px;">Judul Banner</th>
                        <td><input name="judul" type="text" value="<?php echo esc_attr($item->judul??''); ?>" class="regular-text" placeholder="Promo Lebaran..." required></td>
                    </tr>
                    <tr>
                        <th>Gambar Banner</th>
                        <td>
                            <div style="display: flex; gap: 15px; align-items: flex-start;">
                                <div style="flex-grow: 1;">
                                    <input type="text" name="gambar" id="dw_banner_img" value="<?php echo esc_attr($item->gambar??''); ?>" class="large-text" placeholder="URL Gambar">
                                    <button type="button" class="button button-secondary" id="btn_upl_banner" style="margin-top: 10px;"><span class="dashicons dashicons-format-image"></span> Pilih Gambar</button>
                                </div>
                            </div>
                            <div style="margin-top: 15px; background: #f0f0f1; padding: 10px; border-radius: 4px; text-align: center;">
                                <img id="prev_banner" src="<?php echo esc_url($item->gambar??'https://placehold.co/600x200?text=Preview+Banner'); ?>" style="max-width: 100%; height: auto; max-height: 200px; object-fit: cover; border-radius: 4px;">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Link Tujuan</th>
                        <td><input name="link" type="url" value="<?php echo esc_attr($item->link??''); ?>" class="regular-text" placeholder="https://..."></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <select name="status">
                                <option value="aktif" <?php selected($item->status??'','aktif'); ?>>Aktif</option>
                                <option value="nonaktif" <?php selected($item->status??'','nonaktif'); ?>>Nonaktif</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Urutan Prioritas</th>
                        <td><input name="prioritas" type="number" value="<?php echo esc_attr($item->prioritas??'10'); ?>" class="small-text"> <p class="description">Angka kecil tampil duluan.</p></td>
                    </tr>
                </table>
                
                <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                    <input type="submit" class="button button-primary button-large" value="Simpan Banner">
                    <a href="?page=dw-banner" class="button button-large">Batal</a>
                </div>
            </form>
        </div>
    </div>
    <script>
    jQuery('#btn_upl_banner').click(function(e){
        e.preventDefault(); var frame = wp.media({title:'Pilih Banner', multiple:false, library:{type:'image'}});
        frame.on('select', function(){ 
            var url = frame.state().get('selection').first().toJSON().url; 
            jQuery('#dw_banner_img').val(url); jQuery('#prev_banner').attr('src', url); 
        }); frame.open();
    });
    </script>
    <?php
}
?>