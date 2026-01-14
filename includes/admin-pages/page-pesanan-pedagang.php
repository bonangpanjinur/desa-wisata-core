<?php
/**
 * File: includes/admin-pages/page-pesanan-pedagang.php
 * * Admin Page: Pesanan Masuk (Pedagang)
 * * Menampilkan daftar pesanan dan detail pesanan untuk pedagang.
 * * Termasuk logika update status, input resi, dan verifikasi pembayaran.
 */

defined( 'ABSPATH' ) || exit;

// --- 1. HANDLERS (LOGIKA BACKEND) ---

/**
 * Form handler untuk update pesanan oleh pedagang
 * Dijalankan pada hook 'admin_init'
 */
function dw_pesanan_pedagang_form_handler() {
    // Pastikan ini adalah request POST dan nonce valid
    if ('POST' !== $_SERVER['REQUEST_METHOD'] || !isset($_POST['dw_update_pesanan_nonce']) || !wp_verify_nonce($_POST['dw_update_pesanan_nonce'], 'dw_update_pesanan_action')) {
        return; 
    }

    if (!current_user_can('dw_manage_pesanan') && !current_user_can('manage_options')) {
        wp_die('Anda tidak punya izin.');
    }
    
    $sub_order_id = isset($_POST['sub_order_id']) ? absint($_POST['sub_order_id']) : 0;
    $new_status   = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';
    $nomor_resi   = isset($_POST['nomor_resi']) ? sanitize_text_field($_POST['nomor_resi']) : '';
    $ongkir_final = isset($_POST['ongkir_final']) && $_POST['ongkir_final'] !== '' ? floatval($_POST['ongkir_final']) : null;
    $action_type  = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : ''; 

    if ($sub_order_id > 0) {
        global $wpdb;
        $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
        $current_user_id = get_current_user_id(); 

        // Verifikasi kepemilikan
        $order_owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id_pedagang FROM $table_sub WHERE id = %d", $sub_order_id
        ));

        // Allow admin to edit too
        if ( current_user_can('manage_options') || ($order_owner_id && $order_owner_id == $current_user_id) ) {
            $notes = ''; 
            
            // Logika Aksi Cepat Lunas
            if ($action_type === 'set_lunas') {
                 $current_status = $wpdb->get_var($wpdb->prepare("SELECT status_pesanan FROM $table_sub WHERE id = %d", $sub_order_id));
                 if ($current_status === 'menunggu_konfirmasi') {
                     $new_status = 'lunas';
                     $notes = 'Pembayaran diverifikasi oleh Pedagang/Admin.';
                 } else {
                      add_settings_error('dw_pesanan_error', 'invalid_lunas_action', 'Hanya pesanan yang menunggu konfirmasi yang bisa ditandai lunas.', 'error');
                      set_transient('settings_errors_dw_pesanan', get_settings_errors(), 30);
                      wp_redirect(wp_get_referer());
                      exit;
                 }
            }
            elseif (empty($new_status)) {
                 $new_status = $wpdb->get_var($wpdb->prepare("SELECT status_pesanan FROM $table_sub WHERE id = %d", $sub_order_id)); 
            }

            // Validasi Resi
            if ($new_status === 'dikirim_ekspedisi') {
                if (empty($nomor_resi)) {
                    add_settings_error('dw_pesanan_error', 'missing_resi', 'Nomor Resi wajib diisi untuk status Dikirim Ekspedisi.', 'error');
                    set_transient('settings_errors_dw_pesanan', get_settings_errors(), 30);
                    wp_redirect(wp_get_referer());
                    exit;
                }
            }

            // Panggil Helper Update Status
            if (function_exists('dw_update_sub_order_status')) { 
                $updated_result = dw_update_sub_order_status($sub_order_id, $new_status, $notes, $nomor_resi, $ongkir_final, $current_user_id);

                if ( is_wp_error($updated_result) ) {
                    add_settings_error('dw_pesanan_error', $updated_result->get_error_code(), $updated_result->get_error_message(), 'error');
                } else if ( $updated_result === true ) {
                    add_settings_error('dw_pesanan_notice', 'status_updated', 'Status pesanan berhasil diperbarui.', 'success');
                } else {
                    add_settings_error('dw_pesanan_error', 'update_failed', 'Gagal memperbarui status pesanan.', 'error');
                }
            } else {
                 add_settings_error('dw_pesanan_error', 'function_missing', 'Fungsi update pesanan tidak ditemukan (dw_update_sub_order_status).', 'error');
            }

            set_transient('settings_errors_dw_pesanan', get_settings_errors(), 30);
            wp_redirect(wp_get_referer());
            exit;
        } else {
             wp_die('Anda tidak memiliki izin untuk mengelola pesanan ini.');
        }
    } else {
        add_settings_error('dw_pesanan_error', 'invalid_order_id', 'ID Pesanan tidak valid.', 'error');
        set_transient('settings_errors_dw_pesanan', get_settings_errors(), 30);
        wp_redirect(wp_get_referer());
        exit;
    }
}
add_action('admin_init', 'dw_pesanan_pedagang_form_handler');

