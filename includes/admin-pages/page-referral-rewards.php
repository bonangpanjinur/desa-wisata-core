<?php
/**
 * Page: Referral Rewards Log
 * Location: includes/admin-pages/page-refferal-rewards.php
 * Description: Menampilkan riwayat reward kuota yang diberikan kepada pedagang
 * karena berhasil mengajak pembeli baru mendaftar (Logic Database v3.7).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dw_render_referral_rewards_page() {
    global $wpdb;

    // --- 1. PREPARE DATA ---
    // Menggabungkan tabel Reward -> Pedagang -> User (Pembeli)
    $table_reward   = $wpdb->prefix . 'dw_referral_reward';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_users    = $wpdb->base_prefix . 'users';

    // Handle Filter Pencarian (Opsional, jika ingin dikembangkan nanti)
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
    
    // --- 2. HITUNG STATISTIK ---
    $total_quota_given = 0;
    $total_referrals = count($rewards);
    
    foreach($rewards as $r) {
        $total_quota_given += intval($r->bonus_quota_diberikan);
    }

    // --- 3. TAMPILAN HALAMAN (UI) ---
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Riwayat Reward Referral Pedagang</h1>
        <p class="description">Daftar pedagang yang berhasil mengajak pembeli baru mendaftar dan mendapatkan bonus kuota transaksi.</p>
        <hr class="wp-header-end">
        
        <!-- Search Bar -->
        <form method="get" style="margin: 20px 0;">
            <input type="hidden" name="page" value="dw-referral-reward">
            <?php 
            // Pertahankan parameter halaman lain jika ada
            ?>
            <p class="search-box">
                <label class="screen-reader-text" for="post-search-input">Cari Reward:</label>
                <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Cari Toko / Pembeli / Kode...">
                <input type="submit" id="search-submit" class="button" value="Cari Data">
            </p>
        </form>

        <!-- Stats Cards -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom: 20px; clear:both;">
            <div class="dw-card" style="background:#fff; padding:20px; border-left:4px solid #2271b1; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                <h3 style="margin:0 0 5px; color:#646970; font-size:13px; text-transform:uppercase;">Total Referral Sukses</h3>
                <span style="font-size:28px; font-weight:700; color:#1d2327;"><?php echo number_format($total_referrals); ?></span>
                <span style="color:#646970; font-size:12px;">Pembeli Baru</span>
            </div>
            <div class="dw-card" style="background:#fff; padding:20px; border-left:4px solid #00a32a; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                <h3 style="margin:0 0 5px; color:#646970; font-size:13px; text-transform:uppercase;">Total Bonus Diberikan</h3>
                <span style="font-size:28px; font-weight:700; color:#1d2327;"><?php echo number_format($total_quota_given); ?></span>
                <span style="color:#646970; font-size:12px;">Kuota Transaksi</span>
            </div>
        </div>

        <!-- Main Table -->
        <div class="dw-card" style="background:#fff; padding:0; box-shadow:0 1px 2px rgba(0,0,0,0.1); overflow:hidden; border-radius:4px;">
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
                                <span class="dashicons dashicons-groups" style="font-size:40px; width:40px; height:40px; margin-bottom:10px;"></span><br>
                                Belum ada data referral yang tercatat.
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
                                    <small><?php echo esc_html($row->nama_pemilik); ?></small>
                                </td>
                                <td>
                                    <span class="dashicons dashicons-admin-users" style="font-size:14px; color:#8c8f94; vertical-align:middle;"></span> 
                                    <?php echo esc_html($row->nama_pembeli); ?><br>
                                    <small style="color:#8c8f94;"><?php echo esc_html($row->user_email); ?></small>
                                </td>
                                <td>
                                    <code style="background:#f0f0f1; padding:3px 6px; border-radius:3px; font-weight:600; color:#2271b1;">
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
                                        <span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span> <span style="font-weight:600; color:#00a32a;">Sukses</span>
                                    <?php elseif($row->status == 'fraud'): ?>
                                        <span class="dashicons dashicons-warning" style="color:#d63638;"></span> <span style="font-weight:600; color:#d63638;">Fraud</span>
                                    <?php else: ?>
                                        <span style="font-weight:600; color:#dba617;"><?php echo ucfirst($row->status); ?></span>
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