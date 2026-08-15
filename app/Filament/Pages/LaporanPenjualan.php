<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Tables\LaporanPenjualanTable;
use App\Models\Customer;
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

class LaporanPenjualan extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    protected static ?string $title = 'Laporan Penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'laporan-penjualan';

    public ?string $date_from = null;

    public ?string $date_until = null;

    public ?string $customer_id = null;

    public ?string $group_by = 'barang';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Tampilkan Laporan')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->modalSubmitActionLabel('Tampilkan')
                ->modalCancelActionLabel('Batal')
                ->schema([
                    DatePicker::make('date_from')
                        ->label('Periode Dari')
                        ->default($this->date_from ?? now()->startOfMonth()->toDateString())
                        ->required(),
                    DatePicker::make('date_until')
                        ->label('Periode Sampai')
                        ->default($this->date_until ?? now()->toDateString())
                        ->required(),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(fn (): array => Customer::query()
                            ->orderBy('descr')
                            ->pluck('descr', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->default($this->customer_id),
                    Select::make('group_by')
                        ->label('Ringkasan Per')
                        ->options([
                            'barang' => 'Barang',
                            'customer' => 'Customer',
                        ])
                        ->default($this->group_by ?? 'barang')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->date_from = $data['date_from'];
                    $this->date_until = $data['date_until'];
                    $this->customer_id = $data['customer_id'] ?? null;
                    $this->group_by = $data['group_by'];
                }),
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
        return LaporanPenjualanTable::configure(
            $table,
            fn (): array => filled($this->date_from) && filled($this->date_until)
                ? LaporanPenjualanTable::buildRows(
                    (string) $this->date_from,
                    (string) $this->date_until,
                    (string) ($this->customer_id ?? ''),
                    (string) ($this->group_by ?? 'barang'),
                )
                : [],
        );
    }

    /**
     * @return array<int, Section>
     */
    protected function getInfoComponents(): array
    {
        if (blank($this->date_from) || blank($this->date_until)) {
            return [];
        }

        $parts = [
            'Periode: '
                .Carbon::parse($this->date_from)->format('d M Y')
                .' - '
                .Carbon::parse($this->date_until)->format('d M Y'),
        ];

        if (filled($this->customer_id)) {
            $customer = Customer::find($this->customer_id);
            $parts[] = 'Customer: '.($customer?->descr ?? '-');
        }

        $parts[] = 'Ringkasan Per: '.($this->group_by === 'customer' ? 'Customer' : 'Barang');

        return [
            Section::make('Laporan Penjualan')
                ->description(implode('  |  ', $parts)),
        ];
    }
}
