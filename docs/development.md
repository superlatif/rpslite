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

Konvensi yang dipakai:

- Navigasi dikelompokkan lewat `$navigationGroup` (`Tabel` untuk master, `Transaksi` untuk transaksi).
- **Semua halaman hanya `List`** — pembuatan data lewat modal. Tidak ada halaman `Create`/`Edit` terpisah.
- Repeater detail transaksi **tidak** memakai `->relationship()`; baris detail disimpan manual di dalam `CreateAction::using()`.
- Ikuti aturan mengikat di `.ai/rules/` (diindeks oleh `.ai/rules/index.md`) — terutama untuk `TrPurchases`, `TrSales`, `CustomerPayments`.

## Pola Transaksi (Penting)

1. Resource `TrPurchaseResource` dan `TrSaleResource` sama-sama memakai model `TrHeader`; pemisahannya lewat override `getEloquentQuery()->where('trr_type', ...)`.
2. Simpan data memakai `CreateAction::make()->using(...)` + `->databaseTransaction()` agar semua operasi (header, detail, stok) atomik.
3. Nomor transaksi otomatis (`PB-`/`PJ-`) — lihat `docs/transaksi.md`.
4. `hpp_at_transaction` adalah snapshot yang tidak boleh diubah setelah posting.

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

- Repositori ini bukan repo git (belum diinisialisasi). Jika diinisialisasi: `git add` hanya file yang relevan, jangan commit secret, dan ikuti gaya commit yang ada.
- Jangan membuat file dokumentasi baru tanpa diminta; langsung edit yang sudah ada bila memungkinkan. Dokumen pendukung saat ini ada di `docs/`.