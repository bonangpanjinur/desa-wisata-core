<?php
/**
 * File Name:   page-pesanan-pedagang.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-pesanan-pedagang.php
 *
 * --- PERUBAHAN V3.2.0 (REKOMENDASI PERBAIKAN) ---
 * - (Perbaikan #1) Sentralisasi Logika:
 * - Menghapus blok pengecekan kuota (`if ($is_quota_transition) { ... }`)
 * dari `dw_pesanan_pedagang_form_handler()`.
 * - Logika ini sekarang sepenuhnya ditangani oleh helper `dw_update_order_status`.
 * - Memperbarui penanganan hasil dari `dw_update_order_status()` untuk
 * memeriksa `is_wp_error()` (Perbaikan #3).
 *
 * --- PERBAIKAN V3.2.3 (LOGIKA SKEMA) ---
 * - Mengubah handler form untuk mengirim `sub_order_id` (bukan `order_id`)
 * ke helper `dw_update_sub_order_status` yang baru.
 * - Mengubah logika `set_lunas` agar menargetkan `dw_transaksi_sub`.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Form handler untuk update pesanan oleh pedagang
function dw_pesanan_pedagang_form_handler() {
    // Pastikan ini adalah request POST dan nonce valid
    if ('POST' !== $_SERVER['REQUEST_METHOD'] || !isset($_POST['dw_update_pesanan_nonce']) || !wp_verify_nonce($_POST['dw_update_pesanan_nonce'], 'dw_update_pesanan_action')) {
        return; // Keluar jika bukan POST atau nonce tidak valid
    }

    if (!current_user_can('dw_manage_pesanan')) {
        wp_die('Anda tidak punya izin.');
    }
    
    // --- PERBAIKAN: Gunakan sub_order_id ---
    $sub_order_id = isset($_POST['sub_order_id']) ? absint($_POST['sub_order_id']) : 0;
    $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';
    $nomor_resi = isset($_POST['nomor_resi']) ? sanitize_text_field($_POST['nomor_resi']) : '';
    // BARU: Ambil Ongkir Final
    $ongkir_final = isset($_POST['ongkir_final']) && $_POST['ongkir_final'] !== '' ? floatval($_POST['ongkir_final']) : null;
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : ''; // Cek aksi cepat

    if ($sub_order_id > 0) {
        global $wpdb;
        $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
        $current_user_id = get_current_user_id(); // Dapatkan user_id untuk cek kuota

        // Verifikasi bahwa pedagang ini adalah pemilik pesanan
        $order_owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id_pedagang FROM $table_sub WHERE id = %d", $sub_order_id
        ));

        if ($order_owner_id && $order_owner_id == $current_user_id) {
            $notes = ''; // Untuk catatan tambahan jika perlu
            
            // Logika Aksi Cepat Lunas
            if ($action_type === 'set_lunas') {
                 // Pastikan status sebelumnya adalah 'menunggu_konfirmasi'
                 $current_status = $wpdb->get_var($wpdb->prepare("SELECT status_pesanan FROM $table_sub WHERE id = %d", $sub_order_id));
                 if ($current_status === 'menunggu_konfirmasi') {
                     $new_status = 'lunas';
                     $notes = 'Pembayaran diverifikasi oleh Pedagang.';
                 } else {
                     // Jika status bukan menunggu_konfirmasi, jangan proses aksi cepat lunas
                      add_settings_error('dw_pesanan_error', 'invalid_lunas_action', 'Hanya pesanan yang menunggu konfirmasi yang bisa ditandai lunas.', 'error');
                      set_transient('settings_errors_dw_pesanan', get_settings_errors(), 30);
                      wp_redirect(wp_get_referer());
                      exit;
                 }
            }
             // Jika BUKAN aksi cepat lunas, dan status baru tidak ada, berarti hanya update resi/ongkir
             elseif (empty($new_status)) {
                 $new_status = $wpdb->get_var($wpdb->prepare("SELECT status_pesanan FROM $table_sub WHERE id = %d", $sub_order_id)); // Gunakan status saat ini
             }


            // --- [PERBAIKAN #1: Hapus Duplikasi Logika Kuota] ---
            // (Logika ini sekarang ada di helper)

            // Validasi untuk status dikirim_ekspedisi
            if ($new_status === 'dikirim_ekspedisi') {
                if (empty($nomor_resi)) {
                    add_settings_error('dw_pesanan_error', 'missing_resi', 'Nomor Resi wajib diisi untuk status Dikirim Ekspedisi.', 'error');
                    set_transient('settings_errors_dw_pesanan', get_settings_errors(), 30);
                    wp_redirect(wp_get_referer());
                    exit;
                }
                // if ($ongkir_final === null || $ongkir_final < 0) {
                //     add_settings_error('dw_pesanan_error', 'missing_ongkir_final', 'Biaya Ongkir Final wajib diisi (minimal 0) untuk status Dikirim Ekspedisi.', 'error');
                //     set_transient('settings_errors_dw_pesanan', get_settings_errors(), 30);
                //     wp_redirect(wp_get_referer());
                //     exit;
                // }
            }

            // Panggil fungsi update status terpusat
            if (function_exists('dw_update_sub_order_status')) { // --- PERBAIKAN: Panggil helper baru ---
                
                // --- PERBAIKAN #3: Tangani WP_Error ---
                $updated_result = dw_update_sub_order_status($sub_order_id, $new_status, $notes, $nomor_resi, $ongkir_final, $current_user_id);

                if ( is_wp_error($updated_result) ) {
                    // Gagal, $updated_result adalah WP_Error
                    add_settings_error('dw_pesanan_error', $updated_result->get_error_code(), $updated_result->get_error_message(), 'error');
                
                } else if ( $updated_result === true ) {
                    // Sukses
                    add_settings_error('dw_pesanan_notice', 'status_updated', 'Status pesanan berhasil diperbarui.', 'success');
                
                } else {
                    // Seharusnya tidak terjadi, tapi sebagai fallback
                    add_settings_error('dw_pesanan_error', 'update_failed', 'Gagal memperbarui status pesanan.', 'error');
                }
                // --- AKHIR PERBAIKAN #3 ---

            } else {
                 add_settings_error('dw_pesanan_error', 'function_missing', 'Fungsi update pesanan tidak ditemukan (dw_update_sub_order_status).', 'error');
                 error_log('Error: Fungsi dw_update_sub_order_status() tidak ditemukan.');
            }

            // Simpan notifikasi untuk ditampilkan
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
// Hook 'admin_init' memastikan ini berjalan sebelum header dikirim
add_action('admin_init', 'dw_pesanan_pedagang_form_handler');

// KELAS DW_Pesanan_Pedagang_List_Table DIHAPUS DARI SINI
// karena sudah dideklarasikan di: includes/list-tables/class-dw-pesanan-pedagang-list-table.php

// Fungsi untuk merender halaman "Pesanan Saya" untuk pedagang
function dw_pesanan_pedagang_page_render() {
    
    // --- PERBAIKAN: Pindahkan Pengecekan Aksi 'view' ke atas ---
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $sub_order_id = isset($_GET['sub_order_id']) ? absint($_GET['sub_order_id']) : 0;
    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

    if ('view' === $action && $sub_order_id > 0 && $order_id > 0) {
        dw_pesanan_pedagang_detail_render($order_id, $sub_order_id);
        return;
    }
    // --- AKHIR PERBAIKAN ---


    // Pastikan kelas List Table sudah dimuat (seharusnya sudah di admin-menus.php)
    if ( ! class_exists('DW_Pesanan_Pedagang_List_Table') ) {
        if ( file_exists( DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pesanan-pedagang-list-table.php' ) ) {
             require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-pesanan-pedagang-list-table.php';
        } else {
             echo '<div class="notice notice-error"><p>Error: File class-dw-pesanan-pedagang-list-table.php tidak ditemukan.</p></div>';
             return;
        }
    }

    // --- PERBAIKAN: Ambil ID Pedagang dari User ID ---
    global $wpdb;
    $current_user_id = get_current_user_id();
    $pedagang_id_user = $wpdb->get_var($wpdb->prepare("SELECT id_user FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $current_user_id));
    if (!$pedagang_id_user) {
         echo '<div class="wrap"><div class="notice notice-error"><p>Profil pedagang Anda tidak ditemukan.</p></div></div>';
         return;
    }
    // --- AKHIR PERBAIKAN ---


    $pesananListTable = new DW_Pesanan_Pedagang_List_Table($pedagang_id_user); // Kirim ID User Pedagang
    $pesananListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header"><h1>Manajemen Pesanan Saya</h1></div>

        <?php
        // BARU: Menampilkan notifikasi error/sukses dari transient
        $errors = get_transient('settings_errors_dw_pesanan');
        if($errors) {
            // Tampilkan error dulu
            settings_errors('dw_pesanan_error');
            // Tampilkan notice/info/sukses setelahnya
            settings_errors('dw_pesanan_notice');
            delete_transient('settings_errors_dw_pesanan');
        }
        ?>

        <p>Kelola semua pesanan yang masuk ke toko Anda. Gunakan tombol "Verifikasi & Lunas" setelah Pembeli mengunggah bukti pembayaran.</p>

        <?php // Form untuk bulk actions (jika ada) dan search ?>
        <form method="get">
            <?php // Input hidden untuk parameter GET yang perlu dipertahankan (page) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
            // $pesananListTable->search_box('Cari Pesanan', 'search_id'); // Jika perlu search box
            $pesananListTable->display(); // Menampilkan tabel pesanan
            ?>
        </form>
    </div>
    <?php 
}

/**
 * [BARU] Merender halaman detail pesanan untuk Pedagang.
 */
