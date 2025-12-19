<?php
/**
 * Halaman Manajemen Ojek (Admin Side)
 * Menampilkan list, form tambah/edit, dan proses approval.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load List Table Class
require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-ojek-list-table.php';

function dw_ojek_management_page_render() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    // Handle Actions (Save/Approve/Delete)
    dw_handle_ojek_actions();

    echo '<div class="wrap">';
    
    if ($action == 'add' || $action == 'edit') {
        dw_render_ojek_form($action, $id);
    } else {
        dw_render_ojek_list();
    }
    
    echo '</div>';
}

/**
 * Render Tabel List Ojek
 */
function dw_render_ojek_list() {
    $ojek_table = new DW_Ojek_List_Table();
    $ojek_table->prepare_items();
    
    ?>
    <h1 class="wp-heading-inline">Manajemen Ojek Desa</h1>
    <a href="?page=dw-manajemen-ojek&action=add" class="page-title-action">Tambah Ojek Baru</a>
    <hr class="wp-header-end">
    
    <form method="post">
        <?php
        $ojek_table->search_box('Cari Driver', 'search_id');
        $ojek_table->display(); 
        ?>
    </form>
    <?php
}

/**
 * Render Form Tambah/Edit Ojek
 */
function dw_render_ojek_form($action, $id) {
    global $wpdb;
    $data = [];
    $title = 'Tambah Ojek Baru';
    
    // Default Data
    $data = [
        'user_email' => '',
        'first_name' => '',
        'last_name' => '',
        'no_hp' => '',
        'nik' => '',
        'plat_nomor' => '',
        'merk_motor' => '',
        'alamat_domisili' => '',
        'status_pendaftaran' => 'menunggu'
    ];

    if ($action == 'edit' && $id > 0) {
        $title = 'Edit Data Ojek';
        $ojek = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_ojek WHERE id = %d", $id));
        
        if ($ojek) {
            $user = get_userdata($ojek->id_user);
            $data = [
                'user_email' => $user ? $user->user_email : '',
                'first_name' => '', // Bisa dipecah dari nama_lengkap jika perlu
                'last_name' => $ojek->nama_lengkap, // Simpelnya pakai ini
                'no_hp' => $ojek->no_hp,
                'nik' => $ojek->nik,
                'plat_nomor' => $ojek->plat_nomor,
                'merk_motor' => $ojek->merk_motor,
                'alamat_domisili' => $ojek->alamat_domisili,
                'status_pendaftaran' => $ojek->status_pendaftaran
            ];
        }
    }
    ?>
    <h1><?php echo $title; ?></h1>
    
    <form method="post" action="" class="card" style="max-width: 800px; padding: 20px;">
        <?php wp_nonce_field('dw_save_ojek_action', 'dw_save_ojek_nonce'); ?>
        <input type="hidden" name="dw_action_type" value="<?php echo $action; ?>">
        <input type="hidden" name="ojek_id" value="<?php echo $id; ?>">

        <h3>Data Akun Pengguna</h3>
        <table class="form-table">
            <tr>
                <th><label>Email (Username)</label></th>
                <td>
                    <input type="email" name="user_email" value="<?php echo esc_attr($data['user_email']); ?>" class="regular-text" <?php echo ($action=='edit') ? 'readonly' : 'required'; ?>>
                    <?php if($action=='add'): ?><p class="description">Password akan dikirim ke email ini.</p><?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label>Nama Lengkap</label></th>
                <td><input type="text" name="nama_lengkap" value="<?php echo esc_attr($data['last_name']); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label>No. HP (WhatsApp)</label></th>
                <td><input type="text" name="no_hp" value="<?php echo esc_attr($data['no_hp']); ?>" class="regular-text" required></td>
            </tr>
        </table>

        <h3>Data Kendaraan & Dokumen</h3>
        <table class="form-table">
            <tr>
                <th><label>NIK (KTP)</label></th>
                <td><input type="text" name="nik" value="<?php echo esc_attr($data['nik']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label>Merk Motor</label></th>
                <td><input type="text" name="merk_motor" value="<?php echo esc_attr($data['merk_motor']); ?>" class="regular-text" placeholder="Contoh: Honda Beat 2020"></td>
            </tr>
            <tr>
                <th><label>Plat Nomor</label></th>
                <td><input type="text" name="plat_nomor" value="<?php echo esc_attr($data['plat_nomor']); ?>" class="regular-text" placeholder="Contoh: D 1234 ABC"></td>
            </tr>
            <tr>
                <th><label>Alamat Domisili</label></th>
                <td><textarea name="alamat_domisili" class="large-text" rows="3"><?php echo esc_textarea($data['alamat_domisili']); ?></textarea></td>
            </tr>
            <tr>
                <th><label>Status Pendaftaran</label></th>
                <td>
                    <select name="status_pendaftaran">
                        <option value="menunggu" <?php selected($data['status_pendaftaran'], 'menunggu'); ?>>Menunggu Verifikasi</option>
                        <option value="disetujui" <?php selected($data['status_pendaftaran'], 'disetujui'); ?>>Disetujui (Aktif)</option>
                        <option value="ditolak" <?php selected($data['status_pendaftaran'], 'ditolak'); ?>>Ditolak</option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">Simpan Data Ojek</button>
            <a href="?page=dw-manajemen-ojek" class="button">Batal</a>
        </p>
    </form>
    <?php
}

