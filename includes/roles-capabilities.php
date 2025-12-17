<?php
/**
 * File Name:   roles-capabilities.php
 * File Folder: includes/
 * File Path:   includes/roles-capabilities.php
 *
 * Mengelola peran pengguna kustom dan hak akses (capabilities).
 * UPDATE: Menambahkan Self-Healing Logic untuk memastikan hak akses selalu update.
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

// 1. DEFINISI KONSTANTA ROLE ID (Agar konsisten)
if ( ! defined( 'DW_ROLE_ADMIN_DESA' ) ) {
    define( 'DW_ROLE_ADMIN_DESA', 'admin_desa' );
}

function dw_get_custom_capabilities() {
    return [
        'dw_manage_settings', 'dw_manage_desa', 'dw_manage_wisata', 'dw_manage_pedagang',
        'dw_approve_pedagang', 'dw_manage_produk', 'dw_manage_pesanan', 'dw_manage_promosi',
        'dw_manage_banners', 'dw_view_logs', 'dw_manage_ongkir', 'dw_moderate_reviews'
    ];
}

function dw_create_roles_and_caps() {
    // Hapus peran lama untuk refresh bersih
    if ( get_role( 'admin_kabupaten' ) ) remove_role('admin_kabupaten');
    if ( get_role( DW_ROLE_ADMIN_DESA ) ) remove_role(DW_ROLE_ADMIN_DESA);
    if ( get_role( 'pedagang' ) ) remove_role('pedagang');

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
        $admin_kab_role->add_cap('dw_manage_pedagang'); 
    }

    // Role: Admin Desa (FIXED)
    add_role(DW_ROLE_ADMIN_DESA, 'Admin Desa', [
        'read' => true,
        'upload_files' => true, 
        'list_users' => true,
    ]);
    $admin_desa_role = get_role(DW_ROLE_ADMIN_DESA);
    if ($admin_desa_role) {
        $admin_desa_role->add_cap('edit_dw_wisatas');         
        $admin_desa_role->add_cap('publish_dw_wisatas');      
        $admin_desa_role->add_cap('delete_dw_wisatas');       
        $admin_desa_role->add_cap('read_dw_wisata');          
        $admin_desa_role->add_cap('dw_manage_pedagang'); // PENTING: Izin Pedagang
        $admin_desa_role->add_cap('dw_approve_pedagang'); 
    }

    // Role: Pedagang
    add_role('pedagang', 'Pedagang', [
        'read' => true,
        'upload_files' => true, 
    ]);
    $pedagang_role = get_role('pedagang');
    if ($pedagang_role) {
        $pedagang_role->add_cap('edit_dw_produks');         
        $pedagang_role->add_cap('publish_dw_produks');      
        $pedagang_role->add_cap('delete_dw_produks');       
        $pedagang_role->add_cap('read_dw_produk');          
        $pedagang_role->add_cap('dw_manage_pesanan');       
        $pedagang_role->add_cap('assign_terms', 'kategori_produk');
    }

    dw_ensure_admin_capabilities();
}

function dw_ensure_admin_capabilities() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $custom_caps = dw_get_custom_capabilities();
        foreach ($custom_caps as $cap) $admin_role->add_cap($cap);
        $admin_role->add_cap('moderate_comments');
        $admin_role->add_cap('manage_categories');
        
        // CPT Caps
        foreach (['dw_produk', 'dw_wisata'] as $cpt) {
            $obj = get_post_type_object($cpt);
            if ($obj && isset($obj->cap)) {
                foreach (get_object_vars($obj->cap) as $cap_name) $admin_role->add_cap($cap_name);
            }
        }
        // Tax Caps
        foreach (['kategori_produk', 'kategori_wisata'] as $tax) {
            $obj = get_taxonomy($tax);
            if ($obj && isset($obj->cap)) {
                $admin_role->add_cap($obj->cap->manage_terms);
                $admin_role->add_cap($obj->cap->edit_terms);
                $admin_role->add_cap($obj->cap->delete_terms);
                $admin_role->add_cap($obj->cap->assign_terms);
            }
        }
    }
}

// --- SELF HEALING: Update Role Otomatis ---
function dw_force_refresh_caps_once() {
    // Cek apakah Admin Desa punya hak 'dw_manage_pedagang'. Jika tidak, refresh.
    $role = get_role(DW_ROLE_ADMIN_DESA);
    if ($role && !$role->has_cap('dw_manage_pedagang')) {
        dw_create_roles_and_caps();
        // Cek versi jika tersedia, atau biarkan berjalan jika cap hilang
        if (defined('DW_CORE_VERSION')) {
             update_option('dw_caps_version', DW_CORE_VERSION); 
        }
    }
}
add_action('init', 'dw_force_refresh_caps_once', 999);

/**
 * AUTO-FIX: Hapus Role Duplikat (Baru Ditambahkan)
 * Berjalan saat admin init untuk membersihkan role ganda.
 */
