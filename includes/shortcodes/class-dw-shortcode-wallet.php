<?php
/**
 * Shortcode: [dw_wallet_dashboard]
 * Path: includes/shortcodes/class-dw-shortcode-wallet.php
 * Menampilkan saldo, form withdraw, dan riwayat transaksi user.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Shortcode_Wallet {

    public static function init() {
        add_shortcode('dw_wallet_dashboard', [__CLASS__, 'render']);
    }

    public static function render($atts) {
        // 1. Cek Login
        if (!is_user_logged_in()) {
            return '<div class="dw-alert dw-alert-warning" style="background:#fff3cd; color:#856404; padding:10px; border-radius:4px;">Silakan login untuk mengakses dompet Anda.</div>';
        }

        $user_id = get_current_user_id();
        
        // 2. Handle Form Submission (Request Withdraw)
        $message = '';
        if (isset($_POST['dw_withdraw_submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'dw_withdraw_request')) {
            $amount = floatval($_POST['amount']);
            $bank_details = sanitize_textarea_field($_POST['bank_details']);
            
            if (class_exists('DW_Wallet')) {
                // Minimum penarikan (Default 50rb)
                $min_withdraw = get_option('dw_min_withdraw_amount', 50000); 
                
                if ($amount < $min_withdraw) {
                     $message = '<div class="dw-alert dw-alert-danger" style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Minimal penarikan adalah Rp ' . number_format($min_withdraw, 0, ',', '.') . '</div>';
                } else {
                    $result = DW_Wallet::create_withdrawal_request($user_id, $amount, $bank_details);
                    if (is_wp_error($result)) {
                        $message = '<div class="dw-alert dw-alert-danger" style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">' . $result->get_error_message() . '</div>';
                    } else {
                        $message = '<div class="dw-alert dw-alert-success" style="background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;">Permintaan penarikan berhasil dikirim. Menunggu persetujuan admin.</div>';
                    }
                }
            }
        }

        // 3. Ambil Data Saldo & History
        $balance = 0;
        $history = [];
        if (class_exists('DW_Wallet')) {
            $balance = DW_Wallet::get_balance($user_id);
            // Ambil history dari meta (fallback)
            $history = get_user_meta($user_id, '_dw_wallet_history', true);
            if (!is_array($history)) $history = [];
            
            // Sort history descending (Terbaru diatas)
             usort($history, function($a, $b) {
                $t1 = isset($a['date']) ? strtotime($a['date']) : 0;
                $t2 = isset($b['date']) ? strtotime($b['date']) : 0;
                return $t2 - $t1;
            });
        }

        ob_start();
        ?>
        <div class="dw-wallet-wrapper">
            <?php echo $message; ?>
            
            <!-- Card Saldo -->
            <div class="dw-wallet-card" style="background: #fdfdfd; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <h3 style="margin:0; font-size:14px; color:#666;">Saldo Aktif</h3>
                    <div class="dw-balance" style="font-size: 24px; font-weight: bold; color: #2ecc71;">Rp <?php echo number_format($balance, 0, ',', '.'); ?></div>
                </div>
                <div>
                    <button id="btn-show-withdraw" class="button button-primary" style="margin-top:10px;">Tarik Dana</button>
                </div>
            </div>

            <!-- Form Withdraw (Hidden by default) -->
            <div id="dw-withdraw-form" style="display:none; margin-bottom: 20px; border: 1px solid #cce5ff; background: #e7f5ff; padding: 15px; border-radius: 5px;">
                <h4 style="margin-top:0;">Request Penarikan Dana</h4>
                <form method="post" action="">
                    <?php wp_nonce_field('dw_withdraw_request'); ?>
                    <p>
                        <label style="display:block; margin-bottom:5px;">Nominal (Rp)</label>
                        <input type="number" name="amount" min="<?php echo get_option('dw_min_withdraw_amount', 50000); ?>" max="<?php echo esc_attr($balance); ?>" required style="width:100%; max-width:300px; padding:8px;">
                        <br><small>Min: Rp <?php echo number_format(get_option('dw_min_withdraw_amount', 50000), 0, ',', '.'); ?></small>
                    </p>
                    <p>
                        <label style="display:block; margin-bottom:5px;">Detail Rekening (Nama Bank, No Rek, Atas Nama)</label>
                        <textarea name="bank_details" required style="width:100%; padding:8px;" rows="3" placeholder="Contoh: BCA 1234567890 a.n Budi Santoso"></textarea>
                    </p>
                    <p>
                        <input type="submit" name="dw_withdraw_submit" class="button button-primary" value="Kirim Permintaan">
                    </p>
                </form>
            </div>

            <!-- Riwayat Transaksi -->
            <div class="dw-wallet-history">
                <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px;">Riwayat Transaksi</h4>
                <div style="overflow-x:auto;">
                    <table class="dw-table" style="width:100%; border-collapse: collapse; font-size: 14px;">
                        <thead>
                            <tr style="background:#f9f9f9; text-align:left;">
                                <th style="padding:10px; border-bottom:1px solid #eee;">Tanggal</th>
                                <th style="padding:10px; border-bottom:1px solid #eee;">Keterangan</th>
                                <th style="padding:10px; border-bottom:1px solid #eee;">Tipe</th>
                                <th style="padding:10px; border-bottom:1px solid #eee;">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)) : ?>
                                <tr><td colspan="4" style="padding:15px; text-align:center; color:#999;">Belum ada transaksi.</td></tr>
                            <?php else : ?>
                                <?php foreach ($history as $trx) : 
                                    $is_in = isset($trx['flow']) && $trx['flow'] == 'in';
                                    $color = $is_in ? '#27ae60' : '#c0392b';
                                    $sign = $is_in ? '+' : '-';
                                    $date_display = isset($trx['date']) ? date('d M Y H:i', strtotime($trx['date'])) : '-';
                                ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding:10px;"><?php echo $date_display; ?></td>
                                    <td style="padding:10px;"><?php echo isset($trx['desc']) ? esc_html($trx['desc']) : '-'; ?></td>
                                    <td style="padding:10px;">
                                        <span style="font-size:11px; padding:3px 6px; background:#eee; border-radius:3px; text-transform:uppercase;">
                                            <?php echo isset($trx['type']) ? esc_html($trx['type']) : 'trx'; ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px; color:<?php echo $color; ?>; font-weight:bold;">
                                        <?php echo $sign; ?> Rp <?php echo number_format($trx['amount'], 0, ',', '.'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('#btn-show-withdraw').click(function(e){
                e.preventDefault();
                $('#dw-withdraw-form').slideToggle();
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}