/**
 * Handle POST Actions
 */
function dw_handle_ojek_actions() {
    global $wpdb;
    
    // 1. Approve via Link
    if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
        $id = absint($_GET['id']);
        check_admin_referer('approve_ojek_' . $id);
        
        $wpdb->update($wpdb->prefix.'dw_ojek', ['status_pendaftaran' => 'disetujui'], ['id' => $id]);
        
        // Beri Bonus Kuota jika baru pertama kali diapprove (Logic ada di dw-ojek-handler.php via role hook, 
        // tapi jika update DB manual, kita perlu trigger manual atau biarkan user login dulu).
        // Disini kita biarkan update DB saja.
        
        echo '<div class="notice notice-success is-dismissible"><p>Ojek berhasil disetujui.</p></div>';
    }

    // 2. Delete via Link
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = absint($_GET['id']);
        check_admin_referer('delete_ojek_' . $id);
        
        // Hapus User juga? Opsional. Disini hanya hapus data ojek.
        $wpdb->delete($wpdb->prefix.'dw_ojek', ['id' => $id]);
        echo '<div class="notice notice-success is-dismissible"><p>Data ojek dihapus.</p></div>';
    }

    // 3. Save Form (Add/Edit)
    if (isset($_POST['dw_save_ojek_nonce']) && wp_verify_nonce($_POST['dw_save_ojek_nonce'], 'dw_save_ojek_action')) {
        
        $action_type = $_POST['dw_action_type'];
        $email = sanitize_email($_POST['user_email']);
        $nama = sanitize_text_field($_POST['nama_lengkap']);
        
        // --- PROSES DATA USER WP ---
        if ($action_type == 'add') {
            if (email_exists($email)) {
                echo '<div class="notice notice-error"><p>Email sudah terdaftar!</p></div>';
                return;
            }
            
            // Create User
            $password = wp_generate_password();
            $user_id = wp_create_user($email, $password, $email);
            
            if (is_wp_error($user_id)) {
                echo '<div class="notice notice-error"><p>' . $user_id->get_error_message() . '</p></div>';
                return;
            }
            
            // Set Role & Nama
            $user = new WP_User($user_id);
            $user->set_role('dw_ojek');
            wp_update_user(['ID' => $user_id, 'display_name' => $nama, 'first_name' => $nama]);
            
            // Kirim Email Notifikasi (Opsional)
            // wp_new_user_notification($user_id, null, 'both'); 

        } else {
            // Edit Mode - Ambil ID User dari table ojek
            $ojek_id = absint($_POST['ojek_id']);
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT id_user FROM {$wpdb->prefix}dw_ojek WHERE id = %d", $ojek_id));
            
            // Update User WP (Nama)
            wp_update_user(['ID' => $user_id, 'display_name' => $nama]);
        }

        // --- PROSES DATA DB OJEK ---
        $db_data = [
            'nama_lengkap' => $nama,
            'no_hp' => sanitize_text_field($_POST['no_hp']),
            'nik' => sanitize_text_field($_POST['nik']),
            'merk_motor' => sanitize_text_field($_POST['merk_motor']),
            'plat_nomor' => sanitize_text_field($_POST['plat_nomor']),
            'alamat_domisili' => sanitize_textarea_field($_POST['alamat_domisili']),
            'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran'])
        ];

        if ($action_type == 'add') {
            $db_data['id_user'] = $user_id;
            $db_data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'dw_ojek', $db_data);
        } else {
            $db_data['updated_at'] = current_time('mysql');
            $wpdb->update($wpdb->prefix . 'dw_ojek', $db_data, ['id' => absint($_POST['ojek_id'])]);
        }

        echo '<div class="notice notice-success is-dismissible"><p>Data ojek berhasil disimpan.</p></div>';
    }
}
?>