<?php
/**
 * File Name:   page-chat.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-chat.php
 *
 * BARU: Halaman admin untuk Pedagang mengelola Inkuiri (Chat) Produk.
 *
 * @package DesaWisataCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Merender halaman Chat/Inkuiri.
 */
function dw_chat_page_render() {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $produk_id = isset($_GET['produk_id']) ? absint($_GET['produk_id']) : 0;

    if ('view' === $action && $produk_id > 0) {
        dw_chat_thread_render($produk_id);
        return;
    }

    // Tampilan Daftar Thread
    $chatListTable = new DW_Chat_List_Table();
    $chatListTable->prepare_items();
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Inkuiri Produk Saya</h1>
        </div>
        <p>Di sini Anda dapat melihat dan membalas semua pertanyaan atau negosiasi yang masuk dari Pembeli terkait produk Anda.</p>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php $chatListTable->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * Merender tampilan thread chat tunggal.
 *
 * @param int $produk_id ID CPT Produk.
 */
function dw_chat_thread_render($produk_id) {
    global $wpdb;
    $current_user_id = get_current_user_id();
    
    // Pastikan produk ini milik pedagang yang sedang login
    if (get_post_field('post_author', $produk_id) !== $current_user_id) {
        echo '<div class="notice notice-error"><p>Anda tidak memiliki akses ke produk ini.</p></div>';
        return;
    }

    $produk_title = get_the_title($produk_id);
    
    // Ambil semua pesan untuk produk ini
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT t1.*, u.display_name AS sender_name 
         FROM {$wpdb->prefix}dw_chat_message t1
         JOIN {$wpdb->users} u ON t1.sender_id = u.ID
         WHERE t1.produk_id = %d
         ORDER BY t1.created_at ASC",
        $produk_id
    ));
    
    // Tandai semua pesan yang diterima sebagai sudah dibaca
    $wpdb->update(
        $wpdb->prefix . 'dw_chat_message',
        ['is_read' => 1],
        ['receiver_id' => $current_user_id, 'produk_id' => $produk_id]
    );

    $last_pembeli_id = 0;
    if (!empty($messages)) {
         $last_message = end($messages);
         $last_pembeli_id = $last_message->sender_id !== $current_user_id ? $last_message->sender_id : $last_message->receiver_id;
    }
    $pembeli = get_userdata($last_pembeli_id);
    ?>
    <div class="wrap dw-wrap">
        <div class="dw-header">
            <h1>Balas Inkuiri: <?php echo esc_html($produk_title); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=dw-chat'); ?>" class="button button-secondary">← Kembali ke Daftar Inkuiri</a>
        </div>

        <div class="dw-form-card" style="max-width: 800px; margin: 20px 0;">
            <p><strong>Pembeli:</strong> <?php echo esc_html($pembeli ? $pembeli->display_name : 'N/A'); ?></p>
            <p><strong>Produk:</strong> <a href="<?php echo get_edit_post_link($produk_id); ?>" target="_blank"><?php echo esc_html($produk_title); ?></a></p>
            
            <hr>
            <h3>Riwayat Pesan</h3>
            <div id="dw-admin-chat-history" style="height: 400px; overflow-y: scroll; border: 1px solid #ddd; padding: 15px; background: #fafafa;">
                <?php foreach ($messages as $msg) : 
                    $is_pedagang = (int) $msg->sender_id === $current_user_id;
                    $class = $is_pedagang ? 'dw-chat-out-admin' : 'dw-chat-in-admin';
                    $sender_name = $is_pedagang ? 'Saya (Pedagang)' : esc_html($msg->sender_name);
                    ?>
                    <div class="<?php echo $class; ?>" style="margin-bottom: 10px; display: flex; <?php echo $is_pedagang ? 'justify-content: flex-end;' : 'justify-content: flex-start;'; ?>">
                         <div style="max-width: 80%; padding: 10px; border-radius: 8px; font-size: 13px; <?php echo $is_pedagang ? 'background-color: #d8eaff; margin-left: 10px;' : 'background-color: #fff; border: 1px solid #ccc; margin-right: 10px;'; ?>">
                            <strong style="font-size: 11px; color: #555;"><?php echo $sender_name; ?></strong>
                            <p style="margin: 5px 0 0;"><?php echo esc_html($msg->message); ?></p>
                            <small style="display: block; text-align: right; color: #888; font-size: 10px;"><?php echo date('H:i', strtotime($msg->created_at)); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <h4 style="margin-top: 20px;">Balas Pesan</h4>
            <form id="dw-admin-reply-form" style="margin-top: 10px;">
                <textarea id="dw-reply-message" rows="4" style="width: 100%;" placeholder="Ketik balasan Anda di sini..."></textarea>
                <input type="hidden" id="dw-chat-produk-id" value="<?php echo esc_attr($produk_id); ?>">
                <input type="hidden" id="dw-chat-receiver-id" value="<?php echo esc_attr($last_pembeli_id); ?>">
                <button type="submit" class="button button-primary" id="dw-send-admin-reply">Kirim Balasan</button>
                <span id="dw-admin-reply-feedback" style="margin-left: 10px;"></span>
            </form>
        </div>
    </div>
    <script>
    // Gulir chat ke bawah saat dimuat
    document.addEventListener('DOMContentLoaded', function() {
        var chatHistory = document.getElementById('dw-admin-chat-history');
        if (chatHistory) {
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    });
    </script>
    <?php
}
