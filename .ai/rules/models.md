---
paths:
  - app/Models/TbStock.php
  - app/Models/Customer.php
---

# Models

## harga_pokok auto-sync via saving event + weighted average on purchase
TbStock::saving event sets harga_pokok = harga_beli whenever the stock has no PURCHASE TrDetails (covers create & edit). Once purchase transactions exist, harga_pokok is app-managed: recalculateHpp() recomputes weighted-average HPP (SUM(subtotal)/SUM(qty) over all PURCHASE details) and is called after each purchase in ListTrPurchases::createPurchase. Do not expose harga_pokok in the stock form; it is read-only app data.

## Angsuran mengonsumsi retur kredit secara proporsional
Customer::applyPayment()/reversePayment() kini mengalokasikan secara proporsional, bukan hanya ke SALE. Pembayaran P dengan posisi SALE=s dan SALE_RET=r (net n=s-r) mengurangi sisa SALE sebesar P*s/n dan sisa SALE_RET sebesar P*r/n, sehingga saat piutang bersih lunas kedua sisi mencapai 0. Bagian SALE dialokasikan FIFO (header ter-linked didahulukan, kelebihan mengalir ke invoice lain); bagian SALE_RET FIFO. reversePayment membalik proporsinya dari state saat ini.
