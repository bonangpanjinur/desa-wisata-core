<?php
/**
 * File Path: includes/admin-pages/page-ongkir.php
 *
 * --- PERUBAHAN (STRATEGI ONGKIR BARU) ---
 * - File ini tidak lagi digunakan. Menu "Manajemen Ongkir" telah dihapus
 * dari `admin-menus.php`.
 * - Pengaturan ongkir sekarang dikelola oleh Pedagang.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Merender halaman Laporan Komisi (Hanya Penjualan).
 */
function dw_ongkir_page_render() {
    ?>
     <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Manajemen Ongkir Lokal</h1>
        </div>
        <div class="notice notice-warning">
            <p><strong>Menu Ini Tidak Digunakan Lagi.</strong></p>
            <p>Sesuai dengan strategi pengiriman yang baru, pengaturan ongkos kirim (Ojek Lokal dan Ekspedisi Nasional) sekarang dikelola oleh masing-masing <strong>Pedagang</strong> melalui aplikasi frontend mereka.</p>
            <p>Menu ini akan dihapus di versi selanjutnya.</p>
        </div>
    </div>
    <?php
}
