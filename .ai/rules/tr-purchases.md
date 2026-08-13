---
paths:
  - 'app/Filament/Resources/TrPurchases/**'
---

# Tr Purchases

## Pembelian = TrHeader type PURCHASE, stok ditambah
Resource Pembelian memakai model TrHeader yang difilter where('trr_type','PURCHASE') di getEloquentQuery(). Transaksi dibuat via CreateAction->using() (modal, bukan halaman terpisah) + databaseTransaction: nomor otomatis PB-XXXXXX, detail di-persist manual via header->details()->createMany(), lalu stok TbStock di-increment. Tunai: paid=total, remaining=0. Kredit: paid=0, remaining=total.
