<?php
/**
 * File Name:   page-settings.php
 * File Folder: includes/admin-pages/
 * File Path:   includes/admin-pages/page-settings.php
 *
 * --- PERUBAHAN V3.2.0 (REKOMENDASI PERBAIKAN) ---
 * - MENAMBAHKAN field `<textarea>` baru "Domain Frontend yang Diizinkan"
 * untuk pengaturan CORS (Perbaikan #2).
 * - Menambahkan sanitasi untuk field baru di `dw_settings_sanitize`.
 *
 * --- PERBAIKAN (ANALISIS API) ---
 * - Menambahkan hook `add_action('update_option_dw_settings', ...)`
 * untuk menghapus cache transient `dw_api_public_settings_cache`
 * setiap kali pengaturan disimpan.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dw_admin_settings_page_handler() {
    ?>
    <div class="wrap">
        <h1>Pengaturan Desa Wisata Core</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'dw_settings_group' );
            do_settings_sections( 'dw_settings_page' );
            submit_button( 'Simpan Pengaturan' );
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'dw_register_settings');
function dw_register_settings() {
    register_setting('dw_settings_group', 'dw_settings', 'dw_settings_sanitize');

    // Section 1: Pengaturan Umum (yang sudah ada)
    add_settings_section(
        'dw_settings_section_general',
        'Pengaturan Umum',
        'dw_settings_section_general_callback',
        'dw_settings_page'
    );

    add_settings_field(
        'dw_biaya_promosi_produk',
        'Biaya Promosi Produk per Hari (Rp)',
        'dw_settings_field_biaya_promosi_callback',
        'dw_settings_page',
        'dw_settings_section_general'
    );

    add_settings_field(
        'dw_kuota_gratis_default',
        'Default Kuota Transaksi Gratis',
        'dw_settings_field_kuota_gratis_callback',
        'dw_settings_page',
        'dw_settings_section_general'
    );
    
    add_settings_field(
        'dw_biaya_aktivasi_desa',
        'Biaya Aktivasi Fitur Desa (Rp)',
        'dw_settings_field_biaya_aktivasi_desa_callback',
        'dw_settings_page',
        'dw_settings_section_general'
    );

    // --- PERBAIKAN #2: Tambahkan field CORS di sini ---
    add_settings_field(
        'dw_allowed_cors_origins',
        'Domain Frontend (CORS)',
        'dw_settings_field_allowed_cors_origins_callback',
        'dw_settings_page',
        'dw_settings_section_general'
    );
    // --- AKHIR PERBAIKAN #2 ---
    
    // --- Section 2: Branding Frontend (BARU) ---
    add_settings_section(
        'dw_settings_section_branding',
        'Pengaturan Frontend (Branding)',
        'dw_settings_section_branding_callback',
        'dw_settings_page'
    );

    // --- PENAMBAHAN BARU (LUPA PASSWORD) ---
    add_settings_field(
        'dw_frontend_url',
        'Frontend URL',
        'dw_settings_field_frontend_url_callback',
        'dw_settings_page',
        'dw_settings_section_branding'
    );
    // --- AKHIR PENAMBAHAN ---

    add_settings_field(
        'dw_nama_website',
        'Nama Website',
        'dw_settings_field_nama_website_callback',
        'dw_settings_page',
        'dw_settings_section_branding'
    );

    add_settings_field(
        'dw_logo_frontend',
        'Logo Frontend',
        'dw_settings_field_logo_frontend_callback',
        'dw_settings_page',
        'dw_settings_section_branding'
    );

    add_settings_field(
        'dw_warna_utama',
        'Warna Utama (Primary Color)',
        'dw_settings_field_warna_utama_callback',
        'dw_settings_page',
        'dw_settings_section_branding'
    );
}

// --- PERBAIKAN PERFORMA (Sesuai Analisis Poin 3.2.2) ---
/**
 * Menghapus cache transient pengaturan publik saat opsi disimpan.
 */
function dw_clear_public_settings_cache($old_value, $value) {
    delete_transient('dw_api_public_settings_cache');
}
add_action('update_option_dw_settings', 'dw_clear_public_settings_cache', 10, 2);
// --- AKHIR PERBAIKAN ---


