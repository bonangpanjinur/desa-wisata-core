Dokumentasi API Desa Wisata Core

Dokumentasi ini menjelaskan endpoint REST API yang tersedia di plugin Desa Wisata Core.

Base URL: https://your-domain.com/wp-json/dw/v1

Daftar Isi

Otentikasi & Akun

Data Publik (Guest)

Keranjang & Checkout

Area Pembeli

Area Pedagang (Merchant)

1. Otentikasi & Akun

Semua endpoint yang memerlukan login harus menyertakan header:
Authorization: Bearer <token_jwt>

Login

Mendapatkan token akses JWT.

URL: /auth/login

Method: POST

Body:

{
  "username": "email@example.com",
  "password": "password_user"
}


Response:

{
  "token": "eyJ0eXAiOiJKV1...",
  "refresh_token": "def50200...",
  "user": {
    "id": 1,
    "username": "user",
    "email": "email@example.com",
    "roles": ["subscriber", "pedagang"],
    "is_pedagang": true
  }
}


Register (Pendaftaran Pembeli)

Mendaftar sebagai pengguna baru.

URL: /auth/register

Method: POST

Body:

{
  "username": "username_baru",
  "email": "email@example.com",
  "password": "password_kuat",
  "fullname": "Nama Lengkap",
  "no_hp": "081234567890"
}


Refresh Token

Memperbarui token akses yang kadaluwarsa.

URL: /auth/refresh

Method: POST

Body:

{ "refresh_token": "def50200..." }


Logout

Mencabut sesi token.

URL: /auth/logout

Method: POST

Body:

{ "refresh_token": "def50200..." }


2. Data Publik

Endpoint ini dapat diakses tanpa login (Public).

Daftar Banner

URL: /banner

Method: GET

Daftar Kategori Produk

URL: /kategori/produk

Method: GET

Daftar Desa Wisata

URL: /desa

Method: GET

Params: page, search, provinsi_id

Detail Desa

URL: /desa/{id}

Method: GET

Daftar Produk

URL: /produk

Method: GET

Params: page, per_page, search, kategori, desa (ID), toko (ID User).

Detail Produk

URL: /produk/{id}

Method: GET

Daftar Wisata

URL: /wisata

Method: GET

Params: page, search, kategori, desa.

Detail Wisata

URL: /wisata/{id} (atau slug)

Method: GET

3. Keranjang & Checkout

Memerlukan Header Authorization.

Sinkronisasi Keranjang

Menggabungkan keranjang lokal dengan database.

URL: /cart/sync

Method: POST

Body:

{
  "cart_items": [
     { "productId": 101, "quantity": 2 }
  ]
}


Cek Ongkir (Shipping Options)

Mendapatkan opsi pengiriman dari tiap toko.

URL: /shipping-options

Method: POST

Body:

{
  "address_api": {
      "kabupaten_id": "35.15",
      "kecamatan_id": "35.15.01"
  },
  "cart_items": [ ... ]
}


Checkout (Buat Pesanan)

URL: /checkout

Method: POST

Body:

{
  "shipping_address_id": 12,
  "payment_method": "transfer_bank",
  "cart_items": [ ... ],
  "seller_shipping_choices": {
      "5": { "metode": "local_delivery" },
      "8": { "metode": "jne" }
  }
}


4. Area Pembeli

Riwayat Pesanan Saya

URL: /orders

Method: GET

Detail Pesanan

URL: /orders/{id}

Method: GET

Konfirmasi Pembayaran

URL: /orders/{id}/confirm

Method: POST

Content-Type: multipart/form-data

Body: bukti_bayar (File), catatan (Text)

5. Area Pedagang

Khusus untuk user dengan role pedagang.

Dashboard Ringkasan

Mendapatkan statistik penjualan, stok habis, dll.

URL: /pedagang/dashboard/summary

Method: GET

Profil Toko

Mendapatkan data toko saat ini.

URL: /pedagang/profile/me

Method: GET

Update Profil Toko

URL: /pedagang/profile/me

Method: POST

Body:

{
  "nama_toko": "Toko Baru",
  "deskripsi_toko": "...",
  "no_rekening": "12345",
  "nama_bank": "BCA"
}


Kelola Produk (List)

URL: /pedagang/produk

Method: GET

Tambah Produk

URL: /pedagang/produk

Method: POST

Content-Type: multipart/form-data atau application/json

Body:

{
  "nama_produk": "Kopi Bubuk",
  "deskripsi": "...",
  "harga_dasar": 25000,
  "stok": 100
}


Edit/Hapus Produk

Detail: GET /pedagang/produk/{id}

Update: POST /pedagang/produk/{id}

Hapus: DELETE /pedagang/produk/{id}

Kelola Pesanan Masuk

URL: /pedagang/orders

Method: GET

Params: status (opsional: menunggu_konfirmasi, diproses, dikirim_ekspedisi, selesai, dibatalkan)

Detail Sub-Order

Mendapatkan detail pesanan spesifik yang masuk ke toko pedagang.

URL: /pedagang/orders/sub/{sub_order_id}

Method: GET

Update Status Pesanan

Pedagang memproses pesanan (Terima, Tolak, Kirim Resi).

URL: /pedagang/orders/sub/{sub_order_id}

Method: POST

Body:

{
  "status": "dikirim_ekspedisi",
  "nomor_resi": "JP123456", // Wajib jika dikirim
  "catatan": "Barang sudah dikirim"
}


Paket & Kuota

Daftar Paket: GET /pedagang/paket/daftar

Beli Paket: POST /pedagang/paket/beli (Body: id_paket, file bukti bayar)
