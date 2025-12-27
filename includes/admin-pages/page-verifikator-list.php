<?php
/**
 * File Name:   page-verifikator-list.php
 * File Folder: includes/admin-pages/
 * Description: Menampilkan dan mengelola daftar verifikator UMKM serta verifikator Desa.
 * Menangani pendaftaran verifikator baru dan pelacakan komisi.
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) exit;

function dw_page_verifikator_list() {
    global $wpdb;
    $table_verifikator = $wpdb->prefix . 'dw_verifikator';

    // Handle Form Submission untuk Verifikator Baru
    if (isset($_POST['add_verifikator'])) {
        $user_id = intval($_POST['user_id']);
        $kode = sanitize_text_field($_POST['kode_referal']);
        $nama = sanitize_text_field($_POST['nama_lengkap']);
        $tipe = sanitize_text_field($_POST['tipe']);

        $wpdb->insert($table_verifikator, array(
            'user_id' => $user_id,
            'nama_lengkap' => $nama,
            'kode_referal' => $kode,
            'tipe_verifikator' => $tipe,
            'status' => 'aktif'
        ));
        echo '<div class="updated"><p>Verifikator berhasil ditambahkan!</p></div>';
    }

    $verifikators = $wpdb->get_results("SELECT * FROM $table_verifikator ORDER BY id DESC");
    ?>
    <div class="wrap">
        <h1>Manajemen Verifikator UMKM & Desa</h1>
        
        <div class="card" style="max-width: 500px; padding: 20px; margin-bottom: 20px;">
            <h3>Tambah Verifikator Baru</h3>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th>User WP (ID)</th>
                        <td><input type="number" name="user_id" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Nama Lengkap</th>
                        <td><input type="text" name="nama_lengkap" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Kode Referal</th>
                        <td><input type="text" name="kode_referal" placeholder="Contoh: UMKM001" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Tipe</th>
                        <td>
                            <select name="tipe">
                                <option value="umkm">Verifikator UMKM</option>
                                <option value="desa">Verifikator Desa</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p><input type="submit" name="add_verifikator" class="button button-primary" value="Simpan Verifikator"></p>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Verifikator</th>
                    <th>Kode Referal</th>
                    <th>Tipe</th>
                    <th>Status</th>
                    <th>Komisi Diterima</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($verifikators): foreach ($verifikators as $v): ?>
                <tr>
                    <td><?php echo $v->id; ?></td>
                    <td><?php echo esc_html($v->nama_lengkap); ?></td>
                    <td><strong><?php echo esc_html($v->kode_referal); ?></strong></td>
                    <td><?php echo strtoupper($v->tipe_verifikator); ?></td>
                    <td><?php echo $v->status; ?></td>
                    <td>Rp <?php echo number_format($v->total_komisi_diterima, 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6">Belum ada verifikator.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}