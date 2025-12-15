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
        $target.empty().append('<option value="">Memuat data...</option>').prop('disabled', true);
        
        // Reset anak-anaknya juga jika ada (Chain Reaction)
        if (type === 'kabupaten') {
            $('#dw_kabupaten').empty().append('<option value="">-- Pilih Kabupaten --</option>').prop('disabled', true);
            $('#dw_kecamatan').empty().append('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            $('#dw_desa').empty().append('<option value="">-- Pilih Kelurahan --</option>').prop('disabled', true);
        } else if (type === 'kecamatan') {
            $('#dw_kecamatan').empty().append('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            $('#dw_desa').empty().append('<option value="">-- Pilih Kelurahan --</option>').prop('disabled', true);
        } else if (type === 'kelurahan') {
            $('#dw_desa').empty().append('<option value="">-- Pilih Kelurahan --</option>').prop('disabled', true);
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
                if (response.success && response.data.length > 0) {
                    var options = '<option value="">' + placeholder + '</option>';
                    $.each(response.data, function(index, item) {
                        // Sesuaikan field ID dan Nama sesuai respon API
                        var id = item.code || item.id; // API wilayah.id kadang pakai 'code'
                        var name = item.name || item.nama; 
                        options += '<option value="' + id + '">' + name + '</option>';
                    });
                    $target.html(options).prop('disabled', false);
                    
                    // Trigger Select2 update jika digunakan
                    if ($.fn.select2) {
                        $target.trigger('change.select2'); // Penting untuk refresh UI Select2
                    }
                } else {
                    $target.html('<option value="">Data Kosong / Gagal</option>');
                    console.error('Data wilayah kosong:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Wilayah:', error);
                $target.html('<option value="">Gagal Terhubung API</option>');
                // Fallback: coba reload sekali lagi jika timeout
            }
        });
    }

    // A. Load Provinsi saat halaman dimuat (jika dropdown ada dan belum ada isinya)
    if ($('#dw_provinsi').length > 0 && $('#dw_provinsi option').length <= 1) {
        loadWilayah('provinsi', '', '#dw_provinsi', '-- Pilih Provinsi --');
    }

    // B. Event Listener: Ganti Provinsi -> Load Kabupaten
    $(document).on('change', '#dw_provinsi', function() {
        var provId = $(this).val();
        var provName = $(this).find('option:selected').text();
        $('.dw-provinsi-nama').val(provName); // Simpan nama hidden

        if (provId) {
            loadWilayah('kabupaten', provId, '#dw_kabupaten', '-- Pilih Kabupaten --');
        }
    });

    // C. Event Listener: Ganti Kota -> Load Kecamatan
    $(document).on('change', '#dw_kabupaten', function() {
        var kotaId = $(this).val();
        var kotaName = $(this).find('option:selected').text();
        $('.dw-kabupaten-nama').val(kotaName);

        if (kotaId) {
            loadWilayah('kecamatan', kotaId, '#dw_kecamatan', '-- Pilih Kecamatan --');
        }
    });

    // D. Event Listener: Ganti Kecamatan -> Load Kelurahan
    $(document).on('change', '#dw_kecamatan', function() {
        var kecId = $(this).val();
        var kecName = $(this).find('option:selected').text();
        $('.dw-kecamatan-nama').val(kecName);

        if (kecId) {
            loadWilayah('kelurahan', kecId, '#dw_desa', '-- Pilih Kelurahan --');
        }
    });

    // E. Event Listener: Ganti Kelurahan -> Cek Auto Verify / Simpan Nama
    $(document).on('change', '#dw_desa', function() {
        var kelId = $(this).val();
        var kelName = $(this).find('option:selected').text();
        $('.dw-desa-nama').val(kelName);

        if (kelId && $('#dw-desa-match-status').length > 0) {
            // Cek apakah desa ini terdaftar di sistem untuk verifikasi otomatis
            // (Logika ini sudah ada di file PHP page-pedagang.php inline script, 
            // tapi kita biarkan di sini sebagai pelengkap jika inline script tidak jalan)
        }
    });

    /**
     * =========================================================================
     * 2. INISIALISASI PLUGIN (Select2, Color Picker, dll)
     * =========================================================================
     */
    if ($.fn.select2) {
        $('.dw-select2, .dw-provinsi-select, .dw-kabupaten-select, .dw-kecamatan-select, .dw-desa-select').select2({
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
        $('.tab-content').hide(); // Pastikan Anda punya div dengan class tab-content
        
        // Ambil href untuk target ID (misal #general)
        var target = $(this).attr('href');
        // Jika linknya query string (misal ?page=settings&tab=general), abaikan JS tab switching ini
        if(target.indexOf('?') === -1) {
             $(target).show();
        } else {
            window.location.href = target;
        }
    });

    /**
     * =========================================================================
     * 3. UPLOAD GAMBAR (Media Uploader WordPress)
     * =========================================================================
     */
    var mediaUploader;
    $('.dw-upload-button').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var wrapper = button.closest('.dw-image-uploader-wrapper');
        var inputUrl = wrapper.find('.dw-image-url');
        var imgPreview = wrapper.find('.dw-image-preview');
        var removeBtn = wrapper.find('.dw-remove-image-button');

        // Reuse uploader instance if available
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
            inputUrl.val(attachment.url);
            imgPreview.attr('src', attachment.url);
            removeBtn.show();
        });

        mediaUploader.open();
    });
    
    // Hapus Gambar
    $('.dw-remove-image-button').on('click', function(e){
        e.preventDefault();
        var wrapper = $(this).closest('.dw-image-uploader-wrapper');
        wrapper.find('.dw-image-url').val('');
        var defaultSrc = wrapper.find('.dw-image-preview').data('default-src') || 'https://placehold.co/300x150/e2e8f0/64748b?text=Pilih+Gambar';
        wrapper.find('.dw-image-preview').attr('src', defaultSrc);
        $(this).hide();
    });

    /**
     * =========================================================================
     * 4. FITUR CHAT (AJAX Send)
     * =========================================================================
     */
    $('#dw-admin-reply-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $('#dw-send-admin-reply');
        var message = $('#dw-reply-message').val();
        var produkId = $('#dw-chat-produk-id').val();
        // receiver_id di sini adalah pembeli, tapi kita pakai logic backend untuk menentukan target
        
        if ($.trim(message) === '') return;

        $btn.prop('disabled', true).text('Mengirim...');

        $.ajax({
            url: dw_admin_vars.ajax_url,
            method: 'POST',
            data: {
                action: 'dw_send_message',
                // Kita modifikasi ajax handler dw_handle_send_message agar support kirim via produk_id
                // atau gunakan order_id jika chat berbasis order. 
                // Karena ini fitur inkuiri produk, pastikan backend support.
                // Untuk sementara kita asumsikan backend butuh order_id, tapi di list table kita pakai produk_id.
                // SOLUSI: Kita perlu sesuaikan dw_handle_send_message di PHP nanti jika belum support produk_id.
                // Saat ini kita kirim dummy order_id 0 dan tambahan data.
                order_id: 0, 
                produk_id: produkId,
                message: message,
                nonce: dw_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); 
                } else {
                    alert('Gagal: ' + (response.data.message || 'Error'));
                    $btn.prop('disabled', false).text('Kirim Balasan');
                }
            },
            error: function() {
                alert('Kesalahan koneksi.');
                $btn.prop('disabled', false).text('Kirim Balasan');
            }
        });
    });

});