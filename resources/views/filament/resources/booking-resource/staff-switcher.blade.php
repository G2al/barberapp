@php
    use Illuminate\Support\Facades\Storage;
@endphp

<div class="mb-4 overflow-x-auto md:hidden">
    <div class="flex gap-3">
        @foreach ($staffMembers as $staff)
            @php
                $tabKey = 'staff_' . $staff->id;
                $isActive = $this->activeTab === $tabKey;
                $imageUrl = $staff->image ? Storage::url($staff->image) : null;
            @endphp

            <button
                type="button"
                wire:click="$set('activeTab', '{{ $tabKey }}')"
                style="min-width: 92px;"
                @class([
                    'flex flex-col items-center gap-2 rounded-xl border px-3 py-3 text-sm font-semibold transition',
                    'border-primary-500 bg-primary-50 text-primary-700 shadow-sm' => $isActive,
                    'border-gray-200 bg-white text-gray-700 hover:border-primary-300 hover:bg-primary-50' => ! $isActive,
                ])
            >
                @if ($imageUrl)
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ $staff->full_name }}"
                        class="h-14 w-14 rounded-full object-cover ring-2 {{ $isActive ? 'ring-primary-500' : 'ring-gray-200' }}"
                    >
                @else
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-base font-bold ring-2 {{ $isActive ? 'ring-primary-500' : 'ring-gray-200' }}">
                        {{ mb_substr($staff->first_name, 0, 1) }}{{ mb_substr($staff->last_name, 0, 1) }}
                    </span>
                @endif

                <span class="truncate" style="max-width: 80px;">{{ $staff->first_name }}</span>
            </button>
        @endforeach
    </div>
</div>
