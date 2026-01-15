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
        if ( ! is_user_logged_in() ) return '<div class="p-4 text-red-500">Silakan login.</div>';
        
        if ( ! current_user_can( 'dw_manage_pesanan' ) && ! current_user_can( 'administrator' ) && ! current_user_can( 'pedagang' ) ) {
            return '<div class="p-4 text-red-500">Akses ditolak. Khusus Pedagang.</div>';
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table_pedagang = $wpdb->prefix . 'dw_pedagang';
        $table_produk   = $wpdb->prefix . 'dw_produk';

        // Ambil Data Pedagang
        $pedagang = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_pedagang WHERE id_user = %d", $user_id));
        if (!$pedagang) return '<div class="p-4 text-red-500">Data toko tidak ditemukan.</div>';

        // Ambil Produk Toko
        $produk_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_produk WHERE id_pedagang = %d AND status='aktif' ORDER BY nama_produk ASC", $pedagang->id));

        ob_start();
        ?>
        <!-- Load Dependencies -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <style>
            .pos-scroll::-webkit-scrollbar { width: 6px; }
            .pos-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
            .pos-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            .pos-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
            .fade-in { animation: fadeIn 0.3s ease-in-out; }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        </style>

        <div class="bg-gray-100 h-screen w-full flex flex-col md:flex-row overflow-hidden font-sans text-slate-800">
            
            <!-- KIRI: Katalog Produk -->
            <div class="flex-1 flex flex-col h-full relative">
                <!-- Header Katalog -->
                <div class="bg-white p-4 border-b border-gray-200 flex justify-between items-center shadow-sm z-10">
                    <div>
                        <h2 class="font-bold text-lg text-gray-800"><i class="fas fa-store text-blue-600 mr-2"></i><?php echo esc_html($pedagang->nama_toko); ?></h2>
                        <p class="text-xs text-gray-500">Kasir / Point of Sale</p>
                    </div>
                    <div class="relative w-64">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        <input type="text" id="pos-search" placeholder="Cari produk..." class="w-full pl-10 pr-4 py-2 bg-gray-100 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white transition" onkeyup="filterProducts()">
                    </div>
                </div>

                <!-- Grid Produk -->
                <div class="flex-1 overflow-y-auto p-4 pos-scroll bg-gray-50">
                    <?php if($produk_list): ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="product-grid">
                            <?php foreach($produk_list as $p): ?>
                            <div class="bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer transition-all pos-card product-item group" 
                                 onclick='addToCart(<?php echo json_encode($p); ?>)'
                                 data-name="<?php echo strtolower($p->nama_produk); ?>">
                                <div class="h-32 bg-gray-100 rounded-lg mb-3 overflow-hidden relative">
                                    <?php if($p->foto_utama): ?>
                                        <img src="<?php echo esc_url($p->foto_utama); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                                    <?php else: ?>
                                        <div class="flex items-center justify-center h-full text-gray-300"><i class="fas fa-image text-2xl"></i></div>
                                    <?php endif; ?>
                                    <div class="absolute top-2 right-2 bg-black/60 text-white text-[10px] px-2 py-0.5 rounded-md backdrop-blur">
                                        Stok: <?php echo $p->stok; ?>
                                    </div>
                                </div>
                                <h4 class="font-bold text-sm text-gray-800 mb-1 leading-snug line-clamp-2"><?php echo esc_html($p->nama_produk); ?></h4>
                                <p class="text-blue-600 font-bold text-sm">Rp <?php echo number_format($p->harga, 0, ',', '.'); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-full text-gray-400">
                            <i class="fas fa-box-open text-4xl mb-2"></i>
                            <p>Belum ada produk.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KANAN: Keranjang / Struk -->
            <div class="w-full md:w-96 bg-white border-l border-gray-200 flex flex-col h-full shadow-xl z-20">
                <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <h3 class="font-bold text-gray-800"><i class="fas fa-shopping-cart mr-2"></i>Pesanan</h3>
                    <button onclick="clearCart()" class="text-xs text-red-500 hover:text-red-700 font-bold">Reset</button>
                </div>

                <!-- List Item -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3 pos-scroll" id="cart-items-container">
                    <!-- Cart items injected via JS -->
                    <div id="empty-cart-msg" class="text-center py-10 text-gray-400 text-sm">
                        <i class="fas fa-cash-register text-3xl mb-2 opacity-50"></i>
                        <p>Keranjang kosong</p>
                    </div>
                </div>

                <!-- Total & Action -->
                <div class="p-5 border-t border-gray-200 bg-gray-50">
                    <div class="space-y-2 mb-4 text-sm">
                        <div class="flex justify-between text-gray-600"><span>Subtotal</span><span id="subtotal">Rp 0</span></div>
                        <div class="flex justify-between font-bold text-lg text-gray-900 border-t border-gray-200 pt-2"><span>Total</span><span id="grand-total">Rp 0</span></div>
                    </div>
                    
                    <button onclick="openPaymentModal()" id="btn-pay" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-200 transition-all transform active:scale-95 disabled:bg-gray-300 disabled:shadow-none disabled:cursor-not-allowed flex items-center justify-center gap-2" disabled>
                        <span>Bayar Sekarang</span> <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL PEMBAYARAN -->
        <div id="modal-payment" class="fixed inset-0 z-50 hidden transition-opacity duration-300">
            <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" onclick="closePaymentModal()"></div>
            <div class="absolute inset-x-0 bottom-0 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 w-full md:w-[450px] bg-white md:rounded-3xl rounded-t-3xl shadow-2xl p-6 transform transition-all scale-100">
                
                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Pembayaran</h3>
                    <p class="text-gray-500 text-sm">Total Tagihan: <span class="font-bold text-blue-600 text-lg" id="modal-total-display">Rp 0</span></p>
                </div>

                <!-- Metode Bayar -->
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <label class="cursor-pointer">
                        <input type="radio" name="pay_method" value="tunai" class="peer sr-only" checked onchange="togglePaymentInput()">
                        <div class="p-3 border-2 border-gray-200 rounded-xl peer-checked:border-blue-500 peer-checked:bg-blue-50 text-center transition hover:bg-gray-50">
                            <i class="fas fa-money-bill-wave text-xl mb-1 text-green-600"></i>
                            <div class="text-xs font-bold">Tunai</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="pay_method" value="qris" class="peer sr-only" onchange="togglePaymentInput()">
                        <div class="p-3 border-2 border-gray-200 rounded-xl peer-checked:border-blue-500 peer-checked:bg-blue-50 text-center transition hover:bg-gray-50">
                            <i class="fas fa-qrcode text-xl mb-1 text-blue-600"></i>
                            <div class="text-xs font-bold">QRIS</div>
                        </div>
                    </label>
                </div>

                <!-- Input Tunai -->
                <div id="cash-input-group" class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Uang Diterima</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-gray-400 font-bold">Rp</span>
                        <input type="text" id="pay-amount" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-lg font-bold text-gray-800 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0" onkeyup="formatInputRupiah(this); calculateChange()">
                    </div>
                    <!-- Quick Money Buttons -->
                    <div class="flex gap-2 mt-3 overflow-x-auto pb-2 no-scrollbar">
                        <button type="button" onclick="setCash(10000)" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">10rb</button>
                        <button type="button" onclick="setCash(20000)" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">20rb</button>
                        <button type="button" onclick="setCash(50000)" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">50rb</button>
                        <button type="button" onclick="setCash(100000)" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-gray-200 whitespace-nowrap">100rb</button>
                        <button type="button" onclick="setCash('exact')" class="bg-blue-100 text-blue-600 px-3 py-1 rounded-lg text-xs font-bold hover:bg-blue-200 whitespace-nowrap">Pas</button>
                    </div>
                    <div class="mt-3 flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="text-sm font-bold text-gray-500">Kembalian</span>
                        <span class="text-lg font-bold text-orange-600" id="change-display">Rp 0</span>
                    </div>
                </div>

                <!-- Input Nama Pelanggan (Opsional) -->
                <div class="mb-6">
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Nama Pelanggan (Opsional)</label>
                     <input type="text" id="customer-name" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Contoh: Bpk Budi">
                </div>

                <button onclick="processPayment()" id="btn-confirm-pay" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-2xl shadow-lg transform active:scale-95 transition text-lg flex items-center justify-center gap-2 disabled:bg-gray-400 disabled:cursor-not-allowed">
                    <i class="fas fa-check-circle"></i> Selesaikan Transaksi
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
                        Swal.fire({icon: 'warning', title: 'Stok Habis', text: 'Stok produk ini sudah maksimal di keranjang.', timer: 1500, showConfirmButton: false});
                        return;
                    }
                } else {
                    if(product.stok > 0) {
                        cart.push({...product, qty: 1});
                    } else {
                        Swal.fire({icon: 'error', title: 'Stok Kosong', text: 'Produk ini tidak tersedia.', timer: 1500, showConfirmButton: false});
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
                        Swal.fire({icon: 'warning', title: 'Stok Maksimal', text: 'Stok tidak mencukupi.', timer: 1000, showConfirmButton: false});
                    }
                    renderCart();
                }
            }

            function clearCart() {
                if(cart.length === 0) return;
                Swal.fire({
                    title: 'Kosongkan keranjang?',
                    showCancelButton: true,
                    confirmButtonText: 'Ya',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        cart = [];
                        renderCart();
                    }
                });
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
                        el.className = 'bg-gray-50 p-3 rounded-lg border border-gray-200 flex justify-between items-center fade-in';
                        el.innerHTML = `
                            <div class="flex-1">
                                <h5 class="text-sm font-bold text-gray-800 line-clamp-1">${item.nama_produk}</h5>
                                <div class="text-xs text-gray-500">Rp ${new Intl.NumberFormat('id-ID').format(item.harga)}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center bg-white rounded-lg border border-gray-300 h-8">
                                    <button onclick="updateQty(${item.id}, -1)" class="px-2 text-gray-600 hover:bg-gray-100 rounded-l-lg h-full">-</button>
                                    <span class="px-2 text-xs font-bold w-6 text-center">${item.qty}</span>
                                    <button onclick="updateQty(${item.id}, 1)" class="px-2 text-gray-600 hover:bg-gray-100 rounded-r-lg h-full">+</button>
                                </div>
                                <div class="text-sm font-bold text-gray-800 w-20 text-right">
                                    ${new Intl.NumberFormat('id-ID').format(totalItem)}
                                </div>
                            </div>
                        `;
                        container.appendChild(el);
                    });
                }

                document.getElementById('subtotal').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
                document.getElementById('grand-total').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
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

                if(pay >= total) {
                    document.getElementById('change-display').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(change);
                    document.getElementById('change-display').classList.remove('text-red-500');
                    document.getElementById('change-display').classList.add('text-orange-600');
                    btn.disabled = false;
                } else {
                    document.getElementById('change-display').innerText = 'Kurang Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(change));
                    document.getElementById('change-display').classList.add('text-red-500');
                    document.getElementById('change-display').classList.remove('text-orange-600');
                    btn.disabled = true;
                }
            }

            // --- PROCESS PAYMENT (AJAX) ---
            function processPayment() {
                const btn = document.getElementById('btn-confirm-pay');
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
                    action: 'dw_pos_submit_order', // Pastikan handler ini ada di ajax-handlers.php
                    nonce: '<?php echo wp_create_nonce("dw_pos_action"); ?>',
                    cart: cart,
                    total: total,
                    method: method,
                    cash: cashAmount,
                    customer: customer
                };

                // Simulasi AJAX Request (Ganti dengan real call ke ajax-handlers.php)
                // Karena kita di dalam shortcode, kita bisa buat handler AJAX di dalam plugin core.
                // Untuk sekarang, kita asumsi handler sudah siap atau kita buat dummy response.
                
                $.post(ajaxUrl, orderData, function(response) {
                    if(response.success) {
                        const trxId = response.data.trx_id;
                        Swal.fire({
                            icon: 'success',
                            title: 'Transaksi Berhasil!',
                            text: 'Kembalian: ' + document.getElementById('change-display').innerText,
                            showCancelButton: true,
                            confirmButtonText: 'Cetak Struk',
                            cancelButtonText: 'Tutup'
                        }).then((res) => {
                            if(res.isConfirmed) {
                                // Redirect ke halaman cetak struk
                                window.open('<?php echo home_url("/print-struk?id="); ?>' + response.data.kode_unik, '_blank');
                            }
                            // Reset
                            cart = [];
                            renderCart();
                            closePaymentModal();
                        });
                    } else {
                        Swal.fire('Gagal', response.data.message || 'Terjadi kesalahan.', 'error');
                    }
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Selesaikan Transaksi';
                    btn.disabled = false;
                }).fail(function() {
                    Swal.fire('Error', 'Koneksi terputus.', 'error');
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Selesaikan Transaksi';
                    btn.disabled = false;
                });
            }
            
            // Perlu jQuery untuk $.post
            if (typeof jQuery === 'undefined') {
                var script = document.createElement('script');
                script.src = "https://code.jquery.com/jquery-3.6.0.min.js";
                script.onload = function() { console.log('jQuery loaded for POS'); };
                document.head.appendChild(script);
            }
        </script>
        <?php
        return ob_get_clean();
    }
}