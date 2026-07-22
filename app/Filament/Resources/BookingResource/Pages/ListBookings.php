<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Widgets\BookingStatsWidget;
use App\Models\Booking;
use App\Models\Staff;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected static string $view = 'filament.resources.booking-resource.pages.list-bookings';

    public function getDefaultActiveTab(): string | int | null
    {
        $firstStaff = Staff::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->first();

        return $firstStaff ? 'staff_' . $firstStaff->id : null;
    }

    public function getTabs(): array
    {
        $tabs = [];

        Staff::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->each(function (Staff $staff) use (&$tabs): void {
                $tabs['staff_' . $staff->id] = Tab::make($staff->full_name)
                    ->badge(fn () => Booking::query()
                        ->where('staff_id', $staff->id)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->whereDate('date', Carbon::today())
                        ->count())
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('staff_id', $staff->id));
            });

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
