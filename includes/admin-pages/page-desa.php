<?php
/**
 * File Name: includes/admin-pages/page-pedagang.php
 * Description: Manajemen Pedagang dengan UI/UX Modern v3.15 (Slug Register Fixed).
 * UPDATE: 
 * 1. Fix: Link Pendaftaran sekarang mengarah ke /register/ sesuai request.
 * 2. Feature: Added "Registration Link" specifically for recruiting sub-agents/pedagang.
 * 3. UX: Better copy button interaction.
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// 1. Pastikan class API Address tersedia
$address_api_path = dirname(dirname(__FILE__)) . '/address-api.php';
if (file_exists($address_api_path)) {
    require_once $address_api_path;
}

// 2. Pastikan Class Logic Referral tersedia
if ( ! class_exists( 'DW_Referral_Logic' ) ) {
    $logic_path = DW_CORE_PLUGIN_DIR . 'includes/class-dw-referral-logic.php';
    if (file_exists($logic_path)) require_once $logic_path;
}

/**
 * Render Halaman Manajemen Pedagang
 */
function dw_render_page_pedagang() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dw_pedagang';
    $table_desa = $wpdb->prefix . 'dw_desa';
    $table_verifikator = $wpdb->prefix . 'dw_verifikator';
    $table_users = $wpdb->users;
    
    $message = '';
    $message_type = '';

    // Action handling
    $action_view = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id_view     = isset($_GET['id']) ? intval($_GET['id']) : 0;

    /**
     * =========================================================================
     * 1. VIEW DETAIL (Status Referral)
     * =========================================================================
     */
    if ( $action_view === 'view_detail' && $id_view > 0 ) {
        $pedagang = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id_view ) );
        
        if ( $pedagang ) :
            $referral_info = '<span class="dw-badge">Organik / Tanpa Kode</span>';
            $commission_info = '-';

            if ( $pedagang->id_desa > 0 && $pedagang->id_verifikator == 0 ) {
                $desa_name = $wpdb->get_var( $wpdb->prepare( "SELECT nama_desa FROM {$table_desa} WHERE id = %d", $pedagang->id_desa ) );
                $referral_info = '<span class="dw-badge dw-badge-primary">Referral Desa: ' . esc_html( $desa_name ) . '</span>';
                $commission_info = 'Masuk ke <strong>Kas Desa</strong>';
            } elseif ( $pedagang->id_verifikator > 0 ) {
                $verif_name = $wpdb->get_var( $wpdb->prepare( "SELECT nama_lengkap FROM {$table_verifikator} WHERE id = %d", $pedagang->id_verifikator ) );
                $referral_info = '<span class="dw-badge dw-badge-warning">Referral Verifikator: ' . esc_html( $verif_name ) . '</span>';
                $commission_info = 'Masuk ke <strong>Dompet Verifikator</strong>';
            }
            
            // Generate Share Links
            // 1. Link Toko (Untuk Pembeli)
            $link_toko = home_url( '/' . ($pedagang->slug_toko ?: 'toko') . '/?ref=' . $pedagang->kode_referral_saya );
            
            // 2. Link Pendaftaran (Updated ke /register/)
            $link_daftar = home_url( '/register/?ref=' . $pedagang->kode_referral_saya );
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline">Detail Pedagang: <?php echo esc_html( $pedagang->nama_toko ); ?></h1>
                <a href="?page=dw-pedagang" class="page-title-action">Kembali ke Daftar</a>
                <hr class="wp-header-end">
                
                <div class="dw-detail-grid">
                    <div class="dw-card">
                        <h3>Informasi Toko</h3>
                        <table class="form-table">
                            <tr><th>Pemilik</th><td><?php echo esc_html( $pedagang->nama_pemilik ); ?> (<?php echo esc_html( $pedagang->nomor_wa ); ?>)</td></tr>
                            <tr><th>Alamat</th><td><?php echo esc_html( $pedagang->alamat_lengkap ); ?></td></tr>
                            <tr><th>Status Akun</th><td><?php echo strtoupper( $pedagang->status_akun ); ?></td></tr>
                            <tr><th>Kuota Transaksi</th><td><strong><?php echo number_format( $pedagang->sisa_transaksi ); ?></strong> Transaksi</td></tr>
                        </table>
                        
                        <h4 style="margin-top:20px; border-bottom:1px solid #eee; padding-bottom:10px;">Link & Referral</h4>
                        <table class="form-table">
                            <tr>
                                <th>Kode Referral Saya</th>
                                <td><code style="background:#eef2f7; padding:5px 8px; font-size:14px; font-weight:bold; color:#2271b1;"><?php echo esc_html($pedagang->kode_referral_saya); ?></code></td>
                            </tr>
                            <tr>
                                <th>Link Toko (Pembeli)</th>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <input type="text" class="regular-text" value="<?php echo esc_url($link_toko); ?>" readonly onclick="this.select();">
                                        <a href="<?php echo esc_url($link_toko); ?>" target="_blank" class="button"><span class="dashicons dashicons-external"></span></a>
                                    </div>
                                    <p class="description">Bagikan ke pembeli untuk belanja.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Link Pendaftaran</th>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <input type="text" class="regular-text" value="<?php echo esc_url($link_daftar); ?>" readonly onclick="this.select();">
                                        <button type="button" class="button dw-copy-btn" data-clipboard-text="<?php echo esc_url($link_daftar); ?>">Salin</button>
                                    </div>
                                    <p class="description">Bagikan ke calon pedagang lain untuk mendaftar sebagai downline/mitra. <br><em>Link ini mengarah ke halaman <code>/register</code>.</em></p>
                                </td>
                            </tr>
                        </table>

                        <div style="margin-top:20px;">
                            <a href="?page=dw-pedagang&action=edit&id=<?php echo $pedagang->id; ?>" class="button button-primary">Edit Data Lengkap</a>
                        </div>
                    </div>

                    <div class="dw-card" style="border-left: 4px solid #72aee6;">
                        <h3>Relasi & Komisi</h3>
                        <p>Status pendaftaran pedagang ini:</p>
                        <div style="font-size: 1.2em; margin-bottom: 10px;"><?php echo $referral_info; ?></div>
                        <p class="description">
                            Mendaftar Menggunakan Kode: <strong><?php echo $pedagang->terdaftar_melalui_kode ? esc_html($pedagang->terdaftar_melalui_kode) : '-'; ?></strong><br>
                            Arah Komisi Transaksi: <?php echo $commission_info; ?>
                        </p>
                    </div>
                </div>
            </div>
            <style>
                .dw-detail-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
                .dw-card { background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
                .dw-badge { padding: 4px 8px; border-radius: 4px; background: #eee; font-weight: 600; display:inline-block; }
                .dw-badge-primary { background: #2271b1; color: #fff; }
                .dw-badge-warning { background: #f0b849; color: #1d2327; }
            </style>
            <script>
                jQuery(document).ready(function($){
                    $('.dw-copy-btn').click(function(){
                        var copyText = $(this).prev('input');
                        copyText.select();
                        document.execCommand("copy");
                        alert("Link tersalin: " + copyText.val());
                    });
                });
            </script>
            <?php
            return;
        endif;
    }

    /**
     * =========================================================================
     * 2. LOGIKA SAVE / UPDATE
     * =========================================================================
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pedagang'])) {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_pedagang_action')) {
            echo '<div class="notice notice-error is-dismissible"><p>Security Check Failed. Silakan refresh halaman.</p></div>'; 
            return;
        }

        $action = sanitize_text_field($_POST['action_pedagang']);

        if ($action === 'delete' && !empty($_POST['pedagang_id'])) {
            $wpdb->delete($table_name, ['id' => intval($_POST['pedagang_id'])]);
            $message = 'Data pedagang berhasil dihapus.'; $message_type = 'success';
        
        } elseif ($action === 'save') {
            // VALIDASI PHP
            if (empty($_POST['nama_toko'])) {
                $message = 'Gagal: Nama Toko wajib diisi.'; $message_type = 'error';
            } elseif (empty($_POST['nama_pemilik'])) {
                $message = 'Gagal: Nama Pemilik wajib diisi.'; $message_type = 'error';
            } else {
                
                $safe_array_map = function($input) {
                    if (!isset($input) || !is_array($input)) return [];
                    return array_map('sanitize_text_field', wp_unslash($input));
                };

                // A. RELASI INDUK
                $input_kode_induk = sanitize_text_field($_POST['manual_referral_code']);
                
                $id_desa_final = isset($_POST['current_id_desa']) ? intval($_POST['current_id_desa']) : 0;
                $id_verif_final = isset($_POST['current_id_verifikator']) ? intval($_POST['current_id_verifikator']) : 0;
                $is_independent = isset($_POST['current_is_independent']) ? intval($_POST['current_is_independent']) : 1;
                $kode_terdaftar_final = isset($_POST['current_terdaftar_via']) ? sanitize_text_field($_POST['current_terdaftar_via']) : '';

                if (isset($_POST['is_independent_choice'])) {
                    if ($_POST['is_independent_choice'] == '1') {
                        $id_desa_final = 0; $id_verif_final = 0; $is_independent = 1;
                    } else {
                        $is_independent = 0;
                    }
                }

                if (!empty($input_kode_induk)) {
                    if (class_exists('DW_Referral_Logic')) {
                        $logic = new DW_Referral_Logic();
                        $ref_data = $logic->get_referrer_data($input_kode_induk);

                        if ($ref_data) {
                            $is_independent = 0;
                            $kode_terdaftar_final = $input_kode_induk;
                            if ($ref_data['type'] === 'desa') {
                                $id_desa_final = $ref_data['id']; $id_verif_final = 0; 
                            } elseif ($ref_data['type'] === 'verifikator') {
                                $id_verif_final = $ref_data['id']; $id_desa_final = $ref_data['parent_desa_id'];
                            }
                        } else {
                            $message .= 'Peringatan: Kode Referral Induk tidak ditemukan. Relasi tidak berubah.<br>';
                        }
                    }
                }

                // B. KODE REFERRAL SAYA
                $kode_referral_saya = sanitize_text_field($_POST['kode_referral_saya']);
                if (empty($kode_referral_saya) || $kode_referral_saya == '(Otomatis)') {
                    if (class_exists('DW_Referral_Logic')) {
                        $logic = new DW_Referral_Logic();
                        $kode_referral_saya = $logic->generate_referral_code('pedagang', sanitize_text_field($_POST['nama_toko']));
                    } else {
                        $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $_POST['nama_toko']), 0, 3));
                        $kode_referral_saya = 'PDG-' . $clean . '-' . rand(1000,9999);
                    }
                }

                // C. ZONA ONGKIR JSON
                $ojek_zona_data = [
                    'satu_kecamatan' => [
                        'dekat' => ['harga' => floatval($_POST['ojek_dekat_harga']), 'desa_ids' => $safe_array_map($_POST['ojek_dekat_desa_ids'] ?? [])],
                        'jauh'  => ['harga' => floatval($_POST['ojek_jauh_harga']), 'desa_ids' => $safe_array_map($_POST['ojek_jauh_desa_ids'] ?? [])]
                    ],
                    'beda_kecamatan' => [
                        'dekat' => ['harga' => floatval($_POST['ojek_beda_kec_dekat_harga']), 'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_dekat_ids'] ?? [])],
                        'jauh'  => ['harga' => floatval($_POST['ojek_beda_kec_jauh_harga']), 'kecamatan_ids' => $safe_array_map($_POST['ojek_beda_kec_jauh_ids'] ?? [])]
                    ]
                ];

                // D. PREPARE DATA
                $data = [
                    'id_user'                => intval($_POST['id_user_pedagang']),
                    'nama_toko'              => sanitize_text_field($_POST['nama_toko']),
                    'slug_toko'              => sanitize_title($_POST['nama_toko']),
                    'nama_pemilik'           => sanitize_text_field($_POST['nama_pemilik']),
                    'nomor_wa'               => sanitize_text_field($_POST['nomor_wa']),
                    'nik'                    => sanitize_text_field($_POST['nik']),
                    // Relasi
                    'id_desa'                => $id_desa_final,
                    'id_verifikator'         => $id_verif_final,
                    'is_independent'         => $is_independent,
                    'terdaftar_melalui_kode' => $kode_terdaftar_final,
                    'kode_referral_saya'     => $kode_referral_saya,
                    // Alamat
                    'alamat_lengkap'         => sanitize_textarea_field($_POST['pedagang_detail']),
                    'api_provinsi_id'        => sanitize_text_field($_POST['pedagang_prov']),
                    'api_kabupaten_id'       => sanitize_text_field($_POST['pedagang_kota']),
                    'api_kecamatan_id'       => sanitize_text_field($_POST['pedagang_kec']),
                    'api_kelurahan_id'       => sanitize_text_field($_POST['pedagang_nama_id']),
                    'provinsi_nama'          => sanitize_text_field($_POST['provinsi_text']),
                    'kabupaten_nama'         => sanitize_text_field($_POST['kabupaten_text']),
                    'kecamatan_nama'         => sanitize_text_field($_POST['kecamatan_text']),
                    'kelurahan_nama'         => sanitize_text_field($_POST['kelurahan_text']),
                    'kode_pos'               => sanitize_text_field($_POST['kode_pos']),
                    'url_gmaps'              => esc_url_raw($_POST['url_gmaps']),
                    // Media
                    'foto_profil'            => esc_url_raw($_POST['foto_profil']),
                    'foto_sampul'            => esc_url_raw($_POST['foto_sampul']),
                    'url_ktp'                => esc_url_raw($_POST['url_ktp']),
                    'qris_image_url'         => esc_url_raw($_POST['qris_image_url']),
                    'nama_bank'              => sanitize_text_field($_POST['nama_bank']),
                    'no_rekening'            => sanitize_text_field($_POST['no_rekening']),
                    'atas_nama_rekening'     => sanitize_text_field($_POST['atas_nama_rekening']),
                    // Status
                    'status_pendaftaran'     => sanitize_text_field($_POST['status_pendaftaran']),
                    'status_akun'            => sanitize_text_field($_POST['status_akun']),
                    'sisa_transaksi'         => intval($_POST['sisa_transaksi']),
                    'shipping_ojek_lokal_aktif' => isset($_POST['shipping_ojek_lokal_aktif']) ? 1 : 0,
                    'shipping_ojek_lokal_zona'  => json_encode($ojek_zona_data),
                    'updated_at'             => current_time('mysql')
                ];

                if ($data['status_akun'] === 'aktif' && empty($_POST['old_verified_at'])) {
                    $data['is_verified'] = 1;
                    $data['verified_at'] = current_time('mysql');
                    $data['verified_by_id'] = get_current_user_id();
                }

                if (!empty($_POST['pedagang_id'])) {
                    $wpdb->update($table_name, $data, ['id' => intval($_POST['pedagang_id'])]);
                    $message .= 'Data Pedagang berhasil disimpan.'; 
                    if(empty($message_type)) $message_type = 'success';
                } else {
                    $data['created_at'] = current_time('mysql');
                    $wpdb->insert($table_name, $data);
                    $message = 'Pedagang baru berhasil ditambahkan.'; $message_type = 'success';
                }
            }
        }
    }

    /**
     * =========================================================================
     * 3. DATA PREPARATION
     * =========================================================================
     */
    $is_edit = ($action_view == 'edit' || $action_view == 'new');
    $edit_data = null;
    $ojek_zona = null;
    
    $default_data = (object) [
        'id' => 0, 'id_user' => 0, 'nama_toko' => '', 'slug_toko' => '', 'nama_pemilik' => '', 'nomor_wa' => '',
        'status_pendaftaran' => 'disetujui', 'status_akun' => 'aktif', 'sisa_transaksi' => 0,
        'id_desa' => 0, 'id_verifikator' => 0, 'is_independent' => 1, 
        'kode_referral_saya' => '', 'terdaftar_melalui_kode' => '', 'verified_at' => '',
        'api_provinsi_id'=>'', 'api_kabupaten_id'=>'', 'api_kecamatan_id'=>'', 'api_kelurahan_id'=>'',
        'provinsi_nama'=>'', 'kabupaten_nama'=>'', 'kecamatan_nama'=>'', 'kelurahan_nama'=>'',
        'kode_pos'=>'', 'url_gmaps'=>'', 'alamat_lengkap'=>'', 'nik'=>'',
        'foto_profil'=>'', 'foto_sampul'=>'', 'url_ktp'=>'', 'qris_image_url'=>'',
        'nama_bank'=>'', 'no_rekening'=>'', 'atas_nama_rekening'=>'',
        'shipping_ojek_lokal_aktif'=>0
    ];

    if ($is_edit && $id_view > 0) {
        $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id_view));
    }
    if (!$edit_data) {
        $edit_data = $default_data;
        if (class_exists('DW_Referral_Logic')) {
            $logic = new DW_Referral_Logic();
            $edit_data->kode_referral_saya = $logic->generate_referral_code('pedagang', 'NEW'); 
        }
    }

    // Persiapkan Share Link untuk Mode Edit
    // Format: domain.com/slug-toko/?ref=KODE
    $current_slug = $edit_data->slug_toko ? $edit_data->slug_toko : 'toko-baru';
    $share_link_edit = home_url( '/' . $current_slug . '/?ref=' . $edit_data->kode_referral_saya );
    // Update ke /register/
    $share_link_register = home_url( '/register/?ref=' . $edit_data->kode_referral_saya );

    $ojek_zona_decoded = json_decode($edit_data->shipping_ojek_lokal_zona ?? '', true);
    $default_zona = [
        'satu_kecamatan' => ['dekat' => ['harga' => 0, 'desa_ids' => []], 'jauh' => ['harga' => 0, 'desa_ids' => []]],
        'beda_kecamatan' => ['dekat' => ['harga' => 0, 'kecamatan_ids' => []], 'jauh' => ['harga' => 0, 'kecamatan_ids' => []]]
    ];
    $ojek_zona = array_replace_recursive($default_zona, is_array($ojek_zona_decoded) ? $ojek_zona_decoded : []);

    $nama_desa_induk = '-';
    $nama_verif_induk = '-';
    if (!empty($edit_data->id_desa)) {
        $d = $wpdb->get_row($wpdb->prepare("SELECT nama_desa FROM $table_desa WHERE id = %d", $edit_data->id_desa));
        if($d) $nama_desa_induk = $d->nama_desa;
    }
    if (!empty($edit_data->id_verifikator)) {
        $v = $wpdb->get_row($wpdb->prepare("SELECT nama_lengkap FROM $table_verifikator WHERE id = %d", $edit_data->id_verifikator));
        if($v) $nama_verif_induk = $v->nama_lengkap;
    }

    $users = get_users(['orderby' => 'display_name', 'role__in' => ['subscriber', 'customer', 'pedagang', 'umkm']]);

    /**
     * =========================================================================
     * 4. UI VIEW
     * =========================================================================
     */
    ?>
    <style>
        .dw-admin-wrap { max-width: 1280px; margin: 20px 20px 50px 0; }
        .dw-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .dw-tabs-layout { display: flex; min-height: 750px; border: 1px solid #c3c4c7; }
        .dw-tabs-nav { width: 220px; background: #f0f0f1; border-right: 1px solid #c3c4c7; list-style: none; padding: 0; margin: 0; flex-shrink: 0; }
        .dw-tab-trigger { padding: 15px 20px; cursor: pointer; color: #50575e; font-weight: 500; border-bottom: 1px solid #dcdcde; transition: 0.2s; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .dw-tab-trigger:hover { background: #fff; color: #2271b1; }
        .dw-tab-trigger.active { background: #fff; color: #135e96; font-weight: 600; border-left: 4px solid #2271b1; margin-right: -1px; }
        .dw-tab-content { flex: 1; padding: 30px; background: #fff; }
        .dw-tab-pane { display: none; animation: fadeIn 0.3s; }
        .dw-tab-pane.active { display: block; }
        .dw-form-group { margin-bottom: 20px; }
        .dw-form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #2c3338; }
        .dw-input { width: 100%; height: 36px; line-height: normal; }
        .dw-input-code { font-family: monospace; letter-spacing: 1px; font-weight: bold; background: #f6f7f7; color: #2271b1; border: 1px solid #8c8f94; text-transform: uppercase; }
        .sub-card { background: #fff; border: 1px solid #dcdcde; padding: 20px; border-radius: 5px; margin-bottom: 15px; }
        .sub-card h4 { margin-top: 0; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; color: #2271b1; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-aktif { background: #edfaef; color: #00a32a; border: 1px solid #c6e1c6; }
        .status-nonaktif { background: #f6f7f7; color: #50575e; border: 1px solid #dcdcde; }
    </style>

    <div class="wrap dw-admin-wrap">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-store" style="margin-right: 10px;"></span>
            <?php echo $is_edit ? 'Formulir Data Pedagang' : 'Manajemen Pedagang UMKM'; ?>
        </h1>
        <?php if(!$is_edit): ?>
            <a href="?page=dw-pedagang&action=new" class="page-title-action">Tambah Pedagang Baru</a>
        <?php endif; ?>
        <hr class="wp-header-end">

        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <?php if (!$is_edit): ?>
            <!-- LIST TABLE VIEW -->
            <div class="dw-card">
                <?php
                $search_q = isset($_GET['s']) ? esc_sql($_GET['s']) : '';
                $sql = "SELECT p.*, d.nama_desa AS nama_desa_induk, v.nama_lengkap AS nama_verifikator_induk
                        FROM {$table_name} p
                        LEFT JOIN {$table_desa} d ON p.id_desa = d.id
                        LEFT JOIN {$table_verifikator} v ON p.id_verifikator = v.id
                        WHERE 1=1 ";
                if($search_q) {
                    $sql .= " AND (p.nama_toko LIKE '%$search_q%' OR p.nama_pemilik LIKE '%$search_q%')";
                }
                $sql .= " ORDER BY p.created_at DESC";
                $results = $wpdb->get_results($sql);
                ?>
                <form method="get">
                    <input type="hidden" name="page" value="dw-pedagang">
                    <p class="search-box">
                        <label class="screen-reader-text" for="post-search-input">Cari Pedagang:</label>
                        <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_q); ?>">
                        <input type="submit" id="search-submit" class="button" value="Cari Pedagang">
                    </p>
                </form>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="20%">Nama Toko / Pemilik</th>
                            <th width="15%">Relasi Induk</th>
                            <th width="15%">Kode Saya</th>
                            <th width="10%">Status</th>
                            <th width="10%">Sisa Kuota</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($results): foreach($results as $r): ?>
                        <?php
                            $induk_display = '';
                            if ($r->is_independent == 1) {
                                $induk_display = '<span class="status-badge" style="background:#e5e7eb; color:#374151;">Independent</span>';
                            } else {
                                if ($r->nama_desa_induk) $induk_display = '<strong>Desa:</strong> ' . esc_html($r->nama_desa_induk);
                                elseif ($r->nama_verifikator_induk) $induk_display = '<strong>Verifikator:</strong> ' . esc_html($r->nama_verifikator_induk);
                                else $induk_display = '-';
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><a href="?page=dw-pedagang&action=edit&id=<?php echo $r->id; ?>"><?php echo esc_html($r->nama_toko); ?></a></strong><br>
                                <span class="description"><?php echo esc_html($r->nama_pemilik); ?></span>
                            </td>
                            <td><?php echo $induk_display; ?></td>
                            <td><code style="color:#2271b1;"><?php echo esc_html($r->kode_referral_saya); ?></code></td>
                            <td><span class="status-badge status-<?php echo $r->status_akun; ?>"><?php echo strtoupper($r->status_akun); ?></span></td>
                            <td><?php echo number_format($r->sisa_transaksi); ?></td>
                            <td style="text-align:right;">
                                <a href="?page=dw-pedagang&action=view_detail&id=<?php echo $r->id; ?>" class="button button-small" style="border-color:#2271b1; color:#2271b1;">Detail</a>
                                <a href="?page=dw-pedagang&action=edit&id=<?php echo $r->id; ?>" class="button button-small">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus permanen?');">
                                    <input type="hidden" name="action_pedagang" value="delete">
                                    <input type="hidden" name="pedagang_id" value="<?php echo $r->id; ?>">
                                    <?php wp_nonce_field('dw_pedagang_action'); ?>
                                    <button type="submit" class="button button-small" style="color:#d63638; border-color:#d63638;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" style="text-align:center;">Data tidak ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- FORM EDIT (VERTICAL TABS) -->
            <form method="post" id="form-pedagang">
                <?php wp_nonce_field('dw_pedagang_action'); ?>
                <input type="hidden" name="action_pedagang" value="save">
                <?php if ($id_view > 0): ?><input type="hidden" name="pedagang_id" value="<?php echo $id_view; ?>"><?php endif; ?>
                
                <!-- Hidden Fields -->
                <input type="hidden" name="current_id_desa" value="<?php echo esc_attr($edit_data->id_desa); ?>">
                <input type="hidden" name="current_id_verifikator" value="<?php echo esc_attr($edit_data->id_verifikator); ?>">
                <input type="hidden" name="current_is_independent" value="<?php echo esc_attr($edit_data->is_independent); ?>">
                <input type="hidden" name="current_terdaftar_via" value="<?php echo esc_attr($edit_data->terdaftar_melalui_kode); ?>">
                <input type="hidden" name="old_verified_at" value="<?php echo esc_attr($edit_data->verified_at); ?>">

                <div class="dw-card dw-tabs-layout">
                    <!-- Sidebar Nav -->
                    <ul class="dw-tabs-nav">
                        <li class="dw-tab-trigger active" data-target="tab-info"><span class="dashicons dashicons-id"></span> Info Toko</li>
                        <li class="dw-tab-trigger" data-target="tab-relasi"><span class="dashicons dashicons-networking"></span> Relasi & Referral</li>
                        <li class="dw-tab-trigger" data-target="tab-lokasi"><span class="dashicons dashicons-location"></span> Wilayah & Alamat</li>
                        <li class="dw-tab-trigger" data-target="tab-ongkir"><span class="dashicons dashicons-car"></span> Ongkir Lokal</li>
                        <li class="dw-tab-trigger" data-target="tab-media"><span class="dashicons dashicons-format-image"></span> Media & Legalitas</li>
                        <li class="dw-tab-trigger" data-target="tab-keuangan"><span class="dashicons dashicons-money"></span> Data Keuangan</li>
                    </ul>

                    <!-- Content -->
                    <div class="dw-tab-content">
                        
                        <!-- TAB 1: INFO TOKO -->
                        <div id="tab-info" class="dw-tab-pane active">
                            <h2>Informasi Dasar Toko</h2>
                            <div class="dw-form-group">
                                <label>Pemilik Akun (User WordPress)</label>
                                <select name="id_user_pedagang" class="dw-input select2">
                                    <option value="">-- Pilih User --</option>
                                    <?php foreach($users as $u) echo '<option value="'.$u->ID.'" '.selected($edit_data->id_user, $u->ID, false).'>'.$u->display_name.' ('.$u->user_email.')</option>'; ?>
                                </select>
                            </div>
                            <div style="display:flex; gap:20px;">
                                <div class="dw-form-group" style="flex:1;">
                                    <label>Nama Toko <span style="color:red;">*</span></label>
                                    <!-- REQUIRED attribute removed to prevent hidden tab validation error -->
                                    <input type="text" name="nama_toko" class="dw-input" value="<?php echo esc_attr($edit_data->nama_toko); ?>">
                                </div>
                                <div class="dw-form-group" style="flex:1;">
                                    <label>Nama Pemilik <span style="color:red;">*</span></label>
                                    <input type="text" name="nama_pemilik" class="dw-input" value="<?php echo esc_attr($edit_data->nama_pemilik); ?>">
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Nomor WhatsApp</label>
                                <input type="text" name="nomor_wa" class="dw-input" value="<?php echo esc_attr($edit_data->nomor_wa); ?>">
                            </div>
                            <div style="display:flex; gap:20px;">
                                <div class="dw-form-group" style="flex:1;">
                                    <label>Status Akun</label>
                                    <select name="status_akun" class="dw-input" style="font-weight:bold;">
                                        <option value="aktif" <?php selected($edit_data->status_akun, 'aktif'); ?>>AKTIF</option>
                                        <option value="nonaktif" <?php selected($edit_data->status_akun, 'nonaktif'); ?>>NONAKTIF</option>
                                        <option value="suspend" <?php selected($edit_data->status_akun, 'suspend'); ?>>SUSPEND</option>
                                    </select>
                                </div>
                                <div class="dw-form-group" style="flex:1;">
                                    <label>Status Pendaftaran</label>
                                    <select name="status_pendaftaran" class="dw-input">
                                        <option value="disetujui" <?php selected($edit_data->status_pendaftaran, 'disetujui'); ?>>Disetujui</option>
                                        <option value="menunggu_desa" <?php selected($edit_data->status_pendaftaran, 'menunggu_desa'); ?>>Menunggu Verifikasi Desa</option>
                                        <option value="menunggu" <?php selected($edit_data->status_pendaftaran, 'menunggu'); ?>>Menunggu Admin</option>
                                        <option value="ditolak" <?php selected($edit_data->status_pendaftaran, 'ditolak'); ?>>Ditolak</option>
                                    </select>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Sisa Kuota Transaksi</label>
                                <input type="number" name="sisa_transaksi" class="dw-input" value="<?php echo intval($edit_data->sisa_transaksi); ?>" style="width:150px;">
                            </div>
                        </div>

                        <!-- TAB 2: RELASI & REFERRAL -->
                        <div id="tab-relasi" class="dw-tab-pane">
                            <h2>Relasi & Kode Referral</h2>
                            <div class="sub-card" style="border-left: 4px solid #00a32a;">
                                <h4>Kode Referral Saya</h4>
                                <p class="description">Kode ini otomatis dibuat sistem. Sebarkan ke pembeli untuk mendapatkan bonus transaksi.</p>
                                <div class="dw-form-group">
                                    <label>Kode Unik Pedagang</label>
                                    <input type="text" name="kode_referral_saya" class="dw-input dw-input-code" value="<?php echo esc_attr($edit_data->kode_referral_saya); ?>" placeholder="Kode akan otomatis dibuat jika kosong">
                                </div>
                                <div class="dw-form-group" style="margin-top:15px;">
                                    <label>Link Referal Toko (Bagikan ini)</label>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" class="dw-input" value="<?php echo esc_url($share_link_edit); ?>" readonly onclick="this.select();" style="background:#f0f0f1; cursor:pointer;">
                                        <button type="button" class="button dw-copy-btn" data-clipboard-text="<?php echo esc_url($share_link_edit); ?>">Salin</button>
                                    </div>
                                    <p class="description" style="font-size:12px;">Format: <?php echo esc_html(home_url('/namatoko/?ref=KODE')); ?></p>
                                </div>
                                <div class="dw-form-group" style="margin-top:15px;">
                                    <label>Link Pendaftaran Downline/Reseller</label>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" class="dw-input" value="<?php echo esc_url($share_link_register); ?>" readonly onclick="this.select();" style="background:#f0f0f1; cursor:pointer;">
                                        <button type="button" class="button dw-copy-btn" data-clipboard-text="<?php echo esc_url($share_link_register); ?>">Salin</button>
                                    </div>
                                    <p class="description" style="font-size:12px;">Gunakan link ini untuk mengajak pedagang lain mendaftar di bawah jaringan Anda.</p>
                                </div>
                            </div>
                            <div class="sub-card" style="border-left: 4px solid #2271b1;">
                                <h4>Relasi Induk</h4>
                                <table class="form-table" style="margin:0;">
                                    <tr>
                                        <th>Status Saat Ini</th>
                                        <td>
                                            <select name="is_independent_choice" class="dw-input" style="width:auto;">
                                                <option value="0" <?php selected($edit_data->is_independent, 0); ?>>Terhubung (Punya Induk)</option>
                                                <option value="1" <?php selected($edit_data->is_independent, 1); ?>>Independent (Langsung Admin)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Induk Aktif</th>
                                        <td>
                                            <?php if($edit_data->is_independent == 1): ?>
                                                <span class="status-badge" style="background:#e5e7eb;">Admin Pusat (Independent)</span>
                                            <?php else: ?>
                                                <ul style="margin:0; padding-left:15px;">
                                                    <li><strong>Desa:</strong> <?php echo esc_html($nama_desa_induk); ?> (ID: <?php echo $edit_data->id_desa; ?>)</li>
                                                    <li><strong>Verifikator:</strong> <?php echo esc_html($nama_verif_induk); ?> (ID: <?php echo $edit_data->id_verifikator; ?>)</li>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr style="background:#f0f6fc;">
                                        <th>Pindah Induk</th>
                                        <td>
                                            <input type="text" name="manual_referral_code" class="dw-input dw-input-code" placeholder="Masukkan Kode Referral Desa/Verifikator Baru...">
                                            <p class="description" style="color:#d63638;">Isi form ini <strong>HANYA</strong> jika ingin memindahkan pedagang ke Induk lain.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 3: LOKASI -->
                        <div id="tab-lokasi" class="dw-tab-pane">
                            <h2>Wilayah & Alamat</h2>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                <div class="dw-form-group">
                                    <label>Provinsi</label>
                                    <select name="pedagang_prov" class="dw-region-prov dw-input" data-current="<?php echo esc_attr($edit_data->api_provinsi_id); ?>">
                                        <option value="">Memuat...</option>
                                    </select>
                                </div>
                                <div class="dw-form-group">
                                    <label>Kota/Kabupaten</label>
                                    <select name="pedagang_kota" class="dw-region-kota dw-input" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id); ?>">
                                        <option value="">-- Pilih Provinsi Dulu --</option>
                                    </select>
                                </div>
                                <div class="dw-form-group">
                                    <label>Kecamatan</label>
                                    <select name="pedagang_kec" class="dw-region-kec dw-input" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id); ?>">
                                        <option value="">-- Pilih Kota Dulu --</option>
                                    </select>
                                </div>
                                <div class="dw-form-group">
                                    <label>Kelurahan/Desa</label>
                                    <select name="pedagang_nama_id" class="dw-region-desa dw-input" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id); ?>">
                                        <option value="">-- Pilih Kecamatan Dulu --</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Hidden Text Inputs -->
                            <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi_nama); ?>">
                            <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten_nama); ?>">
                            <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan_nama); ?>">
                            <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan_nama); ?>">

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                <div class="dw-form-group">
                                    <label>Kode Pos</label>
                                    <input type="text" name="kode_pos" class="dw-input" value="<?php echo esc_attr($edit_data->kode_pos); ?>">
                                </div>
                                <div class="dw-form-group">
                                    <label>URL Google Maps</label>
                                    <input type="text" name="url_gmaps" class="dw-input" value="<?php echo esc_attr($edit_data->url_gmaps); ?>">
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Alamat Lengkap</label>
                                <textarea name="pedagang_detail" class="dw-input" rows="3"><?php echo esc_textarea($edit_data->alamat_lengkap); ?></textarea>
                            </div>
                        </div>

                        <!-- TAB 4: ONGKIR LOKAL -->
                        <div id="tab-ongkir" class="dw-tab-pane">
                            <h2>Ongkos Kirim Lokal</h2>
                            <div class="dw-form-group">
                                <label><input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked($edit_data->shipping_ojek_lokal_aktif, 1); ?>> Aktifkan Layanan</label>
                            </div>
                            <div class="sub-card">
                                <h4>Zona 1: Satu Kecamatan</h4>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                    <div>
                                        <label>Tarif Dekat (Rp)</label>
                                        <input type="number" name="ojek_dekat_harga" class="dw-input" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['dekat']['harga']); ?>">
                                        <label style="margin-top:5px;">Desa Include:</label>
                                        <select name="ojek_dekat_desa_ids[]" class="dw-input select2-villages" multiple>
                                            <?php if($ojek_zona['satu_kecamatan']['dekat']['desa_ids']) foreach($ojek_zona['satu_kecamatan']['dekat']['desa_ids'] as $v) echo "<option value='$v' selected>$v</option>"; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Tarif Jauh (Rp)</label>
                                        <input type="number" name="ojek_jauh_harga" class="dw-input" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['jauh']['harga']); ?>">
                                        <label style="margin-top:5px;">Desa Include:</label>
                                        <select name="ojek_jauh_desa_ids[]" class="dw-input select2-villages" multiple>
                                            <?php if($ojek_zona['satu_kecamatan']['jauh']['desa_ids']) foreach($ojek_zona['satu_kecamatan']['jauh']['desa_ids'] as $v) echo "<option value='$v' selected>$v</option>"; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="sub-card">
                                <h4>Zona 2: Beda Kecamatan (Satu Kota)</h4>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                    <div>
                                        <label>Tarif Dekat (Rp)</label>
                                        <input type="number" name="ojek_beda_kec_dekat_harga" class="dw-input" value="<?php echo esc_attr($ojek_zona['beda_kecamatan']['dekat']['harga']); ?>">
                                        <label style="margin-top:5px;">Kecamatan Include:</label>
                                        <select name="ojek_beda_kec_dekat_ids[]" class="dw-input select2-districts" multiple>
                                            <?php if($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids']) foreach($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'] as $v) echo "<option value='$v' selected>$v</option>"; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Tarif Jauh (Rp)</label>
                                        <input type="number" name="ojek_beda_kec_jauh_harga" class="dw-input" value="<?php echo esc_attr($ojek_zona['beda_kecamatan']['jauh']['harga']); ?>">
                                        <label style="margin-top:5px;">Kecamatan Include:</label>
                                        <select name="ojek_beda_kec_jauh_ids[]" class="dw-input select2-districts" multiple>
                                            <?php if($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids']) foreach($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'] as $v) echo "<option value='$v' selected>$v</option>"; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 5: MEDIA -->
                        <div id="tab-media" class="dw-tab-pane">
                            <h2>Media & Dokumen</h2>
                            <div class="dw-form-group">
                                <label>NIK KTP</label>
                                <input type="text" name="nik" class="dw-input" value="<?php echo esc_attr($edit_data->nik); ?>" maxlength="16">
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                <div class="dw-form-group">
                                    <label>Foto Profil</label>
                                    <input type="text" name="foto_profil" id="f_profil" class="dw-input" value="<?php echo esc_attr($edit_data->foto_profil); ?>">
                                    <button type="button" class="button btn_upload" data-target="#f_profil">Upload</button>
                                </div>
                                <div class="dw-form-group">
                                    <label>Foto Sampul</label>
                                    <input type="text" name="foto_sampul" id="f_sampul" class="dw-input" value="<?php echo esc_attr($edit_data->foto_sampul); ?>">
                                    <button type="button" class="button btn_upload" data-target="#f_sampul">Upload</button>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>Foto KTP</label>
                                <input type="text" name="url_ktp" id="f_ktp" class="dw-input" value="<?php echo esc_attr($edit_data->url_ktp); ?>">
                                <button type="button" class="button btn_upload" data-target="#f_ktp">Upload</button>
                            </div>
                        </div>

                        <!-- TAB 6: KEUANGAN -->
                        <div id="tab-keuangan" class="dw-tab-pane">
                            <h2>Data Rekening</h2>
                            <div class="dw-form-group">
                                <label>Nama Bank</label>
                                <input type="text" name="nama_bank" class="dw-input" value="<?php echo esc_attr($edit_data->nama_bank); ?>">
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                <div class="dw-form-group">
                                    <label>No. Rekening</label>
                                    <input type="text" name="no_rekening" class="dw-input" value="<?php echo esc_attr($edit_data->no_rekening); ?>">
                                </div>
                                <div class="dw-form-group">
                                    <label>Atas Nama</label>
                                    <input type="text" name="atas_nama_rekening" class="dw-input" value="<?php echo esc_attr($edit_data->atas_nama_rekening); ?>">
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label>QRIS Image</label>
                                <input type="text" name="qris_image_url" id="f_qris" class="dw-input" value="<?php echo esc_attr($edit_data->qris_image_url); ?>">
                                <button type="button" class="button btn_upload" data-target="#f_qris">Upload QRIS</button>
                            </div>
                        </div>

                    </div>
                </div>
                
                <!-- SINGLE PRIMARY SAVE BUTTON -->
                <div style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #c3c4c7; border-top: none; text-align: right;">
                    <a href="?page=dw-pedagang" class="button button-secondary" style="margin-right: 10px;">Batal</a>
                    <button type="submit" class="button button-primary button-large">Simpan Semua Perubahan</button>
                </div>
                
            </form>
        <?php endif; ?>
    </div>

    <!-- JAVASCRIPT HANDLERS -->
    <script>
    jQuery(document).ready(function($){
        // 1. Tab Handler
        $('.dw-tab-trigger').on('click', function(){
            $('.dw-tab-trigger').removeClass('active');
            $(this).addClass('active');
            $('.dw-tab-pane').removeClass('active');
            $('#' + $(this).data('target')).addClass('active');
            // Re-init Select2 if needed (fix hidden element width)
            if($(this).data('target') === 'tab-ongkir' || $(this).data('target') === 'tab-info') {
                $('.select2, .select2-villages, .select2-districts').select2({ width:'100%' });
            }
        });

        // 2. Media Uploader
        $(document).on('click', '.btn_upload', function(e){
            e.preventDefault();
            var btn = $(this), target = btn.data('target');
            var frame = wp.media({ title: 'Pilih File', multiple: false }).on('select', function(){
                $(target).val(frame.state().get('selection').first().toJSON().url);
            }).open();
        });

        // Copy Button Handler
        $('.dw-copy-btn').click(function(){
            var copyText = $(this).data('clipboard-text');
            var tempInput = $("<input>");
            $("body").append(tempInput);
            tempInput.val(copyText).select();
            document.execCommand("copy");
            tempInput.remove();
            alert("Link tersalin: " + copyText);
        });

        // 3. Address API Waterfall Loader
        function loadRegion(type, pid, target, selId) {
            var act = '';
            if(type === 'prov') act = 'dw_fetch_provinces';
            else if(type === 'kota') act = 'dw_fetch_regencies';
            else if(type === 'kec') act = 'dw_fetch_districts';
            else if(type === 'desa') act = 'dw_fetch_villages';
            
            if(type !== 'prov' && (!pid || pid === "")) return;

            var data = { 
                action: act, 
                nonce: '<?php echo wp_create_nonce("dw_region_nonce"); ?>' 
            };
            if(type==='kota') data.province_id = pid;
            if(type==='kec') data.regency_id = pid;
            if(type==='desa') data.district_id = pid;

            $.get(ajaxurl, data, function(res){
                if(res.success){
                    target.empty().append('<option value="">Pilih...</option>');
                    $.each(res.data, function(i,v){
                        var isSelected = (String(v.id) === String(selId));
                        target.append('<option value="'+v.id+'" '+(isSelected?'selected':'')+'>'+v.name+'</option>');
                    });
                    
                    if(selId) {
                        target.val(selId); 
                        target.trigger('change.dw_waterfall'); 
                    }
                }
            });
        }

        // --- Event Listeners for User Interaction ---
        $('.dw-region-prov').on('change', function(e){ 
            if(e.originalEvent) {
                var val = $(this).val();
                var txt = $(this).find('option:selected').text();
                $('.dw-text-prov').val(txt);
                $('.dw-region-kota').empty().append('<option value="">Memuat...</option>');
                $('.dw-region-kec, .dw-region-desa').empty().append('<option value="">--</option>');
                loadRegion('kota', val, $('.dw-region-kota'), null);
            }
        });

        $('.dw-region-kota').on('change', function(e){ 
            if(e.originalEvent) {
                var val = $(this).val();
                var txt = $(this).find('option:selected').text();
                $('.dw-text-kota').val(txt);
                $('.dw-region-kec').empty().append('<option value="">Memuat...</option>');
                $('.dw-region-desa').empty().append('<option value="">--</option>');
                loadRegion('kec', val, $('.dw-region-kec'), null);
            }
        });

        $('.dw-region-kec').on('change', function(e){ 
            if(e.originalEvent) {
                var val = $(this).val();
                var txt = $(this).find('option:selected').text();
                $('.dw-text-kec').val(txt);
                $('.dw-region-desa').empty().append('<option value="">Memuat...</option>');
                loadRegion('desa', val, $('.dw-region-desa'), null);
            }
        });

        $('.dw-region-desa').on('change', function(e){
            if(e.originalEvent) {
                var txt = $(this).find('option:selected').text();
                $('.dw-text-desa').val(txt);
            }
        });

        // --- Waterfall Handlers (Auto Load Edit Mode) ---
        $('.dw-region-prov').on('change.dw_waterfall', function(){
            var val = $(this).val();
            var nextSel = $('.dw-region-kota').data('current');
            loadRegion('kota', val, $('.dw-region-kota'), nextSel);
        });

        $('.dw-region-kota').on('change.dw_waterfall', function(){
            var val = $(this).val();
            var nextSel = $('.dw-region-kec').data('current');
            loadRegion('kec', val, $('.dw-region-kec'), nextSel);
        });

        $('.dw-region-kec').on('change.dw_waterfall', function(){
            var val = $(this).val();
            var nextSel = $('.dw-region-desa').data('current');
            loadRegion('desa', val, $('.dw-region-desa'), nextSel);
        });

        // 4. Initial Load
        var initProvVal = $('.dw-region-prov').data('current');
        loadRegion('prov', null, $('.dw-region-prov'), initProvVal);

        // 5. Init Select2
        if ($.fn.select2) {
            $('.select2, .select2-villages, .select2-districts').select2({ width:'100%' });
        }
    });
    </script>
    <?php
}