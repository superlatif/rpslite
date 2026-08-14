<?php

namespace App\Filament\Widgets;

use App\Models\TbStock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Stok Menipis & Habis')
            ->description('Barang dengan stok ≤ 5 butuh re-stock segera')
            ->query(
                fn (): Builder => TbStock::query()
                    ->with('cate')
                    ->where('stock', '<=', 5)
                    ->orderBy('stock')
                    ->orderBy('descr'),
            )
            ->columns([
                TextColumn::make('code')
                    ->label('Kode'),
                TextColumn::make('descr')
                    ->label('Nama Barang')
                    ->searchable(),
                TextColumn::make('cate.descr')
                    ->label('Kategori'),
                TextColumn::make('satuan')
                    ->label('Satuan'),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric(0)
                    ->alignEnd()
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => (int) $state === 0 ? 'Habis' : (string) (int) $state)
                    ->color(fn (mixed $state): string => (int) $state === 0 ? 'danger' : 'warning'),
                TextColumn::make('harga_pokok')
                    ->label('Harga Pokok')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
            ])
            ->paginated(false);
    }
}
