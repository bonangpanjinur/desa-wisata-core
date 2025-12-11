<?php
/**
 * File Name:   whatsapp-templates.php
 * File Folder: includes/
 * File Path:   includes/whatsapp-templates.php
 *
 * *** CATATAN HEADLESS ***
 * Fungsi `dw_generate_whatsapp_link_for_order` menghasilkan link `wa.me`
 * yang mungkin sebelumnya digunakan pada tombol di frontend WordPress.
 * Dalam mode headless, tombol ini akan dibuat di aplikasi frontend.
 *
 * Fungsi ini *mungkin* masih berguna jika Anda memanggilnya dari backend
 * untuk keperluan lain (misalnya, mengirim link ini dalam notifikasi email ke admin/pedagang),
 * tetapi penggunaannya secara langsung di frontend sudah tidak relevan.
 *
 * => REKOMENDASI: Tinjau kembali apakah fungsi ini masih dipanggil dari
 * bagian backend lain. Jika tidak, file ini bisa dihapus.
 *
 * PERBAIKAN (KRITIS):
 * - Mengganti referensi tabel lama (`dw_penjual`) ke tabel yang benar (`dw_pedagang`).
 *
 * @package DesaWisataCore
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Membuat link wa.me dengan pesan pre-filled untuk konfirmasi pesanan.
 *
 * @param int $order_id ID Pesanan dari tabel `dw_transaksi`.
 * @return string URL WhatsApp atau '#' jika gagal.
 */
function dw_generate_whatsapp_link_for_order($order_id) {
    global $wpdb;

    // 1. Dapatkan data pesanan dari dw_transaksi
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dw_transaksi WHERE id = %d", $order_id));
    if (!$order) {
        return '#';
    }

    // 2. Dapatkan nomor WhatsApp pedagang dari data PEDAGANG
    $nomor_wa_pedagang = $wpdb->get_var($wpdb->prepare(
        "SELECT nomor_wa FROM {$wpdb->prefix}dw_pedagang WHERE id = %d",
        $order->id_pedagang
    ));

    if (empty($nomor_wa_pedagang)) {
        error_log("Gagal mendapatkan nomor WA untuk pedagang ID: {$order->id_pedagang} (Order ID: {$order_id})");
        return '#'; // Gagal mendapatkan nomor WA pedagang
    }

    // Pastikan nomor WA dalam format 62xxx
    $nomor_wa_pedagang = preg_replace('/[^0-9]/', '', $nomor_wa_pedagang);
    if (substr($nomor_wa_pedagang, 0, 1) === '0') {
        $nomor_wa_pedagang = '62' . substr($nomor_wa_pedagang, 1);
    } elseif (substr($nomor_wa_pedagang, 0, 2) !== '62') {
         // Anggap ini 62 jika bukan 62
         $nomor_wa_pedagang = '62' . $nomor_wa_pedagang;
    }


    // 3. Buat pesan template
    $order_code = $order->kode_unik; // Menggunakan kode_unik dari dw_transaksi
    $site_name = get_bloginfo('name');
    $total_payment = number_format($order->total_akhir ?? $order->total_harga_produk, 0, ',', '.'); // Gunakan total akhir jika ada
    $metode_pengiriman_text = ucfirst(str_replace('_', ' ', $order->metode_pengiriman ?? 'N/A'));


    // Template pesan WhatsApp
    $template = "Halo, saya telah membuat pesanan di *{site_name}*.\n\nBerikut detailnya:\n*No. Pesanan:* {order_code}\n*Metode Pengiriman:* {metode_pengiriman}\n*Total Pembayaran:* Rp {total_payment}\n\nMohon info jika pesanan sudah diproses atau jika ada pertanyaan.\n(Bukti bayar sudah/akan diunggah via sistem).\n\nTerima kasih.";

    $message = str_replace(
        ['{site_name}', '{order_code}', '{total_payment}', '{metode_pengiriman}'],
        [$site_name, $order_code, $total_payment, $metode_pengiriman_text],
        $template
    );

    return 'https://wa.me/' . $nomor_wa_pedagang . '?text=' . urlencode($message);
}
