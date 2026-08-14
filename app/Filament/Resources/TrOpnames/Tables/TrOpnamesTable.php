<?php

namespace App\Filament\Resources\TrOpnames\Tables;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrOpnamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trs_number')
                    ->label('No. Transaksi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trs_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('details_count')
                    ->label('Jumlah Barang'),
            ])
            ->defaultSort('trs_number', 'desc')
            ->filters([
                Filter::make('trs_date')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('Transaksi mulai dari')
                            ->default(now())
                            ->displayFormat('d-m-Y'),
                        DatePicker::make('date_until')
                            ->label('sampai dengan')
                            ->default(now())
                            ->displayFormat('d-m-Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('trs_date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('trs_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! filled($data['date_from']) && ! filled($data['date_until'])) {
                            return null;
                        }

                        $indicators = [];

                        if (filled($data['date_from'])) {
                            $indicators[] = 'Mulai: '.Carbon::parse($data['date_from'])->format('d M Y');
                        }

                        if (filled($data['date_until'])) {
                            $indicators[] = 'Sampai: '.Carbon::parse($data['date_until'])->format('d M Y');
                        }

                        return implode(' - ', $indicators);
                    }),
            ])->persistFiltersInSession()

            ->toolbarActions([
                //
            ]);
    }
}
