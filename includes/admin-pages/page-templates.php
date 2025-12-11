<?php
/**
 * File Name:   page-templates.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-templates.php
 */
function dw_templates_page_render() {
    $templatesListTable = new DW_Templates_List_Table();
    $templatesListTable->prepare_items();
     ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Template Pesan WhatsApp</h1>
            <!-- Tombol Tambah Baru -->
        </div>
        <p>Halaman ini untuk mengelola template pesan otomatis WhatsApp.</p>
         <form method="post">
            <?php $templatesListTable->display(); ?>
        </form>
    </div>
    <?php
}

