<?php
/**
 * File Name:   reviews.php
 * File Folder: includes/
 * Description: Library lengkap untuk manajemen ulasan (CRUD + Kalkulasi).
 * * Functions:
 * 1. dw_submit_review_handler (Create)
 * 2. dw_get_reviews (Read)
 * 3. dw_approve_review (Update Status)
 * 4. dw_delete_review (Delete)
 * 5. dw_recalculate_rating_stats (Logic Kalkulasi Produk/Wisata)
 * 6. dw_recalculate_shop_rating (Logic Kalkulasi Toko)
 * 7. Helpers (Check Purchase, etc)
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

    $tipe      = sanitize_text_field($data['tipe']); // 'produk' atau 'wisata'
    $target_id = intval($data['target_id']);
    $rating    = intval($data['rating']);
    $komentar  = sanitize_textarea_field($data['komentar']);

    // Validasi Rating
    if ($rating < 1 || $rating > 5) return new WP_Error('invalid_rating', 'Rating harus antara 1-5.');

    // Validasi Pembelian (Jika Produk)
    if ($tipe === 'produk') {
        if (!dw_check_user_purchased_product($user_id, $target_id)) {
            return new WP_Error('not_purchased', 'Anda harus membeli produk ini dan transaksi selesai sebelum memberi ulasan.');
        }
    }

    // Cek duplikasi (User hanya boleh review 1x per item?)
    // Opsional: Aktifkan jika ingin membatasi 1 review per user per produk
    /*
    $exist = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_ulasan WHERE user_id=%d AND target_id=%d AND tipe=%s", $user_id, $target_id, $tipe));
    if ($exist) return new WP_Error('duplicate', 'Anda sudah mengulas item ini.');
    */

    // Insert
    $wpdb->insert($table_ulasan, [
        'tipe'      => $tipe,
        'target_id' => $target_id,
        'user_id'   => $user_id,
        'rating'    => $rating,
        'komentar'  => $komentar,
        'status_moderasi' => 'pending', // Default pending agar aman
        'created_at' => current_time('mysql')
    ]);

    return ['success' => true, 'message' => 'Ulasan berhasil dikirim dan menunggu moderasi.'];
}

/* ==========================================================================
   2. READ (GET REVIEWS)
   ========================================================================== */

/**
 * Mengambil daftar ulasan untuk ditampilkan di frontend (Hanya yang disetujui)
 */
function dw_get_reviews($target_id, $tipe = 'produk', $limit = 10, $offset = 0) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';
    $table_users  = $wpdb->prefix . 'users'; // Join ke tabel user WP untuk ambil nama/foto

    // Query join untuk mengambil nama user & foto avatar (jika ada)
    // Asumsi: Kita ambil display_name dari tabel users
    $sql = "SELECT u.*, wp_users.display_name, wp_users.user_email 
            FROM $table_ulasan u
            LEFT JOIN $table_users wp_users ON u.user_id = wp_users.ID
            WHERE u.target_id = %d 
            AND u.tipe = %s 
            AND u.status_moderasi = 'disetujui'
            ORDER BY u.created_at DESC
            LIMIT %d OFFSET %d";

    $reviews = $wpdb->get_results($wpdb->prepare($sql, $target_id, $tipe, $limit, $offset));

    // Format data tambahan (misal Gravatar)
    foreach ($reviews as $r) {
        $r->avatar_url = get_avatar_url($r->user_email);
        $r->human_date = human_time_diff(strtotime($r->created_at), current_time('timestamp')) . ' yang lalu';
    }

    return $reviews;
}

/**
 * Hitung total ulasan (untuk pagination)
 */
function dw_count_reviews($target_id, $tipe = 'produk') {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';
    return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_ulasan WHERE target_id = %d AND tipe = %s AND status_moderasi = 'disetujui'", $target_id, $tipe));
}

/* ==========================================================================
   3. UPDATE (APPROVE / MODERATE)
   ========================================================================== */

