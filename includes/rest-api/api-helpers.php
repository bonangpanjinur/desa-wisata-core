<?php
/**
 * File Name:   api-helpers.php
 * File Folder: includes/rest-api/
 * File Path:   includes/rest-api/api-helpers.php
 *
 * Berisi fungsi helper KHUSUS untuk memformat dan memproses data REST API.
 *
 * --- UPDATE LENGKAP (v3.3) ---
 * 1. Otentikasi: dw_get_user_id_from_request()
 * 2. Validator: dw_rest_validate_numeric() untuk mencegah error 500.
 * 3. Optimasi: dw_api_format_produk_list() dengan EAGER LOADING (Fix N+1 Query).
 * 4. Fitur CRUD: Fungsi simpan produk & wisata (Save Data) dimuat lengkap.
 * 5. Formatting: Semua helper format gambar, variasi, dan rating.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. HELPER OTENTIKASI & VALIDASI
// =========================================================================

/**
 * Mendapatkan User ID dari header Authorization (Bearer Token).
 */
function dw_get_user_id_from_request( WP_REST_Request $request, $return_error = true ) {
    $auth_header = $request->get_header( 'Authorization' );
    
    if ( empty( $auth_header ) ) {
        return $return_error ? new WP_Error( 'rest_auth_header_missing', 'Header otentikasi (Authorization) tidak ditemukan.', [ 'status' => 401 ] ) : 0;
    }
    
    if ( ! preg_match( '/^Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
        return $return_error ? new WP_Error( 'rest_auth_malformed', 'Format header otentikasi tidak valid. Gunakan "Bearer {token}".', [ 'status' => 401 ] ) : 0;
    }
    
    $token = $matches[1];
    if ( empty( $token ) ) {
        return $return_error ? new WP_Error( 'rest_auth_token_missing', 'Token JWT tidak ditemukan.', [ 'status' => 401 ] ) : 0;
    }
    
    // Pastikan fungsi decode tersedia (dari includes/helpers.php)
    if ( ! function_exists( 'dw_decode_jwt' ) ) {
        return $return_error ? new WP_Error( 'rest_auth_helper_missing', 'Fungsi otentikasi internal tidak ditemukan.', [ 'status' => 500 ] ) : 0;
    }

    $decoded_token = dw_decode_jwt( $token );
    
    if ( is_wp_error( $decoded_token ) ) {
        return $return_error ? $decoded_token : 0;
    }
    
    if ( isset( $decoded_token->data->user_id ) && is_numeric( $decoded_token->data->user_id ) ) {
        return (int) $decoded_token->data->user_id;
    }
    
    return $return_error ? new WP_Error( 'rest_auth_payload_invalid', 'Payload token tidak valid.', [ 'status' => 401 ] ) : 0;
}

/**
 * Validator kustom untuk menggantikan is_numeric() yang kadang menyebabkan error 500
 * pada register_rest_route 'validate_callback'.
 */
function dw_rest_validate_numeric( $value, $request, $param ) {
    return is_numeric( $value );
}


// =========================================================================
// 2. FUNGSI FORMATTER DATA (READ)
// =========================================================================

function dw_api_get_image_urls($attachment_id) {
    if (!$attachment_id) return null;
    return [
        'thumbnail' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
        'medium'    => wp_get_attachment_image_url($attachment_id, 'medium'),
        'large'     => wp_get_attachment_image_url($attachment_id, 'large'),
        'full'      => wp_get_attachment_image_url($attachment_id, 'full'),
    ];
}

function dw_api_get_gallery_urls($ids_string) {
    if (empty($ids_string)) return [];
    $ids = array_filter(array_map('absint', explode(',', $ids_string)));
    $gallery = [];
    foreach ($ids as $id) {
        $urls = dw_api_get_image_urls($id);
        if ($urls) $gallery[] = $urls;
    }
    return $gallery;
}

function dw_api_get_product_variations($post_id) {
    global $wpdb;
    $variations = $wpdb->get_results($wpdb->prepare(
        "SELECT id, deskripsi_variasi, harga_variasi, stok_variasi 
         FROM {$wpdb->prefix}dw_produk_variasi 
         WHERE id_produk = %d",
        $post_id
    ), 'ARRAY_A');
    
    return array_map(function($v) {
        return [
            'id' => (int) $v['id'],
            'deskripsi' => $v['deskripsi_variasi'],
            'harga_variasi' => (float) $v['harga_variasi'],
            'stok' => $v['stok_variasi'] !== null ? (int) $v['stok_variasi'] : null,
        ];
    }, $variations);
}

function dw_api_get_toko_by_author($author_id) {
    if (!$author_id) return null;
    
    $cache_key = "toko_data_{$author_id}";
    $toko_data = wp_cache_get($cache_key, 'dw_api_helpers');
    
    if (false === $toko_data) {
        global $wpdb;
        $toko_data = $wpdb->get_row($wpdb->prepare(
            "SELECT p.id_user as id_pedagang, p.nama_toko, p.id_desa, d.nama_desa 
             FROM {$wpdb->prefix}dw_pedagang p
             LEFT JOIN {$wpdb->prefix}dw_desa d ON p.id_desa = d.id
             WHERE p.id_user = %d",
            $author_id
        ), 'ARRAY_A');
        
        if ($toko_data) {
            $toko_data['id_pedagang'] = (int) $toko_data['id_pedagang'];
            $toko_data['id_desa'] = (int) $toko_data['id_desa'];
        }
        wp_cache_set($cache_key, $toko_data, 'dw_api_helpers', HOUR_IN_SECONDS);
    }
    
    return $toko_data;
}

function dw_api_get_desa_by_id($desa_id) {
    if (!$desa_id) return null;
    
    $cache_key = "desa_data_{$desa_id}";
    $desa_data = wp_cache_get($cache_key, 'dw_api_helpers');
    
    if (false === $desa_data) {
        global $wpdb;
        $desa_data = $wpdb->get_row($wpdb->prepare(
            "SELECT id, nama_desa, foto, kabupaten 
             FROM {$wpdb->prefix}dw_desa WHERE id = %d",
            $desa_id
        ), 'ARRAY_A');
        
        if ($desa_data) {
            $desa_data['id'] = (int) $desa_data['id'];
        }
        wp_cache_set($cache_key, $desa_data, 'dw_api_helpers', HOUR_IN_SECONDS);
    }
    
    return $desa_data;
}

function dw_api_get_average_rating($target_id, $target_type) {
    if (function_exists('dw_get_rating_summary')) {
        $summary = dw_get_rating_summary($target_id, $target_type);
        return [
            'count' => $summary['total_reviews'],
            'average' => $summary['average_rating'],
        ];
    }
    return ['count' => 0, 'average' => 0.0];
}

function dw_api_parse_facilities($meta_value) {
    if (is_array($meta_value)) return array_filter($meta_value);
    if (is_string($meta_value)) return array_filter(array_map('trim', explode("\n", $meta_value)));
    return [];
}

function dw_api_parse_social_media($meta_value) {
    if (is_array($meta_value)) return $meta_value;
     if (is_string($meta_value)) {
        $media_sosial_arr = [];
        $lines = explode("\n", trim($meta_value));
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2 && !empty(trim($parts[0])) && !empty(trim($parts[1]))) {
                $media_sosial_arr[sanitize_key(trim($parts[0]))] = esc_url_raw(trim($parts[1]));
            }
        }
        return $media_sosial_arr;
    }
    return [];
}

