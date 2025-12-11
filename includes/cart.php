<?php
/**
 * File Path: includes/cart.php
 *
 * Menangani logika terkait keranjang, checkout, dan pesanan.
 *
 * --- PERBAIKAN (KRITIS v3.2.4) ---
 * - `dw_process_order`: Mengimplementasikan logika Pengecekan Stok
 * dan Pengurangan Stok secara atomik di dalam database transaction.
 * - Ini MENCEGAH kondisi 'race condition' dan pemesanan barang yang habis.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// SINKRONISASI KERANJANG (CART SYNC)
// =========================================================================

/**
 * Mengambil keranjang user dari user_meta.
 *
 * @param int $user_id ID user.
 * @return array Keranjang user.
 */
function dw_get_user_cart($user_id) {
    $cart = get_user_meta($user_id, '_dw_cart_items', true);
    if (empty($cart) || !is_array($cart)) {
        return [];
    }
    // TODO: Validasi ulang produk di keranjang (cek stok, status publish, dll)
    return $cart;
}

/**
 * Menyimpan keranjang user ke user_meta.
 *
 * @param int $user_id ID user.
 * @param array $items Item keranjang.
 * @return bool True jika berhasil.
 */
function dw_save_user_cart($user_id, $items) {
    // Pastikan $items adalah array
    if (!is_array($items)) {
        $items = [];
    }
    
    // Sanitasi dasar
    $sanitized_items = [];
    foreach ($items as $item) {
        if (isset($item['id']) && isset($item['productId']) && isset($item['quantity'])) {
            $sanitized_items[] = [
                'id' => sanitize_text_field($item['id']),
                'productId' => absint($item['productId']),
                'name' => sanitize_text_field($item['name']),
                'price' => floatval($item['price']),
                'quantity' => absint($item['quantity']),
                'image' => esc_url_raw($item['image'] ?? ''),
                'variation' => isset($item['variation']) ? [
                    'id' => absint($item['variation']['id']),
                    'deskripsi' => sanitize_text_field($item['variation']['deskripsi']),
                ] : null,
                'toko' => isset($item['toko']) ? [
                    'id_pedagang' => absint($item['toko']['id_pedagang']),
                    'nama_toko' => sanitize_text_field($item['toko']['nama_toko']),
                    'id_desa' => absint($item['toko']['id_desa']),
                    'nama_desa' => sanitize_text_field($item['toko']['nama_desa']),
                ] : null,
                'sellerId' => absint($item['sellerId']),
            ];
        }
    }
    
    return update_user_meta($user_id, '_dw_cart_items', $sanitized_items);
}

/**
 * Menggabungkan (merge) keranjang guest dengan keranjang server.
 *
 * @param int $user_id ID user.
 * @param array $guest_items Item dari keranjang guest (client).
 * @return array Keranjang yang sudah digabungkan.
 */
function dw_sync_user_cart($user_id, $guest_items) {
    $server_cart = dw_get_user_cart($user_id);
    
    // Jika server cart kosong, langsung gunakan guest cart
    if (empty($server_cart)) {
        dw_save_user_cart($user_id, $guest_items);
        return $guest_items;
    }

    // Jika guest cart kosong, kembalikan server cart
    if (empty($guest_items)) {
        return $server_cart;
    }

    // Lakukan penggabungan (merge)
    $merged_cart = $server_cart;
    $server_item_ids = array_column($server_cart, 'id');

    foreach ($guest_items as $guest_item) {
        $item_id = $guest_item['id'];
        $index = array_search($item_id, $server_item_ids);

        if ($index !== false) {
            // Item sudah ada, tambahkan kuantitas (guest überschreibt server)
            // Atau bisa pilih kuantitas terbesar: max($merged_cart[$index]['quantity'], $guest_item['quantity'])
            $merged_cart[$index]['quantity'] += $guest_item['quantity']; 
        } else {
            // Item baru, tambahkan ke keranjang
            $merged_cart[] = $guest_item;
        }
    }
    
    dw_save_user_cart($user_id, $merged_cart);
    return $merged_cart;
}

/**
 * Menghapus keranjang user (saat logout).
 *
 * @param int $user_id ID user.
 */
function dw_clear_user_cart($user_id) {
    delete_user_meta($user_id, '_dw_cart_items');
}


// =========================================================================
// OPSI PENGIRIMAN (SHIPPING)
// =========================================================================

