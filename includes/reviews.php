<?php
/**
 * File Name:   reviews.php
 * File Folder: includes/
 * Description: Library lengkap untuk manajemen ulasan (CRUD + Kalkulasi).
 * * UPDATE: 
 * - Integrasi Universal Review (Produk, Wisata, Ojek).
 * - Support `target_type` ('post' vs 'user').
 * - Sinkronisasi rating ke tabel `dw_ojek`.
 */

if (!defined('ABSPATH')) exit;

/* ==========================================================================
   1. CREATE (SUBMIT REVIEW)
   ========================================================================== */

function dw_submit_review_handler($data) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';

    $user_id = get_current_user_id();
    if (!$user_id) return new WP_Error('auth_error', 'Silakan login terlebih dahulu.');

    // Sanitasi & Persiapan Data
    $tipe           = sanitize_text_field($data['tipe']); // 'produk', 'wisata', 'ojek'
    $target_id      = intval($data['target_id']);
    $target_type    = isset($data['target_type']) ? sanitize_text_field($data['target_type']) : 'post'; // Default 'post' untuk backward compatibility
    $rating         = intval($data['rating']);
    $komentar       = sanitize_textarea_field($data['komentar']);
    $transaction_id = isset($data['transaction_id']) ? intval($data['transaction_id']) : null;

    // 1. Validasi Rating
    if ($rating < 1 || $rating > 5) return new WP_Error('invalid_rating', 'Rating harus antara 1-5.');

    // 2. Validasi Berdasarkan Tipe
    if ($tipe === 'produk') {
        // Produk harus dibeli dulu
        if (!dw_check_user_purchased_product($user_id, $target_id)) {
            return new WP_Error('not_purchased', 'Anda harus membeli produk ini dan transaksi selesai sebelum memberi ulasan.');
        }
        $target_type = 'post';
        
    } elseif ($tipe === 'ojek') {
        // Ojek harus punya transaksi valid
        $target_type = 'user'; // Targetnya adalah ID User (Driver)
        
        // Validasi Transaksi Ojek (Opsional tapi direkomendasikan)
        /* $valid_trx = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dw_transaksi WHERE id = %d AND id_pembeli = %d AND status_transaksi = 'selesai'", 
            $transaction_id, $user_id
        ));
        if (!$valid_trx) return new WP_Error('invalid_trx', 'Transaksi tidak valid atau belum selesai.');
        */
    }

    // 3. Cek Duplikasi (Opsional - User review 1x per transaksi untuk ojek, atau 1x per item untuk produk)
    /*
    if ($transaction_id) {
        $exist = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_ulasan WHERE transaction_id=%d AND user_id=%d", $transaction_id, $user_id));
        if ($exist) return new WP_Error('duplicate', 'Anda sudah mengulas transaksi ini.');
    }
    */

    // 4. Insert ke Database
    $inserted = $wpdb->insert($table_ulasan, [
        'tipe'            => $tipe,
        'target_id'       => $target_id,
        'target_type'     => $target_type,
        'user_id'         => $user_id,
        'transaction_id'  => $transaction_id,
        'rating'          => $rating,
        'komentar'        => $komentar,
        'status_moderasi' => 'approved', // Auto-approve untuk UX yang lebih baik (atau ubah ke 'pending' jika butuh moderasi ketat)
        'created_at'      => current_time('mysql')
    ]);

    if ($inserted === false) {
        return new WP_Error('db_error', 'Gagal menyimpan ulasan.');
    }

    // 5. Trigger Rekalkulasi Langsung (Jika Auto-Approve)
    dw_recalculate_rating_stats($target_id, $tipe, $target_type);

    return ['success' => true, 'message' => 'Ulasan berhasil dikirim.', 'id' => $wpdb->insert_id];
}

/* ==========================================================================
   2. READ (GET REVIEWS)
   ========================================================================== */

/**
 * Mengambil daftar ulasan untuk ditampilkan di frontend
 */
