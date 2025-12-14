jQuery(document).ready(function($) {
    
    // API URL untuk wilayah indonesia (Gunakan CDN umum atau endpoint lokal jika punya)
    const API_WILAYAH_BASE = 'https://www.emsifa.com/api-wilayah-indonesia/api';

    // Helper untuk load data
    function loadWilayah(type, parentId, targetSelector, selectedValue = null) {
        let url = '';
        if (type === 'provinces') url = `${API_WILAYAH_BASE}/provinces.json`;
        if (type === 'regencies') url = `${API_WILAYAH_BASE}/regencies/${parentId}.json`;
        if (type === 'districts') url = `${API_WILAYAH_BASE}/districts/${parentId}.json`;
        if (type === 'villages')  url = `${API_WILAYAH_BASE}/villages/${parentId}.json`;

        if(!url) return;

        $(targetSelector).html('<option>Loading...</option>').prop('disabled', true);

        $.getJSON(url, function(data) {
            let html = '<option value="">-- Pilih --</option>';
            data.forEach(item => {
                let isSelected = (selectedValue && String(item.id) === String(selectedValue)) ? 'selected' : '';
                html += `<option value="${item.id}" ${isSelected}>${item.name}</option>`;
            });
            $(targetSelector).html(html).prop('disabled', false);
            
            // Trigger change manual jika ada selected value agar anak-anaknya ke-load juga
            if(selectedValue) {
                $(targetSelector).trigger('change'); 
            }
        });
    }

    // --- LOGIKA ON LOAD (MODE EDIT) ---
    // Cek apakah dropdown punya atribut 'data-selected' yang diisi PHP
    const savedProv = $('#select_provinsi').data('selected');
    const savedKab  = $('#select_kabupaten').data('selected');
    const savedKec  = $('#select_kecamatan').data('selected');
    const savedKel  = $('#select_kelurahan').data('selected');

    // 1. Load Provinsi Pertama kali
    loadWilayah('provinces', null, '#select_provinsi', savedProv);

    // --- EVENT LISTENERS ---
    
    // Provinsi Change -> Load Kabupaten
    $('#select_provinsi').on('change', function() {
        let id = $(this).val();
        let name = $(this).find("option:selected").text();
        $('#provinsi_nama').val(name); // Simpan nama provinsi

        // Jika sedang initial load (otomatis trigger), gunakan savedKab, jika user ganti manual, null
        let nextSelected = (String(id) === String(savedProv)) ? savedKab : null;
        if(id) loadWilayah('regencies', id, '#select_kabupaten', nextSelected);
        else $('#select_kabupaten').html('<option>--</option>').prop('disabled', true);
    });

    // Kabupaten Change -> Load Kecamatan
    $('#select_kabupaten').on('change', function() {
        let id = $(this).val();
        let name = $(this).find("option:selected").text();
        $('#kabupaten_nama').val(name); // Simpan nama kabupaten

        let nextSelected = (String(id) === String(savedKab)) ? savedKec : null;
        if(id) loadWilayah('districts', id, '#select_kecamatan', nextSelected);
        else $('#select_kecamatan').html('<option>--</option>').prop('disabled', true);
    });

    // Kecamatan Change -> Load Kelurahan
    $('#select_kecamatan').on('change', function() {
        let id = $(this).val();
        let name = $(this).find("option:selected").text();
        $('#kecamatan_nama').val(name); // Simpan nama kecamatan

        let nextSelected = (String(id) === String(savedKec)) ? savedKel : null;
        if(id) loadWilayah('villages', id, '#select_kelurahan', nextSelected);
        else $('#select_kelurahan').html('<option>--</option>').prop('disabled', true);
    });

    // Kelurahan Change -> Simpan Nama Kelurahan
    $('#select_kelurahan').on('change', function() {
        let name = $(this).find("option:selected").text();
        $('#kelurahan_nama').val(name); // Simpan nama kelurahan
    });

    // Helper: Hapus Notifikasi setelah 3 detik
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 3000);

});