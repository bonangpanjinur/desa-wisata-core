<?php
/**
 * File Name:   api-public.php
 * File Folder: includes/rest-api/
 * File Path:   includes/rest-api/api-public.php
 *
 * Mendaftarkan semua endpoint API publik (tidak perlu login).
 *
 * PERBAIKAN 500 ERROR:
 * - Mengganti semua 'validate_callback' => 'is_numeric'
 * - menjadi 'validate_callback' => 'dw_rest_validate_numeric'
 * - Ini untuk memperbaiki error "is_numeric() expects exactly 1 parameter, 3 given".
 *
 * PERBAIKAN (Error 500 Pengguna):
 * - dw_api_get_banners(): Memperbaiki query SQL. Menghapus 'tipe' dan mengganti 'urutan' dengan 'prioritas'.
 * - dw_api_get_reviews(): Memperbaiki nama tabel 'dw_reviews' -> 'dw_ulasan' dan 'status' -> 'status_moderasi'.
 * - dw_api_get_public_settings(): Memperbaiki logika pengambilan options dari array 'dw_settings'.
 *
 * PERBAIKAN (ANALISIS API):
 * - Menambahkan transient cache pada endpoint publik yang statis:
 * - dw_api_get_banners
 * - dw_api_get_kategori_produk
 * - dw_api_get_kategori_wisata
 * - dw_api_get_public_settings
 *
 * PERBAIKAN (404 NOT FOUND):
 * - Menghapus `add_action('rest_api_init', ...)` di akhir file.
 * - Fungsi pendaftaran sekarang dipanggil langsung oleh `rest-api.php`.
 *
 * PERBAIKAN (FATAL ERROR v3.2.5):
 * - Mengganti pemanggilan fungsi detail formatter yang hilang/direname:
 * - `dw_api_format_produk_detail` -> `dw_internal_format_produk_data`
 * - `dw_api_format_wisata_detail` -> `dw_internal_format_wisata_data`
 *
 * --- PERUBAHAN (RELASI ALAMAT) ---
 * - `dw_api_get_desa_detail_by_id`:
 * - Mengambil `api_..._id` dari tabel `dw_desa`.
 * - Memperbarui query pedagang agar mencari berdasarkan `id_desa` YANG COCOK
 * ATAU (OR) berdasarkan `api_..._id` YANG COCOK jika `id_desa` pedagang `NULL`.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mendaftarkan semua endpoint API publik.
 */
