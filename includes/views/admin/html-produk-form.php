<?php
/**
 * View: Form Tambah/Edit Produk
 * Path: includes/views/admin/html-produk-form.php
 * UPDATE FASE 1: Tambah Input Variasi & Flash Sale
 */

if (!defined('ABSPATH')) {
    exit;
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = $product_id ? get_post($product_id) : null;

// Ambil Meta Data
$price = $product ? get_post_meta($product->ID, '_price', true) : '';
$stock = $product ? get_post_meta($product->ID, '_stock', true) : '';
$unit  = $product ? get_post_meta($product->ID, '_unit', true) : 'pcs';
$image_url = $product ? get_the_post_thumbnail_url($product->ID, 'medium') : '';

// FASE 1: Data Variasi & Promo
$attributes = $product ? get_post_meta($product->ID, '_dw_product_attributes', true) : []; 
// Struktur Attributes: [['name' => 'Warna', 'options' => 'Merah, Biru'], ...]
if (!is_array($attributes)) $attributes = [];

$is_promo = $product ? get_post_meta($product->ID, '_is_promo', true) : '';
$promo_price = $product ? get_post_meta($product->ID, '_promo_price', true) : '';
$promo_start = $product ? get_post_meta($product->ID, '_promo_start', true) : '';
$promo_end = $product ? get_post_meta($product->ID, '_promo_end', true) : '';

$action_url = admin_url('admin.php?page=dw-produk&action=' . ($product ? 'edit&id=' . $product->ID : 'add'));
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $product ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h1>
    <a href="<?php echo admin_url('admin.php?page=dw-produk'); ?>" class="page-title-action">Kembali</a>
    <hr class="wp-header-end">

    <form method="post" action="<?php echo esc_url($action_url); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('dw_save_product'); ?>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                
                <!-- KOLOM UTAMA -->
                <div id="post-body-content">
                    
                    <!-- Judul -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <input type="text" name="post_title" size="30" value="<?php echo $product ? esc_attr($product->post_title) : ''; ?>" id="title" spellcheck="true" autocomplete="off" placeholder="Nama Produk (misal: Keripik Pisang Coklat)" required style="width:100%; font-size:1.5em; height:auto; padding:10px;">
                    </div>

                    <!-- Deskripsi -->
                    <div class="postbox">
                        <div class="postbox-header"><h2 class="hndle">Deskripsi Produk</h2></div>
                        <div class="inside">
                            <?php 
                            wp_editor($product ? $product->post_content : '', 'post_content', array(
                                'textarea_name' => 'post_content',
                                'media_buttons' => true,
                                'textarea_rows' => 10
                            )); 
                            ?>
                        </div>
                    </div>

                    <!-- FASE 1: VARIASI PRODUK -->
                    <div class="postbox">
                        <div class="postbox-header"><h2 class="hndle">Variasi Produk (Opsional)</h2></div>
                        <div class="inside">
                            <p class="description">Tambahkan pilihan untuk pembeli, misal: Ukuran, Warna, Rasa.</p>
                            
                            <div id="dw-attributes-wrapper">
                                <?php 
                                // Tampilkan variasi yang sudah ada
                                if (!empty($attributes)) :
                                    foreach ($attributes as $index => $attr) : 
                                ?>
                                    <div class="dw-attribute-row" style="background:#f9f9f9; padding:10px; border:1px solid #ddd; margin-bottom:10px; display:flex; gap:10px; align-items:center;">
                                        <div style="flex:1;">
                                            <label>Nama Variasi</label>
                                            <input type="text" name="attributes[<?php echo $index; ?>][name]" value="<?php echo esc_attr($attr['name']); ?>" placeholder="Contoh: Ukuran" style="width:100%;">
                                        </div>
                                        <div style="flex:2;">
                                            <label>Pilihan (Pisahkan dengan koma)</label>
                                            <input type="text" name="attributes[<?php echo $index; ?>][options]" value="<?php echo esc_attr($attr['options']); ?>" placeholder="Contoh: S, M, L, XL" style="width:100%;">
                                        </div>
                                        <div>
                                            <label>&nbsp;</label><br>
                                            <button type="button" class="button dw-remove-attr text-danger">Hapus</button>
                                        </div>
                                    </div>
                                <?php 
                                    endforeach;
                                endif; 
                                ?>
                            </div>

                            <button type="button" id="dw-add-attribute" class="button button-secondary">+ Tambah Variasi Baru</button>
                        </div>
                    </div>

                    <!-- FASE 1: HARGA & PROMO -->
                    <div class="postbox">
                        <div class="postbox-header"><h2 class="hndle">Harga & Promo (Flash Sale)</h2></div>
                        <div class="inside">
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                <div>
                                    <label>Harga Normal (Rp)</label>
                                    <input type="number" name="_price" value="<?php echo esc_attr($price); ?>" class="widefat" required>
                                </div>
                                <div>
                                    <label>Stok</label>
                                    <input type="number" name="_stock" value="<?php echo esc_attr($stock); ?>" class="widefat">
                                </div>
                            </div>
                            
                            <hr>
                            
                            <p>
                                <label>
                                    <input type="checkbox" name="_is_promo" id="toggle-promo" value="yes" <?php checked($is_promo, 'yes'); ?>> 
                                    <strong>Aktifkan Harga Coret / Flash Sale?</strong>
                                </label>
                            </p>

                            <div id="promo-fields" style="display:<?php echo ($is_promo === 'yes') ? 'block' : 'none'; ?>; background:#e7f5ff; padding:15px; border-radius:5px;">
                                <div style="margin-bottom:10px;">
                                    <label>Harga Promo (Rp)</label>
                                    <input type="number" name="_promo_price" value="<?php echo esc_attr($promo_price); ?>" class="widefat" placeholder="Lebih murah dari harga normal">
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                    <div>
                                        <label>Mulai Promo</label>
                                        <input type="datetime-local" name="_promo_start" value="<?php echo esc_attr($promo_start); ?>" class="widefat">
                                    </div>
                                    <div>
                                        <label>Selesai Promo</label>
                                        <input type="datetime-local" name="_promo_end" value="<?php echo esc_attr($promo_end); ?>" class="widefat">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- SIDEBAR -->
                <div id="postbox-container-1" class="postbox-container">
                    
                    <!-- Publish -->
                    <div class="postbox">
                        <div class="postbox-header"><h2 class="hndle">Terbitkan</h2></div>
                        <div class="inside">
                            <p>Status: <strong><?php echo $product ? $product->post_status : 'Baru'; ?></strong></p>
                            <input type="submit" name="dw_submit_product" class="button button-primary button-large" value="Simpan Produk" style="width:100%;">
                        </div>
                    </div>

                    <!-- Gambar -->
                    <div class="postbox">
                        <div class="postbox-header"><h2 class="hndle">Gambar Produk</h2></div>
                        <div class="inside">
                            <input type="file" name="product_image" accept="image/*">
                            <?php if ($image_url) : ?>
                                <br><br>
                                <img src="<?php echo esc_url($image_url); ?>" style="max-width:100%; height:auto; border:1px solid #ddd;">
                            <?php endif; ?>
                            <p class="description">Format: JPG/PNG. Maks 2MB.</p>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </form>
</div>

<!-- Script untuk Dinamis Form Variasi & Promo -->
<script>
jQuery(document).ready(function($){
    // Toggle Promo
    $('#toggle-promo').change(function(){
        if($(this).is(':checked')) {
            $('#promo-fields').slideDown();
        } else {
            $('#promo-fields').slideUp();
        }
    });

    // Tambah Variasi
    $('#dw-add-attribute').click(function(){
        var count = $('.dw-attribute-row').length;
        var html = `
        <div class="dw-attribute-row" style="background:#f9f9f9; padding:10px; border:1px solid #ddd; margin-bottom:10px; display:flex; gap:10px; align-items:center;">
            <div style="flex:1;">
                <label>Nama Variasi</label>
                <input type="text" name="attributes[${count}][name]" placeholder="Contoh: Ukuran" style="width:100%;" required>
            </div>
            <div style="flex:2;">
                <label>Pilihan (Pisahkan dengan koma)</label>
                <input type="text" name="attributes[${count}][options]" placeholder="Contoh: S, M, L, XL" style="width:100%;" required>
            </div>
            <div>
                <label>&nbsp;</label><br>
                <button type="button" class="button dw-remove-attr text-danger">Hapus</button>
            </div>
        </div>`;
        $('#dw-attributes-wrapper').append(html);
    });

    // Hapus Variasi
    $(document).on('click', '.dw-remove-attr', function(){
        $(this).closest('.dw-attribute-row').remove();
    });
});
</script>