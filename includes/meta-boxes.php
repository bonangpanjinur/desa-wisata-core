<?php
/**
 * File Name:   meta-boxes.php
 * File Folder: includes/
 * File Path:   includes/meta-boxes.php
 * * Description: 
 * Menangani tampilan form input (Meta Box) pada halaman edit admin untuk Post Type 'dw_produk' dan 'dw_wisata'.
 * Berfungsi menghubungkan UI WordPress dengan penyimpanan ke Custom Table (dw_produk & dw_wisata).
 * * * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. REGISTRASI META BOXES
 * Mengaitkan fungsi tampilan ke Post Type yang sesuai.
 */
function dw_add_custom_meta_boxes() {
    // Meta Box untuk Produk
    add_meta_box(
        'dw_produk_data_box',           // ID unik
        'Data Detail Produk (Custom)',  // Judul
        'dw_render_produk_meta_box',    // Callback tampilan
        'dw_produk',                    // Post Type
        'normal',                       // Posisi (normal, side, advanced)
        'high'                          // Prioritas
    );

    // Meta Box untuk Wisata
    add_meta_box(
        'dw_wisata_data_box',
        'Informasi Objek Wisata (Custom)',
        'dw_render_wisata_meta_box',
        'dw_wisata',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'dw_add_custom_meta_boxes' );


/**
 * 2. TAMPILAN META BOX: PRODUK
 */
function dw_render_produk_meta_box( $post ) {
    global $wpdb;

    // Security Nonce
    wp_nonce_field( 'dw_save_custom_data', 'dw_meta_box_nonce' );

    // A. Ambil Data Eksisting dari Custom Table
    // Kita asumsikan ada kolom 'post_id' di tabel dw_produk untuk menghubungkan CPT dengan Table.
    // Jika menggunakan skema full custom tanpa post_id, Anda mungkin perlu menyesuaikan query ini 
    // atau menambahkan kolom post_id ke tabel dw_produk via phpMyAdmin.
    $table_name = $wpdb->prefix . 'dw_produk';
    
    // Coba ambil data berdasarkan ID (Jika ID tabel disinkronkan) atau logika Post ID
    // Untuk keamanan integrasi CPT, idealnya tabel memiliki kolom `post_id`.
    // Fallback: Kita cek post_meta dulu jika tabel belum support, atau gunakan query ini jika sudah ada kolom post_id.
    $data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d OR slug = %s LIMIT 1", $post->ID, $post->post_name ), ARRAY_A );
    
    // Default values
    $id_pedagang = isset($data['id_pedagang']) ? $data['id_pedagang'] : get_current_user_id();
    $harga       = isset($data['harga']) ? $data['harga'] : '';
    $stok        = isset($data['stok']) ? $data['stok'] : '';
    $berat       = isset($data['berat_gram']) ? $data['berat_gram'] : '';
    $kondisi     = isset($data['kondisi']) ? $data['kondisi'] : 'baru';
    $status      = isset($data['status']) ? $data['status'] : 'aktif';

    // B. Ambil Daftar Pedagang untuk Dropdown
    $pedagang_list = $wpdb->get_results( "SELECT id, nama_toko FROM {$wpdb->prefix}dw_pedagang WHERE status_akun = 'aktif'" );
    ?>
    
    <div class="dw-meta-wrapper" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- Kolom Kiri -->
        <div class="dw-meta-col">
            <p>
                <label for="dw_id_pedagang" style="font-weight: 600; display: block; margin-bottom: 5px;">Toko / Pedagang</label>
                <select name="dw_id_pedagang" id="dw_id_pedagang" class="widefat" style="width: 100%;">
                    <option value="">-- Pilih Pedagang --</option>
                    <?php foreach ( $pedagang_list as $p ) : ?>
                        <option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $id_pedagang, $p->id ); ?>>
                            <?php echo esc_html( $p->nama_toko ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="description">Produk ini milik pedagang siapa?</span>
            </p>

            <p>
                <label for="dw_harga" style="font-weight: 600; display: block; margin-bottom: 5px;">Harga (Rp)</label>
                <input type="number" name="dw_harga" id="dw_harga" value="<?php echo esc_attr( $harga ); ?>" class="widefat" placeholder="Contoh: 15000">
            </p>

            <p>
                <label for="dw_stok" style="font-weight: 600; display: block; margin-bottom: 5px;">Stok Barang</label>
                <input type="number" name="dw_stok" id="dw_stok" value="<?php echo esc_attr( $stok ); ?>" class="widefat">
            </p>
        </div>

        <!-- Kolom Kanan -->
        <div class="dw-meta-col">
            <p>
                <label for="dw_berat" style="font-weight: 600; display: block; margin-bottom: 5px;">Berat (Gram)</label>
                <input type="number" name="dw_berat" id="dw_berat" value="<?php echo esc_attr( $berat ); ?>" class="widefat" placeholder="Contoh: 500">
            </p>

            <p>
                <label for="dw_kondisi" style="font-weight: 600; display: block; margin-bottom: 5px;">Kondisi</label>
                <select name="dw_kondisi" id="dw_kondisi" class="widefat">
                    <option value="baru" <?php selected( $kondisi, 'baru' ); ?>>Baru</option>
                    <option value="bekas" <?php selected( $kondisi, 'bekas' ); ?>>Bekas</option>
                </select>
            </p>
            
            <p>
                <label for="dw_status" style="font-weight: 600; display: block; margin-bottom: 5px;">Status Ketersediaan</label>
                <select name="dw_status" id="dw_status" class="widefat">
                    <option value="aktif" <?php selected( $status, 'aktif' ); ?>>Aktif (Dijual)</option>
                    <option value="habis" <?php selected( $status, 'habis' ); ?>>Habis Stok</option>
                    <option value="arsip" <?php selected( $status, 'arsip' ); ?>>Diarsipkan</option>
                </select>
            </p>
        </div>

    </div>
    <div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
        <small><strong>Note:</strong> Data ini disimpan di tabel khusus (<code>dw_produk</code>) untuk performa maksimal.</small>
    </div>
    <?php
}


/**
 * 3. TAMPILAN META BOX: WISATA
 */
function dw_render_wisata_meta_box( $post ) {
    global $wpdb;

    wp_nonce_field( 'dw_save_custom_data', 'dw_meta_box_nonce' );

    $table_name = $wpdb->prefix . 'dw_wisata';
    // Logic pengambilan data sama seperti produk
    $data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d OR slug = %s LIMIT 1", $post->ID, $post->post_name ), ARRAY_A );

    // Default values
    $id_desa     = isset($data['id_desa']) ? $data['id_desa'] : '';
    $harga_tiket = isset($data['harga_tiket']) ? $data['harga_tiket'] : '';
    $jam_buka    = isset($data['jam_buka']) ? $data['jam_buka'] : '';
    $kontak      = isset($data['kontak_pengelola']) ? $data['kontak_pengelola'] : '';
    $maps        = isset($data['lokasi_maps']) ? $data['lokasi_maps'] : '';

    // Ambil Daftar Desa
    $desa_list = $wpdb->get_results( "SELECT id, nama_desa FROM {$wpdb->prefix}dw_desa WHERE status = 'aktif'" );
    ?>

    <div class="dw-meta-wrapper">
        <p>
            <label for="dw_id_desa" style="font-weight: 600; display: block; margin-bottom: 5px;">Lokasi Desa Wisata</label>
            <select name="dw_id_desa" id="dw_id_desa" class="widefat">
                <option value="">-- Pilih Desa --</option>
                <?php foreach ( $desa_list as $d ) : ?>
                    <option value="<?php echo esc_attr( $d->id ); ?>" <?php selected( $id_desa, $d->id ); ?>>
                        <?php echo esc_html( $d->nama_desa ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <p>
                <label for="dw_harga_tiket" style="font-weight: 600; display: block; margin-bottom: 5px;">Harga Tiket (Rp)</label>
                <input type="number" name="dw_harga_tiket" id="dw_harga_tiket" value="<?php echo esc_attr( $harga_tiket ); ?>" class="widefat" placeholder="0 jika gratis">
            </p>

            <p>
                <label for="dw_jam_buka" style="font-weight: 600; display: block; margin-bottom: 5px;">Jam Operasional</label>
                <input type="text" name="dw_jam_buka" id="dw_jam_buka" value="<?php echo esc_attr( $jam_buka ); ?>" class="widefat" placeholder="08:00 - 17:00">
            </p>
        </div>

        <p>
            <label for="dw_kontak" style="font-weight: 600; display: block; margin-bottom: 5px;">Kontak Pengelola (WA/Telp)</label>
            <input type="text" name="dw_kontak" id="dw_kontak" value="<?php echo esc_attr( $kontak ); ?>" class="widefat">
        </p>

        <p>
            <label for="dw_maps" style="font-weight: 600; display: block; margin-bottom: 5px;">Link Google Maps</label>
            <input type="url" name="dw_maps" id="dw_maps" value="<?php echo esc_url( $maps ); ?>" class="widefat" placeholder="https://maps.google.com/...">
        </p>
    </div>
    <?php
}


/**
 * 4. SAVE HANDLER (PENYIMPANAN DATA)
 * Menyimpan input dari Meta Box ke Custom Table Database.
 */
function dw_save_custom_meta_box_data( $post_id ) {
    global $wpdb;

    // 1. Cek Nonce & Permission
    if ( ! isset( $_POST['dw_meta_box_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['dw_meta_box_nonce'], 'dw_save_custom_data' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $post_type = get_post_type( $post_id );
    $post = get_post( $post_id );

    // 2. Handler untuk PRODUK
    if ( 'dw_produk' === $post_type ) {
        $table = $wpdb->prefix . 'dw_produk';
        
        // Sanitasi Input
        $data = [
            'nama_produk' => $post->post_title, // Sinkronisasi Judul
            'slug'        => $post->post_name,  // Sinkronisasi Slug
            'deskripsi'   => $post->post_content, // Sinkronisasi Deskripsi
            'id_pedagang' => isset($_POST['dw_id_pedagang']) ? absint($_POST['dw_id_pedagang']) : 0,
            'harga'       => isset($_POST['dw_harga']) ? floatval($_POST['dw_harga']) : 0,
            'stok'        => isset($_POST['dw_stok']) ? absint($_POST['dw_stok']) : 0,
            'berat_gram'  => isset($_POST['dw_berat']) ? absint($_POST['dw_berat']) : 0,
            'kondisi'     => isset($_POST['dw_kondisi']) ? sanitize_text_field($_POST['dw_kondisi']) : 'baru',
            'status'      => isset($_POST['dw_status']) ? sanitize_text_field($_POST['dw_status']) : 'aktif',
            'updated_at'  => current_time( 'mysql' )
        ];

        // Cek apakah data sudah ada (berdasarkan slug atau id jika ada mapping)
        // Disarankan menambahkan kolom 'post_id' di tabel dw_produk untuk akurasi 100%.
        // Di sini kita pakai slug sebagai kunci unik sementara.
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $post->post_name ) );

        if ( $exists ) {
            $wpdb->update( $table, $data, ['id' => $exists] );
        } else {
            // Jika baru, tambahkan created_at
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $table, $data );
        }
    }

    // 3. Handler untuk WISATA
    if ( 'dw_wisata' === $post_type ) {
        $table = $wpdb->prefix . 'dw_wisata';

        $data = [
            'nama_wisata' => $post->post_title,
            'slug'        => $post->post_name,
            'deskripsi'   => $post->post_content,
            'id_desa'     => isset($_POST['dw_id_desa']) ? absint($_POST['dw_id_desa']) : 0,
            'harga_tiket' => isset($_POST['dw_harga_tiket']) ? floatval($_POST['dw_harga_tiket']) : 0,
            'jam_buka'    => isset($_POST['dw_jam_buka']) ? sanitize_text_field($_POST['dw_jam_buka']) : '',
            'kontak_pengelola' => isset($_POST['dw_kontak']) ? sanitize_text_field($_POST['dw_kontak']) : '',
            'lokasi_maps' => isset($_POST['dw_maps']) ? esc_url_raw($_POST['dw_maps']) : '',
            'updated_at'  => current_time( 'mysql' )
        ];

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $post->post_name ) );

        if ( $exists ) {
            $wpdb->update( $table, $data, ['id' => $exists] );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $table, $data );
        }
    }
}
add_action( 'save_post', 'dw_save_custom_meta_box_data' );
?>