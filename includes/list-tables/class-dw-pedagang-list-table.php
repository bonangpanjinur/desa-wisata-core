 <?php
 /**
  * File Name:   class-dw-pedagang-list-table.php
  * File Folder: includes/list-tables/
  * File Path:   includes/list-tables/class-dw-pedagang-list-table.php
  *
  * --- FASE 1: REFAKTOR PENDAFTARAN GRATIS ---
  * PERUBAHAN BESAR:
  * - MENGHAPUS kolom "Pembayaran Reg.".
  * - MENGHAPUS filter "Status Pembayaran".
  * - MENGHAPUS JOIN ke tabel `dw_transaksi_pendaftaran`.
  *
  * --- PERBAIKAN (NONCE) ---
  * - Mengganti `action=delete` menjadi `action=dw_delete` untuk menghindari
  * konflik dengan penanganan `action=delete` bawaan WordPress.
  *
  * --- PERUBAHAN (LOGIKA HAPUS & NAMA) ---
  * - Mengganti 'Pedagang' menjadi 'Toko'
  * - Memperbarui pesan konfirmasi hapus
  *
  * --- PERUBAHAN (MODEL 3 - REQUEST PENGGUNA) ---
  * - MENAMBAHKAN kolom "Jenis Paket".
  * - MENAMBAHKAN JOIN ke 'dw_pembelian_paket' untuk mendapatkan nama paket terakhir.
  * - MENAMBAHKAN logika render kolom 'jenis_paket' (Paket Dibeli, Paket Gratis, Habis).
  */

 if ( ! class_exists( 'WP_List_Table' ) ) {
     require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
 }

 class DW_Pedagang_List_Table extends WP_List_Table {

     public function __construct() {
         // --- PERUBAHAN: Ganti nama singular/plural ---
         parent::__construct( [ 'singular' => 'Toko', 'plural' => 'Toko', 'ajax' => false ] );
     }

     public function get_columns() {
         return [
             'cb'           => '<input type="checkbox" />',
             'nama_toko'    => 'Nama Toko',
             'user'         => 'Pengguna',
             'desa'         => 'Desa',
             'status_pendaftaran' => 'Pendaftaran',
             'status_akun'  => 'Status Akun',
             'jenis_paket'  => 'Jenis Paket', // BARU
         ];
     }

      protected function column_nama_toko($item) {
         $actions = [
             'edit' => sprintf('<a href="?page=%s&action=edit&id=%s">Edit</a>', $_REQUEST['page'], $item['id']),
         ];

         // HANYA tampilkan tombol hapus untuk Administrator
         if (current_user_can('administrator')) {
             $nonce = wp_create_nonce('dw_delete_pedagang_action'); // Nama nonce yang konsisten
             
             // --- PERBAIKAN: Ganti action=delete menjadi action=dw_delete ---
             // --- PERUBAHAN: Perbarui pesan konfirmasi ---
             $actions['delete'] = sprintf(
                 '<a href="?page=%s&action=dw_delete&id=%s&_wpnonce=%s" style="color:#a00;" class="dw-confirm-link" data-confirm-message="%s">Hapus Permanen</a>',
                 $_REQUEST['page'], 
                 $item['id'], 
                 $nonce,
                 esc_attr('PERINGATAN: Menghapus toko ini akan menghapus semua data terkait (transaksi, produk, dll.) secara permanen. Akun pengguna WP-nya TIDAK akan dihapus. Anda yakin?')
             );
         }

         return sprintf('<strong>%s</strong> %s', esc_html($item['nama_toko']), $this->row_actions($actions));
     }


     protected function column_default( $item, $column_name ) {
         switch ($column_name) {
             case 'user':
                 $user = get_user_by('id', $item['id_user']);
                 return $user ? esc_html($user->display_name) . '<br><small>' . esc_html($user->user_email) . '</small>' : 'N/A';
             case 'desa':
                 // Cek jika nama desa sudah di-JOIN
                 return isset($item['nama_desa']) ? esc_html($item['nama_desa']) : dw_get_desa_name_by_id($item['id_desa']);
             case 'status_pendaftaran':
             case 'status_akun':
                  // Gunakan class untuk styling
                  $status_class = 'dw-status-' . str_replace(' ', '-', esc_attr($item[$column_name]));
                  return '<span class="' . $status_class . '">' . ucfirst(str_replace('_', ' ', esc_html($item[$column_name]))) . '</span>';
             
             // --- BARU: Render Kolom Jenis Paket ---
             case 'jenis_paket':
                $sisa_transaksi = (int) $item['sisa_transaksi'];
                $nama_paket = $item['nama_paket_snapshot'] ?? null; // Ini datang dari JOIN
                
                if (!empty($nama_paket)) {
                    // Jika ada nama paket dari pembelian terakhir, tampilkan itu.
                    return '<span style="color:green; font-weight:bold;">' . esc_html($nama_paket) . '</span>';
                }
                
                if ($sisa_transaksi > 0) {
                    // Jika tidak ada paket dibeli TAPI kuota > 0, berarti ini paket gratis.
                    return '<em>Paket Gratis (Default)</em>';
                }
                
                // Jika tidak ada paket dibeli DAN kuota <= 0
                return '<span style="color:red;">Tanpa Paket (Habis)</span>';

             default:
                 return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
         }
     }

     protected function column_cb($item) {
         return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item['id']);
     }

     protected function get_sortable_columns() {
          return [
             'nama_toko'    => ['nama_toko', false],
             'desa'         => ['nama_desa', false], // Nama kolom alias dari JOIN
             'status_pendaftaran' => ['status_pendaftaran', false],
             'status_akun'  => ['status_akun', false],
             'jenis_paket'  => ['nama_paket_snapshot', false], // BARU
          ];
     }

     public function prepare_items() {
         global $wpdb;
         $table_pedagang = $wpdb->prefix . 'dw_pedagang';
         $table_desa = $wpdb->prefix . 'dw_desa';
         $table_pembelian_paket = $wpdb->prefix . 'dw_pembelian_paket'; // BARU

         $per_page = $this->get_items_per_page('pedagang_per_page', 20);
         $current_page = $this->get_pagenum();
         $offset = ($current_page - 1) * $per_page;

         // Mendefinisikan header kolom
         $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

         // Sorting
         $orderby = isset($_GET['orderby']) && array_key_exists($_GET['orderby'], $this->get_sortable_columns()) ? sanitize_sql_orderby($_GET['orderby']) : 'p.created_at';
         $order = isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc']) ? strtolower($_GET['order']) : 'desc';

         // Sesuaikan alias kolom untuk sorting
         if ($orderby === 'nama_desa') $orderby = 'd.nama_desa';
         elseif ($orderby === 'nama_toko') $orderby = 'p.nama_toko';
         elseif ($orderby === 'status_pendaftaran') $orderby = 'p.status_pendaftaran';
         elseif ($orderby === 'status_akun') $orderby = 'p.status_akun';
         elseif ($orderby === 'nama_paket_snapshot') $orderby = 'pp.nama_paket_snapshot'; // BARU
         else $orderby = 'p.created_at'; // Default


         // Filter (jika ada)
         $where_clauses = [];
         $prepared_args = [];
         if (!empty($_GET['status_akun_filter']) && in_array($_GET['status_akun_filter'], ['aktif', 'nonaktif', 'nonaktif_habis_kuota'])) { // Tambah status habis kuota
             $where_clauses[] = "p.status_akun = %s";
             $prepared_args[] = sanitize_key($_GET['status_akun_filter']);
         }
         if (!empty($_GET['status_pendaftaran_filter']) && in_array($_GET['status_pendaftaran_filter'], ['menunggu','menunggu_desa', 'disetujui', 'ditolak'])) {
             $where_clauses[] = "p.status_pendaftaran = %s";
             $prepared_args[] = sanitize_key($_GET['status_pendaftaran_filter']);
         }
         
         // Tambahkan pencarian jika ada
         if ( ! empty( $_REQUEST['s'] ) ) {
             $search_term = '%' . $wpdb->esc_like( stripslashes( $_REQUEST['s'] ) ) . '%';
              $where_clauses[] = "(p.nama_toko LIKE %s OR p.nama_pemilik LIKE %s OR d.nama_desa LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->users} u WHERE u.ID = p.id_user AND (u.display_name LIKE %s OR u.user_email LIKE %s)))";
              $prepared_args[] = $search_term;
              $prepared_args[] = $search_term;
              $prepared_args[] = $search_term;
              $prepared_args[] = $search_term;
              $prepared_args[] = $search_term;
         }


         $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

         // --- PERUBAHAN QUERY: Tambah JOIN untuk paket ---
         $count_sql_base = "SELECT COUNT(p.id)
                            FROM $table_pedagang p
                            LEFT JOIN $table_desa d ON p.id_desa = d.id
                            LEFT JOIN (
                                SELECT id_pedagang, nama_paket_snapshot
                                FROM $table_pembelian_paket
                                WHERE (id_pedagang, created_at) IN (
                                    SELECT id_pedagang, MAX(created_at)
                                    FROM $table_pembelian_paket
                                    WHERE status = 'disetujui'
                                    GROUP BY id_pedagang
                                )
                            ) pp ON p.id = pp.id_pedagang";

         $main_sql_base = "SELECT p.*, d.nama_desa, pp.nama_paket_snapshot
                           FROM $table_pedagang p
                           LEFT JOIN $table_desa d ON p.id_desa = d.id
                           LEFT JOIN (
                                SELECT id_pedagang, nama_paket_snapshot
                                FROM $table_pembelian_paket
                                WHERE (id_pedagang, created_at) IN (
                                    SELECT id_pedagang, MAX(created_at)
                                    FROM $table_pembelian_paket
                                    WHERE status = 'disetujui'
                                    GROUP BY id_pedagang
                                )
                            ) pp ON p.id = pp.id_pedagang";
        // --- AKHIR PERUBAHAN QUERY ---


         // Query untuk menghitung total item
         $total_items_query = $count_sql_base . ' ' . $where_sql;
         $total_items = 0;

         if ( ! empty( $prepared_args ) ) {
             $total_items_sql = $wpdb->prepare( $total_items_query, $prepared_args );
             if ($total_items_sql) {
                 $total_items = (int) $wpdb->get_var( $total_items_sql );
             }
         } else {
             $total_items = (int) $wpdb->get_var( $total_items_query );
         }

         // Query utama dengan JOIN untuk mengambil data
         $query = $main_sql_base . ' ' . $where_sql . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

         $limit_offset_args = [$per_page, $offset];
         $final_prepared_args = array_merge($prepared_args, $limit_offset_args);
         
         $prepared_main_query = $wpdb->prepare($query, $final_prepared_args);

         if ($prepared_main_query) {
             $this->items = $wpdb->get_results($prepared_main_query, ARRAY_A);
         } else {
             $this->items = [];
         }


         // Set pagination args
         $this->set_pagination_args([
             'total_items' => $total_items,
             'per_page'    => $per_page,
             'total_pages' => ceil($total_items / $per_page)
         ]);
     }

     /**
      * Menampilkan dropdown filter di atas tabel.
      */
     protected function extra_tablenav($which) {
         if ($which == "top") {
             ?>
             <div class="alignleft actions">
                 <?php
                 // Filter Status Akun
                 $current_status_akun = $_GET['status_akun_filter'] ?? '';
                 // BARU: Tambah 'nonaktif_habis_kuota'
                 $akun_statuses = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif (Manual)', 'nonaktif_habis_kuota' => 'Nonaktif (Kuota Habis)'];
                 echo '<select name="status_akun_filter">';
                 echo '<option value="">Semua Status Akun</option>';
                 foreach ($akun_statuses as $value => $label) {
                     printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($current_status_akun, $value, false), esc_html($label));
                 }
                 echo '</select>';

                  // Filter Status Pendaftaran
                 $current_status_pendaftaran = $_GET['status_pendaftaran_filter'] ?? '';
                 $pendaftaran_statuses = ['menunggu' => 'Menunggu', 'menunggu_desa' => 'Menunggu Desa', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
                 echo '<select name="status_pendaftaran_filter">';
                 echo '<option value="">Semua Status Daftar</option>';
                 foreach ($pendaftaran_statuses as $value => $label) {
                     printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($current_status_pendaftaran, $value, false), esc_html($label));
                 }
                 echo '</select>';

                  // --- HAPUS FILTER STATUS PEMBAYARAN ---
                 // echo '<select name="status_pembayaran_filter">'; ...

                 submit_button(__('Filter'), 'secondary', 'filter_action', false);
                 ?>
             </div>
             <?php
         }
     }

     /**
      * Menampilkan pesan jika tidak ada item.
      */
     public function no_items() {
         // --- PERUBAHAN: Ganti nama ---
         _e( 'Tidak ada toko ditemukan.', 'desa-wisata-core' );
     }

 } // End class
 ?>
