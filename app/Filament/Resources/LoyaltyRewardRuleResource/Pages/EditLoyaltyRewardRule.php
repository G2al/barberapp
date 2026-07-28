<?php

namespace App\Filament\Resources\LoyaltyRewardRuleResource\Pages;

use App\Filament\Resources\LoyaltyRewardRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyRewardRule extends EditRecord
{
    protected static string $resource = LoyaltyRewardRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
