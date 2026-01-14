<?php
/**
 * File: includes/shortcodes/class-dw-shortcodes.php
 * * Handler untuk semua shortcode di sisi frontend.
 * * Menggabungkan fitur Dashboard, POS, dan Top Up Paket (Xendit).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcodes {

    public function __construct() {
        // Dashboard & Fitur Utama
        add_shortcode( 'dw_dashboard_toko', [ $this, 'render_dashboard_toko' ] );
        add_shortcode( 'dw_dashboard_desa', [ $this, 'render_dashboard_desa' ] );
        add_shortcode( 'dw_transaksi_list', [ $this, 'render_transaksi_list' ] );
        add_shortcode( 'dw_pos_system', [ $this, 'render_pos_system' ] );
        
        // FASE 4: Top Up Paket
        add_shortcode( 'dw_topup_paket', [ $this, 'render_topup_paket' ] );
        
        // Register Scripts specific for shortcodes
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
        
        // Ajax handler untuk create invoice
        add_action( 'wp_ajax_dw_create_topup_invoice', [ $this, 'ajax_create_topup_invoice' ] );
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_script( 'jquery' );
        // SweetAlert2 (optional but recommended for UX)
        wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true );
        
        // CSS sederhana untuk card paket & dashboard
        wp_add_inline_style( 'wp-block-library', '
            .dw-frontend-wrapper { margin-bottom: 30px; }
            .dw-header-section { margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
            .dw-stats-grid-frontend { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
            .dw-stat-card { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .dw-stat-card .label { display: block; color: #666; font-size: 0.9em; margin-bottom: 5px; }
            .dw-stat-card .value { display: block; font-size: 1.5em; font-weight: bold; color: #333; }
            .dw-action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
            .dw-btn { padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: 500; display: inline-block; }
            .dw-btn-primary { background: #0073aa; color: #fff; border: 1px solid #0073aa; }
            .dw-btn-secondary { background: #f0f0f1; color: #333; border: 1px solid #ccc; }
            .dw-btn-outline { background: transparent; color: #0073aa; border: 1px solid #0073aa; }
            
            /* Topup CSS */
            .dw-paket-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
            .dw-paket-card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; text-align: center; transition: 0.3s; cursor: pointer; background: #fff; }
            .dw-paket-card:hover, .dw-paket-card.selected { border-color: #0073aa; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
            .dw-paket-price { font-size: 1.5em; font-weight: bold; color: #28a745; margin: 10px 0; }
            .dw-btn-pay { width: 100%; padding: 12px; background: #0073aa; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
            .dw-btn-pay:disabled { background: #ccc; cursor: not-allowed; }

            /* Table */
            .dw-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .dw-table th, .dw-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            .dw-table th { background: #f9f9f9; }
        ' );
    }

    /**
     * 1. Shortcode: [dw_dashboard_toko]
     * Halaman utama pedagang untuk melihat statistik dan menu.
     */
    public function render_dashboard_toko($atts) {
        // Cek Login & Role
        if ( ! is_user_logged_in() ) {
            return $this->alert_message( 'Silakan login terlebih dahulu.', 'warning' );
        }
        
        $user = wp_get_current_user();
        if ( ! in_array( 'pedagang', (array) $user->roles ) && ! in_array( 'administrator', (array) $user->roles ) ) {
            return $this->alert_message( 'Akses ditolak. Halaman ini khusus Pedagang.', 'danger' );
        }

        // Mulai Output Buffer
        ob_start();
        ?>
        <div class="dw-frontend-wrapper">
            <div class="dw-header-section">
                <h2>Halo, <?php echo esc_html( $user->display_name ); ?> 👋</h2>
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
    public function render_dashboard_desa($atts) {
        if ( ! is_user_logged_in() ) return $this->alert_message( 'Silakan login.', 'warning' );

        $user = wp_get_current_user();
        // Cek capability khusus admin desa (asumsi 'manage_desa_wisata' atau role check)
        if ( ! in_array( 'admin_desa', (array) $user->roles ) && ! in_array( 'administrator', (array) $user->roles ) ) {
            return $this->alert_message( 'Akses khusus Admin Desa.', 'danger' );
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
    public function render_transaksi_list($atts) {
        if ( ! is_user_logged_in() ) return $this->alert_message( 'Login diperlukan.', 'warning' );

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
    public function render_pos_system($atts) {
        if ( ! is_user_logged_in() ) return $this->alert_message( 'Silakan login.', 'warning' );

        ob_start();
        ?>
        <div class="dw-pos-container">
            <div class="dw-pos-header">
                <h3>Kasir Desa Wisata</h3>
                <input type="text" id="dw-pos-search" placeholder="Cari produk (Scan Barcode)..." style="width: 100%; padding: 10px; margin-bottom: 10px;">
            </div>
            
            <div class="dw-pos-layout">
                <div class="dw-pos-products">
                    <p style="padding:20px; text-align:center; color:#666; border: 1px dashed #ccc;">Memuat Katalog Produk...</p>
                    <!-- Produk akan di-load via AJAX di Fase selanjutnya -->
                </div>
                <div class="dw-pos-cart" style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
                    <h4>Keranjang Belanja</h4>
                    <div class="dw-cart-items">
                        <!-- Cart Items -->
                    </div>
                    <div class="dw-cart-total" style="margin-top: 20px;">
                        <strong>Total: Rp 0</strong>
                        <button class="dw-btn dw-btn-success" style="width:100%; margin-top:10px; background: #28a745; color: white; border: none;">Bayar Sekarang</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 5. Shortcode: [dw_topup_paket] (FASE 4)
     * Form pemilihan paket transaksi & integrasi Xendit
     */
    public function render_topup_paket( $atts ) {
        if ( ! is_user_logged_in() || ! dw_is_pedagang() ) {
            return $this->alert_message( 'Silakan login sebagai pedagang untuk membeli paket.', 'warning' );
        }

        global $wpdb;
        $pakets = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}dw_paket_transaksi WHERE status = 'active' ORDER BY harga ASC" );

        ob_start();
        ?>
        <div class="dw-topup-container">
            <h3>Pilih Paket Transaksi</h3>
            <p>Tambah kuota transaksi untuk toko Anda agar tetap bisa berjualan.</p>
            
            <form id="dw-topup-form">
                <div class="dw-paket-grid">
                    <?php if ( $pakets ) : ?>
                        <?php foreach ( $pakets as $paket ) : ?>
                            <div class="dw-paket-card" onclick="selectPaket(this, <?php echo $paket->id; ?>)">
                                <h4><?php echo esc_html( $paket->nama_paket ); ?></h4>
                                <div class="dw-paket-price">Rp <?php echo number_format( $paket->harga, 0, ',', '.' ); ?></div>
                                <p><?php echo esc_html( $paket->jumlah_transaksi ); ?> Transaksi</p>
                                <p class="description"><?php echo esc_html( $paket->deskripsi ); ?></p>
                                <input type="radio" name="paket_id" value="<?php echo $paket->id; ?>" style="display:none;">
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Belum ada paket tersedia.</p>
                    <?php endif; ?>
                </div>
                
                <input type="hidden" name="action" value="dw_create_topup_invoice">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dw_topup_nonce'); ?>">
                
                <button type="submit" id="btn-process-payment" class="dw-btn-pay" disabled>Bayar Sekarang via Xendit</button>
            </form>
        </div>

        <script>
        function selectPaket(el, id) {
            jQuery('.dw-paket-card').removeClass('selected');
            jQuery(el).addClass('selected');
            jQuery(el).find('input[type="radio"]').prop('checked', true);
            jQuery('#btn-process-payment').prop('disabled', false);
        }

        jQuery(document).ready(function($) {
            $('#dw-topup-form').on('submit', function(e) {
                e.preventDefault();
                var btn = $('#btn-process-payment');
                btn.text('Memproses...').prop('disabled', true);

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.invoice_url;
                        } else {
                            Swal.fire('Error', response.data.message || 'Gagal membuat invoice', 'error');
                            btn.text('Bayar Sekarang via Xendit').prop('disabled', false);
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Terjadi kesalahan server', 'error');
                        btn.text('Bayar Sekarang via Xendit').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX Handler: Buat Invoice Xendit
     */
    public function ajax_create_topup_invoice() {
        check_ajax_referer( 'dw_topup_nonce', 'nonce' );
        
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $user_id = get_current_user_id();
        $paket_id = isset( $_POST['paket_id'] ) ? intval( $_POST['paket_id'] ) : 0;

        global $wpdb;
        $paket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dw_paket_transaksi WHERE id = %d AND status = 'active'", $paket_id ) );

        if ( ! $paket ) {
            wp_send_json_error( [ 'message' => 'Paket tidak ditemukan.' ] );
        }

        // 1. Buat Record Transaksi Pending di DB
        $result = $wpdb->insert(
            "{$wpdb->prefix}dw_transaksi_paket",
            [
                'user_id' => $user_id,
                'paket_id' => $paket_id,
                'total_harga' => $paket->harga,
                'status' => 'pending',
                'created_at' => current_time( 'mysql' )
            ]
        );

        if ( ! $result ) {
            wp_send_json_error( [ 'message' => 'Gagal membuat data transaksi.' ] );
        }

        $transaksi_id = $wpdb->insert_id;
        $current_user = wp_get_current_user();
        
        // 2. Panggil Xendit Gateway
        require_once DW_PLUGIN_DIR . 'includes/classes/gateways/class-dw-xendit-gateway.php';
        $gateway = new DW_Xendit_Gateway();

        $external_id = 'DW-TOPUP-' . $transaksi_id;
        
        $params = [
            'external_id' => $external_id,
            'amount'      => $paket->harga,
            'payer_email' => $current_user->user_email,
            'description' => "Top Up Paket: " . $paket->nama_paket
        ];

        $invoice = $gateway->create_invoice( $params );

        if ( is_wp_error( $invoice ) ) {
            // Update status failed jika API error
            $wpdb->update( "{$wpdb->prefix}dw_transaksi_paket", ['status' => 'failed'], ['id' => $transaksi_id] );
            wp_send_json_error( [ 'message' => $invoice->get_error_message() ] );
        }

        // 3. Simpan URL Invoice (opsional) & Kembalikan URL ke Frontend
        if ( isset( $invoice['invoice_url'] ) ) {
            wp_send_json_success( [ 'invoice_url' => $invoice['invoice_url'] ] );
        } else {
            wp_send_json_error( [ 'message' => 'Gagal mendapatkan URL Pembayaran.' ] );
        }
    }

    /**
     * Helper: Pesan Alert Sederhana
     */
    private function alert_message($msg, $type = 'info') {
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