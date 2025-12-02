<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffAvailabilityResource\Pages;
use App\Models\StaffAvailability;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StaffAvailabilityResource extends Resource
{
    protected static ?string $model = StaffAvailability::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Orari Staff';
    protected static ?string $modelLabel = 'Orario Staff';
    protected static ?string $pluralModelLabel = 'Orari Staff';
    protected static ?string $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('staff_id')
                    ->relationship('staff', 'first_name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Staff'),

                Forms\Components\Select::make('weekday')
                    ->options([
                        0 => 'Domenica',
                        1 => 'Lunedi',
                        2 => 'Martedi',
                        3 => 'Mercoledi',
                        4 => 'Giovedi',
                        5 => 'Venerdi',
                        6 => 'Sabato',
                    ])
                    ->required()
                    ->label('Giorno'),

                Forms\Components\TimePicker::make('start_time')
                    ->required()
                    ->seconds(false)
                    ->label('Inizio'),

                Forms\Components\TimePicker::make('end_time')
                    ->required()
                    ->seconds(false)
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
                Tables\Columns\TextColumn::make('staff.full_name')
                    ->label('Staff')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('weekday')
                    ->label('Giorno')
                    ->formatStateUsing(fn ($state) => [
                        0 => 'Domenica',
                        1 => 'Lunedi',
                        2 => 'Martedi',
                        3 => 'Mercoledi',
                        4 => 'Giovedi',
                        5 => 'Venerdi',
                        6 => 'Sabato',
                    ][$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Inizio'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Fine'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Attivo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('staff_id')
                    ->relationship('staff', 'first_name')
                    ->label('Staff'),

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
            ])
            ->defaultSort('weekday');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffAvailabilities::route('/'),
            'create' => Pages\CreateStaffAvailability::route('/create'),
            'edit' => Pages\EditStaffAvailability::route('/{record}/edit'),
        ];
    }
}
