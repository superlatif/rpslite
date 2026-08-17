# Panduan Pengguna (User Guide) — RPS Lite

---

## 1. Persiapan Awal

### 1.1 Login
1. Buka browser ke `http://localhost:8000` (atau domain server Anda)
2. Klik **Login** → masukkan email & password admin
3. Dashboard akan tampil dengan ringkasan penjualan, stok, piutang

### 1.2 Navigasi Sidebar
| Group | Menu | Fungsi |
|-------|------|--------|
| **Tabel** | Kategori Barang | Kelola kategori produk |
| | Data Barang | Kelola stok, harga, gambar |
| | Data Supplier | Kelola data supplier |
| | Data Customer | Kelola data customer |
| **Transaksi** | Pembelian | Input beli barang dari supplier |
| | Retur Pembelian | Retur barang ke supplier |
| | Penjualan | Jual barang ke customer |
| | Retur Penjualan | Terima retur dari customer |
| | Angsuran Customer | Bayar cicilan piutang |
| | Stok Opname | Sesuaikan stok fisik vs sistem |
| **Laporan** | Laporan Penjualan | Ringkasan penjualan per barang/customer |
| | Kartu Stok | Mutasi masuk/keluar per barang |
| | Piutang (Aging) | Umur piutang per customer |
| | Kartu Piutang | Mutasi piutang per customer |
| | Nilai Persediaan | Nilai stok (qty × HPP) |

---

## 2. Data Master (Tabel)

### 2.1 Kategori Barang
- **Tambah**: Klik "Tambah" → isi Nama Kategori → Simpan
- **Edit**: Klik ikon pensil di baris tabel
- **Hapus**: Klik ikon trash → Konfirmasi (tidak bisa hapus jika masih dipakai barang)

### 2.2 Data Barang (Stok)
**Field Wajib:**
- **Kode**: Auto-generate 8 digit (tidak bisa diubah)
- **Nama Barang**: Max 50 karakter
- **Satuan**: Default `PCS` (bisa diganti: BOX, PACK, dll)
- **Harga Beli**: Harga dari supplier
- **Harga Jual**: Harga ke customer
- **Kategori**: Pilih dari dropdown (opsional)
- **Gambar**: Upload foto barang (opsional)

**Catatan:**
- `Harga Pokok (HPP)` **otomatis** dihitung rata-rata tertimbang dari pembelian
- Saat belum ada pembelian: `HPP = Harga Beli`
- Stok awal = 0, bertambah saat pembelian, berkurang saat penjualan

### 2.3 Data Customer
- **Nama**, **Alamat**, **Telepon/WA**
- Piutang otomatis terhitung dari transaksi kredit
- Lihat **Piutang Bersih** di kolom tabel

### 2.4 Data Supplier
- **Nama**, **Alamat**, **Telepon/WA**
- Hutang tercatat di transaksi pembelian kredit

---

## 3. Transaksi

> **Penting**: Semua transaksi menggunakan **Modal Form** (popup), bukan halaman terpisah.
> Klik **"Tambah"** di kanan atas tabel → isi form → **"Simpan"**.

### 3.1 Pembelian (`PB-XXXXXX`)
1. Klik **Tambah** pada halaman Pembelian
2. **Header**:
   - Tanggal (default hari ini)
   - **Supplier** (wajib pilih)
3. **Detail** (Repeater):
   - Klik **"Tambah Barang"**
   - Pilih Barang → Harga Beli terisi otomatis
   - Isi **Qty** & **Harga** (bisa edit)
   - Subtotal & Grand Total hitung otomatis
4. **Jenis Bayar**:
   - **Tunai**: Langsung lunas
   - **Kredit**: Jadi hutang ke supplier (`remaining_amount = total`)
5. Klik **Simpan** → Stok otomatis **bertambah**, HPP diperbarui

### 3.2 Retur Pembelian (`RPB-XXXXXX`)
1. Pilih **Supplier** → Tanggal
2. Isi detail barang yang dikembalikan
3. **Validasi**: Stok tidak boleh negatif setelah dikurangi
4. Simpan → Stok **berkurang**, HPP **tidak** berubah

### 3.3 Penjualan (`PJ-XXXXXX`)
1. Klik **Tambah** pada halaman Penjualan
2. **Header**:
   - Tanggal
   - **Customer** (wajib **hanya jika Kredit**)
   - **Jenis Bayar**: Tunai / Kredit
     - Jika **Customer kosong** → otomatis **Tunai** (disabled Kredit)