function dw_pesanan_pedagang_detail_render($order_id, $sub_order_id) {
    global $wpdb;
    $current_user_id = get_current_user_id();

    // 1. Ambil data order utama
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));
    // 2. Ambil data sub-order
    $sub_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi_sub WHERE id = %d", $sub_order_id));
    // 3. Ambil data item
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi_items WHERE id_sub_transaksi = %d", $sub_order_id));
    // 4. Ambil data pembeli
    $pembeli = get_userdata($order->id_pembeli);

    // Keamanan: Pastikan sub-order ini milik pedagang yang sedang login
    if (!$order || !$sub_order || $sub_order->id_pedagang != $current_user_id) {
         echo '<div class="wrap"><div class="notice notice-error"><p>Pesanan tidak ditemukan atau Anda tidak memiliki akses.</p></div></div>';
         return;
    }
    
    $current_status = $sub_order->status_pesanan;
    $status_pembayaran = $order->status_transaksi;

    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Detail Pesanan #<?php echo esc_html($order->kode_unik); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=dw-pesanan-pedagang'); ?>" class="button button-secondary">← Kembali ke Daftar Pesanan</a>
        </div>
        
        <?php
        // Menampilkan notifikasi error/sukses
        $errors = get_transient('settings_errors_dw_pesanan');
        if($errors) {
            settings_errors('dw_pesanan_error');
            settings_errors('dw_pesanan_notice');
            delete_transient('settings_errors_dw_pesanan');
        }
        ?>

        <div id="poststuff" style="margin-top: 20px;">
            <div id="post-body" class="metabox-holder columns-2">

                <!-- Kolom Utama (Form Aksi) -->
                <div id="post-body-content">
                    <div class="postbox">
                        <h2 class="hndle"><span>Update Status Pesanan</span></h2>
                        <div class="inside">
                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                <input type="hidden" name="action" value="dw_update_pesanan_action"> <!-- Ini harus menargetkan admin-post -->
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                <input type="hidden" name="sub_order_id" value="<?php echo esc_attr($sub_order_id); ?>">
                                <?php wp_nonce_field('dw_update_pesanan_action', 'dw_update_pesanan_nonce'); ?>

                                <p>Status Pembayaran Saat Ini: <strong><?php echo dw_get_order_status_badge($status_pembayaran); ?></strong></p>
                                <p>Status Pesanan Toko Saat Ini: <strong><?php echo dw_get_order_status_badge($current_status); ?></strong></p>
                                
                                <?php if ($status_pembayaran === 'pembayaran_dikonfirmasi' && $current_status === 'menunggu_konfirmasi'): ?>
                                    <p class="description" style="color:green; font-weight:bold;">Pembeli telah mengunggah bukti bayar. Silakan verifikasi.</p>
                                    <button type="submit" name="action_type" value="set_lunas" class="button button-primary button-large" onclick="return confirm('Anda yakin pembayaran ini Lunas?');">Verifikasi & Tandai Lunas</button>
                                <?php endif; ?>

                                <hr>
                                <h4>Ubah Status Manual:</h4>
                                <select name="new_status" id="new_status" style="min-width: 200px;">
                                    <option value="" <?php selected($current_status, ''); ?>>-- Pilih Status Baru --</option>
                                    <option value="diproses" <?php selected($current_status, 'diproses'); ?>>Diproses</option>
                                    <option value="diantar_ojek" <?php selected($current_status, 'diantar_ojek'); ?>>Dikirim (Ojek Lokal)</option>
                                    <option value="dikirim_ekspedisi" <?php selected($current_status, 'dikirim_ekspedisi'); ?>>Dikirim (Ekspedisi)</option>
                                    <option value="selesai" <?php selected($current_status, 'selesai'); ?>>Selesai</option>
                                    <option value="dibatalkan" <?php selected($current_status, 'dibatalkan'); ?>>Dibatalkan</option>
                                </select>
                                
                                <div id="form_field_resi" style="margin-top: 15px; <?php echo ($current_status !== 'dikirim_ekspedisi') ? 'display:none;' : ''; ?>">
                                    <label for="nomor_resi">Nomor Resi:</label><br>
                                    <input type="text" name="nomor_resi" id="nomor_resi" value="<?php echo esc_attr($sub_order->no_resi); ?>" class="regular-text">
                                </div>
                                
                                <div id="form_field_ongkir_final" style="margin-top: 15px; <?php echo ($current_status !== 'dikirim_ekspedisi') ? 'display:none;' : ''; ?>">
                                    <label for="ongkir_final">Biaya Ongkir Final (Rp):</label><br>
                                    <input type="number" name="ongkir_final" id="ongkir_final" value="<?php echo esc_attr($sub_order->ongkir); ?>" class="regular-text" min="0">
                                    <p class="description">Biaya ongkir final jika berbeda dari estimasi.</p>
                                </div>

                                <button type="submit" name="action_type" value="update_manual" class="button button-secondary" style="margin-top: 15px;">Update Status Manual</button>

                                <script>
                                // Tampilkan/sembunyikan field resi
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
                    
                    <div class="postbox">
                        <h2 class="hndle"><span>Item Pesanan</span></h2>
                        <div class="inside">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr><th>Produk</th><th>Kuantitas</th><th style="text-align: right;">Total Harga</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($item->nama_produk); ?></strong>
                                            <?php if (!empty($item->nama_variasi)): ?>
                                                <br><small>(<?php echo esc_html($item->nama_variasi); ?>)</small>
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
                                    <tr style="font-weight: bold;"><th colspan="2" style="text-align: right;">Total Pesanan Toko:</th><td style="text-align: right;">Rp <?php echo number_format($sub_order->total_pesanan_toko, 0, ',', '.'); ?></td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Kolom Samping -->
                <div id="postbox-container-1" class="postbox-container">
                    <div class="postbox">
                        <h2 class="hndle"><span>Detail Pengiriman</span></h2>
                        <div class="inside">
                            <strong><?php echo esc_html($order->nama_penerima); ?></strong>
                            <p><?php echo esc_html($order->no_hp); ?><br>
                            <?php echo esc_html($order->alamat_lengkap); ?><br>
                            <?php echo esc_html($order->kelurahan); ?>, <?php echo esc_html($order->kecamatan); ?><br>
                            <?php echo esc_html($order->kabupaten); ?>, <?php echo esc_html($order->provinsi); ?> <?php echo esc_html($order->kode_pos); ?>
                            </p>
                        </div>
                    </div>
                    <div class="postbox">
                        <h2 class="hndle"><span>Detail Pembeli</span></h2>
                        <div class="inside">
                            <strong><?php echo esc_html($pembeli->display_name); ?></strong>
                            <p><?php echo esc_html($pembeli->user_email); ?></p>
                            <?php if ($order->catatan_pembeli): ?>
                                <strong>Catatan:</strong>
                                <p><em><?php echo esc_html($order->catatan_pembeli); ?></em></p>
                            <?php endif; ?>
                        </div>
                    </div>
                     <div class="postbox">
                        <h2 class="hndle"><span>Bukti Pembayaran</span></h2>
                        <div class="inside">
                            <?php if ($order->bukti_pembayaran): ?>
                                <a href="<?php echo esc_url($order->bukti_pembayaran); ?>" target="_blank">
                                    <img src="<?php echo esc_url($order->bukti_pembayaran); ?>" style="width: 100%; height: auto;" alt="Bukti Pembayaran">
                                </a>
                            <?php else: ?>
                                <p>Pembeli belum mengunggah bukti pembayaran.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    <?php
}
?>