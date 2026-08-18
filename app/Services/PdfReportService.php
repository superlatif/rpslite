<?php

namespace App\Services;

use App\Filament\Pages\Tables\LaporanKartuStokTable;
use App\Filament\Pages\Tables\LaporanNilaiPersediaanTable;
use App\Models\TbStock;
use App\Models\TrHeader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class PdfReportService
{
    public function kartuStok(TbStock $stock, string $dateFrom, string $dateUntil): Response
    {
        $rows = LaporanKartuStokTable::buildRows((string) $stock->id, $dateFrom, $dateUntil);

        return $this->stream(
            'laporan.kartu-stok',
            [
                'stock' => $stock,
                'dateFrom' => $dateFrom,
                'dateUntil' => $dateUntil,
                'rows' => $rows,
            ],
            'kartu-stok-'.($stock->code ?? $stock->id).'-'.$dateFrom.'-'.$dateUntil,
        );
    }

    public function nilaiPersediaan(string $cateId = '', mixed $onlyAvailable = null): Response
    {
        $rows = LaporanNilaiPersediaanTable::buildRows($cateId, $onlyAvailable);

        return $this->stream(
            'laporan.nilai-persediaan',
            ['rows' => $rows],
            'laporan-nilai-persediaan-'.now()->format('Ymd-His'),
        );
    }

    public function penjualan(TrHeader $header): Response
    {
        $header->load('customer', 'details.stock');

        return $this->stream(
            'laporan.penjualan',
            ['header' => $header],
            'laporan-penjualan-'.$header->trs_number,
        );
    }

    public function pembelian(TrHeader $header): Response
    {
        $header->load('supplier', 'details.stock');

        return $this->stream(
            'laporan.pembelian',
            ['header' => $header],
            'laporan-pembelian-'.$header->trs_number,
        );
    }

    public function returPenjualan(TrHeader $header): Response
    {
        $header->load('customer', 'sourceSale', 'details.stock');

        return $this->stream(
            'laporan.retur-penjualan',
            ['header' => $header],
            'laporan-retur-penjualan-'.$header->trs_number,
        );
    }

    public function returPembelian(TrHeader $header): Response
    {
        $header->load('supplier', 'sourcePurchase', 'details.stock');

        return $this->stream(
            'laporan.retur-pembelian',
            ['header' => $header],
            'laporan-retur-pembelian-'.$header->trs_number,
        );
    }

    public function penjualanRingkas(string $dateFrom = '', string $dateUntil = '', string $customerId = '', string $trsType = ''): Response
    {
        return $this->stream(
            'laporan.penjualan-ringkas',
            [
                'dateFrom' => $dateFrom,
                'dateUntil' => $dateUntil,
                'customerId' => $customerId,
                'trsType' => $trsType,
                'headers' => self::penjualanRingkasHeaders($dateFrom, $dateUntil, $customerId, $trsType),
            ],
            'laporan-penjualan'.($dateFrom ? '-'.$dateFrom : '').($dateUntil ? '-'.$dateUntil : ''),
        );
    }

    public function pembelianRingkas(string $dateFrom = '', string $dateUntil = '', string $supplierId = '', string $trsType = ''): Response
    {
        return $this->stream(
            'laporan.pembelian-ringkas',
            [
                'dateFrom' => $dateFrom,
                'dateUntil' => $dateUntil,
                'supplierId' => $supplierId,
                'trsType' => $trsType,
                'headers' => self::pembelianRingkasHeaders($dateFrom, $dateUntil, $supplierId, $trsType),
            ],
            'laporan-pembelian'.($dateFrom ? '-'.$dateFrom : '').($dateUntil ? '-'.$dateUntil : ''),
        );
    }

    public static function penjualanRingkasHeaders(string $dateFrom = '', string $dateUntil = '', string $customerId = '', string $trsType = ''): Collection
    {
        return TrHeader::query()
            ->where(fn ($query) => $query->where('trr_type', 'SALE')
                ->orWhere('trr_type', 'SALE_RET'))
            ->when(filled($dateFrom), fn ($query) => $query->whereDate('trs_date', '>=', $dateFrom))
            ->when(filled($dateUntil), fn ($query) => $query->whereDate('trs_date', '<=', $dateUntil))
            ->when(filled($customerId), fn ($query) => $query->where('customer_id', $customerId))
            ->when(filled($trsType) && $trsType !== '', fn ($query) => $query->where('trs_type', (int) $trsType))
            ->with('customer')
            ->with('details')
            ->orderBy('trs_date')
            ->orderBy('trs_number')
            ->get();
    }

    public static function pembelianRingkasHeaders(string $dateFrom = '', string $dateUntil = '', string $supplierId = '', string $trsType = ''): Collection
    {
        return TrHeader::query()
            ->where(fn ($query) => $query->where('trr_type', 'PURCHASE')
                ->orWhere('trr_type', 'PURCHASE_RET'))
            ->when(filled($dateFrom), fn ($query) => $query->whereDate('trs_date', '>=', $dateFrom))
            ->when(filled($dateUntil), fn ($query) => $query->whereDate('trs_date', '<=', $dateUntil))
            ->when(filled($supplierId), fn ($query) => $query->where('supplier_id', $supplierId))
            ->when(filled($trsType) && $trsType !== '', fn ($query) => $query->where('trs_type', (int) $trsType))
            ->with('supplier')
            ->orderBy('trs_date')
            ->orderBy('trs_number')
            ->get();
    }

    protected function stream(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'margin_top' => 1.0,
                'margin_bottom' => 1.0,
                'margin_left' => 1.0,
                'margin_right' => 1.0,
            ])
            ->stream($filename.'.pdf');
    }
}
