<?php
// includes/shortcodes/class-dw-shortcode-product-list.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DW_Shortcode_Product_List {

    public function __construct() {
        add_shortcode( 'dw_product_list', array( $this, 'render' ) );
    }

    /**
     * Shortcode: [dw_product_list limit="12" category="oleh-oleh"]
     */
    public function render( $atts ) {
        $atts = shortcode_atts( array(
            'category' => '',
            'limit'    => 12,
            'columns'  => 4, // Default kolom
        ), $atts );

        // Query Produk (Support CPT 'dw_produk' sesuai tema)
        $args = array(
            'post_type'      => 'dw_produk', // Sesuaikan dengan CPT tema Anda
            'posts_per_page' => $atts['limit'],
            'post_status'    => 'publish',
        );

        if ( ! empty( $atts['category'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'kategori_produk', // Sesuaikan dengan tax tema
                    'field'    => 'slug',
                    'terms'    => $atts['category'],
                ),
            );
        }

        $query = new WP_Query( $args );

        ob_start();
        ?>
        
        <?php if ( $query->have_posts() ) : ?>
            <div class="dw-shortcode-product-list container mx-auto px-4">
                <!-- Grid Wrapper meniru archive-dw_produk.php -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-<?php echo esc_attr($atts['columns']); ?> gap-4 md:gap-6">
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        
                        <?php 
                        // OPSI 1: Gunakan template part tema jika ada (Best Practice)
                        if ( locate_template( 'template-parts/card-produk.php' ) ) {
                            get_template_part( 'template-parts/card-produk' );
                        } 
                        // OPSI 2: Fallback markup jika file tema tidak ditemukan (Hardcoded mirip tema)
                        else {
                            ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition group h-full flex flex-col relative">
                                <!-- Badge / Label -->
                                <?php if(get_post_meta(get_the_ID(), 'stok_produk', true) <= 0): ?>
                                    <div class="absolute top-2 right-2 bg-gray-500 text-white text-[10px] px-2 py-1 rounded z-10 font-bold">Habis</div>
                                <?php endif; ?>

                                <a href="<?php the_permalink(); ?>" class="block h-40 bg-gray-100 relative overflow-hidden">
                                    <?php if ( has_post_thumbnail() ) {
                                        the_post_thumbnail( 'medium', ['class' => 'w-full h-full object-cover group-hover:scale-105 transition duration-500'] );
                                    } else {
                                        echo '<div class="flex items-center justify-center h-full text-gray-400 text-xs">No Image</div>';
                                    } ?>
                                </a>
                                
                                <div class="p-3 flex flex-col flex-1">
                                    <div class="text-[10px] text-gray-500 mb-1 uppercase tracking-wide">
                                        <?php 
                                        $terms = get_the_terms( get_the_ID(), 'kategori_produk' );
                                        echo $terms && ! is_wp_error( $terms ) ? esc_html( $terms[0]->name ) : 'Umum'; 
                                        ?>
                                    </div>
                                    
                                    <h3 class="font-bold text-gray-800 mb-1 text-sm leading-snug line-clamp-2 hover:text-blue-600 transition">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h3>
                                    
                                    <div class="mt-auto pt-3">
                                        <div class="flex items-center justify-between mb-3">
                                            <div class="text-blue-600 font-bold text-sm">
                                               Rp <?php echo number_format( (float) get_post_meta( get_the_ID(), 'harga_produk', true ), 0, ',', '.' ); ?>
                                            </div>
                                            <div class="text-[10px] text-gray-400 flex items-center gap-1">
                                                <i class="fas fa-star text-yellow-400"></i> 
                                                <?php echo number_format((float)(get_post_meta(get_the_ID(), 'rating_rata_rata', true) ?: 5.0), 1); ?>
                                            </div>
                                        </div>
                                        
                                        <button class="w-full bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white text-xs font-bold py-2 rounded-lg transition-colors duration-200 dw-add-to-cart flex justify-center items-center gap-2" 
                                                data-id="<?php the_ID(); ?>"
                                                data-nonce="<?php echo wp_create_nonce('dw_add_to_cart_nonce'); ?>">
                                            <i class="fas fa-cart-plus"></i> + Keranjang
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        ?>

                    <?php endwhile; ?>
                </div>
            </div>
            <?php wp_reset_postdata(); ?>
        <?php else : ?>
            <div class="text-center py-12 px-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 text-gray-400 mb-4">
                    <i class="fas fa-box-open text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Belum ada produk</h3>
                <p class="text-gray-500 text-sm mt-1">Silakan cek kembali nanti untuk produk terbaru.</p>
            </div>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }
}