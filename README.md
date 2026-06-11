# QOMA - QR Order Management Application

## 📖 Tentang Project

QOMA (QR Order Management Application) adalah aplikasi SaaS Point of Sale (POS) berbasis web yang dirancang untuk membantu pemilik usaha makanan dan minuman dalam mengelola pemesanan pelanggan, stok bahan baku, keuangan, serta manajemen outlet secara terpusat.

Sistem menggunakan QR Code Ordering sehingga pelanggan dapat melakukan pemesanan langsung dari meja tanpa perlu login. Pesanan akan langsung diterima oleh outlet dan diproses oleh kasir.

QOMA menerapkan konsep multi-tenant dimana satu owner dapat memiliki banyak outlet sesuai dengan paket subscription yang dipilih.

---

## 🎯 Tujuan Project

- Digitalisasi proses pemesanan pelanggan.
- Mengurangi kesalahan pencatatan pesanan.
- Mengotomatisasi pengurangan stok bahan baku.
- Memudahkan monitoring keuangan setiap outlet.
- Memudahkan owner mengelola banyak cabang dalam satu sistem.
- Menyediakan sistem subscription untuk model bisnis SaaS.

---

## 🏗️ Arsitektur Sistem

### Role dalam Sistem

1. Pelanggan
2. Outlet
3. Owner
4. Super Admin

---

## ⚙️ Teknologi yang Digunakan

### Backend

- Laravel
- PHP
- REST API
- Laravel Sanctum Authentication
- Laravel Queue
- Laravel Scheduler

### Database

- Supabase PostgreSQL

### Development Environment

- Laragon

### Storage

- Laravel Storage
- Supabase Storage (opsional)

### Realtime

- Laravel Event & Broadcasting

---

## 🚀 Fitur Utama

### Landing Page

- Informasi produk
- Pricing Plan
- Registrasi Owner
- Upgrade Subscription
- Informasi Paket

### Subscription Plan

#### Free Trial

- Maksimal 2 outlet
- Akses fitur dasar

#### Pro

- Outlet tidak terbatas
- Semua fitur premium

---

# 👤 Role Pelanggan

Pelanggan tidak perlu membuat akun.

## Alur Pemesanan

1. Scan QR Code meja.
2. Sistem membuka menu outlet.
3. Pilih menu.
4. Tambahkan addon jika tersedia.
5. Masukkan ke keranjang.
6. Isi nama dan nomor telepon.
7. Konfirmasi pesanan.
8. Sistem membuat order otomatis.
9. Pelanggan menuju kasir untuk pembayaran.

## Data Pesanan

Setiap pesanan memiliki:

- ID Pesanan
- Nomor Meja
- Nama Pelanggan
- Nomor Telepon
- Detail Menu
- Total Harga

---

# 🏪 Role Outlet

Role outlet berfungsi sebagai:

- Kasir
- Pengelola stok bahan baku

---

## Manajemen Pesanan

### Menerima Pesanan

Pesanan dari pelanggan akan masuk otomatis ke dashboard outlet.

Informasi yang diterima:

- ID Pesanan
- Nomor Meja
- Nama Pelanggan
- Nomor Telepon
- Detail Pesanan

### Validasi Pesanan

Kasir dapat:

- Menambah menu
- Mengurangi menu
- Mengubah jumlah pesanan

Sebelum pembayaran dikonfirmasi.

### Konfirmasi Pembayaran

Setelah pelanggan membayar:

- Status pembayaran dikonfirmasi
- Pesanan tidak dapat diubah kembali
- Sistem mengurangi stok bahan baku secara otomatis

---

## Manajemen Meja

- Tambah meja
- Edit meja
- Hapus meja
- Generate QR Code otomatis

Setiap meja memiliki QR Code unik.

---

## Manajemen Stok Bahan Baku

### Penambahan Stok

Outlet dapat menambah stok dari bahan baku yang telah dibuat owner.

Data yang dicatat:

- Bahan Baku
- Jumlah
- Tanggal Masuk
- Tanggal Kadaluarsa

---

### Stock Opname

Digunakan untuk pengurangan stok secara manual.

Alasan pengurangan:

- Busuk
- Rusak
- Tidak Layak
- Hilang

Data opname:

- Bahan Baku
- Jenis Kerusakan
- Jumlah
- Foto Bukti

---

### Notifikasi Stok

Sistem memberikan alert jika:

- Stok < 5
- Mendekati tanggal kadaluarsa

---

## Manajemen Menu Outlet

Outlet dapat:

- Mengubah harga menu
- Menyesuaikan harga berdasarkan kondisi wilayah

---

## Operasional Outlet

### Buka/Tutup Toko

Outlet dapat:

- Membuka toko
- Menutup toko

Ketika toko ditutup:

