<?php
/**
 * Class DW_POS_Handler
 * Path: includes/classes/class-dw-pos-handler.php
 * Description: Menangani logika backend untuk sistem Point of Sales (Kasir).
 * Mencakup pencarian produk, kalkulasi keranjang, dan penyimpanan order.
 * Version: 2.5.0
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}

class DW_POS_Handler {

    /**
     * Inisialisasi Hook AJAX
     */
    public static function init() {
        // AJAX: Cari Produk
        add_action('wp_ajax_dw_pos_search_product', [__CLASS__, 'handle_search_product']);
        
        // AJAX: Hitung Keranjang (Untuk validasi harga backend)
        add_action('wp_ajax_dw_pos_calculate_cart', [__CLASS__, 'handle_calculate_cart']);
        
        // AJAX: Simpan Order
        add_action('wp_ajax_dw_pos_create_order', [__CLASS__, 'handle_create_order']);
    }

    /**
     * 1. Handle Pencarian Produk
     */
    public static function handle_search_product() {
        // Security Check
        check_ajax_referer('dw_nonce_action', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        $merchant_id = isset($_GET['merchant_id']) ? intval($_GET['merchant_id']) : 0;

        // Query Produk (Post Type: Produk)
        // Asumsi: Produk disimpan sebagai Custom Post Type 'produk' atau tabel khusus.
        // Di sini kita gunakan asumsi tabel wp_posts + meta untuk simplifikasi integrasi standar WP.
        
        $args = [
            'post_type'      => 'produk',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $term,
        ];

        // Jika ada filter pedagang tertentu (untuk multi-vendor)
        if ($merchant_id > 0) {
            $args['author'] = $merchant_id; // Asumsi author = user pedagang
        }

        $query = new WP_Query($args);
        $results = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $pid = get_the_ID();
                
                // Ambil harga (Asumsi disimpan di meta '_price')
                $price = get_post_meta($pid, '_price', true) ?: 0;
                $stock = get_post_meta($pid, '_stock', true) ?: 0;
                $img   = get_the_post_thumbnail_url($pid, 'thumbnail') ?: '';

                $results[] = [
                    'id'    => $pid,
                    'text'  => get_the_title() . ' - Rp ' . number_format($price, 0, ',', '.'),
                    'name'  => get_the_title(),
                    'price' => (float)$price,
                    'stock' => (int)$stock,
                    'image' => $img
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success($results);
    }

    /**
     * 2. Handle Kalkulasi Keranjang (Server-side Logic)
     * Mencegah manipulasi harga dari browser console.
     */
    public static function handle_calculate_cart() {
        check_ajax_referer('dw_nonce_action', 'security');

        $items = isset($_POST['items']) ? $_POST['items'] : [];
        $discount_val = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
        
        if (empty($items)) {
            wp_send_json_error(['message' => 'Keranjang kosong']);
        }

        $subtotal = 0;
        $calculated_items = [];

        foreach ($items as $item) {
            $product_id = intval($item['id']);
            $qty = intval($item['qty']);
            
            // Ambil harga asli dari database (Security)
            $real_price = (float) get_post_meta($product_id, '_price', true);
            
            $line_total = $real_price * $qty;
            $subtotal += $line_total;

            $calculated_items[] = [
                'id' => $product_id,
                'name' => get_the_title($product_id),
                'price' => $real_price,
                'qty' => $qty,
                'line_total' => $line_total
            ];
        }

        // Hitung Diskon
        // Jika diskon > subtotal, set 0
        $final_discount = ($discount_val > $subtotal) ? $subtotal : $discount_val;
        $total = $subtotal - $final_discount;

        wp_send_json_success([
            'subtotal' => $subtotal,
            'discount' => $final_discount,
            'total'    => $total,
            'items'    => $calculated_items
        ]);
    }

    /**
     * 3. Handle Simpan Order
     */
    public static function handle_create_order() {
        check_ajax_referer('dw_nonce_action', 'security');
        
        // Cek permission: pedagang atau admin
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Anda tidak memiliki izin membuat pesanan.']);
        }

        global $wpdb;
        $table_transaksi = $wpdb->prefix . 'dw_transaksi'; // Pastikan tabel ini ada sesuai skema

        // Sanitasi Input
        $items_raw = isset($_POST['items']) ? $_POST['items'] : [];
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : 'Umum';
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'cash';
        $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
        
        if (empty($items_raw)) {
            wp_send_json_error(['message' => 'Keranjang kosong.']);
        }

        // Kalkulasi Ulang (Double Check)
        $subtotal = 0;
        $order_items = [];
        
        foreach ($items_raw as $item) {
            $pid = intval($item['id']);
            $qty = intval($item['qty']);
            $price = (float) get_post_meta($pid, '_price', true);
            
            $subtotal += ($price * $qty);
            $order_items[] = [
                'product_id' => $pid,
                'product_name' => get_the_title($pid),
                'price' => $price,
                'qty' => $qty,
                'subtotal' => $price * $qty
            ];
            
            // TODO: Kurangi Stok Produk di sini jika perlu
            // $current_stock = get_post_meta($pid, '_stock', true);
            // update_post_meta($pid, '_stock', $current_stock - $qty);
        }

        $total_amount = $subtotal - $discount;
        if ($total_amount < 0) $total_amount = 0;

        // Generate Invoice ID
        $invoice_id = 'INV-' . date('Ymd') . '-' . strtoupper(wp_generate_password(4, false));
        $user_id = get_current_user_id();

        // Data untuk Insert DB
        $data = [
            'invoice_id'      => $invoice_id,
            'user_id'         => $user_id, // Pedagang yang input
            'customer_name'   => $customer_name,
            'total_amount'    => $total_amount,
            'discount_amount' => $discount,
            'payment_method'  => $payment_method,
            'status'          => 'completed', // POS biasanya langsung lunas
            'items_json'      => json_encode($order_items),
            'created_at'      => current_time('mysql'),
        ];

        // Format SQL (%s = string, %d = integer, %f = float)
        $format = ['%s', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s'];

        $inserted = $wpdb->insert($table_transaksi, $data, $format);

        if ($inserted) {
            wp_send_json_success([
                'message' => 'Transaksi berhasil disimpan.',
                'invoice_id' => $invoice_id,
                'order_id' => $wpdb->insert_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Gagal menyimpan transaksi ke database. Error: ' . $wpdb->last_error]);
        }
    }
}

// Inisialisasi Class
DW_POS_Handler::init();