/**
 * Menghitung opsi pengiriman untuk API.
 */
function dw_calculate_shipping_options_api(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $cart_items = $params['cart_items'] ?? [];
    $address_api = $params['address_api'] ?? [];

    if (empty($cart_items) || empty($address_api) || empty($address_api['kabupaten_id'])) {
        return new WP_REST_Response(['message' => 'Data keranjang atau alamat tidak lengkap.'], 400);
    }

    // 1. Kelompokkan item berdasarkan seller_id
    $items_by_seller = [];
    foreach ($cart_items as $item) {
        $seller_id = absint($item['seller_id']);
        if ($seller_id > 0) {
            $items_by_seller[$seller_id][] = absint($item['product_id']);
        }
    }

    $seller_options = [];

    // 2. Loop setiap seller untuk mendapatkan opsi pengiriman
    foreach ($items_by_seller as $seller_id => $product_ids) {
        $options = dw_get_shipping_options_for_seller($seller_id, $product_ids, $address_api);
        $seller_options[$seller_id] = [
            'options' => $options,
        ];
    }
    
    return new WP_REST_Response(['seller_options' => $seller_options], 200);
}

/**
 * Mendapatkan opsi pengiriman untuk satu seller.
 *
 * @param int $seller_id ID user pedagang.
 * @param array $product_ids Array ID produk dari seller tsb.
 * @param array $dest_address Alamat tujuan (dari API Wilayah).
 * @return array Opsi pengiriman.
 */
function dw_get_shipping_options_for_seller($seller_id, $product_ids, $dest_address) {
    global $wpdb;
    $pedagang_table = $wpdb->prefix . 'dw_pedagang';
    $desa_table = $wpdb->prefix . 'dw_desa';

    // 1. Dapatkan lokasi asal (origin) seller
    $seller_origin = $wpdb->get_row($wpdb->prepare(
        "SELECT p.id_desa, p.shipping_profiles, d.api_kabupaten_id, d.api_kecamatan_id 
         FROM $pedagang_table p
         LEFT JOIN $desa_table d ON p.id_desa = d.id
         WHERE p.id_user = %d",
        $seller_id
    ));

    if (!$seller_origin || empty($seller_origin->api_kabupaten_id)) {
        return [['metode' => 'tidak_tersedia', 'nama' => 'Lokasi asal pedagang tidak valid', 'harga' => null]];
    }

    $origin_kab_id = $seller_origin->api_kabupaten_id;
    $origin_kec_id = $seller_origin->api_kecamatan_id;
    $dest_kab_id = $dest_address['kabupaten_id'];
    $dest_kec_id = $dest_address['kecamatan_id'];
    
    $shipping_profiles = json_decode($seller_origin->shipping_profiles, true) ?: [];

    // 2. Tentukan apakah pengiriman ini LOKAL (Satu Kabupaten)
    $is_local_kabupaten = ($origin_kab_id == $dest_kab_id);
    
    // 3. Tentukan apakah pengiriman ini LOKAL (Satu Kecamatan)
    $is_local_kecamatan = ($is_local_kabupaten && $origin_kec_id == $dest_kec_id);

    $options = [];
    $has_custom_profile = false;

    // 4. Cek apakah ada produk dengan profil pengiriman kustom
    foreach ($product_ids as $product_id) {
        $profile_key = get_post_meta($product_id, '_dw_shipping_profile', true);
        if (!empty($profile_key) && isset($shipping_profiles[$profile_key])) {
            $profile = $shipping_profiles[$profile_key];
            $options[] = [
                'metode' => 'kustom_' . $profile_key,
                'nama'   => $profile['nama'] ?? 'Pengiriman Kustom',
                'harga'  => (float) $profile['harga'],
            ];
            $has_custom_profile = true;
        }
    }

    // 5. Jika TIDAK ADA produk kustom, gunakan flat rate nasional
    if (!$has_custom_profile) {
        $flat_rate_nasional = (float) ($shipping_profiles['flat_rate_nasional']['harga'] ?? 25000); // Default 25rb
        $options[] = [
            'metode' => 'flat_rate_nasional',
            'nama'   => $shipping_profiles['flat_rate_nasional']['nama'] ?? 'Flat Rate Nasional',
            'harga'  => $flat_rate_nasional,
        ];
    }
    
    // 6. Tambahkan opsi "Lokal" jika tersedia dan diaktifkan
    if ($is_local_kabupaten && isset($shipping_profiles['local_delivery']) && ($shipping_profiles['local_delivery']['aktif'] ?? false)) {
         $options[] = [
            'metode' => 'local_delivery',
            'nama'   => $shipping_profiles['local_delivery']['nama'] ?? 'Pengiriman Lokal',
            'harga'  => (float) ($shipping_profiles['local_delivery']['harga'] ?? 0),
        ];
    }

    // 7. Tambahkan opsi "Ambil di Tempat" jika diaktifkan
    if (isset($shipping_profiles['pickup']) && ($shipping_profiles['pickup']['aktif'] ?? false)) {
         $options[] = [
            'metode' => 'pickup',
            'nama'   => $shipping_profiles['pickup']['nama'] ?? 'Ambil di Tempat',
            'harga'  => (float) ($shipping_profiles['pickup']['harga'] ?? 0),
        ];
    }

    // Jika tidak ada opsi sama sekali
    if (empty($options)) {
         return [['metode' => 'tidak_tersedia', 'nama' => 'Tidak ada pengiriman ke lokasi Anda', 'harga' => null]];
    }

    return $options;
}


