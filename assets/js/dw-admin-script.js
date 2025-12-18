/**
 * Lokasi: assets/js/dw-admin-script.js
 * Deskripsi: Logika Admin (Cascading Dropdown, Promo, Ad Settings)
 */
jQuery(document).ready(function($) {
    'use strict';

    const dw_ajax_url = (typeof dw_ajax !== 'undefined') ? dw_ajax.ajax_url : ajaxurl;

    /**
     * =========================================
     * 1. Dynamic Address Loading (Wilayah.id)
     * =========================================
     */
    const sel = {
        prov: '.dw-region-prov',
        kota: '.dw-region-kota',
        kec:  '.dw-region-kec',
        desa: '.dw-region-desa'
    };

    function loadRegionData(action, paramName, paramValue, $target, nextActions = []) {
        $target.html('<option value="">Memuat...</option>').prop('disabled', true);
        
        const data = { action: action };
        if(paramName) data[paramName] = paramValue;

        $.ajax({
            url: dw_ajax_url,
            data: data,
            success: function(res) {
                if (res.success) {
                    let html = `<option value="">-- Pilih --</option>`;
                    const currentId = $target.data('current');
                    res.data.forEach(item => {
                        const selected = (currentId && currentId == item.id) ? 'selected' : '';
                        html += `<option value="${item.id}" ${selected}>${item.name}</option>`;
                    });
                    $target.html(html).prop('disabled', false);

                    // Auto trigger next level if "current" value exists (for edit page)
                    if(currentId) {
                        $target.trigger('change');
                        $target.data('current', ''); // clear after use
                    }
                }
            }
        });
    }

    // Initial Load Provinces
    if ($(sel.prov).length > 0) {
        loadRegionData('dw_fetch_provinces', null, null, $(sel.prov));
    }

    $(document).on('change', sel.prov, function() {
        const id = $(this).val();
        const text = $(this).find('option:selected').text();
        $('.dw-text-prov').val(id ? text : '');
        $(sel.kec + ',' + sel.desa).html('<option value="">-- Pilih --</option>').prop('disabled', true);
        if(id) loadRegionData('dw_fetch_regencies', 'province_id', id, $(sel.kota));
    });

    $(document).on('change', sel.kota, function() {
        const id = $(this).val();
        const text = $(this).find('option:selected').text();
        $('.dw-text-kota').val(id ? text : '');
        $(sel.desa).html('<option value="">-- Pilih --</option>').prop('disabled', true);
        if(id) loadRegionData('dw_fetch_districts', 'regency_id', id, $(sel.kec));
    });

    $(document).on('change', sel.kec, function() {
        const id = $(this).val();
        const text = $(this).find('option:selected').text();
        $('.dw-text-kec').val(id ? text : '');
        if(id) loadRegionData('dw_fetch_villages', 'district_id', id, $(sel.desa));
    });

    $(document).on('change', sel.desa, function() {
        const text = $(this).find('option:selected').text();
        $('.dw-text-desa').val($(this).val() ? text : '');
    });

    /**
     * =========================================
     * 2. Promotion & Ad Settings (Keep Existing)
     * =========================================
     */
    
    // Modal Promo
    $('.dw-add-promo-btn').on('click', function(e) {
        e.preventDefault();
        $('#dw-promotion-form')[0].reset();
        $('#promo_id').val('');
        $('#dw-promo-modal').fadeIn();
    });

    $('.dw-close-modal').on('click', function() { $('#dw-promo-modal').fadeOut(); });

    // Ad Settings Rows
    $('.dw-add-row').on('click', function(e) {
        e.preventDefault();
        const type = $(this).data('type');
        const tableBody = $('#table-' + type + '-packages tbody');
        const ts = new Date().getTime();
        tableBody.find('.empty-row').remove();
        tableBody.append(`
            <tr>
                <td><input type="text" name="ad_packages[${type}][${ts}][name]" style="width:100%" required></td>
                <td><input type="number" name="ad_packages[${type}][${ts}][days]" class="small-text" required> Hari</td>
                <td><input type="number" name="ad_packages[${type}][${ts}][price]" class="regular-text" required></td>
                <td><button type="button" class="button dw-remove-row"><span class="dashicons dashicons-trash"></span></button></td>
            </tr>
        `);
    });

    $(document).on('click', '.dw-remove-row', function() { $(this).closest('tr').remove(); });
});