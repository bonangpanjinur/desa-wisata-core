<?php
/**
 * File Name:   reviews.php
 * File Folder: includes/
 * File Path:   includes/reviews.php
 *
 * Mengelola fungsionalitas ulasan dan rating.
 *
 * PERBAIKAN v3.1:
 * - Menyempurnakan `dw_insert_review` dengan validasi dasar.
 * - Mengganti nama tabel dari `dw_review` ke `dw_ulasan`.
 *
 * PERBAIKAN (Error 500 Pengguna):
 * - dw_check_user_purchase(): Memperbaiki query JOIN agar sesuai skema database 3-tabel (transaksi, sub, items).
 * - dw_get_approved_reviews(): Memperbaiki nama tabel 'dw_reviews' -> 'dw_ulasan'
 *
 * @package DesaWisataCore
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Menyimpan ulasan baru ke database.
 *
 * @param array $data Data ulasan ['target_id', 'target_type', 'rating', 'komentar'].
 * @param int $user_id ID pengguna yang mengirim ulasan.
 * @return int|WP_Error ID ulasan baru atau WP_Error jika gagal.
 */
function dw_insert_review($data, $user_id) {
    // Basic validation
    if (empty($data['target_id']) || empty($data['target_type']) || empty($data['rating'])) {
        return new WP_Error('missing_review_data', 'Data target ID, tipe, dan rating wajib diisi.');
    }
    if (!in_array($data['target_type'], ['produk', 'wisata'])) {
        return new WP_Error('invalid_target_type', 'Tipe target ulasan tidak valid.');
    }
    $rating = intval($data['rating']);
    if ($rating < 1 || $rating > 5) {
        return new WP_Error('invalid_rating', 'Rating harus antara 1 dan 5.');
    }

    // Validasi apakah user sudah pernah mengulas target ini sebelumnya
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_ulasan';
    $existing_review = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d AND target_id = %d AND target_type = %s",
        $user_id,
        absint($data['target_id']),
        sanitize_key($data['target_type'])
    ));

    if ($existing_review) {
        return new WP_Error('duplicate_review', 'Anda sudah pernah memberikan ulasan untuk item ini.');
    }


    // TODO: Tambahkan validasi apakah user sudah membeli produk / mengunjungi wisata ini?
    // Ini memerlukan query ke tabel transaksi atau sistem booking wisata (jika ada).
    // Implementasi 'dw_check_user_purchase' di bawah ini masih placeholder.
    if ($data['target_type'] === 'produk') {
        $has_purchased = dw_check_user_purchase($user_id, absint($data['target_id']));
        if (!$has_purchased) {
            return new WP_Error('not_purchased', 'Anda hanya bisa mengulas produk yang sudah dibeli.');
        }
    }
    // TODO: Tambahkan validasi serupa untuk 'wisata' jika ada sistem booking/tiket.


    $insert_data = [
        'user_id'         => $user_id,
        'target_id'       => absint($data['target_id']),
        'target_type'     => sanitize_key($data['target_type']), // 'produk' or 'wisata'
        'rating'          => $rating,
        'komentar'        => isset($data['komentar']) ? wp_kses_post($data['komentar']) : '', // Allow basic HTML
        'status_moderasi' => 'pending', // Default status
        'created_at'      => current_time('mysql', 1), // Use GMT time
    ];

    $inserted = $wpdb->insert($table_name, $insert_data);

    if ($inserted) {
        $new_review_id = $wpdb->insert_id;
        // Trigger action hook jika perlu (misal: notifikasi admin)
        do_action('dw_new_review_submitted', $new_review_id, $insert_data);
        // Hapus cache hitungan ulasan pending
        wp_cache_delete('dw_pending_reviews_count', 'desa_wisata_core');
        return $new_review_id;
    } else {
        error_log("Gagal insert ulasan ke DB: " . $wpdb->last_error); // Log error database
        return new WP_Error('db_insert_error', 'Gagal menyimpan ulasan ke database.');
    }
}

/**
 * (Placeholder -> Semi-Implementasi) Fungsi untuk memeriksa apakah user sudah membeli produk.
 * Implementasi detail tergantung struktur data transaksi Anda.
 * Memeriksa apakah ada transaksi 'selesai' untuk user dan produk tersebut.
 */
