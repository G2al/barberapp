<?php

namespace App\Filament\Resources\LoyaltyRewardRuleResource\Pages;

use App\Filament\Resources\LoyaltyRewardRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyRewardRules extends ListRecords
{
    protected static string $resource = LoyaltyRewardRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
