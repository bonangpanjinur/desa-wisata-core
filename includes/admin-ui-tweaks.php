<?php
/**
 * File Path: includes/admin-ui-tweaks.php
 *
 * FITUR BARU:
 * - Menambahkan kolom Custom "Desa" dan "Toko" ke Tabel List Standar WordPress.
 * - Mengambil data relasi dari Custom Table (dw_pedagang & dw_desa) secara otomatis.
 * - Membersihkan tampilan editor (menghapus meta box bawaan yang tidak perlu).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DW_Admin_UI_Tweaks {

    public function __construct() {
        // Hook untuk membersihkan UI Editor
        add_action( 'add_meta_boxes', [ $this, 'cleanup_meta_boxes' ], 20, 2 );
        
        // --- 1. MODIFIKASI TABEL PRODUK (dw_produk) ---
        add_filter( 'manage_dw_produk_posts_columns', [ $this, 'add_produk_columns' ] );
        add_action( 'manage_dw_produk_posts_custom_column', [ $this, 'render_produk_columns' ], 10, 2 );
        
        // --- 2. MODIFIKASI TABEL WISATA (dw_wisata) ---
        add_filter( 'manage_dw_wisata_posts_columns', [ $this, 'add_wisata_columns' ] );
        add_action( 'manage_dw_wisata_posts_custom_column', [ $this, 'render_wisata_columns' ], 10, 2 );
    }

    /**
     * Membersihkan Meta Box bawaan WP yang tidak relevan agar UI lebih bersih.
     * Note: Meta box custom (Form Input) sudah ditangani oleh `includes/meta-boxes.php`,
     * jadi kita tidak perlu mendaftarkannya lagi di sini (mencegah duplikasi).
     */
    public function cleanup_meta_boxes( $post_type, $post ) {
        if ( !in_array($post_type, ['dw_wisata', 'dw_produk']) ) {
            return;
        }

        // Hapus elemen standar yang digantikan oleh Custom Fields kita
        remove_meta_box( 'slugdiv', $post_type, 'normal' );      // Slug (sudah auto)
        remove_meta_box( 'postcustom', $post_type, 'normal' );   // Custom Fields native
        remove_meta_box( 'commentstatusdiv', $post_type, 'normal' );
        remove_meta_box( 'commentsdiv', $post_type, 'normal' );
        
        // Sembunyikan author box bawaan (karena kita pakai logika relasi sendiri)
        remove_meta_box( 'authordiv', $post_type, 'normal' );
    }

    // =========================================================================
    // LOGIKA KOLOM TABEL: PRODUK
    // =========================================================================

    public function add_produk_columns($columns) {
        $new_columns = [];
        // Menyisipkan kolom baru setelah 'title' agar urutannya rapi
        foreach($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['dw_toko']  = 'Toko (Pedagang)';
                $new_columns['dw_desa']  = 'Asal Desa'; // <--- INI YANG ANDA CARI
                $new_columns['dw_harga'] = 'Harga';
                $new_columns['dw_stok']  = 'Stok';
            }
        }
        // Hapus kolom author bawaan jika dirasa redundan (opsional)
        // unset($new_columns['author']); 
        return $new_columns;
    }

    public function render_produk_columns($column, $post_id) {
        global $wpdb;
        $post = get_post($post_id);
        
        // 1. Ambil Data Relasi Toko & Desa
        // Logika: Produk dimiliki oleh Author (User ID) -> User ID ada di tabel dw_pedagang -> dw_pedagang punya id_desa
        if ($column === 'dw_toko' || $column === 'dw_desa') {
            $pedagang = $wpdb->get_row($wpdb->prepare(
                "SELECT p.nama_toko, d.nama_desa 
                 FROM {$wpdb->prefix}dw_pedagang p
                 LEFT JOIN {$wpdb->prefix}dw_desa d ON p.id_desa = d.id
                 WHERE p.id_user = %d",
                $post->post_author
            ));
            
            if ($column === 'dw_toko') {
                if ($pedagang) {
                    echo '<strong>' . esc_html($pedagang->nama_toko) . '</strong>';
                } else {
                    echo '<span style="color:orange;">(Belum ada Toko)</span>';
                }
            }
            
            if ($column === 'dw_desa') {
                if ($pedagang && $pedagang->nama_desa) {
                    echo '<span class="dashicons dashicons-location" style="color:#2271b1; font-size:14px;"></span> ' . esc_html($pedagang->nama_desa);
                } else {
                    echo '<span style="color:#aaa;">- Belum Terhubung -</span>';
                }
            }
        }

        // 2. Ambil Data Harga & Stok (Dari Custom Table dw_produk)
        if ($column === 'dw_harga' || $column === 'dw_stok') {
            // Kita cocokkan berdasarkan slug post_name karena id mungkin berbeda jika sync belum sempurna
            $produk_data = $wpdb->get_row($wpdb->prepare(
                "SELECT harga, stok FROM {$wpdb->prefix}dw_produk WHERE slug = %s",
                $post->post_name
            ));
            
            if ($column === 'dw_harga') {
                echo $produk_data ? 'Rp ' . number_format($produk_data->harga, 0, ',', '.') : '-';
            }
            if ($column === 'dw_stok') {
                echo $produk_data ? esc_html($produk_data->stok) : '-';
            }
        }
    }

    // =========================================================================
    // LOGIKA KOLOM TABEL: WISATA
    // =========================================================================

    public function add_wisata_columns($columns) {
        $new_columns = [];
        foreach($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['dw_desa_wisata'] = 'Lokasi Desa'; // <--- INI YANG ANDA CARI
                $new_columns['dw_tiket'] = 'Harga Tiket';
            }
        }
        return $new_columns;
    }

    public function render_wisata_columns($column, $post_id) {
        global $wpdb;
        $post = get_post($post_id);

        // Ambil data dari tabel dw_wisata dan dw_desa
        $wisata_data = $wpdb->get_row($wpdb->prepare(
            "SELECT w.harga_tiket, d.nama_desa 
             FROM {$wpdb->prefix}dw_wisata w
             LEFT JOIN {$wpdb->prefix}dw_desa d ON w.id_desa = d.id
             WHERE w.slug = %s",
            $post->post_name
        ));

        if ($column === 'dw_desa_wisata') {
            if ($wisata_data && $wisata_data->nama_desa) {
                echo '<span class="dashicons dashicons-palmtree" style="color:#10b981; font-size:14px;"></span> <strong>' . esc_html($wisata_data->nama_desa) . '</strong>';
            } else {
                // Fallback: Coba ambil dari post meta jika tabel custom belum sync
                $meta_desa_id = get_post_meta($post_id, '_dw_id_desa', true);
                if ($meta_desa_id) {
                    $nama = $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM {$wpdb->prefix}dw_desa WHERE id=%d", $meta_desa_id));
                    echo $nama ? esc_html($nama) : '(Data Desa Terhapus)';
                } else {
                    echo '<span style="color:red;">- Belum Terhubung -</span>';
                }
            }
        }

        if ($column === 'dw_tiket') {
            echo $wisata_data ? 'Rp ' . number_format($wisata_data->harga_tiket, 0, ',', '.') : '-';
        }
    }
}

new DW_Admin_UI_Tweaks();