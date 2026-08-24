@extends('layouts.app')
@section('title', 'PlayNova | پلتفرم مسابقات آنلاین Call of Duty Mobile')
@section('meta_description', 'PlayNova پلتفرم رسمی برگزاری مسابقات آنلاین Call of Duty Mobile با جوایز نقدی، ثبت‌نام آسان و رقابت زنده.')
@section('canonical', url('/'))

@push('structured_data')
    @include('components.home-structured-data')
@endpush

@section('content')
@php
    $heroSlides = [
        asset('hero-slide-1.png'),
        asset('hero-slide-2.png'),
        asset('hero-slide-3.png'),
    ];
    $leagueMeta = [
        'beginner' => ['title' => 'مبتدی', 'subtitle' => 'مناسب برای تازه‌کارها', 'class' => 'league-card--beginner', 'icon' => 'beginner'],
        'intermediate' => ['title' => 'متوسط', 'subtitle' => 'برای بازیکنان با تجربه', 'class' => 'league-card--intermediate', 'icon' => 'intermediate'],
        'professional' => ['title' => 'حرفه‌ای', 'subtitle' => 'برای حرفه‌ای‌های واقعی', 'class' => 'league-card--professional', 'icon' => 'professional'],
    ];
@endphp

<!-- Hero Carousel -->
<section class="hero-carousel" x-data="{ active: 0, total: {{ count($heroSlides) }} }" x-init="setInterval(() => { active = (active + 1) % total }, 5000)">
    @foreach($heroSlides as $i => $img)
        <div class="hero-slide" :class="{ 'is-active': active === {{ $i }} }" style="background-image: url('{{ $img }}');">
            <div class="hero-content">
                <a href="#special" class="btn-glow-primary hero-cta">مشاهده مسابقات</a>
            </div>
        </div>
    @endforeach
    <div class="hero-dots">
        @foreach($heroSlides as $i => $img)
            <button type="button" class="hero-dot" :class="{ 'is-active': active === {{ $i }} }" @click="active = {{ $i }}" aria-label="اسلاید {{ $i + 1 }}"></button>
        @endforeach
    </div>
</section>

<!-- دسته‌بندی مسابقات -->
<section class="mb-8">
    <h2 class="text-lg font-bold mb-4 text-white">دسته‌بندی مسابقات</h2>
    <div class="space-y-3">
        @foreach($leagueMeta as $key => $meta)
            <div class="league-card {{ $meta['class'] }} rounded-2xl p-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="league-card__shield">
                        @include('components.league-icon', ['level' => $meta['icon']])
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-base text-white">{{ $meta['title'] }}</h3>
                        <p class="text-xs text-gray-400">{{ $meta['subtitle'] }}</p>
                    </div>
                </div>
                <a href="#league-{{ $key }}" class="league-card__btn shrink-0 text-xs font-bold px-3 py-2 rounded-xl border transition">مشاهده مسابقات</a>
            </div>
        @endforeach
    </div>
</section>

<!-- مسابقات ویژه -->
<section id="special" class="mb-8 scroll-mt-24">
    <h2 class="text-lg font-bold mb-4 text-white">مسابقات ویژه</h2>
    @if($activeTournaments->isEmpty())
        <div class="text-center py-10 bg-dark-800/50 rounded-2xl border border-dark-600">
            <p class="text-gray-500">در حال حاضر مسابقه فعالی وجود ندارد.</p>
        </div>
    @else
        <div class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory special-scroll">
            @foreach($activeTournaments as $idx => $t)
                @php
                    $regCount = (float) ($t->registrations_count ?? $t->registered_count ?? 0);
                    $capacity = max(1, (float) ($t->capacity ?? 1));
                    $heroImg = $heroSlides[$idx % count($heroSlides)];
                @endphp
                <article class="special-card snap-start">
                    <div class="special-card__img" style="background-image: url('{{ $heroImg }}');"></div>
                    <div class="special-card__body">
                        <h3 class="font-bold text-sm text-white mb-1 truncate">{{ $t->title }}</h3>
                        @include('components.tournament-schedule', ['t' => $t, 'class' => 'text-xs text-gray-400 mb-1'])
                        @include('components.tournament-stats', ['t' => $t, 'regCount' => $regCount, 'capacity' => $capacity])
                        @include('components.tournament-actions', ['t' => $t, 'regCount' => $regCount, 'capacity' => $capacity, 'compact' => true])
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

