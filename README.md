# RPS Lite

Sistem **Point of Sale (POS)** sederhana berbasis web untuk mencatat **pembelian**, **penjualan**, **retur**, **stok opname**, dan **angsuran customer** dengan manajemen stok barang. Dibangun di atas **Laravel 13** + **Filament 5** (panel admin), database **SQLite**.

## Fitur

- **Data Master**
  - Kategori barang (`tb-cate`)
  - Data barang / stok (`tb-barang`) — kode otomatis 8 digit, foto, harga beli & jual
  - Data customer (`tb-customer`)
  - Data supplier (`tb-supplier`)
- **Transaksi**
  - **Pembelian** (`pembelian`) — dari supplier, stok otomatis bertambah, nomor `PB-XXXXXX`, HPP rata-rata tertimbang diperbarui
  - **Retur Pembelian** (`retur-pembelian`) — barang dikembalikan ke supplier, stok otomatis berkurang, nomor `RPB-XXXXXX`
  - **Penjualan** (`penjualan`) — ke customer, stok otomatis berkurang (dengan validasi stok), nomor `PJ-XXXXXX`
  - **Retur Penjualan** (`retur-penjualan`) — barang kembali dari customer, stok otomatis bertambah, nomor `RPJ-XXXXXX`
  - **Stok Opname** (`opname`) — penyesuaian stok fisik vs sistem, nomor `OP-XXXXXX`
  - **Angsuran Customer** (`angsuran-customer`) — pembayaran cicilan tagihan kredit (alokasi FIFO), sisa tagihan otomatis diperbarui
- **Laporan**
  - Laporan Penjualan — ringkasan per barang / per customer
  - Kartu Stok — mutasi masuk/keluar per barang
  - Piutang (Aging) — umur piutang per customer
  - Kartu Piutang — mutasi piutang per customer
  - Nilai Persediaan — nilai stok (`stock × harga_pokok`)
- **Dashboard** — widget ringkasan penjualan/pembelian, laba kotor, stok menipis, dan grafik tren.
- **Pembayaran Tunai / Kredit** untuk setiap transaksi (kolom `trs_type`), piutang terlacak lewat `paid_amount` / `remaining_amount`.
- HPP (harga pokok) di-snapshot per baris detail transaksi (`hpp_at_transaction`) untuk perhitungan laba.

## Teknologi

| Komponen | Versi |
| --- | --- |
| PHP | ^8.3 |
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
# 1. Salin .env dan isi konfigurasi (bila .env.example tersedia, buat manual jika tidak)
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

Buka <http://localhost:8000> (dashboard), lalu login di <http://localhost:8000/login> dengan user Filament yang dibuat di langkah instalasi.

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
│   ├── Pages/                      # Halaman Laporan (bukan resource)
│   │   ├── LaporanPenjualan.php
│   │   ├── LaporanKartuStok.php
│   │   ├── LaporanPiutang.php
│   │   ├── LaporanKartuPiutang.php
│   │   ├── LaporanNilaiPersediaan.php
│   │   └── Tables/                 # Tabel laporan (query & buildRows)
│   ├── Resources/
│   │   ├── TbCates/                # Kategori barang
│   │   ├── TbStocks/               # Data barang / stok
│   │   ├── TbCustomers/            # Data customer
│   │   ├── TbSuppliers/            # Data supplier
│   │   ├── TrPurchases/            # Transaksi pembelian
│   │   ├── TrPurchaseReturns/      # Retur pembelian
│   │   ├── TrSales/                # Transaksi penjualan
│   │   ├── TrSaleReturns/          # Retur penjualan
│   │   ├── TrOpnames/              # Stok opname
│   │   └── CustomerPayments/       # Angsuran customer
│   ├── Widgets/                    # Widget dashboard (statistik & grafik)
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
| Tabel | Kategori Barang | `/tb-cate` |
| Tabel | Data Barang | `/tb-barang` |
| Tabel | Data Supplier | `/tb-supplier` |
| Tabel | Data Customer | `/tb-customer` |
| Transaksi | Pembelian | `/pembelian` |
| Transaksi | Retur Pembelian | `/retur-pembelian` |
| Transaksi | Penjualan | `/penjualan` |
| Transaksi | Retur Penjualan | `/retur-penjualan` |
| Transaksi | Angsuran Customer | `/angsuran-customer` |
| Transaksi | Stok Opname | `/opname` |
| Laporan | Laporan Penjualan | `/laporan-penjualan` |
| Laporan | Kartu Stok | `/laporan-kartu-stok` |
| Laporan | Piutang (Aging) | `/laporan-piutang` |
| Laporan | Kartu Piutang | `/laporan-kartu-piutang` |
| Laporan | Nilai Persediaan | `/laporan-nilai-persediaan` |

## Testing

```bash
php artisan test --compact
# filter test tertentu
php artisan test --compact --filter=NamaTest
```

## Dokumentasi Pendukung

- [Skema Database](docs/database.md)
- [Alur Transaksi (Pembelian, Penjualan, Retur, Opname, Angsuran)](docs/transaksi.md)
- [Panduan Pengembangan](docs/development.md)

## Konvensi Kode

- Format otomatis dengan **Laravel Pint**: `vendor/bin/pint`
- Pengujian dengan **Pest**
- Aturan proyek yang mengikat untuk agent/pengembang ada di `.ai/rules/`
