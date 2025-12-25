<?php
/**
 * File Name:   access-control.php
 * File Folder: includes/
 * Description: Menangani pembatasan akses dashboard WP dan Proteksi Limit Monetisasi.
 * * UPDATE: 
 * - Memastikan halaman depan (Frontend) TIDAK PERNAH di-redirect.
 * - Redirect hanya berlaku untuk URL /wp-admin.
 * - Added: Proteksi backend untuk limit kuota Wisata (Freemium).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Sembunyikan Admin Bar untuk user non-admin.
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
 * KUNCI PERBAIKAN: Fungsi ini hanya jalan di hook 'admin_init'.
 * Artinya, fungsi ini TIDAK AKAN berjalan saat user membuka halaman depan.
 */
function dw_redirect_non_admin_users() {
    // Double check: Pastikan kita benar-benar di area admin
    if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
        return; // Jangan lakukan apa-apa di frontend!
    }
        
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

    // Jika user mencoba masuk /wp-admin TAPI tidak punya hak akses:
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
            wp_redirect( home_url() );
            exit;
        }
    }
}
// PENTING: Gunakan 'admin_init', bukan 'init'.
add_action( 'admin_init', 'dw_redirect_non_admin_users' );

/**
 * 3. Redirect Login: Setelah login sukses.
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
            // Prioritaskan redirect dari URL jika ada (misal: habis klik Beli)
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
 * 4. Mengubah URL Login Default
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

/**
 * 6. (BARU) Proteksi Limit Wisata untuk Akun Gratis (Freemium)
 * Batasi Pembuatan Wisata via Hook WordPress (Backend Protection).
 * Mencegah user mengakali via direct URL atau script jika kuota sudah habis.
 */
function dw_prevent_wisata_limit_exceeded($data, $postarr) {
    // Cek apakah post type adalah wisata
    if ($data['post_type'] !== 'wisata') {
        return $data;
    }

    // Jika user adalah admin pusat, biarkan
    if (current_user_can('administrator')) {
        return $data;
    }

    // Cek apakah ini post baru (ID belum ada atau 0) atau update
    // Kita hanya membatasi pembuatan BARU, bukan update yang sudah ada
    $is_new = false;
    if (empty($postarr['ID']) || $postarr['post_status'] == 'auto-draft') {
        $is_new = true;
    } else {
        $post = get_post($postarr['ID']);
        if ($post && $post->post_status == 'auto-draft') {
            $is_new = true;
        }
    }

    // Jika update post yang sudah ada (bukan baru), izinkan
    if (!$is_new && isset($postarr['ID']) && $postarr['ID'] > 0) {
        return $data;
    }

    // Cek kapabilitas menggunakan helper function dw_can_add_wisata()
    // Helper ini ada di includes/helpers.php
    if (function_exists('dw_can_add_wisata') && !dw_can_add_wisata(get_current_user_id())) {
        // Batalkan penyimpanan.
        // wp_die akan menghentikan proses dan menampilkan pesan error XML/HTML ke user
        wp_die(
            '<h1>Batas Kuota Tercapai</h1>' .
            '<p>Maaf, Anda telah mencapai batas maksimal upload Wisata (2 Item) untuk akun Gratis.</p>' .
            '<p>Silakan <a href="' . admin_url('admin.php?page=dw-paket-transaksi') . '">Upgrade ke Premium</a> untuk upload tanpa batas dan fitur verifikasi UMKM.</p>', 
            'Limit Tercapai', 
            array('response' => 403, 'back_link' => true)
        );
    }

    return $data;
}
add_filter('wp_insert_post_data', 'dw_prevent_wisata_limit_exceeded', 10, 2);