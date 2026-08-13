---
paths:
  - 'app/Filament/Resources/CustomerPayments/**'
---

# Customer Payments

## Angsuran meng-update paid/remaining header
CustomerPaymentResource menyimpan ke tabel customer_payments. Saat create (CreateAction->using + databaseTransaction), header SALE terkait di-increment paid_amount dan di-decrement remaining_amount; tolak jika amount > sisa tagihan. Aksi Hapus di tabel membalik kedua angka tersebut sebelum delete. Select tr_header_id hanya menampilkan SALE dengan remaining_amount > 0.
