<?php

namespace App\Filament\Resources\LoyaltyPointTransactionResource\Pages;

use App\Filament\Resources\LoyaltyPointTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyPointTransaction extends EditRecord
{
    protected static string $resource = LoyaltyPointTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