/**
 * Format detail lengkap PRODUK (untuk single view/edit).
 */
function dw_internal_format_produk_data($post_or_id) {
    $post = get_post($post_or_id);
    if (!$post || $post->post_type !== 'dw_produk') return null;
    
    $author_id = (int) $post->post_author;
    $toko = dw_api_get_toko_by_author($author_id);
    $variasi = dw_api_get_product_variations($post->ID);

    return [
        'id' => $post->ID,
        'nama_produk' => $post->post_title,
        'slug' => $post->post_name,
        'deskripsi' => $post->post_content,
        'status' => $post->post_status,
        'harga_dasar' => (float) get_post_meta($post->ID, '_dw_harga_dasar', true),
        'stok' => get_post_meta($post->ID, '_dw_stok', true),
        'gambar_unggulan' => dw_api_get_image_urls(get_post_thumbnail_id($post->ID)),
        'galeri_foto' => dw_api_get_gallery_urls(get_post_meta($post->ID, '_dw_galeri_foto', true)),
        'variasi' => $variasi,
        'kategori' => wp_get_post_terms($post->ID, 'kategori_produk', ['fields' => 'slugs']),
        'toko' => $toko,
        'catatan_ongkir' => get_post_meta($post->ID, '_dw_catatan_ongkir', true),
        'shipping_profile' => get_post_meta($post->ID, '_dw_shipping_profile', true),
        'rating' => dw_api_get_average_rating($post->ID, 'produk'), // Tambahan rating di detail
    ];
}

/**
 * Format detail lengkap WISATA (untuk single view/edit).
 */
