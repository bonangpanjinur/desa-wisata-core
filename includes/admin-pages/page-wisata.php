<?php
/**
 * File Name:   page-wisata.php
 * Description: CRUD Wisata dengan Smart Context (Admin Desa hanya bisa input untuk desanya sendiri).
 */

if (!defined('ABSPATH')) exit;

function dw_wisata_page_render() {
    global $wpdb;
    $table_wisata = $wpdb->prefix . 'dw_wisata';
    $table_desa   = $wpdb->prefix . 'dw_desa';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Cari Desa yang dikelola user ini
    $my_desa_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM $table_desa WHERE id_user_desa = %d", $current_user_id));

    $message = ''; $msg_type = '';

    // --- HANDLE ACTION ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wisata'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_wisata_save')) {
            echo '<div class="notice notice-error"><p>Security Fail.</p></div>'; return;
        }

        // Tentukan ID Desa
        $id_desa_input = 0;
        if ($is_super_admin) {
            $id_desa_input = intval($_POST['id_desa']);
        } else {
            // Paksa ID Desa milik user login
            $id_desa_input = $my_desa_data ? intval($my_desa_data->id) : 0;
        }

        if ($id_desa_input === 0) {
            echo '<div class="notice notice-error"><p>Error: Akun Anda tidak terhubung dengan Desa manapun.</p></div>'; return;
        }

        $nama = sanitize_text_field($_POST['nama_wisata']);
        $data = [
            'id_desa'      => $id_desa_input,
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
            // Security Extra
            if (!$is_super_admin) {
                $check_owner = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_wisata WHERE id = %d AND id_desa = %d", intval($_POST['wisata_id']), $id_desa_input));
                if (!$check_owner) {
                    echo '<div class="notice notice-error"><p>Dilarang mengedit wisata desa lain!</p></div>'; return;
                }
            }

            $wpdb->update($table_wisata, $data, ['id' => intval($_POST['wisata_id'])]);
            $message = 'Wisata diperbarui.'; $msg_type = 'success';
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table_wisata, $data);
            $message = 'Wisata berhasil ditambahkan.'; $msg_type = 'success';
        }
    }

    // --- VIEW ---
    $is_edit = isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit');
    $edit_data = null;
    
    if ($is_edit && isset($_GET['id'])) {
        $query_edit = "SELECT * FROM $table_wisata WHERE id = %d";
        if (!$is_super_admin && $my_desa_data) {
            $query_edit .= " AND id_desa = " . intval($my_desa_data->id);
        }
        $edit_data = $wpdb->get_row($wpdb->prepare($query_edit, intval($_GET['id'])));
        
        if (isset($_GET['id']) && !$edit_data) {
            echo '<div class="notice notice-error"><p>Data tidak ditemukan atau akses ditolak.</p></div>';
            $is_edit = false;
        }
    }

    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Manajemen Objek Wisata</h1>
        <?php if(!$is_edit): ?><a href="?page=dw-wisata&action=new" class="page-title-action">Tambah Baru</a><?php endif; ?>
        <hr class="wp-header-end">

        <?php if($message): ?><div class="notice notice-<?php echo $msg_type; ?> is-dismissible"><p><?php echo $message; ?></p></div><?php endif; ?>

        <?php if($is_edit): ?>
            <div class="card" style="padding:20px; margin-top:10px;">
                <form method="post">
                    <?php wp_nonce_field('dw_wisata_save'); ?>
                    <input type="hidden" name="action_wisata" value="save">
                    <?php if($edit_data): ?><input type="hidden" name="wisata_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label>Desa Wisata *</label></th>
                            <td>
                                <?php if ($is_super_admin): ?>
                                    <!-- ADMIN: Dropdown Bebas -->
                                    <?php $list_desa = $wpdb->get_results("SELECT id, nama_desa FROM $table_desa WHERE status='aktif'"); ?>
                                    <select name="id_desa" required class="regular-text">
                                        <option value="">-- Pilih Desa --</option>
                                        <?php foreach($list_desa as $d): ?>
                                            <option value="<?php echo $d->id; ?>" <?php selected($edit_data ? $edit_data->id_desa : '', $d->id); ?>>
                                                <?php echo esc_html($d->nama_desa); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <!-- ADMIN DESA: Terkunci ke Desanya Sendiri -->
                                    <?php if ($my_desa_data): ?>
                                        <input type="text" class="regular-text" value="<?php echo esc_attr($my_desa_data->nama_desa); ?>" readonly style="background:#f0f0f1;">
                                        <input type="hidden" name="id_desa" value="<?php echo esc_attr($my_desa_data->id); ?>">
                                        <p class="description">Anda sedang menambahkan wisata untuk desa ini.</p>
                                    <?php else: ?>
                                        <div class="notice notice-error inline"><p>Akun Anda belum di-assign ke Desa manapun.</p></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php if ($is_super_admin || $my_desa_data): ?>
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
                        <?php endif; ?>
                    </table>
                    
                    <?php if ($is_super_admin || $my_desa_data): ?>
                        <p class="submit"><input type="submit" class="button button-primary" value="Simpan Wisata"></p>
                    <?php endif; ?>
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
            <!-- TABEL VIEW -->
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Nama Wisata</th><th>Asal Desa</th><th>Harga Tiket</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php 
                    $sql_list = "SELECT w.*, d.nama_desa 
                                 FROM $table_wisata w 
                                 LEFT JOIN $table_desa d ON w.id_desa = d.id";
                    
                    // Filter: Jika bukan admin, hanya lihat wisata desanya sendiri
                    if (!$is_super_admin && $my_desa_data) {
                        $sql_list .= " WHERE w.id_desa = " . intval($my_desa_data->id);
                    } elseif (!$is_super_admin && !$my_desa_data) {
                        $sql_list .= " WHERE 1=0";
                    }
                    
                    $sql_list .= " ORDER BY w.id DESC";
                    $rows = $wpdb->get_results($sql_list);
                    
                    foreach($rows as $r): 
                        $edit_url = "?page=dw-wisata&action=edit&id={$r->id}";
                        $desa_html = !empty($r->nama_desa) ? '<span class="dashicons dashicons-location" style="color:#2271b1;"></span> '.esc_html($r->nama_desa) : '-';
                    ?>
                    <tr>
                        <td><strong><a href="<?php echo $edit_url; ?>"><?php echo esc_html($r->nama_wisata); ?></a></strong></td>
                        <td><?php echo $desa_html; ?></td>
                        <td>Rp <?php echo number_format($r->harga_tiket); ?></td>
                        <td><?php echo $r->status; ?></td>
                        <td><a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}