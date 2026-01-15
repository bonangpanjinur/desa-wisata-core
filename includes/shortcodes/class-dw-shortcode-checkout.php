<?php
// includes/shortcodes/class-dw-shortcode-checkout.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcode_Checkout {

    public function __construct() {
        add_shortcode( 'dw_checkout', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        // Cek Login
        if ( ! is_user_logged_in() ) {
            return '<div class="dw-alert dw-alert-warning p-4 bg-yellow-50 text-yellow-800 rounded border border-yellow-200">Silakan <a href="' . wp_login_url( get_permalink() ) . '" class="font-bold underline">login</a> terlebih dahulu untuk melanjutkan checkout.</div>';
        }

        global $wpdb;
        $user_id    = get_current_user_id();
        $user_wp    = wp_get_current_user();
        $roles      = (array) $user_wp->roles;
        
        // --- 1. PROSES PEMBUATAN PESANAN (POST HANDLER) ---
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dw_place_order']) ) {
            
            // Verifikasi Nonce
            if ( ! isset($_POST['dw_checkout_nonce']) || ! wp_verify_nonce($_POST['dw_checkout_nonce'], 'dw_checkout_action') ) {
                return '<div class="bg-red-100 text-red-700 p-4 rounded mb-4">Keamanan tidak valid. Silakan refresh halaman.</div>';
            }

            $selected_cart_ids = isset($_POST['cart_ids']) ? array_map('intval', $_POST['cart_ids']) : [];
            if ( empty($selected_cart_ids) ) {
                return '<div class="bg-red-100 text-red-700 p-4 rounded mb-4">Tidak ada produk yang dipilih.</div>';
            }

            // Sanitasi Input
            $nama_penerima  = sanitize_text_field($_POST['nama_penerima']);
            $no_hp          = sanitize_text_field($_POST['no_hp']); 
            $alamat_lengkap = sanitize_textarea_field($_POST['alamat_lengkap']);
            $kode_pos       = sanitize_text_field($_POST['kode_pos']);
            $metode_bayar   = sanitize_text_field($_POST['payment_method']);

            // Wilayah
            $provinsi       = sanitize_text_field($_POST['provinsi_nama']);
            $kabupaten      = sanitize_text_field($_POST['kabupaten_nama']);
            $kecamatan      = sanitize_text_field($_POST['kecamatan_nama']);
            $kelurahan      = sanitize_text_field($_POST['kelurahan_nama']);
            $prov_id        = sanitize_text_field($_POST['provinsi_id']);
            $kota_id        = sanitize_text_field($_POST['kota_id']);
            $kec_id         = sanitize_text_field($_POST['kecamatan_id']);
            $kel_id         = sanitize_text_field($_POST['kelurahan_id']);

            // Kurir & Ongkir
            $post_kurir     = isset($_POST['kurir']) ? $_POST['kurir'] : []; 
            $post_ongkir    = isset($_POST['ongkir_value']) ? $_POST['ongkir_value'] : [];

            // Ambil Item Keranjang
            $ids_placeholder = implode(',', array_fill(0, count($selected_cart_ids), '%d'));
            $cart_sql = $wpdb->prepare(
                "SELECT c.*, p.nama_produk, p.id_pedagang, p.foto_utama, p.berat_gram,
                        COALESCE(v.harga_variasi, p.harga) as final_price,
                        v.deskripsi_variasi
                 FROM {$wpdb->prefix}dw_cart c
                 JOIN {$wpdb->prefix}dw_produk p ON c.id_produk = p.id
                 LEFT JOIN {$wpdb->prefix}dw_produk_variasi v ON c.id_variasi = v.id
                 WHERE c.id IN ($ids_placeholder)",
                $selected_cart_ids
            );
            $selected_items = $wpdb->get_results($cart_sql);

            if ( $selected_items ) {
                
                // A. Update User Meta (Sync Address)
                update_user_meta($user_id, 'billing_phone', $no_hp);
                update_user_meta($user_id, 'billing_address_1', $alamat_lengkap);
                update_user_meta($user_id, 'billing_postcode', $kode_pos);
                update_user_meta($user_id, 'api_provinsi_id', $prov_id);
                update_user_meta($user_id, 'api_kabupaten_id', $kota_id);
                update_user_meta($user_id, 'api_kecamatan_id', $kec_id);
                update_user_meta($user_id, 'api_kelurahan_id', $kel_id);
                update_user_meta($user_id, 'billing_state', $provinsi);
                update_user_meta($user_id, 'billing_city', $kabupaten);
                
                // B. Update Tabel Role Khusus
                $table_user = '';
                $where_user = [];
                $data_update_user = [];

                $data_address_base = [
                    'alamat_lengkap'   => $alamat_lengkap,
                    'api_provinsi_id'  => $prov_id,
                    'api_kabupaten_id' => $kota_id,
                    'api_kecamatan_id' => $kec_id,
                    'api_kelurahan_id' => $kel_id,
                    'kode_pos'         => $kode_pos,
                ];

                if (in_array('pedagang', $roles)) {
                    $table_user = "{$wpdb->prefix}dw_pedagang";
                    $where_user = ['id_user' => $user_id];
                    $data_update_user = array_merge($data_address_base, [
                        'provinsi_nama'    => $provinsi, 'kabupaten_nama'   => $kabupaten,
                        'kecamatan_nama'   => $kecamatan, 'kelurahan_nama'   => $kelurahan,
                        'nomor_wa'         => $no_hp
                    ]);
                } elseif (in_array('admin_desa', $roles) || in_array('editor_desa', $roles)) {
                    $table_user = "{$wpdb->prefix}dw_desa";
                    $where_user = ['id_user_desa' => $user_id];
                    $data_update_user = array_merge($data_address_base, [
                        'provinsi' => $provinsi, 'kabupaten' => $kabupaten,
                        'kecamatan' => $kecamatan, 'kelurahan' => $kelurahan
                    ]);
                } elseif (in_array('verifikator_umkm', $roles)) {
                    $table_user = "{$wpdb->prefix}dw_verifikator";
                    $where_user = ['id_user' => $user_id];
                    $data_update_user = array_merge($data_address_base, [
                        'provinsi' => $provinsi, 'kabupaten' => $kabupaten,
                        'kecamatan' => $kecamatan, 'kelurahan' => $kelurahan,
                        'nomor_wa' => $no_hp
                    ]);
                } else {
                    $table_user = "{$wpdb->prefix}dw_pembeli";
                    $where_user = ['id_user' => $user_id];
                    $data_update_user = array_merge($data_address_base, [
                        'provinsi' => $provinsi, 'kabupaten' => $kabupaten,
                        'kecamatan' => $kecamatan, 'kelurahan' => $kelurahan,
                        'no_hp' => $no_hp
                    ]);
                }

                if ($table_user && !empty($data_update_user)) {
                    $check_user = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_user WHERE " . key($where_user) . " = %d", current($where_user)));
                    if ($check_user) {
                        $wpdb->update($table_user, $data_update_user, $where_user);
                    }
                }
                
                // C. Kalkulasi Total
                $total_produk = 0;
                $total_ongkir = 0;
                $items_by_toko = [];

                foreach ($selected_items as $item) {
                    $items_by_toko[$item->id_pedagang][] = $item;
                    $total_produk += ($item->final_price * $item->qty);
                }

                foreach ($post_ongkir as $ongkir_val) {
                    $total_ongkir += floatval($ongkir_val);
                }

                $grand_total = $total_produk + $total_ongkir;
                $kode_unik = 'TRX-' . strtoupper(wp_generate_password(8, false));

                // D. Insert Transaksi Utama
                $wpdb->insert("{$wpdb->prefix}dw_transaksi", [
                    'kode_unik'         => $kode_unik,
                    'id_pembeli'        => $user_id,
                    'total_produk'      => $total_produk,
                    'total_ongkir'      => $total_ongkir,
                    'biaya_layanan'     => 0,
                    'total_transaksi'   => $grand_total,
                    'nama_penerima'     => $nama_penerima,
                    'no_hp'             => $no_hp,
                    'alamat_lengkap'    => $alamat_lengkap,
                    'provinsi'          => $provinsi,
                    'kabupaten'         => $kabupaten,
                    'kecamatan'         => $kecamatan,
                    'kelurahan'         => $kelurahan,
                    'kode_pos'          => $kode_pos,
                    'metode_pembayaran' => $metode_bayar,
                    'status_transaksi'  => 'menunggu_pembayaran',
                    'created_at'        => current_time('mysql'),
                    'batas_bayar'       => date('Y-m-d H:i:s', strtotime('+24 hours'))
                ]);
                $trx_id = $wpdb->insert_id;

                // E. Insert Sub Transaksi & Items
                foreach ($items_by_toko as $toko_id => $toko_items) {
                    $sub_total_toko = 0;
                    foreach ($toko_items as $ti) {
                        $sub_total_toko += ($ti->final_price * $ti->qty);
                    }

                    $ongkir_toko = floatval($post_ongkir[$toko_id] ?? 0);
                    $kode_kirim_raw = isset($post_kurir[$toko_id]) ? sanitize_text_field($post_kurir[$toko_id]) : 'pickup';
                    $metode_kirim_db = 'Ambil Sendiri'; 

                    switch ($kode_kirim_raw) {
                        case 'ojek': $metode_kirim_db = 'Ojek Lokal'; break;
                        case 'ekspedisi': $metode_kirim_db = 'Ekspedisi'; break;
                        case 'pickup': default: $metode_kirim_db = 'Ambil Sendiri'; break;
                    }
                    
                    $nama_toko = $wpdb->get_var($wpdb->prepare("SELECT nama_toko FROM {$wpdb->prefix}dw_pedagang WHERE id = %d", $toko_id));

                    $wpdb->insert("{$wpdb->prefix}dw_transaksi_sub", [
                        'id_transaksi'       => $trx_id,
                        'id_pedagang'        => $toko_id,
                        'nama_toko'          => $nama_toko,
                        'sub_total'          => $sub_total_toko,
                        'ongkir'             => $ongkir_toko,
                        'total_pesanan_toko' => $sub_total_toko + $ongkir_toko,
                        'metode_pengiriman'  => $metode_kirim_db,
                        'status_pesanan'     => 'menunggu_konfirmasi',
                        'created_at'         => current_time('mysql')
                    ]);
                    $sub_id = $wpdb->insert_id;

                    foreach ($toko_items as $ti) {
                        $wpdb->insert("{$wpdb->prefix}dw_transaksi_items", [
                            'id_sub_transaksi' => $sub_id,
                            'id_produk'        => $ti->id_produk,
                            'id_variasi'       => $ti->id_variasi,
                            'nama_produk'      => $ti->nama_produk,
                            'foto_snapshot'    => $ti->foto_utama,
                            'berat_snapshot'   => $ti->berat_gram,
                            'nama_variasi'     => $ti->deskripsi_variasi,
                            'harga_satuan'     => $ti->final_price,
                            'jumlah'           => $ti->qty,
                            'total_harga'      => $ti->final_price * $ti->qty
                        ]);
                    }
                }

                // F. Hapus Item dari Keranjang
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}dw_cart WHERE id IN ($ids_placeholder)", $selected_cart_ids));

                // G. Redirect
                $redirect_url = in_array($metode_bayar, ['tunai', 'cod']) 
                    ? home_url('/terima-kasih?id=' . $kode_unik . '&method=' . $metode_bayar) 
                    : home_url('/pembayaran?id=' . $kode_unik);

                if ( !headers_sent() ) {
                    wp_redirect($redirect_url);
                    exit;
                } else {
                    return '<script>window.location.href="' . $redirect_url . '";</script>';
                }
            }
        }

        // --- 2. PENGAMBILAN DATA ALAMAT ---
        $data_source = null;
        $role_type   = 'pembeli'; 

        if (in_array('pedagang', $roles)) {
            $role_type = 'pedagang';
            $data_source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $user_id));
        } elseif (in_array('admin_desa', $roles) || in_array('editor_desa', $roles)) {
            $role_type = 'desa';
            $data_source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id));
        } elseif (in_array('verifikator_umkm', $roles)) {
            $role_type = 'verifikator';
            $data_source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_verifikator WHERE id_user = %d", $user_id));
        } else {
            $data_source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_pembeli WHERE id_user = %d", $user_id));
        }

        $nama_penerima = $user_wp->display_name;
        $no_hp = get_user_meta($user_id, 'billing_phone', true);
        if (empty($no_hp) && $data_source) {
            if (isset($data_source->nomor_wa) && !empty($data_source->nomor_wa)) {
                $no_hp = $data_source->nomor_wa;
            } elseif (isset($data_source->no_hp) && !empty($data_source->no_hp)) {
                $no_hp = $data_source->no_hp;
            }
        }

        $alamat_detail = get_user_meta($user_id, 'billing_address_1', true);
        $kode_pos      = get_user_meta($user_id, 'billing_postcode', true);
        $prov_id       = get_user_meta($user_id, 'api_provinsi_id', true);
        $kota_id       = get_user_meta($user_id, 'api_kabupaten_id', true);
        $kec_id        = get_user_meta($user_id, 'api_kecamatan_id', true);
        $kel_id        = get_user_meta($user_id, 'api_kelurahan_id', true);
        $prov_nm       = get_user_meta($user_id, 'billing_state', true); 
        $kota_nm       = get_user_meta($user_id, 'billing_city', true); 
        $kec_nm        = ''; 
        $kel_nm        = ''; 

        if (empty($alamat_detail) && $data_source) {
            $alamat_detail = $data_source->alamat_lengkap;
            $kode_pos      = $data_source->kode_pos ?? '';
            $prov_id       = $data_source->api_provinsi_id ?? '';
            $kota_id       = $data_source->api_kabupaten_id ?? '';
            $kec_id        = $data_source->api_kecamatan_id ?? '';
            $kel_id        = $data_source->api_kelurahan_id ?? '';

            switch ($role_type) {
                case 'pedagang':
                    $prov_nm = $data_source->provinsi_nama; $kota_nm = $data_source->kabupaten_nama;
                    $kec_nm  = $data_source->kecamatan_nama; $kel_nm  = $data_source->kelurahan_nama;
                    $nama_penerima = $data_source->nama_pemilik;
                    break;
                case 'desa': 
                    $prov_nm = $data_source->provinsi; $kota_nm = $data_source->kabupaten;
                    $kec_nm  = $data_source->kecamatan; $kel_nm  = $data_source->kelurahan;
                    $nama_penerima = $data_source->nama_desa;
                    break;
                default:
                    $prov_nm = $data_source->provinsi; $kota_nm = $data_source->kabupaten;
                    $kec_nm  = $data_source->kecamatan; $kel_nm  = $data_source->kelurahan;
                    $nama_penerima = isset($data_source->nama_lengkap) ? $data_source->nama_lengkap : $user_wp->display_name;
                    break;
            }
        }

        // --- 3. AMBIL DATA CART (POST DATA) ---
        // Jika form ini di-load, harusnya menerima POST cart_ids dari halaman Keranjang
        $selected_cart_ids = isset($_POST['cart_ids']) ? array_map('intval', $_POST['cart_ids']) : [];
        
        // Render Empty State jika tidak ada cart_ids (misal user akses langsung url /checkout)
        if ( empty($selected_cart_ids) ) {
            ob_start();
            ?>
            <div class="bg-gray-50 min-h-[60vh] flex flex-col items-center justify-center p-10 font-sans text-center">
                <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-lg mb-4">
                    <i class="fas fa-shopping-basket text-4xl text-gray-300"></i>
                </div>
                <h2 class="text-xl font-bold mb-2 text-gray-800">Tidak ada produk untuk diproses</h2>
                <p class="text-gray-500 mb-6">Silakan pilih barang di keranjang belanja terlebih dahulu.</p>
                <a href="<?php echo home_url('/keranjang'); ?>" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-full shadow-md transition-all">Ke Keranjang</a>
            </div>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <?php
            return ob_get_clean();
        }

        $ids_placeholder = implode(',', array_fill(0, count($selected_cart_ids), '%d'));
        $sql = "SELECT c.id as cart_id, c.qty, c.id_produk, c.id_variasi,
                       p.nama_produk, p.berat_gram, p.foto_utama, 
                       COALESCE(v.harga_variasi, p.harga) as final_price,
                       v.deskripsi_variasi,
                       t.id as toko_id, t.nama_toko, t.kabupaten_nama as asal_kota, 
                       t.api_kecamatan_id as merchant_kec_id,
                       t.shipping_ojek_lokal_aktif, t.shipping_ojek_lokal_zona,
                       t.shipping_nasional_aktif, t.allow_pesan_di_tempat
                FROM {$wpdb->prefix}dw_cart c
                JOIN {$wpdb->prefix}dw_produk p ON c.id_produk = p.id
                LEFT JOIN {$wpdb->prefix}dw_produk_variasi v ON c.id_variasi = v.id
                JOIN {$wpdb->prefix}dw_pedagang t ON p.id_pedagang = t.id
                WHERE c.id IN ($ids_placeholder) 
                ORDER BY t.nama_toko ASC";

        $items = $wpdb->get_results($wpdb->prepare($sql, $selected_cart_ids));

        $grouped_checkout = [];
        $grand_total_barang = 0;

        foreach ($items as $item) {
            $subtotal = $item->qty * $item->final_price;
            $grouped_checkout[$item->toko_id]['toko'] = [
                'nama' => $item->nama_toko,
                'kota' => $item->asal_kota,
                'kec_id' => $item->merchant_kec_id,
                'ojek_aktif' => $item->shipping_ojek_lokal_aktif,
                'ojek_zona' => $item->shipping_ojek_lokal_zona,
                'nasional_aktif' => $item->shipping_nasional_aktif,
                'pickup_aktif' => $item->allow_pesan_di_tempat
            ];
            $grouped_checkout[$item->toko_id]['items'][] = $item;
            $grand_total_barang += $subtotal;
        }

        ob_start();
        ?>
        <!-- Import Dependencies -->
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: { extend: { colors: { primary: '#16a34a', 'primary-dark': '#15803d', 'secondary': '#f3f4f6' } } }
            }
        </script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <div class="bg-gray-50 min-h-screen py-8 md:py-12 font-sans text-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="mb-8 text-center md:text-left">
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight flex items-center justify-center md:justify-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center text-lg shadow-lg">
                            <i class="fas fa-lock"></i>
                        </div>
                        Checkout Aman
                    </h1>
                    <p class="mt-2 text-sm text-gray-500 ml-1">Selesaikan pesanan Anda dengan mengisi informasi di bawah ini.</p>
                </div>

                <form id="form-checkout" action="" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    <?php wp_nonce_field('dw_checkout_action', 'dw_checkout_nonce'); ?>
                    <input type="hidden" name="dw_place_order" value="1">
                    
                    <?php foreach ($selected_cart_ids as $cid) : ?>
                        <input type="hidden" name="cart_ids[]" value="<?php echo $cid; ?>">
                    <?php endforeach; ?>

                    <!-- KOLOM KIRI (Alamat & Produk) -->
                    <div class="lg:col-span-8 space-y-8">
                        
                        <!-- Alamat Pengiriman -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="bg-gray-50/50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-map-marker-alt text-primary"></i> Alamat Pengiriman
                                </h3>
                            </div>
                            
                            <div class="p-6 space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="space-y-1">
                                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">Nama Penerima</label>
                                        <input type="text" name="nama_penerima" value="<?php echo esc_attr($nama_penerima); ?>" 
                                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none" required>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">No. WhatsApp</label>
                                        <input type="text" name="no_hp" value="<?php echo esc_attr($no_hp); ?>" 
                                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none" required>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">Alamat Lengkap</label>
                                    <textarea name="alamat_lengkap" rows="2" 
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none" required><?php echo esc_textarea($alamat_detail); ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="space-y-1">
                                        <label class="text-xs font-bold text-gray-600">Provinsi</label>
                                        <select name="provinsi_id" id="region-provinsi" 
                                                class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none" 
                                                data-selected="<?php echo esc_attr($prov_id); ?>" required>
                                            <option value="">Memuat...</option>
                                        </select>
                                        <input type="hidden" name="provinsi_nama" id="provinsi_nama" value="<?php echo esc_attr($prov_nm); ?>">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-bold text-gray-600">Kota/Kabupaten</label>
                                        <select name="kota_id" id="region-kabupaten" 
                                                class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none disabled:bg-gray-100" 
                                                data-selected="<?php echo esc_attr($kota_id); ?>" disabled required>
                                        </select>
                                        <input type="hidden" name="kabupaten_nama" id="kabupaten_nama" value="<?php echo esc_attr($kota_nm); ?>">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-bold text-gray-600">Kecamatan</label>
                                        <select name="kecamatan_id" id="region-kecamatan" 
                                                class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none disabled:bg-gray-100" 
                                                data-selected="<?php echo esc_attr($kec_id); ?>" disabled required>
                                        </select>
                                        <input type="hidden" name="kecamatan_nama" id="kecamatan_nama" value="<?php echo esc_attr($kec_nm); ?>">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-bold text-gray-600">Kelurahan / Desa</label>
                                        <select name="kelurahan_id" id="region-kelurahan" 
                                                class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none disabled:bg-gray-100" 
                                                data-selected="<?php echo esc_attr($kel_id); ?>" disabled required>
                                        </select>
                                        <input type="hidden" name="kelurahan_nama" id="kelurahan_nama" value="<?php echo esc_attr($kel_nm); ?>">
                                    </div>
                                </div>

                                <div class="md:w-1/3 space-y-1">
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">Kode Pos</label>
                                    <input type="text" name="kode_pos" id="kode_pos" value="<?php echo esc_attr($kode_pos); ?>" 
                                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- List Produk per Toko -->
                        <?php foreach ($grouped_checkout as $toko_id => $group): $toko = $group['toko']; ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                            <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-3 border-b border-gray-100 flex flex-wrap justify-between items-center gap-2">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2 text-base">
                                    <i class="fas fa-store text-primary"></i> <?php echo esc_html($toko['nama']); ?>
                                </h4>
                                <div class="text-xs text-gray-500 bg-white px-2 py-1 rounded-full border border-gray-200 shadow-sm">
                                    <?php echo esc_html($toko['kota']); ?>
                                </div>
                            </div>

                            <div class="p-6">
                                <div class="space-y-6 mb-6">
                                    <?php foreach ($group['items'] as $item): ?>
                                        <div class="flex gap-4 items-start">
                                            <div class="w-20 h-20 rounded-lg overflow-hidden border border-gray-200 shrink-0">
                                                <img src="<?php echo esc_url($item->foto_utama); ?>" class="w-full h-full object-cover" alt="Produk">
                                            </div>
                                            <div class="flex-grow">
                                                <h5 class="text-sm font-semibold text-gray-800 line-clamp-2 leading-snug mb-1"><?php echo esc_html($item->nama_produk); ?></h5>
                                                <?php if(!empty($item->deskripsi_variasi)): ?>
                                                    <p class="text-xs text-gray-500 mb-2 bg-gray-50 inline-block px-1.5 rounded">Var: <?php echo esc_html($item->deskripsi_variasi); ?></p>
                                                <?php endif; ?>
                                                <div class="flex flex-wrap justify-between items-end mt-1">
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo $item->qty; ?> barang x <span class="font-medium text-gray-700">Rp <?php echo number_format($item->final_price, 0, ',', '.'); ?></span>
                                                    </div>
                                                    <span class="text-sm font-bold text-gray-900">Rp <?php echo number_format($item->qty * $item->final_price, 0, ',', '.'); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100/50">
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                        <div class="flex-grow space-y-2">
                                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide flex items-center gap-1">
                                                <i class="fas fa-truck text-blue-500"></i> Jasa Pengiriman
                                            </label>
                                            <div class="relative">
                                                <select name="kurir[<?php echo $toko_id; ?>]" 
                                                        class="w-full appearance-none pl-4 pr-10 py-2.5 bg-white border border-gray-300 rounded-lg text-sm cursor-pointer focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none shipping-dropdown" 
                                                        required
                                                        data-merchant="<?php echo $toko_id; ?>"
                                                        data-merchant-kecamatan="<?php echo esc_attr($toko['kec_id']); ?>"
                                                        data-ojek-zones='<?php echo htmlspecialchars($toko['ojek_zona'] ?? '{}', ENT_QUOTES, 'UTF-8'); ?>'>
                                                    <option value="">-- Pilih Kurir --</option>
                                                    <?php if($toko['pickup_aktif']): ?><option value="pickup">Ambil Sendiri di Toko (Gratis)</option><?php endif; ?>
                                                    <?php if($toko['ojek_aktif']): ?><option value="ojek">Ojek Lokal (Kurir Desa)</option><?php endif; ?>
                                                    <?php if($toko['nasional_aktif']): ?><option value="ekspedisi">Ekspedisi (JNE/POS/J&T) - Bayar Tempat</option><?php endif; ?>
                                                </select>
                                                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-500">
                                                    <i class="fas fa-chevron-down text-xs"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="min-w-[100px] text-right sm:text-right flex flex-row sm:flex-col justify-between items-center sm:justify-center border-t sm:border-t-0 sm:border-l border-blue-100 pt-3 sm:pt-0 sm:pl-4 mt-1 sm:mt-0">
                                            <span class="text-xs text-gray-500 mb-0.5">Ongkos Kirim</span>
                                            <div class="text-base font-bold text-blue-600" id="ongkir-display-<?php echo $toko_id; ?>">Rp 0</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="ongkir_value[<?php echo $toko_id; ?>]" id="ongkir-value-<?php echo $toko_id; ?>" value="0">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Metode Pembayaran -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="bg-gray-50/50 px-6 py-4 border-b border-gray-100">
                                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-wallet text-primary"></i> Metode Pembayaran
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <!-- Option: Transfer -->
                                    <label class="relative cursor-pointer group">
                                        <input type="radio" name="payment_method" value="transfer" class="peer sr-only" required checked>
                                        <div class="p-4 rounded-xl border-2 border-gray-200 bg-white hover:bg-gray-50 peer-checked:border-primary peer-checked:bg-green-50 transition-all h-full flex flex-col items-center text-center justify-center gap-2">
                                            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-lg mb-1">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <div>
                                                <span class="block font-bold text-sm text-gray-800">Transfer / QRIS</span>
                                                <span class="block text-[10px] text-gray-500 leading-tight mt-1">Konfirmasi Manual via Bukti Foto</span>
                                            </div>
                                            <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-primary">
                                                <i class="fas fa-check-circle text-lg"></i>
                                            </div>
                                        </div>
                                    </label>

                                    <!-- Option: Tunai -->
                                    <label class="relative cursor-pointer group">
                                        <input type="radio" name="payment_method" value="tunai" class="peer sr-only">
                                        <div class="p-4 rounded-xl border-2 border-gray-200 bg-white hover:bg-gray-50 peer-checked:border-yellow-400 peer-checked:bg-yellow-50 transition-all h-full flex flex-col items-center text-center justify-center gap-2">
                                            <div class="w-10 h-10 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-lg mb-1">
                                                <i class="fas fa-cash-register"></i>
                                            </div>
                                            <div>
                                                <span class="block font-bold text-sm text-gray-800">Tunai (Kasir)</span>
                                                <span class="block text-[10px] text-gray-500 leading-tight mt-1">Bayar langsung di kasir toko</span>
                                            </div>
                                            <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-yellow-500">
                                                <i class="fas fa-check-circle text-lg"></i>
                                            </div>
                                        </div>
                                    </label>

                                    <!-- Option: COD -->
                                    <label class="relative cursor-pointer group">
                                        <input type="radio" name="payment_method" value="cod" class="peer sr-only">
                                        <div class="p-4 rounded-xl border-2 border-gray-200 bg-white hover:bg-gray-50 peer-checked:border-orange-400 peer-checked:bg-orange-50 transition-all h-full flex flex-col items-center text-center justify-center gap-2">
                                            <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-lg mb-1">
                                                <i class="fas fa-hand-holding-dollar"></i>
                                            </div>
                                            <div>
                                                <span class="block font-bold text-sm text-gray-800">COD (Bayar Tempat)</span>
                                                <span class="block text-[10px] text-gray-500 leading-tight mt-1">Bayar saat kurir sampai rumah</span>
                                            </div>
                                            <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-orange-500">
                                                <i class="fas fa-check-circle text-lg"></i>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KOLOM KANAN (Sticky Summary) -->
                    <div class="lg:col-span-4">
                        <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-100 sticky top-24">
                            <h3 class="font-bold text-lg mb-6 flex items-center gap-2 text-gray-800">
                                <i class="fas fa-receipt text-gray-400"></i> Ringkasan Belanja
                            </h3>
                            
                            <div class="space-y-4 text-sm text-gray-600 mb-6">
                                <div class="flex justify-between items-center">
                                    <span>Total Harga Barang</span>
                                    <span class="font-bold text-gray-800">Rp <?php echo number_format($grand_total_barang, 0, ',', '.'); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>Total Ongkos Kirim</span>
                                    <span class="font-bold text-gray-800" id="summary-ongkir">Rp 0</span>
                                </div>
                            </div>

                            <div class="border-t border-dashed border-gray-300 pt-5 mb-6">
                                <div class="flex justify-between items-end">
                                    <span class="font-bold text-gray-800 text-base">Total Tagihan</span>
                                    <span class="text-2xl font-extrabold text-primary" id="summary-grand-total">
                                        Rp <?php echo number_format($grand_total_barang, 0, ',', '.'); ?>
                                    </span>
                                </div>
                            </div>

                            <button type="submit" id="btn-submit-order" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-4 rounded-xl shadow-lg shadow-green-200 transition-all transform active:scale-95 flex items-center justify-center gap-2 group">
                                <span>Buat Pesanan Sekarang</span>
                                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                            </button>
                            
                            <div class="mt-4 text-center">
                                <p class="text-xs text-gray-400 flex items-center justify-center gap-1">
                                    <i class="fas fa-shield-alt"></i> Transaksi Anda Dijamin Aman
                                </p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            const baseTotalBarang = <?php echo $grand_total_barang; ?>;
            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

            document.addEventListener('DOMContentLoaded', function() {
                // --- LOGIKA FORM WILAYAH ---
                function loadRegion(action, parentId, target, ph) {
                    let url = `${ajaxUrl}?action=${action}`;
                    if(parentId) url += `&${parentId.key}=${parentId.value}`;
                    
                    target.innerHTML = '<option value="">Memuat...</option>'; 
                    target.disabled = true;

                    fetch(url).then(r=>r.json()).then(res=>{
                        if(res.success) {
                            target.innerHTML = `<option value="">${ph}</option>`;
                            res.data.forEach(item => {
                                const sel = target.dataset.selected == item.id ? 'selected' : '';
                                target.innerHTML += `<option value="${item.id}" data-name="${item.name}" ${sel}>${item.name}</option>`;
                            });
                            target.disabled = false;
                            if(target.dataset.selected) { target.dispatchEvent(new Event('change')); target.dataset.selected=''; }
                        }
                    });
                }

                const prov = document.getElementById('region-provinsi');
                const kab = document.getElementById('region-kabupaten');
                const kec = document.getElementById('region-kecamatan');
                const kel = document.getElementById('region-kelurahan');

                loadRegion('dw_fetch_provinces', null, prov, 'Pilih Provinsi');

                prov.addEventListener('change', function(){ 
                    document.getElementById('provinsi_nama').value = this.options[this.selectedIndex].text;
                    if(this.value) loadRegion('dw_fetch_regencies', {key:'province_id', value:this.value}, kab, 'Pilih Kota'); 
                });
                kab.addEventListener('change', function(){ 
                    document.getElementById('kabupaten_nama').value = this.options[this.selectedIndex].text;
                    if(this.value) loadRegion('dw_fetch_districts', {key:'regency_id', value:this.value}, kec, 'Pilih Kecamatan'); 
                });
                kec.addEventListener('change', function(){ 
                    document.getElementById('kecamatan_nama').value = this.options[this.selectedIndex].text;
                    calculateShippingAll(); 
                    if(this.value) loadRegion('dw_fetch_villages', {key:'district_id', value:this.value}, kel, 'Pilih Desa'); 
                });
                kel.addEventListener('change', function(){ 
                    document.getElementById('kelurahan_nama').value = this.options[this.selectedIndex].text;
                    calculateShippingAll();
                });

                // --- LOGIKA ONGKIR ---
                const dropdowns = document.querySelectorAll('.shipping-dropdown');
                const dispOngkir = document.getElementById('summary-ongkir');
                const dispTotal = document.getElementById('summary-grand-total');

                function formatRupiah(num) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
                }

                function calculateShippingAll() {
                    let totalOngkir = 0;
                    let hasNonPickup = false; 
                    
                    const userKecId = kec.value; 
                    const userKelId = kel.value;

                    dropdowns.forEach(sel => {
                        const mid = sel.dataset.merchant;
                        const merchKec = sel.dataset.merchantKecamatan;
                        const costDisplay = document.getElementById(`ongkir-display-${mid}`);
                        const costInput = document.getElementById(`ongkir-value-${mid}`);
                        
                        let cost = 0;
                        let note = 'Gratis';

                        if(sel.value !== 'pickup' && sel.value !== '') {
                            hasNonPickup = true;
                        }

                        if(sel.value === 'pickup') {
                            cost = 0;
                            note = 'Gratis';
                        } 
                        else if(sel.value === 'ekspedisi') {
                            cost = 0; 
                            note = 'Bayar Tempat';
                        }
                        else if(sel.value === 'ojek') {
                            const zones = JSON.parse(sel.dataset.ojekZones);
                            let foundCost = null;

                            if (userKecId && merchKec) {
                                if (String(userKecId) === String(merchKec)) {
                                    const z = zones.satu_kecamatan;
                                    if(z) {
                                        if (z.dekat && z.dekat.desa_ids && z.dekat.desa_ids.includes(userKelId)) {
                                            foundCost = parseInt(z.dekat.harga);
                                        } else {
                                            foundCost = parseInt(z.jauh?.harga || 0); 
                                        }
                                    }
                                } else {
                                    const z = zones.beda_kecamatan;
                                    if(z) {
                                        if (z.dekat && z.dekat.kecamatan_ids && z.dekat.kecamatan_ids.includes(userKecId)) {
                                            foundCost = parseInt(z.dekat.harga);
                                        } else if (z.jauh && z.jauh.kecamatan_ids && z.jauh.kecamatan_ids.includes(userKecId)) {
                                            foundCost = parseInt(z.jauh.harga);
                                        }
                                    }
                                }
                            }

                            if (foundCost !== null && foundCost > 0) {
                                cost = foundCost;
                                note = formatRupiah(cost);
                            } else if(!userKecId) {
                                note = 'Alamat?';
                            } else {
                                note = 'Tidak Terjangkau';
                            }
                        }

                        costDisplay.innerHTML = note;
                        costInput.value = cost;
                        totalOngkir += cost;
                    });

                    dispOngkir.innerText = formatRupiah(totalOngkir);
                    dispTotal.innerText = formatRupiah(baseTotalBarang + totalOngkir);

                    // --- LOGIKA DISABLE TUNAI ---
                    const radioTunai = document.querySelector('input[name="payment_method"][value="tunai"]');
                    if(radioTunai) {
                        const visualDiv = radioTunai.nextElementSibling; 
                        const parentLabel = radioTunai.closest('label');
                        
                        if(hasNonPickup) {
                            radioTunai.disabled = true;
                            if(radioTunai.checked) {
                                document.querySelector('input[name="payment_method"][value="transfer"]').checked = true;
                            }
                            if(visualDiv) {
                                visualDiv.classList.add('opacity-50', 'bg-gray-100');
                                visualDiv.classList.remove('hover:bg-gray-50');
                            }
                            if(parentLabel) {
                                parentLabel.classList.add('cursor-not-allowed');
                                parentLabel.title = "Metode Tunai hanya tersedia untuk Ambil Sendiri";
                            }
                        } else {
                            radioTunai.disabled = false;
                            if(visualDiv) {
                                visualDiv.classList.remove('opacity-50', 'bg-gray-100');
                                visualDiv.classList.add('hover:bg-gray-50');
                            }
                            if(parentLabel) {
                                parentLabel.classList.remove('cursor-not-allowed');
                                parentLabel.title = "";
                            }
                        }
                    }
                }

                dropdowns.forEach(s => s.addEventListener('change', calculateShippingAll));
                
                document.getElementById('form-checkout').addEventListener('submit', function(e){
                    if(!kec.value) {
                        e.preventDefault();
                        alert('Mohon lengkapi alamat pengiriman untuk menghitung ongkir.');
                        kec.focus();
                    }
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
}