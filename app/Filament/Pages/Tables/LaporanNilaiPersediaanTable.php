<?php

namespace App\Filament\Pages\Tables;

use App\Models\TbStock;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class LaporanNilaiPersediaanTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(TbStock::query())
            ->columns([
                TextColumn::make('code')
                    ->label('Kode'),
                TextColumn::make('descr')
                    ->label('Nama Barang')
                    ->searchable(),
                TextColumn::make('satuan')
                    ->label('Satuan'),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric(0)
                    ->alignEnd()
                    ->summarize(
                        Sum::make('total_stok')
                            ->label('Total Stok')
                            ->numeric(0),
                    ),
                TextColumn::make('harga_pokok')
                    ->label('Harga Pokok')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('nilai_persediaan')
                    ->label('Nilai Persediaan')
                    ->numeric(2, '.', ',')
                    ->alignEnd()
                    ->state(fn (TbStock $record): float => round(
                        (float) $record->stock * (float) $record->harga_pokok,
                        2,
                    ))
                    ->sortable(query: fn (EloquentBuilder $query, string $direction): EloquentBuilder => $query->orderByRaw('stock * harga_pokok '.$direction))
                    ->summarize(
                        Summarizer::make('total_nilai')
                            ->label('Total Nilai')
                            ->numeric(2, '.', ',')
                            ->using(fn (QueryBuilder $query): float => (float) $query->sum(DB::raw('stock * harga_pokok'))),
                    ),
                TextColumn::make('cate.descr')
                    ->label('Kategori'),
            ])
            ->filters([
                SelectFilter::make('tb_cate_id')
                    ->label('Kategori')
                    ->relationship('cate', 'descr')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('stok_tersedia')
                    ->label('Hanya stok di atas nol')
                    ->default(true)
                    ->queries(
                        true: fn (EloquentBuilder $query): EloquentBuilder => $query->where('stock', '>', 0),
                        false: fn (EloquentBuilder $query): EloquentBuilder => $query,
                        blank: fn (EloquentBuilder $query): EloquentBuilder => $query,
                    ),
            ])
            ->defaultSort('descr');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildRows(?string $cateId = null, mixed $onlyAvailable = null): array
    {
        $query = TbStock::query()
            ->with('cate')
            ->orderBy('descr');

        if (filled($cateId)) {
            $query->where('tb_cate_id', $cateId);
        }

        if ((int) $onlyAvailable === 1) {
            $query->where('stock', '>', 0);
        }

        return $query
            ->get()
            ->map(fn (TbStock $stock): array => [
                'code' => (string) $stock->code,
                'descr' => (string) $stock->descr,
                'satuan' => (string) ($stock->satuan ?? ''),
                'stock' => (int) $stock->stock,
                'harga_pokok' => (float) $stock->harga_pokok,
                'nilai_persediaan' => round((float) $stock->stock * (float) $stock->harga_pokok, 2),
                'kategori' => (string) ($stock->cate?->descr ?? ''),
            ])
            ->all();
    }
}
