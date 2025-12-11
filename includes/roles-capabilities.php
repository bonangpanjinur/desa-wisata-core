<?php
/**
 * File Name:   roles-capabilities.php
 * File Folder: includes/
 * File Path:   includes/roles-capabilities.php
 *
 * Mengelola peran pengguna kustom dan hak akses (capabilities).
 *
 * PERUBAHAN KRITIS (HEADLESS MURNI):
 * - Menguatkan filter `map_meta_cap` untuk membatasi Pedagang hanya ke produknya sendiri
 * dan Admin Desa hanya ke wisata desanya.
 * - Mencakup kapabilitas 'read_post' (melihat) untuk membatasi akses pada daftar CPT.
 *
 * --- PERBAIKAN (SARAN PENINGKATAN UX) ---
 * - Menghapus (mengomentari) kapabilitas 'read_dw_produk' dari role 'admin_desa'
 * agar mereka tidak melihat menu "Produk" yang tidak relevan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Mendapatkan daftar semua kapabilitas kustom yang didefinisikan oleh plugin.
 *
 * @return array Daftar string kapabilitas.
 */
function dw_get_custom_capabilities() {
    return [
        'dw_manage_settings',    // Mengelola pengaturan plugin
        'dw_manage_desa',        // Menambah/mengedit/menghapus data desa di tabel dw_desa
        'dw_manage_wisata',      // Kemampuan umum mengelola CPT wisata (biasanya admin)
        'dw_manage_pedagang',    // Menambah/mengedit/menghapus data pedagang di tabel dw_pedagang
        'dw_approve_pedagang',   // Menyetujui pendaftaran pedagang
        'dw_manage_produk',      // Kemampuan umum mengelola CPT produk (biasanya admin)
        'dw_manage_pesanan',     // Pedagang mengelola pesanan untuk tokonya
        'dw_manage_promosi',     // Mengelola data promosi (menyetujui/menolak)
        'dw_manage_banners',     // Mengelola banner/slider
        'dw_view_logs',          // Melihat log aktivitas plugin
        'dw_manage_ongkir',      // Mengelola zona ongkir lokal
        'dw_moderate_reviews',   // Kapabilitas kustom untuk moderasi ulasan (meskipun `moderate_comments` lebih umum digunakan)
    ];
}

/**
 * Membuat atau memperbarui peran pengguna kustom dan menetapkan kapabilitasnya.
 * Fungsi ini biasanya dipanggil saat aktivasi plugin.
 */
function dw_create_roles_and_caps() {

    // Hapus peran lama jika ada, untuk memastikan kapabilitas terbaru diterapkan saat re-aktivasi.
    if ( get_role( 'admin_kabupaten' ) ) remove_role('admin_kabupaten');
    if ( get_role( 'admin_desa' ) ) remove_role('admin_desa');
    if ( get_role( 'pedagang' ) ) remove_role('pedagang');
    if ( get_role( 'penjual' ) ) remove_role('penjual'); 
    if ( get_role( 'pembeli' ) ) remove_role('pembeli'); 

    // --- Definisi Peran Baru ---

    // Role: Admin Kabupaten
    add_role('admin_kabupaten', 'Admin Kabupaten', [
        'read' => true, 
    ]);
    $admin_kab_role = get_role('admin_kabupaten');
    if ($admin_kab_role) {
        $admin_kab_role->add_cap('dw_manage_desa');        
        $admin_kab_role->add_cap('dw_manage_ongkir');      
        $admin_kab_role->add_cap('dw_view_logs');          
        $admin_kab_role->add_cap('dw_approve_pedagang');   
        $admin_kab_role->add_cap('dw_manage_promosi');     
        $admin_kab_role->add_cap('dw_moderate_reviews');   
        $admin_kab_role->add_cap('moderate_comments');     

        // --- HAK AKSES KATEGORI DIHAPUS (Hanya Super Admin) ---
    }

    // Role: Admin Desa
    add_role('admin_desa', 'Admin Desa', [
        'read' => true,
        'upload_files' => true, 
    ]);
    $admin_desa_role = get_role('admin_desa');
    if ($admin_desa_role) {
        // Kapabilitas untuk CPT Wisata.
        $admin_desa_role->add_cap('edit_dw_wisatas');         // Akses menu 'Wisata' & bisa edit
        $admin_desa_role->add_cap('publish_dw_wisatas');      // Bisa mempublikasikan wisata baru
        $admin_desa_role->add_cap('delete_dw_wisatas');       // Bisa menghapus wisata (ke trash)
        $admin_desa_role->add_cap('read_dw_wisata');          // Bisa melihat wisata individu (penting untuk map_meta_cap)

        // --- PERBAIKAN (SARAN UX): Hapus akses baca produk ---
        // $admin_desa_role->add_cap('read_dw_produk'); 

        // --- HAK AKSES KATEGORI DIHAPUS (Hanya Super Admin) ---
    }

    // Role: Pedagang
    add_role('pedagang', 'Pedagang', [
        'read' => true,
        'upload_files' => true, 
    ]);
    $pedagang_role = get_role('pedagang');
    if ($pedagang_role) {
        // Kapabilitas dasar CPT Produk
        $pedagang_role->add_cap('edit_dw_produks');         // Akses menu 'Produk' & bisa edit
        $pedagang_role->add_cap('publish_dw_produks');      // Bisa publikasi produk
        $pedagang_role->add_cap('delete_dw_produks');       // Bisa hapus produk (ke trash)
        $pedagang_role->add_cap('read_dw_produk');          // Bisa melihat produk individu

        // Kapabilitas kustom
        $pedagang_role->add_cap('dw_manage_pesanan');       // Akses menu & kelola pesanan masuk

        // --- BARU: Hak Akses Kategori untuk Pedagang ---
        // Pedagang hanya boleh MEMILIH (assign) kategori produk
        $pedagang_role->add_cap('assign_terms', 'kategori_produk');
    }

    // Pastikan peran Administrator (Super Admin) memiliki semua kapabilitas.
    dw_ensure_admin_capabilities();
}

