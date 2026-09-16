<?php

namespace App\Services;

use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingWaitlistEntry;
use App\Models\ClosedSlot;
use App\Models\Service;
use App\Models\SpecialOpening;
use App\Models\Staff;
use App\Models\StaffAvailability;
use App\Notifications\WaitlistBookingAssignedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingWaitlistService
{
    public function join(int $userId, int $staffId, int $serviceId, string $date, string $time): array
    {
        if (Carbon::parse($date.' '.$time)->isPast()) {
            return ['error' => 'Non puoi entrare in lista d’attesa per un orario già passato.', 'status' => 422];
        }

        $staff = Staff::whereKey($staffId)->where('is_active', true)->first();
        $service = Service::whereKey($serviceId)->where('is_active', true)->first();

        if (! $staff || ! $service || ! $staff->services()->whereKey($serviceId)->exists()) {
            return ['error' => 'Servizio o professionista non disponibile.', 'status' => 422];
        }

        if (! $this->fitsOpeningHours($staff, $service, $date, $time)) {
            return ['error' => 'L’orario richiesto non rientra negli orari di apertura.', 'status' => 422];
        }

        if ($this->isClosed($staffId, $date, $time)) {
            return ['error' => 'Questo orario è chiuso e non può essere inserito in lista d’attesa.', 'status' => 422];
        }

        if (! $this->hasOverlap($staffId, $service, $date, $time)) {
            return ['error' => 'Questo orario è disponibile: puoi prenotarlo direttamente.', 'status' => 409];
        }

        $entry = DB::transaction(function () use ($userId, $staffId, $serviceId, $date, $time) {
            $entry = BookingWaitlistEntry::where('user_id', $userId)
                ->where('staff_id', $staffId)
                ->where('service_id', $serviceId)
                ->whereDate('date', $date)
                ->where('time', 'like', substr($time, 0, 5).'%')
                ->lockForUpdate()
                ->first();

            if ($entry?->status === 'waiting') {
                return $entry;
            }

            if ($entry?->status === 'assigned') {
                return $entry;
            }

            if ($entry) {
                $entry->update(['status' => 'waiting', 'assigned_booking_id' => null, 'created_at' => now()]);

                return $entry->refresh();
            }

            return BookingWaitlistEntry::create([
                'user_id' => $userId,
                'staff_id' => $staffId,
                'service_id' => $serviceId,
                'date' => $date,
                'time' => $time,
                'status' => 'waiting',
            ]);
        });

        $position = $entry->status === 'waiting'
            ? BookingWaitlistEntry::where('staff_id', $staffId)
                ->whereDate('date', $date)
                ->where('time', 'like', substr($time, 0, 5).'%')
                ->where('status', 'waiting')
                ->where('id', '<=', $entry->id)
                ->count()
            : null;

        return ['entry' => $entry->load(['staff', 'service']), 'position' => $position];
    }

    public function promoteFor(Booking $cancelledBooking): void
    {
        $promotedIds = DB::transaction(function () use ($cancelledBooking) {
            $entries = BookingWaitlistEntry::where('staff_id', $cancelledBooking->staff_id)
                ->whereDate('date', substr((string) $cancelledBooking->getRawOriginal('date'), 0, 10))
                ->where('time', 'like', substr((string) $cancelledBooking->time, 0, 5).'%')
                ->where('status', 'waiting')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $promoted = [];
            foreach ($entries as $entry) {
                $entry->loadMissing(['user', 'staff', 'service']);
                if (! $entry->user?->is_active || ! $entry->staff?->is_active || ! $entry->service?->is_active) {
                    $entry->update(['status' => 'skipped']);

                    continue;
                }

                if (! $entry->staff->services()->whereKey($entry->service_id)->exists()) {
                    $entry->update(['status' => 'skipped']);

                    continue;
                }

                $entryDate = substr((string) $entry->getRawOriginal('date'), 0, 10);
                $entryTime = substr((string) $entry->time, 0, 5);
                if (Carbon::parse($entryDate.' '.$entryTime)->isPast()) {
                    $entry->update(['status' => 'skipped']);

                    continue;
                }

                if (! $this->fitsOpeningHours($entry->staff, $entry->service, $entryDate, $entryTime)) {
                    $entry->update(['status' => 'skipped']);

                    continue;
                }

                if ($this->isClosed($entry->staff_id, $entryDate, $entryTime)) {
                    $entry->update(['status' => 'skipped']);

                    continue;
                }

                if ($this->hasOverlap($entry->staff_id, $entry->service, $entryDate, $entryTime)) {
                    break;
                }

                if (! Booking::canUserBookMore($entry->user_id)) {
                    $entry->update(['status' => 'skipped']);

                    continue;
                }

                $booking = Booking::create([
                    'user_id' => $entry->user_id,
                    'staff_id' => $entry->staff_id,
                    'service_id' => $entry->service_id,
                    'date' => $entry->date,
                    'time' => $entry->time,
                    'status' => 'confirmed',
                ]);

                $entry->update(['status' => 'assigned', 'assigned_booking_id' => $booking->id]);
                $promoted[] = $booking->id;
                break;
            }

            return $promoted;
        });

        foreach ($promotedIds as $bookingId) {
            $booking = Booking::with(['user', 'staff', 'service'])->find($bookingId);
            if (! $booking?->user) {
                continue;
            }

            try {
                $booking->user->notify(new WaitlistBookingAssignedNotification($booking));
            } catch (\Throwable $exception) {
                Log::error('Waitlist assignment notification failed', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function hasOverlap(int $staffId, Service $service, string $date, string $time): bool
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.substr($time, 0, 5));
        $end = $start->copy()->addMinutes((int) $service->duration);

        $bookings = Booking::with('service')
            ->where('staff_id', $staffId)
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        foreach ($bookings as $booking) {
            $bookingStart = Carbon::createFromFormat('Y-m-d H:i', $date.' '.substr((string) $booking->time, 0, 5));
            $bookingEnd = $bookingStart->copy()->addMinutes((int) ($booking->service?->duration ?? 0));
            if ($start->lt($bookingEnd) && $end->gt($bookingStart)) {
                return true;
            }
        }

        return false;
    }

    private function isClosed(int $staffId, string $date, string $time): bool
    {
        return ClosedSlot::appliesToStaff($staffId)
            ->coversDate($date)
            ->where(function ($query) use ($time) {
                $query->whereNull('time')->orWhereTime('time', substr($time, 0, 5));
            })
            ->exists();
    }

    private function fitsOpeningHours(Staff $staff, Service $service, string $date, string $time): bool
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.substr($time, 0, 5));
        $end = $start->copy()->addMinutes((int) $service->duration);
        $weekday = $start->dayOfWeek;

        $special = SpecialOpening::whereDate('date', $date)->where('is_active', true)->get();
        $personal = $staff->uses_salon_hours ? collect() : StaffAvailability::where('staff_id', $staff->id)
            ->where('weekday', $weekday)->where('is_active', true)->get();
        $windows = $special->isNotEmpty() ? $special : ($personal->isNotEmpty()
            ? $personal
            : Availability::where('weekday', $weekday)->where('is_active', true)->get());

        foreach ($windows as $window) {
            $windowStart = Carbon::parse($date.' '.$window->start_time);
            $windowEnd = Carbon::parse($date.' '.$window->end_time);
            if ($start->greaterThanOrEqualTo($windowStart) && $end->lessThanOrEqualTo($windowEnd)) {
                return true;
            }
        }

        return false;
    }
}