function dw_approve_review($review_id) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';

    // 1. Update Status
    $updated = $wpdb->update($table_ulasan, ['status_moderasi' => 'disetujui'], ['id' => $review_id]);

    if ($updated === false) return false;

    // 2. Ambil Info Review untuk Recalculate
    $review = $wpdb->get_row($wpdb->prepare("SELECT tipe, target_id FROM $table_ulasan WHERE id = %d", $review_id));
    
    if ($review) {
        dw_recalculate_rating_stats($review->target_id, $review->tipe);
    }
    return true;
}

function dw_reject_review($review_id) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';
    
    // Update jadi ditolak
    $wpdb->update($table_ulasan, ['status_moderasi' => 'ditolak'], ['id' => $review_id]);
    
    // Perlu recalculate? Bisa jadi, jika sebelumnya 'disetujui' lalu diubah ke 'ditolak'
    $review = $wpdb->get_row($wpdb->prepare("SELECT tipe, target_id FROM $table_ulasan WHERE id = %d", $review_id));
    if ($review) {
        dw_recalculate_rating_stats($review->target_id, $review->tipe);
    }
}

/* ==========================================================================
   4. DELETE (HAPUS)
   ========================================================================== */

function dw_delete_review($review_id) {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';

    // Ambil info sebelum hapus (untuk recalc)
    $review = $wpdb->get_row($wpdb->prepare("SELECT tipe, target_id, status_moderasi FROM $table_ulasan WHERE id = %d", $review_id));

    if (!$review) return false;

    // Hapus
    $wpdb->delete($table_ulasan, ['id' => $review_id]);

    // Recalculate hanya jika review yang dihapus statusnya 'disetujui'
    // Karena review pending/ditolak tidak mempengaruhi nilai rata-rata
    if ($review->status_moderasi === 'disetujui') {
        dw_recalculate_rating_stats($review->target_id, $review->tipe);
    }

    return true;
}

/* ==========================================================================
   5. CALCULATION LOGIC (INTI SISTEM RATING)
   ========================================================================== */

/**
 * Menghitung ulang rata-rata produk/wisata & Trigger update toko
 */
function dw_recalculate_rating_stats($target_id, $tipe = 'produk') {
    global $wpdb;
    $table_ulasan = $wpdb->prefix . 'dw_ulasan';

    // A. Hitung Rata-rata & Total untuk Target (Produk/Wisata)
    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ulasan 
         FROM $table_ulasan 
         WHERE target_id = %d AND tipe = %s AND status_moderasi = 'disetujui'",
        $target_id, $tipe
    ));

    $avg_rating   = $stats->avg_rating ? number_format((float)$stats->avg_rating, 2, '.', '') : 0;
    $total_ulasan = $stats->total_ulasan ? intval($stats->total_ulasan) : 0;

    // B. Update Tabel Target
    if ($tipe === 'produk') {
        $wpdb->update(
            $wpdb->prefix . 'dw_produk',
            ['rating_avg' => $avg_rating, 'total_ulasan' => $total_ulasan],
            ['id' => $target_id]
        );

        // --- C. TRIGGGER UPDATE TOKO (AKUMULASI) ---
        // Cari ID Pedagang dari produk ini
        $id_pedagang = $wpdb->get_var($wpdb->prepare("SELECT id_pedagang FROM {$wpdb->prefix}dw_produk WHERE id = %d", $target_id));
        
        if ($id_pedagang) {
            dw_recalculate_shop_rating($id_pedagang);
        }

    } elseif ($tipe === 'wisata') {
        $wpdb->update(
            $wpdb->prefix . 'dw_wisata',
            ['rating_avg' => $avg_rating, 'total_ulasan' => $total_ulasan],
            ['id' => $target_id]
        );
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
        AND u.status_moderasi = 'disetujui'
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
    // Pastikan status transaksi = 'selesai'
    $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}dw_transaksi_items ti
            JOIN {$wpdb->prefix}dw_transaksi_sub ts ON ti.id_sub_transaksi = ts.id
            JOIN {$wpdb->prefix}dw_transaksi t ON ts.id_transaksi = t.id
            WHERE t.id_pembeli = %d 
            AND ti.id_produk = %d 
            AND t.status_transaksi = 'selesai'";
    
    $count = $wpdb->get_var($wpdb->prepare($sql, $user_id, $product_id));
    return $count > 0;
}
?>