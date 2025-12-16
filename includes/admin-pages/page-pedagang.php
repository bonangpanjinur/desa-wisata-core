<?php
/**
 * Halaman Manajemen Pedagang
 * Menangani tampilan (UI) dan logika penyimpanan data pedagang.
 */

// Pastikan tidak diakses langsung
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fungsi utama untuk merender halaman Pedagang.
 * Dipanggil oleh dw_render_pedagang() di admin-menus.php
 */
function dw_pedagang_page_render() {
    // 1. Logika Pemrosesan Form (Simpan Data)
    $message = '';
    $message_type = '';

    if (isset($_POST['submit_pedagang']) && check_admin_referer('dw_add_pedagang_nonce')) {
        $nama = sanitize_text_field($_POST['nama_pedagang']);
        $email = sanitize_email($_POST['email_pedagang']);
        $telepon = sanitize_text_field($_POST['telepon_pedagang']);
        $nama_toko = sanitize_text_field($_POST['nama_toko']);
        $deskripsi = sanitize_textarea_field($_POST['deskripsi_toko']);
        
        // Data Alamat (Cascading Dropdown)
        $provinsi = sanitize_text_field($_POST['provinsi']);
        $kota = sanitize_text_field($_POST['kota']);
        $kecamatan = sanitize_text_field($_POST['kecamatan']);
        $desa = sanitize_text_field($_POST['desa']);
        $alamat_rinci = sanitize_textarea_field($_POST['alamat_rinci']);

        // Validasi sederhana
        if (empty($nama) || empty($email) || empty($nama_toko)) {
            $message = 'Nama, Email, dan Nama Toko wajib diisi.';
            $message_type = 'error';
        } else {
            global $wpdb;
            $table_name = $wpdb->prefix . 'dw_pedagang';

            // Cek apakah email sudah ada
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE email = %s", $email));

            if ($existing) {
                $message = 'Email pedagang sudah terdaftar.';
                $message_type = 'error';
            } else {
                // Insert data
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'user_id' => get_current_user_id(), // Sementara assign ke admin, nanti bisa disesuaikan
                        'nama_pedagang' => $nama,
                        'email' => $email,
                        'telepon' => $telepon,
                        'nama_toko' => $nama_toko,
                        'deskripsi_toko' => $deskripsi,
                        'provinsi' => $provinsi,
                        'kota' => $kota,
                        'kecamatan' => $kecamatan,
                        'desa' => $desa,
                        'alamat_lengkap' => $alamat_rinci,
                        'status_verifikasi' => 'pending', // Default pending
                        'created_at' => current_time('mysql')
                    )
                );

                if ($result) {
                    $message = 'Pedagang berhasil ditambahkan.';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal menambahkan pedagang. Error database: ' . $wpdb->last_error;
                    $message_type = 'error';
                }
            }
        }
    }

    // 2. Persiapan Tabel Data (List Table)
    // Pastikan file class di-include di dalam fungsi untuk menghindari error 'convert_to_screen'
    if (!class_exists('DW_Pedagang_List_Table')) {
        require_once dirname(__DIR__) . '/list-tables/class-dw-pedagang-list-table.php';
    }

    $pedagang_list_table = new DW_Pedagang_List_Table();
    $pedagang_list_table->prepare_items();

    // 3. Output HTML Halaman
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Toko / Pedagang</h1>
        <a href="#" class="page-title-action" id="btn-add-pedagang">Tambah Baru</a>
        <hr class="wp-header-end">

        <?php if (!empty($message)) : ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Form Tambah Pedagang (Hidden by default) -->
        <div id="form-add-pedagang" class="card" style="max-width: 800px; padding: 20px; margin-top: 20px; display: none;">
            <h2>Tambah Pedagang Baru</h2>
            <form method="post" action="">
                <?php wp_nonce_field('dw_add_pedagang_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="nama_pedagang">Nama Lengkap</label></th>
                            <td><input name="nama_pedagang" type="text" id="nama_pedagang" value="" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_pedagang">Email</label></th>
                            <td><input name="email_pedagang" type="email" id="email_pedagang" value="" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="telepon_pedagang">No. Telepon / WA</label></th>
                            <td><input name="telepon_pedagang" type="text" id="telepon_pedagang" value="" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="nama_toko">Nama Toko / Usaha</label></th>
                            <td><input name="nama_toko" type="text" id="nama_toko" value="" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="deskripsi_toko">Deskripsi Singkat</label></th>
                            <td><textarea name="deskripsi_toko" id="deskripsi_toko" class="large-text" rows="3"></textarea></td>
                        </tr>

                        <!-- SECTION ALAMAT (CASCADING DROPDOWN) -->
                        <tr>
                            <th scope="row" colspan="2"><h3 style="margin: 0; padding-top: 10px; border-top: 1px solid #ccc;">Lokasi & Alamat</h3></th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="provinsi">Provinsi</label></th>
                            <td>
                                <select name="provinsi" id="provinsi" class="regular-text">
                                    <option value="">-- Pilih Provinsi --</option>
                                    <?php
                                    // Mengambil data provinsi dari fungsi helper/API jika ada
                                    if (function_exists('dw_get_provinces')) {
                                        $provinces = dw_get_provinces();
                                        if ($provinces) {
                                            foreach ($provinces as $id => $name) {
                                                echo '<option value="' . esc_attr($id) . '">' . esc_html($name) . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description">Pilih provinsi lokasi usaha.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="kota">Kabupaten / Kota</label></th>
                            <td>
                                <select name="kota" id="kota" class="regular-text" disabled>
                                    <option value="">-- Pilih Kabupaten/Kota --</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="kecamatan">Kecamatan</label></th>
                            <td>
                                <select name="kecamatan" id="kecamatan" class="regular-text" disabled>
                                    <option value="">-- Pilih Kecamatan --</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="desa">Desa / Kelurahan</label></th>
                            <td>
                                <select name="desa" id="desa" class="regular-text" disabled>
                                    <option value="">-- Pilih Desa/Kelurahan --</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="alamat_rinci">Alamat Rinci</label></th>
                            <td>
                                <textarea name="alamat_rinci" id="alamat_rinci" class="large-text" rows="2" placeholder="Nama Jalan, RT/RW, No. Rumah..."></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="submit_pedagang" id="submit" class="button button-primary" value="Simpan Pedagang">
                    <button type="button" class="button button-secondary" id="btn-cancel-pedagang">Batal</button>
                </p>
            </form>
        </div>

        <!-- Tabel List Pedagang -->
        <div id="list-pedagang-container" style="margin-top: 20px;">
            <form id="pedagang-filter" method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                <?php $pedagang_list_table->display(); ?>
            </form>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Toggle form visibility
            $('#btn-add-pedagang').on('click', function(e) {
                e.preventDefault();
                $('#form-add-pedagang').slideDown();
                $('#list-pedagang-container').slideUp();
                $(this).hide();
            });

            $('#btn-cancel-pedagang').on('click', function(e) {
                e.preventDefault();
                $('#form-add-pedagang').slideUp();
                $('#list-pedagang-container').slideDown();
                $('#btn-add-pedagang').show();
            });
        });
    </script>
    <?php
}