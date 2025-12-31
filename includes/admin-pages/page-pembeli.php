<?php
/**
 * File Name:   includes/admin-pages/page-pembeli.php
 * Description: Dashboard Manajemen Pembeli (Premium UI).
 * Database:    wp_users, dw_pembeli, dw_transaksi.
 * Version:     5.4 (UX Fixed: Link Ganti Password & Auto Copy & CRUD)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$t_pembeli   = $wpdb->prefix . 'dw_pembeli';
$t_transaksi = $wpdb->prefix . 'dw_transaksi';

// --- 1. HANDLE AJAX REQUESTS (SELF-CONTAINED & BUFFER CLEANED) ---
if ( isset($_GET['dw_ajax']) ) {
    // PENTING: Bersihkan buffer output agar response JSON bersih
    while ( ob_get_level() ) { ob_end_clean(); }
    header('Content-Type: application/json');

    $action = $_GET['dw_ajax'];
    
    // A. Fetch Transactions
    if ( $action == 'get_tx' && isset($_GET['uid']) ) {
        $uid = intval($_GET['uid']);
        $res = $wpdb->get_results($wpdb->prepare("SELECT kode_unik, total_transaksi, status_transaksi, created_at FROM $t_transaksi WHERE id_pembeli = %d ORDER BY created_at DESC LIMIT 10", $uid));
        
        $data = [];
        foreach($res as $r) {
            $data[] = [
                'kode'   => '#' . $r->kode_unik,
                'total'  => number_format($r->total_transaksi, 0, ',', '.'),
                'status' => str_replace('_', ' ', $r->status_transaksi),
                'date'   => date('d M Y, H:i', strtotime($r->created_at))
            ];
        }
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // B. Fetch Regions
    if ( in_array($action, ['get_prov','get_kab','get_kec','get_kel']) ) {
        $res = [];
        // Gunakan fungsi dari address-api.php jika tersedia
        // Pastikan parameter ID dikirim dengan benar
        $id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

        if($action == 'get_prov' && class_exists('DW_Address_API')) $res = DW_Address_API::get_provinces();
        elseif($action == 'get_kab' && class_exists('DW_Address_API')) $res = DW_Address_API::get_cities($id);
        elseif($action == 'get_kec' && class_exists('DW_Address_API')) $res = DW_Address_API::get_subdistricts($id);
        elseif($action == 'get_kel' && class_exists('DW_Address_API')) $res = DW_Address_API::get_villages($id);
        
        echo json_encode(['success' => true, 'data' => $res]);
        exit;
    }

    // C. GENERATE MANUAL LINK GANTI PASSWORD (Hardcore Mode)
    if ( $action == 'gen_reset_link' && isset($_GET['uid']) ) {
        if(!current_user_can('edit_users')) {
            echo json_encode(['success' => false, 'data' => 'Akses ditolak']);
            exit;
        }
        
        $u = get_userdata(intval($_GET['uid']));
        if($u) {
            $key = get_password_reset_key($u);
            if ( is_wp_error($key) ) {
                echo json_encode(['success' => false, 'data' => $key->get_error_message()]);
            } else {
                // Link standar WP untuk set password baru
                $link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($u->user_login), 'login');
                echo json_encode(['success' => true, 'data' => $link]);
            }
        } else {
            echo json_encode(['success' => false, 'data' => 'User tidak ditemukan']);
        }
        exit;
    }
    
    exit; // Stop execution
}

// --- 2. HANDLE FORM SUBMISSION (POST) ---
if ( isset($_POST['dw_action']) && $_POST['dw_action'] == 'save_buyer' && check_admin_referer('dw_save_buyer_nonce') ) {
    $uid = intval($_POST['user_id']);
    
    // Update Core WP User
    $u_data = ['ID' => $uid, 'display_name' => sanitize_text_field($_POST['nama_lengkap'])];
    if(isset($_POST['email'])) $u_data['user_email'] = sanitize_email($_POST['email']);
    wp_update_user($u_data);

    // Prepare Data dw_pembeli
    $data = [
        'id_user'           => $uid,
        'nama_lengkap'      => sanitize_text_field($_POST['nama_lengkap']),
        'nik'               => sanitize_text_field($_POST['nik']),
        'no_hp'             => sanitize_text_field($_POST['no_hp']),
        'alamat_lengkap'    => sanitize_textarea_field($_POST['alamat_lengkap']),
        'api_provinsi_id'   => sanitize_text_field($_POST['api_provinsi_id']),
        'provinsi'          => sanitize_text_field($_POST['provinsi_nama']),
        'api_kabupaten_id'  => sanitize_text_field($_POST['api_kabupaten_id']),
        'kabupaten'         => sanitize_text_field($_POST['kabupaten_nama']),
        'api_kecamatan_id'  => sanitize_text_field($_POST['api_kecamatan_id']),
        'kecamatan'         => sanitize_text_field($_POST['kecamatan_nama']),
        'api_kelurahan_id'  => sanitize_text_field($_POST['api_kelurahan_id']),
        'kelurahan'         => sanitize_text_field($_POST['kelurahan_nama']),
        'updated_at'        => current_time('mysql')
    ];

    $exist = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t_pembeli WHERE id_user = %d", $uid));
    if($exist) {
        $wpdb->update($t_pembeli, $data, ['id_user' => $uid]);
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($t_pembeli, $data);
    }

    update_user_meta($uid, 'billing_phone', $data['no_hp']);

    // Redirect to same page with success message
    $redirect_url = add_query_arg('msg', 'saved', admin_url('admin.php?page=dw-pembeli'));
    wp_redirect($redirect_url);
    exit;
}

// Handle Reset Password (Email Mode)
if ( isset($_GET['action']) && $_GET['action'] == 'reset_pass' && isset($_GET['uid']) ) {
    $u = get_userdata(intval($_GET['uid']));
    if($u) {
        retrieve_password($u->user_login);
        $redirect_url = remove_query_arg(['action','uid'], admin_url('admin.php?page=dw-pembeli'));
        $redirect_url = add_query_arg('msg', 'email_sent', $redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
}

// --- 3. VIEW DATA ---
$total_buyers = count_users()['avail_roles']['subscriber'] ?? 0;
if(isset(count_users()['avail_roles']['customer'])) $total_buyers += count_users()['avail_roles']['customer'];
$total_tx     = $wpdb->get_var("SELECT COUNT(id) FROM $t_transaksi WHERE status_transaksi = 'selesai'");
$total_spend  = $wpdb->get_var("SELECT SUM(total_transaksi) FROM $t_transaksi WHERE status_transaksi = 'selesai'");

$args = ['role__in' => ['subscriber', 'customer'], 'number' => 50, 'orderby' => 'registered', 'order' => 'DESC'];
$users = get_users($args);

$buyer_list = [];
foreach($users as $u) {
    $profil = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_pembeli WHERE id_user = %d", $u->ID));
    $stats  = $wpdb->get_row($wpdb->prepare("SELECT COUNT(id) as c, SUM(total_transaksi) as t FROM $t_transaksi WHERE id_pembeli=%d AND status_transaksi='selesai'", $u->ID));
    
    $loc = '-';
    if($profil && $profil->kabupaten) $loc = $profil->kabupaten . ', ' . $profil->provinsi;

    $buyer_list[] = (object) [
        'id'       => $u->ID,
        'email'    => $u->user_email,
        'name'     => $profil ? $profil->nama_lengkap : $u->display_name,
        'phone'    => $profil ? $profil->no_hp : get_user_meta($u->ID, 'billing_phone', true),
        'nik'      => $profil ? $profil->nik : '',
        'date'     => $u->user_registered,
        'location' => $loc,
        'address'  => $profil ? $profil->alamat_lengkap : '',
        'orders'   => (int) ($stats->c ?? 0),
        'spent'    => (float) ($stats->t ?? 0),
        'avatar'   => get_avatar_url($u->ID),
        'raw_prov' => $profil ? $profil->api_provinsi_id : '',
        'raw_kab'  => $profil ? $profil->api_kabupaten_id : '',
        'raw_kec'  => $profil ? $profil->api_kecamatan_id : '',
        'raw_kel'  => $profil ? $profil->api_kelurahan_id : '',
        'nm_prov'  => $profil ? $profil->provinsi : '',
        'nm_kab'   => $profil ? $profil->kabupaten : '',
        'nm_kec'   => $profil ? $profil->kecamatan : '',
        'nm_kel'   => $profil ? $profil->kelurahan : '',
    ];
}
?>

<!-- STYLE & UI -->
<style>
    :root { --dw-p: #2563eb; --dw-g: #10b981; --dw-o: #f59e0b; --dw-r: #ef4444; --dw-txt: #1e293b; --dw-gry: #64748b; --dw-bg: #f8fafc; --dw-bd: #e2e8f0; }
    .dw-wrap { max-width: 1200px; margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--dw-txt); }
    
    .dw-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .dw-head h1 { font-size: 24px; font-weight: 800; margin: 0; color: var(--dw-txt); }
    .dw-head p { margin: 5px 0 0; color: var(--dw-gry); font-size: 14px; }
    .dw-search { padding: 10px 15px; border: 1px solid var(--dw-bd); border-radius: 8px; width: 250px; }

    .dw-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .dw-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--dw-bd); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .dw-card h3 { margin: 0 0 5px; font-size: 11px; text-transform: uppercase; color: var(--dw-gry); font-weight: 700; }
    .dw-card .val { font-size: 24px; font-weight: 700; }
    .bl-blue { border-left: 4px solid var(--dw-p); } .bl-green { border-left: 4px solid var(--dw-g); } .bl-orange { border-left: 4px solid var(--dw-o); }

    .dw-tbl-box { background: #fff; border-radius: 12px; border: 1px solid var(--dw-bd); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .dw-tbl { width: 100%; border-collapse: collapse; }
    .dw-tbl th { background: #f8fafc; padding: 15px 20px; text-align: left; font-size: 12px; font-weight: 600; color: var(--dw-gry); text-transform: uppercase; border-bottom: 1px solid var(--dw-bd); }
    .dw-tbl td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 14px; }
    .dw-tbl tr:hover { background: #fcfcfc; }
    
    .u-flex { display: flex; align-items: center; gap: 12px; }
    .u-ava { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; background: #eee; }
    .btn-small { padding: 6px 12px; font-size: 12px; border-radius: 6px; cursor: pointer; border: 1px solid var(--dw-bd); background: #fff; color: var(--dw-txt); transition: 0.2s; }
    .btn-small:hover { background: var(--dw-bg); border-color: #cbd5e1; }

    /* Modal */
    .dw-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 9999; backdrop-filter: blur(2px); align-items: center; justify-content: center; }
    .dw-m-content { background: #fff; width: 650px; max-width: 95%; max-height: 90vh; border-radius: 16px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: slideUp 0.3s ease; }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .dw-m-head { padding: 20px 24px; border-bottom: 1px solid var(--dw-bd); display: flex; justify-content: space-between; align-items: center; background: #fff; }
    .dw-m-head h2 { margin: 0; font-size: 18px; font-weight: 700; }
    
    .dw-m-body { padding: 24px; overflow-y: auto; flex: 1; background: #fff; }
    .dw-m-foot { padding: 16px 24px; background: #f8fafc; border-top: 1px solid var(--dw-bd); display: flex; justify-content: flex-end; gap: 10px; }

    .f-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
    .f-group label { display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: var(--dw-gry); }
    .f-input, .f-select, .f-area { width: 100%; padding: 10px; border: 1px solid var(--dw-bd); border-radius: 8px; font-size: 14px; box-sizing: border-box; }
    .f-input:focus { outline: none; border-color: var(--dw-p); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
    
    /* Tabs */
    .dw-tabs { display: flex; background: #f8fafc; border-bottom: 1px solid var(--dw-bd); padding: 0 24px; }
    .dw-tab { background: none; border: none; padding: 15px 0; margin-right: 25px; font-weight: 600; color: var(--dw-gry); cursor: pointer; border-bottom: 2px solid transparent; }
    .dw-tab.active { color: var(--dw-p); border-bottom-color: var(--dw-p); }
    .tab-pane { display: none; } .tab-pane.active { display: block; }

    .btn-p { background: var(--dw-p); color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    .btn-p:hover { background: #1d4ed8; }
    .btn-s { background: #fff; border: 1px solid var(--dw-bd); color: var(--dw-gry); padding: 10px 16px; border-radius: 8px; cursor: pointer; }
    .btn-s:hover { background: var(--dw-bg); color: var(--dw-txt); }
    .btn-w { background: #fff; border: 1px solid #f59e0b; color: #d97706; padding: 10px 16px; border-radius: 8px; cursor: pointer; }
    .btn-w:hover { background: #fffbeb; }
    
    .notice-success { margin: 0 0 20px 0 !important; border-left-color: var(--dw-g) !important; }
</style>

<div class="wrap dw-wrap">
    
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
        <div class="notice notice-success is-dismissible"><p><strong>Berhasil!</strong> Data pembeli telah disimpan.</p></div>
    <?php endif; ?>
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'email_sent'): ?>
        <div class="notice notice-success is-dismissible"><p><strong>Berhasil!</strong> Email reset password telah dikirim ke user.</p></div>
    <?php endif; ?>

    <div class="dw-head">
        <div>
            <h1>Data Wisatawan</h1>
            <p>Kelola profil, alamat, dan riwayat transaksi pembeli.</p>
        </div>
        <input type="text" id="liveSearch" class="dw-search" placeholder="Cari nama atau email...">
    </div>

    <div class="dw-stats">
        <div class="dw-card bl-blue"><h3>Total Wisatawan</h3><span class="val"><?php echo number_format($total_buyers); ?></span></div>
        <div class="dw-card bl-green"><h3>Transaksi Sukses</h3><span class="val"><?php echo number_format($total_tx); ?></span></div>
        <div class="dw-card bl-orange"><h3>Total Belanja</h3><span class="val">Rp <?php echo number_format($total_spend, 0, ',', '.'); ?></span></div>
    </div>

    <div class="dw-tbl-box">
        <table class="dw-tbl" id="mainTable">
            <thead>
                <tr>
                    <th>Pengguna</th>
                    <th>Kontak</th>
                    <th>Domisili</th>
                    <th>Statistik</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($buyer_list)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px; color:#aaa;">Belum ada data.</td></tr>
                <?php else: foreach($buyer_list as $b): 
                    $json = htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td>
                        <div class="u-flex">
                            <img src="<?php echo $b->avatar; ?>" class="u-ava">
                            <div>
                                <div style="font-weight:600;"><?php echo esc_html($b->name); ?></div>
                                <div style="font-size:11px; color:#888;">Join: <?php echo date('d M Y', strtotime($b->date)); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?php echo esc_html($b->email); ?></div>
                        <small style="color:#888;"><?php echo esc_html($b->phone); ?></small>
                    </td>
                    <td><?php echo esc_html($b->location); ?></td>
                    <td>
                        <div style="font-weight:600;"><?php echo $b->orders; ?> Trx</div>
                        <small style="color:var(--dw-g);">Rp <?php echo number_format($b->spent, 0, ',', '.'); ?></small>
                    </td>
                    <td>
                        <button class="btn-small" onclick='openModal(<?php echo $json; ?>)'><span class="dashicons dashicons-edit"></span> Detail</button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- MODAL FORM -->
<div id="dwModal" class="dw-modal">
    <form method="post" class="dw-m-content">
        <?php wp_nonce_field('dw_save_buyer_nonce'); ?>
        <input type="hidden" name="dw_action" value="save_buyer">
        <input type="hidden" name="user_id" id="f_uid">

        <div class="dw-m-head">
            <h2 id="mTitle">Edit Pembeli</h2>
            <span class="dashicons dashicons-no-alt" style="cursor:pointer; color:#888;" onclick="closeModal()"></span>
        </div>

        <div class="dw-tabs">
            <div class="dw-tab active" onclick="tab('profile')">Profil & Alamat</div>
            <div class="dw-tab" onclick="tab('history')">Riwayat</div>
        </div>

        <div class="dw-m-body">
            <!-- Tab Profile -->
            <div id="t-profile" class="tab-pane active">
                <div class="f-grid">
                    <div class="f-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="f_name" class="f-input" required>
                    </div>
                    <div class="f-group">
                        <label>NIK (Identitas)</label>
                        <input type="text" name="nik" id="f_nik" class="f-input" maxlength="16">
                    </div>
                </div>
                <div class="f-grid">
                    <div class="f-group">
                        <label>Email Login</label>
                        <input type="email" name="email" id="f_email" class="f-input" required>
                    </div>
                    <div class="f-group">
                        <label>No. HP / WhatsApp</label>
                        <input type="text" name="no_hp" id="f_hp" class="f-input">
                    </div>
                </div>

                <hr style="border:0; border-top:1px dashed #ddd; margin:15px 0;">
                <h4 style="margin:0 0 10px; font-size:13px; color:var(--dw-p); text-transform:uppercase;">Alamat Pengiriman (Waterfall)</h4>

                <input type="hidden" name="provinsi_nama" id="t_prov">
                <input type="hidden" name="kabupaten_nama" id="t_kab">
                <input type="hidden" name="kecamatan_nama" id="t_kec">
                <input type="hidden" name="kelurahan_nama" id="t_kel">

                <div class="f-grid">
                    <div class="f-group">
                        <label>Provinsi</label>
                        <select name="api_provinsi_id" id="s_prov" class="f-select"><option value="">Pilih...</option></select>
                    </div>
                    <div class="f-group">
                        <label>Kabupaten/Kota</label>
                        <select name="api_kabupaten_id" id="s_kab" class="f-select" disabled></select>
                    </div>
                </div>
                <div class="f-grid">
                    <div class="f-group">
                        <label>Kecamatan</label>
                        <select name="api_kecamatan_id" id="s_kec" class="f-select" disabled></select>
                    </div>
                    <div class="f-group">
                        <label>Kelurahan/Desa</label>
                        <select name="api_kelurahan_id" id="s_kel" class="f-select" disabled></select>
                    </div>
                </div>
                <div class="f-group">
                    <label>Alamat Lengkap (Jalan, RT/RW)</label>
                    <textarea name="alamat_lengkap" id="f_alamat" class="f-area" rows="2"></textarea>
                </div>
            </div>

            <!-- Tab History -->
            <div id="t-history" class="tab-pane">
                <ul id="tx-list" style="list-style:none; padding:0;">
                    <li style="text-align:center; padding:30px; color:#999;">Memuat data...</li>
                </ul>
            </div>
        </div>

        <div class="dw-m-foot">
            <div style="display:flex; gap:10px; align-items:center;">
                <button type="button" class="btn-s" id="btnReset" onclick="doReset()" title="Kirim Email Ganti Password ke User">
                    <span class="dashicons dashicons-email-alt" style="font-size:14px;"></span> Via Email
                </button>
                <button type="button" class="btn-w" id="btnLinkReset" onclick="getManualLink()" title="Copy Link Ganti Password Langsung (Untuk dikirim via WA)">
                    <span class="dashicons dashicons-admin-links" style="font-size:14px;"></span> Copy Link Ganti Pass
                </button>
            </div>
            <div style="flex:1;"></div>
            <button type="button" class="btn-s" onclick="closeModal()">Batal</button>
            <button type="submit" class="btn-p">Simpan Perubahan</button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Gunakan URL saat ini + parameter ajax
    // Pastikan tidak menumpuk query string yang sudah ada jika page direfresh
    const selfAjax = window.location.pathname + window.location.search + '&dw_ajax=';

    $('#liveSearch').on('keyup', function() {
        let val = $(this).val().toLowerCase();
        $('#mainTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1)
        });
    });

    window.openModal = function(data) {
        $('#dwModal').fadeIn(200).css('display', 'flex');
        tab('profile');
        
        $('#f_uid').val(data.id);
        $('#mTitle').text('Edit: ' + data.name);
        $('#f_name').val(data.name);
        $('#f_nik').val(data.nik);
        $('#f_email').val(data.email);
        $('#f_hp').val(data.phone);
        $('#f_alamat').val(data.address);

        loadRegion('get_prov', null, $('#s_prov'), data.raw_prov);
        $('#t_prov').val(data.nm_prov);

        if(data.raw_prov) { loadRegion('get_kab', data.raw_prov, $('#s_kab'), data.raw_kab); $('#t_kab').val(data.nm_kab); }
        else { $('#s_kab').empty().prop('disabled', true); }

        if(data.raw_kab) { loadRegion('get_kec', data.raw_kab, $('#s_kec'), data.raw_kec); $('#t_kec').val(data.nm_kec); }
        else { $('#s_kec').empty().prop('disabled', true); }

        if(data.raw_kec) { loadRegion('get_kel', data.raw_kec, $('#s_kel'), data.raw_kel); $('#t_kel').val(data.nm_kel); }
        else { $('#s_kel').empty().prop('disabled', true); }

        fetchTx(data.id);
    }

    window.closeModal = function() { $('#dwModal').fadeOut(200); }

    window.tab = function(id) {
        $('.dw-tab').removeClass('active');
        $('.tab-pane').removeClass('active');
        $(`.dw-tab[onclick="tab('${id}')"]`).addClass('active');
        $(`#t-${id}`).addClass('active');
        
        if(id === 'history') $('.dw-m-foot').hide(); else $('.dw-m-foot').css('display','flex');
    }

    function loadRegion(act, id, el, selId) {
        el.prop('disabled', true).html('<option>Loading...</option>');
        // Use selfAjax
        let apiData = { };
        if(id) {
            apiData.id = id;
        }

        $.get(selfAjax + act, apiData, function(res) {
            el.empty().append('<option value="">-- Pilih --</option>');
            if(res.success) {
                res.data.forEach(item => {
                    let isSel = (item.id == selId) ? 'selected' : '';
                    el.append(`<option value="${item.id}" data-nm="${item.name}" ${isSel}>${item.name}</option>`);
                });
                el.prop('disabled', false);
            } else {
                console.error("Failed to load region:", res);
                el.html('<option>Gagal memuat</option>');
            }
        });
    }

    $('#s_prov').change(function(){
        $('#t_prov').val($(this).find(':selected').data('nm'));
        loadRegion('get_kab', $(this).val(), $('#s_kab'), null);
        $('#s_kec, #s_kel').empty().prop('disabled', true);
    });
    $('#s_kab').change(function(){
        $('#t_kab').val($(this).find(':selected').data('nm'));
        loadRegion('get_kec', $(this).val(), $('#s_kec'), null);
        $('#s_kel').empty().prop('disabled', true);
    });
    $('#s_kec').change(function(){
        $('#t_kec').val($(this).find(':selected').data('nm'));
        loadRegion('get_kel', $(this).val(), $('#s_kel'), null);
    });
    $('#s_kel').change(function(){
        $('#t_kel').val($(this).find(':selected').data('nm'));
    });

    function fetchTx(uid) {
        $('#tx-list').html('<li style="padding:20px; text-align:center;">Loading...</li>');
        $.get(selfAjax + 'get_tx&uid=' + uid, function(res) {
            if(res.success && res.data.length > 0) {
                let h = '';
                res.data.forEach(t => {
                    let c = t.status.includes('selesai') ? '#10b981' : '#f59e0b';
                    h += `<li style="padding:10px; border-bottom:1px solid #eee; display:flex; justify-content:space-between;">
                        <div><strong>${t.kode}</strong><br><small style="color:#888">${t.date}</small></div>
                        <div style="text-align:right;"><strong>Rp ${t.total}</strong><br><span style="color:${c}; font-size:11px; font-weight:bold; text-transform:uppercase;">${t.status}</span></div>
                    </li>`;
                });
                $('#tx-list').html(h);
            } else {
                $('#tx-list').html('<li style="padding:30px; text-align:center; color:#aaa;">Belum ada transaksi.</li>');
            }
        });
    }

    // --- RESET ACTIONS ---
    window.doReset = function() {
        if(confirm('Kirim email ganti password ke user?')) {
            // Bersihkan query string sebelumnya agar tidak dobel
            let currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('action', 'reset_pass');
            currentUrl.searchParams.set('uid', $('#f_uid').val());
            window.location.href = currentUrl.toString();
        }
    }

    window.getManualLink = function() {
        if(!confirm('GENERATE LINK GANTI PASSWORD?\n\nPeringatan: Link sebelumnya akan hangus. Link ini memaksa user untuk set password baru.')) return;
        
        let uid = $('#f_uid').val();
        $.get(selfAjax + 'gen_reset_link&uid=' + uid, function(res) {
            if(res.success) {
                let link = res.data;
                // Auto Copy UX dengan Fallback
                if(navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link).then(() => {
                        alert('Link Ganti Password BERHASIL DISALIN ke clipboard!');
                    }).catch(err => {
                        // Fallback jika clipboard API gagal (misal tidak HTTPS)
                        prompt("Gagal auto-copy. Silakan salin link di bawah ini:", link);
                    });
                } else {
                    // Fallback untuk browser lama
                    prompt("Link Ganti Password (Salin Manual):", link);
                }
            } else {
                alert('Gagal: ' + res.data);
            }
        });
    }

    // Init load
    loadRegion('get_prov', null, $('#s_prov'), null);
});
</script>