function dw_api_register_public_routes() {
    $namespace = 'dw/v1';

    // Endpoint untuk Banner
    register_rest_route( $namespace, '/banner', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_banners',
        'permission_callback' => '__return_true',
    ] );

    // Endpoint untuk Kategori Produk
    register_rest_route( $namespace, '/kategori/produk', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_kategori_produk',
        'permission_callback' => '__return_true',
    ] );

    // Endpoint untuk Kategori Wisata
    register_rest_route( $namespace, '/kategori/wisata', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_kategori_wisata',
        'permission_callback' => '__return_true',
    ] );

    // Endpoint daftar desa
    register_rest_route( $namespace, '/desa', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_desa',
        'permission_callback' => '__return_true',
    ] );

    // Endpoint detail desa
    register_rest_route( $namespace, '/desa/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_desa_detail_by_id',
        'permission_callback' => '__return_true',
        'args'     => [
            'id' => [
                'validate_callback' => 'dw_rest_validate_numeric', // PERBAIKAN DI SINI
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );

    // Endpoint detail toko
    register_rest_route( $namespace, '/toko/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_toko_detail_by_id',
        'permission_callback' => '__return_true',
        'args'     => [
            'id' => [
                'validate_callback' => 'dw_rest_validate_numeric', // PERBAIKAN DI SINI
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );

    // Endpoint daftar produk
    register_rest_route( $namespace, '/produk', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_produk',
        'permission_callback' => '__return_true',
    ] );

    // Endpoint detail produk
    register_rest_route( $namespace, '/produk/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_produk_detail_by_id',
        'permission_callback' => '__return_true',
        'args'     => [
            'id' => [
                'validate_callback' => 'dw_rest_validate_numeric', // PERBAIKAN DI SINI
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );

    // Endpoint slug produk
    register_rest_route( $namespace, '/produk/slug/(?P<slug>[a-zA-Z0-9-]+)', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_produk_detail_by_slug',
        'permission_callback' => '__return_true',
        'args'     => [
            'slug' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param);
                },
                'sanitize_callback' => 'sanitize_key',
            ],
        ],
    ] );

    // Endpoint daftar wisata
    register_rest_route( $namespace, '/wisata', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_wisata',
        'permission_callback' => '__return_true',
    ] );

    // Endpoint slug wisata
    register_rest_route( $namespace, '/wisata/slug/(?P<slug>[a-zA-Z0-9-]+)', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_wisata_detail_by_slug',
        'permission_callback' => '__return_true',
        'args'     => [
            'slug' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param);
                },
                'sanitize_callback' => 'sanitize_key',
            ],
        ],
    ] );

    // Endpoint Ulasan
    register_rest_route( $namespace, '/reviews/(?P<target_type>\w+)/(?P<target_id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_reviews',
        'permission_callback' => '__return_true',
        'args'     => [
            'target_id' => [
                'validate_callback' => 'dw_rest_validate_numeric', // PERBAIKAN DI SINI
                'sanitize_callback' => 'absint',
            ],
            'target_type' => [
                'validate_callback' => function($param, $request, $key) {
                    return in_array($param, ['produk', 'wisata']);
                },
            ],
        ],
    ] );

    // Endpoint Pengaturan Publik
    register_rest_route( $namespace, '/settings', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_public_settings',
        'permission_callback' => '__return_true',
    ] );

    // Endpoint Alamat - Provinsi
    register_rest_route( $namespace, '/alamat/provinsi', [
        'methods'  => 'GET',
        'callback' => 'dw_api_get_alamat_provinsi',
        'permission_callback' => '__return_true',
    ] );

    // Endpoint Alamat - Kabupaten by Provinsi ID
    register_rest_route( $namespace, '/alamat/provinsi/(?P<id>[\d.]+)/kabupaten', [ // PERBAIKAN: Izinkan titik
        'methods'  => 'GET',
        'callback' => 'dw_api_get_alamat_kabupaten',
        'permission_callback' => '__return_true',
        'args'     => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param) && preg_match('/^[\d.]+$/', $param); // Validasi ID wilayah
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );

    // Endpoint Alamat - Kecamatan by Kabupaten ID
    register_rest_route( $namespace, '/alamat/kabupaten/(?P<id>[\d.]+)/kecamatan', [ // PERBAIKAN: Izinkan titik
        'methods'  => 'GET',
        'callback' => 'dw_api_get_alamat_kecamatan',
        'permission_callback' => '__return_true',
        'args'     => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param) && preg_match('/^[\d.]+$/', $param);
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );

    // Endpoint Alamat - Kelurahan by Kecamatan ID
    register_rest_route( $namespace, '/alamat/kecamatan/(?P<id>[\d.]+)/kelurahan', [ // PERBAIKAN: Izinkan titik
        'methods'  => 'GET',
        'callback' => 'dw_api_get_alamat_kelurahan',
        'permission_callback' => '__return_true',
        'args'     => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param) && preg_match('/^[\d.]+$/', $param);
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );

    // Endpoint Opsi Pengiriman
    register_rest_route( $namespace, '/shipping-options', [
        'methods'  => 'POST',
        'callback' => 'dw_api_get_shipping_options',
        'permission_callback' => '__return_true', // Bisa diakses publik/guest
    ] );

}
// add_action( 'rest_api_init', 'dw_api_register_public_routes' ); // <-- PERBAIKAN 404: Dihapus