function dw_check_user_purchase($user_id, $product_id) {
    // Return true sementara untuk development agar tidak memblokir pengiriman ulasan
    // Ganti 'return true;' dengan kode di bawah ini saat siap diuji/production
    // return true;

    global $wpdb;
    // PERBAIKAN: Query JOIN disesuaikan dengan skema database baru (transaksi -> sub_transaksi -> items)
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(t.id) 
         FROM {$wpdb->prefix}dw_transaksi t
         JOIN {$wpdb->prefix}dw_transaksi_sub ts ON t.id = ts.id_transaksi
         JOIN {$wpdb->prefix}dw_transaksi_items ti ON ts.id = ti.id_sub_transaksi
         WHERE t.id_pembeli = %d 
           AND ti.id_produk = %d 
           AND ts.status_pesanan = 'selesai'", // Cek status di sub-transaksi
        $user_id, $product_id
    ));
    return $count > 0;
}

/**
 * Mengambil ulasan yang disetujui untuk target tertentu.
 *
 * @param int $target_id ID produk atau wisata.
 * @param string $target_type 'produk' atau 'wisata'.
 * @param int $per_page Jumlah per halaman.
 * @param int $page Halaman saat ini.
 * @return array Hasil query ['reviews' => [], 'total' => 0, 'total_pages' => 0, 'average_rating' => 0.0].
 */
function dw_get_approved_reviews($target_id, $target_type, $per_page = 10, $page = 1) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan'; // PERBAIKAN: Nama tabel
    $table_users = $wpdb->users;

    $target_id = absint($target_id);
    $target_type = sanitize_key($target_type);
    $per_page = absint($per_page);
    if ($per_page <= 0) $per_page = 10; // Default jika 0 atau negatif
    $page = absint($page);
    if ($page <= 0) $page = 1; // Default jika 0 atau negatif
    $offset = ($page - 1) * $per_page;

    $where_clause = $wpdb->prepare(
        "WHERE ulasan.target_id = %d AND ulasan.target_type = %s AND ulasan.status_moderasi = 'disetujui'",
        $target_id, $target_type
    );

    // Hitung total ulasan yang disetujui dan rata-rata rating dalam satu query
    $stats = $wpdb->get_row(
        "SELECT COUNT(ulasan.id) as total, AVG(ulasan.rating) as average_rating
         FROM $table_ulasan ulasan $where_clause"
    );

    $total_reviews = $stats ? (int) $stats->total : 0;
    $average_rating = $stats ? round((float) $stats->average_rating, 1) : 0.0; // Bulatkan ke 1 desimal

    // Ambil data ulasan dengan join ke tabel user untuk nama
    $reviews = [];
    if ($total_reviews > 0) {
        $reviews = $wpdb->get_results(
            "SELECT ulasan.id, ulasan.user_id, ulasan.rating, ulasan.komentar, ulasan.created_at, users.display_name
             FROM $table_ulasan ulasan
             LEFT JOIN $table_users users ON ulasan.user_id = users.ID
             $where_clause
             ORDER BY ulasan.created_at DESC
             LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );

        // Format tanggal dan hapus user_id
        if ($reviews) {
            foreach ($reviews as $key => $review) {
                $reviews[$key]['tanggal_formatted'] = date('d M Y', strtotime($review['created_at']));
                // Anonimkan jika perlu atau hapus data sensitif
                 unset($reviews[$key]['user_id']); // Mungkin tidak perlu user_id di frontend publik
            }
        }
    }


    return [
        'reviews'     => $reviews ?: [],
        'total'       => $total_reviews,
        'average_rating' => $average_rating, // Tambahkan rata-rata rating
        'total_pages' => ceil($total_reviews / $per_page),
        'current_page'=> $page,
    ];
}

/**
 * Mendapatkan ringkasan rating untuk sebuah target.
 *
 * @param int $target_id ID produk atau wisata.
 * @param string $target_type 'produk' atau 'wisata'.
 * @return array ['total_reviews' => int, 'average_rating' => float]
 */
function dw_get_rating_summary($target_id, $target_type) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';

    $target_id = absint($target_id);
    $target_type = sanitize_key($target_type);

    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(id) as total_reviews, AVG(rating) as average_rating
         FROM $table_ulasan
         WHERE target_id = %d AND target_type = %s AND status_moderasi = 'disetujui'",
        $target_id, $target_type
    ));

    return [
        'total_reviews' => $stats ? (int) $stats->total_reviews : 0,
        'average_rating' => $stats && $stats->average_rating ? round((float) $stats->average_rating, 1) : 0.0,
    ];
}
?>