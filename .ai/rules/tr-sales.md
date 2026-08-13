---
paths:
  - 'app/Filament/Resources/TrSales/**'
---

# Tr Sales

## Penjualan = TrHeader type SALE, stok dikurangi
Sama dengan Pembelian tapi trr_type=SALE, nomor PJ-XXXXXX, customer_id wajib jika kredit, dan stok TbStock di-decrement setelah validasi kecukupan stok (throw ValidationException per baris detail). hpp_at_transaction diisi dari harga_pokok saat transaksi.
