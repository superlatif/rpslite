# UI / UX Specification — RPS Lite

---

## 1. Design System

| Aspect | Specification |
|--------|---------------|
| **Framework** | Filament 5 (admin panel) + Tailwind CSS 4 |
| **Color Scheme** | Filament default (Indigo primary), Dark mode supported |
| **Typography** | System font stack (Inter via Tailwind), responsive scaling |
| **Spacing** | Tailwind spacing scale (4px base) |
| **Icons** | Heroicons (via `Filament\Support\Icons\Heroicon`) |
| **Components** | Filament Forms, Tables, Infolists, Schemas (Grid, Section, Group) |
| **Responsive** | Mobile-first, sidebar collapsible, table horizontal scroll on mobile |

---

## 2. Layout Structure

```
┌─────────────────────────────────────────────────────────────┐
│  Top Bar: Logo | Search | Notifications | User Menu        │
├──────────────┬──────────────────────────────────────────────┤
│              │                                              │
│  Sidebar     │  Main Content                                │
│  (Navigation)│                                              │
│              │  ┌────────────────────────────────────────┐  │
│  - Tabel     │  │ Page Header: Title + Header Actions    │  │
│  - Transaksi │  ├────────────────────────────────────────┤  │
│  - Laporan   │  │                                        │  │
│  - Dashboard │  │  Content Area:                         │  │
│              │  │  - Resource: Table + Modal Forms       │  │
│              │  │  - Page (Laporan): Filter Bar + Table  │  │
│              │  │  - Dashboard: Widgets Grid             │  │
│              │  │                                        │  │
│              │  └────────────────────────────────────────┘  │
└──────────────┴──────────────────────────────────────────────┘
```

**Navigation Groups** (sidebar):
- **Tabel** (Master Data): Kategori, Barang, Supplier, Customer
- **Transaksi**: Pembelian, Retur Pembelian, Penjualan, Retur Penjualan, Angsuran Customer, Stok Opname
- **Laporan**: Penjualan, Kartu Stok, Piutang Aging, Kartu Piutang, Nilai Persediaan

---

## 3. Resource UI Pattern (Transaksi & Master)

### 3.1 List Page (Default View)
- **Table** dengan kolom sesuai resource
- **Header Actions**: CreateAction (modal), Export (jika ada), Filter (built-in)
- **Row Actions**: View, Edit (modal), Delete, Print/Struk (khusus Penjualan)
- **Bulk Actions**: Delete (dengan SafeDeleteAction), Export
- **Filters**: Sidebar filter (date range, status, customer/supplier, dll.)
- **Search**: Global search di top bar + column search di table header

### 3.2 Modal Form (Create/Edit)
- **Trigger**: Header Action "Tambah" / Row Action "Edit"
- **Size**: `max-w-4xl` (lebar 896px) untuk transaksi, `max-w-2xl` untuk master
- **Layout**: `Grid` + `Section` + `Group`
- **Repeater** untuk detail transaksi (barang, qty, harga, subtotal)
- **Live Updates**: `->live()` / `->live(onBlur: true)` untuk kalkulasi real-time
- **Conditional Fields**: `->visible()`, `->disabled()`, `->dehydrated()` berdasarkan field lain
- **Validation**: Inline di form, `required`, `numeric`, `min`, `unique`, custom closure

### 3.3 Form Components per Resource

| Resource | Key Form Components |
|----------|---------------------|
| **TbStock** | TextInput (code read-only, auto), TextInput descr, Select satuan, TextInput harga_beli/jual (decimal), Select kategori, FileUpload gambar |
| **Customer/Supplier** | TextInput descr, TextInput alamat, TextInput phone |
| **TrPurchases/TrSales** | DatePicker tanggal, Select customer/supplier (createOptionForm), Select trs_type (disabled jika customer/supplier kosong), Repeater detail (Select barang → auto-harga, qty, harga, subtotal read-only), Grand Total read-only |
| **TrPurchaseReturns/TrSaleReturns** | Sama + Select source_purchase_id/source_sale_id (filter remaining_amount > 0, live update saat customer/supplier berubah) |
| **TrOpnames** | DatePicker, Repeater (Select barang → tampil stok sistem read-only, TextInput stok fisik, selisih auto) |
| **CustomerPayments** | Select customer, Select invoice SALE kredit (sisa > 0), DatePicker, TextInput amount (max = netReceivable) |

