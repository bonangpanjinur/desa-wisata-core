<?php
/**
 * File Name:   meta-boxes.php
 * File Folder: includes/
 * File Path:   includes/meta-boxes.php
 *
 * --- PERUBAHAN (REKOMENDASI MVP+) ---
 * - Poin 1: Menambahkan dropdown "Profil Pengiriman" di `dw_produk_details_meta_box_html`.
 * - Poin 1: Menambahkan logika penyimpanan untuk `_dw_shipping_profile` di `dw_save_produk_meta_box_data`.
 *
 * PERBAIKAN KRITIS:
 * - Memperbaiki fungsi dw_save_produk_meta_box_data() untuk memastikan
 * ID Pedagang (id_user) yang dipilih di meta box disimpan ke kolom post_author.
 * - Memperbarui meta box Wisata dengan field baru (Video URL, Atraksi Terdekat, Harga Teks).
 * - BARU: Menambahkan PREVIEW Galeri dan Video di meta box Wisata.
 *
 * PERBAIKAN V3.2.2 (FATAL ERROR FIX):
 * - Menghapus satu kurung kurawal penutup `}` yang tersesat di akhir file
 * yang menyebabkan PHP Parse Error (500 Internal Server Error).
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Hook untuk registrasi meta box telah dipindahkan ke admin-ui-tweaks.php

/**
 * Helper untuk mendapatkan URL embed video (khususnya YouTube/Vimeo).
 * @param string $url URL video.
 * @return string|null URL embed yang di-format atau null jika tidak dikenal.
 */
function dw_get_embed_video_url($url) {
    if (empty($url)) return null;

    // YouTube
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $url, $match)) {
        return "https://www.youtube.com/embed/" . $match[1];
    }
    // Vimeo
    if (preg_match('/vimeo\.com\/(\d+)/i', $url, $match)) {
        return "https://player.vimeo.com/video/" . $match[1];
    }
    // Jika tidak dikenal, kembalikan null atau URL asli
    return null; 
}


/**
 * Tampilan HTML untuk meta box detail produk.
 * **PERBAIKAN KRITIS: Menambahkan Dropdown Pedagang.**
 * **PERUBAHAN Poin 1: Menambahkan Dropdown Profil Pengiriman.**
 */