function dw_get_reviews($target_id, $tipe = 'produk', $limit = 10, $offset = 0) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';
    $table_users  = $wpdb->prefix . 'users';

    // Tentukan target_type berdasarkan tipe agar query lebih spesifik (index friendly)
    $target_type = ($tipe === 'ojek') ? 'user' : 'post';

    $sql = "SELECT u.*, wp_users.display_name, wp_users.user_email 
            FROM $table_ulasan u
            LEFT JOIN $table_users wp_users ON u.user_id = wp_users.ID
            WHERE u.target_id = %d 
            AND u.tipe = %s 
            AND u.target_type = %s
            AND u.status_moderasi = 'approved'
            ORDER BY u.created_at DESC
            LIMIT %d OFFSET %d";

    $reviews = $wpdb->get_results($wpdb->prepare($sql, $target_id, $tipe, $target_type, $limit, $offset));

    // Format data tambahan
    foreach ($reviews as $r) {
        $r->avatar_url = get_avatar_url($r->user_email);
        $r->human_date = human_time_diff(strtotime($r->created_at), current_time('timestamp')) . ' yang lalu';
    }

    return $reviews;
}

/**
 * Hitung total ulasan
 */
function dw_count_reviews($target_id, $tipe = 'produk') {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';
    $target_type = ($tipe === 'ojek') ? 'user' : 'post';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_ulasan WHERE target_id = %d AND tipe = %s AND target_type = %s AND status_moderasi = 'approved'", 
        $target_id, $tipe, $target_type
    ));
}

/* ==========================================================================
   3. UPDATE (APPROVE / MODERATE)
   ========================================================================== */

function dw_approve_review($review_id) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';

    $updated = $wpdb->update($table_ulasan, ['status_moderasi' => 'approved'], ['id' => $review_id]);

    if ($updated === false) return false;

    // Recalculate
    $review = $wpdb->get_row($wpdb->prepare("SELECT tipe, target_id, target_type FROM $table_ulasan WHERE id = %d", $review_id));
    if ($review) {
        // Fallback target_type jika data lama kosong
        $t_type = !empty($review->target_type) ? $review->target_type : ($review->tipe === 'ojek' ? 'user' : 'post');
        dw_recalculate_rating_stats($review->target_id, $review->tipe, $t_type);
    }
    return true;
}

function dw_reject_review($review_id) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';
    
    $wpdb->update($table_ulasan, ['status_moderasi' => 'rejected'], ['id' => $review_id]);
    
    $review = $wpdb->get_row($wpdb->prepare("SELECT tipe, target_id, target_type FROM $table_ulasan WHERE id = %d", $review_id));
    if ($review) {
        $t_type = !empty($review->target_type) ? $review->target_type : ($review->tipe === 'ojek' ? 'user' : 'post');
        dw_recalculate_rating_stats($review->target_id, $review->tipe, $t_type);
    }
}

/* ==========================================================================
   4. DELETE
   ========================================================================== */

function dw_delete_review($review_id) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';

    $review = $wpdb->get_row($wpdb->prepare("SELECT tipe, target_id, target_type, status_moderasi FROM $table_ulasan WHERE id = %d", $review_id));

    if (!$review) return false;

    $wpdb->delete($table_ulasan, ['id' => $review_id]);

    if ($review->status_moderasi === 'approved') {
        $t_type = !empty($review->target_type) ? $review->target_type : ($review->tipe === 'ojek' ? 'user' : 'post');
        dw_recalculate_rating_stats($review->target_id, $review->tipe, $t_type);
    }

    return true;
}

/* ==========================================================================
   5. CALCULATION LOGIC (INTI SISTEM RATING)
   ========================================================================== */

/**
 * Menghitung ulang rata-rata dan update tabel entitas terkait
 * UPDATE: Support update tabel dw_ojek
 */
