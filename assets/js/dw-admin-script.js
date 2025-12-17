jQuery(document).ready(function($) {
    
    // =================================================
    // 1. HANDLER VERIFIKASI PAKET (Terima / Tolak)
    // =================================================
    $('.dw-verify-paket-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var postId = button.data('id');
        var actionType = button.data('type'); // 'approve' atau 'reject'
        var originalText = button.text();

        if (!confirm('Apakah Anda yakin ingin ' + (actionType === 'approve' ? 'menerima' : 'menolak') + ' paket ini?')) {
            return;
        }

        button.text('Memproses...').prop('disabled', true);

        $.ajax({
            url: dw_admin_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'dw_process_verifikasi_paket',
                security: dw_admin_vars.nonce, // Harus cocok dengan check_ajax_referer di PHP
                post_id: postId,
                action_type: actionType
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload(); // Reload halaman untuk melihat perubahan status
                } else {
                    alert('Gagal: ' + response.data);
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('Terjadi kesalahan koneksi.');
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // =================================================
    // 2. HANDLER VERIFIKASI PEDAGANG (Dokumen)
    // =================================================
    $('.dw-verify-pedagang-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var pedagangId = button.data('id');
        var actionType = button.data('type');
        var originalText = button.text();

        if (!confirm('Lanjutkan proses verifikasi pedagang ini?')) {
            return;
        }

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
                    button.text(originalText).prop('disabled', false);
                }
            }
        });
    });

    // =================================================
    // 3. HANDLER PAYOUT KOMISI (Bayar / Tolak)
    // =================================================
    $('.dw-payout-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var komisiId = button.data('id');
        var status = button.data('status'); // 'paid' atau 'rejected'
        var originalText = button.text();

        if (!confirm('Ubah status pembayaran komisi ini?')) {
            return;
        }

        button.text('...').prop('disabled', true);

        $.ajax({
            url: dw_admin_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'dw_process_payout_komisi',
                security: dw_admin_vars.nonce,
                komisi_id: komisiId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Gagal: ' + response.data);
                    button.text(originalText).prop('disabled', false);
                }
            }
        });
    });

});