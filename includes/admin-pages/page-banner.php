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

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin-ui-components.php';

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

    ?>
    <div class="wrap dw-wrap">
        <?php
        // --- Notices ---
        if ( isset($_GET['message']) ) {
            $msg_code = $_GET['message'];
            $notice   = '';
            $type     = 'info';
            
            switch ($msg_code) {
                case 'created': $notice = 'Banner berhasil ditambahkan.'; $type = 'success'; break;
                case 'updated': $notice = 'Banner berhasil diperbarui.'; $type = 'success'; break;
                case 'deleted': $notice = 'Banner berhasil dihapus.'; $type = 'success'; break;
                case 'error': 
                    $notice = 'Terjadi kesalahan saat menyimpan data.'; 
                    $type = 'danger';
                    break;
            }

            if ( $notice ) {
                dw_admin_render_alert($notice, $type);
            }
        }

        // --- FORM MODE ---
        if ( $action === 'add' || ($action === 'edit' && $id > 0) ) {
            wp_enqueue_media();

            $item = null;
            if ( $id > 0 ) {
                $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
                if (!$item) {
                    dw_admin_render_alert('Banner tidak ditemukan.', 'danger');
                    return;
                }
            }

            $judul     = $item ? $item->judul : '';
            $gambar    = $item ? $item->gambar : '';
            $link      = $item ? $item->link : '';
            $status    = $item ? $item->status : 'aktif';
            $prioritas = $item ? $item->prioritas : 10;
            ?>
            <div class="dw-card">
                <div class="dw-card-header">
                    <h3 class="card-heading"><?php echo $id > 0 ? 'Edit Banner' : 'Tambah Banner Baru'; ?></h3>
                    <a href="<?php echo admin_url('admin.php?page=dw-banner'); ?>" class="dw-btn-link">Kembali</a>
                </div>
                <div class="dw-card-body">
                    <form method="post" action="">
                        <input type="hidden" name="dw_action" value="save_banner">
                        <input type="hidden" name="banner_id" value="<?php echo intval($id); ?>">
                        <?php wp_nonce_field('dw_save_banner_nonce'); ?>

                        <div class="dw-form-group">
                            <label class="dw-label" for="judul">Judul Banner</label>
                            <input type="text" name="judul" id="judul" value="<?php echo esc_attr($judul); ?>" class="dw-input" required>
                            <p class="dw-help-text">Judul untuk identifikasi internal.</p>
                        </div>

                        <div class="dw-form-group">
                            <label class="dw-label" for="gambar">Gambar Banner</label>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input type="text" name="gambar" id="gambar" value="<?php echo esc_url($gambar); ?>" class="dw-input" placeholder="URL Gambar" required>
                                <button type="button" class="dw-button dw-button-secondary" id="btn-upload-gambar">Pilih Media</button>
                            </div>
                            <p class="dw-help-text">Rekomendasi: 1200x400 px atau rasio 3:1.</p>
                            
                            <div id="preview-gambar-container" style="margin-top: 15px; max-width: 100%; width: 400px; border: 1px solid var(--dw-border-color); border-radius: 8px; overflow: hidden; background: #f8fafc; display: <?php echo $gambar ? 'block' : 'none'; ?>;">
                                <img id="preview-gambar-img" src="<?php echo esc_url($gambar); ?>" style="width: 100%; height: auto; display: block;">
                            </div>
                        </div>

                        <div class="dw-form-group">
                            <label class="dw-label" for="link">Link Tujuan</label>
                            <input type="url" name="link" id="link" value="<?php echo esc_url($link); ?>" class="dw-input" placeholder="https://...">
                            <p class="dw-help-text">Opsional. URL tujuan saat banner diklik.</p>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="dw-form-group">
                                <label class="dw-label" for="prioritas">Urutan Prioritas</label>
                                <input type="number" name="prioritas" id="prioritas" value="<?php echo esc_attr($prioritas); ?>" class="dw-input" min="0">
                                <p class="dw-help-text">Angka lebih kecil akan muncul lebih awal.</p>
                            </div>

                            <div class="dw-form-group">
                                <label class="dw-label" for="status">Status Publikasi</label>
                                <select name="status" id="status" class="dw-select">
                                    <option value="aktif" <?php selected($status, 'aktif'); ?>>Aktif (Tampil)</option>
                                    <option value="nonaktif" <?php selected($status, 'nonaktif'); ?>>Nonaktif (Sembunyi)</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="dw-button dw-button-primary">
                                <?php echo $id > 0 ? 'Simpan Perubahan' : 'Terbitkan Banner'; ?>
                            </button>
                        </div>
                    </form>
                </div>
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
                <div class="dw-card">
                    <div class="dw-card-header">
                        <h3 class="card-heading">Manajemen Banner</h3>
                        <a href="<?php echo admin_url('admin.php?page=dw-banner&action=add'); ?>" class="dw-button dw-button-primary" style="text-decoration: none;">Tambah Baru</a>
                    </div>
                    <div class="dw-card-body">
                        <form id="dw-banner-list" method="get">
                            <input type="hidden" name="page" value="dw-banner" />
                            <?php 
                            $list_table->search_box('Cari Banner', 'search_id');
                            $list_table->display(); 
                            ?>
                        </form>
                    </div>
                </div>
                <style>
                    .column-status span {
                        padding: 4px 10px;
                        border-radius: 999px;
                        font-size: 11px;
                        font-weight: 600;
                        text-transform: uppercase;
                    }
                    .column-status .aktif { background: #dcfce7; color: #166534; }
                    .column-status .nonaktif { background: #fee2e2; color: #991b1b; }
                    .column-gambar img { border-radius: 8px; border: 1px solid var(--dw-border-color); }
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
                dw_admin_render_alert('Error: Class DW_Banner_List_Table tidak ditemukan.', 'danger');
            }
        }
        ?>
    </div>
    <?php
}
