<?php
/**
 * Migration v4.1: Add order notification sound settings to pedagang table.
 */

function dw_migration_v4_1() {
    global ;
     = $wpdb->prefix . 'dw_pedagang';
    
    // Check if column exists
     = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'order_notification_sound'");
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN order_notification_sound VARCHAR(255) DEFAULT NULL AFTER qris_image_url");
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN order_notification_type ENUM('upload', 'youtube', 'default') DEFAULT 'default' AFTER order_notification_sound");
        error_log('[DW Core] Migration v4.1: Added order_notification columns to ' . $table_name);
    }
}
add_action('admin_init', 'dw_migration_v4_1');
