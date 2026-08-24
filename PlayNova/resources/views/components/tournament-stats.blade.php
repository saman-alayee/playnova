@php
    $regCount = (float) ($regCount ?? $t->registered_count ?? $t->registrations_count ?? 0);
    $capacity = max(1, (float) ($capacity ?? $t->capacity ?? 1));
    $entryFee = (float) ($t->entry_fee ?? 0);
    $prizePool = (float) ($t->prize_pool ?? 0);
@endphp
<div class="tournament-stats {{ $class ?? 'text-xs space-y-1.5 mb-2' }}">
    <div class="flex justify-between gap-2">
        <span class="text-gray-400">ورودی:</span>
        <span class="font-bold text-white">{{ number_format($entryFee) }} تومان</span>
    </div>
    <div class="flex justify-between gap-2">
        <span class="text-gray-400">جایزه:</span>
        <span class="font-bold text-secondary">{{ number_format($prizePool) }} تومان</span>
    </div>
    <div class="flex justify-between gap-2">
        <span class="text-gray-400">ظرفیت:</span>
        <span class="font-bold text-white"><span dir="ltr" class="inline-block">{{ number_format($regCount) }}/{{ number_format($capacity) }}</span></span>
    </div>
</div>