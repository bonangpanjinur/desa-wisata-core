jQuery(document).ready(function($) {

    console.log('DW Admin Script Initialized');

    // Cek apakah variabel dari PHP masuk
    if (typeof dw_admin_vars === 'undefined') {
        console.error('ERROR CRITICAL: dw_admin_vars tidak ditemukan. Cek file admin-assets.php Anda.');
        // Fallback darurat (Mencegah crash, tapi mungkin nonce expired)
        var dw_admin_vars = { ajaxurl: ajaxurl, nonce: '' }; 
    }

    // =================================================
    // HANDLER VERIFIKASI PAKET (Sesuai ID/Class dari File Anda)
    // =================================================
    $(document).on('click', '.dw-verify-paket-btn', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var card = button.closest('.dw-request-card');
        var postId = button.data('id');       // ID Transaksi
        var actionType = button.data('type'); // approve / reject
        var spinner = $('#spinner-' + postId);
        var originalText = button.html();

        // 1. Konfirmasi User
        var pesan = (actionType === 'approve') 
            ? 'Yakin ingin MENERIMA pembayaran ini dan mengaktifkan paket?' 
            : 'Yakin ingin MENOLAK permintaan ini?';
        
        if (!confirm(pesan)) {
            return;
        }

        // 2. UI Loading State
        button.prop('disabled', true).text('Memproses...');
        button.siblings('button').prop('disabled', true); // Matikan tombol sebelahnya juga
        if(spinner.length) spinner.show();

        // 3. Kirim AJAX
        $.ajax({
            url: dw_admin_vars.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'dw_process_verifikasi_paket', // Action sesuai di ajax-handlers.php (Handler LAMA/EXISTING)
                security: dw_admin_vars.nonce,         // Nonce keamanan
                post_id: postId,
                action_type: actionType
            },
            success: function(response) {
                if(spinner.length) spinner.hide();

                if (response.success) {
                    // Sukses: Beri efek visual lalu reload
                    card.css('background-color', '#dcfce7').fadeOut(500, function() {
                        alert(response.data);
                        location.reload(); 
                    });
                } else {
                    // Gagal: Kembalikan tombol
                    alert('GAGAL: ' + (response.data || 'Terjadi kesalahan sistem.'));
                    button.html(originalText).prop('disabled', false);
                    button.siblings('button').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                if(spinner.length) spinner.hide();
                console.error('AJAX Error:', error);
                console.log(xhr.responseText);
                
                alert('Terjadi kesalahan koneksi server. Cek console browser untuk detail.');
                button.html(originalText).prop('disabled', false);
                button.siblings('button').prop('disabled', false);
            }
        });
    });

    // =================================================
    // HANDLER VERIFIKASI PEDAGANG (KTP/SELFIE)
    // =================================================
    $(document).on('click', '.dw-verify-pedagang-btn', function(e) {
        e.preventDefault();
        var button = $(this);
        var pedagangId = button.data('id');
        var actionType = button.data('type');

        if (!confirm('Proses verifikasi pedagang ini?')) return;

        button.prop('disabled', true).text('...');

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
                    button.prop('disabled', false).text(actionType === 'approve' ? 'Terima' : 'Tolak');
                }
            }
        });
    });

    // =================================================
    // HANDLER BULK PAYOUT
    // =================================================
    $(document).on('click', '.dw-bulk-payout-btn', function(e) {
        e.preventDefault();
        var button = $(this);
        var desaId = button.data('desa-id');
        
        if (!confirm('Konfirmasi pembayaran telah dilakukan?')) return;

        button.prop('disabled', true).text('Processing...');

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
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Tandai Lunas');
                }
            }
        });
    });

});