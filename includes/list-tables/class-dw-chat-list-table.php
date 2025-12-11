<?php
/**
 * File Name:   class-dw-chat-list-table.php
 * File Folder: includes/list-tables/
 * File Path:   includes/list-tables/class-dw-chat-list-table.php
 *
 * BARU: List Table untuk menampilkan daftar Inkuiri (Chat) yang masuk ke Pedagang.
 *
 * @package DesaWisataCore
 */
if (!class_exists('WP_List_Table')) { require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php'); }

class DW_Chat_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(['singular' => 'Inkuiri', 'plural' => 'Inkuiri', 'ajax' => false]);
    }

    public function get_columns() {
        return [
            'produk'        => 'Produk',
            'pembeli'       => 'Pembeli Terakhir',
            'last_message'  => 'Pesan Terakhir',
            'status'        => 'Status',
            'aksi'          => 'Aksi',
            'waktu'         => 'Waktu Terakhir',
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $current_user_id = get_current_user_id();

        $this->_column_headers = [$this->get_columns(), [], []]; // Kolom sortable dikosongkan untuk sementara

        // *** SARAN PENINGKATAN (Efisiensi Query) ***
        // Query ini menggunakan subquery dengan MAX(created_at) yang bisa menjadi
        // lambat pada tabel dw_chat_message yang sangat besar.
        // Jika Anda mengalami masalah performa di halaman admin chat, pertimbangkan:
        // 1. Tambahkan indeks komposit pada (produk_id, created_at) di tabel dw_chat_message.
        // 2. Gunakan window function (ROW_NUMBER() PARTITION BY produk_id ORDER BY created_at DESC)
        //    jika versi MySQL Anda mendukung (>= 8.0).
        // 3. Denormalisasi: Simpan ID atau timestamp pesan terakhir di tabel terpisah
        //    (misal: dw_chat_threads) yang diupdate setiap ada pesan baru.
        $query = $wpdb->prepare(
            "SELECT
                t1.*,
                u_sender.display_name AS sender_name,
                u_receiver.display_name AS receiver_name,
                (SELECT COUNT(id) FROM {$wpdb->prefix}dw_chat_message WHERE produk_id = t1.produk_id AND receiver_id = %d AND is_read = 0) as unread_count
             FROM {$wpdb->prefix}dw_chat_message t1
             INNER JOIN (
                 SELECT produk_id, MAX(created_at) AS MaxDate
                 FROM {$wpdb->prefix}dw_chat_message
                 WHERE receiver_id = %d OR sender_id = %d
                 GROUP BY produk_id
             ) t2 ON t1.produk_id = t2.produk_id AND t1.created_at = t2.MaxDate
             LEFT JOIN {$wpdb->users} u_sender ON t1.sender_id = u_sender.ID
             LEFT JOIN {$wpdb->users} u_receiver ON t1.receiver_id = u_receiver.ID
             WHERE t1.receiver_id = %d OR t1.sender_id = %d
             ORDER BY t1.created_at DESC",
             $current_user_id, // Untuk unread count
             $current_user_id, $current_user_id, // Untuk grouping
             $current_user_id, $current_user_id // Untuk filtering
        );
        $this->items = $wpdb->get_results($query, ARRAY_A);

        // Pengaturan Paginasi (jika diperlukan di masa depan)
        // ... (kode paginasi bisa ditambahkan di sini) ...

    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'last_message':
                // Tampilkan siapa pengirim terakhir
                $sender_label = ((int)$item['sender_id'] === get_current_user_id()) ? 'Anda: ' : '';
                return $sender_label . wp_trim_words(esc_html($item['message']), 10, '...');
            case 'waktu':
                return date_i18n( 'd M Y, H:i', strtotime( $item['created_at'] ) ); // Format tanggal WordPress
            case 'status':
                $unread_count = (int) $item['unread_count'];
                return $unread_count > 0 ?
                       '<span class="awaiting-mod update-plugins count-' . $unread_count . '"><span class="plugin-count">' . number_format_i18n($unread_count) . '</span></span> Pesan Baru' : // Gunakan style WP untuk notif
                       '<span class="selesai">Sudah Dibaca</span>'; // Gunakan class status
            case 'aksi':
                 // Tentukan ID lawan bicara (pembeli) untuk link Balas
                 $current_user_id = get_current_user_id();
                 $other_user_id = ((int)$item['sender_id'] === $current_user_id) ? (int)$item['receiver_id'] : (int)$item['sender_id'];

                return sprintf(
                    '<a href="?page=%s&action=view&produk_id=%s&other_user_id=%s" class="button button-primary button-small">Lihat & Balas</a>', // Tambahkan other_user_id jika perlu
                    $_REQUEST['page'],
                    $item['produk_id'],
                    $other_user_id // Kirim ID pembeli ke halaman detail chat
                );
            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : ''; // Fallback aman
        }
    }

    protected function column_produk($item) {
        $title = get_the_title($item['produk_id']);
        $link = get_edit_post_link($item['produk_id']); // Link ke halaman edit produk
        if ($title && $link) {
            return sprintf('<strong><a href="%s" target="_blank" title="Edit Produk Ini">%s</a></strong>', esc_url($link), esc_html($title));
        } elseif ($title) {
             return '<strong>' . esc_html($title) . '</strong>';
        }
        return '<em>(Produk Dihapus?)</em>';
    }

    protected function column_pembeli($item) {
        // Tampilkan nama pembeli (yang bukan user saat ini)
        $current_user_id = get_current_user_id();
        $other_user_id = ((int)$item['sender_id'] === $current_user_id) ? (int)$item['receiver_id'] : (int)$item['sender_id'];
        $other_user_name = ((int)$item['sender_id'] === $current_user_id) ? esc_html($item['receiver_name']) : esc_html($item['sender_name']);

        if (!empty($other_user_name)) {
            // Opsional: Tambahkan link ke profil user jika perlu
            // $profile_link = get_edit_user_link($other_user_id);
            // return sprintf('<a href="%s">%s</a>', esc_url($profile_link), $other_user_name);
            return $other_user_name;
        }
        return 'N/A';
    }

    /**
     * Menampilkan pesan jika tidak ada item.
     */
    public function no_items() {
        _e( 'Belum ada inkuiri produk yang masuk.', 'desa-wisata-core' );
    }
}
