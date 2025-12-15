<?php
/**
 * File: includes/admin-pages/page-banner.php
 * Description: CRUD Banner dengan tampilan yang konsisten.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handler Form & Delete (sama seperti sebelumnya, disingkat)
function dw_banner_init() {
    if(isset($_POST['dw_submit_banner'])) { /* logic simpan... */ }
    if(isset($_GET['action']) && $_GET['action']=='delete') { /* logic hapus... */ }
}
// Note: Logic handler lengkapnya tetap pakai yang ada di file Anda sebelumnya, 
// hanya pastikan fungsi render di bawah ini yang diupdate.

function dw_banner_page_render() {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'add' || $action === 'edit') { dw_banner_form_render(isset($_GET['id']) ? absint($_GET['id']) : 0); return; }
    
    if(!class_exists('DW_Banner_List_Table')) require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-banner-list-table.php';
    $table = new DW_Banner_List_Table();
    $table->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Banner</h1>
        <a href="?page=dw-banner&action=add" class="page-title-action">Tambah Banner</a>
        <hr class="wp-header-end">
        
        <?php settings_errors('dw_banner_notices'); ?>
        
        <div class="card" style="margin-top:20px; padding:0;">
            <form method="post">
                <input type="hidden" name="page" value="dw-banner">
                <?php $table->display(); ?>
            </form>
        </div>
    </div>
    <?php
}

// Function form render juga perlu CSS wrapper card
function dw_banner_form_render($id) {
    global $wpdb;
    $item = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_banner WHERE id=%d", $id)) : null;
    ?>
    <div class="wrap dw-wrap">
        <h1><?php echo $id ? 'Edit Banner' : 'Tambah Banner'; ?></h1>
        <div class="card" style="padding:20px; max-width:800px; margin-top:20px;">
            <form method="post">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="dw_submit_banner" value="1">
                <?php wp_nonce_field('dw_save_banner_nonce'); ?>
                
                <table class="form-table">
                    <tr><th>Judul</th><td><input name="judul" type="text" value="<?php echo esc_attr($item->judul??''); ?>" class="regular-text" required></td></tr>
                    <tr><th>Gambar</th><td>
                        <input type="text" name="gambar" id="dw_banner_img" value="<?php echo esc_attr($item->gambar??''); ?>" class="regular-text">
                        <button type="button" class="button" id="btn_upl_banner">Upload</button>
                        <br><img id="prev_banner" src="<?php echo esc_attr($item->gambar??''); ?>" style="max-width:300px; margin-top:10px; border-radius:4px;">
                    </td></tr>
                    <tr><th>Link</th><td><input name="link" type="url" value="<?php echo esc_attr($item->link??''); ?>" class="large-text"></td></tr>
                    <tr><th>Status</th><td><select name="status"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select></td></tr>
                    <tr><th>Prioritas</th><td><input name="prioritas" type="number" value="<?php echo esc_attr($item->prioritas??'10'); ?>" class="small-text"></td></tr>
                </table>
                <p class="submit"><input type="submit" class="button button-primary" value="Simpan Banner"></p>
            </form>
        </div>
    </div>
    <script>
    jQuery('#btn_upl_banner').click(function(e){
        e.preventDefault(); var frame = wp.media({title:'Pilih Banner', multiple:false});
        frame.on('select', function(){ 
            var url = frame.state().get('selection').first().toJSON().url; 
            jQuery('#dw_banner_img').val(url); jQuery('#prev_banner').attr('src', url); 
        }); frame.open();
    });
    </script>
    <?php
}
?>