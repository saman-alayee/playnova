@php
    $regCount = (float) ($regCount ?? $t->registrations_count ?? $t->registered_count ?? 0);
    $capacity = max(1, (float) ($capacity ?? $t->capacity ?? 1));
    $hasDescription = filled($t->description);
    $registerLabel = ($compact ?? false) ? 'ثبت‌نام' : 'ثبت‌نام';
    $loginLabel = 'اطلاعات ورود';
@endphp

<div class="tournament-actions {{ $hasDescription ? '' : 'tournament-actions--single' }}">
    @if($hasDescription)
        <button type="button"
            onclick="openDescriptionModal({{ json_encode($t->title, JSON_UNESCAPED_UNICODE) }}, {{ json_encode($t->description, JSON_UNESCAPED_UNICODE) }})"
            class="tournament-actions__btn tournament-actions__btn--outline">
            توضیحات
        </button>
    @endif

    @auth
        @php
            $userId = (int) Auth::id();
            $isRegistered = \App\Models\Registration::where('user_id', $userId)
                ->where('tournament_id', $t->id)
                ->whereNotNull('seat_number')
                ->exists();
            $pendingSeat = \App\Models\Registration::where('user_id', $userId)
                ->where('tournament_id', $t->id)
                ->whereNull('seat_number')
                ->where('status', 'waiting')
                ->where(function ($q) {
                    $q->where('reservation_type', 'solo')->orWhereNull('reservation_type');
                })
                ->exists();
            $pendingTeamOut = \App\Models\TeamInvite::where('tournament_id', $t->id)
                ->where('inviter_id', $userId)
                ->where('status', 'pending')
                ->exists();
        @endphp
        @if($isRegistered && $t->allowsGameLogin())
            <button type="button"
                onclick="openGameLoginModalById('{{ route('tournaments.game-login', $t) }}')"
                class="tournament-actions__btn tournament-actions__btn--primary">
                {{ $loginLabel }}
            </button>
        @elseif($isRegistered)
            <span class="tournament-actions__btn tournament-actions__btn--muted">ثبت‌نام شده</span>
        @elseif($pendingTeamOut)
            <span class="tournament-actions__btn tournament-actions__btn--muted">در انتظار تأیید هم‌تیمی</span>
        @elseif($pendingSeat)
            <a href="{{ route('tournaments.select-seat', $t) }}" class="tournament-actions__btn tournament-actions__btn--primary tournament-actions__btn--success">
                انتخاب جایگاه
            </a>
        @elseif($t->acceptsRegistration() && $regCount < $capacity)
            <button type="button" onclick="openRegisterModal({{ $t->id }})" class="tournament-actions__btn tournament-actions__btn--primary tournament-actions__btn--success">
                {{ $registerLabel }}
            </button>
        @elseif($t->status === 'ongoing')
            <span class="tournament-actions__btn tournament-actions__btn--muted">ثبت‌نام بسته شده</span>
        @elseif($t->acceptsRegistration())
            <span class="tournament-actions__btn tournament-actions__btn--muted">ظرفیت تکمیل</span>
        @else
            <span class="tournament-actions__btn tournament-actions__btn--muted">به‌زودی</span>
        @endif
    @else
        <a href="{{ route('login') }}" class="tournament-actions__btn tournament-actions__btn--primary">
            {{ ($compact ?? false) ? 'ورود' : 'ورود و ثبت‌نام' }}
        </a>
    @endauth
</div>
