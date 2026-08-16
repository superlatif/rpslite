<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Tables\LaporanNilaiPersediaanTable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class LaporanNilaiPersediaan extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Nilai Persediaan';

    protected static ?string $title = 'Laporan Nilai Persediaan';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'laporan-nilai-persediaan';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetak')
                ->label('Cetak')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (): string => $this->getReportUrl('laporan-nilai-persediaan.cetak'))
                ->openUrlInNewTab(),

            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => $this->getReportUrl('laporan-nilai-persediaan.export')),
        ];
    }

    protected function getReportUrl(string $routeName): string
    {
        $filters = $this->tableFilters ?? [];

        return route("filament.admin.{$routeName}", [
            'cate_id' => $filters['tb_cate_id']['value'] ?? null,
            'only_available' => $filters['stok_tersedia']['value'] ?? null,
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return LaporanNilaiPersediaanTable::configure($table);
    }
}
