<x-filament-panels::page>
    <style>
        .dark #booking-month {
            color: #f4f4f5;
            background-color: #18181b;
            border-color: rgb(255 255 255 / 0.12);
            color-scheme: dark;
        }

        .dark #booking-month option {
            color: #f4f4f5;
            background-color: #18181b;
        }
    </style>

    <div class="booking-resource-page">
        <div class="flex flex-col gap-y-6">
            <div class="flex items-center gap-3">
                <label for="booking-month" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    Mese
                </label>
                <select
                    id="booking-month"
                    wire:model.live="selectedMonth"
                    class="fi-select-input block w-auto rounded-lg border-gray-300 bg-white py-2 pl-3 pr-10 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                    @foreach (range(-6, 12) as $monthOffset)
                        @php
                            $optionMonth = now()->startOfMonth()->addMonths($monthOffset);
                        @endphp
                        <option value="{{ $optionMonth->format('Y-m') }}">
                            {{ ucfirst($optionMonth->locale('it')->translatedFormat('F Y')) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-filament-panels::resources.tabs />

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

            {{ $this->table }}

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    </div>
</x-filament-panels::page>
