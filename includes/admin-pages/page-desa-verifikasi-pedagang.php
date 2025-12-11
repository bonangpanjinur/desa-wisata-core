<?php
/**
 * File Name:   page-desa-verifikasi-pedagang.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-desa-verifikasi-pedagang.php
 *
 * --- FASE 1: REFAKTOR PENDAFTARAN GRATIS ---
 * PERUBAHAN BESAR:
 * - MENGHAPUS "Tahap 2: Verifikasi Pembayaran".
 * - MENGHAPUS referensi ke `dw_transaksi_pendaftaran`.
 * - MENGHAPUS cek `dw_is_admin_desa_blocked` dan fungsinya.
 * - MENGUBAH handler `approve_kelayakan`:
 * - Sekarang juga mengupdate `status_akun` pedagang menjadi 'aktif'.
 * - Sekarang juga mengubah role user dari 'subscriber' menjadi 'pedagang'.
 * - Mengubah pesan notifikasi menjadi "akun Anda telah diaktifkan".
 *
 * --- PERBAIKAN (MODEL 3 - KUOTA GRATIS) ---
 * - Saat menyetujui kelayakan, sekarang mengambil 'default_kuota_transaksi_gratis'
 * dari 'dw_settings' dan menambahkannya ke 'sisa_transaksi' pedagang.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MENGHAPUS FUNGSI dw_is_admin_desa_blocked()
 */
// if (!function_exists('dw_is_admin_desa_blocked')) { ... }


/**
 * Handler untuk Aksi Admin Desa: Verifikasi Kelayakan, Tolak.
 * (Aksi Pembayaran Dihapus)
 */
