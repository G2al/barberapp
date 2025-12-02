<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark';

    protected static ?string $navigationLabel = 'Prenotazioni';

    protected static ?string $modelLabel = 'Prenotazione';

    protected static ?string $pluralModelLabel = 'Prenotazioni';

    protected static ?string $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Utente'),

                Forms\Components\Select::make('staff_id')
                    ->relationship('staff', 'first_name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Barbiere'),

                Forms\Components\Select::make('service_id')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Servizio'),

                Forms\Components\Select::make('haircut_id')
                    ->relationship('haircut', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->label('Taglio'),

                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->label('Data'),

                Forms\Components\TimePicker::make('time')
                    ->required()
                    ->label('Ora'),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'In sospeso',
                        'confirmed' => 'Confermata',
                        'completed' => 'Completata',
                        'cancelled' => 'Annullata',
                        'no_show' => 'Non presentato',
                    ])
                    ->default('pending')
                    ->required()
                    ->label('Stato'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->whereDate('date', '>=', Carbon::today())
                ->orderBy('date')
                ->orderBy('time')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cliente')
                    ->formatStateUsing(fn ($state, $record) => trim(($record->user->name ?? '') . ' ' . ($record->user->surname ?? '')))
                    ->description(fn ($record) => $record->user->email ?? '')
                    ->searchable(['users.name', 'users.surname', 'users.email'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('staff.first_name')
                    ->searchable()
                    ->label('Barbiere'),

                Tables\Columns\TextColumn::make('service.name')
                    ->searchable()
                    ->label('Servizio'),

                Tables\Columns\TextColumn::make('date')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y'))
                    ->sortable()
                    ->label('Data'),

                Tables\Columns\TextColumn::make('time')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i'))
                    ->label('Ora'),

                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn ($state) => [
                        'pending' => 'In sospeso',
                        'confirmed' => 'Confermata',
                        'completed' => 'Completata',
                        'cancelled' => 'Annullata',
                        'no_show' => 'Non presentato',
                    ][$state])
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'info' => 'completed',
                        'danger' => 'cancelled',
                        'gray' => 'no_show',
                    ])
                    ->label('Stato'),
            ])
            ->filters([
                Tables\Filters\Filter::make('today')
                    ->query(fn ($query) => $query->whereDate('date', Carbon::today()))
                    ->toggle()
                    ->label('Oggi'),

                Tables\Filters\Filter::make('tomorrow')
                    ->query(fn ($query) => $query->whereDate('date', Carbon::tomorrow()))
                    ->toggle()
                    ->label('Domani'),

                Tables\Filters\Filter::make('saturday')
                    ->query(fn ($query) => $query->whereDate('date', Carbon::today()->next(Carbon::SATURDAY)))
                    ->toggle()
                    ->label('Sabato'),

                Tables\Filters\Filter::make('this_week')
                    ->query(fn ($query) => $query->whereBetween('date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]))
                    ->toggle()
                    ->label('Questa settimana'),

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

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'In sospeso',
                        'confirmed' => 'Confermata',
                        'completed' => 'Completata',
                        'cancelled' => 'Annullata',
                        'no_show' => 'Non presentato',
                    ])
                    ->label('Stato'),

                Tables\Filters\SelectFilter::make('staff_id')
                    ->relationship('staff', 'first_name')
                    ->label('Barbiere'),

                Tables\Filters\SelectFilter::make('service_id')
                    ->relationship('service', 'name')
                    ->label('Servizio'),
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
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
