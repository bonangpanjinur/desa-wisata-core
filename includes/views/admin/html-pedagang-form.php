<?php
/**
 * View: Form Pedagang
 * Path: includes/views/admin/html-pedagang-form.php
 * Description: Template HTML untuk form tambah/edit pedagang beserta script JS terkait.
 * Version: 2.5.0
 * @package DesaWisataCore
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<form method="post" class="dw-form-grid">
    <?php wp_nonce_field('dw_pedagang_action'); ?>
    <input type="hidden" name="action_pedagang" value="save">
    <input type="hidden" name="pedagang_id" value="<?php echo esc_attr($edit_data->id ?? ''); ?>">

    <div class="dw-dashboard-split">
        <!-- LEFT COLUMN: MAIN CONTENT -->
        <div class="dw-content">
            <!-- TABS NAVIGATION -->
            <div class="dw-card" style="padding:0; overflow:hidden; margin-bottom:20px;">
                <div class="dw-form-tabs" style="display:flex; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <div class="dw-form-tab active" data-target="tab-umum" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                        <span class="dashicons dashicons-admin-users"></span> Umum
                    </div>
                    <div class="dw-form-tab" data-target="tab-lokasi" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                        <span class="dashicons dashicons-location"></span> Lokasi
                    </div>
                    <div class="dw-form-tab" data-target="tab-visual" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                        <span class="dashicons dashicons-format-image"></span> Visual
                    </div>
                    <div class="dw-form-tab" data-target="tab-keuangan" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                        <span class="dashicons dashicons-money-alt"></span> Keuangan
                    </div>
                    <div class="dw-form-tab" data-target="tab-pengaturan" style="padding:15px 20px; cursor:pointer; font-weight:600; border-bottom:2px solid transparent;">
                        <span class="dashicons dashicons-admin-generic"></span> Pengaturan
                    </div>
                </div>

                <div class="dw-card-body">
                    
                    <!-- TAB: UMUM -->
                    <div id="tab-umum" class="dw-tab-pane active">
                        <div class="dw-form-group">
                            <label class="dw-label">Nama Toko <span style="color:red;">*</span></label>
                            <input name="nama_toko" type="text" value="<?php echo esc_attr($edit_data->nama_toko ?? ''); ?>" class="dw-input" style="font-size:16px; padding:10px;" required>
                        </div>
                        
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">User Pemilik (WP User) <span style="color:red;">*</span></label>
                                <select name="id_user_pedagang" class="dw-input select2">
                                    <?php foreach($users as $u): ?>
                                        <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($edit_data->id_user ?? '', $u->ID); ?>><?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_email); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">Nama Pemilik (Sesuai KTP)</label>
                                <input name="nama_pemilik" type="text" value="<?php echo esc_attr($edit_data->nama_pemilik ?? ''); ?>" class="dw-input">
                            </div>
                        </div>

                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">Nomor WhatsApp</label>
                                <input name="nomor_wa" type="text" value="<?php echo esc_attr($edit_data->nomor_wa ?? ''); ?>" class="dw-input" placeholder="62812xxx">
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">NIK Pemilik</label>
                                <input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="dw-input" placeholder="16 Digit NIK">
                            </div>
                        </div>
                        
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">Jam Buka</label>
                                <input name="jam_buka" type="time" value="<?php echo esc_attr($edit_data->jam_buka ?? ''); ?>" class="dw-input">
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">Jam Tutup</label>
                                <input name="jam_tutup" type="time" value="<?php echo esc_attr($edit_data->jam_tutup ?? ''); ?>" class="dw-input">
                            </div>
                        </div>
                    </div>

                    <!-- TAB: LOKASI -->
                    <div id="tab-lokasi" class="dw-tab-pane" style="display:none;">
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">Provinsi</label>
                                <select name="pedagang_prov" class="dw-input dw-region-prov" data-current="<?php echo esc_attr($edit_data->api_provinsi_id ?? ''); ?>">
                                    <option value="">Pilih Provinsi...</option>
                                    <?php if(class_exists('DW_Address_API')): $provs = DW_Address_API::get_provinces(); foreach($provs as $v): ?>
                                        <option value="<?php echo esc_attr($v['id']); ?>" <?php selected($edit_data->api_provinsi_id ?? '', $v['id']); ?>><?php echo esc_html($v['name']); ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                                <input type="hidden" name="provinsi_text" class="dw-text-prov" value="<?php echo esc_attr($edit_data->provinsi_nama ?? ''); ?>">
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">Kota/Kabupaten</label>
                                <select name="pedagang_kota" class="dw-input dw-region-kota" data-current="<?php echo esc_attr($edit_data->api_kabupaten_id ?? ''); ?>">
                                    <option value="">Pilih Kota...</option>
                                    <?php if($edit_data && !empty($edit_data->api_provinsi_id) && class_exists('DW_Address_API')): $cities = DW_Address_API::get_cities($edit_data->api_provinsi_id); foreach($cities as $v): ?>
                                        <option value="<?php echo esc_attr($v['id']); ?>" <?php selected($edit_data->api_kabupaten_id ?? '', $v['id']); ?>><?php echo esc_html($v['name']); ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                                <input type="hidden" name="kabupaten_text" class="dw-text-kota" value="<?php echo esc_attr($edit_data->kabupaten_nama ?? ''); ?>">
                            </div>
                        </div>
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">Kecamatan</label>
                                <select name="pedagang_kec" class="dw-input dw-region-kec" data-current="<?php echo esc_attr($edit_data->api_kecamatan_id ?? ''); ?>">
                                    <option value="">Pilih Kecamatan...</option>
                                    <?php if($edit_data && !empty($edit_data->api_kabupaten_id) && class_exists('DW_Address_API')): $districts = DW_Address_API::get_subdistricts($edit_data->api_kabupaten_id); foreach($districts as $v): ?>
                                        <option value="<?php echo esc_attr($v['id']); ?>" <?php selected($edit_data->api_kecamatan_id ?? '', $v['id']); ?>><?php echo esc_html($v['name']); ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                                <input type="hidden" name="kecamatan_text" class="dw-text-kec" value="<?php echo esc_attr($edit_data->kecamatan_nama ?? ''); ?>">
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">Kelurahan/Desa</label>
                                <select name="pedagang_nama_id" class="dw-input dw-region-desa" data-current="<?php echo esc_attr($edit_data->api_kelurahan_id ?? ''); ?>">
                                    <option value="">Pilih Kelurahan...</option>
                                    <?php if($edit_data && !empty($edit_data->api_kecamatan_id) && class_exists('DW_Address_API')): $villages = DW_Address_API::get_villages($edit_data->api_kecamatan_id); foreach($villages as $v): ?>
                                        <option value="<?php echo esc_attr($v['id']); ?>" <?php selected($edit_data->api_kelurahan_id ?? '', $v['id']); ?>><?php echo esc_html($v['name']); ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                                <input type="hidden" name="kelurahan_text" class="dw-text-desa" value="<?php echo esc_attr($edit_data->kelurahan_nama ?? ''); ?>">
                            </div>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Alamat Lengkap</label>
                            <textarea name="pedagang_detail" class="dw-input" rows="3" placeholder="Nama jalan, nomor rumah, RT/RW..."><?php echo esc_textarea($edit_data->alamat_lengkap ?? ''); ?></textarea>
                        </div>
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">Kode Pos</label>
                                <input name="kode_pos" id="inp_kode_pos" type="text" value="<?php echo esc_attr($edit_data->kode_pos ?? ''); ?>" class="dw-input" placeholder="Contoh: 12345">
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">URL Google Maps</label>
                                <input name="url_gmaps" type="text" value="<?php echo esc_url($edit_data->url_gmaps ?? ''); ?>" class="dw-input" placeholder="https://goo.gl/maps/...">
                            </div>
                        </div>
                    </div>

                    <!-- TAB: VISUAL -->
                    <div id="tab-visual" class="dw-tab-pane" style="display:none;">
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">Foto Profil Toko</label>
                                <div style="margin-bottom:12px; text-align:center; background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
                                    <img id="prev_foto_profil" src="<?php echo esc_url($edit_data->foto_profil ?? 'https://placehold.co/150x150?text=Profil'); ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:4px solid #fff; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" name="foto_profil" id="foto_profil" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>" class="dw-input" readonly>
                                    <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_profil" data-preview="#prev_foto_profil">Upload</button>
                                </div>
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">Foto Sampul Toko</label>
                                <div style="margin-bottom:12px; background:#f8fafc; padding:10px; border-radius:12px; border:1px solid #e2e8f0;">
                                    <img id="prev_foto_sampul" src="<?php echo esc_url($edit_data->foto_sampul ?? 'https://placehold.co/600x200?text=Sampul+Toko'); ?>" style="width:100%; height:100px; object-fit:cover; border-radius:8px;">
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" name="foto_sampul" id="foto_sampul" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>" class="dw-input" readonly>
                                    <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_sampul" data-preview="#prev_foto_sampul">Upload</button>
                                </div>
                            </div>
                        </div>
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">NIK Pemilik</label>
                                <input name="nik" type="text" value="<?php echo esc_attr($edit_data->nik ?? ''); ?>" class="dw-input" placeholder="Masukkan 16 digit NIK">
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">Foto KTP</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <div style="width:60px; height:40px; border-radius:4px; overflow:hidden; border:1px solid #e2e8f0;">
                                        <img id="prev_url_ktp" src="<?php echo esc_url($edit_data->url_ktp ?? 'https://placehold.co/60x40?text=KTP'); ?>" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                    <input type="text" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>" class="dw-input" readonly>
                                    <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="url_ktp" data-preview="#prev_url_ktp">Upload</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dw-form-group">
                            <label class="dw-label">Galeri Toko (Foto Tambahan)</label>
                            <p class="dw-help-text">Gunakan fitur galeri produk untuk detail lebih lanjut.</p>
                            <input type="hidden" name="galeri_urls" id="galeri_urls" value="<?php echo esc_attr($edit_data->galeri ?? ''); ?>">
                        </div>
                    </div>

                    <!-- TAB: KEUANGAN -->
                    <div id="tab-keuangan" class="dw-tab-pane" style="display:none;">
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">Nama Bank</label>
                                <input name="nama_bank" type="text" value="<?php echo esc_attr($edit_data->nama_bank ?? ''); ?>" class="dw-input" placeholder="Contoh: BCA, Mandiri">
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">Nomor Rekening</label>
                                <input name="no_rekening" type="text" value="<?php echo esc_attr($edit_data->no_rekening ?? ''); ?>" class="dw-input">
                            </div>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Atas Nama Rekening</label>
                            <input name="atas_nama_rekening" type="text" value="<?php echo esc_attr($edit_data->atas_nama_rekening ?? ''); ?>" class="dw-input">
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">QRIS Pembayaran</label>
                            <div style="display:flex; gap:10px;">
                                <div style="width:80px; height:80px; border:1px solid #ddd; padding:5px;">
                                    <img id="prev_qris_image_url" src="<?php echo esc_url($edit_data->qris_image_url ?? 'https://placehold.co/150x150?text=QRIS'); ?>" style="width:100%; height:100%; object-fit:contain;">
                                </div>
                                <div style="flex:1;">
                                    <input type="text" name="qris_image_url" id="qris_image_url" value="<?php echo esc_attr($edit_data->qris_image_url ?? ''); ?>" class="dw-input" readonly>
                                    <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="qris_image_url" data-preview="#prev_qris_image_url" style="margin-top:5px;">Upload QRIS</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: PENGATURAN -->
                    <div id="tab-pengaturan" class="dw-tab-pane" style="display:none;">
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label class="dw-label">Sisa Kuota Transaksi</label>
                                <input name="sisa_transaksi" type="number" value="<?php echo esc_attr($edit_data->sisa_transaksi ?? '0'); ?>" class="dw-input">
                            </div>
                            <div class="dw-form-group">
                                <label class="dw-label">Terdaftar Melalui Kode Referral</label>
                                <input name="terdaftar_melalui_kode" type="text" value="<?php echo esc_attr($edit_data->terdaftar_melalui_kode ?? ''); ?>" class="dw-input">
                            </div>
                        </div>
                        <div class="dw-form-group">
                            <label class="dw-label">Kode Referral Toko Saya</label>
                            <input type="text" value="<?php echo esc_attr($edit_data->kode_referral_saya ?? '-'); ?>" class="dw-input" readonly>
                        </div>
                        
                        <hr style="margin:20px 0; border:0; border-top:1px solid #e2e8f0;">
                        <h4 class="dw-label" style="font-size:14px; margin-bottom:15px;">Pengaturan Ongkir</h4>
                        
                        <div class="dw-grid-2-col">
                            <div class="dw-form-group">
                                <label style="display:flex; align-items:center; gap:10px;">
                                    <input type="checkbox" name="shipping_nasional_aktif" value="1" <?php checked($edit_data->shipping_nasional_aktif ?? 0, 1); ?>>
                                    Aktifkan Pengiriman Nasional (Flat)
                                </label>
                                <input name="shipping_nasional_harga" type="number" value="<?php echo esc_attr($edit_data->shipping_nasional_harga ?? '0'); ?>" class="dw-input" placeholder="Biaya Flat (Rp)" style="margin-top:5px;">
                            </div>
                            <div class="dw-form-group">
                                <label style="display:flex; align-items:center; gap:10px;">
                                    <input type="checkbox" name="shipping_ojek_lokal_aktif" value="1" <?php checked($edit_data->shipping_ojek_lokal_aktif ?? 0, 1); ?>>
                                    Aktifkan Ojek Lokal
                                </label>
                                
                                <!-- OJEK LOKAL INPUTS -->
                                <div style="margin-top:10px; background:#f9f9f9; padding:15px; border-radius:4px; border:1px solid #e2e8f0;">
                                    
                                    <!-- Zona 1: Satu Kecamatan -->
                                    <strong style="display:block; margin-bottom:10px; color:var(--dw-primary);">Zona 1: Satu Kecamatan</strong>
                                    <div class="dw-grid-2-col">
                                        <div>
                                            <label style="font-size:12px;">Tarif Dekat (Rp)</label>
                                            <input type="number" name="ojek_dekat_harga" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['dekat']['harga'] ?? 0); ?>" class="dw-input">
                                            <label style="font-size:12px; margin-top:5px; display:block;">Pilih Desa (Dekat)</label>
                                            <select name="ojek_dekat_desa_ids[]" class="dw-input select2-villages" multiple="multiple">
                                                    <?php if(!empty($ojek_zona['satu_kecamatan']['dekat']['desa_ids'])): 
                                                        foreach($ojek_zona['satu_kecamatan']['dekat']['desa_ids'] as $vid) echo "<option value='" . esc_attr($vid) . "' selected>" . esc_html($vid) . "</option>"; 
                                                    endif; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label style="font-size:12px;">Tarif Jauh (Rp)</label>
                                            <input type="number" name="ojek_jauh_harga" value="<?php echo esc_attr($ojek_zona['satu_kecamatan']['jauh']['harga'] ?? 0); ?>" class="dw-input">
                                            <label style="font-size:12px; margin-top:5px; display:block;">Pilih Desa (Jauh)</label>
                                            <select name="ojek_jauh_desa_ids[]" class="dw-input select2-villages" multiple="multiple">
                                                    <?php if(!empty($ojek_zona['satu_kecamatan']['jauh']['desa_ids'])): 
                                                        foreach($ojek_zona['satu_kecamatan']['jauh']['desa_ids'] as $vid) echo "<option value='" . esc_attr($vid) . "' selected>" . esc_html($vid) . "</option>"; 
                                                    endif; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div style="border-top:1px dashed #ddd; margin:15px 0;"></div>

                                    <!-- Zona 2: Beda Kecamatan -->
                                    <strong style="display:block; margin-bottom:10px; color:var(--dw-primary);">Zona 2: Beda Kecamatan</strong>
                                    <div class="dw-grid-2-col">
                                        <div>
                                            <label style="font-size:12px;">Tarif Dekat (Rp)</label>
                                            <input type="number" name="ojek_beda_kec_dekat_harga" value="<?php echo esc_attr($ojek_zona['beda_kecamatan']['dekat']['harga'] ?? 0); ?>" class="dw-input">
                                            <label style="font-size:12px; margin-top:5px; display:block;">Pilih Kecamatan (Dekat)</label>
                                            <select name="ojek_beda_kec_dekat_ids[]" class="dw-input select2-districts" multiple="multiple">
                                                    <?php if(!empty($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'])): 
                                                        foreach($ojek_zona['beda_kecamatan']['dekat']['kecamatan_ids'] as $kid) echo "<option value='" . esc_attr($kid) . "' selected>" . esc_html($kid) . "</option>"; 
                                                    endif; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label style="font-size:12px;">Tarif Jauh (Rp)</label>
                                            <input type="number" name="ojek_beda_kec_jauh_harga" value="<?php echo esc_attr($ojek_zona['beda_kecamatan']['jauh']['harga'] ?? 0); ?>" class="dw-input">
                                            <label style="font-size:12px; margin-top:5px; display:block;">Pilih Kecamatan (Jauh)</label>
                                            <select name="ojek_beda_kec_jauh_ids[]" class="dw-input select2-districts" multiple="multiple">
                                                    <?php if(!empty($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'])): 
                                                        foreach($ojek_zona['beda_kecamatan']['jauh']['kecamatan_ids'] as $kid) echo "<option value='" . esc_attr($kid) . "' selected>" . esc_html($kid) . "</option>"; 
                                                    endif; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div style="border-top:1px dashed #ddd; margin:15px 0;"></div>
                                    
                                    <!-- Blacklist Zone -->
                                    <strong style="display:block; margin-bottom:10px; color:#d63638;">Zona Blacklist (Tidak Tercover)</strong>
                                    <p class="dw-help-text" style="color: #646970; font-size:12px; margin-bottom:10px;">Area ini akan dialihkan ke kurir ekspedisi di frontend.</p>
                                    <div class="dw-grid-2-col">
                                        <div>
                                            <label style="font-size:12px;">Blacklist Desa (Satu Kecamatan)</label>
                                            <select name="ojek_blacklist_desa_ids[]" class="dw-input select2-villages" multiple="multiple">
                                                    <?php if(!empty($ojek_zona['blacklist']['desa_ids'])): 
                                                    foreach($ojek_zona['blacklist']['desa_ids'] as $vid) echo "<option value='" . esc_attr($vid) . "' selected>" . esc_html($vid) . "</option>"; 
                                                endif; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label style="font-size:12px;">Blacklist Kecamatan (Luar Kecamatan)</label>
                                            <select name="ojek_blacklist_kec_ids[]" class="dw-input select2-districts" multiple="multiple">
                                                    <?php if(!empty($ojek_zona['blacklist']['kecamatan_ids'])): 
                                                    foreach($ojek_zona['blacklist']['kecamatan_ids'] as $kid) echo "<option value='" . esc_attr($kid) . "' selected>" . esc_html($kid) . "</option>"; 
                                                endif; ?>
                                            </select>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="dw-form-group">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="allow_pesan_di_tempat" value="1" <?php checked($edit_data->allow_pesan_di_tempat ?? 0, 1); ?>>
                                Izinkan Pesan di Tempat (Ambil Sendiri)
                            </label>
                        </div>
                    </div>

                    <div style="margin-top:20px; text-align:right;">
                        <a href="?page=dw-pedagang" class="dw-button dw-button-secondary">Batal</a>
                        <button type="submit" class="dw-button dw-button-primary">Simpan Data</button>
                    </div>

                </div>
            </div>
        </div>

        <!-- Right Column: Sidebar (Status & Foto Utama) -->
        <div class="dw-sidebar">
            
            <div class="dw-card">
                <div class="dw-card-header"><h3 class="card-heading">Status Akun</h3></div>
                <div class="dw-card-body">
                    <div class="dw-form-group">
                        <label class="dw-label">Status Keaktifan</label>
                        <select name="status_akun" class="dw-input">
                            <option value="aktif" <?php selected($edit_data->status_akun ?? '', 'aktif'); ?>>Aktif</option>
                            <option value="nonaktif" <?php selected($edit_data->status_akun ?? '', 'nonaktif'); ?>>Non-Aktif</option>
                            <option value="suspend" <?php selected($edit_data->status_akun ?? '', 'suspend'); ?>>Suspend</option>
                        </select>
                    </div>
                    <div class="dw-form-group">
                        <label class="dw-label">Status Pendaftaran</label>
                        <select name="status_pendaftaran" class="dw-input">
                            <option value="disetujui" <?php selected($edit_data->status_pendaftaran ?? '', 'disetujui'); ?>>Disetujui</option>
                            <option value="menunggu" <?php selected($edit_data->status_pendaftaran ?? '', 'menunggu'); ?>>Menunggu</option>
                            <option value="ditolak" <?php selected($edit_data->status_pendaftaran ?? '', 'ditolak'); ?>>Ditolak</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="dw-card">
                <div class="dw-card-header"><h3 class="card-heading">Foto Identitas</h3></div>
                <div class="dw-card-body">
                    <div class="dw-form-group">
                        <label class="dw-label">Foto Profil Toko</label>
                        <div style="text-align:center; margin-bottom:10px; background:#f8fafc; padding:15px; border-radius:8px;">
                            <img id="prev_foto_profil" src="<?php echo esc_url($edit_data->foto_profil ?? 'https://placehold.co/100x100?text=Profil'); ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                        </div>
                        <input type="hidden" name="foto_profil" id="foto_profil" value="<?php echo esc_attr($edit_data->foto_profil ?? ''); ?>">
                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_profil" data-preview="#prev_foto_profil" style="width:100%;">Upload Profil</button>
                    </div>

                    <div class="dw-form-group">
                        <label class="dw-label">Foto Sampul Toko</label>
                        <div style="text-align:center; margin-bottom:10px; background:#f8fafc; padding:10px; border-radius:8px;">
                            <img id="prev_foto_sampul" src="<?php echo esc_url($edit_data->foto_sampul ?? 'https://placehold.co/300x150?text=Sampul'); ?>" style="width:100%; height:100px; object-fit:cover; border-radius:6px;">
                        </div>
                        <input type="hidden" name="foto_sampul" id="foto_sampul" value="<?php echo esc_attr($edit_data->foto_sampul ?? ''); ?>">
                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="foto_sampul" data-preview="#prev_foto_sampul" style="width:100%;">Upload Sampul</button>
                    </div>

                    <div class="dw-form-group">
                        <label class="dw-label">Foto KTP</label>
                        <div style="text-align:center; margin-bottom:10px; background:#f8fafc; padding:10px; border-radius:8px;">
                            <img id="prev_url_ktp" src="<?php echo esc_url($edit_data->url_ktp ?? 'https://placehold.co/300x150?text=KTP'); ?>" style="width:100%; height:80px; object-fit:cover; border-radius:6px;">
                        </div>
                        <input type="hidden" name="url_ktp" id="url_ktp" value="<?php echo esc_attr($edit_data->url_ktp ?? ''); ?>">
                        <button type="button" class="dw-button dw-button-secondary btn_upload" data-target="url_ktp" data-preview="#prev_url_ktp" style="width:100%;">Upload KTP</button>
                    </div>
                </div>
            </div>

        </div> <!-- End Right Column -->

    </div>
</form>

<script>
jQuery(document).ready(function($){
    // Tab Logic
    $('.dw-form-tab').click(function(){
        $('.dw-form-tab').removeClass('active').css('border-bottom', 'none');
        $(this).addClass('active').css('border-bottom', '3px solid var(--dw-primary)');
        $('.dw-tab-pane').hide();
        $('#'+$(this).data('target')).show();
    });
    
    // Set default active tab style
    $('.dw-form-tab.active').css('border-bottom', '3px solid var(--dw-primary)');

    // Media Upload
    $('.btn_upload').click(function(e){
        e.preventDefault(); 
        var btn = $(this), target = btn.data('target'), preview = btn.data('preview');
        var frame = wp.media({title:'Pilih Gambar', multiple:false}).on('select', function(){
            var url = frame.state().get('selection').first().toJSON().url;
            $('#'+target).val(url); 
            if(preview) $(preview).attr('src', url);
        }).open();
    });

    // Region API Logic
    function loadRegion(type, pid, target, selId) {
        var act = type==='prov'?'dw_fetch_provinces':(type==='kota'?'dw_fetch_regencies':(type==='kec'?'dw_fetch_districts':'dw_fetch_villages'));
        if(type!=='prov' && !pid) return;
        $.get(ajaxurl, { action:act, province_id:pid, regency_id:pid, district_id:pid, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
            if(res.success) {
                target.empty().append('<option value="">Pilih...</option>');
                $.each(res.data, function(i,v){ 
                    let pos = v.postal_code || '';
                    target.append(`<option value="${v.id}" data-nama="${v.name}" data-pos="${pos}" ${(String(v.id)===String(selId)?'selected':'')}>${v.name}</option>`); 
                });
            }
        });
    }
    
    $('.dw-region-prov').change(function(){ $('.dw-text-prov').val($(this).find('option:selected').text()); loadRegion('kota', $(this).val(), $('.dw-region-kota'), null); });
    $('.dw-region-kota').change(function(){ $('.dw-text-kota').val($(this).find('option:selected').text()); loadRegion('kec', $(this).val(), $('.dw-region-kec'), null); });
    $('.dw-region-kec').change(function(){ $('.dw-text-kec').val($(this).find('option:selected').text()); loadRegion('desa', $(this).val(), $('.dw-region-desa'), null); });
    $('.dw-region-desa').change(function(){ 
        $('.dw-text-desa').val($(this).find('option:selected').text()); 
        let pos = $(this).find('option:selected').attr('data-pos');
        if(pos) $('#inp_kode_pos').val(pos);
    });

    // Init Region if Edit
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

    // Init Select2 for Ongkir
    if($.fn.select2) { 
        $('.select2').select2({width:'100%'}); 
        $('.select2-districts').select2({ width: '100%', placeholder: 'Pilih Kecamatan' });
        $('.select2-villages').select2({ width: '100%', placeholder: 'Pilih Desa' });
    }
    
    // ONGKIR: Load Dynamic Options based on selected Kabupaten/Kecamatan
        function loadOngkirOptions() {
        var kecId = $('select[name="pedagang_kec"]').val();
        var kabId = $('select[name="pedagang_kota"]').val();
        
        // Load Desa for Zona 1 (Satu Kecamatan)
        if(kecId) {
            $.get(ajaxurl, { action:'dw_fetch_villages', district_id:kecId, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
                if(res.success) {
                    var $sel = $('.select2-villages');
                    
                    // Store selected values before empty
                    $sel.each(function(){
                        var $this = $(this);
                        var savedVals = $this.val() || [];
                        var currentSelections = [];
                        $this.find('option:selected').each(function(){
                            currentSelections.push($(this).val());
                        });

                        $this.empty();
                        $.each(res.data, function(i,v){
                            var isSelected = currentSelections.includes(v.id) ? 'selected' : '';
                            $this.append(`<option value="${v.id}" ${isSelected}>${v.name}</option>`);
                        });
                        $this.trigger('change.select2'); // Notify select2 of updates
                    });
                }
            });
        }

        // Load Kecamatan for Zona 2 (Satu Kabupaten)
        if(kabId) {
            $.get(ajaxurl, { action:'dw_fetch_districts', regency_id:kabId, nonce:'<?php echo wp_create_nonce("dw_region_nonce"); ?>' }, function(res){
                if(res.success) {
                    var $sel = $('.select2-districts');
                        $sel.each(function(){
                        var $this = $(this);
                        var currentSelections = [];
                        $this.find('option:selected').each(function(){
                            currentSelections.push($(this).val());
                        });

                        $this.empty();
                        $.each(res.data, function(i,v){
                            var isSelected = currentSelections.includes(v.id) ? 'selected' : '';
                            $this.append(`<option value="${v.id}" ${isSelected}>${v.name}</option>`);
                        });
                        $this.trigger('change.select2');
                    });
                }
            });
        }
    }

    // Trigger load ongkir options when region changes
    $('select[name="pedagang_kec"], select[name="pedagang_kota"]').on('change', function(){
            setTimeout(loadOngkirOptions, 800);
    });
    
    // Trigger on init if edit mode
    if($('select[name="pedagang_kec"]').data('current')) {
            setTimeout(loadOngkirOptions, 1000);
    }

    // --- LOGIKA EKSKLUSI PILIHAN (Mutual Exclusion) ---
    
    // 1. Desa (Zona 1 & Blacklist)
    var desaGroups = ['select[name="ojek_dekat_desa_ids[]"]', 'select[name="ojek_jauh_desa_ids[]"]', 'select[name="ojek_blacklist_desa_ids[]"]'];
    
    $(desaGroups.join(',')).on('change', function (e) {
        var allSelected = [];
        desaGroups.forEach(function(selector) {
            var vals = $(selector).val() || [];
            allSelected = allSelected.concat(vals);
        });

        desaGroups.forEach(function(selector) {
            var $sel = $(selector);
            var myVal = $sel.val() || [];
            
            $sel.find('option').each(function(){
                var optVal = $(this).val();
                if(allSelected.includes(optVal) && !myVal.includes(optVal)) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
        });
    });

    // 2. Kecamatan (Zona 2 & Blacklist)
        var kecGroups = ['select[name="ojek_beda_kec_dekat_ids[]"]', 'select[name="ojek_beda_kec_jauh_ids[]"]', 'select[name="ojek_blacklist_kec_ids[]"]'];
        $(kecGroups.join(',')).on('change', function (e) {
            var allSelected = [];
            kecGroups.forEach(function(selector) {
                allSelected = allSelected.concat($(selector).val() || []);
            });

            kecGroups.forEach(function(selector) {
                var $sel = $(selector);
                var myVal = $sel.val() || [];
                $sel.find('option').each(function(){
                    var optVal = $(this).val();
                    if(allSelected.includes(optVal) && !myVal.includes(optVal)) {
                        $(this).prop('disabled', true);
                    } else {
                        $(this).prop('disabled', false);
                    }
                });
            });
        });

});
</script>