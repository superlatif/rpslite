<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Tables\LaporanPiutangTable;
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

class LaporanPiutang extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Piutang (Aging)';

    protected static ?string $title = 'Laporan Piutang (Aging)';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'laporan-piutang';

    public ?string $asof = null;

    public ?string $customer_id = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Tampilkan Laporan')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->modalSubmitActionLabel('Tampilkan')
                ->modalCancelActionLabel('Batal')
                ->schema([
                    DatePicker::make('asof')
                        ->label('Per Tanggal')
                        ->default($this->asof ?? now()->toDateString())
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
                ])
                ->action(function (array $data): void {
                    $this->asof = $data['asof'];
                    $this->customer_id = $data['customer_id'] ?? null;
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
        return LaporanPiutangTable::configure(
            $table,
            fn (): array => filled($this->asof)
                ? LaporanPiutangTable::buildRows(
                    (string) $this->asof,
                    (string) ($this->customer_id ?? ''),
                )
                : [],
        );
    }

    /**
     * @return array<int, Section>
     */
    protected function getInfoComponents(): array
    {
        if (blank($this->asof)) {
            return [];
        }

        $parts = [
            'Per Tanggal: '.Carbon::parse($this->asof)->format('d M Y'),
        ];

        if (filled($this->customer_id)) {
            $customer = Customer::find($this->customer_id);
            $parts[] = 'Customer: '.($customer?->descr ?? '-');
        }

        return [
            Section::make('Laporan Piutang (Aging)')
                ->description(implode('  |  ', $parts)),
        ];
    }
}
