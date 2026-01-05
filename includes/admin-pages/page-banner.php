<?php
/**
 * Page Banner Management
 * * Handles listing, adding, editing, and deleting banners.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle POST/GET actions for Banner CRUD
 * This function must run before any HTML output to allow redirects.
 */
function dw_handle_banner_actions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'banner';

    // 1. Handle DELETE
    if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) ) {
        $id = intval($_GET['id']);
        
        // Verifikasi Nonce
        if ( ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'delete_banner_' . $id) ) {
            wp_die('Security check failed');
        }

        $wpdb->delete($table_name, array('id' => $id), array('%d'));

        // Redirect
        wp_redirect(add_query_arg(array('page' => 'dw-banner', 'message' => 'deleted'), admin_url('admin.php')));
        exit;
    }

    // 2. Handle SAVE (Insert/Update)
    if ( isset($_POST['dw_action']) && $_POST['dw_action'] === 'save_banner' ) {
        // Verifikasi Nonce
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'dw_save_banner_nonce') ) {
            wp_die('Security check failed');
        }

        // Ambil Data dari Form
        $id        = isset($_POST['banner_id']) ? intval($_POST['banner_id']) : 0;
        $judul     = sanitize_text_field($_POST['judul']);
        $gambar    = esc_url_raw($_POST['gambar']);
        $link      = esc_url_raw($_POST['link']);
        $status    = sanitize_text_field($_POST['status']); // ENUM('aktif','nonaktif')
        $prioritas = intval($_POST['prioritas']);

        $data = array(
            'judul'     => $judul,
            'gambar'    => $gambar,
            'link'      => $link,
            'status'    => $status,
            'prioritas' => $prioritas
        );

        $format = array('%s', '%s', '%s', '%s', '%d');

        if ( $id > 0 ) {
            // Update Existing
            $wpdb->update($table_name, $data, array('id' => $id), $format, array('%d'));
            $message = 'updated';
        } else {
            // Insert New
            $wpdb->insert($table_name, $data, $format);
            $message = 'created';
        }

        // Redirect
        wp_redirect(add_query_arg(array('page' => 'dw-banner', 'message' => $message), admin_url('admin.php')));
        exit;
    }
}

// Jalankan handler sebelum render halaman
dw_handle_banner_actions();

/**
 * Render Halaman Manajemen Banner
 */
