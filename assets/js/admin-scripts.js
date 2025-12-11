/**
 * File Path: assets/js/admin-scripts.js
 *
 * PERBAIKAN:
 * - Memperbarui `initMediaAndGallery` untuk menangani tag anchor (`<a>`)
 * dengan kelas `.dw-confirm-link` selain form.
 * - Ini akan memperbaiki semua link "Hapus" di List Table yang
 * sebelumnya diblokir oleh browser.
 *
 * PERBAIKAN (REQUEST PENGGUNA):
 * - Mengubah selector di `initMediaAndGallery` dari `'.dw-upload-button'`
 * menjadi `'.dw-upload-button, .dw-gallery-button'`
 * - Ini akan memperbaiki masalah di mana tombol "Tambah/Kelola Galeri"
 * di meta box Produk dan Wisata tidak merespon saat diklik.
 *
 * --- PERUBAHAN (USER REQUEST: BRANDING) ---
 * - Menambahkan `initColorPicker` untuk mengaktifkan color picker di halaman settings.
 */
jQuery(function($) {
    'use strict';

    /**
     * Objek utama untuk mengelola semua skrip admin Desa Wisata.
     */
    var DesaWisataAdmin = {

        /**
         * Metode inisialisasi utama.
         */
        init: function() {
            this.initMediaAndGallery(); // Fungsi ini sekarang menangani semua uploader
            this.initProductVariations();
            this.initDynamicAddresses();
            this.initPedagangAddressAutofill();
            this.initSelect2();
            this.initAdminChatReply();
            this.initConfirmSubmit(); // Memperbaiki fungsi konfirmasi
            this.initColorPicker(); // BARU: Panggil fungsi color picker
        },

        /**
         * Menginisialisasi WordPress Media Uploader untuk gambar tunggal dan galeri.
         * Dibuat generik untuk menangani semua instance .dw-image-uploader-wrapper
         * dan .dw-gallery-wrapper.
         */
        initMediaAndGallery: function() {
            // 1a. Uploader untuk gambar tunggal (misal: foto desa, QRIS, bukti bayar)
            // PERBAIKAN: Menambahkan '.dw-gallery-button' ke selector
            $(document).on('click', '.dw-upload-button, .dw-gallery-button', function(e) {
                e.preventDefault();
                var button = $(this);
                // Tentukan apakah tombol yang diklik adalah untuk galeri atau uploader tunggal
                var isGallery = button.hasClass('dw-gallery-button');
                var $wrapper;
                
                if (isGallery) {
                    $wrapper = button.closest('.dw-gallery-wrapper');
                } else {
                    $wrapper = button.closest('.dw-image-uploader-wrapper');
                }

                var $image_url_field = $wrapper.find('.dw-image-url, .dw-gallery-ids'); // Targetkan field URL atau ID galeri
                var $image_preview = $wrapper.find('.dw-image-preview'); // Preview gambar tunggal
                var $previewContainer = $wrapper.find('.dw-gallery-preview'); // Preview galeri
                var $remove_button = $wrapper.find('.dw-remove-image-button, .dw-remove-gallery-item'); // Tombol hapus

                // Konfigurasi uploader
                var uploaderOptions = {
                    title: isGallery ? 'Pilih Gambar untuk Galeri' : 'Pilih Gambar',
                    button: { text: isGallery ? 'Tambahkan ke Galeri' : 'Gunakan Gambar Ini' },
                    multiple: isGallery ? 'add' : false, // Multiple hanya untuk galeri
                    library: { type: 'image' },
                };

                var mediaUploader = wp.media(uploaderOptions);

                // Jika galeri, handle pra-pemilihan
                if (isGallery) {
                    mediaUploader.on('open', function() {
                        var selection = mediaUploader.state().get('selection');
                        var existingIds = $image_url_field.val().split(',').filter(Number).map(Number);
                        existingIds.forEach(function(id) {
                            var attachment = wp.media.attachment(id);
                            attachment.fetch();
                            selection.add(attachment ? [attachment] : []);
                        });
                    });
                }

                // Setelah gambar dipilih
                mediaUploader.on('select', function() {
                    if (isGallery) {
                        // Handle galeri
                        var attachments = mediaUploader.state().get('selection').toJSON();
                        var newIds = [];
                        $previewContainer.html(''); // Kosongkan preview galeri
                        attachments.forEach(function(att) {
                            newIds.push(att.id);
                            // Tampilkan thumbnail
                            $previewContainer.append('<div class="gallery-item" data-id="' + att.id + '"><img src="' + (att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url) + '"/><a href="#" class="dw-remove-gallery-item">×</a></div>');
                        });
                        $image_url_field.val(newIds.join(',')); // Simpan ID yang digabung
                    } else {
                        // Handle gambar tunggal
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        if (attachment.url) {
                            $image_url_field.val(attachment.url); // Simpan URL lengkap
                            // Pilih ukuran preview yang sesuai
                            var previewUrl = attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                            if ($image_preview.length) {
                                $image_preview.attr('src', previewUrl);
                            }
                            // Tampilkan tombol hapus jika ada
                            var $singleRemoveButton = $wrapper.find('.dw-remove-image-button');
                            if($singleRemoveButton.length) {
                                $singleRemoveButton.show();
                            }
                        }
                    }
                });
                mediaUploader.open();
            });

            // 1b. Penghapus gambar tunggal
            $(document).on('click', '.dw-remove-image-button', function(e) {
                e.preventDefault();
                var $wrapper = $(this).closest('.dw-image-uploader-wrapper');
                var $image_url_field = $wrapper.find('.dw-image-url');
                var $image_preview = $wrapper.find('.dw-image-preview');
                var defaultSrc = $image_preview.data('default-src') || 'https://placehold.co/150x150/e2e8f0/64748b?text=Pilih+Gambar'; // Placeholder umum

                $image_url_field.val('');
                $image_preview.attr('src', defaultSrc);
                $(this).hide();
            });

            // 1c. Hapus item dari galeri
            $(document).on('click', '.dw-remove-gallery-item', function(e) {
                e.preventDefault();
                var $item = $(this).closest('.gallery-item');
                var itemId = $item.data('id');
                var $wrapper = $item.closest('.dw-gallery-wrapper');
                var $idsField = $wrapper.find('.dw-gallery-ids');
                var currentIds = $idsField.val().split(',').filter(Number).map(Number);
                var newIds = currentIds.filter(function(id) { return id !== itemId; });
                $idsField.val(newIds.join(','));
                $item.remove();

                // Jika galeri kosong, tampilkan pesan? (Opsional)
                if ($wrapper.find('.gallery-item').length === 0) {
                   // $wrapper.find('.dw-gallery-preview').html('<p>Galeri kosong.</p>');
                }
            });
        },


        initProductVariations: function() {
            // ... (Kode Variasi tetap sama) ...
            $('#dw-add-variation-button').on('click', function() {
                $('#dw-variations-container .dw-variation-row-empty').remove();
                var template = $('#dw-variation-row-template').html();
                $('#dw-variations-container').append(template);
            });

            $('#dw-variations-container').on('click', '.dw-remove-variation', function(e) {
                e.preventDefault();
                $(this).closest('tr').remove();
                if ($('#dw-variations-container .dw-variation-row').length === 0) {
                    $('#dw-variations-container').html('<tr class="dw-variation-row-empty"><td colspan="4">Klik "Tambah Variasi" untuk memulai.</td></tr>');
                }
            });
        },

        initDynamicAddresses: function() {
            // ... (Kode Alamat Dinamis tetap sama) ...
            $('.dw-address-wrapper').each(function() {
                var wrapper = $(this);
                var $provinsiSelect = wrapper.find('.dw-provinsi-select');
                var $kabupatenSelect = wrapper.find('.dw-kabupaten-select');
                var $kecamatanSelect = wrapper.find('.dw-kecamatan-select');
                var $desaSelect = wrapper.find('.dw-desa-select');

                function resetDropdown($select, message) {
                    $select.html('<option value="">' + message + '</option>').prop('disabled', true);
                }

                $provinsiSelect.on('change', function() {
                    var provinsiId = $(this).val();
                    var provinsiName = $(this).find('option:selected').text().trim();
                    wrapper.find('.dw-provinsi-nama').val(provinsiName);
                    resetDropdown($kabupatenSelect, 'Pilih Provinsi Dulu');
                    resetDropdown($kecamatanSelect, 'Pilih Kabupaten Dulu');
                    resetDropdown($desaSelect, 'Pilih Kecamatan Dulu');
                    if (!provinsiId) return;
                    $kabupatenSelect.html('<option>Memuat...</option>').prop('disabled', false);
                    $.post(ajaxurl, { action: 'dw_get_kabupaten', nonce: dw_admin_vars.nonce, provinsi_id: provinsiId }).done(function(response) { if (response.success) { $kabupatenSelect.html('<option value="">-- Pilih Kabupaten/Kota --</option>'); $.each(response.data, function(i, item) { $kabupatenSelect.append($('<option>', { value: item.code, text: item.name })); }); } else { resetDropdown($kabupatenSelect, 'Gagal memuat data'); } });
                });

                $kabupatenSelect.on('change', function() {
                    var kabupatenId = $(this).val();
                    var kabupatenName = $(this).find('option:selected').text().trim();
                    wrapper.find('.dw-kabupaten-nama').val(kabupatenName);
                    resetDropdown($kecamatanSelect, 'Pilih Kabupaten Dulu');
                    resetDropdown($desaSelect, 'Pilih Kecamatan Dulu');
                    if (!kabupatenId) return;
                    $kecamatanSelect.html('<option>Memuat...</option>').prop('disabled', false);
                    $.post(ajaxurl, { action: 'dw_get_kecamatan', nonce: dw_admin_vars.nonce, kabupaten_id: kabupatenId }).done(function(response) { if (response.success) { $kecamatanSelect.html('<option value="">-- Pilih Kecamatan --</option>'); $.each(response.data, function(i, item) { $kecamatanSelect.append($('<option>', { value: item.code, text: item.name })); }); } else { resetDropdown($kecamatanSelect, 'Gagal memuat data'); } });
                });

                $kecamatanSelect.on('change', function() {
                    var kecamatanId = $(this).val();
                    var kecamatanName = $(this).find('option:selected').text().trim();
                    wrapper.find('.dw-kecamatan-nama').val(kecamatanName);
                    resetDropdown($desaSelect, 'Pilih Kecamatan Dulu');
                    if (!kecamatanId) return;
                    $desaSelect.html('<option>Memuat...</option>').prop('disabled', false);
                    $.post(ajaxurl, { action: 'dw_get_desa', nonce: dw_admin_vars.nonce, kecamatan_id: kecamatanId }).done(function(response) { if (response.success) { $desaSelect.html('<option value="">-- Pilih Desa/Kelurahan --</option>'); $.each(response.data, function(i, item) { $desaSelect.append($('<option>', { value: item.code, text: item.name })); }); } else { resetDropdown($desaSelect, 'Gagal memuat data'); } });
                });

                $desaSelect.on('change', function() {
                    var desaName = $(this).find('option:selected').text().trim();
                    wrapper.find('.dw-desa-nama').val(desaName);
                });
            });
        },

        initPedagangAddressAutofill: function() {
            // ... (Kode Autofill Alamat tetap sama) ...
             function handlePedagangAddressAutofill() {
                var desaId = $('#id_desa.dw-desa-selector-for-autofill').val();
                var $addressText = $('#dw-pedagang-address-text');
                if (!desaId) {
                    $addressText.html('Pilih desa untuk melihat alamat.');
                    return;
                }
                $addressText.html('<i>Memuat alamat...</i>');
                $.post(dw_admin_vars.ajax_url, {
                    action: 'dw_get_desa_address',
                    nonce: dw_admin_vars.nonce,
                    desa_id: desaId
                }).done(function(response) {
                    if (response.success) {
                        $addressText.html(response.data.alamat || '<i>Alamat tidak tersedia.</i>');
                    } else {
                        $addressText.html('<i style="color:red;">Gagal memuat alamat.</i>');
                    }
                }).fail(function() {
                    $addressText.html('<i style="color:red;">Error koneksi.</i>');
                });
            }

            $(document).on('change', '#id_desa.dw-desa-selector-for-autofill', handlePedagangAddressAutofill);

            // Trigger saat load jika sudah ada nilai terpilih (misal saat edit)
            if ($('#id_desa.dw-desa-selector-for-autofill').length && $('#id_desa.dw-desa-selector-for-autofill').val()) {
                handlePedagangAddressAutofill();
            }
        },

        initSelect2: function() {
            // ... (Kode Select2 tetap sama) ...
            if ($('#id_user').length > 0 && $.fn.select2) { // Cek apakah select2 sudah dimuat
                try {
                    $('#id_user').select2({
                        width: '100%',
                        placeholder: 'Ketik untuk mencari nama atau email...',
                        allowClear: true
                    });
                } catch(e) {
                    console.error("Gagal menginisialisasi Select2:", e);
                }
            } else if ($('#id_user').length > 0) {
                 console.warn("Select2 library not loaded or function not available.");
            }
        },

        initAdminChatReply: function() {
            // ... (Kode Balas Chat tetap sama) ...
            $('#dw-admin-reply-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $button = $('#dw-send-admin-reply');
                var $messageField = $('#dw-reply-message');
                var $feedback = $('#dw-admin-reply-feedback');

                var message = $messageField.val().trim();
                var produkId = $('#dw-chat-produk-id').val();
                var receiverId = $('#dw-chat-receiver-id').val();

                if (message.length === 0) {
                    $feedback.html('<span style="color:red;">Pesan tidak boleh kosong.</span>');
                    return;
                }

                $button.prop('disabled', true).text('Mengirim...');
                $feedback.html('').hide();

                $.post(dw_admin_vars.ajax_url, {
                    action: 'dw_send_admin_reply_message',
                    nonce: dw_admin_vars.nonce,
                    product_id: produkId,
                    receiver_id: receiverId,
                    message: message
                }).done(function(response) {
                    if (response.success) {
                        $messageField.val('');
                        $feedback.html('<span style="color:green;">' + response.data.message + '</span>').fadeIn();

                        // **Refresh Halaman (atau muat ulang chat history via AJAX)**
                        // Untuk kemudahan, kita refresh halaman, tapi AJAX update lebih baik
                        // Menambahkan delay sedikit sebelum reload untuk user melihat pesan sukses
                        setTimeout(function() {
                           window.location.reload();
                        }, 1000);

                    } else {
                        $feedback.html('<span style="color:red;">' + (response.data.message || 'Gagal mengirim balasan.') + '</span>').fadeIn();
                        $button.prop('disabled', false).text('Kirim Balasan'); // Aktifkan lagi jika gagal
                    }
                }).fail(function() {
                    $feedback.html('<span style="color:red;">Error koneksi.</span>').fadeIn();
                    $button.prop('disabled', false).text('Kirim Balasan'); // Aktifkan lagi jika gagal koneksi
                });
                // Hapus always agar button tidak aktif lagi jika sukses (karena akan reload)
            });
        },

        /**
         * PERBAIKAN: Menginisialisasi konfirmasi untuk form DAN link (tag <a>).
         * Ini memperbaiki link "Hapus" yang diblokir `confirm()`.
         */
        initConfirmSubmit: function() {
            // 1. Handler untuk Form (sudah ada)
            $(document).on('submit', 'form.dw-confirm-form', function(e) {
                var $form = $(this);
                var message = $form.data('confirm-message') || 'Apakah Anda yakin ingin melanjutkan?';

                // Tampilkan dialog konfirmasi
                if (!confirm(message)) {
                    e.preventDefault(); // Batalkan submit jika user klik 'Cancel'
                }
                // Jika user klik 'OK', form akan disubmit secara normal.
            });

            // 2. PERBAIKAN: Handler untuk Link (tag <a>)
            $(document).on('click', 'a.dw-confirm-link', function(e) {
                var $link = $(this);
                var message = $link.data('confirm-message') || 'Apakah Anda yakin?';
                var href = $link.attr('href');

                // Tampilkan dialog konfirmasi
                if (!confirm(message)) {
                    e.preventDefault(); // Batalkan navigasi link jika user klik 'Cancel'
                }
                // Jika user klik 'OK', biarkan event klik berlanjut (navigasi ke href)
            });
        },

        /**
         * BARU: Menginisialisasi WordPress Color Picker.
         */
        initColorPicker: function() {
            if (typeof $.fn.wpColorPicker === 'function') {
                $('.dw-color-picker').wpColorPicker();
            } else {
                console.warn("wpColorPicker script not loaded.");
                // Fallback ke input color standar jika gagal
                $('.dw-color-picker').attr('type', 'color').css('width', '100px');
            }
        }
    };

    // Jalankan inisialisasi utama.
    DesaWisataAdmin.init();

});

