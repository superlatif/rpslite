# Alur Transaksi

Dokumen ini menjelaskan bagaimana transaksi **Pembelian**, **Penjualan**, **Retur**, **Stok Opname**, dan **Angsuran Customer** bekerja di RPS Lite. Seluruh transaksi dibuat melalui **modal form** pada halaman daftar masing-masing resource (bukan halaman Create/Edit terpisah), dan diproses atomik di dalam `DB::transaction` (`CreateAction::make()->using(...)->databaseTransaction()`).

## Format Nomor Transaksi

Nomor dibuat otomatis (read-only) saat transaksi disimpan, dengan pola `PREFIX-XXXXXX` (6 digit), dihitung dari nomor terakhir dengan prefix yang sama lalu diincrement:

| Tipe | Prefix | Contoh |
| --- | --- | --- |
| Pembelian (`PURCHASE`) | `PB` | `PB-000001` |
| Retur Pembelian (`PURCHASE_RET`) | `RPB` | `RPB-000001` |
| Penjualan (`SALE`) | `PJ` | `PJ-000001` |
| Retur Penjualan (`SALE_RET`) | `RPJ` | `RPJ-000001` |
| Stok Opname (`OPNAME`) | `OP` | `OP-000001` |

## 1. Pembelian

- **Header**: tanggal, supplier (wajib).
- **Detail**: repeatable baris barang — pilih barang (mengisi harga otomatis dari `harga_beli`), isi qty dan harga.
- **Saat simpan**:
  1. Validasi: barang tidak boleh duplikat, `qty > 0`.
  2. Subtotal tiap baris = `qty × unit_price`; `total_amount = Σ subtotal`.
  3. **Stok bertambah** (`tb_stocks.stock += qty`).
  4. **HPP diperbarui**: `recalculateHpp()` menghitung ulang HPP rata-rata tertimbang (`SUM(subtotal) / SUM(qty)` dari semua detail pembelian).
  5. Header + detail + update stok dibungkus satu transaksi database.

> Catatan: pembelian kredit ke supplier (hutang) belum memiliki resource pembayaran terpisah; hanya dicatat di `paid/remaining_amount` header.

## 2. Retur Pembelian

Barang dikembalikan ke supplier (kebalikan dari pembelian).

- **Header**: tanggal, supplier (wajib). Nomor `RPB-XXXXXX`.
- **Saat simpan**:
  1. Validasi stok: stok berkurang tidak boleh melebihi stok tersedia (pesan error per baris).
  2. **Stok berkurang** (`tb_stocks.stock -= qty`).
  3. Tidak memicu `recalculateHpp()`.

## 3. Penjualan

- **Header**: tanggal, customer (wajib **hanya jika kredit**), jenis pembayaran (Tunai/Kredit).
- **Detail**: sama seperti pembelian, tapi harga diisi otomatis dari `harga_jual`.
- **Saat simpan**:
  1. Validasi stok per baris: jika stok kurang, muncul pesan error `Stok '...' tersedia N.` dan seluruh simpan dibatalkan.
  2. `hpp_at_transaction` disnapshot dari `tb_stocks.harga_pokok` saat transaksi.
  3. **Stok berkurang** (`tb_stocks.stock -= qty`).
  4. Semua dalam satu transaksi database.

> **Cetak Struk**: setiap baris penjualan di tabel Penjualan memiliki action **"Cetak Struk"** (di kiri baris, dalam action group) yang membuka struk di tab baru. Struk menampilkan "Yth" customer (nama/alamat/telp), tanggal, jenis pembayaran (TUNAI/KREDIT), **sisa piutang** bila masih kredit, rincian barang, dan total. Route `filament.admin.penjualan.struk` (`GET /penjualan/{trHeader}/struk`) hanya berlaku untuk header `SALE` (selain itu 404) dan memakai view `resources/views/struk/penjualan.blade.php` (auto-print saat dibuka).

## 4. Retur Penjualan

Barang kembali dari customer (kebalikan dari penjualan).

- **Header**: tanggal, customer, jenis pembayaran (Tunai/Kredit). Nomor `RPJ-XXXXXX`.
- **Saat simpan**:
  1. **Stok bertambah** (`tb_stocks.stock += qty`).
  2. `hpp_at_transaction` disnapshot dari `harga_pokok` saat transaksi.
  3. **Retur kredit mengurangi piutang customer**: `SALE_RET` kredit mencatat `remaining_amount = total` dan berlaku sebagai kredit yang mengurangi sisa tagihan customer (lihat `Customer::netReceivable()`); saat angsuran dibayar, retur ini **ikut dikonsumsi secara proporsional**.

## 5. Stok Opname

