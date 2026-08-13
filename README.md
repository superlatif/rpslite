# RPS Lite

Sistem **Point of Sale (POS)** sederhana berbasis web untuk mencatat **pembelian**, **penjualan**, dan **angsuran customer** dengan manajemen stok barang. Dibangun di atas **Laravel 13** + **Filament 5** (panel admin), database **SQLite**.

## Fitur

- **Data Master**
  - Kategori barang (`tb-cate`)
  - Data barang / stok (`tb-barang`) — kode otomatis 8 digit, foto, harga beli & jual
  - Data customer (`tb-customer`)
  - Data supplier (`tb-supplier`)
- **Transaksi**
  - **Pembelian** (`pembelian`) — dari supplier, stok otomatis bertambah, nomor `PB-XXXXXX`
  - **Penjualan** (`penjualan`) — ke customer, stok otomatis berkurang (dengan validasi stok), nomor `PJ-XXXXXX`
  - **Angsuran Customer** (`angsuran-customer`) — pembayaran cicilan tagihan kredit, sisa tagihan otomatis diperbarui
- **Pembayaran Tunai / Kredit** untuk setiap transaksi (kolom `trs_type`), piutang terlacak lewat `paid_amount` / `remaining_amount`.
- HPP (harga pokok) di-snapshot per baris detail transaksi (`hpp_at_transaction`) untuk perhitungan laba nantinya.

## Teknologi

| Komponen | Versi |
| --- | --- |
| PHP | ^8.5 |
| Laravel | ^13.8 |
| Filament | ^5.0 |
| Database | SQLite (default) |
| Testing | Pest ^5.1 |
| Formatting | Laravel Pint ^1.27 |
| Frontend | Vite 8 + Tailwind CSS 4 |

## Persyaratan

- PHP >= 8.3 (disarankan 8.5)
- Composer
- Node.js + npm
- Ekstensi SQLite di PHP

## Instalasi

```bash
# 1. Salin .env dan isi konfigurasi (bila belum ada)
cp .env.example .env

# 2. Generate app key
php artisan key:generate

# 3. Install dependency PHP & build aset frontend
composer install
npm install
npm run build

# 4. Buat database SQLite & jalankan migrasi
touch database/database.sqlite
php artisan migrate

# 5. (Opsional) buat user admin
php artisan make:filament-user
```

Alternatif, jalankan skrip setup bawaan Laravel:

```bash
composer run setup
```

## Menjalankan Aplikasi

### Mode Development (server + queue + log + vite)

```bash
composer run dev
```

Buka <http://localhost:8000/admin>, lalu login dengan user Filament yang dibuat di langkah instalasi.

### Manual

```bash
php artisan serve
# terminal terpisah
npm run dev
```

### Build Aset untuk Produksi

```bash
npm run build
```

## Struktur Proyek

```
app/
├── Filament/
│   ├── Actions/                    # SafeDeleteAction (hapus aman terhadap FK)
│   ├── Resources/
│   │   ├── TbCates/                # Kategori barang
│   │   ├── TbStocks/               # Data barang / stok
│   │   ├── TbCustomers/            # Data customer
│   │   ├── TbSuppliers/            # Data supplier
│   │   ├── TrPurchases/            # Transaksi pembelian
│   │   ├── TrSales/                # Transaksi penjualan
│   │   └── CustomerPayments/       # Angsuran customer
│   └── ...
├── Models/
│   ├── TbCate.php, TbStock.php
│   ├── Customer.php, Supplier.php
│   ├── TrHeader.php, TrDetail.php
│   └── CustomerPayment.php
database/migrations/                # Skema database
docs/                               # Dokumentasi pendukung
tests/                              # Pengujian Pest
```

Setiap resource Filament mengikuti pola folder:

```
Resources/<Nama>/
├── <Nama>Resource.php     # Definisi resource (model, ikon, group, slug)
├── Schemas/<Nama>Form.php # Form (modal)
├── Tables/<Nama>Table.php # Tabel
└── Pages/List<Nama>.php   # Halaman daftar (modal create)
```

## Menu / Halaman

| Group | Label | Slug |
| --- | --- | --- |
| Tabel | Data Barang | `/admin/tb-barang` |
| Tabel | Kategori | `/admin/tb-cate` |
| Tabel | Data Customer | `/admin/tb-customer` |
| Tabel | Data Supplier | `/admin/tb-supplier` |
| Transaksi | Pembelian | `/admin/pembelian` |
| Transaksi | Penjualan | `/admin/penjualan` |
| Transaksi | Angsuran Customer | `/admin/angsuran-customer` |

## Testing

```bash
php artisan test --compact
# filter test tertentu
php artisan test --compact --filter=NamaTest
```

## Dokumentasi Pendukung

- [Skema Database](docs/database.md)
- [Alur Transaksi (Pembelian, Penjualan, Angsuran)](docs/transaksi.md)
- [Panduan Pengembangan](docs/development.md)

## Konvensi Kode

- Format otomatis dengan **Laravel Pint**: `vendor/bin/pint`
- Pengujian dengan **Pest**
- Aturan proyek yang mengikat untuk agent/pengembang ada di `.ai/rules/`
