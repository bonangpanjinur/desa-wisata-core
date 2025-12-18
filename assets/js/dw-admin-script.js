jQuery(document).ready(function($){
    
    // 1. Inisialisasi Select2
    if ($.fn.select2) {
        $('.select2-villages, .select2-districts, .select2').select2({ width: '100%' });
    }

    // 2. Logika Tab Navigasi (Event Listener)
    $('.dw-tabs-nav li').on('click', function(e) {
        e.preventDefault();
        
        // Hapus kelas active dari semua tab dan konten
        $('.dw-tabs-nav li').removeClass('active');
        $('.dw-tab-pane').removeClass('active').hide();

        // Tambahkan kelas active ke tab yang diklik
        $(this).addClass('active');

        // Ambil target ID dari atribut data-target
        var targetId = $(this).data('target');
        var $target = $('#' + targetId);

        // Tampilkan konten target
        $target.fadeIn(200).addClass('active');

        // Khusus Tab Pengaturan: Trigger load ongkir jika belum dimuat
        if(targetId === 'tab-pengaturan') {
            var k = $('.dw-pd-kec').val();
            var c = $('.dw-pd-kota').val();
            // Panggil fungsi load ongkir jika kecamatan/kota sudah terpilih
            if(k) loadOngkirVillages(k);
            if(c) loadOngkirDistricts(c);
        }
    });

    // 3. Logika Wilayah Utama (Provinsi -> Kota -> Kecamatan -> Desa)
    
    // Load Provinsi
    $.post(ajaxurl, { action: 'dw_get_provinces' }, function(res){
        if(res.success && res.data){
            var $prov = $('.dw-pd-prov');
            var current = $prov.data('current');
            $prov.empty().append('<option value="">Pilih Provinsi</option>');
            $.each(res.data, function(i, v){
                $prov.append(new Option(v.name, v.id, false, (current == v.id)));
            });
            if(current) $prov.trigger('change');
        }
    }, 'json');

    // Change Provinsi -> Load Kota
    $('.dw-pd-prov').change(function(){
        var id = $(this).val();
        var text = $(this).find('option:selected').text();
        $('.dw-text-prov').val(text);
        var $kota = $('.dw-pd-kota');
        $kota.empty().append('<option value="">Memuat...</option>').prop('disabled', true);
        
        if(id){
            $.post(ajaxurl, { action: 'dw_get_cities', prov_id: id }, function(res){
                $kota.empty().append('<option value="">Pilih Kota/Kabupaten</option>').prop('disabled', false);
                if(res.success && res.data){
                    var current = $kota.data('current');
                    $.each(res.data, function(i, v){
                        $kota.append(new Option(v.name, v.id, false, (current == v.id)));
                    });
                    if(current && !$kota.data('loaded')) { 
                        $kota.data('loaded', true).trigger('change'); 
                    }
                }
            }, 'json');
        } else {
            $kota.empty().append('<option value="">Pilih Kota/Kabupaten</option>');
        }
    });

    // Change Kota -> Load Kecamatan
    $('.dw-pd-kota').change(function(){
        var id = $(this).val();
        var text = $(this).find('option:selected').text();
        $('.dw-text-kota').val(text);
        var $kec = $('.dw-pd-kec');
        $kec.empty().append('<option value="">Memuat...</option>').prop('disabled', true);

        // Update Ongkir Options for Beda Kecamatan (District level)
        if($('#tab-pengaturan').is(':visible') || $(this).data('loaded')) {
            loadOngkirDistricts(id);
        }

        if(id){
            $.post(ajaxurl, { action: 'dw_get_districts', city_id: id }, function(res){
                $kec.empty().append('<option value="">Pilih Kecamatan</option>').prop('disabled', false);
                if(res.success && res.data && res.data.data){
                    var current = $kec.data('current');
                    $.each(res.data.data, function(i, v){
                        $kec.append(new Option(v.name, v.code, false, (current == v.code)));
                    });
                    if(current && !$kec.data('loaded')) {
                        $kec.data('loaded', true).trigger('change');
                    }
                }
            }, 'json');
        } else {
            $kec.empty().append('<option value="">Pilih Kecamatan</option>');
        }
    });

    // Change Kecamatan -> Load Desa
    $('.dw-pd-kec').change(function(){
        var id = $(this).val();
        var text = $(this).find('option:selected').text();
        $('.dw-text-kec').val(text);
        var $desa = $('.dw-pd-desa');
        $desa.empty().append('<option value="">Memuat...</option>').prop('disabled', true);

        // Update Ongkir Options for Satu Kecamatan (Village level)
        if($('#tab-pengaturan').is(':visible') || $(this).data('loaded')) {
            loadOngkirVillages(id);
        }

        if(id){
            $.post(ajaxurl, { action: 'dw_get_villages', dist_id: id }, function(res){
                $desa.empty().append('<option value="">Pilih Kelurahan</option>').prop('disabled', false);
                if(res.success && res.data && res.data.data){
                    var current = $desa.data('current');
                    $.each(res.data.data, function(i, v){
                        $desa.append(new Option(v.name, v.code, false, (current == v.code)));
                    });
                }
            }, 'json');
        } else {
            $desa.empty().append('<option value="">Pilih Kelurahan</option>');
        }
    });

    $('.dw-pd-desa').change(function(){
        var text = $(this).find('option:selected').text();
        $('.dw-text-desa').val(text);
    });


    // 4. Logika Ongkir Auto-Populate (Fungsi Helper)

    function loadOngkirVillages(kecId) {
        var $villageSelects = $('select[name="ojek_dekat_desa[]"], select[name="ojek_jauh_desa[]"]');
        if(!kecId) { $villageSelects.empty(); return; }
        
        // Jangan reload jika parent ID sama (mencegah loop/reload berulang)
        if($villageSelects.data('parent-id') == kecId) return;

        $villageSelects.prop('disabled', true);
        $.post(ajaxurl, { action: 'dw_get_villages', dist_id: kecId }, function(res){
            $villageSelects.prop('disabled', false);
            if(res.success && res.data && res.data.data) {
                var villages = res.data.data;
                $villageSelects.each(function(){
                    var $sel = $(this);
                    var savedVals = $sel.val() || []; // Nilai saat ini/tersimpan
                    $sel.empty();
                    $.each(villages, function(i, v){
                        // Pertahankan pilihan jika ada
                        // v.code dikonversi ke string untuk keamanan perbandingan
                        var isSel = savedVals.includes(String(v.code));
                        $sel.append(new Option(v.name, v.code, isSel, isSel));
                    });
                    $sel.trigger('change'); // Refresh Select2 UI
                });
                $villageSelects.data('parent-id', kecId);
            }
        }, 'json');
    }

    function loadOngkirDistricts(kotaId) {
        var $districtSelects = $('select[name="ojek_beda_kec_dekat_ids[]"], select[name="ojek_beda_kec_jauh_ids[]"]');
        if(!kotaId) { $districtSelects.empty(); return; }

        if($districtSelects.data('parent-id') == kotaId) return;

        $districtSelects.prop('disabled', true);
        $.post(ajaxurl, { action: 'dw_get_districts', city_id: kotaId }, function(res){
            $districtSelects.prop('disabled', false);
            if(res.success && res.data && res.data.data) {
                var districts = res.data.data;
                $districtSelects.each(function(){
                    var $sel = $(this);
                    var savedVals = $sel.val() || [];
                    $sel.empty();
                    $.each(districts, function(i, d){
                        var isSel = savedVals.includes(String(d.code));
                        $sel.append(new Option(d.name, d.code, isSel, isSel));
                    });
                    $sel.trigger('change');
                });
                $districtSelects.data('parent-id', kotaId);
            }
        }, 'json');
    }

    // 5. Media Uploader WordPress
    $('.btn_upload').click(function(e){
        e.preventDefault();
        var target = $(this).data('target');
        var preview = $(this).data('preview');
        var frame = wp.media({ title: 'Pilih Gambar', multiple: false, library: { type: 'image' } });
        
        frame.on('select', function(){
            var url = frame.state().get('selection').first().toJSON().url;
            $(target).val(url);
            if(preview) $(preview).attr('src', url);
        });
        
        frame.open();
    });

});