/**
 * Memastikan peran Administrator memiliki semua kapabilitas kustom plugin
 * dan kapabilitas bawaan WordPress yang relevan.
 */
function dw_ensure_admin_capabilities() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $custom_caps = dw_get_custom_capabilities();
        foreach ($custom_caps as $cap) {
            $admin_role->add_cap($cap);
        }

        $admin_role->add_cap('moderate_comments');
        $admin_role->add_cap('manage_categories'); // Kapabilitas umum

        // Pastikan admin bisa mengelola SEMUA post CPT
        $cpt_types = ['dw_produk', 'dw_wisata'];
        foreach ($cpt_types as $cpt) {
            $post_type_object = get_post_type_object($cpt);
            if ($post_type_object && isset($post_type_object->cap)) {
                foreach (get_object_vars($post_type_object->cap) as $cap_name) {
                     $admin_role->add_cap($cap_name);
                }
            }
        }
        
        // Pastikan admin bisa mengelola SEMUA taksonomi kustom
         $taxonomies = ['kategori_produk', 'kategori_wisata'];
         foreach ($taxonomies as $tax_slug) {
             $taxonomy_object = get_taxonomy($tax_slug);
             if ($taxonomy_object && isset($taxonomy_object->cap)) {
                 $admin_role->add_cap($taxonomy_object->cap->manage_terms);
                 $admin_role->add_cap($taxonomy_object->cap->edit_terms);
                 $admin_role->add_cap($taxonomy_object->cap->delete_terms);
                 $admin_role->add_cap($taxonomy_object->cap->assign_terms);
             }
         }
    }
}


/**
 * Filter KRITIS untuk `map_meta_cap`.
 * Digunakan untuk membatasi akses edit/hapus/baca CPT hanya pada item milik sendiri
 * atau milik desa yang dikelola.
 *
 * @param array   $caps      Array kapabilitas yang diperlukan untuk tindakan.
 * @param string  $cap       Kapabilitas spesifik yang sedang diperiksa (misal: 'edit_post').
 * @param int     $user_id   ID pengguna yang tindakannya sedang diperiksa.
 * @param array   $args      Argumen tambahan, biasanya berisi ID post $args[0].
 * @return array  Array kapabilitas yang dimodifikasi.
 */
