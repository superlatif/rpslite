---
paths:
  - 'app/Filament/Resources/**/Tables/**'
---

# Tables

## Table headerActions read filter state via $livewire
To add Cetak/Export actions to a Resource table that honor current table filters, use `$table->headerActions([...])`. In the action `url()`/`disabled()` closures inject `$livewire` (the List page, implements HasFilters) and read filter state with `$livewire->getTableFilterState('filterName')`. SelectFilter state is `['value' => ...]`; a custom Filter with date fields is `['date_from' => ..., 'date_until' => ...]`. Route params must be passed explicitly.
