---
paths:
  - 'app/Filament/Resources/TrSaleReturns/**'
---

# Tr Sale Returns

## createSaleReturn memvalidasi faktur sumber; paid_amount retur selalu total
ListTrSaleReturns::createSaleReturn menolak retur tanpa source_sale_id, total > remaining_amount faktur sumber, atau faktur milik customer lain (ValidationException). Header SALE_RET dibuat paid_amount=total dan remaining_amount=0; faktur sumber di-increment paid_amount dan di-decrement remaining_amount. Form pakai Select source_sale_id (options SALE kredit terbuka per customer); error form pada field ini muncul sebagai mountedActions.0.data.source_sale_id karena implicit in rule, sedangkan ValidationException custom memakai key polos source_sale_id.
