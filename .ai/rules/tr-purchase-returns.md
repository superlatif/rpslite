---
paths:
  - 'app/Filament/Resources/TrPurchaseReturns/**'
---

# Tr Purchase Returns

## Retur pembelian harus mereferensikan faktur beli kredit terbuka
Retur pembelian wajib memilih source_purchase_id = faktur PURCHASE kredit (trs_type=1, remaining_amount>0) milik supplier terpilih. Nilai retur tidak boleh melebihi remaining_amount faktur. Header retur selalu paid_amount=total dan remaining_amount=0 (untuk Tunai dan Kredit). Faktur sumber: paid_amount += total, remaining_amount -= total. Retur tunai dianggap supplier mengembalikan uang tunai ke toko; retur kredit mengurangi utang. Stok barang di-decrement pada retur pembelian (kebalikan pembelian).
