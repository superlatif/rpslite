# Product Requirements Document (PRD) — RPS Lite

---

## 1. Ringkasan Produk

**RPS Lite** adalah sistem **Point of Sale (POS)** berbasis web untuk toko/retail skala kecil-menengah yang mencakup siklus penuh:
- **Pembelian** dari supplier (tunai/kredit)
- **Penjualan** ke customer (tunai/kredit)
- **Retur** pembelian & penjualan
- **Stok Opname** (penyesuaian fisik vs sistem)
- **Angsuran Piutang** customer dengan alokasi proporsional
- **Laporan** penjualan, stok, piutang, nilai persediaan
- **Dashboard** ringkasan & grafik tren

Dibangun dengan **Laravel 13**, **Filament 5** (admin panel), **SQLite**, **Pest** untuk testing.

---

## 2. Stakeholder & User Persona

| Role | Deskripsi | Akses Utama |
|------|-----------|-------------|
| **Owner / Manajer** | Pemilik toko, memantau omzet, laba, stok, piutang | Dashboard, Semua Laporan |
| **Kasir / Admin Penjualan** | Mencatat penjualan, retur jual, angsuran customer, cetak struk | Penjualan, Retur Penjualan, Angsuran Customer, Cetak Struk |
| **Gudang / Purchasing** | Mencatat pembelian, retur beli, stok opname, kelola barang | Pembelian, Retur Pembelian, Stok Opname, Data Barang |
| **Akunting / Finance** | Memantau piutang aging, kartu piutang, nilai persediaan | Laporan Piutang, Kartu Piutang, Nilai Persediaan |

---

## 3. Fitur Utama (Functional Requirements)

### 3.1 Data Master (Tabel)

| Modul | Entitas | Field Utama | Business Rule |
|-------|---------|-------------|---------------|
| **Kategori** | `TbCate` | `descr` (max 30) | Hanya nama kategori; tidak bisa dihapus jika masih dipakai barang |
| **Barang / Stok** | `TbStock` | `code` (auto 8-digit, unique), `descr`, `satuan` (default PCS), `harga_beli`, `harga_jual`, `harga_pokok` (HPP, auto), `stock` (qty integer), `gambar` (path file), `tb_cate_id` (FK nullable) | - `code` digenerate otomatis<br>- `harga_pokok` = `harga_beli` saat belum ada pembelian<br>- Setelah ada pembelian → HPP rata-rata tertimbang (`SUM(subtotal)/SUM(qty)`)<br>- Gambar disimpan di `storage/app/public/items/` |
| **Customer** | `Customer` | `descr`, `alamat`, `phone` | Piutang dihitung otomatis dari transaksi kredit |
| **Supplier** | `Supplier` | `descr`, `alamat`, `phone` | Hutang dicatat di header transaksi (belum ada resource pembayaran supplier terpisah) |

---

### 3.2 Transaksi (Transaksi)

Semua transaksi menggunakan **modal form** di halaman List (tidak ada halaman Create/Edit terpisah), disimpan atomik via `DB::transaction`.

#### 3.2.1 Pembelian (`PURCHASE`) — Nomor `PB-XXXXXX`
- **Header**: Tanggal, Supplier (wajib)
- **Detail**: Repeater barang → pilih barang (harga otomatis dari `harga_beli`), qty, harga
- **Saat Simpan**:
  1. Validasi: barang tidak duplikat, qty > 0
  2. `subtotal = qty × unit_price`, `total_amount = Σ subtotal`
  3. **Stok bertambah**: `tb_stocks.stock += qty`
  4. **HPP diperbarui**: `recalculateHpp()` → rata-rata tertimbang dari semua detail pembelian
  5. Header + Detail + Update Stok dalam 1 transaksi DB
- **Kredit**: `trs_type = 1` → `paid_amount = 0`, `remaining_amount = total_amount` (hutang ke supplier)

#### 3.2.2 Retur Pembelian (`PURCHASE_RET`) — Nomor `RPB-XXXXXX`
- **Header**: Tanggal, Supplier (wajib)
- **Validasi**: Stok berkurang tidak boleh melebihi stok tersedia
- **Saat Simpan**: Stok berkurang (`stock -= qty`), **tidak** memicu recalculate HPP

#### 3.2.3 Penjualan (`SALE`) — Nomor `PJ-XXXXXX`
- **Header**: Tanggal, Customer (wajib **hanya jika kredit**), Jenis Bayar (Tunai/Kredit)
- **Detail**: Repeater barang → harga otomatis dari `harga_jual`
- **Saat Simpan**:
  1. Validasi stok per baris: error jika stok kurang → rollback seluruh transaksi
  2. **Snapshot HPP**: `hpp_at_transaction = tb_stocks.harga_pokok` saat transaksi (immutable)
  3. **Stok berkurang**: `stock -= qty`
  4. Semua dalam 1 transaksi DB
