<?php

namespace App\Filament\Resources\StaffAvailabilityResource\Pages;

use App\Filament\Resources\StaffAvailabilityResource;
use App\Models\StaffAvailability;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffAvailability extends CreateRecord
{
    protected static string $resource = StaffAvailabilityResource::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('staff_id')
                ->relationship('staff', 'first_name')
                ->searchable()
                ->preload()
                ->required()
                ->label('Staff'),

            Forms\Components\Select::make('weekdays')
                ->options([
                    0 => 'Domenica',
                    1 => 'Lunedi',
                    2 => 'Martedi',
                    3 => 'Mercoledi',
                    4 => 'Giovedi',
                    5 => 'Venerdi',
                    6 => 'Sabato',
                ])
                ->multiple()
                ->required()
                ->label('Giorni'),

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

    protected function handleRecordCreation(array $data): StaffAvailability
    {
        $weekdays = $data['weekdays'] ?? [];
        unset($data['weekdays']);

        $record = null;
        foreach ($weekdays as $weekday) {
            $record = StaffAvailability::create(array_merge($data, ['weekday' => $weekday]));
        }

        return $record;
    }
}
