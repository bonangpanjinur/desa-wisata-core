jQuery(document).ready(function($) {
    'use strict';

    /**
     * =========================================================================
     * 1. PENANGANAN ALAMAT / WILAYAH (FIX ERROR PROXY/DNS)
     * Menggunakan AJAX (admin-ajax.php) alih-alih REST API untuk stabilitas.
     * =========================================================================
     */
    
    // Fungsi umum untuk memuat wilayah
    function loadWilayah(type, parentId, targetSelector, placeholder) {
        var $target = $(targetSelector);
        
        // Reset dropdown target
        $target.empty().append('<option value="">Memuat...</option>').prop('disabled', true);
        
        // Reset anak-anaknya juga jika ada (Chain Reaction)
        if (type === 'kabupaten') {
            $('#kecamatan').empty().append('<option value="">Pilih Kecamatan</option>').prop('disabled', true);
            $('#kelurahan').empty().append('<option value="">Pilih Kelurahan</option>').prop('disabled', true);
        } else if (type === 'kecamatan') {
            $('#kelurahan').empty().append('<option value="">Pilih Kelurahan</option>').prop('disabled', true);
        }

        // Panggil AJAX Handler (dw_get_wilayah)
        $.ajax({
            url: dw_admin_vars.ajax_url, // Menggunakan URL relatif (admin-ajax.php)
            method: 'GET',
            data: {
                action: 'dw_get_wilayah', // Action yang kita buat di PHP
                type: type,
                id: parentId,
                nonce: dw_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value="">' + placeholder + '</option>';
                    $.each(response.data, function(index, item) {
                        // Sesuaikan field ID dan Nama sesuai respon API
                        var id = item.id; 
                        var name = item.name || item.nama; // Handle variasi nama field
                        options += '<option value="' + id + '">' + name + '</option>';
                    });
                    $target.html(options).prop('disabled', false);
                    
                    // Trigger Select2 update jika digunakan
                    if ($.fn.select2) {
                        $target.trigger('change');
                    }
                } else {
                    $target.html('<option value="">Gagal memuat data</option>');
                    alert('Gagal memuat wilayah: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $target.html('<option value="">Error koneksi</option>');
            }
        });
    }

    // A. Load Provinsi saat halaman dimuat (jika dropdown ada)
    if ($('#provinsi').length > 0) {
        loadWilayah('provinsi', '', '#provinsi', 'Pilih Provinsi');
    }

    // B. Event Listener: Ganti Provinsi -> Load Kabupaten
    $(document).on('change', '#provinsi', function() {
        var provId = $(this).val();
        if (provId) {
            loadWilayah('kabupaten', provId, '#kota', 'Pilih Kota/Kabupaten');
        } else {
            $('#kota').empty().prop('disabled', true);
        }
    });

    // C. Event Listener: Ganti Kota -> Load Kecamatan
    $(document).on('change', '#kota', function() {
        var kotaId = $(this).val();
        if (kotaId) {
            loadWilayah('kecamatan', kotaId, '#kecamatan', 'Pilih Kecamatan');
        } else {
            $('#kecamatan').empty().prop('disabled', true);
        }
    });

    // D. Event Listener: Ganti Kecamatan -> Load Kelurahan
    $(document).on('change', '#kecamatan', function() {
        var kecId = $(this).val();
        if (kecId) {
            loadWilayah('kelurahan', kecId, '#kelurahan', 'Pilih Kelurahan');
        } else {
            $('#kelurahan').empty().prop('disabled', true);
        }
    });

    // E. Event Listener: Ganti Kelurahan -> Cek Auto Verify (Khusus Halaman Pedagang)
    $(document).on('change', '#kelurahan', function() {
        var kelId = $(this).val();
        if (kelId && $('#dw_auto_verify_status').length > 0) {
            // Cek apakah desa ini terdaftar di sistem untuk verifikasi otomatis
            $.post(dw_admin_vars.ajax_url, {
                action: 'dw_check_desa_match_from_address',
                kel_id: kelId,
                nonce: dw_admin_vars.nonce
            }, function(res) {
                if (res.success && res.data.matched) {
                    $('#dw_auto_verify_status').html('<span style="color:green; font-weight:bold;">✓ Terverifikasi: ' + res.data.nama_desa + '</span>');
                } else {
                    $('#dw_auto_verify_status').html('<span style="color:orange;">Menunggu Verifikasi Manual</span>');
                }
            });
        }
    });

    /**
     * =========================================================================
     * 2. INISIALISASI PLUGIN (Select2, Color Picker, dll)
     * =========================================================================
     */
    if ($.fn.select2) {
        $('.dw-select2').select2({
            width: '100%'
        });
    }

    if ($.fn.wpColorPicker) {
        $('.dw-color-field').wpColorPicker();
    }

    // Tab Navigation Sederhana
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').hide();
        $($(this).attr('href')).show();
    });

    /**
     * =========================================================================
     * 3. UPLOAD GAMBAR (Media Uploader WordPress)
     * =========================================================================
     */
    var mediaUploader;
    $('.dw-upload-btn').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var targetId = button.data('target');
        var previewId = button.data('preview');

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Pilih Gambar',
            button: { text: 'Gunakan Gambar Ini' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.url); // Simpan URL
            if (previewId) {
                $('#' + previewId).attr('src', attachment.url).show();
            }
        });

        mediaUploader.open();
    });

    /**
     * =========================================================================
     * 4. FITUR CHAT (AJAX Send)
     * =========================================================================
     */
    $('#dw-chat-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $input = $form.find('textarea[name="message"]');
        
        if ($.trim($input.val()) === '') return;

        $btn.prop('disabled', true).text('Mengirim...');

        $.ajax({
            url: dw_admin_vars.ajax_url,
            method: 'POST',
            data: {
                action: 'dw_send_message',
                order_id: $form.find('input[name="order_id"]').val(),
                message: $input.val(),
                nonce: dw_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Reload untuk melihat pesan baru
                } else {
                    alert('Gagal mengirim: ' + response.data.message);
                    $btn.prop('disabled', false).text('Kirim');
                }
            },
            error: function() {
                alert('Terjadi kesalahan jaringan.');
                $btn.prop('disabled', false).text('Kirim');
            }
        });
    });

});