<?php
/**
 * File Name:   access-control.php
 * File Folder: includes/
 * Description: Menangani pembatasan akses dashboard WP dan redirect user.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Sembunyikan Admin Bar untuk semua kecuali Administrator dan Admin Kabupaten.
 */
function dw_disable_admin_bar() {
    if ( ! current_user_can( 'administrator' ) && ! current_user_can( 'admin_kabupaten' ) && ! current_user_can('admin_desa') ) {
        show_admin_bar( false );
    }
}
add_action( 'after_setup_theme', 'dw_disable_admin_bar' );

/**
 * 2. Redirect User Non-Admin dari /wp-admin ke Halaman Custom.
 */
function dw_redirect_non_admin_users() {
    // Jika user sedang di admin, bukan melakukan AJAX, dan bukan admin/admin_desa
    if ( is_admin() && ! defined( 'DOING_AJAX' ) && 
         ! current_user_can( 'administrator' ) && 
         ! current_user_can( 'admin_kabupaten' ) &&
         ! current_user_can( 'admin_desa' ) ) { // Admin Desa boleh masuk wp-admin (opsional)
        
        $current_user = wp_get_current_user();
        
        // Logika Redirect Berdasarkan Role
        if ( in_array( 'pedagang', (array) $current_user->roles ) ) {
            // Arahkan ke halaman Dashboard Toko di Frontend (Theme)
            // Pastikan Anda membuat Page dengan slug 'dashboard-toko'
            wp_redirect( home_url( '/dashboard-toko/' ) ); 
            exit;
        } 
        elseif ( in_array( 'pembeli', (array) $current_user->roles ) ) {
            // Arahkan ke halaman Akun Saya di Frontend
            wp_redirect( home_url( '/akun-saya/' ) );
            exit;
        }
        else {
            // Role lain (subscriber biasa), lempar ke home
            wp_redirect( home_url() );
            exit;
        }
    }
}
add_action( 'admin_init', 'dw_redirect_non_admin_users' );

/**
 * 3. Redirect Login: Setelah login, arahkan ke dashboard yang sesuai.
 */
function dw_login_redirect( $redirect_to, $request, $user ) {
    // Jika ada error atau user belum valid
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        
        if ( in_array( 'administrator', $user->roles ) || in_array( 'admin_kabupaten', $user->roles ) ) {
            return admin_url(); // Tetap ke WP Admin
        } 
        elseif ( in_array( 'admin_desa', $user->roles ) ) {
            // Admin Desa bisa ke WP Admin, tapi menu dibatasi (sudah ada di code Anda)
            // Atau mau frontend dashboard juga? Asumsi ke admin panel khusus:
            return admin_url( 'admin.php?page=dw-dashboard' ); 
        }
        elseif ( in_array( 'pedagang', $user->roles ) ) {
            return home_url( '/dashboard-toko/' );
        } 
        elseif ( in_array( 'pembeli', $user->roles ) ) {
            return home_url( '/akun-saya/' );
        }
    }
    
    return $redirect_to;
}
add_filter( 'login_redirect', 'dw_login_redirect', 10, 3 );