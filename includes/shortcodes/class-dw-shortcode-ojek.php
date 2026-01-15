<?php
// includes/shortcodes/class-dw-shortcode-ojek.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcode_Ojek {

    public function __construct() {
        add_shortcode( 'dw_dashboard_ojek', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        // 1. Cek Login
        if ( ! is_user_logged_in() ) {
            return '<script>window.location.href="' . wp_login_url( get_permalink() ) . '";</script>';
        }

        // 2. Cek Capability
        if ( ! current_user_can( 'dw_view_orders' ) && ! current_user_can( 'administrator' ) ) {
            return '<script>window.location.href="' . home_url() . '";</script>';
        }

        ob_start();

        // Panggil Header Tema jika belum dipanggil
        if ( ! did_action( 'get_header' ) ) {
            get_header();
        }
        ?>

        <!-- CDN Dependencies (Jika tema tidak memuat) -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <div class="bg-gray-50 min-h-screen font-sans flex dw-ojek-dashboard">
            
            <!-- SIDEBAR -->
            <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col fixed h-full z-20" id="ojek-sidebar">
                <div class="p-6 border-b border-gray-100 flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-500 text-white rounded-xl flex items-center justify-center text-xl shadow-lg shadow-orange-500/30">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-800 leading-tight">Driver</h2>
                        <p class="text-[10px] text-gray-400">Ojek Wisata</p>
                    </div>
                </div>

                <!-- STATUS TOGGLE -->
                <div class="p-4">
                    <div class="bg-gray-100 p-1 rounded-xl flex">
                        <button class="flex-1 py-2 text-xs font-bold rounded-lg bg-white shadow-sm text-green-600 transition">Online</button>
                        <button class="flex-1 py-2 text-xs font-bold rounded-lg text-gray-500 hover:text-gray-700 transition">Offline</button>
                    </div>
                </div>

                <nav class="flex-1 p-4 space-y-1">
                    <button onclick="switchTab('orderan')" class="nav-item w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-600 rounded-xl hover:bg-orange-50 hover:text-orange-600 transition active-tab" id="nav-orderan">
                        <i class="fas fa-list-ul w-5 text-center"></i> Order Masuk
                        <span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full ml-auto">2</span>
                    </button>
                    <button onclick="switchTab('riwayat')" class="nav-item w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-600 rounded-xl hover:bg-orange-50 hover:text-orange-600 transition" id="nav-riwayat">
                        <i class="fas fa-history w-5 text-center"></i> Riwayat
                    </button>
                    <button onclick="switchTab('profil')" class="nav-item w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-600 rounded-xl hover:bg-orange-50 hover:text-orange-600 transition" id="nav-profil">
                        <i class="fas fa-id-card w-5 text-center"></i> Profil Driver
                    </button>
                </nav>

                <div class="p-4 border-t border-gray-100">
                    <a href="<?php echo wp_logout_url(home_url()); ?>" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 rounded-xl hover:bg-red-50 transition w-full">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i> Keluar
                    </a>
                </div>
            </aside>

            <!-- MOBILE HEADER -->
            <div class="md:hidden fixed top-0 left-0 right-0 h-16 bg-white border-b border-gray-200 z-30 flex items-center justify-between px-4 shadow-sm">
                <span class="font-bold text-gray-800 flex items-center gap-2"><i class="fas fa-motorcycle text-orange-500"></i> Driver Panel</span>
                <button onclick="toggleSidebar()" class="text-gray-600 p-2">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <!-- MAIN CONTENT -->
            <main class="flex-1 md:ml-64 p-4 md:p-8 pt-20 md:pt-8 overflow-y-auto h-screen">
                
                <!-- TAB: ORDER MASUK -->
                <div id="view-orderan" class="tab-content animate-fade-in">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">Order Masuk</h1>
                    
                    <!-- Dummy Data Order -->
                    <div class="space-y-4">
                        <!-- Order Card 1 -->
                        <div class="bg-white p-5 rounded-2xl border border-orange-200 shadow-sm relative overflow-hidden">
                            <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-orange-500"></div>
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded">Antar Paket</span>
                                    <h3 class="font-bold text-lg text-gray-800 mt-1">Warung Bu Dewi -> Hotel Melati</h3>
                                </div>
                                <span class="font-bold text-xl text-orange-600">Rp 15.000</span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                                <span class="flex items-center gap-1"><i class="far fa-clock"></i> 2 menit lalu</span>
                                <span class="flex items-center gap-1"><i class="fas fa-route"></i> 2.5 km</span>
                            </div>
                            <div class="flex gap-2">
                                <button class="flex-1 bg-green-600 text-white font-bold py-4 rounded-xl hover:bg-green-700 transition shadow-lg shadow-green-500/20 text-lg btn-terima-order">Terima Order</button>
                                <button class="px-4 py-2.5 border border-red-200 text-red-600 font-bold rounded-xl hover:bg-red-50">Tolak</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: RIWAYAT -->
                <div id="view-riwayat" class="tab-content hidden animate-fade-in">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">Riwayat Perjalanan</h1>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center text-gray-400">
                        <i class="fas fa-history text-4xl mb-3 opacity-30"></i>
                        <p>Belum ada riwayat perjalanan.</p>
                    </div>
                </div>

                <!-- TAB: PROFIL (Placeholder) -->
                <div id="view-profil" class="tab-content hidden animate-fade-in">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">Profil Driver</h1>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                        <p class="text-gray-500">Fitur profil sedang dalam pengembangan.</p>
                    </div>
                </div>

            </main>
        </div>

        <style>
        .active-tab { background-color: #fff7ed; color: #f97316; border-right: 3px solid #f97316; }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        </style>

        <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active-tab'));
            
            const targetView = document.getElementById('view-' + tabName);
            const targetNav = document.getElementById('nav-' + tabName);

            if(targetView) targetView.classList.remove('hidden');
            if(targetNav) targetNav.classList.add('active-tab');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('ojek-sidebar');
            if(sidebar) {
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('flex');
            }
        }
        </script>

        <?php
        // Panggil Footer Tema jika belum dipanggil
        if ( ! did_action( 'get_footer' ) ) {
            get_footer();
        }
        
        return ob_get_clean();
    }
}