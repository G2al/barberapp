<?php

namespace App\Filament\Resources\ClosedSlotResource\Pages;

use App\Filament\Resources\ClosedSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClosedSlots extends ListRecords
{
    protected static string $resource = ClosedSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
