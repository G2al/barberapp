<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Widgets\BookingStatsWidget;
use App\Models\Booking;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected static string $view = 'filament.resources.booking-resource.pages.list-bookings';

    public string $selectedMonth;

    public function mount(): void
    {
        parent::mount();

        $this->selectedMonth = Carbon::today()->format('Y-m');
    }

    public function updatedSelectedMonth(): void
    {
        unset($this->cachedTabs);

        $currentMonth = Carbon::today()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m', $this->selectedMonth)->startOfMonth();
        $this->activeTab = $this->selectedMonth === $currentMonth
            ? 'day_' . Carbon::today()->format('Y-m-d')
            : 'day_' . $month->format('Y-m-d');
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'day_' . Carbon::today()->format('Y-m-d');
    }

    public function getTabs(): array
    {
        $tabs = [];

        $month = Carbon::createFromFormat('Y-m', $this->selectedMonth)->startOfMonth();
        $lastDay = $month->copy()->endOfMonth();
        $today = Carbon::today();
        $weekdays = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];

        $dates = [];
        for ($date = $month->copy(); $date->lte($lastDay); $date->addDay()) {
            $dates[] = $date->copy();
        }

        if ($month->format('Y-m') === $today->format('Y-m')) {
            usort($dates, fn (Carbon $left, Carbon $right) => [
                $left->isSameDay($today) ? 0 : 1,
                $left->lessThan($today) ? 1 : 0,
                $left->timestamp,
            ] <=> [
                $right->isSameDay($today) ? 0 : 1,
                $right->lessThan($today) ? 1 : 0,
                $right->timestamp,
            ]);
        }

        foreach ($dates as $date) {
            $dateKey = $date->format('Y-m-d');
            $label = $date->isSameDay($today)
                ? 'Oggi'
                : ($date->isTomorrow() ? 'Domani' : $weekdays[$date->dayOfWeek] . ' ' . $date->format('d/m'));

            $tabs['day_' . $dateKey] = Tab::make($label)
                ->badge(fn () => Booking::query()
                    ->whereDate('date', $dateKey)
                    ->where('status', '!=', 'cancelled')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('date', $dateKey));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BookingStatsWidget::class,
        ];
    }
}
