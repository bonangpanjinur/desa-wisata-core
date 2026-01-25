<?php
/**
 * Plugin Name: Desa Wisata Core
 * Description: Plugin inti untuk manajemen Desa Wisata, Marketplace UMKM, dan Sistem Ojek Lokal.
 * Version: 2.9.0
 * Author: Bonang Panji Nur ganteng
 * Text Domain: desa-wisata-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// 1. Define Plugin Constants (CRITICAL FIX)
if ( ! defined( 'DW_PLUGIN_DIR' ) ) {
    define( 'DW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DW_PLUGIN_URL' ) ) {
    define( 'DW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'DW_PLUGIN_FILE' ) ) {
    define( 'DW_PLUGIN_FILE', __FILE__ );
}

// 2. Load Composer Autoloader (Jika ada)
if ( file_exists( DW_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once DW_PLUGIN_DIR . 'vendor/autoload.php';
}

// 3. Include Initialization File
require_once DW_PLUGIN_DIR . 'includes/init.php';

// 4. Activation & Deactivation Hooks
register_activation_hook( __FILE__, 'dw_activate_plugin' );
register_deactivation_hook( __FILE__, 'dw_deactivate_plugin' );

function dw_activate_plugin() {
    require_once DW_PLUGIN_DIR . 'includes/activation.php';
    if ( function_exists( 'dw_activation_run' ) ) {
        dw_activation_run();
    }
}

function dw_deactivate_plugin() {
    require_once DW_PLUGIN_DIR . 'includes/deactivation.php';
    if ( function_exists( 'dw_deactivation_run' ) ) {
        dw_deactivation_run();
    }
}