function dw_settings_sanitize($input) {
    $new_input = [];
    $options = get_option('dw_settings'); // Ambil data lama
    
    // Sanitasi data lama
    if ( isset( $input['biaya_promosi_produk'] ) ) {
        $new_input['biaya_promosi_produk'] = absint( $input['biaya_promosi_produk'] );
    }
    if ( isset( $input['kuota_gratis_default'] ) ) {
        $new_input['kuota_gratis_default'] = absint( $input['kuota_gratis_default'] );
    }

    if ( isset( $input['biaya_aktivasi_desa'] ) ) {
        $new_input['biaya_aktivasi_desa'] = absint( $input['biaya_aktivasi_desa'] );
    }

    // --- PERBAIKAN #2: Sanitasi field CORS ---
    if ( isset( $input['allowed_cors_origins'] ) ) {
        // Gunakan sanitize_textarea_field untuk menyimpan daftar per baris
        $new_input['allowed_cors_origins'] = sanitize_textarea_field( $input['allowed_cors_origins'] );
    } else {
        $new_input['allowed_cors_origins'] = $options['allowed_cors_origins'] ?? '';
    }
    // --- AKHIR PERBAIKAN #2 ---

    // --- PENAMBAHAN BARU (LUPA PASSWORD) ---
    if ( isset( $input['frontend_url'] ) ) {
        // Simpan URL dengan trailing slash dihapus
        $new_input['frontend_url'] = rtrim(esc_url_raw( $input['frontend_url'] ), '/');
    } else {
        $new_input['frontend_url'] = $options['frontend_url'] ?? '';
    }
    // --- AKHIR PENAMBAHAN ---

    // --- Sanitasi Data Baru ---
    if ( isset( $input['nama_website'] ) ) {
        $new_input['nama_website'] = sanitize_text_field( $input['nama_website'] );
    } else {
        // Pertahankan nilai lama jika tidak diset
        $new_input['nama_website'] = $options['nama_website'] ?? '';
    }

    if ( isset( $input['logo_frontend'] ) ) {
        $new_input['logo_frontend'] = esc_url_raw( $input['logo_frontend'] );
    } else {
        $new_input['logo_frontend'] = $options['logo_frontend'] ?? '';
    }

    if ( isset( $input['warna_utama'] ) && sanitize_hex_color( $input['warna_utama'] ) ) {
        $new_input['warna_utama'] = sanitize_hex_color( $input['warna_utama'] );
    } else {
         $new_input['warna_utama'] = $options['warna_utama'] ?? '#0073AA'; // Default
    }

    return $new_input;
}

// --- Callbacks Section 1 (Umum) ---

function dw_settings_section_general_callback() {
    echo '<p>Pengaturan dasar untuk fungsionalitas plugin Desa Wisata.</p>';
}

function dw_settings_field_biaya_promosi_callback() {
    $options = get_option('dw_settings');
    $value = isset($options['biaya_promosi_produk']) ? $options['biaya_promosi_produk'] : '10000';
    echo '<input type="number" id="dw_biaya_promosi_produk" name="dw_settings[biaya_promosi_produk]" value="' . esc_attr($value) . '" />';
    echo '<p class="description">Biaya untuk mempromosikan produk sebagai "Unggulan" per hari.</p>';
}

function dw_settings_field_kuota_gratis_callback() {
    $options = get_option('dw_settings');
    $value = isset($options['kuota_gratis_default']) ? $options['kuota_gratis_default'] : '100';
    echo '<input type="number" step="1" min="0" id="dw_kuota_gratis_default" name="dw_settings[kuota_gratis_default]" value="' . esc_attr($value) . '" />';
    echo '<p class="description">Jumlah kuota transaksi gratis yang diberikan kepada pedagang baru saat pendaftaran disetujui.</p>';
}

