<?php
/**
 * Halaman Admin: Manajemen Verifikator UMKM (Modern UI Version)
 * Path: includes/admin-pages/page-verifikator-list.php
 * Deskripsi: Menangani CRUD Verifikator dengan Style Terpusat (Admin Style).
 */

defined('ABSPATH') || exit;

// Enqueue Style Admin Utama (Jika ada, biarkan. Jika tidak, style di bawah akan handle)
// wp_enqueue_style('dw-admin-style', ...);

global $wpdb;
$table_verifikator = $wpdb->prefix . 'dw_verifikator';
$users_table = $wpdb->users;

// --- 1. HANDLE FORM SUBMISSION (LOGIC TETAP SAMA) ---
$message = '';
$error = '';

if (isset($_POST['dw_action']) && $_POST['dw_action'] == 'save_verifikator') {
    if (!isset($_POST['dw_verifikator_nonce']) || !wp_verify_nonce($_POST['dw_verifikator_nonce'], 'dw_save_verifikator')) {
        wp_die('Security check failed');
    }

    // Sanitasi Data Input
    $data = [
        'nama_lengkap'   => sanitize_text_field($_POST['nama_lengkap']),
        'nik'            => sanitize_text_field($_POST['nik']),
        'nomor_wa'       => sanitize_text_field($_POST['nomor_wa']),
        'alamat_lengkap' => sanitize_textarea_field($_POST['alamat_lengkap']),
        'kode_referral'  => sanitize_text_field($_POST['kode_referral']),
        'status'         => sanitize_text_field($_POST['status']),
        
        // Data Wilayah (API)
        'provinsi'          => sanitize_text_field($_POST['provinsi_nama']),
        'kabupaten'         => sanitize_text_field($_POST['kabupaten_nama']),
        'kecamatan'         => sanitize_text_field($_POST['kecamatan_nama']),
        'kelurahan'         => sanitize_text_field($_POST['kelurahan_nama']),
        'api_provinsi_id'   => sanitize_text_field($_POST['api_provinsi_id']),
        'api_kabupaten_id'  => sanitize_text_field($_POST['api_kabupaten_id']),
        'api_kecamatan_id'  => sanitize_text_field($_POST['api_kecamatan_id']),
        'api_kelurahan_id'  => sanitize_text_field($_POST['api_kelurahan_id']),
        'kode_pos'          => sanitize_text_field($_POST['kode_pos']),

        // Data Keuangan (Bank)
        'nama_bank'          => sanitize_text_field($_POST['nama_bank']),
        'no_rekening'        => sanitize_text_field($_POST['no_rekening']),
        'atas_nama_rekening' => sanitize_text_field($_POST['atas_nama_rekening']),
    ];

    // Handle Upload Foto Profil
    if (!empty($_FILES['foto_profil']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $upload = wp_handle_upload($_FILES['foto_profil'], ['test_form' => false]);
        if (isset($upload['url']) && !isset($upload['error'])) {
            $data['foto_profil'] = $upload['url'];
        }
    }

    $verifikator_id = isset($_POST['verifikator_id']) ? intval($_POST['verifikator_id']) : 0;
    $user_id_input = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($verifikator_id > 0) {
        // UPDATE Existing
        $is_taken = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_verifikator WHERE user_id = %d AND id != %d", 
            $user_id_input, 
            $verifikator_id
        ));

        if ($is_taken) {
            $error = "Gagal: User WordPress yang dipilih sudah terhubung dengan verifikator lain.";
        } else {
            if ($user_id_input > 0) {
                $data['user_id'] = $user_id_input; 
            }
            $data['updated_at'] = current_time('mysql');
            $wpdb->update($table_verifikator, $data, ['id' => $verifikator_id]);
            $message = "Data verifikator berhasil diperbarui.";
        }

    } else {
        // INSERT New
        if ($user_id_input > 0) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_verifikator WHERE user_id = %d", $user_id_input));
            if ($exists) {
                $error = "Error: User ini sudah terdaftar sebagai verifikator.";
            } else {
                $data['user_id'] = $user_id_input;
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table_verifikator, $data);
                $message = "Verifikator baru berhasil ditambahkan.";
                
                $u = new WP_User($user_id_input);
                $u->add_role('verifikator_umkm');
            }
        } else {
            $error = "Error: User WordPress harus dipilih.";
        }
    }
}

// --- 2. HANDLE DELETE ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    check_admin_referer('delete_verifikator_' . $del_id);
    $wpdb->delete($table_verifikator, ['id' => $del_id]);
    $message = "Verifikator dihapus.";
}

// --- 3. PREPARE TABLE DATA ---
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$limit = 20;
$offset = ($paged - 1) * $limit;

$where = "WHERE 1=1";
if ($search) {
    $where .= $wpdb->prepare(" AND (nama_lengkap LIKE %s OR nik LIKE %s OR nomor_wa LIKE %s OR kode_referral LIKE %s)", "%$search%", "%$search%", "%$search%", "%$search%");
}

$total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_verifikator $where");
$total_pages = ceil($total_items / $limit);