/**
 * Export Excel Handler
 */
add_action('admin_init', function() {
    if (isset($_GET['dw_export_pesanan']) && (current_user_can('dw_manage_pesanan') || current_user_can('manage_options'))) {
        global $wpdb;
        $current_user_id = get_current_user_id();
        
        $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        
        // Jika admin, mungkin ingin export semua (opsional), saat ini default ke user pedagang
        $query = $wpdb->prepare("
            SELECT s.*, p.nama_bank, p.no_rekening, p.nama_rekening 
            FROM $table_sub s
            JOIN $table_pedagang p ON s.id_pedagang = p.id_user
            WHERE s.id_pedagang = %d
        ", $current_user_id);
        
        $results = $wpdb->get_results($query);
        
        if ($results) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=laporan-pesanan-' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID Pesanan', 'Tanggal', 'Total', 'Ongkir', 'Status', 'Bank', 'No Rekening', 'Nama Rekening', 'Nominal Bersih']);
            
            foreach ($results as $row) {
                fputcsv($output, [
                    $row->id,
                    $row->created_at,
                    $row->total_pesanan_toko,
                    $row->ongkir,
                    $row->status_pesanan,
                    $row->nama_bank,
                    $row->no_rekening,
                    $row->nama_rekening,
                    $row->total_pesanan_toko 
                ]);
            }
            fclose($output);
            exit;
        }
    }
});


// --- 2. RENDER FUNCTIONS (TAMPILAN) ---

/**
 * Fungsi Utama: Menentukan apakah merender List atau Detail
 */
function dw_render_pesanan_pedagang_page() {
    // Pastikan user memiliki hak akses
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'pedagang' ) ) {
        wp_die( __( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'desa-wisata-core' ) );
    }

    $action       = isset($_GET['action']) ? $_GET['action'] : 'list';
    $sub_order_id = isset($_GET['sub_order_id']) ? absint($_GET['sub_order_id']) : 0;
    $order_id     = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

    // Jika Action View Detail
    if ('view' === $action && $sub_order_id > 0 && $order_id > 0) {
        dw_pesanan_pedagang_detail_render($order_id, $sub_order_id);
        return;
    }

    // Default: Render List Table
    dw_pesanan_pedagang_list_render();
}

/**
 * Render Tampilan List (Tabel)
 */
