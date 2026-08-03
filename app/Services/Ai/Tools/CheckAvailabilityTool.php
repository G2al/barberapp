<?php

namespace App\Services\Ai\Tools;

use App\Models\Service;
use App\Models\Staff;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckAvailabilityTool
{
    public const NAME = 'check_availability';

    public function __construct(private readonly AvailabilityService $availabilityService) {}

    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => self::NAME,
            'description' => 'Controlla gli slot reali disponibili per un servizio in una data. Usalo sempre per domande sulla disponibilita.',
            'strict' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'date' => [
                        'type' => 'string',
                        'description' => 'Data richiesta in formato YYYY-MM-DD, interpretata nel fuso Europe/Rome.',
                        'pattern' => '^\\d{4}-\\d{2}-\\d{2}$',
                    ],
                    'time' => [
                        'type' => ['string', 'null'],
                        'description' => 'Orario richiesto in formato HH:mm oppure null.',
                        'pattern' => '^([01]\\d|2[0-3]):[0-5]\\d$',
                    ],
                    'service_id' => [
                        'type' => ['integer', 'null'],
                        'description' => 'ID del servizio oppure null se viene usato service_name.',
                    ],
                    'service_name' => [
                        'type' => ['string', 'null'],
                        'description' => 'Nome del servizio oppure null se viene usato service_id.',
                    ],
                    'staff_id' => [
                        'type' => ['integer', 'null'],
                        'description' => 'ID del professionista oppure null.',
                    ],
                    'staff_name' => [
                        'type' => ['string', 'null'],
                        'description' => 'Nome del professionista oppure null.',
                    ],
                ],
                'required' => [
                    'date',
                    'time',
                    'service_id',
                    'service_name',
                    'staff_id',
                    'staff_name',
                ],
                'additionalProperties' => false,
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'service_id' => ['nullable', 'integer', 'min:1', 'required_without:service_name'],
            'service_name' => ['nullable', 'string', 'max:100', 'required_without:service_id'],
            'staff_id' => ['nullable', 'integer', 'min:1'],
            'staff_name' => ['nullable', 'string', 'max:150'],
        ])->validate();

        $date = Carbon::createFromFormat('Y-m-d', $validated['date'], 'Europe/Rome')->startOfDay();
        if ($date->lt(Carbon::now('Europe/Rome')->startOfDay())) {
            throw ValidationException::withMessages(['date' => 'La data richiesta e gia trascorsa.']);
        }

        $service = $this->resolveService($validated);
        if (! $service) {
            return $this->result($validated['date'], null, null, false, $validated['time'] ?? null, []);
        }

        $staff = Staff::query()
            ->where('is_active', true)
            ->whereHas('services', fn ($query) => $query->where('services.id', $service->id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $requestedStaff = $this->resolveStaff($staff, $validated);
        if (($validated['staff_id'] ?? null) !== null || filled($validated['staff_name'] ?? null)) {
            if (! $requestedStaff) {
                return $this->result($validated['date'], $service->name, null, false, $validated['time'] ?? null, []);
            }

            $staff = collect([$requestedStaff]);
        }

        $availability = $staff->map(function (Staff $member) use ($service, $validated): array {
            $slots = $this->availabilityService->availableSlots($member, $service, $validated['date'])['slots'];

            return [
                'staff' => $member,
                'slots' => $slots,
            ];
        });

        return $this->formatAvailability(
            $validated['date'],
            $service,
            $availability,
            $validated['time'] ?? null,
            $requestedStaff,
        );
    }

    public function bookingAction(array $arguments, array $result): ?array
    {
        if (($result['available'] ?? false) !== true || blank($result['requested_slot'] ?? null)) {
            return null;
        }

        $service = $this->resolveService($arguments);
        if (! $service) {
            return null;
        }

        $compatibleStaff = Staff::query()
            ->where('is_active', true)
            ->whereHas('services', fn ($query) => $query->where('services.id', $service->id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $staff = $this->resolveStaff($compatibleStaff, $arguments);
        if (! $staff && filled($result['professional'] ?? null)) {
            $professional = $this->normalizeName((string) $result['professional']);
            $staff = $compatibleStaff->first(
                fn (Staff $member): bool => $this->normalizeName($this->staffName($member)) === $professional,
            );
        }

        if (! $staff) {
            return null;
        }

        $date = (string) $result['date'];
        $time = (string) $result['requested_slot'];
        $staffName = $this->staffName($staff);

        return [
            'type' => 'confirm_booking',
            'label' => 'Conferma prenotazione',
            'method' => 'POST',
            'url' => '/api/bookings',
            'payload' => [
                'staff_id' => $staff->id,
                'service_id' => $service->id,
                'date' => $date,
                'time' => $time,
            ],
            'summary' => [
                'service' => $service->name,
                'staff' => $staffName,
                'date' => $date,
                'time' => $time,
                'duration_minutes' => (int) $service->duration,
                'price_eur' => $service->price === null ? null : number_format((float) $service->price, 2, '.', ''),
            ],
        ];
    }

    private function resolveService(array $arguments): ?Service
    {
        if (($arguments['service_id'] ?? null) !== null) {
            return Service::query()
                ->whereKey($arguments['service_id'])
                ->where('is_active', true)
                ->first();
        }

        $requestedName = $this->normalizeName((string) ($arguments['service_name'] ?? ''));

        return Service::query()
            ->where('is_active', true)
            ->get()
            ->first(fn (Service $service): bool => $this->normalizeName($service->name) === $requestedName);
    }

    private function resolveStaff(Collection $staff, array $arguments): ?Staff
    {
        if (($arguments['staff_id'] ?? null) !== null) {
            return $staff->firstWhere('id', (int) $arguments['staff_id']);
        }

        if (blank($arguments['staff_name'] ?? null)) {
            return null;
        }

        $requestedName = $this->normalizeName((string) $arguments['staff_name']);

        return $staff->first(function (Staff $member) use ($requestedName): bool {
            $fullName = trim($member->first_name.' '.$member->last_name);

            return $this->normalizeName($fullName) === $requestedName
                || $this->normalizeName($member->first_name) === $requestedName;
        });
    }

    private function formatAvailability(
        string $date,
        Service $service,
        Collection $availability,
        ?string $requestedTime,
        ?Staff $requestedStaff,
    ): array {
        if ($requestedTime !== null) {
            $available = $availability->first(
                fn (array $item): bool => in_array($requestedTime, $item['slots'], true),
            );

            if ($available) {
                return $this->result(
                    $date,
                    $service->name,
                    $this->staffName($available['staff']),
                    true,
                    $requestedTime,
                    [],
                );
            }

            $best = $availability
                ->filter(fn (array $item): bool => $item['slots'] !== [])
                ->sortBy(fn (array $item): int => min(array_map(
                    fn (string $slot): int => $this->distance($requestedTime, $slot),
                    $item['slots'],
                )))
                ->first();

            $alternatives = $best
                ? $this->nearestSlots($best['slots'], $requestedTime)
                : [];
            $professional = $best
                ? $this->staffName($best['staff'])
                : ($requestedStaff ? $this->staffName($requestedStaff) : null);

            return $this->result($date, $service->name, $professional, false, $requestedTime, $alternatives);
        }

        $best = $availability
            ->filter(fn (array $item): bool => $item['slots'] !== [])
            ->sortBy(fn (array $item): string => $item['slots'][0])
            ->first();

        return $this->result(
            $date,
            $service->name,
            $best ? $this->staffName($best['staff']) : ($requestedStaff ? $this->staffName($requestedStaff) : null),
            $best !== null,
            null,
            $best ? array_slice($best['slots'], 0, 3) : [],
        );
    }

    private function nearestSlots(array $slots, string $requestedTime): array
    {
        usort($slots, fn (string $a, string $b): int => $this->distance($requestedTime, $a) <=> $this->distance($requestedTime, $b));

        return array_slice($slots, 0, 3);
    }

    private function distance(string $first, string $second): int
    {
        return abs($this->minutes($first) - $this->minutes($second));
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }

    private function staffName(Staff $staff): string
    {
        return trim($staff->first_name.' '.$staff->last_name);
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\\s+/', ' ', $name)));
    }

    private function result(
        string $date,
        ?string $service,
        ?string $professional,
        bool $available,
        ?string $requestedSlot,
        array $alternatives,
    ): array {
        return [
            'date' => $date,
            'service' => $service,
            'professional' => $professional,
            'available' => $available,
            'requested_slot' => $requestedSlot,
            'alternatives' => array_values(array_slice($alternatives, 0, 3)),
        ];
    }
}
