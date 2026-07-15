<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AvailabilityResource\Pages;
use App\Models\Availability;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AvailabilityResource extends Resource
{
    protected static ?string $model = Availability::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Orari Salone';

    protected static ?string $modelLabel = 'Orario';

    protected static ?string $pluralModelLabel = 'Orari Salone';

    protected static ?string $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('weekday')
                    ->options([
                        0 => 'Domenica',
                        1 => 'Lunedì',
                        2 => 'Martedì',
                        3 => 'Mercoledì',
                        4 => 'Giovedì',
                        5 => 'Venerdì',
                        6 => 'Sabato',
                    ])
                    ->required()
                    ->label('Giorno della settimana'),

                Forms\Components\Select::make('slot_type')
                    ->options([
                        'morning' => 'Mattina',
                        'afternoon' => 'Pomeriggio',
                        'continuous' => 'Orario continuo',
                    ])
                    ->required()
                    ->label('Fascia oraria'),

                Forms\Components\TimePicker::make('start_time')
                    ->required()
                    ->label('Inizio'),

                Forms\Components\TimePicker::make('end_time')
                    ->required()
                    ->label('Fine'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Attivo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('weekday')
                    ->formatStateUsing(fn ($state) => [
                        0 => 'Domenica',
                        1 => 'Lunedì',
                        2 => 'Martedì',
                        3 => 'Mercoledì',
                        4 => 'Giovedì',
                        5 => 'Venerdì',
                        6 => 'Sabato',
                    ][$state])
                    ->sortable()
                    ->label('Giorno'),

                Tables\Columns\TextColumn::make('slot_type')
                    ->formatStateUsing(fn ($state) => [
                        'morning' => 'Mattina',
                        'afternoon' => 'Pomeriggio',
                        'continuous' => 'Orario continuo',
                    ][$state] ?? $state)
                    ->label('Fascia'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Inizio'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Fine'),

                Tables\Columns\BooleanColumn::make('is_active')
                    ->label('Attivo'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Attivo'),
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
            'index' => Pages\ListAvailabilities::route('/'),
            'create' => Pages\CreateAvailability::route('/create'),
            'edit' => Pages\EditAvailability::route('/{record}/edit'),
        ];
    }
}
