<?php
/**
 * Class DW_Favorites
 * Menangani logika wishlist/favorit untuk Produk dan Wisata.
 * * Catatan: Pembuatan tabel database ditangani oleh includes/activation.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Favorites {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dw_favorites';
    }

    /**
     * Toggle Favorite (Like/Unlike)
     * * @param int $user_id
     * @param int $object_id (Post ID Produk/Wisata)
     * @param string $type ('produk' atau 'wisata')
     * @return array Status action
     */
    public function toggle_favorite( $user_id, $object_id, $type = 'produk' ) {
        global $wpdb;

        // Cek apakah sudah ada
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $this->table_name WHERE user_id = %d AND object_id = %d",
            $user_id, $object_id
        ) );

        if ( $exists ) {
            // Jika ada, hapus (Unlike)
            $wpdb->delete(
                $this->table_name,
                array( 'id' => $exists ),
                array( '%d' )
            );
            return array( 'status' => 'removed', 'message' => 'Dihapus dari favorit' );
        } else {
            // Jika belum ada, tambah (Like)
            $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'object_id' => $object_id,
                    'object_type' => $type,
                    'created_at' => current_time( 'mysql' )
                ),
                array( '%d', '%d', '%s', '%s' )
            );
            return array( 'status' => 'added', 'message' => 'Ditambahkan ke favorit' );
        }
    }

    /**
     * Cek status apakah user menyukai item ini
     */
    public function is_favorited( $user_id, $object_id ) {
        global $wpdb;
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $this->table_name WHERE user_id = %d AND object_id = %d",
            $user_id, $object_id
        ) );
        return ! empty( $id );
    }

    /**
     * Ambil list favorit user
     */
    public function get_user_favorites( $user_id, $type = null ) {
        global $wpdb;
        $sql = "SELECT object_id FROM $this->table_name WHERE user_id = %d";
        $params = array($user_id);

        if ( $type ) {
            $sql .= " AND object_type = %s";
            $params[] = $type;
        }

        $sql .= " ORDER BY created_at DESC";

        return $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Hitung total like untuk sebuah item
     */
    public function count_likes( $object_id ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $this->table_name WHERE object_id = %d",
            $object_id
        ) );
    }
}