function dw_internal_format_wisata_data($post_or_id) {
    $post = get_post($post_or_id);
    if (!$post || $post->post_type !== 'dw_wisata') return null;

    $desa_id = (int) get_post_meta($post->ID, '_dw_id_desa', true);
    
    return [
        'id' => $post->ID,
        'nama_wisata' => $post->post_title,
        'slug' => $post->post_name,
        'deskripsi' => $post->post_content,
        'status' => $post->post_status,
        'id_desa' => $desa_id,
        'gambar_unggulan' => dw_api_get_image_urls(get_post_thumbnail_id($post->ID)),
        'galeri_foto' => dw_api_get_gallery_urls(get_post_meta($post->ID, '_dw_galeri_foto', true)),
        'kategori' => wp_get_post_terms($post->ID, 'kategori_wisata', ['fields' => 'slugs']),
        'info' => [
            'harga_tiket' => get_post_meta($post->ID, '_dw_harga_tiket', true),
            'jam_buka' => get_post_meta($post->ID, '_dw_jam_buka', true),
            'hari_buka' => get_post_meta($post->ID, '_dw_hari_buka', true),
            'kontak' => get_post_meta($post->ID, '_dw_kontak', true),
            'fasilitas' => dw_api_parse_facilities(get_post_meta($post->ID, '_dw_fasilitas', true)),
            'atraksi_terdekat' => dw_api_parse_facilities(get_post_meta($post->ID, '_dw_nearby_attractions', true)),
        ],
        'lokasi' => [
            'alamat' => get_post_meta($post->ID, '_dw_alamat', true),
            'koordinat' => get_post_meta($post->ID, '_dw_koordinat', true),
            'url_google_maps' => get_post_meta($post->ID, '_dw_url_google_maps', true),
        ],
        'media' => [
            'url_website' => get_post_meta($post->ID, '_dw_url_website', true),
            'video_url' => get_post_meta($post->ID, '_dw_video_url', true),
            'media_sosial' => dw_api_parse_social_media(get_post_meta($post->ID, '_dw_media_sosial', true)),
        ],
        'rating' => dw_api_get_average_rating($post->ID, 'wisata'),
    ];
}

// =========================================================================
// 3. FUNGSI OPTIMASI LIST (EAGER LOADING)
// =========================================================================

/**
 * Helper untuk memformat satu item produk.
 * Mendukung parameter $preloaded_toko (Opsional) untuk optimasi N+1.
 */
function dw_api_format_produk_list_item($post, $preloaded_toko = null) {
    if (is_int($post)) {
        $post = get_post($post);
    }
    if (!$post || $post->post_type !== 'dw_produk') return null;

    $author_id = (int) $post->post_author;
    
    // OPTIMASI: Gunakan data preloaded jika ada
    if ($preloaded_toko) {
        $toko = $preloaded_toko;
    } else {
        $toko = dw_api_get_toko_by_author($author_id);
    }
    
    return [
        'id' => $post->ID,
        'nama_produk' => $post->post_title,
        'slug' => $post->post_name,
        'harga_dasar' => (float) get_post_meta($post->ID, '_dw_harga_dasar', true),
        'gambar_unggulan' => dw_api_get_image_urls(get_post_thumbnail_id($post->ID)),
        'kategori' => wp_get_post_terms($post->ID, 'kategori_produk', ['fields' => 'slugs']),
        'toko' => $toko,
        'rating' => dw_api_get_average_rating($post->ID, 'produk'),
    ];
}

/**
 * [OPTIMASI UTAMA] Mengambil data Toko sekaligus dalam satu query.
 * Mengurangi query database drastis saat memuat daftar produk.
 */
function dw_api_format_produk_list($posts) {
    if (empty($posts) || !is_array($posts)) {
        return [];
    }

    // 1. Kumpulkan semua ID Pedagang (Author) yang unik
    $author_ids = [];
    foreach ($posts as $post) {
        $pid = is_int($post) ? $post : $post->ID; 
        $p_obj = get_post($pid);
        if ($p_obj) {
            $author_ids[] = $p_obj->post_author;
        }
    }
    $author_ids = array_unique(array_filter($author_ids));

    // 2. Query Data Toko Sekaligus (WHERE IN)
    $toko_map = [];
    if (!empty($author_ids)) {
        global $wpdb;
        $ids_placeholder = implode(',', array_fill(0, count($author_ids), '%d'));
        
        $query = "SELECT p.id_user as id_pedagang, p.nama_toko, p.id_desa, d.nama_desa 
                  FROM {$wpdb->prefix}dw_pedagang p
                  LEFT JOIN {$wpdb->prefix}dw_desa d ON p.id_desa = d.id
                  WHERE p.id_user IN ($ids_placeholder)";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $author_ids), 'ARRAY_A');
        
        if ($results) {
            foreach ($results as $row) {
                // Casting tipe data
                $row['id_pedagang'] = (int) $row['id_pedagang'];
                $row['id_desa'] = (int) $row['id_desa'];
                $toko_map[$row['id_pedagang']] = $row;
            }
        }
    }

    // 3. Format Item dengan menyuntikkan data toko (Dependency Injection)
    $formatted_items = [];
    foreach ($posts as $post) {
        $p_obj = is_int($post) ? get_post($post) : $post;
        if (!$p_obj) continue;

        $toko_data = isset($toko_map[$p_obj->post_author]) ? $toko_map[$p_obj->post_author] : null;
        
        // Panggil formatter item dengan data preloaded
        $item = dw_api_format_produk_list_item($p_obj, $toko_data);
        if ($item) {
            $formatted_items[] = $item;
        }
    }

    return $formatted_items;
}

