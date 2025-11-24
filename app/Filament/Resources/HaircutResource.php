<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HaircutResource\Pages;
use App\Models\Haircut;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HaircutResource extends Resource
{
    protected static ?string $model = Haircut::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $navigationLabel = 'Tagli';

    protected static ?string $modelLabel = 'Taglio';

    protected static ?string $pluralModelLabel = 'Tagli';

    protected static ?string $navigationGroup = 'Servizi & Prodotti';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->label('Nome'),

                Forms\Components\Textarea::make('description')
                    ->nullable()
                    ->label('Descrizione'),

                Forms\Components\TextInput::make('photo')
                    ->nullable()
                    ->label('Foto URL'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Attivo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nome'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->label('Descrizione'),

                Tables\Columns\BooleanColumn::make('is_active')
                    ->label('Attivo'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Creato'),
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
            'index' => Pages\ListHaircuts::route('/'),
            'create' => Pages\CreateHaircut::route('/create'),
            'edit' => Pages\EditHaircut::route('/{record}/edit'),
        ];
    }
}