/**
 * Mengambil daftar banner.
 * PERBAIKAN: Query SQL diperbaiki sesuai skema tabel `dw_banner`.
 */
function dw_api_get_banners() {
    // --- PERBAIKAN PERFORMA (Sesuai Analisis Poin 3.2.2) ---
    $cache_key = 'dw_api_banners_cache';
    $cached_data = get_transient($cache_key);
    if (false !== $cached_data) {
        return new WP_REST_Response($cached_data, 200);
    }
    // --- AKHIR PERBAIKAN ---

    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_banner';
    // PERBAIKAN: Menghapus kolom 'tipe' dan mengganti 'urutan' dengan 'prioritas'.
    $results = $wpdb->get_results("SELECT judul, gambar, link FROM $table_name WHERE status = 'aktif' ORDER BY prioritas ASC, id DESC");
    
    set_transient($cache_key, $results, HOUR_IN_SECONDS); // Simpan cache selama 1 jam
    
    return new WP_REST_Response($results, 200);
}

/**
 * Mengambil kategori produk.
 */
function dw_api_get_kategori_produk() {
    // --- PERBAIKAN PERFORMA (Sesuai Analisis Poin 3.2.2) ---
    $cache_key = 'dw_api_kategori_produk_cache';
    $cached_data = get_transient($cache_key);
    if (false !== $cached_data) {
        return new WP_REST_Response($cached_data, 200);
    }
    // --- AKHIR PERBAIKAN ---

    $terms = get_terms([
        'taxonomy' => 'kategori_produk',
        'hide_empty' => false,
    ]);
    if (is_wp_error($terms)) {
        return new WP_REST_Response(['message' => $terms->get_error_message()], 500);
    }
    $formatted_terms = array_map(function($term) {
        return [
            'id' => $term->term_id,
            'nama' => $term->name,
            'slug' => $term->slug,
        ];
    }, $terms);
    
    set_transient($cache_key, $formatted_terms, HOUR_IN_SECONDS * 6); // Cache 6 jam

    return new WP_REST_Response($formatted_terms, 200);
}

/**
 * Mengambil kategori wisata.
 */
function dw_api_get_kategori_wisata() {
    // --- PERBAIKAN PERFORMA (Sesuai Analisis Poin 3.2.2) ---
    $cache_key = 'dw_api_kategori_wisata_cache';
    $cached_data = get_transient($cache_key);
    if (false !== $cached_data) {
        return new WP_REST_Response($cached_data, 200);
    }
    // --- AKHIR PERBAIKAN ---

    $terms = get_terms([
        'taxonomy' => 'kategori_wisata',
        'hide_empty' => false,
    ]);
    if (is_wp_error($terms)) {
        return new WP_REST_Response(['message' => $terms->get_error_message()], 500);
    }
    $formatted_terms = array_map(function($term) {
        return [
            'id' => $term->term_id,
            'nama' => $term->name,
            'slug' => $term->slug,
        ];
    }, $terms);
    
    set_transient($cache_key, $formatted_terms, HOUR_IN_SECONDS * 6); // Cache 6 jam

    return new WP_REST_Response($formatted_terms, 200);
}

/**
 * Mengambil daftar desa (publik).
 */
