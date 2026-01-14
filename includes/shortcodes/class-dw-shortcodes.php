<?php
/**
 * Class DW_Shortcodes
 * Path: includes/shortcodes/class-dw-shortcodes.php
 * Description: Menangani registrasi dan logika rendering semua shortcode frontend.
 * Version: 2.5.0
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Shortcodes {

    /**
     * Inisialisasi Hook
     */
    public static function init() {
        // Dashboard Frontend
        add_shortcode('dw_dashboard_toko', [__CLASS__, 'render_dashboard_toko']);
        add_shortcode('dw_dashboard_desa', [__CLASS__, 'render_dashboard_desa']);
        
        // Fitur Transaksi & POS
        add_shortcode('dw_transaksi_list', [__CLASS__, 'render_transaksi_list']);
        add_shortcode('dw_pos_system', [__CLASS__, 'render_pos_system']);
    }

    /**
     * 1. Shortcode: [dw_dashboard_toko]
     * Halaman utama pedagang untuk melihat statistik dan menu.
     */
    public static function render_dashboard_toko($atts) {
        // Cek Login & Role
        if (!is_user_logged_in()) {
            return self::alert_message('Silakan login terlebih dahulu.', 'warning');
        }
        
        $user = wp_get_current_user();
        if (!in_array('pedagang', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            return self::alert_message('Akses ditolak. Halaman ini khusus Pedagang.', 'danger');
        }

        // Mulai Output Buffer
        ob_start();
        ?>
        <div class="dw-frontend-wrapper">
            <div class="dw-header-section">
                <h2>Halo, <?php echo esc_html($user->display_name); ?> 👋</h2>
                <p>Selamat datang di Dashboard Pedagang.</p>
            </div>

            <!-- Statistik Ringkas (Placeholder Logic) -->
            <div class="dw-stats-grid-frontend">
                <div class="dw-stat-card">
                    <span class="label">Total Penjualan</span>
                    <strong class="value">Rp 0</strong>
                </div>
                <div class="dw-stat-card">
                    <span class="label">Pesanan Baru</span>
                    <strong class="value">0</strong>
                </div>
                <div class="dw-stat-card">
                    <span class="label">Produk Aktif</span>
                    <strong class="value">0</strong>
                </div>
            </div>

            <!-- Menu Navigasi Cepat -->
            <div class="dw-action-buttons">
                <a href="#" class="dw-btn dw-btn-primary">Kelola Produk</a>
                <a href="#" class="dw-btn dw-btn-secondary">Riwayat Transaksi</a>
                <a href="#" class="dw-btn dw-btn-outline">Pengaturan Toko</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 2. Shortcode: [dw_dashboard_desa]
     * Dashboard untuk Admin Desa memantau pedagang dan komisi.
     */
    public static function render_dashboard_desa($atts) {
        if (!is_user_logged_in()) return self::alert_message('Silakan login.', 'warning');

        $user = wp_get_current_user();
        // Cek capability khusus admin desa (asumsi 'manage_desa_wisata' atau role check)
        if (!in_array('admin_desa', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            return self::alert_message('Akses khusus Admin Desa.', 'danger');
        }

        ob_start();
        ?>
        <div class="dw-frontend-wrapper">
            <div class="dw-header-section">
                <h2>Dashboard Desa Wisata</h2>
                <p>Kelola ekosistem desa Anda.</p>
            </div>
            
            <div class="dw-info-box">
                <p>Fitur dashboard desa sedang dalam proses migrasi arsitektur.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 3. Shortcode: [dw_transaksi_list]
     * Menampilkan riwayat transaksi (Bisa untuk Pembeli atau Pedagang).
     */
    public static function render_transaksi_list($atts) {
        if (!is_user_logged_in()) return self::alert_message('Login diperlukan.', 'warning');

        ob_start();
        ?>
        <div class="dw-frontend-wrapper">
            <h3>Riwayat Transaksi</h3>
            <div class="dw-table-responsive">
                <table class="dw-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" style="text-align:center;">Belum ada data transaksi (Migrasi DB).</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 4. Shortcode: [dw_pos_system]
     * Halaman Kasir (Point of Sales).
     */
    public static function render_pos_system($atts) {
        if (!is_user_logged_in()) return self::alert_message('Silakan login.', 'warning');

        // Load asset khusus POS jika diperlukan
        // wp_enqueue_script('dw-pos-js', ...);

        ob_start();
        ?>
        <div class="dw-pos-container">
            <div class="dw-pos-header">
                <h3>Kasir Desa Wisata</h3>
                <input type="text" id="dw-pos-search" placeholder="Cari produk (Scan Barcode)...">
            </div>
            
            <div class="dw-pos-layout">
                <div class="dw-pos-products">
                    <p style="padding:20px; text-align:center; color:#666;">Memuat Katalog Produk...</p>
                    <!-- Produk akan di-load via AJAX di Fase selanjutnya -->
                </div>
                <div class="dw-pos-cart">
                    <h4>Keranjang Belanja</h4>
                    <div class="dw-cart-items">
                        <!-- Cart Items -->
                    </div>
                    <div class="dw-cart-total">
                        <strong>Total: Rp 0</strong>
                        <button class="dw-btn dw-btn-success" style="width:100%; margin-top:10px;">Bayar Sekarang</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper: Pesan Alert Sederhana
     */
    private static function alert_message($msg, $type = 'info') {
        // Mapping tipe ke warna/class (bisa disesuaikan dengan CSS tema)
        $style = 'padding: 15px; border-radius: 5px; margin-bottom: 20px;';
        switch ($type) {
            case 'danger': $style .= 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;'; break;
            case 'warning': $style .= 'background: #fef9c3; color: #854d0e; border: 1px solid #fde047;'; break;
            case 'success': $style .= 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;'; break;
            default: $style .= 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;'; break;
        }
        return sprintf('<div style="%s">%s</div>', esc_attr($style), esc_html($msg));
    }
}

// Jalankan Init
DW_Shortcodes::init();