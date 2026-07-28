<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';
    protected static ?string $navigationLabel = 'Servizi';
    protected static ?string $modelLabel = 'Servizio';
    protected static ?string $pluralModelLabel = 'Servizi';

    protected static ?string $navigationGroup = 'Servizi & Prodotti';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome del servizio')
                ->required(),

            Forms\Components\Textarea::make('description')
                ->label('Descrizione')
                ->nullable(),

            Forms\Components\TextInput::make('price')
                ->label('Prezzo')
                ->numeric()
                ->step('0.01')
                ->nullable(),

            Forms\Components\TextInput::make('duration')
                ->label('Durata (minuti)')
                ->numeric()
                ->required(),

            Forms\Components\TextInput::make('loyalty_points')
                ->label('Punti fidelity')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            Forms\Components\Toggle::make('is_active')
                ->label('Attivo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Servizio')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('price')
                ->label('Prezzo €')
                ->sortable(),

            Tables\Columns\TextColumn::make('duration')
                ->label('Durata')
                ->suffix(' min')
                ->sortable(),

            Tables\Columns\TextColumn::make('loyalty_points')
                ->label('Punti')
                ->badge()
                ->color('warning')
                ->sortable(),

            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->label('Attivo'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit'   => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