3. **Detail**:
   - Pilih Barang → Harga Jual terisi otomatis
   - Isi Qty → **Validasi stok real-time** (error jika stok kurang)
   - Subtotal & Grand Total otomatis
4. Simpan → Stok **berkurang**, `hpp_at_transaction` di-snapshot
5. **Cetak Struk**: Klik ikon **Printer** di kolom aksi baris tabel
   - Buka tab baru → auto-print ke printer thermal
   - Struk berisi: toko, customer, tanggal, jenis bayar, sisa piutang (jika kredit), detail barang, total

### 3.4 Retur Penjualan (`RPJ-XXXXXX`)
1. Pilih **Customer** → Tanggal
2. **Jika Kredit**: Wajib pilih **No. Faktur Jual** asal (dropdown hanya invoice kredit yang masih punya sisa tagihan)
3. Isi detail barang yang dikembalikan
4. Simpan → Stok **bertambah**
5. **Retur Kredit**: Mengurangi piutang customer (otomatis masuk perhitungan `netReceivable`)

### 3.5 Stok Opname (`OP-XXXXXX`)
1. Hanya isi **Tanggal**
2. **Detail**:
   - Pilih Barang → **Stok Sistem** tampil read-only
   - Isi **Stok Fisik** (hasil hitung manual)
   - **Selisih** = Fisik − Sistem (otomatis, bisa +/−)
3. Simpan → Stok **diset langsung** ke nilai fisik
4. `qty` tersimpan **signed** (+ surplus, - shortage)

### 3.6 Angsuran Customer (Bayar Cicilan)
1. Pilih **Customer** → otomatis hitung **Piutang Bersih** (max bayar)
2. **Opsional**: Pilih **Invoice** tertentu (dropdown SALE kredit dengan sisa > 0)
3. Isi **Tanggal Bayar** & **Jumlah**
4. Simpan → Alokasi **otomatis proporsional + FIFO**:
   - Pembayaran dibagi ke **SALE kredit** & **SALE_RET kredit** sebanding porsi
   - Bagian SALE: bayar invoice terlama dulu (FIFO), kelebihan ke invoice berikutnya
   - Bagian SALE_RET: bayar FIFO ke transaksi retur
5. **Hapus Angsuran**: Klik ikon trash di tabel → alokasi dibalikkan otomatis

---

## 4. Laporan

Semua laporan pakai **Filter Periode** + **Generate** → tampil tabel → **Cetak** / **Export CSV**.

### 4.1 Laporan Penjualan
- Filter: Tanggal, Customer, Group by (Barang / Customer)
- Kolom: Kode, Nama, Qty, Total, HPP, Laba, Margin%

### 4.2 Kartu Stok
- Filter: Barang, Tanggal Mulai - Tanggal Akhir
- Kolom: Tanggal, No. Transaksi, Tipe, Keterangan, Masuk, Keluar, Saldo, HPP, Nilai
- **Cetak**: Auto-print landscape A4
- **Export**: CSV UTF-8 (bisa buka Excel)

### 4.3 Piutang (Aging)
- Filter: Tanggal cut-off
- Kolom: Customer, Current, 1-30, 31-60, 61-90, 90+, Total
- Berdasarkan umur invoice dari `trs_date`

### 4.4 Kartu Piutang
- Filter: Customer, Tanggal
- Mutasi per customer: Invoice, Bayar, Retur, Saldo berjalan

### 4.5 Nilai Persediaan
- Filter: Kategori (opsional), Hanya stok > 0
- Kolom: Kategori, Kode, Nama, Satuan, Stok, HPP, Nilai, %
- Total per kategori + Grand Total

---

## 5. Dashboard

| Widget | Isi |
|--------|-----|
| **Sales Stats** | Total penjualan, jumlah transaksi, rata-rata, piutang |
| **Sales Trend** | Grafik penjualan 30 hari / 12 bulan |
| **Inventory Stats** | Total item, total qty stok, nilai persediaan |
| **Low Stock** | Barang stok ≤ 10 (klik untuk ke Data Barang) |
| **Stock Value by Category** | Pie/Bar chart nilai persediaan per kategori |

---

## 6. Cetak Struk Thermal (Printer)

