jQuery(document).ready(function($) {
    
    /**
     * LOGIKA WILAYAH INDONESIA (UNIVERSAL)
     * Menggunakan API Internal Plugin (Cached) untuk performa maksimal.
     * Bekerja otomatis pada element dengan class:
     * - .dw-provinsi-select
     * - .dw-kabupaten-select
     * - .dw-kecamatan-select
     * - .dw-desa-select
     */

    // Ambil Base URL dari localize script (init.php)
    const API_BASE = dw_admin_vars.rest_url + 'alamat/';
    const API_NONCE = dw_admin_vars.rest_nonce;

    function loadWilayah(type, parentId, targetSelector) {
        if (!parentId && type !== 'provinsi') return;

        let endpoint = '';
        if (type === 'kabupaten') endpoint = `provinsi/${parentId}/kabupaten`;
        if (type === 'kecamatan') endpoint = `kabupaten/${parentId}/kecamatan`;
        if (type === 'kelurahan') endpoint = `kecamatan/${parentId}/kelurahan`;

        let $target = $(targetSelector);
        $target.html('<option value="">Memuat...</option>').prop('disabled', true);

        $.ajax({
            url: API_BASE + endpoint,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', API_NONCE);
            },
            success: function(response) {
                let html = '<option value="">-- Pilih --</option>';
                // Handle response format (kadang direct array, kadang dalam object data)
                let data = response.data ? response.data : response; 
                
                if (Array.isArray(data)) {
                    data.forEach(function(item) {
                        html += `<option value="${item.id}">${item.name}</option>`;
                    });
                }
                
                $target.html(html).prop('disabled', false);
                
                // [FITUR] Auto-select jika sedang mode edit (ada atribut data-selected)
                let savedVal = $target.attr('data-selected');
                if (savedVal) {
                    $target.val(savedVal).trigger('change');
                    $target.removeAttr('data-selected'); // Hapus agar tidak trigger lagi
                }
            },
            error: function() {
                $target.html('<option value="">Gagal memuat data</option>');
            }
        });
    }

    // --- EVENT LISTENERS (DELEGATED) ---
    // Menggunakan 'body' agar jalan juga di elemen yang diload via AJAX/Dinamic

    // 1. PROVINSI CHANGE
    $('body').on('change', '.dw-provinsi-select, #dw_provinsi, #select_provinsi', function() {
        let id = $(this).val();
        let name = $(this).find('option:selected').text();
        
        // Simpan nama ke hidden input (jika ada)
        $('.dw-provinsi-nama').val(name);
        
        // Reset anak-anaknya
        $('.dw-kabupaten-select, #dw_kabupaten, #select_kabupaten').html('<option value="">-- Pilih --</option>').prop('disabled', true);
        $('.dw-kecamatan-select, #dw_kecamatan, #select_kecamatan').html('<option value="">-- Pilih --</option>').prop('disabled', true);
        $('.dw-desa-select, #dw_desa, #select_kelurahan').html('<option value="">-- Pilih --</option>').prop('disabled', true);

        // Load Kabupaten
        if(id) loadRegion('kabupaten', id, '.dw-kabupaten-select, #dw_kabupaten, #select_kabupaten');
    });

    // 2. KABUPATEN CHANGE
    $('body').on('change', '.dw-kabupaten-select, #dw_kabupaten, #select_kabupaten', function() {
        let id = $(this).val();
        let name = $(this).find('option:selected').text();
        $('.dw-kabupaten-nama').val(name);

        $('.dw-kecamatan-select, #dw_kecamatan, #select_kecamatan').html('<option value="">-- Pilih --</option>').prop('disabled', true);
        $('.dw-desa-select, #dw_desa, #select_kelurahan').html('<option value="">-- Pilih --</option>').prop('disabled', true);

        if(id) loadRegion('kecamatan', id, '.dw-kecamatan-select, #dw_kecamatan, #select_kecamatan');
    });

    // 3. KECAMATAN CHANGE
    $('body').on('change', '.dw-kecamatan-select, #dw_kecamatan, #select_kecamatan', function() {
        let id = $(this).val();
        let name = $(this).find('option:selected').text();
        $('.dw-kecamatan-nama').val(name);

        $('.dw-desa-select, #dw_desa, #select_kelurahan').html('<option value="">-- Pilih --</option>').prop('disabled', true);

        if(id) loadRegion('kelurahan', id, '.dw-desa-select, #dw_desa, #select_kelurahan');
    });

    // 4. KELURAHAN CHANGE
    $('body').on('change', '.dw-desa-select, #dw_desa, #select_kelurahan', function() {
        let name = $(this).find('option:selected').text();
        $('.dw-desa-nama').val(name);
    });

    // Wrapper function agar konsisten dengan event listener
    function loadRegion(type, id, selector) {
        loadWilayah(type, id, selector);
    }

    // --- INISIALISASI PADA HALAMAN LOAD ---
    // Jika ada dropdown provinsi yang punya data (mode edit), trigger change untuk load anak-anaknya
    // Tapi kita perlu hati-hati agar tidak me-reset value yang sudah tersimpan di PHP (data-selected)
    
    // Cek apakah ini load pertama
    if ($('.dw-provinsi-select, #dw_provinsi').length > 0) {
        // Tidak perlu load provinsi via AJAX lagi jika PHP sudah merendernya (di address-api.php)
        // Cukup trigger logika untuk children jika ada data-selected
        
        /* CATATAN: Di file PHP (page-desa.php), Anda sudah merender <option> untuk Provinsi.
           Jadi kita TIDAK PERLU me-load provinsi via JS lagi.
           Kita hanya perlu menangani event 'change' atau trigger manual untuk load anak-anaknya.
        */
        
        let $kab = $('.dw-kabupaten-select, #dw_kabupaten');
        let savedProv = $('.dw-provinsi-select, #dw_provinsi').val() || $('.dw-provinsi-select, #dw_provinsi').attr('data-selected');
        
        // Jika Kabupaten masih kosong/disabled tapi Provinsi ada isinya (Mode Edit), load Kabupaten
        if (savedProv && ($kab.children('option').length <= 1 || $kab.prop('disabled'))) {
             loadRegion('kabupaten', savedProv, '.dw-kabupaten-select, #dw_kabupaten');
        }
    }

    // --- FITUR LAIN: HAPUS NOTIFIKASI ---
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 4000);

});