function dw_recalculate_rating_stats($target_id, $tipe = 'produk', $target_type = 'post') {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';

    // A. Hitung Statistik
    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ulasan 
         FROM $table_ulasan 
         WHERE target_id = %d AND tipe = %s AND target_type = %s AND status_moderasi = 'approved'",
        $target_id, $tipe, $target_type
    ));

    $avg_rating   = $stats->avg_rating ? number_format((float)$stats->avg_rating, 2, '.', '') : 0;
    $total_ulasan = $stats->total_ulasan ? intval($stats->total_ulasan) : 0;

    // B. Update Tabel Target
    if ($tipe === 'produk') {
        // 1. Update Tabel Produk
        $wpdb->update(
            $wpdb->prefix . 'dw_produk',
            ['rating_avg' => $avg_rating, 'total_ulasan' => $total_ulasan],
            ['id' => $target_id]
        );
        // 2. Update Meta Produk (Untuk kompatibilitas)
        update_post_meta($target_id, 'dw_rating_avg', $avg_rating);

        // 3. Trigger Update Rating Toko (Akumulasi)
        $id_pedagang = $wpdb->get_var($wpdb->prepare("SELECT id_pedagang FROM {$wpdb->prefix}dw_produk WHERE id = %d", $target_id));
        if ($id_pedagang) {
            dw_recalculate_shop_rating($id_pedagang);
        }

    } elseif ($tipe === 'wisata') {
        // Update Tabel Wisata
        $wpdb->update(
            $wpdb->prefix . 'dw_wisata',
            ['rating_avg' => $avg_rating, 'total_ulasan' => $total_ulasan],
            ['id' => $target_id]
        );
        update_post_meta($target_id, 'dw_rating_avg', $avg_rating);

    } elseif ($tipe === 'ojek') {
        // Update Tabel Ojek
        $table_ojek = $wpdb->prefix . 'dw_ojek';
        
        // Cek apakah driver ada di tabel dw_ojek
        $ojek_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_ojek WHERE id_user = %d", $target_id));
        
        if ($ojek_exists) {
            $wpdb->update(
                $table_ojek,
                ['rating_avg' => $avg_rating, 'total_trip' => $total_ulasan], // Asumsi total trip ~ total ulasan (bisa disesuaikan)
                ['id_user' => $target_id]
            );
        }
        
        // Update User Meta (Standar)
        update_user_meta($target_id, 'dw_ojek_rating_avg', $avg_rating);
    }
}

/**
 * Hitung Rata-rata Toko (Dari semua ulasan produknya)
 */
function dw_recalculate_shop_rating($pedagang_id) {
    global $wpdb;
    
    // Strategi: Rata-rata dari SEMUA ulasan produk milik toko ini
    $sql_shop_rating = "
        SELECT AVG(u.rating) as shop_rating, COUNT(u.id) as shop_reviews
        FROM {$wpdb->prefix}dw_ulasan u
        JOIN {$wpdb->prefix}dw_produk p ON u.target_id = p.id
        WHERE p.id_pedagang = %d 
        AND u.tipe = 'produk' 
        AND u.status_moderasi = 'approved'
    ";

    $shop_stats = $wpdb->get_row($wpdb->prepare($sql_shop_rating, $pedagang_id));
    
    $shop_rating = $shop_stats->shop_rating ? number_format((float)$shop_stats->shop_rating, 2, '.', '') : 0;
    $shop_count  = $shop_stats->shop_reviews ? intval($shop_stats->shop_reviews) : 0;

    // Update Tabel Pedagang
    $wpdb->update(
        $wpdb->prefix . 'dw_pedagang',
        [
            'rating_toko'       => $shop_rating,
            'total_ulasan_toko' => $shop_count
        ],
        ['id' => $pedagang_id]
    );
}

/* ==========================================================================
   6. HELPERS
   ========================================================================== */

/**
 * Cek apakah user sudah membeli produk & transaksi selesai
 */
function dw_check_user_purchased_product($user_id, $product_id) {
    global $wpdb;
    // Cek di tabel transaksi items join ke transaksi utama
    $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}dw_transaksi_items ti
            JOIN {$wpdb->prefix}dw_transaksi_sub ts ON ti.id_sub_transaksi = ts.id
            JOIN {$wpdb->prefix}dw_transaksi t ON ts.id_transaksi = t.id
            WHERE t.id_pembeli = %d 
            AND ti.id_produk = %d 
            AND t.status_transaksi = 'selesai'";
    
    $count = $wpdb->get_var($wpdb->prepare($sql, $user_id, $product_id));
    return $count > 0;
}

/* ==========================================================================
   7. AJAX HANDLERS (NEW)
   ========================================================================== */

function dw_ajax_submit_review() {
    check_ajax_referer('dw_nonce', 'nonce'); // Pastikan nonce dikirim dari frontend

    $result = dw_submit_review_handler($_POST);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    } else {
        wp_send_json_success($result);
    }
}
add_action('wp_ajax_dw_submit_review', 'dw_ajax_submit_review');
// Jika ingin user non-login bisa review (tidak disarankan untuk kasus ini), tambahkan nopriv
// add_action('wp_ajax_nopriv_dw_submit_review', 'dw_ajax_submit_review'); 
?>