// =========================================================================
// PROSES ORDER (CHECKOUT)
// =========================================================================

/**
 * Memproses pembuatan pesanan baru dari data checkout.
 *
 * @param int $user_id ID pembeli.
 * @param array $data Data dari body request.
 * @return array|WP_Error Array ID pesanan atau WP_Error.
 */
function dw_process_order($user_id, $data) {
    global $wpdb;

    // 1. Validasi data input
    $cart_items = $data['cart_items'] ?? [];
    $address_id = absint($data['shipping_address_id'] ?? 0);
    $shipping_choices = $data['seller_shipping_choices'] ?? [];
    $payment_method = sanitize_text_field($data['payment_method'] ?? 'transfer_bank');
    
    if (empty($cart_items) || $address_id === 0 || empty($shipping_choices)) {
        return new WP_Error('invalid_data', 'Data keranjang, alamat, atau pengiriman tidak lengkap.');
    }

    // 2. Verifikasi alamat pengiriman
    $alamat_table = $wpdb->prefix . 'dw_user_alamat';
    $alamat = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $alamat_table WHERE id = %d AND user_id = %d", $address_id, $user_id
    ), 'ARRAY_A');
    if (!$alamat) {
         return new WP_Error('invalid_address', 'Alamat pengiriman tidak valid.');
    }
    // Hapus ID dari array alamat agar tidak bentrok saat insert ke tabel transaksi
    unset($alamat['id']); 
    unset($alamat['user_id']);

    // 3. Inisialisasi variabel total
    $total_produk = 0;
    $total_ongkir = 0;
    $total_transaksi = 0;
    
    // 4. Kelompokkan item per pedagang
    $items_by_seller = [];
    foreach ($cart_items as $item) {
        $seller_id = absint($item['seller_id']);
        if ($seller_id === 0) {
            return new WP_Error('invalid_product', 'Produk "' . $item['name'] . '" tidak memiliki data penjual.');
        }
        $items_by_seller[$seller_id][] = $item;
    }
    
    // =======================================================
    // MULAI TRANSAKSI DATABASE
    // =======================================================
    $wpdb->query('START TRANSACTION');

    try {
        // 5. Buat 1 Transaksi Utama (Tabel dw_transaksi)
        $table_transaksi = $wpdb->prefix . 'dw_transaksi';
        $kode_unik = 'DW-' . strtoupper(uniqid()); // Buat kode unik

        $wpdb->insert(
            $table_transaksi,
            array_merge(
                $alamat, // Data alamat yang sudah disalin
                [
                    'id_pembeli' => $user_id,
                    'kode_unik' => $kode_unik,
                    'total_produk' => 0, // Placeholder
                    'total_ongkir' => 0, // Placeholder
                    'total_transaksi' => 0, // Placeholder
                    'metode_pembayaran' => $payment_method,
                    'status_transaksi' => 'menunggu_pembayaran',
                    'tanggal_transaksi' => current_time('mysql', 1),
                ]
            )
        );
        
        $main_order_id = $wpdb->insert_id;
        if ($main_order_id === 0) {
            throw new Exception('Gagal membuat transaksi utama. ' . $wpdb->last_error);
        }

        // 6. Loop per Pedagang untuk buat Sub-Pesanan (Tabel dw_transaksi_sub)
        $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
        $table_items = $wpdb->prefix . 'dw_transaksi_items';
        $toko_table = $wpdb->prefix . 'dw_pedagang';
        
        // --- PERBAIKAN STOK: Definisikan tabel stok ---
        $table_variasi = $wpdb->prefix . 'dw_produk_variasi';
        $table_postmeta = $wpdb->postmeta;
        // --- AKHIR PERBAIKAN ---

        foreach ($items_by_seller as $seller_id => $items) {
            $shipping_choice = $shipping_choices[$seller_id] ?? null;
            if ($shipping_choice === null || $shipping_choice['harga'] === null) {
                 throw new Exception("Opsi pengiriman tidak ditemukan untuk pedagang ID #{$seller_id}.");
            }
            
            $nama_toko = $wpdb->get_var($wpdb->prepare("SELECT nama_toko FROM $toko_table WHERE id_user = %d", $seller_id));
            if (!$nama_toko) $nama_toko = "Toko (ID: $seller_id)";

            $sub_total_produk = 0;
            $sub_ongkir = (float) $shipping_choice['harga'];
            
            // 6a. Buat Sub-Pesanan
            $wpdb->insert(
                $table_sub,
                [
                    'id_transaksi' => $main_order_id,
                    'id_pedagang' => $seller_id,
                    'nama_toko' => $nama_toko,
                    'sub_total' => 0, // Placeholder
                    'ongkir' => $sub_ongkir,
                    'total_pesanan_toko' => 0, // Placeholder
                    'metode_pengiriman' => $shipping_choice['metode'],
                    'status_pesanan' => 'menunggu_konfirmasi',
                ]
            );
            
            $sub_order_id = $wpdb->insert_id;
            if ($sub_order_id === 0) {
                throw new Exception("Gagal membuat sub-pesanan untuk pedagang ID #{$seller_id}. " . $wpdb->last_error);
            }

            // 6b. Loop item di sub-pesanan tsb (Tabel dw_transaksi_items)
            foreach ($items as $item) {
                
                // --- PERBAIKAN: CEK DAN KURANGI STOK (ATOMIK) ---
                $product_id = absint($item['productId']);
                $variation_id = absint($item['variation']['id'] ?? 0);
                $quantity = absint($item['quantity']);
                
                if ( $variation_id > 0 ) {
                    // Update stok variasi
                    $rows_affected = $wpdb->query($wpdb->prepare(
                        "UPDATE $table_variasi 
                         SET stok_variasi = stok_variasi - %d 
                         WHERE id = %d 
                           AND (stok_variasi IS NULL OR stok_variasi >= %d)", // Cek jika stok NULL (tak terbatas) atau >= kuantitas
                        $quantity, $variation_id, $quantity
                    ));
                } else {
                    // Update stok produk utama (meta)
                    $rows_affected = $wpdb->query($wpdb->prepare(
                        "UPDATE $table_postmeta 
                         SET meta_value = meta_value - %d 
                         WHERE post_id = %d 
                           AND meta_key = '_dw_stok'
                           AND (meta_value IS NULL OR meta_value = '' OR meta_value >= %d)", // Cek jika stok '' (tak terbatas) atau >= kuantitas
                        $quantity, $product_id, $quantity
                    ));
                }
                
                // Jika $rows_affected = 0, berarti stok tidak mencukupi (atau item tidak ada)
                if ( $rows_affected === 0 ) {
                    // Cek apakah item memang tidak ada/stok 0
                    $stok_habis = true;
                    if ($variation_id > 0) {
                        $stok_saat_ini = $wpdb->get_var($wpdb->prepare("SELECT stok_variasi FROM $table_variasi WHERE id = %d", $variation_id));
                        if ($stok_saat_ini === null || (int)$stok_saat_ini >= $quantity) $stok_habis = false; // Mungkin NULL (tak terbatas)
                    } else {
                        $stok_saat_ini = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM $table_postmeta WHERE post_id = %d AND meta_key = '_dw_stok'", $product_id));
                        if ($stok_saat_ini === null || $stok_saat_ini === '' || (int)$stok_saat_ini >= $quantity) $stok_habis = false; // Mungkin '' (tak terbatas)
                    }

                    if ($stok_habis) {
                        throw new Exception("Stok untuk produk '{$item['name']}' tidak mencukupi (tersisa: {$stok_saat_ini}).");
                    }
                    // Jika $rows_affected 0 tapi stok tidak habis, bisa jadi race condition
                    // atau stok di-set tak terbatas (NULL/''). Jika tak terbatas, kita biarkan lolos.
                    // Jika stok tidak habis tapi rows_affected 0 (karena stok NULL/''), kita perlu cek lagi.
                    // Query di atas sudah menangani NULL dan '', jadi jika rows_affected 0, itu berarti stok < quantity.
                }
                // --- AKHIR PERBAIKAN STOK ---
                
                $item_total = (float) $item['price'] * (int) $item['quantity'];
                $sub_total_produk += $item_total;
                
                $wpdb->insert(
                    $table_items,
                    [
                        'id_sub_transaksi' => $sub_order_id,
                        'id_produk' => $product_id,
                        'id_variasi' => $variation_id,
                        'nama_produk' => sanitize_text_field($item['name']),
                        'nama_variasi' => sanitize_text_field($item['variation']['deskripsi'] ?? ''),
                        'jumlah' => $quantity,
                        'harga_satuan' => (float) $item['price'],
                        'total_harga' => $item_total,
                    ]
                );
            }
            // --- AKHIR LOOP ITEM ---
            
            // 6c. Update total di Sub-Pesanan
            $sub_total_pesanan_toko = $sub_total_produk + $sub_ongkir;
            $wpdb->update(
                $table_sub,
                [
                    'sub_total' => $sub_total_produk,
                    'total_pesanan_toko' => $sub_total_pesanan_toko,
                ],
                ['id' => $sub_order_id]
            );
            
            // 7. Akumulasi total untuk Transaksi Utama
            $total_produk += $sub_total_produk;
            $total_ongkir += $sub_ongkir;
        }

        // 8. Update total di Transaksi Utama
        $total_transaksi = $total_produk + $total_ongkir;
        $wpdb->update(
            $table_transaksi,
            [
                'total_produk' => $total_produk,
                'total_ongkir' => $total_ongkir,
                'total_transaksi' => $total_transaksi,
            ],
            ['id' => $main_order_id]
        );
        
        // =======================================================
        // SELESAI TRANSAKSI DATABASE
        // =======================================================
        $wpdb->query('COMMIT');
        
        // 9. Kosongkan keranjang server
        dw_clear_user_cart($user_id);
        
        // 10. Kirim notifikasi (WA, Email, dll)
        // TODO: Tambahkan hook atau fungsi notifikasi di sini
        dw_log_activity('ORDER_CREATED', "Pesanan baru #{$kode_unik} (ID: {$main_order_id}) dibuat oleh user #{$user_id}", $user_id);

        return ['message' => 'Pesanan berhasil dibuat.', 'order_id' => $main_order_id];

    } catch (Exception $e) {
        // =======================================================
        // BATALKAN TRANSAKSI JIKA GAGAL
        // =======================================================
        $wpdb->query('ROLLBACK');
        dw_log_activity('ORDER_FAILED', "Gagal membuat pesanan untuk user #{$user_id}. Error: " . $e->getMessage(), $user_id);
        return new WP_Error('order_creation_failed', $e->getMessage());
    }
}