$items = $wpdb->get_results("
    SELECT v.*, u.user_email, u.user_login 
    FROM $table_verifikator v 
    LEFT JOIN $users_table u ON v.user_id = u.ID 
    $where 
    ORDER BY v.created_at DESC 
    LIMIT $limit OFFSET $offset
");

$used_user_ids = $wpdb->get_col("SELECT user_id FROM $table_verifikator");
if (!$used_user_ids) $used_user_ids = [];

?>

<!-- CSS PREMIUM DASHBOARD (Updated from assets/css/admin-style.css) -->
<style>
    /* =========================================
       1. CORE VARIABLES & THEME CONFIG
       ========================================= */
    :root {
        /* Brand Colors (Premium Palette) */
        --dw-brand-blue: #1e40af;      /* Biru Gelap (Header) */
        --dw-brand-light: #eff6ff;     /* Biru Sangat Muda (Background Icon) */
        --dw-brand-hover: #1e3a8a;     /* Hover State */
        
        /* Text Colors */
        --dw-text-dark: #1e293b;       /* Slate 800 */
        --dw-text-grey: #64748b;       /* Slate 500 */
        --dw-text-white: #ffffff;
        
        /* UI Colors */
        --dw-bg: #f0f2f5;              /* Background Halaman Dashboard */
        --dw-white: #ffffff;
        --dw-border-color: #e2e8f0;
        
        /* Status Indicators */
        --dw-success: #10b981;
        --dw-warning: #f59e0b;
        --dw-danger: #ef4444;
        --dw-info: #0ea5e9;

        /* Legacy/WP Compatibility Variables (Mapped to New Palette) */
        --dw-primary: var(--dw-brand-blue);
        --dw-primary-hover: var(--dw-brand-hover);
        --dw-secondary: var(--dw-text-grey);
        --dw-border: var(--dw-border-color);
    }

    /* =========================================
       2. GLOBAL RESETS & LAYOUT
       ========================================= */
    .dw-wrap, 
    .dw-admin-wrapper {
        margin: 20px 20px 0 0;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: var(--dw-text-dark);
        max-width: 1200px;
    }

    /* --- HERO BANNER (Blue Box) --- */
    .dw-dashboard-hero {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); /* Gradient Biru Profesional */
        color: #fff;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.15);
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Pola background halus */
    .dw-dashboard-hero::before {
        content: '';
        position: absolute;
        right: 0; bottom: 0;
        width: 300px; height: 100%;
        background: radial-gradient(circle at bottom right, rgba(255,255,255,0.1) 0%, transparent 60%);
        pointer-events: none;
    }

    .dw-hero-content h2 {
        color: #fff !important;
        font-size: 28px;
        font-weight: 700;
        margin: 0 0 10px 0;
        line-height: 1.2;
    }

    .dw-hero-content p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 15px;
        margin: 0;
        max-width: 600px;
    }

    .dw-hero-date {
        background: rgba(255, 255, 255, 0.1);
        padding: 10px 20px;
        border-radius: 12px;
        backdrop-filter: blur(5px);
        text-align: right;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .dw-hero-date span {
        display: block;
        font-size: 12px;
        text-transform: uppercase;
        opacity: 0.8;
        margin-bottom: 4px;
    }

    .dw-hero-date strong {
        font-size: 16px;
        font-weight: 600;
    }

    /* --- STATS GRID (Clean Cards) --- */
    .dw-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Responsif */
        gap: 24px;
        margin-bottom: 32px;
    }

    .dw-stat-card {
        background: var(--dw-white);
        border-radius: 16px;
        padding: 24px;
        border: 1px solid var(--dw-border-color);
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 160px; /* Tinggi seragam */
        box-sizing: border-box;
    }

    .dw-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        border-color: #cbd5e1;
    }

    .dw-stat-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        font-size: 24px;
    }

    /* Warna-warni Icon Background */
    .bg-blue { background: #eff6ff; color: #2563eb; }
    .bg-green { background: #f0fdf4; color: #16a34a; }
    .bg-purple { background: #f3e8ff; color: #9333ea; }
    .bg-orange { background: #fff7ed; color: #ea580c; }
    .bg-teal { background: #ccfbf1; color: #0d9488; }

    .dw-stat-value {
        font-size: 28px;
        font-weight: 800;
        color: var(--dw-text-dark);
        margin: 0;
        line-height: 1;
    }

    .dw-stat-label {
        font-size: 14px;
        color: var(--dw-text-grey);
        font-weight: 500;
        margin-top: 8px;
        display: block;
    }

    /* =========================================
       3. COMPONENTS STANDARD (Cards, Tables, Forms)
       ========================================= */

    /* --- Page Header (Standardized) --- */
    .dw-page-header {
        background: transparent;
        padding-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .dw-header-title h1 {
        font-size: 24px;
        font-weight: 700;
        color: var(--dw-text-dark);
        margin: 0;
        line-height: 1.2;
        padding: 0;
        display: inline-block;
    }

    .dw-subtitle {
        font-size: 14px;
        color: var(--dw-text-grey);
        margin: 6px 0 0;
    }

    .dw-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* --- Card Container --- */
    .dw-card {
        background: var(--dw-white);
        border-radius: 16px;
        border: 1px solid var(--dw-border-color);
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        margin-bottom: 24px;
        padding: 0; /* Reset padding to handle header/body separation */
        overflow: hidden;
        box-sizing: border-box;
    }

    .dw-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--dw-border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }

    .dw-card-header h3, 
    .dw-card-header h3.card-heading {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: var(--dw-text-dark);
    }

    .dw-card-body,
    .dw-card > form { /* Direct form inside card gets padding */
        padding: 24px;
    }

    /* --- Buttons --- */
    .dw-button,
    .dw-header-actions .button,
    .dw-header-actions .page-title-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        text-decoration: none;
        line-height: normal;
        height: auto;
    }

    .dw-button-primary,
    .dw-header-actions .button-primary {
        background: var(--dw-brand-blue);
        color: #fff;
        border: 1px solid var(--dw-brand-blue);
    }

    .dw-button-primary:hover,
    .dw-header-actions .button-primary:hover {
        background: var(--dw-brand-hover);
        color: #fff;
    }

    .dw-button-secondary {
        background: #f1f5f9;
        color: var(--dw-text-dark);
        border: 1px solid var(--dw-border-color);
    }

    .dw-button-secondary:hover {
        background: #e2e8f0;
    }

    /* --- Forms --- */
    .dw-form-group {
        margin-bottom: 20px;
    }

    .dw-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--dw-text-dark);
        font-size: 14px;
    }

    .dw-input, .dw-select, .dw-textarea,
    .dw-card input[type="text"], 
    .dw-card input[type="email"], 
    .dw-card input[type="number"], 
    .dw-card select, 
    .dw-card textarea {
        width: 100%;
        max-width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--dw-border-color);
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
        margin: 0;
    }

    .dw-input:focus, .dw-select:focus, .dw-textarea:focus,
    .dw-card input:focus, .dw-card select:focus, .dw-card textarea:focus {
        outline: none;
        border-color: var(--dw-brand-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .dw-help-text {
        margin-top: 6px;
        font-size: 12px;
        color: var(--dw-text-grey);
    }

    .dw-grid-2-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    .dw-section-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--dw-text-dark);
        margin: 24px 0 16px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--dw-bg);
    }

    /* --- Table (Custom Modern) --- */
    .dw-table-wrapper {
        overflow-x: auto;
        background: var(--dw-white);
        border-radius: 12px;
        border: 1px solid var(--dw-border-color);
        margin-bottom: 20px;
    }

    .dw-modern-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .dw-modern-table th {
        background: #f8fafc;
        padding: 16px;
        font-weight: 600;
        color: var(--dw-text-grey);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        border-bottom: 1px solid var(--dw-border-color);
    }

    .dw-modern-table td {
        padding: 16px;
        border-bottom: 1px solid var(--dw-border-color);
        color: var(--dw-text-dark);
        font-size: 14px;
    }

    .dw-modern-table tr:last-child td {
        border-bottom: none;
    }

    .dw-modern-table tr:hover td {
        background: #f1f5f9;
    }

    /* --- Badges --- */
    .dw-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-success { background: #dcfce7; color: #166534; }
    .status-warning { background: #fef9c3; color: #854d0e; }
    .status-danger { background: #fee2e2; color: #991b1b; }

    /* Modal Styles */
    .dw-modal {
        display: none; 
        position: fixed; 
        z-index: 9999; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        overflow: auto; 
        background-color: rgba(0,0,0,0.5); 
        backdrop-filter: blur(2px);
        align-items: center;
        justify-content: center;
    }

    .dw-modal-content {
        background-color: #fefefe;
        margin: auto;
        border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        position: relative;
        animation: fadeIn 0.3s;
        border: none;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .dw-modal-close {
        color: #94a3b8;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
    }

    .dw-modal-close:hover {
        color: var(--dw-danger);
    }
    
    /* UTILS */
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .info-item { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; font-size: 13px; }
    .info-item span.dashicons { font-size: 16px; color: #94a3b8; width: 16px; height: 16px; }

</style>

<div class="wrap dw-admin-wrapper">
    <!-- PAGE HEADER -->
    <div class="dw-page-header">
        <div class="dw-header-title">
            <h1>Manajemen Verifikator UMKM</h1>
            <p class="dw-subtitle">Kelola data mitra verifikator, wilayah operasional, dan komisi.</p>
        </div>
        <div class="dw-header-actions">
            <button id="btn-add-verifikator" class="dw-button dw-button-primary">
                <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;margin-right:5px;"></span> Tambah Baru
            </button>
        </div>
    </div>

    <!-- NOTICES -->
    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible" style="margin-left:0; margin-bottom: 20px;"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible" style="margin-left:0; margin-bottom: 20px;"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <!-- STATS GRID -->
    <div class="dw-stats-grid">
        <div class="dw-stat-card">
            <div class="dw-stat-icon-wrapper bg-blue"><span class="dashicons dashicons-groups"></span></div>
            <div>
                <h3 class="dw-stat-value"><?php echo $total_items; ?></h3>
                <span class="dw-stat-label">Total Verifikator</span>
            </div>
        </div>
        <div class="dw-stat-card">
            <div class="dw-stat-icon-wrapper bg-green"><span class="dashicons dashicons-money-alt"></span></div>
            <div>
                <?php 
                    $total_komisi = $wpdb->get_var("SELECT SUM(total_pendapatan_komisi) FROM $table_verifikator"); 
                ?>
                <h3 class="dw-stat-value">Rp <?php echo number_format($total_komisi, 0, ',', '.'); ?></h3>
                <span class="dw-stat-label">Total Komisi Tersalurkan</span>
            </div>
        </div>
    </div>

    <!-- MAIN CARD -->
    <div class="dw-card">
        <div class="dw-card-header">
            <h3 class="card-heading">Daftar Mitra Verifikator</h3>
            <form method="get" style="display:flex; gap:10px;">
                <input type="hidden" name="page" value="dw-verifikator">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Cari Nama/NIK/WA..." class="dw-input" style="width: 250px; padding: 8px 12px;">
                <button type="submit" class="dw-button dw-button-secondary">Cari</button>
            </form>
        </div>
        
        <!-- TABLE LIST -->
        <div class="dw-card-body p-0">
            <div class="dw-table-wrapper" style="border:none; margin-bottom:0;">
                <table class="dw-modern-table">
                    <thead>
                        <tr>
                            <th width="25%">Profil & Akun</th>
                            <th width="20%">Kontak & Wilayah</th>
                            <th width="15%">Status & Referral</th>
                            <th width="20%">Keuangan (Saldo)</th>
                            <th width="10%" class="text-center">Kinerja</th>
                            <th width="10%" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items): foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <img src="<?php echo !empty($item->foto_profil) ? esc_url($item->foto_profil) : 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($item->user_email))).'?s=60&d=mp'; ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0;">
                                        <div>
                                            <strong style="font-size: 14px; color: var(--dw-text-dark); display:block;"><?php echo esc_html($item->nama_lengkap); ?></strong>
                                            <div style="font-size: 11px; color: var(--dw-text-grey); margin-top:2px;">
                                                <span class="dashicons dashicons-id" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span> <?php echo esc_html($item->nik); ?>
                                            </div>
                                            <span style="font-size: 11px; color: #94a3b8;">@<?php echo esc_html($item->user_login); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="info-item">
                                        <span class="dashicons dashicons-whatsapp"></span>
                                        <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $item->nomor_wa)); ?>" target="_blank" style="text-decoration: none; color: var(--dw-brand-blue); font-weight:500;"><?php echo esc_html($item->nomor_wa); ?></a>
                                    </div>
                                    <div class="info-item" style="align-items: flex-start;">
                                        <span class="dashicons dashicons-location" style="margin-top:2px;"></span>
                                        <span style="color: var(--dw-text-grey); line-height: 1.3;">
                                            <?php echo esc_html(($item->kabupaten ? $item->kabupaten : $item->provinsi)); ?><br>
                                            <small style="font-size: 10px; color: #cbd5e1;"><?php echo mb_strimwidth(esc_html($item->alamat_lengkap), 0, 40, "..."); ?></small>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = ($item->status == 'aktif') ? 'status-success' : (($item->status == 'pending') ? 'status-warning' : 'status-danger');
                                    $status_label = ucfirst($item->status);
                                    echo "<span class='dw-badge $status_class' style='margin-bottom:6px;'>$status_label</span>";
                                    ?>
                                    <div style="display:flex; align-items:center; gap:5px;">
                                        <small style="color:#94a3b8; font-size:10px;">REF:</small>
                                        <code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; color:#475569; font-size:11px; border:1px solid #e2e8f0;"><?php echo esc_html($item->kode_referral ?: '-'); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 14px; font-weight: 700; color: var(--dw-text-dark);">Rp <?php echo number_format($item->saldo_saat_ini, 0, ',', '.'); ?></div>
                                    <div style="margin-top: 4px; font-size: 11px; color: var(--dw-text-grey);">
                                        Total: <span style="color: var(--dw-success);">+Rp <?php echo number_format($item->total_pendapatan_komisi, 0, ',', '.'); ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div style="background: #eff6ff; color: var(--dw-brand-blue); border-radius: 8px; padding: 6px; display: inline-block; min-width: 40px;">
                                        <div style="font-size: 16px; font-weight: 700;"><?php echo intval($item->total_verifikasi_sukses); ?></div>
                                        <div style="font-size: 9px; text-transform: uppercase; font-weight: 600; opacity: 0.8;">UMKM</div>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div style="display:flex; justify-content:flex-end; gap:5px;">
                                        <button type="button" class="dw-button dw-button-secondary btn-detail" data-json='<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>' title="Lihat Detail" style="padding: 6px 8px;"><span class="dashicons dashicons-visibility" style="margin:0;"></span></button>
                                        <button type="button" class="dw-button dw-button-secondary edit-verifikator" data-json='<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>' title="Edit" style="padding: 6px 8px;"><span class="dashicons dashicons-edit" style="margin:0;"></span></button>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dw-verifikator&action=delete&id=' . $item->id), 'delete_verifikator_' . $item->id); ?>" onclick="return confirm('Yakin hapus verifikator ini?');" class="dw-button dw-button-secondary" style="color:var(--dw-danger); padding: 6px 8px;" title="Hapus"><span class="dashicons dashicons-trash" style="margin:0;"></span></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" style="padding: 60px; text-align: center; color: #94a3b8;">
                                <span class="dashicons dashicons-buddicons-groups" style="font-size: 40px; width:40px; height:40px; margin-bottom:10px; display:block; margin: 0 auto 10px;"></span>
                                Belum ada data verifikator. Silakan tambah baru.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom" style="padding: 0 24px 20px; border-top: 1px solid var(--dw-border-color); margin-top:0;">
                    <div class="tablenav-pages">
                        <span class="displaying-num" style="color: var(--dw-text-grey); font-size:12px;"><?php echo $total_items; ?> items</span>
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $paged
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ================= MODAL FORM (ADD/EDIT) ================= -->
<div id="modal-verifikator" class="dw-modal">
    <div class="dw-modal-content" style="width: 750px;">
        <div class="dw-card-header" style="border-bottom:1px solid #e2e8f0; background:#fff; border-radius: 16px 16px 0 0; padding: 20px 24px;">
            <h3 class="modal-title" style="font-size:18px;">Tambah Verifikator Baru</h3>
            <span class="close-modal dw-modal-close">&times;</span>
        </div>
        
        <form method="post" enctype="multipart/form-data" id="form-verifikator">
            <div class="dw-card-body" style="max-height: 70vh; overflow-y: auto; padding: 24px;">
                <?php wp_nonce_field('dw_save_verifikator', 'dw_verifikator_nonce'); ?>
                <input type="hidden" name="dw_action" value="save_verifikator">
                <input type="hidden" name="verifikator_id" id="verifikator_id" value="">
                
                <!-- Section 1 -->
                <h4 class="dw-section-title" style="margin-top:0;">1. Informasi Akun & Pribadi</h4>
                <div class="dw-grid-2-col">
                    <div class="dw-form-group">
                        <label for="user_id" class="dw-label">User WordPress <span style="color:red">*</span></label>
                        <?php 
                        $users = get_users(['role__in' => ['subscriber', 'verifikator_umkm', 'contributor'], 'fields' => ['ID', 'display_name', 'user_email']]); 
                        ?>
                        <select name="user_id" id="user_id" class="dw-select">
                            <option value="">-- Pilih User WP --</option>
                            <?php foreach($users as $user): 
                                $is_used = in_array($user->ID, $used_user_ids);
                                $used_attr = $is_used ? 'data-used="1"' : 'data-used="0"';
                            ?>
                                <option value="<?php echo $user->ID; ?>" <?php echo $used_attr; ?>><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p style="font-size:11px; color:#94a3b8; margin:4px 0 0;">Hubungkan dengan akun login WordPress.</p>
                    </div>
                    <div class="dw-form-group">
                        <label for="nik" class="dw-label">NIK (KTP) <span style="color:red">*</span></label>
                        <input type="text" name="nik" id="nik" class="dw-input" required placeholder="16 digit angka">
                    </div>
                </div>

                <div class="dw-grid-2-col">
                    <div class="dw-form-group">
                        <label for="nama_lengkap" class="dw-label">Nama Lengkap <span style="color:red">*</span></label>
                        <input type="text" name="nama_lengkap" id="nama_lengkap" class="dw-input" required placeholder="Sesuai KTP">
                    </div>
                    <div class="dw-form-group">
                        <label for="nomor_wa" class="dw-label">Nomor WhatsApp <span style="color:red">*</span></label>
                        <input type="text" name="nomor_wa" id="nomor_wa" class="dw-input" required placeholder="0812...">
                    </div>
                </div>

                <div class="dw-form-group">
                    <label for="foto_profil" class="dw-label">Foto Profil</label>
                    <input type="file" name="foto_profil" id="foto_profil" class="dw-input" style="padding: 8px;">
                    <div class="img-preview-container" style="display:none; margin-top:15px; text-align:center; background:#f8fafc; padding:10px; border-radius:8px;">
                        <img id="preview_foto" src="" style="width:100px; height:100px; object-fit:cover; border-radius:50%; border:3px solid #fff; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                    </div>
                </div>

                <!-- Section 2 -->
                <h4 class="dw-section-title">2. Wilayah Operasional</h4>
                <div class="dw-form-group">
                    <label for="alamat_lengkap" class="dw-label">Alamat Lengkap</label>
                    <textarea name="alamat_lengkap" id="alamat_lengkap" class="dw-textarea" rows="2" placeholder="Nama Jalan, RT/RW..."></textarea>
                </div>

                <!-- Hidden inputs for names -->
                <input type="hidden" name="provinsi_nama" id="provinsi_nama">
                <input type="hidden" name="kabupaten_nama" id="kabupaten_nama">
                <input type="hidden" name="kecamatan_nama" id="kecamatan_nama">
                <input type="hidden" name="kelurahan_nama" id="kelurahan_nama">

                <div class="dw-grid-2-col">
                    <div class="dw-form-group">
                        <label class="dw-label">Provinsi</label>
                        <select name="api_provinsi_id" id="api_provinsi_id" class="dw-select region-select">
                            <option value="">Pilih Provinsi</option>
                        </select>
                    </div>
                    <div class="dw-form-group">
                        <label class="dw-label">Kabupaten/Kota</label>
                        <select name="api_kabupaten_id" id="api_kabupaten_id" class="dw-select region-select" disabled>
                            <option value="">Pilih Kabupaten</option>
                        </select>
                    </div>
                </div>
                <div class="dw-grid-2-col">
                    <div class="dw-form-group">
                        <label class="dw-label">Kecamatan</label>
                        <select name="api_kecamatan_id" id="api_kecamatan_id" class="dw-select region-select" disabled>
                            <option value="">Pilih Kecamatan</option>
                        </select>
                    </div>
                    <div class="dw-form-group">
                        <label class="dw-label">Kelurahan/Desa</label>
                        <select name="api_kelurahan_id" id="api_kelurahan_id" class="dw-select region-select" disabled>
                            <option value="">Pilih Kelurahan</option>
                        </select>
                    </div>
                </div>
                <div class="dw-form-group">
                    <label for="kode_pos" class="dw-label">Kode Pos</label>
                    <input type="text" name="kode_pos" id="kode_pos" class="dw-input" style="width:150px;">
                </div>

                <!-- Section 3 -->
                <h4 class="dw-section-title">3. Data Keuangan & Status</h4>
                <div style="background: #eff6ff; padding: 20px; border-radius: 12px; border: 1px solid #dbeafe; margin-bottom: 20px;">
                    <div class="dw-grid-2-col">
                        <div class="dw-form-group">
                            <label for="nama_bank" class="dw-label">Nama Bank</label>
                            <input type="text" name="nama_bank" id="nama_bank" class="dw-input" placeholder="BCA, BRI...">
                        </div>
                        <div class="dw-form-group">
                            <label for="no_rekening" class="dw-label">Nomor Rekening</label>
                            <input type="text" name="no_rekening" id="no_rekening" class="dw-input">
                        </div>
                    </div>
                    <div class="dw-form-group" style="margin-bottom:0;">
                        <label for="atas_nama_rekening" class="dw-label">Atas Nama Pemilik</label>
                        <input type="text" name="atas_nama_rekening" id="atas_nama_rekening" class="dw-input">
                    </div>
                </div>
                
                <div class="dw-grid-2-col">
                    <div class="dw-form-group">
                        <label for="kode_referral" class="dw-label">Kode Referral</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="kode_referral" id="kode_referral" class="dw-input" placeholder="Auto / Custom">
                            <button type="button" id="btn-generate-ref" class="dw-button dw-button-secondary" title="Buat Acak">
                                <span class="dashicons dashicons-randomize" style="margin-top:4px;"></span>
                            </button>
                        </div>
                    </div>
                    <div class="dw-form-group">
                        <label for="status" class="dw-label">Status Akun</label>
                        <select name="status" id="status" class="dw-select">
                            <option value="pending">Pending</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="padding: 20px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: right; border-radius: 0 0 16px 16px;">
                <button type="button" class="dw-button dw-button-secondary close-modal" style="margin-right:10px;">Batal</button>
                <button type="submit" name="submit" id="submit" class="dw-button dw-button-primary">Simpan Verifikator</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL DETAIL VIEW (LANDSCAPE LAYOUT IMPROVED UX) ================= -->
<div id="modal-detail" class="dw-modal">
    <div class="dw-modal-content" style="width: 900px; height: 600px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;">
        
        <!-- Header -->
        <div class="dw-card-header" style="background: #fff; padding: 15px 30px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 36px; height: 36px; background: #eff6ff; color: var(--dw-brand-blue); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <span class="dashicons dashicons-id-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
                </div>
                <div>
                    <h3 class="modal-title" style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">Detail Mitra Verifikator</h3>
                </div>
            </div>
            <span class="close-modal dw-modal-close" style="font-size: 24px;">&times;</span>
        </div>
        
        <div class="modal-body-layout" style="display: flex; flex-direction: row; height: 100%; overflow: hidden;">
            
            <!-- LEFT SIDEBAR (Profile Summary) -->
            <div class="detail-left-panel" style="width: 300px; background: #f8fafc; border-right: 1px solid #e2e8f0; padding: 30px; text-align: center; overflow-y: auto; flex-shrink: 0;">
                <div style="position: relative; display: inline-block; margin-bottom: 15px;">
                    <img src="" id="det-foto" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                    <div id="det-status-indicator" style="position: absolute; bottom: 8px; right: 8px; width: 24px; height: 24px; border-radius: 50%; border: 3px solid #fff;"></div>
                </div>
                
                <h3 id="det-nama" style="margin: 0 0 5px; font-size: 18px; font-weight: 700; color: #1e293b;"></h3>
                <p id="det-role-label" style="margin: 0 0 20px; font-size: 13px; color: #64748b;">Mitra Verifikator UMKM</p>
                
                <div id="det-status-badge" style="margin-bottom: 25px;"></div>

                <!-- Stats Mini -->
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 20px; text-align: left;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 5px;">Total UMKM</div>
                    <div style="display:flex; align-items:baseline; gap:5px;">
                        <span id="det-total-umkm" style="font-size: 28px; font-weight: 800; color: #0f172a;">0</span>
                        <span style="font-size: 12px; color: #64748b;">Terverifikasi</span>
                    </div>
                    <div style="width:100%; height:4px; background:#f1f5f9; border-radius:2px; margin-top:8px;">
                        <div style="width:70%; height:100%; background:var(--dw-brand-blue); border-radius:2px;"></div>
                    </div>
                </div>

                <div style="text-align: left;">
                    <p style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Kode Referral</p>
                    <div style="background: #eff6ff; border: 1px dashed #93c5fd; color: #1d4ed8; padding: 10px; border-radius: 8px; font-family: monospace; font-weight: 700; text-align: center; font-size: 16px; cursor: pointer; transition: all 0.2s;" onclick="navigator.clipboard.writeText(this.innerText); alert('Kode disalin!');" title="Klik untuk salin">
                        <span id="det-ref">-</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT CONTENT (Details) -->
            <div class="detail-right-panel" style="flex-grow: 1; padding: 30px; overflow-y: auto;">
                
                <!-- Financial Cards -->
                <h4 style="font-size: 13px; font-weight: 700; color: #64748b; margin-top: 0; margin-bottom: 15px; text-transform: uppercase;">Informasi Keuangan</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <!-- Gradient Card -->
                    <div style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 25px; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3); position: relative; overflow: hidden;">
                        <div style="position: relative; z-index: 1;">
                            <div style="font-size: 13px; opacity: 0.9; font-weight: 500; margin-bottom: 8px;">Saldo Dompet</div>
                            <div style="font-size: 32px; font-weight: 800; letter-spacing: -0.5px;" id="det-saldo">Rp 0</div>
                        </div>
                        <span class="dashicons dashicons-wallet" style="position: absolute; right: -15px; bottom: -20px; font-size: 100px; width: 100px; height: 100px; color: rgba(255,255,255,0.1); transform: rotate(-15deg);"></span>
                    </div>
                    
                    <div style="background: #fff; border: 1px solid #e2e8f0; padding: 25px; border-radius: 16px;">
                        <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 8px;">Total Pendapatan (Lifetime)</div>
                        <div style="font-size: 28px; font-weight: 800; color: #0f172a;" id="det-total-komisi">Rp 0</div>
                        <div style="font-size: 11px; color: #10b981; margin-top: 5px; font-weight:500;">
                            <span class="dashicons dashicons-arrow-up-alt" style="font-size:14px; width:14px; height:14px;"></span> Produktif
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    
                    <!-- Column 1: Personal Info -->
                    <div>
                        <h4 style="font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px;">DATA PRIBADI</h4>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div>
                                <span style="display: block; color: #94a3b8; font-size: 11px; font-weight: 600; margin-bottom: 2px;">NIK (KTP)</span>
                                <span style="font-size: 14px; font-weight: 500; color: #334155; font-family: monospace;" id="det-nik">-</span>
                            </div>
                            <div>
                                <span style="display: block; color: #94a3b8; font-size: 11px; font-weight: 600; margin-bottom: 2px;">Email Akun</span>
                                <span style="font-size: 14px; font-weight: 500; color: #334155;" id="det-email">-</span>
                            </div>
                            <div>
                                <span style="display: block; color: #94a3b8; font-size: 11px; font-weight: 600; margin-bottom: 2px;">WhatsApp</span>
                                <a href="#" id="det-wa-link" target="_blank" style="font-size: 14px; font-weight: 600; color: var(--dw-brand-blue); text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                    <span id="det-wa">-</span> <span class="dashicons dashicons-external" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Bank Info -->
                    <div>
                        <h4 style="font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px;">REKENING BANK</h4>
                         <div style="background: #f8fafc; border-radius: 8px; padding: 15px; border: 1px solid #e2e8f0;">
                            <div style="margin-bottom: 10px;">
                                <span style="display: block; color: #64748b; font-size: 11px; font-weight: 600;">Bank</span>
                                <span style="font-size: 15px; font-weight: 700; color: #0f172a;" id="det-bank">-</span>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <span style="display: block; color: #64748b; font-size: 11px; font-weight: 600;">Nomor Rekening</span>
                                <span style="font-size: 15px; font-weight: 500; color: #334155; font-family: monospace;" id="det-rek">-</span>
                            </div>
                            <div>
                                <span style="display: block; color: #64748b; font-size: 11px; font-weight: 600;">Atas Nama</span>
                                <span style="font-size: 14px; font-weight: 500; color: #334155;" id="det-an">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Full Width Address -->
                <div style="margin-top: 30px;">
                    <h4 style="font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px;">ALAMAT LENGKAP</h4>
                    <p style="font-size: 14px; color: #475569; line-height: 1.6; background: #fff; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin:0;" id="det-alamat">
                        -
                    </p>
                </div>

            </div>
        </div>
        
        <!-- Footer -->
        <div class="modal-footer" style="padding: 15px 30px; border-top: 1px solid #f1f5f9; background: #f8fafc; text-align: right; flex-shrink: 0;">
            <button type="button" class="dw-button dw-button-secondary close-modal" style="font-weight: 600;">Tutup</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 1. MODAL SYSTEM
    function openModal(modalId, title) {
        if(title) $(modalId).find('.modal-title').text(title);
        $(modalId).fadeIn(200).css('display', 'flex'); // Flex for centering
        $('body').css('overflow', 'hidden'); 
    }

    function closeModal() {
        $('.dw-modal').fadeOut(200);
        $('body').css('overflow', 'auto');
    }

    $('#btn-add-verifikator').click(function(e) {
        e.preventDefault();
        $('#verifikator_id').val(''); 
        $('#user_id').prop('disabled', false).val(''); 
        
        // Reset status opsi user
        $('#user_id option').each(function() {
            var text = $(this).text().replace(' (Terpakai)', '');
            if ($(this).data('used') == 1) {
                $(this).prop('disabled', true).text(text + ' (Terpakai)');
            } else {
                $(this).prop('disabled', false).text(text);
            }
        });

        $('.img-preview-container').hide();
        $('#form-verifikator')[0].reset();
        
        // Reset dropdown wilayah
        $('#api_kabupaten_id, #api_kecamatan_id, #api_kelurahan_id').html('<option value="">Pilih...</option>').prop('disabled', true);
        
        openModal('#modal-verifikator', 'Tambah Verifikator Baru');
    });

    $('.close-modal').click(function() {
        closeModal();
    });

    $(window).click(function(e) {
        if ($(e.target).hasClass('dw-modal')) {
            closeModal();
        }
    });

    // 2. KODE GENERATOR UX
    $('#btn-generate-ref').click(function() {
        var nama = $('#nama_lengkap').val();
        var prefix = nama ? nama.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, 'REF') : 'REF';
        var random = Math.floor(1000 + Math.random() * 9000); 
        var code = prefix + random;
        
        var $input = $('#kode_referral');
        $input.val(code).css('transition', 'background 0.3s').css('background-color', '#dcfce7');
        setTimeout(function() {
            $input.css('background-color', '');
        }, 500);
    });

    // Foto Preview UX
    $('#foto_profil').change(function(){
        const file = this.files[0];
        if (file){
            let reader = new FileReader();
            reader.onload = function(event){
                $('#preview_foto').attr('src', event.target.result);
                $('.img-preview-container').show();
            }
            reader.readAsDataURL(file);
        }
    });

    // 3. EDIT LOGIC
    $('.edit-verifikator').click(function(e) {
        e.preventDefault();
        var data = $(this).data('json');
        
        $('#verifikator_id').val(data.id);
        
        // Logic User ID
        $('#user_id').prop('disabled', false).val(data.user_id);
        $('#user_id option').each(function() {
            var isUsed = $(this).data('used') == 1;
            var isCurrentOwner = $(this).val() == data.user_id;
            var cleanText = $(this).text().replace(' (Terpakai)', '');
            $(this).text(cleanText);

            if (isUsed) {
                if (isCurrentOwner) {
                    $(this).prop('disabled', false);
                } else {
                    $(this).prop('disabled', true).text(cleanText + ' (Terpakai)');
                }
            } else {
                $(this).prop('disabled', false);
            }
        });

        $('#nama_lengkap').val(data.nama_lengkap);
        $('#nik').val(data.nik);
        $('#nomor_wa').val(data.nomor_wa);
        $('#alamat_lengkap').val(data.alamat_lengkap);
        $('#kode_referral').val(data.kode_referral);
        $('#status').val(data.status);
        
        $('#nama_bank').val(data.nama_bank);
        $('#no_rekening').val(data.no_rekening);
        $('#atas_nama_rekening').val(data.atas_nama_rekening);
        $('#kode_pos').val(data.kode_pos);

        // Pre-fill names
        $('#provinsi_nama').val(data.provinsi);
        $('#kabupaten_nama').val(data.kabupaten);
        $('#kecamatan_nama').val(data.kecamatan);
        $('#kelurahan_nama').val(data.kelurahan);

        if(data.foto_profil) {
            $('#preview_foto').attr('src', data.foto_profil);
            $('.img-preview-container').show();
        } else {
            $('.img-preview-container').hide();
        }
        
        openModal('#modal-verifikator', 'Edit Verifikator: ' + data.nama_lengkap);

        // Trigger Region Loads for Edit
        $('#api_provinsi_id').val(data.api_provinsi_id);
        // Note: Untuk auto-populate wilayah secara cascading saat edit, idealnya perlu request AJAX berantai.
        // Di sini kita set value hidden agar tersimpan jika tidak diubah user.
    });

    // 4. DETAIL MODAL LOGIC (Updated for Landscape)
    $('.btn-detail').click(function(e) {
        e.preventDefault();
        var data = $(this).data('json');
        var fmt = new Intl.NumberFormat('id-ID');

        // Populate Data
        $('#det-nama').text(data.nama_lengkap);
        $('#det-foto').attr('src', data.foto_profil || 'https://www.gravatar.com/avatar/?d=mp');
        $('#det-nik').text(data.nik || '-');
        
        // Format WA link
        var cleanWa = data.nomor_wa.replace(/\D/g, '');
        if (cleanWa.startsWith('0')) cleanWa = '62' + cleanWa.substring(1);
        $('#det-wa').text(data.nomor_wa);
        $('#det-wa-link').attr('href', 'https://wa.me/' + cleanWa);
        
        $('#det-email').text(data.user_email);
        
        // Build Full Address
        var alamatFull = data.alamat_lengkap || '';
        var parts = [data.kelurahan, data.kecamatan, data.kabupaten, data.provinsi, data.kode_pos];
        var locationStr = parts.filter(Boolean).join(', ');
        
        if (locationStr) {
            alamatFull += (alamatFull ? ' — ' : '') + locationStr;
        }
        
        $('#det-alamat').text(alamatFull || '-');
        
        $('#det-saldo').text('Rp ' + fmt.format(data.saldo_saat_ini));
        $('#det-total-komisi').text('Rp ' + fmt.format(data.total_pendapatan_komisi));
        $('#det-bank').text(data.nama_bank || '-');
        $('#det-rek').text(data.no_rekening || '-');
        $('#det-an').text(data.atas_nama_rekening || '-');
        
        $('#det-ref').text(data.kode_referral || '-');
        $('#det-total-umkm').text(data.total_verifikasi_sukses);

        // Status Badge & Indicator
        var statusHtml = '';
        var indicatorColor = '#94a3b8'; // default gray

        if(data.status === 'aktif') {
            statusHtml = '<span class="dw-badge status-success">Aktif</span>';
            indicatorColor = '#10b981'; // green
        }
        else if(data.status === 'pending') {
            statusHtml = '<span class="dw-badge status-warning">Pending</span>';
            indicatorColor = '#f59e0b'; // orange
        }
        else {
            statusHtml = '<span class="dw-badge status-danger">Nonaktif</span>';
            indicatorColor = '#ef4444'; // red
        }
        
        $('#det-status-badge').html(statusHtml);
        $('#det-status-indicator').css('background-color', indicatorColor);

        openModal('#modal-detail');
    });

    // --- LOGIKA LOAD WILAYAH (AJAX) ---
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

    function loadRegion(action, parentKey, parentVal, target) {
        target.prop('disabled', true).html('<option>Loading...</option>');
        $.get(ajaxUrl, { action: action, [parentKey]: parentVal }, function(res) {
            if(res.success) {
                let opts = '<option value="">Pilih...</option>';
                $.each(res.data, function(i, v) {
                    opts += `<option value="${v.id}">${v.name}</option>`;
                });
                target.html(opts).prop('disabled', false);
            }
        });
    }

    // Init Provinsi
    loadRegion('dw_fetch_provinces', '', '', $('#api_provinsi_id'));

    $('#api_provinsi_id').change(function() {
        $('#provinsi_nama').val($(this).find('option:selected').text());
        loadRegion('dw_fetch_regencies', 'province_id', $(this).val(), $('#api_kabupaten_id'));
        $('#api_kecamatan_id').html('<option>Pilih Kecamatan</option>').prop('disabled', true);
        $('#api_kelurahan_id').html('<option>Pilih Kelurahan</option>').prop('disabled', true);
    });

    $('#api_kabupaten_id').change(function() {
        $('#kabupaten_nama').val($(this).find('option:selected').text());
        loadRegion('dw_fetch_districts', 'regency_id', $(this).val(), $('#api_kecamatan_id'));
        $('#api_kelurahan_id').html('<option>Pilih Kelurahan</option>').prop('disabled', true);
    });

    $('#api_kecamatan_id').change(function() {
        $('#kecamatan_nama').val($(this).find('option:selected').text());
        loadRegion('dw_fetch_villages', 'district_id', $(this).val(), $('#api_kelurahan_id'));
    });

    $('#api_kelurahan_id').change(function() {
        $('#kelurahan_nama').val($(this).find('option:selected').text());
    });
});
</script>