<?php

namespace App\Filament\Resources\HaircutResource\Pages;

use App\Filament\Resources\HaircutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHaircuts extends ListRecords
{
    protected static string $resource = HaircutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