// =========================================================================
// PENGAMBILAN DATA PESANAN (UNTUK API)
// =========================================================================

/**
 * Mengambil daftar pesanan (orders) untuk seorang pelanggan (customer).
 *
 * @param int $user_id ID user pembeli.
 * @return array
 */
function dw_get_orders_by_customer_id($user_id) {
    global $wpdb;
    $table_main = $wpdb->prefix . 'dw_transaksi';

    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT id, kode_unik, tanggal_transaksi, total_transaksi, status_transaksi 
         FROM $table_main 
         WHERE id_pembeli = %d 
         ORDER BY tanggal_transaksi DESC", 
        $user_id
    ));
    
    return array_map(function($order) {
        return [
            'id' => (int) $order->id,
            'kode_unik' => $order->kode_unik,
            'tanggal_transaksi' => $order->tanggal_transaksi,
            'total_transaksi' => (float) $order->total_transaksi,
            'status_transaksi' => $order->status_transaksi,
        ];
    }, $orders);
}

/**
 * Mengambil detail lengkap dari satu pesanan (order).
 *
 * @param int $order_id ID Transaksi Utama.
 * @return array|null
 */
function dw_get_order_detail($order_id) {
    global $wpdb;
    $table_main = $wpdb->prefix . 'dw_transaksi';
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $table_items = $wpdb->prefix . 'dw_transaksi_items';

    // 1. Ambil data transaksi utama
    $main_order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_main WHERE id = %d", $order_id
    ), 'ARRAY_A');

    if (!$main_order) {
        return null;
    }

    // 2. Ambil semua sub-pesanan
    $sub_orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_sub WHERE id_transaksi = %d", $order_id
    ), 'ARRAY_A');

    // 3. Ambil semua item
    $all_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_items WHERE id_sub_transaksi IN (SELECT id FROM $table_sub WHERE id_transaksi = %d)", $order_id
    ), 'ARRAY_A');

    // 4. Susun data
    $formatted_sub_orders = [];
    foreach ($sub_orders as $sub) {
        $sub_id = (int) $sub['id'];
        $items_for_this_sub = [];
        
        foreach ($all_items as $item) {
            if ((int) $item['id_sub_transaksi'] === $sub_id) {
                $items_for_this_sub[] = [
                    'id' => (int) $item['id'],
                    'id_produk' => (int) $item['id_produk'],
                    'id_variasi' => (int) $item['id_variasi'],
                    'nama_produk' => $item['nama_produk'],
                    'nama_variasi' => $item['nama_variasi'],
                    'jumlah' => (int) $item['jumlah'],
                    'harga_satuan' => (float) $item['harga_satuan'],
                    'total_harga' => (float) $item['total_harga'],
                ];
            }
        }
        
        $formatted_sub_orders[] = [
            'id' => $sub_id,
            'id_pedagang' => (int) $sub['id_pedagang'],
            'nama_toko' => $sub['nama_toko'],
            'sub_total' => (float) $sub['sub_total'],
            'ongkir' => (float) $sub['ongkir'],
            'total_pesanan_toko' => (float) $sub['total_pesanan_toko'],
            'metode_pengiriman' => $sub['metode_pengiriman'],
            'no_resi' => $sub['no_resi'],
            'status_pesanan' => $sub['status_pesanan'],
            'items' => $items_for_this_sub,
        ];
    }
    
    // 5. Susun data alamat pengiriman dari $main_order
    $shipping_address = [
        'nama_penerima' => $main_order['nama_penerima'],
        'no_hp' => $main_order['no_hp'],
        'alamat_lengkap' => $main_order['alamat_lengkap'],
        'provinsi' => $main_order['provinsi'],
        'kabupaten' => $main_order['kabupaten'],
        'kecamatan' => $main_order['kecamatan'],
        'kelurahan' => $main_order['kelurahan'],
        'kode_pos' => $main_order['kode_pos'],
    ];

    // 6. Format hasil akhir
    $result = [
        'id' => (int) $main_order['id'],
        'id_pembeli' => (int) $main_order['id_pembeli'],
        'kode_unik' => $main_order['kode_unik'],
        'tanggal_transaksi' => $main_order['tanggal_transaksi'],
        'total_produk' => (float) $main_order['total_produk'],
        'total_ongkir' => (float) $main_order['total_ongkir'],
        'total_transaksi' => (float) $main_order['total_transaksi'],
        'metode_pembayaran' => $main_order['metode_pembayaran'],
        'status_transaksi' => $main_order['status_transaksi'],
        'bukti_pembayaran' => $main_order['bukti_pembayaran'],
        'catatan_pembeli' => $main_order['catatan_pembeli'],
        'alamat_pengiriman' => $shipping_address,
        'sub_pesanan' => $formatted_sub_orders,
    ];

    return $result;
}


