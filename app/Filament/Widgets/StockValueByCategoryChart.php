<?php

namespace App\Filament\Widgets;

use App\Models\TbStock;
use Filament\Widgets\ChartWidget;

class StockValueByCategoryChart extends ChartWidget
{
    protected ?string $heading = 'Nilai Stok per Kategori';

    protected ?string $description = 'Total nilai stok (stock × harga_pokok) per kategori barang';

    protected function getData(): array
    {
        $rows = TbStock::query()
            ->barang()
            ->leftJoin('tb_cates', 'tb_stocks.tb_cate_id', '=', 'tb_cates.id')
            ->selectRaw('COALESCE(tb_cates.descr, \'Tanpa Kategori\') as category, SUM(tb_stocks.stock * tb_stocks.harga_pokok) as value')
            ->groupBy('category')
            ->orderByDesc('value')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Nilai Stok',
                    'data' => $rows->map(fn ($row): float => (float) $row->value),
                ],
            ],
            'labels' => $rows->pluck('category'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
