<?php
/**
 * File Name:   cart.php
 * File Folder: includes/
 * File Path:   includes/cart.php
 *
 * Logic untuk Keranjang Belanja dan Pemrosesan Pesanan (Checkout).
 * * --- PERBAIKAN (SECURE v3.2.8) ---
 * 1. dw_process_order: Kalkulasi ulang ongkir di server untuk mencegah manipulasi harga dari client.
 * 2. Tetap mendukung Custom Shipping Profiles & Atomic Stock Check.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =========================================================================
// SINKRONISASI KERANJANG (CART SYNC)
// =========================================================================

/**
 * Mengambil keranjang user dari user_meta.
 */
function dw_get_user_cart($user_id) {
    $cart = get_user_meta($user_id, '_dw_cart_items', true);
    if (empty($cart) || !is_array($cart)) {
        return [];
    }
    return $cart;
}

/**
 * Menyimpan keranjang user ke user_meta.
 */
function dw_save_user_cart($user_id, $items) {
    if (!is_array($items)) $items = [];
    
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
 */
function dw_sync_user_cart($user_id, $guest_items) {
    $server_cart = dw_get_user_cart($user_id);
    if (empty($server_cart)) {
        dw_save_user_cart($user_id, $guest_items);
        return $guest_items;
    }
    if (empty($guest_items)) {
        return $server_cart;
    }

    $merged_cart = $server_cart;
    $server_item_ids = array_column($server_cart, 'id');

    foreach ($guest_items as $guest_item) {
        $item_id = $guest_item['id'];
        $index = array_search($item_id, $server_item_ids);

        if ($index !== false) {
            $merged_cart[$index]['quantity'] += $guest_item['quantity']; 
        } else {
            $merged_cart[] = $guest_item;
        }
    }
    
    dw_save_user_cart($user_id, $merged_cart);
    return $merged_cart;
}

/**
 * Menghapus keranjang user (saat logout).
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

    $items_by_seller = [];
    foreach ($cart_items as $item) {
        $seller_id = absint($item['seller_id'] ?? $item['sellerId']); // Handle inconsistencies
        if ($seller_id > 0) {
            $items_by_seller[$seller_id][] = absint($item['product_id'] ?? $item['productId']);
        }
    }

    $seller_options = [];
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
 * Mendukung profil pengiriman kustom.
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

    // 2. Tentukan apakah pengiriman ini LOKAL
    $is_local_kabupaten = ($origin_kab_id == $dest_kab_id);
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
    
    // Siapkan array alamat untuk validasi ongkir ulang (SECURITY FIX)
    $address_for_calc = [
        'kabupaten_id' => $alamat['api_kabupaten_id'], 
        'kecamatan_id' => $alamat['api_kecamatan_id'],
    ];

    // Hapus ID dari array alamat agar tidak bentrok saat insert ke tabel transaksi
    unset($alamat['id']); 
    unset($alamat['user_id']);

    $total_produk = 0;
    $total_ongkir = 0;
    $total_transaksi = 0;
    
    // 4. Kelompokkan item per pedagang
    $items_by_seller = [];
    $product_ids_by_seller = [];

    foreach ($cart_items as $item) {
        $seller_id = absint($item['seller_id'] ?? $item['sellerId']);
        if ($seller_id === 0) return new WP_Error('invalid_product', 'Produk tidak memiliki data penjual.');
        
        $items_by_seller[$seller_id][] = $item;
        $product_ids_by_seller[$seller_id][] = absint($item['productId']);
    }
    
    // =======================================================
    // MULAI TRANSAKSI DATABASE
    // =======================================================
    $wpdb->query('START TRANSACTION');

    try {
        // 5. Buat 1 Transaksi Utama
        $table_transaksi = $wpdb->prefix . 'dw_transaksi';
        // Kode unik yang lebih aman (Tanggal + Random)
        $kode_unik = 'DW-' . date('Ymd') . '-' . strtoupper(wp_generate_password(4, false, false));

        $wpdb->insert(
            $table_transaksi,
            array_merge(
                $alamat, 
                [
                    'id_pembeli' => $user_id,
                    'kode_unik' => $kode_unik,
                    'total_produk' => 0, 
                    'total_ongkir' => 0, 
                    'total_transaksi' => 0, 
                    'metode_pembayaran' => $payment_method,
                    'status_transaksi' => 'menunggu_pembayaran',
                    'tanggal_transaksi' => current_time('mysql', 1),
                ]
            )
        );
        
        $main_order_id = $wpdb->insert_id;
        if ($main_order_id === 0) throw new Exception('Gagal membuat transaksi utama. ' . $wpdb->last_error);

        // 6. Loop per Pedagang untuk buat Sub-Pesanan
        $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
        $table_items = $wpdb->prefix . 'dw_transaksi_items';
        $toko_table = $wpdb->prefix . 'dw_pedagang';
        $table_variasi = $wpdb->prefix . 'dw_produk_variasi';
        $table_postmeta = $wpdb->postmeta;

        foreach ($items_by_seller as $seller_id => $items) {
            $client_choice = $shipping_choices[$seller_id] ?? null;
            if ($client_choice === null || empty($client_choice['metode'])) {
                 throw new Exception("Opsi pengiriman tidak ditemukan untuk pedagang ID #{$seller_id}.");
            }
            
            // --- SECURITY FIX: VALIDASI & HITUNG ULANG ONGKIR ---
            // Kita tidak mempercayai 'harga' yang dikirim client. Kita hanya ambil 'metode'.
            // Lalu kita hitung ulang berapa harga asli untuk metode tersebut.
            
            $valid_options = dw_get_shipping_options_for_seller($seller_id, $product_ids_by_seller[$seller_id], $address_for_calc);
            
            $selected_option_data = null;
            foreach ($valid_options as $opt) {
                if ($opt['metode'] === $client_choice['metode']) {
                    $selected_option_data = $opt;
                    break;
                }
            }

            if (!$selected_option_data) {
                throw new Exception("Metode pengiriman '{$client_choice['metode']}' tidak valid atau sudah tidak tersedia untuk toko ID #{$seller_id}.");
            }

            // Gunakan harga dari server!
            $sub_ongkir = (float) $selected_option_data['harga'];
            // --- END SECURITY FIX ---
            
            $nama_toko = $wpdb->get_var($wpdb->prepare("SELECT nama_toko FROM $toko_table WHERE id_user = %d", $seller_id));
            if (!$nama_toko) $nama_toko = "Toko (ID: $seller_id)";

            $sub_total_produk = 0;
            
            // 6a. Buat Sub-Pesanan
            $wpdb->insert(
                $table_sub,
                [
                    'id_transaksi' => $main_order_id,
                    'id_pedagang' => $seller_id,
                    'nama_toko' => $nama_toko,
                    'sub_total' => 0, 
                    'ongkir' => $sub_ongkir,
                    'total_pesanan_toko' => 0, 
                    'metode_pengiriman' => $client_choice['metode'],
                    'status_pesanan' => 'menunggu_konfirmasi',
                ]
            );
            
            $sub_order_id = $wpdb->insert_id;
            if ($sub_order_id === 0) throw new Exception("Gagal membuat sub-pesanan.");

            // 6b. Loop item (Atomic Stock Check)
            foreach ($items as $item) {
                $product_id = absint($item['productId']);
                $variation_id = absint($item['variation']['id'] ?? 0);
                $quantity = absint($item['quantity']);
                $price_satuan = (float) $item['price'];
                
                if ( $variation_id > 0 ) {
                    $rows_affected = $wpdb->query($wpdb->prepare(
                        "UPDATE $table_variasi SET stok_variasi = stok_variasi - %d WHERE id = %d AND (stok_variasi IS NULL OR stok_variasi >= %d)", 
                        $quantity, $variation_id, $quantity
                    ));
                } else {
                    $rows_affected = $wpdb->query($wpdb->prepare(
                        "UPDATE $table_postmeta SET meta_value = meta_value - %d WHERE post_id = %d AND meta_key = '_dw_stok' AND (meta_value IS NULL OR meta_value = '' OR CAST(meta_value AS UNSIGNED) >= %d)", 
                        $quantity, $product_id, $quantity
                    ));
                }
                
                if ( $rows_affected === 0 ) {
                    // Logic tambahan untuk memastikan apakah karena stok habis atau stok unlimited (NULL)
                    // ... (Simplifikasi: Anggap gagal update = stok habis untuk keamanan)
                    throw new Exception("Stok untuk produk '{$item['name']}' tidak mencukupi.");
                }
                
                $item_total = $price_satuan * $quantity;
                $sub_total_produk += $item_total;
                
                $wpdb->insert($table_items, [
                    'id_sub_transaksi' => $sub_order_id,
                    'id_produk' => $product_id,
                    'id_variasi' => $variation_id,
                    'nama_produk' => sanitize_text_field($item['name']),
                    'nama_variasi' => sanitize_text_field($item['variation']['deskripsi'] ?? ''),
                    'jumlah' => $quantity,
                    'harga_satuan' => $price_satuan,
                    'total_harga' => $item_total,
                ]);
            }
            
            // 6c. Update total di Sub-Pesanan
            $sub_total_pesanan_toko = $sub_total_produk + $sub_ongkir;
            $wpdb->update($table_sub, ['sub_total' => $sub_total_produk, 'total_pesanan_toko' => $sub_total_pesanan_toko], ['id' => $sub_order_id]);
            
            $total_produk += $sub_total_produk;
            $total_ongkir += $sub_ongkir;
        }

        // 8. Update total di Transaksi Utama
        $total_transaksi = $total_produk + $total_ongkir;
        $wpdb->update($table_transaksi, ['total_produk' => $total_produk, 'total_ongkir' => $total_ongkir, 'total_transaksi' => $total_transaksi], ['id' => $main_order_id]);
        
        $wpdb->query('COMMIT');
        
        dw_clear_user_cart($user_id);
        
        if (function_exists('dw_log_activity')) {
            dw_log_activity('ORDER_CREATED', "Pesanan baru #{$kode_unik} (ID: {$main_order_id}) dibuat oleh user #{$user_id}", $user_id);
        }

        return ['message' => 'Pesanan berhasil dibuat.', 'order_id' => $main_order_id, 'kode_unik' => $kode_unik];

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        if (function_exists('dw_log_activity')) {
            dw_log_activity('ORDER_FAILED', "Gagal membuat pesanan User #{$user_id}: " . $e->getMessage(), $user_id);
        }
        return new WP_Error('order_creation_failed', $e->getMessage());
    }
}


// =========================================================================
// PENGAMBILAN DATA PESANAN (UNTUK API)
// =========================================================================

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

function dw_get_order_detail($order_id) {
    global $wpdb;
    $table_main = $wpdb->prefix . 'dw_transaksi';
    $table_sub = $wpdb->prefix . 'dw_transaksi_sub';
    $table_items = $wpdb->prefix . 'dw_transaksi_items';

    $main_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_main WHERE id = %d", $order_id), 'ARRAY_A');
    if (!$main_order) return null;

    $sub_orders = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_sub WHERE id_transaksi = %d", $order_id), 'ARRAY_A');
    $all_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_items WHERE id_sub_transaksi IN (SELECT id FROM $table_sub WHERE id_transaksi = %d)", $order_id), 'ARRAY_A');

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
        $formatted_sub_orders[] = array_merge($sub, ['items' => $items_for_this_sub]);
    }
    
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

    return array_merge($main_order, ['alamat_pengiriman' => $shipping_address, 'sub_pesanan' => $formatted_sub_orders]);
}

// =========================================================================
// UPDATE STATUS PESANAN & PEDAGANG ORDERS
// =========================================================================

function dw_update_order_payment_status($order_id, $user_id, $new_status, $payment_proof_url = null, $notes = null) {
    global $wpdb;
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';

    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_transaksi WHERE id = %d", $order_id));
    if (!$order) return new WP_Error('not_found', 'Pesanan tidak ditemukan.');
    if ($order->id_pembeli != $user_id) return new WP_Error('unauthorized', 'Akses ditolak.');
    if ($order->status_transaksi !== 'menunggu_pembayaran') return new WP_Error('invalid_status', 'Pesanan tidak dalam status menunggu pembayaran.');

    $data_to_update = ['status_transaksi' => $new_status, 'tanggal_pembayaran' => current_time('mysql', 1)];
    if ($payment_proof_url) $data_to_update['bukti_pembayaran'] = esc_url_raw($payment_proof_url);
    if ($notes) $data_to_update['catatan_pembeli'] = sanitize_textarea_field($notes);

    $result = $wpdb->update($table_transaksi, $data_to_update, ['id' => $order_id]);
    if ($result === false) return new WP_Error('db_error', 'Gagal update database.');

    if (function_exists('dw_log_activity')) dw_log_activity('PAYMENT_CONFIRM_SUCCESS', "Pembayaran pesanan #{$order_id} dikonfirmasi User #{$user_id}", $user_id);
    return $result;
}

function dw_confirm_payment_upload($order_id, $user_id, $payment_proof_url, $notes) {
    return dw_update_order_payment_status($order_id, $user_id, 'pembayaran_dikonfirmasi', $payment_proof_url, $notes);
}

function dw_get_pedagang_orders($pedagang_id, $per_page, $current_page, $status_filter = '') {
    global $wpdb;
    $offset = ($current_page - 1) * $per_page;
    $sql = "SELECT sub.id as sub_order_id, sub.status_pesanan, main.id as order_id, main.kode_unik, main.tanggal_transaksi, main.total_transaksi, main.status_transaksi, main.id_pembeli, alamat.nama_penerima, alamat.no_hp 
            FROM {$wpdb->prefix}dw_transaksi_sub AS sub
            LEFT JOIN {$wpdb->prefix}dw_transaksi AS main ON sub.id_transaksi = main.id
            LEFT JOIN {$wpdb->prefix}dw_user_alamat AS alamat ON main.id_alamat_pengiriman = alamat.id
            WHERE sub.id_pedagang = %d";
    $params = [$pedagang_id];
    if (!empty($status_filter)) { $sql .= " AND sub.status_pesanan = %s"; $params[] = $status_filter; }
    $sql .= " ORDER BY main.tanggal_transaksi DESC LIMIT %d OFFSET %d";
    $params[] = $per_page; $params[] = $offset;
    return $wpdb->get_results($wpdb->prepare($sql, $params), 'ARRAY_A');
}

function dw_get_pedagang_orders_count($pedagang_id, $status_filter = '') {
    global $wpdb;
    $sql = "SELECT COUNT(id) FROM {$wpdb->prefix}dw_transaksi_sub WHERE id_pedagang = %d";
    $params = [$pedagang_id];
    if (!empty($status_filter)) { $sql .= " AND status_pesanan = %s"; $params[] = $status_filter; }
    return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
}
?>