<?php

namespace App\Filament\Resources\LoyaltyPointTransactionResource\Pages;

use App\Filament\Resources\LoyaltyPointTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyPointTransactions extends ListRecords
{
    protected static string $resource = LoyaltyPointTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
