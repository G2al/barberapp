<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyRewardRuleResource\Pages;
use App\Models\LoyaltyRewardRule;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyRewardRuleResource extends Resource
{
    protected static ?string $model = LoyaltyRewardRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Regole punti';
    protected static ?string $modelLabel = 'Regola punti';
    protected static ?string $pluralModelLabel = 'Regole punti';
    protected static ?string $navigationGroup = 'Fidelity';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Regola')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome interno')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('type')
                        ->label('Tipo regola')
                        ->options([
                            LoyaltyRewardRule::TYPE_POINTS_THRESHOLD => 'Soglia punti',
                            LoyaltyRewardRule::TYPE_SERVICE_COUNT => 'Numero servizi',
                        ])
                        ->live()
                        ->required(),

                    Forms\Components\TextInput::make('points_required')
                        ->label('Punti richiesti')
                        ->numeric()
                        ->minValue(1)
                        ->required(fn (Get $get): bool => $get('type') === LoyaltyRewardRule::TYPE_POINTS_THRESHOLD)
                        ->visible(fn (Get $get): bool => $get('type') === LoyaltyRewardRule::TYPE_POINTS_THRESHOLD),

                    Forms\Components\Select::make('service_id')
                        ->label('Servizio da contare')
                        ->relationship('service', 'name')
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('type') === LoyaltyRewardRule::TYPE_SERVICE_COUNT)
                        ->visible(fn (Get $get): bool => $get('type') === LoyaltyRewardRule::TYPE_SERVICE_COUNT),

                    Forms\Components\TextInput::make('visits_required')
                        ->label('Numero servizi richiesti')
                        ->numeric()
                        ->minValue(1)
                        ->required(fn (Get $get): bool => $get('type') === LoyaltyRewardRule::TYPE_SERVICE_COUNT)
                        ->visible(fn (Get $get): bool => $get('type') === LoyaltyRewardRule::TYPE_SERVICE_COUNT),

                    Forms\Components\Toggle::make('is_repeatable')
                        ->label('Ripetibile')
                        ->default(true),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Attiva')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Premio')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('reward_title')
                        ->label('Titolo premio')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('reward_service_id')
                        ->label('Servizio omaggio')
                        ->relationship('rewardService', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\TextInput::make('reward_points_cost')
                        ->label('Punti scalati al riscatto')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\TextInput::make('expires_after_days')
                        ->label('Scadenza dopo giorni')
                        ->numeric()
                        ->minValue(1)
                        ->nullable(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordine')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\Textarea::make('reward_description')
                        ->label('Descrizione premio')
                        ->columnSpanFull()
                        ->rows(3)
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Regola')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => $state === LoyaltyRewardRule::TYPE_SERVICE_COUNT ? 'Servizi' : 'Punti')
                    ->colors([
                        'warning' => LoyaltyRewardRule::TYPE_POINTS_THRESHOLD,
                        'info' => LoyaltyRewardRule::TYPE_SERVICE_COUNT,
                    ]),

                Tables\Columns\TextColumn::make('points_required')
                    ->label('Punti')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Servizio contato')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('visits_required')
                    ->label('N. servizi')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reward_title')
                    ->label('Premio')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        LoyaltyRewardRule::TYPE_POINTS_THRESHOLD => 'Soglia punti',
                        LoyaltyRewardRule::TYPE_SERVICE_COUNT => 'Numero servizi',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Attiva'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRewardRules::route('/'),
            'create' => Pages\CreateLoyaltyRewardRule::route('/create'),
            'edit' => Pages\EditLoyaltyRewardRule::route('/{record}/edit'),
        ];
    }
}
