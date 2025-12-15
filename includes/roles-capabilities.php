<?php
/**
 * File Name:   roles-capabilities.php
 * File Folder: includes/
 * File Path:   includes/roles-capabilities.php
 *
 * Mengelola peran pengguna kustom dan hak akses (capabilities).
 *
 * PERBAIKAN:
 * - Menambahkan kapabilitas 'dw_manage_pedagang' ke role 'admin_desa'.
 * - Ini memperbaiki masalah Admin Desa tidak bisa menambah/mengedit pedagang.
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
        'dw_moderate_reviews',   // Kapabilitas kustom untuk moderasi ulasan
    ];
}

/**
 * Membuat atau memperbarui peran pengguna kustom dan menetapkan kapabilitasnya.
 */
function dw_create_roles_and_caps() {

    // Hapus peran lama jika ada untuk refresh kapabilitas
    if ( get_role( 'admin_kabupaten' ) ) remove_role('admin_kabupaten');
    if ( get_role( 'admin_desa' ) ) remove_role('admin_desa');
    if ( get_role( 'pedagang' ) ) remove_role('pedagang');

    // --- Definisi Peran Baru ---

    // Role: Admin Kabupaten
    add_role('admin_kabupaten', 'Admin Kabupaten', [ 'read' => true ]);
    $admin_kab_role = get_role('admin_kabupaten');
    if ($admin_kab_role) {
        $admin_kab_role->add_cap('dw_manage_desa');        
        $admin_kab_role->add_cap('dw_manage_ongkir');      
        $admin_kab_role->add_cap('dw_view_logs');          
        $admin_kab_role->add_cap('dw_approve_pedagang');   
        $admin_kab_role->add_cap('dw_manage_promosi');     
        $admin_kab_role->add_cap('dw_moderate_reviews');   
        $admin_kab_role->add_cap('moderate_comments');     
        $admin_kab_role->add_cap('dw_manage_pedagang'); // Admin Kab juga perlu ini
    }

    // Role: Admin Desa
    add_role('admin_desa', 'Admin Desa', [
        'read' => true,
        'upload_files' => true, 
        'list_users' => true,
    ]);
    $admin_desa_role = get_role('admin_desa');
    if ($admin_desa_role) {
        // Kapabilitas untuk CPT Wisata.
        $admin_desa_role->add_cap('edit_dw_wisatas');         
        $admin_desa_role->add_cap('publish_dw_wisatas');      
        $admin_desa_role->add_cap('delete_dw_wisatas');       
        $admin_desa_role->add_cap('read_dw_wisata');          

        // --- PERBAIKAN UTAMA DI SINI ---
        // Memberikan akses manajemen pedagang ke Admin Desa
        $admin_desa_role->add_cap('dw_manage_pedagang');
        // -------------------------------

        $admin_desa_role->add_cap('dw_approve_pedagang'); // Untuk verifikasi
    }

    // Role: Pedagang
    add_role('pedagang', 'Pedagang', [
        'read' => true,
        'upload_files' => true, 
    ]);
    $pedagang_role = get_role('pedagang');
    if ($pedagang_role) {
        // Kapabilitas CPT Produk
        $pedagang_role->add_cap('edit_dw_produks');         
        $pedagang_role->add_cap('publish_dw_produks');      
        $pedagang_role->add_cap('delete_dw_produks');       
        $pedagang_role->add_cap('read_dw_produk');          
        $pedagang_role->add_cap('dw_manage_pesanan');       
        $pedagang_role->add_cap('assign_terms', 'kategori_produk');
    }

    // Pastikan peran Administrator (Super Admin) memiliki semua kapabilitas.
    dw_ensure_admin_capabilities();
}

/**
 * Memastikan peran Administrator memiliki semua kapabilitas kustom.
 */
function dw_ensure_admin_capabilities() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $custom_caps = dw_get_custom_capabilities();
        foreach ($custom_caps as $cap) {
            $admin_role->add_cap($cap);
        }
        $admin_role->add_cap('moderate_comments');
        $admin_role->add_cap('manage_categories');

        // Caps CPT
        $cpt_types = ['dw_produk', 'dw_wisata'];
        foreach ($cpt_types as $cpt) {
            $post_type_object = get_post_type_object($cpt);
            if ($post_type_object && isset($post_type_object->cap)) {
                foreach (get_object_vars($post_type_object->cap) as $cap_name) {
                     $admin_role->add_cap($cap_name);
                }
            }
        }
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

