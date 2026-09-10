<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceRestrictionsRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceRestrictions';

    protected static ?string $title = 'Servizi disabilitati';

    protected static ?string $modelLabel = 'limitazione';

    protected static ?string $pluralModelLabel = 'limitazioni';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('service_id')
                ->label('Servizio')
                ->relationship('service', 'name')
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Select::make('staff_id')
                ->label('Staff')
                ->relationship('staff', 'first_name')
                ->getOptionLabelFromRecordUsing(fn ($record): string => trim($record->first_name . ' ' . $record->last_name))
                ->placeholder('Tutti gli staff')
                ->searchable()
                ->preload()
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service.name')
            ->columns([
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Servizio')
                    ->searchable(),
                Tables\Columns\TextColumn::make('staff.first_name')
                    ->label('Staff')
                    ->formatStateUsing(fn ($state, $record): string => $record->staff
                        ? trim($record->staff->first_name . ' ' . $record->staff->last_name)
                        : 'Tutti gli staff'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Inserita il')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