function dw_map_meta_cap_filter( $caps, $cap, $user_id, $args ) {
    global $wpdb;
    $post_id = isset($args[0]) ? absint($args[0]) : 0;
    $post = $post_id ? get_post($post_id) : null;

    $user = get_userdata($user_id);
    if (!$user || in_array('administrator', $user->roles)) return $caps; // Admin bebas

    // Kapabilitas yang sedang kita batasi: edit, delete, publish, read
    $target_caps = ['edit_post', 'delete_post', 'publish_posts', 'read_post'];
    if (!in_array($cap, $target_caps)) {
        return $caps;
    }
    
    // Tentukan post type dari post atau konteks layar
    $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = $post ? $post->post_type : ($current_screen ? $current_screen->post_type : null);
    if (!$post_type) return $caps; 

    // --- Logika untuk Admin Desa (Target: dw_wisata) ---
    if (in_array('admin_desa', $user->roles) && $post_type === 'dw_wisata') {
        $managed_desa_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id
        ));

        // Jika ini post yang sudah ada (edit/delete/read/publish)
        if ($post_id > 0 && $post) {
            $wisata_desa_id = get_post_meta($post_id, '_dw_id_desa', true);

            // Larang semua akses jika Desa yang dikelola tidak cocok atau tidak ada
            if ($managed_desa_id && $wisata_desa_id && (int)$managed_desa_id === (int)$wisata_desa_id) {
                // Izinkan tindakan, ubah kapabilitas yang diperlukan ke kapabilitas dasar CPT
                if ($cap === 'edit_post') $caps = ['edit_dw_wisatas'];
                if ($cap === 'delete_post') $caps = ['delete_dw_wisatas'];
                if ($cap === 'publish_posts') $caps = ['publish_dw_wisatas'];
                if ($cap === 'read_post') $caps = ['read_dw_wisata'];
            } else {
                // MENCABUT HAK AKSES JIKA TIDAK MEMILIKI KEPEMILIKAN
                $caps = ['do_not_allow']; 
            }
        }
        // Jika ini post baru ($post_id = 0)
        elseif ($post_id === 0) {
            // Izinkan membuat post wisata baru (diasumsikan akan diisi ID Desa yang benar saat save)
            if ($cap === 'edit_post') $caps = ['edit_dw_wisatas']; 
            if ($cap === 'publish_posts') $caps = ['publish_dw_wisatas'];
        }
        // Jika tidak ada ID desa yang dikelola, larang semua aksi terkait wisata
        elseif (!$managed_desa_id) {
             $caps = ['do_not_allow'];
        }

        return $caps;
    }

    // --- Logika untuk Pedagang (Target: dw_produk) ---
    if (in_array('pedagang', $user->roles) && $post_type === 'dw_produk') {
        // Jika ini post yang sudah ada (edit/delete/read/publish)
        if ($post_id > 0 && $post) {
            // Jika penulis post SAMA DENGAN user ID pedagang
            if ((int)$post->post_author === $user_id) {
                // Izinkan, gunakan kapabilitas dasar CPT yang dimiliki Pedagang
                if ($cap === 'edit_post') $caps = ['edit_dw_produks'];
                if ($cap === 'delete_post') $caps = ['delete_dw_produks'];
                if ($cap === 'publish_posts') $caps = ['publish_dw_produks'];
                if ($cap === 'read_post') $caps = ['read_dw_produk'];
            } else {
                // MENCABUT HAK AKSES JIKA BUKAN MILIKNYA
                $caps = ['do_not_allow'];
            }
        }
        // Jika ini post baru ($post_id = 0)
        elseif ($post_id === 0) {
            // Izinkan pedagang membuat produk baru (post_author akan otomatis diisi dengan ID mereka)
            if ($cap === 'edit_post') $caps = ['edit_dw_produks'];
            if ($cap === 'publish_posts') $caps = ['publish_dw_produks'];
        }
         return $caps;
    }

    // Kembalikan kapabilitas asli jika tidak ada aturan khusus yang cocok
    return $caps;
}
add_filter( 'map_meta_cap', 'dw_map_meta_cap_filter', 10, 4 ); 

// Set hook untuk membatasi query WP List Table (untuk list produk/wisata di admin)
// Ini adalah KUNCI untuk membatasi LIST ITEM yang terlihat di dashboard Admin Desa/Pedagang.
add_action( 'pre_get_posts', 'dw_restrict_cpt_queries_by_role' );

/**
 * Membatasi query CPT di admin untuk Admin Desa dan Pedagang.
 *
 * @param WP_Query $query
 * @return void
 */
function dw_restrict_cpt_queries_by_role( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( $user_id === 0 ) {
        return;
    }
    
    $user = wp_get_current_user();
    
    // Jangan batasi Administrator atau Admin Kabupaten
    if ( current_user_can('administrator') || current_user_can('admin_kabupaten') ) {
        return;
    }

    global $wpdb;

    // --- Pedagang (Hanya boleh lihat produknya sendiri) ---
    if ( in_array( 'pedagang', $user->roles ) && $query->get( 'post_type' ) === 'dw_produk' ) {
        // Batasi query ke post_author yang sama dengan user ID
        $query->set( 'author', $user_id );
        // Penting: Pastikan mereka TIDAK bisa melihat post dari user lain (misal, edit.php?author=other_id)
        if ( isset( $_GET['author'] ) && $_GET['author'] != $user_id ) {
            // Set author ke user saat ini, atau ke -1 untuk memastikan tidak ada hasil jika terjadi manipulasi
            $query->set( 'author', $user_id );
        }
    }

    // --- Admin Desa (Hanya boleh lihat wisata desanya) ---
    if ( in_array( 'admin_desa', $user->roles ) && $query->get( 'post_type' ) === 'dw_wisata' ) {
        // Dapatkan ID desa yang dikelola oleh admin desa ini
        $managed_desa_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id
        ) );
        
        if ( $managed_desa_id ) {
            // Terapkan meta query untuk membatasi wisata berdasarkan ID desa
            $meta_query = $query->get( 'meta_query' );
            if ( ! is_array( $meta_query ) ) {
                $meta_query = [];
            }
            $meta_query[] = [
                'key'     => '_dw_id_desa',
                'value'   => $managed_desa_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
            $query->set( 'meta_query', $meta_query );
        } else {
            // Jika admin desa tidak terikat pada desa manapun, jangan tampilkan apa-apa
            $query->set( 'post__in', [0] );
        }
    }
}

