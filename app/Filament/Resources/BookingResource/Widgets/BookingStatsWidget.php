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
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        // Contatori base
        $bookingsToday = Booking::whereDate('date', $today)->count();
        $bookingsTomorrow = Booking::whereDate('date', $tomorrow)->count();
        $bookingsSaturday = Booking::whereDate('date', $saturday)->count();
        $bookingsWeek = Booking::whereBetween('date', [$weekStart, $weekEnd])->count();

        // Top servizi oggi (aggregato, niente hardcode)
        $servicesToday = Booking::selectRaw('service_id, count(*) as total')
            ->whereDate('date', $today)
            ->groupBy('service_id')
            ->with('service:id,name')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(function ($row) {
                $name = $row->service->name ?? 'N/D';
                return "{$name}: {$row->total}";
            })
            ->implode(' | ');

        // Top barbieri oggi
        $staffToday = Booking::selectRaw('staff_id, count(*) as total')
            ->whereDate('date', $today)
            ->groupBy('staff_id')
            ->with('staff:id,first_name')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(function ($row) {
                $name = $row->staff->first_name ?? 'N/D';
                return "{$name}: {$row->total}";
            })
            ->implode(' | ');

        // Stati odierni
        $statusToday = Booking::selectRaw('status, count(*) as total')
            ->whereDate('date', $today)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(function ($count, $status) {
                return "{$status}: {$count}";
            })
            ->values()
            ->implode(' | ');

        return [
            Stat::make('Oggi', $bookingsToday)
                ->description($servicesToday ?: 'Nessuna prenotazione')
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

            Stat::make('Questa settimana', $bookingsWeek)
                ->description($weekStart->format('d/m') . ' - ' . $weekEnd->format('d/m'))
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make('Top professioniste (Oggi)', $staffToday ?: 'Nessuna')
                ->description('Distribuzione del lavoro')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Stati oggi', $statusToday ?: 'Nessuna prenotazione')
                ->description('pending/confirmed/cancelled')
                ->descriptionIcon('heroicon-o-rectangle-stack')
                ->color('gray'),
        ];
    }
}
