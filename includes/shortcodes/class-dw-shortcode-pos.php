<?php
// includes/shortcodes/class-dw-shortcode-pos.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcode_POS {

    public function __construct() {
        add_shortcode( 'dw_pos', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        // 1. Cek Login & Hak Akses
        if ( ! is_user_logged_in() ) return '<div class="p-4 text-red-500 font-sans">Silakan login untuk mengakses kasir.</div>';
        
        if ( ! current_user_can( 'dw_manage_pesanan' ) && ! current_user_can( 'administrator' ) && ! current_user_can( 'pedagang' ) ) {
            return '<div class="p-4 text-red-500 font-sans">Akses ditolak. Halaman ini khusus untuk Pedagang.</div>';
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        $table_produk   = $wpdb->prefix . 'dw_produk';

        // Ambil Data Pedagang
        $pedagang = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id_user = %d", $user_id));
        if (!$pedagang) return '<div class="p-4 text-red-500 font-sans">Data toko tidak ditemukan. Silakan daftar sebagai pedagang terlebih dahulu.</div>';

        // Ambil Produk Toko
        $produk_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_produk WHERE id_pedagang = %d AND status='aktif' ORDER BY nama_produk ASC", $pedagang->id));

        ob_start();
        ?>
        <!-- Load Dependencies (CDN) -->
        <!-- Pastikan ini dimuat karena kita tidak memanggil get_header() -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            tailwind.config = {
                theme: { extend: { colors: { primary: '#16a34a', secondary: '#1e293b' } } }
            }
        </script>

        <style>
            /* Custom Scrollbar untuk POS */
            .pos-scroll::-webkit-scrollbar { width: 6px; }
            .pos-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
            .pos-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            .pos-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
            .fade-in { animation: fadeIn 0.3s ease-in-out; }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            
            /* Reset dasar untuk memastikan fullscreen */
            body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; }
        </style>

        <!-- Container Utama POS (Fullscreen) -->
        <div class="bg-gray-100 h-screen w-screen fixed top-0 left-0 z-[9999] flex flex-col md:flex-row font-sans text-slate-800 overflow-hidden">
            
            <!-- KIRI: Katalog Produk -->
            <div class="flex-1 flex flex-col h-full relative border-r border-gray-200">
                <!-- Header Katalog -->
                <div class="bg-white p-4 border-b border-gray-200 flex justify-between items-center shadow-sm z-10">
                    <div>
                        <h2 class="font-bold text-lg text-gray-800 flex items-center">
                            <i class="fas fa-store text-blue-600 mr-2"></i> <?php echo esc_html($pedagang->nama_toko); ?>
                        </h2>
                        <p class="text-xs text-gray-500">Kasir / Point of Sale</p>
                    </div>
                    <div class="flex gap-3">
                        <div class="relative w-64 hidden md:block">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" id="pos-search" placeholder="Cari produk (Nama/SKU)..." class="w-full pl-10 pr-4 py-2 bg-gray-100 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white transition" onkeyup="filterProducts()">
                        </div>
                        <a href="<?php echo home_url('/dashboard-toko'); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 transition">
                            <i class="fas fa-arrow-left"></i> <span class="hidden sm:inline">Keluar</span>
                        </a>
                    </div>
                </div>

                <!-- Grid Produk -->
                <div class="flex-1 overflow-y-auto p-4 pos-scroll bg-gray-50">
                    <!-- Search Mobile -->
                    <div class="relative w-full mb-4 md:hidden">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        <input type="text" id="pos-search-mobile" placeholder="Cari produk..." class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 transition" onkeyup="document.getElementById('pos-search').value=this.value; filterProducts();">
                    </div>

                    <?php if($produk_list): ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4" id="product-grid">
                            <?php foreach($produk_list as $p): ?>
                            <div class="bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer transition-all pos-card product-item group relative" 
                                 onclick='addToCart(<?php echo json_encode($p); ?>)'
                                 data-name="<?php echo strtolower($p->nama_produk); ?>">
                                
                                <div class="h-32 bg-gray-100 rounded-lg mb-3 overflow-hidden relative">
                                    <?php if($p->foto_utama): ?>
                                        <img src="<?php echo esc_url($p->foto_utama); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500" loading="lazy">
                                    <?php else: ?>
                                        <div class="flex items-center justify-center h-full text-gray-300"><i class="fas fa-image text-2xl"></i></div>
                                    <?php endif; ?>
                                    
                                    <div class="absolute top-2 right-2 bg-black/60 text-white text-[10px] px-2 py-0.5 rounded-md backdrop-blur font-medium">
                                        Stok: <?php echo $p->stok; ?>
                                    </div>
                                    
                                    <!-- Overlay Add -->
                                    <div class="absolute inset-0 bg-blue-600/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                                        <div class="bg-blue-600 text-white w-10 h-10 rounded-full flex items-center justify-center shadow-lg transform scale-0 group-hover:scale-100 transition duration-300">
                                            <i class="fas fa-plus"></i>
                                        </div>
                                    </div>
                                </div>
                                <h4 class="font-bold text-sm text-gray-800 mb-1 leading-snug line-clamp-2 min-h-[2.5rem]"><?php echo esc_html($p->nama_produk); ?></h4>
                                <p class="text-blue-600 font-bold text-sm">Rp <?php echo number_format($p->harga, 0, ',', '.'); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-full text-gray-400">
                            <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mb-4"><i class="fas fa-box-open text-4xl text-gray-400"></i></div>
                            <h3 class="text-lg font-bold text-gray-600">Belum ada produk</h3>
                            <p class="text-sm">Tambahkan produk di dashboard terlebih dahulu.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KANAN: Keranjang / Struk -->
            <div class="w-full md:w-[400px] bg-white flex flex-col h-full shadow-2xl z-20 relative">
                <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center shadow-sm">
                    <h3 class="font-bold text-gray-800 text-lg"><i class="fas fa-shopping-cart text-blue-600 mr-2"></i>Pesanan</h3>
                    <button onclick="clearCart()" class="text-xs bg-red-100 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-200 transition font-bold flex items-center gap-1">
                        <i class="fas fa-trash"></i> Reset
                    </button>
                </div>

                <!-- List Item -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3 pos-scroll bg-white" id="cart-items-container">
                    <!-- Cart items injected via JS -->
                    <div id="empty-cart-msg" class="flex flex-col items-center justify-center h-full text-gray-400 opacity-60">
                        <i class="fas fa-cash-register text-6xl mb-4 text-gray-200"></i>
                        <p class="font-medium">Keranjang masih kosong</p>
                        <p class="text-xs">Pilih produk di sebelah kiri</p>
                    </div>
                </div>

                <!-- Total & Action -->
                <div class="p-5 border-t border-gray-200 bg-gray-50 shadow-[0_-5px_20px_rgba(0,0,0,0.05)]">
                    <div class="space-y-2 mb-5">
                        <div class="flex justify-between text-gray-600 text-sm"><span>Subtotal</span><span id="subtotal" class="font-mono">Rp 0</span></div>
                        <div class="flex justify-between font-bold text-xl text-gray-900 pt-2 border-t border-gray-200 mt-2">
                            <span>Total</span>
                            <span id="grand-total" class="text-blue-600">Rp 0</span>
                        </div>
                    </div>
                    
                    <button onclick="openPaymentModal()" id="btn-pay" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all transform active:scale-[0.98] disabled:bg-gray-300 disabled:shadow-none disabled:cursor-not-allowed flex items-center justify-center gap-2 text-lg" disabled>
                        <span>Bayar Sekarang</span> <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL PEMBAYARAN -->
        <div id="modal-payment" class="fixed inset-0 z-[10000] hidden transition-opacity duration-300">
            <div class="absolute inset-0 bg-gray-900/80 backdrop-blur-sm" onclick="closePaymentModal()"></div>
            <div class="absolute inset-x-0 bottom-0 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 w-full md:w-[480px] bg-white md:rounded-3xl rounded-t-3xl shadow-2xl p-6 transform transition-all scale-100">
                
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900">Pembayaran</h3>
                        <p class="text-gray-500 text-sm mt-1">Selesaikan transaksi ini.</p>
                    </div>
                    <button onclick="closePaymentModal()" class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition"><i class="fas fa-times"></i></button>
                </div>

                <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 mb-6 text-center">
                     <p class="text-xs text-blue-600 uppercase font-bold tracking-wider mb-1">Total Tagihan</p>
                     <p class="text-3xl font-black text-blue-700" id="modal-total-display">Rp 0</p>
                </div>

                <!-- Metode Bayar -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <label class="cursor-pointer">
                        <input type="radio" name="pay_method" value="tunai" class="peer sr-only" checked onchange="togglePaymentInput()">
                        <div class="p-4 border-2 border-gray-200 rounded-2xl peer-checked:border-green-500 peer-checked:bg-green-50 text-center transition hover:bg-gray-50 flex flex-col items-center gap-2 h-full">
                            <div class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-lg"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="font-bold text-gray-800">Tunai</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="pay_method" value="qris" class="peer sr-only" onchange="togglePaymentInput()">
                        <div class="p-4 border-2 border-gray-200 rounded-2xl peer-checked:border-blue-500 peer-checked:bg-blue-50 text-center transition hover:bg-gray-50 flex flex-col items-center gap-2 h-full">
                            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-lg"><i class="fas fa-qrcode"></i></div>
                            <div class="font-bold text-gray-800">QRIS</div>
                        </div>
                    </label>
                </div>

                <!-- Input Tunai -->
                <div id="cash-input-group" class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Uang Diterima</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-lg">Rp</span>
                        <input type="text" id="pay-amount" class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl text-2xl font-bold text-gray-800 focus:ring-2 focus:ring-green-500 focus:bg-white outline-none transition" placeholder="0" onkeyup="formatInputRupiah(this); calculateChange()">
                    </div>
                    
                    <!-- Quick Money Buttons -->
                    <div class="flex gap-2 mt-3 overflow-x-auto pb-2 no-scrollbar px-1">
                        <button type="button" onclick="setCash(10000)" class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-gray-50 hover:border-gray-300 shadow-sm whitespace-nowrap transition">10rb</button>
                        <button type="button" onclick="setCash(20000)" class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-gray-50 hover:border-gray-300 shadow-sm whitespace-nowrap transition">20rb</button>
                        <button type="button" onclick="setCash(50000)" class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-gray-50 hover:border-gray-300 shadow-sm whitespace-nowrap transition">50rb</button>
                        <button type="button" onclick="setCash(100000)" class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-gray-50 hover:border-gray-300 shadow-sm whitespace-nowrap transition">100rb</button>
                        <button type="button" onclick="setCash('exact')" class="bg-blue-50 border border-blue-200 text-blue-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-blue-100 shadow-sm whitespace-nowrap transition">Uang Pas</button>
                    </div>

                    <div class="mt-4 flex justify-between items-center bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <span class="text-sm font-bold text-gray-500">Kembalian</span>
                        <span class="text-xl font-bold text-gray-400" id="change-display">Rp 0</span>
                    </div>
                </div>

                <!-- Input Nama Pelanggan (Opsional) -->
                <div class="mb-6">
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Nama Pelanggan (Opsional)</label>
                     <input type="text" id="customer-name" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Contoh: Bpk Budi">
                </div>

                <button onclick="processPayment()" id="btn-confirm-pay" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-green-200 transform active:scale-[0.98] transition text-lg flex items-center justify-center gap-2 disabled:bg-gray-300 disabled:shadow-none disabled:cursor-not-allowed">
                    <i class="fas fa-print"></i> Bayar & Cetak Struk
                </button>
            </div>
        </div>

        <script>
            // --- STATE MANAGEMENT ---
            let cart = [];
            const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
            
            // --- POS LOGIC ---
            function filterProducts() {
                const query = document.getElementById('pos-search').value.toLowerCase();
                document.querySelectorAll('.product-item').forEach(el => {
                    const name = el.dataset.name;
                    el.style.display = name.includes(query) ? 'block' : 'none';
                });
            }

            function addToCart(product) {
                const existing = cart.find(item => item.id === product.id);
                if(existing) {
                    if(existing.qty < product.stok) {
                        existing.qty++;
                    } else {
                        const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1500});
                        Toast.fire({icon: 'warning', title: 'Stok Habis'});
                        return;
                    }
                } else {
                    if(product.stok > 0) {
                        cart.push({...product, qty: 1});
                    } else {
                        const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1500});
                        Toast.fire({icon: 'error', title: 'Stok Kosong'});
                        return;
                    }
                }
                renderCart();
            }

            function updateQty(id, change) {
                const item = cart.find(i => i.id == id);
                if(item) {
                    const newQty = item.qty + change;
                    if(newQty > 0 && newQty <= item.stok) {
                        item.qty = newQty;
                    } else if (newQty <= 0) {
                        cart = cart.filter(i => i.id != id);
                    } else {
                        const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1500});
                        Toast.fire({icon: 'warning', title: 'Stok Maksimal'});
                    }
                    renderCart();
                }
            }

            function clearCart() {
                if(cart.length === 0) return;
                cart = [];
                renderCart();
            }

            function renderCart() {
                const container = document.getElementById('cart-items-container');
                const emptyMsg = document.getElementById('empty-cart-msg');
                const btnPay = document.getElementById('btn-pay');
                
                container.innerHTML = '';
                let subtotal = 0;

                if(cart.length === 0) {
                    container.appendChild(emptyMsg);
                    emptyMsg.classList.remove('hidden');
                    btnPay.disabled = true;
                } else {
                    emptyMsg.classList.add('hidden');
                    btnPay.disabled = false;

                    cart.forEach(item => {
                        const totalItem = item.harga * item.qty;
                        subtotal += totalItem;

                        const el = document.createElement('div');
                        el.className = 'bg-gray-50 p-3 rounded-xl border border-gray-200 flex justify-between items-center fade-in hover:bg-gray-100 transition';
                        el.innerHTML = `
                            <div class="flex-1 mr-3">
                                <h5 class="text-sm font-bold text-gray-800 line-clamp-1">${item.nama_produk}</h5>
                                <div class="text-xs text-gray-500 font-mono mt-0.5">Rp ${new Intl.NumberFormat('id-ID').format(item.harga)}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center bg-white rounded-lg border border-gray-300 h-8 shadow-sm">
                                    <button onclick="updateQty(${item.id}, -1)" class="px-2.5 text-gray-500 hover:text-red-500 hover:bg-gray-50 rounded-l-lg h-full transition">-</button>
                                    <span class="px-1 text-sm font-bold w-6 text-center">${item.qty}</span>
                                    <button onclick="updateQty(${item.id}, 1)" class="px-2.5 text-gray-500 hover:text-green-600 hover:bg-gray-50 rounded-r-lg h-full transition">+</button>
                                </div>
                                <div class="text-sm font-bold text-gray-800 w-20 text-right">
                                    ${new Intl.NumberFormat('id-ID').format(totalItem)}
                                </div>
                            </div>
                        `;
                        container.appendChild(el);
                    });
                }

                const subtotalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
                document.getElementById('subtotal').innerText = subtotalFmt;
                document.getElementById('grand-total').innerText = subtotalFmt;
            }

            function getCartTotal() {
                return cart.reduce((sum, item) => sum + (item.harga * item.qty), 0);
            }

            // --- PAYMENT UI LOGIC ---
            function openPaymentModal() {
                const total = getCartTotal();
                document.getElementById('modal-total-display').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
                document.getElementById('modal-payment').classList.remove('hidden');
                
                // Reset Input
                document.getElementById('pay-amount').value = '';
                document.getElementById('change-display').innerText = 'Rp 0';
                document.getElementById('change-display').classList.remove('text-orange-600', 'text-green-600');
                document.getElementById('change-display').classList.add('text-gray-400');
                document.getElementById('btn-confirm-pay').disabled = true;
                
                togglePaymentInput(); // Cek default radio
            }

            function closePaymentModal() {
                document.getElementById('modal-payment').classList.add('hidden');
            }

            function togglePaymentInput() {
                const method = document.querySelector('input[name="pay_method"]:checked').value;
                const cashGroup = document.getElementById('cash-input-group');
                const btn = document.getElementById('btn-confirm-pay');
                
                if(method === 'qris') {
                    cashGroup.classList.add('opacity-50', 'pointer-events-none');
                    btn.disabled = false;
                } else {
                    cashGroup.classList.remove('opacity-50', 'pointer-events-none');
                    calculateChange(); // Re-validate cash
                }
            }

            function formatInputRupiah(el) {
                let val = el.value.replace(/[^0-9]/g, '');
                el.value = new Intl.NumberFormat('id-ID').format(val);
            }

            function setCash(amount) {
                const total = getCartTotal();
                let val = amount;
                if(amount === 'exact') val = total;
                
                document.getElementById('pay-amount').value = new Intl.NumberFormat('id-ID').format(val);
                calculateChange();
            }

            function calculateChange() {
                const total = getCartTotal();
                const payStr = document.getElementById('pay-amount').value.replace(/\./g, '');
                const pay = parseInt(payStr) || 0;
                const change = pay - total;
                const btn = document.getElementById('btn-confirm-pay');
                const changeDisplay = document.getElementById('change-display');

                if(pay >= total) {
                    changeDisplay.innerText = 'Kembali: Rp ' + new Intl.NumberFormat('id-ID').format(change);
                    changeDisplay.classList.remove('text-gray-400', 'text-red-500');
                    changeDisplay.classList.add('text-orange-600');
                    btn.disabled = false;
                } else {
                    changeDisplay.innerText = 'Kurang Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(change));
                    changeDisplay.classList.remove('text-gray-400', 'text-orange-600');
                    changeDisplay.classList.add('text-red-500');
                    btn.disabled = true;
                }
            }

            // --- PROCESS PAYMENT (AJAX) ---
            function processPayment() {
                const btn = document.getElementById('btn-confirm-pay');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                btn.disabled = true;

                const method = document.querySelector('input[name="pay_method"]:checked').value;
                const customer = document.getElementById('customer-name').value || 'Pelanggan Umum';
                const total = getCartTotal();
                let cashAmount = 0;
                
                if(method === 'tunai') {
                    cashAmount = parseInt(document.getElementById('pay-amount').value.replace(/\./g, '')) || total;
                } else {
                    cashAmount = total; // QRIS dianggap pas
                }

                // Prepare Data
                const orderData = {
                    action: 'dw_pos_submit_order', // Handler ini harus ada di ajax-handlers.php
                    nonce: '<?php echo wp_create_nonce("dw_pos_action"); ?>',
                    cart: cart,
                    total: total,
                    method: method,
                    cash: cashAmount,
                    customer: customer
                };

                // jQuery Post
                if (typeof jQuery !== 'undefined') {
                    jQuery.post(ajaxUrl, orderData, function(response) {
                        if(response.success) {
                            const changeText = document.getElementById('change-display').innerText;
                            Swal.fire({
                                icon: 'success',
                                title: 'Transaksi Berhasil!',
                                html: `<p class="text-2xl font-bold mb-2">Rp ${new Intl.NumberFormat('id-ID').format(total)}</p><p class="text-gray-500">${changeText}</p>`,
                                showCancelButton: true,
                                confirmButtonText: '<i class="fas fa-print"></i> Cetak Struk',
                                cancelButtonText: 'Tutup',
                                confirmButtonColor: '#16a34a',
                                cancelButtonColor: '#64748b'
                            }).then((res) => {
                                if(res.isConfirmed) {
                                    // Buka halaman print struk
                                    window.open('<?php echo home_url("/print-struk?id="); ?>' + response.data.kode_unik, '_blank');
                                }
                                // Reset System
                                cart = [];
                                renderCart();
                                closePaymentModal();
                            });
                        } else {
                            Swal.fire('Gagal', response.data.message || 'Terjadi kesalahan saat memproses transaksi.', 'error');
                        }
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }).fail(function() {
                        Swal.fire('Error', 'Koneksi ke server terputus.', 'error');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
                } else {
                    alert('Error: jQuery not found.');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            }
        </script>
        <?php
        return ob_get_clean();
    }
}