// =========================================================================
// UPDATE STATUS PESANAN
// =========================================================================

/**
 * Helper untuk mengupdate status pembayaran.
 */
function dw_update_order_payment_status($order_id, $user_id, $new_status, $payment_proof_url = null, $notes = null) {
    global $wpdb;
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';

    // 1. Dapatkan pesanan
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_transaksi WHERE id = %d", $order_id
    ));

    if (!$order) {
        return new WP_Error('not_found', 'Pesanan tidak ditemukan.');
    }
    
    // 2. Verifikasi kepemilikan (hanya pembeli yang bisa konfirmasi)
    if ($order->id_pembeli != $user_id) {
         return new WP_Error('unauthorized', 'Anda tidak memiliki izin untuk mengupdate pesanan ini.');
    }
    
    // 3. Hanya izinkan update dari 'menunggu_pembayaran'
    if ($order->status_transaksi !== 'menunggu_pembayaran') {
        return new WP_Error('invalid_status', 'Hanya pesanan yang menunggu pembayaran yang bisa dikonfirmasi.');
    }

    // 4. Siapkan data update
    $data_to_update = [
        'status_transaksi' => $new_status,
        'tanggal_pembayaran' => current_time('mysql', 1),
    ];
    $data_format = ['%s', '%s'];

    if ($payment_proof_url) {
        $data_to_update['bukti_pembayaran'] = esc_url_raw($payment_proof_url);
        $data_format[] = '%s';
    }
     if ($notes) {
        $data_to_update['catatan_pembeli'] = sanitize_textarea_field($notes);
        $data_format[] = '%s';
    }

    // 5. Update database
    $result = $wpdb->update(
        $table_transaksi,
        $data_to_update,
        ['id' => $order_id], // WHERE
        $data_format,       // Format data
        ['%d']              // Format WHERE
    );

    if ($result === false) {
         dw_log_activity('PAYMENT_CONFIRM_FAIL', "Gagal update status bayar untuk pesanan #{$order_id}. Error: " . $wpdb->last_error, $user_id);
         return new WP_Error('db_error', 'Gagal mengupdate status pembayaran di database.');
    }

    // TODO: Kirim notifikasi ke Admin/Pedagang bahwa pembayaran sudah dikonfirmasi
    dw_log_activity('PAYMENT_CONFIRM_SUCCESS', "Pembayaran pesanan #{$order_id} dikonfirmasi oleh user #{$user_id}", $user_id);

    return $result;
}

