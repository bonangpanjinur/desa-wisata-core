<?php
/**
 * Page: Manajemen Paket Transaksi
 * Database: dw_paket_transaksi (v3.7)
 * Features: CRUD (Create, Read, Update, Delete) dengan dukungan Komisi Nominal.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_render_paket_transaksi_page() {
    global $wpdb;
    $table_paket = $wpdb->prefix . 'dw_paket_transaksi';

    // --- 1. HANDLE POST (SIMPAN / UPDATE) ---
    if ( isset( $_POST['submit_paket'] ) && check_admin_referer( 'dw_save_paket' ) ) {
        $data = [
            'nama_paket'       => sanitize_text_field( $_POST['nama_paket'] ),
            'deskripsi'        => sanitize_textarea_field( $_POST['deskripsi'] ),
            'harga'            => floatval( $_POST['harga'] ),
            'jumlah_transaksi' => intval( $_POST['jumlah_transaksi'] ),
            'komisi_nominal'   => floatval( $_POST['komisi_nominal'] ), // Field Baru v3.7
            'status'           => 'aktif'
        ];
        $format = ['%s', '%s', '%f', '%d', '%f', '%s'];

        if ( ! empty( $_POST['paket_id'] ) ) {
            // UPDATE
            $wpdb->update( $table_paket, $data, ['id' => intval($_POST['paket_id'])], $format, ['%d'] );
            echo '<div class="notice notice-success is-dismissible"><p>Paket berhasil diperbarui.</p></div>';
        } else {
            // INSERT
            $wpdb->insert( $table_paket, $data, $format );
            echo '<div class="notice notice-success is-dismissible"><p>Paket baru berhasil disimpan.</p></div>';
        }
    }

    // --- 2. HANDLE DELETE ---
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
        $wpdb->delete( $table_paket, ['id' => intval($_GET['id'])], ['%d'] );
        echo '<div class="notice notice-success is-dismissible"><p>Paket berhasil dihapus.</p></div>';
    }

    // --- 3. PREPARE EDIT DATA ---
    $edit_data = null;
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
        $edit_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_paket WHERE id = %d", intval($_GET['id']) ) );
    }

    // --- 4. FETCH ALL PACKAGES ---
    $pakets = $wpdb->get_results( "SELECT * FROM $table_paket WHERE status = 'aktif' ORDER BY harga ASC" );
    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Paket & Komisi Referral</h1>
        <hr class="wp-header-end">
        
        <div style="display:flex; gap:20px; margin-top: 20px;">
            
            <!-- FORM INPUT (KIRI) -->
            <div class="dw-card" style="flex:1; background:#fff; padding:20px; border:1px solid #c3c4c7; height:fit-content;">
                <h2><?php echo $edit_data ? 'Edit Paket' : 'Tambah Paket Baru'; ?></h2>
                
                <form method="post" action="<?php echo remove_query_arg(['action', 'id']); ?>">
                    <?php wp_nonce_field( 'dw_save_paket' ); ?>
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="paket_id" value="<?php echo esc_attr($edit_data->id); ?>">
                    <?php endif; ?>

                    <div class="form-field" style="margin-bottom:15px;">
                        <label style="font-weight:600;">Nama Paket</label>
                        <input type="text" name="nama_paket" required class="widefat" 
                               value="<?php echo $edit_data ? esc_attr($edit_data->nama_paket) : ''; ?>"
                               placeholder="Contoh: Paket Starter">
                    </div>

                    <div class="form-field" style="margin-bottom:15px;">
                        <label style="font-weight:600;">Deskripsi</label>
                        <textarea name="deskripsi" class="widefat" rows="3"><?php echo $edit_data ? esc_textarea($edit_data->deskripsi) : ''; ?></textarea>
                    </div>

                    <div class="form-field" style="margin-bottom:15px;">
                        <label style="font-weight:600;">Harga (Rp)</label>
                        <input type="number" name="harga" required class="widefat" 
                               value="<?php echo $edit_data ? esc_attr($edit_data->harga) : ''; ?>"
                               placeholder="50000">
                    </div>

                    <div class="form-field" style="margin-bottom:15px;">
                        <label style="font-weight:600;">Kuota Transaksi (Kali)</label>
                        <input type="number" name="jumlah_transaksi" required class="widefat" 
                               value="<?php echo $edit_data ? esc_attr($edit_data->jumlah_transaksi) : ''; ?>"
                               placeholder="100">
                        <p class="description">Jumlah transaksi yang didapat pedagang.</p>
                    </div>

                    <!-- UPDATE: Input Komisi Nominal -->
                    <div style="background:#f0f6fc; padding:15px; border-left:4px solid #72aee6; margin-bottom:20px;">
                        <label style="font-weight:600; color:#1d2327;">Komisi Referral (Rp)</label>
                        <p class="description" style="margin:5px 0 10px;">Nominal Rupiah pasti yang akan masuk ke saldo Desa/Verifikator pemilik kode referral.</p>
                        <input type="number" name="komisi_nominal" required class="widefat" 
                               value="<?php echo $edit_data ? esc_attr($edit_data->komisi_nominal) : ''; ?>"
                               placeholder="5000">
                    </div>

                    <div style="display:flex; gap:10px;">
                        <button type="submit" name="submit_paket" class="button button-primary button-large" style="width:100%;">
                            <?php echo $edit_data ? 'Update Paket' : 'Simpan Paket'; ?>
                        </button>
                        <?php if ($edit_data): ?>
                            <a href="<?php echo remove_query_arg(['action', 'id']); ?>" class="button button-secondary button-large">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- TABEL LIST (KANAN) -->
            <div class="dw-card" style="flex:2; background:#fff; padding:0; border:1px solid #c3c4c7;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Nama Paket</th>
                            <th>Harga</th>
                            <th>Kuota</th>
                            <th>Komisi (Rp)</th>
                            <th style="width:120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $pakets ) ) : ?>
                            <tr><td colspan="5" style="padding:20px; text-align:center;">Belum ada paket tersedia.</td></tr>
                        <?php else : ?>
                            <?php foreach ( $pakets as $p ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $p->nama_paket ); ?></strong><br>
                                    <small style="color:#646970;"><?php echo esc_html( $p->deskripsi ); ?></small>
                                </td>
                                <td>Rp <?php echo number_format( $p->harga, 0, ',', '.' ); ?></td>
                                <td><?php echo number_format( $p->jumlah_transaksi ); ?></td>
                                <td>
                                    <span style="color: #008a20; font-weight: bold; background: #e7f7ed; padding: 2px 6px; border-radius: 4px;">
                                        + Rp <?php echo number_format( $p->komisi_nominal, 0, ',', '.' ); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?page=dw-paket-transaksi&action=edit&id=<?php echo $p->id; ?>" class="button button-small">Edit</a>
                                    <a href="?page=dw-paket-transaksi&action=delete&id=<?php echo $p->id; ?>" 
                                       class="button button-small button-link-delete" 
                                       style="color: #b32d2e;"
                                       onclick="return confirm('Hapus paket ini selamanya?')">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
?>