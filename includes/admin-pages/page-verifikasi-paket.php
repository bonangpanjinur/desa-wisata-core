<?php
// Pastikan akses langsung dicegah
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render Halaman Verifikasi Paket
 * Fungsi ini harus dipanggil dari callback menu di admin-menus.php
 */
function dw_render_page_verifikasi_paket() {
    global $wpdb;

    // Definisi nama tabel sesuai activation.php
    $table_pembelian = $wpdb->prefix . 'dw_pembelian_paket'; // Tabel transaksi pembelian paket
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';         // Tabel data pedagang
    $table_users = $wpdb->prefix . 'users';                  // Tabel user WP

    // --- LOGIC: HANDLE FORM SUBMISSION ---
    $message = '';
    $message_type = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
        
        // Cek nonce keamanan
        if (!isset($_POST['dw_verif_nonce']) || !wp_verify_nonce($_POST['dw_verif_nonce'], 'dw_verifikasi_paket_action')) {
            $message = 'Security check failed.';
            $message_type = 'error';
        } else {
            $pembelian_id = intval($_POST['pembelian_id']);
            
            // Ambil data pembelian
            $pembelian = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_pembelian WHERE id = %d", 
                $pembelian_id
            ));

            if ($pembelian && $pembelian->status === 'pending') {
                
                if ($_POST['action_type'] === 'approve') {
                    // 1. Update status pembelian di dw_pembelian_paket
                    $update_pembelian = $wpdb->update(
                        $table_pembelian,
                        array(
                            'status' => 'disetujui',
                            'processed_at' => current_time('mysql'),
                            'catatan_admin' => 'Diverifikasi manual oleh admin'
                        ),
                        array('id' => $pembelian_id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );

                    // 2. Tambahkan kuota ke tabel dw_pedagang & Aktifkan Akun
                    if ($update_pembelian !== false) {
                        // Ambil data pedagang saat ini untuk menambah sisa transaksi
                        $pedagang = $wpdb->get_row($wpdb->prepare("SELECT sisa_transaksi FROM $table_pedagang WHERE id = %d", $pembelian->id_pedagang));
                        
                        $sisa_transaksi_lama = $pedagang ? intval($pedagang->sisa_transaksi) : 0;
                        $tambahan_kuota = intval($pembelian->jumlah_transaksi);
                        $sisa_transaksi_baru = $sisa_transaksi_lama + $tambahan_kuota;

                        $wpdb->update(
                            $table_pedagang,
                            array(
                                'status_akun' => 'aktif', // Aktifkan akun
                                'sisa_transaksi' => $sisa_transaksi_baru, // Update kuota
                                'updated_at' => current_time('mysql')
                            ),
                            array('id' => $pembelian->id_pedagang),
                            array('%s', '%d', '%s'),
                            array('%d')
                        );

                        $message = "Paket disetujui! Kuota pedagang bertambah " . $tambahan_kuota . " transaksi.";
                        $message_type = 'success';
                    } else {
                        $message = "Gagal mengupdate database pembelian.";
                        $message_type = 'error';
                    }

                } elseif ($_POST['action_type'] === 'reject') {
                    // Ambil alasan tolak
                    $alasan_tolak = isset($_POST['alasan_tolak']) ? sanitize_textarea_field($_POST['alasan_tolak']) : 'Ditolak oleh admin';

                    // Update status menjadi ditolak
                    $wpdb->update(
                        $table_pembelian,
                        array(
                            'status' => 'ditolak',
                            'processed_at' => current_time('mysql'),
                            'catatan_admin' => $alasan_tolak // Simpan alasan
                        ),
                        array('id' => $pembelian_id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                    $message = "Permintaan paket ditolak.";
                    $message_type = 'warning';
                }
            } else {
                $message = "Data tidak ditemukan atau sudah diproses sebelumnya.";
                $message_type = 'error';
            }
        }
    }

    // --- QUERY DATA ---
    // Mengambil data dari dw_pembelian_paket (sesuai activation.php)
    // JOIN ke dw_pedagang untuk dapat id_user
    // JOIN ke users untuk dapat nama asli
    $items = $wpdb->get_results("
        SELECT 
            pp.id AS pembelian_id,
            pp.nama_paket_snapshot,
            pp.harga_paket,
            pp.jumlah_transaksi,
            pp.url_bukti_bayar,
            pp.created_at,
            pp.status,
            ped.nama_toko,
            u.display_name AS nama_pemilik,
            u.user_email
        FROM $table_pembelian pp
        LEFT JOIN $table_pedagang ped ON pp.id_pedagang = ped.id
        LEFT JOIN $table_users u ON ped.id_user = u.ID
        WHERE pp.status = 'pending'
        ORDER BY pp.created_at ASC
    ");

    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Verifikasi Pembelian Paket</h1>
        <p class="description">Verifikasi bukti transfer pembelian kuota transaksi pedagang.</p>
        <hr class="wp-header-end">

        <?php if (!empty($message)) : ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($items)) : ?>
            <div class="notice notice-info inline" style="margin-top: 20px;">
                <p>Tidak ada permintaan pembelian paket yang menunggu verifikasi saat ini.</p>
            </div>
        <?php else : ?>
            <div class="card" style="margin-top: 20px; padding: 0; max-width: 100%;">
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Pedagang / Toko</th>
                            <th style="width: 20%;">Detail Paket</th>
                            <th style="width: 15%;">Tagihan</th>
                            <th style="width: 15%;">Waktu Request</th>
                            <th style="width: 10%;">Bukti</th>
                            <th style="width: 20%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo $item->nama_toko ? esc_html($item->nama_toko) : '<em>(Toko dihapus)</em>'; ?></strong><br>
                                    <span class="description">Pemilik: <?php echo esc_html($item->nama_pemilik); ?></span><br>
                                    <small><?php echo esc_html($item->user_email); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($item->nama_paket_snapshot); ?></strong><br>
                                    <span class="dashicons dashicons-cart" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> 
                                    <?php echo number_format($item->jumlah_transaksi); ?> Transaksi
                                </td>
                                <td>
                                    <strong style="color: #2271b1;">Rp <?php echo number_format($item->harga_paket, 0, ',', '.'); ?></strong>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($item->created_at)); ?><br>
                                    <span class="description"><?php echo date('H:i', strtotime($item->created_at)); ?> WIB</span>
                                </td>
                                <td>
                                    <?php if (!empty($item->url_bukti_bayar)) : ?>
                                        <a href="<?php echo esc_url($item->url_bukti_bayar); ?>" target="_blank" class="button button-small">
                                            <span class="dashicons dashicons-visibility" style="margin-top:2px;"></span> Lihat
                                        </a>
                                    <?php else : ?>
                                        <span class="description">- Kosong -</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <!-- Form Approve (Langsung Submit) -->
                                        <form method="post" action="" style="display:inline;">
                                            <?php wp_nonce_field('dw_verifikasi_paket_action', 'dw_verif_nonce'); ?>
                                            <input type="hidden" name="pembelian_id" value="<?php echo esc_attr($item->pembelian_id); ?>">
                                            <input type="hidden" name="action_type" value="approve">
                                            <button type="submit" class="button button-primary" onclick="return confirm('Yakin ingin menyetujui paket ini? Kuota transaksi akan ditambahkan ke pedagang.');">
                                                <span class="dashicons dashicons-yes" style="margin-top:4px;"></span> Terima
                                            </button>
                                        </form>

                                        <!-- Tombol Reject (Buka Modal) -->
                                        <button type="button" class="button button-secondary open-reject-modal" 
                                                data-id="<?php echo esc_attr($item->pembelian_id); ?>" 
                                                data-toko="<?php echo esc_attr($item->nama_toko); ?>"
                                                style="color: #d63638; border-color: #d63638;">
                                            <span class="dashicons dashicons-no" style="margin-top:4px;"></span> Tolak
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal Penolakan -->
            <div id="dw-reject-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
                <div style="background:#fff; width:400px; max-width:90%; padding:20px; border-radius:5px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                    <h3 style="margin-top:0;">Tolak Paket</h3>
                    <p>Anda akan menolak permintaan paket dari <strong id="modal-toko-name"></strong>.</p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('dw_verifikasi_paket_action', 'dw_verif_nonce'); ?>
                        <input type="hidden" name="pembelian_id" id="modal-pembelian-id" value="">
                        <input type="hidden" name="action_type" value="reject">
                        
                        <label for="alasan_tolak" style="font-weight:600; display:block; margin-bottom:5px;">Alasan Penolakan:</label>
                        <textarea name="alasan_tolak" id="alasan_tolak" rows="4" style="width:100%; margin-bottom:15px;" placeholder="Contoh: Bukti transfer buram, Nominal tidak sesuai..." required></textarea>
                        
                        <div style="text-align:right;">
                            <button type="button" class="button" id="close-reject-modal">Batal</button>
                            <button type="submit" class="button button-primary" style="background-color: #d63638; border-color: #d63638;">Tolak Permintaan</button>
                        </div>
                    </form>
                </div>
            </div>

            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    var modal = document.getElementById('dw-reject-modal');
                    var modalTokoName = document.getElementById('modal-toko-name');
                    var modalInputId = document.getElementById('modal-pembelian-id');
                    var btns = document.querySelectorAll('.open-reject-modal');
                    var closeBtn = document.getElementById('close-reject-modal');

                    // Buka modal saat tombol tolak diklik
                    btns.forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var id = this.getAttribute('data-id');
                            var toko = this.getAttribute('data-toko');
                            
                            modalInputId.value = id;
                            modalTokoName.textContent = toko;
                            modal.style.display = 'flex';
                        });
                    });

                    // Tutup modal
                    closeBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });

                    // Tutup modal jika klik di luar area konten
                    window.addEventListener('click', function(event) {
                        if (event.target == modal) {
                            modal.style.display = 'none';
                        }
                    });
                });
            </script>
        <?php endif; ?>
    </div>
<?php
}
?>