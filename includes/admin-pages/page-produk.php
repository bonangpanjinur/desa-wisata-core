<?php
/**
 * File Name: includes/admin-pages/page-produk.php
 * Description: Manajemen Produk Lengkap (Modern UI/UX Enhanced).
 * Features: Stats Dashboard, Tabbed Form, Modern Table, Gallery, Variation Management & Flash Sale.
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

// --- 1. FIX PATHS & INCLUDES (Relative Path for Safety) ---
$current_dir = dirname(__FILE__); // includes/admin-pages
$includes_dir = dirname($current_dir); // includes

// Include UI components
$ui_path = $includes_dir . '/admin-ui-components.php';
if (file_exists($ui_path)) {
    require_once $ui_path;
}

/**
 * 1. HANDLER: SIMPAN & HAPUS
 * Fungsi ini menangani logika database.
 * NOTE: Dipanggil manual di awal fungsi render agar redirect berfungsi.
 */
function dw_produk_form_handler() {
    global $wpdb;
    
    // --- A. LOGIKA HAPUS (DELETE) ---
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        // Validasi Nonce URL
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'dw_del_prod_nonce')) {
            dw_add_notice('Security check failed (Nonce Error).', 'error');
            return;
        }
        dw_handle_delete_produk(intval($_GET['id']));
    }

    // --- LOGIKA PIN TO TOP (FEATURED) ---
    if (isset($_GET['action']) && $_GET['action'] == 'toggle_featured' && isset($_GET['id'])) {
        if (!current_user_can('manage_options')) return;
        $id = intval($_GET['id']);
        $current_featured = $wpdb->get_var($wpdb->prepare("SELECT is_featured FROM {$wpdb->prefix}dw_produk WHERE id = %d", $id));
        $wpdb->update("{$wpdb->prefix}dw_produk", ['is_featured' => $current_featured ? 0 : 1], ['id' => $id]);
        dw_add_notice('Status Featured berhasil diperbarui.', 'success');
        wp_redirect(remove_query_arg(['action', 'id'])); exit;
    }

    // --- B. LOGIKA SIMPAN (SAVE/UPDATE) ---
    // 1. Cek apakah tombol submit ditekan
    if (!isset($_POST['dw_submit_produk'])) return;

    // 2. Cek Nonce Form
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_prod_save')) {
        dw_add_notice('Security check failed. Silakan refresh halaman.', 'error');
        return;
    }
    
    // Asumsi nama tabel menggunakan prefix 'dw_'. 
    $table_produk   = $wpdb->prefix . 'dw_produk'; 
    $table_variasi  = $wpdb->prefix . 'dw_produk_variasi';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    // 3. Validasi Pedagang
    $id_pedagang_input = 0;
    if ($is_super_admin) {
        $id_pedagang_input = isset($_POST['id_pedagang']) ? intval($_POST['id_pedagang']) : 0;
    } else {
        if (!$my_pedagang_data) {
            dw_add_notice('Anda belum terdaftar sebagai pedagang.', 'error');
            return;
        }
        $id_pedagang_input = intval($my_pedagang_data->id);
    }

    // 4. Proses Galeri (Array -> JSON)
    $galeri_json = '[]';
    if (!empty($_POST['galeri_urls'])) {
        // wp_unslash penting sebelum sanitasi jika data post raw
        $raw_urls = explode(',', wp_unslash($_POST['galeri_urls']));
        $galeri_array = array_filter($raw_urls); // Hapus elemen kosong
        $galeri_json = json_encode(array_values($galeri_array));
    }

    // Bersihkan format harga (hapus titik/koma jika ada, pastikan format DB friendly)
    // Menggunakan string filter untuk DECIMAL agar presisi terjaga
    $raw_harga = wp_unslash($_POST['harga']);
    $harga_db = preg_replace('/[^0-9.]/', '', $raw_harga); 

    // Sanitasi Harga Promo
    $raw_promo = isset($_POST['promo_price']) ? wp_unslash($_POST['promo_price']) : 0;
    $promo_db = preg_replace('/[^0-9.]/', '', $raw_promo);

    // 5. Data Utama Produk (Sanitasi Lengkap Sesuai Schema)
    $data = [
        'id_pedagang'   => $id_pedagang_input,
        'nama_produk'   => sanitize_text_field(wp_unslash($_POST['nama_produk'])),
        'slug'          => sanitize_title(wp_unslash($_POST['nama_produk'])),
        'deskripsi'     => wp_kses_post(wp_unslash($_POST['deskripsi'])),
        'harga'         => $harga_db, // DECIMAL(15,2)
        'stok'          => intval(wp_unslash($_POST['stok'])),
        'berat_gram'    => intval(wp_unslash($_POST['berat_gram'])),
        'kondisi'       => sanitize_key(wp_unslash($_POST['kondisi'])), // ENUM('baru','bekas')
        'kategori'      => sanitize_text_field(wp_unslash($_POST['kategori'])),
        'foto_utama'    => esc_url_raw(wp_unslash($_POST['foto_utama'])),
        'galeri'        => $galeri_json, // JSON
        'status'        => sanitize_text_field(wp_unslash($_POST['status'])), // ENUM
        
        // FASE 1: FLASH SALE FIELDS
        'is_promo'      => isset($_POST['is_promo']) ? 1 : 0,
        'promo_price'   => $promo_db,
        'promo_start'   => sanitize_text_field(wp_unslash($_POST['promo_start'])),
        'promo_end'     => sanitize_text_field(wp_unslash($_POST['promo_end'])),
        
        'updated_at'    => current_time('mysql')
    ];

    $produk_id = isset($_POST['produk_id']) ? intval($_POST['produk_id']) : 0;
    $notif_msg = '';
    $success = false;

    // 6. Eksekusi Simpan Produk Utama
    if ($produk_id > 0) {
        // Mode Edit: Cek kepemilikan jika bukan admin
        if (!$is_super_admin) {
            $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_produk WHERE id=%d AND id_pedagang=%d", $produk_id, $id_pedagang_input));
            if (!$check) {
                dw_add_notice('Dilarang mengedit produk toko lain.', 'error');
                return;
            }
        }
        $result = $wpdb->update($table_produk, $data, ['id' => $produk_id]);
        if ($result === false) {
            dw_add_notice('Gagal update database: ' . $wpdb->last_error, 'error');
            return;
        }
        $notif_msg = 'Produk berhasil diperbarui.';
        $success = true;
    } else {
        // Mode Baru
        $data['created_at'] = current_time('mysql');
        // Set default values untuk kolom lain jika perlu (terjual, rating_avg, dilihat default 0 di DB)
        
        $result = $wpdb->insert($table_produk, $data);
        if ($result === false) {
            dw_add_notice('Gagal menyimpan database: ' . $wpdb->last_error, 'error');
            return;
        }
        $produk_id = $wpdb->insert_id;
        $notif_msg = 'Produk baru berhasil ditambahkan.';
        $success = true;
    }

    // 7. Simpan Variasi (Hapus Lama -> Insert Baru)
    if ($success && $produk_id) {
        // Hapus variasi lama
        $wpdb->delete($table_variasi, ['id_produk' => $produk_id]);

        // Insert variasi baru
        if (!empty($_POST['var_nama']) && is_array($_POST['var_nama'])) {
            $var_nama  = $_POST['var_nama'];
            $var_harga = $_POST['var_harga'];
            $var_stok  = $_POST['var_stok'];
            $var_sku   = $_POST['var_sku'];
            $var_foto  = $_POST['var_foto'];

            $count = count($var_nama);
            for ($i = 0; $i < $count; $i++) {
                if (empty($var_nama[$i])) continue;

                $raw_harga_var = $var_harga[$i];
                $harga_var_db = preg_replace('/[^0-9.]/', '', $raw_harga_var);

                $var_data = [
                    'id_produk'         => $produk_id,
                    'deskripsi_variasi' => sanitize_text_field(wp_unslash($var_nama[$i])),
                    'harga_variasi'     => $harga_var_db,
                    'stok_variasi'      => intval(wp_unslash($var_stok[$i])),
                    'sku'               => sanitize_text_field(wp_unslash($var_sku[$i])),
                    'foto'              => esc_url_raw(wp_unslash($var_foto[$i])),
                    'is_default'        => ($i === 0) ? 1 : 0
                ];
                $wpdb->insert($table_variasi, $var_data);
            }
        }
    }

    // 8. Redirect Sukses
    dw_add_notice($notif_msg, 'success');
    // Gunakan redirect Javascript jika headers sudah terkirim (fallback), atau wp_redirect standar
    if (!headers_sent()) {
        wp_redirect(add_query_arg(['page' => 'dw-produk', 'action' => 'edit', 'id' => $produk_id], admin_url('admin.php')));
        exit;
    } else {
        echo '<script>window.location.href="' . add_query_arg(['page' => 'dw-produk', 'action' => 'edit', 'id' => $produk_id], admin_url('admin.php')) . '";</script>';
        exit;
    }
}

