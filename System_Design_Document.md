# Desain Sistem: Refaktorisasi dan Standardisasi Halaman Admin Desa Wisata Core

## 1. Pendahuluan

Dokumen ini menguraikan rencana desain sistem untuk refaktorisasi dan standardisasi halaman admin pada proyek Desa Wisata Core. Tujuannya adalah untuk meningkatkan konsistensi desain, modularitas kode, dan kemudahan pemeliharaan, sesuai dengan panduan teknis yang telah disediakan. Implementasi akan berfokus pada pemisahan tanggung jawab (separation of concerns), penggunaan kembali komponen (reusability), dan peningkatan keterbacaan kode.

## 2. Prinsip Umum Refaktorisasi

Refaktorisasi akan berpegang pada prinsip-prinsip berikut untuk memastikan keberhasilan dan kualitas hasil:

*   **Atomic Changes**: Perubahan akan dilakukan dalam unit terkecil yang memungkinkan dan diuji secara berkala untuk meminimalkan risiko dan memudahkan pelacakan.
*   **Single Source of Truth (SSOT)**: Setiap gaya visual atau logika bisnis akan didefinisikan di satu lokasi sentral untuk menghindari duplikasi dan memastikan konsistensi.
*   **Separation of Concerns**: Logika bisnis (PHP) akan dipisahkan secara tegas dari presentasi (HTML/CSS). PHP akan bertanggung jawab atas data dan logika, sementara HTML/CSS akan menangani tampilan.
*   **Reusability**: Komponen antarmuka pengguna (UI) dan fungsi PHP akan dirancang agar dapat digunakan kembali di berbagai bagian halaman admin, mengurangi duplikasi kode dan mempercepat pengembangan di masa mendatang.
*   **Non-Regression**: Setiap perubahan akan diuji secara menyeluruh untuk memastikan tidak ada fungsionalitas atau tampilan yang sudah ada menjadi rusak.
*   **Readability**: Kode akan ditulis dengan bersih, terstruktur, dan didokumentasikan dengan baik agar mudah dibaca dan dipahami oleh pengembang lain.

## 3. Alur Kerja Detil Refaktorisasi

Proses refaktorisasi akan dibagi menjadi tiga fase utama, masing-masing dengan langkah-langkah spesifik:

### Fase 1: Sentralisasi Gaya CSS

**Tujuan**: Memindahkan semua gaya CSS inline dari file PHP ke stylesheet eksternal `assets/css/admin-style.css` dan membuat kelas CSS baru yang diperlukan untuk mencapai desain yang seragam.

**Langkah-langkah Implementasi**:

1.  **Identifikasi File dengan Inline Styles**: Menggunakan perintah `grep -l "<style>" includes/admin-pages/*.php` untuk mendapatkan daftar semua file PHP yang mengandung blok `<style>` inline. (Daftar file telah diidentifikasi: `page-banner.php`, `page-dashboard.php`, `page-desa.php`, `page-komisi.php`, `page-logs.php`, `page-manajemen-pesanan-pusat.php`, `page-ojek-management.php`, `page-pedagang.php`, `page-pembeli.php`, `page-produk.php`, `page-promosi.php`, `page-referral-rewards.php`, `page-reviews.php`, `page-settings.php`, `page-verifikasi-paket.php`, `page-verifikator-list.php`, `page-verifikator-umkm.php`, `page-wisata.php`).
2.  **Proses Setiap File PHP**: Untuk setiap file dalam daftar:
    *   **Ekstraksi Blok `<style>`**: Temukan dan ekstrak semua blok `<style>...</style>`.
    *   **Analisis dan Pemindahan Gaya**: Untuk setiap aturan CSS yang diekstrak, periksa duplikasi dengan `assets/css/admin-style.css`. Jika gaya unik dan berulang, buat kelas CSS baru yang deskriptif (misalnya, `.dw-form-input`, `.dw-button-primary`) dan tambahkan ke `admin-style.css`. Pindahkan semua aturan CSS yang diekstrak ke `admin-style.css`.
    *   **Ganti Inline Styles di HTML**: Ganti atribut `style="..."` di markup HTML dengan atribut `class="..."` yang merujuk pada kelas CSS yang baru atau yang sudah ada.
    *   **Hapus Blok `<style>`**: Setelah semua gaya dipindahkan atau diganti, hapus seluruh blok `<style>...</style>` dari file PHP.
    *   **Simpan Perubahan**: Simpan file PHP yang telah dimodifikasi.
3.  **Tambahkan Kelas CSS Baru untuk Elemen Form**: Tambahkan definisi kelas CSS berikut ke `assets/css/admin-style.css` (jika belum ada, berdasarkan pemeriksaan `admin-style.css` yang sudah dilakukan, beberapa sudah ada):
    *   `.dw-form-group`
    *   `.dw-label`
    *   `.dw-input`
    *   `.dw-select`
    *   `.dw-textarea`
    *   `.dw-button`, `.dw-button-primary`, `.dw-button-secondary`
    *   Kelas untuk alert/notifikasi (misalnya, `.dw-alert`, `.dw-alert-success`, `.dw-alert-error`, `.dw-alert-warning`).
