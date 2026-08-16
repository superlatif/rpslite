---
paths:
  - 'app/Filament/Resources/SupplierPayments/**'
---

# Supplier Payments

## Angsuran meng-update paid/remaining header (cermin CustomerPayments)
Angsuran Supplier menyimpan ke tabel supplier_payments. Saat create (CreateAction->using + databaseTransaction), header PURCHASE terkait di-increment paid_amount dan di-decrement remaining_amount; tolak jika amount > sisa hutang. Aksi Hapus di tabel membalik kedua angka tersebut sebelum delete. Select tr_header_id hanya menampilkan PURCHASE dengan remaining_amount > 0.
