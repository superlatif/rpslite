---
paths:
  - 'app/Filament/**'
---

# Filament

## Summarizer::using() receives QueryBuilder, not EloquentBuilder
In Filament 5, `TextColumn::summarize(Summarizer::using(...))` passes a `Illuminate\Database\Query\Builder` to the closure, NOT an `Eloquent\Builder`. Type-hint `QueryBuilder` in the closure or you get a TypeError. Also, Eloquent accessors (e.g. `$record->debet`) are not DB columns, so you cannot `$query->get()->sum('debet')` — use SQL `SUM(CASE...)`/`where('trs_number','LIKE','PB%')` in the query instead.

## Semua operasi tulis multi-tabel wajib DB::transaction
Semua alur tulis yang menyentuh >1 tabel/baris (create penjualan/pembelian/retur/opname, pembayaran, hapus) harus dibungkus DB::transaction atau ->databaseTransaction() agar rollback penuh. Jangan pakai DeleteBulkAction::make() polos: ia menelan exception per-record sehingga tidak rollback — gunakan App\Filament\Actions\SafeDeleteBulkAction yang membungkus seluruh delete dalam DB::transaction dan membatalkan semuanya bila satu baris gagal.
