<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyRewardResource\Pages;
use App\Models\LoyaltyReward;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyRewardResource extends Resource
{
    protected static ?string $model = LoyaltyReward::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'Premi clienti';
    protected static ?string $modelLabel = 'Premio cliente';
    protected static ?string $pluralModelLabel = 'Premi clienti';
    protected static ?string $navigationGroup = 'Fidelity';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Cliente')
                ->relationship('user', 'name')
                ->searchable(['name', 'surname', 'email', 'phone'])
                ->preload()
                ->required(),

            Forms\Components\Select::make('loyalty_reward_rule_id')
                ->label('Regola')
                ->relationship('rule', 'name')
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Select::make('reward_service_id')
                ->label('Servizio premio')
                ->relationship('rewardService', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\TextInput::make('title')
                ->label('Titolo')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->label('Descrizione')
                ->nullable(),

            Forms\Components\TextInput::make('points_cost')
                ->label('Punti scalati')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            Forms\Components\Select::make('status')
                ->label('Stato')
                ->options([
                    LoyaltyReward::STATUS_AVAILABLE => 'Disponibile',
                    LoyaltyReward::STATUS_REDEEMED => 'Usato',
                    LoyaltyReward::STATUS_EXPIRED => 'Scaduto',
                ])
                ->required(),

            Forms\Components\TextInput::make('code')
                ->label('Codice')
                ->required()
                ->maxLength(20),

            Forms\Components\DateTimePicker::make('earned_at')
                ->label('Sbloccato il'),

            Forms\Components\DateTimePicker::make('redeemed_at')
                ->label('Usato il'),

            Forms\Components\DateTimePicker::make('expires_at')
                ->label('Scade il'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cliente')
                    ->formatStateUsing(fn ($state, LoyaltyReward $record): string => trim($record->user->name . ' ' . $record->user->surname))
                    ->searchable(['users.name', 'users.surname', 'users.email', 'users.phone'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Premio')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        LoyaltyReward::STATUS_AVAILABLE => 'Disponibile',
                        LoyaltyReward::STATUS_REDEEMED => 'Usato',
                        LoyaltyReward::STATUS_EXPIRED => 'Scaduto',
                        default => $state,
                    })
                    ->colors([
                        'success' => LoyaltyReward::STATUS_AVAILABLE,
                        'info' => LoyaltyReward::STATUS_REDEEMED,
                        'gray' => LoyaltyReward::STATUS_EXPIRED,
                    ]),

                Tables\Columns\TextColumn::make('code')
                    ->label('Codice')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('points_cost')
                    ->label('Punti')
                    ->sortable(),

                Tables\Columns\TextColumn::make('earned_at')
                    ->label('Sbloccato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Scadenza')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        LoyaltyReward::STATUS_AVAILABLE => 'Disponibile',
                        LoyaltyReward::STATUS_REDEEMED => 'Usato',
                        LoyaltyReward::STATUS_EXPIRED => 'Scaduto',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('redeem')
                    ->label('Segna usato')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (LoyaltyReward $record): bool => $record->status === LoyaltyReward::STATUS_AVAILABLE)
                    ->action(fn (LoyaltyReward $record) => app(\App\Services\LoyaltyService::class)->redeem($record)),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRewards::route('/'),
            'create' => Pages\CreateLoyaltyReward::route('/create'),
            'edit' => Pages\EditLoyaltyReward::route('/{record}/edit'),
        ];
    }
}
