# Daftar Tugas (Task) — RPS Lite

Daftar kerja aktif & tertunda. Centang (`[x]`) saat selesai. Dokumen pendukung: `PRD.md`, `docs/development.md`, `docs/roadmap.md`.

## Backlog Aktif

- [ ] **Responsivitas layar tablet & HP**
  - [ ] Audit menyeluruh selesai — panel Filament sudah responsive bawaan; view laporan/struk adalah dokumen cetak (print-oriented).
  - [ ] Putuskan: tambah `overflow-x: auto` pada container tabel laporan agar nyaman dibuka di HP, atau biarkan print-oriented.
- [ ] **Laporan Laba/Rugi** — omzet, HPP, laba kotor per periode/barang (lihat `docs/agenda_laporan.txt`).
- [ ] **Laporan Angsuran** — penerimaan angsuran per periode per customer (lihat `docs/agenda_laporan.txt`).
- [ ] **Laporan Stok menipis / habis** — alert re-stock harian (lihat `docs/agenda_laporan.txt`).
- [ ] **Resource pembayaran supplier** — hutang ke supplier tercatat di header transaksi, belum ada resource pembayaran terpisah (lihat `PRD.md` 3.1).

## Backlog Selesai

- [x] Struk penjualan dengan pilihan lebar kertas **58mm / 80mm** (tersimpan permanen di `settings`, halaman "Pengaturan Printer").
- [x] Semua operasi tulis database dibungkus **transaction + rollback** (termasuk `SafeDeleteBulkAction` untuk bulk delete).
- [x] Laporan Pembelian (resource `LaporanPembelians` + view cetak `pembelian-ringkas`).
- [x] Laporan Penjualan Ringkas (view cetak `penjualan-ringkas`).
- [x] Widget dashboard: ringkasan penjualan, laba kotor, piutang, stok menipis, grafik tren.
- [x] Laporan: Penjualan, Pembelian, Kartu Stok (cetak + export Excel/CSV), Piutang (Aging), Kartu Piutang, Nilai Persediaan.
- [x] Transaksi: Pembelian, Retur Pembelian, Penjualan (+ struk), Retur Penjualan, Stok Opname, Angsuran Customer (alokasi proporsional).

## Aturan Pengerjaan

- Jalankan `vendor/bin/pint` setelah mengubah file PHP.
- Jalankan test dengan `php artisan test --compact` (filter: `--filter=NamaTest`).
- Ikuti aturan mengikat di `.ai/rules/` (diindeks oleh `.ai/rules/index.md`).
- Setiap perubahan harus disertai test.