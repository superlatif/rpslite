---
paths:
  - 'resources/views/laporan/**'
---

# Laporan

## Laporan & cetak pakai dompdf, bukan window.print
Semua laporan & cetak (kecuali struk thermal) dirender ke PDF via barryvdh/laravel-dompdf lewat App\Services\PdfReportService. View di resources/views/laporan/** adalah template PDF: JANGAN tambahkan toolbar, tombol window.print(), <script>, atau @media print ke view ini — tidak akan muncul di PDF. Struk (resources/views/struk/penjualan.blade.php) tetap HTML + ThermalPrinterService dan TIDAK boleh diganti dompdf. Route report (cetak/laporan) terdaftar di AdminPanelProvider->authenticatedRoutes() dan men-stream PDF; route export tetap CSV dengan BOM UTF-8.