function dw_render_page_banner() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'banner';
    
    // Tentukan mode: List atau Form (Add/Edit)
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id     = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // --- Notifikasi Pesan ---
    if ( isset($_GET['message']) ) {
        $msg_code = $_GET['message'];
        $notice   = '';
        
        if ( $msg_code === 'created' ) $notice = 'Banner berhasil ditambahkan.';
        elseif ( $msg_code === 'updated' ) $notice = 'Banner berhasil diperbarui.';
        elseif ( $msg_code === 'deleted' ) $notice = 'Banner berhasil dihapus.';

        if ( $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }
    }

    // --- MODE FORM (ADD / EDIT) ---
    if ( $action === 'add' || ($action === 'edit' && $id > 0) ) {
        
        // Pastikan library media WordPress dimuat
        wp_enqueue_media();

        $item = null;
        if ( $id > 0 ) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        }

        // Default Values
        $judul     = $item ? $item->judul : '';
        $gambar    = $item ? $item->gambar : '';
        $link      = $item ? $item->link : '';
        $status    = $item ? $item->status : 'aktif';
        $prioritas = $item ? $item->prioritas : 10;
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo $id > 0 ? 'Edit Banner' : 'Tambah Banner Baru'; ?></h1>
            <a href="<?php echo admin_url('admin.php?page=dw-banner'); ?>" class="page-title-action">Kembali</a>
            <hr class="wp-header-end">

            <form method="post" action="">
                <!-- Hidden Fields untuk Handler -->
                <input type="hidden" name="dw_action" value="save_banner">
                <input type="hidden" name="banner_id" value="<?php echo intval($id); ?>">
                <?php wp_nonce_field('dw_save_banner_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="judul">Judul Banner</label></th>
                        <td>
                            <input type="text" name="judul" id="judul" value="<?php echo esc_attr($judul); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gambar">Gambar Banner</label></th>
                        <td>
                            <input type="text" name="gambar" id="gambar" value="<?php echo esc_url($gambar); ?>" class="regular-text" placeholder="URL Gambar" required>
                            <button type="button" class="button" id="btn-upload-gambar">Pilih Gambar</button>
                            <p class="description">Gunakan gambar dengan rasio aspek landscape (misal 1200x400 px).</p>
                            
                            <div id="preview-gambar-container" style="margin-top: 10px; max-width: 400px; border: 1px solid #ddd; padding: 5px; background: #f9f9f9; display: <?php echo $gambar ? 'block' : 'none'; ?>;">
                                <img id="preview-gambar-img" src="<?php echo esc_url($gambar); ?>" style="width: 100%; height: auto;">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="link">Link Tujuan (Opsional)</label></th>
                        <td>
                            <input type="url" name="link" id="link" value="<?php echo esc_url($link); ?>" class="regular-text" placeholder="https://...">
                            <p class="description">Link halaman yang dituju saat banner diklik.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="prioritas">Prioritas</label></th>
                        <td>
                            <input type="number" name="prioritas" id="prioritas" value="<?php echo esc_attr($prioritas); ?>" class="small-text">
                            <p class="description">Angka lebih kecil akan tampil lebih dulu (misal 1 lebih dulu dari 10).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status">Status</label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="aktif" <?php selected($status, 'aktif'); ?>>Aktif</option>
                                <option value="nonaktif" <?php selected($status, 'nonaktif'); ?>>Nonaktif</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button($id > 0 ? 'Simpan Perubahan' : 'Tambah Banner'); ?>
            </form>

            <script>
            jQuery(document).ready(function($){
                $('#btn-upload-gambar').click(function(e) {
                    e.preventDefault();
                    var image_frame;
                    if(image_frame){
                        image_frame.open();
                        return;
                    }
                    image_frame = wp.media({
                        title: 'Pilih Gambar Banner',
                        multiple: false,
                        library: { type: 'image' }
                    });
                    image_frame.on('select', function(){
                        var media_attachment = image_frame.state().get('selection').first().toJSON();
                        $('#gambar').val(media_attachment.url);
                        $('#preview-gambar-img').attr('src', media_attachment.url);
                        $('#preview-gambar-container').show();
                    });
                    image_frame.open();
                });
            });
            </script>
        </div>
        <?php

    } else {
        // --- MODE LIST TABLE ---
        
        // Pastikan class List Table tersedia
        if ( ! class_exists('DW_Banner_List_Table') ) {
            $list_table_path = plugin_dir_path(dirname(__FILE__)) . 'list-tables/class-dw-banner-list-table.php';
            if ( file_exists($list_table_path) ) {
                require_once $list_table_path;
            }
        }

        if ( class_exists('DW_Banner_List_Table') ) {
            $list_table = new DW_Banner_List_Table();
            $list_table->prepare_items();
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline">Manajemen Banner</h1>
                <a href="<?php echo admin_url('admin.php?page=dw-banner&action=add'); ?>" class="page-title-action">Tambah Baru</a>
                <hr class="wp-header-end">
                
                <form id="dw-banner-list" method="get">
                    <input type="hidden" name="page" value="dw-banner" />
                    <?php $list_table->search_box('Cari Banner', 'search_id'); ?>
                    <?php $list_table->display(); ?>
                </form>
            </div>
            <?php
        } else {
            echo '<div class="notice notice-error"><p>Error: Class <code>DW_Banner_List_Table</code> tidak ditemukan. Pastikan file ada di folder <code>includes/list-tables/</code>.</p></div>';
        }
    }
}