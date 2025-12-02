<?php

namespace App\Filament\Resources\StaffAvailabilityResource\Pages;

use App\Filament\Resources\StaffAvailabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaffAvailabilities extends ListRecords
{
    protected static string $resource = StaffAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
