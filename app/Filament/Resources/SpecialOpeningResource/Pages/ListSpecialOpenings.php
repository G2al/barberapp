<?php

namespace App\Filament\Resources\SpecialOpeningResource\Pages;

use App\Filament\Resources\SpecialOpeningResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSpecialOpenings extends ListRecords
{
    protected static string $resource = SpecialOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
