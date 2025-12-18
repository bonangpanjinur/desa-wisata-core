jQuery(document).ready(function ($) {
    'use strict';

    /**
     * =========================================
     * 1. Dynamic Address Loading (Alamat)
     * =========================================
     */
    const $provinsiSelect = $('#dw_provinsi_id');
    const $kotaSelect = $('#dw_kota_id');
    const $kecamatanSelect = $('#dw_kecamatan_id');

    if ($provinsiSelect.length > 0) {
        $provinsiSelect.on('change', function () {
            const provinceId = $(this).val();
            $kotaSelect.html('<option value="">Memuat Kota...</option>').prop('disabled', true);
            $kecamatanSelect.html('<option value="">Pilih Kecamatan</option>').prop('disabled', true);

            if (provinceId) {
                $.ajax({
                    url: dw_ajax.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: { action: 'dw_get_cities', province_id: provinceId, nonce: dw_ajax.nonce },
                    success: function (response) {
                        if (response.success) {
                            let options = '<option value="">Pilih Kota/Kabupaten</option>';
                            $.each(response.data.cities, function (index, city) {
                                let cityName = city.city_name || city.name;
                                let cityId = city.city_id || city.id;
                                let cityType = city.type || '';
                                options += `<option value="${cityId}">${cityType} ${cityName}</option>`;
                            });
                            $kotaSelect.html(options).prop('disabled', false);
                        } else {
                            $kotaSelect.html('<option value="">Gagal Memuat</option>');
                        }
                    }
                });
            }
        });
    }

    if ($kotaSelect.length > 0) {
        $kotaSelect.on('change', function () {
            const cityId = $(this).val();
            if ($kecamatanSelect.length > 0) {
                $kecamatanSelect.html('<option value="">Memuat Kecamatan...</option>').prop('disabled', true);
                if (cityId) {
                    $.ajax({
                        url: dw_ajax.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: { action: 'dw_get_subdistricts', city_id: cityId, nonce: dw_ajax.nonce },
                        success: function (response) {
                            if (response.success) {
                                let options = '<option value="">Pilih Kecamatan</option>';
                                $.each(response.data.subdistricts, function (index, sub) {
                                    options += `<option value="${sub.subdistrict_id}">${sub.subdistrict_name}</option>`;
                                });
                                $kecamatanSelect.html(options).prop('disabled', false);
                            } else {
                                $kecamatanSelect.html('<option value="">Data tidak tersedia</option>');
                            }
                        }
                    });
                }
            }
        });
    }


    /**
     * =========================================
     * 2. Promotion Management (Voucher)
     * =========================================
     */
    // Menggunakan delegate event agar lebih robust
    $(document).on('click', '.dw-add-promo-btn', function(e) {
        e.preventDefault();
        $('#dw-promotion-form')[0].reset();
        $('#promo_id').val('');
        $('#modal-title').text('Tambah Voucher Diskon');
        $('#dw-promo-modal').fadeIn();
    });

    $(document).on('click', '.dw-close-modal', function() {
        $('#dw-promo-modal').fadeOut();
    });

    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).is('#dw-promo-modal')) {
            $('#dw-promo-modal').fadeOut();
        }
    });

    $('#dw-promotion-form').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.text();

        $submitBtn.text('Menyimpan...').prop('disabled', true);

        $.ajax({
            url: dw_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=dw_save_promotion&nonce=' + dw_ajax.nonce,
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $submitBtn.text(originalText).prop('disabled', false);
                }
            },
            error: function () {
                alert('Gagal terkoneksi ke server.');
                $submitBtn.text(originalText).prop('disabled', false);
            }
        });
    });

    /**
     * =========================================
     * 3. Ad Settings Management (Paket Iklan)
     * =========================================
     */
    
    // Helper: Tambah Baris (Menggunakan Event Delegation)
    $(document).on('click', '.dw-add-row', function(e) {
        e.preventDefault();
        
        const type = $(this).data('type'); // banner, wisata, produk
        const tableBody = $('#table-' + type + '-packages tbody');
        
        // Debugging: Cek apakah tombol terdeteksi
        console.log('Tombol Tambah Baris Diklik: ' + type);

        if (tableBody.length === 0) {
            console.error('Tabel tidak ditemukan: #table-' + type + '-packages tbody');
            return;
        }

        const timestamp = new Date().getTime(); // Unique ID

        // Hapus pesan "Belum ada paket" jika ada
        tableBody.find('.empty-row').remove();

        const newRow = `
            <tr>
                <td><input type="text" name="ad_packages[${type}][${timestamp}][name]" class="regular-text" style="width:100%" placeholder="Contoh: Paket 7 Hari" required></td>
                <td><input type="number" name="ad_packages[${type}][${timestamp}][days]" class="small-text" placeholder="7" required> Hari</td>
                <td><input type="number" name="ad_packages[${type}][${timestamp}][price]" class="regular-text" placeholder="50000" required></td>
                <td><input type="number" name="ad_packages[${type}][${timestamp}][quota]" class="small-text" placeholder="5" required></td>
                <td><button type="button" class="button dw-remove-row"><span class="dashicons dashicons-trash"></span></button></td>
            </tr>
        `;
        
        tableBody.append(newRow);
    });

    // Helper: Hapus Baris
    $(document).on('click', '.dw-remove-row', function(e) {
        e.preventDefault();
        if(confirm('Hapus baris ini?')) {
            $(this).closest('tr').remove();
        }
    });

    // Save Ad Settings AJAX
    $('#dw-ad-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const $btn = $('#btn-save-ad-settings');
        const originalText = $btn.text();

        $btn.text('Menyimpan Pengaturan...').prop('disabled', true);

        $.ajax({
            url: dw_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=dw_save_ad_settings&nonce=' + dw_ajax.nonce,
            success: function(response) {
                if(response.success) {
                    alert('Pengaturan Harga & Kuota Iklan berhasil disimpan!');
                    $btn.text('Tersimpan').prop('disabled', false);
                    setTimeout(() => $btn.text(originalText), 2000);
                } else {
                    alert('Gagal: ' + response.data.message);
                    $btn.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('Terjadi kesalahan server saat menyimpan.');
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });

});