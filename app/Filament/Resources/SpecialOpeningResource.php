<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SpecialOpeningResource\Pages;
use App\Models\SpecialOpening;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SpecialOpeningResource extends Resource
{
    protected static ?string $model = SpecialOpening::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'Orari Speciali (Salone)';
    protected static ?string $modelLabel = 'Orario Speciale';
    protected static ?string $pluralModelLabel = 'Orari Speciali';
    protected static ?string $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->label('Data'),

                Forms\Components\TimePicker::make('start_time')
                    ->required()
                    ->seconds(false)
                    ->label('Inizio'),

                Forms\Components\TimePicker::make('end_time')
                    ->required()
                    ->seconds(false)
                    ->label('Fine'),

                Forms\Components\TextInput::make('note')
                    ->label('Nota')
                    ->maxLength(255)
                    ->nullable(),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Attivo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Data'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Inizio'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Fine'),

                Tables\Columns\TextColumn::make('note')
                    ->limit(40)
                    ->label('Nota'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Attivo'),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dal'),
                        Forms\Components\DatePicker::make('to')->label('Al'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    })
                    ->label('Intervallo date'),

                Tables\Filters\TernaryFilter::make('is_active')->label('Attivo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpecialOpenings::route('/'),
            'create' => Pages\CreateSpecialOpening::route('/create'),
            'edit' => Pages\EditSpecialOpening::route('/{record}/edit'),
        ];
    }
}
