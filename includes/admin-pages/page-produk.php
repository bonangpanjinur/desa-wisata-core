<?php
/**
 * File Name:   includes/admin-pages/page-produk.php
 * Description: Manajemen Produk Lengkap (Modern UI/UX Enhanced).
 * Features:    Stats Dashboard, Tabbed Form, Modern Table, Gallery & Variation Management.
 * @package     DesaWisataCore
 */

defined('ABSPATH') || exit;

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

    // --- B. LOGIKA SIMPAN (SAVE/UPDATE) ---
    // 1. Cek apakah tombol submit ditekan
    if (!isset($_POST['dw_submit_produk'])) return;

    // 2. Cek Nonce Form
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dw_prod_save')) {
        dw_add_notice('Security check failed. Silakan refresh halaman.', 'error');
        return;
    }
    
    // Asumsi nama tabel menggunakan prefix 'dw_'. 
    // Sesuaikan jika schema Anda menggunakan prefix WP standar saja (misal: wp_produk).
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

    // 5. Data Utama Produk (Sanitasi Lengkap Sesuai Schema)
    $data = [
        'id_pedagang'  => $id_pedagang_input,
        'nama_produk'  => sanitize_text_field(wp_unslash($_POST['nama_produk'])),
        'slug'         => sanitize_title(wp_unslash($_POST['nama_produk'])),
        'deskripsi'    => wp_kses_post(wp_unslash($_POST['deskripsi'])),
        'harga'        => $harga_db, // DECIMAL(15,2)
        'stok'         => intval(wp_unslash($_POST['stok'])),
        'berat_gram'   => intval(wp_unslash($_POST['berat_gram'])),
        'kondisi'      => sanitize_key(wp_unslash($_POST['kondisi'])), // ENUM('baru','bekas')
        'kategori'     => sanitize_text_field(wp_unslash($_POST['kategori'])),
        'foto_utama'   => esc_url_raw(wp_unslash($_POST['foto_utama'])),
        'galeri'       => $galeri_json, // JSON
        'status'       => sanitize_text_field(wp_unslash($_POST['status'])), // ENUM
        'updated_at'   => current_time('mysql')
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

    <!-- STYLE CSS MODERN (TETAP SAMA) -->
    <style>
        :root {
            --dw-primary: #2563eb; 
            --dw-primary-dark: #1d4ed8; 
            --dw-success: #16a34a; 
            --dw-warning: #d97706; 
            --dw-danger: #dc2626; 
            --dw-gray-50: #f8fafc;
            --dw-gray-100: #f1f5f9; 
            --dw-gray-200: #e2e8f0; 
            --dw-gray-300: #cbd5e1;
            --dw-gray-700: #334155; 
            --dw-gray-800: #1e293b;
            --dw-radius: 8px; 
            --dw-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dw-container { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        /* Modern Cards */
        .dw-modern-card { background: white; border-radius: var(--dw-radius); box-shadow: var(--dw-shadow); padding: 25px; margin-bottom: 20px; border: 1px solid var(--dw-gray-200); }
        .dw-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--dw-gray-100); }
        .dw-card-title { font-size: 18px; font-weight: 700; color: var(--dw-gray-800); margin: 0; display:flex; align-items:center; gap:8px; }

        /* Buttons */
        .dw-btn { padding: 9px 18px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border: 1px solid transparent; }
        .dw-btn-primary { background: var(--dw-primary); color: white; } 
        .dw-btn-primary:hover { background: var(--dw-primary-dark); color: white; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
        .dw-btn-outline { background: white; border-color: var(--dw-gray-300); color: var(--dw-gray-700); } 
        .dw-btn-outline:hover { background: var(--dw-gray-50); border-color: var(--dw-gray-400); color: var(--dw-gray-800); }
        .dw-btn-sm { padding: 6px 12px; font-size: 12px; } 
        .dw-btn-danger { background: white; border-color: #fca5a5; color: var(--dw-danger); } 
        .dw-btn-danger:hover { background: #fef2f2; border-color: var(--dw-danger); }

        /* Inputs */
        .dw-input { width: 100%; padding: 10px 12px; border: 1px solid var(--dw-gray-300); border-radius: 6px; font-size: 14px; transition: 0.2s; }
        .dw-input:focus { border-color: var(--dw-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); outline: none; }
        .dw-form-group { margin-bottom: 20px; } 
        .dw-form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dw-gray-700); font-size: 13px; }
        
        .dw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .dw-edit-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        
        /* Image Preview */
        .dw-img-preview { width: 100%; height: 220px; background: #f8fafc; border: 2px dashed var(--dw-gray-300); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 12px; position: relative; transition: border-color 0.2s; }
        .dw-img-preview:hover { border-color: var(--dw-primary); }
        .dw-img-preview img { width: 100%; height: 100%; object-fit: cover; }
        .dw-img-preview.empty::after { content: 'Tidak ada gambar'; color: #94a3b8; font-size: 13px; font-weight: 500; display: flex; flex-direction: column; align-items: center; gap: 5px; }
        .dw-img-preview.empty::before { content: '\f128'; font-family: dashicons; font-size: 32px; color: #cbd5e1; }

        /* Status Pills */
        .dw-pill { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px; }
        .dw-pill.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } 
        .dw-pill.warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; } 
        .dw-pill.gray { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        /* Tabs Internal Form */
        .dw-form-tabs { display: flex; border-bottom: 1px solid var(--dw-gray-200); margin-bottom: 0; background: #f8fafc; border-radius: var(--dw-radius) var(--dw-radius) 0 0; }
        .dw-form-tab { padding: 15px 25px; cursor: pointer; font-weight: 600; color: var(--dw-gray-700); border-right: 1px solid var(--dw-gray-200); border-bottom: 1px solid var(--dw-gray-200); background: #f8fafc; transition:0.2s; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .dw-form-tab:first-child { border-top-left-radius: var(--dw-radius); }
        .dw-form-tab:hover { background: #fff; color: var(--dw-primary); } 
        .dw-form-tab.active { background: #fff; border-bottom-color: transparent; color: var(--dw-primary); border-top: 3px solid var(--dw-primary); margin-top: -1px; }
        .dw-tab-pane { display: none; padding: 30px; animation: fadeIn 0.3s; } 
        .dw-tab-pane.active { display: block; }
        
        /* Gallery */
        .g-item { position: relative; width: 100px; height: 100px; border-radius: 8px; overflow: hidden; border: 1px solid var(--dw-gray-200); box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .g-item:hover { transform: scale(1.05); }
        .g-item img { width: 100%; height: 100%; object-fit: cover; }
        .rem-g { position: absolute; top: 4px; right: 4px; background: rgba(220, 38, 38, 0.9); color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; transition: background 0.2s; }
        .rem-g:hover { background: #ef4444; }

        /* Stats Cards */
        .dw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .dw-stat-box { 
            background: white; padding: 25px; border-radius: var(--dw-radius); border: 1px solid var(--dw-gray-200); 
            display: flex; align-items: center; gap: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s;
        }
        .dw-stat-box:hover { transform: translateY(-2px); box-shadow: var(--dw-shadow); }
        .dw-stat-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0; }
        .dw-stat-icon.blue { background: #e0f2fe; color: #0284c7; } 
        .dw-stat-icon.green { background: #dcfce7; color: #16a34a; } 
        .dw-stat-icon.red { background: #fee2e2; color: #dc2626; }
        .dw-stat-content h4 { margin: 0 0 4px; font-size: 26px; font-weight: 800; color: var(--dw-gray-800); line-height: 1; } 
        .dw-stat-content span { font-size: 13px; font-weight: 600; color: var(--dw-gray-500); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Modern Table */
        .dw-table-wrapper { overflow-x: auto; border-radius: var(--dw-radius); border: 1px solid var(--dw-gray-200); background: white; }
        .dw-table { width: 100%; border-collapse: collapse; }
        .dw-table th { background: var(--dw-gray-50); text-align: left; padding: 14px 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--dw-gray-700); border-bottom: 1px solid var(--dw-gray-200); letter-spacing: 0.5px; }
        .dw-table td { padding: 16px 20px; border-bottom: 1px solid var(--dw-gray-200); font-size: 14px; vertical-align: middle; color: var(--dw-gray-700); }
        .dw-table tr:last-child td { border-bottom: none; }
        .dw-table tr:hover { background: #f8fafc; }

        @media (max-width: 960px) { .dw-edit-layout { grid-template-columns: 1fr; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <div class="wrap dw-container">
        <!-- HEADER -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h1 class="wp-heading-inline" style="font-size: 28px; font-weight: 800; color: #0f172a; margin-right: 15px;">Manajemen Produk</h1>
                <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">Kelola katalog produk, stok, variasi, dan harga.</p>
            </div>
            <?php if (!$is_edit): ?>
                <a href="?page=dw-produk&action=new" class="dw-btn dw-btn-primary">
                    <span class="dashicons dashicons-plus-alt2" style="font-size: 18px;"></span> Tambah Produk
                </a>
            <?php endif; ?>
        </div>

        <?php dw_display_notices(); // Tampilkan Notif disini ?>

        <?php if($is_edit): ?>
            <!-- === VIEW: ADD / EDIT === -->
            <form method="post" action="">
                <!-- HIDDEN INPUT TRIGGER -->
                <input type="hidden" name="dw_submit_produk" value="1">
                <?php wp_nonce_field('dw_prod_save'); ?>
                <?php if($edit_data): ?><input type="hidden" name="produk_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                <div style="margin-bottom: 20px;">
                    <a href="?page=dw-produk" class="dw-btn dw-btn-outline"><span class="dashicons dashicons-arrow-left-alt"></span> Kembali</a>
                </div>

                <div class="dw-edit-layout">
                    <!-- LEFT COLUMN -->
                    <div class="dw-main-col">
                        <div class="dw-modern-card" style="padding:0; overflow:hidden;">
                            <!-- Internal Tabs -->
                            <div class="dw-form-tabs">
                                <div class="dw-form-tab active" data-target="tab-info">
                                    <span class="dashicons dashicons-info"></span> Informasi Dasar
                                </div>
                                <div class="dw-form-tab" data-target="tab-gallery">
                                    <span class="dashicons dashicons-images-alt2"></span> Galeri Foto
                                </div>
                                <div class="dw-form-tab" data-target="tab-variations">
                                    <span class="dashicons dashicons-list-view"></span> Variasi Produk
                                </div>
                            </div>

                            <!-- TAB 1: INFO DASAR -->
                            <div id="tab-info" class="dw-tab-pane active">
                                <div class="dw-form-group">
                                    <label>Nama Produk <span style="color:var(--dw-danger)">*</span></label>
                                    <input type="text" name="nama_produk" class="dw-input" style="font-size:16px; font-weight:600; padding:12px;" value="<?php echo esc_attr($edit_data->nama_produk ?? ''); ?>" required placeholder="Contoh: Keripik Singkong Balado">
                                </div>
                                
                                <div class="dw-form-group">
                                    <label>Deskripsi Produk</label>
                                    <?php wp_editor($edit_data->deskripsi ?? '', 'deskripsi', ['textarea_rows'=>8, 'media_buttons'=>true, 'editor_class'=>'dw-input']); ?>
                                </div>

                                <div class="dw-grid-2">
                                    <div class="dw-form-group">
                                        <label>Harga Satuan (Rp) <span style="color:var(--dw-danger)">*</span></label>
                                        <div style="position:relative;">
                                            <span style="position:absolute; left:12px; top:10px; color:#94a3b8; font-weight:600;">Rp</span>
                                            <input type="number" name="harga" class="dw-input" style="padding-left:40px;" value="<?php echo esc_attr($edit_data->harga ?? 0); ?>" required>
                                        </div>
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Stok Tersedia <span style="color:var(--dw-danger)">*</span></label>
                                        <input type="number" name="stok" class="dw-input" value="<?php echo esc_attr($edit_data->stok ?? 0); ?>" required>
                                    </div>
                                </div>

                                <div class="dw-grid-2">
                                    <div class="dw-form-group">
                                        <label>Berat (Gram)</label>
                                        <input type="number" name="berat_gram" class="dw-input" value="<?php echo esc_attr($edit_data->berat_gram ?? 0); ?>">
                                    </div>
                                    <div class="dw-form-group">
                                        <label>Kondisi</label>
                                        <select name="kondisi" class="dw-input">
                                            <option value="baru" <?php selected($edit_data ? $edit_data->kondisi : 'baru', 'baru'); ?>>Baru</option>
                                            <option value="bekas" <?php selected($edit_data ? $edit_data->kondisi : '', 'bekas'); ?>>Bekas</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 2: GALERI -->
                            <div id="tab-gallery" class="dw-tab-pane">
                                <div class="dw-form-group">
                                    <label style="font-size:16px;">Foto Galeri Tambahan</label>
                                    <p class="description" style="margin-bottom:15px;">Upload foto dari berbagai sudut untuk menarik pembeli.</p>
                                    
                                    <div id="galeri-container" style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
                                        <?php 
                                        $galeri_urls = [];
                                        if (!empty($edit_data->galeri)) {
                                            $decoded = json_decode($edit_data->galeri, true);
                                            if (is_array($decoded)) {
                                                foreach($decoded as $url) {
                                                    $galeri_urls[] = $url;
                                                    echo '<div class="g-item"><img src="'.esc_url($url).'"><span class="rem-g" data-url="'.esc_attr($url).'">&times;</span></div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                    <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr(implode(',', $galeri_urls)); ?>">
                                    <button type="button" class="dw-btn dw-btn-outline" id="btn_galeri">
                                        <span class="dashicons dashicons-plus-alt2"></span> Tambah Foto Galeri
                                    </button>
                                </div>
                            </div>

                            <!-- TAB 3: VARIASI -->
                            <div id="tab-variations" class="dw-tab-pane">
                                <div class="dw-form-group">
                                    <label style="font-size:16px;">Variasi Produk</label>
                                    <p class="description" style="margin-bottom:15px;">Gunakan jika produk memiliki pilihan warna atau ukuran. Kosongkan jika produk tunggal.</p>
                                    
                                    <div class="dw-table-wrapper">
                                        <table class="dw-table">
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
                                                        <td style="text-align:center;"><button type="button" class="dw-btn dw-btn-danger dw-btn-sm btn-del-var"><span class="dashicons dashicons-trash" style="margin:0;"></span></button></td>
                                                    </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div style="margin-top:15px;">
                                        <button type="button" class="dw-btn dw-btn-primary dw-btn-sm" id="btn-add-var">
                                            <span class="dashicons dashicons-plus"></span> Tambah Baris Variasi
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="dw-sidebar-col">
                        
                        <!-- PUBLISH BOX -->
                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Penerbitan</h3></div>
                            <div class="dw-form-group">
                                <label>Status</label>
                                <select name="status" class="dw-input">
                                    <option value="aktif" <?php selected($edit_data->status ?? '', 'aktif'); ?>>Aktif</option>
                                    <option value="habis" <?php selected($edit_data->status ?? '', 'habis'); ?>>Habis Stok</option>
                                    <option value="arsip" <?php selected($edit_data->status ?? '', 'arsip'); ?>>Arsip (Sembunyikan)</option>
                                </select>
                            </div>
                            <button type="submit" class="dw-btn dw-btn-primary" style="width:100%; justify-content:center; margin-top:10px;">
                                <span class="dashicons dashicons-saved"></span> Simpan Produk
                            </button>
                        </div>

                        <!-- CATEGORY BOX -->
                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Kategori</h3></div>
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
                                <p class="description" style="margin-top:10px; font-size:12px;">
                                    <a href="edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk" target="_blank" style="text-decoration:none; display:flex; align-items:center; gap:4px;">
                                        <span class="dashicons dashicons-plus"></span> Kelola Kategori
                                    </a>
                                </p>
                            </div>
                        </div>

                        <!-- IMAGE BOX -->
                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Foto Utama</h3></div>
                            <div class="dw-form-group">
                                <div class="dw-img-preview <?php echo empty($edit_data->foto_utama) ? 'empty' : ''; ?>">
                                    <?php if(!empty($edit_data->foto_utama)): ?>
                                        <img id="img_prev_prod" src="<?php echo esc_url($edit_data->foto_utama); ?>">
                                    <?php else: ?>
                                        <img id="img_prev_prod" src="" style="display:none;">
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="foto_utama" id="foto_utama" value="<?php echo esc_attr($edit_data->foto_utama ?? ''); ?>">
                                <button type="button" class="dw-btn dw-btn-outline dw-btn-sm" id="btn_upl" style="width:100%; justify-content:center;">Pilih Foto Utama</button>
                            </div>
                        </div>

                        <!-- OWNER BOX -->
                        <div class="dw-modern-card">
                            <div class="dw-card-header"><h3 class="dw-card-title">Pemilik Toko</h3></div>
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
            </form>

            <script>
            jQuery(document).ready(function($){
                // Tab Switcher
                $('.dw-form-tab').click(function(){
                    $('.dw-form-tab').removeClass('active');
                    $('.dw-tab-pane').removeClass('active');
                    $(this).addClass('active');
                    $('#'+$(this).data('target')).addClass('active');
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
                                $('#galeri-container').append('<div class="g-item"><img src="'+att.url+'"><span class="rem-g" data-url="'+att.url+'">&times;</span></div>');
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
                        '<td style="text-align:center;"><button type="button" class="dw-btn dw-btn-danger dw-btn-sm btn-del-var"><span class="dashicons dashicons-trash" style="margin:0;"></span></button></td>'+
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
                <div class="dw-stat-box">
                    <div class="dw-stat-icon blue"><span class="dashicons dashicons-products"></span></div>
                    <div class="dw-stat-content"><h4><?php echo $total_prod; ?></h4><span>Total Produk</span></div>
                </div>
                <div class="dw-stat-box">
                    <div class="dw-stat-icon green"><span class="dashicons dashicons-cart"></span></div>
                    <div class="dw-stat-content"><h4><?php echo $active_prod; ?></h4><span>Aktif Dijual</span></div>
                </div>
                <div class="dw-stat-box">
                    <div class="dw-stat-icon red"><span class="dashicons dashicons-warning"></span></div>
                    <div class="dw-stat-content"><h4><?php echo $empty_prod; ?></h4><span>Stok Habis</span></div>
                </div>
            </div>

            <!-- TABLE CARD -->
            <div class="dw-modern-card" style="padding:0; overflow:hidden;">
                <!-- Toolbar -->
                <div style="padding:15px 20px; background:var(--dw-gray-50); border-bottom:1px solid var(--dw-gray-200); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:16px; color:var(--dw-gray-800);">Daftar Produk</h3>
                    <form method="get" style="display:flex; gap:10px;">
                        <input type="hidden" name="page" value="dw-produk">
                        <input type="text" name="s" class="dw-input" placeholder="Cari produk..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" style="background:white; width:250px;">
                        <button type="submit" class="dw-btn dw-btn-outline">Cari</button>
                    </form>
                </div>

                <div class="dw-table-wrapper" style="border:none; border-radius:0;">
                    <table class="dw-table">
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
                            ?>
                            <tr>
                                <td>
                                    <img src="<?php echo esc_url($img); ?>" style="width:50px; height:50px; border-radius:8px; object-fit:cover; border:1px solid var(--dw-gray-200);">
                                </td>
                                <td>
                                    <strong style="font-size:15px; color:var(--dw-gray-800); display:block; margin-bottom:4px;"><?php echo esc_html($r->nama_produk); ?></strong>
                                    <?php if($is_super_admin): ?><span style="font-size:12px; color:var(--dw-gray-500);">Toko: <?php echo esc_html($r->nama_toko); ?></span><?php endif; ?>
                                </td>
                                <td><span class="dw-pill gray" style="font-weight:600;"><?php echo esc_html($r->kategori); ?></span></td>
                                <td style="font-weight:700; color:var(--dw-gray-800);">Rp <?php echo number_format($r->harga, 0, ',', '.'); ?></td>
                                <td>
                                    <?php if($r->stok <= 0): ?>
                                        <span style="color:var(--dw-danger); font-weight:700;">0 (Habis)</span>
                                    <?php else: ?>
                                        <span style="font-weight:600;"><?php echo $r->stok; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($r->status == 'aktif'): ?><span class="dw-pill success">Aktif</span>
                                    <?php elseif($r->status == 'habis'): ?><span class="dw-pill warning">Habis</span>
                                    <?php else: ?><span class="dw-pill gray">Arsip</span><?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                        <a href="<?php echo $edit_url; ?>" class="dw-btn dw-btn-outline dw-btn-sm" title="Edit"><span class="dashicons dashicons-edit"></span></a>
                                        <a href="<?php echo $del_url; ?>" class="dw-btn dw-btn-danger dw-btn-sm" onclick="return confirm('Hapus produk ini?');" title="Hapus"><span class="dashicons dashicons-trash"></span></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" style="text-align:center; padding:50px; color:var(--dw-gray-500);">Belum ada data produk.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}