- **Cetak Struk**: Action per baris → buka tab baru, auto-print (thermal 80mm). Tampilkan: customer (nama/alamat/telp), tanggal, jenis bayar, **sisa piutang** jika kredit, rincian barang, total.

#### 3.2.4 Retur Penjualan (`SALE_RET`) — Nomor `RPJ-XXXXXX`
- **Header**: Tanggal, Customer, Jenis Bayar (Tunai/Kredit)
- **Saat Simpan**:
  1. **Stok bertambah**: `stock += qty`
  2. **Snapshot HPP** dari `harga_pokok` saat transaksi
  3. **Retur Kredit**: Mencatat `remaining_amount = total` → mengurangi piutang customer. Saat angsuran dibayar, retur ini **ikut dikonsumsi proporsional** (lihat 3.3).

#### 3.2.5 Stok Opname (`OPNAME`) — Nomor `OP-XXXXXX`
- **Header**: Hanya tanggal, `total/paid/remaining = 0`
- **Detail**: Pilih barang → tampil **Stok Sistem** (read-only), isi **Stok Fisik**, selisih otomatis
- **Saat Simpan**:
  1. Validasi stok fisik ≥ 0
  2. `qty` **bersifat signed** (positif = surplus, negatif = shortage) — satu-satunya tipe yang signed
  3. **Stok diset langsung** ke nilai fisik: `stock = stok_fisik`
  4. Tidak memicu recalculate HPP

---

### 3.3 Angsuran Customer (Piutang)

**Resource**: `CustomerPayments` (model `CustomerPayment`)

- **Form**: Customer (wajib) → pilih Invoice Kredit (`SALE` dengan `remaining_amount > 0`) → Tanggal Bayar → Jumlah
  - Dropdown invoice menampilkan sisa tagihan, auto-isi jumlah
  - Batas maksimal = **Piutang Bersih** = `Customer::netReceivable()` = `Σ SALE kredit − Σ SALE_RET kredit`
- **Alokasi Proporsional** (`Customer::applyPayment()`):
  - Pembayaran mengonsumsi **SALE kredit** & **SALE_RET kredit** sebanding porsinya terhadap piutang bersih
  - Bagian SALE: dialokasikan **FIFO** (invoice terlama dulu); jika invoice dipilih manual → invoice itu didahulukan, kelebihan mengalir ke invoice lain
  - Bagian SALE_RET: dialokasikan FIFO ke transaksi `SALE_RET`
- **Hapus Angsuran** (`reversePayment()`): Balikkan alokasi proporsional → hapus record

**State Keuangan Header**:
| Kondisi | paid_amount | remaining_amount |
|---------|-------------|------------------|
| Tunai | = total | 0 |
| Kredit (belum bayar) | 0 | = total |
| Kredit (sebagian) | > 0 | > 0 |
| Kredit (lunas) | = total | 0 |
| Retur Jual Kredit | 0 | = total (mengurangi piutang) |

---

### 3.4 Laporan (Laporan)

Semua laporan adalah **Filament Page** (bukan Resource), pakai `HasTable` + `EmbeddedTable`, filter via Action modal di header.

| Laporan | Deskripsi | Fitur Cetak/Export |
|---------|-----------|---------------------|
| **Laporan Penjualan** | Ringkasan per barang / per customer, periode tanggal | Cetak (view), Export CSV (UTF-8 BOM) |
| **Kartu Stok** | Mutasi masuk/keluar per barang (detail per transaksi) | Cetak (view + auto-print), Export CSV |
| **Piutang (Aging)** | Umur piutang per customer (current, 30, 60, 90+ hari) | Cetak, Export CSV |
| **Kartu Piutang** | Mutasi piutang per customer (invoice, bayar, sisa) | Cetak, Export CSV |
| **Nilai Persediaan** | `stock × harga_pokok` per barang, total per kategori | Cetak, Export CSV |

**Teknis Cetak/Export**: Route GET di `AdminPanelProvider::authenticatedRoutes()` → return View (print) atau Stream CSV (UTF-8 BOM `\xEF\xBB\xBF`). Tombol disabled sampai filter terisi.

---

### 3.5 Dashboard (Widget)

| Widget | Sumber Data | Keterangan |
|--------|-------------|------------|
| **SalesStatsOverview** | `TrHeader` (SALE) | Total penjualan, total transaksi, rata-rata |
| **SalesTrendChart** | `TrHeader` (SALE) | Grafik tren penjualan harian/bulanan |
| **Laba Kotor** | `TrDetail` (SALE) | `Σ (unit_price − hpp_at_transaction) × qty` |
| **InventoryStatsOverview** | `TbStock` | Total item, total stok, nilai persediaan |
| **LowStockTable** | `TbStock` | Barang dengan stok ≤ threshold |
| **StockValueByCategoryChart** | `TbStock` + `TbCate` | Pie/bar chart nilai persediaan per kategori |

