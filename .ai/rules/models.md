---
paths:
  - app/Models/TbStock.php
---

# Models

## harga_pokok auto-sync via saving event + weighted average on purchase
TbStock::saving event sets harga_pokok = harga_beli whenever the stock has no PURCHASE TrDetails (covers create & edit). Once purchase transactions exist, harga_pokok is app-managed: recalculateHpp() recomputes weighted-average HPP (SUM(subtotal)/SUM(qty) over all PURCHASE details) and is called after each purchase in ListTrPurchases::createPurchase. Do not expose harga_pokok in the stock form; it is read-only app data.