function dw_api_get_desa(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_desa';

    // Ambil parameter
    $params = $request->get_params();
    $per_page = isset($params['per_page']) ? absint($params['per_page']) : 10;
    $page = isset($params['page']) ? absint($params['page']) : 1;
    $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
    $offset = ($page - 1) * $per_page;

    // Buat query
    $where_clauses = ["status = 'aktif'"];
    $query_params = [];

    if (!empty($search)) {
        $where_clauses[] = "(nama_desa LIKE %s OR kabupaten LIKE %s OR provinsi LIKE %s)";
        $like_search = '%' . $wpdb->esc_like($search) . '%';
        $query_params[] = $like_search;
        $query_params[] = $like_search;
        $query_params[] = $like_search;
    }
    
    // [BARU] Filter by Provinsi ID
    if (!empty($params['provinsi_id'])) {
        $where_clauses[] = "api_provinsi_id = %s"; // Menggunakan kolom api_provinsi_id
        $query_params[] = sanitize_text_field($params['provinsi_id']);
    }

    $where_sql = "WHERE " . implode(' AND ', $where_clauses);

    // Query untuk total
    $total_query = "SELECT COUNT(id) FROM $table_name $where_sql";
    if (!empty($query_params)) {
        $total_desa = $wpdb->get_var($wpdb->prepare($total_query, $query_params));
    } else {
        $total_desa = $wpdb->get_var($total_query);
    }

    // Query untuk data
    $data_query = "SELECT id, nama_desa, deskripsi, foto, kecamatan, kabupaten, provinsi, api_provinsi_id FROM $table_name $where_sql ORDER BY nama_desa ASC LIMIT %d OFFSET %d";
    $query_params_with_limit = $query_params;
    $query_params_with_limit[] = $per_page;
    $query_params_with_limit[] = $offset;

    $desa_list = $wpdb->get_results($wpdb->prepare($data_query, $query_params_with_limit), 'ARRAY_A');
   
    // Format data
    $formatted_data = array_map(function($desa) {
        return [
            'id' => (int) $desa['id'],
            'nama_desa' => $desa['nama_desa'],
            'deskripsi' => $desa['deskripsi'],
            'foto' => $desa['foto'],
            'kecamatan' => $desa['kecamatan'],
            'kabupaten' => $desa['kabupaten'],
            'provinsi' => $desa['provinsi'],
            'id_provinsi' => $desa['api_provinsi_id'], // Menggunakan api_provinsi_id
        ];
    }, $desa_list);

    $total_pages = ceil($total_desa / $per_page);

    $response = [
        'data' => $formatted_data,
        'total' => (int) $total_desa,
        'total_pages' => (int) $total_pages,
        'current_page' => (int) $page,
    ];

    return new WP_REST_Response($response, 200);
}

/**
 * Mengambil detail desa berdasarkan ID.
 * --- PERUBAHAN (RELASI ALAMAT) ---
 */
function dw_api_get_desa_detail_by_id(WP_REST_Request $request) {
    global $wpdb;
    $id = $request['id'];
    $desa_table = $wpdb->prefix . 'dw_desa';

    // 1. Ambil data desa (TAMBAHKAN KOLOM API)
    $desa = $wpdb->get_row($wpdb->prepare(
        "SELECT id, nama_desa, deskripsi, foto, kelurahan, kecamatan, kabupaten, provinsi,
                api_provinsi_id, api_kabupaten_id, api_kecamatan_id, api_kelurahan_id 
         FROM $desa_table WHERE id = %d AND status = 'aktif'",
        $id
    ), 'ARRAY_A');

    if (!$desa) {
        return new WP_REST_Response(['message' => 'Desa tidak ditemukan.'], 404);
    }

    // Format data desa
    $formatted_desa = [
        'id' => (int) $desa['id'],
        'nama_desa' => $desa['nama_desa'],
        'deskripsi' => $desa['deskripsi'],
        'foto' => $desa['foto'],
        'kelurahan' => $desa['kelurahan'],
        'kecamatan' => $desa['kecamatan'],
        'kabupaten' => $desa['kabupaten'],
        'provinsi' => $desa['provinsi'],
    ];
    
    // 2. Ambil ID Pedagang (QUERY BARU SESUAI RENCANA)
    $pedagang_table = $wpdb->prefix . 'dw_pedagang';
    $pedagang_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id_user 
         FROM $pedagang_table
         WHERE status_akun = 'aktif'
         AND (
             -- 1. Pedagang yang sudah ter-link
             id_desa = %d 
             OR 
             -- 2. Pedagang yang belum ter-link TAPI alamatnya cocok
             (
                 id_desa IS NULL
                 AND api_kelurahan_id = %s
                 AND api_kecamatan_id = %s
                 AND api_kabupaten_id = %s
             )
         )",
         $id, // Untuk id_desa
         $desa['api_kelurahan_id'], // Untuk api_kelurahan_id
         $desa['api_kecamatan_id'], // Untuk api_kecamatan_id
         $desa['api_kabupaten_id']  // Untuk api_kabupaten_id
    ));

    $produk_list = [];
    $total_produk = 0;

    if (!empty($pedagang_ids)) {
        // 3. Ambil produk dari semua pedagang di desa ini
        $args_produk = [
            'post_type' => 'dw_produk',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'author__in' => $pedagang_ids, // Ambil produk berdasarkan author (pedagang)
        ];
        $produk_query = new WP_Query($args_produk);
        
        // --- PERBAIKAN: Gunakan Helper Baru ---
        $produk_list = dw_api_format_produk_list($produk_query->posts);
        // --- AKHIR PERBAIKAN ---
        
        $total_produk = (int) $produk_query->found_posts;
    }


    // 4. Ambil wisata dari desa ini
    $args_wisata = [
        'post_type' => 'dw_wisata',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'meta_query' => [
            [
                'key' => '_dw_id_desa',
                'value' => $id,
                'compare' => '='
            ]
        ]
    ];
    $wisata_query = new WP_Query($args_wisata);
    $wisata_list = dw_api_format_wisata_list($wisata_query->posts);


    $response = [
        'desa' => $formatted_desa,
        'produk' => [
            'data' => $produk_list,
            'total' => $total_produk,
        ],
        'wisata' => [
            'data' => $wisata_list,
            'total' => (int) $wisata_query->found_posts,
        ],
    ];

    return new WP_REST_Response($response, 200);
}


