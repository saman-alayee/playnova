@php
    $regCount = (float) ($t->registered_count ?? $t->registrations_count ?? 0);
    if (! is_numeric($regCount) || $regCount < 0) {
        $regCount = 0;
    }
    $capacity = (float) ($t->capacity ?? 1);
    if (! is_numeric($capacity) || $capacity <= 0) {
        $capacity = 1;
    }
    $entryFee = (float) ($t->entry_fee ?? 0);
    $prizePool = (float) ($t->prize_pool ?? 0);
    $remaining = max(0, $capacity - $regCount);
    $percent = $capacity > 0 ? min(100, max(0, round(($regCount / $capacity) * 100, 2))) : 0;
@endphp

<div class="card-tournament rounded-2xl p-5 {{ $compact ?? false ? 'min-w-[280px] max-w-[320px] shrink-0' : '' }}">
    <div class="flex justify-between items-start mb-3 gap-2">
        <h3 class="font-bold text-base text-white leading-snug">{{ $t->title ?? 'بدون عنوان' }}</h3>
        <span class="text-xs shrink-0 px-2 py-1 rounded-full @class([
            'bg-success/20 text-success' => $t->status === 'active',
            'bg-primary/20 text-primary' => $t->status === 'ongoing',
            'bg-secondary/20 text-secondary' => $t->status === 'upcoming',
            'bg-gray-700/30 text-gray-400' => ! in_array($t->status, ['active', 'ongoing', 'upcoming'], true),
        ])">
            {{ $t->statusLabel() }}
        </span>
    </div>
    <p class="text-xs text-gray-400 mb-2">{{ $t->game ?? 'Call of Duty Mobile' }}</p>
    @include('components.tournament-schedule', ['t' => $t, 'class' => 'text-xs text-gray-400 mb-3'])
    <div class="text-sm space-y-2 mb-3">
        <div class="flex justify-between"><span class="text-gray-400">ورودی:</span><span class="font-bold">{{ number_format($entryFee) }} تومان</span></div>
        <div class="flex justify-between"><span class="text-gray-400">جایزه:</span><span class="font-bold text-secondary">{{ number_format($prizePool) }} تومان</span></div>
        <div class="flex justify-between"><span class="text-gray-400">ثبت‌نام‌شده:</span><span class="font-bold"><span dir="ltr" class="inline-block">{{ number_format($regCount) }}/{{ number_format($capacity) }}</span></span></div>
    </div>
    <div class="w-full h-2 bg-gray-700 rounded-full overflow-hidden mb-4">
        <div class="tournament-capacity-fill h-full rounded-full {{ $regCount > 0 && $percent < 4 ? 'tournament-capacity-fill--min' : '' }}" style="width: {{ $percent }}%;"></div>
    </div>

    @include('components.tournament-actions', ['t' => $t])
</div>