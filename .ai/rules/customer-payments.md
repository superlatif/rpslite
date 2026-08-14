---
paths:
  - 'app/Filament/Resources/CustomerPayments/**'
---

# Customer Payments

## Angsuran meng-update paid/remaining header
CustomerPaymentResource menyimpan ke tabel customer_payments. Saat create (CreateAction->using + databaseTransaction), header SALE terkait di-increment paid_amount dan di-decrement remaining_amount; tolak jika amount > sisa tagihan. Aksi Hapus di tabel membalik kedua angka tersebut sebelum delete. Select tr_header_id hanya menampilkan SALE dengan remaining_amount > 0.

## Installments are net of sales returns
A credit SALE_RET header stores remaining_amount = total and acts as a customer credit that reduces piutang. Customer::netReceivable() = sum(SALE remaining) - sum(SALE_RET remaining) is the hard cap for any payment; never allow paying beyond it or the Piutang report goes negative. Use Customer::applyPayment()/reversePayment() to allocate payments FIFO across open credit SALE headers (linked payment -> single invoice; unlinked -> oldest first).