/**
 * Mengambil detail toko berdasarkan ID Pedagang (User ID).
 */
function dw_api_get_toko_detail_by_id(WP_REST_Request $request) {
    global $wpdb;
    $id = $request['id']; // Ini adalah ID User Pedagang
    $toko_table = $wpdb->prefix . 'dw_pedagang';
    $desa_table = $wpdb->prefix . 'dw_desa';

    // 1. Ambil data toko
    $toko = $wpdb->get_row($wpdb->prepare(
        "SELECT p.id_user, p.nama_toko, p.deskripsi_toko, p.id_desa, d.nama_desa 
         FROM $toko_table p
         LEFT JOIN $desa_table d ON p.id_desa = d.id
         WHERE p.id_user = %d AND p.status_akun = 'aktif'",
        $id
    ), 'ARRAY_A');

    if (!$toko) {
        return new WP_REST_Response(['message' => 'Toko tidak ditemukan.'], 404);
    }

    $formatted_toko = [
        'id' => (int) $toko['id_user'], // [PERBAIKAN] Menggunakan id_user sebagai ID toko di frontend
        'nama_toko' => $toko['nama_toko'],
        'deskripsi_toko' => $toko['deskripsi_toko'],
        'id_desa' => (int) $toko['id_desa'],
        'nama_desa' => $toko['nama_desa'],
        // TODO: Tambah logo, banner, dll
    ];

    // 2. Ambil produk dari toko ini
    $args_produk = [
        'post_type' => 'dw_produk',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'author' => $id, // Cari berdasarkan post_author
    ];
    $produk_query = new WP_Query($args_produk);
    
    // --- PERBAIKAN: Gunakan Helper Baru ---
    $produk_list = dw_api_format_produk_list($produk_query->posts);
    // --- AKHIR PERBAIKAN ---

    $response = [
        'toko' => $formatted_toko,
        'produk' => [
            'data' => $produk_list,
            'total' => (int) $produk_query->found_posts,
        ],
    ];

    return new WP_REST_Response($response, 200);
}

/**
 * Mengambil daftar produk (publik).
 */
