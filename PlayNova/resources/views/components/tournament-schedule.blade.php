@if($t->start_date)
    @php $schedule = \App\Services\JalaliService::formatDateTime($t->start_date); @endphp
    <div class="tournament-schedule {{ $class ?? '' }}">
        <div class="tournament-schedule__date">
            <span class="tournament-schedule__date-label">🗓 تاریخ برگزاری</span>
            <span class="tournament-schedule__date-value">{{ $schedule['date'] }}</span>
        </div>
        <div class="tournament-schedule__time">
            <div class="tournament-schedule__time-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke-width="1.8"></circle>
                    <path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                ساعت ورود به بازی
            </div>
            <div class="tournament-schedule__time-value">{{ $schedule['time'] }}</div>
        </div>
    </div>
@endif