---

## 4. Laporan Pages (Filament Pages)

### 4.1 Structure
```
Page (implements HasTable)
├── getHeaderActions(): Generate Action (modal filter)
├── content(Schema): EmbeddedTable::make()
└── Properties: $date_from, $date_to, $customer_id, $stock_id, etc.
```

### 4.2 Filter Modal (Generate Action)
- Fields: DatePicker range, Select customer/supplier/barang (searchable, preload)
- Submit → simpan ke property Livewire → `resetTable()` → reload data

### 4.3 Table (EmbeddedTable)
- Columns: sesuai laporan (lihat tabel di bawah)
- No pagination (semua data) atau pagination large
- Totals row di footer (jika supported)
- Header Actions: Cetak (route GET view), Export CSV (route GET stream)

### 4.4 Cetak / Export
- **Route**: GET di `AdminPanelProvider::authenticatedRoutes()`
- **Cetak**: Return Blade view → auto-print via `window.print()` + `@media print` CSS
- **Export**: Stream CSV dengan BOM UTF-8 (`\xEF\xBB\xBF`), header kolom lokal (Indonesia)

---

## 5. Dashboard Widgets

| Widget | Type | Layout | Data |
|--------|------|--------|------|
| **SalesStatsOverview** | StatsOverviewWidget | 4 cards horizontal | Total Penjualan, Transaksi, Rata-rata, Piutang |
| **SalesTrendChart** | ChartWidget (Line) | Full width | 30 hari / 12 bulan penjualan |
| **InventoryStatsOverview** | StatsOverviewWidget | 3 cards | Total Item, Total Stok, Nilai Persediaan |
| **LowStockTable** | TableWidget | Below stats | Barang stok ≤ 10 (qty, harga_pokok, nilai) |
| **StockValueByCategoryChart** | ChartWidget (Pie/Bar) | Half width | Nilai persediaan per kategori |

**Widget Grid**: 2 kolom desktop, 1 kolom mobile (Filament default responsive)

---

## 6. Key UI Components Detail

### 6.1 Repeater Transaksi (Detail Barang)

```
┌─────────────────────────────────────────────────────────────┐
│ Detail Penjualan                                    [+Tambah]│
├──────────────┬────────┬───────────┬─────────────────────────┤
│ Nama Barang  │ Qty    │ Harga     │ Jumlah                  │
├──────────────┼────────┼───────────┼─────────────────────────┤
│ ▼ Select     │ [  1 ] │ [ 5000 ]  │ [ 5.000 ] (read-only)  │
│    (search)  │        │           │                         │
├──────────────┼────────┼───────────┼─────────────────────────┤
│ ▼ Select     │ [  2 ] │ [15000 ]  │ [30.000 ]              │
├──────────────┴────────┴───────────┴─────────────────────────┤
│ Grand Total: [ 35.000 ] (read-only, prefix, right-align)    │
└─────────────────────────────────────────────────────────────┘
```

**Behavior**:
- Select barang → `afterStateUpdated` set `unit_price` dari `harga_jual`/`harga_beli` + hitung subtotal
- Qty/Harga change (onBlur) → hitung subtotal → hitung grand total
- Subtotal & Grand Total: `mask(RawJs::make('$money($input)'))` + `stripCharacters(',')` → format rupiah
- `dehydrated()` pada read-only fields agar ikut tersimpan

### 6.2 Customer/Supplier Select dengan Create Option

```
Select Customer ▼
├── Search existing...
├── [Create New Customer] → Modal Form (TbCustomerForm::components())
└── After create → auto-select
```

### 6.3 Conditional Payment Type (trs_type)

```
Customer: [Pelanggan A ▼]          ← live()
Jenis Bayar: [Tunai ▼]             ← disabled jika customer kosong
                                      dehydrated hanya jika customer terisi
                                      helperText: "Pilih customer terlebih dahulu"
```

### 6.4 Retur Source Invoice Select

