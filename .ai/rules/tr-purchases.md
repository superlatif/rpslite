---
paths:
  - 'app/Filament/Resources/TrPurchases/**'
---

# Tr Purchases

## Pembelian = TrHeader type PURCHASE, stok ditambah
Resource Pembelian memakai model TrHeader yang difilter where('trr_type','PURCHASE') di getEloquentQuery(). Transaksi dibuat via CreateAction->using() (modal, bukan halaman terpisah) + databaseTransaction: nomor otomatis PB-XXXXXX, detail di-persist manual via header->details()->createMany(), lalu stok TbStock di-increment. Tunai: paid=total, remaining=0. Kredit: paid=0, remaining=total.

## Pembelian selalu merekam jenis pembayaran Tunai/Kredit
Form pembelian wajib memilih supplier dan jenis pembayaran (trs_type Tunai/Kredit). Kredit => paid_amount=0, remaining_amount=total; Tunai => paid_amount=total, remaining_amount=0. Select trs_type hanya dehydrated saat supplier terisi.
