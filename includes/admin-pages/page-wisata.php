<?php
/**
 * File Name:   page-wisata.php
 * Description: CRUD Wisata Custom Table dengan Kolom Relasi Desa yang Jelas.
 */

if (!defined('ABSPATH')) exit;

function dw_wisata_page_render() {
    global $wpdb;
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_desa   = $wpdb->prefix . 'dw_desa';
    
    $message = ''; $msg_type = '';

    // --- HANDLE ACTION (SIMPAN DATA) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wisata'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_wisata_save')) {
            echo '<div class="notice notice-error"><p>Security Fail.</p></div>'; return;
        }

        $nama = sanitize_text_field($_POST['nama_wisata']);
        $data = [
            'id_desa'      => intval($_POST['id_desa']),
            'nama_wisata'  => $nama,
            'slug'         => sanitize_title($nama),
            'deskripsi'    => wp_kses_post($_POST['deskripsi']),
            'harga_tiket'  => floatval($_POST['harga_tiket']),
            'jam_buka'     => sanitize_text_field($_POST['jam_buka']),
            'foto_utama'   => esc_url_raw($_POST['foto_utama']),
            'status'       => sanitize_text_field($_POST['status']),
            'updated_at'   => current_time('mysql')
        ];

        if (!empty($_POST['wisata_id'])) {
            $wpdb->update($table_wisata, $data, ['id' => intval($_POST['wisata_id'])]);
            $message = 'Data wisata berhasil diperbarui.'; $msg_type = 'success';
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table_wisata, $data);
            $message = 'Objek wisata baru berhasil ditambahkan.'; $msg_type = 'success';
        }
    }

    // --- VIEW LOGIC ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit');
    $edit_data = null;
    if ($is_edit && isset($_GET['id'])) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_wisata WHERE id = %d", intval($_GET['id'])));
    }

    // Ambil list desa untuk Dropdown Form
    $list_desa = $wpdb->get_results("SELECT id, nama_desa FROM $table_desa WHERE status='aktif'");

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Objek Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-wisata&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if($message): ?><div class="notice notice-<?php echo $msg_type; ?> is-dismissible"><p><?php echo $message; ?></p></div><?php endif; ?>

        <?php if($is_edit): ?>
            <!-- FORM INPUT / EDIT -->
            <div class="card" style="padding:20px; margin-top:10px;">
                <form method="post">
                    <?php wp_nonce_field('dw_wisata_save'); ?>
                    <input type="hidden" name="action_wisata" value="save">
                    <?php if($edit_data): ?><input type="hidden" name="wisata_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label>Pilih Desa *</label></th>
                            <td>
                                <select name="id_desa" required class="regular-text">
                                    <option value="">-- Pilih Desa --</option>
                                    <?php foreach($list_desa as $d): ?>
                                        <option value="<?php echo $d->id; ?>" <?php selected($edit_data ? $edit_data->id_desa : '', $d->id); ?>>
                                            <?php echo esc_html($d->nama_desa); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Lokasi objek wisata ini berada.</p>
                            </td>
                        </tr>

                        <tr><th>Nama Wisata</th><td><input name="nama_wisata" type="text" value="<?php echo esc_attr($edit_data->nama_wisata ?? ''); ?>" class="regular-text" required></td></tr>
                        <tr><th>Deskripsi</th><td><?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>5, 'media_buttons'=>false]); ?></td></tr>
                        <tr><th>Harga Tiket (Rp)</th><td><input name="harga_tiket" type="number" value="<?php echo esc_attr($edit_data->harga_tiket ?? 0); ?>" class="regular-text"></td></tr>
                        <tr><th>Jam Buka</th><td><input name="jam_buka" type="text" value="<?php echo esc_attr($edit_data->jam_buka ?? '08:00 - 17:00'); ?>" class="regular-text"></td></tr>
                        
                        <tr><th>Foto Utama</th><td>
                            <input type="text" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>" class="regular-text">
                            <button type="button" class="button" id="btn_upl">Upload</button>
                        </td></tr>

                        <tr><th>Status</th><td>
                            <select name="status">
                                <option value="aktif" <?php selected($edit_data ? $edit_data->status : '', 'aktif'); ?>>Aktif</option>
                                <option value="nonaktif" <?php selected($edit_data ? $edit_data->status : '', 'nonaktif'); ?>>Nonaktif</option>
                            </select>
                        </td></tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Simpan Wisata">
                        <a href="?page=dw-wisata" class="button">Batal</a>
                    </p>
                </form>
            </div>
            <script>
            jQuery(document).ready(function($){
                $('#btn_upl').click(function(e){
                    e.preventDefault(); var frame = wp.media({title:'Foto Wisata', multiple:false});
                    frame.on('select', function(){ $('#foto_utama').val(frame.state().get('selection').first().toJSON().url); });
                    frame.open();
                });
            });
            </script>
        <?php else: ?>
            <!-- TABEL LIST WISATA -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="25%">Nama Wisata</th>
                        <th width="20%">Asal Desa</th> <!-- KOLOM BARU -->
                        <th width="15%">Harga Tiket</th>
                        <th width="15%">Jam Buka</th>
                        <th width="10%">Status</th>
                        <th width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // QUERY UPDATE: JOIN ke dw_desa
                    $rows = $wpdb->get_results("
                        SELECT w.*, d.nama_desa 
                        FROM $table_wisata w 
                        LEFT JOIN $table_desa d ON w.id_desa = d.id 
                        ORDER BY w.id DESC
                    ");
                    
                    if($rows):
                        foreach($rows as $r): 
                            $edit_url = "?page=dw-wisata&action=edit&id={$r->id}";
                            
                            // Logika Tampilan Desa
                            $desa_html = !empty($r->nama_desa) 
                                ? '<span class="dashicons dashicons-location" style="font-size:14px; color:#2271b1;"></span> ' . esc_html($r->nama_desa)
                                : '<span style="color:#a00;">- Belum Terhubung -</span>';
                            
                            // Logika Badge Status
                            $status_style = 'background:#eee; color:#666;';
                            if($r->status == 'aktif') $status_style = 'background:#dcfce7; color:#166534;';
                            if($r->status == 'nonaktif') $status_style = 'background:#fee2e2; color:#991b1b;';
                    ?>
                    <tr>
                        <td><strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_wisata); ?></a></strong></td>
                        
                        <!-- ISI KOLOM DESA -->
                        <td><?php echo $desa_html; ?></td>
                        
                        <td>Rp <?php echo number_format($r->harga_tiket); ?></td>
                        <td><?php echo esc_html($r->jam_buka); ?></td>
                        <td><span style="padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; <?php echo $status_style; ?>"><?php echo strtoupper($r->status); ?></span></td>
                        <td><a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; 
                    else: ?>
                        <tr><td colspan="6">Belum ada objek wisata. Silakan tambah baru.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}