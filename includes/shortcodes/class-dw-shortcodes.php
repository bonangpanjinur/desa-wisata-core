<?php
/**
 * Class DW_Shortcodes
 * Path: includes/shortcodes/class-dw-shortcodes.php
 * * UPDATE FASE 5.3: 
 * - Enqueue 'dw-frontend.css' dan 'dw-dashboard.js'
 * - Update struktur HTML [dw_dashboard_toko] dengan ID dan Skeleton Classes.
 * - Mempertahankan logika Top Up Paket & POS System.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_Shortcodes {

    public function __construct() {
        // Register Shortcodes
        add_shortcode('dw_dashboard_toko', [$this, 'render_dashboard_toko']);
        add_shortcode('dw_dashboard_desa', [$this, 'render_dashboard_desa']);
        add_shortcode('dw_transaksi_list', [$this, 'render_transaksi_list']);
        add_shortcode('dw_pos_system', [$this, 'render_pos_system']);
        add_shortcode('dw_topup_paket', [$this, 'render_topup_paket']); // Fase 4

        // Enqueue Scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // Ajax handler untuk create invoice (Fase 4 - Xendit)
        add_action( 'wp_ajax_dw_create_topup_invoice', [ $this, 'ajax_create_topup_invoice' ] );
    }

    public function enqueue_frontend_scripts() {
        // CSS Frontend Utama (Fase 5.3)
        wp_enqueue_style('dw-frontend-css', DW_PLUGIN_URL . 'assets/css/dw-frontend.css', [], '2.8.0');

        // JS Dependencies
        wp_enqueue_script('jquery');
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);

        // JS Dashboard Logic (Fase 5.3)
        // Load hanya jika ada shortcode dashboard
        global $post;
        if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'dw_dashboard_toko') || has_shortcode($post->post_content, 'dw_dashboard_desa'))) {
            wp_enqueue_script('dw-dashboard-js', DW_PLUGIN_URL . 'assets/js/dw-dashboard.js', ['jquery'], '2.8.0', true);
            
            // Kirim variable ke JS
            wp_localize_script('dw-dashboard-js', 'dw_dashboard_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dw_nonce_frontend')
            ]);
        }
        
        // CSS Inline Fallback / Tambahan (Opsional, jika dw-frontend.css belum terload sempurna)
        // Terutama untuk fitur Top Up yang CSS-nya spesifik
        wp_add_inline_style( 'dw-frontend-css', '
            /* Topup CSS Specific Override/Addition */
            .dw-paket-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
            .dw-paket-card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; text-align: center; transition: 0.3s; cursor: pointer; background: #fff; }
            .dw-paket-card:hover, .dw-paket-card.selected { border-color: #0073aa; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
            .dw-paket-price { font-size: 1.5em; font-weight: bold; color: #28a745; margin: 10px 0; }
            .dw-btn-pay { width: 100%; padding: 12px; background: #0073aa; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
            .dw-btn-pay:disabled { background: #ccc; cursor: not-allowed; }
        ' );
    }

    /**
     * 1. Shortcode: [dw_dashboard_toko]
     * Dashboard Pedagang dengan Skeleton Loader (Fase 5.3 Updated)
     */
    public function render_dashboard_toko($atts) {
        if (!is_user_logged_in()) return $this->alert_message('Silakan login.', 'warning');
        
        $user = wp_get_current_user();
        if (!in_array('pedagang', (array) $user->roles) && !in_array('administrator', (array) $user->roles) && !in_array('dw_ojek', (array) $user->roles)) {
            return $this->alert_message('Akses ditolak.', 'danger');
        }

        ob_start();
        ?>
        <div class="dw-frontend-wrapper">
            <!-- Header -->
            <div class="dw-header-section">
                <div>
                    <h2>Halo, <?php echo esc_html($user->display_name); ?> 👋</h2>
                    <p style="color:#64748b; margin-top:5px;">Berikut ringkasan aktivitas toko Anda.</p>
                </div>
                <div class="dw-action-buttons">
                    <a href="<?php echo site_url('/pos'); ?>" class="dw-btn dw-btn-primary">Buka Kasir (POS)</a>
                    <a href="<?php echo site_url('/transaksi'); ?>" class="dw-btn dw-btn-secondary">Riwayat Transaksi</a>
                    <a href="<?php echo site_url('/pengaturan-toko'); ?>" class="dw-btn dw-btn-outline">Pengaturan Toko</a>
                </div>
            </div>

            <!-- Statistik Grid dengan ID untuk JS -->
            <div class="dw-stats-grid">
                <div class="dw-stat-card">
                    <span class="dw-stat-label">Total Penjualan</span>
                    <!-- Tambahkan class dw-skeleton secara default, JS akan menghapusnya -->
                    <span id="stat-sales" class="dw-stat-value dw-skeleton dw-skeleton-text">Rp ...</span>
                </div>
                <div class="dw-stat-card">
                    <span class="dw-stat-label">Pesanan Baru</span>
                    <span id="stat-orders" class="dw-stat-value dw-skeleton dw-skeleton-text">...</span>
                </div>
                <div class="dw-stat-card">
                    <span class="dw-stat-label">Produk Aktif</span>
                    <span id="stat-products" class="dw-stat-value dw-skeleton dw-skeleton-text">...</span>
                </div>
            </div>

            <!-- Tabel Transaksi Terbaru -->
            <h3 style="margin-bottom:15px; color:#2c3e50;">Transaksi Terakhir</h3>
            <div class="dw-table-container">
                <table class="dw-table" id="dw-transaction-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Skeleton Rows (Loading State) -->
                        <?php for($i=0; $i<3; $i++): ?>
                        <tr>
                            <td><span class="dw-skeleton dw-skeleton-text" style="width:80px;"></span></td>
                            <td><span class="dw-skeleton dw-skeleton-text" style="width:120px;"></span></td>
                            <td><span class="dw-skeleton dw-skeleton-text" style="width:100px;"></span></td>
                            <td><span class="dw-skeleton dw-skeleton-text" style="width:60px;"></span></td>
                            <td><span class="dw-skeleton dw-skeleton-text" style="width:50px;"></span></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
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
        return sprintf('<div class="dw-badge dw-badge-%s" style="padding:15px; font-size:1rem; display:block; margin-bottom:20px;">%s</div>', esc_attr($type), esc_html($msg));
    }
}