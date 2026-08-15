---
paths:
  - 'app/Filament/Pages/**'
---

# Pages

## Date traps in report queries
SQLite stores trs_date as datetime string ("2026-08-13 00:00:00") even for `date` columns: use whereDate() for range comparisons, never whereBetween on the raw column. Carbon diffInDays() returns a signed float (negative when the later date is the receiver): wrap with abs() before bucketing/aging.

## Cetak & export Excel laporan via header action
Header action Cetak & Export Excel pada halaman laporan memakai URL-based Action (->url()) yang menunjuk ke route di panel authenticatedRoutes (AdminPanelProvider), bukan action closure Livewire: route GET /laporan-kartu-stok/cetak mengembalikan view cetak (window.print() on load, toolbar tersembunyi saat print), dan /laporan-kartu-stok/export men-stream CSV dengan BOM UTF-8 ("\xEF\xBB\xBF") agar Excel membaca UTF-8 dengan benar. Gunakan ->disabled(fn () => blank(...)) saat filter belum dipilih. Pola sama dengan struk penjualan: route print/export laporan harus didaftarkan lewat ->authenticatedRoutes().
