<?php
// includes/admin-pages/page-promosi.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Halaman Manajemen Promosi & Iklan
 */
function dw_render_promosi_page() {
    
    // Fix: Define path safely if constant is missing
    $plugin_root = defined('DW_PLUGIN_DIR') ? DW_PLUGIN_DIR : dirname( dirname( dirname( __FILE__ ) ) ) . '/';

    // Pastikan class list table sudah dimuat (Lazy load)
    if ( ! class_exists( 'DW_Promosi_List_Table' ) ) {
        // Coba load dari path relatif jika constant gagal
        $list_table_path = $plugin_root . 'includes/list-tables/class-dw-promosi-list-table.php';
        
        if ( file_exists( $list_table_path ) ) {
            require_once $list_table_path;
        } else {
            // Fallback relative to this file
            $fallback_path = dirname( dirname( __FILE__ ) ) . '/list-tables/class-dw-promosi-list-table.php';
            if ( file_exists( $fallback_path ) ) {
                require_once $fallback_path;
            }
        }
    }

    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'list';
    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Manajemen Promosi & Iklan</h1>
        
        <?php if ( $active_tab === 'list' ) : ?>
            <a href="#" class="page-title-action dw-add-promo-btn">Tambah Voucher Diskon</a>
        <?php endif; ?>
        
        <hr class="wp-header-end">

        <nav class="nav-tab-wrapper">
            <a href="?page=dw-promosi&tab=list" class="nav-tab <?php echo $active_tab === 'list' ? 'nav-tab-active' : ''; ?>">Voucher Diskon (Kupon)</a>
            <a href="?page=dw-promosi&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Paket Iklan (Banner/Wisata)</a>
        </nav>

        <div class="dw-admin-content" style="margin-top: 20px;">
            
            <!-- TAB 1: DAFTAR PROMOSI (VOUCHER) -->
            <?php if ( $active_tab === 'list' ) : ?>
                
                <div class="notice notice-info inline">
                    <p>Tab ini untuk mengatur <strong>Kode Voucher Diskon</strong> yang digunakan pembeli saat checkout.</p>
                </div>

                <form method="post">
                    <?php
                    if ( class_exists( 'DW_Promosi_List_Table' ) ) {
                        $promosi_table = new DW_Promosi_List_Table();
                        $promosi_table->prepare_items();
                        $promosi_table->search_box( 'Cari Voucher', 'search_id' );
                        $promosi_table->display();
                    } else {
                        echo '<div class="notice notice-error"><p>Error: Class DW_Promosi_List_Table tidak ditemukan.</p></div>';
                    }
                    ?>
                </form>

                <!-- Modal Tambah/Edit Promosi -->
                <div id="dw-promo-modal" class="dw-modal" style="display:none;">
                    <div class="dw-modal-content">
                        <span class="dw-close-modal">&times;</span>
                        <h2 id="modal-title">Tambah Voucher Diskon</h2>
                        <form id="dw-promotion-form">
                            <input type="hidden" name="id" id="promo_id">
                            
                            <div class="form-group">
                                <label>Kode Voucher</label>
                                <input type="text" name="code" id="promo_code" required placeholder="Contoh: DISKON50">
                            </div>
                            
                            <div class="form-group">
                                <label>Tipe Diskon</label>
                                <select name="discount_type" id="promo_type">
                                    <option value="fixed">Nominal (Rp)</option>
                                    <option value="percent">Persentase (%)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Jumlah Diskon</label>
                                <input type="number" name="amount" id="promo_amount" required>
                            </div>

                            <div class="form-group">
                                <label>Periode Berlaku</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="date" name="start_date" id="promo_start" required>
                                    <span>s/d</span>
                                    <input type="date" name="end_date" id="promo_end" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Min. Pembelian</label>
                                <input type="number" name="min_purchase" id="promo_min" value="0">
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="button button-primary">Simpan Voucher</button>
                            </div>
                        </form>
                    </div>
                </div>

            <!-- TAB 2: PENGATURAN HARGA IKLAN (PAKET) -->
            <?php elseif ( $active_tab === 'settings' ) : 
                $ad_settings = get_option( 'dw_ad_packages', array() );
                $banner_packages = isset($ad_settings['banner']) ? $ad_settings['banner'] : array();
                $wisata_packages = isset($ad_settings['wisata']) ? $ad_settings['wisata'] : array();
                $produk_packages = isset($ad_settings['produk']) ? $ad_settings['produk'] : array();
            ?>
                
                <form id="dw-ad-settings-form">
                    
                    <div class="notice notice-warning inline">
                        <p><strong>Pengaturan Paket Iklan:</strong> Paket ini akan muncul di dashboard Desa/Pedagang untuk dibeli. <br>
                        <strong>Kuota (Slot):</strong> Jumlah maksimal iklan aktif secara bersamaan untuk paket ini. Jika penuh, paket tidak bisa dibeli.</p>
                    </div>

                    <!-- 1. PENGATURAN BANNER CAROUSEL -->
                    <div class="card" style="max-width: 100%; margin-bottom: 20px; padding: 20px;">
                        <h2>1. Paket Iklan Banner Carousel</h2>
                        <p class="description">Iklan slide besar di halaman utama.</p>
                        
                        <table class="widefat fixed striped" id="table-banner-packages">
                            <thead>
                                <tr>
                                    <th>Nama Paket</th>
                                    <th>Durasi (Hari)</th>
                                    <th>Harga (Rp)</th>
                                    <th>Kuota (Slot)</th>
                                    <th style="width: 50px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty($banner_packages) ): ?>
                                    <tr class="empty-row"><td colspan="5">Belum ada paket. Klik tambah baris.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($banner_packages as $idx => $pkg): ?>
                                        <tr>
                                            <td><input type="text" name="ad_packages[banner][<?php echo $idx; ?>][name]" value="<?php echo esc_attr($pkg['name']); ?>" class="regular-text" style="width:100%"></td>
                                            <td><input type="number" name="ad_packages[banner][<?php echo $idx; ?>][days]" value="<?php echo esc_attr($pkg['days']); ?>" class="small-text"> Hari</td>
                                            <td><input type="number" name="ad_packages[banner][<?php echo $idx; ?>][price]" value="<?php echo esc_attr($pkg['price']); ?>" class="regular-text"></td>
                                            <td><input type="number" name="ad_packages[banner][<?php echo $idx; ?>][quota]" value="<?php echo isset($pkg['quota']) ? esc_attr($pkg['quota']) : 5; ?>" class="small-text"></td>
                                            <td><button type="button" class="button dw-remove-row"><span class="dashicons dashicons-trash"></span></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 10px;">
                            <button type="button" class="button action dw-add-row" data-type="banner">+ Tambah Paket Banner</button>
                        </div>
                    </div>

                    <!-- 2. PENGATURAN WISATA FAVORIT -->
                    <div class="card" style="max-width: 100%; margin-bottom: 20px; padding: 20px;">
                        <h2>2. Paket Iklan Wisata Favorit</h2>
                        <p class="description">Highlight di bagian Wisata Favorit.</p>
                        
                        <table class="widefat fixed striped" id="table-wisata-packages">
                            <thead>
                                <tr>
                                    <th>Nama Paket</th>
                                    <th>Durasi (Hari)</th>
                                    <th>Harga (Rp)</th>
                                    <th>Kuota (Slot)</th>
                                    <th style="width: 50px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty($wisata_packages) ): ?>
                                    <tr class="empty-row"><td colspan="5">Belum ada paket. Klik tambah baris.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($wisata_packages as $idx => $pkg): ?>
                                        <tr>
                                            <td><input type="text" name="ad_packages[wisata][<?php echo $idx; ?>][name]" value="<?php echo esc_attr($pkg['name']); ?>" class="regular-text" style="width:100%"></td>
                                            <td><input type="number" name="ad_packages[wisata][<?php echo $idx; ?>][days]" value="<?php echo esc_attr($pkg['days']); ?>" class="small-text"> Hari</td>
                                            <td><input type="number" name="ad_packages[wisata][<?php echo $idx; ?>][price]" value="<?php echo esc_attr($pkg['price']); ?>" class="regular-text"></td>
                                            <td><input type="number" name="ad_packages[wisata][<?php echo $idx; ?>][quota]" value="<?php echo isset($pkg['quota']) ? esc_attr($pkg['quota']) : 10; ?>" class="small-text"></td>
                                            <td><button type="button" class="button dw-remove-row"><span class="dashicons dashicons-trash"></span></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 10px;">
                            <button type="button" class="button action dw-add-row" data-type="wisata">+ Tambah Paket Wisata</button>
                        </div>
                    </div>

                    <!-- 3. PENGATURAN PRODUK FAVORIT -->
                    <div class="card" style="max-width: 100%; margin-bottom: 20px; padding: 20px;">
                        <h2>3. Paket Iklan Produk Favorit</h2>
                        <p class="description">Highlight di bagian Produk UMKM Unggulan.</p>
                        
                        <table class="widefat fixed striped" id="table-produk-packages">
                            <thead>
                                <tr>
                                    <th>Nama Paket</th>
                                    <th>Durasi (Hari)</th>
                                    <th>Harga (Rp)</th>
                                    <th>Kuota (Slot)</th>
                                    <th style="width: 50px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty($produk_packages) ): ?>
                                    <tr class="empty-row"><td colspan="5">Belum ada paket. Klik tambah baris.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($produk_packages as $idx => $pkg): ?>
                                        <tr>
                                            <td><input type="text" name="ad_packages[produk][<?php echo $idx; ?>][name]" value="<?php echo esc_attr($pkg['name']); ?>" class="regular-text" style="width:100%"></td>
                                            <td><input type="number" name="ad_packages[produk][<?php echo $idx; ?>][days]" value="<?php echo esc_attr($pkg['days']); ?>" class="small-text"> Hari</td>
                                            <td><input type="number" name="ad_packages[produk][<?php echo $idx; ?>][price]" value="<?php echo esc_attr($pkg['price']); ?>" class="regular-text"></td>
                                            <td><input type="number" name="ad_packages[produk][<?php echo $idx; ?>][quota]" value="<?php echo isset($pkg['quota']) ? esc_attr($pkg['quota']) : 20; ?>" class="small-text"></td>
                                            <td><button type="button" class="button dw-remove-row"><span class="dashicons dashicons-trash"></span></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 10px;">
                            <button type="button" class="button action dw-add-row" data-type="produk">+ Tambah Paket Produk</button>
                        </div>
                    </div>

                    <div class="submit-section" style="position: sticky; bottom: 0; background: #fff; padding: 15px; border-top: 1px solid #ddd; z-index: 10;">
                        <button type="submit" class="button button-primary button-large" id="btn-save-ad-settings">Simpan Semua Pengaturan Harga</button>
                    </div>

                </form>

            <?php endif; ?>

        </div>
    </div>

    <style>
    .dw-modal {
        position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);
        display: flex; align-items: center; justify-content: center;
    }
    .dw-modal-content {
        background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 500px; max-width: 90%; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .dw-close-modal {
        color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;
    }
    .dw-close-modal:hover, .dw-close-modal:focus { color: black; text-decoration: none; cursor: pointer; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; }
    </style>
    <?php
}

if ( did_action( 'current_screen' ) ) {
    dw_render_promosi_page();
}