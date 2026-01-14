jQuery(document).ready(function($) {
    
    // Cek apakah ada elemen map di halaman
    if ($('#dw-map-picker').length === 0) {
        return;
    }

    var defaultLat = dw_maps.default_lat || -6.200000;
    var defaultLng = dw_maps.default_lng || 106.816666;
    
    // Cek jika sudah ada nilai tersimpan di input
    var savedLat = $('#input-latitude').val();
    var savedLng = $('#input-longitude').val();

    var initialLat = savedLat ? parseFloat(savedLat) : defaultLat;
    var initialLng = savedLng ? parseFloat(savedLng) : defaultLng;

    // Inisialisasi Peta
    var map = L.map('dw-map-picker').setView([initialLat, initialLng], 13);

    // Tambahkan Tile Layer (OpenStreetMap - Gratis)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Tambahkan Marker yang bisa digeser
    var marker = L.marker([initialLat, initialLng], {
        draggable: true
    }).addTo(map);

    // Event saat marker selesai digeser
    marker.on('dragend', function(event) {
        var position = marker.getLatLng();
        updateInputFields(position.lat, position.lng);
    });

    // Event saat peta diklik
    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateInputFields(e.latlng.lat, e.latlng.lng);
    });

    function updateInputFields(lat, lng) {
        $('#input-latitude').val(lat.toFixed(6));
        $('#input-longitude').val(lng.toFixed(6));
        
        // Jika ada fungsi callback hitung ongkir (di halaman checkout)
        if (typeof dwCalculateOngkir === 'function') {
            dwCalculateOngkir(lat, lng);
        }
    }

    // Fix map render issue saat dimuat di dalam tab tersembunyi/modal
    setTimeout(function(){ map.invalidateSize(); }, 400);
});

// Fungsi Global untuk Hitung Ongkir (Frontend)
function dwCalculateOngkir(lat, lng) {
    var pedagangId = jQuery('#pedagang_id').val();
    
    if (!pedagangId) return;

    jQuery('#ongkir-loading').show();
    jQuery('#ongkir-result').text('Menghitung...');

    jQuery.ajax({
        url: dw_maps.ajax_url,
        type: 'POST',
        data: {
            action: 'dw_calculate_ongkir',
            nonce: dw_maps.nonce,
            lat: lat,
            lng: lng,
            pedagang_id: pedagangId
        },
        success: function(response) {
            jQuery('#ongkir-loading').hide();
            if (response.success) {
                jQuery('#ongkir-result').html(
                    '<span class="success">Jarak: ' + response.data.distance + '<br>' +
                    'Biaya: ' + response.data.cost_formatted + '</span>'
                );
                // Update input hidden untuk form checkout
                jQuery('#input-ongkir-cost').val(response.data.cost);
            } else {
                jQuery('#ongkir-result').html('<span class="error">' + response.data.message + '</span>');
            }
        },
        error: function() {
            jQuery('#ongkir-loading').hide();
            jQuery('#ongkir-result').text('Gagal menghitung ongkir.');
        }
    });
}