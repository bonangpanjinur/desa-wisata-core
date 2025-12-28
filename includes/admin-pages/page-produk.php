<?php
/**
 * File Name:   includes/admin-pages/page-produk.php
 * Description: Manajemen Produk UMKM & Variasi dengan UI Premium v3.7.
 * Fitur: CRUD Produk, Galeri JSON, Stok, Berat, Relasi Pedagang, & Variasi Produk.
 * @package DesaWisataCore
 */

defined('ABSPATH') || exit;

/**
 * 1. HANDLER: SIMPAN DATA (PRODUK & VARIASI)
 */
function dw_handle_save_produk() {
    global $wpdb;
    $table_produk  = $wpdb->prefix . 'dw_produk';
    $table_variasi = $wpdb->prefix . 'dw_produk_variasi';

    // Verifikasi Keamanan
    if (!isset($_POST['dw_prod_nonce']) || !wp_verify_nonce($_POST['dw_prod_nonce'], 'save_produk')) {
        return ['status' => 'error', 'msg' => 'Validasi keamanan gagal.'];
    }

    $id = isset($_POST['produk_id']) ? intval($_POST['produk_id']) : 0;
    
    // 1. Simpan Data Utama Produk
    $galeri_raw = isset($_POST['galeri']) ? $_POST['galeri'] : [];
    $galeri_json = json_encode(array_values(array_filter(array_map('esc_url_raw', $galeri_raw))));

    // Pastikan ID Pedagang tersimpan karena ini KEY relasi
    $id_pedagang = isset($_POST['id_pedagang']) ? intval($_POST['id_pedagang']) : 0;
    if ($id_pedagang === 0) {
        return ['status' => 'error', 'msg' => 'Gagal: Produk harus dikaitkan dengan Pedagang (Toko).'];
    }

    $data = [
        'id_pedagang' => $id_pedagang, // RELASI KEY
        'nama_produk' => sanitize_text_field($_POST['nama_produk']),
        'slug'        => sanitize_title($_POST['nama_produk']),
        'deskripsi'   => wp_kses_post($_POST['deskripsi']),
        'harga'       => floatval($_POST['harga']), 
        'stok'        => intval($_POST['stok']),    
        'berat_gram'  => intval($_POST['berat_gram']),
        'kondisi'     => sanitize_text_field($_POST['kondisi']),
        'kategori'    => sanitize_text_field($_POST['kategori']), // Taxonomy term name
        'foto_utama'  => esc_url_raw($_POST['foto_utama']),
        'galeri'      => $galeri_json,
        'status'      => sanitize_text_field($_POST['status']),
        'updated_at'  => current_time('mysql')
    ];

    if ($id > 0) {
        $wpdb->update($table_produk, $data, ['id' => $id]);
        $product_id = $id;
        $msg_suffix = 'diperbarui';
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table_produk, $data);
        $product_id = $wpdb->insert_id;
        $msg_suffix = 'ditambahkan';
    }

    // 2. Simpan Data Variasi
    if ($product_id) {
        $existing_vars = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_variasi WHERE id_produk = %d", $product_id));
        $submitted_ids = [];

        if (isset($_POST['variasi']) && is_array($_POST['variasi'])) {
            foreach ($_POST['variasi'] as $idx => $var) {
                $var_id = isset($_POST['variasi_id'][$idx]) ? intval($_POST['variasi_id'][$idx]) : 0;
                
                $var_data = [
                    'id_produk'         => $product_id,
                    'deskripsi_variasi' => sanitize_text_field($var['deskripsi']),
                    'harga_variasi'     => floatval($var['harga']),
                    'stok_variasi'      => intval($var['stok']),
                    'sku'               => sanitize_text_field($var['sku']),
                    'foto'              => esc_url_raw($var['foto']),
                    'is_default'        => (isset($var['is_default']) && $var['is_default'] == 1) ? 1 : 0
                ];

                if ($var_id > 0 && in_array($var_id, $existing_vars)) {
                    $wpdb->update($table_variasi, $var_data, ['id' => $var_id]);
                    $submitted_ids[] = $var_id;
                } else {
                    $wpdb->insert($table_variasi, $var_data);
                    $submitted_ids[] = $wpdb->insert_id;
                }
            }
        }

        // Hapus variasi yang dihapus user dari form
        $to_delete = array_diff($existing_vars, $submitted_ids);
        if (!empty($to_delete)) {
            $ids_str = implode(',', array_map('intval', $to_delete));
            $wpdb->query("DELETE FROM $table_variasi WHERE id IN ($ids_str)");
        }
    }

    return ['status' => 'success', 'msg' => "Produk berhasil $msg_suffix."];
}

