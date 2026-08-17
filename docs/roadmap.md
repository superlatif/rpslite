# Roadmap — RPS Lite

Arah pengembangan jangka pendek & panjang. Berbasis `PRD.md` dan kebutuhan operasional POS. Status: `[x]` selesai, `[ ]` belum.

## V1 — Fondasi (Selesai)

- [x] Data master: Kategori, Barang/Stok, Customer, Supplier.
- [x] Transaksi: Pembelian, Retur Pembelian, Penjualan (+ struk), Retur Penjualan, Stok Opname.
- [x] HPP rata-rata tertimbang (`recalculateHpp`), snapshot `hpp_at_transaction`.
- [x] Angsuran customer dengan alokasi proporsional (SALE & SALE_RET kredit).
- [x] Dashboard widget: ringkasan penjualan, laba kotor, piutang, stok, grafik tren.
- [x] Laporan: Penjualan, Pembelian, Kartu Stok, Piutang (Aging), Kartu Piutang, Nilai Persediaan — cetak & export Excel (CSV).
- [x] Semua operasi DB atomik (transaction + rollback), termasuk bulk delete.

## V2 — Penyempurnaan POS (Dalam Proses)

- [x] Struk penjualan 58mm/80mm (pengaturan tersimpan permanen).
- [x] Laporan Pembelian & Penjualan Ringkas (cetak).
- [ ] Responsivitas layar tablet & HP untuk halaman cetak (laporan).
- [ ] Laporan Laba/Rugi per periode/barang.
- [ ] Laporan Angsuran per periode per customer.
- [ ] Laporan stok menipis/habis untuk alert re-stock harian.
- [ ] Resource pembayaran supplier (hutang).

## V3 — Penguatan & Skalabilitas (Belum Dimulai)

- [ ] Multi-user & role permission (owner, kasir, gudang, akunting) — lihat persona di `PRD.md` §2.
- [ ] Audit log untuk transaksi & perubahan data master.
- [ ] Backup database otomatis (SQLite → file dump terjadwal).
- [ ] Pagination & optimasi query pada laporan data besar.
- [ ] Dark mode & tema panel lanjutan.

## V4 — Mobile & Integrasi (Belum Dimulai)

- [ ] Tampilan kasir mobile-optimized (perangkat tablet/HP di kasir).
- [ ] Integrasi printer thermal Bluetooth/LAN untuk perangkat portabel (`mike42/escpos-php`, Web Intent RawBT — lihat `docs/agenda_laporan.txt`).
- [ ] Export PDF untuk semua laporan.
- [ ] API (opsional) untuk integrasi eksternal / aplikasi mobile.

## Catatan

- Prioritas harian tertinggi (dari `docs/agenda_laporan.txt`): **piutang** (aging & angsuran kredit) dan **stok menipis/habis**.
- Perubahan arsitektur besar harus mengikuti `ARCHITECTURE.md` & aturan `.ai/rules/`.