<?php
/**
 * Initialization File
 * Path: includes/init.php
 * Description: Memuat semua dependensi, class, dan fungsi yang diperlukan plugin.
 * Version: 2.8.0
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Core Constants & Helpers
require_once DW_PLUGIN_DIR . 'includes/helpers.php';
require_once DW_PLUGIN_DIR . 'includes/data-integrity.php';

// 2. Post Types & Taxonomies
require_once DW_PLUGIN_DIR . 'includes/post-types.php';
require_once DW_PLUGIN_DIR . 'includes/taxonomies.php';

// 3. User & Roles
require_once DW_PLUGIN_DIR . 'includes/roles-capabilities.php';
require_once DW_PLUGIN_DIR . 'includes/user-profiles.php';
require_once DW_PLUGIN_DIR . 'includes/access-control.php';

// 4. Admin Interfaces
if (is_admin()) {
    require_once DW_PLUGIN_DIR . 'includes/admin-menus.php';
    require_once DW_PLUGIN_DIR . 'includes/admin-assets.php';
    require_once DW_PLUGIN_DIR . 'includes/meta-boxes.php';
    require_once DW_PLUGIN_DIR . 'includes/ajax-handlers.php'; // AJAX Handlers
} else {
    // Load AJAX handlers untuk frontend (agar fungsi AJAX jalan di non-admin pages)
    require_once DW_PLUGIN_DIR . 'includes/ajax-handlers.php';
}

// 5. Frontend Logic (Shortcodes & Public Views)
require_once DW_PLUGIN_DIR . 'includes/shortcodes/class-dw-shortcodes.php';

// 6. Business Logic Modules
require_once DW_PLUGIN_DIR . 'includes/commission-handler.php';
require_once DW_PLUGIN_DIR . 'includes/cart.php';
require_once DW_PLUGIN_DIR . 'includes/reviews.php';
require_once DW_PLUGIN_DIR . 'includes/promotions.php';
require_once DW_PLUGIN_DIR . 'includes/logs.php';
require_once DW_PLUGIN_DIR . 'includes/whatsapp-templates.php';
require_once DW_PLUGIN_DIR . 'includes/address-api.php';
require_once DW_PLUGIN_DIR . 'includes/relasi-handler.php';

// 7. Classes
require_once DW_PLUGIN_DIR . 'includes/class-dw-favorites.php';
require_once DW_PLUGIN_DIR . 'includes/class-dw-ojek-handler.php';
require_once DW_PLUGIN_DIR . 'includes/class-dw-seeder.php';
// [New] POS Handler untuk Kasir
if ( file_exists( DW_PLUGIN_DIR . 'includes/classes/class-dw-pos-handler.php' ) ) {
    require_once DW_PLUGIN_DIR . 'includes/classes/class-dw-pos-handler.php';
}

// Referral System
require_once DW_PLUGIN_DIR . 'includes/class-dw-referral-logic.php';
require_once DW_PLUGIN_DIR . 'includes/class-dw-referral-handler.php';
require_once DW_PLUGIN_DIR . 'includes/class-dw-referral-hooks.php';

// 8. REST API
// [UPDATE] General REST API dinonaktifkan (Integrasi Monolith)
// require_once DW_PLUGIN_DIR . 'includes/rest-api.php'; 

// [NEW] Payment API (Khusus Webhook Xendit)
add_action( 'rest_api_init', function() {
    if ( file_exists( DW_PLUGIN_DIR . 'includes/rest-api/api-payment.php' ) ) {
        require_once DW_PLUGIN_DIR . 'includes/rest-api/api-payment.php';
        if ( class_exists( 'DW_Payment_API' ) ) {
            $payment_api = new DW_Payment_API();
            $payment_api->register_routes();
        }
    }
});

// 9. Scheduled Tasks (Cron)
require_once DW_PLUGIN_DIR . 'includes/cron-jobs.php';

// Hook Aktivasi
if ( defined( 'DW_PLUGIN_FILE' ) ) {
    register_activation_hook(DW_PLUGIN_FILE, 'dw_activation_run');
    register_deactivation_hook(DW_PLUGIN_FILE, 'dw_deactivation_run');
}

/**
 * Inisialisasi Komponen Utama
 * Dijalankan saat 'plugins_loaded'
 */
function dw_init_components() {
    // 1. Init Roles
    if ( class_exists( 'DW_Roles_Capabilities' ) ) {
        $roles = new DW_Roles_Capabilities();
        $roles->init();
    }

    // 2. Init Shortcodes (Dashboard & POS)
    if ( class_exists( 'DW_Shortcodes' ) ) {
        new DW_Shortcodes();
    }
}
add_action( 'plugins_loaded', 'dw_init_components' );

/**
 * Registrasi Post Types & Taxonomies
 * Dijalankan saat 'init'
 */
function dw_register_types_taxonomies() {
    if ( function_exists( 'dw_register_post_types' ) ) {
        dw_register_post_types();
    }
    if ( function_exists( 'dw_register_taxonomies' ) ) {
        dw_register_taxonomies();
    }
}
add_action( 'init', 'dw_register_types_taxonomies' );