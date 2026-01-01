<?php
/**
 * File Name:   includes/admin-pages/page-paket-transaksi.php
 * Description: Manajemen Paket Transaksi & Setting Kuota Gratis Pendaftaran.
 * UPDATE: Menambahkan pengaturan kuota gratis dinamis berdasarkan sumber pendaftaran.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function dw_render_paket_transaksi_page() {
    global $wpdb;
    $table_paket = $wpdb->prefix . 'dw_paket_transaksi';

    // --- 1. HANDLE POST: SAVE SETTINGS (KUOTA GRATIS) ---
    if ( isset($_POST['save_quota_settings']) && check_admin_referer('dw_save_quota_settings') ) {
        // Simpan ke WP Options agar bisa diakses global
        update_option('dw_quota_free_default', intval($_POST['quota_default']));
        update_option('dw_quota_free_via_desa', intval($_POST['quota_desa']));
        update_option('dw_quota_free_via_verif', intval($_POST['quota_verif']));
        
        echo '<div class="notice notice-success is-dismissible"><p>Pengaturan Kuota Gratis berhasil disimpan.</p></div>';
    }

    // --- 2. HANDLE POST: CRUD PAKET BERBAYAR ---
    if ( isset( $_POST['submit_paket'] ) && check_admin_referer( 'dw_save_paket' ) ) {
        // Validasi Input
        $nama_paket = sanitize_text_field( $_POST['nama_paket'] );
        $harga = floatval( $_POST['harga'] );
        $komisi_nominal = floatval( $_POST['komisi_nominal'] );
        $persentase = floatval( $_POST['persentase_komisi'] );

        // Logic Check: Prioritas Komisi
        if ($komisi_nominal > $harga) {
            $komisi_nominal = $harga; // Safety
        }

        $data = [
            'nama_paket'        => $nama_paket,
            'deskripsi'         => sanitize_textarea_field( $_POST['deskripsi'] ),
            'harga'             => $harga,
            'jumlah_transaksi'  => intval( $_POST['jumlah_transaksi'] ),
            'target_role'       => sanitize_text_field( $_POST['target_role'] ),
            'komisi_nominal'    => $komisi_nominal,     
            'persentase_komisi' => $persentase,         
            'status'            => 'aktif'
        ];
        
        $format = ['%s', '%s', '%f', '%d', '%s', '%f', '%f', '%s'];

        if ( ! empty( $_POST['paket_id'] ) ) {
            // UPDATE
            $wpdb->update( $table_paket, $data, ['id' => intval($_POST['paket_id'])], $format, ['%d'] );
            echo '<div class="notice notice-success is-dismissible"><p>Paket berhasil diperbarui.</p></div>';
        } else {
            // INSERT
            $wpdb->insert( $table_paket, $data, $format );
            echo '<div class="notice notice-success is-dismissible"><p>Paket baru berhasil dibuat.</p></div>';
        }
    }

    // --- 3. HANDLE DELETE PAKET ---
    if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['id'] ) ) {
        $id_del = intval( $_GET['id'] );
        $wpdb->delete( $table_paket, ['id' => $id_del] );
        echo '<div class="notice notice-success is-dismissible"><p>Paket dihapus.</p></div>';
    }

    // --- 4. PREPARE DATA ---
    // Data Edit Paket
    $edit_data = null;
    if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['id'] ) ) {
        $edit_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_paket WHERE id = %d", intval( $_GET['id'] ) ) );
    }

    // Data Settings Kuota (Default Value jika belum diset)
    $q_default = get_option('dw_quota_free_default', 0);   // Default 0 jika tidak ada referral
    $q_desa    = get_option('dw_quota_free_via_desa', 50); // Default 50 jika via Desa
    $q_verif   = get_option('dw_quota_free_via_verif', 50); // Default 50 jika via Verifikator

    // --- 5. VIEW ---
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Paket & Insentif Pendaftaran</h1>
        <hr class="wp-header-end">

        <!-- SECTION 1: PENGATURAN KUOTA GRATIS (GLOBAL) -->
        <div class="dw-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px; border-left: 4px solid #2271b1;">
            <h2 style="margin-top: 0;">🎁 Pengaturan Kuota Gratis (Insentif Pendaftaran Baru)</h2>
            <p>Tentukan jumlah kuota transaksi gratis yang didapatkan Pedagang saat pertama kali mendaftar. Gunakan ini sebagai strategi marketing agar Desa/Verifikator semangat mengajak pedagang.</p>
            
            <form method="post" style="margin-top: 15px;">
                <?php wp_nonce_field('dw_save_quota_settings'); ?>
                <input type="hidden" name="save_quota_settings" value="1">
                
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div>
                        <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #666;">Daftar Mandiri (Tanpa Referral)</label>
                        <input type="number" name="quota_default" value="<?php echo esc_attr($q_default); ?>" class="small-text" min="0">
                        <span class="description"> Transaksi</span>
                    </div>
                    
                    <div>
                        <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #d63638;">Via Link Verifikator</label>
                        <input type="number" name="quota_verif" value="<?php echo esc_attr($q_verif); ?>" class="small-text" min="0">
                        <span class="description"> Transaksi</span>
                    </div>

                    <div>
                        <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #00ba37;">Via Link Desa</label>
                        <input type="number" name="quota_desa" value="<?php echo esc_attr($q_desa); ?>" class="small-text" min="0">
                        <span class="description"> Transaksi</span>
                    </div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Simpan Pengaturan Kuota</button>
                </p>
            </form>
        </div>

        <!-- SECTION 2: CRUD PAKET BERBAYAR -->
        <div class="dw-grid-container" style="display: flex; gap: 20px; margin-top: 30px;">
            
            <!-- FORM INPUT PAKET -->
            <div class="dw-card" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top:0;"><?php echo $edit_data ? 'Edit Paket Berbayar' : 'Buat Paket Berbayar Baru'; ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'dw_save_paket' ); ?>
                    <input type="hidden" name="paket_id" value="<?php echo $edit_data ? $edit_data->id : ''; ?>">

                    <table class="form-table">
                        <tr>
                            <th><label>Nama Paket</label></th>
                            <td><input type="text" name="nama_paket" class="regular-text" required value="<?php echo $edit_data ? esc_attr($edit_data->nama_paket) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Target Pengguna</label></th>
                            <td>
                                <select name="target_role">
                                    <option value="pedagang" <?php echo ($edit_data && $edit_data->target_role == 'pedagang') ? 'selected' : ''; ?>>Pedagang</option>
                                    <option value="ojek" <?php echo ($edit_data && $edit_data->target_role == 'ojek') ? 'selected' : ''; ?>>Ojek Lokal</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Harga Paket (Rp)</label></th>
                            <td><input type="number" name="harga" class="regular-text" required min="0" value="<?php echo $edit_data ? floatval($edit_data->harga) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Kuota Transaksi</label></th>
                            <td>
                                <input type="number" name="jumlah_transaksi" class="regular-text" required min="1" value="<?php echo $edit_data ? intval($edit_data->jumlah_transaksi) : ''; ?>">
                                <p class="description">Jumlah transaksi sukses yang didapat.</p>
                            </td>
                        </tr>
                        
                        <!-- SETTING KOMISI -->
                        <tr>
                            <th colspan="2" style="padding-top:20px; border-bottom:1px solid #eee;">
                                <h3 style="margin:0;">Pengaturan Bagi Hasil (Referral)</h3>
                                <p class="description" style="margin-top:5px;">
                                    Komisi ini diberikan kepada <strong>Desa</strong> ATAU <strong>Verifikator</strong> saat pedagang membeli paket ini.
                                </p>
                            </th>
                        </tr>
                        <tr>
                            <th><label>Komisi Nominal (Rp)</label></th>
                            <td>
                                <input type="number" name="komisi_nominal" class="regular-text" min="0" value="<?php echo $edit_data ? floatval($edit_data->komisi_nominal) : '0'; ?>">
                                <p class="description">Prioritas Utama. Contoh: Rp 5.000 per penjualan paket.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Persentase Komisi (%)</label></th>
                            <td>
                                <input type="number" name="persentase_komisi" class="regular-text" min="0" max="100" step="0.1" value="<?php echo $edit_data ? floatval($edit_data->persentase_komisi) : '0'; ?>">
                                <p class="description">Opsional. Digunakan jika Komisi Nominal = 0.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label>Deskripsi</label></th>
                            <td><textarea name="deskripsi" class="large-text" rows="3"><?php echo $edit_data ? esc_textarea($edit_data->deskripsi) : ''; ?></textarea></td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="submit_paket" class="button button-primary"><?php echo $edit_data ? 'Update Paket' : 'Simpan Paket'; ?></button>
                        <?php if($edit_data): ?>
                            <a href="?page=dw-paket-transaksi" class="button button-secondary">Batal</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <!-- TABEL LIST PAKET -->
            <div class="dw-list" style="flex: 2;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Harga</th>
                            <th>Kuota</th>
                            <th>Komisi Referrer</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $pakets = $wpdb->get_results( "SELECT * FROM $table_paket ORDER BY id DESC" );
                        if ( empty( $pakets ) ) : ?>
                            <tr><td colspan="5">Belum ada paket.</td></tr>
                        <?php else : foreach ( $pakets as $p ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $p->nama_paket ); ?></strong>
                                    <br>
                                    <small style="color:#666;"><?php echo esc_html( $p->target_role ); ?></small>
                                </td>
                                <td>Rp <?php echo number_format( $p->harga, 0, ',', '.' ); ?></td>
                                <td><?php echo number_format( $p->jumlah_transaksi ); ?> Trx</td>
                                <td>
                                    <?php if ( $p->komisi_nominal > 0 ) : ?>
                                        <span style="color: #135e96; font-weight: bold; background: #e7f7ed; padding: 2px 6px; border-radius: 4px;">
                                            Rp <?php echo number_format( $p->komisi_nominal, 0, ',', '.' ); ?>
                                        </span>
                                    <?php elseif ( $p->persentase_komisi > 0 ) : ?>
                                        <span style="color: #135e96; font-weight: bold; background: #e7f7ed; padding: 2px 6px; border-radius: 4px;">
                                            <?php echo $p->persentase_komisi; ?>%
                                        </span>
                                    <?php else : ?>
                                        <span style="color:#999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=dw-paket-transaksi&action=edit&id=<?php echo $p->id; ?>" class="button button-small">Edit</a>
                                    <a href="?page=dw-paket-transaksi&action=delete&id=<?php echo $p->id; ?>" 
                                       class="button button-small button-link-delete" 
                                       style="color: #b32d2e;"
                                       onclick="return confirm('Hapus paket ini selamanya?')">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}