Penyesuaian stok untuk mencocokkan stok sistem dengan hasil hitungan fisik.

- **Header**: hanya tanggal. Nomor `OP-XXXXXX`; `total/paid/remaining = 0`.
- **Detail**: pilih barang → tampil **stok sistem** (read-only), isi **stok fisik**, selisih dihitung otomatis (`stok_fisik − stok_sistem`).
- **Saat simpan**:
  1. Validasi `stok_fisik ≥ 0`.
  2. **`qty` tersimpan bertanda** — ini satu-satunya tipe transaksi yang menyimpan `qty` signed: positif = surplus (+stok), negatif = shortage (−stok).
  3. **Stok diset langsung** ke nilai fisik (`tb_stocks.stock = stok_fisik`).
  4. Tidak memicu `recalculateHpp()`.

## 6. Angsuran Customer

- Resource ini mencatat pembayaran cicilan atas **piutang bersih** customer (`netReceivable` = penjualan kredit dikurangi retur penjualan kredit).
- **Form**: customer (wajib), pilih invoice kredit (`SALE` dengan `remaining_amount > 0`), tanggal bayar, jumlah.
  - Dropdown invoice menampilkan sisa tagihan dan otomatis mengisi kolom jumlah.
  - Batas maksimal pembayaran adalah **sisa tagihan bersih** `Customer::netReceivable()` = `Σ SALE kredit − Σ SALE_RET kredit`.
- **Saat simpan** (`ListCustomerPayments::createPayment`):
  1. Validasi `amount ≤ netReceivable` dan (bila invoice dipilih) `amount ≤ remaining_amount` invoice tersebut.
  2. Simpan ke `customer_payments`.
  3. Alokasikan lewat `Customer::applyPayment()` — **proporsional**: pembayaran mengonsumsi sisa penjualan kredit (`SALE`) **dan** retur penjualan kredit (`SALE_RET`) sebanding porsinya terhadap sisa bersih. Bagian penjualan dialokasikan **FIFO** (invoice terlama lebih dulu); bila menautkan ke invoice tertentu, invoice itu didahulukan dan kelebihannya mengalir ke invoice lain. Bagian retur dialokasikan FIFO ke transaksi `SALE_RET`.
- **Saat menghapus** angsuran (action "Hapus" di tabel):
  1. Balikkan alokasi lewat `Customer::reversePayment()` (mengembalikan sisa `SALE` & `SALE_RET` sesuai proporsi semula).
  2. Baru hapus record.

## Ringkasan State Keuangan

| Kondisi | paid_amount | remaining_amount |
| --- | --- | --- |
| Tunai (lunas saat transaksi) | `= total` | `0` |
| Kredit (belum bayar) | `0` | `= total` |
| Kredit (sebagian dibayar) | `> 0` | `> 0` |
| Kredit (lunas lewat angsuran) | `= total` | `0` |
| Retur penjualan kredit (kredit utk customer) | `0` | `= total` (mengurangi piutang) |

## Tempat Implementasi

| Logika | File |
| --- | --- |
| Simpan pembelian + update stok + HPP | `app/Filament/Resources/TrPurchases/Pages/ListTrPurchases.php` |
| Simpan retur pembelian + validasi + update stok | `app/Filament/Resources/TrPurchaseReturns/Pages/ListTrPurchaseReturns.php` |
| Simpan penjualan + validasi + update stok | `app/Filament/Resources/TrSales/Pages/ListTrSales.php` |
| Simpan retur penjualan + update stok | `app/Filament/Resources/TrSaleReturns/Pages/ListTrSaleReturns.php` |
| Simpan opname + set stok fisik | `app/Filament/Resources/TrOpnames/Pages/ListTrOpnames.php` |
| Simpan & hapus angsuran + alokasi proporsional (SALE & SALE_RET) | `app/Filament/Resources/CustomerPayments/Pages/ListCustomerPayments.php`, `app/Filament/Resources/CustomerPayments/Tables/CustomerPaymentsTable.php`, `app/Models/Customer.php` |
| Cetak struk penjualan | `app/Filament/Resources/TrSales/Tables/TrSalesTable.php`, `app/Providers/Filament/AdminPanelProvider.php` (route `penjualan.struk`), `resources/views/struk/penjualan.blade.php` |
| Filter resource per tipe | `app/Filament/Resources/TrPurchases/TrPurchaseResource.php`, `app/Filament/Resources/TrPurchaseReturns/TrPurchaseReturnResource.php`, `app/Filament/Resources/TrSales/TrSaleResource.php`, `app/Filament/Resources/TrSaleReturns/TrSaleReturnResource.php`, `app/Filament/Resources/TrOpnames/TrOpnameResource.php` (`getEloquentQuery`) |
