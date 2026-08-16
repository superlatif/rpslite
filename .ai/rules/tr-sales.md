---
paths:
  - 'app/Filament/Resources/TrSales/**'
  - app/Filament/Resources/TrSales/TrSaleResource.php
---

# Tr Sales

## Penjualan = TrHeader type SALE, stok dikurangi
Sama dengan Pembelian tapi trr_type=SALE, nomor PJ-XXXXXX, customer_id wajib jika kredit, dan stok TbStock di-decrement setelah validasi kecukupan stok (throw ValidationException per baris detail). hpp_at_transaction diisi dari harga_pokok saat transaksi.

## Cetak struk penjualan via record action
Tombol "Cetak Struk" per baris di tabel Penjualan adalah record action ActionGroup (name cetakStruk, Heroicon::OutlinedPrinter, openUrlInNewTab) diposisikan RecordActionsPosition::BeforeColumns. URL-nya menunjuk ke route filament.admin.penjualan.struk (GET penjualan/{trHeader}/struk) yang didaftarkan via ->authenticatedRoutes() di AdminPanelProvider, bukan routes/web.php (auth middleware Laravel akan redirect ke route('login') yang tidak ada; Filament memakai route login filament.admin.auth.login). Closure route mengabort 404 bila trr_type !== 'SALE', eager-loads customer + details.stock, lalu return view('struk.penjualan'). Struk hanya boleh dipanggil untuk header SALE. Saat mengetes route via HTTP harus login sebagai user FilamentUser (User::factory()->create() + actingAs); tabel memakai filter trs_date default now() sehingga record yang ditest lewat Livewire/table harus trs_date hari ini.

## Retur penjualan wajib menunjuk nomor faktur jual dan dibatasi remaining_amount
Setiap retur penjualan (SALE_RET) harus memilih No. Faktur Jual (source_sale_id) berupa SALE kredit terbuka (trr_type=SALE, trs_type=1, remaining_amount>0) milik customer terpilih. Nilai retur tidak boleh melebihi remaining_amount faktur tersebut. Membuat retur otomatis menaikkan paid_amount dan menurunkan remaining_amount faktur sumber. Header retur selalu diisi paid_amount=total walau kredit (remaining=0). Retur tunai berarti pemilik toko mengembalikan uang tunai.
