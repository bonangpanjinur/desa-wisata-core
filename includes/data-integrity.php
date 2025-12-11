<?php
/**
 * File Path: includes/data-integrity.php
 *
 * --- FASE 1: REFAKTOR PENDAFTARAN GRATIS ---
 * PERUBAHAN:
 * - MENGHAPUS referensi ke `dw_transaksi_pendaftaran` saat menghapus pedagang.
 *
 * --- PERBAIKAN (FATAL ERROR 500) ---
 * - MENAMBAHKAN fungsi `dw_pedagang_delete_handler` yang dipindahkan dari
 * `admin-pages/page-pedagang.php` agar selalu tersedia untuk API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menangani logika SEBELUM sebuah desa dihapus dari database.
 * Fungsi ini akan melepaskan relasi dari Pedagang dan Wisata.
 *
 * @param int $desa_id ID desa yang akan dihapus.
 */
function dw_handle_desa_deletion( $desa_id ) {
	global $wpdb;
	$desa_id = absint( $desa_id );
	if ( $desa_id === 0 ) {
		return;
	}

	// 1. Lepaskan relasi Pedagang (set id_desa menjadi NULL).
	$wpdb->update(
		$wpdb->prefix . 'dw_pedagang',
		[ 'id_desa' => null ],
		[ 'id_desa' => $desa_id ],
		[ '%d' ],
		[ '%d' ]
	);

	// 2. Hapus meta relasi dari semua CPT Wisata yang terkait.
	$wisata_posts = get_posts( [
		'post_type'  => 'dw_wisata',
		'meta_key'   => '_dw_id_desa',
		'meta_value' => $desa_id,
		'fields'     => 'ids',
		'numberposts' => -1,
	] );

	if ( ! empty( $wisata_posts ) ) {
		foreach ( $wisata_posts as $wisata_id ) {
			delete_post_meta( $wisata_id, '_dw_id_desa' );
		}
	}

    dw_log_activity('DATA_INTEGRITY', "Relasi untuk Desa #{$desa_id} dilepaskan sebelum penghapusan.", 0);
}

/**
 * Menangani penghapusan data terkait saat seorang pengguna dihapus.
 * Jika pengguna adalah 'pedagang', semua produk (CPT dw_produk) miliknya akan dihapus.
 *
 * @param int $user_id ID pengguna yang dihapus.
 */
function dw_handle_user_deletion( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'pedagang', (array) $user->roles ) ) {
		return;
	}

	// Dapatkan semua produk yang dimiliki oleh pengguna ini.
	$produk_posts = get_posts( [
		'post_type'   => 'dw_produk',
		'author'      => $user_id,
		'fields'      => 'ids',
		'numberposts' => -1,
	] );

	if ( ! empty( $produk_posts ) ) {
		foreach ( $produk_posts as $produk_id ) {
			// true = hapus permanen, false = pindah ke trash
			wp_delete_post( $produk_id, true );
		}
	}
    dw_log_activity('DATA_INTEGRITY', "Semua produk milik user pedagang #{$user_id} ({$user->user_login}) dihapus.", 0);
}

/**
 * Sinkronisasi data alamat denormalisasi pada CPT Wisata saat data Desa diperbarui.
 *
 * @param int   $desa_id ID desa yang diperbarui.
 * @param array $desa_data Data baru dari desa.
 */
function dw_sync_wisata_address_on_desa_update( $desa_id, $desa_data ) {
	$desa_id = absint( $desa_id );
	if ( $desa_id === 0 ) {
		return;
	}

    // Dapatkan semua CPT Wisata yang berelasi dengan desa ini.
    $wisata_posts = get_posts( [
		'post_type'  => 'dw_wisata',
		'meta_key'   => '_dw_id_desa',
		'meta_value' => $desa_id,
		'fields'     => 'ids',
		'numberposts' => -1,
	] );

    if ( ! empty( $wisata_posts ) ) {
		foreach ( $wisata_posts as $wisata_id ) {
            // Update meta fields alamat di setiap post wisata.
			update_post_meta( $wisata_id, '_dw_provinsi', $desa_data['provinsi'] ?? '' );
			update_post_meta( $wisata_id, '_dw_kabupaten', $desa_data['kabupaten'] ?? '' );
			update_post_meta( $wisata_id, '_dw_kecamatan', $desa_data['kecamatan'] ?? '' );
			update_post_meta( $wisata_id, '_dw_kelurahan', $desa_data['kelurahan'] ?? '' );
		}
	}
}

