Dokumentasi API Desa Wisata Core

Dokumentasi ini menjelaskan endpoint REST API yang tersedia di plugin Desa Wisata Core.

Base URL: https://your-domain.com/wp-json/dw/v1

Daftar Isi

Otentikasi & Akun

Data Publik (Produk, Wisata, Desa)

Wilayah (Alamat)

Keranjang & Checkout

Pesanan Pembeli

Manajemen Pedagang (Toko)

1. Otentikasi & Akun

Semua endpoint yang memerlukan login harus menyertakan header:
Authorization: Bearer <token_jwt>

Login

Mendapatkan token akses JWT.

URL: /auth/login

Method: POST

Body:

{
  "username": "email@example.com", // atau username
  "password": "password_user"
}


Response:

{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user_email": "email@example.com",
  "user_nicename": "User Name",
  "user_display_name": "User Name",
  "roles": ["subscriber", "pedagang"] // Role user
}


Register (Pendaftaran)

Mendaftar sebagai pengguna baru.

URL: /auth/register

Method: POST

Body:

{
  "username": "username_baru",
  "email": "email@example.com",
  "password": "password_kuat",
  "nama_lengkap": "Nama Lengkap",
  "no_hp": "081234567890"
}


Refresh Token

Mendapatkan token baru jika token lama kadaluwarsa (opsional, jika diimplementasikan di client).

URL: /auth/token/refresh

Method: POST

Header: Authorization: Bearer <token_lama>

2. Data Publik

Endpoint ini dapat diakses tanpa login.

Daftar Banner

URL: /banner

Method: GET

Daftar Kategori Produk

URL: /kategori/produk

Method: GET

Daftar Desa Wisata

URL: /desa

Method: GET

Params:

page: (int) Halaman ke berapa.

search: (string) Cari nama desa.

provinsi_id: (string) Filter ID Provinsi.

Detail Desa

Mendapatkan info desa beserta daftar produk dan wisatanya.

URL: /desa/{id}

Method: GET

Daftar Produk

URL: /produk

Method: GET

Params:

page: (int) Halaman (default: 1).

per_page: (int) Item per halaman (default: 10).

search: (string) Kata kunci pencarian.

kategori: (string) Slug kategori.

desa: (int) ID Desa.

toko: (int) ID User Pedagang.

Detail Produk

URL: /produk/{id} atau /produk/slug/{slug}

Method: GET

Daftar Wisata

URL: /wisata

Method: GET

Params: page, search, kategori, desa.

Detail Wisata

URL: /wisata/slug/{slug}

Method: GET

3. Wilayah (Alamat)

Mengambil data wilayah administratif Indonesia untuk form alamat.

Provinsi: GET /alamat/provinsi

Kabupaten: GET /alamat/provinsi/{id_provinsi}/kabupaten

Kecamatan: GET /alamat/kabupaten/{id_kabupaten}/kecamatan

Kelurahan/Desa: GET /alamat/kecamatan/{id_kecamatan}/kelurahan

4. Keranjang & Checkout

Memerlukan Header Authorization.

Sinkronisasi Keranjang

Menggabungkan keranjang tamu (local storage) dengan keranjang di database saat user login.

URL: /cart/sync

Method: POST

Body:

{
  "cart_items": [
     {
       "productId": 101,
       "quantity": 2,
       "variation": { "id": 5 } // Opsional
     }
  ]
}


Cek Ongkir (Shipping Options)

Mendapatkan opsi pengiriman yang tersedia dari tiap toko di keranjang ke alamat tujuan.

URL: /shipping-options

Method: POST

Body:

{
  "address_api": {
      "kabupaten_id": "35.15", // ID Kabupaten Tujuan
      "kecamatan_id": "35.15.01" // ID Kecamatan Tujuan
  },
  "cart_items": [
      { "product_id": 101, "quantity": 1, "seller_id": 5 },
      { "product_id": 202, "quantity": 2, "seller_id": 8 }
  ]
}


Response:

{
  "seller_options": {
      "5": { // ID Pedagang
          "options": [
              { "metode": "jne", "nama": "JNE REG", "harga": 25000 },
              { "metode": "local_delivery", "nama": "Kurir Desa", "harga": 5000 }
          ]
      },
      "8": { ... }
  }
}


Checkout (Buat Pesanan)

URL: /checkout

Method: POST

Body:

{
  "shipping_address_id": 12, // ID Alamat User (dari database)
  "payment_method": "transfer_bank",
  "cart_items": [ ... ], // Sama seperti shipping-options
  "seller_shipping_choices": {
      "5": { "metode": "local_delivery" }, // User memilih Kurir Desa untuk Toko ID 5
      "8": { "metode": "jne" }
  }
}


5. Pesanan Pembeli

Memerlukan Header Authorization.

Riwayat Pesanan Saya

URL: /orders

Method: GET

Detail Pesanan

URL: /orders/{id}

Method: GET

Konfirmasi Pembayaran

Mengunggah bukti transfer untuk pesanan.

URL: /orders/{id}/confirm

Method: POST

Content-Type: multipart/form-data

Body:

bukti_bayar: (File Gambar)

catatan: (Text) Catatan tambahan (opsional)

6. Manajemen Pedagang

Khusus untuk user dengan role pedagang atau administrator.

Dashboard Ringkasan

URL: /pedagang/dashboard/summary

Method: GET

Kelola Produk (List)

URL: /pedagang/produk

Method: GET

Tambah/Edit Produk

URL: /pedagang/produk (Tambah) atau /pedagang/produk/{id} (Edit)

Method: POST

Body:

{
  "nama_produk": "Kopi Bubuk Asli",
  "deskripsi": "Kopi robusta pilihan...",
  "harga_dasar": 25000,
  "stok": 100,
  "kategori": [15, 18], // ID Kategori
  "galeri_foto": [101, 102], // ID Attachment Media
  "variasi": [
     { "deskripsi": "250gr", "harga_variasi": 25000, "stok": 50 },
     { "deskripsi": "500gr", "harga_variasi": 45000, "stok": 50 }
  ]
}


Kelola Pesanan Masuk

URL: /pedagang/pesanan

Method: GET

Params: status (menunggu_konfirmasi, diproses, dikirim, selesai, dll).

Update Status Pesanan

Pedagang memperbarui status pesanan (misal: kirim barang).

URL: /pedagang/pesanan/{sub_order_id}/update

Method: POST

Body:

{
  "status": "dikirim_ekspedisi",
  "resi": "JP123456789", // Wajib jika status dikirim_ekspedisi
  "catatan": "Barang sudah dipickup kurir."
}


(Catatan: Jika status diubah menjadi dibatalkan, stok produk otomatis dikembalikan).

Upload Media (Gambar Produk)

URL: /media/upload

Method: POST

Content-Type: multipart/form-data

Body:

file: (File Gambar)