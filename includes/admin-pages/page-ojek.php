<?php
/**
 * Halaman Panel Ojek (Backend UI for Driver)
 * Menampilkan: Status Switch, Order Masuk, dan Riwayat.
 */

if (!defined('ABSPATH')) {
    exit;
}

function dw_ojek_panel_page_render() {
    $user_id = get_current_user_id();
    $status_kerja = get_user_meta($user_id, 'dw_ojek_status_kerja', true) ?: 'offline';
    $kuota = (int) get_user_meta($user_id, 'dw_ojek_quota', true);
    
    // Handle Tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'status';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Panel Driver Ojek</h1>
        <hr class="wp-header-end">

        <!-- Info Bar -->
        <div class="notice notice-info inline" style="margin: 15px 0; padding: 10px;">
            <p>
                <strong>Status:</strong> 
                <span class="dw-status-badge <?php echo $status_kerja; ?>">
                    <?php echo strtoupper($status_kerja); ?>
                </span> 
                | 
                <strong>Sisa Kuota:</strong> <?php echo $kuota; ?> Trip
            </p>
        </div>

        <nav class="nav-tab-wrapper">
            <a href="?page=dw-ojek-panel&tab=status" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>">Status & Order</a>
            <a href="?page=dw-ojek-panel&tab=riwayat" class="nav-tab <?php echo $active_tab == 'riwayat' ? 'nav-tab-active' : ''; ?>">Riwayat Perjalanan</a>
            <a href="?page=dw-ojek-panel&tab=profil" class="nav-tab <?php echo $active_tab == 'profil' ? 'nav-tab-active' : ''; ?>">Profil Kendaraan</a>
        </nav>

        <div class="dw-tab-content" style="margin-top: 20px;">
            <?php 
            if ($active_tab === 'status') {
                dw_render_ojek_tab_status($user_id, $status_kerja, $kuota);
            } elseif ($active_tab === 'riwayat') {
                dw_render_ojek_tab_riwayat($user_id);
            } elseif ($active_tab === 'profil') {
                echo '<p>Edit profil dan data kendaraan dapat dilakukan melalui menu "Profil" di pojok kanan atas.</p>';
                echo '<a href="profile.php" class="button">Edit Profil</a>';
            }
            ?>
        </div>
    </div>

    <!-- Script Sederhana untuk Toggle Status -->
    <script>
    jQuery(document).ready(function($) {
        $('#btn-toggle-status').on('click', function() {
            var btn = $(this);
            var currentStatus = btn.data('status');
            var newStatus = (currentStatus === 'online') ? 'offline' : 'online';
            
            btn.prop('disabled', true).text('Updating...');

            $.post(ajaxurl, {
                action: 'dw_ojek_update_status', // Handler di class-dw-ojek-handler.php
                status: newStatus,
                nonce: '<?php echo wp_create_nonce("dw_nonce"); ?>'
            }, function(res) {
                if(res.success) {
                    location.reload();
                } else {
                    alert('Gagal: ' + (res.data.message || 'Error'));
                    btn.prop('disabled', false).text('Coba Lagi');
                }
            });
        });
    });
    </script>
    <?php
}

function dw_render_ojek_tab_status($user_id, $status_kerja, $kuota) {
    ?>
    <div class="card" style="max-width: 600px; padding: 20px;">
        <h3>Kontrol Status</h3>
        <p>Anda harus berstatus <strong>ONLINE</strong> agar pesanan masuk ke aplikasi Anda.</p>
        
        <?php if ($kuota <= 0): ?>
            <div class="notice notice-error inline"><p>Kuota Habis! Anda tidak bisa Online. Silakan hubungi admin untuk topup.</p></div>
            <button class="button button-large" disabled>Aktifkan Aplikasi (Kuota Habis)</button>
        <?php else: ?>
            <?php if ($status_kerja === 'online'): ?>
                <button id="btn-toggle-status" data-status="online" class="button button-secondary button-hero">
                    Matikan Aplikasi (Go Offline)
                </button>
            <?php else: ?>
                <button id="btn-toggle-status" data-status="offline" class="button button-primary button-hero">
                    Aktifkan Aplikasi (Go Online)
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div style="margin-top: 30px;">
        <h3>Pesanan Masuk (Realtime Mockup)</h3>
        <p class="description">Daftar orderan yang menunggu driver di sekitar Anda.</p>
        
        <?php 
        // Query Pesanan dengan status 'menunggu_driver'
        // Dalam implementasi nyata, ini harusnya pakai AJAX polling / WebSocket
        global $wpdb;
        $orders = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE status_transaksi = 'menunggu_driver' ORDER BY created_at DESC LIMIT 5");
        
        if ($orders): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Jemput</th>
                        <th>Tujuan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $order): 
                        $ojek_data = json_decode($order->ojek_data, true);
                        $pickup = $ojek_data['pickup']['address'] ?? '-';
                        // $dropoff = $ojek_data['dropoff']['address'] ?? '-';
                        ?>
                        <tr>
                            <td>#<?php echo esc_html($order->kode_unik); ?></td>
                            <td><?php echo esc_html($pickup); ?></td>
                            <td>(Lihat Peta)</td>
                            <td>
                                <button class="button button-small button-primary">Ajukan Ongkos</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="notice notice-warning inline"><p>Belum ada pesanan masuk saat ini.</p></div>
        <?php endif; ?>
    </div>
    <?php
}

function dw_render_ojek_tab_riwayat($user_id) {
    global $wpdb;
    // Ambil data dari tabel dw_transaksi (Join atau cari assigned_ojek_id di JSON ojek_data jika perlu)
    // Sederhananya kita asumsikan ada kolom assigned_driver atau kita parse JSON (berat)
    // Untuk performa, disarankan simpan ID driver di kolom terpisah atau meta saat deal.
    
    // Mockup View
    ?>
    <h3>Riwayat Perjalanan Terakhir</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Kode</th>
                <th>Tujuan</th>
                <th>Pendapatan</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="5">Belum ada riwayat perjalanan.</td></tr>
        </tbody>
    </table>
    <?php
}
?>