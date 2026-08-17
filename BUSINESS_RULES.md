# Business Rules — RPS Lite

---

## 1. Transaksi & Nomor

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-TRX-001** | Setiap transaksi wajib memiliki nomor unik format `PREFIX-XXXXXX` (6 digit), di-generate otomatis dari nomor terakhir dengan prefix yang sama. |
| **BR-TRX-002** | Prefix per tipe: `PB` (Pembelian), `RPB` (Retur Pembelian), `PJ` (Penjualan), `RPJ` (Retur Penjualan), `OP` (Stok Opname). |
| **BR-TRX-003** | `trr_type` (enum) menentukan perilaku stok & keuangan: `PURCHASE`, `PURCHASE_RET`, `SALE`, `SALE_RET`, `OPNAME`. |
| **BR-TRX-004** | Semua transaksi disimpan **atomik** dalam satu `DB::transaction()` (header + detail + update stok). |
| **BR-TRX-005** | Tidak boleh ada dua detail dengan `stock_id` yang sama dalam satu header transaksi. |

---

## 2. Stok & Inventori

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-STK-001** | Arah stok ditentukan dari `trr_type`, **bukan** dari tanda `qty` (kecuali OPNAME). |
| **BR-STK-002** | **Stok Masuk (+)**: `PURCHASE`, `SALE_RET`, `OPNAME` (surplus). |
| **BR-STK-003** | **Stok Keluar (−)**: `SALE`, `PURCHASE_RET`, `OPNAME` (shortage). |
| **BR-STK-004** | `OPNAME` adalah satu-satunya tipe yang menyimpan `qty` **signed** (positif = surplus, negatif = shortage). Stok diset langsung ke nilai fisik (`stock = stok_fisik`). |
| **BR-STK-005** | Validasi stok pada `SALE` & `PURCHASE_RET`: stok setelah dikurangi **tidak boleh negatif**. Jika gagal → rollback seluruh transaksi. |
| **BR-STK-006** | `PURCHASE` & `SALE_RET` memicu `TbStock::recalculateHpp()` (rata-rata tertimbang). `PURCHASE_RET`, `SALE`, `OPNAME` **tidak** memicu recalculate HPP. |
| **BR-STK-007** | `harga_pokok` (HPP running) = `SUM(subtotal_pembelian) / SUM(qty_pembelian)` dari **semua** detail `PURCHASE` historis. |
| **BR-STK-008** | Jika barang belum pernah dibeli (`hasNoPurchaseTransactions()`): `harga_pokok = harga_beli` (di-set via event `saving`). |
| **BR-STK-009** | `hpp_at_transaction` di `tr_details` adalah **snapshot immutable** HPP saat transaksi terjadi — tidak boleh diubah setelah posting. Digunakan untuk hitung laba per transaksi. |
| **BR-STK-010** | Kode barang (`code`) auto-generate 8 digit unique. Tidak bisa diubah user. |

---

## 3. Harga & Pembayaran

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-PRC-001** | `trs_type`: `0` = Tunai, `1` = Kredit. |
| **BR-PRC-002** | **Tunai**: `paid_amount = total_amount`, `remaining_amount = 0`. |
| **BR-PRC-003** | **Kredit**: `paid_amount = 0`, `remaining_amount = total_amount` (piutang/hutang). |
| **BR-PRC-004** | `customer_id` wajib diisi **hanya jika** `trs_type = 1` (kredit) pada `SALE`/`SALE_RET`. |
| **BR-PRC-005** | `supplier_id` wajib diisi **hanya jika** `trs_type = 1` (kredit) pada `PURCHASE`/`PURCHASE_RET`. |
| **BR-PRC-006** | Harga default detail: `PURCHASE`/`PURCHASE_RET` → `harga_beli`; `SALE`/`SALE_RET` → `harga_jual`. Bisa di-override manual. |
| **BR-PRC-007** | `subtotal` detail = `qty × unit_price` (dua desimal). `total_amount` header = Σ `subtotal`. |

---

## 4. Piutang Customer (Accounts Receivable)

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-AR-001** | Piutang bersih customer = `Customer::netReceivable()` = `receivableBalance (SALE kredit)` − `returnCredit (SALE_RET kredit)`. |
| **BR-AR-002** | `receivableBalance` = Σ `remaining_amount` dari header `SALE` dengan `trs_type = 1`. |
| **BR-AR-003** | `returnCredit` = Σ `remaining_amount` dari header `SALE_RET` dengan `trs_type = 1` (retur kredit mengurangi piutang). |
| **BR-AR-004** | Maksimal pembayaran angsuran = `netReceivable()` (tidak boleh overpay). |
| **BR-AR-005** | **Alokasi Proporsional** (`Customer::applyPayment()`):<br>• Pembayaran dibagi ke SALE kredit & SALE_RET kredit sebanding porsi masing-masing terhadap net receivable.<br>• Bagian SALE: dialokasikan **FIFO** (invoice terlama dulu). Jika invoice dipilih manual → invoice itu didahulukan, kelebihan mengalir ke invoice lain.<br>• Bagian SALE_RET: dialokasikan FIFO ke transaksi `SALE_RET`. |
| **BR-AR-006** | **Rollback Alokasi** (`Customer::reversePayment()`): saat angsuran dihapus, kembalikan `remaining_amount` ke invoice SALE & SALE_RET sesuai proporsi semula (kebalikan applyPayment). |
| **BR-AR-007** | `CustomerPayment` mencatat: `customer_id` (wajib), `tr_header_id` (nullable, untuk tracking per invoice), `payment_date`, `amount`. |
| **BR-AR-008** | Retur Penjualan Kredit (`SALE_RET` dengan `trs_type = 1`): `remaining_amount = total_amount`, bersifat **kredit bagi customer** (mengurangi piutang). |