<!-- مسابقات هر لیگ -->
@foreach($leagueMeta as $key => $meta)
    @php $items = $leagues[$key] ?? collect(); @endphp
    @if($items->isNotEmpty())
        <section id="league-{{ $key }}" class="mb-8 scroll-mt-24">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-white">مسابقات {{ $meta['title'] }}</h2>
                <span class="text-xs text-gray-500">{{ $items->count() }} مسابقه</span>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($items as $t)
                    @include('components.tournament-card', ['t' => $t])
                @endforeach
            </div>
        </section>
    @endif
@endforeach

@auth
    @php
        $registerModalTournaments = collect($activeTournaments);
        foreach ($leagues as $leagueItems) {
            $registerModalTournaments = $registerModalTournaments->merge($leagueItems);
        }
        $registerModalTournaments = $registerModalTournaments->unique('id');
    @endphp
    @foreach($registerModalTournaments as $t)
        @php
            $modalRegCount = (float) ($t->registrations_count ?? $t->registered_count ?? 0);
            $modalCapacity = (float) ($t->capacity ?? 1);
            $modalIsRegistered = \App\Models\Registration::where('user_id', Auth::id())
                ->where('tournament_id', $t->id)
                ->whereNotNull('seat_number')
                ->exists();
            $modalPendingTeam = \App\Models\TeamInvite::where('tournament_id', $t->id)
                ->where('inviter_id', Auth::id())
                ->where('status', 'pending')
                ->exists();
        @endphp
        @include('components.register-tournament-modal', compact('t', 'modalRegCount', 'modalCapacity', 'modalIsRegistered', 'modalPendingTeam'))
    @endforeach
@endauth

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.register-tournament-modal').forEach(function (modal) {
        if (modal.parentElement !== document.body) document.body.appendChild(modal);
    });
    document.querySelectorAll('[id^="acceptRules-"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var id = this.id.replace('acceptRules-', '');
            var btn = document.getElementById('nextRegisterStepBtn-' + id);
            if (!btn) return;
            btn.disabled = !this.checked;
            btn.style.opacity = this.checked ? '1' : '0.5';
            btn.style.cursor = this.checked ? 'pointer' : 'not-allowed';
        });
    });
    document.querySelectorAll('[id^="nextRegisterStepBtn-"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.id.replace('nextRegisterStepBtn-', '');
            var rules = document.getElementById('registerStepRules-' + id);
            var typeStep = document.getElementById('registerStepType-' + id);
            if (rules) rules.style.display = 'none';
            if (typeStep) typeStep.style.display = 'block';
        });
    });
});
function closeRegisterModal(id) {
    var m = document.getElementById('registerModal-' + id);
    if (m) m.style.display = 'none';
    backToRegisterRules(id);
}
function backToRegisterRules(id) {
    ['registerStepRules', 'registerStepType', 'registerStepTeam'].forEach(function (prefix) {
        var el = document.getElementById(prefix + '-' + id);
        if (el) el.style.display = prefix === 'registerStepRules' ? 'block' : 'none';
    });
    var cb = document.getElementById('acceptRules-' + id);
    var btn = document.getElementById('nextRegisterStepBtn-' + id);
    if (cb) cb.checked = false;
    if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; btn.style.cursor = 'not-allowed'; }
}
function backToRegisterType(id) {
    var typeStep = document.getElementById('registerStepType-' + id);
    var teamStep = document.getElementById('registerStepTeam-' + id);
    if (typeStep) typeStep.style.display = 'block';
    if (teamStep) teamStep.style.display = 'none';
}
function showTeamInviteStep(id) {
    var typeStep = document.getElementById('registerStepType-' + id);
    var teamStep = document.getElementById('registerStepTeam-' + id);
    if (typeStep) typeStep.style.display = 'none';
    if (teamStep) teamStep.style.display = 'block';
}
window.openRegisterModal = function (id) {
    var modal = document.getElementById('registerModal-' + id);
    if (!modal) return;
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
    modal.style.display = 'flex';
    backToRegisterRules(id);
};
</script>
@endpush
@endsection
