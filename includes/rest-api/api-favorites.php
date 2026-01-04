<?php
/**
 * REST API untuk Fitur Favorit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_API_Favorites {

    public function register_routes() {
        // Endpoint: POST /wp-json/dw-api/v1/favorites/toggle
        register_rest_route( 'dw-api/v1', '/favorites/toggle', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'toggle_favorite' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        // Endpoint: GET /wp-json/dw-api/v1/favorites/me
        register_rest_route( 'dw-api/v1', '/favorites/me', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_my_favorites' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        // Hanya user login yang bisa akses
        return is_user_logged_in();
    }

    /**
     * Handler untuk Like/Unlike
     */
    public function toggle_favorite( $request ) {
        $user_id = get_current_user_id();
        $object_id = $request->get_param( 'object_id' );
        $type = $request->get_param( 'type' ); // 'produk' atau 'wisata'

        if ( ! $object_id ) {
            return new WP_Error( 'missing_param', 'Object ID wajib diisi', array( 'status' => 400 ) );
        }

        if ( ! $type ) {
            $type = 'produk'; // Default
        }

        $favorites = new DW_Favorites();
        $result = $favorites->toggle_favorite( $user_id, $object_id, $type );

        // Ambil jumlah like terbaru untuk update UI real-time
        $new_count = $favorites->count_likes( $object_id );

        return rest_ensure_response( array(
            'success' => true,
            'action'  => $result['status'], // 'added' atau 'removed'
            'message' => $result['message'],
            'count'   => $new_count
        ) );
    }

    /**
     * Ambil list favorit user yang sedang login
     */
    public function get_my_favorites( $request ) {
        $user_id = get_current_user_id();
        $type = $request->get_param( 'type' );

        $favorites = new DW_Favorites();
        $ids = $favorites->get_user_favorites( $user_id, $type );

        // Opsional: Return data object lengkap (Title, Image, Price)
        // Di sini kita return ID saja agar ringan, frontend fetch detailnya terpisah atau kita hydrasi di sini
        
        return rest_ensure_response( array(
            'success' => true,
            'data'    => $ids
        ) );
    }
}