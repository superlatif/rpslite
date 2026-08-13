<?php

namespace App\Filament\Actions;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Throwable;

class SafeDeleteAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->action(function (Model $record): void {
            try {
                $record->delete();
            } catch (Throwable $exception) {
                if ($this->isForeignKeyViolation($exception)) {
                    $title = $this->getRecordTitle($record) ?? "#{$record->getKey()}";

                    $this->failureNotification(
                        Notification::make()
                            ->danger()
                            ->title('Data tidak dapat dihapus')
                            ->body("'{$title}' masih digunakan oleh data lain, sehingga tidak dapat dihapus.")
                    );

                    $this->failure();

                    return;
                }

                throw $exception;
            }

            $this->success();
        });
    }

    protected function isForeignKeyViolation(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        return $exception->getCode() === '23000'
            && str_contains($exception->getMessage(), 'FOREIGN KEY');
    }
}
