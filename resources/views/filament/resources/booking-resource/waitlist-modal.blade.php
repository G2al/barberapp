<div class="space-y-4">
    @if ($entries->isEmpty())
        <p class="text-sm text-gray-600 dark:text-gray-400">Non ci sono persone in lista d’attesa per questo slot.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Posizione</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Cliente</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Inserito il</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Stato</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach ($entries as $entry)
                        @php
                            $status = [
                                'waiting' => 'In attesa',
                                'assigned' => 'Prenotazione assegnata',
                                'skipped' => 'Saltato',
                                'cancelled' => 'Ritirato',
                            ][$entry->status] ?? $entry->status;
                            $position = $positions[$entry->id] ?? '—';
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ $position }}</td>
                            <td class="px-4 py-3 text-gray-950 dark:text-white">
                                <div class="font-medium">{{ trim(($entry->user?->name ?? '') . ' ' . ($entry->user?->surname ?? '')) ?: ($entry->user?->email ?? 'Cliente') }}</div>
                                @if ($entry->user?->phone)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->user->phone }}</div>
                                @endif
                                @if ($entry->assigned_booking_id)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Prenotazione #{{ $entry->assigned_booking_id }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $entry->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