function dw_fix_duplicate_roles() {
    global $wp_roles;
    
    if ( ! isset( $wp_roles ) ) {
        $wp_roles = new WP_Roles();
    }

    $target_role_name = 'Admin Desa';
    $correct_slug     = DW_ROLE_ADMIN_DESA; // 'admin_desa'

    foreach ( $wp_roles->roles as $slug => $details ) {
        // Logika: Jika Nama role = "Admin Desa" TAPI slug-nya BUKAN 'admin_desa'
        if ( $details['name'] === $target_role_name && $slug !== $correct_slug ) {
            
            // 1. Pindahkan user ke role yang benar
            $users = get_users( array( 'role' => $slug ) );
            if ( ! empty( $users ) ) {
                foreach ( $users as $user ) {
                    $user->add_role( $correct_slug );
                    $user->remove_role( $slug );
                }
            }

            // 2. Hapus Role Duplikat
            remove_role( $slug );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "Desa Wisata Core: Menghapus role duplikat '$slug'." );
            }
        }
    }
}
add_action( 'admin_init', 'dw_fix_duplicate_roles' );


// --- FILTER RESTRIKSI AKSES (SAMA SEPERTI KODE ASLI) ---
function dw_map_meta_cap_filter( $caps, $cap, $user_id, $args ) {
    global $wpdb;
    $post_id = isset($args[0]) ? absint($args[0]) : 0;
    $post = $post_id ? get_post($post_id) : null;
    $user = get_userdata($user_id);
    if (!$user || in_array('administrator', $user->roles)) return $caps; 

    $target_caps = ['edit_post', 'delete_post', 'publish_posts', 'read_post'];
    if (!in_array($cap, $target_caps)) return $caps;
    
    $post_type = $post ? $post->post_type : (function_exists('get_current_screen') && get_current_screen() ? get_current_screen()->post_type : null);
    if (!$post_type) return $caps; 

    if (in_array(DW_ROLE_ADMIN_DESA, $user->roles) && $post_type === 'dw_wisata') {
        $managed_desa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id));
        if ($post_id > 0 && $post) {
            $wisata_desa_id = get_post_meta($post_id, '_dw_id_desa', true);
            if ($managed_desa_id && $wisata_desa_id && (int)$managed_desa_id === (int)$wisata_desa_id) {
                if ($cap === 'edit_post') $caps = ['edit_dw_wisatas'];
                if ($cap === 'delete_post') $caps = ['delete_dw_wisatas'];
                if ($cap === 'publish_posts') $caps = ['publish_dw_wisatas'];
                if ($cap === 'read_post') $caps = ['read_dw_wisata'];
            } else { $caps = ['do_not_allow']; }
        } elseif ($post_id === 0) {
            if ($cap === 'edit_post') $caps = ['edit_dw_wisatas']; 
            if ($cap === 'publish_posts') $caps = ['publish_dw_wisatas'];
        } elseif (!$managed_desa_id) { $caps = ['do_not_allow']; }
        return $caps;
    }

    if (in_array('pedagang', $user->roles) && $post_type === 'dw_produk') {
        if ($post_id > 0 && $post) {
            if ((int)$post->post_author === $user_id) {
                if ($cap === 'edit_post') $caps = ['edit_dw_produks'];
                if ($cap === 'delete_post') $caps = ['delete_dw_produks'];
                if ($cap === 'publish_posts') $caps = ['publish_dw_produks'];
                if ($cap === 'read_post') $caps = ['read_dw_produk'];
            } else { $caps = ['do_not_allow']; }
        } elseif ($post_id === 0) {
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
        if ( isset( $_GET['author'] ) && $_GET['author'] != $user_id ) $query->set( 'author', $user_id );
    }
    if ( in_array( DW_ROLE_ADMIN_DESA, $user->roles ) && $query->get( 'post_type' ) === 'dw_wisata' ) {
        $managed_desa_id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_desa WHERE id_user_desa = %d", $user_id) );
        if ( $managed_desa_id ) {
            $meta_query = $query->get( 'meta_query' );
            if ( ! is_array( $meta_query ) ) $meta_query = [];
            $meta_query[] = ['key' => '_dw_id_desa', 'value' => $managed_desa_id, 'compare' => '=', 'type' => 'NUMERIC'];
            $query->set( 'meta_query', $meta_query );
        } else { $query->set( 'post__in', [0] ); }
    }
}
?>