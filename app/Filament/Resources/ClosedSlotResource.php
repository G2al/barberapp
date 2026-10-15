<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClosedSlotResource\Pages;
use App\Filament\Resources\ClosedSlotResource\RelationManagers;
use App\Models\ClosedSlot;
use App\Models\Staff;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                Forms\Components\Radio::make('closure_type')
                    ->label('Tipo di chiusura')
                    ->options([
                        'full_day' => 'Giorno/i interi',
                        'time_range' => 'Fascia oraria',
                        'legacy' => 'Orario singolo (record esistente)',
                    ])
                    ->default('full_day')
                    ->formatStateUsing(fn (?ClosedSlot $record): string => $record?->start_time && $record?->end_time
                        ? 'time_range'
                        : ($record?->time ? 'legacy' : 'full_day'))
                    ->dehydrated(false)
                    ->inline()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state): void {
                        if ($state === 'full_day') {
                            $set('time', null);
                            $set('start_time', null);
                            $set('end_time', null);
                        } elseif ($state === 'time_range') {
                            $set('time', null);
                            $set('end_date', null);
                        } else {
                            $set('start_time', null);
                            $set('end_time', null);
                            $set('end_date', null);
                        }
                    })
                    ->required(),

                Forms\Components\Radio::make('is_global')
                    ->label('Ambito chiusura')
                    ->options([
                        false => 'Staff specifico',
                        true => 'Tutto il negozio',
                    ])
                    ->default(false)
                    ->inline()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state): void {
                        if ((bool) $state) {
                            $set('staff_id', null);
                        }
                    })
                    ->required(),

                Forms\Components\Select::make('staff_id')
                    ->label('Staff')
                    ->options(fn () => Staff::query()
                        ->where('is_active', true)
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get()
                        ->pluck('full_name', 'id'))
                    ->required(fn (Get $get): bool => ! (bool) $get('is_global'))
                    ->hidden(fn (Get $get): bool => (bool) $get('is_global'))
                    ->searchable()
                    ->preload(),

                Forms\Components\DatePicker::make('date')
                    ->label('Data inizio')
                    ->required()
                    ->minDate(now()->startOfDay()),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Data fine')
                    ->helperText('Disponibile per la chiusura di giorni interi.')
                    ->nullable()
                    ->visible(fn (Get $get): bool => $get('closure_type') === 'full_day')
                    ->dehydrated(fn (Get $get): bool => $get('closure_type') === 'full_day')
                    ->minDate(fn (Get $get) => $get('date') ?: now()->startOfDay()),

                Forms\Components\TimePicker::make('time')
                    ->label('Orario singolo')
                    ->nullable()
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => $get('closure_type') === 'legacy')
                    ->dehydrated(fn (Get $get): bool => $get('closure_type') === 'legacy'),

                Forms\Components\TimePicker::make('start_time')
                    ->label('Ora inizio')
                    ->required(fn (Get $get): bool => $get('closure_type') === 'time_range')
                    ->visible(fn (Get $get): bool => $get('closure_type') === 'time_range')
                    ->seconds(false)
                    ->rules([
                        function (Get $get): \Closure {
                            return function (string $attribute, $value, \Closure $fail) use ($get): void {
                                $date = $get('date');
                                if (! $date || ! $value || ! Carbon::parse($date)->isToday()) {
                                    return;
                                }

                                $selected = Carbon::createFromFormat('Y-m-d H:i', $date.' '.substr((string) $value, 0, 5));
                                if ($selected->lessThanOrEqualTo(now())) {
                                    $fail('Per una chiusura di oggi, l\'ora di inizio deve essere futura.');
                                }
                            };
                        },
                    ]),

                Forms\Components\TimePicker::make('end_time')
                    ->label('Ora fine')
                    ->required(fn (Get $get): bool => $get('closure_type') === 'time_range')
                    ->visible(fn (Get $get): bool => $get('closure_type') === 'time_range')
                    ->seconds(false)
                    ->after('start_time'),

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
                Tables\Columns\TextColumn::make('scope')
                    ->label('Ambito')
                    ->state(fn (ClosedSlot $record): string => $record->is_global
                        ? 'Tutto il negozio'
                        : ($record->staff?->full_name ?? 'Staff non disponibile'))
                    ->badge()
                    ->color(fn (ClosedSlot $record): string => $record->is_global ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('date')
                    ->label('Data inizio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Data fine')
                    ->date('d/m/Y')
                    ->placeholder('Solo un giorno')
                    ->sortable(),

                Tables\Columns\TextColumn::make('closure_time')
                    ->label('Orario / fascia')
                    ->state(fn (ClosedSlot $record): string => $record->start_time && $record->end_time
                        ? substr($record->start_time, 0, 5) . ' - ' . substr($record->end_time, 0, 5)
                        : ($record->time ? substr($record->time, 0, 5) : 'Giorno intero'))
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
                Tables\Filters\TernaryFilter::make('is_global')
                    ->label('Ambito')
                    ->trueLabel('Tutto il negozio')
                    ->falseLabel('Staff specifico'),

                Tables\Filters\SelectFilter::make('staff_id')
                    ->options(fn () => Staff::query()
                        ->where('is_active', true)
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get()
                        ->pluck('full_name', 'id'))
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
                                fn (Builder $query, $date) => $query->where(function (Builder $query) use ($date) {
                                    $query->where(function (Builder $query) use ($date) {
                                        $query->whereNull('end_date')
                                            ->whereDate('date', '>=', $date);
                                    })->orWhereDate('end_date', '>=', $date);
                                }),
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
