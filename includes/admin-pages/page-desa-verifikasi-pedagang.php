<?php
/**
 * File Name:   page-desa-verifikasi-pedagang.php
 * Description: Verifikasi Pedagang oleh Desa.
 * * LOGIKA MERGED:
 * 1. Mempertahankan Cek Membership Premium Desa.
 * 2. Mempertahankan Pemberian Kuota Gratis saat Approve.
 * 3. UPDATE QUERY: Hanya menampilkan pedagang yang memakai Kode Referral Desa (atau Kode Pedagang di desa ini) 
 * DAN tidak dihandle oleh Verifikator.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler Aksi Admin Desa
 */
function dw_desa_pedagang_verification_handler() {
    if (!isset($_POST['dw_desa_verification_action'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_pedagang_nonce')) wp_die('Security check failed.');

    if (!current_user_can('dw_approve_pedagang')) {
        add_settings_error('dw_desa_notices', 'permission_denied', 'Anda tidak punya izin.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=dw-desa-verifikasi'));
        exit;
    }

    global $wpdb;
    $pedagang_id = absint($_POST['pedagang_id']);
    $action_type = sanitize_key($_POST['action_type']);
    $redirect_url = admin_url('admin.php?page=dw-desa-verifikasi');

    $pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id_user, id_desa, nama_toko FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $pedagang_id));
    if (!$pedagang_data) return;
    
    $id_user_pedagang = $pedagang_data->id_user;
    $desa_id = $pedagang_data->id_desa;
    $pedagang_name = $pedagang_data->nama_toko;

    // --- CEK PREMIUM DESA (LOGIKA LAMA) ---
    $desa_status = $wpdb->get_var($wpdb->prepare("SELECT status_akses_verifikasi FROM {$wpdb->prefix}dw_desa WHERE id = %d", $desa_id));
    if ($desa_status !== 'active') {
        add_settings_error('dw_desa_notices', 'feature_locked', 'GAGAL: Fitur Verifikasi Pedagang terkunci. Desa Anda harus status PREMIUM.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect($redirect_url);
        exit;
    }

    // --- HANDLE APPROVE ---
    if ($action_type === 'approve_kelayakan') {
        // Update status
        $wpdb->update(
            $wpdb->prefix . 'dw_pedagang',
            [
                'status_pendaftaran' => 'disetujui',
                'status_akun' => 'aktif', // Langsung aktif
                'approved_by' => 'desa'
            ],
            ['id' => $pedagang_id]
        );

        // Beri Kuota Gratis (LOGIKA LAMA)
        $options = get_option('dw_settings');
        $default_kuota = isset($options['kuota_gratis_default']) ? absint($options['kuota_gratis_default']) : 0;
        
        $wpdb->update(
            $wpdb->prefix . 'dw_pedagang',
            ['sisa_transaksi' => $default_kuota],
            ['id' => $pedagang_id]
        );

        // Update Role User
        $user = new WP_User($id_user_pedagang);
        if ($user->exists()) {
            $user->remove_role('subscriber');
            $user->add_role('pedagang');
        }

        dw_log_activity('VERIFIKASI_DESA', "Desa menyetujui pedagang #{$pedagang_id}", get_current_user_id());
        add_settings_error('dw_desa_notices', 'success', "Pedagang disetujui. Diberikan {$default_kuota} kuota gratis.", 'success');

    // --- HANDLE REJECT ---
    } elseif ($action_type === 'reject_kelayakan') {
        $alasan = sanitize_textarea_field($_POST['rejection_reason'] ?? '-');
        $wpdb->update(
            $wpdb->prefix . 'dw_pedagang',
            ['status_pendaftaran' => 'ditolak'],
            ['id' => $pedagang_id]
        );
        add_settings_error('dw_desa_notices', 'rejected', "Pedagang ditolak. Alasan: {$alasan}", 'warning');
    }

    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'dw_desa_pedagang_verification_handler');

/**
 * Render Halaman
 */
function dw_admin_desa_verifikasi_page_render() {
    global $wpdb;
    $current_user_id = get_current_user_id();

    // Ambil Data Desa User Login
    $desa = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa, status_akses_verifikasi FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $current_user_id));
    
    if (!$desa) {
        echo '<div class="wrap"><div class="notice notice-error"><p>Anda tidak terhubung dengan Desa manapun.</p></div></div>';
        return;
    }

    // --- QUERY PENTING: Filter Sesuai Request User ---
    // 1. id_desa = Desa Ini
    // 2. id_verifikator = 0 (Jika ada verifikator, masuk ke page verifikator)
    // 3. terdaftar_melalui_kode != '' (Jika kosong, masuk ke Admin Pusat)
    $sql = "SELECT * FROM {$wpdb->prefix}dw_pedagang 
            WHERE id_desa = %d 
            AND status_pendaftaran IN ('menunggu_desa', 'menunggu') 
            AND id_verifikator = 0 
            AND terdaftar_melalui_kode != ''
            ORDER BY created_at ASC";

    $pedagang_list = $wpdb->get_results($wpdb->prepare($sql, $desa->id), ARRAY_A);
    ?>

    <div class="wrap dw-wrap">
        <h1>Verifikasi Pedagang Desa <?php echo esc_html($desa->nama_desa); ?></h1>
        
        <?php settings_errors('dw_desa_notices'); ?>

        <?php if ($desa->status_akses_verifikasi !== 'active'): ?>
             <!-- TAMPILAN BLOKIR PREMIUM (LAMA) -->
            <div style="background: #fff; border-left: 4px solid #d63638; padding: 20px; margin-top: 20px;">
                <h3><span class="dashicons dashicons-lock"></span> Fitur Terkunci</h3>
                <p>Fitur verifikasi hanya untuk Desa Premium. Silakan upgrade membership desa Anda.</p>
                <a href="<?php echo admin_url('admin.php?page=dw-desa'); ?>" class="button button-primary">Upgrade Premium</a>
            </div>
        <?php else: ?>
            <!-- TAMPILAN TABEL -->
            <div class="notice notice-info inline">
                <p>
                    Daftar di bawah ini adalah pedagang yang mendaftar menggunakan <strong>Kode Referral Desa Anda</strong> (atau kode pedagang di desa ini). <br>
                    Pedagang yang mendaftar menggunakan Kode Verifikator akan muncul di dashboard Verifikator terkait. <br>
                    Pedagang tanpa kode referral akan diverifikasi oleh Admin Pusat.
                </p>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nama Toko</th>
                        <th>Kode Referral</th>
                        <th>Owner</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedagang_list)): ?>
                        <tr><td colspan="5">Tidak ada pedagang yang perlu diverifikasi oleh Desa.</td></tr>
                    <?php else: foreach ($pedagang_list as $p): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($p['nama_toko']); ?></strong><br>
                                <small>WA: <?php echo esc_html($p['nomor_wa']); ?></small>
                            </td>
                            <td><span class="badge"><?php echo esc_html($p['terdaftar_melalui_kode']); ?></span></td>
                            <td><?php echo esc_html($p['nama_pemilik']); ?></td>
                            <td><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('dw_desa_pedagang_nonce'); ?>
                                    <input type="hidden" name="dw_desa_verification_action" value="1">
                                    <input type="hidden" name="pedagang_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="action_type" value="approve_kelayakan" class="button button-primary button-small">Setujui & Aktifkan</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('dw_desa_pedagang_nonce'); ?>
                                    <input type="hidden" name="dw_desa_verification_action" value="1">
                                    <input type="hidden" name="pedagang_id" value="<?php echo $p['id']; ?>">
                                    <input type="text" name="rejection_reason" placeholder="Alasan" style="width:80px; font-size:10px;">
                                    <button type="submit" name="action_type" value="reject_kelayakan" class="button button-secondary button-small">Tolak</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
?>