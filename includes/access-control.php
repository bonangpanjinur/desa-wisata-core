<?php
/**
 * File Name:   access-control.php
 * File Folder: includes/
 * Description: Menangani pembatasan akses dashboard WP, redirect user, dan kustomisasi login URL.
 * * UPDATE PERBAIKAN:
 * - Mengizinkan akses publik ke Frontend (Home, Produk, Cart).
 * - Hanya memblokir akses ke /wp-admin bagi non-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Sembunyikan Admin Bar untuk semua kecuali Administrator dan Admin Kabupaten.
 */
function dw_disable_admin_bar() {
    $allowed_roles = array( 'administrator', 'admin_kabupaten', 'admin_desa' );
    $user = wp_get_current_user();
    
    $show_bar = false;
    if ( is_user_logged_in() ) {
        foreach ( $allowed_roles as $role ) {
            if ( in_array( $role, (array) $user->roles ) ) {
                $show_bar = true;
                break;
            }
        }
    }

    if ( ! $show_bar ) {
        show_admin_bar( false );
    }
}
add_action( 'after_setup_theme', 'dw_disable_admin_bar' );

/**
 * 2. Redirect User Non-Admin dari /wp-admin ke Halaman Custom.
 * PERBAIKAN: Fungsi ini hanya jalan saat is_admin() bernilai true.
 * Artinya, halaman depan (Frontend) TIDAK AKAN terkena redirect ini.
 */
function dw_redirect_non_admin_users() {
    // KONDISI UTAMA: Hanya jalankan jika user mencoba akses area ADMIN (/wp-admin)
    // DAN bukan proses AJAX (karena AJAX butuh akses admin-ajax.php)
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        
        $user = wp_get_current_user();
        
        // Daftar role yang BOLEH akses wp-admin
        $admin_access_roles = array( 'administrator', 'admin_kabupaten', 'admin_desa' );
        
        $can_access = false;
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            foreach ( $admin_access_roles as $role ) {
                if ( in_array( $role, $user->roles ) ) {
                    $can_access = true;
                    break;
                }
            }
        }

        // Jika user SUDAH login tapi TIDAK punya akses admin (misal: Pedagang/Pembeli)
        // Maka tendang mereka keluar dari /wp-admin
        if ( ! $can_access ) {
            
            if ( in_array( 'pedagang', (array) $user->roles ) ) {
                wp_redirect( home_url( '/dashboard-toko/' ) ); 
                exit;
            } 
            elseif ( in_array( 'pembeli', (array) $user->roles ) ) {
                wp_redirect( home_url( '/akun-saya/' ) );
                exit;
            }
            else {
                // Role lain atau user aneh, lempar ke home
                wp_redirect( home_url() );
                exit;
            }
        }
    }
    // Jika tidak masuk blok if (is_admin), berarti user sedang di Frontend.
    // Biarkan mereka mengakses halaman apapun.
}
add_action( 'admin_init', 'dw_redirect_non_admin_users' );

/**
 * 3. Redirect Login: Setelah login sukses, arahkan ke dashboard yang sesuai.
 */
function dw_login_redirect( $redirect_to, $request, $user ) {
    if ( ! is_wp_error( $user ) && isset( $user->roles ) && is_array( $user->roles ) ) {
        
        if ( in_array( 'administrator', $user->roles ) || in_array( 'admin_kabupaten', $user->roles ) ) {
            return admin_url(); 
        } 
        elseif ( in_array( 'admin_desa', $user->roles ) ) {
            return admin_url(); 
        }
        elseif ( in_array( 'pedagang', $user->roles ) ) {
            return home_url( '/dashboard-toko/' );
        } 
        elseif ( in_array( 'pembeli', $user->roles ) ) {
            // Jika ada parameter redirect_to di URL (misal dari tombol Beli), prioritaskan itu
            if ( isset( $_REQUEST['redirect_to'] ) ) {
                return $_REQUEST['redirect_to'];
            }
            return home_url( '/akun-saya/' );
        }
    }
    return $redirect_to;
}
add_filter( 'login_redirect', 'dw_login_redirect', 10, 3 );

/**
 * 4. Mengubah URL Login Default WordPress
 */
function dw_change_login_url( $login_url, $redirect, $force_reauth ) {
    $login_page = home_url( '/login/' );
    if ( ! empty( $redirect ) ) {
        $login_page = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_page );
    }
    return $login_page;
}
add_filter( 'login_url', 'dw_change_login_url', 10, 3 );

/**
 * 5. Redirect Paksa wp-login.php ke Halaman Custom
 */
function dw_redirect_wp_login_php() {
    $current_url = $_SERVER['REQUEST_URI'];
    if ( strpos( $current_url, 'wp-login.php' ) !== false ) {
        $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
        if ( ! in_array( $action, array( 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass' ) ) ) {
            wp_redirect( home_url( '/login/' ) );
            exit;
        }
    }
}
add_action( 'init', 'dw_redirect_wp_login_php' );