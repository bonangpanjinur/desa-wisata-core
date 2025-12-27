<?php
/**
 * File: includes/admin-pages/page-verifikator-umkm.php
 * Deskripsi: Halaman Dashboard Verifikator UMKM (Kode, Saldo, Binaan).
 * * Fitur:
 * 1. Manajemen Kode Unik (Referral) untuk Verifikator.
 * 2. Monitoring Saldo Komisi (Earnings).
 * 3. Pengaturan Komisi Dinamis (Khusus Admin).
 * 4. Daftar Pedagang Binaan (WP_List_Table).
 */

if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();
$user_data       = get_userdata($current_user_id);
$verifier_code   = get_user_meta($current_user_id, 'dw_verifier_code', true);
$balance         = (float) get_user_meta($current_user_id, 'dw_balance', true);
$is_admin        = current_user_can('administrator');

// Proses Simpan Pengaturan Komisi (Hanya jika diakses oleh Administrator)
if ($is_admin && isset($_POST['dw_save_comm_settings'])) {
    check_admin_referer('dw_save_comm_nonce');
    
    $new_settings = [
        'platform' => intval($_POST['comm_platform']),
        'desa'     => intval($_POST['comm_desa']),
        'verifier' => intval($_POST['comm_verifier']),
    ];

    // Validasi: Total persentase harus tepat 100%
    if (($new_settings['platform'] + $new_settings['desa'] + $new_settings['verifier']) === 100) {
        update_option('dw_commission_settings', $new_settings);
        echo '<div class="updated"><p>Pengaturan komisi berhasil disimpan!</p></div>';
    } else {
        echo '<div class="error"><p>Gagal! Total persentase harus tepat 100% (Platform + Desa + Verifikator = 100).</p></div>';
    }
}

// Otomatis generate kode verifikator jika user login adalah verifikator dan belum punya kode
if (empty($verifier_code) && current_user_can('verifikator_umkm')) {
    if (function_exists('dw_generate_verifier_code')) {
        $verifier_code = dw_generate_verifier_code($current_user_id);
    }
}
?>

