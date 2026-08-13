<?php

namespace App\Filament\Resources\TbCates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TbCateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::components());
    }

    public static function components(): array
    {
        return [
            TextInput::make('descr')
                ->label('Deskripsi')
                ->maxLength(30),
        ];
    }
}