function dw_settings_field_biaya_aktivasi_desa_callback() {
    $options = get_option('dw_settings');
    $value = isset($options['biaya_aktivasi_desa']) ? $options['biaya_aktivasi_desa'] : '50000';
    echo '<input type="number" step="1000" min="0" id="dw_biaya_aktivasi_desa" name="dw_settings[biaya_aktivasi_desa]" value="' . esc_attr($value) . '" />';
    echo '<p class="description">Biaya sekali bayar bagi Desa untuk mengaktifkan fitur verifikasi pedagang & mendapatkan komisi 5%.</p>';
}

// --- PERBAIKAN #2: Callback untuk field CORS ---
function dw_settings_field_allowed_cors_origins_callback() {
    $options = get_option('dw_settings');
    $value = isset($options['allowed_cors_origins']) ? $options['allowed_cors_origins'] : '';
    echo '<textarea id="dw_allowed_cors_origins" name="dw_settings[allowed_cors_origins]" rows="5" class="large-text" placeholder="https://sadesa.site&#10;http://localhost:3000">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Daftar domain frontend yang diizinkan untuk mengakses REST API. <strong>Satu domain per baris.</strong> Sangat penting untuk keamanan.</p>';
}
// --- AKHIR PERBAIKAN #2 ---


// --- Callbacks Section 2 (Branding BARU) ---

function dw_settings_section_branding_callback() {
    echo '<p>Pengaturan ini akan digunakan oleh aplikasi frontend untuk menampilkan logo, nama, dan warna tema.</p>';
}

// --- PENAMBAHAN BARU (LUPA PASSWORD) ---
/**
 * Callback untuk merender field "Frontend URL".
 */
function dw_settings_field_frontend_url_callback() {
    $options = get_option('dw_settings');
    $value = isset($options['frontend_url']) ? $options['frontend_url'] : '';
    echo '<input type="url" id="dw_frontend_url" name="dw_settings[frontend_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://sadesa.site" />';
    echo '<p class="description">URL utama aplikasi frontend Anda. <strong>Penting:</strong> Ini digunakan untuk link reset password. (Contoh: <code>https://sadesa.site</code>)</p>';
}
// --- AKHIR PENAMBAHAN ---


function dw_settings_field_nama_website_callback() {
    $options = get_option('dw_settings');
    $value = isset($options['nama_website']) ? $options['nama_website'] : get_bloginfo('name'); // Ambil dari nama situs WP jika kosong
    echo '<input type="text" id="dw_nama_website" name="dw_settings[nama_website]" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">Nama yang akan ditampilkan di header frontend.</p>';
}

function dw_settings_field_logo_frontend_callback() {
    $options = get_option('dw_settings');
    $value = isset($options['logo_frontend']) ? $options['logo_frontend'] : '';
    ?>
    <div class="dw-image-uploader-wrapper">
        <img src="<?php echo esc_url($value ?: 'https://placehold.co/150x50/e2e8f0/64748b?text=Logo'); ?>" data-default-src="https://placehold.co/150x50/e2e8f0/64748b?text=Logo" class="dw-image-preview" style="width:150px; height:auto; min-height: 50px; object-fit:contain; border-radius:4px; border:1px solid #ddd; background-color: #f9f9f9;"/>
        <input name="dw_settings[logo_frontend]" type="hidden" value="<?php echo esc_attr($value); ?>" class="dw-image-url">
        <button type="button" class="button dw-upload-button">Pilih/Ubah Logo</button>
        <button type="button" class="button button-link-delete dw-remove-image-button" style="<?php echo empty($value) ? 'display:none;' : ''; ?>">Hapus Logo</button>
    </div>
    <p class="description">Unggah logo yang akan ditampilkan di frontend. (Disarankan format PNG transparan).</p>
    <?php
}

function dw_settings_field_warna_utama_callback() {
    $options = get_option('dw_settings');
    $value = isset($options['warna_utama']) ? $options['warna_utama'] : '#0073AA'; // Default biru WP
    // Tambahkan class 'dw-color-picker' agar bisa diambil oleh JS
    echo '<input type="text" id="dw_warna_utama" name="dw_settings[warna_utama]" value="' . esc_attr($value) . '" class="dw-color-picker" data-default-color="#0073AA" />';
    echo '<p class="description">Warna utama (HEX) yang akan digunakan untuk tombol, link, dan aksen di frontend.</p>';
}
?>