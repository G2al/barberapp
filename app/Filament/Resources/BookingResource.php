<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Staff;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

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

                Forms\Components\Textarea::make('note')
                    ->nullable()
                    ->rows(3)
                    ->maxLength(1000)
                    ->label('Nota / prenotazione per conto di'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['user', 'staff', 'service', 'haircut'])
                ->orderBy('date')
                ->orderBy('time')
            )
            ->header(fn () => view('filament.resources.booking-resource.staff-switcher', [
                'staffMembers' => Staff::query()
                    ->where('is_active', true)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get(),
            ]))
            ->columns([
                Split::make([
                    Tables\Columns\TextColumn::make('when')
                        ->label('Quando')
                        ->state(fn (Booking $record) => Carbon::parse($record->time)->format('H:i'))
                        ->description(fn (Booking $record) => Carbon::parse($record->date)->translatedFormat('d M, D'))
                        ->badge()
                        ->color('gray')
                        ->extraAttributes(['class' => 'min-w-[72px]']),

                    Stack::make([
                        Tables\Columns\TextColumn::make('service.name')
                            ->label('Servizio')
                            ->weight('bold')
                            ->searchable(),

                        Tables\Columns\TextColumn::make('user_full_name')
                            ->label('Cliente')
                            ->state(fn (Booking $record) => trim(($record->user->name ?? '') . ' ' . ($record->user->surname ?? '')) ?: $record->user?->email)
                            ->searchable(['users.name', 'users.surname', 'users.email', 'users.phone']),

                        Tables\Columns\TextColumn::make('note_preview')
                            ->label('Note')
                            ->state(fn (Booking $record) => filled($record->note) ? $record->note : null)
                            ->placeholder('Senza note')
                            ->color('warning')
                            ->wrap()
                            ->searchable(query: fn ($query, string $search) => $query->where('note', 'like', "%{$search}%")),
                    ])->space(1),

                    Tables\Columns\TextColumn::make('status')
                        ->label('Stato')
                        ->formatStateUsing(fn ($state) => [
                            'pending' => 'In sospeso',
                            'confirmed' => 'Confermata',
                            'completed' => 'Completata',
                            'cancelled' => 'Annullata',
                            'no_show' => 'Non presentato',
                        ][$state] ?? $state)
                        ->badge()
                        ->color(fn ($state) => [
                            'pending' => 'warning',
                            'confirmed' => 'success',
                            'completed' => 'info',
                            'cancelled' => 'danger',
                            'no_show' => 'gray',
                        ][$state] ?? 'gray'),
                ])
                    ->from('md')
                    ->extraAttributes(['class' => 'md:hidden']),

                Panel::make([
                    Stack::make([
                        Tables\Columns\TextColumn::make('service_details')
                            ->label('Dettagli servizio')
                            ->state(fn (Booking $record) => collect([
                                $record->haircut?->name,
                                $record->service?->duration ? $record->service->duration . ' min' : null,
                            ])->filter()->join(' - ') ?: 'Nessun dettaglio'),

                        Tables\Columns\TextColumn::make('client_contact')
                            ->label('Contatto cliente')
                            ->state(fn (Booking $record) => $record->user?->phone ?: $record->user?->email ?: 'Nessun contatto'),
                    ])->space(2),
                ])
                    ->collapsible()
                    ->collapsed()
                    ->extraAttributes(['class' => 'md:hidden']),

                Tables\Columns\TextColumn::make('date')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m/Y'))
                    ->sortable()
                    ->label('Data')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('time')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('H:i'))
                    ->label('Ora')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cliente')
                    ->formatStateUsing(fn ($state, Booking $record) => trim(($record->user->name ?? '') . ' ' . ($record->user->surname ?? '')) ?: $record->user?->email)
                    ->description(fn (Booking $record) => $record->user?->phone ?: $record->user?->email)
                    ->searchable(['users.name', 'users.surname', 'users.email', 'users.phone'])
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('desktop_service')
                    ->state(fn (Booking $record) => $record->service?->name)
                    ->label('Servizio')
                    ->description(fn (Booking $record) => collect([
                        $record->haircut?->name,
                        $record->service?->duration ? $record->service->duration . ' min' : null,
                    ])->filter()->join(' - '))
                    ->searchable(query: fn ($query, string $search) => $query->whereHas('service', fn ($query) => $query->where('name', 'like', "%{$search}%")))
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->placeholder('Senza note')
                    ->limit(45)
                    ->wrap()
                    ->searchable()
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('desktop_status')
                    ->label('Stato')
                    ->state(fn (Booking $record) => $record->status)
                    ->formatStateUsing(fn ($state) => [
                        'pending' => 'In sospeso',
                        'confirmed' => 'Confermata',
                        'completed' => 'Completata',
                        'cancelled' => 'Annullata',
                        'no_show' => 'Non presentato',
                    ][$state] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'info' => 'completed',
                        'danger' => 'cancelled',
                        'gray' => 'no_show',
                    ])
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\Filter::make('today')
                    ->query(fn ($query) => $query->whereDate('date', Carbon::today()))
                    ->default()
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

                Tables\Filters\SelectFilter::make('service_id')
                    ->relationship('service', 'name')
                    ->label('Servizio'),
            ])
            ->poll('30s')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('call_user')
                        ->label('Chiama cliente')
                        ->icon('heroicon-o-phone')
                        ->color('success')
                        ->url(fn (Booking $record) => 'tel:' . preg_replace('/\s+/', '', $record->user?->phone ?? ''))
                        ->visible(fn (Booking $record) => filled($record->user?->phone)),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->label('Azioni')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('change_status')
                        ->label('Cambia stato')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->options([
                                    'pending' => 'In sospeso',
                                    'confirmed' => 'Confermata',
                                    'completed' => 'Completata',
                                    'cancelled' => 'Annullata',
                                    'no_show' => 'Non presentato',
                                ])
                                ->required()
                                ->label('Nuovo stato'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['status' => $data['status']]);
                        })
                        ->deselectRecordsAfterCompletion(),

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