function dw_api_get_produk(WP_REST_Request $request) {
    $params = $request->get_params();
    
    $args = [
        'post_type' => 'dw_produk',
        'post_status' => 'publish',
        'posts_per_page' => isset($params['per_page']) ? absint($params['per_page']) : 10,
        'paged' => isset($params['page']) ? absint($params['page']) : 1,
        's' => isset($params['search']) ? sanitize_text_field($params['search']) : '',
    ];

    // Filter by Kategori
    if (!empty($params['kategori'])) {
        $args['tax_query'][] = [
            'taxonomy' => 'kategori_produk',
            'field' => 'slug',
            'terms' => sanitize_text_field($params['kategori']),
        ];
    }
    
    // Filter by Pedagang/Toko ID (author)
    if (!empty($params['toko'])) {
        $args['author'] = absint($params['toko']);
    }

    // Filter by Desa ID (Memerlukan join atau query terpisah)
    if (!empty($params['desa'])) {
        global $wpdb;
        
        // --- PERUBAHAN (RELASI ALAMAT) ---
        // 1. Ambil data alamat desa dulu
        $desa_table = $wpdb->prefix . 'dw_desa';
        $desa_alamat = $wpdb->get_row($wpdb->prepare(
            "SELECT api_kelurahan_id, api_kecamatan_id, api_kabupaten_id 
             FROM $desa_table WHERE id = %d", 
             absint($params['desa'])
        ));
        
        // 2. Query pedagang yang cocok (via id_desa ATAU via alamat)
        $pedagang_table = $wpdb->prefix . 'dw_pedagang';
        $sql_pedagang = "SELECT id_user FROM $pedagang_table WHERE status_akun = 'aktif' AND (id_desa = %d";
        $sql_params = [absint($params['desa'])];

        if ($desa_alamat && !empty($desa_alamat->api_kelurahan_id)) {
            $sql_pedagang .= " OR (id_desa IS NULL AND api_kelurahan_id = %s AND api_kecamatan_id = %s AND api_kabupaten_id = %s)";
            $sql_params[] = $desa_alamat->api_kelurahan_id;
            $sql_params[] = $desa_alamat->api_kecamatan_id;
            $sql_params[] = $desa_alamat->api_kabupaten_id;
        }
        $sql_pedagang .= ")";
        
        $pedagang_ids = $wpdb->get_col($wpdb->prepare($sql_pedagang, $sql_params));
        // --- AKHIR PERUBAHAN ---
        
        if (!empty($pedagang_ids)) {
            $args['author__in'] = $pedagang_ids;
        } else {
            $args['author__in'] = [0]; // Tidak ada pedagang, jangan tampilkan produk
        }
    }


    // TODO: Add sorting

    $query = new WP_Query($args);
    
    // --- PERBAIKAN: Gunakan Helper Baru ---
    $produk_list = dw_api_format_produk_list($query->posts);
    // --- AKHIR PERBAIKAN ---

    $response = [
        'data' => $produk_list,
        'total' => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'current_page' => (int) $args['paged'],
    ];

    return new WP_REST_Response($response, 200);
}

/**
 * Mengambil detail produk berdasarkan ID.
 */
function dw_api_get_produk_detail_by_id(WP_REST_Request $request) {
    $id = $request['id'];
    $post = get_post($id);

    if (!$post || $post->post_type !== 'dw_produk' || $post->post_status !== 'publish') {
        return new WP_REST_Response(['message' => 'Produk tidak ditemukan.'], 404);
    }

    // --- PERBAIKAN: Ganti nama fungsi ---
    $formatted_produk = dw_internal_format_produk_data($post);
    // --- AKHIR PERBAIKAN ---
    return new WP_REST_Response($formatted_produk, 200);
}

/**
 * Mengambil detail produk berdasarkan slug.
 */
