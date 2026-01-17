<?php
/**
 * Controller: Manajemen Desa
 * Path: includes/admin-pages/page-desa.php
 * Description: Controller utama untuk manajemen desa. Logika backend dipisah dari view.
 * Version: 2.7.0 (Financial Integrated + User Mapping Fix)
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// Dependencies
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/admin-ui-components.php';
require_once $base_path . '/address-api.php';

// Logic Referral
if ( ! class_exists( 'DW_Referral_Logic' ) ) {
    $logic_path = $base_path . '/class-dw-referral-logic.php';
    if (file_exists($logic_path)) require_once $logic_path;
}

// Logic Wallet (NEW: Load class wallet untuk statistik keuangan)
if ( ! class_exists( 'DW_Wallet' ) ) {
    $wallet_path = $base_path . '/classes/class-dw-wallet.php';
    if (file_exists($wallet_path)) require_once $wallet_path;
}

function dw_desa_page_render() {
    // Security Check
    if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
        wp_die(esc_html__('Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.', 'desa-wisata-core'));
    }

    global $wpdb;
    $table_desa     = $wpdb->prefix . 'dw_desa';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_users    = $wpdb->users;
    
    // Assets
    if ( ! did_action( 'wp_enqueue_media' ) ) {
        wp_enqueue_media();
    }
    
    $message = '';
    $message_type = '';

    // URL Action Handling
    $action_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
    $active_tab  = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'data_desa';
    $id_view     = isset($_GET['id']) ? intval($_GET['id']) : 0;

    /**
     * =========================================================================
     * LOGIC POST HANDLING
     * =========================================================================
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // A. SIMPAN PENGATURAN HARGA
        if (isset($_POST['action_save_settings']) && check_admin_referer('dw_desa_settings_save')) {
            $settings = get_option('dw_settings', []);
            $settings['harga_premium_desa'] = absint($_POST['harga_premium_desa']);
            update_option('dw_settings', $settings);
            $message = 'Pengaturan harga berhasil disimpan.';
            $message_type = 'success';
        }

        // B. VERIFIKASI PREMIUM
        if (isset($_POST['action_verify_desa']) && check_admin_referer('dw_verify_desa')) {
            $desa_id = absint($_POST['desa_id']);
            $decision = sanitize_key($_POST['decision']); 
            
            if ($decision === 'approve') {
                $wpdb->update($table_desa, ['status_akses_verifikasi' => 'active', 'alasan_penolakan' => null], ['id' => $desa_id]);
                $message = 'Desa berhasil di-upgrade ke status PREMIUM (Active).';
                $message_type = 'success';
            } else {
                $reason = sanitize_textarea_field($_POST['alasan_penolakan']);
                $wpdb->update($table_desa, ['status_akses_verifikasi' => 'locked', 'alasan_penolakan' => $reason], ['id' => $desa_id]);
                $message = 'Pengajuan Premium ditolak. Status dikembalikan ke Free (Locked).';
                $message_type = 'warning';
            }
        }

        // C. DELETE DESA
        if (isset($_POST['action_desa']) && $_POST['action_desa'] === 'delete' && check_admin_referer('dw_desa_action')) {
            if (!empty($_POST['desa_id'])) {
                $desa_id_to_delete = intval($_POST['desa_id']);
                
                // Cek apakah masih ada pedagang terdaftar
                $count_pedagang = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_pedagang} WHERE id_desa = %d", $desa_id_to_delete));
                
                if ($count_pedagang > 0) {
                    $message = sprintf('Gagal: Masih ada <strong>%d Pedagang</strong> terdaftar di Desa ini. Silakan pindahkan atau hapus pedagang terlebih dahulu.', intval($count_pedagang)); 
                    $message_type = 'error';
                } else {
                    $deleted = $wpdb->delete($table_desa, ['id' => $desa_id_to_delete]);
                    if ($deleted) {
                        $message = "Desa berhasil dihapus secara permanen."; $message_type = "success";
                    } else {
                        $message = "Gagal menghapus desa."; $message_type = "error";
                    }
                }
            }
        }
        
        // D. SAVE DESA (INSERT / UPDATE)
        if (isset($_POST['dw_action']) && $_POST['dw_action'] === 'save_desa' && check_admin_referer('dw_save_desa_nonce')) {
            if (empty($_POST['nama_desa'])) {
                $message = 'Gagal: Nama Desa wajib diisi.'; $message_type = 'error';
            } else {
                $id_desa_save = isset($_POST['desa_id']) ? intval($_POST['desa_id']) : 0;
                
                // --- GENERATE REFERRAL CODE ---
                $kode_referral = sanitize_text_field($_POST['kode_referral']);
                
                // Jika kosong, generate otomatis
                if (empty($kode_referral)) {
                    $prov_txt = sanitize_text_field($_POST['provinsi_text']);
                    $kab_txt  = sanitize_text_field($_POST['kabupaten_text']);
                    $kel_txt  = sanitize_text_field($_POST['kelurahan_text']);
                    $nama_desa_input = sanitize_text_field($_POST['nama_desa']);

                    // Helper function internal untuk kode wilayah
                    $get_region_code = function($text, $type = '') {
                        if (empty($text)) return 'XXX';
                        $clean = trim(strtolower($text));
                        $clean = preg_replace('/^(provinsi|kabupaten|kota|desa|kelurahan)\s+/', '', $clean);
                        if ($type === 'province') {
                            if ($clean == 'jawa barat') return 'JAB';
                            if ($clean == 'jawa tengah') return 'JTG';
                            if ($clean == 'jawa timur') return 'JTM';
                            if (strpos($clean, 'jakarta') !== false) return 'DKI';
                            if (strpos($clean, 'yogyakarta') !== false) return 'DIY';
                        }
                        $clean_no_space = str_replace(' ', '', $clean);
                        return strtoupper(substr($clean_no_space, 0, 3));
                    };

                    $c_prov = $get_region_code($prov_txt, 'province');
                    $c_kab  = $get_region_code($kab_txt);
                    $c_des  = $get_region_code(!empty($kel_txt) ? $kel_txt : $nama_desa_input);
                    $rand   = rand(1000, 9999);

                    $kode_referral = "$c_prov-$c_kab-$c_des-$rand";

                    // Cek Unik
                    $is_exist = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE kode_referral = %s AND id != %d", $kode_referral, $id_desa_save));
                    while($is_exist) {
                        $rand = rand(1000, 9999);
                        $kode_referral = "$c_prov-$c_kab-$c_des-$rand";
                        $is_exist = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE kode_referral = %s AND id != %d", $kode_referral, $id_desa_save));
                    }
                } else {
                    $kode_referral = strtoupper($kode_referral);
                }

                // MAPPING DATA
                $data = [
                    'id_user_desa'            => intval($_POST['id_user_desa']),
                    'nama_desa'               => sanitize_text_field($_POST['nama_desa']),
                    'slug_desa'               => sanitize_title($_POST['nama_desa']),
                    'kode_referral'           => $kode_referral,
                    'deskripsi'               => wp_kses_post($_POST['deskripsi']),
                    'nomor_wa'                => sanitize_text_field($_POST['nomor_wa']), 
                    'jam_buka'                => sanitize_text_field($_POST['jam_buka']),
                    'jam_tutup'               => sanitize_text_field($_POST['jam_tutup']),
                    'foto'                    => esc_url_raw($_POST['foto_url']),
                    'foto_sampul'             => esc_url_raw($_POST['foto_sampul_url']),
                    'status'                  => sanitize_text_field($_POST['status']), 
                    'no_rekening_desa'        => sanitize_text_field($_POST['no_rekening_desa']),
                    'nama_bank_desa'          => sanitize_text_field($_POST['nama_bank_desa']),
                    'atas_nama_rekening_desa' => sanitize_text_field($_POST['atas_nama_rekening_desa']),
                    'qris_image_url_desa'     => esc_url_raw($_POST['qris_url']),
                    'api_provinsi_id'         => sanitize_text_field($_POST['api_provinsi_id']),
                    'api_kabupaten_id'        => sanitize_text_field($_POST['api_kabupaten_id']),
                    'api_kecamatan_id'        => sanitize_text_field($_POST['api_kecamatan_id']),
                    'api_kelurahan_id'        => sanitize_text_field($_POST['api_kelurahan_id']),
                    'provinsi'                => sanitize_text_field($_POST['provinsi_text']),
                    'kabupaten'               => sanitize_text_field($_POST['kabupaten_text']),
                    'kecamatan'               => sanitize_text_field($_POST['kecamatan_text']),
                    'kelurahan'               => sanitize_text_field($_POST['kelurahan_text']),
                    'alamat_lengkap'          => sanitize_textarea_field($_POST['alamat_lengkap']),
                    'kode_pos'                => sanitize_text_field($_POST['kode_pos']),
                    'status_akses_verifikasi' => sanitize_text_field($_POST['status_akses_verifikasi']),
                    'bukti_bayar_akses'       => esc_url_raw($_POST['bukti_bayar_akses_url']),
                    'alasan_penolakan'        => sanitize_textarea_field($_POST['alasan_penolakan']),
                    'updated_at'              => current_time('mysql')
                ];
                
                if ($id_desa_save > 0) {
                    $wpdb->update($table_desa, $data, ['id' => $id_desa_save]);
                    $message = 'Data Desa berhasil diperbarui.'; 
                } else {
                    $data['created_at'] = current_time('mysql');
                    $data['total_pendapatan'] = 0;
                    $data['saldo_komisi'] = 0;
                    $wpdb->insert($table_desa, $data);
                    $message = 'Desa baru berhasil ditambahkan.'; 
                }
                $message_type = 'success';
                
                if ($id_desa_save == 0) $action_view = 'list';
            }
        }
    }
    
    // --- DATA PREPARATION (FIXED USER LOGIC) ---
    $is_edit = ($action_view === 'edit' || $action_view === 'add');
    $edit_data = null;
    
    $default_data = (object) [
        'id' => 0, 'id_user_desa' => 0, 'nama_desa' => '', 'deskripsi' => '',
        'kode_referral' => '', 
        'nomor_wa' => '', 'status' => 'pending', 
        'foto' => '', 'foto_sampul' => '',
        'status_akses_verifikasi' => 'locked', 
        'bukti_bayar_akses' => '', 'alasan_penolakan' => '',
        'api_provinsi_id'=>'', 'api_kabupaten_id'=>'', 'api_kecamatan_id'=>'', 'api_kelurahan_id'=>'',
        'provinsi'=>'', 'kabupaten'=>'', 'kecamatan'=>'', 'kelurahan'=>'',
        'alamat_lengkap'=>'', 'kode_pos'=>'',
        'nama_bank_desa'=>'', 'no_rekening_desa'=>'', 'atas_nama_rekening_desa'=>'', 'qris_image_url_desa'=>'',
        'jam_buka' => '', 'jam_tutup' => '',
        'total_pendapatan' => 0, 'saldo_komisi' => 0
    ];

    if ($action_view === 'edit' && $id_view > 0) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id = %d", $id_view));
    }
    if (!$edit_data) $edit_data = $default_data;

    // [FIX] User Mapping for Dropdown
    $assigned_users = $wpdb->get_results("SELECT id_user_desa, nama_desa FROM $table_desa");
    $user_village_map = [];
    foreach ($assigned_users as $row) {
        $user_village_map[$row->id_user_desa] = $row->nama_desa;
    }

    // [FIX] Ambil User yang role-nya Administrator atau Admin Desa
    $users = get_users([
        'orderby' => 'display_name', 
        'role__in' => ['admin_desa', 'administrator', 'desa'] // Pastikan role 'desa' juga masuk jika ada
    ]);

    // Stats & Counters (UPDATED: Gunakan data Wallet jika ada untuk akurasi)
    $count_verify = $wpdb->get_var("SELECT COUNT(*) FROM $table_desa WHERE status_akses_verifikasi = 'pending'");
    $total_pendapatan_all = $wpdb->get_var("SELECT SUM(total_pendapatan) FROM $table_desa") ?: 0;
    
    // Cek total saldo dari tabel Wallet untuk akurasi tinggi
    $table_wallet = $wpdb->prefix . 'dw_wallet';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_wallet'") == $table_wallet) {
        $total_saldo_komisi_all = $wpdb->get_var("SELECT SUM(balance) FROM $table_wallet") ?: 0;
    } else {
        // Fallback ke legacy column
        $total_saldo_komisi_all = $wpdb->get_var("SELECT SUM(saldo_komisi) FROM $table_desa") ?: 0;
    }

    $total_desa = 0; $active_count = 0; 
    if (!$is_edit) {
        $total_desa = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa");
        $active_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_desa WHERE status = 'aktif'");
    }

    // Settings Data
    $settings = get_option('dw_settings', []);
    $harga = isset($settings['harga_premium_desa']) ? $settings['harga_premium_desa'] : 0;

    ?>
    <div class="wrap dw-admin-wrapper">
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1>Manajemen Desa Wisata</h1>
                <p class="dw-subtitle">Kelola data desa, verifikasi akun, dan pengaturan keuangan.</p>
            </div>
             <div class="dw-header-actions">
                <?php if (!$is_edit && $active_tab == 'data_desa'): ?>
                    <a href="?page=dw-desa&tab=data_desa&view=add" class="button button-primary">Tambah Desa</a>
                <?php elseif($is_edit): ?>
                     <a href="?page=dw-desa" class="button">Kembali</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if(!$is_edit): ?>
        <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <a href="?page=dw-desa&tab=data_desa" class="nav-tab <?php echo $active_tab == 'data_desa' ? 'nav-tab-active' : ''; ?>">Data Desa</a>
            <a href="?page=dw-desa&tab=verifikasi" class="nav-tab <?php echo $active_tab == 'verifikasi' ? 'nav-tab-active' : ''; ?>">Verifikasi Premium</a>
            <a href="?page=dw-desa&tab=pengaturan" class="nav-tab <?php echo $active_tab == 'pengaturan' ? 'nav-tab-active' : ''; ?>">Pengaturan Harga</a>
        </h2>
        <?php endif; ?>

        <div class="dw-content-body">
            <?php 
            if ($is_edit) {
                // Pastikan file form ada
                $form_file = dirname(dirname(__FILE__)) . '/views/admin/html-desa-form.php';
                if (file_exists($form_file)) {
                    require_once $form_file;
                } else {
                     echo '<div class="notice notice-error"><p>Error: File view html-desa-form.php tidak ditemukan.</p></div>';
                }
            } else {
                // Pastikan file list ada
                $view_file = dirname(dirname(__FILE__)) . '/views/admin/html-desa-list.php';
                if (file_exists($view_file)) {
                    require $view_file; 
                } else {
                    echo '<div class="notice notice-error"><p>Error: File view html-desa-list.php tidak ditemukan.</p></div>';
                }
            } 
            ?>
        </div>
    </div>
    <?php
}