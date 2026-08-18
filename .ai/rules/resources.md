---
paths:
  - 'app/Filament/Resources/**'
---

# Resources

## Jasa disatukan di tb_stocks tanpa tracking stok
Jasa disimpan di tabel tb_stocks memakai kolom boolean is_jasa (model TbStock + scopes barang()/jasa()). Jasa tetap muncul di penjualan/pembelian/retur bersama barang, tapi jangan pernah increment/decrement/cek kolom stock untuk record is_jasa=true (skip loop inventory di ListTrSales/ListTrPurchases/ListTrSaleReturns/ListTrPurchaseReturns). Jasa DILARANG di opname (form sudah filter barang(), ListTrOpnames juga throw ValidationException) dan dikeluarkan dari laporan kartu stok, nilai persediaan, serta widget inventori (LowStockTable, InventoryStatsOverview, StockValueByCategoryChart) via scope barang(). harga_pokok jasa tetap disinkron = harga_beli saat tanpa pembelian.
