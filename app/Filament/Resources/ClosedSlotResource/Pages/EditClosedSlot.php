<?php

namespace App\Filament\Resources\ClosedSlotResource\Pages;

use App\Filament\Resources\ClosedSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClosedSlot extends EditRecord
{
    protected static string $resource = ClosedSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
