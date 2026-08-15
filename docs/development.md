# Panduan Pengembangan

Panduan untuk pengembang yang berkontribusi pada **RPS Lite**.

## Stack

- PHP 8.5, Laravel 13, Filament 5, SQLite, Pest 5, Vite 8 + Tailwind 4, Laravel Pint.
- Versi pastinya cek `composer show --direct` dan `package.json`.

## Perintah Umum

```bash
# Menjalankan semua server sekaligus (server, queue, log, vite)
composer run dev

# Hanya build aset untuk produksi
npm run build

# Format kode PHP (wajib setelah mengubah file PHP)
vendor/bin/pint

# Menjalankan test
php artisan test --compact
php artisan test --compact --filter=NamaTest
```

## Struktur Resource Filament

Semua resource mengikuti pola **satu resource = satu folder** di `app/Filament/Resources/`:

```
app/Filament/Resources/<Nama>/
├── <Nama>Resource.php     # $model, $navigationGroup, $slug, getEloquentQuery(), getPages()
├── Schemas/<Nama>Form.php # definisi form
├── Tables/<Nama>Table.php # tabel, aksi baris, filter
└── Pages/List<Nama>.php   # halaman daftar + header action (CreateAction modal)
```

Resource transaksi yang ada: `TrPurchases`, `TrPurchaseReturns`, `TrSales`, `TrSaleReturns`, `TrOpnames` (semuanya memakai model `TrHeader`), plus `CustomerPayments`.

Konvensi yang dipakai:

- Navigasi dikelompokkan lewat `$navigationGroup` (`Tabel` untuk master, `Transaksi` untuk transaksi, `Laporan` untuk laporan).
- **Semua halaman hanya `List`** — pembuatan data lewat modal. Tidak ada halaman `Create`/`Edit` terpisah.
- Repeater detail transaksi **tidak** memakai `->relationship()`; baris detail disimpan manual di dalam `CreateAction::using()`.
- Resource transaksi memfilter model `TrHeader` lewat override `getEloquentQuery()->where('trr_type', ...)` (atau `whereIn` untuk laporan).
- **Route khusus panel** (cetak struk, cetak/export laporan) didaftarkan lewat `->authenticatedRoutes()` di `AdminPanelProvider`, **bukan** `routes/web.php` — middleware `auth` Laravel akan mengarahkan ke `route('login')` yang tidak ada (login Filament berada di `filament.admin.auth.login`).
- Ikuti aturan mengikat di `.ai/rules/` (diindeks oleh `.ai/rules/index.md`) — terutama untuk `TrPurchases`, `TrSales`, `TrOpnames`, `CustomerPayments`.

## Pola Transaksi (Penting)

1. Simpan data memakai `CreateAction::make()->using(...)` + `->databaseTransaction()` agar semua operasi (header, detail, stok) atomik.
2. Nomor transaksi otomatis (`PB-`/`RPB-`/`PJ-`/`RPJ-`/`OP-`) — lihat `docs/transaksi.md`.
3. `hpp_at_transaction` adalah snapshot yang tidak boleh diubah setelah posting.
4. **HPP stok** (`tb_stocks.harga_pokok`) dikelola otomatis: `recalculateHpp()` menghitung rata-rata tertimbang dari detail pembelian (`app/Models/TbStock.php`).
5. **Piutang customer** memakai `Customer::netReceivable()` (SALE kredit − SALE_RET kredit) sebagai batas pembayaran. Alokasi angsuran lewat `Customer::applyPayment()` / `reversePayment()` bersifat **proporsional**: pembayaran mengonsumsi sisa `SALE` dan `SALE_RET` sebanding porsinya; bagian penjualan dialokasikan FIFO (invoice tertaut didahulukan, kelebihan mengalir ke invoice lain), bagian retur FIFO ke `SALE_RET`.

## Halaman Laporan

Laporan adalah **halaman Filament biasa** (bukan resource), di `app/Filament/Pages/`:

- Kelas `Page implements HasTable` + `InteractsWithTable`.
- Filter laporan (periode, customer, dsb.) berupa `Action::make('generate')` di `getHeaderActions()` dengan modal form; aksinya menyimpan nilai ke property Livewire (`$date_from`, `$stock_id`, dll.).
- Tabel memakai `EmbeddedTable::make()` di dalam `content(Schema $schema)`, dan kelas tabel-nya ada di `app/Filament/Pages/Tables/` (query & `buildRows()`).
- **Trap SQLite**: kolom `date` tersimpan sebagai string datetime; gunakan `whereDate()` untuk rentang tanggal (lihat `.ai/rules/pages.md`).
- **Cetak & Export Excel** laporan memakai header `Action` berbasis `->url()` (bukan closure `->action()` Livewire) yang menunjuk ke route GET di `->authenticatedRoutes()`, karena butuh response langsung (Html view / stream CSV). Contoh: `laporan-kartu-stok.cetak` (view cetak `resources/views/laporan/kartu-stok.blade.php`, auto-print via `window.print()`, tombol toolbar tersembunyi saat print) dan `laporan-kartu-stok.export` (stream CSV ber-BOM UTF-8 `\xEF\xBB\xBF` agar Excel membaca UTF-8). Tombol dinonaktifkan (`->disabled(fn () => blank(...))`) sampai filter terisi. Struk penjualan memakai pola yang sama (`resources/views/struk/penjualan.blade.php`).

## Widget Dashboard

- `app/Filament/Widgets/` — widget otomatis ter-discover oleh panel.
- `SalesStatsOverview` & `SalesTrendChart` hanya mem-filter `trr_type = 'SALE'`; laba kotor = `Σ (unit_price − hpp_at_transaction) × qty`.
- Widget inventory (`InventoryStatsOverview`, `LowStockTable`, `StockValueByCategoryChart`) query langsung ke `tb_stocks`.

## Pembuatan Resource Baru

```bash
php artisan make:filament-resource NamaBaru --no-interaction
```

Kemudian pindahkan/rapikan file agar mengikuti pola folder `Schemas/`, `Tables/`, `Pages/` di atas, lalu isi `form()` dan `table()` dengan delegasi ke kelas-kelas tersebut.

## Model

- Selalu definisikan `$fillable`, `$casts` (khususnya `decimal:2` untuk kolom uang, `date` untuk tanggal), dan relasi Eloquent.
- Setiap kolom yang dipakai di form/tabel harus tercakup di `$fillable`.

## Testing

- Tulis test sebagai **feature test Pest**: `php artisan make:test --pest NamaTest`.
- Gunakan factory model untuk data uji; jangan bikin model manual di tinker untuk pengujian.

## Git & Dokumentasi

- Repositori sudah diinisialisasi git (branch `main`). Saat commit: `git add` hanya file yang relevan, jangan commit secret, dan ikuti gaya commit yang ada.
- Jangan membuat file dokumentasi baru tanpa diminta; langsung edit yang sudah ada bila memungkinkan. Dokumen pendukung saat ini ada di `docs/`.
