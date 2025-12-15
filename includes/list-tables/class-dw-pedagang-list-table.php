<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DW_Pedagang_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'pedagang',
            'plural'   => 'pedagang',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'foto_profil' => 'Profil',
            'nama_toko'   => 'Nama Toko',
            'pemilik'     => 'Pemilik',
            'lokasi'      => 'Lokasi & Relasi',
            'status'      => 'Status',
            'tanggal'     => 'Terdaftar',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'nama_toko' => [ 'nama_toko', false ],
            'status'    => [ 'status_akun', false ],
            'tanggal'   => [ 'created_at', false ],
        ];
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'tanggal': 
                return !empty($item['created_at']) ? date_i18n(get_option('date_format'), strtotime($item['created_at'])) : '-';
            default: 
                return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
        }
    }

    protected function column_cb( $item ) {
        return sprintf('<input type="checkbox" name="pedagang[]" value="%s" />', $item['id']);
    }

    protected function column_foto_profil( $item ) {
        $url = !empty($item['foto_profil']) ? $item['foto_profil'] : 'https://placehold.co/50x50/e2e8f0/64748b?text=IMG';
        return sprintf(
            '<img src="%s" style="width:40px; height:40px; object-fit:cover; border-radius:50%%; border:1px solid #ddd;" alt="Profil" />',
            esc_url($url)
        );
    }

    protected function column_nama_toko( $item ) {
        // Build Actions (Edit & Delete)
        $edit_url = admin_url('admin.php?page=dw-pedagang&action=edit&id=' . $item['id']);
        $delete_nonce = wp_create_nonce('dw_delete_pedagang_action');
        $delete_url = admin_url('admin.php?page=dw-pedagang&action=dw_delete&id=' . $item['id'] . '&_wpnonce=' . $delete_nonce);

        $actions = [
            'edit'   => sprintf('<a href="%s">Edit</a>', $edit_url),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'Yakin ingin menghapus toko ini secara permanen?\')" style="color:red">Hapus Permanen</a>', $delete_url),
        ];

        // Badge Verifikasi (Sesuai kolom DB: nik & url_ktp)
        $badges = '';
        if (!empty($item['nik'])) {
            $badges .= '<span class="dashicons dashicons-id" title="NIK Terisi" style="color:#46b450; font-size:16px; margin-left:5px;"></span>';
        }
        if (!empty($item['url_ktp'])) {
            $badges .= '<span class="dashicons dashicons-format-image" title="Foto KTP Ada" style="color:#46b450; font-size:16px; margin-left:2px;"></span>';
        }

        return sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>%s %s',
            $edit_url,
            esc_html($item['nama_toko']),
            $badges,
            $this->row_actions($actions)
        );
    }

    protected function column_pemilik($item) {
        $u = get_userdata($item['id_user']);
        $user_login = $u ? $u->user_login : '<span style="color:red;">(User Dihapus)</span>';
        
        $html = '<strong>' . esc_html($item['nama_pemilik']) . '</strong><br>';
        if (!empty($item['nomor_wa'])) {
            $html .= '<span style="color:#666; font-size:12px;">WA: ' . esc_html($item['nomor_wa']) . '</span><br>';
        }
        $html .= '<span style="color:#999; font-size:11px;">WP: ' . esc_html($user_login) . '</span>';
        
        return $html;
    }

    protected function column_lokasi($item) {
        global $wpdb;
        $html = '';
        
        // 1. Relasi Desa Wisata
        if (!empty($item['id_desa'])) {
            $desa_name = $wpdb->get_var($wpdb->prepare("SELECT nama_desa FROM {$wpdb->prefix}dw_desa WHERE id = %d", $item['id_desa']));
            if ($desa_name) {
                $html .= '<div style="margin-bottom:4px;"><span class="dashicons dashicons-admin-home" style="color:#2271b1;"></span> <strong>' . esc_html($desa_name) . '</strong></div>';
            } else {
                $html .= '<div style="color:red; margin-bottom:4px;">Desa ID #' . esc_html($item['id_desa']) . ' (Tidak Ditemukan)</div>';
            }
        } else {
            $html .= '<div style="margin-bottom:4px; color:#666;"><em>Independen (Tanpa Desa)</em></div>';
        }

        // 2. Alamat Fisik (Sesuai kolom DB: alamat_lengkap)
        if (!empty($item['alamat_lengkap'])) {
            // Potong jika terlalu panjang
            $alamat_short = mb_strimwidth(strip_tags($item['alamat_lengkap']), 0, 40, "...");
            $html .= '<span style="font-size:11px; color:#555;" title="' . esc_attr($item['alamat_lengkap']) . '">' . esc_html($alamat_short) . '</span>';
        } elseif (!empty($item['api_kecamatan_id'])) {
            $html .= '<span style="font-size:11px; color:#999;">Kecamatan ID: ' . esc_html($item['api_kecamatan_id']) . '</span>';
        } else {
            $html .= '<span style="font-size:11px; color:#ccc;">- Alamat belum diisi -</span>';
        }
        
        return $html;
    }

    protected function column_status($item) {
        // Status Pendaftaran (ENUM: menunggu, disetujui, ditolak, menunggu_desa)
        $st_daftar = $item['status_pendaftaran'];
        $label_daftar = ucfirst(str_replace('_', ' ', $st_daftar));
        $bg_daftar = '#e2e3e5'; $txt_daftar = '#383d41'; // Default (menunggu)

        if ($st_daftar == 'disetujui') { $bg_daftar = '#d4edda'; $txt_daftar = '#155724'; }
        elseif ($st_daftar == 'ditolak') { $bg_daftar = '#f8d7da'; $txt_daftar = '#721c24'; }
        elseif ($st_daftar == 'menunggu_desa') { $bg_daftar = '#fff3cd'; $txt_daftar = '#856404'; }

        // Status Akun (ENUM: aktif, nonaktif, suspend)
        $st_akun = $item['status_akun'];
        $icon_akun = '';
        
        if ($st_akun == 'aktif') {
            $icon_akun = '<span class="dashicons dashicons-yes" style="color:green" title="Aktif"></span> Aktif';
        } elseif ($st_akun == 'suspend') {
            $icon_akun = '<span class="dashicons dashicons-lock" style="color:orange" title="Ditangguhkan"></span> Suspend';
        } else {
            $icon_akun = '<span class="dashicons dashicons-no" style="color:red" title="Nonaktif"></span> Nonaktif';
        }
        
        return sprintf(
            '<div style="margin-bottom:3px;"><span style="background:%s; color:%s; padding:2px 6px; border-radius:3px; font-size:10px; font-weight:bold;">%s</span></div>' .
            '<div style="font-size:12px;">%s</div>',
            $bg_daftar, $txt_daftar, esc_html($label_daftar),
            $icon_akun
        );
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_pedagang';
        
        // Pagination
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Search
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $where = "WHERE 1=1";
        if ($search) {
            $where .= $wpdb->prepare(" AND (nama_toko LIKE %s OR nama_pemilik LIKE %s OR nomor_wa LIKE %s)", "%$search%", "%$search%", "%$search%");
        }

        // Query
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where");
        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }
}