function dw_api_format_wisata_list_item($post) {
     if (is_int($post)) {
        $post = get_post($post);
    }
    if (!$post || $post->post_type !== 'dw_wisata') return null;
    
    return [
        'id' => $post->ID,
        'nama_wisata' => $post->post_title,
        'slug' => $post->post_name,
        'gambar_unggulan' => dw_api_get_image_urls(get_post_thumbnail_id($post->ID)),
        'kategori' => wp_get_post_terms($post->ID, 'kategori_wisata', ['fields' => 'slugs']),
        'lokasi' => [
            'kabupaten' => get_post_meta($post->ID, '_dw_kabupaten', true),
        ],
        'rating' => dw_api_get_average_rating($post->ID, 'wisata'),
    ];
}

function dw_api_format_wisata_list($posts) {
    if (empty($posts) || !is_array($posts)) {
        return [];
    }
    return array_filter(array_map('dw_api_format_wisata_list_item', $posts));
}


// =========================================================================
// 4. FUNGSI PENYIMPANAN DATA (CREATE/UPDATE) - LENGKAP
// =========================================================================

/**
 * Menyimpan data produk dari API ke Database (Meta & Taxonomies).
 */
function dw_api_save_produk_data($post_id, $params, $user_id) {
    // 1. Simpan Post Meta Standar
    $meta_fields = ['_dw_harga_dasar', '_dw_stok', '_dw_catatan_ongkir', '_dw_shipping_profile'];
    foreach ($meta_fields as $meta_key) {
        $param_key = str_replace('_dw_', '', $meta_key);
        if (isset($params[$param_key])) {
            $value = ($param_key === 'harga_dasar') ? floatval($params[$param_key]) : sanitize_text_field($params[$param_key]);
            update_post_meta($post_id, $meta_key, $value);
        }
    }
    
    // 2. Simpan Galeri Foto
    if (isset($params['galeri_foto']) && is_array($params['galeri_foto'])) {
        $gallery_ids = array_map('absint', $params['galeri_foto']);
        update_post_meta($post_id, '_dw_galeri_foto', implode(',', $gallery_ids));
        // Set gambar pertama sebagai thumbnail jika thumbnail utama tidak ada
        if (!has_post_thumbnail($post_id) && !empty($gallery_ids)) {
            set_post_thumbnail($post_id, $gallery_ids[0]);
        }
    }
    
    // 3. Simpan Kategori (Taxonomy)
    if (isset($params['kategori']) && is_array($params['kategori'])) {
        wp_set_post_terms($post_id, $params['kategori'], 'kategori_produk', false);
    }
    
    // 4. Simpan Variasi Produk (Complex Logic)
    if (isset($params['variasi']) && is_array($params['variasi'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_produk_variasi';
        
        // Ambil variasi eksisting untuk dibandingkan (hapus yang tidak disubmit)
        $existing_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_name WHERE id_produk = %d", $post_id));
        $submitted_ids = [];
        
        foreach ($params['variasi'] as $var) {
            if (empty($var['deskripsi']) || !isset($var['harga_variasi'])) continue;
            
            $var_id = isset($var['id']) ? absint($var['id']) : 0;
            $data = [
                'id_produk' => $post_id,
                'deskripsi_variasi' => sanitize_text_field($var['deskripsi']),
                'harga_variasi' => floatval($var['harga_variasi']),
                'stok_variasi' => isset($var['stok']) ? absint($var['stok']) : null,
            ];
            
            if ($var_id > 0 && in_array($var_id, $existing_ids)) {
                $wpdb->update($table_name, $data, ['id' => $var_id]);
                $submitted_ids[] = $var_id;
            } else {
                $wpdb->insert($table_name, $data);
                $submitted_ids[] = $wpdb->insert_id;
            }
        }
        
        // Hapus variasi lama yang tidak ada di submission baru
        $ids_to_delete = array_diff($existing_ids, $submitted_ids);
        if (!empty($ids_to_delete)) {
            $wpdb->query("DELETE FROM $table_name WHERE id IN (" . implode(',', $ids_to_delete) . ")");
        }
    }
    
    return true;
}

/**
 * Menyimpan data wisata dari API ke Database.
 */
function dw_api_save_wisata_data($post_id, $params, $user_id) {
    // 1. Simpan Meta Data Wisata
    $meta_map = [
        'harga_tiket' => '_dw_harga_tiket',
        'jam_buka' => '_dw_jam_buka',
        'hari_buka' => '_dw_hari_buka',
        'kontak' => '_dw_kontak',
        'alamat' => '_dw_alamat',
        'koordinat' => '_dw_koordinat',
        'url_google_maps' => '_dw_url_google_maps',
        'url_website' => '_dw_url_website',
        'video_url' => '_dw_video_url',
        '_dw_id_desa' => '_dw_id_desa', // ID Desa (Penting!)
    ];
    
    foreach ($meta_map as $param_key => $meta_key) {
        if (isset($params[$param_key])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field($params[$param_key]));
        }
    }
    
    // 2. Simpan Galeri
    if (isset($params['galeri_foto']) && is_array($params['galeri_foto'])) {
        $gallery_ids = array_map('absint', $params['galeri_foto']);
        update_post_meta($post_id, '_dw_galeri_foto', implode(',', $gallery_ids));
        if (!has_post_thumbnail($post_id) && !empty($gallery_ids)) {
            set_post_thumbnail($post_id, $gallery_ids[0]);
        }
    }
    
    // 3. Simpan Kategori
    if (isset($params['kategori']) && is_array($params['kategori'])) {
        wp_set_post_terms($post_id, $params['kategori'], 'kategori_wisata', false);
    }
    
    // 4. Simpan Fasilitas (Array serialized)
    if (isset($params['fasilitas']) && is_array($params['fasilitas'])) {
        $fasilitas = array_filter(array_map('sanitize_text_field', $params['fasilitas']));
        update_post_meta($post_id, '_dw_fasilitas', $fasilitas);
    }
    
    // 5. Simpan Atraksi Terdekat
    if (isset($params['atraksi_terdekat']) && is_array($params['atraksi_terdekat'])) {
        $atraksi = array_filter(array_map('sanitize_text_field', $params['atraksi_terdekat']));
        update_post_meta($post_id, '_dw_nearby_attractions', $atraksi);
    }
    
    // 6. Simpan Media Sosial (Array/Object)
    if (isset($params['media_sosial']) && (is_array($params['media_sosial']) || is_object($params['media_sosial']))) {
        $media_sosial_arr = [];
        foreach ($params['media_sosial'] as $key => $value) {
            $media_sosial_arr[sanitize_key($key)] = esc_url_raw($value);
        }
        update_post_meta($post_id, '_dw_media_sosial', $media_sosial_arr);
    }
    
    return true;
}

/**
 * Helper internal untuk mengambil data Desa tunggal (untuk API Admin).
 *
 * @param WP_REST_Request $request Request object, harus berisi 'id'.
 * @return WP_REST_Response|WP_Error
 */
function dw_api_get_single_desa(WP_REST_Request $request) {
    global $wpdb;
    $id = $request['id'];
    if (empty($id)) {
        return new WP_Error('rest_invalid_id', __('ID Desa tidak disediakan.', 'desa-wisata-core'), ['status' => 400]);
    }
    
    $desa_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dw_desa WHERE id = %d",
        $id
    ), 'ARRAY_A');
    
    if (!$desa_data) {
        return new WP_Error('rest_not_found', __('Desa tidak ditemukan.', 'desa-wisata-core'), ['status' => 404]);
    }
    
    // Format tipe data untuk JSON yang konsisten
    $desa_data['id'] = (int) $desa_data['id'];
    $desa_data['id_user_desa'] = $desa_data['id_user_desa'] ? (int) $desa_data['id_user_desa'] : null;
    $desa_data['persentase_komisi_penjualan'] = (float) $desa_data['persentase_komisi_penjualan'];
    $desa_data['persentase_komisi_tiket'] = isset($desa_data['persentase_komisi_tiket']) ? (float) $desa_data['persentase_komisi_tiket'] : 0;

    return new WP_REST_Response($desa_data, 200);
}
?>