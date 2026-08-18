<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Tables\LaporanKartuPiutangTable;
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

class LaporanKartuPiutang extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $navigationLabel = 'Kartu Piutang';

    protected static ?string $title = 'Laporan Kartu Piutang';

    protected static string|UnitEnum|null $navigationGroup = 'Penjualan';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'laporan-kartu-piutang';

    public ?string $customer_id = null;

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
                    $this->customer_id = $data['customer_id'] ?? null;
                    $this->date_from = $data['date_from'];
                    $this->date_until = $data['date_until'];
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
        return LaporanKartuPiutangTable::configure(
            $table,
            fn (): array => filled($this->date_from) && filled($this->date_until)
                ? LaporanKartuPiutangTable::buildRows(
                    $this->customer_id,
                    (string) $this->date_from,
                    (string) $this->date_until,
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
            'Periode: '.Carbon::parse($this->date_from)->format('d M Y')
                .' - '.Carbon::parse($this->date_until)->format('d M Y'),
        ];

        if (filled($this->customer_id)) {
            $customer = Customer::find($this->customer_id);
            $parts[] = 'Customer: '.($customer?->descr ?? '-');
        } else {
            $parts[] = 'Semua Customer';
        }

        return [
            Section::make('Kartu Piutang')
                ->description(implode('  |  ', $parts)),
        ];
    }
}
