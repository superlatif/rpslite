<?php

namespace App\Filament\Resources\TbSuppliers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class TbSupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->columnSpanFull()
                    ->inlineLabel(true)
                    ->schema([
                        TextInput::make('descr')
                            ->label('Nama Supplier')
                            ->maxLength(30)
                            ->required(),
                        TextInput::make('alamat')
                            ->label('Alamat')
                            ->maxLength(30),
                        TextInput::make('phone')
                            ->label('No. HP / WA')
                            ->maxLength(30),
                    ]),
            ]);
    }
}
