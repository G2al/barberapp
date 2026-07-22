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
                style="min-width: 92px; {{ $isActive ? 'background:#111827;color:#facc15;border-color:#d4af37;box-shadow:0 8px 18px rgba(17,24,39,.18);' : 'background:#fffbeb;color:#3f2f08;border-color:#f3d88b;' }}"
                @class([
                    'flex flex-col items-center gap-2 rounded-xl border px-3 py-3 text-sm font-semibold transition',
                    'shadow-sm' => $isActive,
                    'hover:border-amber-400 hover:bg-amber-50' => ! $isActive,
                ])
            >
                @if ($imageUrl)
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ $staff->full_name }}"
                        class="h-14 w-14 rounded-full object-cover ring-2 {{ $isActive ? 'ring-amber-300' : 'ring-amber-200' }}"
                    >
                @else
                    <span class="flex h-14 w-14 items-center justify-center rounded-full text-base font-bold ring-2 {{ $isActive ? 'bg-gray-900 text-amber-300 ring-amber-300' : 'bg-amber-100 text-amber-900 ring-amber-200' }}">
                        {{ mb_substr($staff->first_name, 0, 1) }}{{ mb_substr($staff->last_name, 0, 1) }}
                    </span>
                @endif

                <span class="truncate" style="max-width: 80px;">{{ $staff->first_name }}</span>
            </button>
        @endforeach
    </div>
</div>
