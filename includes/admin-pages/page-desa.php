<?php
/**
 * Halaman Manajemen Desa
 * * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}

// Proses form submission
if (isset($_POST['action'])) {
    if (isset($_POST['action']) && $_POST['action'] == 'add_desa' && check_admin_referer('dw_add_desa_nonce')) {
        global $wpdb;
        
        $user_id = intval($_POST['user_id']);
        
        // Cek apakah user sudah punya desa
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dw_desa WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            add_settings_error('dw_desa_messages', 'dw_desa_exists', 'User ini sudah terdaftar sebagai pengelola desa.', 'error');
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'dw_desa',
                array(
                    'user_id' => $user_id,
                    'nama_desa' => sanitize_text_field($_POST['nama_desa']),
                    'deskripsi' => sanitize_textarea_field($_POST['deskripsi']),
                    'lokasi' => sanitize_textarea_field($_POST['lokasi']),
                    'kontak' => sanitize_text_field($_POST['kontak']),
                    'status' => 'active', // Default active jika ditambahkan admin
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            add_settings_error('dw_desa_messages', 'dw_desa_created', 'Desa wisata berhasil ditambahkan.', 'updated');
        }
    } elseif ($_POST['action'] == 'delete_desa' && isset($_POST['desa_id']) && check_admin_referer('dw_delete_desa_nonce')) {
        // Handle delete
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'dw_desa',
            array('id' => intval($_POST['desa_id'])),
            array('%d')
        );
        add_settings_error('dw_desa_messages', 'dw_desa_deleted', 'Desa wisata berhasil dihapus.', 'updated');
    }
}

// Inisialisasi List Table
require_once DESA_WISATA_CORE_PATH . 'includes/list-tables/class-dw-desa-list-table.php';
$list_table = new DW_Desa_List_Table();
$list_table->prepare_items();

// Ambil list user role 'desa_admin' yang belum punya desa untuk dropdown "Tambah Baru"
$users = get_users(array('role' => 'desa_admin'));
?>

<div class="wrap dw-admin-wrapper">
    <!-- Header Section -->
    <div class="dw-page-header">
        <div class="dw-header-title">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="dw-subtitle">Kelola daftar desa wisata yang terdaftar dalam sistem.</p>
        </div>
        <div class="dw-header-actions">
            <!-- Toggle Form Tambah Baru (Simple JS toggle could be added here later) -->
            <button type="button" class="button button-primary" id="btn-add-new-desa">Tambah Desa Baru</button>
        </div>
    </div>

    <!-- Body Section -->
    <div class="dw-content-body">
        <?php settings_errors('dw_desa_messages'); ?>

        <!-- Form Tambah Desa (Hidden by default, toggle via JS recommended, but shown inline for now if needed or put in modal) -->
        <div id="form-add-desa" class="dw-card" style="display:none; border-left: 4px solid var(--dw-primary);">
            <h2 style="margin-top:0;">Tambah Desa Wisata</h2>
            <form method="post" action="">
                <?php wp_nonce_field('dw_add_desa_nonce'); ?>
                <input type="hidden" name="action" value="add_desa">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="user_id">Pengelola (User)</label></th>
                        <td>
                            <select name="user_id" id="user_id" required class="regular-text">
                                <option value="">Pilih User Pengelola...</option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>">
                                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Hanya menampilkan user dengan role 'Desa Admin'.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nama_desa">Nama Desa</label></th>
                        <td><input type="text" name="nama_desa" id="nama_desa" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deskripsi">Deskripsi Singkat</label></th>
                        <td><textarea name="deskripsi" id="deskripsi" class="regular-text" rows="3"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lokasi">Alamat Lengkap</label></th>
                        <td><textarea name="lokasi" id="lokasi" class="regular-text" rows="2"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kontak">Kontak (HP/WA)</label></th>
                        <td><input type="text" name="kontak" id="kontak" class="regular-text"></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Simpan Desa">
                    <button type="button" class="button button-secondary" id="btn-cancel-add">Batal</button>
                </p>
            </form>
        </div>

        <!-- Tabel Data -->
        <div class="dw-card">
            <form id="dw-desa-list" method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
                <?php
                $list_table->search_box('Cari Desa', 'search_id');
                $list_table->display();
                ?>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#btn-add-new-desa').on('click', function() {
        $('#form-add-desa').slideDown();
        $(this).hide();
    });
    
    $('#btn-cancel-add').on('click', function() {
        $('#form-add-desa').slideUp();
        $('#btn-add-new-desa').show();
    });
});
</script>