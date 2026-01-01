<?php
/**
 * Page: Referral Rewards Log
 * Location: includes/admin-pages/page-refferal-rewards.php
 * Description: Menampilkan riwayat reward kuota dan fitur Sinkronisasi Data Referral yang hilang.
 * Update: Bonus Kuota Dinamis (Mengambil dari get_option).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dw_render_referral_rewards_page() {
    global $wpdb;

    $table_reward   = $wpdb->prefix . 'dw_referral_reward';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_pembeli  = $wpdb->prefix . 'dw_pembeli';
    $table_users    = $wpdb->base_prefix . 'users';

    // Ambil Pengaturan Bonus (Default 5 jika belum diatur)
    $current_bonus_setting = get_option('dw_referral_bonus_amount', 5);

    // --- 1. LOGIC: SINKRONISASI DATA HILANG (FIXER) ---
    if ( isset($_POST['action_sync_rewards']) && check_admin_referer('sync_rewards_action', 'sync_nonce') ) {
        
        // Ambil semua pembeli yang punya referrer valid (Tipe Pedagang)
        $all_referrals = $wpdb->get_results("SELECT id_user, referrer_id, terdaftar_melalui_kode, created_at FROM $table_pembeli WHERE referrer_type = 'pedagang' AND referrer_id > 0");
        
        $fixed_count = 0;
        
        // Gunakan nilai dinamis
        $bonus_per_ref = intval($current_bonus_setting); 

        foreach ( $all_referrals as $ref ) {
            // Cek apakah sudah tercatat di tabel reward
            $is_recorded = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $table_reward WHERE id_user_baru = %d", $ref->id_user) );
            
            if ( ! $is_recorded ) {
                // DATA HILANG DITEMUKAN -> BUAT REWARD BARU
                $wpdb->insert($table_reward, [
                    'id_pedagang'           => $ref->referrer_id,
                    'id_user_baru'          => $ref->id_user,
                    'kode_referral_used'    => $ref->terdaftar_melalui_kode,
                    'bonus_quota_diberikan' => $bonus_per_ref,
                    'status'                => 'verified', // Anggap verified karena user sudah ada
                    'created_at'            => $ref->created_at // Gunakan tanggal daftar asli
                ]);

                // Update Saldo/Kuota Pedagang (Backfill Bonus)
                // Kita gunakan query increment agar aman
                $wpdb->query( $wpdb->prepare("UPDATE $table_pedagang SET sisa_transaksi = sisa_transaksi + %d, total_referral_pembeli = total_referral_pembeli + 1 WHERE id = %d", $bonus_per_ref, $ref->referrer_id) );

                $fixed_count++;
            }
        }

        if ( $fixed_count > 0 ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Sukses:</strong> Sinkronisasi selesai. ' . $fixed_count . ' data reward yang hilang berhasil dipulihkan & bonus (' . $bonus_per_ref . ' kuota) telah diberikan.</p></div>';
        } else {
            echo '<div class="notice notice-info is-dismissible"><p>Data sudah sinkron. Tidak ada reward yang hilang.</p></div>';
        }
    }

    // --- 2. LOGIC: SIMPAN PENGATURAN BONUS (BARU) ---
    if ( isset($_POST['action_save_bonus']) && check_admin_referer('save_bonus_action', 'bonus_nonce') ) {
        $new_bonus = intval($_POST['dw_referral_bonus_amount']);
        update_option('dw_referral_bonus_amount', $new_bonus);
        $current_bonus_setting = $new_bonus; // Update tampilan
        echo '<div class="notice notice-success is-dismissible"><p>Pengaturan bonus berhasil disimpan.</p></div>';
    }

    // --- 3. PREPARE DATA TABLE ---
    // Handle Filter Pencarian
    $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    $sql = "SELECT r.*, p.nama_toko, p.nama_pemilik, u.display_name as nama_pembeli, u.user_email 
            FROM $table_reward r
            LEFT JOIN $table_pedagang p ON r.id_pedagang = p.id
            LEFT JOIN $table_users u ON r.id_user_baru = u.ID
            WHERE 1=1";

    if ( !empty($search_query) ) {
        $sql .= $wpdb->prepare(" AND (r.kode_referral_used LIKE %s OR p.nama_toko LIKE %s OR u.display_name LIKE %s)", 
            '%'.$search_query.'%', '%'.$search_query.'%', '%'.$search_query.'%');
    }

    $sql .= " ORDER BY r.created_at DESC";
    $rewards = $wpdb->get_results($sql);
    
    // Hitung Statistik
    $total_quota_given = 0;
    $total_referrals = count($rewards);
    foreach($rewards as $r) { $total_quota_given += intval($r->bonus_quota_diberikan); }

    // --- 4. TAMPILAN HALAMAN (UI) ---
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Riwayat Reward Referral Pedagang</h1>
        
        <div style="background:#fff; padding:15px; border:1px solid #c3c4c7; margin:15px 0; display:flex; align-items:center; gap:20px; border-left:4px solid #72aee6;">
            <!-- Form Pengaturan Bonus -->
            <form method="post" style="display:flex; align-items:center; gap:10px;">
                <?php wp_nonce_field('save_bonus_action', 'bonus_nonce'); ?>
                <input type="hidden" name="action_save_bonus" value="1">
                <label for="dw_referral_bonus_amount" style="font-weight:600;">Atur Bonus per Referral:</label>
                <input type="number" name="dw_referral_bonus_amount" id="dw_referral_bonus_amount" value="<?php echo esc_attr($current_bonus_setting); ?>" class="small-text" min="0">
                <span class="description">Kuota Transaksi</span>
                <button type="submit" class="button button-primary">Simpan Pengaturan</button>
            </form>

            <div style="border-left:1px solid #ddd; height:30px;"></div>

            <!-- Tombol Sync -->
            <form method="post">
                <?php wp_nonce_field('sync_rewards_action', 'sync_nonce'); ?>
                <input type="hidden" name="action_sync_rewards" value="1">
                <button type="submit" class="button button-secondary" onclick="return confirm('Sistem akan memindai tabel pembeli dan membuat data reward yang hilang secara otomatis dengan bonus <?php echo $current_bonus_setting; ?> kuota. Lanjutkan?')">
                    <span class="dashicons dashicons-update" style="vertical-align:middle; font-size:16px;"></span> Sinkronisasi & Perbaiki Data
                </button>
            </form>
        </div>

        <hr class="wp-header-end">
        
        <!-- Search Bar -->
        <form method="get" style="margin: 20px 0;">
            <input type="hidden" name="page" value="dw-referral-reward">
            <p class="search-box">
                <label class="screen-reader-text" for="post-search-input">Cari Reward:</label>
                <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Cari Toko / Pembeli / Kode...">
                <input type="submit" id="search-submit" class="button" value="Cari Data">
            </p>
        </form>

        <!-- Stats Cards -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-bottom: 25px; clear:both;">
            <div style="background:#fff; padding:20px; border-left:4px solid #2271b1; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                <h3 style="margin:0 0 5px; color:#646970; font-size:13px; text-transform:uppercase;">Total Referral Sukses</h3>
                <span style="font-size:24px; font-weight:700; color:#1d2327;"><?php echo number_format($total_referrals); ?></span>
                <div style="color:#646970; font-size:12px; margin-top:5px;">Pembeli Baru</div>
            </div>
            <div style="background:#fff; padding:20px; border-left:4px solid #00a32a; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                <h3 style="margin:0 0 5px; color:#646970; font-size:13px; text-transform:uppercase;">Total Bonus Diberikan</h3>
                <span style="font-size:24px; font-weight:700; color:#1d2327;"><?php echo number_format($total_quota_given); ?></span>
                <div style="color:#646970; font-size:12px; margin-top:5px;">Kuota Transaksi</div>
            </div>
        </div>

        <!-- Main Table -->
        <div style="background:#fff; border:1px solid #c3c4c7; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%;">Tanggal</th>
                        <th style="width: 25%;">Pedagang (Penerima)</th>
                        <th style="width: 25%;">Pembeli Baru (Referral)</th>
                        <th style="width: 15%;">Kode Dipakai</th>
                        <th style="width: 10%;">Bonus</th>
                        <th style="width: 10%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rewards ) ) : ?>
                        <tr>
                            <td colspan="6" style="padding:40px; text-align:center; color:#646970;">
                                <span class="dashicons dashicons-groups" style="font-size:40px; width:40px; height:40px; margin-bottom:10px; color:#a7aaad;"></span><br>
                                Belum ada data referral yang tercatat.<br>
                                <em style="font-size:12px;">Coba klik tombol "Sinkronisasi" di atas jika yakin ada data.</em>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rewards as $row ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('d M Y', strtotime($row->created_at)); ?></strong><br>
                                    <small style="color:#8c8f94;"><?php echo date('H:i', strtotime($row->created_at)); ?> WIB</small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($row->nama_toko); ?></strong><br>
                                    <small style="color:#646970;"><?php echo esc_html($row->nama_pemilik); ?></small>
                                </td>
                                <td>
                                    <span class="dashicons dashicons-admin-users" style="font-size:14px; color:#8c8f94; vertical-align:middle;"></span> 
                                    <?php echo esc_html($row->nama_pembeli); ?><br>
                                    <small style="color:#8c8f94;"><?php echo esc_html($row->user_email); ?></small>
                                </td>
                                <td>
                                    <code style="background:#f0f0f1; padding:3px 6px; border-radius:3px; font-weight:600; color:#2271b1; font-size:11px;">
                                        <?php echo esc_html($row->kode_referral_used); ?>
                                    </code>
                                </td>
                                <td>
                                    <span style="color:#00a32a; font-weight:700; background:#d1e7dd; padding:2px 8px; border-radius:10px; font-size:11px;">
                                        +<?php echo intval($row->bonus_quota_diberikan); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row->status == 'verified'): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span> <span style="font-weight:600; color:#00a32a; font-size:11px;">Sukses</span>
                                    <?php elseif($row->status == 'fraud'): ?>
                                        <span class="dashicons dashicons-warning" style="color:#d63638;"></span> <span style="font-weight:600; color:#d63638; font-size:11px;">Fraud</span>
                                    <?php else: ?>
                                        <span style="font-weight:600; color:#dba617; font-size:11px;"><?php echo ucfirst($row->status); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>