- Pelanggan tidak dapat memesan
- Dashboard kasir tidak menerima order baru

---

## Keuangan Outlet

### Pendapatan

Dihitung dari:

- Total transaksi berhasil

### Pengeluaran

Dihitung dari:

- Pembelian bahan baku
- Penyesuaian stok

### Laba/Rugi

Sistem menghitung:

```
Keuntungan = Pendapatan - Pengeluaran
```

Jika hasil negatif maka ditampilkan sebagai kerugian.

---

## Activity Log

Mencatat seluruh aktivitas outlet seperti:

- Login
- Transaksi
- Stock Opname
- Update Stok
- Perubahan Menu

---

# 👑 Role Owner

Owner merupakan pemilik usaha.

---

## Dashboard Owner

Menampilkan:

- Total Outlet
- Total Karyawan
- Total Pendapatan
- Total Kerugian

### Grafik Analitik

Menampilkan:

- Grafik Pendapatan
- Grafik Laba/Rugi

Dengan filter:

- Harian
- Mingguan
- Bulanan

---

## Manajemen Outlet

Owner dapat:

- Menambah outlet
- Mengedit outlet
- Melihat daftar outlet

Data outlet:

- Nama Outlet
- Alamat
- Email
- Password

---

## Manajemen Menu

Owner membuat master menu.

Data menu:

- Nama Menu
- Gambar
- Harga Default
- Kategori
- Bahan Baku
- Deskripsi

Kategori:

- Makanan
- Minuman
- Snack
- Dessert
- Lainnya

---

## Manajemen Bahan Baku

Owner membuat master bahan baku.

Data:

- Nama
- Gambar
- Satuan
- Harga Default

---

## Keuangan Owner

Owner dapat melihat:

### Ringkasan

- Total Pendapatan
- Total Pengeluaran
- Total Keuntungan
- Total Kerugian

### Filter

- 1 Hari
- 7 Hari
- 30 Hari

---

## Subscription

Owner dapat:

- Melihat paket aktif
- Upgrade paket
- Memperpanjang paket

---

## Activity Log

Mencatat seluruh aktivitas owner.

---

# 🛡️ Role Super Admin

Super Admin mengelola seluruh sistem SaaS.

---

## Dashboard Super Admin

Menampilkan:

- Total Usaha
- Total Outlet
- Total Pendapatan Subscription
- Monthly Recurring Revenue (MRR)

---

## Analitik MRR

Filter:

- Harian
- Mingguan
- Bulanan

---

## Manajemen Subscription

### Plan

CRUD Paket Subscription

Data:

- Nama Paket
- Harga
- Batas Outlet

Contoh:

- Free Trial
- Pro

---

### Pelanggan Subscription

Menampilkan:

- Nama Perusahaan
- Nama Owner
- Jenis Subscription
- Tanggal Mulai

Detail:

#### Subscription

- Subscription ID
- Plan ID
- Start Date
- Created At
- Updated At

#### Usaha

- Nama Perusahaan
- Email
- Alamat
- Total Outlet

---

## Notifikasi

Super Admin menerima notifikasi ketika:

- Ada owner baru mendaftar
- Ada subscription baru
- Ada upgrade subscription

---

## Activity Log

Mencatat aktivitas seluruh sistem.

---

# 📊 Flow Sistem

## Pelanggan

```text
Scan QR
   ↓
Pilih Menu
   ↓
Keranjang
   ↓
Isi Data Pelanggan
   ↓
Konfirmasi Pesanan
   ↓
Masuk Dashboard Outlet
   ↓
Pembayaran Kasir
   ↓
Stok Berkurang Otomatis
```

---

## Owner

```text
Registrasi
   ↓
Pilih Paket
   ↓
Buat Outlet
   ↓
Kelola Menu
   ↓
Kelola Bahan Baku
   ↓
Monitor Keuangan
```

---

## Struktur Multi Tenant

```text
Super Admin
      │
      ▼
    Owner
      │
 ┌────┴────┐
 ▼         ▼
Outlet A  Outlet B
 │         │
 ▼         ▼
Pelanggan Pelanggan
```

---

# 🔒 Keamanan

- Authentication menggunakan Laravel Sanctum
- Password Hashing menggunakan Bcrypt
- Role Based Access Control (RBAC)
- Activity Logging
- Validasi Request Laravel

---

# 📈 Future Development

- Realtime Notification
- Integrasi Payment Gateway
- Dashboard AI Analytics
- Prediksi Kebutuhan Stok
- Mobile Application
- Export PDF & Excel
- Multi Bahasa

---

# 👨‍💻 Developer

Project ini dikembangkan sebagai sistem SaaS POS multi-outlet dengan fitur QR Ordering, Inventory Management, Subscription Management, dan Financial Monitoring menggunakan Laravel dan Supabase PostgreSQL.
