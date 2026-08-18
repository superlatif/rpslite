<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Tables\LaporanKartuStokTable;
use App\Models\TbStock;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class LaporanKartuStok extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?string $navigationLabel = 'Kartu Stok';

    protected static ?string $title = 'Laporan Kartu Stok';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'laporan-kartu-stok';

    public ?string $stock_id = null;

    public ?string $date_from = null;

    public ?string $date_until = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Tampilkan Laporan')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->modalSubmitActionLabel('Tampilkan')
                ->modalCancelActionLabel('Batal')
                ->schema([
                    Select::make('stock_id')
                        ->label('Nama Barang')
                        ->options(fn (): array => TbStock::query()
                            ->barang()
                            ->orderBy('descr')
                            ->pluck('descr', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default($this->stock_id),
                    DatePicker::make('date_from')
                        ->label('Periode Dari')
                        ->default($this->date_from ?? now()->startOfMonth()->toDateString())
                        ->required(),
                    DatePicker::make('date_until')
                        ->label('Periode Sampai')
                        ->default($this->date_until ?? now()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->stock_id = $data['stock_id'];
                    $this->date_from = $data['date_from'];
                    $this->date_until = $data['date_until'];
                }),

            Action::make('cetak')
                ->label('Cetak')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (): string => route('filament.admin.laporan-kartu-stok.cetak', [
                    'stock_id' => $this->stock_id,
                    'date_from' => $this->date_from,
                    'date_until' => $this->date_until,
                ]))
                ->openUrlInNewTab()
                ->disabled(fn (): bool => blank($this->stock_id)),

            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => route('filament.admin.laporan-kartu-stok.export', [
                    'stock_id' => $this->stock_id,
                    'date_from' => $this->date_from,
                    'date_until' => $this->date_until,
                ]))
                ->disabled(fn (): bool => blank($this->stock_id)),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...$this->getInfoComponents(),
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return LaporanKartuStokTable::configure(
            $table,
            fn (): array => filled($this->stock_id)
                ? LaporanKartuStokTable::buildRows(
                    (string) $this->stock_id,
                    (string) ($this->date_from ?? ''),
                    (string) ($this->date_until ?? ''),
                )
                : [],
        );
    }

    /**
     * @return array<int, Section>
     */
    protected function getInfoComponents(): array
    {
        if (blank($this->stock_id) || blank($this->date_from) || blank($this->date_until)) {
            return [];
        }

        $stock = TbStock::find($this->stock_id);

        return [
            Section::make("Kartu Stok: {$stock?->code} - {$stock?->descr}")
                ->description('Periode: '
                    .Carbon::parse($this->date_from)->format('d M Y')
                    .' - '
                    .Carbon::parse($this->date_until)->format('d M Y')),
        ];
    }
}
