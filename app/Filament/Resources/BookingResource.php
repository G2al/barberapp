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
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

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
                    ->label('Professionista'),

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
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Prenotazione')
                    ->state(function (Booking $record): HtmlString {
                        $client = trim(($record->user->name ?? '') . ' ' . ($record->user->surname ?? '')) ?: ($record->user->email ?? 'Cliente');
                        $service = $record->service?->name ?? 'Servizio';
                        $note = filled($record->note) ? e($record->note) : '<span class="text-gray-500">Senza note</span>';
                        $status = [
                            'pending' => 'In sospeso',
                            'confirmed' => 'Confermata',
                            'completed' => 'Completata',
                            'cancelled' => 'Annullata',
                            'no_show' => 'Non presentato',
                        ][$record->status] ?? $record->status;
                        $statusClass = [
                            'pending' => 'background:#fef3c7;color:#92400e;ring-color:#fde68a;',
                            'confirmed' => 'background:#dcfce7;color:#166534;ring-color:#bbf7d0;',
                            'completed' => 'background:#dbeafe;color:#1e40af;ring-color:#bfdbfe;',
                            'cancelled' => 'background:#fee2e2;color:#991b1b;ring-color:#fecaca;',
                            'no_show' => 'background:#f3f4f6;color:#374151;ring-color:#e5e7eb;',
                        ][$record->status] ?? 'background:#f3f4f6;color:#374151;ring-color:#e5e7eb;';

                        return new HtmlString(sprintf(
                            '<div class="space-y-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-base font-semibold text-gray-950 dark:text-white">%s</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">%s</div>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-sm font-semibold ring-1" style="%s">%s</span>
                                </div>
                                <div class="text-sm"><span class="font-semibold">%s</span></div>
                                <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-100 dark:ring-amber-800">%s</div>
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">%s</div>
                            </div>',
                            e(Carbon::parse($record->time)->format('H:i')),
                            e(Carbon::parse($record->date)->translatedFormat('d M, D')),
                            $statusClass,
                            e($status),
                            e($service),
                            $note,
                            e($client),
                        ));
                    })
                    ->html()
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('note', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('service', fn ($query) => $query->where('name', 'like', "%{$search}%")))
                    ->hiddenFrom('md'),

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

                Tables\Columns\TextColumn::make('staff.full_name')
                    ->label('Professionista')
                    ->searchable(['staff.first_name', 'staff.last_name'])
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
