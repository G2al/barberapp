<?php

namespace App\Services\Ai;

use App\Models\Availability;
use App\Models\ClosedSlot;
use App\Models\LoyaltyRewardRule;
use App\Models\Service;
use App\Models\SpecialOpening;
use App\Models\Staff;
use App\Models\StaffAvailability;
use Illuminate\Support\Facades\Cache;

class SalonContextBuilder
{
    public function __construct(private readonly PublicBookingRules $bookingRules) {}

    public function build(): array
    {
        $seconds = max(0, (int) config('ai.context_cache_seconds', 120));

        if ($seconds === 0) {
            return $this->queryContext();
        }

        return Cache::remember('ai:salon-public-context:v1', $seconds, fn (): array => $this->queryContext());
    }

    private function queryContext(): array
    {
        $today = today();
        $lastDay = $today->copy()->addDays(max(0, (int) config('ai.special_days_ahead', 30)));

        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'price', 'duration', 'loyalty_points'])
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'price_eur' => $service->price === null ? null : number_format((float) $service->price, 2, '.', ''),
                'duration_minutes' => (int) $service->duration,
                'loyalty_points' => (int) ($service->loyalty_points ?? 0),
            ])->values()->all();

        $staff = Staff::query()
            ->with(['services' => fn ($query) => $query->where('services.is_active', true)->orderBy('services.name')])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'role'])
            ->map(fn (Staff $member): array => [
                'id' => $member->id,
                'name' => trim($member->first_name.' '.$member->last_name),
                'role' => $member->role,
                'services' => $member->services->pluck('name')->values()->all(),
            ])->values()->all();

        $ordinaryHours = Availability::query()
            ->where('is_active', true)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get(['weekday', 'slot_type', 'start_time', 'end_time'])
            ->map(fn (Availability $availability): array => [
                'weekday' => (int) $availability->weekday,
                'period' => $availability->slot_type,
                'start' => substr((string) $availability->start_time, 0, 5),
                'end' => substr((string) $availability->end_time, 0, 5),
            ])->values()->all();

        $staffHours = StaffAvailability::query()
            ->with('staff:id,first_name,last_name')
            ->where('is_active', true)
            ->whereHas('staff', fn ($query) => $query->where('is_active', true))
            ->orderBy('staff_id')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get(['staff_id', 'weekday', 'start_time', 'end_time'])
            ->map(fn (StaffAvailability $availability): array => [
                'staff' => trim($availability->staff->first_name.' '.$availability->staff->last_name),
                'weekday' => (int) $availability->weekday,
                'start' => substr((string) $availability->start_time, 0, 5),
                'end' => substr((string) $availability->end_time, 0, 5),
            ])->values()->all();

        $specialOpenings = SpecialOpening::query()
            ->where('is_active', true)
            ->whereBetween('date', [$today->toDateString(), $lastDay->toDateString()])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get(['date', 'start_time', 'end_time'])
            ->map(fn (SpecialOpening $opening): array => [
                'date' => $opening->date->toDateString(),
                'start' => substr((string) $opening->start_time, 0, 5),
                'end' => substr((string) $opening->end_time, 0, 5),
            ])->values()->all();

        $closures = ClosedSlot::query()
            ->with('staff:id,first_name,last_name')
            ->whereDate('date', '<=', $lastDay->toDateString())
            ->where(function ($query) use ($today) {
                $query->where(function ($singleDay) use ($today) {
                    $singleDay->whereNull('end_date')
                        ->whereDate('date', '>=', $today->toDateString());
                })->orWhereDate('end_date', '>=', $today->toDateString());
            })
            ->orderBy('date')
            ->orderBy('time')
            ->get(['staff_id', 'is_global', 'date', 'end_date', 'time'])
            ->map(fn (ClosedSlot $closure): array => [
                'scope' => $closure->is_global ? 'salone' : 'staff',
                'staff' => $closure->staff
                    ? trim($closure->staff->first_name.' '.$closure->staff->last_name)
                    : null,
                'from' => $closure->date->toDateString(),
                'to' => $closure->end_date?->toDateString() ?? $closure->date->toDateString(),
                'time' => $closure->time ? substr((string) $closure->time, 0, 5) : null,
                'whole_day' => $closure->time === null,
            ])->values()->all();

        $loyaltyRules = LoyaltyRewardRule::query()
            ->with(['service:id,name', 'rewardService:id,name'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LoyaltyRewardRule $rule): array => [
                'name' => $rule->name,
                'type' => $rule->type,
                'service' => $rule->service?->name,
                'points_required' => $rule->points_required,
                'visits_required' => $rule->visits_required,
                'reward' => $rule->reward_title,
                'reward_description' => $rule->reward_description,
                'reward_service' => $rule->rewardService?->name,
                'reward_points_cost' => $rule->reward_points_cost,
                'expires_after_days' => $rule->expires_after_days,
                'repeatable' => (bool) $rule->is_repeatable,
            ])->values()->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'special_period' => [
                'from' => $today->toDateString(),
                'to' => $lastDay->toDateString(),
            ],
            'salon' => [
                'name' => config('app.name'),
                'location' => config('barbershop.location'),
            ],
            'services' => $services,
            'staff' => $staff,
            'ordinary_hours' => $ordinaryHours,
            'staff_hours' => $staffHours,
            'special_openings' => $specialOpenings,
            'closures' => $closures,
            'booking_rules' => $this->bookingRules->toArray(),
            'loyalty_rules' => $loyaltyRules,
        ];
    }
}
