---
paths:
  - 'app/Filament/Resources/TrSales/**'
---

# Tr Sales

## Penjualan = TrHeader type SALE, stok dikurangi
Sama dengan Pembelian tapi trr_type=SALE, nomor PJ-XXXXXX, customer_id wajib jika kredit, dan stok TbStock di-decrement setelah validasi kecukupan stok (throw ValidationException per baris detail). hpp_at_transaction diisi dari harga_pokok saat transaksi.

## Cetak struk penjualan via record action
Tombol "Cetak Struk" per baris di tabel Penjualan adalah record action ActionGroup (name cetakStruk, Heroicon::OutlinedPrinter, openUrlInNewTab) diposisikan RecordActionsPosition::BeforeColumns. URL-nya menunjuk ke route filament.admin.penjualan.struk (GET penjualan/{trHeader}/struk) yang didaftarkan via ->authenticatedRoutes() di AdminPanelProvider, bukan routes/web.php (auth middleware Laravel akan redirect ke route('login') yang tidak ada; Filament memakai route login filament.admin.auth.login). Closure route mengabort 404 bila trr_type !== 'SALE', eager-loads customer + details.stock, lalu return view('struk.penjualan'). Struk hanya boleh dipanggil untuk header SALE. Saat mengetes route via HTTP harus login sebagai user FilamentUser (User::factory()->create() + actingAs); tabel memakai filter trs_date default now() sehingga record yang ditest lewat Livewire/table harus trs_date hari ini.
