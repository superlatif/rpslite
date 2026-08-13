# Alur Transaksi

Dokumen ini menjelaskan bagaimana transaksi **Pembelian**, **Penjualan**, dan **Angsuran Customer** bekerja di RPS Lite. Seluruh transaksi dibuat melalui **modal form** pada halaman daftar masing-masing resource (bukan halaman Create/Edit terpisah), dan diproses atomik di dalam `DB::transaction`.

## Format Nomor Transaksi

Nomor dibuat otomatis (read-only) saat transaksi disimpan, dengan pola `PREFIX-XXXXXX` (6 digit):

| Tipe | Prefix | Contoh |
| --- | --- | --- |
| Pembelian (`PURCHASE`) | `PB` | `PB-000001` |
| Penjualan (`SALE`) | `PJ` | `PJ-000001` |

Nomor dihitung dari nilai terakhir dengan prefix yang sama (diurutkan menurun), lalu diincrement.

## 1. Pembelian

- **Header**: tanggal, supplier (wajib), jenis pembayaran (Tunai/Kredit).
- **Detail**: repeatable baris barang — pilih barang (mengisi harga otomatis dari `harga_beli`), isi qty dan harga.
- **Saat simpan**:
  1. Subtotal tiap baris dihitung `qty × unit_price`.
  2. `hpp_at_transaction` disnapshot dari `tb_stocks.harga_pokok`.
  3. `total_amount = Σ subtotal`.
  4. Tunai → `paid_amount = total`, `remaining_amount = 0`. Kredit → `paid_amount = 0`, `remaining_amount = total`.
  5. **Stok bertambah** (`tb_stocks.stock += qty`).
  6. Header + detail + update stok dibungkus satu transaksi database.

> Catatan: pembelian kredit ke supplier (hutang) belum memiliki resource pembayaran terpisah; hanya dicatat di `paid/remaining_amount` header.

## 2. Penjualan

- **Header**: tanggal, customer (wajib **hanya jika kredit**), jenis pembayaran (Tunai/Kredit).
- **Detail**: sama seperti pembelian, tapi harga diisi otomatis dari `harga_jual`.
- **Saat simpan**:
  1. **Validasi stok** per baris: jika stok kurang, muncul pesan error `Stok '...' tersedia N.` dan seluruh simpan dibatalkan.
  2. Hitung subtotal, total, `paid/remaining` (aturan yang sama dengan pembelian).
  3. **Stok berkurang** (`tb_stocks.stock -= qty`).
  4. Semua dalam satu transaksi database.

## 3. Angsuran Customer

- Resource ini mencatat pembayaran cicilan atas **penjualan kredit** yang belum lunas.
- **Form**: customer (wajib), pilih invoice kredit (`SALE` dengan `remaining_amount > 0`), tanggal bayar, jumlah.
  - Dropdown invoice menampilkan sisa tagihan dan otomatis mengisi kolom jumlah dengan sisa tersebut.
- **Saat simpan**:
  1. Validasi `amount ≤ remaining_amount` (tidak boleh melebihi sisa tagihan).
  2. Simpan ke `customer_payments`.
  3. Perbarui header: `paid_amount += amount`, `remaining_amount -= amount`.
- **Saat menghapus** angsuran (action "Hapus"):
  1. **Balikkan** angsuran ke header: `paid_amount -= amount`, `remaining_amount += amount`.
  2. Baru hapus record.

## Ringkasan State Keuangan

| Kondisi | paid_amount | remaining_amount |
| --- | --- | --- |
| Tunai (lunas saat transaksi) | `= total` | `0` |
| Kredit (belum bayar) | `0` | `= total` |
| Kredit (sebagian dibayar) | `> 0` | `> 0` |
| Kredit (lunas lewat angsuran) | `= total` | `0` |

## Tempat Implementasi

| Logika | File |
| --- | --- |
| Simpan pembelian + update stok | `app/Filament/Resources/TrPurchases/Pages/ListTrPurchases.php` |
| Simpan penjualan + validasi + update stok | `app/Filament/Resources/TrSales/Pages/ListTrSales.php` |
| Simpan & hapus angsuran + update header | `app/Filament/Resources/CustomerPayments/Pages/ListCustomerPayments.php`, `app/Filament/Resources/CustomerPayments/Tables/CustomerPaymentsTable.php` |
| Filter resource per tipe | `app/Filament/Resources/TrPurchases/TrPurchaseResource.php`, `app/Filament/Resources/TrSales/TrSaleResource.php` (`getEloquentQuery`) |
