<?php
/**
 * File: includes/admin-pages/page-verifikator-list.php
 * Deskripsi: Halaman bagi Super Admin untuk melihat & mengelola list Akun Verifikator.
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('administrator')) {
    wp_die('Anda tidak memiliki akses ke halaman ini.');
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Manajemen Akun Verifikator</h1>
    <a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="page-title-action">Tambah Verifikator Baru</a>
    <hr class="wp-header-end">

    <div class="notice notice-info">
        <p>Halaman ini menampilkan semua user dengan role <strong>Verifikator UMKM</strong>. Anda bisa memantau kode agen dan saldo komisi mereka di sini.</p>
    </div>

    <div id="dw-verifikator-list-table">
        <form method="get">
            <input type="hidden" name="page" value="dw-verifikator-list" />
            <?php
            require_once plugin_dir_path(__FILE__) . '../list-tables/class-dw-verifikator-list-table.php';
            $table = new DW_Verifikator_List_Table();
            $table->prepare_items();
            $table->search_box('Cari Verifikator', 'dw-search');
            $table->display();
            ?>
        </form>
    </div>
</div>