<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClosedSlotResource\Pages;
use App\Filament\Resources\ClosedSlotResource\RelationManagers;
use App\Models\ClosedSlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClosedSlotResource extends Resource
{
    protected static ?string $model = ClosedSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Giorni Chiusi';

    protected static ?string $modelLabel = 'Giorno Chiuso';

    protected static ?string $pluralModelLabel = 'Giorni Chiusi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('staff_id')
                    ->relationship('staff', 'first_name')
                    ->label('Staff')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\DatePicker::make('date')
                    ->label('Data')
                    ->required()
                    ->minDate(now()),

                Forms\Components\TimePicker::make('time')
                    ->label('Orario (lascia vuoto per giorno intero)')
                    ->nullable()
                    ->seconds(false),

                Forms\Components\TextInput::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->placeholder('Es: Natale, Riposo, Chiuso per ferie')
                    ->maxLength(255),
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

                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('time')
                    ->label('Orario')
                    ->default('Giorno intero')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('staff_id')
                    ->relationship('staff', 'first_name')
                    ->label('Staff'),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Da'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, $date) => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'] ?? null,
                                fn (Builder $query, $date) => $query->whereDate('date', '<=', $date),
                            );
                    }),
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
            ->defaultSort('date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClosedSlots::route('/'),
            'create' => Pages\CreateClosedSlot::route('/create'),
            'edit' => Pages\EditClosedSlot::route('/{record}/edit'),
        ];
    }
}