function dw_api_get_produk_detail_by_slug(WP_REST_Request $request) {
    $slug = $request['slug'];
    $args = [
        'name' => $slug,
        'post_type' => 'dw_produk',
        'post_status' => 'publish',
        'posts_per_page' => 1
    ];
    $posts = get_posts($args);

    if (empty($posts)) {
        return new WP_REST_Response(['message' => 'Produk tidak ditemukan.'], 404);
    }

    // --- PERBAIKAN: Ganti nama fungsi ---
    $formatted_produk = dw_internal_format_produk_data($posts[0]);
    // --- AKHIR PERBAIKAN ---
    return new WP_REST_Response($formatted_produk, 200);
}


/**
 * Mengambil daftar wisata (publik).
 */
function dw_api_get_wisata(WP_REST_Request $request) {
    $params = $request->get_params();
    
    $args = [
        'post_type' => 'dw_wisata',
        'post_status' => 'publish',
        'posts_per_page' => isset($params['per_page']) ? absint($params['per_page']) : 10,
        'paged' => isset($params['page']) ? absint($params['page']) : 1,
        's' => isset($params['search']) ? sanitize_text_field($params['search']) : '',
    ];

    // Filter by Kategori
    if (!empty($params['kategori'])) {
        $args['tax_query'][] = [
            'taxonomy' => 'kategori_wisata',
            'field' => 'slug',
            'terms' => sanitize_text_field($params['kategori']),
        ];
    }

    // Filter by Desa ID
    if (!empty($params['desa'])) {
        $args['meta_query'][] = [
            'key' => '_dw_id_desa',
            'value' => absint($params['desa']),
            'compare' => '='
        ];
    }
    
    $query = new WP_Query($args);
    $wisata_list = dw_api_format_wisata_list($query->posts);

    $response = [
        'data' => $wisata_list,
        'total' => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'current_page' => (int) $args['paged'],
    ];

    return new WP_REST_Response($response, 200);
}


/**
 * Mengambil detail wisata berdasarkan slug.
 */
function dw_api_get_wisata_detail_by_slug(WP_REST_Request $request) {
    $slug = $request['slug'];
    $args = [
        'name' => $slug,
        'post_type' => 'dw_wisata',
        'post_status' => 'publish',
        'posts_per_page' => 1
    ];
    $posts = get_posts($args);

    if (empty($posts)) {
        return new WP_REST_Response(['message' => 'Wisata tidak ditemukan.'], 404);
    }

    // --- PERBAIKAN: Ganti nama fungsi ---
    $formatted_wisata = dw_internal_format_wisata_data($posts[0]);
    // --- AKHIR PERBAIKAN ---
    return new WP_REST_Response($formatted_wisata, 200);
}

/**
 * Mengambil ulasan untuk produk atau wisata.
 */
function dw_api_get_reviews(WP_REST_Request $request) {
    global $wpdb;
    $target_id = $request['target_id'];
    $target_type = $request['target_type']; // 'produk' or 'wisata'
    
    $params = $request->get_params();
    $per_page = isset($params['per_page']) ? absint($params['per_page']) : 5;
    $page = isset($params['page']) ? absint($params['page']) : 1;
    $offset = ($page - 1) * $per_page;

    $table_name = $wpdb->prefix . 'dw_ulasan'; // PERBAIKAN: Nama tabel yang benar

    // Query total
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM $table_name WHERE target_id = %d AND target_type = %s AND status_moderasi = 'disetujui'", // PERBAIKAN: Nama kolom 'status' diubah ke 'status_moderasi'
        $target_id, $target_type
    ));

    // Query data
    $reviews = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.user_id, u.display_name, r.rating, r.komentar, r.created_at as tanggal 
         FROM $table_name r
         LEFT JOIN $wpdb->users u ON r.user_id = u.ID
         WHERE r.target_id = %d AND r.target_type = %s AND status_moderasi = 'disetujui'
         ORDER BY r.created_at DESC
         LIMIT %d OFFSET %d",
        $target_id, $target_type, $per_page, $offset
    ));

    $formatted_reviews = array_map(function($review) {
        return [
            'id' => (int) $review->id,
            'user_id' => (int) $review->user_id,
            'display_name' => $review->display_name ?: 'Anonim',
            'rating' => (int) $review->rating,
            'komentar' => $review->komentar,
            'tanggal' => $review->tanggal,
            'tanggal_formatted' => date_i18n( 'j F Y', strtotime( $review->tanggal ) ),
        ];
    }, $reviews);

    $response = [
        'reviews' => $formatted_reviews,
        'total' => (int) $total,
        'total_pages' => ceil($total / $per_page),
        'current_page' => $page,
    ];

    return new WP_REST_Response($response, 200);
}