### 6.1 Persyaratan
- Printer thermal 80mm (USB/Network) terhubung ke **Server** (bukan ke HP user)
- CUPS terinstall & printer bernama **`ThermalRaw`** (default)
- Test: `lpstat -p -d` → harus muncul `ThermalRaw is idle`

### 6.2 Cara Cetak
1. Buka halaman **Penjualan**
2. Klik ikon **Printer** di baris transaksi
3. Konfirmasi "Kirim struk ke printer?" → **Ya, cetak**
4. Struk langsung keluar dari printer thermal

### 6.3 Jika Printer Tidak Cetak
- Cek `lpstat -p` apakah printer `enabled` & `idle`
- Cek error CUPS: `tail -f /var/log/cups/error_log`
- Pastikan user `www-data` punya akses print: `sudo usermod -a -G lp www-data`

### 6.4 Akses dari HP (Mobile)
> **Tidak bisa cetak langsung** dari HP karena printer terhubung ke server.
> Solusi: Gunakan **Printer Network/WiFi** (lihat bagian Troubleshooting).

---

## 7. Tips & Trik

### 7.1 Shortcut Keyboard
| Tombol | Aksi |
|--------|------|
| `Ctrl + K` | Buka Command Palette (Filament) |
| `Escape` | Tutup modal |
| `Tab` | Next field di form |
| `Enter` | Submit form (di button fokus) |

### 7.2 Pencarian Cepat
- Gunakan **Search** di tabel (kanan atas) untuk filter cepat
- Column search: klik header kolom → ketik filter

### 7.3 Bulk Actions
- Centang checkbox di kiri tabel → pilih **Delete** / **Export** di dropdown bulk action

### 7.4 Format Uang
- Semua input uang pakai format **Rp 1.000.000** (auto-format)
- Simpan ke database sebagai desimal (1000000.00)

### 7.5 Backup Database
```bash
# Manual backup SQLite
cp database/database.sqlite storage/backups/db_$(date +%F).sqlite

# Atau via Laravel (jika pakai MySQL/Postgres)
php artisan backup:run
```

---

## 8. Troubleshooting

| Masalah | Penyebab | Solusi |
|---------|----------|--------|
| **Stok minus setelah penjualan** | Race condition / validasi gagal | Cek log, pastikan transaksi atomic |
| **HPP tidak berubah setelah beli** | Barang belum punya detail pembelian sebelumnya | Normal: HPP = Harga Beli pertama kali |
| **Piutang tidak berkurang setelah bayar** | Angsuran melebihi netReceivable | Cek `Customer::netReceivable()` sebelum bayar |
| **Struk garbled/karakter aneh** | Printer terima HTML bukan raw text | Pastikan pakai route `/penjualan/{id}/struk` (sudah diperbaiki v1.1) |
| **Printer tidak merespons** | CUPS down / printer offline | `sudo systemctl restart cups` + cek `lpstat` |
| **Error "null byte in escapeshellarg"** | ESC/POS cut command pakai `\x00` | Sudah difix di `ThermalPrinterService` (strip null byte) |
| **Migration error FK constraint** | Data lama tidak konsisten | Hapus data orphan: `TrHeader` tanpa customer/supplier |

---

## 9. Glosarium Istilah

| Istilah | Arti |
|---------|------|
| **HPP** | Harga Pokok Penjualan (rata-rata tertimbang pembelian) |
| **Piutang** | Tagihan customer dari penjualan kredit |
| **Aging** | Pembagian piutang berdasarkan umur tunggakan |
| **FIFO** | First In First Out (bayar invoice terlama dulu) |
| **Proporsional** | Pembagian pembayaran mengikuti porsi masing-masing komponen |
| **Snapshot** | Nilai yang "di-freeze" saat transaksi (contoh: HPP saat jual) |
| **Opname** | Penyesuaian stok fisik vs sistem |
| **Retur** | Barang dikembalikan (ke supplier / dari customer) |

---

## 10. Bantuan & Dukungan

- **Dokumentasi Teknis**: `docs/` (database.md, transaksi.md, development.md)
- **Business Rules**: `BUSINESS_RULES.md`
- **Arsitektur**: `ARCHITECTURE.md`
- **UI Specification**: `UI.md`
- **Product Requirements**: `PRD.md`

**Error Log**: `storage/logs/laravel.log`  
**Browser Log**: Via Laravel Boost MCP tool

---

*User Guide ini berlaku untuk RPS Lite v1.x (Laravel 13 + Filament 5). Update saat fitur baru ditambahkan.*