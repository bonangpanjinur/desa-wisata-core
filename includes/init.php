<?php
/**
 * Inisialisasi plugin
 *
 * Memuat semua file yang diperlukan untuk fungsi dasar plugin.
 *
 * @package    Desa_Wisata_Core
 * @subpackage Desa_Wisata_Core/includes
 * @author     Bonang Panji
 */

// Cegah akses langsung
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fungsi untuk memuat file-file core
 */
function dw_core_init() {

    // Cek apakah constant DW_CORE_PATH sudah ada untuk mencegah error
    if ( ! defined( 'DW_CORE_PATH' ) ) {
        return; 
    }

	// 1. Load file-file core dasar
	require_once DW_CORE_PATH . 'includes/post-types.php';
	require_once DW_CORE_PATH . 'includes/taxonomies.php';
	require_once DW_CORE_PATH . 'includes/roles-capabilities.php';
	require_once DW_CORE_PATH . 'includes/admin-menus.php';
	require_once DW_CORE_PATH . 'includes/admin-assets.php';
    require_once DW_CORE_PATH . 'includes/admin-ui-tweaks.php';
	require_once DW_CORE_PATH . 'includes/meta-boxes.php';
	require_once DW_CORE_PATH . 'includes/ajax-handlers.php';
    require_once DW_CORE_PATH . 'includes/logs.php'; 

    // 2. Load Class Handlers & Logic Utama
    require_once DW_CORE_PATH . 'includes/class-dw-favorites.php';
    require_once DW_CORE_PATH . 'includes/class-dw-ojek-handler.php';
    require_once DW_CORE_PATH . 'includes/commission-handler.php';
    require_once DW_CORE_PATH . 'includes/cron-jobs.php';
    
    // 3. Load Referral System
    require_once DW_CORE_PATH . 'includes/class-dw-referral-logic.php';
    require_once DW_CORE_PATH . 'includes/class-dw-referral-hooks.php';
    require_once DW_CORE_PATH . 'includes/class-dw-referral-handler.php';

    // 4. Helper functions & Fitur E-commerce
    require_once DW_CORE_PATH . 'includes/helpers.php';
    require_once DW_CORE_PATH . 'includes/promotions.php';
    require_once DW_CORE_PATH . 'includes/cart.php';
    require_once DW_CORE_PATH . 'includes/reviews.php';
    require_once DW_CORE_PATH . 'includes/user-profiles.php';

    // 5. Integrasi API
    require_once DW_CORE_PATH . 'includes/address-api.php'; // Wilayah Indonesia
    require_once DW_CORE_PATH . 'includes/rest-api/index.php'; // REST API Endpoint
    
    // 6. Notifikasi
    require_once DW_CORE_PATH . 'includes/whatsapp-templates.php';

    // Hook inisialisasi tambahan jika diperlukan
    // do_action('dw_core_init_complete');
}

// Jalankan fungsi init pada hook 'plugins_loaded'
add_action( 'plugins_loaded', 'dw_core_init' );