/**
 * --- FUNGSI DIPINDAHKAN DARI page-pedagang.php ---
 * Handler untuk menghapus permanen data pedagang (Hanya Super Admin).
 * Dipanggil oleh `dw_pedagang_page_render()` dan `dw_api_admin_delete_pedagang()`.
 */
function dw_pedagang_delete_handler() {
    // Pastikan ini adalah aksi delete dan ID ada
    // (Pengecekan nonce dan ID dilakukan oleh fungsi pemanggil)
    $pedagang_id = absint($_GET['id'] ?? 0);
    
    // Jika ID dari API, ambil dari parameter request
    if ($pedagang_id === 0 && isset($request['id'])) {
         $pedagang_id = absint($request['id']);
    }

    if ($pedagang_id === 0) {
        add_settings_error('dw_pedagang_notices', 'pedagang_no_id', 'ID Toko tidak ditemukan untuk dihapus.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30);
        return;
    }

    if (!current_user_can('administrator')) {
        wp_die('Anda tidak memiliki izin (Hanya Super Admin).');
        exit;
    }

    global $wpdb;
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_transaksi = $wpdb->prefix . 'dw_transaksi';
    $table_transaksi_item = $wpdb->prefix . 'dw_transaksi_item';

    $pedagang = $wpdb->get_row($wpdb->prepare("SELECT id_user, nama_toko FROM $table_pedagang WHERE id = %d", $pedagang_id));
    if (!$pedagang) {
        add_settings_error('dw_pedagang_notices', 'pedagang_not_found', 'Data Toko tidak ditemukan.', 'error');
        set_transient('settings_errors', get_settings_errors(), 30);
        return;
    }
    $user_id = absint($pedagang->id_user);

    // Hapus produk CPT
    $produk_posts = get_posts([
        'post_type' => 'dw_produk', 'author' => $user_id, 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any'
    ]);
    foreach ($produk_posts as $post_id) {
        wp_delete_post($post_id, true); // true = force delete
    }

    // Hapus transaksi penjualan terkait
    $transaksi_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_transaksi WHERE id_pedagang = %d", $pedagang_id));
    if (!empty($transaksi_ids)) {
        $ids_placeholder = implode( ',', array_fill( 0, count( $transaksi_ids ), '%d' ) );
        if (!empty($ids_placeholder)) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM $table_transaksi_item WHERE id_transaksi IN ($ids_placeholder)", $transaksi_ids ) );
        }
        $wpdb->delete($table_transaksi, ['id_pedagang' => $pedagang_id], ['%d']);
    }

    // Hapus data pedagang itu sendiri
    $wpdb->delete($table_pedagang, ['id' => $pedagang_id], ['%d']);

    // --- PERUBAHAN LOGIKA HAPUS ---
    // Hapus user WordPress terkait
    if ($user_id > 0) {
        // require_once(ABSPATH . 'wp-admin/includes/user.php'); // (Sebaiknya dimuat jika belum)
        $user = get_userdata($user_id);
        if ($user) {
            // Jangan hapus user, ubah role-nya saja
            $user->remove_role('pedagang');
            if (empty($user->roles)) {
                $user->add_role('subscriber'); // Jadikan subscriber jika tidak punya role lain
            }
        }
    }
    // --- AKHIR PERUBAHAN LOGIKA HAPUS ---

    // Log aktivitas
    if (function_exists('dw_log_activity')) {
        // --- PERUBAHAN: Update pesan log ---
        dw_log_activity('PEDAGANG_DELETED_PERMANENTLY', "Toko '{$pedagang->nama_toko}' (#{$pedagang_id}) dihapus permanen oleh Admin #".get_current_user_id().". Akun pengguna #{$user_id} tetap ada sebagai subscriber.", get_current_user_id());
    }

    // --- PERUBAHAN: Update pesan sukses ---
    add_settings_error('dw_pedagang_notices', 'pedagang_deleted', 'Toko berhasil dihapus permanen. Akun pengguna terkait TIDAK dihapus, hanya diubah rolenya menjadi Subscriber.', 'success');
    set_transient('settings_errors', get_settings_errors(), 30);
    // Redirect ditangani oleh fungsi pemanggil (dw_pedagang_page_render atau API)
}