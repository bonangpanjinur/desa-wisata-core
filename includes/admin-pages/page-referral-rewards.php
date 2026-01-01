<?php
/**
 * Page: Referral Rewards Log
 * Location: includes/admin-pages/page-refferal-rewards.php
 * Description: Menampilkan riwayat reward kuota dan fitur Sinkronisasi Data Referral yang hilang.
 * Update: UI/UX Modern Redesign.
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
                    'status'                => 'verified',
                    'created_at'            => $ref->created_at 
                ]);

                // Update Saldo/Kuota Pedagang
                $wpdb->query( $wpdb->prepare("UPDATE $table_pedagang SET sisa_transaksi = sisa_transaksi + %d, total_referral_pembeli = total_referral_pembeli + 1 WHERE id = %d", $bonus_per_ref, $ref->referrer_id) );

                $fixed_count++;
            }
        }

        if ( $fixed_count > 0 ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Sukses:</strong> Sinkronisasi selesai. ' . $fixed_count . ' data reward yang hilang berhasil dipulihkan.</p></div>';
        } else {
            echo '<div class="notice notice-info is-dismissible"><p>Data sudah sinkron. Tidak ada reward yang hilang.</p></div>';
        }
    }

    // --- 2. LOGIC: SIMPAN PENGATURAN BONUS ---
    if ( isset($_POST['action_save_bonus']) && check_admin_referer('save_bonus_action', 'bonus_nonce') ) {
        $new_bonus = intval($_POST['dw_referral_bonus_amount']);
        update_option('dw_referral_bonus_amount', $new_bonus);
        $current_bonus_setting = $new_bonus;
        echo '<div class="notice notice-success is-dismissible"><p>Pengaturan bonus berhasil disimpan.</p></div>';
    }

    // --- 3. PREPARE DATA TABLE ---
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

    // --- 4. TAMPILAN UI/UX MODERN ---
    ?>
    <style>
        :root {
            --dw-primary: #2271b1;
            --dw-success: #00ba37;
            --dw-text: #3c434a;
            --dw-light-bg: #f6f7f7;
            --dw-border: #c3c4c7;
        }
        .dw-wrap {
            max-width: 1200px;
            margin: 20px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        
        /* Stats Cards */
        .dw-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .dw-stat-card {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e4e7;
            display: flex;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dw-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .dw-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 16px;
        }
        .dw-stat-icon.blue { background: #e3f2fd; color: #2271b1; }
        .dw-stat-icon.green { background: #e6fffa; color: #00ba37; }
        
        .dw-stat-content h3 {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            color: #646970;
            letter-spacing: 0.5px;
        }
        .dw-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1d2327;
            line-height: 1.2;
            margin-top: 4px;
        }
        .dw-stat-meta {
            font-size: 12px;
            color: #8c8f94;
            margin-top: 2px;
        }

        /* Action Bar */
        .dw-action-bar {
            background: #fff;
            border: 1px solid #e2e4e7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .dw-settings-form {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #eaecf0;
        }
        .dw-settings-form label { font-weight: 600; font-size: 13px; color: #1d2327; }
        .dw-settings-form input[type="number"] {
            width: 70px;
            border-radius: 4px;
            border: 1px solid #c3c4c7;
            padding: 4px 8px;
        }
        .dw-search-box {
            position: relative;
        }
        .dw-search-box input {
            padding-left: 30px;
            border-radius: 20px;
            border: 1px solid #c3c4c7;
            width: 250px;
            font-size: 13px;
        }
        .dw-search-box .dashicons {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #8c8f94;
        }

        /* Table Styling */
        .dw-table-wrapper {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e4e7;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .wp-list-table {
            border: none;
            box-shadow: none;
        }
        .wp-list-table thead th {
            background: #f8f9fa;
            border-bottom: 1px solid #e2e4e7;
            font-weight: 600;
            color: #1d2327;
            padding: 15px 12px;
            font-size: 13px;
        }
        .wp-list-table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            color: #50575e;
            border-bottom: 1px solid #f0f0f1;
        }
        .wp-list-table tbody tr:last-child td { border-bottom: none; }
        .wp-list-table tbody tr:hover { background: #fafafa; }
        
        /* Badges & Chips */
        .dw-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .dw-badge-success { background: #e7f9ed; color: #00ba37; }
        .dw-badge-warning { background: #fff8e5; color: #f5a623; }
        .dw-badge-error { background: #fbeaea; color: #d63638; }
        .dw-badge-blue { background: #e3f2fd; color: #2271b1; }
        
        .dw-code-chip {
            font-family: 'Monaco', 'Consolas', monospace;
            background: #f0f0f1;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #2271b1;
            border: 1px solid #dcdcde;
            letter-spacing: 0.5px;
        }
        
        .dw-user-info {
            display: flex;
            flex-direction: column;
        }
        .dw-user-name { font-weight: 600; color: #1d2327; margin-bottom: 2px; }
        .dw-user-meta { font-size: 12px; color: #8c8f94; }

    </style>

    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline" style="font-size: 24px; margin-bottom: 20px;">
            Riwayat Reward Referral <span style="font-weight:400; color:#646970; font-size:18px;">Pedagang</span>
        </h1>
        
        <!-- Statistik -->
        <div class="dw-stats-grid">
            <div class="dw-stat-card">
                <div class="dw-stat-icon blue"><span class="dashicons dashicons-groups"></span></div>
                <div class="dw-stat-content">
                    <h3>Referral Sukses</h3>
                    <div class="dw-stat-value"><?php echo number_format($total_referrals); ?></div>
                    <div class="dw-stat-meta">Pembeli Baru Terdaftar</div>
                </div>
            </div>
            <div class="dw-stat-card">
                <div class="dw-stat-icon green"><span class="dashicons dashicons-awards"></span></div>
                <div class="dw-stat-content">
                    <h3>Total Bonus</h3>
                    <div class="dw-stat-value"><?php echo number_format($total_quota_given); ?></div>
                    <div class="dw-stat-meta">Kuota Transaksi Diberikan</div>
                </div>
            </div>
        </div>

        <!-- Action Bar: Settings & Sync -->
        <div class="dw-action-bar">
            <!-- Kiri: Pengaturan -->
            <form method="post" class="dw-settings-form">
                <?php wp_nonce_field('save_bonus_action', 'bonus_nonce'); ?>
                <input type="hidden" name="action_save_bonus" value="1">
                <label for="dw_referral_bonus_amount">Bonus per Referral:</label>
                <input type="number" name="dw_referral_bonus_amount" id="dw_referral_bonus_amount" value="<?php echo esc_attr($current_bonus_setting); ?>" min="0">
                <span style="font-size:13px; color:#646970;">Kuota</span>
                <button type="submit" class="button button-secondary button-small">Simpan</button>
            </form>

            <!-- Kanan: Sync & Search -->
            <div style="display:flex; gap:10px; align-items:center;">
                <form method="post">
                    <?php wp_nonce_field('sync_rewards_action', 'sync_nonce'); ?>
                    <input type="hidden" name="action_sync_rewards" value="1">
                    <button type="submit" class="button button-secondary" title="Cek data yang hilang" onclick="return confirm('Sistem akan memindai tabel pembeli dan membuat data reward yang hilang secara otomatis. Lanjutkan?')">
                        <span class="dashicons dashicons-update" style="vertical-align:middle; margin-top:-2px;"></span> Sinkronisasi Data
                    </button>
                </form>

                <form method="get" class="dw-search-box">
                    <input type="hidden" name="page" value="dw-referral-reward">
                    <span class="dashicons dashicons-search"></span>
                    <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Cari Toko, Pembeli, atau Kode...">
                </form>
            </div>
        </div>

        <!-- Main Table -->
        <div class="dw-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%;">Waktu</th>
                        <th style="width: 25%;">Pedagang (Penerima)</th>
                        <th style="width: 25%;">Pembeli Baru (Referral)</th>
                        <th style="width: 15%;">Kode Dipakai</th>
                        <th style="width: 10%; text-align:center;">Bonus</th>
                        <th style="width: 10%; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rewards ) ) : ?>
                        <tr>
                            <td colspan="6" style="padding:60px 20px; text-align:center;">
                                <div style="color:#a0a5ab; margin-bottom:10px;">
                                    <span class="dashicons dashicons-database" style="font-size:48px; width:48px; height:48px;"></span>
                                </div>
                                <h3 style="margin:0; color:#646970;">Belum ada data referral</h3>
                                <p style="color:#8c8f94; margin-top:5px;">Data referral akan muncul di sini setelah ada pembeli yang mendaftar menggunakan kode pedagang.</p>
                                <br>
                                <button type="button" class="button button-small" onclick="document.querySelector('form[name=action_sync_rewards] button').click()">Coba Sinkronisasi Manual</button>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rewards as $row ) : ?>
                            <tr>
                                <td>
                                    <div class="dw-user-info">
                                        <span class="dw-user-name"><?php echo date('d M Y', strtotime($row->created_at)); ?></span>
                                        <span class="dw-user-meta"><?php echo date('H:i', strtotime($row->created_at)); ?> WIB</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="dw-user-info">
                                        <span class="dw-user-name"><?php echo esc_html($row->nama_toko); ?></span>
                                        <span class="dw-user-meta"><span class="dashicons dashicons-store" style="font-size:12px;vertical-align:text-top;"></span> <?php echo esc_html($row->nama_pemilik); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="dw-user-info">
                                        <span class="dw-user-name"><?php echo esc_html($row->nama_pembeli); ?></span>
                                        <span class="dw-user-meta"><span class="dashicons dashicons-email" style="font-size:12px;vertical-align:text-top;"></span> <?php echo esc_html($row->user_email); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="dw-code-chip"><?php echo esc_html($row->kode_referral_used); ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="dw-badge dw-badge-blue">
                                        +<?php echo intval($row->bonus_quota_diberikan); ?> Kuota
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if($row->status == 'verified'): ?>
                                        <span class="dw-badge dw-badge-success"><span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px; margin-right:2px;"></span> Sukses</span>
                                    <?php elseif($row->status == 'fraud'): ?>
                                        <span class="dw-badge dw-badge-error">Fraud</span>
                                    <?php else: ?>
                                        <span class="dw-badge dw-badge-warning"><?php echo ucfirst($row->status); ?></span>
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