4.  **Verifikasi Visual**: Lakukan verifikasi visual pada halaman admin yang relevan di browser setelah setiap set perubahan untuk memastikan tidak ada regresi visual dan desain terlihat konsisten.

### Fase 2: Komponentisasi UI

**Tujuan**: Mengidentifikasi blok UI yang berulang dan mengubahnya menjadi fungsi PHP pembantu yang dapat digunakan kembali di `includes/admin-ui-components.php`.

**Langkah-langkah Implementasi**:

1.  **Buat File `includes/admin-ui-components.php`**: File ini sudah ada dan berisi beberapa fungsi komponen UI dasar (`dw_admin_render_card`, `dw_admin_render_stat_card`, `dw_admin_render_table`, `dw_admin_render_form_group`, `dw_admin_render_alert`).
2.  **Definisikan Fungsi Komponen UI Tambahan**: Jika ada blok UI berulang lainnya yang belum dikomponentisasi, buat fungsi PHP baru di `includes/admin-ui-components.php`. Contoh fungsi yang sudah ada dan akan digunakan:
    *   `dw_admin_render_card($title, $body_content, $link_text = '', $link_url = '')`
    *   `dw_admin_render_stat_card($icon_class, $bg_class, $value, $label)`
    *   `dw_admin_render_table($headers, $rows, $classes = [])`
    *   `dw_admin_render_form_group($label, $input_html, $help_text = '', $label_for = '')`
    *   `dw_admin_render_alert($message, $type = 'info')`
3.  **Ganti Markup HTML di File PHP**: Ganti markup HTML yang berulang di semua file PHP di `includes/admin-pages/` dengan panggilan ke fungsi komponen yang sesuai. Prioritaskan penggantian elemen yang paling sering muncul (misalnya, card, form group).
4.  **Verifikasi Fungsionalitas dan Tampilan**: Uji halaman admin yang relevan untuk memastikan fungsionalitas tetap utuh dan tampilan sesuai dengan desain yang diharapkan setelah setiap set penggantian.

### Fase 3: Refaktorisasi Logika PHP

**Tujuan**: Memisahkan logika bisnis dari tampilan, mengurangi duplikasi kode PHP, dan meningkatkan struktur kode.

**Langkah-langkah Implementasi**:

1.  **Sentralisasi Pengambilan Data Pengguna dan Konteks Role**:
    *   Buat fungsi pembantu baru `dw_get_admin_context()` di `includes/helpers.php` (atau `includes/admin-utils.php` jika dibuat). Fungsi ini akan mengembalikan objek berisi `$current_user`, `$user_id`, `$role_context`, dan `$context_id`. Logika penentuan role yang saat ini ada di `page-dashboard.php` akan dipindahkan ke fungsi ini.
    *   Ganti semua instansi `wp_get_current_user()` dan logika penentuan role yang berulang di halaman admin dengan panggilan ke `dw_get_admin_context()`.
2.  **Abstraksi Query Database Umum**:
    *   Identifikasi query database yang sering diulang di berbagai halaman admin (misalnya, mengambil daftar desa, pedagang, produk). Contoh query telah ditemukan di `page-dashboard.php`.
    *   Pindahkan query-query ini ke fungsi pembantu di `includes/helpers.php` atau file model baru. Ganti query langsung dengan panggilan fungsi ini.
3.  **Sanitasi dan Escape**: Pastikan semua output data ke HTML di-escape dengan benar (`esc_html()`, `esc_attr()`, `wp_kses_post()`) untuk mencegah kerentanan keamanan seperti XSS.

## 4. Verifikasi dan Pengujian

Setelah setiap fase atau set tugas selesai, verifikasi menyeluruh akan dilakukan:

*   **Verifikasi Visual**: Periksa tata letak, warna, font, dan responsivitas di browser. Bandingkan dengan tampilan sebelum perubahan untuk memastikan tidak ada regresi.
*   **Verifikasi Fungsional**: Uji semua interaksi pengguna (pengisian form, klik tombol, navigasi) untuk memastikan semua fitur bekerja dengan benar.
*   **Pemeriksaan Konsol Browser**: Periksa konsol JavaScript untuk error dan tab Network untuk memastikan semua aset (CSS, JS) dimuat dengan benar.
*   **Pemeriksaan Kode**: Gunakan alat linting atau inspeksi kode untuk memastikan kepatuhan terhadap standar pengkodean PHP dan WordPress.

## 5. Catatan Penting

*   **Backup**: Selalu buat backup proyek sebelum memulai refaktorisasi besar.
*   **Version Control**: Gunakan Git secara aktif. Lakukan commit kecil dan deskriptif untuk setiap perubahan logis.
*   **Prioritas**: Mulai dengan halaman yang paling sederhana atau yang memiliki paling banyak gaya inline untuk membangun momentum dan memahami pola.
*   **Iterasi**: Proses ini bersifat iteratif. Jangan ragu untuk kembali dan menyempurnakan fungsi atau kelas jika ditemukan cara yang lebih baik.

Dengan mengikuti panduan ini, proses refaktorisasi akan menjadi lebih terstruktur dan hasilnya akan lebih konsisten dan mudah dikelola.
