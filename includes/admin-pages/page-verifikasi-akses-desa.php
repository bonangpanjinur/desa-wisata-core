<?php
/**
 * Page: Verifikasi Akses Desa & Pengaturan
 * Description: Halaman untuk admin memverifikasi pembayaran akses desa dan mengatur konfigurasi harga/tampilan.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Wrapper function untuk merender halaman.
 * Dipanggil dari admin-menus.php
 */
function dw_verifikasi_akses_desa_page_render() {

    // Pastikan user memiliki akses
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.', 'desa-wisata' ) );
    }

    global $wpdb;
    $table_desa = $wpdb->prefix . 'dw_desa';
    $table_logs = $wpdb->prefix . 'dw_logs';

    // --- 1. HANDLE FORM SUBMISSION (PENGATURAN) ---
    if ( isset( $_POST['dw_save_verif_settings'] ) && check_admin_referer( 'dw_verif_settings_action', 'dw_verif_settings_nonce' ) ) {
        $settings = array(
            'enable_paid_access' => isset($_POST['enable_paid_access']) ? 1 : 0,
            'access_price'       => sanitize_text_field( $_POST['access_price'] ),
            'wa_confirm'         => sanitize_text_field( $_POST['wa_confirm'] ),
            
            // Wording / Kata-kata
            'text_locked_title'  => sanitize_text_field( $_POST['text_locked_title'] ),
            'text_locked_desc'   => wp_kses_post( $_POST['text_locked_desc'] ),
            'text_cta_btn'       => sanitize_text_field( $_POST['text_cta_btn'] ),
            'text_pending_msg'   => sanitize_text_field( $_POST['text_pending_msg'] ),
        );

        update_option( 'dw_verif_access_settings', $settings );
        
        // Log Aktivitas
        $wpdb->insert($table_logs, array(
            'user_id'    => get_current_user_id(),
            'aksi'       => 'update_settings',
            'keterangan' => 'Admin memperbarui pengaturan Verifikasi Akses Desa.'
        ));

        echo '<div class="notice notice-success is-dismissible"><p>Pengaturan berhasil disimpan.</p></div>';
    }

    // --- 2. HANDLE ACTIONS (APPROVE via GET) ---
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'approve' && isset( $_GET['desa_id'] ) && check_admin_referer( 'dw_verif_action' ) ) {
        $desa_id = absint( $_GET['desa_id'] );
        
        // Ambil data desa untuk email notifikasi
        $desa_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_desa WHERE id = %d", $desa_id ) );

        if ( $desa_data ) {
            // Update Status Active & Bersihkan Alasan Penolakan (jika ada sisa masa lalu)
            $wpdb->update(
                $table_desa,
                array( 
                    'status_akses_verifikasi' => 'active',
                    'alasan_penolakan'        => null // Reset alasan penolakan
                ),
                array( 'id' => $desa_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            // Log
            $wpdb->insert($table_logs, array(
                'user_id'    => get_current_user_id(),
                'aksi'       => 'approve_akses_desa',
                'keterangan' => "Menyetujui akses untuk Desa ID: $desa_id ({$desa_data->nama_desa})"
            ));

            // Kirim Email Approve (Opsional, tetap dikirim untuk approve)
            $user_desa = get_userdata( $desa_data->id_user_desa );
            if ( $user_desa ) {
                $message = "Halo Admin Desa " . $desa_data->nama_desa . ",\n\nPermintaan verifikasi akses fitur Anda telah DISETUJUI.\nTerima kasih.";
                wp_mail( $user_desa->user_email, "Akses Desa Wisata Disetujui", $message );
            }

            echo '<div class="notice notice-success is-dismissible"><p>Akses Desa berhasil <strong>DISETUJUI</strong>.</p></div>';
        }
    }

    // --- 3. HANDLE REJECTION (VIA POST FORM) ---
    if ( isset( $_POST['dw_reject_submit'] ) && check_admin_referer( 'dw_reject_action', 'dw_reject_nonce' ) ) {
        $desa_id = absint( $_POST['reject_desa_id'] );
        $alasan  = sanitize_textarea_field( $_POST['alasan_tolak'] );
        
        $desa_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_desa WHERE id = %d", $desa_id ) );

        if ( $desa_data ) {
            // Update Status Kembali ke Locked & Simpan Alasan ke Database
            $wpdb->update(
                $table_desa,
                array( 
                    'status_akses_verifikasi' => 'locked',
                    'alasan_penolakan'        => $alasan // Simpan langsung di kolom tabel
                ), 
                array( 'id' => $desa_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            // Log
            $wpdb->insert($table_logs, array(
                'user_id'    => get_current_user_id(),
                'aksi'       => 'reject_akses_desa',
                'keterangan' => "Menolak akses Desa ID: $desa_id. Alasan: $alasan"
            ));

            echo '<div class="notice notice-warning is-dismissible"><p>Permintaan akses <strong>DITOLAK</strong>. Alasan telah disimpan di database.</p></div>';
        }
    }

    // --- 4. GET DATA & SETTINGS ---
    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'requests';
    $settings    = get_option( 'dw_verif_access_settings', array() );

    // Default Values
    $defaults = array(
        'enable_paid_access' => 1,
        'access_price'       => '150000',
        'wa_confirm'         => '',
        'text_locked_title'  => 'Akses Fitur Terkunci',
        'text_locked_desc'   => 'Maaf, akun Desa Anda belum memiliki akses ke fitur Verifikasi Pedagang. Silakan lakukan pembayaran biaya aktivasi untuk membuka fitur ini selamanya.',
        'text_cta_btn'       => 'Buka Akses Sekarang',
        'text_pending_msg'   => 'Pembayaran Anda sedang kami verifikasi. Mohon tunggu maksimal 1x24 jam.',
    );
    $settings = wp_parse_args( $settings, $defaults );

    // Query Data
    $pending_requests = $wpdb->get_results( "SELECT * FROM $table_desa WHERE status_akses_verifikasi = 'pending' ORDER BY updated_at DESC" );
    $history_requests = $wpdb->get_results( "SELECT * FROM $table_desa WHERE status_akses_verifikasi = 'active' ORDER BY updated_at DESC LIMIT 20" );

    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Verifikasi Akses Desa</h1>
        <hr class="wp-header-end">

        <nav class="nav-tab-wrapper">
            <a href="?page=dw-verifikasi-akses-desa&tab=requests" class="nav-tab <?php echo $current_tab == 'requests' ? 'nav-tab-active' : ''; ?>">
                Permintaan Masuk 
                <?php if(count($pending_requests) > 0): ?>
                    <span class="awaiting-mod count-<?php echo count($pending_requests); ?>"><span class="pending-count"><?php echo count($pending_requests); ?></span></span>
                <?php endif; ?>
            </a>
            <a href="?page=dw-verifikasi-akses-desa&tab=history" class="nav-tab <?php echo $current_tab == 'history' ? 'nav-tab-active' : ''; ?>">Riwayat Aktif</a>
            <a href="?page=dw-verifikasi-akses-desa&tab=settings" class="nav-tab <?php echo $current_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Pengaturan & Tampilan</a>
        </nav>

        <div class="dw-admin-content" style="margin-top: 20px;">
            
            <!-- TAB 1: PERMINTAAN MASUK -->
            <?php if ( $current_tab == 'requests' ) : ?>
                
                <?php if ( empty( $pending_requests ) ) : ?>
                    <div class="dw-empty-state" style="text-align: center; padding: 50px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                        <span class="dashicons dashicons-yes" style="font-size: 48px; width: 48px; height: 48px; color: #46b450;"></span>
                        <h3>Tidak ada permintaan baru</h3>
                        <p>Semua permintaan akses desa telah diproses.</p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="20%">Nama Desa</th>
                                <th width="15%">Lokasi</th>
                                <th width="25%">Bukti Pembayaran</th>
                                <th width="15%">Waktu Request</th>
                                <th width="25%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $pending_requests as $row ) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $row->nama_desa ); ?></strong><br>
                                        <?php 
                                            $u = get_userdata($row->id_user_desa);
                                            echo '<small class="description">User: ' . ($u ? $u->user_login : 'Unknown') . '</small>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $row->kecamatan . ', ' . $row->kabupaten ); ?>
                                    </td>
                                    <td>
                                        <?php if ( ! empty( $row->bukti_bayar_akses ) ) : ?>
                                            <a href="<?php echo esc_url( $row->bukti_bayar_akses ); ?>" target="_blank">
                                                <img src="<?php echo esc_url( $row->bukti_bayar_akses ); ?>" style="max-width: 120px; border: 1px solid #ddd; padding: 3px; border-radius: 4px;">
                                                <br><small><span class="dashicons dashicons-visibility"></span> Lihat Penuh</small>
                                            </a>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-warning" style="color: orange;"></span> <span style="color:#a00;">Tidak ada bukti</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date_i18n( 'd M Y', strtotime( $row->updated_at ) ); ?><br>
                                        <small><?php echo date_i18n( 'H:i', strtotime( $row->updated_at ) ); ?> WIB</small>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <!-- Tombol Terima (GET Link) -->
                                            <?php $nonce_url = wp_nonce_url( admin_url( 'admin.php?page=dw-verifikasi-akses-desa&tab=requests&action=approve&desa_id=' . $row->id ), 'dw_verif_action' ); ?>
                                            <a href="<?php echo $nonce_url; ?>" class="button button-primary" onclick="return confirm('Setujui akses untuk desa ini?');">
                                                <span class="dashicons dashicons-yes" style="margin-top:4px;"></span> Terima
                                            </a>
                                            
                                            <!-- Tombol Tolak (Modal Trigger) -->
                                            <button type="button" class="button button-secondary" style="color: #a00; border-color: #d63638;" 
                                                onclick="openRejectModal(<?php echo $row->id; ?>, '<?php echo esc_js($row->nama_desa); ?>')">
                                                <span class="dashicons dashicons-no" style="margin-top:4px;"></span> Tolak
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <!-- TAB 2: RIWAYAT -->
            <?php elseif ( $current_tab == 'history' ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Nama Desa</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th>Disetujui Pada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $history_requests ) ) : ?>
                            <tr><td colspan="4">Belum ada riwayat desa aktif.</td></tr>
                        <?php else : ?>
                            <?php foreach ( $history_requests as $row ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $row->nama_desa ); ?></strong></td>
                                    <td><?php echo esc_html( $row->kabupaten ); ?></td>
                                    <td>
                                        <span style="background: #cfdec4; color: #3b6e22; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px;">
                                            <span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px;"></span> AKTIF
                                        </span>
                                    </td>
                                    <td><?php echo date_i18n( 'd M Y H:i', strtotime( $row->updated_at ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <!-- TAB 3: PENGATURAN -->
            <?php elseif ( $current_tab == 'settings' ) : ?>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'dw_verif_settings_action', 'dw_verif_settings_nonce' ); ?>
                    
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <!-- Kolom Kiri: Form -->
                        <div style="flex: 2; min-width: 300px;">
                            <div class="card" style="padding: 0 20px 20px; margin-top: 0;">
                                <h3>Konfigurasi Umum</h3>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Status Fitur Berbayar</th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="enable_paid_access" value="1" <?php checked( $settings['enable_paid_access'], 1 ); ?>>
                                                Aktifkan Pembayaran untuk Akses
                                            </label>
                                            <p class="description">Jika tidak dicentang, sistem frontend mungkin akan meloloskan semua desa.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Harga Akses (Rp)</th>
                                        <td>
                                            <input type="number" name="access_price" value="<?php echo esc_attr( $settings['access_price'] ); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nomor WhatsApp Konfirmasi</th>
                                        <td>
                                            <input type="text" name="wa_confirm" value="<?php echo esc_attr( $settings['wa_confirm'] ); ?>" class="regular-text" placeholder="628xxxxxxxx">
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="card" style="padding: 0 20px 20px;">
                                <h3>Pengaturan Teks (Wording)</h3>
                                <p class="description">Kustomisasi pesan yang tampil di dashboard Desa saat status mereka masih <strong>Locked</strong> atau <strong>Pending</strong>.</p>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Judul Halaman (Locked)</th>
                                        <td>
                                            <input type="text" name="text_locked_title" value="<?php echo esc_attr( $settings['text_locked_title'] ); ?>" class="large-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Deskripsi (Locked)</th>
                                        <td>
                                            <?php 
                                            wp_editor( $settings['text_locked_desc'], 'text_locked_desc', array(
                                                'textarea_rows' => 5,
                                                'media_buttons' => false,
                                                'teeny' => true
                                            ) ); 
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Label Tombol (CTA)</th>
                                        <td>
                                            <input type="text" name="text_cta_btn" value="<?php echo esc_attr( $settings['text_cta_btn'] ); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Pesan Saat Pending</th>
                                        <td>
                                            <textarea name="text_pending_msg" class="large-text" rows="2"><?php echo esc_textarea( $settings['text_pending_msg'] ); ?></textarea>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <p class="submit">
                                <input type="submit" name="dw_save_verif_settings" id="submit" class="button button-primary" value="Simpan Pengaturan">
                            </p>
                        </div>

                        <!-- Kolom Kanan: Live Preview -->
                        <div style="flex: 1; min-width: 300px;">
                            <div class="postbox">
                                <div class="postbox-header"><h2 class="hndle">Preview Tampilan Desa</h2></div>
                                <div class="inside" style="background: #f0f0f1; padding: 20px;">
                                    
                                    <!-- Preview State: Locked -->
                                    <h4 style="margin-top:0; color:#666;">Saat Terkunci:</h4>
                                    <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">
                                        <div style="text-align: center; margin-bottom: 10px;">
                                            <span class="dashicons dashicons-lock" style="font-size: 32px; height: 32px; width: 32px; color: #555;"></span>
                                        </div>
                                        <h3 style="text-align: center; margin: 5px 0 10px; font-size: 16px;">
                                            <?php echo esc_html( $settings['text_locked_title'] ); ?>
                                        </h3>
                                        <div style="font-size: 13px; color: #666; margin-bottom: 15px; line-height: 1.4;">
                                            <?php echo wp_kses_post( $settings['text_locked_desc'] ); ?>
                                        </div>
                                        <div style="text-align: center;">
                                            <button class="button button-primary"><?php echo esc_html( $settings['text_cta_btn'] ); ?></button>
                                        </div>
                                    </div>

                                    <!-- Preview State: Pending -->
                                    <h4 style="margin-top:0; color:#666;">Saat Pending:</h4>
                                    <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid #ffba00;">
                                        <h4 style="margin: 0 0 5px;">Menunggu Verifikasi</h4>
                                        <p style="font-size: 13px; color: #555; margin: 0;">
                                            <?php echo esc_html( $settings['text_pending_msg'] ); ?>
                                        </p>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL POPUP TOLAK -->
    <div id="dw-reject-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
        <div style="background:#fff; width:400px; max-width:90%; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.3);">
            <h2 style="margin-top:0;">Tolak Permintaan</h2>
            <p>Berikan alasan penolakan agar Desa dapat memperbaikinya.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'dw_reject_action', 'dw_reject_nonce' ); ?>
                <input type="hidden" name="dw_reject_submit" value="1">
                <input type="hidden" name="reject_desa_id" id="reject_desa_id" value="">
                
                <p><strong>Desa:</strong> <span id="reject_desa_name">-</span></p>
                
                <textarea name="alasan_tolak" id="alasan_tolak" rows="4" style="width:100%;" placeholder="Contoh: Bukti transfer buram, mohon upload ulang..." required></textarea>
                
                <div style="margin-top:15px; text-align:right;">
                    <button type="button" class="button" onclick="closeRejectModal()">Batal</button>
                    <button type="submit" class="button button-primary button-large">Kirim Penolakan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openRejectModal(id, name) {
        document.getElementById('reject_desa_id').value = id;
        document.getElementById('reject_desa_name').textContent = name;
        document.getElementById('dw-reject-modal').style.display = 'block';
        document.getElementById('alasan_tolak').focus();
    }
    
    function closeRejectModal() {
        document.getElementById('dw-reject-modal').style.display = 'none';
    }
    
    // Close modal if clicked outside
    window.onclick = function(event) {
        var modal = document.getElementById('dw-reject-modal');
        if (event.target == modal) {
            closeRejectModal();
        }
    }
    </script>
<?php
}
?>