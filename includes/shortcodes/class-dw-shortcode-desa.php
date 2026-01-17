<?php
/**
 * Shortcode: Dashboard Desa [dw_dashboard_desa]
 * Path: includes/shortcodes/class-dw-shortcode-desa.php
 * Description: Dashboard frontend untuk Desa Wisata (Statistik, Dompet, Manajemen UMKM).
 * Version: 2.6.0 (Financial Integrated)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcode_Desa {

    public function __construct() {
        add_shortcode( 'dw_dashboard_desa', array( $this, 'render' ) );
    }

    public static function init() {
        $instance = new self();
    }

    public function render( $atts ) {
        // 1. Cek Login
        if ( ! is_user_logged_in() ) {
            return '<div class="dw-alert dw-alert-warning p-4 bg-yellow-50 text-yellow-800 rounded border border-yellow-200">Silakan <a href="' . wp_login_url( get_permalink() ) . '" class="font-bold underline">login</a> terlebih dahulu untuk mengakses dashboard desa.</div>';
        }

        // 2. Cek Hak Akses
        $current_user = wp_get_current_user();
        $roles        = ( array ) $current_user->roles;

        // Izinkan jika user adalah 'administrator', 'admin_desa', 'desa', ATAU memiliki kapabilitas 'dw_manage_desa'
        if ( ! in_array( 'administrator', $roles ) && 
             ! in_array( 'admin_desa', $roles ) && 
             ! in_array( 'desa', $roles ) && 
             ! current_user_can( 'dw_manage_desa' ) ) {
            
            // Redirect ke halaman akun saya jika akses ditolak (menggunakan JS karena headers sudah terkirim di shortcode)
            return '<script>window.location.href="' . home_url('/akun-saya/') . '";</script><div class="p-4 text-center">Mengalihkan...</div>';
        }

        $current_user_id = get_current_user_id();
        global $wpdb;

        // --- CONFIG: TABLE NAMES ---
        $table_desa     = $wpdb->prefix . 'dw_desa'; 
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        $table_wisata   = $wpdb->prefix . 'dw_wisata';
        $table_payout   = $wpdb->prefix . 'dw_payout_ledger';

        // 2. Ambil Data Desa
        $desa_data = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_desa WHERE id_user_desa = %d", $current_user_id) );

        // --- AUTO-CREATE: Jika data desa belum ada ---
        if ( ! $desa_data ) {
            $nama_default = 'Desa ' . $current_user->display_name;
            $slug_default = sanitize_title($nama_default) . '-' . $current_user_id;
            
            // Generate Kode Referral Awal
            $rand = rand(1000, 9999);
            $ref_code = 'DESA-' . strtoupper(substr($current_user->display_name, 0, 3)) . '-' . $rand;

            $wpdb->insert($table_desa, [
                'id_user_desa'            => $current_user_id,
                'nama_desa'               => $nama_default,
                'slug_desa'               => $slug_default,
                'kode_referral'           => $ref_code,
                'status'                  => 'pending',
                'status_akses_verifikasi' => 'locked', 
                'created_at'              => current_time('mysql'),
                'updated_at'              => current_time('mysql'),
                'saldo_komisi'            => 0, // Default saldo
                'total_pendapatan'        => 0  // Default total pendapatan
            ]);
            
            $desa_data = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_desa WHERE id_user_desa = %d", $current_user_id) );
        }

        $id_desa = $desa_data->id;
        $akses_premium = ($desa_data->status_akses_verifikasi === 'active');
        $status_verifikasi = $desa_data->status_akses_verifikasi; 

        // --- MESSAGE HANDLER ---
        $msg = '';
        $msg_type = '';

        // ==========================================
        // FORM HANDLERS (Existing from previous code)
        // ==========================================

        // 1. Simpan Profil & Update Referral
        if ( isset($_POST['save_profil_desa']) && check_admin_referer('save_profil_desa_action', 'profil_desa_nonce') ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' ); 
            require_once( ABSPATH . 'wp-admin/includes/image.php' ); 
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            
            $prov_txt = sanitize_text_field($_POST['provinsi_nama']); 
            $kab_txt  = sanitize_text_field($_POST['kabupaten_nama']); 
            $kec_txt  = sanitize_text_field($_POST['kecamatan_nama']); 
            $kel_txt  = sanitize_text_field($_POST['kelurahan_nama']);

            // Generator ID Wilayah (Jika belum punya)
            $kode_referral_save = $desa_data->kode_referral;
            if ( empty($kode_referral_save) ) {
                if(!empty($prov_txt) && !empty($kab_txt) && !empty($kel_txt)) {
                    $get_code = function($text){ 
                        $c=trim(strtolower($text)); $c=preg_replace('/^(provinsi|kabupaten|kota|desa|kelurahan)\s+/','',$c);
                        if($c=='jawa barat')return 'JAB'; if($c=='jawa tengah')return 'JTG'; if($c=='jawa timur')return 'JTM'; if(strpos($c,'jakarta')!==false)return 'DKI'; if(strpos($c,'yogyakarta')!==false)return 'DIY';
                        return strtoupper(substr(str_replace(' ','',$c),0,3));
                    };
                    $c_prov=$get_code($prov_txt); $c_kab=$get_code($kab_txt); $c_des=$get_code($kel_txt); $rand=rand(1000,9999); 
                    $calon_kode = "$c_prov-$c_kab-$c_des-$rand";
                    // Cek Unik
                    while($wpdb->get_var($wpdb->prepare("SELECT id FROM $table_desa WHERE kode_referral=%s AND id!=%d",$calon_kode,$id_desa))){ $rand=rand(1000,9999); $calon_kode="$c_prov-$c_kab-$c_des-$rand"; }
                    $kode_referral_save = $calon_kode;
                } else {
                    $kode_referral_save = 'DESA-' . rand(10000, 99999);
                }
            }

            $update_desa = [
                'nama_desa'         => sanitize_text_field($_POST['nama_desa']), 
                'deskripsi'         => wp_kses_post($_POST['deskripsi']), 
                'alamat_lengkap'    => sanitize_textarea_field($_POST['alamat_lengkap']), 
                'kode_referral'     => $kode_referral_save,
                'nomor_wa'          => sanitize_text_field($_POST['nomor_wa']),
                'nama_bank_desa'            => sanitize_text_field($_POST['nama_bank_desa']), 
                'no_rekening_desa'          => sanitize_text_field($_POST['no_rekening_desa']), 
                'atas_nama_rekening_desa'   => sanitize_text_field($_POST['atas_nama_rekening_desa']),
                'api_provinsi_id'           => sanitize_text_field($_POST['api_provinsi_id']), 
                'api_kabupaten_id'          => sanitize_text_field($_POST['api_kabupaten_id']), 
                'api_kecamatan_id'          => sanitize_text_field($_POST['api_kecamatan_id']), 
                'api_kelurahan_id'          => sanitize_text_field($_POST['api_kelurahan_id']),
                'provinsi' => $prov_txt, 'kabupaten' => $kab_txt, 'kecamatan' => $kec_txt, 'kelurahan' => $kel_txt, 
                'updated_at' => current_time('mysql')
            ];
            
            $files_map = ['foto_desa'=>'foto','foto_sampul'=>'foto_sampul','qris_desa'=>'qris_image_url_desa'];
            foreach($files_map as $i=>$c){ 
                if(!empty($_FILES[$i]['name'])){ 
                    $u=wp_handle_upload($_FILES[$i],['test_form'=>false]); 
                    if(isset($u['url']))$update_desa[$c]=$u['url']; 
                } 
            }
            
            $wpdb->update($table_desa, $update_desa, ['id'=>$id_desa]); 
            $msg="Profil diperbarui."; $msg_type="success"; 
            $desa_data=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id=%d",$id_desa));
        }

        // 2. Upload Bukti Verifikasi
        if ( isset($_POST['action_upload_bukti']) && check_admin_referer('upload_bukti_action', 'upload_bukti_nonce') ) {
            if(!function_exists('wp_handle_upload')) require_once(ABSPATH.'wp-admin/includes/file.php');
            $u=$_FILES['bukti_bayar']; 
            if(!empty($u['name'])){ 
                $m=wp_handle_upload($u,['test_form'=>false]); 
                if($m&&!isset($m['error'])){ 
                    $wpdb->update($table_desa,['bukti_bayar_akses'=>$m['url'],'status_akses_verifikasi'=>'pending','updated_at'=>current_time('mysql')],['id'=>$id_desa]); 
                    $msg="Bukti terkirim."; $msg_type="success"; $status_verifikasi='pending'; 
                } 
            }
        }

        // 3. Verifikasi UMKM
        if ( isset($_POST['action_verifikasi']) && check_admin_referer('verifikasi_pedagang_action', 'verifikasi_nonce') ) {
            if(!$akses_premium){ 
                $msg="Fitur Premium."; $msg_type="error"; 
            } else {
                $st=sanitize_text_field($_POST['status_keputusan']); 
                $upd=['status_pendaftaran'=>$st,'approved_by'=>'desa','updated_at'=>current_time('mysql')];
                if($st=='disetujui'){ 
                    $upd['status_akun']='aktif'; 
                    $opt=get_option('dw_settings'); 
                    $upd['sisa_transaksi']=isset($opt['kuota_gratis_default'])?absint($opt['kuota_gratis_default']):0; 
                } else { 
                    $upd['status_akun']='nonaktif'; 
                }
                $wpdb->update($table_pedagang, $upd, ['id'=>intval($_POST['id_pedagang'])]); 
                $msg="Status diperbarui."; $msg_type="success";
            }
        }

        // 4. Save Wisata
        if ( isset($_POST['save_wisata']) && check_admin_referer('save_wisata_action', 'wisata_nonce') ) {
            require_once(ABSPATH.'wp-admin/includes/file.php'); 
            require_once(ABSPATH.'wp-admin/includes/image.php'); 
            require_once(ABSPATH.'wp-admin/includes/media.php');
            
            $wid=isset($_POST['wisata_id'])&&is_numeric($_POST['wisata_id'])?intval($_POST['wisata_id']):0;
            if(!$akses_premium&&$wid===0){ $cnt=$wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_wisata WHERE id_desa=%d",$id_desa)); if($cnt>=2){ $msg="Kuota Penuh."; $msg_type="error"; } }
            
            if(empty($msg)){
                $dt=['id_desa'=>$id_desa,'nama_wisata'=>sanitize_text_field($_POST['nama_wisata']),'kategori'=>sanitize_text_field($_POST['kategori']),'deskripsi'=>wp_kses_post($_POST['deskripsi']),'harga_tiket'=>floatval($_POST['harga_tiket']),'jam_buka'=>sanitize_text_field($_POST['jam_buka']),'fasilitas'=>sanitize_textarea_field($_POST['fasilitas']),'kontak_pengelola'=>sanitize_text_field($_POST['kontak_pengelola']),'lokasi_maps'=>esc_url_raw($_POST['lokasi_maps']),'updated_at'=>current_time('mysql')];
                if(!empty($_FILES['foto_utama']['name'])){ $u=wp_handle_upload($_FILES['foto_utama'],['test_form'=>false]); if(isset($u['url'])) $dt['foto_utama']=$u['url']; }
                
                if($wid>0){ 
                    $wpdb->update($table_wisata,$dt,['id'=>$wid,'id_desa'=>$id_desa]); 
                    $msg="Update berhasil."; $msg_type="success"; 
                } else { 
                    $dt['slug']=sanitize_title($_POST['nama_wisata']).'-'.rand(100,999); 
                    $dt['created_at']=current_time('mysql'); 
                    $dt['status']='aktif'; 
                    $wpdb->insert($table_wisata,$dt); 
                    $msg="Wisata ditambah."; $msg_type="success"; 
                }
            }
        }

        // 5. Delete Wisata
        if ( isset($_GET['action']) && $_GET['action'] == 'hapus_wisata' && isset($_GET['id']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'hapus_wisata_'.$_GET['id']) ) {
            $wpdb->delete($table_wisata, ['id' => intval($_GET['id']), 'id_desa' => $id_desa]); 
            // Redirect JS untuk membersihkan URL
            return '<script>window.location.href="' . remove_query_arg(['action','id','_wpnonce']) . '";</script>';
        }

        // 6. Withdraw (Manual ke Payout Ledger + Integrasi Wallet Logic)
        if ( isset($_POST['action_withdraw']) && check_admin_referer('withdraw_action', 'withdraw_nonce') ) {
            $amount = floatval(str_replace('.', '', $_POST['nominal_withdraw'])); 
            
            // Gunakan DW_Wallet jika ada untuk saldo yang lebih akurat
            $saldo_saat_ini = 0;
            if (class_exists('DW_Wallet')) {
                $saldo_saat_ini = DW_Wallet::get_balance($current_user_id);
            } else {
                $saldo_saat_ini = $desa_data->saldo_komisi;
            }
            
            if ( $amount <= 0 ) {
                $msg = "Nominal tidak valid."; $msg_type = "error";
            } elseif ( $amount > $saldo_saat_ini ) {
                $msg = "Saldo tidak mencukupi."; $msg_type = "error";
            } elseif ( empty($desa_data->no_rekening_desa) || empty($desa_data->nama_bank_desa) ) {
                $msg = "Lengkapi data rekening desa di menu Profil."; $msg_type = "error";
            } else {
                
                // --- INTEGRASI WALLET SYSTEM BARU ---
                if (class_exists('DW_Wallet')) {
                    // Panggil fungsi request_withdrawal yang handle Xendit otomatis
                    $bank_details = [
                        'bank_code' => $desa_data->nama_bank_desa, // Pastikan format kode bank sesuai Xendit (e.g. BNI, BCA)
                        'account_number' => $desa_data->no_rekening_desa,
                        'account_name' => $desa_data->atas_nama_rekening_desa
                    ];
                    
                    $result = DW_Wallet::request_withdrawal($current_user_id, $amount, $bank_details);
                    
                    if (is_wp_error($result)) {
                        $msg = $result->get_error_message();
                        $msg_type = "error";
                    } else {
                        $msg = "Permintaan pencairan berhasil diproses (ID: #$result).";
                        $msg_type = "success";
                    }
                } else {
                    // --- FALLBACK KE SISTEM LAMA (Manual Ledger) ---
                    $wpdb->query($wpdb->prepare("UPDATE $table_desa SET saldo_komisi = saldo_komisi - %f WHERE id = %d", $amount, $id_desa));
                    $wpdb->insert($table_payout, [
                        'order_id' => 0, 
                        'payable_to_type' => 'desa',
                        'payable_to_id' => $id_desa,
                        'amount' => $amount,
                        'status' => 'pending',
                        'created_at' => current_time('mysql')
                    ]);
                    $msg = "Permintaan pencairan manual berhasil."; $msg_type = "success";
                }
                
                // Refresh Data Desa
                $desa_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_desa WHERE id=%d",$id_desa));
            }
        }

        // ==========================================
        // DATA QUERY
        // ==========================================
        $wisata_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_wisata WHERE id_desa = %d AND status = 'aktif' ORDER BY created_at DESC", $id_desa));
        $total_umkm = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_pedagang WHERE id_desa = %d AND status_akun = 'aktif'", $id_desa));
        $umkm_active = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id_desa = %d AND status_akun = 'aktif' ORDER BY created_at DESC LIMIT 10", $id_desa));
        $total_wisata_count = count($wisata_list);
        $kategori_wisata = ['Wisata Alam', 'Wisata Bahari', 'Wisata Budaya', 'Wisata Sejarah', 'Spot Foto', 'Camping Ground', 'Edukasi'];
        $settings = get_option('dw_settings', []); 
        $harga_premium = isset($settings['harga_premium_desa']) ? $settings['harga_premium_desa'] : 0;
        $sys_bank_name = get_option('dw_bank_name', '-'); 
        $sys_bank_account = get_option('dw_bank_account', '-'); 
        $sys_bank_holder = get_option('dw_bank_holder', '-'); 

        // Fetch History Payouts (Gabungan tabel ledger lama & withdrawals baru)
        $payout_history = [];
        
        // 1. Cek Tabel Withdrawals Baru
        $table_wd = $wpdb->prefix . 'dw_withdrawals';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_wd'") == $table_wd) {
            $payout_history = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_wd WHERE user_id = %d ORDER BY created_at DESC", $current_user_id));
        } else {
            // 2. Fallback Tabel Ledger Lama
            $payout_history = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_payout WHERE payable_to_type = 'desa' AND payable_to_id = %d ORDER BY created_at DESC", $id_desa));
        }

        // Hitung Saldo Realtime untuk Tampilan
        $saldo_tampil = 0;
        if (class_exists('DW_Wallet')) {
            $saldo_tampil = DW_Wallet::get_balance($current_user_id);
        } else {
            $saldo_tampil = $desa_data->saldo_komisi;
        }

        ob_start();

        // 2. Panggil Header Tema (Hanya jika belum ada)
        if ( ! did_action( 'get_header' ) ) {
            get_header();
        }
        ?>

        <!-- CDN Tailwind & FontAwesome -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <!-- SweetAlert2 -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        
        <script>
            tailwind.config = {
                theme: { extend: { colors: { primary: '#16a34a', 'primary-dark': '#15803d', 'secondary': '#f3f4f6' } } }
            }
        </script>
        <style>
            .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            .active-tab { background-color: #eff6ff; color: #2563eb; border-right: 3px solid #2563eb; }
        </style>

        <!-- WRAPPER UTAMA -->
        <div class="bg-gray-50 font-sans flex flex-col md:flex-row relative dw-dashboard-container min-h-screen">

            <!-- MOBILE MENU TOGGLE -->
            <div class="md:hidden bg-white border-b border-gray-200 px-4 py-3 flex justify-between items-center sticky top-0 z-30 shadow-sm">
                <span class="font-bold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-columns text-blue-600"></i> Menu Desa
                </span>
                <button onclick="toggleSidebar()" class="text-gray-600 p-2 bg-gray-100 rounded-lg hover:bg-gray-200">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <!-- SIDEBAR -->
            <aside id="dashboard-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-200 transform -translate-x-full md:translate-x-0 md:relative md:block transition-transform duration-300 pt-0 h-full overflow-y-auto">
                <div class="h-full flex flex-col justify-between">
                    <div>
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo $desa_data->foto ? esc_url($desa_data->foto) : 'https://ui-avatars.com/api/?name='.urlencode($desa_data->nama_desa).'&background=random'; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm">
                                <div class="overflow-hidden">
                                    <h2 class="font-bold text-gray-800 text-sm truncate w-32" title="<?php echo esc_attr($desa_data->nama_desa); ?>"><?php echo esc_html($desa_data->nama_desa); ?></h2>
                                    <p class="text-xs text-gray-500 truncate"><?php echo esc_html($desa_data->kabupaten); ?></p>
                                </div>
                            </div>
                        </div>
                        <nav class="p-4 space-y-2">
                            <button onclick="switchTab('ringkasan')" id="nav-ringkasan" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition active-tab text-left">
                                <i class="fas fa-home w-5 text-center"></i> <span class="font-medium">Ringkasan</span>
                            </button>
                            <button onclick="switchTab('verifikasi')" id="nav-verifikasi" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition text-left relative">
                                <i class="fas fa-check-circle w-5 text-center"></i> <span class="font-medium">Verifikasi UMKM</span>
                                <?php if($pending_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_pedagang WHERE id_desa = %d AND status_pendaftaran = 'menunggu_desa'", $id_desa))): ?>
                                    <span class="absolute right-4 bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $pending_count; ?></span>
                                <?php endif; ?>
                            </button>
                            <button onclick="switchTab('data-umkm')" id="nav-data-umkm" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition text-left">
                                <i class="fas fa-store w-5 text-center"></i> <span class="font-medium">Data UMKM</span>
                            </button>
                            <button onclick="switchTab('wisata')" id="nav-wisata" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition text-left">
                                <i class="fas fa-map-marked-alt w-5 text-center"></i> <span class="font-medium">Wisata</span>
                            </button>
                            <button onclick="switchTab('keuangan')" id="nav-keuangan" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition text-left">
                                <i class="fas fa-wallet w-5 text-center"></i> <span class="font-medium">Keuangan</span>
                            </button>
                            <button onclick="switchTab('profil')" id="nav-profil" class="nav-item w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-50 hover:text-blue-600 transition text-left">
                                <i class="fas fa-cog w-5 text-center"></i> <span class="font-medium">Profil Desa</span>
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
            <div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-30 hidden opacity-0 transition-opacity duration-300 md:hidden"></div>

            <!-- MAIN CONTENT -->
            <main class="flex-1 p-4 md:p-8 transition-all duration-300 min-h-[80vh]">
                
                <!-- Notifikasi -->
                <?php if($msg): ?>
                     <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                icon: '<?php echo $msg_type; ?>',
                                title: '<?php echo $msg_type === "success" ? "Berhasil" : "Info"; ?>',
                                text: '<?php echo $msg; ?>',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        });
                    </script>
                <?php endif; ?>

                <!-- VIEW: RINGKASAN -->
                <div id="view-ringkasan" class="tab-content animate-fade-in">
                    
                    <!-- [BARU] KARTU KODE REFERRAL DESA -->
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-5 mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm relative overflow-hidden group">
                        <div class="relative z-10">
                            <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-1 flex items-center gap-2">
                                <i class="fas fa-tag"></i> Link Referral Desa
                            </p>
                            <h2 class="text-2xl font-mono font-bold text-gray-800 tracking-wide">
                                <?php echo esc_html($desa_data->kode_referral); ?>
                            </h2>
                            <p class="text-xs text-gray-500 mt-1">Bagikan link ini kepada UMKM/Pedagang agar otomatis terdaftar di desa ini.</p>
                        </div>
                        <div class="relative z-10">
                            <?php $ref_link_desa = home_url('/register?ref=' . $desa_data->kode_referral); ?>
                            <button onclick="copyToClipboard('<?php echo esc_js($ref_link_desa); ?>')" class="bg-white hover:bg-green-600 hover:text-white text-green-700 border border-green-200 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all active:scale-95 flex items-center gap-2">
                                <i class="fas fa-link"></i> <span>Salin Link Daftar</span>
                            </button>
                        </div>
                        <div class="absolute right-0 top-0 h-full w-32 bg-green-100/30 -skew-x-12 translate-x-10 group-hover:translate-x-5 transition-transform duration-500"></div>
                    </div>

                    <header class="mb-8"><h1 class="text-2xl font-bold text-gray-800">Ringkasan Desa</h1><p class="text-gray-500 text-sm">Statistik perkembangan.</p></header>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Saldo Komisi -->
                        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition cursor-pointer" onclick="switchTab('keuangan')">
                            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-wallet"></i></div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Saldo Dompet</p>
                                <h3 class="text-2xl font-bold text-gray-800">Rp <?php echo number_format($saldo_tampil, 0, ',', '.'); ?></h3>
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="fas fa-store"></i></div>
                            <div><p class="text-sm text-gray-500 font-medium">Total UMKM</p><h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($total_umkm); ?></h3></div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                            <div class="w-12 h-12 <?php echo $akses_premium ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500'; ?> rounded-xl flex items-center justify-center text-xl"><i class="fas <?php echo $akses_premium ? 'fa-crown' : 'fa-lock'; ?>"></i></div>
                            <div><p class="text-sm text-gray-500 font-medium">Status Membership</p><h3 class="text-lg font-bold text-gray-800"><?php echo $akses_premium ? 'PREMIUM' : 'FREE PLAN'; ?></h3></div>
                        </div>
                    </div>
                </div>

                <!-- VIEW: VERIFIKASI -->
                <div id="view-verifikasi" class="tab-content hidden animate-fade-in">
                    <header class="mb-8"><h1 class="text-2xl font-bold text-gray-800">Verifikasi UMKM</h1><p class="text-gray-500 text-sm">Validasi pendaftaran pedagang.</p></header>
                    <?php if ( ! $akses_premium ): ?>
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-lg p-8 text-center max-w-2xl mx-auto">
                            <div class="bg-gradient-to-r from-gray-800 to-gray-900 p-8 text-white rounded-xl mb-8">
                                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-lock text-3xl"></i></div>
                                <h2 class="text-2xl font-bold mb-2">Fitur Terkunci</h2>
                                <p class="text-gray-300 text-sm">Upgrade ke Premium untuk membuka fitur Verifikasi UMKM.</p>
                            </div>
                            <?php if ($status_verifikasi == 'pending'): ?>
                                <div class="text-center p-4 bg-yellow-50 rounded-xl text-yellow-800 font-bold"><i class="fas fa-clock"></i> Bukti pembayaran sedang diverifikasi admin.</div>
                            <?php else: ?>
                                <div class="bg-gray-50 p-6 rounded-xl border text-left">
                                    <h3 class="font-bold mb-4">Upload Bukti Pembayaran (Rp <?php echo number_format($harga_premium, 0, ',', '.'); ?>)</h3>
                                    <div class="mb-4 text-sm bg-white p-3 rounded border">
                                        <p><strong>Bank:</strong> <?php echo esc_html($sys_bank_name); ?></p>
                                        <p><strong>Rek:</strong> <?php echo esc_html($sys_bank_account); ?> (a.n <?php echo esc_html($sys_bank_holder); ?>)</p>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data">
                                        <?php wp_nonce_field('upload_bukti_action', 'upload_bukti_nonce'); ?>
                                        <input type="hidden" name="action_upload_bukti" value="1">
                                        <input type="file" name="bukti_bayar" required class="block w-full text-sm mb-4 border rounded bg-white p-2">
                                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 rounded hover:bg-blue-700">Kirim Bukti</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php $umkm_pending = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id_desa = %d AND status_pendaftaran = 'menunggu_desa' ORDER BY created_at DESC", $id_desa)); ?>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <?php if($umkm_pending): ?>
                            <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-500 font-bold text-xs border-b border-gray-100"><tr><th class="px-6 py-4">Toko</th><th class="px-6 py-4">Pemilik</th><th class="px-6 py-4">Alamat</th><th class="px-6 py-4 text-center">Aksi</th></tr></thead><tbody class="divide-y divide-gray-100">
                            <?php foreach($umkm_pending as $u): ?>
                            <tr class="hover:bg-gray-50"><td class="px-6 py-4 font-bold"><?php echo esc_html($u->nama_toko); ?></td><td class="px-6 py-4"><?php echo esc_html($u->nama_pemilik); ?></td><td class="px-6 py-4 truncate max-w-xs"><?php echo esc_html($u->alamat_lengkap); ?></td><td class="px-6 py-4 text-center"><form method="POST" class="inline-flex gap-2"><?php wp_nonce_field('verifikasi_pedagang_action', 'verifikasi_nonce'); ?><input type="hidden" name="id_pedagang" value="<?php echo $u->id; ?>"><button type="submit" name="action_verifikasi" value="1" onclick="this.form.status_keputusan.value='disetujui'" class="bg-green-100 text-green-700 px-3 py-1 rounded font-bold hover:bg-green-200">Terima</button><button type="submit" name="action_verifikasi" value="1" onclick="this.form.status_keputusan.value='ditolak'" class="bg-red-100 text-red-700 px-3 py-1 rounded font-bold hover:bg-red-200">Tolak</button><input type="hidden" name="status_keputusan" value=""></form></td></tr>
                            <?php endforeach; ?>
                            </tbody></table></div>
                            <?php else: ?><div class="p-10 text-center text-gray-400">Tidak ada pendaftaran baru.</div><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- VIEW: DATA UMKM -->
                <div id="view-data-umkm" class="tab-content hidden animate-fade-in">
                    <header class="mb-8 flex justify-between items-center">
                        <div><h1 class="text-2xl font-bold text-gray-800">Data UMKM</h1><p class="text-gray-500 text-sm">Daftar pedagang aktif.</p></div>
                        <div class="text-sm bg-blue-50 text-blue-700 px-4 py-2 rounded-lg border border-blue-100">Total: <strong><?php echo number_format($total_umkm); ?></strong> Mitra</div>
                    </header>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <?php if($umkm_active): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs border-b border-gray-100"><tr><th class="px-6 py-4">Info Toko</th><th class="px-6 py-4">Pemilik & Lokasi</th><th class="px-6 py-4">Statistik</th><th class="px-6 py-4">Kontak</th><th class="px-6 py-4 text-center">Status</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                <?php foreach($umkm_active as $u): $wa_link = 'https://wa.me/' . preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $u->nomor_wa)); $foto_url = !empty($u->foto_profil) ? esc_url($u->foto_profil) : 'https://ui-avatars.com/api/?name='.urlencode($u->nama_toko).'&background=random'; ?>
                                <tr class="hover:bg-gray-50 transition group">
                                    <td class="px-6 py-4"><div class="flex items-center gap-4"><img src="<?php echo $foto_url; ?>" class="w-12 h-12 rounded-xl object-cover border border-gray-200 shadow-sm"><div><div class="font-bold text-gray-800 text-base mb-0.5"><?php echo esc_html($u->nama_toko); ?></div><div class="text-xs text-gray-500">Bergabung: <?php echo date('d M Y', strtotime($u->created_at)); ?></div></div></div></td>
                                    <td class="px-6 py-4"><div class="font-medium text-gray-700 mb-1"><i class="fas fa-user-circle text-gray-400 mr-1"></i> <?php echo esc_html($u->nama_pemilik); ?></div><div class="text-xs text-gray-500 line-clamp-2 w-48" title="<?php echo esc_attr($u->alamat_lengkap); ?>"><i class="fas fa-map-marker-alt text-red-400 mr-1"></i> <?php echo esc_html($u->alamat_lengkap ?: 'Alamat belum diisi'); ?></div></td>
                                    <td class="px-6 py-4"><div class="flex flex-col gap-1"><span class="text-xs font-medium text-gray-600 flex items-center gap-1"><i class="fas fa-star text-yellow-400"></i> Rating: <strong><?php echo number_format($u->rating_toko, 1); ?></strong></span><span class="text-xs font-medium text-gray-600 flex items-center gap-1"><i class="fas fa-receipt text-blue-400"></i> Kuota: <strong><?php echo number_format($u->sisa_transaksi); ?></strong></span></div></td>
                                    <td class="px-6 py-4"><a href="<?php echo $wa_link; ?>" target="_blank" class="inline-flex items-center gap-2 text-green-600 hover:text-green-700 bg-green-50 hover:bg-green-100 px-3 py-1.5 rounded-lg text-xs font-bold transition"><i class="fab fa-whatsapp text-lg"></i> Chat</a></td>
                                    <td class="px-6 py-4 text-center"><span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold <?php echo ($u->status_akun=='aktif')?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>"><span class="w-1.5 h-1.5 rounded-full <?php echo ($u->status_akun=='aktif')?'bg-green-500':'bg-red-500'; ?>"></span> <?php echo ucfirst($u->status_akun); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?><div class="p-16 text-center text-gray-400"><div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fas fa-store-slash"></i></div><h3 class="text-gray-800 font-bold text-lg mb-1">Belum ada data</h3><p>Belum ada UMKM yang terverifikasi.</p></div><?php endif; ?>
                    </div>
                </div>

                <!-- VIEW: KELOLA WISATA -->
                <div id="view-wisata" class="tab-content hidden animate-fade-in">
                    <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div><h1 class="text-2xl font-bold text-gray-800">Objek Wisata</h1><p class="text-gray-500 text-sm">Kelola destinasi wisata.</p></div>
                        <?php $limit_reached = (!$akses_premium && $total_wisata_count >= 2); ?>
                        <?php if($limit_reached): ?>
                            <button onclick="alert('Kuota Penuh! Upgrade ke Premium agar bisa tambahkan wisata.'); switchTab('verifikasi');" class="bg-gray-800 hover:bg-gray-900 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg transition flex items-center gap-2"><i class="fas fa-lock"></i> Tambah Wisata</button>
                        <?php else: ?>
                            <button onclick="openWisataModalNew()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg transition"><i class="fas fa-plus"></i> Tambah</button>
                        <?php endif; ?>
                    </header>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if($wisata_list): foreach($wisata_list as $index => $w): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-lg transition relative">
                            <div class="h-40 bg-gray-100 relative overflow-hidden">
                                <img src="<?php echo !empty($w->foto_utama) ? esc_url($w->foto_utama) : 'https://placehold.co/400x250?text=Wisata'; ?>" class="w-full h-full object-cover">
                                <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-md px-2 py-1 rounded-lg text-[10px] font-bold text-gray-700 shadow-sm uppercase"><?php echo esc_html($w->kategori); ?></div>
                            </div>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-900 text-lg mb-1 truncate"><?php echo esc_html($w->nama_wisata); ?></h3>
                                <p class="text-sm text-gray-500 mb-4 line-clamp-2"><?php echo $w->deskripsi ? strip_tags($w->deskripsi) : '-'; ?></p>
                                <div class="flex items-center justify-between pt-4 border-t border-gray-50">
                                    <span class="text-blue-600 font-bold text-sm bg-blue-50 px-2 py-1 rounded-md"><?php echo ($w->harga_tiket > 0) ? 'Rp ' . number_format($w->harga_tiket, 0, ',', '.') : 'Gratis'; ?></span>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="editWisata(<?php echo $index; ?>)" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center transition"><i class="fas fa-pen text-xs"></i></button>
                                        <?php $del_nonce = wp_create_nonce('hapus_wisata_'.$w->id); ?>
                                        <a href="<?php echo add_query_arg(['action'=>'hapus_wisata','id'=>$w->id,'_wpnonce'=>$del_nonce]); ?>" onclick="return confirm('Yakin ingin menghapus wisata ini?');" class="w-8 h-8 rounded-full bg-red-50 hover:bg-red-100 text-red-500 flex items-center justify-center transition"><i class="fas fa-trash text-xs"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; else: ?>
                        <div class="col-span-full py-16 text-center text-gray-400">Belum ada objek wisata.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- VIEW: KEUANGAN / KOMISI (FITUR BARU) -->
                <div id="view-keuangan" class="tab-content hidden animate-fade-in">
                    <header class="mb-8"><h1 class="text-2xl font-bold text-gray-800">Manajemen Keuangan</h1><p class="text-gray-500 text-sm">Kelola komisi dan pencairan dana desa.</p></header>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <!-- Saldo Card -->
                        <div class="bg-gradient-to-br from-green-500 to-green-700 text-white rounded-3xl p-8 shadow-xl relative overflow-hidden">
                            <div class="relative z-10">
                                <p class="text-green-100 uppercase tracking-widest text-xs font-bold mb-2">Saldo Komisi Tersedia</p>
                                <h2 class="text-4xl font-extrabold mb-6">Rp <?php echo number_format($saldo_tampil, 0, ',', '.'); ?></h2>
                                <div class="flex gap-3">
                                    <button onclick="document.getElementById('modal-withdraw').classList.remove('hidden')" class="bg-white text-green-700 px-6 py-3 rounded-xl font-bold hover:bg-green-50 transition shadow-lg flex items-center gap-2"><i class="fas fa-money-bill-wave"></i> Cairkan Dana</button>
                                    <?php 
                                        $min_wd = get_option('dw_min_withdrawal_amount', 10000); 
                                    ?>
                                    <div class="text-xs text-green-100 flex items-center max-w-[150px] leading-tight">Minimal penarikan Rp <?php echo number_format($min_wd, 0, ',', '.'); ?></div>
                                </div>
                            </div>
                            <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-10 translate-y-10"><i class="fas fa-wallet text-9xl"></i></div>
                        </div>
                        
                        <!-- Info Bank -->
                        <div class="bg-white rounded-3xl border border-gray-200 p-8 shadow-sm">
                            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-university text-gray-400"></i> Rekening Penerimaan</h3>
                            <div class="space-y-4">
                                <?php if($desa_data->nama_bank_desa && $desa_data->no_rekening_desa): ?>
                                    <div><label class="text-xs text-gray-400 uppercase font-bold">Bank</label><p class="font-bold text-gray-800 text-lg"><?php echo esc_html($desa_data->nama_bank_desa); ?></p></div>
                                    <div><label class="text-xs text-gray-400 uppercase font-bold">Nomor Rekening</label><p class="font-mono text-gray-800 text-lg tracking-wider"><?php echo esc_html($desa_data->no_rekening_desa); ?></p></div>
                                    <div><label class="text-xs text-gray-400 uppercase font-bold">Atas Nama</label><p class="text-gray-800"><?php echo esc_html($desa_data->atas_nama_rekening_desa); ?></p></div>
                                    <div class="mt-4 pt-4 border-t border-gray-100"><button onclick="switchTab('profil')" class="text-blue-600 text-sm font-bold hover:underline">Ubah Rekening</button></div>
                                <?php else: ?>
                                    <div class="text-center py-6">
                                        <p class="text-gray-500 text-sm mb-4">Rekening bank belum diatur.</p>
                                        <button onclick="switchTab('profil')" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-200">Atur Rekening</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Riwayat -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50"><h3 class="font-bold text-gray-700">Riwayat Penarikan</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-100 uppercase text-xs">
                                    <tr>
                                        <th class="p-4 pl-6">Tanggal Request</th>
                                        <th class="p-4">Nominal</th>
                                        <th class="p-4">Status</th>
                                        <th class="p-4 text-right pr-6">Tanggal Cair</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if($payout_history): foreach($payout_history as $pay): 
                                        $status_badge = 'bg-gray-100 text-gray-600';
                                        $label = $pay->status;

                                        // Support status dari tabel ledger lama & withdrawals baru
                                        if ($pay->status === 'pending' || $pay->status === 'processing') {
                                            $status_badge = 'bg-yellow-100 text-yellow-700';
                                            $label = 'Diproses';
                                        } elseif ($pay->status === 'paid' || $pay->status === 'completed') {
                                            $status_badge = 'bg-green-100 text-green-700';
                                            $label = 'Berhasil';
                                        } elseif ($pay->status === 'rejected' || $pay->status === 'failed') {
                                            $status_badge = 'bg-red-100 text-red-700';
                                            $label = 'Gagal/Ditolak';
                                        }
                                        
                                        // Handle perbedaan nama kolom tanggal
                                        $date_req = isset($pay->created_at) ? $pay->created_at : $pay->tanggal;
                                        $date_paid = isset($pay->paid_at) ? $pay->paid_at : (isset($pay->updated_at) ? $pay->updated_at : '-');
                                    ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="p-4 pl-6 text-gray-600"><?php echo date('d M Y, H:i', strtotime($date_req)); ?></td>
                                        <td class="p-4 font-bold text-gray-800">Rp <?php echo number_format($pay->amount, 0, ',', '.'); ?></td>
                                        <td class="p-4"><span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?php echo $status_badge; ?>"><?php echo $label; ?></span></td>
                                        <td class="p-4 pr-6 text-right text-gray-500"><?php echo ($date_paid && $date_paid != '-') ? date('d M Y', strtotime($date_paid)) : '-'; ?></td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="p-8 text-center text-gray-400">Belum ada riwayat penarikan.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- VIEW: PROFIL DESA -->
                <div id="view-profil" class="tab-content hidden animate-fade-in">
                    <!-- View Mode -->
                    <div id="profil-view-mode">
                        <header class="mb-6 flex justify-between items-center">
                            <h1 class="text-2xl font-bold text-gray-800">Profil Desa</h1>
                            <button onclick="toggleProfilMode('edit')" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow hover:bg-blue-700 transition"><i class="fas fa-edit"></i> Edit Profil</button>
                        </header>
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden p-6">
                            <div class="flex flex-col md:flex-row gap-6">
                                <div class="w-full md:w-1/3">
                                    <img src="<?php echo $desa_data->foto_sampul ? esc_url($desa_data->foto_sampul) : 'https://placehold.co/400x300?text=Cover'; ?>" class="w-full h-48 object-cover rounded-xl mb-4 shadow-sm border border-gray-100">
                                    <div class="flex items-center gap-4 mb-4">
                                        <img src="<?php echo $desa_data->foto ? esc_url($desa_data->foto) : 'https://placehold.co/100x100?text=Logo'; ?>" class="w-16 h-16 rounded-full border border-gray-200 object-cover shadow-sm">
                                        <div><h2 class="text-xl font-bold text-gray-800 leading-tight"><?php echo esc_html($desa_data->nama_desa); ?></h2><p class="text-gray-500 text-xs"><?php echo esc_html($desa_data->kabupaten); ?></p></div>
                                    </div>
                                    <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 text-center mb-4">
                                        <p class="text-xs font-bold text-blue-500 uppercase mb-1">Kode ID Wilayah</p>
                                        <?php if($desa_data->kode_referral): ?>
                                            <div class="text-xl font-mono font-bold text-blue-800 tracking-wider"><?php echo esc_html($desa_data->kode_referral); ?></div>
                                        <?php else: ?>
                                            <div class="text-sm text-red-500 italic">Belum dibuat. Silakan lengkapi wilayah.</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- [BARU] Info Kontak Desa -->
                                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 text-center mb-4">
                                        <p class="text-xs font-bold text-gray-400 uppercase mb-1">WhatsApp Desa</p>
                                        <?php if($desa_data->nomor_wa): ?>
                                            <div class="text-lg font-bold text-green-600 flex items-center justify-center gap-1"><i class="fab fa-whatsapp"></i> <?php echo esc_html($desa_data->nomor_wa); ?></div>
                                        <?php else: ?>
                                            <div class="text-sm text-gray-400 italic">Belum diatur</div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                                <div class="w-full md:w-2/3 space-y-6">
                                    <div class="bg-gray-50 p-6 rounded-xl border border-gray-100"><label class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 block">Deskripsi Desa</label><p class="text-gray-700 leading-relaxed text-sm"><?php echo nl2br(esc_html($desa_data->deskripsi ?: 'Belum ada deskripsi.')); ?></p></div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="bg-blue-50 p-5 rounded-xl border border-blue-100"><h3 class="font-bold text-blue-800 mb-3 flex items-center gap-2"><i class="fas fa-wallet"></i> Rekening Desa</h3><div class="space-y-2 text-sm text-blue-900"><div class="flex justify-between border-b border-blue-200 pb-1"><span>Bank:</span> <strong><?php echo esc_html($desa_data->nama_bank_desa ?: '-'); ?></strong></div><div class="flex justify-between border-b border-blue-200 pb-1"><span>No. Rek:</span> <strong class="font-mono"><?php echo esc_html($desa_data->no_rekening_desa ?: '-'); ?></strong></div><div class="flex justify-between"><span>A.N:</span> <strong><?php echo esc_html($desa_data->atas_nama_rekening_desa ?: '-'); ?></strong></div></div></div>
                                        <div class="bg-green-50 p-5 rounded-xl border border-green-100"><h3 class="font-bold text-green-800 mb-3 flex items-center gap-2"><i class="fas fa-map-marker-alt"></i> Lokasi</h3><div class="space-y-1 text-sm text-green-900"><p><?php echo esc_html($desa_data->alamat_lengkap ?: '-'); ?></p><p class="text-xs mt-2 opacity-75"><?php echo esc_html($desa_data->kelurahan); ?>, <?php echo esc_html($desa_data->kecamatan); ?></p><p class="text-xs opacity-75"><?php echo esc_html($desa_data->kabupaten); ?>, <?php echo esc_html($desa_data->provinsi); ?></p></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Mode -->
                    <div id="profil-edit-mode" class="hidden">
                        <header class="mb-6 flex justify-between items-center"><h1 class="text-2xl font-bold text-gray-800">Edit Profil</h1><button onclick="toggleProfilMode('view')" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-xl text-sm font-bold shadow hover:bg-gray-300 transition">Batal</button></header>
                        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 space-y-8">
                            <?php wp_nonce_field('save_profil_desa_action', 'profil_desa_nonce'); ?>
                            <input type="hidden" name="save_profil_desa" value="1">
                            
                            <div class="grid md:grid-cols-3 gap-8">
                                <div class="space-y-4">
                                    <div><label class="block text-xs font-bold text-gray-500 uppercase mb-2">Logo Desa</label><div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:bg-gray-50 cursor-pointer relative group"><input type="file" name="foto_desa" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="previewImage(this, 'prev_logo')"><img id="prev_logo" src="<?php echo $desa_data->foto ? esc_url($desa_data->foto) : 'https://placehold.co/100x100?text=Logo'; ?>" class="w-20 h-20 mx-auto rounded-full object-cover mb-2"><p class="text-xs text-blue-500 font-bold group-hover:underline">Ganti Logo</p></div></div>
                                    <div><label class="block text-xs font-bold text-gray-500 uppercase mb-2">QRIS (Opsional)</label><div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:bg-gray-50 cursor-pointer relative group"><input type="file" name="qris_desa" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="previewImage(this, 'prev_qris')"><img id="prev_qris" src="<?php echo $desa_data->qris_image_url_desa ? esc_url($desa_data->qris_image_url_desa) : 'https://placehold.co/100x100?text=QRIS'; ?>" class="w-20 h-20 mx-auto object-contain mb-2"><p class="text-xs text-blue-500 font-bold group-hover:underline">Upload QRIS</p></div></div>
                                </div>
                                <div class="md:col-span-2 space-y-6">
                                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Nama Desa</label><input type="text" name="nama_desa" value="<?php echo esc_attr($desa_data->nama_desa); ?>" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 outline-none"></div>
                                    
                                    <!-- [BARU] Input Nomor WA -->
                                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Nomor WhatsApp (Kontak Desa)</label><input type="text" name="nomor_wa" value="<?php echo esc_attr($desa_data->nomor_wa); ?>" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="08xxxxxxxxxx"></div>

                                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Foto Sampul</label><input type="file" name="foto_sampul" class="w-full border border-gray-300 rounded-lg p-2 text-sm bg-gray-50"></div>
                                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Deskripsi</label><textarea name="deskripsi" rows="4" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 outline-none"><?php echo esc_textarea($desa_data->deskripsi); ?></textarea></div>
                                </div>
                            </div>
                            
                            <div class="pt-6 border-t border-gray-100">
                                <h3 class="text-lg font-bold text-gray-800 mb-4">Lokasi Wilayah</h3>
                                <div id="region-data" data-prov="<?php echo esc_attr($desa_data->api_provinsi_id); ?>" data-kota="<?php echo esc_attr($desa_data->api_kabupaten_id); ?>" data-kec="<?php echo esc_attr($desa_data->api_kecamatan_id); ?>" data-desa="<?php echo esc_attr($desa_data->api_kelurahan_id); ?>"></div>
                                <div class="grid grid-cols-2 gap-4 mb-4"><div><label class="text-xs font-bold block mb-1">Provinsi</label><select name="api_provinsi_id" id="dw_provinsi" class="w-full border rounded-lg p-2.5 bg-white"><option>Loading...</option></select></div><div><label class="text-xs font-bold block mb-1">Kabupaten</label><select name="api_kabupaten_id" id="dw_kota" class="w-full border rounded-lg p-2.5 bg-white" disabled></select></div></div>
                                <div class="grid grid-cols-2 gap-4 mb-4"><div><label class="text-xs font-bold block mb-1">Kecamatan</label><select name="api_kecamatan_id" id="dw_kecamatan" class="w-full border rounded-lg p-2.5 bg-white" disabled></select></div><div><label class="text-xs font-bold block mb-1">Kelurahan</label><select name="api_kelurahan_id" id="dw_desa" class="w-full border rounded-lg p-2.5 bg-white" disabled></select></div></div>
                                <input type="hidden" name="provinsi_nama" id="input_provinsi_nama" value="<?php echo esc_attr($desa_data->provinsi); ?>"><input type="hidden" name="kabupaten_nama" id="input_kabupaten_name" value="<?php echo esc_attr($desa_data->kabupaten); ?>"><input type="hidden" name="kecamatan_nama" id="input_kecamatan_name" value="<?php echo esc_attr($desa_data->kecamatan); ?>"><input type="hidden" name="kelurahan_nama" id="input_kelurahan_name" value="<?php echo esc_attr($desa_data->kelurahan); ?>">
                                <div><label class="text-sm font-bold block mb-1">Alamat Lengkap</label><textarea name="alamat_lengkap" rows="2" class="w-full border border-gray-300 rounded-lg p-2.5"><?php echo esc_textarea($desa_data->alamat_lengkap); ?></textarea></div>
                            </div>

                            <div class="pt-6 border-t border-gray-100"><h3 class="text-lg font-bold text-gray-800 mb-4">Rekening Penerimaan</h3><div class="grid md:grid-cols-3 gap-4"><div><label class="block text-xs font-bold uppercase text-gray-500 mb-1">Nama Bank</label><input type="text" name="nama_bank_desa" value="<?php echo esc_attr($desa_data->nama_bank_desa); ?>" class="w-full border rounded-lg p-2.5"></div><div><label class="block text-xs font-bold uppercase text-gray-500 mb-1">No. Rekening</label><input type="text" name="no_rekening_desa" value="<?php echo esc_attr($desa_data->no_rekening_desa); ?>" class="w-full border rounded-lg p-2.5 font-mono"></div><div><label class="block text-xs font-bold uppercase text-gray-500 mb-1">Atas Nama</label><input type="text" name="atas_nama_rekening_desa" value="<?php echo esc_attr($desa_data->atas_nama_rekening_desa); ?>" class="w-full border rounded-lg p-2.5"></div></div></div>
                            <div class="pt-6 text-right"><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg transition flex items-center gap-2 ml-auto"><i class="fas fa-save"></i> Simpan Perubahan</button></div>
                        </form>
                    </div>
                </div>
            </main>
        </div>

        <!-- Modal Wisata & JS Scripts -->
        <div id="modal-wisata" class="fixed inset-0 z-[60] hidden transition-opacity duration-300">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeWisataModal()"></div>
            <div class="absolute inset-y-0 right-0 w-full max-w-lg bg-white shadow-2xl transform transition-transform translate-x-full duration-300 flex flex-col" id="modal-wisata-panel">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white z-10"><h2 class="text-xl font-bold text-gray-800" id="mw_title">Tambah Wisata</h2><button onclick="closeWisataModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times text-xl"></i></button></div>
                <div class="flex-1 overflow-y-auto p-6">
                    <form method="POST" enctype="multipart/form-data" id="form-wisata">
                        <?php wp_nonce_field('save_wisata_action', 'wisata_nonce'); ?><input type="hidden" name="save_wisata" value="1"><input type="hidden" name="wisata_id" id="mw_id" value="">
                        <div class="space-y-4">
                            <div><label class="block text-sm font-bold mb-1">Nama Wisata</label><input type="text" name="nama_wisata" id="mw_nama" required class="w-full border rounded px-3 py-2"></div>
                            <div id="mw_preview_box" class="hidden mb-2"><label class="text-xs font-bold text-gray-500">Foto Saat Ini:</label><img id="mw_preview_img" src="" class="h-32 rounded border mt-1 object-cover w-full"></div>
                            <div><label class="block text-sm font-bold mb-1">Upload Foto</label><input type="file" name="foto_utama" class="w-full text-sm border rounded p-1"></div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-bold mb-1">Kategori</label><select name="kategori" id="mw_kategori" class="w-full border rounded px-3 py-2"><?php foreach($kategori_wisata as $k) echo "<option value='$k'>$k</option>"; ?></select></div><div><label class="block text-sm font-bold mb-1">Harga (Rp)</label><input type="number" name="harga_tiket" id="mw_harga" class="w-full border rounded px-3 py-2"></div></div>
                            <div><label class="block text-sm font-bold mb-1">Jam Buka</label><input type="text" name="jam_buka" id="mw_jam" class="w-full border rounded px-3 py-2"></div>
                            
                            <!-- [BARU] Auto-fill Kontak -->
                            <div>
                                <label class="block text-sm font-bold mb-1">Kontak Pengelola</label>
                                <!-- Kita simpan nomor wa desa di data attribute untuk diambil JS -->
                                <input type="text" name="kontak_pengelola" id="mw_kontak" class="w-full border rounded px-3 py-2" data-default-wa="<?php echo esc_attr($desa_data->nomor_wa); ?>" placeholder="Kosongkan untuk pakai nomor desa">
                            </div>
                            
                            <div><label class="block text-sm font-bold mb-1">Deskripsi</label><textarea name="deskripsi" id="mw_deskripsi" rows="3" class="w-full border rounded px-3 py-2"></textarea></div>
                            <div><label class="block text-sm font-bold mb-1">Fasilitas</label><textarea name="fasilitas" id="mw_fasilitas" rows="2" class="w-full border rounded px-3 py-2"></textarea></div>
                            <div><label class="block text-sm font-bold mb-1">Link Maps</label><input type="url" name="lokasi_maps" id="mw_maps" class="w-full border rounded px-3 py-2"></div>
                        </div>
                        <div class="mt-6 pt-4 border-t"><button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded hover:bg-blue-700 shadow-lg">Simpan Data</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Withdraw -->
        <div id="modal-withdraw" class="fixed inset-0 z-[60] hidden">
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="document.getElementById('modal-withdraw').classList.add('hidden')"></div>
            <div class="absolute inset-0 flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl w-full max-w-sm p-8 shadow-2xl transform scale-100 transition-all text-center">
                    <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4"><i class="fas fa-money-bill-wave"></i></div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Tarik Dana Desa</h3>
                    <p class="text-gray-500 text-sm mb-6">Saldo tersedia: <strong>Rp <?php echo number_format($saldo_tampil, 0, ',', '.'); ?></strong></p>
                    
                    <form method="POST">
                        <?php wp_nonce_field('withdraw_action', 'withdraw_nonce'); ?>
                        <input type="hidden" name="action_withdraw" value="1">
                        <div class="mb-6 text-left">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nominal Penarikan</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400 font-bold">Rp</span>
                                <input type="text" name="nominal_withdraw" class="w-full border border-gray-300 rounded-xl pl-10 pr-3 py-3 font-bold text-lg focus:ring-2 focus:ring-green-500 outline-none" placeholder="0" onkeyup="let v=this.value.replace(/[^0-9]/g,''); this.value=new Intl.NumberFormat('id-ID').format(v);">
                            </div>
                            <p class="text-[10px] text-gray-400 mt-2 italic">*Dana akan ditransfer ke rekening desa yang terdaftar.</p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" onclick="document.getElementById('modal-withdraw').classList.add('hidden')" class="flex-1 py-3 text-gray-600 font-bold bg-gray-100 rounded-xl hover:bg-gray-200 transition">Batal</button>
                            <button type="submit" class="flex-1 py-3 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition shadow-lg">Cairkan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            var wisataData = <?php echo json_encode($wisata_list); ?>;
            var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";

            function toggleSidebar() {
                const sidebar = document.getElementById('dashboard-sidebar');
                const backdrop = document.getElementById('sidebar-backdrop');
                if (sidebar && backdrop) {
                    if (sidebar.classList.contains('-translate-x-full')) {
                        sidebar.classList.remove('-translate-x-full');
                        backdrop.classList.remove('hidden');
                        setTimeout(() => backdrop.classList.remove('opacity-0'), 10);
                    } else {
                        sidebar.classList.add('-translate-x-full');
                        backdrop.classList.add('opacity-0');
                        setTimeout(() => backdrop.classList.add('hidden'), 300);
                    }
                }
            }

            function switchTab(tabName) {
                document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
                document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active-tab'));
                const target = document.getElementById('view-' + tabName);
                const nav = document.getElementById('nav-' + tabName);
                if(target) target.classList.remove('hidden');
                if(nav) nav.classList.add('active-tab');
                
                if(window.innerWidth < 768) {
                      const sidebar = document.getElementById('dashboard-sidebar');
                      if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                          toggleSidebar();
                      }
                }
            }

            function toggleProfilMode(mode) {
                document.getElementById('profil-view-mode').classList.toggle('hidden', mode === 'edit');
                document.getElementById('profil-edit-mode').classList.toggle('hidden', mode !== 'edit');
            }

            function previewImage(input, previewId) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) { document.getElementById(previewId).src = e.target.result; }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            const mw = document.getElementById('modal-wisata');
            const mp = document.getElementById('modal-wisata-panel');
            
            function openWisataModalNew() {
                resetForm();
                if(document.getElementById('mw_title')) document.getElementById('mw_title').innerText = 'Tambah Wisata';
                const kontakField = document.getElementById('mw_kontak');
                if(kontakField) {
                    const defaultWA = kontakField.getAttribute('data-default-wa');
                    if(defaultWA) kontakField.value = defaultWA;
                }
                mw.classList.remove('hidden');
                setTimeout(() => mp.classList.remove('translate-x-full'), 10);
            }

            function editWisata(index) {
                resetForm();
                var data = wisataData[index];
                if(data) {
                    const setVal = (id, val) => { const el = document.getElementById(id); if(el) el.value = val || ''; };
                    if(document.getElementById('mw_title')) document.getElementById('mw_title').innerText = 'Edit Wisata';
                    setVal('mw_id', data.id); setVal('mw_nama', data.nama_wisata); setVal('mw_kategori', data.kategori); setVal('mw_harga', data.harga_tiket); setVal('mw_jam', data.jam_buka); setVal('mw_kontak', data.kontak_pengelola); setVal('mw_deskripsi', data.deskripsi); setVal('mw_fasilitas', data.fasilitas); setVal('mw_maps', data.lokasi_maps);
                    const previewBox = document.getElementById('mw_preview_box');
                    const previewImg = document.getElementById('mw_preview_img');
                    if(data.foto_utama && previewBox && previewImg) { previewBox.classList.remove('hidden'); previewImg.src = data.foto_utama; }
                    mw.classList.remove('hidden'); setTimeout(() => mp.classList.remove('translate-x-full'), 10);
                }
            }
            
            function resetForm() {
                const form = document.getElementById('form-wisata'); if(form) form.reset();
                const idField = document.getElementById('mw_id'); if(idField) idField.value = ''; 
                const previewBox = document.getElementById('mw_preview_box'); if(previewBox) previewBox.classList.add('hidden');
            }
            
            function closeWisataModal() { mp.classList.add('translate-x-full'); setTimeout(() => mw.classList.add('hidden'), 300); }

            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('Link berhasil disalin!');
                }, function(err) {
                    alert('Gagal menyalin link.');
                });
            }

            // Region Logic
            jQuery(document).ready(function($) {
                var els = { prov: $('#dw_provinsi'), kota: $('#dw_kota'), kec: $('#dw_kecamatan'), desa: $('#dw_desa') };
                var data = $('#region-data').data();
                function loadR(act, pid, el, sel, cb) {
                    el.html('<option>Loading...</option>').prop('disabled', true);
                    var p = { action: act };
                    if(act=='dw_fetch_regencies') p.province_id = pid; if(act=='dw_fetch_districts') p.regency_id = pid; if(act=='dw_fetch_villages') p.district_id = pid;
                    $.get(ajaxurl, p, function(res){
                        if(res.success) {
                            var o = '<option value="">-- Pilih --</option>';
                            $.each(res.data.data||res.data, function(i,v){ var id=v.id||v.code; o+='<option value="'+id+'" '+(id==sel?'selected':'')+'>'+(v.name||v.nama)+'</option>'; });
                            el.html(o).prop('disabled', false); if(cb) cb();
                        }
                    });
                }
                function setText(el, target) { $(target).val($(el).find('option:selected').text()); }
                if(data.prov) {
                    loadR('dw_fetch_provinces', null, els.prov, data.prov, function(){
                        loadR('dw_fetch_regencies', data.prov, els.kota, data.kota, function(){
                            if(data.kota) loadR('dw_fetch_districts', data.kota, els.kec, data.kec, function(){
                                if(data.kec) loadR('dw_fetch_villages', data.kec, els.kel, data.kel);
                            });
                        });
                    });
                } else { loadR('dw_fetch_provinces', null, els.prov, null); }
                els.prov.change(function(){ setText(this,'#input_provinsi_nama'); loadR('dw_fetch_regencies', $(this).val(), els.kota, null); els.kota.val(''); els.kec.empty().prop('disabled',true); els.desa.empty().prop('disabled',true); });
                els.kota.change(function(){ setText(this,'#input_kabupaten_name'); loadR('dw_fetch_districts', $(this).val(), els.kec, null); els.kec.val(''); els.desa.empty().prop('disabled',true); });
                els.kec.change(function(){ setText(this,'#input_kecamatan_name'); loadR('dw_fetch_villages', $(this).val(), els.desa, null); });
                els.desa.change(function(){ setText(this,'#input_kelurahan_name'); });
            });
        </script>
        <?php
        // 2. Panggil Footer Tema (Hanya jika belum ada)
        if ( ! did_action( 'get_footer' ) ) {
            get_footer();
        }

        return ob_get_clean();
    }
}

DW_Shortcode_Desa::init();