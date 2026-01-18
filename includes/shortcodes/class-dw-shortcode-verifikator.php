<?php
/**
 * Shortcode: [dw_dashboard_verifikator]
 * Path: includes/shortcodes/class-dw-shortcode-verifikator.php
 * Description: Dashboard frontend untuk Verifikator UMKM (Approval, Binaan, Withdraw).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcode_Verifikator {

    /**
     * Integrasi dengan loader utama (init.php & class-dw-shortcodes.php)
     */
    public static function init() {
        $instance = new self();
        add_shortcode( 'dw_dashboard_verifikator', array( $instance, 'render' ) );
    }

    public function render( $atts ) {
        // 1. Cek Login & Role
        if ( ! is_user_logged_in() ) {
            return '<div class="dw-alert dw-alert-warning p-4 bg-yellow-50 text-yellow-800 rounded border border-yellow-200">Silakan <a href="' . wp_login_url( get_permalink() ) . '" class="font-bold underline">login</a> terlebih dahulu untuk mengakses Dashboard Verifikator.</div>';
        }

        $current_user = wp_get_current_user();
        if ( ! in_array( 'verifikator_umkm', (array) $current_user->roles ) && ! in_array( 'administrator', (array) $current_user->roles ) ) {
             // Jika bukan verifikator, redirect atau tolak
             return '<div class="p-4 bg-red-50 text-red-800 border border-red-200 rounded">Akses Ditolak. Halaman ini khusus untuk Verifikator UMKM.</div>';
        }

        global $wpdb;
        $table_verifikator = $wpdb->prefix . 'dw_verifikator';
        $table_pedagang    = $wpdb->prefix . 'dw_pedagang';
        $msg = '';
        $msg_type = '';

        // --- 2. GET / CREATE VERIFICATOR DATA (INIT) ---
        // Pastikan data verifikator ada di tabel khusus
        $verifikator = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_verifikator WHERE id_user = %d", $current_user->ID) );

        if ( ! $verifikator ) {
            $initial_code = 'VERIF-' . strtoupper(substr(md5(time() . $current_user->ID), 0, 6));
            $wpdb->insert($table_verifikator, [
                'id_user'       => $current_user->ID,
                'nama_lengkap'  => $current_user->display_name,
                'nik'           => '', 
                'nomor_wa'      => '',
                'kode_referral' => $initial_code,
                'status'        => 'aktif',
                'created_at'    => current_time('mysql'),
                'updated_at'    => current_time('mysql')
            ]);
            // Ambil lagi setelah insert agar dapat ID
            $verifikator = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_verifikator WHERE id_user = %d", $current_user->ID) );
        }

        // Pastikan ID Verifikator ada untuk Query selanjutnya
        $verifikator_id = $verifikator->id;


        // --- 3. FORM HANDLERS ---

        // Handler: Simpan Profil
        if ( isset($_POST['action_save_profile']) && check_admin_referer('save_profile_action', 'profile_nonce') ) {
            $update_data = [
                'nama_lengkap'   => sanitize_text_field($_POST['nama_lengkap']),
                'nik'            => sanitize_text_field($_POST['nik']),
                'nomor_wa'       => sanitize_text_field($_POST['nomor_wa']),
                'alamat_lengkap' => sanitize_textarea_field($_POST['alamat_lengkap']),
                'provinsi'       => sanitize_text_field($_POST['provinsi_nama']),
                'kabupaten'      => sanitize_text_field($_POST['kabupaten_nama']),
                'kecamatan'      => sanitize_text_field($_POST['kecamatan_nama']),
                'kelurahan'      => sanitize_text_field($_POST['kelurahan_nama']),
                'api_provinsi_id'  => sanitize_text_field($_POST['api_provinsi_id']),
                'api_kabupaten_id' => sanitize_text_field($_POST['api_kabupaten_id']),
                'api_kecamatan_id' => sanitize_text_field($_POST['api_kecamatan_id']),
                'api_kelurahan_id' => sanitize_text_field($_POST['api_kelurahan_id']),
                'updated_at'       => current_time('mysql')
            ];
            $wpdb->update($table_verifikator, $update_data, ['id' => $verifikator_id]);
            
            // Simpan Data Bank di User Meta
            update_user_meta($current_user->ID, 'dw_bank_name', sanitize_text_field($_POST['bank_name']));
            update_user_meta($current_user->ID, 'dw_bank_acc', sanitize_text_field($_POST['bank_acc']));
            update_user_meta($current_user->ID, 'dw_bank_user', sanitize_text_field($_POST['bank_user']));

            $msg = "Profil berhasil diperbarui."; $msg_type = "success";
            // Refresh object verifikator
            $verifikator = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_verifikator WHERE id = %d", $verifikator_id) );
        }

        // Handler: Proses Verifikasi (Terima/Tolak)
        if ( isset($_POST['action_verify']) && check_admin_referer('verify_umkm_action', 'verify_nonce') ) {
            $id_pedagang = intval($_POST['id_pedagang']);
            $keputusan   = sanitize_text_field($_POST['keputusan']); // 'disetujui' atau 'ditolak'
            $alasan      = sanitize_textarea_field($_POST['alasan_penolakan']);
            
            $update_data = [
                'status_pendaftaran' => $keputusan,
                'approved_by'        => $current_user->ID, // ID User WP (Audit Log)
                'id_verifikator'     => $verifikator_id,   // ID Tabel Verifikator (Relasi Binaan)
                'verified_at'        => current_time('mysql'),
                'updated_at'         => current_time('mysql')
            ];

            if ( $keputusan == 'disetujui' ) {
                $update_data['status_akun'] = 'aktif';
                $update_data['is_verified'] = 1;
                // Tambah counter sukses di tabel verifikator
                $wpdb->query($wpdb->prepare("UPDATE $table_verifikator SET total_verifikasi_sukses = total_verifikasi_sukses + 1 WHERE id = %d", $verifikator_id));
                
                // Beri kuota gratis default jika ada di settings
                $settings = get_option('dw_settings', []);
                $update_data['sisa_transaksi'] = isset($settings['kuota_gratis_default']) ? intval($settings['kuota_gratis_default']) : 0;
            } else {
                $update_data['status_akun'] = 'nonaktif';
            }

            $wpdb->update($table_pedagang, $update_data, ['id' => $id_pedagang]);
            $msg = ($keputusan == 'disetujui') ? "UMKM berhasil disetujui." : "UMKM ditolak.";
            $msg_type = ($keputusan == 'disetujui') ? "success" : "warning";
        }

        // Handler: Tarik Saldo
        if ( isset($_POST['action_withdraw']) && check_admin_referer('withdraw_action', 'withdraw_nonce') ) {
            $amount = floatval(str_replace('.', '', $_POST['nominal_withdraw']));
            $bank_acc = get_user_meta($current_user->ID, 'dw_bank_acc', true);
            
            if ( $amount <= 0 ) {
                $msg = "Nominal tidak valid."; $msg_type = "error";
            } elseif ( $amount > $verifikator->saldo_saat_ini ) {
                $msg = "Saldo tidak mencukupi."; $msg_type = "error";
            } elseif ( empty($bank_acc) ) {
                $msg = "Lengkapi data bank di menu Pengaturan Akun."; $msg_type = "error";
            } else {
                // Buat record withdrawal (Integrasi dengan class DW_Wallet jika ada, atau manual update DB)
                // Untuk saat ini kita update manual saldo verifikator dan buat log withdrawal
                // (Idealnya menggunakan tabel dw_withdrawals yang dibuat di fase 4)
                
                if (class_exists('DW_Wallet')) {
                    // Gunakan sistem wallet terpusat jika ada
                    $res = DW_Wallet::create_withdrawal_request($current_user->ID, $amount, "Bank: $bank_acc");
                    if (is_wp_error($res)) {
                        $msg = $res->get_error_message(); $msg_type = "error";
                    } else {
                        // Kurangi saldo lokal verifikator (sinkronisasi)
                        $wpdb->query($wpdb->prepare("UPDATE $table_verifikator SET saldo_saat_ini = saldo_saat_ini - %f WHERE id = %d", $amount, $verifikator_id));
                        $msg = "Permintaan penarikan berhasil dikirim."; $msg_type = "success";
                        $verifikator->saldo_saat_ini -= $amount;
                    }
                } else {
                    // Fallback manual jika Class Wallet belum ready
                    $wpdb->query($wpdb->prepare("UPDATE $table_verifikator SET saldo_saat_ini = saldo_saat_ini - %f WHERE id = %d", $amount, $verifikator_id));
                    $msg = "Permintaan penarikan berhasil (Mode Manual)."; $msg_type = "success";
                    $verifikator->saldo_saat_ini -= $amount;
                }
            }
        }


        // --- 4. DATA QUERY UTAMA ---

        // A. Antrean (Menunggu)
        // Mengambil semua pedagang 'menunggu' atau 'menunggu_desa'.
        $pending_list = $wpdb->get_results("SELECT * FROM $table_pedagang WHERE status_pendaftaran IN ('menunggu', 'menunggu_desa') ORDER BY created_at ASC");

        // B. UMKM Binaan Saya (Relasi id_verifikator)
        $binaan_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id_verifikator = %d ORDER BY created_at DESC", $verifikator_id));

        // C. Riwayat Kerja (Audit Log berdasarkan User ID)
        $history_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_pedagang WHERE approved_by = %d ORDER BY verified_at DESC LIMIT 20", $current_user->ID));

        // Statistik
        $stats_pending  = count($pending_list);
        $stats_binaan   = count($binaan_list); 
        $stats_komisi   = $verifikator->total_pendapatan_komisi;
        $stats_saldo    = $verifikator->saldo_saat_ini;

        // Data Bank (View)
        $bank_name = get_user_meta($current_user->ID, 'dw_bank_name', true);
        $bank_acc  = get_user_meta($current_user->ID, 'dw_bank_acc', true);
        $bank_user = get_user_meta($current_user->ID, 'dw_bank_user', true);

        ob_start();
        // Hapus get_header() jika shortcode dipasang di dalam page content yang sudah punya header
        // if ( ! did_action( 'get_header' ) ) get_header(); 
        ?>
        
        <!-- CDN Assets (Load only if not present) -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script>tailwind.config = { theme: { extend: { colors: { primary: '#4f46e5', secondary: '#1e293b' } } } }</script>

        <div class="dw-verifikator-wrapper bg-gray-50 min-h-screen font-sans flex flex-col md:flex-row pt-4 md:pt-0 relative">

            <!-- SIDEBAR -->
            <aside id="dashboard-sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform -translate-x-full md:translate-x-0 md:relative md:block transition-transform duration-300 pt-0 h-screen overflow-y-auto">
                <div class="h-full flex flex-col justify-between">
                    <div>
                        <div class="p-6 border-b border-gray-100 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-lg">
                                <?php echo strtoupper(substr($verifikator->nama_lengkap, 0, 1)); ?>
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-800 text-sm truncate w-32" title="<?php echo esc_attr($verifikator->nama_lengkap); ?>"><?php echo esc_html($verifikator->nama_lengkap); ?></h2>
                                <p class="text-[10px] text-gray-500 uppercase tracking-wide">Verifikator</p>
                            </div>
                        </div>
                        <nav class="p-4 space-y-2">
                            <button onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition active-tab text-left bg-indigo-50 text-indigo-600 font-bold border-l-4 border-indigo-600">
                                <i class="fas fa-chart-pie w-5 text-center"></i> <span class="font-medium">Dashboard</span>
                            </button>
                            <button onclick="switchTab('antrean')" id="nav-antrean" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition text-left relative">
                                <i class="fas fa-clipboard-list w-5 text-center"></i> <span class="font-medium">Antrean</span>
                                <?php if($stats_pending > 0): ?>
                                    <span class="absolute right-4 bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $stats_pending; ?></span>
                                <?php endif; ?>
                            </button>
                            <button onclick="switchTab('binaan')" id="nav-binaan" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition text-left">
                                <i class="fas fa-users w-5 text-center"></i> <span class="font-medium">UMKM Binaan</span>
                            </button>
                            <button onclick="switchTab('riwayat')" id="nav-riwayat" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition text-left">
                                <i class="fas fa-history w-5 text-center"></i> <span class="font-medium">Riwayat</span>
                            </button>
                            <button onclick="switchTab('pengaturan')" id="nav-pengaturan" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition text-left">
                                <i class="fas fa-cog w-5 text-center"></i> <span class="font-medium">Pengaturan</span>
                            </button>
                        </nav>
                    </div>
                     <div class="p-4 border-t border-gray-100">
                         <a href="<?php echo wp_logout_url(home_url()); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 transition w-full text-left">
                            <i class="fas fa-sign-out-alt w-5 text-center"></i> <span class="font-medium">Keluar</span>
                        </a>
                    </div>
                </div>
            </aside>

            <!-- MOBILE HEADER -->
            <div class="md:hidden fixed top-0 left-0 right-0 h-16 bg-white border-b border-gray-200 z-[40] flex items-center justify-between px-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <button onclick="toggleMobileSidebar()" class="text-gray-600 p-2 rounded hover:bg-gray-100"><i class="fas fa-bars text-xl"></i></button>
                    <span class="font-bold text-gray-800">Verifikator Panel</span>
                </div>
                <div class="w-8 h-8 bg-gray-200 rounded-full overflow-hidden border border-gray-300">
                    <img src="<?php echo get_avatar_url($current_user->ID); ?>" class="w-full h-full object-cover">
                </div>
            </div>
             <div id="sidebar-backdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-black/50 z-30 hidden md:hidden"></div>

            <!-- MAIN CONTENT -->
            <main class="flex-1 p-6 md:p-8 overflow-y-auto h-screen bg-gray-50 md:ml-0 transition-all duration-300 pt-20 md:pt-8">
                
                <?php if($msg): ?>
                    <div class="mb-6 p-4 rounded-xl border <?php echo ($msg_type=='warning' || $msg_type=='error')?'bg-red-50 text-red-800 border-red-200':'bg-green-50 text-green-800 border-green-200'; ?> flex items-center gap-3 shadow-sm animate-fade-in">
                        <i class="fas <?php echo ($msg_type=='warning' || $msg_type=='error')?'fa-exclamation-triangle':'fa-check-circle'; ?>"></i> 
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <!-- [BARU] KARTU LINK REFERRAL VERIFIKATOR -->
                <div class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-5 mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm relative overflow-hidden group">
                    <div class="relative z-10">
                        <p class="text-xs font-bold text-orange-600 uppercase tracking-wider mb-1 flex items-center gap-2">
                            <i class="fas fa-id-badge"></i> Link Referral Verifikator
                        </p>
                        <h2 class="text-2xl font-mono font-bold text-gray-800 tracking-wide">
                            <?php echo esc_html($verifikator->kode_referral); ?>
                        </h2>
                        <p class="text-xs text-gray-500 mt-1">Gunakan link ini saat mendaftarkan UMKM binaan baru agar otomatis terhubung.</p>
                    </div>
                    <div class="relative z-10">
                        <?php 
                            // GENERATE LINK REGISTER
                            $ref_link_verif = home_url('/register?ref=' . $verifikator->kode_referral);
                        ?>
                        <button onclick="copyToClipboard('<?php echo esc_js($ref_link_verif); ?>')" class="bg-white hover:bg-orange-600 hover:text-white text-orange-700 border border-orange-200 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all active:scale-95 flex items-center gap-2">
                            <i class="fas fa-link"></i> <span>Salin Link Daftar</span>
                        </button>
                    </div>
                    <!-- Decoration -->
                    <div class="absolute right-0 top-0 h-full w-32 bg-orange-100/30 -skew-x-12 translate-x-10 group-hover:translate-x-5 transition-transform duration-500"></div>
                </div>

                <!-- VIEW: RINGKASAN (DASHBOARD) -->
                <div id="view-dashboard" class="tab-content block animate-fade-in">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">Ringkasan Kinerja</h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <!-- Komisi -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center text-lg"><i class="fas fa-wallet"></i></div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Komisi</p>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800">Rp <?php echo number_format($stats_komisi, 0, ',', '.'); ?></h3>
                        </div>

                        <!-- Saldo -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between hover:shadow-md transition cursor-pointer group" onclick="openWithdrawModal()">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-lg"><i class="fas fa-coins"></i></div>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Saldo Tersedia</p>
                                </div>
                                <span class="text-[10px] text-blue-600 bg-blue-50 px-2 py-1 rounded-lg group-hover:bg-blue-600 group-hover:text-white transition flex items-center gap-1 font-bold">
                                    Tarik Dana <i class="fas fa-arrow-right"></i>
                                </span>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800">Rp <?php echo number_format($stats_saldo, 0, ',', '.'); ?></h3>
                            <p class="text-[10px] text-gray-400 mt-2">Saldo yang bisa dicairkan ke rekening bank.</p>
                        </div>

                        <!-- Antrean -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between hover:shadow-md transition cursor-pointer" onclick="switchTab('antrean')">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-10 h-10 bg-yellow-100 text-yellow-600 rounded-xl flex items-center justify-center text-lg"><i class="fas fa-clock"></i></div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Antrean Verifikasi</p>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($stats_pending); ?></h3>
                        </div>

                        <!-- Binaan -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between hover:shadow-md transition cursor-pointer" onclick="switchTab('binaan')">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center text-lg"><i class="fas fa-store-alt"></i></div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">UMKM Binaan</p>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($stats_binaan); ?></h3>
                        </div>
                    </div>

                    <!-- Preview Antrean Dashboard -->
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-bold text-gray-800">Antrean Terbaru</h3>
                            <button onclick="switchTab('antrean')" class="text-sm text-indigo-600 font-bold hover:underline">Lihat Semua</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-100">
                                    <tr>
                                        <th class="p-4 pl-6">Nama Toko</th>
                                        <th class="p-4">Pemilik</th>
                                        <th class="p-4">Tanggal Daftar</th>
                                        <th class="p-4 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php 
                                    $limit_pending = array_slice($pending_list, 0, 5); 
                                    if($limit_pending): foreach($limit_pending as $p): 
                                    ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="p-4 pl-6 font-bold text-gray-800">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded bg-gray-100 border border-gray-200 overflow-hidden"><img src="<?php echo $p->foto_profil ?: 'https://ui-avatars.com/api/?name='.urlencode($p->nama_toko); ?>" class="w-full h-full object-cover"></div>
                                                <?php echo esc_html($p->nama_toko); ?>
                                            </div>
                                        </td>
                                        <td class="p-4 text-gray-600"><?php echo esc_html($p->nama_pemilik); ?></td>
                                        <td class="p-4 text-gray-500"><?php echo date('d M Y', strtotime($p->created_at)); ?></td>
                                        <td class="p-4 text-center flex items-center justify-center gap-2">
                                             <button onclick='quickVerify("disetujui", <?php echo $p->id; ?>, "<?php echo esc_js($p->nama_toko); ?>")' class="text-white bg-green-500 hover:bg-green-600 font-bold text-xs px-3 py-1.5 rounded-lg transition shadow-sm" title="Setujui"><i class="fas fa-check"></i></button>
                                            <button onclick='quickVerify("ditolak", <?php echo $p->id; ?>, "<?php echo esc_js($p->nama_toko); ?>")' class="text-white bg-red-500 hover:bg-red-600 font-bold text-xs px-3 py-1.5 rounded-lg transition shadow-sm" title="Tolak"><i class="fas fa-times"></i></button>
                                            <button onclick='openDetailModal(<?php echo json_encode($p); ?>)' class="text-indigo-600 hover:text-indigo-800 font-bold text-xs bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100 transition">Detail</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="p-8 text-center text-gray-400">Tidak ada antrean pending.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB: ANTREAN -->
                <div id="view-antrean" class="tab-content hidden animate-fade-in">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">Antrean Verifikasi</h1>
                        <div class="relative">
                            <input type="text" id="search-antrean" onkeyup="searchTable('search-antrean', 'table-antrean')" placeholder="Cari toko..." class="pl-9 pr-4 py-2 border rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none w-64">
                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <table class="w-full text-sm text-left" id="table-antrean">
                            <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-100">
                                <tr>
                                    <th class="p-4 pl-6">Info Toko</th>
                                    <th class="p-4">Pemilik & NIK</th>
                                    <th class="p-4">Alamat</th>
                                    <th class="p-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if($pending_list): foreach($pending_list as $p): ?>
                                <tr class="hover:bg-gray-50 transition group">
                                    <td class="p-4 pl-6 font-bold text-gray-800">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-gray-100 overflow-hidden"><img src="<?php echo $p->foto_profil ?: 'https://ui-avatars.com/api/?name='.urlencode($p->nama_toko); ?>" class="w-full h-full object-cover"></div>
                                            <div><?php echo esc_html($p->nama_toko); ?><div class="text-[10px] text-gray-400 font-normal"><?php echo date('d M Y', strtotime($p->created_at)); ?></div></div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-600">
                                        <div class="font-medium"><?php echo esc_html($p->nama_pemilik); ?></div>
                                        <div class="text-xs text-gray-400 font-mono"><?php echo esc_html($p->nik); ?></div>
                                    </td>
                                    <td class="p-4 text-gray-500 max-w-xs truncate" title="<?php echo esc_attr($p->alamat_lengkap); ?>"><?php echo esc_html($p->alamat_lengkap); ?></td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick='quickVerify("disetujui", <?php echo $p->id; ?>, "<?php echo esc_js($p->nama_toko); ?>")' class="bg-green-100 text-green-700 hover:bg-green-200 px-3 py-1.5 rounded-lg font-bold text-xs transition flex items-center gap-1"><i class="fas fa-check"></i> Setujui</button>
                                            <button onclick='quickVerify("ditolak", <?php echo $p->id; ?>, "<?php echo esc_js($p->nama_toko); ?>")' class="bg-red-100 text-red-700 hover:bg-red-200 px-3 py-1.5 rounded-lg font-bold text-xs transition flex items-center gap-1"><i class="fas fa-times"></i> Tolak</button>
                                            <button onclick='openDetailModal(<?php echo json_encode($p); ?>)' class="bg-gray-100 text-gray-600 hover:bg-gray-200 px-3 py-1.5 rounded-lg font-bold text-xs transition" title="Lihat Detail"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="4" class="p-8 text-center text-gray-400">Tidak ada antrean.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB: UMKM BINAAN (FIXED) -->
                <div id="view-binaan" class="tab-content hidden animate-fade-in">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">UMKM Binaan Saya</h1>
                        <div class="relative">
                            <input type="text" id="search-binaan" onkeyup="searchTable('search-binaan', 'table-binaan')" placeholder="Cari binaan..." class="pl-9 pr-4 py-2 border rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none w-64">
                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <table class="w-full text-sm text-left" id="table-binaan">
                            <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-100">
                                <tr>
                                    <th class="p-4 pl-6">Nama Toko</th>
                                    <th class="p-4">Pemilik & Kontak</th>
                                    <th class="p-4">Bergabung</th>
                                    <th class="p-4 text-center">Status</th>
                                    <th class="p-4 text-center">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if($binaan_list): foreach($binaan_list as $m): 
                                    $status_class = ($m->status_akun == 'aktif') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                    $wa_url = 'https://wa.me/' . preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $m->nomor_wa));
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="p-4 pl-6 font-bold text-gray-800">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded bg-gray-100 border border-gray-200 overflow-hidden"><img src="<?php echo $m->foto_profil ?: 'https://ui-avatars.com/api/?name='.urlencode($m->nama_toko); ?>" class="w-full h-full object-cover"></div>
                                            <div>
                                                <?php echo esc_html($m->nama_toko); ?>
                                                <div class="text-[10px] text-gray-400 font-normal">Sisa Kuota: <?php echo $m->sisa_transaksi; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-600">
                                        <div class="font-medium"><?php echo esc_html($m->nama_pemilik); ?></div>
                                        <a href="<?php echo $wa_url; ?>" target="_blank" class="text-xs text-green-600 hover:underline"><i class="fab fa-whatsapp"></i> <?php echo esc_html($m->nomor_wa); ?></a>
                                    </td>
                                    <td class="p-4 text-gray-500"><?php echo date('d M Y', strtotime($m->created_at)); ?></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase <?php echo $status_class; ?>"><?php echo ucfirst($m->status_akun); ?></span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <button onclick='openDetailModal(<?php echo json_encode($m); ?>, true)' class="text-gray-500 hover:text-indigo-600 font-bold text-xs border px-3 py-1 rounded-lg hover:bg-gray-50 transition">Lihat</button>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="5" class="p-8 text-center text-gray-400">Belum ada UMKM yang menjadi binaan Anda.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB: RIWAYAT -->
                <div id="view-riwayat" class="tab-content hidden animate-fade-in">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">Riwayat Verifikasi</h1>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-100"><tr><th class="p-4 pl-6">Tanggal</th><th class="p-4">Nama Toko</th><th class="p-4">Pemilik</th><th class="p-4 text-center">Keputusan</th></tr></thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if($history_list): foreach($history_list as $h): $st = ($h->status_pendaftaran == 'disetujui') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>
                                <tr class="hover:bg-gray-50 transition"><td class="p-4 pl-6 text-gray-500"><?php echo date('d M Y, H:i', strtotime($h->verified_at)); ?></td><td class="p-4 font-bold text-gray-800"><?php echo esc_html($h->nama_toko); ?></td><td class="p-4 text-gray-600"><?php echo esc_html($h->nama_pemilik); ?></td><td class="p-4 text-center"><span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?php echo $st; ?>"><?php echo ucfirst($h->status_pendaftaran); ?></span></td></tr>
                                <?php endforeach; else: ?><tr><td colspan="4" class="p-8 text-center text-gray-400">Belum ada riwayat.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB: PENGATURAN (PROFIL & BANK) -->
                <div id="view-pengaturan" class="tab-content hidden animate-fade-in">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">Pengaturan Akun</h1>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- FORM PROFIL -->
                        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                            <h3 class="font-bold text-gray-800 mb-6 flex items-center gap-2"><i class="fas fa-user-cog text-indigo-500"></i> Data Diri & Lokasi</h3>
                            <form method="POST">
                                <?php wp_nonce_field('save_profile_action', 'profile_nonce'); ?>
                                <input type="hidden" name="action_save_profile" value="1">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                                    <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Lengkap</label><input type="text" name="nama_lengkap" value="<?php echo esc_attr($verifikator->nama_lengkap); ?>" class="w-full border rounded-lg p-2.5 text-sm"></div>
                                    <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">NIK</label><input type="text" name="nik" value="<?php echo esc_attr($verifikator->nik); ?>" class="w-full border rounded-lg p-2.5 text-sm"></div>
                                    <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nomor WhatsApp</label><input type="text" name="nomor_wa" value="<?php echo esc_attr($verifikator->nomor_wa); ?>" class="w-full border rounded-lg p-2.5 text-sm"></div>
                                    <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kode Referral</label><input type="text" value="<?php echo esc_attr($verifikator->kode_referral); ?>" readonly class="w-full border rounded-lg p-2.5 bg-gray-100 text-gray-500 font-mono text-sm"></div>
                                </div>

                                <div class="border-t border-gray-100 pt-5 mb-5">
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Wilayah Kerja / Domisili</label>
                                    
                                    <!-- DATA WILAYAH HIDDEN -->
                                    <div id="region-data-v" 
                                        data-prov="<?php echo esc_attr($verifikator->api_provinsi_id); ?>" 
                                        data-kota="<?php echo esc_attr($verifikator->api_kabupaten_id); ?>" 
                                        data-kec="<?php echo esc_attr($verifikator->api_kecamatan_id); ?>" 
                                        data-kel="<?php echo esc_attr($verifikator->api_kelurahan_id); ?>">
                                    </div>
                                    <input type="hidden" name="provinsi_nama" id="v_prov_nama" value="<?php echo esc_attr($verifikator->provinsi); ?>">
                                    <input type="hidden" name="kabupaten_nama" id="v_kab_nama" value="<?php echo esc_attr($verifikator->kabupaten); ?>">
                                    <input type="hidden" name="kecamatan_nama" id="v_kec_nama" value="<?php echo esc_attr($verifikator->kecamatan); ?>">
                                    <input type="hidden" name="kelurahan_nama" id="v_kel_nama" value="<?php echo esc_attr($verifikator->kelurahan); ?>">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <select name="api_provinsi_id" id="v_prov" class="w-full border rounded-lg p-2.5 text-sm bg-gray-50"></select>
                                        <select name="api_kabupaten_id" id="v_kota" class="w-full border rounded-lg p-2.5 text-sm bg-gray-50" disabled></select>
                                        <select name="api_kecamatan_id" id="v_kec" class="w-full border rounded-lg p-2.5 text-sm bg-gray-50" disabled></select>
                                        <select name="api_kelurahan_id" id="v_kel" class="w-full border rounded-lg p-2.5 text-sm bg-gray-50" disabled></select>
                                    </div>
                                    <div class="mt-4"><textarea name="alamat_lengkap" rows="2" class="w-full border rounded-lg p-2.5 text-sm" placeholder="Alamat Lengkap (Jalan, RT/RW)"><?php echo esc_textarea($verifikator->alamat_lengkap); ?></textarea></div>
                                </div>

                                <!-- DATA BANK -->
                                <div class="border-t border-gray-100 pt-5 mb-5">
                                    <h4 class="font-bold text-gray-800 mb-3 text-sm">Rekening Pencairan Komisi</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div><label class="text-[10px] text-gray-400 font-bold uppercase">Nama Bank</label><input type="text" name="bank_name" value="<?php echo esc_attr($bank_name); ?>" class="w-full border rounded-lg p-2 text-sm"></div>
                                        <div><label class="text-[10px] text-gray-400 font-bold uppercase">No. Rekening</label><input type="text" name="bank_acc" value="<?php echo esc_attr($bank_acc); ?>" class="w-full border rounded-lg p-2 text-sm font-mono"></div>
                                        <div><label class="text-[10px] text-gray-400 font-bold uppercase">Atas Nama</label><input type="text" name="bank_user" value="<?php echo esc_attr($bank_user); ?>" class="w-full border rounded-lg p-2 text-sm"></div>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg transition transform hover:-translate-y-0.5">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </main>
        </div>

        <!-- MODAL DETAIL -->
        <div id="modal-detail" class="fixed inset-0 z-50 hidden transition-opacity duration-300">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('modal-detail')"></div>
            <div class="absolute inset-y-0 right-0 w-full max-w-2xl bg-white shadow-2xl transform transition-transform translate-x-full duration-300 flex flex-col" id="modal-detail-panel">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white sticky top-0 z-10">
                    <h2 class="text-xl font-bold text-gray-800">Detail Pedagang</h2>
                    <button onclick="closeModal('modal-detail')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                </div>
                <div class="p-6 flex-1 overflow-y-auto space-y-6">
                    <div class="flex items-center gap-4">
                        <img id="d-foto" src="" class="w-20 h-20 rounded-2xl object-cover border border-gray-200">
                        <div><h3 class="text-2xl font-bold text-gray-900" id="d-toko">-</h3><p class="text-gray-500" id="d-pemilik">-</p></div>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 space-y-3 text-sm">
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">NIK</span><span class="font-mono font-bold" id="d-nik">-</span></div>
                        <div class="flex justify-between border-b pb-2"><span class="text-gray-500">Tanggal Daftar</span><span class="font-bold" id="d-date">-</span></div>
                        <div><span class="text-gray-500 block mb-1">Alamat</span><p class="text-gray-800" id="d-alamat">-</p></div>
                        <div id="d-gmaps-container" class="hidden pt-2"><a id="d-gmaps" href="#" target="_blank" class="text-blue-600 hover:underline font-bold"><i class="fas fa-map-marker-alt"></i> Buka Maps</a></div>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 mb-3">Dokumen</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div><p class="text-xs text-gray-500 mb-1">KTP</p><img id="d-ktp-img" src="" class="w-full rounded border cursor-pointer" onclick="viewImage('d-ktp-img')"></div>
                            <div><p class="text-xs text-gray-500 mb-1">Sampul</p><img id="d-sampul-img" src="" class="w-full rounded border cursor-pointer" onclick="viewImage('d-sampul-img')"></div>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 bg-gray-50 sticky bottom-0 z-10" id="modal-footer-action">
                    <button id="btn-process-modal" class="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-xl hover:bg-indigo-700 transition">Proses Verifikasi</button>
                </div>
            </div>
        </div>

        <!-- MODAL PROSES -->
        <div id="modal-process" class="fixed inset-0 z-[60] hidden">
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeModal('modal-process')"></div>
            <div class="absolute inset-0 flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl w-full max-w-md p-6 shadow-2xl transform scale-100 transition-all">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Keputusan Verifikasi</h3>
                    <form method="POST">
                        <?php wp_nonce_field('verify_umkm_action', 'verify_nonce'); ?>
                        <input type="hidden" name="action_verify" value="1">
                        <input type="hidden" name="id_pedagang" id="proc-id">
                        <div class="flex gap-3 mb-4">
                            <label class="flex-1 cursor-pointer"><input type="radio" name="keputusan" value="disetujui" class="peer hidden" checked onchange="toggleReason(false)"><div class="p-4 rounded-xl border-2 border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50 text-center transition"><div class="text-2xl mb-1 text-gray-400 peer-checked:text-green-600"><i class="fas fa-check-circle"></i></div><span class="font-bold text-gray-600 peer-checked:text-green-800">Setujui</span></div></label>
                            <label class="flex-1 cursor-pointer"><input type="radio" name="keputusan" value="ditolak" class="peer hidden" onchange="toggleReason(true)"><div class="p-4 rounded-xl border-2 border-gray-200 peer-checked:border-red-500 peer-checked:bg-red-50 text-center transition"><div class="text-2xl mb-1 text-gray-400 peer-checked:text-red-600"><i class="fas fa-times-circle"></i></div><span class="font-bold text-gray-600 peer-checked:text-red-800">Tolak</span></div></label>
                        </div>
                        <div id="reason-box" class="hidden mb-6 animate-fade-in"><label class="block text-xs font-bold text-gray-600 mb-1">Alasan Penolakan</label><textarea name="alasan_penolakan" rows="3" class="w-full border border-gray-300 rounded-lg p-3 text-sm"></textarea></div>
                        <div class="flex gap-3"><button type="button" onclick="closeModal('modal-process')" class="flex-1 py-3 text-gray-600 font-bold bg-gray-100 rounded-xl">Batal</button><button type="submit" class="flex-1 py-3 bg-gray-900 text-white font-bold rounded-xl">Simpan</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL WITHDRAW -->
        <div id="modal-withdraw" class="fixed inset-0 z-[60] hidden">
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeModal('modal-withdraw')"></div>
            <div class="absolute inset-0 flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl w-full max-w-sm p-8 shadow-2xl transform scale-100 transition-all text-center">
                    <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4"><i class="fas fa-money-bill-wave"></i></div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Tarik Saldo Komisi</h3>
                    <p class="text-gray-500 text-sm mb-6">Saldo tersedia: <strong>Rp <?php echo number_format($verifikator->saldo_saat_ini, 0, ',', '.'); ?></strong></p>
                    
                    <form method="POST">
                        <?php wp_nonce_field('withdraw_action', 'withdraw_nonce'); ?>
                        <input type="hidden" name="action_withdraw" value="1">
                        <div class="mb-6 text-left">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nominal Penarikan</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400 font-bold">Rp</span>
                                <input type="text" name="nominal_withdraw" class="w-full border border-gray-300 rounded-xl pl-10 pr-3 py-3 font-bold text-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0" onkeyup="formatRupiah(this)">
                            </div>
                            <p class="text-[10px] text-gray-400 mt-2 italic">*Minimal penarikan Rp 50.000. Proses 1-2 hari kerja.</p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" onclick="closeModal('modal-withdraw')" class="flex-1 py-3 text-gray-600 font-bold bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                            <button type="submit" class="flex-1 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition shadow-lg">Tarik</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <style>
            .tab-content { display: none; }
            .tab-content.block { display: block; animation: fadeIn 0.3s ease-out; }
            .nav-item.active { background-color: #eef2ff; color: #4f46e5; border-left: 4px solid #4f46e5; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        </style>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";

            // Toggle Sidebar
            function toggleMobileSidebar() {
                const sidebar = document.getElementById('dashboard-sidebar');
                const backdrop = document.getElementById('sidebar-backdrop');
                if (sidebar) {
                    sidebar.classList.toggle('-translate-x-full');
                    if (backdrop) backdrop.classList.toggle('hidden');
                }
            }

            // Switch Tab
            function switchTab(tabName) {
                document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('block', 'hidden'));
                document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
                document.getElementById('view-' + tabName).classList.remove('hidden');
                document.getElementById('view-' + tabName).classList.add('block');
                document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
                document.getElementById('nav-' + tabName).classList.add('active');
                if(window.innerWidth < 768) { 
                    const sb = document.getElementById('dashboard-sidebar');
                    if(sb && !sb.classList.contains('-translate-x-full')) toggleMobileSidebar();
                }
            }

            // Quick Verify Function
            function quickVerify(decision, id, name) {
                if(confirm('Anda yakin ingin ' + (decision == 'disetujui' ? 'MENYETUJUI' : 'MENOLAK') + ' toko ' + name + '?')) {
                    document.getElementById('proc-id').value = id;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    
                    const fields = {
                        'verify_nonce': '<?php echo wp_create_nonce("verify_umkm_action"); ?>',
                        'action_verify': '1',
                        'id_pedagang': id,
                        'keputusan': decision,
                        'alasan_penolakan': (decision == 'ditolak') ? 'Ditolak via Quick Action' : ''
                    };

                    for (const key in fields) {
                        const hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = key;
                        hiddenField.value = fields[key];
                        form.appendChild(hiddenField);
                    }

                    document.body.appendChild(form);
                    form.submit();
                }
            }

            // Table Search
            function searchTable(inputId, tableId) {
                var input, filter, table, tr, td, i, txtValue;
                input = document.getElementById(inputId);
                filter = input.value.toUpperCase();
                table = document.getElementById(tableId);
                tr = table.getElementsByTagName("tr");
                for (i = 0; i < tr.length; i++) {
                    // Search col 0 (Nama Toko) and 1 (Pemilik)
                    td0 = tr[i].getElementsByTagName("td")[0];
                    td1 = tr[i].getElementsByTagName("td")[1];
                    if (td0 || td1) {
                        txtValue0 = td0.textContent || td0.innerText;
                        txtValue1 = td1.textContent || td1.innerText;
                        if (txtValue0.toUpperCase().indexOf(filter) > -1 || txtValue1.toUpperCase().indexOf(filter) > -1) {
                            tr[i].style.display = "";
                        } else {
                            tr[i].style.display = "none";
                        }
                    }       
                }
            }

            // Format Rupiah Input
            function formatRupiah(el) {
                let val = el.value.replace(/[^0-9]/g, '');
                el.value = new Intl.NumberFormat('id-ID').format(val);
            }

            // Initialize
            document.addEventListener("DOMContentLoaded", () => {
                const p = new URLSearchParams(window.location.search);
                switchTab(p.get('tab') || 'dashboard');
                
                // Region Auto-Load for Verifikator
                var els={prov:$('#v_prov'),kota:$('#v_kota'),kec:$('#v_kec'),desa:$('#v_kel')}, data=$('#region-data-v').data();
                function l(a,pid,el,sel,cb){ el.html('<option>Loading...</option>').prop('disabled',true); var p={action:a}; if(a=='dw_fetch_regencies')p.province_id=pid; if(a=='dw_fetch_districts')p.regency_id=pid; if(a=='dw_fetch_villages')p.district_id=pid; $.get(ajaxurl,p,function(r){ if(r.success){ var o='<option value="">-- Pilih --</option>'; $.each(r.data.data||r.data,function(i,v){ var id=String(v.id||v.code); o+='<option value="'+id+'" '+(id==sel?'selected':'')+'>'+(v.name||v.nama)+'</option>'; }); el.html(o).prop('disabled',false); if(cb)cb(); }}); }
                function s(el,t){ var txt=$(el).find('option:selected').text(); if(txt!=='Loading...'&&txt!=='-- Pilih --')$(t).val(txt); }
                
                l('dw_fetch_provinces',null,els.prov,String(data.prov),function(){ 
                    if(data.prov) l('dw_fetch_regencies',data.prov,els.kota,String(data.kota),function(){ 
                        if(data.kota) l('dw_fetch_districts',data.kota,els.kec,String(data.kec),function(){ 
                            if(data.kec) l('dw_fetch_villages',data.kec,els.desa,String(data.kel)); 
                        }); 
                    }); 
                });

                els.prov.change(function(){s(this,'#v_prov_nama'); l('dw_fetch_regencies',$(this).val(),els.kota,null); els.kota.val(''); });
                els.kota.change(function(){s(this,'#v_kab_nama'); l('dw_fetch_districts',$(this).val(),els.kec,null); });
                els.kec.change(function(){s(this,'#v_kec_nama'); l('dw_fetch_villages',$(this).val(),els.desa,null); });
                els.desa.change(function(){s(this,'#v_kel_nama');});
            });

            // Modals
            function openDetailModal(data, isReadOnly = false) {
                document.getElementById('d-toko').textContent = data.nama_toko;
                document.getElementById('d-pemilik').textContent = data.nama_pemilik;
                document.getElementById('d-nik').textContent = data.nik || '-';
                document.getElementById('d-date').textContent = new Date(data.created_at).toLocaleDateString('id-ID');
                document.getElementById('d-alamat').textContent = data.alamat_lengkap || '-';
                document.getElementById('d-foto').src = data.foto_profil || 'https://ui-avatars.com/api/?name='+encodeURIComponent(data.nama_toko);
                
                const ktp = document.getElementById('d-ktp-img');
                if(data.url_ktp) { ktp.src = data.url_ktp; ktp.parentElement.classList.remove('hidden'); } else { ktp.parentElement.classList.add('hidden'); }
                
                const sampul = document.getElementById('d-sampul-img');
                if(data.foto_sampul) { sampul.src = data.foto_sampul; sampul.parentElement.classList.remove('hidden'); } else { sampul.parentElement.classList.add('hidden'); }

                if(data.url_gmaps) { document.getElementById('d-gmaps').href = data.url_gmaps; document.getElementById('d-gmaps-container').classList.remove('hidden'); } else { document.getElementById('d-gmaps-container').classList.add('hidden'); }

                // Logic Tombol Proses
                const footer = document.getElementById('modal-footer-action');
                if(isReadOnly) {
                    footer.classList.add('hidden'); // Sembunyikan tombol proses jika hanya lihat detail binaan
                } else {
                    footer.classList.remove('hidden');
                    const btn = document.getElementById('btn-process-modal');
                    const newBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(newBtn, btn);
                    newBtn.onclick = function() { closeModal('modal-detail'); setTimeout(()=>processItem(data.id, data.nama_toko),300); };
                }

                document.getElementById('modal-detail').classList.remove('hidden');
                setTimeout(() => document.getElementById('modal-detail-panel').classList.remove('translate-x-full'), 10);
            }

            function processItem(id, name) {
                document.getElementById('proc-id').value = id;
                // document.getElementById('proc-name').textContent = name; // Element not found in HTML, removing
                toggleReason(false);
                document.querySelector('input[name="keputusan"][value="disetujui"]').checked = true;
                document.getElementById('modal-process').classList.remove('hidden');
            }

            function openWithdrawModal() {
                document.getElementById('modal-withdraw').classList.remove('hidden');
            }

            function closeModal(id) {
                if(id==='modal-detail'){
                    document.getElementById('modal-detail-panel').classList.add('translate-x-full');
                    setTimeout(() => document.getElementById(id).classList.add('hidden'), 300);
                } else {
                    document.getElementById(id).classList.add('hidden');
                }
            }

            function toggleReason(show) {
                const box = document.getElementById('reason-box');
                const area = box.querySelector('textarea');
                if(show) { box.classList.remove('hidden'); area.required = true; } else { box.classList.add('hidden'); area.required = false; }
            }

            function viewImage(id) {
                const src = document.getElementById(id).src;
                if(src) window.open(src, '_blank');
            }
        </script>

        <?php
        // Hapus get_footer() jika shortcode dipasang di dalam page content
        // if ( ! did_action( 'get_footer' ) ) get_footer();
        return ob_get_clean();
    }
}