function dw_pesanan_pedagang_list_render() {
    require_once DW_PLUGIN_DIR . 'includes/list-tables/class-dw-pesanan-pedagang-list-table.php';

    global $wpdb;
    $current_user_id = get_current_user_id();
    
    // Ambil ID Pedagang (jika diperlukan oleh list table logic lama)
    // Note: DW_Pesanan_Pedagang_List_Table biasanya sudah handle ini secara internal atau via argumen
    // Kita panggil tanpa argumen jika class sudah modern, atau dengan ID jika class lama.
    // Asumsi: Class sudah di-update di Fase 1/2.
    $list_table = new DW_Pesanan_Pedagang_List_Table(); 
    $list_table->prepare_items();

    ?>
    <div class="wrap dw-admin-wrapper">
        <!-- Header Section -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1>Manajemen Pesanan Saya</h1>
                <p class="dw-subtitle">Kelola pesanan yang masuk dari pembeli untuk produk Anda.</p>
            </div>
            <div class="dw-header-actions">
                <a href="<?php echo add_query_arg('dw_export_pesanan', '1'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-media-spreadsheet" style="margin-top:3px;"></span> Export Excel (CSV)
                </a>
            </div>
        </div>

        <!-- Notifikasi Transient -->
        <?php
        $errors = get_transient('settings_errors_dw_pesanan');
        if($errors) {
            echo '<div class="dw-content-body" style="padding-bottom:0;">';
            settings_errors('dw_pesanan_error');
            settings_errors('dw_pesanan_notice');
            echo '</div>';
            delete_transient('settings_errors_dw_pesanan');
        }
        ?>

        <!-- Body Section -->
        <div class="dw-content-body">
            <div class="dw-card">
                <form id="dw-pesanan-pedagang-form" method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                    
                    <?php 
                    $list_table->views(); 
                    $list_table->search_box( __( 'Cari Pesanan', 'desa-wisata-core' ), 'search_id' ); 
                    $list_table->display(); 
                    ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Tampilan Detail Pesanan (Detail View)
 * * UPDATE: Menggunakan Layout dw-grid-2-col dan dw-card
 */
function dw_pesanan_pedagang_detail_render($order_id, $sub_order_id) {
    global $wpdb;
    $current_user_id = get_current_user_id();

    // 1. Ambil data
    $order     = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));
    $sub_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi_sub WHERE id = %d", $sub_order_id));
    $items     = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi_items WHERE id_sub_transaksi = %d", $sub_order_id));
    $pembeli   = get_userdata($order->id_pembeli);

    // Keamanan
    if (!$order || !$sub_order) {
         echo '<div class="wrap dw-admin-wrapper"><div class="notice notice-error"><p>Pesanan tidak ditemukan.</p></div></div>';
         return;
    }
    // Cek kepemilikan (skip jika admin)
    if (!current_user_can('manage_options') && $sub_order->id_pedagang != $current_user_id) {
         echo '<div class="wrap dw-admin-wrapper"><div class="notice notice-error"><p>Anda tidak memiliki akses ke pesanan ini.</p></div></div>';
         return;
    }
    
    $current_status = $sub_order->status_pesanan;
    $status_pembayaran = $order->status_transaksi;

    ?>
    <div class="wrap dw-admin-wrapper">
        <!-- Header Detail -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1>Detail Pesanan #<?php echo esc_html($order->kode_unik); ?></h1>
                <p class="dw-subtitle">Tanggal Pesanan: <?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $order->created_at ) ); ?></p>
            </div>
            <div class="dw-header-actions">
                <a href="<?php echo admin_url('admin.php?page=dw-pesanan-pedagang'); ?>" class="button">
                    &larr; Kembali ke Daftar
                </a>
            </div>
        </div>
        
        <!-- Notifikasi -->
        <?php
        $errors = get_transient('settings_errors_dw_pesanan');
        if($errors) {
            echo '<div class="dw-content-body" style="padding-bottom:0;">';
            settings_errors('dw_pesanan_error');
            settings_errors('dw_pesanan_notice');
            echo '</div>';
            delete_transient('settings_errors_dw_pesanan');
        }
        ?>

        <!-- Grid Layout Content -->
        <div class="dw-content-body">
            <div class="dw-grid-2-col">
                
                <!-- KOLOM UTAMA (KIRI) -->
                <div class="main-column">
                    
                    <!-- 1. Card Update Status -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3>Update Status Pesanan</h3>
                        </div>
                        <div class="dw-card-body">
                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                <input type="hidden" name="action" value="dw_update_pesanan_action">
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                <input type="hidden" name="sub_order_id" value="<?php echo esc_attr($sub_order_id); ?>">
                                <?php wp_nonce_field('dw_update_pesanan_action', 'dw_update_pesanan_nonce'); ?>

                                <div style="display:flex; gap:20px; margin-bottom:20px;">
                                    <div>
                                        <strong>Status Pembayaran:</strong><br>
                                        <?php echo dw_get_order_status_badge($status_pembayaran); ?>
                                    </div>
                                    <div>
                                        <strong>Status Pesanan Toko:</strong><br>
                                        <?php echo dw_get_order_status_badge($current_status); ?>
                                    </div>
                                </div>
                                
                                <?php if ($status_pembayaran === 'pembayaran_dikonfirmasi' && $current_status === 'menunggu_konfirmasi'): ?>
                                    <div class="notice notice-success inline" style="margin: 0 0 15px 0;">
                                        <p><strong>Aksi Diperlukan:</strong> Pembeli telah mengunggah bukti bayar. Mohon cek bukti di kolom kanan, lalu klik tombol verifikasi.</p>
                                    </div>
                                    <button type="submit" name="action_type" value="set_lunas" class="button button-primary button-large" onclick="return confirm('Anda yakin pembayaran ini Lunas dan Valid?');">Verifikasi & Tandai Lunas</button>
                                    <hr style="margin: 20px 0;">
                                <?php endif; ?>

                                <h4>Ubah Status Manual:</h4>
                                <select name="new_status" id="new_status" style="width: 100%; max-width: 300px; margin-bottom:10px;">
                                    <option value="" <?php selected($current_status, ''); ?>>-- Pilih Status Baru --</option>
                                    <option value="diproses" <?php selected($current_status, 'diproses'); ?>>Diproses</option>
                                    <option value="diantar_ojek" <?php selected($current_status, 'diantar_ojek'); ?>>Dikirim (Ojek Lokal)</option>
                                    <option value="dikirim_ekspedisi" <?php selected($current_status, 'dikirim_ekspedisi'); ?>>Dikirim (Ekspedisi)</option>
                                    <option value="selesai" <?php selected($current_status, 'selesai'); ?>>Selesai</option>
                                    <option value="dibatalkan" <?php selected($current_status, 'dibatalkan'); ?>>Dibatalkan</option>
                                </select>
                                
                                <!-- Field Resi -->
                                <div id="form_field_resi" style="margin-bottom: 15px; display:none; background: #f0f0f1; padding: 10px; border-radius: 4px;">
                                    <label for="nomor_resi"><strong>Nomor Resi:</strong> <span style="color:red;">*</span></label><br>
                                    <input type="text" name="nomor_resi" id="nomor_resi" value="<?php echo esc_attr($sub_order->no_resi); ?>" class="regular-text" style="width:100%;">
                                </div>
                                
                                <!-- Field Ongkir Final -->
                                <div id="form_field_ongkir_final" style="margin-bottom: 15px; display:none; background: #f0f0f1; padding: 10px; border-radius: 4px;">
                                    <label for="ongkir_final"><strong>Biaya Ongkir Final (Rp):</strong></label><br>
                                    <input type="number" name="ongkir_final" id="ongkir_final" value="<?php echo esc_attr($sub_order->ongkir); ?>" class="regular-text" min="0" style="width:100%;">
                                    <p class="description">Isi jika biaya ongkir realisasi berbeda dari estimasi awal.</p>
                                </div>

                                <button type="submit" name="action_type" value="update_manual" class="button button-secondary">Update Status</button>

                                <script>
                                jQuery(document).ready(function($) {
                                    $('#new_status').on('change', function() {
                                        if ($(this).val() === 'dikirim_ekspedisi') {
                                            $('#form_field_resi, #form_field_ongkir_final').slideDown();
                                        } else {
                                            $('#form_field_resi, #form_field_ongkir_final').slideUp();
                                        }
                                    }).trigger('change');
                                });
                                </script>
                            </form>
                        </div>
                    </div>

                    <!-- 2. Card Item Pesanan -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3>Item Pesanan</h3>
                        </div>
                        <div class="dw-card-body" style="padding:0;">
                            <table class="wp-list-table widefat fixed striped" style="border:none; box-shadow:none;">
                                <thead>
                                    <tr><th>Produk</th><th>Kuantitas</th><th style="text-align: right;">Total Harga</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($item->nama_produk); ?></strong>
                                            <?php if (!empty($item->nama_variasi)): ?>
                                                <br><small style="color:#646970;">(<?php echo esc_html($item->nama_variasi); ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo (int) $item->jumlah; ?> x Rp <?php echo number_format($item->harga_satuan, 0, ',', '.'); ?></td>
                                        <td style="text-align: right;">Rp <?php echo number_format($item->total_harga, 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr><th colspan="2" style="text-align: right;">Subtotal Produk:</th><td style="text-align: right;">Rp <?php echo number_format($sub_order->sub_total, 0, ',', '.'); ?></td></tr>
                                    <tr><th colspan="2" style="text-align: right;">Ongkos Kirim (<?php echo esc_html($sub_order->metode_pengiriman); ?>):</th><td style="text-align: right;">Rp <?php echo number_format($sub_order->ongkir, 0, ',', '.'); ?></td></tr>
                                    <tr style="background:#f6f7f7;"><th colspan="2" style="text-align: right; font-weight:bold;">Total Pesanan Toko:</th><td style="text-align: right; font-weight:bold;">Rp <?php echo number_format($sub_order->total_pesanan_toko, 0, ',', '.'); ?></td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>
                
                <!-- KOLOM SAMPING (KANAN) -->
                <div class="side-column">
                    
                    <!-- 1. Detail Pengiriman -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3>Detail Pengiriman</h3>
                        </div>
                        <div class="dw-card-body">
                            <p style="margin-top:0;"><strong><?php echo esc_html($order->nama_penerima); ?></strong></p>
                            <p><?php echo esc_html($order->no_hp); ?></p>
                            <hr style="border-top:1px dashed #ddd; border-bottom:none;">
                            <p><?php echo esc_html($order->alamat_lengkap); ?><br>
                            <?php echo esc_html($order->kelurahan); ?>, <?php echo esc_html($order->kecamatan); ?><br>
                            <?php echo esc_html($order->kabupaten); ?>, <?php echo esc_html($order->provinsi); ?> <?php echo esc_html($order->kode_pos); ?>
                            </p>
                        </div>
                    </div>

                    <!-- 2. Detail Pembeli -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3>Info Pembeli</h3>
                        </div>
                        <div class="dw-card-body">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                                <?php echo get_avatar($pembeli->ID, 40); ?>
                                <div>
                                    <strong><?php echo esc_html($pembeli->display_name); ?></strong><br>
                                    <a href="mailto:<?php echo esc_attr($pembeli->user_email); ?>"><?php echo esc_html($pembeli->user_email); ?></a>
                                </div>
                            </div>
                            <?php if ($order->catatan_pembeli): ?>
                                <div style="background:#fff9c4; padding:10px; border-radius:4px; font-size:12px;">
                                    <strong>Catatan:</strong><br>
                                    <em><?php echo esc_html($order->catatan_pembeli); ?></em>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 3. Bukti Pembayaran -->
                    <div class="dw-card">
                        <div class="dw-card-header">
                            <h3>Bukti Pembayaran</h3>
                        </div>
                        <div class="dw-card-body">
                            <?php if ($order->bukti_pembayaran): ?>
                                <a href="<?php echo esc_url($order->bukti_pembayaran); ?>" target="_blank">
                                    <img src="<?php echo esc_url($order->bukti_pembayaran); ?>" style="width: 100%; height: auto; border:1px solid #ddd; border-radius:4px;" alt="Bukti Pembayaran">
                                </a>
                                <p style="text-align:center; margin-bottom:0;"><a href="<?php echo esc_url($order->bukti_pembayaran); ?>" target="_blank">Lihat Ukuran Asli</a></p>
                            <?php else: ?>
                                <div style="text-align:center; padding:20px; color:#646970; background:#f0f0f1; border-radius:4px;">
                                    <span class="dashicons dashicons-format-image" style="font-size:32px; height:32px; width:32px; margin-bottom:10px;"></span><br>
                                    Belum ada bukti pembayaran.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div> <!-- End Grid -->
        </div>
    </div>
    <?php
}