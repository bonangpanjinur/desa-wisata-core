<?php
/**
 * Page: Manajemen Paket Transaksi
 * Description: CRUD Paket Kuota untuk Pedagang/Desa/Verifikator
 * Update: Perbaikan Label "Komisi Desa" menjadi "Komisi Desa/Verifikator"
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_paket = $wpdb->prefix . 'dw_paket_transaksi';

// Handle Form Submission (Tambah/Edit)
if ( isset( $_POST['submit_paket'] ) && check_admin_referer( 'dw_save_paket' ) ) {
    $nama      = sanitize_text_field( $_POST['nama_paket'] );
    $deskripsi = sanitize_textarea_field( $_POST['deskripsi'] );
    $harga     = floatval( $_POST['harga'] );
    $jumlah    = intval( $_POST['jumlah_transaksi'] );
    $komisi    = floatval( $_POST['persentase_komisi'] ); // Menggunakan field komisi gabungan
    
    $data = array(
        'nama_paket' => $nama,
        'deskripsi' => $deskripsi,
        'harga' => $harga,
        'jumlah_transaksi' => $jumlah,
        'persentase_komisi_desa' => $komisi, // Disimpan di kolom database yg lama tapi maknanya baru
        'status' => 'aktif'
    );

    if ( isset( $_POST['paket_id'] ) && ! empty( $_POST['paket_id'] ) ) {
        $wpdb->update( $table_paket, $data, array( 'id' => intval( $_POST['paket_id'] ) ) );
        echo '<div class="notice notice-success"><p>Paket berhasil diperbarui.</p></div>';
    } else {
        $wpdb->insert( $table_paket, $data );
        echo '<div class="notice notice-success"><p>Paket baru berhasil dibuat.</p></div>';
    }
}

// Handle Delete
if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['id'] ) ) {
    $wpdb->delete( $table_paket, array( 'id' => intval( $_GET['id'] ) ) );
    echo '<div class="notice notice-success"><p>Paket dihapus.</p></div>';
}

$pakets = $wpdb->get_results( "SELECT * FROM $table_paket ORDER BY harga ASC" );
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Manajemen Paket Transaksi & Kuota</h1>
    
    <div style="display: flex; gap: 20px; margin-top: 20px;">
        <!-- Form Tambah/Edit -->
        <div class="dw-card" style="flex: 1; max-width: 400px;">
            <h3>Tambah / Edit Paket</h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'dw_save_paket' ); ?>
                
                <div class="form-field">
                    <label>Nama Paket</label>
                    <input type="text" name="nama_paket" required class="widefat">
                </div>
                <br>
                <div class="form-field">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" class="widefat" rows="3"></textarea>
                </div>
                <br>
                <div class="form-field">
                    <label>Harga (Rp)</label>
                    <input type="number" name="harga" required class="widefat">
                </div>
                <br>
                <div class="form-field">
                    <label>Jumlah Kuota Transaksi</label>
                    <input type="number" name="jumlah_transaksi" required class="widefat">
                    <p class="description">Berapa kali pedagang bisa menerima pesanan.</p>
                </div>
                <br>
                <div class="form-field">
                    <label><strong>Persentase Komisi Desa / Verifikator (%)</strong></label>
                    <input type="number" step="0.1" name="persentase_komisi" required class="widefat" value="0">
                    <p class="description">
                        Persentase ini akan diberikan kepada pihak yang mereferensikan pedagang (Entah itu Kas Desa atau Dompet Verifikator).<br>
                        <em>Contoh: Isi 10 untuk 10%.</em>
                    </p>
                </div>
                <br>
                <button type="submit" name="submit_paket" class="button button-primary">Simpan Paket</button>
            </form>
        </div>

        <!-- Tabel List -->
        <div class="dw-card" style="flex: 2;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nama Paket</th>
                        <th>Harga</th>
                        <th>Kuota</th>
                        <th>Komisi (Desa/Verif)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $pakets ) ) : ?>
                        <tr><td colspan="5">Belum ada paket tersedia.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $pakets as $p ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $p->nama_paket ); ?></strong><br>
                                <small><?php echo esc_html( $p->deskripsi ); ?></small>
                            </td>
                            <td>Rp <?php echo number_format( $p->harga, 0, ',', '.' ); ?></td>
                            <td><?php echo number_format( $p->jumlah_transaksi ); ?></td>
                            <td><?php echo floatval( $p->persentase_komisi_desa ); ?>%</td>
                            <td>
                                <a href="?page=dw-paket-transaksi&action=delete&id=<?php echo $p->id; ?>" class="button button-small button-link-delete" onclick="return confirm('Hapus paket ini?')">Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .dw-card {
        background: #fff;
        padding: 20px;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
</style>