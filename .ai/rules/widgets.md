---
paths:
  - 'app/Filament/Widgets/**'
---

# Widgets

## Dashboard widgets query SALE headers only
SalesStatsOverview + SalesTrendChart both filter trr_type='SALE' (not SALE_RET). Tunai = trs_type 0, Kredit = 1. Laba kotor = sum((unit_price - hpp_at_transaction) * qty) joined on tr_headers. Use whereDate() for trs_date ranges (SQLite datetime string trap) and iterator_to_array() when expanding Carbon::range() into a date list.

## Inventory dashboard widgets query tb_stocks directly
Inventory widgets on the dashboard: InventoryStatsOverview (total items, SUM(stock) qty, SUM(stock*harga_pokok) value), LowStockTable (tb_stocks with stock<=5, badge Habis when 0), StockValueByCategoryChart (bar, leftJoin tb_cates grouped by category, COALESCE for 'Tanpa Kategori'). Test factories: TbStock's saving event overrides harga_pokok=harga_beli when no PURCHASE details exist, so tests should set harga_beli not harga_pokok.