/**
 * Mengambil pengaturan publik (global).
 * PERBAIKAN: Mengambil data dari array 'dw_settings' yang benar
 */
function dw_api_get_public_settings() {
    // --- PERBAIKAN PERFORMA (Sesuai Analisis Poin 3.2.2) ---
    $cache_key = 'dw_api_public_settings_cache';
    $cached_data = get_transient($cache_key);
    if (false !== $cached_data) {
        return new WP_REST_Response($cached_data, 200);
    }
    // --- AKHIR PERBAIKAN ---

    $options = get_option('dw_settings', []);
    
    $settings = [
        'nama_website' => $options['nama_website'] ?? get_option('blogname'),
        'deskripsi_website' => get_option('blogdescription'),
        'warna_utama' => $options['warna_utama'] ?? '#2563EB', // Mengambil 'warna_utama' dari array
        'logo_frontend' => $options['logo_frontend'] ?? '', // BARU: Menambahkan logo
    ];
    
    set_transient($cache_key, $settings, HOUR_IN_SECONDS * 6); // Cache 6 jam
    
    return new WP_REST_Response($settings, 200);
}


// --- FUNGSI ALAMAT (WILAYAH) ---

/**
 * Mengambil data provinsi dari API eksternal (cache 1 hari).
 */
function dw_api_get_alamat_provinsi() {
    $data = dw_get_api_provinsi(); // Fungsi ini ada di 'includes/address-api.php'
    if (is_wp_error($data)) {
        return new WP_REST_Response(['message' => $data->get_error_message()], 500);
    }
    return new WP_REST_Response($data, 200);
}

/**
 * Mengambil data kabupaten dari API eksternal.
 */
function dw_api_get_alamat_kabupaten(WP_REST_Request $request) {
    $provinsi_id = $request['id'];
    $data = dw_get_api_kabupaten($provinsi_id); // Fungsi ini ada di 'includes/address-api.php'
    if (is_wp_error($data)) {
        return new WP_REST_Response(['message' => $data->get_error_message()], 500);
    }
    return new WP_REST_Response($data, 200);
}

/**
 * Mengambil data kecamatan dari API eksternal.
 */
function dw_api_get_alamat_kecamatan(WP_REST_Request $request) {
    $kabupaten_id = $request['id'];
    $data = dw_get_api_kecamatan($kabupaten_id); // Fungsi ini ada di 'includes/address-api.php'
    if (is_wp_error($data)) {
        return new WP_REST_Response(['message' => $data->get_error_message()], 500);
    }
    return new WP_REST_Response($data, 200);
}

/**
 * Mengambil data kelurahan dari API eksternal.
 */
function dw_api_get_alamat_kelurahan(WP_REST_Request $request) {
    $kecamatan_id = $request['id'];
    $data = dw_get_api_desa($kecamatan_id); // Fungsi ini ada di 'includes/address-api.php'
    if (is_wp_error($data)) {
        return new WP_REST_Response(['message' => $data->get_error_message()], 500);
    }
    return new WP_REST_Response($data, 200);
}


/**
 * Mengambil opsi pengiriman.
 */
function dw_api_get_shipping_options(WP_REST_Request $request) {
    // Fungsi ini ada di 'includes/cart.php'
    return dw_calculate_shipping_options_api($request);
}

// --- FUNGSI FORMATTER (HELPER LOKAL) ---
// (Semua fungsi formatter dipindahkan ke api-helpers.php)
?>