// ... Filter map_meta_cap dan restrict query tetap sama ...
function dw_map_meta_cap_filter( $caps, $cap, $user_id, $args ) {
    global $wpdb;
    $post_id = isset($args[0]) ? absint($args[0]) : 0;
    $post = $post_id ? get_post($post_id) : null;

    $user = get_userdata($user_id);
    if (!$user || in_array('administrator', $user->roles)) return $caps; 

    $target_caps = ['edit_post', 'delete_post', 'publish_posts', 'read_post'];
    if (!in_array($cap, $target_caps)) {
        return $caps;
    }
    
    $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = $post ? $post->post_type : ($current_screen ? $current_screen->post_type : null);
    if (!$post_type) return $caps; 

    // --- Logika untuk Admin Desa (Target: dw_wisata) ---
    if (in_array('admin_desa', $user->roles) && $post_type === 'dw_wisata') {
        $managed_desa_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id
        ));

        if ($post_id > 0 && $post) {
            $wisata_desa_id = get_post_meta($post_id, '_dw_id_desa', true);
            if ($managed_desa_id && $wisata_desa_id && (int)$managed_desa_id === (int)$wisata_desa_id) {
                if ($cap === 'edit_post') $caps = ['edit_dw_wisatas'];
                if ($cap === 'delete_post') $caps = ['delete_dw_wisatas'];
                if ($cap === 'publish_posts') $caps = ['publish_dw_wisatas'];
                if ($cap === 'read_post') $caps = ['read_dw_wisata'];
            } else {
                $caps = ['do_not_allow']; 
            }
        }
        elseif ($post_id === 0) {
            if ($cap === 'edit_post') $caps = ['edit_dw_wisatas']; 
            if ($cap === 'publish_posts') $caps = ['publish_dw_wisatas'];
        }
        elseif (!$managed_desa_id) {
             $caps = ['do_not_allow'];
        }
        return $caps;
    }

    // --- Logika untuk Pedagang (Target: dw_produk) ---
    if (in_array('pedagang', $user->roles) && $post_type === 'dw_produk') {
        if ($post_id > 0 && $post) {
            if ((int)$post->post_author === $user_id) {
                if ($cap === 'edit_post') $caps = ['edit_dw_produks'];
                if ($cap === 'delete_post') $caps = ['delete_dw_produks'];
                if ($cap === 'publish_posts') $caps = ['publish_dw_produks'];
                if ($cap === 'read_post') $caps = ['read_dw_produk'];
            } else {
                $caps = ['do_not_allow'];
            }
        }
        elseif ($post_id === 0) {
            if ($cap === 'edit_post') $caps = ['edit_dw_produks'];
            if ($cap === 'publish_posts') $caps = ['publish_dw_produks'];
        }
         return $caps;
    }

    return $caps;
}
add_filter( 'map_meta_cap', 'dw_map_meta_cap_filter', 10, 4 ); 

add_action( 'pre_get_posts', 'dw_restrict_cpt_queries_by_role' );
function dw_restrict_cpt_queries_by_role( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;

    $user_id = get_current_user_id();
    if ( $user_id === 0 ) return;
    
    $user = wp_get_current_user();
    if ( current_user_can('administrator') || current_user_can('admin_kabupaten') ) return;

    global $wpdb;

    if ( in_array( 'pedagang', $user->roles ) && $query->get( 'post_type' ) === 'dw_produk' ) {
        $query->set( 'author', $user_id );
        if ( isset( $_GET['author'] ) && $_GET['author'] != $user_id ) {
            $query->set( 'author', $user_id );
        }
    }

    if ( in_array( 'admin_desa', $user->roles ) && $query->get( 'post_type' ) === 'dw_wisata' ) {
        $managed_desa_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id
        ) );
        
        if ( $managed_desa_id ) {
            $meta_query = $query->get( 'meta_query' );
            if ( ! is_array( $meta_query ) ) $meta_query = [];
            $meta_query[] = [
                'key'     => '_dw_id_desa',
                'value'   => $managed_desa_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
            $query->set( 'meta_query', $meta_query );
        } else {
            $query->set( 'post__in', [0] );
        }
    }
}
?>