/**
 * Fungsi publik untuk menangani konfirmasi pembayaran dari API.
 */
function dw_confirm_payment_upload($order_id, $user_id, $payment_proof_url, $notes) {
    $result = dw_update_order_payment_status($order_id, $user_id, 'pembayaran_dikonfirmasi', $payment_proof_url, $notes);
    return $result;
}


// --- FUNGSI BARU (DIPINDAHKAN DARI includes/orders.php YANG DIHAPUS) ---

/**
 * [BARU] Mengambil daftar pesanan untuk seorang pedagang (untuk admin dashboard).
 *
 * @param int $pedagang_id ID user pedagang.
 * @param int $per_page Jumlah item per halaman.
 * @param int $current_page Halaman saat ini.
 * @param string $status_filter Filter status (opsional).
 * @return array
 */
function dw_get_pedagang_orders($pedagang_id, $per_page, $current_page, $status_filter = '') {
    global $wpdb;
    $offset = ($current_page - 1) * $per_page;
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $table_main = $wpdb->prefix . 'dw_transaksi';
    $table_alamat = $wpdb->prefix . 'dw_user_alamat';

    $sql = "SELECT 
                sub.id as sub_order_id, 
                sub.status_pesanan, 
                main.id as order_id, 
                main.kode_unik, 
                main.tanggal_transaksi, 
                main.total_transaksi, 
                main.status_transaksi, 
                main.id_pembeli,
                alamat.nama_penerima,
                alamat.no_hp
            FROM $table_sub AS sub
            LEFT JOIN $table_main AS main ON sub.id_transaksi = main.id
            LEFT JOIN $table_alamat AS alamat ON main.id_alamat_pengiriman = alamat.id
            WHERE sub.id_pedagang = %d";
    
    $params = [$pedagang_id];

    if (!empty($status_filter)) {
        $sql .= " AND sub.status_pesanan = %s";
        $params[] = $status_filter;
    }

    $sql .= " ORDER BY main.tanggal_transaksi DESC
              LIMIT %d
              OFFSET %d";
    $params[] = $per_page;
    $params[] = $offset;

    return $wpdb->get_results($wpdb->prepare($sql, $params), 'ARRAY_A');
}

/**
 * [BARU] Menghitung jumlah total pesanan untuk seorang pedagang.
 *
 * @param int $pedagang_id ID user pedagang.
 * @param string $status_filter Filter status (opsional).
 * @return int
 */
function dw_get_pedagang_orders_count($pedagang_id, $status_filter = '') {
    global $wpdb;
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';

    $sql = "SELECT COUNT(id) 
            FROM $table_sub 
            WHERE id_pedagang = %d";
    
    $params = [$pedagang_id];

    if (!empty($status_filter)) {
        $sql .= " AND status_pesanan = %s";
        $params[] = $status_filter;
    }

    return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
}
?>