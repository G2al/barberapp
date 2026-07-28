<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyPointTransactionResource\Pages;
use App\Models\LoyaltyPointTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyPointTransactionResource extends Resource
{
    protected static ?string $model = LoyaltyPointTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Movimenti punti';
    protected static ?string $modelLabel = 'Movimento punti';
    protected static ?string $pluralModelLabel = 'Movimenti punti';
    protected static ?string $navigationGroup = 'Fidelity';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Cliente')
                ->relationship('user', 'name')
                ->searchable(['name', 'surname', 'email', 'phone'])
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('points')
                ->label('Punti')
                ->numeric()
                ->required(),

            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    LoyaltyPointTransaction::TYPE_EARNED => 'Guadagnati',
                    LoyaltyPointTransaction::TYPE_REDEEMED => 'Scalati',
                    LoyaltyPointTransaction::TYPE_ADJUSTMENT => 'Correzione manuale',
                ])
                ->default(LoyaltyPointTransaction::TYPE_ADJUSTMENT)
                ->required(),

            Forms\Components\Select::make('service_id')
                ->label('Servizio')
                ->relationship('service', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\TextInput::make('description')
                ->label('Descrizione')
                ->maxLength(255)
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cliente')
                    ->formatStateUsing(fn ($state, LoyaltyPointTransaction $record): string => trim($record->user->name . ' ' . $record->user->surname))
                    ->searchable(['users.name', 'users.surname', 'users.email', 'users.phone'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('points')
                    ->label('Punti')
                    ->badge()
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        LoyaltyPointTransaction::TYPE_EARNED => 'Guadagnati',
                        LoyaltyPointTransaction::TYPE_REDEEMED => 'Scalati',
                        LoyaltyPointTransaction::TYPE_ADJUSTMENT => 'Correzione',
                        default => $state,
                    })
                    ->colors([
                        'success' => LoyaltyPointTransaction::TYPE_EARNED,
                        'danger' => LoyaltyPointTransaction::TYPE_REDEEMED,
                        'warning' => LoyaltyPointTransaction::TYPE_ADJUSTMENT,
                    ]),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Servizio')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->limit(45),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyPointTransactions::route('/'),
            'create' => Pages\CreateLoyaltyPointTransaction::route('/create'),
            'edit' => Pages\EditLoyaltyPointTransaction::route('/{record}/edit'),
        ];
    }
}
