jQuery(document).ready(function($) {
    'use strict';

    /**
     * =========================================
     * 1. Dynamic Address Loading (Global Class-based)
     * =========================================
     * Works for page-desa.php and page-pedagang.php
     */
    
    // Function to load provinces on init
    function loadProvinces() {
        const $provSelects = $('.dw-region-prov');
        if ($provSelects.length === 0) return;

        $provSelects.each(function() {
            const $el = $(this);
            // Load only if empty (prevent double loading)
            if ($el.find('option').length <= 1) {
                // Fetch directly from API or you can create a WP AJAX handler for provinces if preferred
                $.ajax({
                    url: 'https://wilayah.id/api/provinces.json', 
                    type: 'GET',
                    success: function(response) {
                        let options = '<option value="">Pilih Provinsi</option>';
                        if (response.data) {
                            $.each(response.data, function(i, prov) {
                                options += `<option value="${prov.id}">${prov.name}</option>`;
                            });
                            $el.html(options).prop('disabled', false);
                            
                            // Restore selected value if exists (data-current attribute)
                            const current = $el.data('current');
                            if(current) $el.val(current).trigger('change');
                        }
                    }
                });
            }
        });
    }

    // Load Provinces on Init
    loadProvinces();

    // On Province Change -> Load Cities
    $(document).on('change', '.dw-region-prov', function() {
        const $row = $(this).closest('.dw-region-grid');
        const provId = $(this).val();
        const provName = $(this).find('option:selected').text();
        const $kota = $row.find('.dw-region-kota');
        const $kec = $row.find('.dw-region-kec');
        const $kel = $row.find('.dw-region-desa');
        
        // Update Hidden Text Inputs for Backend Save
        $row.parent().find('.dw-text-prov').val(provName);
        $row.parent().find('.dw-text-kota, .dw-text-kec, .dw-text-desa').val(''); // Reset child texts

        // Reset Child Dropdowns
        $kota.html('<option value="">Memuat Kota...</option>').prop('disabled', true);
        $kec.html('<option value="">Pilih Kecamatan</option>').prop('disabled', true);
        $kel.html('<option value="">Pilih Kelurahan</option>').prop('disabled', true);

        if(provId) {
            $.post(ajaxurl, {
                action: 'dw_get_cities',
                prov_id: provId
            }, function(res) {
                if(res.success) {
                    let opts = '<option value="">Pilih Kota/Kab</option>';
                    $.each(res.data.data, function(i, city) {
                        opts += `<option value="${city.code}">${city.name}</option>`;
                    });
                    $kota.html(opts).prop('disabled', false);
                    
                    // Restore selected value
                    const current = $kota.data('current');
                    if(current) $kota.val(current).trigger('change');
                }
            });
        }
    });

    // On City Change -> Load Districts
    $(document).on('change', '.dw-region-kota', function() {
        const $row = $(this).closest('.dw-region-grid');
        const cityId = $(this).val();
        const cityName = $(this).find('option:selected').text();
        const $kec = $row.find('.dw-region-kec');
        const $kel = $row.find('.dw-region-desa');

        // Update Hidden Text
        $row.parent().find('.dw-text-kota').val(cityName);
        
        $kec.html('<option value="">Memuat Kecamatan...</option>').prop('disabled', true);
        $kel.html('<option value="">Pilih Kelurahan</option>').prop('disabled', true);

        if(cityId) {
            $.post(ajaxurl, {
                action: 'dw_get_districts',
                city_id: cityId
            }, function(res) {
                if(res.success) {
                    let opts = '<option value="">Pilih Kecamatan</option>';
                    $.each(res.data.data, function(i, dist) {
                        opts += `<option value="${dist.code}">${dist.name}</option>`;
                    });
                    $kec.html(opts).prop('disabled', false);

                    // Restore selected value
                    const current = $kec.data('current');
                    if(current) $kec.val(current).trigger('change');
                }
            });
        }
    });

    // On District Change -> Load Villages
    $(document).on('change', '.dw-region-kec', function() {
        const $row = $(this).closest('.dw-region-grid');
        const distId = $(this).val();
        const distName = $(this).find('option:selected').text();
        const $kel = $row.find('.dw-region-desa');

        // Update Hidden Text
        $row.parent().find('.dw-text-kec').val(distName);

        $kel.html('<option value="">Memuat Kelurahan...</option>').prop('disabled', true);

        if(distId) {
            $.post(ajaxurl, {
                action: 'dw_get_villages',
                dist_id: distId
            }, function(res) {
                if(res.success) {
                    let opts = '<option value="">Pilih Kelurahan</option>';
                    $.each(res.data.data, function(i, vill) {
                        opts += `<option value="${vill.code}">${vill.name}</option>`;
                    });
                    $kel.html(opts).prop('disabled', false);

                    // Restore selected value
                    const current = $kel.data('current');
                    if(current) $kel.val(current).trigger('change');
                }
            });
        }
    });

    // On Village Change -> Update Text
    $(document).on('change', '.dw-region-desa', function() {
        const $row = $(this).closest('.dw-region-grid');
        const villName = $(this).find('option:selected').text();
        $row.parent().find('.dw-text-desa').val(villName);
    });

    /**
     * =========================================
     * 2. Promotion Management (Voucher)
     * =========================================
     */
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
        
        // Define dw_ajax if not exists (fallback)
        const nonce = (typeof dw_ajax !== 'undefined') ? dw_ajax.nonce : '';

        $submitBtn.text('Menyimpan...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=dw_save_promotion&nonce=' + nonce,
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
    $(document).on('click', '.dw-add-row', function(e) {
        e.preventDefault();
        const type = $(this).data('type');
        const tableBody = $('#table-' + type + '-packages tbody');
        
        if (tableBody.length === 0) return;

        const timestamp = new Date().getTime();
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

    $(document).on('click', '.dw-remove-row', function(e) {
        e.preventDefault();
        if(confirm('Hapus baris ini?')) {
            $(this).closest('tr').remove();
        }
    });

    $('#dw-ad-settings-form').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        const $btn = $('#btn-save-ad-settings');
        const originalText = $btn.text();
        const nonce = (typeof dw_ajax !== 'undefined') ? dw_ajax.nonce : '';

        $btn.text('Menyimpan Pengaturan...').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=dw_save_ad_settings&nonce=' + nonce,
            success: function(response) {
                if(response.success) {
                    alert('Pengaturan Harga & Kuota Iklan berhasil disimpan!');
                    $btn.text('Tersimpan').prop('disabled', false);
                    setTimeout(() => $btn.text(originalText), 2000);
                } else {
                    alert('Gagal: ' + (response.data ? response.data.message : 'Unknown error'));
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