function dw_desa_pedagang_verification_handler() {
    if (!isset($_POST['dw_desa_verification_action'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'], 'dw_desa_pedagang_nonce')) wp_die('Security check failed.');

    // Cek kapabilitas (cek blokir dihapus)
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

    $log_prefix = '';
    $notification_subject = '';
    $notification_message = '';
    $success_message = '';
    $error = null;

    // 1. Aksi Verifikasi Kelayakan (menunggu_desa -> disetujui/ditolak)
    if (in_array($action_type, ['approve_kelayakan', 'reject_kelayakan'])) {
        $new_status_pendaftaran = ($action_type === 'approve_kelayakan') ? 'disetujui' : 'ditolak';

        // Update status pendaftaran
        $wpdb->update(
            $wpdb->prefix . 'dw_pedagang',
            ['status_pendaftaran' => $new_status_pendaftaran],
            ['id' => $pedagang_id]
        );

        dw_log_activity('KELAYAKAN_' . strtoupper($action_type), "Admin Desa mengubah status kelayakan Pedagang #{$pedagang_id} menjadi {$new_status_pendaftaran}.", get_current_user_id());

        if ($new_status_pendaftaran === 'disetujui') {
            // --- PERUBAHAN UTAMA (PEMBERIAN KUOTA GRATIS) ---
            
            // Ambil default kuota dari settings
            $options = get_option('dw_settings');
            $default_kuota = isset($options['kuota_gratis_default']) ? absint($options['kuota_gratis_default']) : 0;

            // Langsung aktifkan akun dan tambahkan kuota gratis
            $wpdb->update(
                $wpdb->prefix . 'dw_pedagang',
                [
                    'status_akun' => 'aktif', // Langsung 'aktif'
                    'sisa_transaksi' => $default_kuota // Tambahkan kuota gratis
                ], 
                ['id' => $pedagang_id]
            );

            $user = new WP_User($id_user_pedagang);
            if ($user->exists()) {
                $user->remove_role('subscriber');
                $user->add_role('pedagang'); // Ubah role
            }
            // --- AKHIR PERUBAHAN ---

            $success_message = "Kelayakan disetujui. Akun pedagang telah diaktifkan dengan {$default_kuota} kuota transaksi gratis.";
            $notification_subject = "Pendaftaran Disetujui & Akun Aktif: {$pedagang_name}";
            $notification_message = "Selamat! Kelayakan pendaftaran toko Anda, '{$pedagang_name}', telah disetujui oleh Admin Desa dan akun Anda sekarang *Aktif*. Anda mendapatkan {$default_kuota} kuota transaksi gratis untuk memulai.";
        
        } else { // Jika ditolak
             $rejection_reason = sanitize_textarea_field($_POST['rejection_reason'] ?? 'Tidak memenuhi kriteria kelayakan lokal.');
             dw_send_pedagang_notification($id_user_pedagang, "Pendaftaran Ditolak: {$pedagang_name}", "Mohon maaf, pendaftaran toko Anda, '{$pedagang_name}', telah ditolak oleh Admin Desa. Alasan: {$rejection_reason}.");
             $success_message = 'Kelayakan ditolak. Pedagang dinotifikasi.';
        }

    // 2. Aksi Verifikasi Pembayaran (DIHAPUS)
    } elseif (in_array($action_type, ['approve_pembayaran', 'reject_pembayaran'])) {
        $error = new WP_Error('rest_invalid_action', 'Aksi verifikasi pembayaran tidak lagi digunakan.', ['status' => 400]);
    } else {
        $error = new WP_Error('rest_invalid_action', 'Aksi tidak valid.', ['status' => 400]);
    }

    // Handle Hasil
    if ($error) {
        add_settings_error('dw_desa_notices', $error->get_error_code(), $error->get_error_message(), 'error');
    } else {
        add_settings_error('dw_desa_notices', 'action_success', $success_message, ($action_type == 'reject_kelayakan') ? 'warning' : 'success');
        if ($log_prefix && function_exists('dw_log_activity')) {
             dw_log_activity($log_prefix . '_HANDLER', "Admin Desa #".get_current_user_id()." aksi '{$action_type}' Pedagang #{$pedagang_id}.", get_current_user_id());
        }
        if ($notification_subject && function_exists('dw_send_pedagang_notification')) {
             dw_send_pedagang_notification($id_user_pedagang, $notification_subject, $notification_message);
        }
         // Invalidate cache notifikasi Admin Desa
        wp_cache_delete('dw_desa_pending_pedagang_' . $desa_id, 'desa_wisata_core');
    }

    set_transient('settings_errors', get_settings_errors(), 30);
    wp_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'dw_desa_pedagang_verification_handler');


/**
 * Render Halaman Admin Desa: Verifikasi Pedagang.
 */
function dw_admin_desa_verifikasi_page_render() {
    global $wpdb;
    $current_user_id = get_current_user_id();

    $desa = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $current_user_id));
    if (!$desa) {
        echo '<div class="wrap"><div class="notice notice-error"><p>Anda tidak terhubung dengan Desa manapun. Hubungi Super Admin.</p></div></div>';
        return;
    }
    $desa_id = $desa->id;

    // Ambil data pedagang yang perlu diurus oleh Desa ini.
    $pedagang_kelayakan = $wpdb->get_results($wpdb->prepare(
        "SELECT id, id_user, nama_toko, status_pendaftaran, created_at FROM {$wpdb->prefix}dw_pedagang
         WHERE id_desa = %d AND status_pendaftaran = %s ORDER BY created_at ASC",
        $desa_id, 'menunggu_desa'
    ), ARRAY_A);

    // --- QUERY PEMBAYARAN DIHAPUS ---
    // $pedagang_pembayaran = ...

    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Verifikasi Pedagang Desa <?php echo esc_html($desa->nama_desa); ?></h1>
        </div>
        <?php
        $errors = get_transient('settings_errors');
        if($errors) { settings_errors('dw_desa_notices'); delete_transient('settings_errors'); }
        // --- NOTIFIKASI BLOKIR DIHAPUS ---
        // if ($is_blocked) : ...
        ?>

        <h2>Verifikasi Kelayakan Lokal (<?php echo count($pedagang_kelayakan); ?>)</h2>
        <p>Pedagang di tahap ini baru mendaftar. Lakukan verifikasi kelayakan (cek domisili/lokasi usaha). Jika disetujui, akun pedagang akan langsung aktif dan mendapatkan kuota transaksi gratis.</p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 30%;">Nama Toko</th>
                    <th style="width: 20%;">Pengguna</th>
                    <th style="width: 30%;">Tanggal Daftar</th>
                    <th style="width: 20%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pedagang_kelayakan)): ?>
                    <tr><td colspan="4">Tidak ada pedagang menunggu verifikasi kelayakan.</td></tr>
                <?php else: foreach ($pedagang_kelayakan as $item): ?>
                    <tr>
                        <td><strong><?php echo esc_html($item['nama_toko']); ?></strong></td>
                        <td><?php echo esc_html(get_user_by('id', $item['id_user'])->display_name ?? 'N/A'); ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($item['created_at'])); ?></td>
                        <td>
                             <form method="post" action="<?php echo admin_url('admin.php?page=dw-desa-verifikasi'); ?>" style="display:inline-block;">
                                <input type="hidden" name="pedagang_id" value="<?php echo esc_attr($item['id']); ?>">
                                <input type="hidden" name="action_type" value="approve_kelayakan">
                                <input type="hidden" name="dw_desa_verification_action" value="1">
                                <?php wp_nonce_field('dw_desa_pedagang_nonce'); ?>
                                <button type="submit" class="button button-primary button-small" onclick="return confirm('Setujui kelayakan dan aktifkan akun pedagang ini? (Akan mendapat kuota gratis)');">Setujui & Aktifkan</button>
                            </form>

                             <form method="post" action="<?php echo admin_url('admin.php?page=dw-desa-verifikasi'); ?>" style="display:inline-block;">
                                <input type="hidden" name="pedagang_id" value="<?php echo esc_attr($item['id']); ?>">
                                <input type="hidden" name="action_type" value="reject_kelayakan">
                                <input type="hidden" name="dw_desa_verification_action" value="1">
                                <?php wp_nonce_field('dw_desa_pedagang_nonce'); ?>
                                <input type="text" name="rejection_reason" placeholder="Alasan Tolak" style="width: 80px; margin-right: 5px;">
                                <button type="submit" class="button button-secondary button-small" onclick="return confirm('Yakin menolak kelayakan?');">Tolak</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <!-- --- BAGIAN TAHAP 2 VERIFIKASI PEMBAYARAN DIHAPUS --- -->
        <!-- <h2 style="margin-top: 40px;">Tahap 2: Verifikasi Pembayaran ... -->
        
    </div>
    <?php
}

?>
