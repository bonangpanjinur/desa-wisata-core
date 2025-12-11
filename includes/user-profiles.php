<?php
/**
 * File Name:   user-profiles.php
 * File Folder: includes/
 * File Path:   includes/user-profiles.php
 *
 * Menambahkan field alamat terstruktur ke halaman profil pengguna WordPress.
 *
 * PERBAIKAN:
 * - Memperbaiki `dw_save_user_address_fields` agar memetakan
 * `$_POST['desa_nama']` (dari hidden input) ke `$_POST['kelurahan']`
 * sebelum menyimpan ke meta.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menampilkan field alamat di halaman profil pengguna.
 *
 * @param WP_User $user Objek user.
 */
function dw_show_user_address_fields( $user ) {
    // Hanya tampilkan untuk role tertentu
    $allowed_roles = ['administrator', 'admin_kabupaten', 'admin_desa', 'penjual'];
    if ( ! array_intersect( $allowed_roles, $user->roles ) ) {
        return;
    }

    $provinsi_id  = get_user_meta( $user->ID, 'provinsi_id', true );
    $kabupaten_id = get_user_meta( $user->ID, 'kabupaten_id', true );
    $kecamatan_id = get_user_meta( $user->ID, 'kecamatan_id', true );
    $kelurahan_id = get_user_meta( $user->ID, 'kelurahan_id', true );

    // Ambil data awal untuk dropdown
    $provinsi_list  = dw_get_api_provinsi();
    $kabupaten_list = !empty($provinsi_id) ? dw_get_api_kabupaten($provinsi_id) : [];
    $kecamatan_list = !empty($kabupaten_id) ? dw_get_api_kecamatan($kabupaten_id) : [];
    $desa_list      = !empty($kecamatan_id) ? dw_get_api_desa($kecamatan_id) : [];
    ?>
    <h3>Alamat Pengguna</h3>
    <div class="dw-address-wrapper"> <!-- Wrapper untuk JS -->
        <table class="form-table">
            <tr>
                <th><label for="dw_provinsi">Provinsi</label></th>
                <td><select name="provinsi_id" id="dw_provinsi" class="dw-provinsi-select" style="width: 100%;">
                    <option value="">-- Pilih Provinsi --</option>
                    <?php foreach ($provinsi_list as $prov) : ?>
                        <option value="<?php echo esc_attr($prov['code']); ?>" <?php selected($provinsi_id, $prov['code']); ?>><?php echo esc_html($prov['name']); ?></option>
                    <?php endforeach; ?>
                </select></td>
            </tr>
             <tr>
                <th><label for="dw_kabupaten">Kabupaten/Kota</label></th>
                <td><select name="kabupaten_id" id="dw_kabupaten" class="dw-kabupaten-select" <?php disabled(empty($kabupaten_list)); ?> style="width: 100%;">
                    <option value="">Pilih Provinsi Dulu</option>
                    <?php foreach ($kabupaten_list as $kab) : ?>
                        <option value="<?php echo esc_attr($kab['code']); ?>" <?php selected($kabupaten_id, $kab['code']); ?>><?php echo esc_html($kab['name']); ?></option>
                    <?php endforeach; ?>
                </select></td>
            </tr>
            <tr>
                <th><label for="dw_kecamatan">Kecamatan</label></th>
                <td><select name="kecamatan_id" id="dw_kecamatan" class="dw-kecamatan-select" <?php disabled(empty($kecamatan_list)); ?> style="width: 100%;">
                    <option value="">Pilih Kabupaten Dulu</option>
                     <?php foreach ($kecamatan_list as $kec) : ?>
                        <option value="<?php echo esc_attr($kec['code']); ?>" <?php selected($kecamatan_id, $kec['code']); ?>><?php echo esc_html($kec['name']); ?></option>
                    <?php endforeach; ?>
                </select></td>
            </tr>
             <tr>
                <th><label for="dw_desa">Desa/Kelurahan</label></th>
                <td><select name="kelurahan_id" id="dw_desa" class="dw-desa-select" <?php disabled(empty($desa_list)); ?> style="width: 100%;">
                    <option value="">Pilih Kecamatan Dulu</option>
                     <?php foreach ($desa_list as $desa) : ?>
                        <option value="<?php echo esc_attr($desa['code']); ?>" <?php selected($kelurahan_id, $desa['code']); ?>><?php echo esc_html($desa['name']); ?></option>
                    <?php endforeach; ?>
                </select></td>
            </tr>
        </table>
        <!-- Hidden inputs -->
        <input type="hidden" class="dw-provinsi-nama" name="provinsi_nama" value="<?php echo esc_attr(get_user_meta( $user->ID, 'provinsi', true )); ?>">
        <input type="hidden" class="dw-kabupaten-nama" name="kabupaten_nama" value="<?php echo esc_attr(get_user_meta( $user->ID, 'kabupaten', true )); ?>">
        <input type="hidden" class="dw-kecamatan-nama" name="kecamatan_nama" value="<?php echo esc_attr(get_user_meta( $user->ID, 'kecamatan', true )); ?>">
        <input type="hidden" class="dw-desa-nama" name="desa_nama" value="<?php echo esc_attr(get_user_meta( $user->ID, 'kelurahan', true )); ?>">
    </div>
    <?php
}
add_action( 'show_user_profile', 'dw_show_user_address_fields' );
add_action( 'edit_user_profile', 'dw_show_user_address_fields' );

/**
 * Menyimpan data alamat dari halaman profil pengguna.
 *
 * @param int $user_id ID pengguna yang sedang diupdate.
 */
function dw_save_user_address_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    $fields = [
        'provinsi_id', 'kabupaten_id', 'kecamatan_id', 'kelurahan_id',
        'provinsi', 'kabupaten', 'kecamatan', 'kelurahan'
    ];

    // --- PERBAIKAN ---
    // Memetakan input hidden 'desa_nama' ke 'kelurahan' agar bisa disimpan oleh loop di bawah.
    $_POST['provinsi'] = sanitize_text_field($_POST['provinsi_nama'] ?? '');
    $_POST['kabupaten'] = sanitize_text_field($_POST['kabupaten_nama'] ?? '');
    $_POST['kecamatan'] = sanitize_text_field($_POST['kecamatan_nama'] ?? '');
    $_POST['kelurahan'] = sanitize_text_field($_POST['desa_nama'] ?? '');
    // --- AKHIR PERBAIKAN ---

    foreach($fields as $field) {
        if(isset($_POST[$field])) {
            update_user_meta( $user_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
    }
}
add_action( 'personal_options_update', 'dw_save_user_address_fields' );
add_action( 'edit_user_profile_update', 'dw_save_user_address_fields' );