function dw_produk_details_meta_box_html($post) {
    wp_nonce_field('dw_save_produk_meta_box_data', 'dw_produk_meta_box_nonce');
    global $wpdb;

    $harga_dasar = get_post_meta($post->ID, '_dw_harga_dasar', true);
    $stok = get_post_meta($post->ID, '_dw_stok', true);
    $catatan_ongkir = get_post_meta($post->ID, '_dw_catatan_ongkir', true);
    $gallery_ids_str = get_post_meta($post->ID, '_dw_galeri_foto', true);
    $variations = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_produk_variasi WHERE id_produk = %d ORDER BY id ASC", $post->ID));
    $current_author_id = $post->post_author;

    // Ambil daftar Pedagang yang statusnya aktif
    $pedagang_list = $wpdb->get_results("SELECT id_user, nama_toko FROM {$wpdb->prefix}dw_pedagang WHERE status_akun = 'aktif' ORDER BY nama_toko ASC");
    
    // --- Poin 1: Ambil data profil pengiriman ---
    $shipping_profiles = [];
    $saved_shipping_profile_key = get_post_meta($post->ID, '_dw_shipping_profile', true);
    if ($current_author_id > 0) {
        $profiles_json = $wpdb->get_var($wpdb->prepare("SELECT shipping_profiles FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $current_author_id));
        if (!empty($profiles_json)) {
            $shipping_profiles = json_decode($profiles_json, true);
            if (!is_array($shipping_profiles)) {
                $shipping_profiles = [];
            }
        }
    }
    // --- Akhir Poin 1 ---

    ?>
    <div class="dw-form-card-inner">

        <h3><span class="dashicons dashicons-store"></span>Relasi Pedagang</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="dw_pedagang_id_user">Pedagang / Toko</label></th>
                    <td>
                        <select name="dw_pedagang_id_user" id="dw_pedagang_id_user" required>
                            <option value="">-- Pilih Pedagang --</option>
                            <?php foreach ($pedagang_list as $pedagang) :
                                $user_data = get_userdata($pedagang->id_user);
                                if ($user_data) :
                            ?>
                                <option value="<?php echo esc_attr($pedagang->id_user); ?>" <?php selected($current_author_id, $pedagang->id_user); ?>>
                                    <?php echo esc_html($pedagang->nama_toko); ?> (<?php echo esc_html($user_data->display_name); ?>)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                        <p class="description">Pilih pedagang yang memiliki produk ini. Ini akan menyetel Penulis (Author) produk.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <hr>

        <h3><span class="dashicons dashicons-money-alt"></span>Informasi Harga & Stok</h3>
        <table class="form-table">
             <tbody>
                <tr>
                    <th><label for="dw_harga_dasar">Harga Dasar (Rp)</label></th>
                    <td><input type="number" id="dw_harga_dasar" name="dw_harga_dasar" class="regular-text" value="<?php echo esc_attr($harga_dasar); ?>" step="100" min="0" placeholder="Harga terendah/dasar">
                    <p class="description">Gunakan harga ini jika produk tidak memiliki variasi (ukuran, warna, dll).</p></td>
                </tr>
                <tr>
                    <th><label for="dw_stok">Stok Total</label></th>
                    <td><input type="number" id="dw_stok" name="dw_stok" class="regular-text" value="<?php echo esc_attr($stok); ?>" step="1" min="0" placeholder="Kosongkan jika tidak terbatas">
                    <p class="description">Abaikan jika stok diatur per variasi.</p></td>
                </tr>
            </tbody>
        </table>

        <hr>
        <h3><span class="dashicons dashicons-admin-settings"></span>Produk dengan Variasi</h3>
        <p class="description">Gunakan bagian ini jika produk punya variasi (misal: ukuran, warna). Jika diisi, **Harga Dasar dan Stok Total di atas akan diabaikan**.</p>
        <div id="dw-variations-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50%;">Deskripsi Variasi (Contoh: Ukuran S)</th>
                        <th style="width: 25%;">Harga Variasi (Rp)</th>
                        <th style="width: 20%;">Stok Variasi</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody id="dw-variations-container">
                     <?php if (!empty($variations)) : foreach ($variations as $var) : ?>
                    <tr class="dw-variation-row">
                        <td><input type="hidden" name="variation_id[]" value="<?php echo esc_attr($var->id); ?>"><input type="text" name="variation_desc[]" value="<?php echo esc_attr($var->deskripsi_variasi); ?>" placeholder="Deskripsi Variasi"></td>
                        <td><input type="number" name="variation_price[]" value="<?php echo esc_attr($var->harga_variasi); ?>" placeholder="Harga Variasi"></td>
                        <td><input type="number" name="variation_stock[]" value="<?php echo esc_attr($var->stok_variasi); ?>" placeholder="Stok (opsional)"></td>
                        <td><a href="#" class="button-link-delete dw-remove-variation" title="Hapus Variasi"><span class="dashicons dashicons-trash"></span></a></td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr class="dw-variation-row-empty"><td colspan="4">Klik "Tambah Variasi" untuk memulai.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="button" class="button button-secondary" id="dw-add-variation-button" style="margin-top: 10px;">+ Tambah Variasi</button>
        </div>

        <hr>
        <h3><span class="dashicons dashicons-format-gallery"></span>Media & Pengiriman</h3>
         <table class="form-table">
            <tbody>
                <tr>
                    <th><label>Galeri Foto</label></th>
                    <td>
                        <div class="dw-gallery-wrapper">
                            <div class="dw-gallery-preview">
                                <?php
                                $gallery_ids = !empty($gallery_ids_str) ? explode(',', $gallery_ids_str) : [];
                                if (!empty($gallery_ids) && !empty($gallery_ids[0])) { // Check if not empty before looping
                                    foreach ($gallery_ids as $image_id) {
                                        $thumb_url = wp_get_attachment_thumb_url($image_id);
                                        if ($thumb_url) { // Make sure image exists
                                            echo '<div class="gallery-item" data-id="' . esc_attr($image_id) . '"><img src="' . esc_url($thumb_url) . '"/><a href="#" class="dw-remove-gallery-item">×</a></div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                            <input type="hidden" name="dw_galeri_foto" class="dw-gallery-ids" value="<?php echo esc_attr($gallery_ids_str); ?>">
                            <button type="button" class="button dw-gallery-button">Tambah/Kelola Galeri</button>
                        </div>
                         <p class="description">Pilih gambar-gambar untuk menampilkan produk dari berbagai sisi.</p>
                    </td>
                </tr>
                <!-- Poin 1: Tambah Dropdown Profil Pengiriman -->
                <tr>
                    <th><label for="dw_shipping_profile">Profil Pengiriman</label></th>
                    <td>
                        <select name="dw_shipping_profile" id="dw_shipping_profile">
                            <option value="">— Gunakan Opsi Flat Rate Nasional Default —</option>
                            <?php if (!empty($shipping_profiles) && is_array($shipping_profiles)): ?>
                                <?php foreach ($shipping_profiles as $key => $profile): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($saved_shipping_profile_key, $key); ?>>
                                        <?php echo esc_html($profile['nama'] ?? $key); ?> (Rp <?php echo number_format($profile['harga'] ?? 0); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Pedagang ini belum membuat profil pengiriman.</option>
                            <?php endif; ?>
                        </select>
                        <p class="description">Pilih profil pengiriman yang sesuai untuk produk ini. Profil dikelola di halaman "Edit Toko" pedagang.</p>
                        <p class="description">Jika tidak dipilih, akan menggunakan "Flat Rate Nasional" standar milik pedagang.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="dw_catatan_ongkir">Catatan Pengiriman</label></th>
                    <td><textarea id="dw_catatan_ongkir" name="dw_catatan_ongkir" rows="3" class="large-text"><?php echo esc_textarea($catatan_ongkir); ?></textarea>
                    <p class="description">Contoh: "Pengiriman hanya setiap hari Senin & Kamis".</p></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Template untuk baris variasi baru (hidden) -->
    <script type="text/html" id="dw-variation-row-template">
        <tr class="dw-variation-row">
            <td><input type="hidden" name="variation_id[]" value="0"><input type="text" name="variation_desc[]" value="" placeholder="Deskripsi Variasi"></td>
            <td><input type="number" name="variation_price[]" value="" placeholder="Harga Variasi"></td>
            <td><input type="number" name="variation_stock[]" value="" placeholder="Stok (opsional)"></td>
            <td><a href="#" class="button-link-delete dw-remove-variation" title="Hapus Variasi"><span class="dashicons dashicons-trash"></span></a></td>
        </tr>
    </script>
    <?php
}

/**
 * Tampilan HTML untuk meta box detail wisata.
 * **PERUBAHAN: Menambahkan field Video URL & Atraksi Terdekat + PREVIEW.**
 */
function dw_wisata_details_meta_box_html($post) {
    wp_nonce_field('dw_save_wisata_meta_box_data', 'dw_wisata_meta_box_nonce');

    // Mengambil semua data meta
    $desa_id = get_post_meta($post->ID, '_dw_id_desa', true);
    $harga_tiket = get_post_meta($post->ID, '_dw_harga_tiket', true);
    $jam_buka = get_post_meta($post->ID, '_dw_jam_buka', true);
    $hari_buka = get_post_meta($post->ID, '_dw_hari_buka', true);
    $kontak = get_post_meta($post->ID, '_dw_kontak', true);
    $alamat = get_post_meta($post->ID, '_dw_alamat', true);
    $koordinat = get_post_meta($post->ID, '_dw_koordinat', true);
    $url_google_maps = get_post_meta($post->ID, '_dw_url_google_maps', true);
    $url_website = get_post_meta($post->ID, '_dw_url_website', true);
    $gallery_ids_str = get_post_meta($post->ID, '_dw_galeri_foto', true);
    // BARU: Ambil nilai field baru
    $video_url = get_post_meta($post->ID, '_dw_video_url', true);
    $nearby_attractions = get_post_meta($post->ID, '_dw_nearby_attractions', true);
    $embed_url = dw_get_embed_video_url($video_url); // Dapatkan URL embed yang diformat

    $fasilitas_raw = get_post_meta($post->ID, '_dw_fasilitas', true);
    $fasilitas = is_array($fasilitas_raw) ? implode("\n", $fasilitas_raw) : ''; // Ubah array ke string untuk textarea

    $media_sosial_raw = get_post_meta($post->ID, '_dw_media_sosial', true);
    $media_sosial = '';
    if (is_array($media_sosial_raw)) {
        foreach ($media_sosial_raw as $key => $value) {
            $media_sosial .= esc_attr($key) . ':' . esc_url($value) . "\n"; // Format ulang untuk textarea
        }
    }

    global $wpdb;
    $desa_list = $wpdb->get_results("SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif' ORDER BY nama_desa ASC");
    ?>
    <div class="dw-form-card-inner">
        <h3><span class="dashicons dashicons-location-alt"></span>Relasi & Lokasi</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="dw_id_desa">Desa Induk</label></th>
                    <td>
                        <select name="dw_id_desa" id="dw_id_desa" required>
                            <option value="">-- Pilih Desa --</option>
                            <?php foreach ($desa_list as $desa) : ?>
                                <option value="<?php echo esc_attr($desa->id); ?>" <?php selected($desa_id, $desa->id); ?>>
                                    <?php echo esc_html($desa->nama_desa); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Pilih desa tempat wisata ini berada. **Wajib diisi**.</p>
                    </td>
                </tr>
                 <tr>
                    <th><label for="dw_alamat">Alamat Lengkap</label></th>
                    <td><textarea id="dw_alamat" name="dw_alamat" rows="3" class="large-text"><?php echo esc_textarea($alamat); ?></textarea>
                    <p class="description">Alamat spesifik lokasi wisata jika berbeda dari alamat desa.</p></td>
                </tr>
                <tr>
                    <th><label for="dw_koordinat">Koordinat</label></th>
                    <td><input type="text" id="dw_koordinat" name="dw_koordinat" value="<?php echo esc_attr($koordinat); ?>" placeholder="Contoh: -7.12345, 107.12345">
                     <p class="description">Format: latitude,longitude</p></td>
                </tr>
                <tr>
                    <th><label for="dw_url_google_maps">URL Google Maps</label></th>
                    <td><input type="url" id="dw_url_google_maps" name="dw_url_google_maps" value="<?php echo esc_attr($url_google_maps); ?>" class="large-text" placeholder="https://maps.app.goo.gl/xxxxxx"></td>
                </tr>
            </tbody>
        </table>
        <hr>
        <h3><span class="dashicons dashicons-info-outline"></span>Informasi Operasional</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="dw_harga_tiket">Harga Tiket Masuk</label></th>
                    <td><input type="text" id="dw_harga_tiket" name="dw_harga_tiket" value="<?php echo esc_attr($harga_tiket); ?>" placeholder="Contoh: Weekday Rp 10rb, Weekend Rp 15rb atau Gratis">
                    <p class="description">Bisa berupa angka (10000) atau teks (Gratis, Weekday Rp X).</p></td>
                </tr>
                <tr>
                    <th><label for="dw_jam_buka">Jam Buka</label></th>
                    <td><input type="text" id="dw_jam_buka" name="dw_jam_buka" value="<?php echo esc_attr($jam_buka); ?>" placeholder="Contoh: 08:00 - 17:00"></td>
                </tr>
                <tr>
                    <th><label for="dw_hari_buka">Hari Buka</label></th>
                    <td><input type="text" id="dw_hari_buka" name="dw_hari_buka" value="<?php echo esc_attr($hari_buka); ?>" placeholder="Contoh: Setiap Hari atau Senin - Jumat"></td>
                </tr>
                 <tr>
                    <th><label for="dw_fasilitas">Fasilitas</label></th>
                    <td><textarea id="dw_fasilitas" name="dw_fasilitas" rows="4" class="large-text" placeholder="Satu fasilitas per baris..."><?php echo esc_textarea($fasilitas); ?></textarea>
                    <p class="description">Pisahkan setiap fasilitas dengan baris baru (Enter).</p></td>
                </tr>
                <tr>
                    <th><label for="dw_nearby_attractions">Atraksi Terdekat</label></th>
                    <td><textarea id="dw_nearby_attractions" name="dw_nearby_attractions" rows="4" class="large-text" placeholder="Satu atraksi/tempat menarik per baris..."><?php echo esc_textarea($nearby_attractions); ?></textarea>
                    <p class="description">Sebutkan tempat menarik lain di sekitar lokasi wisata ini.</p></td>
                </tr>
            </tbody>
        </table>
        <hr>
        <h3><span class="dashicons dashicons-share"></span>Kontak & Media</h3>
        <table class="form-table">
            <tbody>
                 <tr>
                    <th><label for="dw_kontak">Kontak Pengelola</label></th>
                    <td><input type="text" id="dw_kontak" name="dw_kontak" value="<?php echo esc_attr($kontak); ?>" placeholder="Nomor Telepon/WA"></td>
                </tr>
                <tr>
                    <th><label for="dw_url_website">URL Website</label></th>
                    <td><input type="url" id="dw_url_website" name="dw_url_website" value="<?php echo esc_attr($url_website); ?>" class="large-text" placeholder="https://contoh-wisata.com"></td>
                </tr>
                <tr>
                    <th><label for="dw_media_sosial">Media Sosial</label></th>
                    <td><textarea id="dw_media_sosial" name="dw_media_sosial" rows="3" class="large-text" placeholder="instagram: https://instagram.com/akun&#10;facebook: https://facebook.com/akun"><?php echo esc_textarea(trim($media_sosial)); ?></textarea>
                    <p class="description">Gunakan format `nama:url` per baris. Contoh: `instagram:https://...`</p></td>
                </tr>
                 <tr>
                    <th><label for="dw_video_url">URL Video Promo</label></th>
                    <td>
                        <input type="url" id="dw_video_url" name="dw_video_url" value="<?php echo esc_attr($video_url); ?>" class="large-text" placeholder="https://youtube.com/watch?v=xxxxxx">
                        <p class="description">URL video dari YouTube atau Vimeo.</p>
                        <?php if ($embed_url) : ?>
                            <div style="margin-top: 15px; max-width: 450px;">
                                <h4>Preview Video</h4>
                                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
                                    <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                                            src="<?php echo esc_url($embed_url); ?>" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen>
                                    </iframe>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                 <tr>
                    <th><label>Galeri Foto</label></th>
                     <td>
                        <div class="dw-gallery-wrapper">
                            <div class="dw-gallery-preview">
                                 <?php
                                $gallery_ids = !empty($gallery_ids_str) ? explode(',', $gallery_ids_str) : [];
                                if (!empty($gallery_ids) && !empty($gallery_ids[0])) { // Check if not empty before looping
                                    foreach ($gallery_ids as $image_id) {
                                         $thumb_url = wp_get_attachment_thumb_url($image_id);
                                        if ($thumb_url) { // Make sure image exists
                                            echo '<div class="gallery-item" data-id="' . esc_attr($image_id) . '"><img src="' . esc_url($thumb_url) . '"/><a href="#" class="dw-remove-gallery-item">×</a></div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                            <input type="hidden" name="dw_galeri_foto" class="dw-gallery-ids" value="<?php echo esc_attr($gallery_ids_str); ?>">
                            <button type="button" class="button dw-gallery-button">Tambah/Kelola Galeri</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Menyimpan data dari meta box produk.
 * **PERBAIKAN KRITIS: Menyimpan ID Pedagang ke post_author.**
 * **PERUBAHAN Poin 1: Menyimpan _dw_shipping_profile.**
 */
function dw_save_produk_meta_box_data($post_id) {
    if (!isset($_POST['dw_produk_meta_box_nonce']) || !wp_verify_nonce($_POST['dw_produk_meta_box_nonce'], 'dw_save_produk_meta_box_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    // Cek kapabilitas harus di sini, jika user tidak bisa edit post ini, keluar.
    if (isset($_POST['post_type']) && 'dw_produk' == $_POST['post_type'] && !current_user_can('edit_post', $post_id)) return;

    global $wpdb;

    // --- PERBAIKAN KRITIS: Simpan Post Author ---
    // 1. UPDATE POST AUTHOR (Pedagang) - Menggunakan wpdb untuk menghindari rekursif save_post
    if (isset($_POST['dw_pedagang_id_user'])) {
        $new_author_id = absint($_POST['dw_pedagang_id_user']);

        // Ambil data post saat ini untuk dibandingkan
        $current_post_author = $wpdb->get_var($wpdb->prepare("SELECT post_author FROM {$wpdb->posts} WHERE ID = %d", $post_id));

        // Hanya update jika Author berubah dan Author ID > 0
        if ($new_author_id > 0 && $current_post_author != $new_author_id) {
             $wpdb->update(
                $wpdb->posts,
                [ 'post_author' => $new_author_id ],
                [ 'ID' => $post_id ],
                [ '%d' ],
                [ '%d' ]
            );
            // Log aktivitas jika fungsi tersedia
            if(function_exists('dw_log_activity')){
                dw_log_activity('PRODUK_AUTHOR_UPDATED', "Penulis Produk #{$post_id} diubah menjadi User ID: {$new_author_id}", get_current_user_id());
            }
        }
    }
    // --- AKHIR PERBAIKAN ---

    // Simpan data meta utama
    update_post_meta($post_id, '_dw_harga_dasar', isset($_POST['dw_harga_dasar']) ? floatval($_POST['dw_harga_dasar']) : 0);
    update_post_meta($post_id, '_dw_stok', isset($_POST['dw_stok']) ? sanitize_text_field($_POST['dw_stok']) : ''); // Simpan sebagai string, kosong jika tak terbatas
    update_post_meta($post_id, '_dw_catatan_ongkir', isset($_POST['dw_catatan_ongkir']) ? sanitize_textarea_field($_POST['dw_catatan_ongkir']) : '');
    update_post_meta($post_id, '_dw_galeri_foto', isset($_POST['dw_galeri_foto']) ? sanitize_text_field($_POST['dw_galeri_foto']) : '');
    
    // --- Poin 1: Simpan Profil Pengiriman ---
    update_post_meta($post_id, '_dw_shipping_profile', isset($_POST['dw_shipping_profile']) ? sanitize_text_field($_POST['dw_shipping_profile']) : '');


    // Simpan data variasi
    $table_name = $wpdb->prefix . 'dw_produk_variasi';
    $existing_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_name WHERE id_produk = %d", $post_id));
    $submitted_ids = [];

    if (isset($_POST['variation_desc']) && is_array($_POST['variation_desc'])) {
        for ($i = 0; $i < count($_POST['variation_desc']); $i++) {
            // Hanya proses jika deskripsi dan harga ada
            if (!empty($_POST['variation_desc'][$i]) && isset($_POST['variation_price'][$i]) && $_POST['variation_price'][$i] !== '') {
                $var_id = isset($_POST['variation_id'][$i]) ? absint($_POST['variation_id'][$i]) : 0;
                $data = [
                    'id_produk' => $post_id,
                    'deskripsi_variasi' => sanitize_text_field($_POST['variation_desc'][$i]),
                    'harga_variasi' => floatval($_POST['variation_price'][$i]),
                    // Simpan stok sebagai NULL jika input kosong, atau integer jika diisi
                    'stok_variasi'  => (isset($_POST['variation_stock'][$i]) && $_POST['variation_stock'][$i] !== '') ? absint($_POST['variation_stock'][$i]) : null
                ];
                if ($var_id > 0 && in_array($var_id, $existing_ids)) {
                    $wpdb->update($table_name, $data, ['id' => $var_id]);
                    $submitted_ids[] = $var_id;
                } else {
                    // Hanya insert jika data valid (deskripsi & harga ada)
                     if (!empty($data['deskripsi_variasi']) && $data['harga_variasi'] > 0) {
                         $wpdb->insert($table_name, $data);
                         $new_var_id = $wpdb->insert_id;
                         if ($new_var_id) $submitted_ids[] = $new_var_id; // Tambahkan ID baru ke submitted
                     }
                }
            }
        }
    }
    // Hapus variasi yang ada di DB tapi tidak disubmit lagi
    $ids_to_delete = array_diff($existing_ids, $submitted_ids);
    if (!empty($ids_to_delete)) {
        // Pastikan ID valid sebelum query delete
        $valid_ids_to_delete = array_map('absint', $ids_to_delete);
        $valid_ids_to_delete = array_filter($valid_ids_to_delete, function($id){ return $id > 0; });
        if(!empty($valid_ids_to_delete)){
             $wpdb->query("DELETE FROM $table_name WHERE id IN (" . implode(',', $valid_ids_to_delete) . ")");
        }
    }
}
// Hook save_post sudah ada di bawah

/**
 * Menyimpan data dari meta box wisata.
 * **PERUBAHAN: Menambahkan penyimpanan field baru.**
 */
function dw_save_wisata_meta_box_data($post_id) {
    if (!isset($_POST['dw_wisata_meta_box_nonce']) || !wp_verify_nonce($_POST['dw_wisata_meta_box_nonce'], 'dw_save_wisata_meta_box_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['post_type']) && 'dw_wisata' == $_POST['post_type'] && !current_user_can('edit_post', $post_id)) return;

    // Daftar field disesuaikan dengan nama di form HTML (prefix 'dw_')
    $fields_to_save = [
        '_dw_id_desa' => 'dw_id_desa',
        '_dw_harga_tiket' => 'dw_harga_tiket', // Simpan sebagai teks
        '_dw_jam_buka' => 'dw_jam_buka',
        '_dw_hari_buka' => 'dw_hari_buka',
        '_dw_kontak' => 'dw_kontak',
        '_dw_alamat' => 'dw_alamat',
        '_dw_koordinat' => 'dw_koordinat',
        '_dw_url_google_maps' => 'dw_url_google_maps',
        '_dw_url_website' => 'dw_url_website',
        '_dw_galeri_foto' => 'dw_galeri_foto',
        '_dw_video_url' => 'dw_video_url', // BARU
        '_dw_nearby_attractions' => 'dw_nearby_attractions', // BARU (disimpan sebagai teks)
    ];

    foreach ($fields_to_save as $meta_key => $post_key) {
        if (isset($_POST[$post_key])) {
            // Sanitasi berbeda untuk URL dan Textarea
            if (in_array($post_key, ['dw_url_google_maps', 'dw_url_website', 'dw_video_url'])) {
                 $value = esc_url_raw($_POST[$post_key]);
            } elseif (in_array($post_key, ['dw_alamat', 'dw_nearby_attractions'])) {
                 $value = sanitize_textarea_field($_POST[$post_key]);
            } else {
                 $value = sanitize_text_field($_POST[$post_key]);
            }
            update_post_meta($post_id, $meta_key, $value);
        } else {
            // Hapus meta jika field tidak dikirim (misal: dikosongkan)
            delete_post_meta($post_id, $meta_key);
        }
    }

    // Menyimpan field fasilitas (textarea)
    if (isset($_POST['dw_fasilitas'])) {
        $fasilitas_arr = array_filter(array_map('sanitize_text_field', explode("\n", $_POST['dw_fasilitas'])));
        update_post_meta($post_id, '_dw_fasilitas', $fasilitas_arr);
    } else {
         delete_post_meta($post_id, '_dw_fasilitas'); // Hapus jika dikosongkan
    }

    // Menyimpan media sosial (textarea dengan format key:value)
    if (isset($_POST['dw_media_sosial'])) {
        $media_sosial_arr = [];
        $lines = explode("\n", trim($_POST['dw_media_sosial']));
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2 && !empty(trim($parts[0])) && !empty(trim($parts[1]))) {
                $media_sosial_arr[sanitize_key(trim($parts[0]))] = esc_url_raw(trim($parts[1]));
            }
        }
        update_post_meta($post_id, '_dw_media_sosial', $media_sosial_arr);
    } else {
         delete_post_meta($post_id, '_dw_media_sosial'); // Hapus jika dikosongkan
    }
}

// Daftarkan hook save_post untuk kedua CPT
add_action('save_post_dw_produk', 'dw_save_produk_meta_box_data');
add_action('save_post_dw_wisata', 'dw_save_wisata_meta_box_data');

// PERBAIKAN: Kurung kurawal `}` yang tersesat di sini telah dihapus.
?>