/**
 * 2. HANDLER: HAPUS DATA
 */
function dw_handle_delete_produk($id) {
    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'dw_produk', ['id' => $id]);
    $wpdb->delete($wpdb->prefix . 'dw_produk_variasi', ['id_produk' => $id]); 
    return ['status' => 'success', 'msg' => 'Produk dan variasinya berhasil dihapus.'];
}

/**
 * 3. RENDER HALAMAN UTAMA
 */
function dw_produk_page_render() {
    global $wpdb;
    $table_produk   = $wpdb->prefix . 'dw_produk';
    $table_pedagang = $wpdb->prefix . 'dw_pedagang';
    $table_desa     = $wpdb->prefix . 'dw_desa';
    $table_variasi  = $wpdb->prefix . 'dw_produk_variasi';
    
    $message = '';
    $msg_type = '';

    // --- Action Processor ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_produk']) && $_POST['action_produk'] == 'save') {
        $res = dw_handle_save_produk();
        $message = $res['msg'];
        $msg_type = $res['status'];
    } elseif (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        check_admin_referer('delete_produk_' . $_GET['id']);
        $res = dw_handle_delete_produk(intval($_GET['id']));
        $message = $res['msg'];
        $msg_type = $res['status'];
    }

    $view = isset($_GET['view']) ? $_GET['view'] : 'list';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Ambil Data Kategori Taxonomy WP
    $kategori_terms = get_terms([
        'taxonomy' => 'kategori_produk',
        'hide_empty' => false,
    ]);

    // Ambil Data User Saat Ini (Untuk Role Check)
    $current_user_id = get_current_user_id();
    $is_super_admin  = current_user_can('administrator') || current_user_can('admin_kabupaten') || current_user_can('admin_desa');
    $my_pedagang_id  = 0;

    if (!$is_super_admin) {
        $pedagang_row = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_pedagang WHERE id_user = %d", $current_user_id));
        if ($pedagang_row) {
            $my_pedagang_id = $pedagang_row->id;
        } else {
            // Jika bukan admin dan bukan pedagang terdaftar, tolak akses
            echo '<div class="notice notice-error"><p>Anda belum terdaftar sebagai pedagang aktif.</p></div>';
            return;
        }
    }

    wp_enqueue_media();
    ?>

    <!-- STYLE PREMIUM -->
    <style>
        :root { --dw-primary: #2563eb; --dw-bg: #f8fafc; --dw-border: #e2e8f0; --dw-text: #1e293b; --dw-text-muted: #64748b; --dw-radius: 10px; }
        .dw-wrapper { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--dw-text); }
        .dw-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        
        /* Cards & Layout */
        .dw-layout-grid { display: grid; grid-template-columns: 3fr 1fr; gap: 25px; align-items: start; }
        .dw-card { background: #fff; border: 1px solid var(--dw-border); border-radius: var(--dw-radius); box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 20px; overflow: hidden; }
        .dw-card-header { padding: 15px 20px; border-bottom: 1px solid var(--dw-border); background: #fbfbfb; display: flex; justify-content: space-between; align-items: center; }
        .dw-card-header h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--dw-text); }
        .dw-card-body { padding: 25px; }
        
        /* Tabs */
        .dw-tabs-header { display: flex; border-bottom: 1px solid var(--dw-border); background: #f8fafc; padding: 0 10px; }
        .dw-tab-item { background: none; border: none; padding: 15px 20px; font-size: 13px; font-weight: 600; cursor: pointer; color: var(--dw-text-muted); border-bottom: 2px solid transparent; transition: all 0.2s; }
        .dw-tab-item:hover { color: var(--dw-primary); background: #fff; }
        .dw-tab-item.active { color: var(--dw-primary); border-bottom-color: var(--dw-primary); background: #fff; }
        .dw-tab-content { display: none; padding: 25px; animation: fadeIn 0.3s ease; }
        .dw-tab-content.active { display: block; }
        
        /* Forms */
        .dw-form-group { margin-bottom: 20px; }
        .dw-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: var(--dw-text); }
        .dw-form-control { width: 100%; padding: 10px 14px; border: 1.5px solid var(--dw-border); border-radius: 8px; font-size: 14px; transition: 0.2s; box-sizing: border-box; }
        .dw-form-control:focus { outline: none; border-color: var(--dw-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .dw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        /* Buttons */
        .dw-btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 18px; border-radius: 8px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; font-size: 13px; gap: 6px; transition: all 0.2s; }
        .dw-btn-primary { background: var(--dw-primary); color: #fff; }
        .dw-btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
        .dw-btn-secondary { background: #fff; border: 1px solid var(--dw-border); color: var(--dw-text); }
        .dw-btn-secondary:hover { background: #f8fafc; border-color: var(--dw-text-muted); }
        .dw-btn-danger { background: #ef4444; color: #fff; }
        .dw-btn-block { width: 100%; }
        
        /* Table */
        .dw-table { width: 100%; border-collapse: collapse; }
        .dw-table th { background: #f8fafc; padding: 15px 20px; font-weight: 600; color: var(--dw-text-muted); text-transform: uppercase; font-size: 12px; border-bottom: 1px solid var(--dw-border); text-align: left; }
        .dw-table td { padding: 15px 20px; border-bottom: 1px solid var(--dw-border); vertical-align: middle; font-size: 14px; }
        .dw-table tr:hover td { background: #f8fafc; }
        
        /* Badges */
        .dw-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #fff; }
        .badge-aktif { background: #10b981; } .badge-nonaktif { background: #64748b; } .badge-habis { background: #f59e0b; } .badge-arsip { background: #1e293b; }

        /* Media */
        .dw-media-box { border: 2px dashed var(--dw-border); padding: 20px; border-radius: 12px; text-align: center; background: #f8fafc; transition: 0.2s; position: relative; }
        .dw-media-preview img { max-width: 100%; height: auto; border-radius: 8px; border: 1px solid var(--dw-border); margin-bottom: 10px; }
        .dw-gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 10px; margin-top: 15px; }
        .dw-gallery-item { position: relative; border-radius: 8px; overflow: hidden; aspect-ratio: 1; border: 1px solid var(--dw-border); }
        .dw-gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .dw-gallery-remove { position: absolute; top: 4px; right: 4px; background: rgba(239,68,68,0.9); color: #fff; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 14px; }

        /* Variations */
        .var-row { background: #f8fafc; border: 1px solid var(--dw-border); border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative; }
        .var-remove { position: absolute; top: 10px; right: 10px; color: #ef4444; cursor: pointer; font-size: 18px; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <div class="wrap dw-wrapper">
        <?php if ($message): ?>
            <div class="notice notice-<?php echo $msg_type; ?> is-dismissible" style="margin-left:0; margin-bottom:20px; border-left: 4px solid <?php echo $msg_type == 'success' ? '#10b981' : '#ef4444'; ?>;"><p><?php echo $message; ?></p></div>
        <?php endif; ?>

        <!-- ================= VIEW: LIST PRODUK ================= -->
        <?php if ($view == 'list'): ?>
            <div class="dw-header">
                <div>
                    <h1>Manajemen Produk</h1>
                    <p>Kelola katalog produk, stok, variasi, dan harga dari seluruh pedagang.</p>
                </div>
                <div>
                    <form method="get" style="display:inline-block; margin-right:10px;">
                        <input type="hidden" name="page" value="dw-produk">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari nama produk..." class="dw-form-control" style="width:250px; display:inline-block; height:42px;">
                        <button type="submit" class="dw-btn dw-btn-secondary" style="height:42px;">Cari</button>
                    </form>
                    <a href="<?php echo admin_url('admin.php?page=dw-produk&view=add'); ?>" class="dw-btn dw-btn-primary">
                        <span class="dashicons dashicons-plus-alt2"></span> Tambah Produk
                    </a>
                </div>
            </div>

            <div class="dw-card" style="padding:0;">
                <table class="wp-list-table widefat fixed striped dw-table">
                    <thead>
                        <tr>
                            <th width="80">Foto</th>
                            <th>Info Produk</th>
                            <th>Pemilik Toko (Pedagang)</th>
                            <th>Kategori</th>
                            <th>Harga & Stok</th>
                            <th>Status</th>
                            <th width="120" style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                        $limit = 10;
                        $offset = ($paged - 1) * $limit;
                        
                        $where = "WHERE 1=1";
                        if ($search) $where .= $wpdb->prepare(" AND (p.nama_produk LIKE %s)", "%$search%");
                        
                        // Filter untuk Pedagang: Hanya lihat produk sendiri
                        if (!$is_super_admin) {
                            $where .= $wpdb->prepare(" AND p.id_pedagang = %d", $my_pedagang_id);
                        }

                        $sql = "SELECT p.*, ped.nama_toko, d.nama_desa 
                                FROM $table_produk p 
                                LEFT JOIN $table_pedagang ped ON p.id_pedagang = ped.id 
                                LEFT JOIN $table_desa d ON ped.id_desa = d.id
                                $where ORDER BY p.created_at DESC LIMIT %d OFFSET %d";
                        
                        $items = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
                        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_produk p $where");
                        $total_pages = ceil($total_items / $limit);

                        if ($items): foreach ($items as $item): 
                            $status_class = ($item->status == 'aktif') ? 'badge-aktif' : (($item->status == 'habis') ? 'badge-habis' : 'badge-nonaktif');
                        ?>
                            <tr>
                                <td>
                                    <?php if($item->foto_utama): ?>
                                        <img src="<?php echo esc_url($item->foto_utama); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0;">
                                    <?php else: ?>
                                        <div style="width:50px; height:50px; background:#f1f5f9; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#94a3b8;"><span class="dashicons dashicons-format-image"></span></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><a href="<?php echo admin_url('admin.php?page=dw-produk&view=edit&id='.$item->id); ?>" style="color:var(--dw-text); text-decoration:none;"><?php echo esc_html($item->nama_produk); ?></a></strong><br>
                                    <span style="font-size:12px; color:var(--dw-text-muted);">Kondisi: <?php echo ucfirst($item->kondisi); ?></span>
                                </td>
                                <td>
                                    <!-- UPDATE: Menampilkan Nama Toko & Link ID Pedagang -->
                                    <strong><?php echo esc_html($item->nama_toko); ?></strong>
                                    <?php if($item->id_pedagang): ?>
                                        <a href="<?php echo admin_url('admin.php?page=dw-pedagang&view=edit&id='.$item->id_pedagang); ?>" title="Lihat Pedagang" style="color:#64748b; font-size:11px; text-decoration:none;">
                                            <span class="dashicons dashicons-external" style="font-size:12px; width:12px; height:12px;"></span> #<?php echo $item->id_pedagang; ?>
                                        </a>
                                    <?php endif; ?>
                                    <br>
                                    <small style="color:var(--dw-primary);"><?php echo esc_html($item->nama_desa ? $item->nama_desa : 'Independent'); ?></small>
                                </td>
                                <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:500; color: #475569;"><?php echo esc_html($item->kategori); ?></span></td>
                                <td>
                                    <span style="font-weight:700; color:#166534;">Rp <?php echo number_format($item->harga, 0, ',', '.'); ?></span><br>
                                    <small style="color:var(--dw-text-muted);">Stok: <?php echo $item->stok; ?></small>
                                </td>
                                <td><span class="dw-badge <?php echo $status_class; ?>"><?php echo ucfirst($item->status); ?></span></td>
                                <td style="text-align:right;">
                                    <a href="<?php echo admin_url('admin.php?page=dw-produk&view=edit&id='.$item->id); ?>" class="dw-btn dw-btn-secondary" style="padding:6px 12px; font-size:12px;">Edit</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dw-produk&action=delete&id='.$item->id), 'delete_produk_'.$item->id); ?>" class="dw-btn dw-btn-danger" style="padding:6px 12px; font-size:12px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca;" onclick="return confirm('Hapus produk ini?')"><span class="dashicons dashicons-trash" style="margin:0;"></span></a>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:50px; color:var(--dw-text-muted);">Belum ada produk yang ditambahkan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom" style="padding: 15px;">
                        <div class="tablenav-pages">
                            <?php echo paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'total' => $total_pages, 'current' => $paged]); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <!-- ================= VIEW: ADD / EDIT FORM ================= -->
        <?php else: 
            $is_edit = ($view == 'edit');
            $data = null;
            if ($is_edit && isset($_GET['id'])) {
                // Cek kepemilikan
                $check_sql = "SELECT * FROM $table_produk WHERE id = %d";
                if (!$is_super_admin) $check_sql .= " AND id_pedagang = " . $my_pedagang_id;
                
                $data = $wpdb->get_row($wpdb->prepare($check_sql, intval($_GET['id'])));
                
                if (!$data && $is_edit) {
                    echo '<div class="notice notice-error"><p>Produk tidak ditemukan atau Anda tidak memiliki akses.</p></div>';
                    return;
                }

                // Ambil Variasi
                $variations = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_variasi WHERE id_produk = %d", $data->id));
            }
            
            // Variabel Helper
            $id_pedagang = $data->id_pedagang ?? ($is_super_admin ? 0 : $my_pedagang_id);
            $galeri = ($data && $data->galeri) ? json_decode($data->galeri) : [];
            $variations = isset($variations) ? $variations : [];
        ?>
            
            <div class="dw-header" style="margin-top:0;">
                <div></div>
                <a href="<?php echo admin_url('admin.php?page=dw-produk'); ?>" class="dw-btn dw-btn-secondary"><span class="dashicons dashicons-arrow-left-alt"></span> Kembali</a>
            </div>

            <form method="post">
                <?php wp_nonce_field('save_produk', 'dw_prod_nonce'); ?>
                <input type="hidden" name="action_produk" value="save">
                <?php if($is_edit): ?><input type="hidden" name="produk_id" value="<?php echo $data->id; ?>"><?php endif; ?>
                
                <!-- Jika bukan admin, set ID pedagang hidden -->
                <?php if (!$is_super_admin): ?>
                    <input type="hidden" name="id_pedagang" value="<?php echo $my_pedagang_id; ?>">
                <?php endif; ?>

                <div class="dw-layout-grid">
                    <!-- MAIN CONTENT -->
                    <div class="dw-col-main">
                        <div class="dw-card">
                            <div class="dw-tabs-header">
                                <button type="button" class="dw-tab-item active" data-tab="tab-general">Informasi Dasar</button>
                                <button type="button" class="dw-tab-item" data-tab="tab-price">Harga & Stok</button>
                                <button type="button" class="dw-tab-item" data-tab="tab-variasi">Variasi Produk</button>
                                <button type="button" class="dw-tab-item" data-tab="tab-gallery">Galeri Foto</button>
                            </div>
                            
                            <div class="dw-card-body">
                                <!-- Tab: Informasi Dasar -->
                                <div id="tab-general" class="dw-tab-content active">
                                    <div class="dw-form-group">
                                        <label class="dw-label">Nama Produk <span style="color:red">*</span></label>
                                        <input type="text" name="nama_produk" value="<?php echo esc_attr($data->nama_produk ?? ''); ?>" class="dw-form-control dw-input-lg" placeholder="Contoh: Kripik Singkong Balado" required>
                                    </div>
                                    
                                    <div class="dw-form-group">
                                        <label class="dw-label">Deskripsi Produk</label>
                                        <?php wp_editor($data->deskripsi ?? '', 'deskripsi', ['textarea_rows' => 12, 'media_buttons' => true, 'editor_class' => 'dw-editor']); ?>
                                    </div>

                                    <div class="dw-grid-2">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Kategori</label>
                                            <select name="kategori" class="dw-select2" style="width:100%">
                                                <option value="">-- Pilih Kategori --</option>
                                                <?php foreach($kategori_terms as $term): ?>
                                                    <option value="<?php echo esc_attr($term->name); ?>" <?php selected(($data->kategori ?? ''), $term->name); ?>><?php echo esc_html($term->name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p style="font-size:11px; margin-top:5px;"><a href="edit-tags.php?taxonomy=kategori_produk&post_type=dw_produk" target="_blank">+ Kelola Kategori</a></p>
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Kondisi Barang</label>
                                            <select name="kondisi" class="dw-form-control">
                                                <option value="baru" <?php selected($data->kondisi ?? '', 'baru'); ?>>Baru</option>
                                                <option value="bekas" <?php selected($data->kondisi ?? '', 'bekas'); ?>>Bekas</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tab: Harga & Stok -->
                                <div id="tab-price" class="dw-tab-content">
                                    <div class="dw-grid-2">
                                        <div class="dw-form-group">
                                            <label class="dw-label">Harga Satuan (Rp) <span style="color:red">*</span></label>
                                            <input type="number" name="harga" value="<?php echo esc_attr($data->harga ?? 0); ?>" class="dw-form-control" required>
                                        </div>
                                        <div class="dw-form-group">
                                            <label class="dw-label">Stok Tersedia</label>
                                            <input type="number" name="stok" value="<?php echo esc_attr($data->stok ?? 0); ?>" class="dw-form-control">
                                        </div>
                                    </div>
                                    <div class="dw-form-group" style="max-width: 50%;">
                                        <label class="dw-label">Berat Produk (Gram)</label>
                                        <input type="number" name="berat_gram" value="<?php echo esc_attr($data->berat_gram ?? 0); ?>" class="dw-form-control">
                                        <p style="font-size:12px; color:var(--dw-text-muted); margin-top:5px;">Digunakan untuk menghitung ongkir via ekspedisi nasional.</p>
                                    </div>
                                </div>

                                <!-- Tab: Variasi -->
                                <div id="tab-variasi" class="dw-tab-content">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                        <p style="margin:0; font-size:13px; color:var(--dw-text-muted);">Tambah varian jika produk memiliki pilihan warna/ukuran berbeda.</p>
                                        <button type="button" class="dw-btn dw-btn-secondary" id="btn-add-var">+ Tambah Varian</button>
                                    </div>
                                    
                                    <div id="variasi-container">
                                        <?php if(!empty($variations)): foreach($variations as $k => $var): ?>
                                            <div class="var-row">
                                                <span class="var-remove dashicons dashicons-trash"></span>
                                                <input type="hidden" name="variasi_id[<?php echo $k; ?>]" value="<?php echo $var->id; ?>">
                                                <div class="dw-grid-2">
                                                    <div class="dw-form-group">
                                                        <label class="dw-label">Nama Varian (Contoh: Merah, XL)</label>
                                                        <input type="text" name="variasi[<?php echo $k; ?>][deskripsi]" value="<?php echo esc_attr($var->deskripsi_variasi); ?>" class="dw-form-control" required>
                                                    </div>
                                                    <div class="dw-form-group">
                                                        <label class="dw-label">SKU (Kode Unik)</label>
                                                        <input type="text" name="variasi[<?php echo $k; ?>][sku]" value="<?php echo esc_attr($var->sku); ?>" class="dw-form-control">
                                                    </div>
                                                </div>
                                                <div class="dw-grid-2">
                                                    <div class="dw-form-group">
                                                        <label class="dw-label">Harga Varian (Rp)</label>
                                                        <input type="number" name="variasi[<?php echo $k; ?>][harga]" value="<?php echo esc_attr($var->harga_variasi); ?>" class="dw-form-control" required>
                                                    </div>
                                                    <div class="dw-form-group">
                                                        <label class="dw-label">Stok Varian</label>
                                                        <input type="number" name="variasi[<?php echo $k; ?>][stok]" value="<?php echo esc_attr($var->stok_variasi); ?>" class="dw-form-control">
                                                    </div>
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Foto Varian</label>
                                                    <div style="display:flex; gap:10px; align-items:center;">
                                                        <input type="text" name="variasi[<?php echo $k; ?>][foto]" value="<?php echo esc_url($var->foto); ?>" class="dw-form-control var-photo-input">
                                                        <button type="button" class="dw-btn dw-btn-secondary btn-var-upload">Upload</button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; endif; ?>
                                    </div>
                                    
                                    <template id="tmpl-variasi">
                                        <div class="var-row">
                                            <span class="var-remove dashicons dashicons-trash"></span>
                                            <input type="hidden" name="variasi_id[{idx}]" value="0">
                                            <div class="dw-grid-2">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Nama Varian</label>
                                                    <input type="text" name="variasi[{idx}][deskripsi]" class="dw-form-control" placeholder="Contoh: Biru, L" required>
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">SKU</label>
                                                    <input type="text" name="variasi[{idx}][sku]" class="dw-form-control">
                                                </div>
                                            </div>
                                            <div class="dw-grid-2">
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Harga Varian (Rp)</label>
                                                    <input type="number" name="variasi[{idx}][harga]" class="dw-form-control" required>
                                                </div>
                                                <div class="dw-form-group">
                                                    <label class="dw-label">Stok Varian</label>
                                                    <input type="number" name="variasi[{idx}][stok]" class="dw-form-control">
                                                </div>
                                            </div>
                                            <div class="dw-form-group">
                                                <label class="dw-label">Foto Varian</label>
                                                <div style="display:flex; gap:10px; align-items:center;">
                                                    <input type="text" name="variasi[{idx}][foto]" class="dw-form-control var-photo-input">
                                                    <button type="button" class="dw-btn dw-btn-secondary btn-var-upload">Upload</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Tab: Galeri -->
                                <div id="tab-gallery" class="dw-tab-content">
                                    <div style="text-align:center; padding:30px; border:2px dashed var(--dw-border); border-radius:12px; background:#f8fafc; margin-bottom:20px;">
                                        <p style="color:var(--dw-text-muted); margin-bottom:15px;">Tambahkan foto pendukung untuk menarik lebih banyak pembeli.</p>
                                        <button type="button" class="dw-btn dw-btn-primary" id="btn_add_gallery">
                                            <span class="dashicons dashicons-images-alt2"></span> Tambah Foto Galeri
                                        </button>
                                    </div>
                                    
                                    <label class="dw-label">Foto Terupload:</label>
                                    <div class="dw-gallery-grid" id="galeri-container">
                                        <?php if($galeri): foreach($galeri as $g): ?>
                                            <div class="dw-gallery-item">
                                                <img src="<?php echo esc_url($g); ?>">
                                                <span class="dw-gallery-remove"><span class="dashicons dashicons-no-alt"></span></span>
                                                <input type="hidden" name="galeri[]" value="<?php echo esc_url($g); ?>">
                                            </div>
                                        <?php endforeach; endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SIDEBAR COLUMN -->
                    <div class="dw-col-side">
                        <!-- Publish Box -->
                        <div class="dw-card">
                            <div class="dw-card-header"><h3>Status & Penerbitan</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <label class="dw-label">Status Produk</label>
                                    <select name="status" class="dw-form-control">
                                        <option value="aktif" <?php selected($data->status ?? '', 'aktif'); ?>>Aktif (Tampil)</option>
                                        <option value="nonaktif" <?php selected($data->status ?? '', 'nonaktif'); ?>>Disembunyikan</option>
                                        <option value="habis" <?php selected($data->status ?? '', 'habis'); ?>>Stok Habis</option>
                                        <option value="arsip" <?php selected($data->status ?? '', 'arsip'); ?>>Diarsipkan</option>
                                    </select>
                                </div>
                                <button type="submit" class="dw-btn dw-btn-primary dw-btn-block">
                                    <span class="dashicons dashicons-saved"></span> Simpan Produk
                                </button>
                            </div>
                        </div>

                        <!-- Pemilik Box (UPDATE: WAJIB DIISI) -->
                        <?php if ($is_super_admin): ?>
                        <div class="dw-card">
                            <div class="dw-card-header"><h3>Pemilik Toko</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-form-group">
                                    <label class="dw-label">Pilih Toko / Pedagang <span style="color:red">*</span></label>
                                    <select name="id_pedagang" class="dw-select2" style="width:100%;" required>
                                        <option value="">-- Pilih --</option>
                                        <?php 
                                        $pedagangs = $wpdb->get_results("SELECT id, nama_toko FROM $table_pedagang ORDER BY nama_toko ASC");
                                        foreach($pedagangs as $p) {
                                            echo '<option value="'.$p->id.'" '.selected($id_pedagang, $p->id, false).'>'.esc_html($p->nama_toko).'</option>';
                                        }
                                        ?>
                                    </select>
                                    <p style="font-size:11px; color:var(--dw-text-muted); margin-top:5px;">Produk akan dikaitkan dengan toko ini.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Main Image Box -->
                        <div class="dw-card">
                            <div class="dw-card-header"><h3>Foto Utama</h3></div>
                            <div class="dw-card-body">
                                <div class="dw-media-box">
                                    <div class="dw-media-preview <?php echo ($data->foto_utama ?? '') ? 'active' : ''; ?>">
                                        <img src="<?php echo esc_url($data->foto_utama ?? ''); ?>" id="prev_foto">
                                    </div>
                                    <input type="hidden" name="foto_utama" id="val_foto" value="<?php echo esc_url($data->foto_utama ?? ''); ?>">
                                    <button type="button" class="dw-btn dw-btn-secondary btn_upload" data-target="#val_foto" data-preview="#prev_foto" style="margin-top:10px; width:100%;">Pilih Foto Sampul</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            jQuery(document).ready(function($){
                // Select2
                if($.fn.select2) { $('.dw-select2').select2({ width: '100%' }); }

                // Tabs Switching
                $('.dw-tab-item').on('click', function() {
                    $('.dw-tab-item').removeClass('active');
                    $('.dw-tab-content').hide();
                    $(this).addClass('active');
                    $('#' + $(this).data('tab')).fadeIn(200);
                });

                // Media Uploader: Single
                $('.btn_upload').click(function(e){
                    e.preventDefault();
                    var btn = $(this), target = btn.data('target'), prev = btn.data('preview');
                    var frame = wp.media({title:'Pilih Foto', multiple:false, library:{type:'image'}});
                    frame.on('select', function(){
                        var url = frame.state().get('selection').first().toJSON().url;
                        $(target).val(url); $(prev).attr('src', url).parent().show();
                    });
                    frame.open();
                });

                // Media Uploader: Gallery
                $('#btn_add_gallery').click(function(e){
                    e.preventDefault();
                    var frame = wp.media({title:'Pilih Foto Galeri', multiple:true, library:{type:'image'}});
                    frame.on('select', function(){
                        var selection = frame.state().get('selection');
                        selection.map(function(att){
                            var url = att.toJSON().url;
                            $('#galeri-container').append(`
                                <div class="dw-gallery-item">
                                    <img src="${url}">
                                    <span class="dw-gallery-remove"><span class="dashicons dashicons-no-alt"></span></span>
                                    <input type="hidden" name="galeri[]" value="${url}">
                                </div>
                            `);
                        });
                    });
                    frame.open();
                });
                $(document).on('click', '.dw-gallery-remove', function(){ $(this).closest('.dw-gallery-item').remove(); });

                // Variasi Repeater
                var varIdx = <?php echo count($variations); ?>;
                $('#btn-add-var').click(function(){
                    var tpl = $('#tmpl-variasi').html().replace(/{idx}/g, varIdx++);
                    $('#variasi-container').append(tpl);
                });
                $(document).on('click', '.var-remove', function(){ $(this).closest('.var-row').remove(); });
                
                // Variasi Photo Upload
                $(document).on('click', '.btn-var-upload', function(e){
                    e.preventDefault();
                    var btn = $(this), input = btn.siblings('.var-photo-input');
                    var frame = wp.media({title:'Pilih Foto Varian', multiple:false, library:{type:'image'}});
                    frame.on('select', function(){ input.val(frame.state().get('selection').first().toJSON().url); });
                    frame.open();
                });
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}