---

## 5. Hutang Supplier (Accounts Payable)

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-AP-001** | Hutang bersih supplier = `Supplier::netPayable()` = `payableBalance (PURCHASE kredit)` − `returnCredit (PURCHASE_RET kredit)`. |
| **BR-AP-002** | `payableBalance` = Σ `remaining_amount` dari header `PURCHASE` dengan `trs_type = 1`. |
| **BR-AP-003** | `returnCredit` = Σ `remaining_amount` dari header `PURCHASE_RET` dengan `trs_type = 1` (retur kredit mengurangi hutang). |
| **BR-AP-004** | Alokasi pembayaran supplier mirip customer: proporsional + FIFO (implementasi di `Supplier::applyPayment()` / `reversePayment()`). |
| **BR-AP-005** | *Catatan*: Resource `SupplierPayments` sudah ada (model & migration), tapi belum diimplementasikan penuh di UI. |

---

## 6. Retur (Returns)

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-RET-001** | **Retur Pembelian** (`PURCHASE_RET`): Stok berkurang, validasi stok cukup. Tidak mempengaruhi HPP. |
| **BR-RET-002** | **Retur Penjualan** (`SALE_RET`): Stok bertambah, snapshot HPP. |
| **BR-RET-003** | Retur Kredit (`trs_type = 1`): Menciptakan `remaining_amount` yang **mengurangi** piutang (SALE_RET) atau hutang (PURCHASE_RET). |
| **BR-RET-004** | Retur Tunai (`trs_type = 0`): Dianggap uang dikembalikan tunai, `remaining_amount = 0`, tidak mempengaruhi piutang/hutang. |
| **BR-RET-005** | Retur Penjualan wajib memilih `source_sale_id` (invoice jual kredit asal dengan `remaining_amount > 0`) jika kredit. |
| **BR-RET-006** | Retur Pembelian wajib memilih `source_purchase_id` (invoice beli kredit asal dengan `remaining_amount > 0`) jika kredit. |

---

## 7. Stok Opname (Stock Take)

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-OPN-001** | Hanya tanggal di header, `total_amount = paid_amount = remaining_amount = 0`. |
| **BR-OPN-002** | Detail: input **Stok Fisik** (≥ 0). Selisih = `stok_fisik − stok_sistem` (otomatis). |
| **BR-OPN-003** | `qty` tersimpan **signed**: positif = surplus, negatif = shortage. |
| **BR-OPN-004** | Stok diset **langsung** ke nilai fisik: `tb_stocks.stock = stok_fisik` (bukan increment/decrement). |
| **BR-OPN-005** | Tidak memicu `recalculateHpp()`. `hpp_at_transaction` = snapshot `harga_pokok` saat opname. |

---

## 8. Laporan (Reports)

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-RPT-001** | Semua laporan berbasis **periode tanggal** (`trs_date`) pakai `whereDate()` (SQLite simpan datetime string). |
| **BR-RPT-002** | **Laporan Penjualan**: Group by barang / customer, tampil qty, total, laba (unit_price − hpp_at_transaction) × qty. |
| **BR-RPT-003** | **Kartu Stok**: Mutasi detail per barang (masuk/keluar per transaksi), running balance. Urut tanggal + tipe transaksi. |
| **BR-RPT-004** | **Piutang Aging**: Bagi piutang per customer ke bucket: Current (≤0 hari), 1-30, 31-60, 61-90, 90+ hari dari `trs_date` invoice. |
| **BR-RPT-005** | **Kartu Piutang**: Mutasi per customer (invoice, bayar, retur, sisa), running balance. |
| **BR-RPT-006** | **Nilai Persediaan**: `stock × harga_pokok` per barang, subtotal per kategori, grand total. |
| **BR-RPT-007** | Cetak (view + auto-print) & Export CSV (UTF-8 BOM `\xEF\xBB\xBF`) via route GET di `authenticatedRoutes()`. Tombol disabled sampai filter terisi. |

---

