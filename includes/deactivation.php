<?php
/**
 * File Name:   deactivation.php
 * File Folder: includes/
 * File Path:   includes/deactivation.php
 *
 * File ini berisi logika yang dijalankan saat plugin dinonaktifkan.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fungsi untuk deaktivasi plugin.
 * Membersihkan rewrite rules agar tidak ada slug CPT yang tersisa.
 */
function dw_core_deactivate_plugin() {
    flush_rewrite_rules();
}

