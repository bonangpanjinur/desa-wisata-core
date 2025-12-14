<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'dw_wisata';

// --- HANDLE POST ---
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wisata'])) {
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_wisata_action')) die('Security Error');

    $action = $_POST['action_wisata'];

    if ($action === 'delete') {
        $wpdb->delete($table_name, ['id' => intval($_POST['id'])]);
        $message = 'Wisata dihapus.';
    } elseif ($action === 'save') {
        
        // Array Data
        $data = [
            'id_desa'     => intval($_POST['id_desa']),
            'nama_wisata' => sanitize_text_field($_POST['nama_wisata']),
            'slug'        => sanitize_title($_POST['nama_wisata']),
            'deskripsi'   => wp_kses_post($_POST['deskripsi']),
            'harga_tiket' => floatval($_POST['harga_tiket']), // Pastikan float/int
            'jam_buka'    => sanitize_text_field($_POST['jam_buka']),
            'jam_tutup'   => sanitize_text_field($_POST['jam_tutup']),
            'fasilitas'   => sanitize_textarea_field($_POST['fasilitas']), // Bisa dipisah koma
            'lokasi_maps' => esc_url_raw($_POST['lokasi_maps']),
            'foto_utama'  => esc_url_raw($_POST['foto_utama']),
            // Galeri bisa disimpan sebagai JSON array URL
            'galeri'      => isset($_POST['galeri']) ? json_encode(array_map('esc_url_raw', $_POST['galeri'])) : '[]',
            'status'      => sanitize_text_field($_POST['status']),
            'updated_at'  => current_time('mysql')
        ];

        if (!empty($_POST['wisata_id'])) {
            $wpdb->update($table_name, $data, ['id' => intval($_POST['wisata_id'])]);
            $message = 'Wisata diperbarui.';
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table_name, $data);
            $message = 'Wisata ditambahkan.';
        }
    }
}

// --- VIEW HELPER ---
$is_edit = isset($_GET['action']) && $_GET['action'] == 'edit';
$data = null;
if ($is_edit) {
    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
}

// Ambil List Desa untuk Dropdown
$list_desa = $wpdb->get_results("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE status='aktif'");
?>

<div class="wrap">
    <h1>Manajemen Objek Wisata</h1>
    
    <?php if ($message) echo "<div class='notice notice-success is-dismissible'><p>$message</p></div>"; ?>

    <?php if (isset($_GET['action']) && ($_GET['action'] == 'new' || $is_edit)): ?>
        <div class="card" style="padding: 20px; max-width: 800px;">
            <form method="post">
                <?php wp_nonce_field('dw_wisata_action'); ?>
                <input type="hidden" name="action_wisata" value="save">
                <?php if ($data) echo '<input type="hidden" name="wisata_id" value="'.$data->id.'">'; ?>

                <table class="form-table">
                    <tr>
                        <th>Nama Wisata</th>
                        <td><input type="text" name="nama_wisata" class="regular-text" required value="<?php echo esc_attr($data->nama_wisata ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th>Desa Pemilik</th>
                        <td>
                            <select name="id_desa" required>
                                <option value="">-- Pilih Desa --</option>
                                <?php foreach ($list_desa as $d): ?>
                                    <option value="<?php echo $d->id; ?>" <?php selected($data->id_desa ?? '', $d->id); ?>>
                                        <?php echo esc_html($d->nama_desa); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Harga Tiket (Rp)</th>
                        <td><input type="number" name="harga_tiket" value="<?php echo esc_attr($data->harga_tiket ?? 0); ?>"></td>
                    </tr>
                    <tr>
                        <th>Jam Operasional</th>
                        <td>
                            <input type="time" name="jam_buka" value="<?php echo esc_attr($data->jam_buka ?? '08:00'); ?>"> s/d 
                            <input type="time" name="jam_tutup" value="<?php echo esc_attr($data->jam_tutup ?? '17:00'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>Google Maps Link</th>
                        <td><input type="url" name="lokasi_maps" class="large-text" value="<?php echo esc_url($data->lokasi_maps ?? ''); ?>" placeholder="https://maps.google.com/..."></td>
                    </tr>
                    <tr>
                        <th>Deskripsi</th>
                        <td><?php wp_editor($data->deskripsi ?? '', 'deskripsi', ['textarea_rows' => 5]); ?></td>
                    </tr>
                    <tr>
                        <th>Foto Utama</th>
                        <td>
                            <input type="text" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($data->foto_utama ?? ''); ?>" class="regular-text">
                            <button type="button" class="button" onclick="uploadImage('foto_utama')">Upload</button>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <select name="status">
                                <option value="aktif" <?php selected($data->status ?? '', 'aktif'); ?>>Aktif</option>
                                <option value="tutup" <?php selected($data->status ?? '', 'tutup'); ?>>Tutup Sementara</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <hr>
                <button type="submit" class="button button-primary">Simpan Data</button>
                <a href="<?php echo admin_url('admin.php?page=dw-wisata'); ?>" class="button">Kembali</a>
            </form>
        </div>

        <script>
            function uploadImage(targetId) {
                var frame = wp.media({
                    title: 'Pilih Gambar',
                    button: { text: 'Gunakan' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    document.getElementById(targetId).value = attachment.url;
                });
                frame.open();
            }
        </script>

    <?php else: ?>
        <!-- Tampilkan Tabel List Wisata disini (Gunakan class list table seperti di page-desa.php) -->
        <a href="<?php echo admin_url('admin.php?page=dw-wisata&action=new'); ?>" class="page-title-action">Tambah Wisata</a>
        <?php
            require_once DW_CORE_PATH . 'includes/list-tables/class-dw-wisata-list-table.php'; // Pastikan file ini ada atau buat baru
            // ... render list table ...
        ?>
    <?php endif; ?>
</div>