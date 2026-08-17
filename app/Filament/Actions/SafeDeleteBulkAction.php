<?php

namespace App\Filament\Actions;

use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class SafeDeleteBulkAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->action(function (DeleteBulkAction $action, EloquentCollection|Collection|LazyCollection $records): void {
            try {
                DB::transaction(function () use ($records): void {
                    $records->each(function (Model $record): void {
                        if (! $record->delete()) {
                            throw new \RuntimeException("Gagal menghapus record #{$record->getKey()}.");
                        }
                    });
                });

                $action->success();
            } catch (\Throwable $exception) {
                report($exception);

                $action->failureNotification(
                    Notification::make()
                        ->danger()
                        ->title('Data tidak dapat dihapus')
                        ->body('Beberapa data masih digunakan oleh data lain, sehingga tidak ada yang dihapus.')
                );

                $action->failure();
            }
        });
    }
}