---

## 4. Non-Functional Requirements

| Kategori | Requirement |
|----------|-------------|
| **Performa** | SQLite cocok untuk single-user / skala kecil; query laporan dioptimasi dengan eager loading & index |
| **Keamanan** | Auth via Filament Panel (user table), middleware `auth` pada route custom, validasi server-side semua input |
| **Integritas Data** | Semua transaksi `DB::transaction()`, FK cascade/restrict, `hpp_at_transaction` immutable |
| **UI/UX** | Filament 5 + Tailwind 4, modal form, table dengan filter/sort/search, dark mode ready |
| **Testing** | Pest 5 (feature tests), factory untuk data uji |
| **Code Style** | Laravel Pint (PSR-12 + Laravel conventions) |
| **Deployment** | SQLite file (`database/database.sqlite`), `composer run dev` untuk development, `npm run build` untuk production assets |

---

## 5. Business Rules & Logic Penting

1. **HPP (Harga Pokok Penjualan)**
   - Snapshot di `hpp_at_transaction` saat transaksi penjualan/retur jual → **tidak boleh diubah**
   - `harga_pokok` di `tb_stocks` = rata-rata tertimbang dari SEMUA pembelian (`SUM(subtotal)/SUM(qty)`)
   - Saat barang belum pernah dibeli: `harga_pokok = harga_beli` (event `saving`)

2. **Arah Stok** (ditentukan dari `trr_type`, bukan tanda qty):
   - **Masuk (+)**: `PURCHASE`, `SALE_RET`, `OPNAME` (surplus)
   - **Keluar (−)**: `SALE`, `PURCHASE_RET`, `OPNAME` (shortage)

3. **Nomor Transaksi**: Auto-generate `PREFIX-XXXXXX` (6 digit), prefix per tipe:
   - `PB` (Pembelian), `RPB` (Retur Beli), `PJ` (Penjualan), `RPJ` (Retur Jual), `OP` (Opname)

4. **Piutang Bersih Customer**: `netReceivable = receivableBalance (SALE kredit) − returnCredit (SALE_RET kredit)`

5. **Alokasi Proporsional Angsuran**: Pembayaran dibagi ke SALE & SALE_RET sebanding porsi masing-masing terhadap net receivable.

---

## 6. Batasan (Out of Scope v1)

- ❌ Multi-gudang / multi-lokasi stok
- ❌ Pembayaran hutang ke supplier (resource terpisah) — hanya dicatat di header
- ❌ Multi-user role/permission granular (hanya 1 panel Filament, semua user `canAccessPanel = true`)
- ❌ Integrasi payment gateway / e-wallet
- ❌ Barcode scanner hardware (input manual kode barang)
- ❌ Multi-currency
- ❌ Serial number / batch tracking per item

---

## 7. Rencana Rilis / Roadmap

| Versi | Target | Fitur Utama |
|-------|--------|-------------|
| **v1.0 (Current)** | MVP | Semua fitur di atas: master, transaksi, angsuran, laporan, dashboard, struk |
| **v1.1** | Q3 2026 | Pembayaran Supplier (hutang), User roles/permissions, Audit log |
| **v1.2** | Q4 2026 | Multi-gudang, Barcode scanner support, Stock alert (email/WA) |
| **v2.0** | 2027 | API untuk mobile POS, Multi-customer portal (lihat piutang), Reporting engine lanjutan |

---

## 8. Glosarium

| Istilah | Definisi |
|---------|----------|
| **HPP** | Harga Pokok Penjualan (Cost of Goods Sold) — rata-rata tertimbang pembelian |
| **Piutang** | Tagihan customer dari penjualan kredit yang belum lunas |
| **Aging** | Pembagian piutang berdasarkan umur tunggakan (current, 30, 60, 90+ hari) |
| **FIFO** | First In, First Out — alokasi pembayaran ke invoice terlama dulu |
| **Proporsional** | Pembagian pembayaran mengikuti porsi masing-masing komponen (SALE vs SALE_RET) |
| **Snapshot** | Nilai yang di-"freeze" saat transaksi (contoh: `hpp_at_transaction`) |
| **Struk** | Bukti transaksi cetak (thermal 80mm) untuk customer |

---

## 9. Referensi Teknis

- **Repository**: `rpslite` (Laravel 13 + Filament 5)
- **Docs**: `docs/database.md`, `docs/transaksi.md`, `docs/development.md`
- **Convenventions**: `.ai/rules/` (project rules untuk agent)
- **Testing**: `php artisan test --compact`
- **Format Code**: `vendor/bin/pint`

---

*Dokumen ini di-generate dari analisis codebase RPS Lite pada Agustus 2026.*