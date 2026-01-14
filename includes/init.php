<?php
/**
 * Initialization File
 * Path: includes/init.php
 * Description: Memuat semua dependensi, class, dan fungsi yang diperlukan plugin.
 * Version: 2.5.0
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
    require_once DW_PLUGIN_DIR . 'includes/ajax-handlers.php'; // AJAX Handlers (Secured in v2.5)
}

// 5. Frontend Logic (Shortcodes & Public Views)
// [NEW] Memuat handler shortcode untuk Fase 2.1
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

// Referral System
require_once DW_PLUGIN_DIR . 'includes/class-dw-referral-logic.php';
require_once DW_PLUGIN_DIR . 'includes/class-dw-referral-handler.php';
require_once DW_PLUGIN_DIR . 'includes/class-dw-referral-hooks.php';

// 8. REST API
require_once DW_PLUGIN_DIR . 'includes/rest-api.php';

// 9. Scheduled Tasks (Cron)
require_once DW_PLUGIN_DIR . 'includes/cron-jobs.php';

// Hook Aktivasi (Register di sini agar terpanggil saat plugin aktif)
register_activation_hook(DW_PLUGIN_FILE, 'dw_activation_run');
register_deactivation_hook(DW_PLUGIN_FILE, 'dw_deactivation_run');