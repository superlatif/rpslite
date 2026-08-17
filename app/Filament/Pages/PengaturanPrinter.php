<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class PengaturanPrinter extends Page
{
    protected string $view = 'filament.pages.pengaturan-printer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan Printer';

    protected static ?string $title = 'Pengaturan Printer';

    protected static ?string $slug = 'pengaturan-printer';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'paper_size' => Setting::get('thermal.paper_size', config('thermal.paper_size', '80')),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Select::make('paper_size')
                        ->label('Lebar Kertas Struk')
                        ->options([
                            '58' => '58 mm (struk sempit)',
                            '80' => '80 mm (struk lebar)',
                        ])
                        ->helperText('Ukuran kertas ini dipakai untuk semua cetak struk penjualan.')
                        ->required(),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan')
                                ->submit('save')
                                ->icon(Heroicon::OutlinedCheck)
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('thermal.paper_size', $data['paper_size']);

        Notification::make()
            ->success()
            ->title('Pengaturan tersimpan')
            ->send();
    }
}
