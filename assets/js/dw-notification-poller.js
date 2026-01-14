/**
 * DW Notification Poller
 * File: assets/js/dw-notification-poller.js
 * Description: Menangani pemeriksaan real-time untuk pesanan baru dan memutar peringatan audio.
 * @version 2.8.0
 */
jQuery(document).ready(function($) {
    
    var lastCheckTime = new Date().toISOString().slice(0, 19).replace('T', ' '); // Waktu inisialisasi
    var audio = null;

    // Inisialisasi Objek Audio
    if (dw_poll_vars.audio_url) {
        audio = new Audio(dw_poll_vars.audio_url);
    }

    /**
     * Fungsi Polling Utama
     */
    function pollNotifications() {
        $.ajax({
            url: dw_poll_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'dw_check_notifications',
                nonce: dw_poll_vars.nonce,
                last_check: lastCheckTime
            },
            success: function(response) {
                if (response.success) {
                    // Perbarui waktu pemeriksaan terakhir dari waktu server untuk sinkronisasi
                    if (response.data.server_time) {
                        lastCheckTime = response.data.server_time;
                    }

                    if (response.data.has_notification) {
                        triggerAlert(response.data.message, response.data.type);
                    }
                }
            },
            complete: function() {
                // Jadwalkan polling berikutnya
                setTimeout(pollNotifications, dw_poll_vars.interval || 30000);
            }
        });
    }

    /**
     * Pemicu Peringatan Visual & Audio
     */
    function triggerAlert(message, type) {
        // 1. Putar Suara
        if (audio) {
            audio.play().catch(function(error) {
                console.log('Audio autoplay diblokir oleh kebijakan browser:', error);
            });
        }

        // 2. Tampilkan Toast SweetAlert
        Swal.fire({
            title: 'Info Baru!',
            text: message,
            icon: 'info',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // 3. Opsional: Segarkan Grid/Tabel jika ada (tergantung halaman)
        if (type === 'order_ojek' && typeof dwRefreshOjekList === 'function') {
            dwRefreshOjekList(); // Fungsi hipotetikal untuk memuat ulang daftar pesanan
        }
    }

    // Mulai Polling setelah 5 detik halaman dimuat
    setTimeout(pollNotifications, 5000);

    // Tombol Uji Audio (Opsional: Untuk debug di konsol browser)
    // console.log('Poller aktif. Interval: ' + dw_poll_vars.interval + 'ms');
});