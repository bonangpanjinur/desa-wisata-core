<?php
/**
 * View: Form Desa
 * Path: includes/views/admin/html-desa-form.php
 * Description: Template HTML untuk form tambah/edit desa beserta script JS terkait.
 * Version: 2.5.0
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<form method="post" class="dw-form-grid">
    <?php wp_nonce_field('dw_save_desa_nonce'); ?>
    <input type="hidden" name="dw_action" value="save_desa">
    <?php if($edit_data->id > 0): ?><input type="hidden" name="desa_id" value="<?php echo esc_attr($edit_data->id); ?>"><?php endif; ?>

    <div class="dw-grid-2-col">
        
        <!-- Left Column: Main Content -->
        <div class="dw-content">
            <div class="dw-card">
                <div class="dw-card-header"><h3 class="card-heading">Informasi Utama</h3></div>
                <div class="dw-card-body">
                    <div class="dw-form-group">
                        <label class="dw-label">Admin Pengelola (User WP)</label>
                        <select name="id_user_desa" class="dw-input select2" required>
                            <option value="">-- Pilih User --</option>
                            <?php 
                            foreach($users as $u) {
                                $is_selected = ($edit_data->id_user_desa == $u->ID);
                                
                                // Cek apakah user sudah terpakai
                                $is_used = array_key_exists($u->ID, $user_village_map);
                                $village_name = $is_used ? $user_village_map[$u->ID] : '';
                                
                                // Tampilkan user yang dipilih ATAU belum terpakai
                                if ($is_selected || !$is_used) {
                                    echo '<option value="'.esc_attr($u->ID).'" '.selected($edit_data->id_user_desa, $u->ID, false).'>'.esc_html($u->display_name).' ('.esc_html($u->user_email).')</option>';
                                } else {
                                    // Opsi: Tampilkan tapi disable untuk info
                                    echo '<option value="'.esc_attr($u->ID).'" disabled style="color:#a0a0a0;">'.esc_html($u->display_name).' (Terhubung: '.esc_html($village_name).')</option>';
                                }
                            }
                            ?>
                        </select>
                        <div class="dw-help-text">Menampilkan user dengan role 'Admin Desa' atau 'Administrator'. User yang sudah terhubung dengan desa lain tidak dapat dipilih.</div>
                    </div>
                    
                    <div class="dw-form-group">
                        <label class="dw-label">Nama Desa Wisata</label>
                        <input type="text" name="nama_desa" id="inp_nama_desa" class="dw-input" value="<?php echo esc_attr($edit_data->nama_desa); ?>" required>
                    </div>

                    <div class="dw-grid-2-col">
                        <div class="dw-form-group">
                            <label class="dw-label">Nomor WhatsApp</label>
                            <input type="text" name="nomor_wa" class="dw-input" value="<?php echo esc_attr($edit_data->nomor_wa); ?>" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kode Referral</label>
                            <div style="display:flex; gap:10px;">
                                <input type="text" name="kode_referral" id="inp_kode_ref" class="dw-input" value="<?php echo esc_attr($edit_data->kode_referral); ?>" placeholder="Generate Otomatis" readonly>
                                <button type="button" id="btnGenRef" class="dw-button dw-button-secondary" title="Generate Otomatis"><span class="dashicons dashicons-randomize"></span></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dw-grid-2-col">
                        <div class="dw-form-group">
                            <label class="dw-label">Jam Buka</label>
                            <input type="time" name="jam_buka" class="dw-input" value="<?php echo esc_attr($edit_data->jam_buka); ?>">
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Jam Tutup</label>
                            <input type="time" name="jam_tutup" class="dw-input" value="<?php echo esc_attr($edit_data->jam_tutup); ?>">
                        </div>
                    </div>

                    <div class="dw-form-group">
                        <label class="dw-label">Deskripsi</label>
                        <?php wp_editor($edit_data->deskripsi, 'deskripsi', ['textarea_rows' => 5, 'media_buttons' => false, 'editor_class' => 'dw-input']); ?>
                    </div>
                </div>
            </div>

            <div class="dw-card">
                <div class="dw-card-header"><h3 class="card-heading">Lokasi & Wilayah</h3></div>
                <div class="dw-card-body">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="dw-form-group">
                            <label class="dw-label">Provinsi</label>
                            <select name="api_provinsi_id" class="dw-input dw-region-prov" data-current="<?php echo esc_attr($edit_data->api_provinsi_id); ?>"><option value="">Loading...</option></select>
                            <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi); ?>">
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kabupaten</label>
                            <select name="api_kabupaten_id" class="dw-input dw-region-kota" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id); ?>"><option value="">Pilih Provinsi Dulu</option></select>
                            <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten); ?>">
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kecamatan</label>
                            <select name="api_kecamatan_id" class="dw-input dw-region-kec" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id); ?>"><option value="">Pilih Kabupaten Dulu</option></select>
                            <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan); ?>">
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kelurahan</label>
                            <select name="api_kelurahan_id" class="dw-input dw-region-desa" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id); ?>"><option value="">Pilih Kecamatan Dulu</option></select>
                            <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan); ?>">
                        </div>
                    </div>
                    <div class="dw-grid-2-col">
                        <div class="dw-form-group">
                            <label class="dw-label">Alamat Lengkap</label>
                            <textarea name="alamat_lengkap" class="dw-input" rows="2"><?php echo esc_textarea($edit_data->alamat_lengkap); ?></textarea>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kode Pos</label>
                            <input type="text" name="kode_pos" id="inp_kode_pos" class="dw-input" value="<?php echo esc_attr($edit_data->kode_pos); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="dw-card">
                <div class="dw-card-header"><h3 class="card-heading">Rekening Pencairan Komisi</h3></div>
                <div class="dw-card-body">
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                        <div class="dw-form-group">
                            <label class="dw-label">Nama Bank</label>
                            <input type="text" name="nama_bank_desa" class="dw-input" value="<?php echo esc_attr($edit_data->nama_bank_desa); ?>">
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">No. Rekening</label>
                            <input type="text" name="no_rekening_desa" class="dw-input" value="<?php echo esc_attr($edit_data->no_rekening_desa); ?>">
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Atas Nama</label>
                            <input type="text" name="atas_nama_rekening_desa" class="dw-input" value="<?php echo esc_attr($edit_data->atas_nama_rekening_desa); ?>">
                        </div>
                    </div>
                    <div class="dw-form-group">
                        <label class="dw-label">QRIS (Opsional)</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="qris_url" id="qris_url" class="dw-input" value="<?php echo esc_attr($edit_data->qris_image_url_desa); ?>" readonly>
                            <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="#qris_url">Upload</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="dw-card">
                <div class="dw-card-header"><h3 class="card-heading">Sampul Halaman</h3></div>
                <div class="dw-card-body">
                    <div class="dw-form-group">
                        <div style="margin-bottom:10px;">
                            <img src="<?php echo !empty($edit_data->foto_sampul) ? esc_url($edit_data->foto_sampul) : 'https://via.placeholder.com/600x200'; ?>" class="img-preview" id="preview_sampul" style="max-width:100%; height:auto;">
                        </div>
                        <input type="hidden" name="foto_sampul_url" id="foto_sampul_url" value="<?php echo esc_attr($edit_data->foto_sampul); ?>">
                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="#foto_sampul_url" data-preview="#preview_sampul">Upload Foto Sampul</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Sidebar -->
        <div class="dw-sidebar">
            <div class="dw-card">
                <div class="dw-card-header"><h3 class="card-heading">Media & Status</h3></div>
                <div class="dw-card-body">
                    <div class="dw-form-group">
                        <label class="dw-label">Logo Desa</label>
                        <div style="margin-bottom:10px; background:#f0f2f5; padding:10px; border-radius:8px; text-align:center;">
                            <img src="<?php echo !empty($edit_data->foto) ? esc_url($edit_data->foto) : 'https://via.placeholder.com/150'; ?>" class="img-preview" id="preview_foto" style="max-width:100%; height:auto; border-radius:4px;">
                        </div>
                        <input type="hidden" name="foto_url" id="foto_url" value="<?php echo esc_attr($edit_data->foto); ?>">
                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="#foto_url" data-preview="#preview_foto" style="width:100%;">Upload Logo</button>
                    </div>
                    
                    <div class="dw-form-group">
                        <label class="dw-label">Status Publikasi</label>
                        <select name="status" class="dw-input">
                            <option value="aktif" <?php selected($edit_data->status, 'aktif'); ?>>Aktif (Publik)</option>
                            <option value="pending" <?php selected($edit_data->status, 'pending'); ?>>Pending (Menunggu)</option>
                        </select>
                    </div>

                    <div class="dw-form-group">
                        <label class="dw-label">Status Premium (Verifikasi)</label>
                        <select name="status_akses_verifikasi" class="dw-input">
                            <option value="locked" <?php selected($edit_data->status_akses_verifikasi, 'locked'); ?>>Locked (Free)</option>
                            <option value="pending" <?php selected($edit_data->status_akses_verifikasi, 'pending'); ?>>Pending Review</option>
                            <option value="active" <?php selected($edit_data->status_akses_verifikasi, 'active'); ?>>Active (Premium)</option>
                        </select>
                    </div>

                    <?php if($edit_data->id > 0): ?>
                    <div class="dw-form-group" style="padding:15px; background:#f0f9ff; border-radius:8px; border:1px solid #bae6fd;">
                        <label style="color:#0369a1; font-weight:600; font-size:13px; display:block; margin-bottom:8px;">Info Keuangan (Read-only)</label>
                        <div style="font-size:12px; margin-bottom:4px; display:flex; justify-content:space-between;">
                            <span>Pendapatan:</span>
                            <strong>Rp <?php echo number_format($edit_data->total_pendapatan, 0, ',', '.'); ?></strong>
                        </div>
                        <div style="font-size:12px; display:flex; justify-content:space-between;">
                            <span>Saldo:</span>
                            <strong style="color:#b45309;">Rp <?php echo number_format($edit_data->saldo_komisi, 0, ',', '.'); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <hr style="margin: 20px 0; border:0; border-top:1px solid #e2e8f0;">
                    
                    <button type="submit" class="dw-button dw-button-primary" style="width:100%; margin-bottom:10px;">Simpan Data</button>
                    <a href="?page=dw-desa" class="dw-button dw-button-secondary" style="width:100%; text-align:center;">Batal</a>
                </div>
            </div>

            <div class="dw-card">
                <div class="dw-card-header"><h3 class="card-heading">Bukti Bayar</h3></div>
                <div class="dw-card-body">
                    <div class="dw-form-group">
                        <div style="margin-bottom:10px; background:#f0f2f5; padding:10px; border-radius:8px; text-align:center;">
                            <img src="<?php echo !empty($edit_data->bukti_bayar_akses) ? esc_url($edit_data->bukti_bayar_akses) : 'https://via.placeholder.com/150?text=No+Image'; ?>" class="img-preview" id="preview_bukti" style="max-width:100%; height:auto;">
                        </div>
                        <input type="hidden" name="bukti_bayar_akses_url" id="bukti_bayar_akses_url" value="<?php echo esc_attr($edit_data->bukti_bayar_akses); ?>">
                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="#bukti_bayar_akses_url" data-preview="#preview_bukti" style="width:100%;">Upload Bukti</button>
                    </div>
                    <div class="dw-form-group">
                        <label class="dw-label">Alasan Penolakan (Jika ada)</label>
                        <textarea name="alasan_penolakan" class="dw-input" rows="2" placeholder="Tulis alasan jika menolak..."><?php echo esc_textarea($edit_data->alasan_penolakan); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
jQuery(document).ready(function($){
    // Upload Media
    $('.btn_upload').click(function(e){
        e.preventDefault(); var btn = $(this), target = btn.data('target'), preview = btn.data('preview');
        var frame = wp.media({title:'Pilih Gambar', multiple:false}).on('select', function(){
            var url = frame.state().get('selection').first().toJSON().url;
            $(target).val(url); 
            if(preview) $(preview).attr('src', url).show();
        }).open();
    });

    // GENERATOR KODE REFERRAL (JS)
    $('#btnGenRef').click(function(e){
        e.preventDefault();
        
        // Cek apakah input sudah terisi (Mode Edit) - Jika ada, minta konfirmasi
        var currentVal = $('#inp_kode_ref').val();
        if(currentVal && !confirm('Kode Referral sudah terisi: "' + currentVal + '". Apakah Anda yakin ingin membuat ulang? Kode lama akan hilang.')){
            return;
        }

        var prov = $('.dw-text-prov').val() || '';
        var kab  = $('.dw-text-kota').val() || '';
        var kel  = $('.dw-text-desa').val() || '';
        var namaDesa = $('#inp_nama_desa').val() || '';
        
        if(!namaDesa && !kel){
            alert('Gagal Generate: Harap isi Nama Desa atau pilih Wilayah (Kelurahan) terlebih dahulu.');
            return;
        }

        // Helper Logic: 3 Huruf (Smart Mapping)
        function getCode(text, type) {
            if(!text) return 'XXX';
            var clean = text.toLowerCase().trim();
            // Hapus awalan umum
            clean = clean.replace(/^(provinsi|kabupaten|kota|desa|kelurahan)\s+/g, '');
            
            // Mapping Khusus (sama dengan PHP)
            if(type === 'prov') {
                if(clean === 'jawa barat') return 'JAB';
                if(clean === 'jawa tengah') return 'JTG';
                if(clean === 'jawa timur') return 'JTM';
                if(clean.includes('jakarta')) return 'DKI';
                if(clean.includes('yogyakarta')) return 'DIY';
            }
            
            // Remove spaces and take first 3 chars
            return clean.replace(/\s/g, '').substring(0,3).toUpperCase();
        }
        
        // Prioritas: Wilayah -> Nama Desa
        var cProv = getCode(prov, 'prov');
        var cKab  = getCode(kab);
        var cDes  = getCode(kel ? kel : namaDesa); // Fallback ke nama desa jika kelurahan blm dipilih
        
        // Generate 4 digit acak
        var rand = Math.floor(1000 + Math.random() * 9000); // 1000-9999
        
        $('#inp_kode_ref').val(cProv + '-' + cKab + '-' + cDes + '-' + rand);
    });
    
    // Region API
    function loadRegion(type, pid, target, selId) {
        var act = type==='prov'?'dw_fetch_provinces':(type==='kota'?'dw_fetch_regencies':(type==='kec'?'dw_fetch_districts':'dw_fetch_villages'));
        if(type!=='prov' && !pid) return;
        $.get(ajaxurl, { action:act, province_id:pid, regency_id:pid, district_id:pid, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
            if(res.success) {
                target.empty().append('<option value="">Pilih...</option>');
                $.each(res.data, function(i,v){ 
                    // Tambahkan data-pos jika ada (untuk kelurahan)
                    let pos = v.postal_code || '';
                    target.append(`<option value="${v.id}" data-nama="${v.name}" data-pos="${pos}" ${(String(v.id)===String(selId)?'selected':'')}>${v.name}</option>`); 
                });
            }
        });
    }
    
    $('.dw-region-prov').change(function(){ $('.dw-text-prov').val($(this).find('option:selected').text()); loadRegion('kota', $(this).val(), $('.dw-region-kota'), null); });
    $('.dw-region-kota').change(function(){ $('.dw-text-kota').val($(this).find('option:selected').text()); loadRegion('kec', $(this).val(), $('.dw-region-kec'), null); });
    $('.dw-region-kec').change(function(){ $('.dw-text-kec').val($(this).find('option:selected').text()); loadRegion('desa', $(this).val(), $('.dw-region-desa'), null); });
    
    // Auto Fill Postal Code on Village Change
    $('.dw-region-desa').change(function(){ 
        $('.dw-text-desa').val($(this).find('option:selected').text()); 
        // Use attribute to get correct postal code
        let pos = $(this).find('option:selected').attr('data-pos');
        if(pos) {
            $('#inp_kode_pos').val(pos);
        }
    });

    // Init
    loadRegion('prov', null, $('.dw-region-prov'), $('.dw-region-prov').data('current'));
    // Manual trigger for edit mode waterfall (simple version)
    var p = $('.dw-region-prov').data('current'); 
    if(p) {
        loadRegion('kota', p, $('.dw-region-kota'), $('.dw-region-kota').data('current'));
        var k = $('.dw-region-kota').data('current'); 
        if(k) {
            loadRegion('kec', k, $('.dw-region-kec'), $('.dw-region-kec').data('current'));
            var c = $('.dw-region-kec').data('current'); 
            if(c) loadRegion('desa', c, $('.dw-region-desa'), $('.dw-region-desa').data('current'));
        }
    }

    if($.fn.select2) $('.select2').select2({width:'100%'});
});
</script>