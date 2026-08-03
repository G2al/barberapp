<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
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
                ->nullable()
                ->columnSpanFull(),

            Forms\Components\Select::make('department')
                ->label('Reparto')
                ->options([
                    'hair' => 'Parrucchiera',
                    'beauty' => 'Estetica',
                ])
                ->required()
                ->native(false)
                ->default('hair'),

            Forms\Components\Select::make('price_type')
                ->label('Modalita prezzo')
                ->options([
                    'fixed' => 'Prezzo fisso',
                    'starting_from' => 'A partire da',
                ])
                ->required()
                ->native(false)
                ->default('fixed'),

            Forms\Components\TextInput::make('price')
                ->label('Prezzo base')
                ->prefix('EUR')
                ->numeric()
                ->step('0.01')
                ->nullable(),

            Forms\Components\TextInput::make('duration')
                ->label('Durata totale (minuti)')
                ->numeric()
                ->minValue(5)
                ->required(),

            Forms\Components\TextInput::make('loyalty_points')
                ->label('Punti fidelity')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            Forms\Components\Toggle::make('is_active')
                ->label('Prenotabile online')
                ->default(true),

            Forms\Components\Repeater::make('phases')
                ->label('Fasi del servizio')
                ->relationship()
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Fase')
                        ->required(),
                    Forms\Components\TextInput::make('duration')
                        ->label('Minuti')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    Forms\Components\Toggle::make('staff_required')
                        ->label('Professionista impegnata')
                        ->default(true),
                ])
                ->orderColumn('position')
                ->columns(3)
                ->collapsed()
                ->columnSpanFull()
                ->helperText('Configurazione preparatoria: le fasi non modificano ancora gli slot finche i tempi non saranno confermati.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Servizio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department')
                    ->label('Reparto')
                    ->formatStateUsing(fn (string $state): string => $state === 'beauty' ? 'Estetica' : 'Parrucchiera')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'beauty' ? 'danger' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->formatStateUsing(fn ($state, Service $record): string => $record->formatted_price)
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Durata')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('loyalty_points')
                    ->label('Punti')
                    ->badge()
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Online'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->label('Reparto')
                    ->options([
                        'hair' => 'Parrucchiera',
                        'beauty' => 'Estetica',
                    ]),
                Tables\Filters\SelectFilter::make('price_type')
                    ->label('Modalita prezzo')
                    ->options([
                        'fixed' => 'Prezzo fisso',
                        'starting_from' => 'A partire da',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
