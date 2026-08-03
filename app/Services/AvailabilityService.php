<?php

namespace App\Services;

use App\Models\Availability;
use App\Models\Booking;
use App\Models\ClosedSlot;
use App\Models\Service;
use App\Models\SpecialOpening;
use App\Models\Staff;
use App\Models\StaffAvailability;
use Carbon\Carbon;

class AvailabilityService
{
    public function availableSlots(Staff $staff, Service $service, string $date): array
    {
        $weekday = Carbon::parse($date)->dayOfWeek;

        $specialOpenings = SpecialOpening::query()
            ->whereDate('date', $date)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();
        $staffAvailabilities = StaffAvailability::query()
            ->where('staff_id', $staff->id)
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();
        $salonAvailabilities = Availability::query()
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        $timeSlots = match (true) {
            $specialOpenings->isNotEmpty() => $specialOpenings,
            $staffAvailabilities->isNotEmpty() => $staffAvailabilities,
            default => $salonAvailabilities,
        };

        $closedSlots = ClosedSlot::appliesToStaff($staff->id)
            ->coversDate($date)
            ->get();

        if ($timeSlots->isEmpty()) {
            return [
                'slots' => [],
                'service_duration' => (int) $service->duration,
                'closed_slots' => $closedSlots,
                'has_schedule' => false,
            ];
        }

        $bookings = Booking::query()
            ->with('service:id,duration')
            ->where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('time')
            ->get();

        $serviceDuration = (int) $service->duration;
        $availableSlots = [];

        foreach ($timeSlots as $slot) {
            $currentTime = $this->parseTime($slot->start_time);
            $endTime = $this->parseTime($slot->end_time);

            while ($currentTime->copy()->addMinutes($serviceDuration) <= $endTime) {
                $slotStart = $currentTime->copy();
                $slotEnd = $slotStart->copy()->addMinutes($serviceDuration);
                $isAvailable = true;

                foreach ($bookings as $booking) {
                    $bookingTime = $booking->time instanceof \DateTime
                        ? $booking->time->format('H:i:s')
                        : $booking->time;
                    $bookingStart = $this->parseTime($bookingTime);
                    $bookingEnd = $bookingStart->copy()->addMinutes($booking->service->duration);

                    if (
                        ($slotStart >= $bookingStart && $slotStart < $bookingEnd)
                        || ($slotEnd > $bookingStart && $slotEnd <= $bookingEnd)
                        || ($slotStart <= $bookingStart && $slotEnd >= $bookingEnd)
                    ) {
                        $isAvailable = false;
                        break;
                    }
                }

                if ($isAvailable) {
                    foreach ($closedSlots as $closedSlot) {
                        if ($closedSlot->time === null) {
                            $isAvailable = false;
                            break;
                        }

                        if ($slotStart->format('H:i') === substr((string) $closedSlot->time, 0, 5)) {
                            $isAvailable = false;
                            break;
                        }
                    }
                }

                if ($isAvailable) {
                    $availableSlots[] = $slotStart->format('H:i');
                }

                $currentTime->addMinutes($serviceDuration);
            }
        }

        $now = Carbon::now(config('app.timezone', 'Europe/Rome'));
        if ($date === $now->toDateString()) {
            $availableSlots = array_filter(
                $availableSlots,
                fn (string $slot): bool => $slot >= $now->format('H:i'),
            );
        }

        return [
            'slots' => array_values(array_unique($availableSlots)),
            'service_duration' => $serviceDuration,
            'closed_slots' => $closedSlots,
            'has_schedule' => true,
        ];
    }

    private function parseTime(mixed $time): Carbon
    {
        return Carbon::createFromFormat('H:i', substr((string) $time, 0, 5));
    }
}