// Helper Delete
function dw_handle_delete_produk($id) {
    global $wpdb;
    $current_user_id = get_current_user_id();
    $is_super_admin = current_user_can('administrator') || current_user_can('admin_kabupaten');

    if (!$is_super_admin) {
        $my_pedagang_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_pedagang WHERE id_user = %d", $current_user_id));
        $is_owner = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dw_produk WHERE id = %d AND id_pedagang = %d", $id, $my_pedagang_id));
        if (!$is_owner) wp_die('Akses Ditolak.');
    }

    $wpdb->delete("{$wpdb->prefix}dw_produk_variasi", ['id_produk' => $id]);
    $wpdb->delete("{$wpdb->prefix}dw_produk", ['id' => $id]);

    dw_add_notice('Produk berhasil dihapus.', 'success');
    wp_redirect(admin_url('admin.php?page=dw-produk')); exit;
}

// Helper Notice
function dw_add_notice($msg, $type) {
    // Simpan notice ke transient agar muncul setelah redirect
    $notices = get_transient('dw_produk_notices') ?: [];
    $notices[] = ['msg' => $msg, 'type' => $type];
    set_transient('dw_produk_notices', $notices, 45);
}

// Helper Display Notice (Panggil di render)
function dw_display_notices() {
    $notices = get_transient('dw_produk_notices');
    if ($notices) {
        foreach ($notices as $notice) {
            $class = ($notice['type'] == 'success') ? 'updated' : 'error';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($notice['msg']) . '</p></div>';
        }
        delete_transient('dw_produk_notices');
    }
}