<div class="wrap dw-admin-container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1 class="wp-heading-inline">Dashboard Verifikator UMKM</h1>
        <div style="background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #ccd0d4; font-size: 12px; font-weight: 600;">
            <span class="dashicons dashicons-admin-users" style="font-size: 16px; margin-top: 3px; color: #2271b1;"></span> 
            Akun: <?php echo esc_html($user_data->display_name); ?> (<?php echo esc_html(strtoupper(str_replace('_', ' ', $user_data->roles[0]))); ?>)
        </div>
    </div>
    <hr class="wp-header-end">

    <div class="dw-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 20px;">
        
        <!-- BOX 1: KODE VERIFIKATOR (Identitas Utama Akun) -->
        <div class="postbox" style="padding: 20px; border-left: 4px solid #2271b1; background: #fff;">
            <h3 style="margin-top:0; color: #1d2327;">Identitas Kode Agen</h3>
            <div style="font-size: 28px; font-weight: bold; color: #2271b1; background: #f0f6fb; padding: 15px; border-radius: 8px; text-align: center; border: 1px dashed #2271b1; margin: 15px 0;">
                <?php echo $verifier_code ? esc_html($verifier_code) : '<span style="color:#d63638; font-size:16px;">KODE BELUM TERSEDIA</span>'; ?>
            </div>
            <p class="description" style="margin-top:10px; font-style: italic;">
                Berikan kode ini kepada calon pedagang. Akun pedagang yang menggunakan kode ini akan otomatis masuk dalam pengawasan dan bagi hasil Anda.
            </p>
        </div>

        <!-- BOX 2: SALDO EARNINGS (Dompet Akun) -->
        <div class="postbox" style="padding: 20px; border-left: 4px solid #46b450; background: #fff;">
            <h3 style="margin-top:0; color: #1d2327;">Dompet Komisi</h3>
            <div style="font-size: 28px; font-weight: bold; color: #46b450; margin: 15px 0;">
                Rp <?php echo number_format($balance, 0, ',', '.'); ?>
            </div>
            <p class="description">Total bagi hasil bersih yang bisa Anda cairkan ke rekening bank.</p>
            <div style="margin-top: 20px;">
                <button class="button button-primary button-large" style="width:100%; height: 45px; font-weight: bold;">
                    <span class="dashicons dashicons-money-alt" style="margin-top:4px;"></span> Tarik Saldo Ke Rekening
                </button>
            </div>
        </div>

        <!-- BOX 3: KONFIGURASI ADMIN (Dinamis) -->
        <?php if ($is_admin) : ?>
        <div class="postbox" style="padding: 20px; border-left: 4px solid #d63638; background: #fff;">
            <h3 style="margin-top:0; color: #1d2327;">Konfigurasi Komisi Global</h3>
            <form method="post" style="margin-top: 15px;">
                <?php 
                wp_nonce_field('dw_save_comm_nonce');
                $comm = (function_exists('dw_get_commission_settings')) ? dw_get_commission_settings() : ['platform' => 50, 'desa' => 20, 'verifier' => 30]; 
                ?>
                <table class="form-table" style="margin:0">
                    <tr>
                        <th style="width:140px; padding:10px 0; font-size: 13px;">Pusat (Platform)</th>
                        <td><input type="number" name="comm_platform" value="<?php echo esc_attr($comm['platform']); ?>" style="width: 70px; padding: 5px;"> %</td>
                    </tr>
                    <tr>
                        <th style="padding:10px 0; font-size: 13px;">Desa (Wilayah)</th>
                        <td><input type="number" name="comm_desa" value="<?php echo esc_attr($comm['desa']); ?>" style="width: 70px; padding: 5px;"> %</td>
                    </tr>
                    <tr>
                        <th style="padding:10px 0; font-size: 13px;">Verifikator (Agen)</th>
                        <td><input type="number" name="comm_verifier" value="<?php echo esc_attr($comm['verifier']); ?>" style="width: 70px; padding: 5px;"> %</td>
                    </tr>
                </table>
                <p style="font-size:11px; color:#666; margin: 10px 0;">* Admin Pusat mengatur porsi pembagian dari total Admin Fee setiap transaksi.</p>
                <input type="submit" name="dw_save_comm_settings" class="button button-secondary" value="Simpan Skema">
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- SEKSI TABEL: DAFTAR BINAAN -->
    <div class="dw-table-section" style="margin-top: 40px; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #ccd0d4;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin:0; display: flex; align-items: center;">
                <span class="dashicons dashicons-store" style="margin-right: 10px; color: #2271b1;"></span> UMKM Binaan Anda
            </h2>
            <button class="button button-secondary">
                <span class="dashicons dashicons-download" style="margin-top:4px;"></span> Export Laporan
            </button>
        </div>
        <p class="description">Daftar pedagang yang sah menggunakan kode verifikator akun Anda.</p>
        
        <div id="verifikator-umkm-table" style="margin-top: 20px;">
            <?php
            $list_table_path = plugin_dir_path(__FILE__) . '../list-tables/class-dw-verifikator-pedagang-list-table.php';
            if (file_exists($list_table_path)) {
                require_once $list_table_path;
                if (class_exists('DW_Verifikator_Pedagang_List_Table')) {
                    $table = new DW_Verifikator_Pedagang_List_Table();
                    $table->prepare_items();
                    $table->display();
                } else {
                    echo '<div class="notice notice-error"><p>Gagal memuat tabel: Class tidak ditemukan.</p></div>';
                }
            } else {
                echo '<div class="notice notice-warning"><p>File tabel belum tersedia di folder <code>list-tables</code>.</p></div>';
            }
            ?>
        </div>
    </div>
</div>

<style>
    .dw-admin-container .postbox {
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .dw-admin-container .postbox h3 {
        font-size: 16px;
        border-bottom: 1px solid #f0f0f1;
        padding-bottom: 12px;
    }
    #verifikator-umkm-table .wp-list-table th {
        background: #f8f9fa;
    }
    .dw-admin-container .button-primary {
        background: #2271b1;
        border-color: #2271b1;
    }
</style>