<?php
/**
 * Page: Verifikasi UMKM (Role: Verifikator)
 * Logic: Menampilkan pedagang yang terikat (binding) dengan Verifikator ini.
 * Style: Mengadaptasi UI "Verifikasi Akun Sistem" dengan Logic Database v3.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include UI components
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin-ui-components.php';

function dw_render_verifikator_umkm_page() {
    global $wpdb;
    $current_user_id = get_current_user_id();
    
    // --- 1. SECURITY & DATA CHECK ---
    $verifikator = $wpdb->get_row( $wpdb->prepare( "SELECT id, nama_lengkap, kode_referral FROM {$wpdb->prefix}dw_verifikator WHERE id_user = %d", $current_user_id ) );

    if ( ! $verifikator ) {
        echo '<div class="wrap dw-wrap"><div class="notice notice-error"><p>Akses Ditolak. Akun Anda tidak terdaftar sebagai Verifikator.</p></div></div>';
        return;
    }

    // --- 2. HANDLE ACTIONS (VERIFIKASI LAPANGAN) ---
    if ( isset( $_POST['action_verif'] ) && check_admin_referer( 'dw_verif_umkm_action' ) ) {
        $pedagang_id = intval( $_POST['pedagang_id'] );
        $type = $_POST['action_verif']; // 'approve' only for now

        if ( $type === 'approve' ) {
            // Verifikator melakukan ACC (is_verified = 1) dan mengaktifkan akun
            $wpdb->update( 
                "{$wpdb->prefix}dw_pedagang", 
                [
                    'is_verified' => 1, 
                    'verified_at' => current_time( 'mysql' ),
                    'status_akun' => 'aktif',
                    'status_pendaftaran' => 'disetujui'
                ], 
                ['id' => $pedagang_id, 'id_verifikator' => $verifikator->id] 
            );

            // Update Stats Verifikator
            $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->prefix}dw_verifikator SET total_verifikasi_sukses = total_verifikasi_sukses + 1 WHERE id = %d", $verifikator->id) );
            
            echo dw_admin_render_alert('UMKM berhasil diverifikasi lapangan & diaktifkan.', 'success');
        }
    }

    // --- 3. FILTER LOGIC ---
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    
    // Base SQL
    $sql = "SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id_verifikator = %d";
    $params = [$verifikator->id];

    // Apply Filter
    if ($filter_status === 'pending') {
        $sql .= " AND is_verified = 0";
    } elseif ($filter_status === 'approved') {
        $sql .= " AND is_verified = 1";
    }

    $sql .= " ORDER BY created_at DESC";
    
    $umkm_list = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    ?>

    <!-- STYLE DARI FILE SEBELUMNYA -->
    

    <div class="wrap dw-wrap">
        
        <div class="dw-header-flex">
            <div>
                <h1 style="margin:0;">Dashboard Verifikator</h1>
                <p style="color:#64748b; margin:5px 0 0 0;">Halo, <strong><?php echo esc_html( $verifikator->nama_lengkap ); ?></strong>. Kelola UMKM binaan Anda di sini.</p>
            </div>
            
            <!-- Filter Form -->
            <form method="get" class="dw-filter-group">
                <input type="hidden" name="page" value="dw-verifikator-umkm">
                <div class="dw-filter-item">
                    <label>Status Verifikasi</label>
                    <select name="filter_status" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>>Menunggu Verifikasi</option>
                        <option value="approved" <?php selected($filter_status, 'approved'); ?>>Terverifikasi</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Referral Info Card -->
        <div class="dw-info-card">
            <div style="font-size: 24px;">📢</div>
            <div style="flex:1;">
                <strong>Kode Referral Anda:</strong> <span class="dw-code-box"><?php echo esc_html( $verifikator->kode_referral ); ?></span>
                <div style="font-size:13px; margin-top:4px;">Berikan kode ini kepada pedagang saat mendaftar agar otomatis masuk ke daftar verifikasi Anda dan Anda mendapatkan komisi.</div>
            </div>
        </div>

        <div class="dw-main-card">
            <table class="dw-modern-table">
                <thead>
                    <tr>
                        <th>Nama UMKM & Pemilik</th>
                        <th>Lokasi</th>
                        <th>Status Akun</th>
                        <th>Status Verifikasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="account-list">
                    <?php if ( empty( $umkm_list ) ) : ?>
                        <tr><td colspan="5" style="text-align:center; padding:50px; color:#94a3b8;">Belum ada pedagang yang mendaftar menggunakan kode referral Anda.</td></tr>
                    <?php else : foreach ( $umkm_list as $umkm ) : ?>
                        <tr>
                            <td>
                                <strong style="display:block; color:#1e293b; font-size:14px;"><?php echo esc_html( $umkm->nama_toko ); ?></strong>
                                <small style="color:#64748b;">Pemilik: <?php echo esc_html( $umkm->nama_pemilik ); ?></small><br>
                                <small style="color:#64748b;">WA: <?php echo esc_html( $umkm->nomor_wa ); ?></small>
                            </td>
                            <td style="font-size:13px; color:#475569; max-width:250px;">
                                <?php echo esc_html( $umkm->alamat_lengkap ); ?>
                            </td>
                            <td>
                                <?php if ($umkm->status_akun == 'aktif'): ?>
                                    <span style="color:#15803d; font-weight:600;">Aktif</span>
                                <?php else: ?>
                                    <span style="color:#b91c1c; font-weight:600;">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $umkm->is_verified ) : ?>
                                    <span class="status-badge status-verified">Verified</span>
                                    <div style="font-size:10px; color:#64748b; margin-top:4px;">
                                        <?php echo date('d M Y', strtotime($umkm->verified_at)); ?>
                                    </div>
                                <?php else : ?>
                                    <span class="status-badge status-pending">Menunggu Lapangan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! $umkm->is_verified ) : ?>
                                    <form method="post" onsubmit="return confirm('Apakah Anda sudah melakukan pengecekan lapangan dan menjamin keaslian UMKM ini?');">
                                        <?php wp_nonce_field( 'dw_verif_umkm_action' ); ?>
                                        <input type="hidden" name="pedagang_id" value="<?php echo $umkm->id; ?>">
                                        <button type="submit" name="action_verif" value="approve" class="btn-action btn-approve" title="Verifikasi Lapangan">Verifikasi Sekarang</button>
                                    </form>
                                <?php else : ?>
                                    <button class="btn-action btn-disabled" disabled>Sudah Diverifikasi</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>