<?php
// includes/shortcodes/class-dw-shortcode-cart.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcode_Cart {

    public function __construct() {
        add_shortcode( 'dw_cart', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        // If WooCommerce is active and managing cart, use it (optional check, user seems to be using custom logic)
        if ( function_exists('is_cart') && function_exists('wc_get_cart_url') && get_option('dw_use_woocommerce_cart') === 'yes' ) {
             return '<div class="dw-woocommerce-cart-wrapper">' . do_shortcode('[woocommerce_cart]') . '</div>';
        }

        global $wpdb;
        $user_id    = get_current_user_id();
        // Session logic: In WP, usually managed via hooks. 
        // Fallback to cookie if session not started, though relying on PHP native session in shortcode is risky if not init-ed.
        // Assuming the plugin handles session_start() on 'init'.
        $session_id = (function_exists('session_id') && session_id()) ? session_id() : ($_COOKIE['PHPSESSID'] ?? '');
        
        $table_cart = $wpdb->prefix . 'dw_cart';

        // --- 1. QUERY DATA KERANJANG ---
        // (Copying SQL from page-cart.php)
        $sql = $wpdb->prepare(
            "SELECT c.id as cart_id, c.qty, c.id_variasi,
                    p.id as product_id, p.nama_produk, p.slug as slug_produk, p.foto_utama, p.berat_gram,
                    COALESCE(v.harga_variasi, p.harga) as final_price,
                    COALESCE(v.stok_variasi, p.stok) as final_stock,
                    v.deskripsi_variasi as nama_varian, v.foto as foto_varian,
                    t.id as toko_id, t.nama_toko, t.kabupaten_nama, t.slug_toko
             FROM $table_cart c
             JOIN {$wpdb->prefix}dw_produk p ON c.id_produk = p.id
             LEFT JOIN {$wpdb->prefix}dw_produk_variasi v ON c.id_variasi = v.id
             JOIN {$wpdb->prefix}dw_pedagang t ON p.id_pedagang = t.id
             WHERE c.user_id = %d OR (c.user_id IS NULL AND c.session_id = %s)
             ORDER BY t.nama_toko ASC, c.created_at DESC",
            $user_id, $session_id
        );

        $cart_items = $wpdb->get_results($sql);

        // Grouping logic per Toko
        $grouped_items = [];
        $total_count_all = 0;

        if ($cart_items) {
            foreach ($cart_items as $item) {
                $grouped_items[$item->toko_id]['info'] = [
                    'nama' => $item->nama_toko,
                    'slug' => $item->slug_toko,
                    'lokasi' => $item->kabupaten_nama
                ];
                $grouped_items[$item->toko_id]['items'][] = $item;
                $total_count_all += $item->qty;
            }
        }

        ob_start();
        ?>
        <!-- Load Dependencies -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <!-- ... HTML Content from page-cart.php ... -->
        <!-- Replacing get_header/footer with container divs if needed, though shortcode usually sits inside theme layout -->
        
        <div class="dw-cart-shortcode bg-gray-50 min-h-screen pb-32 lg:pb-10 font-sans">
            <div class="max-w-6xl mx-auto px-4 pt-8">
                
                <!-- Header & Title -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-shopping-cart text-blue-600"></i> Keranjang
                    </h1>
                    <?php if (!empty($grouped_items)) : ?>
                    <button type="button" id="btn-bulk-delete" class="text-sm text-red-500 font-medium hover:text-red-700 transition hidden flex items-center gap-1">
                        <i class="fas fa-trash-alt"></i> Hapus Pilihan
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Notification Toast -->
                <div id="cart-toast" class="fixed top-5 right-5 z-[70] transform transition-all duration-300 translate-y-[-150%] opacity-0 pointer-events-none">
                    <div class="bg-gray-800 text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-3">
                        <i id="toast-icon" class="fas fa-check-circle text-green-400"></i>
                        <span id="toast-message" class="font-medium text-sm">Update berhasil</span>
                    </div>
                </div>

                <?php if (empty($grouped_items)) : ?>
                    <!-- EMPTY STATE -->
                    <div class="bg-white rounded-2xl p-16 text-center shadow-sm border border-gray-100 flex flex-col items-center animate-fade-in-up">
                        <div class="w-48 h-48 bg-gray-50 rounded-full flex items-center justify-center mb-6 text-gray-300">
                            <i class="fas fa-shopping-basket text-6xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Keranjang Belanjamu Kosong</h2>
                        <p class="text-gray-500 mb-8 max-w-md">Sepertinya kamu belum menambahkan produk apapun. Yuk, dukung UMKM desa dengan belanja sekarang!</p>
                        <a href="<?php echo home_url('/produk'); ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-10 rounded-full transition-all shadow-lg shadow-green-200 transform hover:-translate-y-1">
                            Mulai Belanja
                        </a>
                    </div>
                <?php else : ?>

                    <form id="cart-form" action="<?php echo home_url('/checkout'); ?>" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-8 relative">
                        
                        <!-- KOLOM KIRI: LIST PRODUK -->
                        <div class="lg:col-span-2 space-y-6">
                            
                            <!-- GLOBAL CHECKBOX -->
                            <div class="bg-white px-6 py-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4 sticky top-[72px] z-20">
                                <label class="inline-flex items-center cursor-pointer select-none">
                                    <input type="checkbox" id="check-all" class="form-checkbox w-5 h-5 text-green-600 rounded border-gray-300 focus:ring-green-500 transition duration-150 ease-in-out">
                                    <span class="ml-3 text-gray-700 font-medium">Pilih Semua (<?php echo $total_count_all; ?>)</span>
                                </label>
                            </div>

                            <!-- LOOP TOKO -->
                            <?php foreach ($grouped_items as $toko_id => $group) : ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden cart-store-group transition-all duration-500 ease-in-out" id="store-group-<?php echo $toko_id; ?>">
                                
                                <!-- Header Toko -->
                                <div class="bg-gray-50/50 px-6 py-3 border-b border-gray-100 flex items-center gap-3">
                                    <input type="checkbox" class="check-store form-checkbox w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500" data-target="store-<?php echo $toko_id; ?>">
                                    <div class="flex items-center gap-2">
                                        <a href="<?php echo home_url('/toko/'.$group['info']['slug']); ?>" class="font-bold text-gray-800 text-sm hover:text-green-600 transition flex items-center">
                                            <i class="fas fa-store mr-2 text-gray-400"></i> <?php echo esc_html($group['info']['nama']); ?>
                                        </a>
                                        <span class="bg-gray-200 text-gray-600 text-[10px] px-2 py-0.5 rounded-full">
                                            <?php echo esc_html($group['info']['lokasi']); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Loop Items -->
                                <div class="divide-y divide-gray-50">
                                    <?php foreach ($group['items'] as $item) : 
                                        $foto_url = !empty($item->foto_varian) ? $item->foto_varian : (!empty($item->foto_utama) ? $item->foto_utama : 'https://via.placeholder.com/150');
                                        $is_out_of_stock = $item->final_stock <= 0;
                                    ?>
                                    <div class="p-3 sm:p-6 flex gap-3 sm:gap-4 relative group transition-all duration-300 hover:bg-gray-50/80 cart-item-row" id="cart-item-<?php echo $item->cart_id; ?>">
                                        
                                        <!-- Checkbox Item -->
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                   name="cart_ids[]" 
                                                   value="<?php echo $item->cart_id; ?>" 
                                                   class="check-item form-checkbox w-5 h-5 text-green-600 rounded border-gray-300 focus:ring-green-500 disabled:bg-gray-100 disabled:cursor-not-allowed store-<?php echo $toko_id; ?>"
                                                   data-price="<?php echo $item->final_price; ?>"
                                                   data-weight="<?php echo $item->berat_gram; ?>"
                                                   data-qty="<?php echo $item->qty; ?>"
                                                   data-store="<?php echo $toko_id; ?>"
                                                   <?php echo $is_out_of_stock ? 'disabled' : ''; ?>
                                            >
                                        </div>

                                        <!-- Gambar -->
                                        <a href="<?php echo home_url('/produk/'.$item->slug_produk); ?>" class="shrink-0 w-16 h-16 sm:w-24 sm:h-24 bg-gray-100 rounded-lg overflow-hidden border border-gray-200 relative group/img">
                                            <img src="<?php echo esc_url($foto_url); ?>" class="w-full h-full object-cover <?php echo $is_out_of_stock ? 'grayscale opacity-50' : ''; ?>">
                                            <?php if($is_out_of_stock): ?>
                                                <div class="absolute inset-0 flex items-center justify-center bg-black/50">
                                                    <span class="text-white text-xs font-bold px-2 py-1 bg-red-600 rounded">Habis</span>
                                                </div>
                                            <?php endif; ?>
                                        </a>

                                        <!-- Detail -->
                                        <div class="flex-grow flex flex-col justify-between">
                                            <div>
                                                <a href="<?php echo home_url('/produk/'.$item->slug_produk); ?>" class="font-medium text-gray-800 line-clamp-2 hover:text-green-600 transition mb-1 text-sm sm:text-base">
                                                    <?php echo esc_html($item->nama_produk); ?>
                                                </a>
                                                
                                                <?php if($item->id_variasi > 0): ?>
                                                    <div class="text-xs text-gray-500 bg-gray-100 inline-block px-2 py-1 rounded mb-2 border border-gray-200">
                                                        Variasi: <?php echo esc_html($item->nama_varian); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="font-bold text-green-600 item-price-display" data-base-price="<?php echo $item->final_price; ?>">
                                                    <?php echo 'Rp ' . number_format($item->final_price, 0, ',', '.'); ?>
                                                </div>
                                            </div>

                                            <!-- Actions: Qty & Delete -->
                                            <div class="flex justify-between items-end mt-3">
                                                <?php if(!$is_out_of_stock): ?>
                                                <div class="flex items-center border border-gray-300 rounded-lg bg-white h-8 w-28 shadow-sm transition-all focus-within:border-green-500 focus-within:ring-1 focus-within:ring-green-500">
                                                    <button type="button" class="btn-qty w-8 h-full flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-l-lg transition active:bg-gray-200" onclick="updateCartQty(<?php echo $item->cart_id; ?>, -1)">-</button>
                                                    
                                                    <input type="number" 
                                                           class="input-qty w-full h-full text-center border-none text-sm font-bold text-gray-800 p-0 focus:ring-0 appearance-none bg-transparent" 
                                                           id="qty-<?php echo $item->cart_id; ?>"
                                                           data-id="<?php echo $item->cart_id; ?>"
                                                           value="<?php echo $item->qty; ?>" 
                                                           min="1" 
                                                           max="<?php echo $item->final_stock; ?>"
                                                    >
                                                    
                                                    <button type="button" class="btn-qty w-8 h-full flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-r-lg transition active:bg-gray-200" onclick="updateCartQty(<?php echo $item->cart_id; ?>, 1)">+</button>
                                                </div>
                                                <?php else: ?>
                                                    <div class="text-xs text-red-500 italic flex items-center gap-1"><i class="fas fa-times-circle"></i> Stok habis</div>
                                                <?php endif; ?>

                                                <button type="button" onclick="deleteCartItem(<?php echo $item->cart_id; ?>)" class="text-gray-400 hover:text-red-500 transition p-2 rounded-full hover:bg-red-50" title="Hapus">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>


                        </div>

                        <!-- KOLOM KANAN: RINGKASAN (Sticky Desktop) -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 sticky top-24 hidden lg:block">
                                <h3 class="font-bold text-gray-800 text-lg mb-4">Ringkasan Belanja</h3>
                                
                                <div class="space-y-3 mb-6">
                                    <div class="flex justify-between text-sm text-gray-600">
                                        <span>Total Item Dipilih</span>
                                        <span class="font-medium" id="summary-total-item">0</span>
                                    </div>
                                    <div class="flex justify-between text-sm text-gray-600">
                                        <span>Total Berat</span>
                                        <span class="font-medium" id="summary-total-weight">0 gram</span>
                                    </div>
                                </div>

                                <div class="border-t border-dashed border-gray-200 pt-4 mb-6">
                                    <div class="flex justify-between items-end">
                                        <span class="font-bold text-gray-800">Total Harga</span>
                                        <span class="text-2xl font-bold text-green-600" id="summary-grand-total">Rp 0</span>
                                    </div>
                                </div>

                                <button type="submit" id="btn-checkout-desktop" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-green-500/30 transition-all disabled:bg-gray-300 disabled:shadow-none disabled:cursor-not-allowed transform active:scale-[0.98]" disabled>
                                    Beli (<span id="btn-count-desktop">0</span>)
                                </button>
                            </div>
                        </div>

                        <!-- MOBILE STICKY BOTTOM BAR -->
                        <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 shadow-[0_-5px_15px_rgba(0,0,0,0.05)] lg:hidden z-[990] flex items-center justify-between safe-area-pb">
                            <div class="flex flex-col">
                                <span class="text-xs text-gray-500">Total Harga</span>
                                <span class="font-bold text-green-600 text-lg" id="mobile-grand-total">Rp 0</span>
                            </div>
                            <button type="submit" id="btn-checkout-mobile" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-md transition-all disabled:bg-gray-300 disabled:cursor-not-allowed active:scale-95" disabled>
                                Checkout (<span id="btn-count-mobile">0</span>)
                            </button>
                        </div>

                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- === CONFIRMATION MODAL === -->
        <div id="confirm-modal" class="fixed inset-0 z-[60] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0 modal-backdrop"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 modal-panel">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 mb-3 sm:mb-0">
                                    <i class="fas fa-trash-alt text-red-600"></i>
                                </div>
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-lg font-bold leading-6 text-gray-900" id="modal-title">Konfirmasi Hapus</h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500" id="modal-message">Apakah Anda yakin ingin menghapus item ini?</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                            <button type="button" id="modal-confirm-btn" class="inline-flex w-full justify-center rounded-lg bg-red-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors focus:ring-2 focus:ring-red-500 focus:ring-offset-1">
                                Ya, Hapus
                            </button>
                            <button type="button" id="modal-cancel-btn" class="mt-2 sm:mt-0 inline-flex w-full justify-center rounded-lg bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:w-auto transition-colors">
                                Batal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- INLINE SCRIPT HANDLER -->
        <script>
            // Config dari PHP ke JS (PENTING)
            const dwCartConfig = {
                ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce("dw_cart_action"); ?>',
                homeUrl: '<?php echo home_url(); ?>'
            };

            // --- LOGIC JS CART ---
            document.addEventListener('DOMContentLoaded', function() {
                const $ = jQuery;
                
                // PENTING: Timer harus OBJECT agar bisa debounce per item, bukan global
                const updateTimers = {}; 
                
                // --- 0. MODAL LOGIC ---
                const modal = document.getElementById('confirm-modal');
                const backdrop = modal.querySelector('.modal-backdrop');
                const panel = modal.querySelector('.modal-panel');
                const confirmBtn = document.getElementById('modal-confirm-btn');
                const cancelBtn = document.getElementById('modal-cancel-btn');
                const modalMessage = document.getElementById('modal-message');
                let confirmCallback = null;

                function showConfirmModal(message, callback) {
                    modalMessage.textContent = message;
                    confirmCallback = callback;
                    
                    modal.classList.remove('hidden');
                    setTimeout(() => {
                        backdrop.classList.remove('opacity-0');
                        panel.classList.remove('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
                        panel.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
                    }, 10);
                }

                function hideModal() {
                    backdrop.classList.add('opacity-0');
                    panel.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
                    panel.classList.add('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
                    
                    setTimeout(() => {
                        modal.classList.add('hidden');
                        confirmCallback = null;
                    }, 300); 
                }

                confirmBtn.addEventListener('click', () => {
                    if (confirmCallback) {
                        const originalText = confirmBtn.innerText;
                        confirmBtn.innerText = 'Memproses...';
                        confirmBtn.disabled = true;
                        
                        confirmCallback().then(() => {
                            hideModal();
                            confirmBtn.innerText = originalText;
                            confirmBtn.disabled = false;
                        });
                    } else {
                        hideModal();
                    }
                });

                cancelBtn.addEventListener('click', hideModal);
                
                // --- 1. UPDATE QTY (Debounced AJAX PER ITEM) ---
                window.updateCartQty = function(cartId, change) {
                    const input = document.getElementById(`qty-${cartId}`);
                    
                    // Ambil nilai saat ini dari input
                    let currentVal = parseInt(input.value) || 0;
                    let newQty = currentVal + change;
                    
                    // Validasi jika dipanggil dari onchange (change = 0)
                    if(change === 0) newQty = currentVal;

                    const max = parseInt(input.getAttribute('max')) || 999;

                    if (newQty < 1) newQty = 1;
                    if (newQty > max) {
                        showToast('Stok maksimal tercapai (' + max + ')', 'error');
                        newQty = max;
                    }

                    // 1. Optimistic UI Update
                    input.value = newQty;
                    const checkbox = document.querySelector(`input[value="${cartId}"]`);
                    if(checkbox) checkbox.dataset.qty = newQty;
                    
                    // Efek visual loading
                    const container = input.closest('.flex');
                    if(container) container.classList.add('opacity-50');

                    recalculateTotal(); 

                    // 2. Debounce AJAX call
                    if(updateTimers[cartId]) clearTimeout(updateTimers[cartId]);

                    updateTimers[cartId] = setTimeout(() => {
                        $.ajax({
                            url: dwCartConfig.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'dw_update_cart_qty',
                                cart_id: cartId,
                                qty: newQty,
                                nonce: dwCartConfig.nonce
                            },
                            success: function(res) {
                                if(container) container.classList.remove('opacity-50');
                                
                                if(res.success) {
                                    if(res.data.total_items !== undefined) {
                                        $('.cart-count').text(res.data.total_items).removeClass('hidden');
                                    }
                                } else {
                                    showToast(res.data.message, 'error');
                                    input.value = currentVal; 
                                    if(checkbox) checkbox.dataset.qty = currentVal;
                                    recalculateTotal();
                                }
                            },
                            error: function() {
                                if(container) container.classList.remove('opacity-50');
                                showToast('Gagal terhubung ke server', 'error');
                                input.value = currentVal;
                                recalculateTotal();
                            }
                        });
                    }, 600); 
                };

                // Event listener untuk input manual
                $('.input-qty').on('change', function() {
                    const id = $(this).data('id');
                    updateCartQty(id, 0); 
                });

                // --- 2. DELETE SINGLE ITEM ---
                window.deleteCartItem = function(cartId) {
                    showConfirmModal('Apakah Anda yakin ingin menghapus item ini dari keranjang?', async function() {
                        return new Promise((resolve) => {
                            $.ajax({
                                url: dwCartConfig.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'dw_remove_cart_item',
                                    cart_id: cartId,
                                    nonce: dwCartConfig.nonce
                                },
                                success: function(res) {
                                    if(res.success) {
                                        if(res.data.total_items !== undefined) {
                                            $('.cart-count').text(res.data.total_items);
                                            if(res.data.total_items == 0) $('.cart-count').addClass('hidden');
                                        }

                                        const $itemRow = $(`#cart-item-${cartId}`);
                                        $itemRow.css('transition', 'all 0.3s ease').css('opacity', '0').css('transform', 'scale(0.9)');
                                        setTimeout(() => {
                                            $itemRow.remove();
                                            checkEmptyState();
                                            recalculateTotal();
                                            showToast('Item dihapus');
                                        }, 300);
                                    } else {
                                        showToast(res.data.message || 'Gagal menghapus item', 'error');
                                    }
                                    resolve();
                                },
                                error: function() {
                                    showToast('Terjadi kesalahan koneksi', 'error');
                                    resolve();
                                }
                            });
                        });
                    });
                };

                // --- 3. BULK DELETE ---
                const btnBulkDelete = document.getElementById('btn-bulk-delete');
                if(btnBulkDelete) {
                    btnBulkDelete.addEventListener('click', function() {
                        const checkedIds = Array.from(document.querySelectorAll('.check-item:checked')).map(cb => cb.value);
                        if(checkedIds.length === 0) return;

                        showConfirmModal(`Hapus ${checkedIds.length} item terpilih?`, async function() {
                            return new Promise((resolve) => {
                                let errors = 0;
                                const deletePromises = checkedIds.map(id => {
                                    return $.ajax({
                                        url: dwCartConfig.ajaxUrl,
                                        type: 'POST',
                                        data: { action: 'dw_remove_cart_item', cart_id: id, nonce: dwCartConfig.nonce }
                                    }).then((res) => {
                                        if(res.success) {
                                            if(res.data.total_items !== undefined) {
                                                $('.cart-count').text(res.data.total_items);
                                            }
                                            $(`#cart-item-${id}`).remove();
                                        } else {
                                            errors++;
                                        }
                                    }).catch(() => { errors++; });
                                });

                                Promise.all(deletePromises).then(() => {
                                    checkEmptyState();
                                    recalculateTotal();
                                    if(errors > 0) {
                                        showToast(`${errors} item gagal dihapus`, 'error');
                                    } else {
                                        showToast('Semua item terpilih berhasil dihapus');
                                    }
                                    resolve();
                                });
                            });
                        });
                    });
                }

                // --- 4. CHECKBOX LOGIC ---
                $('#check-all').on('change', function() {
                    const isChecked = $(this).is(':checked');
                    $('.check-item:not(:disabled), .check-store').prop('checked', isChecked);
                    recalculateTotal();
                    toggleBulkDeleteBtn();
                });

                $('.check-store').on('change', function() {
                    const targetClass = $(this).data('target');
                    const isChecked = $(this).is(':checked');
                    $(`.${targetClass}:not(:disabled)`).prop('checked', isChecked);
                    updateCheckAllStatus();
                    recalculateTotal();
                    toggleBulkDeleteBtn();
                });

                $('.check-item').on('change', function() {
                    const storeId = $(this).data('store');
                    const storeCheckbox = $(`.check-store[data-target="store-${storeId}"]`);
                    const allStoreItems = $(`.check-item.store-${storeId}:not(:disabled)`);
                    const checkedStoreItems = $(`.check-item.store-${storeId}:checked`);
                    storeCheckbox.prop('checked', allStoreItems.length === checkedStoreItems.length);
                    updateCheckAllStatus();
                    recalculateTotal();
                    toggleBulkDeleteBtn();
                });

                function updateCheckAllStatus() {
                    const allItems = $('.check-item:not(:disabled)');
                    const checkedItems = $('.check-item:checked');
                    $('#check-all').prop('checked', allItems.length > 0 && allItems.length === checkedItems.length);
                }

                function toggleBulkDeleteBtn() {
                    const checkedCount = $('.check-item:checked').length;
                    if(checkedCount > 0) {
                        $('#btn-bulk-delete').removeClass('hidden');
                    } else {
                        $('#btn-bulk-delete').addClass('hidden');
                    }
                }

                // --- 5. CALCULATION ---
                function recalculateTotal() {
                    let totalDto = 0;
                    let totalItems = 0;
                    let totalWeight = 0;

                    $('.check-item:checked').each(function() {
                        const price = parseFloat($(this).data('price')) || 0;
                        const weight = parseInt($(this).data('weight')) || 0;
                        const currentQty = parseInt($(`#qty-${this.value}`).val()) || 1; 

                        totalDto += price * currentQty;
                        totalWeight += weight * currentQty;
                        totalItems += currentQty;
                    });

                    const fmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(totalDto);
                    
                    $('#summary-grand-total, #mobile-grand-total').text(fmt);
                    $('#summary-total-item').text(totalItems);
                    $('#summary-total-weight').text(totalWeight + ' gram');
                    
                    $('#btn-count-desktop, #btn-count-mobile').text(totalItems);

                    const btnState = totalItems === 0;
                    $('#btn-checkout-desktop, #btn-checkout-mobile').prop('disabled', btnState);
                }

                // --- 6. UTILS ---
                function checkEmptyState() {
                    if($('.cart-item-row').length === 0) {
                        location.reload(); 
                    } else {
                        $('.cart-store-group').each(function() {
                            if($(this).find('.cart-item-row').length === 0) {
                                $(this).fadeOut(300, function() { $(this).remove(); });
                            }
                        });
                    }
                }

                function showToast(msg, type = 'success') {
                    const toast = $('#cart-toast');
                    const icon = $('#toast-icon');
                    $('#toast-message').text(msg);
                    
                    if(type === 'error') {
                        icon.removeClass('fa-check-circle text-green-400').addClass('fa-times-circle text-red-400');
                    } else {
                        icon.removeClass('fa-times-circle text-red-400').addClass('fa-check-circle text-green-400');
                    }

                    toast.removeClass('translate-y-[-150%] opacity-0').addClass('translate-y-5 opacity-100');
                    setTimeout(() => {
                        toast.removeClass('translate-y-5 opacity-100').addClass('translate-y-[-150%] opacity-0');
                    }, 3000);
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }
}