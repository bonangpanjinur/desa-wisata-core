<?php
/**
 * Page Banner Management
 * Handles listing, adding, editing, and deleting banners.
 * 
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle POST/GET actions for Banner CRUD
 */
function dw_handle_banner_actions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_banner';

    // 1. Handle DELETE (Single)
    if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) ) {
        $id = intval($_GET['id']);
        
        // Verify Nonce
        if ( ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'dw_delete_banner_' . $id) ) {
            wp_die('Security check failed');
        }

        $wpdb->delete($table_name, array('id' => $id), array('%d'));

        wp_redirect(add_query_arg(array('page' => 'dw-banner', 'message' => 'deleted'), admin_url('admin.php')));
        exit;
    }

    // 2. Handle SAVE (Insert/Update)
    if ( isset($_POST['dw_action']) && $_POST['dw_action'] === 'save_banner' ) {
        // Verify Nonce
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'dw_save_banner_nonce') ) {
            wp_die('Security check failed');
        }

        $id        = isset($_POST['banner_id']) ? intval($_POST['banner_id']) : 0;
        $judul     = sanitize_text_field($_POST['judul']);
        $gambar    = esc_url_raw($_POST['gambar']);
        $link      = esc_url_raw($_POST['link']);
        $status    = sanitize_text_field($_POST['status']);
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
            $updated = $wpdb->update($table_name, $data, array('id' => $id), $format, array('%d'));
            $message = ($updated !== false) ? 'updated' : 'error';
        } else {
            $inserted = $wpdb->insert($table_name, $data, $format);
            $message = ($inserted !== false) ? 'created' : 'error';
        }

        wp_redirect(add_query_arg(array('page' => 'dw-banner', 'message' => $message), admin_url('admin.php')));
        exit;
    }

    // 3. Handle Bulk Actions
    if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['ids']) ) {
        // Bulk delete is usually handled within the List Table class, 
        // but we can ensure it redirects correctly here if needed.
    }
}

// Run handler
dw_handle_banner_actions();

/**
 * Render Banner Management Page
 */
function dw_banner_page_render() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_banner';
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id     = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // --- Notices ---
    if ( isset($_GET['message']) ) {
        $msg_code = $_GET['message'];
        $notice   = '';
        $type     = 'success';
        
        switch ($msg_code) {
            case 'created': $notice = 'Banner berhasil ditambahkan.'; break;
            case 'updated': $notice = 'Banner berhasil diperbarui.'; break;
            case 'deleted': $notice = 'Banner berhasil dihapus.'; break;
            case 'error': 
                $notice = 'Terjadi kesalahan saat menyimpan data.'; 
                $type = 'error';
                break;
        }

        if ( $notice ) {
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }
    }

    // --- FORM MODE ---
    if ( $action === 'add' || ($action === 'edit' && $id > 0) ) {
        wp_enqueue_media();

        $item = null;
        if ( $id > 0 ) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            if (!$item) {
                echo '<div class="notice notice-error"><p>Banner tidak ditemukan.</p></div>';
                return;
            }
        }

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

            <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
                <form method="post" action="">
                    <input type="hidden" name="dw_action" value="save_banner">
                    <input type="hidden" name="banner_id" value="<?php echo intval($id); ?>">
                    <?php wp_nonce_field('dw_save_banner_nonce'); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="judul">Judul Banner</label></th>
                            <td>
                                <input type="text" name="judul" id="judul" value="<?php echo esc_attr($judul); ?>" class="regular-text" required>
                                <p class="description">Judul untuk identifikasi internal.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gambar">Gambar Banner</label></th>
                            <td>
                                <div class="dw-image-upload-wrapper">
                                    <input type="text" name="gambar" id="gambar" value="<?php echo esc_url($gambar); ?>" class="regular-text" placeholder="URL Gambar" required>
                                    <button type="button" class="button" id="btn-upload-gambar">Pilih dari Media</button>
                                </div>
                                <p class="description">Rekomendasi: 1200x400 px atau rasio 3:1.</p>
                                
                                <div id="preview-gambar-container" style="margin-top: 15px; max-width: 100%; width: 400px; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; background: #eee; display: <?php echo $gambar ? 'block' : 'none'; ?>;">
                                    <img id="preview-gambar-img" src="<?php echo esc_url($gambar); ?>" style="width: 100%; height: auto; display: block;">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="link">Link Tujuan</label></th>
                            <td>
                                <input type="url" name="link" id="link" value="<?php echo esc_url($link); ?>" class="regular-text" placeholder="https://...">
                                <p class="description">Opsional. URL tujuan saat banner diklik.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="prioritas">Urutan Prioritas</label></th>
                            <td>
                                <input type="number" name="prioritas" id="prioritas" value="<?php echo esc_attr($prioritas); ?>" class="small-text" min="0">
                                <p class="description">Angka lebih kecil akan muncul lebih awal.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="status">Status Publikasi</label></th>
                            <td>
                                <select name="status" id="status">
                                    <option value="aktif" <?php selected($status, 'aktif'); ?>>Aktif (Tampil)</option>
                                    <option value="nonaktif" <?php selected($status, 'nonaktif'); ?>>Nonaktif (Sembunyi)</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button($id > 0 ? 'Simpan Perubahan' : 'Terbitkan Banner', 'primary'); ?>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($){
                var image_frame;
                $('#btn-upload-gambar').click(function(e) {
                    e.preventDefault();
                    if(image_frame){
                        image_frame.open();
                        return;
                    }
                    image_frame = wp.media({
                        title: 'Pilih Gambar Banner',
                        button: { text: 'Gunakan Gambar Ini' },
                        multiple: false,
                        library: { type: 'image' }
                    });
                    image_frame.on('select', function(){
                        var attachment = image_frame.state().get('selection').first().toJSON();
                        $('#gambar').val(attachment.url);
                        $('#preview-gambar-img').attr('src', attachment.url);
                        $('#preview-gambar-container').show();
                    });
                    image_frame.open();
                });

                // Update preview when URL is pasted manually
                $('#gambar').on('change input', function() {
                    var url = $(this).val();
                    if (url) {
                        $('#preview-gambar-img').attr('src', url);
                        $('#preview-gambar-container').show();
                    } else {
                        $('#preview-gambar-container').hide();
                    }
                });
            });
            </script>
        </div>
        <?php

    } else {
        // --- LIST MODE ---
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
                    <?php 
                    $list_table->search_box('Cari Banner', 'search_id');
                    $list_table->display(); 
                    ?>
                </form>
            </div>
            <style>
                .column-status span {
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .column-status .aktif { background: #e7f6ed; color: #207b4d; }
                .column-status .nonaktif { background: #fcf0f1; color: #d63638; }
                .column-gambar img { border: 1px solid #ddd; padding: 2px; background: #fff; }
            </style>
            <script>
            jQuery(document).ready(function($){
                $('.dw-confirm-link').click(function(e){
                    var message = $(this).data('confirm-message') || 'Apakah Anda yakin?';
                    if(!confirm(message)) {
                        e.preventDefault();
                    }
                });
            });
            </script>
            <?php
        } else {
            echo '<div class="notice notice-error"><p>Error: Class <code>DW_Banner_List_Table</code> tidak ditemukan.</p></div>';
        }
    }
}
