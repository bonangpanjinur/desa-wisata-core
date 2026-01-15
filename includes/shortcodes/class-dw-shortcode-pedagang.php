<?php
// includes/shortcodes/class-dw-shortcode-pedagang.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcode_Pedagang {

    public function __construct() {
        add_shortcode( 'dw_dashboard_pedagang', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        // 1. Cek Login
        if ( ! is_user_logged_in() ) {
            return '<div class="dw-alert dw-alert-warning p-4 bg-yellow-50 text-yellow-800 rounded border border-yellow-200">Silakan <a href="' . wp_login_url( get_permalink() ) . '" class="font-bold underline">login</a> terlebih dahulu.</div>';
        }

        // Cek Capability / Role
        if ( ! current_user_can( 'dw_manage_pesanan' ) && ! current_user_can( 'administrator' ) && ! current_user_can( 'pedagang' ) ) {
             return '<script>window.location.href="' . home_url('/akun-saya/') . '";</script><div class="p-4 text-center">Mengalihkan...</div>';
        }

        $current_user_id = get_current_user_id();
        global $wpdb;

        // Definisi Tabel
        $table_pedagang      = $wpdb->prefix . 'dw_pedagang';
        $table_produk        = $wpdb->prefix . 'dw_produk';
        $table_variasi       = $wpdb->prefix . 'dw_produk_variasi'; 
        $table_transaksi     = $wpdb->prefix . 'dw_transaksi';
        $table_transaksi_sub = $wpdb->prefix . 'dw_transaksi_sub';
        $table_items         = $wpdb->prefix . 'dw_transaksi_items';
        $table_paket         = $wpdb->prefix . 'dw_paket_transaksi';
        $table_pembelian     = $wpdb->prefix . 'dw_pembelian_paket';
        $table_desa          = $wpdb->prefix . 'dw_desa';

        // Ambil Data Pedagang
        $pedagang = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id_user = %d", $current_user_id));

        if (!$pedagang) {
            return '<div class="flex items-center justify-center min-h-[50vh] bg-gray-50 font-sans p-4">
                <div class="text-center p-8 bg-white rounded-2xl shadow-xl border border-gray-100 max-w-md w-full">
                    <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6"><i class="fas fa-store-slash"></i></div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-3">Akses Ditolak</h2>
                    <p class="text-gray-500 mb-8 leading-relaxed">Maaf, akun Anda belum terdaftar sebagai Mitra UMKM.</p>
                    <a href="'.home_url('/daftar-pedagang').'" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold block w-full hover:bg-blue-700 transition shadow-lg">Daftar Sekarang</a>
                </div>
            </div>';
        }

        $msg = '';
        $msg_type = '';

        // ==========================================
        // FORM HANDLERS (LOGIC)
        // ==========================================

        // --- HANDLER 0: VERIFIKASI PEMBAYARAN & UPDATE STATUS ---
        if (isset($_POST['dw_action']) && $_POST['dw_action'] == 'verify_payment_order') {
            if ( isset($_POST['dw_order_nonce']) && wp_verify_nonce($_POST['dw_order_nonce'], 'dw_verify_order') ) {
                $order_id       = intval($_POST['order_id']); 
                $parent_trx_id  = intval($_POST['parent_trx_id']); 
                $decision       = sanitize_text_field($_POST['decision']); 
                $current_time   = current_time('mysql');
                
                if ($decision === 'accept') {
                    $wpdb->update($table_transaksi_sub, ['status_pesanan' => 'diproses'], ['id' => $order_id, 'id_pedagang' => $pedagang->id]);
                    // Hanya update global jika status sebelumnya menunggu
                    $wpdb->update($table_transaksi, ['status_transaksi' => 'pembayaran_dikonfirmasi', 'tanggal_pembayaran' => $current_time], ['id' => $parent_trx_id]);
                    $msg = "Pembayaran diterima. Pesanan diproses."; $msg_type = "success";
                } elseif ($decision === 'reject') {
                    $reason = sanitize_textarea_field($_POST['rejection_reason']);
                    $wpdb->update($table_transaksi_sub, ['status_pesanan' => 'dibatalkan', 'alasan_batal' => 'Bukti Bayar Ditolak: ' . $reason], ['id' => $order_id, 'id_pedagang' => $pedagang->id]);
                    $wpdb->update($table_transaksi, ['status_transaksi' => 'pembayaran_gagal'], ['id' => $parent_trx_id]);
                    $msg = "Pembayaran ditolak. Pesanan dibatalkan."; $msg_type = "warning";
                } elseif ($decision === 'update_shipping') {
                    $new_status = sanitize_text_field($_POST['status_pesanan']);
                    $no_resi    = sanitize_text_field($_POST['no_resi']);
                    
                    // Logic Kuota
                    $old_data = $wpdb->get_row($wpdb->prepare("SELECT status_pesanan FROM $table_transaksi_sub WHERE id = %d AND id_pedagang = %d", $order_id, $pedagang->id));
                    $old_status = $old_data ? $old_data->status_pesanan : '';

                    $data_update = ['status_pesanan' => $new_status];
                    if(!empty($no_resi)) { $data_update['no_resi'] = $no_resi; }
                    
                    $wpdb->update($table_transaksi_sub, $data_update, ['id' => $order_id, 'id_pedagang' => $pedagang->id]);
                    
                    if ($new_status === 'selesai' && $old_status !== 'selesai') {
                        $wpdb->query($wpdb->prepare("UPDATE $table_pedagang SET sisa_transaksi = sisa_transaksi - 1 WHERE id = %d", $pedagang->id));
                        $pedagang = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id_user = %d", $current_user_id));
                    }

                    $msg = "Status pesanan diperbarui."; $msg_type = "success";
                }
            }
        }

        // --- HANDLER 1: SIMPAN PENGATURAN TOKO ---
        if ( isset($_POST['dw_action']) && $_POST['dw_action'] == 'save_store_settings' ) {
            if ( isset($_POST['dw_settings_nonce']) && wp_verify_nonce($_POST['dw_settings_nonce'], 'dw_save_settings') ) {
                
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                
                $update_data = [
                    'nama_toko'      => sanitize_text_field($_POST['nama_toko']),
                    'nama_pemilik'   => sanitize_text_field($_POST['nama_pemilik']),
                    'nomor_wa'       => sanitize_text_field($_POST['nomor_wa']),
                    'updated_at'     => current_time('mysql'),
                    'alamat_lengkap' => sanitize_textarea_field($_POST['alamat_lengkap']),
                    'kode_pos'       => sanitize_text_field($_POST['kode_pos']),
                    'url_gmaps'      => esc_url_raw($_POST['url_gmaps']),
                    'provinsi_nama'  => sanitize_text_field($_POST['provinsi_nama']),
                    'kabupaten_nama' => sanitize_text_field($_POST['kabupaten_nama']),
                    'kecamatan_nama' => sanitize_text_field($_POST['kecamatan_nama']),
                    'kelurahan_nama' => sanitize_text_field($_POST['kelurahan_nama']),
                    'nama_bank'      => sanitize_text_field($_POST['nama_bank']),
                    'no_rekening'    => sanitize_text_field($_POST['no_rekening']),
                    'atas_nama_rekening' => sanitize_text_field($_POST['atas_nama_rekening']),
                    'allow_pesan_di_tempat' => isset($_POST['allow_pesan_di_tempat']) ? 1 : 0,
                    'shipping_ojek_lokal_aktif' => isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0,
                    'shipping_nasional_aktif'   => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,
                    'shipping_nasional_harga'   => floatval($_POST['shipping_nasional_harga']),
                ];

                if(isset($_POST['nik'])) $update_data['nik'] = sanitize_text_field($_POST['nik']);

                // Notifikasi
                if (isset($_POST['order_notification_type'])) {
                    $notif_type = sanitize_text_field($_POST['order_notification_type']);
                    $update_data['order_notification_type'] = $notif_type;
                    if ($notif_type == 'youtube') {
                        $update_data['order_notification_sound'] = esc_url_raw($_POST['order_notification_youtube']);
                    } elseif ($notif_type == 'upload' && !empty($_FILES['order_notification_file']['name'])) {
                        $uploaded_file = wp_handle_upload($_FILES['order_notification_file'], ['test_form' => false]);
                        if (isset($uploaded_file['url']) && !isset($uploaded_file['error'])) {
                            $update_data['order_notification_sound'] = $uploaded_file['url'];
                        }
                    } else {
                        $update_data['order_notification_sound'] = NULL;
                    }
                }

                // Wilayah ID
                $kel_id_baru = !empty($_POST['api_kelurahan_id']) ? sanitize_text_field($_POST['api_kelurahan_id']) : '';
                if(!empty($_POST['api_provinsi_id'])) $update_data['api_provinsi_id'] = sanitize_text_field($_POST['api_provinsi_id']);
                if(!empty($_POST['api_kabupaten_id'])) $update_data['api_kabupaten_id'] = sanitize_text_field($_POST['api_kabupaten_id']);
                if(!empty($_POST['api_kecamatan_id'])) $update_data['api_kecamatan_id'] = sanitize_text_field($_POST['api_kecamatan_id']);
                if($kel_id_baru) {
                    $update_data['api_kelurahan_id'] = $kel_id_baru;
                    // Logic Relasi Desa
                    $desa_terkait = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table_desa WHERE api_kelurahan_id = %s", $kel_id_baru ) );
                    if ( $desa_terkait ) {
                        $update_data['id_desa'] = $desa_terkait->id;
                        $update_data['is_independent'] = 0; 
                    } else {
                        $update_data['id_desa'] = NULL;
                        $update_data['is_independent'] = 1; 
                    }
                }

                // Zona Ojek (JSON)
                $safe_array_map = function($input) { return isset($input) && is_array($input) ? array_map('sanitize_text_field', $input) : []; };
                $ojek_data = [
                    'satu_kecamatan' => [
                        'dekat' => ['harga' => floatval($_POST['ojek_dekat_harga']), 'desa_ids' => $safe_array_map($_POST['ojek_dekat_desa_ids'] ?? null)],
                        'jauh' => ['harga' => floatval($_POST['ojek_jauh_harga']), 'desa_ids' => $safe_array_map($_POST['ojek_jauh_desa_ids'] ?? null)]
                    ],
                    'beda_kecamatan' => [
                        'dekat' => ['harga' => floatval($_POST['ojek_beda_kec_dekat_harga']), 'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_dekat_ids'] ?? null)],
                        'jauh' => ['harga' => floatval($_POST['ojek_beda_kec_jauh_harga']), 'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_jauh_ids'] ?? null)]
                    ]
                ];
                $update_data['shipping_ojek_lokal_zona'] = json_encode($ojek_data);

                // Upload Foto
                $files_map = ['foto_profil' => 'foto_profil', 'foto_sampul' => 'foto_sampul', 'foto_ktp' => 'url_ktp', 'foto_qris' => 'qris_image_url'];
                foreach($files_map as $input_name => $db_col) {
                    if ( ! empty($_FILES[$input_name]['name']) ) {
                        $upload = wp_handle_upload( $_FILES[$input_name], ['test_form' => false] );
                        if ( isset($upload['url']) && ! isset($upload['error']) ) {
                            $update_data[$db_col] = $upload['url'];
                        }
                    }
                }

                $wpdb->update($table_pedagang, $update_data, ['id' => $pedagang->id]);
                
                // Sync Meta
                update_user_meta($current_user_id, 'billing_address_1', $update_data['alamat_lengkap']);
                update_user_meta($current_user_id, 'billing_phone', $update_data['nomor_wa']);
                
                $msg = "Pengaturan toko berhasil diperbarui."; $msg_type = "success";
                $pedagang = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id_user = %d", $current_user_id));
            }
        }

        // --- HANDLER 2: PRODUK ---
        if ( isset($_POST['dw_action']) && $_POST['dw_action'] == 'save_product' ) {
            if ( isset($_POST['dw_product_nonce']) && wp_verify_nonce($_POST['dw_product_nonce'], 'dw_save_product') ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php');
                require_once( ABSPATH . 'wp-admin/includes/image.php');
                
                $prod_data = [
                    'id_pedagang' => $pedagang->id,
                    'nama_produk' => sanitize_text_field($_POST['nama_produk']),
                    'harga'       => floatval($_POST['harga']),
                    'stok'        => intval($_POST['stok']),
                    'berat_gram'  => intval($_POST['berat_gram']),
                    'deskripsi'   => wp_kses_post($_POST['deskripsi_produk']),
                    'kategori'    => sanitize_text_field($_POST['kategori']),
                    'kondisi'     => sanitize_text_field($_POST['kondisi']),
                    'status'      => 'aktif',
                    'updated_at'  => current_time('mysql')
                ];

                if (!empty($_FILES['foto_produk']['name'])) {
                    $upload = wp_handle_upload($_FILES['foto_produk'], ['test_form' => false]);
                    if (isset($upload['url']) && !isset($upload['error'])) $prod_data['foto_utama'] = $upload['url'];
                }

                if (!empty($_FILES['galeri_produk']['name'][0])) {
                    $galeri_urls = [];
                    $files = $_FILES['galeri_produk'];
                    foreach ($files['name'] as $key => $value) {
                        if ($files['name'][$key]) {
                            $file = ['name' => $files['name'][$key], 'type' => $files['type'][$key], 'tmp_name' => $files['tmp_name'][$key], 'error' => $files['error'][$key], 'size' => $files['size'][$key]];
                            $upload = wp_handle_upload($file, ['test_form' => false]);
                            if (!isset($upload['error']) && isset($upload['url'])) $galeri_urls[] = $upload['url'];
                        }
                    }
                    if(!empty($galeri_urls)) $prod_data['galeri'] = json_encode($galeri_urls);
                }

                $prod_id = 0;
                if(!empty($_POST['produk_id'])) {
                    $prod_id = intval($_POST['produk_id']);
                    $wpdb->update($table_produk, $prod_data, ['id' => $prod_id, 'id_pedagang' => $pedagang->id]);
                    $msg = 'Produk diperbarui.';
                } else {
                    $prod_data['slug'] = sanitize_title($_POST['nama_produk']) . '-' . time();
                    $prod_data['created_at'] = current_time('mysql');
                    $wpdb->insert($table_produk, $prod_data);
                    $prod_id = $wpdb->insert_id;
                    $msg = 'Produk ditambahkan.';
                }

                // Variasi
                if ($prod_id > 0) {
                    $wpdb->delete($table_variasi, ['id_produk' => $prod_id]);
                    if (isset($_POST['var_nama']) && is_array($_POST['var_nama'])) {
                        foreach ($_POST['var_nama'] as $k => $nm) {
                            if (!empty($nm)) {
                                $wpdb->insert($table_variasi, [
                                    'id_produk' => $prod_id, 'deskripsi_variasi' => sanitize_text_field($nm),
                                    'harga_variasi' => floatval($_POST['var_harga'][$k]), 'stok_variasi' => intval($_POST['var_stok'][$k]),
                                    'is_default' => ($k === 0) ? 1 : 0
                                ]);
                            }
                        }
                    }
                }
                $msg_type = 'success';
            }
        }

        // --- HANDLER 3: HAPUS PRODUK ---
        if ( isset($_GET['act']) && $_GET['act'] == 'del_prod' && isset($_GET['id']) ) {
             $id_del = intval($_GET['id']);
             $wpdb->delete($table_produk, ['id' => $id_del, 'id_pedagang' => $pedagang->id]);
             $wpdb->delete($table_variasi, ['id_produk' => $id_del]);
             $msg = 'Produk dihapus.'; $msg_type = 'success';
        }

        // --- HANDLER 4: BELI PAKET ---
        if (isset($_POST['beli_paket']) && isset($_POST['paket_nonce']) && wp_verify_nonce($_POST['paket_nonce'], 'beli_paket_action')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $id_paket = intval($_POST['id_paket']);
            $paket = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_paket WHERE id = %d", $id_paket));
            if ($paket && !empty($_FILES['bukti_bayar']['name'])) {
                $upload = wp_handle_upload($_FILES['bukti_bayar'], ['test_form' => false]);
                if (isset($upload['url']) && !isset($upload['error'])) {
                    $wpdb->insert($table_pembelian, [
                        'id_pedagang' => $pedagang->id, 'id_paket' => $paket->id,
                        'nama_paket_snapshot' => $paket->nama_paket, 'harga_paket' => $paket->harga,
                        'jumlah_transaksi' => $paket->jumlah_transaksi, 'persentase_komisi_referrer' => 5,
                        'bukti_pembayaran' => $upload['url'], 'status' => 'pending', 'created_at' => current_time('mysql')
                    ]);
                    $msg = "Pembelian diajukan."; $msg_type = "success";
                }
            }
        }

        // ==========================================
        // DATA FETCHING
        // ==========================================
        
        // Produk
        $produk_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_produk WHERE id_pedagang = %d ORDER BY created_at DESC", $pedagang->id));
        if ($produk_list) {
            foreach ($produk_list as $p) {
                $p->variasi = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_variasi WHERE id_produk = %d ORDER BY id ASC", $p->id));
                $p->galeri_list = !empty($p->galeri) ? json_decode($p->galeri) : [];
            }
        }

        // Pesanan
        $order_query = "SELECT sub.*, t.kode_unik, t.bukti_pembayaran, t.status_transaksi as global_status, t.nama_penerima, t.no_hp, t.alamat_lengkap AS alamat_kirim
                        FROM $table_transaksi_sub sub JOIN $table_transaksi t ON sub.id_transaksi = t.id
                        WHERE sub.id_pedagang = %d ORDER BY sub.created_at DESC";
        $order_list = $wpdb->get_results($wpdb->prepare($order_query, $pedagang->id));

        $order_counts = ['all' => count($order_list), 'belum_bayar' => 0, 'perlu_dikirim' => 0, 'dikirim' => 0, 'selesai' => 0, 'dibatalkan' => 0];
        foreach ($order_list as $o) {
            $ps = $o->global_status; $os = $o->status_pesanan;
            if ($ps == 'menunggu_pembayaran') $order_counts['belum_bayar']++;
            elseif (in_array($os, ['dibatalkan', 'pembayaran_gagal'])) $order_counts['dibatalkan']++;
            elseif ($os == 'selesai') $order_counts['selesai']++;
            elseif (in_array($os, ['dikirim_ekspedisi', 'diantar_ojek', 'dalam_perjalanan', 'siap_diambil'])) $order_counts['dikirim']++;
            elseif (in_array($os, ['menunggu_konfirmasi', 'diproses', 'menunggu_driver', 'penawaran_driver', 'nego', 'menunggu_penjemputan'])) $order_counts['perlu_dikirim']++;
            
            $o->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_items WHERE id_sub_transaksi = %d", $o->id));
            $o->total_items = count($o->items);
            $o->first_item_name = !empty($o->items) ? $o->items[0]->nama_produk : 'Produk';
        }

        // Paket & Histori
        $pakets = $wpdb->get_results("SELECT * FROM $table_paket WHERE status = 'aktif' AND target_role = 'pedagang' ORDER BY harga ASC");
        $histori_paket = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_pembelian WHERE id_pedagang = %d ORDER BY created_at DESC LIMIT 10", $pedagang->id));

        // Data Settings
        $saved_zones = [];
        if (!empty($pedagang->shipping_ojek_lokal_zona)) {
            $decoded = json_decode($pedagang->shipping_ojek_lokal_zona, true);
            if (json_last_error() === JSON_ERROR_NONE) $saved_zones = $decoded;
        }
        $ojek_zona = isset($saved_zones['satu_kecamatan']) ? $saved_zones : [
            'satu_kecamatan' => ['dekat' => ['harga' => '', 'desa_ids' => []], 'jauh' => ['harga' => '', 'desa_ids' => []]],
            'beda_kecamatan' => ['dekat' => ['harga' => '', 'kecamatan_ids' => []], 'jauh' => ['harga' => '', 'kecamatan_ids' => []]]
        ];
        $count_produk = count($produk_list);
        $revenue = $wpdb->get_var($wpdb->prepare("SELECT SUM(total_pesanan_toko) FROM $table_transaksi_sub WHERE id_pedagang = %d AND status_pesanan IN ('selesai', 'dikirim_ekspedisi', 'lunas')", $pedagang->id));

        // Helper untuk View
        $default_cats = ['Makanan', 'Fashion', 'Kerajinan', 'Pertanian', 'Jasa', 'Elektronik', 'Kesehatan'];
        $existing_cats = $wpdb->get_col("SELECT DISTINCT kategori FROM $table_produk WHERE kategori != ''");
        $kategori_list = array_unique(array_merge($default_cats, $existing_cats ?: [])); sort($kategori_list);

        // Helper Status (Inline replacement for dw_get_status_badge)
        if (!function_exists('dw_local_status_badge')) {
            function dw_local_status_badge($status) {
                $colors = [
                    'menunggu_pembayaran'=>'bg-yellow-50 text-yellow-700', 'pembayaran_dikonfirmasi'=>'bg-emerald-50 text-emerald-700', 'pembayaran_gagal'=>'bg-red-50 text-red-700',
                    'menunggu_konfirmasi'=>'bg-orange-50 text-orange-700', 'diproses'=>'bg-blue-50 text-blue-700', 'dikirim_ekspedisi'=>'bg-purple-50 text-purple-700',
                    'selesai'=>'bg-green-50 text-green-700', 'dibatalkan'=>'bg-gray-100 text-gray-500'
                ];
                $c = $colors[$status] ?? 'bg-gray-50 text-gray-600';
                return "<span class='px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border border-transparent $c'>".str_replace('_',' ',$status)."</span>";
            }
        }

        ob_start();
        ?>
        <!-- Load Dependencies -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <!-- SweetAlert2 (Tambahan untuk UX lebih baik) -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        
        <script>
            tailwind.config = { theme: { extend: { colors: { primary: '#16a34a', secondary: '#1e293b', surface: '#f8fafc' } } } }
        </script>
        <style>
            .order-tab-btn { @apply px-4 py-2 text-xs font-semibold text-gray-500 rounded-full transition-all duration-200 hover:bg-gray-100 whitespace-nowrap border border-transparent flex items-center gap-2; }
            .order-tab-btn.active { @apply bg-gray-900 text-white shadow-md transform scale-105; }
            .badge-count { @apply px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-600 font-bold min-w-[1.25rem] text-center; }
            .order-tab-btn.active .badge-count { @apply bg-gray-700 text-white; }
            .tab-content { display: none; }
            .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

            /* POS Styles */
            .pos-scroll::-webkit-scrollbar { width: 6px; }
            .pos-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
            .pos-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            .pos-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        </style>

        <div class="bg-gray-50 min-h-screen font-sans flex overflow-hidden text-slate-800 dw-dashboard-container relative">
            
            <!-- Mobile Header -->
            <div class="md:hidden fixed top-0 left-0 right-0 h-16 bg-white/80 backdrop-blur-md border-b border-gray-200 z-[40] flex items-center justify-between px-4 shadow-sm">
                <button onclick="toggleMobileSidebar()" class="text-gray-600 p-2"><i class="fas fa-bars text-xl"></i></button>
                <span class="font-bold text-gray-900 text-sm"><i class="fas fa-store text-primary"></i> Merchant Panel</span>
            </div>

            <!-- Sidebar -->
            <aside id="dashboard-sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform -translate-x-full md:translate-x-0 md:relative md:block transition-transform duration-300 pt-0 h-screen overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center gap-3">
                    <img src="<?php echo $pedagang->foto_profil ? esc_url($pedagang->foto_profil) : 'https://ui-avatars.com/api/?name='.urlencode($pedagang->nama_toko); ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                    <div>
                        <h2 class="font-bold text-gray-800 text-sm truncate w-32"><?php echo esc_html($pedagang->nama_toko); ?></h2>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Mitra</p>
                    </div>
                </div>
                <nav class="p-4 space-y-1">
                    <button onclick="switchTab('ringkasan')" id="nav-ringkasan" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition active bg-blue-50 text-blue-600 font-bold"><i class="fas fa-home w-5"></i> Ringkasan</button>
                    <button onclick="switchTab('produk')" id="nav-produk" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition"><i class="fas fa-box w-5"></i> Produk</button>
                    <button onclick="switchTab('pesanan')" id="nav-pesanan" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition">
                        <i class="fas fa-receipt w-5"></i> Pesanan
                        <?php if($order_counts['perlu_dikirim']>0): ?><span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 rounded-full"><?php echo $order_counts['perlu_dikirim']; ?></span><?php endif; ?>
                    </button>
                    <button onclick="switchTab('kasir')" id="nav-kasir" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition"><i class="fas fa-cash-register w-5"></i> Kasir / POS</button>
                    <button onclick="switchTab('paket')" id="nav-paket" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition"><i class="fas fa-ticket-alt w-5"></i> Paket</button>
                    <button onclick="switchTab('pengaturan')" id="nav-pengaturan" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition"><i class="fas fa-cog w-5"></i> Pengaturan</button>
                    <a href="<?php echo wp_logout_url(home_url()); ?>" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 transition mt-4"><i class="fas fa-sign-out-alt w-5"></i> Keluar</a>
                </nav>
            </aside>
            <div id="sidebar-backdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>

            <!-- MAIN CONTENT -->
            <main class="flex-1 p-4 md:p-8 pt-20 md:pt-8 overflow-y-auto h-screen pb-24">
                
                <?php if($msg): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                icon: '<?php echo $msg_type; ?>',
                                title: '<?php echo $msg_type === "success" ? "Berhasil" : "Info"; ?>',
                                text: '<?php echo $msg; ?>',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        });
                    </script>
                <?php endif; ?>

                <!-- VIEW 1: RINGKASAN -->
                <div id="view-ringkasan" class="tab-content active">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm relative overflow-hidden">
                            <p class="text-sm font-medium text-gray-500 mb-1">Total Pendapatan</p>
                            <h3 class="text-2xl font-bold text-gray-900">Rp <?php echo number_format($revenue?:0,0,',','.'); ?></h3>
                            <div class="absolute right-4 top-4 w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fas fa-wallet"></i></div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm relative overflow-hidden">
                            <p class="text-sm font-medium text-gray-500 mb-1">Total Produk</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $count_produk; ?></h3>
                            <div class="absolute right-4 top-4 w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fas fa-box"></i></div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm relative overflow-hidden">
                            <p class="text-sm font-medium text-gray-500 mb-1">Sisa Kuota</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $pedagang->sisa_transaksi; ?></h3>
                            <div class="absolute right-4 top-4 w-10 h-10 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center"><i class="fas fa-ticket-alt"></i></div>
                        </div>
                    </div>
                    <!-- Referral Card -->
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden mb-8">
                        <div class="relative z-10">
                            <p class="text-xs font-bold uppercase text-indigo-200 mb-2">Kode Referral</p>
                            <h2 class="text-3xl font-mono font-bold"><?php echo !empty($pedagang->kode_referral_saya) ? esc_html($pedagang->kode_referral_saya) : '-'; ?></h2>
                            <button onclick="navigator.clipboard.writeText('<?php echo home_url('/register?ref=' . $pedagang->kode_referral_saya); ?>'); Swal.fire('Tersalin!', 'Link referral berhasil disalin', 'success');" class="mt-4 bg-white text-indigo-600 font-bold py-2 px-4 rounded-lg text-sm shadow-md hover:bg-gray-50"><i class="fas fa-link mr-1"></i> Salin Link</button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: PRODUK -->
                <div id="view-produk" class="tab-content hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">Produk</h1>
                        <button onclick="openProductModal()" class="bg-gray-900 text-white px-4 py-2 rounded-lg font-bold text-sm shadow hover:bg-black"><i class="fas fa-plus"></i> Tambah</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php if($produk_list): foreach($produk_list as $p): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition">
                            <div class="relative h-40 bg-gray-100">
                                <img src="<?php echo !empty($p->foto_utama) ? esc_url($p->foto_utama) : 'https://placehold.co/200'; ?>" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition gap-2">
                                    <button onclick='editProduk(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)' class="bg-white w-8 h-8 rounded-full flex items-center justify-center text-gray-800"><i class="fas fa-pen"></i></button>
                                    <a href="?act=del_prod&id=<?php echo $p->id; ?>" onclick="return confirm('Yakin ingin menghapus produk ini?')" class="bg-white w-8 h-8 rounded-full flex items-center justify-center text-red-500"><i class="fas fa-trash"></i></a>
                                </div>
                            </div>
                            <div class="p-3">
                                <h4 class="font-bold text-gray-800 text-sm truncate"><?php echo esc_html($p->nama_produk); ?></h4>
                                <p class="text-gray-900 font-bold text-sm">Rp <?php echo number_format($p->harga,0,',','.'); ?></p>
                                <p class="text-xs text-gray-500">Stok: <?php echo $p->stok; ?></p>
                            </div>
                        </div>
                        <?php endforeach; else: ?>
                        <div class="col-span-full py-10 text-center text-gray-400">Belum ada produk.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- VIEW 3: PESANAN -->
                <div id="view-pesanan" class="tab-content hidden">
                    <div class="flex overflow-x-auto gap-2 mb-6 pb-2 border-b border-gray-100">
                        <button onclick="filterOrders('all')" class="order-tab-btn active" data-tab="all">Semua <span class="badge-count"><?php echo $order_counts['all']; ?></span></button>
                        <button onclick="filterOrders('perlu_dikirim')" class="order-tab-btn" data-tab="perlu_dikirim">Perlu Dikirim <span class="badge-count"><?php echo $order_counts['perlu_dikirim']; ?></span></button>
                        <button onclick="filterOrders('dikirim')" class="order-tab-btn" data-tab="dikirim">Dikirim <span class="badge-count"><?php echo $order_counts['dikirim']; ?></span></button>
                        <button onclick="filterOrders('selesai')" class="order-tab-btn" data-tab="selesai">Selesai <span class="badge-count"><?php echo $order_counts['selesai']; ?></span></button>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 border-b border-gray-100 text-gray-500 uppercase text-xs"><tr><th class="p-4">Info</th><th class="p-4">Item</th><th class="p-4">Total</th><th class="p-4 text-right">Aksi</th></tr></thead>
                            <tbody>
                                <?php if($order_list): foreach($order_list as $o): 
                                    $cat = 'all';
                                    if ($o->status_pesanan == 'selesai') $cat = 'selesai';
                                    elseif (in_array($o->status_pesanan, ['dikirim_ekspedisi','diantar_ojek','siap_diambil'])) $cat = 'dikirim';
                                    elseif (in_array($o->status_pesanan, ['menunggu_konfirmasi','diproses'])) $cat = 'perlu_dikirim';
                                ?>
                                <tr class="order-row border-b border-gray-50 last:border-0 hover:bg-gray-50 transition" data-category="<?php echo $cat; ?>">
                                    <td class="p-4 align-top">
                                        <span class="font-mono font-bold bg-gray-100 px-2 rounded text-xs"><?php echo $o->kode_unik; ?></span>
                                        <div class="text-xs text-gray-500 mt-1"><?php echo date('d M, H:i', strtotime($o->created_at)); ?></div>
                                        <div class="text-xs font-bold text-gray-800 mt-1"><?php echo esc_html($o->nama_penerima); ?></div>
                                    </td>
                                    <td class="p-4 align-top">
                                        <div class="text-xs font-bold text-gray-800"><?php echo esc_html($o->first_item_name); ?></div>
                                        <?php if($o->total_items > 1): ?><div class="text-[10px] text-gray-500">+<?php echo $o->total_items - 1; ?> lainnya</div><?php endif; ?>
                                    </td>
                                    <td class="p-4 align-top">
                                        <div class="font-bold">Rp <?php echo number_format($o->total_pesanan_toko,0,',','.'); ?></div>
                                        <?php echo dw_local_status_badge($o->status_pesanan); ?>
                                    </td>
                                    <td class="p-4 text-right align-top">
                                        <button onclick='openOrderDetail(<?php echo htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8'); ?>)' class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-blue-700">Detail</button>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="4" class="p-8 text-center text-gray-400">Belum ada pesanan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- VIEW 4: KASIR (POS Integrated) -->
                <div id="view-kasir" class="tab-content hidden h-full">
                     <div class="bg-gray-100 h-full w-full flex flex-col md:flex-row overflow-hidden font-sans text-slate-800 rounded-2xl shadow-sm border border-gray-200">
                        <!-- KIRI: Katalog Produk -->
                        <div class="flex-1 flex flex-col h-full relative">
                            <!-- Header Katalog -->
                            <div class="bg-white p-4 border-b border-gray-200 flex justify-between items-center shadow-sm z-10 rounded-tl-2xl">
                                <div>
                                    <h2 class="font-bold text-lg text-gray-800"><i class="fas fa-store text-blue-600 mr-2"></i><?php echo esc_html($pedagang->nama_toko); ?></h2>
                                    <p class="text-xs text-gray-500">Kasir / Point of Sale</p>
                                </div>
                                <div class="relative w-64">
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                    <input type="text" id="pos-search" placeholder="Cari produk..." class="w-full pl-10 pr-4 py-2 bg-gray-100 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white transition" onkeyup="filterPosProducts()">
                                </div>
                            </div>

                            <!-- Grid Produk -->
                            <div class="flex-1 overflow-y-auto p-4 pos-scroll bg-gray-50 h-[500px]">
                                <?php if($produk_list): ?>
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-4" id="product-grid">
                                        <?php foreach($produk_list as $p): ?>
                                        <div class="bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer transition-all pos-card product-item-pos group" 
                                             onclick='addToPosCart(<?php echo json_encode($p); ?>)'
                                             data-name="<?php echo strtolower($p->nama_produk); ?>">
                                            <div class="h-24 bg-gray-100 rounded-lg mb-2 overflow-hidden relative">
                                                <?php if($p->foto_utama): ?>
                                                    <img src="<?php echo esc_url($p->foto_utama); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                                                <?php else: ?>
                                                    <div class="flex items-center justify-center h-full text-gray-300"><i class="fas fa-image text-2xl"></i></div>
                                                <?php endif; ?>
                                                <div class="absolute top-1 right-1 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded backdrop-blur">
                                                    Stok: <?php echo $p->stok; ?>
                                                </div>
                                            </div>
                                            <h4 class="font-bold text-xs text-gray-800 mb-0.5 leading-snug line-clamp-2"><?php echo esc_html($p->nama_produk); ?></h4>
                                            <p class="text-blue-600 font-bold text-xs">Rp <?php echo number_format($p->harga, 0, ',', '.'); ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                        <i class="fas fa-box-open text-4xl mb-2"></i>
                                        <p>Belum ada produk.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- KANAN: Keranjang / Struk -->
                        <div class="w-full md:w-80 bg-white border-l border-gray-200 flex flex-col h-full z-20 rounded-tr-2xl rounded-br-2xl">
                            <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center rounded-tr-2xl">
                                <h3 class="font-bold text-gray-800 text-sm"><i class="fas fa-shopping-cart mr-2"></i>Pesanan</h3>
                                <button onclick="clearPosCart()" class="text-[10px] text-red-500 hover:text-red-700 font-bold uppercase">Reset</button>
                            </div>

                            <!-- List Item -->
                            <div class="flex-1 overflow-y-auto p-4 space-y-2 pos-scroll h-[400px]" id="pos-cart-items-container">
                                <!-- Cart items injected via JS -->
                                <div id="pos-empty-cart-msg" class="text-center py-10 text-gray-400 text-sm">
                                    <i class="fas fa-cash-register text-3xl mb-2 opacity-50"></i>
                                    <p>Keranjang kosong</p>
                                </div>
                            </div>

                            <!-- Total & Action -->
                            <div class="p-4 border-t border-gray-200 bg-gray-50 rounded-br-2xl">
                                <div class="space-y-1 mb-3 text-sm">
                                    <div class="flex justify-between text-gray-600 text-xs"><span>Subtotal</span><span id="pos-subtotal">Rp 0</span></div>
                                    <div class="flex justify-between font-bold text-base text-gray-900 border-t border-gray-200 pt-2"><span>Total</span><span id="pos-grand-total">Rp 0</span></div>
                                </div>
                                
                                <button onclick="openPaymentModal()" id="btn-pos-pay" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow-lg shadow-blue-200 transition-all transform active:scale-95 disabled:bg-gray-300 disabled:shadow-none disabled:cursor-not-allowed flex items-center justify-center gap-2 text-sm" disabled>
                                    <span>Bayar</span> <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 5: PAKET -->
                <div id="view-paket" class="tab-content hidden">
                    <h1 class="text-2xl font-bold text-gray-900 mb-6">Paket & Kuota</h1>
                    <div class="bg-gray-900 text-white p-8 rounded-3xl mb-8 flex justify-between items-center shadow-xl">
                        <div>
                            <p class="text-gray-400 text-xs font-bold uppercase mb-1">Sisa Kuota</p>
                            <h2 class="text-5xl font-black text-emerald-400"><?php echo number_format($pedagang->sisa_transaksi); ?> <span class="text-lg text-gray-400">Trx</span></h2>
                        </div>
                        <i class="fas fa-ticket-alt text-6xl text-white/10"></i>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php if($pakets): foreach($pakets as $pk): ?>
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 hover:shadow-lg transition">
                            <h3 class="font-bold text-lg"><?php echo esc_html($pk->nama_paket); ?></h3>
                            <div class="text-3xl font-bold text-gray-900 my-2">Rp <?php echo number_format($pk->harga,0,',','.'); ?></div>
                            <p class="text-sm text-gray-500 mb-6"><?php echo $pk->jumlah_transaksi; ?> Transaksi</p>
                            <button onclick="openBuyModal(<?php echo $pk->id; ?>, '<?php echo esc_js($pk->nama_paket); ?>', <?php echo $pk->harga; ?>)" class="w-full bg-gray-900 text-white font-bold py-3 rounded-xl">Beli</button>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- VIEW 6: PENGATURAN -->
                <div id="view-pengaturan" class="tab-content hidden">
                    <h1 class="text-2xl font-bold text-gray-900 mb-6">Pengaturan Toko</h1>
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <?php wp_nonce_field('dw_save_settings', 'dw_settings_nonce'); ?>
                        <input type="hidden" name="dw_action" value="save_store_settings">
                        
                        <div class="bg-white p-6 rounded-2xl border border-gray-200">
                            <h3 class="font-bold border-b pb-2 mb-4">Identitas</h3>
                            <div class="grid md:grid-cols-2 gap-4">
                                <input type="text" name="nama_toko" value="<?php echo esc_attr($pedagang->nama_toko); ?>" class="border rounded p-2" placeholder="Nama Toko">
                                <input type="text" name="nomor_wa" value="<?php echo esc_attr($pedagang->nomor_wa); ?>" class="border rounded p-2" placeholder="WhatsApp">
                            </div>
                            <div class="mt-4">
                                <label class="text-xs font-bold uppercase block mb-2">Alamat</label>
                                <textarea name="alamat_lengkap" class="w-full border rounded p-2"><?php echo esc_textarea($pedagang->alamat_lengkap); ?></textarea>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-gray-200">
                            <h3 class="font-bold border-b pb-2 mb-4">Pengiriman</h3>
                            <div class="flex items-center gap-4 mb-4">
                                <input type="checkbox" name="allow_pesan_di_tempat" <?php checked($pedagang->allow_pesan_di_tempat, 1); ?>> <label>Ambil di Tempat</label>
                                <input type="checkbox" name="shipping_ojek_lokal_aktif" <?php checked($pedagang->shipping_ojek_lokal_aktif, 1); ?>> <label>Ojek Lokal</label>
                            </div>
                            <!-- Simplified inputs for Ojek settings -->
                            <div class="grid md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl">
                                <div><label class="text-xs font-bold block">Tarif Ojek Dekat</label><input type="number" name="ojek_dekat_harga" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['dekat']['harga']); ?>" class="border rounded p-2 w-full"></div>
                                <div><label class="text-xs font-bold block">Tarif Ojek Jauh</label><input type="number" name="ojek_jauh_harga" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['jauh']['harga']); ?>" class="border rounded p-2 w-full"></div>
                            </div>
                        </div>

                        <button type="submit" class="bg-primary text-white px-8 py-3 rounded-xl font-bold shadow-lg">Simpan Pengaturan</button>
                    </form>
                </div>

            </main>
        </div>

        <!-- MODAL PRODUK -->
        <div id="modal-produk" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm transition-opacity">
            <div id="modal-produk-panel" class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl p-6 overflow-y-auto transform translate-x-full transition-transform duration-300">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold" id="modal-title">Produk</h2>
                    <button onclick="closeProductModal()" class="text-gray-500"><i class="fas fa-times"></i></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="form-product">
                    <?php wp_nonce_field('dw_save_product', 'dw_product_nonce'); ?>
                    <input type="hidden" name="dw_action" value="save_product">
                    <input type="hidden" name="produk_id" id="prod_id">
                    <div class="space-y-4">
                        <input type="file" name="foto_produk" class="block w-full text-sm border rounded p-2">
                        <input type="text" name="nama_produk" id="prod_nama" placeholder="Nama Produk" class="w-full border rounded p-2" required>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" name="harga" id="prod_harga" placeholder="Harga" class="border rounded p-2" required>
                            <input type="number" name="stok" id="prod_stok" placeholder="Stok" class="border rounded p-2" required>
                        </div>
                        <input type="number" name="berat_gram" id="prod_berat" placeholder="Berat (Gram)" class="border rounded p-2 w-full" required>
                        <textarea name="deskripsi_produk" id="prod_deskripsi" rows="3" placeholder="Deskripsi" class="border rounded p-2 w-full"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-gray-900 text-white font-bold py-3 rounded-xl mt-6">Simpan</button>
                </form>
            </div>
        </div>

        <!-- MODAL ORDER DETAIL -->
        <div id="modal-order-detail" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm">
            <div id="modal-order-panel" class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl p-6 overflow-y-auto transform translate-x-full transition-transform duration-300">
                <div class="flex justify-between items-center mb-6 border-b pb-4">
                    <div>
                        <h2 class="text-xl font-bold">Detail Pesanan</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1" id="det-kode-unik"></p>
                    </div>
                    <button onclick="closeOrderDetailModal()" class="text-gray-500"><i class="fas fa-times"></i></button>
                </div>
                
                <div class="space-y-6">
                    <!-- Info Pembeli -->
                    <div class="bg-gray-50 p-4 rounded-xl">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2">Pembeli</p>
                        <div class="font-bold text-gray-800" id="det-penerima"></div>
                        <div class="text-sm text-gray-600" id="det-hp"></div>
                        <div class="text-sm text-gray-600 mt-1" id="det-alamat"></div>
                    </div>

                    <!-- Items -->
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase mb-2">Produk</p>
                        <table class="w-full text-sm">
                            <tbody id="det-items-body" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>

                    <!-- Total -->
                    <div class="flex justify-between items-center border-t pt-4 font-bold">
                        <span>Total Pesanan</span>
                        <span class="text-lg text-primary" id="det-total"></span>
                    </div>

                    <!-- Actions Form -->
                    <div id="action-wrapper">
                        <!-- Update Status Form -->
                        <form method="POST" id="form-update-status" class="mt-4 pt-4 border-t">
                            <?php wp_nonce_field('dw_verify_order', 'dw_order_nonce'); ?>
                            <input type="hidden" name="dw_action" value="verify_payment_order">
                            <input type="hidden" name="decision" value="update_shipping">
                            <input type="hidden" name="order_id" id="update-order-id">
                            
                            <label class="block text-xs font-bold mb-2">Update Status</label>
                            <select name="status_pesanan" id="update-status" class="w-full border rounded p-2 mb-3">
                                <option value="diproses">Diproses</option>
                                <option value="dikirim_ekspedisi">Dikirim Ekspedisi</option>
                                <option value="selesai">Selesai</option>
                            </select>
                            <input type="text" name="no_resi" id="update-resi" placeholder="Resi (Opsional)" class="w-full border rounded p-2 mb-3">
                            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl">Update Status</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL PAYMENT POS -->
        <div id="modal-payment" class="fixed inset-0 z-50 hidden transition-opacity duration-300">
            <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" onclick="closePaymentModal()"></div>
            <div class="absolute inset-x-0 bottom-0 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 w-full md:w-[450px] bg-white md:rounded-3xl rounded-t-3xl shadow-2xl p-6 transform transition-all scale-100">
                
                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Pembayaran</h3>
                    <p class="text-gray-500 text-sm">Total Tagihan: <span class="font-bold text-blue-600 text-lg" id="modal-total-display">Rp 0</span></p>
                </div>

                <!-- Metode Bayar -->
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <label class="cursor-pointer">
                        <input type="radio" name="pay_method" value="tunai" class="peer sr-only" checked onchange="togglePaymentInput()">
                        <div class="p-3 border-2 border-gray-200 rounded-xl peer-checked:border-blue-500 peer-checked:bg-blue-50 text-center transition hover:bg-gray-50">
                            <i class="fas fa-money-bill-wave text-xl mb-1 text-green-600"></i>
                            <div class="text-xs font-bold">Tunai</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="pay_method" value="qris" class="peer sr-only" onchange="togglePaymentInput()">
                        <div class="p-3 border-2 border-gray-200 rounded-xl peer-checked:border-blue-500 peer-checked:bg-blue-50 text-center transition hover:bg-gray-50">
                            <i class="fas fa-qrcode text-xl mb-1 text-blue-600"></i>
                            <div class="text-xs font-bold">QRIS</div>
                        </div>
                    </label>
                </div>

                <!-- Input Tunai -->
                <div id="cash-input-group" class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Uang Diterima</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-gray-400 font-bold">Rp</span>
                        <input type="text" id="pay-amount" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-lg font-bold text-gray-800 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0" onkeyup="formatInputRupiah(this); calculateChange()">
                    </div>
                    <!-- Quick Money Buttons -->
                    <div class="flex gap-2 mt-3 overflow-x-auto pb-2 no-scrollbar">
                        <button type="button" onclick="setCash(10000)" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">10rb</button>
                        <button type="button" onclick="setCash(20000)" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">20rb</button>
                        <button type="button" onclick="setCash(50000)" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">50rb</button>
                        <button type="button" onclick="setCash(100000)" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">100rb</button>
                        <button type="button" onclick="setCash('exact')" class="bg-blue-100 text-blue-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-blue-200 whitespace-nowrap">Pas</button>
                    </div>
                    <div class="mt-3 flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="text-sm font-bold text-gray-500">Kembalian</span>
                        <span class="text-lg font-bold text-orange-600" id="change-display">Rp 0</span>
                    </div>
                </div>

                <!-- Input Nama Pelanggan (Opsional) -->
                <div class="mb-6">
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Nama Pelanggan (Opsional)</label>
                     <input type="text" id="customer-name" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Contoh: Bpk Budi">
                </div>

                <button onclick="processPayment()" id="btn-confirm-pay" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl shadow-lg transform active:scale-95 transition text-lg flex items-center justify-center gap-2 disabled:bg-gray-400 disabled:cursor-not-allowed">
                    <i class="fas fa-check-circle"></i> Selesaikan Transaksi
                </button>
            </div>
        </div>

        <script>
            // JS Logic (Switch Tab, Modal, Filter)
            function switchTab(id){
                document.querySelectorAll('.tab-content').forEach(e=>e.classList.remove('active'));
                document.getElementById('view-'+id).classList.add('active');
                document.querySelectorAll('.nav-item').forEach(e=>e.classList.remove('active','bg-blue-50','text-blue-600','font-bold'));
                document.getElementById('nav-'+id).classList.add('active','bg-blue-50','text-blue-600','font-bold');
            }
            
            function toggleMobileSidebar() {
                const s = document.getElementById('dashboard-sidebar');
                const b = document.getElementById('sidebar-backdrop');
                if(s.classList.contains('-translate-x-full')) { s.classList.remove('-translate-x-full'); b.classList.remove('hidden'); }
                else { s.classList.add('-translate-x-full'); b.classList.add('hidden'); }
            }

            // Modal Produk
            function openProductModal() {
                document.getElementById('form-product').reset();
                document.getElementById('prod_id').value = '';
                document.getElementById('modal-produk').classList.remove('hidden');
                setTimeout(()=>document.getElementById('modal-produk-panel').classList.remove('translate-x-full'),10);
            }
            function closeProductModal() {
                document.getElementById('modal-produk-panel').classList.add('translate-x-full');
                setTimeout(()=>document.getElementById('modal-produk').classList.add('hidden'),300);
            }
            function editProduk(p) {
                openProductModal();
                document.getElementById('prod_id').value = p.id;
                document.getElementById('prod_nama').value = p.nama_produk;
                document.getElementById('prod_harga').value = p.harga;
                document.getElementById('prod_stok').value = p.stok;
                document.getElementById('prod_berat').value = p.berat_gram;
                document.getElementById('prod_deskripsi').value = p.deskripsi;
            }

            // Order Logic
            function filterOrders(cat) {
                document.querySelectorAll('.order-tab-btn').forEach(b=>b.classList.remove('active'));
                document.querySelector(`button[data-tab="${cat}"]`).classList.add('active');
                document.querySelectorAll('.order-row').forEach(r=>{
                    if(cat==='all' || r.dataset.category===cat) r.style.display = '';
                    else r.style.display = 'none';
                });
            }

            function openOrderDetail(o) {
                const fmt = new Intl.NumberFormat('id-ID');
                document.getElementById('det-kode-unik').innerText = o.kode_unik;
                document.getElementById('det-penerima').innerText = o.nama_penerima;
                document.getElementById('det-hp').innerText = o.no_hp;
                document.getElementById('det-alamat').innerText = o.alamat_kirim;
                document.getElementById('det-total').innerText = 'Rp ' + fmt.format(o.total_pesanan_toko);
                
                // Set Form IDs
                document.getElementById('update-order-id').value = o.id;
                document.getElementById('update-status').value = o.status_pesanan;
                document.getElementById('update-resi').value = o.no_resi || '';

                // Items
                let html = '';
                if(o.items) {
                    o.items.forEach(i => {
                        html += `<tr class="border-b last:border-0"><td class="py-2">${i.nama_produk}</td><td class="text-right py-2 font-bold">x${i.jumlah}</td></tr>`;
                    });
                }
                document.getElementById('det-items-body').innerHTML = html;

                document.getElementById('modal-order-detail').classList.remove('hidden');
                setTimeout(()=>document.getElementById('modal-order-panel').classList.remove('translate-x-full'),10);
            }
            function closeOrderDetailModal() {
                document.getElementById('modal-order-panel').classList.add('translate-x-full');
                setTimeout(()=>document.getElementById('modal-order-detail').classList.add('hidden'),300);
            }

            // --- POS LOGIC START ---
            let posCart = [];
            const posAjaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
            
            function filterPosProducts() {
                const query = document.getElementById('pos-search').value.toLowerCase();
                document.querySelectorAll('.product-item-pos').forEach(el => {
                    const name = el.dataset.name;
                    el.style.display = name.includes(query) ? 'block' : 'none';
                });
            }

            function addToPosCart(product) {
                const existing = posCart.find(item => item.id === product.id);
                if(existing) {
                    if(existing.qty < product.stok) {
                        existing.qty++;
                    } else {
                        Swal.fire({icon: 'warning', title: 'Stok Habis', text: 'Stok produk ini sudah maksimal di keranjang.', timer: 1500, showConfirmButton: false});
                        return;
                    }
                } else {
                    if(product.stok > 0) {
                        posCart.push({...product, qty: 1});
                    } else {
                        Swal.fire({icon: 'error', title: 'Stok Kosong', text: 'Produk ini tidak tersedia.', timer: 1500, showConfirmButton: false});
                        return;
                    }
                }
                renderPosCart();
            }

            function updatePosQty(id, change) {
                const item = posCart.find(i => i.id == id);
                if(item) {
                    const newQty = item.qty + change;
                    if(newQty > 0 && newQty <= item.stok) {
                        item.qty = newQty;
                    } else if (newQty <= 0) {
                        posCart = posCart.filter(i => i.id != id);
                    } else {
                        Swal.fire({icon: 'warning', title: 'Stok Maksimal', text: 'Stok tidak mencukupi.', timer: 1000, showConfirmButton: false});
                    }
                    renderPosCart();
                }
            }

            function clearPosCart() {
                if(posCart.length === 0) return;
                Swal.fire({
                    title: 'Kosongkan keranjang?',
                    showCancelButton: true,
                    confirmButtonText: 'Ya',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        posCart = [];
                        renderPosCart();
                    }
                });
            }

            function renderPosCart() {
                const container = document.getElementById('pos-cart-items-container');
                const emptyMsg = document.getElementById('pos-empty-cart-msg');
                const btnPay = document.getElementById('btn-pos-pay');
                
                container.innerHTML = '';
                let subtotal = 0;

                if(posCart.length === 0) {
                    container.appendChild(emptyMsg);
                    emptyMsg.classList.remove('hidden');
                    btnPay.disabled = true;
                } else {
                    emptyMsg.classList.add('hidden');
                    btnPay.disabled = false;

                    posCart.forEach(item => {
                        const totalItem = item.harga * item.qty;
                        subtotal += totalItem;

                        const el = document.createElement('div');
                        el.className = 'bg-gray-50 p-2 rounded-lg border border-gray-200 flex justify-between items-center fade-in text-xs mb-2';
                        el.innerHTML = `
                            <div class="flex-1 mr-2">
                                <h5 class="font-bold text-gray-800 line-clamp-1">${item.nama_produk}</h5>
                                <div class="text-gray-500">Rp ${new Intl.NumberFormat('id-ID').format(item.harga)}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex items-center bg-white rounded-lg border border-gray-300 h-6">
                                    <button onclick="updatePosQty(${item.id}, -1)" class="px-2 text-gray-600 hover:bg-gray-100 rounded-l-lg h-full">-</button>
                                    <span class="px-1 font-bold w-4 text-center">${item.qty}</span>
                                    <button onclick="updatePosQty(${item.id}, 1)" class="px-2 text-gray-600 hover:bg-gray-100 rounded-r-lg h-full">+</button>
                                </div>
                                <div class="font-bold text-gray-800 w-16 text-right">
                                    ${new Intl.NumberFormat('id-ID').format(totalItem)}
                                </div>
                            </div>
                        `;
                        container.appendChild(el);
                    });
                }

                document.getElementById('pos-subtotal').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
                document.getElementById('pos-grand-total').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
            }

            function getPosCartTotal() {
                return posCart.reduce((sum, item) => sum + (item.harga * item.qty), 0);
            }

            // --- PAYMENT UI LOGIC ---
            function openPaymentModal() {
                const total = getPosCartTotal();
                document.getElementById('modal-total-display').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
                document.getElementById('modal-payment').classList.remove('hidden');
                
                // Reset Input
                document.getElementById('pay-amount').value = '';
                document.getElementById('change-display').innerText = 'Rp 0';
                document.getElementById('btn-confirm-pay').disabled = true;
                
                togglePaymentInput(); // Cek default radio
            }

            function closePaymentModal() {
                document.getElementById('modal-payment').classList.add('hidden');
            }

            function togglePaymentInput() {
                const method = document.querySelector('input[name="pay_method"]:checked').value;
                const cashGroup = document.getElementById('cash-input-group');
                const btn = document.getElementById('btn-confirm-pay');
                
                if(method === 'qris') {
                    cashGroup.classList.add('opacity-50', 'pointer-events-none');
                    btn.disabled = false;
                } else {
                    cashGroup.classList.remove('opacity-50', 'pointer-events-none');
                    calculateChange(); // Re-validate cash
                }
            }

            function formatInputRupiah(el) {
                let val = el.value.replace(/[^0-9]/g, '');
                el.value = new Intl.NumberFormat('id-ID').format(val);
            }

            function setCash(amount) {
                const total = getPosCartTotal();
                let val = amount;
                if(amount === 'exact') val = total;
                
                document.getElementById('pay-amount').value = new Intl.NumberFormat('id-ID').format(val);
                calculateChange();
            }

            function calculateChange() {
                const total = getPosCartTotal();
                const payStr = document.getElementById('pay-amount').value.replace(/\./g, '');
                const pay = parseInt(payStr) || 0;
                const change = pay - total;
                const btn = document.getElementById('btn-confirm-pay');

                if(pay >= total) {
                    document.getElementById('change-display').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(change);
                    document.getElementById('change-display').classList.remove('text-red-500');
                    document.getElementById('change-display').classList.add('text-orange-600');
                    btn.disabled = false;
                } else {
                    document.getElementById('change-display').innerText = 'Kurang Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(change));
                    document.getElementById('change-display').classList.add('text-red-500');
                    document.getElementById('change-display').classList.remove('text-orange-600');
                    btn.disabled = true;
                }
            }

            // --- PROCESS PAYMENT (AJAX) ---
            function processPayment() {
                const btn = document.getElementById('btn-confirm-pay');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                btn.disabled = true;

                const method = document.querySelector('input[name="pay_method"]:checked').value;
                const customer = document.getElementById('customer-name').value || 'Pelanggan Umum';
                const total = getPosCartTotal();
                let cashAmount = 0;
                
                if(method === 'tunai') {
                    cashAmount = parseInt(document.getElementById('pay-amount').value.replace(/\./g, '')) || total;
                } else {
                    cashAmount = total; // QRIS dianggap pas
                }

                // Prepare Data
                const orderData = {
                    action: 'dw_pos_submit_order', // Pastikan handler ini ada di ajax-handlers.php
                    nonce: '<?php echo wp_create_nonce("dw_pos_action"); ?>', // Pastikan nonce ini di-generate
                    cart: posCart,
                    total: total,
                    method: method,
                    cash: cashAmount,
                    customer: customer
                };

                // Simulasi AJAX Request (Ganti dengan real call ke ajax-handlers.php)
                // Karena kita di dalam shortcode, kita bisa buat handler AJAX di dalam plugin core.
                // Untuk sekarang, kita asumsi handler sudah siap atau kita buat dummy response.
                
                // Gunakan jQuery untuk ajax
                if (typeof jQuery !== 'undefined') {
                    jQuery.post(posAjaxUrl, orderData, function(response) {
                        if(response.success) {
                            const trxId = response.data.trx_id;
                            Swal.fire({
                                icon: 'success',
                                title: 'Transaksi Berhasil!',
                                text: 'Kembalian: ' + document.getElementById('change-display').innerText,
                                showCancelButton: true,
                                confirmButtonText: 'Cetak Struk',
                                cancelButtonText: 'Tutup'
                            }).then((res) => {
                                if(res.isConfirmed) {
                                    // Redirect ke halaman cetak struk
                                    window.open('<?php echo home_url("/print-struk?id="); ?>' + response.data.kode_unik, '_blank');
                                }
                                // Reset
                                posCart = [];
                                renderPosCart();
                                closePaymentModal();
                            });
                        } else {
                            Swal.fire('Gagal', response.data.message || 'Terjadi kesalahan.', 'error');
                        }
                        btn.innerHTML = '<i class="fas fa-check-circle"></i> Selesaikan Transaksi';
                        btn.disabled = false;
                    }).fail(function() {
                        Swal.fire('Error', 'Koneksi terputus.', 'error');
                        btn.innerHTML = '<i class="fas fa-check-circle"></i> Selesaikan Transaksi';
                        btn.disabled = false;
                    });
                } else {
                    console.error("jQuery is not loaded for POS AJAX call");
                    Swal.fire('Error', 'System Error: jQuery not loaded.', 'error');
                }
            }
            // --- POS LOGIC END ---

        </script>
        <?php
        return ob_get_clean();
    }
}