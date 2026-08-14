# Skema Database

RPS Lite menggunakan database relasional (default **SQLite**). Semua tabel didefinisikan di `database/migrations/`.

## Ringkasan

```
tb_cates (kategori)
   └── tb_stocks (barang/stok)
          └── tr_details (detail transaksi)
                 └── tr_headers (header transaksi)
                        ├── customers
                        └── suppliers
                 └── customer_payments (angsuran)
```

## Tabel

### `tb_cates` — Kategori Barang

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id` | bigint (PK) | |
| `descr` | string(30) | Nama kategori |
| `created_at` / `updated_at` | timestamp | |

### `tb_stocks` — Barang / Stok

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id` | bigint (PK) | |
| `code` | string(15) unique | Kode barang (otomatis 8 digit) |
| `descr` | string(50) | Nama barang |
| `satuan` | string(15) | Satuan, default `PCS` |
| `harga_beli` | decimal(15,2) | Harga beli dari supplier (default 0) |
| `harga_jual` | decimal(15,2) | Harga jual ke customer (default 0) |
| `harga_pokok` | decimal(15,2) | Harga pokok (HPP) — dikelola otomatis, default 0 |
| `stock` | integer | Jumlah stok tersedia (default 0) |
| `gambar` | text nullable | Path gambar item |
| `tb_cate_id` | FK → `tb_cates` | Kategori (nullable, restrictOnDelete) |
| `created_at` / `updated_at` | timestamp | |

### `customers` — Customer

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id` | bigint (PK) | |
| `descr` | string(30) | Nama customer |
| `alamat` | string(50) nullable | Alamat |
| `phone` | string(30) nullable | No. HP / WA |
| `created_at` / `updated_at` | timestamp | |

### `suppliers` — Supplier

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id` | bigint (PK) | |
| `descr` | string(30) | Nama supplier |
| `alamat` | string(50) nullable | Alamat |
| `phone` | string(30) nullable | No. HP / WA |
| `created_at` / `updated_at` | timestamp | |

### `tr_headers` — Header Transaksi

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id` | bigint (PK) | |
| `trs_number` | string(10) unique | Nomor transaksi (`PB-`/`RPB-`/`PJ-`/`RPJ-`/`OP-xxxxxx`) |
| `trs_date` | date | Tanggal transaksi |
| `trr_type` | enum | `PURCHASE` / `PURCHASE_RET` / `SALE` / `SALE_RET` / `OPNAME` |
| `customer_id` | FK → `customers` nullable | Diisi untuk SALE & SALE_RET (nullOnDelete) |
| `supplier_id` | FK → `suppliers` nullable | Diisi untuk PURCHASE & PURCHASE_RET (nullOnDelete) |
| `total_amount` | decimal(15,2) | Total nilai transaksi |
| `trs_type` | unsignedTinyInteger | `0` = tunai, `1` = kredit |
| `paid_amount` | decimal(15,2) | Total yang sudah dibayar |
| `remaining_amount` | decimal(15,2) | Sisa tagihan (piutang) |
| `created_at` / `updated_at` | timestamp | |

**Aturan angka:** untuk tunai → `paid_amount = total_amount`, `remaining_amount = 0`. Untuk kredit → `paid_amount = 0`, `remaining_amount = total_amount`.

### `tr_details` — Detail Transaksi

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id` | bigint (PK) | |
| `tr_header_id` | FK → `tr_headers` | Cascade on delete |
| `stock_id` | FK → `tb_stocks` | Barang (restrictOnDelete) |
| `qty` | decimal(10,2) | Jumlah. Absolut untuk semua tipe **kecuali** `OPNAME` yang bertanda (positif = surplus, negatif = shortage) |
| `unit_price` | decimal(15,2) | Harga satuan saat transaksi |
| `hpp_at_transaction` | decimal(15,2) | **Snapshot HPP** saat transaksi — jangan diubah setelah diposting |
| `subtotal` | decimal(15,2) | `qty × unit_price` |
| `created_at` / `updated_at` | timestamp | |

### `customer_payments` — Angsuran Customer

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id` | bigint (PK) | |
| `customer_id` | FK → `customers` | Wajib (restrictOnDelete) |
| `tr_header_id` | FK → `tr_headers` nullable | Invoice kredit terkait (nullOnDelete) |
| `payment_date` | date | Tanggal pembayaran |
| `amount` | decimal(15,2) | Jumlah dibayar |
| `created_at` / `updated_at` | timestamp | |

### Tabel bawaan framework

- `users`, `password_reset_tokens`, `sessions` — autentikasi Laravel
- `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` — cache & queue

## Catatan Penting

- **`trr_type` adalah enum**, bukan tinyint: `PURCHASE`, `PURCHASE_RET`, `SALE`, `SALE_RET`, `OPNAME`. Resource memfilter lewat kolom ini.
- **`hpp_at_transaction` bersifat immutable** — setelah transaksi diposting nilainya tidak boleh diubah, karena menjadi dasar perhitungan laba.
- **`harga_pokok` dikelola otomatis**: saat barang belum punya transaksi pembelian, `harga_pokok = harga_beli` (via event `saving`). Setelah ada pembelian, `recalculateHpp()` menghitung HPP rata-rata tertimbang (`SUM(subtotal) / SUM(qty)` dari detail pembelian). Kolom ini tidak diinput manual di form.
- **Arah stok ditentukan dari tipe header**, bukan dari nilai negatif `qty` (kecuali opname): pembelian & retur penjualan menambah, penjualan & retur pembelian mengurangi, opname menyetel stok ke nilai fisik.