/**
 * 2. RENDER HALAMAN UTAMA (UI MODERN)
 */
function dw_produk_page_info_render() {
    // --- PENTING: Panggil Handler Di Sini Sebelum HTML Keluar ---
    dw_produk_form_handler(); 
    // -----------------------------------------------------------

    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_variasi  = $wpdb->prefix . 'dw_produk_variasi';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten');
    
    // Ambil Data Pedagang User Ini
    $my_pedagang_data = $wpdb->get_row($wpdb->prepare("SELECT id, nama_toko FROM $table_pedagang WHERE id_user = %d", $current_user_id));

    $action = $_GET['action'] ?? 'list';
    $is_edit = ($action == 'new' || $action == 'edit');
    $edit_data = null;
    $variasi_list = [];

    // --- LOGIKA EDIT ---
    if ($action == 'edit' && isset($_GET['id'])) {
        $produk_id = intval($_GET['id']);
        $query_prod = "SELECT * FROM $table_produk WHERE id=%d";
        if (!$is_super_admin && $my_pedagang_data) {
            $query_prod .= " AND id_pedagang = " . intval($my_pedagang_data->id);
        }
        $edit_data = $wpdb->get_row($wpdb->prepare($query_prod, $produk_id));
        
        if ($edit_data) {
            $variasi_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_variasi WHERE id_produk = %d ORDER BY id ASC", $produk_id));
        } else { 
            echo '<div class="notice notice-error"><p>Produk tidak ditemukan atau akses ditolak.</p></div>'; return; 
        }
    }

    // Ambil Kategori
    $kategori_terms = get_terms(['taxonomy' => 'kategori_produk', 'hide_empty' => false]);
    ?>

    <!-- VIEW SECTION -->
    <div class="wrap dw-admin-wrapper">
        <!-- HEADER -->
        <div class="dw-page-header">
            <div class="dw-header-title">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p class="dw-subtitle">Kelola katalog produk, stok, variasi, dan promo.</p>
            </div>
            <div class="dw-header-actions">
                <?php if (!$is_edit): ?>
                    <a href="?page=dw-produk&action=new" class="dw-button dw-button-primary">
                        <span class="dashicons dashicons-plus-alt2" style="margin-right:5px;"></span> Tambah Produk
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php dw_display_notices(); // Tampilkan Notif disini ?>

        <?php if($is_edit): ?>
            <!-- === VIEW: ADD / EDIT === -->
            <form method="post" action="" class="dw-form-grid">
                <!-- HIDDEN INPUT TRIGGER -->
                <input type="hidden" name="dw_submit_produk" value="1">
                <?php wp_nonce_field('dw_prod_save'); ?>
                <?php if($edit_data): ?><input type="hidden" name="produk_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                <div style="margin-bottom: 20px;">
                    <a href="?page=dw-produk" class="dw-button dw-button-secondary"><span class="dashicons dashicons-arrow-left-alt"></span> Kembali</a>
                </div>

                <div class="dw-dashboard-split">
                    <!-- LEFT COLUMN: MAIN CONTENT -->
                    <div class="dw-content">
                        <div class="dw-card" style="padding:0; overflow:hidden;">
                            <!-- Internal Tabs -->
                            <div class="dw-form-tabs" style="display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                <div class="dw-form-tab active" data-target="tab-info" style="padding: 15px 20px; cursor: pointer; font-weight: 600; border-bottom: 2px solid transparent;">
                                    <span class="dashicons dashicons-info"></span> Informasi Dasar
                                </div>
                                <div class="dw-form-tab" data-target="tab-gallery" style="padding: 15px 20px; cursor: pointer; font-weight: 600; border-bottom: 2px solid transparent;">
                                    <span class="dashicons dashicons-images-alt2"></span> Galeri Foto
                                </div>
                                <div class="dw-form-tab" data-target="tab-variations" style="padding: 15px 20px; cursor: pointer; font-weight: 600; border-bottom: 2px solid transparent;">
                                    <span class="dashicons dashicons-list-view"></span> Variasi Produk
                                </div>
                            </div>

                            <div class="dw-card-body">
                                <!-- TAB 1: INFO DASAR -->
                                <div id="tab-info" class="dw-tab-pane active">
                                    <div class="dw-form-group">
                                        <label class="dw-label">Nama Produk <span style="color:var(--dw-danger)">*</span></label>
                                        <input type="text" name="nama_produk" class="dw-input" style="font-size:16px; font-weight:600; padding:12px;" value="<?php echo esc_attr($edit_data->nama_produk ?? ''); ?>" required placeholder="Contoh: Keripik Singkong Balado">
                                    </div>
                                    
                                    <div class="dw-form-group">
                                        <label class="dw-label">Deskripsi Produk</label>
                                        <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>8, 'media_buttons'=>true, 'editor_class'=>'dw-input']); ?>
                                    </div>

                                    <div class="dw-grid-2-col">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Harga Satuan (Rp) <span style="color:var(--dw-danger)">*</span></label>
                                            <div style="position:relative;">
                                                <span style="position:absolute; left:12px; top:10px; color:#94a3b8; font-weight:600;">Rp</span>
                                                <input type="number" name="harga" class="dw-input" style="padding-left:40px;" value="<?php echo esc_attr($edit_data->harga ?? 0); ?>" required>
                                            </div>
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Stok Tersedia <span style="color:var(--dw-danger)">*</span></label>
                                            <input type="number" name="stok" class="dw-input" value="<?php echo esc_attr($edit_data->stok ?? 0); ?>" required>
                                        </div>
                                    </div>

                                    <!-- FASE 1: FLASH SALE / PROMO -->
                                    <div class="dw-form-group" style="margin-top: 20px;">
                                        <label class="dw-label">Promo Flash Sale</label>
                                        <div style="background: #eef2ff; padding: 15px; border-radius: 8px; border: 1px solid #c7d2fe;">
                                            <label style="display:flex; align-items:center; gap:10px; margin-bottom:15px; font-weight:600; cursor:pointer;">
                                                <input type="checkbox" name="is_promo" id="toggle_promo" value="1" <?php checked($edit_data->is_promo ?? 0, 1); ?>>
                                                Aktifkan Harga Coret / Flash Sale
                                            </label>
                                            
                                            <div id="dw-promo-fields" style="display: <?php echo ($edit_data->is_promo ?? 0) ? 'block' : 'none'; ?>;">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Harga Promo (Rp)</label>
                                                    <div style="position:relative;">
                                                        <span style="position:absolute; left:12px; top:10px; color:#94a3b8; font-weight:600;">Rp</span>
                                                        <input type="number" name="promo_price" class="dw-input" style="padding-left:40px; background:white;" value="<?php echo esc_attr($edit_data->promo_price ?? 0); ?>" placeholder="Harga setelah diskon">
                                                    </div>
                                                </div>
                                                <div class="dw-grid-2-col">
                                                    <div class="dw-form-group">
                                                        <label class="dw-label">Mulai Promo</label>
                                                        <input type="datetime-local" name="promo_start" class="dw-input" style="background:white;" value="<?php echo esc_attr($edit_data->promo_start ?? ''); ?>">
                                                    </div>
                                                    <div class="dw-form-group">
                                                        <label class="dw-label">Selesai Promo</label>
                                                        <input type="datetime-local" name="promo_end" class="dw-input" style="background:white;" value="<?php echo esc_attr($edit_data->promo_end ?? ''); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dw-grid-2-col">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Berat (Gram)</label>
                                            <input type="number" name="berat_gram" class="dw-input" value="<?php echo esc_attr($edit_data->berat_gram ?? 0); ?>">
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Kondisi</label>
                                            <select name="kondisi" class="dw-input">
                                                <option value="baru" <?php selected($edit_data ? $edit_data->kondisi : 'baru', 'baru'); ?>>Baru</option>
                                                <option value="bekas" <?php selected($edit_data ? $edit_data->kondisi : '', 'bekas'); ?>>Bekas</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB 2: GALERI -->
                                <div id="tab-gallery" class="dw-tab-pane" style="display:none;">
                                    <div class="dw-form-group">
                                        <label class="dw-label" style="font-size:16px;">Foto Galeri Tambahan</label>
                                        <p class="dw-help-text" style="margin-bottom:15px;">Upload foto dari berbagai sudut untuk menarik pembeli.</p>
                                        
                                        <div id="galeri-container" style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
                                            <?php 
                                            $galeri_urls = [];
                                            if (!empty($edit_data->galeri)) {
                                                $decoded = json_decode($edit_data->galeri, true);
                                                if (is_array($decoded)) {
                                                    foreach($decoded as $url) {
                                                        $galeri_urls[] = $url;
                                                        echo '<div class="g-item" style="position:relative;"><img src="'.esc_url($url).'" style="width:100px;height:100px;object-fit:cover;border-radius:8px;"><span class="rem-g" data-url="'.esc_attr($url).'" style="position:absolute;top:-8px;right:-8px;background:red;color:white;border-radius:50%;width:20px;height:20px;text-align:center;line-height:20px;cursor:pointer;">&times;</span></div>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </div>
                                        <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr(implode(',', $galeri_urls)); ?>">
                                        <button type="button" class="dw-button dw-button-secondary" id="btn_galeri">
                                            <span class="dashicons dashicons-plus-alt2"></span> Tambah Foto Galeri
                                        </button>
                                    </div>
                                </div>

                                <!-- TAB 3: VARIASI -->
                                <div id="tab-variations" class="dw-tab-pane" style="display:none;">
                                    <div class="dw-form-group">
                                        <label class="dw-label" style="font-size:16px;">Variasi Produk</label>
                                        <p class="dw-help-text" style="margin-bottom:15px;">Gunakan jika produk memiliki pilihan warna atau ukuran. Kosongkan jika produk tunggal.</p>
                                        
                                        <div class="dw-table-wrapper" style="border:none;">
                                            <table class="dw-modern-table">
                                                <thead>
                                                    <tr>
                                                        <th>Nama Variasi</th>
                                                        <th width="150">Harga (Rp)</th>
                                                        <th width="100">Stok</th>
                                                        <th width="120">SKU</th>
                                                        <th width="60"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="variasi-rows">
                                                    <?php if($variasi_list): foreach($variasi_list as $var): ?>
                                                        <tr>
                                                            <td><input type="text" name="var_nama[]" class="dw-input" value="<?php echo esc_attr($var->deskripsi_variasi); ?>"></td>
                                                            <td><input type="number" name="var_harga[]" class="dw-input" value="<?php echo esc_attr($var->harga_variasi); ?>"></td>
                                                            <td><input type="number" name="var_stok[]" class="dw-input" value="<?php echo esc_attr($var->stok_variasi); ?>"></td>
                                                            <td>
                                                                <input type="text" name="var_sku[]" class="dw-input" value="<?php echo esc_attr($var->sku); ?>">
                                                                <input type="hidden" name="var_foto[]" value="<?php echo esc_attr($var->foto); ?>">
                                                            </td>
                                                            <td style="text-align:center;"><button type="button" class="dw-button dw-button-secondary btn-del-var" style="color:red;border-color:red;"><span class="dashicons dashicons-trash" style="margin:0;"></span></button></td>
                                                        </tr>
                                                    <?php endforeach; endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div style="margin-top:15px;">
                                            <button type="button" class="dw-button dw-button-secondary" id="btn-add-var">
                                                <span class="dashicons dashicons-plus"></span> Tambah Baris Variasi
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: SIDEBAR -->
                    <div class="dw-sidebar">
                        
                        <!-- PUBLISH BOX -->
                        <div class="dw-card">
                            <div class="dw-card-header"><h3 class="card-heading">Penerbitan</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <label class="dw-label">Status</label>
                                    <select name="status" class="dw-input">
                                        <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                        <option value="habis" <?php selected($edit_data->status ?? '', 'habis'); ?>>Habis Stok</option>
                                        <option value="arsip" <?php selected($edit_data->status ?? '', 'arsip'); ?>>Arsip (Sembunyikan)</option>
                                    </select>
                                </div>
                                <button type="submit" class="dw-button dw-button-primary" style="width:100%; justify-content:center; margin-top:10px;">
                                    <span class="dashicons dashicons-saved" style="margin-right:5px;"></span> Simpan Produk
                                </button>
                            </div>
                        </div>

                        <!-- CATEGORY BOX -->
                        <div class="dw-card">
                            <div class="dw-card-header"><h3 class="card-heading">Kategori</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <select name="kategori" class="dw-input">
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php if (!empty($kategori_terms) && !is_wp_error($kategori_terms)) : ?>
                                            <?php foreach ($kategori_terms as $term) : ?>
                                                <option value="<?php echo esc_attr($term->name); ?>" <?php selected(($edit_data && isset($edit_data->kategori)) ? $edit_data->kategori : '', $term->name); ?>>
                                                    <?php echo esc_html($term->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <p class="dw-help-text" style="margin-top:10px;">
                                        <a href="edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk" target="_blank" style="text-decoration:none; display:flex; align-items:center; gap:4px;">
                                            <span class="dashicons dashicons-plus"></span> Kelola Kategori
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- IMAGE BOX -->
                        <div class="dw-card">
                            <div class="dw-card-header"><h3 class="card-heading">Foto Utama</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <div class="dw-img-preview <?php echo empty($edit_data->foto_utama) ? 'empty' : ''; ?>" style="margin-bottom:10px; text-align:center;">
                                        <?php if(!empty($edit_data->foto_utama)): ?>
                                            <img id="img_prev_prod" src="<?php echo esc_url($edit_data->foto_utama); ?>" style="max-width:100%; height:auto; border-radius:4px;">
                                        <?php else: ?>
                                            <img id="img_prev_prod" src="" style="display:none; max-width:100%; height:auto; border-radius:4px;">
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>">
                                    <button type="button" class="dw-button dw-button-secondary btn_upload" id="btn_upl" style="width:100%; justify-content:center;">Pilih Foto Utama</button>
                                </div>
                            </div>
                        </div>

                        <!-- OWNER BOX -->
                        <div class="dw-card">
                            <div class="dw-card-header"><h3 class="card-heading">Pemilik Toko</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <?php if ($is_super_admin): ?>
                                        <?php $list_pedagang = $wpdb->get_results("SELECT id, nama_toko FROM $table_pedagang WHERE status_akun='aktif'"); ?>
                                        <select name="id_pedagang" class="dw-input select2">
                                            <?php foreach($list_pedagang as $p): ?>
                                                <option value="<?php echo $p->id; ?>" <?php selected($edit_data ? $edit_data->id_pedagang : '', $p->id); ?>>
                                                    <?php echo esc_html($p->nama_toko); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" class="dw-input" value="<?php echo esc_attr($my_pedagang_data->nama_toko ?? '-'); ?>" readonly disabled style="background:#f9fafb; color:#64748b;">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

            <script>
            jQuery(document).ready(function($){
                // Tab Switcher
                $('.dw-form-tab').click(function(){
                    $('.dw-form-tab').removeClass('active').css('border-bottom', '2px solid transparent');
                    $(this).addClass('active').css('border-bottom', '2px solid var(--dw-brand-blue)');
                    $('.dw-tab-pane').hide();
                    $('#'+$(this).data('target')).show();
                });
                
                // Set default active tab styles
                $('.dw-form-tab.active').css('border-bottom', '2px solid var(--dw-brand-blue)');

                // Toggle Promo
                $('#toggle_promo').change(function(){
                    if($(this).is(':checked')) {
                        $('#dw-promo-fields').slideDown();
                    } else {
                        $('#dw-promo-fields').slideUp();
                    }
                });

                // Single Image
                $('#btn_upl').click(function(e){
                    e.preventDefault(); var frame = wp.media({title:'Foto Produk', multiple:false});
                    frame.on('select', function(){ 
                        var url = frame.state().get('selection').first().toJSON().url;
                        $('#foto_utama').val(url); 
                        $('#img_prev_prod').attr('src', url).show().parent().removeClass('empty');
                    }); frame.open();
                });

                // Gallery
                var gFrame;
                $('#btn_galeri').click(function(e){
                    e.preventDefault(); if(gFrame){gFrame.open();return;}
                    gFrame = wp.media({title:'Galeri Produk', multiple:true});
                    gFrame.on('select', function(){
                        var selection = gFrame.state().get('selection');
                        var urls = $('#galeri_urls').val() ? $('#galeri_urls').val().split(',') : [];
                        selection.map(function(att){
                            att = att.toJSON();
                            if(urls.indexOf(att.url) === -1){
                                urls.push(att.url);
                                $('#galeri-container').append('<div class="g-item" style="position:relative;"><img src="'+att.url+'" style="width:100px;height:100px;object-fit:cover;border-radius:8px;"><span class="rem-g" data-url="'+att.url+'" style="position:absolute;top:-8px;right:-8px;background:red;color:white;border-radius:50%;width:20px;height:20px;text-align:center;line-height:20px;cursor:pointer;">&times;</span></div>');
                            }
                        });
                        $('#galeri_urls').val(urls.join(','));
                    });
                    gFrame.open();
                });
                $(document).on('click','.rem-g', function(){
                    var u = $(this).data('url');
                    var urls = $('#galeri_urls').val().split(',');
                    var i = urls.indexOf(u); if(i > -1) urls.splice(i,1);
                    $('#galeri_urls').val(urls.join(',')); $(this).parent().remove();
                });

                // Variations
                $('#btn-add-var').click(function(){
                    var row = '<tr>'+
                        '<td><input type="text" name="var_nama[]" class="dw-input" placeholder="Warna, Ukuran..."></td>'+
                        '<td><input type="number" name="var_harga[]" class="dw-input" placeholder="0"></td>'+
                        '<td><input type="number" name="var_stok[]" class="dw-input" placeholder="0"></td>'+
                        '<td><input type="text" name="var_sku[]" class="dw-input"><input type="hidden" name="var_foto[]"></td>'+
                        '<td style="text-align:center;"><button type="button" class="dw-button dw-button-secondary btn-del-var" style="color:red;border-color:red;"><span class="dashicons dashicons-trash" style="margin:0;"></span></button></td>'+
                    '</tr>';
                    $('#variasi-rows').append(row);
                });
                $(document).on('click', '.btn-del-var', function(){ $(this).closest('tr').remove(); });
                
                if($.fn.select2) { $('.select2').select2({width:'100%'}); }
            });
            </script>

        <?php else: ?>
            <!-- === VIEW: LIST === -->
            
            <!-- STATS DASHBOARD -->
            <div class="dw-stats-grid">
                <?php 
                // Simple Stats Query
                $total_prod = $wpdb->get_var("SELECT COUNT(id) FROM $table_produk WHERE id_pedagang = " . ($is_super_admin ? "id_pedagang" : intval($my_pedagang_data->id ?? 0)));
                $active_prod = $wpdb->get_var("SELECT COUNT(id) FROM $table_produk WHERE status='aktif' AND id_pedagang = " . ($is_super_admin ? "id_pedagang" : intval($my_pedagang_data->id ?? 0)));
                $empty_prod = $wpdb->get_var("SELECT COUNT(id) FROM $table_produk WHERE stok <= 0 AND id_pedagang = " . ($is_super_admin ? "id_pedagang" : intval($my_pedagang_data->id ?? 0)));
                ?>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-products"></span></div>
                    <h4 class="dw-stat-value"><?php echo $total_prod; ?></h4>
                    <span class="dw-stat-label">Total Produk</span>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-cart"></span></div>
                    <h4 class="dw-stat-value"><?php echo $active_prod; ?></h4>
                    <span class="dw-stat-label">Aktif Dijual</span>
                </div>
                <div class="dw-stat-card">
                    <div class="dw-stat-icon-wrapper bg-orange"><span class="dashicons dashicons-warning"></span></div>
                    <h4 class="dw-stat-value"><?php echo $empty_prod; ?></h4>
                    <span class="dw-stat-label">Stok Habis</span>
                </div>
            </div>

            <!-- TABLE CARD -->
            <div class="dw-card" style="padding:0; overflow:hidden;">
                <!-- Toolbar -->
                <div class="dw-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 class="card-heading">Daftar Produk</h3>
                    <form method="get" style="display:flex; gap:10px;">
                        <input type="hidden" name="page" value="dw-produk">
                        <input type="text" name="s" class="dw-input" placeholder="Cari produk..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" style="width:250px;">
                        <button type="submit" class="dw-button dw-button-secondary">Cari</button>
                    </form>
                </div>

                <div class="dw-table-wrapper" style="border:none; border-radius:0;">
                    <table class="dw-modern-table">
                        <thead>
                            <tr>
                                <th width="80">Foto</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Status</th>
                                <th style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $search_q = isset($_GET['s']) ? esc_sql($_GET['s']) : '';
                            $sql_list = "SELECT pr.*, pe.nama_toko FROM $table_produk pr LEFT JOIN $table_pedagang pe ON pr.id_pedagang = pe.id WHERE 1=1";
                            if (!$is_super_admin) $sql_list .= " AND pr.id_pedagang = " . intval($my_pedagang_data->id ?? 0);
                            if ($search_q) $sql_list .= " AND pr.nama_produk LIKE '%$search_q%'";
                            $sql_list .= " ORDER BY pr.id DESC";
                            
                            $rows = $wpdb->get_results($sql_list);
                            
                            if($rows): foreach($rows as $r): 
                                $edit_url = "?page=dw-produk&action=edit&id={$r->id}";
                                $del_url = wp_nonce_url("?page=dw-produk&action=delete&id={$r->id}", 'dw_del_prod_nonce');
                                $img = !empty($r->foto_utama) ? $r->foto_utama : 'https://via.placeholder.com/60?text=IMG';
                                
                                // Logic Harga Promo di List
                                $display_price = number_format($r->harga, 0, ',', '.');
                                if (isset($r->is_promo) && $r->is_promo == 1) {
                                    $now = current_time('mysql');
                                    if ($now >= $r->promo_start && $now <= $r->promo_end) {
                                        $display_price = '<s style="color:#999; font-size:0.9em;">' . $display_price . '</s> <strong style="color:#e74c3c;">' . number_format($r->promo_price, 0, ',', '.') . '</strong>';
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <img src="<?php echo esc_url($img); ?>" style="width:50px; height:50px; border-radius:8px; object-fit:cover; border:1px solid var(--dw-border-color);">
                                </td>
                                <td>
                                    <strong style="font-size:15px; color:var(--dw-text-dark); display:block; margin-bottom:4px;"><?php echo esc_html($r->nama_produk); ?></strong>
                                    <?php if($is_super_admin): ?><span style="font-size:12px; color:var(--dw-text-grey);">Toko: <?php echo esc_html($r->nama_toko); ?></span><?php endif; ?>
                                </td>
                                <td><span class="dw-badge status-neutral"><?php echo esc_html($r->kategori); ?></span></td>
                                <td style="font-weight:700; color:var(--dw-text-dark);">Rp <?php echo $display_price; ?></td>
                                <td>
                                    <?php if($r->stok <= 0): ?>
                                        <span style="color:var(--dw-danger); font-weight:700;">0 (Habis)</span>
                                    <?php else: ?>
                                        <span style="font-weight:600;"><?php echo $r->stok; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($r->status == 'aktif'): ?><span class="dw-badge status-success">Aktif</span>
                                    <?php elseif($r->status == 'habis'): ?><span class="dw-badge status-warning">Habis</span>
                                    <?php else: ?><span class="dw-badge status-neutral">Arsip</span><?php endif; ?>
                                    
                                    <?php if(isset($r->is_promo) && $r->is_promo == 1): ?>
                                        <br><span class="dw-badge status-warning" style="font-size:10px; margin-top:4px;">FLASH SALE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                        <?php if (current_user_can('manage_options')): ?>
                                            <a href="<?php echo add_query_arg(['action' => 'toggle_featured', 'id' => $r->id]); ?>" class="dw-button dw-button-secondary" style="padding: 6px 10px;" title="<?php echo (isset($r->is_featured) && $r->is_featured) ? 'Unpin' : 'Pin to Top'; ?>">
                                                <span class="dashicons dashicons-<?php echo (isset($r->is_featured) && $r->is_featured) ? 'star-filled' : 'star-empty'; ?>" style="margin:0;"></span>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo $edit_url; ?>" class="dw-button dw-button-secondary" style="padding: 6px 10px;" title="Edit"><span class="dashicons dashicons-edit" style="margin:0;"></span></a>
                                        <a href="<?php echo $del_url; ?>" class="dw-button dw-button-secondary" style="padding: 6px 10px; color:var(--dw-danger); border-color:var(--dw-danger);" onclick="return confirm('Hapus produk ini?');" title="Hapus"><span class="dashicons dashicons-trash" style="margin:0;"></span></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" style="text-align:center; padding:50px; color:var(--dw-text-grey);">Belum ada data produk.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}