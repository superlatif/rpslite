---
paths:
  - 'app/Filament/Resources/TrOpnames/**'
---

# Tr Opnames

## OPNAME stores signed qty; stock set to physical count
Stok Opname (trr_type 'OPNAME') is the ONLY transaction type that stores signed qty in tr_details: positive = surplus (+stok), negative = shortage (-stok). qty = stok_fisik - stok_sistem. The opname create flow sets TbStock.stock directly to stok_fisik. Arah di kartu stok (LaporanKartuStokTable) untuk OPNAME diambil dari tanda qty (direction($trrType, $qty)), dan semua perhitungan kartu stok memakai abs(qty). Opname TIDAK memicu recalculateHpp().