```
No. Faktur Jual ▼
├── PJ-000001 — sisa 500.000
├── PJ-000003 — sisa 250.000
└── helperText: "Pilih faktur jual kredit yang masih memiliki sisa tagihan."
```
- Options filter: `where('remaining_amount', '>', 0)` + `where('customer_id', $get('customer_id'))`
- Live update saat customer berubah → reset source_sale_id

---

## 7. Table Columns per Resource

### 7.1 Master Data

| Resource | Columns |
|----------|---------|
| **TbCate** | descr, created_at |
| **TbStock** | code, descr, satuan, harga_beli, harga_jual, harga_pokok, stock, kategori, updated_at |
| **Customer** | descr, alamat, phone, piutang_bersih (netReceivable), created_at |
| **Supplier** | descr, alamat, phone, hutang_bersih (netPayable), created_at |

### 7.2 Transaksi

| Resource | Columns |
|----------|---------|
| **TrPurchases** | trs_number, trs_date, supplier, trs_type (badge), total_amount, paid_amount, remaining_amount, created_at |
| **TrSales** | trs_number, trs_date, customer, trs_type, total_amount, paid_amount, remaining_amount, **Action: Cetak Struk** |
| **TrPurchaseReturns** | trs_number, trs_date, supplier, trs_type, total_amount, source_purchase |
| **TrSaleReturns** | trs_number, trs_date, customer, trs_type, total_amount, source_sale |
| **TrOpnames** | trs_number, trs_date, total_items (count detail), created_at |

### 7.3 Angsuran

| Resource | Columns |
|----------|---------|
| **CustomerPayments** | payment_date, customer, tr_header (invoice), amount, created_at |
| **SupplierPayments** | payment_date, supplier, tr_header, amount, created_at |

---

## 8. Laporan Table Columns

| Laporan | Columns |
|---------|---------|
| **Penjualan (per Barang)** | kode, nama_barang, qty_terjual, total_penjualan, total_hpp, laba_kotor, margin_% |
| **Penjualan (per Customer)** | customer, qty, total_penjualan, total_bayar, sisa_piutang |
| **Kartu Stok** | tanggal, nomor, tipe, keterangan (supplier/customer), qty_masuk, qty_keluar, saldo, hpp, nilai |
| **Piutang Aging** | customer, current, 1-30, 31-60, 61-90, 90+, total |
| **Kartu Piutang** | tanggal, nomor, tipe (Jual/Retur/Bayar), keterangan, debit, kredit, saldo |
| **Nilai Persediaan** | kategori, kode, nama, satuan, stok, harga_pokok, nilai, persen |

---

## 9. Struk Penjualan (Thermal 80mm)

```
┌────────────────────────────────────┐
│         TOKO RPS LITE              │
│   Jl. Contoh No. 123, Jakarta      │
│   Telp: 0812-3456-7890             │
├────────────────────────────────────┤
│ STRUK PENJUALAN                    │
│ No: PJ-000001                      │
│ Tgl: 17/08/2026 14:30              │
│ Kasir: Admin                       │
├────────────────────────────────────┤
│ Yth: Budi Santoso                  │
│ Jl. Merdeka No. 10                 │
│ Telp: 0812-3333-4444               │
│ Bayar: KREDIT                      │
│ Sisa Piutang: Rp 1.500.000         │
├────────────────────────────────────┤
│ Barang            Qty   Harga  Sub │
│ Indomie Goreng     2   3.500  7.000│
│ Aqua 600ml         1   3.000  3.000│
├────────────────────────────────────┤
│ TOTAL:                    10.000   │
├────────────────────────────────────┤
│      Terima Kasih Atas             │
│      Kunjungan Anda                │
└────────────────────────────────────┘
```

**Tech**: `resources/views/struk/penjualan.blade.php`, auto-print via `<script>window.print()</script>`, `@media print` hide buttons.

---

## 10. Responsive Behavior

| Breakpoint | Sidebar | Table | Modal Form |
|------------|---------|-------|------------|
| **≥ 1024px (lg)** | Expanded | Full columns | max-w-4xl |
| **768-1023px (md)** | Collapsible (icon only) | Horizontal scroll | max-w-full mx-4 |
| **< 768px (sm)** | Drawer (hamburger) | Horizontal scroll, stacked cards | Full screen |

