<?php
/**
 * File: includes/admin-pages/page-verifikator-umkm.php
 * Deskripsi: Halaman Dashboard Verifikator UMKM.
 */

if (!defined('ABSPATH')) exit;

/**
 * Logika halaman dibungkus dalam fungsi agar dipanggil melalui Lazy Loading di menu callback.
 * Ini mencegah error "undefined function" WordPress saat file di-require.
 */
function dw_verifikator_page_render() {
    $current_user_id = get_current_user_id();
    $user_data       = get_userdata($current_user_id);
    
    if (!$user_data) return;

    $verifier_code   = get_user_meta($current_user_id, 'dw_verifier_code', true);
    $balance         = (float) get_user_meta($current_user_id, 'dw_balance', true);
    $is_admin        = current_user_can('administrator');

    // Handle Post
    if ($is_admin && isset($_POST['dw_save_comm_settings'])) {
        check_admin_referer('dw_save_comm_nonce');
        $new_settings = ['platform' => intval($_POST['comm_platform']), 'desa' => intval($_POST['comm_desa']), 'verifier' => intval($_POST['comm_verifier'])];
        if (($new_settings['platform'] + $new_settings['desa'] + $new_settings['verifier']) === 100) {
            update_option('dw_commission_settings', $new_settings);
            echo '<div class="updated"><p>Skema komisi disimpan!</p></div>';
        } else {
            echo '<div class="error"><p>Total persentase harus 100%!</p></div>';
        }
    }

    if (empty($verifier_code) && in_array('verifikator_umkm', $user_data->roles)) {
        $verifier_code = dw_generate_verifier_code($current_user_id);
    }
    ?>
    <div class="wrap">
        <h1>Dashboard Verifikator: <?php echo esc_html($user_data->display_name); ?></h1>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px; margin-top:20px;">
            <div class="postbox" style="padding:20px; border-left:4px solid #2271b1">
                <h3>Kode Unik Agen</h3>
                <div style="font-size:28px; font-weight:bold; color:#2271b1; background:#f0f6fb; padding:15px; border-radius:8px; text-align:center; border:1px dashed #2271b1;">
                    <?php echo $verifier_code ? esc_html($verifier_code) : 'N/A'; ?>
                </div>
            </div>

            <div class="postbox" style="padding:20px; border-left:4px solid #46b450">
                <h3>Saldo Komisi</h3>
                <div style="font-size:28px; font-weight:bold; color:#46b450;">
                    <?php echo dw_format_rupiah($balance); ?>
                </div>
                <button class="button button-primary" style="margin-top:15px">Tarik Saldo</button>
            </div>
        </div>

        <div style="margin-top:30px; background:#fff; padding:20px; border-radius:8px; border:1px solid #ccd0d4">
            <h2>UMKM Binaan</h2>
            <?php
            $list_table_path = DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-verifikator-pedagang-list-table.php';
            if (file_exists($list_table_path)) {
                require_once $list_table_path;
                $table = new DW_Verifikator_Pedagang_List_Table();
                $table->prepare_items();
                $table->display();
            }
            ?>
        </div>
    </div>
    <?php
}

// Panggil fungsi render utama
dw_verifikator_page_render();