## 9. Dashboard & Widgets

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-DSH-001** | `SalesStatsOverview` & `SalesTrendChart` filter `trr_type = 'SALE'` only. |
| **BR-DSH-002** | Laba kotor = Σ `(unit_price − hpp_at_transaction) × qty` dari detail `SALE`. |
| **BR-DSH-003** | `InventoryStatsOverview`: total item, total qty stok, total nilai (`stock × harga_pokok`). |
| **BR-DSH-004** | `LowStockTable`: barang dengan `stock <= threshold` (default 10, configurable). |
| **BR-DSH-005** | `StockValueByCategoryChart`: nilai persediaan per kategori (join `tb_cates`). |

---

## 10. Master Data

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-MST-001** | Kategori (`TbCate`): tidak bisa dihapus jika masih ada barang (`restrictOnDelete`). |
| **BR-MST-002** | Barang (`TbStock`): tidak bisa dihapus jika sudah ada di `tr_details` (`restrictOnDelete`). Gambar dihapus otomatis dari storage saat delete model. |
| **BR-MST-003** | Customer/Supplier: tidak bisa dihapus jika masih ada transaksi header (`nullOnDelete` → FK jadi NULL, data transaksi tetap utuh). |
| **BR-MST-004** | `satuan` default `PCS`, max 15 karakter. |
| **BR-MST-005** | `descr` (nama) max 30 untuk kategori/customer/supplier, 50 untuk barang. |

---

## 11. Cetak Struk Penjualan

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-PRT-001** | Route: `GET /penjualan/{trHeader}/struk` (hanya untuk `trr_type = SALE`, selain itu 404). |
| **BR-PRT-002** | View: `resources/views/struk/penjualan.blade.php` (thermal 80mm, auto-print `window.print()`). |
| **BR-PRT-003** | Konten: "Yth" customer (nama/alamat/phone), tanggal, nomor, **jenis bayar (TUNAI/KREDIT)**, **sisa piutang** jika kredit & belum lunas, tabel barang (qty, harga, subtotal), grand total. |
| **BR-PRT-004** | Action "Cetak Struk" di tabel Penjualan → buka tab baru (`target="_blank"`). |

---

## 12. Integritas & Validasi Sistem

| Rule ID | Deskripsi |
|---------|-----------|
| **BR-SYS-001** | Semua uang pakai `decimal(15,2)` → cast `decimal:2` di Model. |
| **BR-SYS-002** | Tanggal pakai `date` cast. Query range pakai `whereDate()`. |
| **BR-SYS-003** | `hpp_at_transaction` **immutable** — tidak ada UI/edit untuk mengubahnya setelah transaksi dibuat. |
| **BR-SYS-004** | `trs_number` unique global (bukan per tipe). |
| **BR-SYS-005** | Soft delete **tidak** dipakai (hard delete dengan FK constraint). |
| **BR-SYS-006** | User Filament: `canAccessPanel() = true` untuk semua user (single role). |
| **BR-SYS-007** | Route custom (cetak/export) pakai middleware `auth` → redirect ke `filament.admin.auth.login`. |

---

## 13. Matrix Ringkasan Transaksi

| Tipe | trr_type | Prefix | Stok | HPP | Piutang/Hutang | source_ref |
|------|----------|--------|------|-----|----------------|------------|
| Pembelian | PURCHASE | PB- | +qty | recalc | Hutang (jika kredit) | - |
| Retur Beli | PURCHASE_RET | RPB- | −qty | no | Kurangi hutang (jika kredit) | source_purchase_id |
| Penjualan | SALE | PJ- | −qty | snapshot | Piutang (jika kredit) | - |
| Retur Jual | SALE_RET | RPJ- | +qty | snapshot | Kurangi piutang (jika kredit) | source_sale_id |
| Opname | OPNAME | OP- | set fisik | no | - | - |

---

## 14. Referensi Implementasi

| Rule Area | File Kunci |
|-----------|------------|
| Stok & HPP | `app/Models/TbStock.php:38-54` |
| Piutang Customer | `app/Models/Customer.php:18-154` |
| Hutang Supplier | `app/Models/Supplier.php:21-157` |
| Header Transaksi | `app/Models/TrHeader.php:66-87` (accessors) |
| Simpan Pembelian | `app/Filament/Resources/TrPurchases/Pages/ListTrPurchases.php` |
| Simpan Penjualan | `app/Filament/Resources/TrSales/Pages/ListTrSales.php` |
| Simpan Retur Beli | `app/Filament/Resources/TrPurchaseReturns/Pages/ListTrPurchaseReturns.php` |
| Simpan Retur Jual | `app/Filament/Resources/TrSaleReturns/Pages/ListTrSaleReturns.php` |
| Simpan Opname | `app/Filament/Resources/TrOpnames/Pages/ListTrOpnames.php` |
| Angsuran Customer | `app/Filament/Resources/CustomerPayments/Pages/ListCustomerPayments.php` |
| Cetak Struk | `app/Filament/Resources/TrSales/Tables/TrSalesTable.php`, `AdminPanelProvider.php` |

---

*Dokumen ini merupakan konsolidasi business rules dari codebase RPS Lite. Update saat logika berubah.*