**Table on Mobile**: Filament `Table::make()->paginated([10, 25, 50])` + horizontal scroll. Card view alternative untuk detail.

---

## 11. Dark Mode

- Filament 5 built-in dark mode (OS preference + manual toggle di user menu)
- Tailwind `dark:` variants sudah aktif
- Custom colors: gunakan `primary-500`/`primary-600` untuk brand, `gray-100`/`gray-800` untuk background
- Struk cetak: force light mode (`@media print { color-scheme: light }`)

---

## 12. Accessibility (a11y)

| Requirement | Implementation |
|-------------|----------------|
| **Labels** | Semua input punya `<label>` (Filament default) |
| **Focus** | Visible focus ring (Tailwind `focus:ring-2 focus:ring-primary-500`) |
| **Contrast** | WCAG AA compliant (Filament default palette) |
| **Keyboard** | Modal trap focus, ESC close, Tab navigation |
| **Screen Reader** | `aria-label` pada icon buttons, table headers scope |
| **Language** | Indonesian (locale `id`), format number/date lokal |

---

## 13. Loading & Empty States

| State | UI |
|-------|----|
| **Table loading** | Skeleton rows (Filament default) |
| **Empty table** | Illustration + "Belum ada data" + CTA "Tambah" |
| **Modal submitting** | Button disabled + spinner + "Menyimpan..." |
| **Report generating** | Loading overlay pada table + "Memuat laporan..." |
| **Print/Export** | Button disabled sampai filter valid |

---

## 14. Notification & Feedback

| Event | Notification |
|-------|--------------|
| **Create success** | `Notification::make()->success()->title('Disimpan')->send()` |
| **Create error** | `Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send()` |
| **Delete success** | `Notification::make()->success()->title('Dihapus')->send()` |
| **Validation error** | Inline field error (Filament default) |
| **Stock tidak cukup** | Custom validation message di `CreateAction::using()` → `Notification::danger()` |
| **Overpay angsuran** | Validation di form + notification |

---

## 15. Custom CSS (resources/css/app.css)

```css
/* Struk print optimization */
@media print {
  .no-print { display: none !important; }
  body { font-size: 12px; line-height: 1.3; }
  .struk-table { font-size: 11px; }
}

/* Tailwind custom utilities */
@utility text-balance {
  text-wrap: balance;
}

/* Low stock badge */
.badge-low-stock {
  @apply bg-red-100 text-red-800 px-2 py-0.5 rounded-full text-xs font-medium;
}
```

---

## 16. File Structure (View & Assets)

```
resources/
├── views/
│   ├── struk/
│   │   └── penjualan.blade.php      # Struk thermal 80mm
│   └── laporan/
│       └── kartu-stok.blade.php     # Cetak kartu stok (A4 landscape)
├── css/
│   └── app.css                      # Tailwind entry + custom utilities
└── js/
    └── app.js                       # Alpine.js (Filament default)
```

---

## 17. UX Checklist untuk Fitur Baru

- [ ] Ikut pola Resource: `Resource.php` + `Schemas/Form.php` + `Tables/Table.php` + `Pages/List.php`
- [ ] Modal form (tidak halaman Create/Edit terpisah)
- [ ] `CreateAction::make()->using()->databaseTransaction()`
- [ ] Repeater detail pakai `TableColumn` layout
- [ ] Live calculation untuk subtotal/grand total
- [ ] Conditional fields (disabled/visible/dehydrated)
- [ ] Validation inline + notification feedback
- [ ] Table dengan filter, search, sort, pagination
- [ ] Row actions: View, Edit, Delete (SafeDeleteAction), Print (jika perlu)
- [ ] Header actions: Create, Export (jika laporan)
- [ ] Dark mode compatible
- [ ] Responsive tested (mobile/tablet/desktop)
- [ ] Accessibility: labels, focus, contrast

---

*Dokumen UI/UX ini mengikuti konvensi Filament 5 + Tailwind 4 di codebase RPS Lite.*