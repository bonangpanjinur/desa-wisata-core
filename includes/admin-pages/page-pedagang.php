<?php
/**
 * Halaman Manajemen Pedagang (Controller)
 * Path: includes/admin-pages/page-pedagang.php
 * Description: Controller utama untuk manajemen pedagang. Logika backend dipisah dari view.
 * Version: 2.5.0 (MVC Refactored)
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// 1. Dependencies
$address_api_path = dirname(dirname(__FILE__)) . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

$ui_path = dirname(dirname(__FILE__)) . '/admin-ui-components.php';
if (file_exists($ui_path)) {
    require_once $ui_path;
}

/**
 * Render Halaman Manajemen Pedagang
 */
if (!function_exists('dw_pedagang_page_render')) {
    function dw_pedagang_page_render() {
        // Security Check
        if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
            wp_die(esc_html__('Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.', 'desa-wisata-core'));
        }

        // Assets
        if ( ! did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dw_pedagang';
        $table_desa = $wpdb->prefix . 'dw_desa';
        $table_verifikator = $wpdb->prefix . 'dw_verifikator';
        
        $message = '';
        $message_type = '';

        // --- LOGIC: SAVE / UPDATE / DELETE ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pedagang'])) {
            
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_pedagang_action')) {
                $message = 'Keamanan tidak valid (Nonce Failed).'; $message_type = 'error';
            } else {
                $action = sanitize_text_field($_POST['action_pedagang']);

                // DELETE
                if ($action === 'delete' && !empty($_POST['pedagang_id'])) {
                    if (!current_user_can('delete_users')) {
                        $message = 'Anda tidak memiliki izin menghapus data.'; $message_type = 'error';
                    } else {
                        $deleted = $wpdb->delete($table_name, ['id' => intval($_POST['pedagang_id'])]);
                        if ($deleted !== false) {
                            $message = 'Data pedagang berhasil dihapus.'; $message_type = 'success';
                        } else {
                            $message = 'Gagal menghapus pedagang. Error: ' . $wpdb->last_error; $message_type = 'error';
                        }
                    }
                
                // SAVE / UPDATE
                } elseif ($action === 'save') {
                    $nama_toko = sanitize_text_field($_POST['nama_toko']);
                    $id_user = intval($_POST['id_user_pedagang']);
                    $kelurahan_id = sanitize_text_field($_POST['pedagang_nama_id']); 
                    
                    // Relasi Daerah & Verifikator
                    $desa_terkait = $wpdb->get_row($wpdb->prepare("SELECT id, nama_desa FROM $table_desa WHERE api_kelurahan_id = %s LIMIT 1", $kelurahan_id));
                    $id_desa = $desa_terkait ? $desa_terkait->id : 0;
                    $is_independent = ($id_desa == 0) ? 1 : 0;

                    $terdaftar_melalui_kode = sanitize_text_field($_POST['terdaftar_melalui_kode']);
                    $id_verifikator = 0; 
                    if (!empty($terdaftar_melalui_kode)) {
                        $verifikator = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_verifikator WHERE kode_referral = %s LIMIT 1", $terdaftar_melalui_kode));
                        if ($verifikator) $id_verifikator = $verifikator->id;
                    }

                    // Status & Verifikasi
                    $status_sekarang = sanitize_text_field($_POST['status_akun']);
                    $approved_by = '';
                    $verified_at = null;
                    $is_verified = 0;

                    if (isset($_POST['status_pendaftaran']) && $_POST['status_pendaftaran'] === 'disetujui') {
                        $is_verified = 1;
                        if (!empty($_POST['pedagang_id'])) {
                            $old_data = $wpdb->get_row($wpdb->prepare("SELECT verified_at, approved_by FROM $table_name WHERE id = %d", intval($_POST['pedagang_id'])));
                            $verified_at = ($old_data && $old_data->verified_at) ? $old_data->verified_at : current_time('mysql');
                            $approved_by = ($old_data) ? $old_data->approved_by : '';
                        } else {
                            $verified_at = current_time('mysql');
                        }
                        if (empty($approved_by)) {
                            $current_user = wp_get_current_user();
                            $approved_by = in_array('administrator', (array) $current_user->roles) ? 'admin' : 'desa';
                        }
                    }

                    // Ongkir Logic
                    $shipping_ojek = isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0;
                    $safe_array_map = function($input) { return isset($input) && is_array($input) ? array_map('sanitize_text_field', $input) : []; };

                    $ojek_zona_data = [
                        'satu_kecamatan' => [
                            'dekat' => ['harga' => floatval($_POST['ojek_dekat_harga'] ?? 0), 'desa_ids' => $safe_array_map($_POST['ojek_dekat_desa_ids'] ?? null)],
                            'jauh' => ['harga' => floatval($_POST['ojek_jauh_harga'] ?? 0), 'desa_ids' => $safe_array_map($_POST['ojek_jauh_desa_ids'] ?? null)]
                        ],
                        'beda_kecamatan' => [
                            'dekat' => ['harga' => floatval($_POST['ojek_beda_kec_dekat_harga'] ?? 0), 'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_dekat_ids'] ?? null)],
                            'jauh' => ['harga' => floatval($_POST['ojek_beda_kec_jauh_harga'] ?? 0), 'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_jauh_ids'] ?? null)]
                        ],
                        'blacklist' => [
                            'desa_ids' => $safe_array_map($_POST['ojek_blacklist_desa_ids'] ?? null),
                            'kecamatan_ids' => $safe_array_map($_POST['ojek_blacklist_kec_ids'] ?? null)
                        ]
                    ];

                    // Data Mapping
                    $data = [
                        'id_user'          => $id_user,
                        'id_desa'          => $id_desa, 
                        'id_verifikator'   => $id_verifikator, 
                        'is_independent'   => $is_independent,
                        'nama_toko'        => $nama_toko,
                        'slug_toko'        => sanitize_title($nama_toko),
                        'nama_pemilik'     => sanitize_text_field($_POST['nama_pemilik'] ?? ''),
                        'nomor_wa'         => sanitize_text_field($_POST['nomor_wa'] ?? ''),
                        'jam_buka'         => sanitize_text_field($_POST['jam_buka'] ?? ''),
                        'jam_tutup'        => sanitize_text_field($_POST['jam_tutup'] ?? ''),
                        'alamat_lengkap'   => sanitize_textarea_field($_POST['pedagang_detail'] ?? ''),
                        'url_gmaps'        => esc_url_raw($_POST['url_gmaps'] ?? ''),
                        'terdaftar_melalui_kode' => $terdaftar_melalui_kode,
                        'nik'              => sanitize_text_field($_POST['nik'] ?? ''),
                        'url_ktp'          => esc_url_raw($_POST['url_ktp'] ?? ''),
                        'foto_profil'      => esc_url_raw($_POST['foto_profil'] ?? ''),
                        'foto_sampul'      => esc_url_raw($_POST['foto_sampul'] ?? ''),
                        'no_rekening'      => sanitize_text_field($_POST['no_rekening'] ?? ''),
                        'nama_bank'        => sanitize_text_field($_POST['nama_bank'] ?? ''),
                        'atas_nama_rekening' => sanitize_text_field($_POST['atas_nama_rekening'] ?? ''),
                        'qris_image_url'   => esc_url_raw($_POST['qris_image_url'] ?? ''),
                        'status_pendaftaran' => sanitize_text_field($_POST['status_pendaftaran'] ?? ''),
                        'status_akun'        => $status_sekarang,
                        'is_verified'        => $is_verified,
                        'verified_at'        => $verified_at,
                        'approved_by'        => $approved_by,
                        'sisa_transaksi'     => intval($_POST['sisa_transaksi']),
                        'api_provinsi_id'    => sanitize_text_field($_POST['pedagang_prov']),
                        'api_kabupaten_id'   => sanitize_text_field($_POST['pedagang_kota']),
                        'api_kecamatan_id'   => sanitize_text_field($_POST['pedagang_kec']),
                        'api_kelurahan_id'   => $kelurahan_id,
                        'provinsi_nama'      => sanitize_text_field($_POST['provinsi_text']),
                        'kabupaten_nama'     => sanitize_text_field($_POST['kabupaten_text']),
                        'kecamatan_nama'     => sanitize_text_field($_POST['kecamatan_text']),
                        'kelurahan_nama'     => sanitize_text_field($_POST['kelurahan_text']),
                        'kode_pos'           => sanitize_text_field($_POST['kode_pos']),
                        'shipping_nasional_aktif' => isset($_POST['shipping_nasional_aktif']) ? 1 : 0,
                        'shipping_nasional_harga' => floatval($_POST['shipping_nasional_harga']),
                        'shipping_ojek_lokal_aktif' => $shipping_ojek,
                        'shipping_ojek_lokal_zona'  => json_encode($ojek_zona_data),
                        'allow_pesan_di_tempat'     => isset($_POST['allow_pesan_di_tempat']) ? 1 : 0,
                        'galeri'                    => !empty($_POST['galeri_urls']) ? json_encode(array_filter(explode(',', wp_unslash($_POST['galeri_urls'])))) : '[]',
                    ];

                    if (!empty($_POST['pedagang_id'])) {
                        $wpdb->update($table_name, $data, ['id' => intval($_POST['pedagang_id'])]);
                        $message = 'Data pedagang berhasil diperbarui.'; $message_type = 'success';
                    } else {
                        $data['kode_referral_saya'] = strtoupper(substr(md5(uniqid($nama_toko, true)), 0, 8));
                        $data['created_at'] = current_time('mysql');
                        $wpdb->insert($table_name, $data);
                        $message = 'Pedagang baru berhasil ditambahkan.'; $message_type = 'success';
                    }
                }
            }
        }

        // --- PREPARE DATA VIEW ---
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        if (isset($_GET['page']) && $_GET['page'] == 'dw-pedagang-new') {
            $action = 'edit'; 
        }

        $edit_data = null;
        $ojek_zona = null; 

        if ($action === 'edit' && !empty($_GET['id'])) {
            $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
             if($edit_data && !empty($edit_data->shipping_ojek_lokal_zona)) {
                $ojek_zona = json_decode($edit_data->shipping_ojek_lokal_zona, true);
            }
        }
        
        // Default ojek zona structure
        if (!$ojek_zona) {
            $ojek_zona = [
                'satu_kecamatan' => ['dekat' => [], 'jauh' => []],
                'beda_kecamatan' => ['dekat' => [], 'jauh' => []],
                'blacklist' => ['desa_ids' => [], 'kecamatan_ids' => []] 
            ];
        }
        if(!isset($ojek_zona['blacklist'])) $ojek_zona['blacklist'] = ['desa_ids' => [], 'kecamatan_ids' => []];

        $users = get_users(['role__in' => ['administrator', 'pedagang', 'subscriber', 'customer']]);
        
        // Statistik Dashboard
        $total_pedagang = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
        $independent_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE is_independent = 1");
        $with_desa_count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE is_independent = 0");
        $total_transaksi = $wpdb->get_var("SELECT SUM(sisa_transaksi) FROM $table_name");
        $total_transaksi = $total_transaksi ? number_format($total_transaksi) : '0';

        ?>

        <!-- WRAPPER UTAMA -->
        <div class="wrap dw-admin-wrapper">
            
            <!-- HEADER -->
            <div class="dw-page-header">
                <div class="dw-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p class="dw-subtitle"><?php echo esc_html__('Kelola daftar toko, pedagang, dan pengaturan wilayah.', 'desa-wisata-core'); ?></p>
                </div>
                <div class="dw-header-actions">
                    <?php if ($action === 'list'): ?>
                        <a href="?page=dw-pedagang&action=edit" class="dw-button dw-button-primary">
                            <span class="dashicons dashicons-plus-alt2" style="margin-right:5px;"></span> <?php echo esc_html__('Tambah Pedagang', 'desa-wisata-core'); ?>
                        </a>
                    <?php else: ?>
                        <a href="?page=dw-pedagang" class="dw-button dw-button-secondary">
                            <span class="dashicons dashicons-arrow-left-alt" style="margin-right:5px;"></span> <?php echo esc_html__('Kembali', 'desa-wisata-core'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NOTIFIKASI -->
            <?php if ($message): ?>
                <div style="margin-bottom: 20px; padding: 12px; border-radius: 6px; font-weight: 500; display: flex; align-items: center; gap: 10px; border: 1px solid transparent;
                    <?php echo $message_type == 'success' ? 'background:#f0fdf4; color:#166534; border-color:#bbf7d0;' : 'background:#fef2f2; color:#991b1b; border-color:#fecaca;'; ?>">
                    <span class="dashicons dashicons-<?php echo $message_type == 'success' ? 'yes' : 'warning'; ?>"></span>
                    <?php echo esc_html($message); ?>
                </div>
            <?php endif; ?>

            <!-- BODY CONTENT (LOAD VIEW) -->
            <div class="dw-content-body">
                
                <?php if ($action === 'list'): ?>
                    
                    <?php require_once dirname(dirname(__FILE__)) . '/views/admin/html-pedagang-list.php'; ?>

                <?php else: ?>
                    
                    <?php require_once dirname(dirname(__FILE__)) . '/views/admin/html-pedagang-form.php'; ?>

                <?php endif; ?>
                
            </div>
        </div>
        <?php
    }
}