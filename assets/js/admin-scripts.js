jQuery(document).ready(function($) {
    
    // Debugging: Cek apakah script dimuat
    console.log('DW Admin Script Loaded!');

    // =================================================
    // 1. HANDLER VERIFIKASI PAKET (Terima / Tolak)
    // =================================================
    // Event listener ini mendengarkan klik pada class .dw-verify-paket-btn
    $(document).on('click', '.dw-verify-paket-btn', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var postId = button.data('id');       // Mengambil data-id="..."
        var actionType = button.data('type'); // Mengambil data-type="..."
        var originalText = button.html();

        // Konfirmasi
        var pesan = (actionType === 'approve') ? 'Terima paket ini dan aktifkan kuota?' : 'Tolak paket ini?';
        if (!confirm(pesan)) {
            return;
        }

        // Ubah tombol jadi loading
        button.text('Memproses...').prop('disabled', true);

        // Kirim AJAX ke WordPress
        $.ajax({
            url: dw_admin_vars.ajaxurl, // URL admin-ajax.php
            type: 'POST',
            data: {
                action: 'dw_process_verifikasi_paket', // Harus sama dengan add_action wp_ajax_... di PHP
                security: dw_admin_vars.nonce,         // Kunci keamanan
                post_id: postId,
                action_type: actionType
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data); // Tampilkan pesan sukses dari PHP
                    location.reload();    // Refresh halaman
                } else {
                    alert('Gagal: ' + response.data);
                    button.html(originalText).prop('disabled', false); // Kembalikan tombol jika gagal
                }
            },
            error: function(xhr, status, error) {
                console.error(error);
                alert('Terjadi kesalahan koneksi server.');
                button.html(originalText).prop('disabled', false);
            }
        });
    });

    // =================================================
    // 2. HANDLER VERIFIKASI PEDAGANG
    // =================================================
    $(document).on('click', '.dw-verify-pedagang-btn', function(e) {
        e.preventDefault();
        var button = $(this);
        var pedagangId = button.data('id');
        var actionType = button.data('type');
        var originalText = button.html();

        if (!confirm('Proses verifikasi pedagang ini?')) return;

        button.text('...').prop('disabled', true);

        $.ajax({
            url: dw_admin_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'dw_verify_pedagang',
                security: dw_admin_vars.nonce,
                pedagang_id: pedagangId,
                action_type: actionType
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    button.html(originalText).prop('disabled', false);
                }
            }
        });
    });

    // =================================================
    // 3. HANDLER BULK PAYOUT (KOMISI)
    // =================================================
    $(document).on('click', '.dw-bulk-payout-btn', function(e) {
        e.preventDefault();
        var button = $(this);
        var desaId = button.data('desa-id');
        var amount = button.data('amount');
        var desaName = button.data('desa-name');
        var originalText = button.html();

        if (!confirm('Konfirmasi transfer Rp ' + amount + ' ke ' + desaName + ' sudah dilakukan?')) return;

        button.text('Processing...').prop('disabled', true);

        $.ajax({
            url: dw_admin_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'dw_process_bulk_payout_desa',
                security: dw_admin_vars.nonce,
                desa_id: desaId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Gagal: ' + response.data);
                    button.html(originalText).prop('disabled', false);
                }
            }
        });
    });

});