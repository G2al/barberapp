<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Widgets\BookingStatsWidget;
use App\Models\Staff;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    public ?int $selectedStaffId = null;

    public function mount(): void
    {
        parent::mount();

        $this->selectedStaffId ??= Staff::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->value('id');
    }

    public function selectStaff(int $staffId): void
    {
        $this->selectedStaffId = $staffId;
        $this->resetPage();
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->when($this->selectedStaffId, fn (Builder $query) => $query->where('staff_id', $this->selectedStaffId));
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
