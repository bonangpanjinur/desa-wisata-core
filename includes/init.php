<?php
/**
 * Initialization File
 * Path: includes/init.php
 * Description: Memuat semua komponen utama plugin saat WordPress 'plugins_loaded'.
 */

if (!defined('ABSPATH')) {
    exit;
}

function dw_core_init() {
    // 1. Load Helper Functions (Paling Awal)
    if (file_exists(DW_CORE_PATH . 'includes/helpers.php')) {
        require_once DW_CORE_PATH . 'includes/helpers.php';
    }

    // 2. Load Classes & Handlers
    $includes = array(
        'includes/post-types.php',           // Register CPT
        'includes/taxonomies.php',           // Register Taxonomies
        'includes/roles-capabilities.php',   // Role Management
        'includes/user-profiles.php',        // User Meta Fields
        'includes/meta-boxes.php',           // Custom Meta Boxes
        'includes/admin-menus.php',          // Admin Menu Structure
        'includes/admin-assets.php',         // Enqueue Scripts/Styles
        'includes/ajax-handlers.php',        // General AJAX
        'includes/address-api.php',          // Wilayah API
        
        // Core Logic Classes
        'includes/cart.php',
        'includes/classes/class-dw-wallet.php',
        'includes/classes/class-dw-pos-handler.php',
        'includes/classes/class-dw-ojek-handler.php', // New Ojek Handler
        'includes/commission-handler.php',
        'includes/cron-jobs.php',
        'includes/whatsapp-templates.php',
        'includes/reviews.php',
        'includes/promotions.php',
        'includes/logs.php',
        
        // REST API
        'includes/rest-api.php',
    );

    foreach ($includes as $file) {
        if (file_exists(DW_CORE_PATH . $file)) {
            require_once DW_CORE_PATH . $file;
        }
    }

    // 3. Initialize Ojek Handler (Singleton)
    // FIX: Check if class exists before calling init() to prevent fatal error
    if ( class_exists( 'DW_Ojek_Handler' ) && method_exists( 'DW_Ojek_Handler', 'init' ) ) {
        DW_Ojek_Handler::init();
    } elseif ( class_exists( 'DW_Ojek_Handler' ) && method_exists( 'DW_Ojek_Handler', 'instance' ) ) {
        // Fallback if init() missing but instance() exists
        DW_Ojek_Handler::instance();
    }

    // 4. Initialize Wallet (Singleton if applicable)
    if ( class_exists( 'DW_Wallet' ) && method_exists( 'DW_Wallet', 'init' ) ) {
        DW_Wallet::init();
    }

    // 5. Initialize Shortcodes
    if (file_exists(DW_CORE_PATH . 'includes/shortcodes/class-dw-shortcodes.php')) {
        require_once DW_CORE_PATH . 'includes/shortcodes/class-dw-shortcodes.php';
        DW_Shortcodes::init();
    }
}
add_action('plugins_loaded', 'dw_core_init');