<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffResource\Pages;
use App\Models\Staff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Staff';
    protected static ?string $label = 'Barbiere';
    protected static ?string $pluralLabel = 'Barbieri';

    protected static ?string $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->label('Nome')
                    ->required(),

                Forms\Components\TextInput::make('last_name')
                    ->label('Cognome')
                    ->required(),

                Forms\Components\TextInput::make('role')
                    ->label('Ruolo')
                    ->maxLength(50),

                Forms\Components\TextInput::make('phone')
                    ->label('Telefono'),

                Forms\Components\FileUpload::make('image')
                    ->label('Foto')
                    ->image()
                    ->directory('staff')
                    ->imageEditor()
                    ->imagePreviewHeight('150')
                    ->downloadable()
                    ->nullable(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Attivo')
                    ->default(true),

                // 🔥 QUI SELEZIONI I SERVIZI ABILITATI PER QUESTO BARBIERE
                Forms\Components\Select::make('services')
                    ->label('Servizi abilitati')
                    ->multiple()
                    ->relationship('services', 'name') // usa la relazione Staff::services()
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Foto')
                    ->circular()
                    ->size(48)
                    ->defaultImageUrl(null),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo'),

                Tables\Columns\TextColumn::make('phone'),

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
            'index'  => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit'   => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
