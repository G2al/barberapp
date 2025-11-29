<?php

namespace App\Filament\Resources\BookingResource\Widgets;

use App\Models\Booking;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $saturday = Carbon::today()->next(Carbon::SATURDAY);

        // Prenotazioni per giorno
        $bookingsToday = Booking::whereDate('date', $today)->count();
        $bookingsTomorrow = Booking::whereDate('date', $tomorrow)->count();
        $bookingsSaturday = Booking::whereDate('date', $saturday)->count();

        // Totale servizi per giorno
        $servicesMap = [
            'Barba classica' => 0,
            'Barba con punto luce' => 0,
            'Barba + Colore' => 0,
            'Shampoo + Taglio Uomo' => 0,
            'Shampoo + Taglio Bambino' => 0,
            'Shampoo + Taglio + Barba' => 0,
            'Colore' => 0,
            'Colpi di Sole' => 0,
            'Total White' => 0,
            'Permanente' => 0,
            'Stiratura' => 0,
            'Shampoo + Piega' => 0,
            'Sopracciglia' => 0,
            'Black Mask' => 0,
            'Colpi di sole - White' => 0,
        ];

        $bookingsTodayDetails = Booking::whereDate('date', $today)
            ->with('service')
            ->get()
            ->groupBy('service.name');

        $servicesMapToday = $servicesMap;
        foreach ($bookingsTodayDetails as $serviceName => $bookings) {
            if (isset($servicesMapToday[$serviceName])) {
                $servicesMapToday[$serviceName] = count($bookings);
            }
        }

        // Per barbieri
        $staffBookings = Booking::whereDate('date', $today)
            ->with('staff')
            ->get()
            ->groupBy('staff.first_name');

        $staffSummary = [];
        foreach ($staffBookings as $staffName => $bookings) {
            $staffSummary[] = "{$staffName}: " . count($bookings);
        }

        // Formatta i servizi di oggi
        $servicesText = collect($servicesMapToday)
            ->filter(fn($count) => $count > 0)
            ->map(fn($count, $name) => "{$name}: {$count}")
            ->values()
            ->implode(' | ');

        return [
            Stat::make('Oggi', $bookingsToday)
                ->description($servicesText ?: 'Nessuna prenotazione')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('success'),

            Stat::make('Domani', $bookingsTomorrow)
                ->description("Prenotazioni in agenda")
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info'),

            Stat::make('Sabato', $bookingsSaturday)
                ->description($saturday->format('d/m/Y'))
                ->descriptionIcon('heroicon-o-calendar')
                ->color('warning'),

            Stat::make('Per Barbiere (Oggi)', implode(' | ', $staffSummary) ?: 'Nessuno')
                ->description("Distribuzione del lavoro")
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
