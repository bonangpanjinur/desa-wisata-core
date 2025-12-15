<?php
/**
 * File Name:   page-reviews.php
 * Description: FIX Error Class Not Found.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Init handler (tetap sama)
function dw_reviews_handle_row_actions() { /* ... kode lama ... */ }
add_action('admin_init', 'dw_reviews_handle_row_actions');

function dw_reviews_moderation_page_render() {
    // --- FIX: Require class file manually if not autoloader ---
    if (!class_exists('DW_Reviews_List_Table')) {
        require_once DW_CORE_PLUGIN_DIR . 'includes/list-tables/class-dw-reviews-list-table.php';
    }

    $reviewsListTable = new DW_Reviews_List_Table();
    $reviewsListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <h1 class="wp-heading-inline">Moderasi Ulasan</h1>
        <hr class="wp-header-end">
        <?php settings_errors('dw_reviews_notices'); ?>
        
        <div class="card" style="padding:0; margin-top:20px; overflow:hidden;">
            <form method="post">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php $reviewsListTable->display(); ?>
            </form>
        </div>
    </div>
    <?php
}
?>