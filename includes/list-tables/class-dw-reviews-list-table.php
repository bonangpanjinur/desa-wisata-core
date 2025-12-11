<?php
/**
 * File Name:   class-dw-reviews-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-reviews-list-table.php
 *
 * WP_List_Table untuk menampilkan ulasan yang menunggu moderasi.
 *
 * @package DesaWisataCore
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class DW_Reviews_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
        parent::__construct( [
            'singular' => 'Ulasan',    // Singular name of the listed records
            'plural'   => 'Ulasan',    // Plural name of the listed records
            'ajax'     => false        // Does this table support ajax?
        ] );
    }

    /**
     * Mendefinisikan kolom-kolom yang akan ditampilkan.
     * @return array Asosiasi ['internal-name' => 'display-name']
     */
	public function get_columns() {
		return [
			'cb'        => '<input type="checkbox" />', // Kolom Checkbox untuk bulk actions
			'author'    => 'Pengirim',                // Nama pengguna yang mengirim ulasan
			'komentar'  => 'Ulasan',                  // Isi komentar ulasan
			'rating'    => 'Rating',                  // Rating bintang
			'target'    => 'Target Ulasan',           // Produk atau Wisata yang diulas
			'submitted_on' => 'Dikirim Pada',         // Tanggal ulasan dikirim
		];
	}

    /**
     * Mendefinisikan kolom mana yang bisa diurutkan (sortable).
     * @return array Asosiasi ['internal-name' => ['orderby', 'asc|desc']]
     */
    protected function get_sortable_columns() {
        // Format: 'column_slug' => ['orderby_sql_column', true/false (true=default asc)]
        return [
            'author'    => ['display_name', false], // Mengurutkan berdasarkan nama display pengguna
            'rating'    => ['rating', false],       // Mengurutkan berdasarkan nilai rating
            // 'target'    => ['target_title', false], // Sorting by target title requires complex JOIN, omitted for simplicity/performance
            'submitted_on' => ['created_at', true], // Kolom default untuk diurutkan (terbaru dulu)
        ];
    }

    /**
     * Mendefinisikan aksi massal (bulk actions) yang tersedia.
     * @return array Asosiasi ['action-slug' => 'Action Label']
     */
    protected function get_bulk_actions() {
        return [
            'approve' => 'Setujui',           // Setujui ulasan yang dipilih
            'reject'  => 'Tolak',             // Tolak ulasan yang dipilih
            'trash'   => 'Hapus Permanen',    // Hapus ulasan yang dipilih secara permanen
        ];
    }

    /**
     * Memproses aksi massal yang dipilih pengguna.
     */
    public function process_bulk_action() {
        $action = $this->current_action(); // Mendapatkan aksi yang sedang dijalankan (misal: 'approve', 'trash')
        $ids    = isset($_REQUEST['review_ids']) ? wp_parse_id_list($_REQUEST['review_ids']) : []; // Ambil ID ulasan yang dipilih

        // Keluar jika tidak ada ID atau tidak ada aksi yang valid
        if (empty($ids) || !$action || !array_key_exists($action, $this->get_bulk_actions())) {
            return;
        }

        // Cek nonce (token keamanan WordPress)
        // PERBAIKAN: Gunakan nonce yang konsisten untuk semua bulk actions
        check_admin_referer('bulk-' . $this->_args['plural']);

        // Cek kapabilitas pengguna (apakah boleh memoderasi)
        if (!current_user_can('moderate_comments')) { // Gunakan kapabilitas WP bawaan
            wp_die(__('Anda tidak memiliki izin untuk mengelola ulasan ini.', 'desa-wisata-core'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_ulasan';
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d')); // Buat placeholder untuk query SQL
        $message = ''; // Pesan notifikasi untuk admin

        // Logika untuk setiap aksi massal
        if ('approve' === $action) {
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET status_moderasi = 'disetujui' WHERE id IN ($ids_placeholder)", $ids));
            $message = __('Ulasan yang dipilih telah disetujui.', 'desa-wisata-core');
        } elseif ('reject' === $action) {
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET status_moderasi = 'ditolak' WHERE id IN ($ids_placeholder)", $ids));
             $message = __('Ulasan yang dipilih telah ditolak.', 'desa-wisata-core');
        } elseif ('trash' === $action) {
            $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($ids_placeholder)", $ids));
             $message = __('Ulasan yang dipilih telah dihapus permanen.', 'desa-wisata-core');
        }

        // Tampilkan notifikasi jika ada pesan
        if (!empty($message)) {
            add_settings_error('dw_reviews_notices', 'bulk_action_success', $message, 'success');
            set_transient('settings_errors', get_settings_errors(), 30); // Simpan notifikasi sementara
            // Trigger action untuk mengupdate cache hitungan pending (jika status berubah)
            if ('trash' === $action || 'approve' === $action || 'reject' === $action) {
                do_action('dw_review_status_updated');
            }
        }
    }


    /**
	 * Menyiapkan data item untuk ditampilkan dalam tabel.
     * Mengambil data dari database, memproses sorting, pagination, dan filtering.
	 */
	public function prepare_items() {
		global $wpdb;
        $table_ulasan = $wpdb->prefix . 'dw_ulasan';
        $table_users = $wpdb->users;

        // Mendefinisikan header kolom (harus dilakukan sebelum mengambil data)
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        // Memproses bulk action (jika ada) sebelum mengambil data
        $this->process_bulk_action();

        // Mengatur pagination
        $per_page = $this->get_items_per_page('reviews_per_page', 20); // Jumlah item per halaman (default 20)
        $current_page = $this->get_pagenum(); // Halaman saat ini
        $offset = ($current_page - 1) * $per_page; // Offset untuk query SQL

        // Filtering (hanya tampilkan ulasan yang statusnya 'pending')
        $where_sql = $wpdb->prepare("WHERE ulasan.status_moderasi = %s", 'pending');

        // Sorting
        // Mengambil parameter orderby dan order dari URL, dengan nilai default jika tidak ada
        $orderby = isset($_GET['orderby']) && array_key_exists($_GET['orderby'], $this->get_sortable_columns()) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

        // Menghitung total item yang cocok dengan filter (untuk pagination)
        $total_items_query = "SELECT COUNT(ulasan.id) FROM $table_ulasan ulasan $where_sql";
        $total_items = (int) $wpdb->get_var($total_items_query);

        // Mengambil data item untuk halaman saat ini dari database
        // Melakukan JOIN dengan tabel users untuk mendapatkan nama display pengirim ulasan
        $items_query = $wpdb->prepare(
            "SELECT ulasan.*, users.display_name
             FROM $table_ulasan ulasan
             LEFT JOIN $table_users users ON ulasan.user_id = users.ID
             $where_sql
             ORDER BY $orderby $order
             LIMIT %d OFFSET %d",
            $per_page, $offset
        );
        $this->items = $wpdb->get_results( $items_query, ARRAY_A ); // Mendapatkan hasil sebagai array asosiatif

        // Mengatur argumen pagination untuk ditampilkan di bawah tabel
        $this->set_pagination_args([
            'total_items' => $total_items,                // Total jumlah item
            'per_page'    => $per_page,                   // Item per halaman
            'total_pages' => ceil($total_items / $per_page) // Total jumlah halaman
        ]);
	}

    /**
	 * Render default untuk kolom yang tidak memiliki metode render spesifik.
     * @param array $item Data item (satu baris dari $this->items).
     * @param string $column_name Nama kolom saat ini.
     * @return string Konten HTML untuk sel tabel.
	 */
    protected function column_default($item, $column_name) {
        // Jika tidak ada metode khusus, kembalikan nilai dari data item
        // dengan sanitasi dasar untuk keamanan.
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    /**
	 * Render kolom checkbox ('cb').
     * @param array $item Data item.
     * @return string Input checkbox HTML.
	 */
	public function column_cb( $item ) {
		// Nilai checkbox adalah ID ulasan
		return sprintf( '<input type="checkbox" name="review_ids[]" value="%d" />', $item['id'] );
	}

    /**
     * Render kolom 'author'.
     * @param array $item Data item.
     * @return string Nama author dengan format tebal.
     */
    protected function column_author($item) {
        // Ambil nama display dari hasil JOIN, fallback jika user tidak ditemukan
        $author_name = $item['display_name'] ?: __('Pengguna Tidak Dikenal', 'desa-wisata-core');
        // TODO: Tambahkan link ke profil pengguna jika perlu
        return '<strong>' . esc_html($author_name) . '</strong>';
    }

    /**
     * Render kolom 'komentar' (dengan aksi moderasi per baris).
     * @param array $item Data item.
     * @return string Komentar yang dipotong dan link aksi.
     */
    protected function column_komentar($item) {
        // Potong komentar panjang agar tabel rapi, tampilkan tooltip jika perlu
        $komentar_full = strip_tags($item['komentar']); // Hapus tag HTML
        $komentar_trimmed = !empty($komentar_full) ? wp_trim_words($komentar_full, 20, '...') : '<em>(' . __('Tidak ada komentar', 'desa-wisata-core') . ')</em>';

        // Membuat link aksi moderasi untuk baris ini (Approve, Reject, Trash)
        // Membuat nonce unik untuk setiap aksi dan item untuk keamanan
        $approve_nonce = wp_create_nonce('dw-approve-review_' . $item['id']);
        $reject_nonce = wp_create_nonce('dw-reject-review_' . $item['id']);
        $trash_nonce = wp_create_nonce('dw-trash-review_' . $item['id']);

        // Membuat array link aksi
        $actions = [
            'approve' => sprintf('<a href="?page=%s&action=approve&review_id=%s&_wpnonce=%s" style="color:green;">%s</a>', $_REQUEST['page'], $item['id'], $approve_nonce, __('Setujui', 'desa-wisata-core')),
            'reject'  => sprintf('<a href="?page=%s&action=reject&review_id=%s&_wpnonce=%s" style="color:orange;">%s</a>', $_REQUEST['page'], $item['id'], $reject_nonce, __('Tolak', 'desa-wisata-core')),
            'trash'   => sprintf('<a href="?page=%s&action=trash&review_id=%s&_wpnonce=%s" style="color:red;" onclick="return confirm(\'%s\')">%s</a>', $_REQUEST['page'], $item['id'], $trash_nonce, esc_attr__('Anda yakin ingin menghapus ulasan ini secara permanen?', 'desa-wisata-core'), __('Hapus', 'desa-wisata-core')),
            // 'edit' => sprintf('<a href="%s">%s</a>', '#', __('Edit', 'desa-wisata-core')), // Placeholder jika perlu edit
        ];

        // Menggabungkan komentar dengan link aksi menggunakan metode bawaan WP_List_Table
        return sprintf('%1$s %2$s', esc_html($komentar_trimmed), $this->row_actions($actions));
        // Catatan: Logika untuk menangani link aksi ini harus ada di file page-reviews.php
    }

    /**
     * Render kolom 'rating' (menampilkan bintang).
     * @param array $item Data item.
     * @return string HTML bintang rating.
     */
    protected function column_rating($item) {
        $rating = intval($item['rating']);
        if ($rating < 1 || $rating > 5) $rating = 0; // Pastikan rating valid

        // Membuat string bintang
        $stars_filled = str_repeat('⭐', $rating); // Karakter bintang penuh
        $stars_empty = str_repeat('☆', 5 - $rating); // Karakter bintang kosong

        return '<span style="color: #ffb400;" title="' . sprintf(__('%d dari 5 bintang', 'desa-wisata-core'), $rating) . '">' . $stars_filled . $stars_empty . '</span>';
    }

    /**
     * Render kolom 'target' (link ke halaman edit produk/wisata).
     * @param array $item Data item.
     * @return string Link ke target yang diulas.
     */
    protected function column_target($item) {
        $target_id = absint($item['target_id']);
        $target_type = sanitize_key($item['target_type']); // 'produk' atau 'wisata'
        $title = '';
        $link = '#'; // Default link jika target tidak ditemukan

        // Cek tipe target dan ambil data post
        if (($target_type === 'produk' || $target_type === 'wisata') && $target_id > 0) {
            $post = get_post($target_id);
            if ($post && $post->post_type === 'dw_' . $target_type) { // Verifikasi post type juga
                $title = $post->post_title;
                $link = get_edit_post_link($target_id); // Link ke halaman edit CPT
            } else {
                $title = sprintf(__("(ID: %d - Tidak Ditemukan atau Tipe Salah)", 'desa-wisata-core'), $target_id);
            }
        } else {
             $title = sprintf(__("(Target Tidak Valid: Tipe '%s', ID %d)", 'desa-wisata-core'), esc_html($target_type), $target_id);
        }

        // Tampilkan tipe target dan link ke target
        return sprintf('(%s) <a href="%s" target="_blank" title="%s">%s</a>',
            ucfirst($target_type), // Tipe: Produk/Wisata
            esc_url($link),        // URL ke halaman edit
            esc_attr(sprintf(__('Edit %s', 'desa-wisata-core'), $title)), // Tooltip
            esc_html(wp_trim_words($title, 10, '...')) // Judul target (dipotong jika panjang)
        );
    }

    /**
     * Render kolom 'submitted_on' (format tanggal).
     * @param array $item Data item.
     * @return string Tanggal yang diformat.
     */
    protected function column_submitted_on($item) {
        // Format tanggal menggunakan fungsi WordPress agar sesuai locale
        $timestamp = strtotime($item['created_at']);
        if (!$timestamp) return '-'; // Fallback jika format tanggal salah

        // 'Y/m/d g:i:s a' -> 2025/10/24 10:44:00 pm
        // 'F j, Y, g:i a' -> October 24, 2025, 10:44 pm
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        return date_i18n("{$date_format} @ {$time_format}", $timestamp);
    }

}

