@extends('layouts.app')
@section('title', 'انتخاب جایگاه | ' . $tournament->title)

@section('content')
<style>
    .seat-page {
        background: radial-gradient(ellipse at top, #1a1508 0%, #050508 45%, #050508 100%);
    }
    .team-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        direction: ltr;
    }
    @media (min-width: 640px) {
        .team-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
    }
    @media (min-width: 1024px) {
        .team-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    .team-card {
        position: relative;
        background: linear-gradient(180deg, rgba(24, 20, 12, 0.95) 0%, rgba(10, 9, 8, 0.98) 100%);
        border: 1px solid rgba(212, 175, 55, 0.55);
        box-shadow: inset 0 0 0 1px rgba(212, 175, 55, 0.12), 0 8px 24px rgba(0, 0, 0, 0.45);
        padding: 0.45rem 0.5rem 0.55rem;
    }
    .team-card::before,
    .team-card::after {
        content: '';
        position: absolute;
        width: 14px;
        height: 14px;
        border-color: rgba(212, 175, 55, 0.85);
        border-style: solid;
        pointer-events: none;
    }
    .team-card::before {
        top: -1px; left: -1px;
        border-width: 2px 0 0 2px;
    }
    .team-card::after {
        bottom: -1px; right: -1px;
        border-width: 0 2px 2px 0;
    }
    .team-card__title {
        text-align: center;
        color: #d4af37;
        font-weight: 800;
        font-size: 0.95rem;
        margin-bottom: 0.45rem;
        letter-spacing: 0.02em;
    }
    .team-card__slots {
        display: grid;
        gap: 0.35rem;
        direction: ltr;
    }
    .seat-slot {
        position: relative;
        min-height: 88px;
        border: 1px solid rgba(212, 175, 55, 0.35);
        background: rgba(0, 0, 0, 0.35);
        padding: 0.35rem 0.25rem 0.5rem;
        text-align: center;
        transition: border-color 0.2s, background 0.2s, transform 0.15s;
    }
    button.seat-slot {
        cursor: pointer;
    }
    button.seat-slot:hover {
        border-color: rgba(212, 175, 55, 0.85);
        background: rgba(212, 175, 55, 0.08);
        transform: translateY(-1px);
    }
    button.seat-slot.is-selected {
        border-color: #d4af37;
        background: rgba(212, 175, 55, 0.16);
        box-shadow: 0 0 0 1px rgba(212, 175, 55, 0.35);
    }
    .seat-slot--taken {
        opacity: 0.72;
        cursor: default;
    }
    .seat-slot__top {
        color: #d4af37;
        font-size: 0.65rem;
        font-weight: 700;
        margin-bottom: 0.15rem;
    }
    .seat-slot__avatar {
        width: 36px;
        height: 36px;
        margin: 0 auto 0.2rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #8B5CF6, #d4af37);
        color: #fff;
        font-weight: 800;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }
    .seat-slot__code {
        display: block;
        color: #f5f5f5;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 0.04em;
        direction: ltr;
        font-family: ui-monospace, monospace;
    }
    .seat-slot__cod {
        font-size: 0.68rem;
        color: #d4af37;
        font-weight: 700;
        margin-top: 0.1rem;
        line-height: 1.25;
        word-break: break-word;
    }
    .seat-slot__user {
        font-size: 0.58rem;
        color: #9ca3af;
        margin-top: 0.1rem;
        line-height: 1.25;
        word-break: break-word;
    }
    .seat-slot__status {
        font-size: 0.58rem;
        color: #6b7280;
        margin-top: 0.1rem;
    }
</style>

<div class="fixed inset-0 z-[9999] seat-page flex flex-col">
    <div class="border-b border-amber-900/40 bg-black/70 px-4 py-4 shrink-0">
        <h1 class="text-xl md:text-2xl font-bold text-[#d4af37] text-center">انتخاب جایگاه</h1>
        <p class="text-center text-sm text-gray-400 mt-1">{{ $tournament->title }} — {{ $tournament->seatModeLabel() }}</p>
        <p class="text-center text-xs text-amber-400/90 mt-2">روی جایگاه خالی (مثلاً 2.1 یا 20.2) کلیک کنید و تأیید نمایید.</p>
        <p class="text-center mt-2 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('home') }}" class="text-xs text-gray-500 hover:text-gray-300 underline">بازگشت به صفحه اصلی</a>
            <form method="POST" action="{{ route('tournaments.cancel-pending', $tournament) }}" class="inline" onsubmit="return confirm('از ثبت‌نام انصراف می‌دهید؟');">
                @csrf
                <button type="submit" class="text-xs text-red-400 hover:text-red-300 underline">انصراف از ثبت‌نام</button>
            </form>
        </p>
    </div>

    <div class="flex-1 overflow-y-auto p-3 md:p-6">
        @if(session('info'))
            <div class="max-w-6xl mx-auto mb-4 rounded-lg border border-amber-700 bg-amber-900/30 px-4 py-3 text-amber-200 text-sm">{{ session('info') }}</div>
        @endif
        @if(session('error'))
            <div class="max-w-6xl mx-auto mb-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('tournaments.select-seat.store', $tournament) }}" id="seatForm" class="max-w-6xl mx-auto">
            @csrf
            <input type="hidden" name="seat_number" id="seat_number" value="">

            <div class="team-grid">
                @foreach($tournament->teamsForGrid() as $teamRow)
                    <div class="team-card">
                        <div class="team-card__title">تیم {{ $teamRow['team'] }}</div>
                        <div class="team-card__slots" style="grid-template-columns: repeat({{ $tournament->seatMode() }}, minmax(0, 1fr));">
                            @foreach($teamRow['slots'] as $slotInfo)
                                @php
                                    $seatNum = $slotInfo['seat_number'];
                                    $label = $slotInfo['label'];
                                    $reg = $occupiedSeats->get($seatNum);
                                @endphp
                                @if($reg)
                                    <div class="seat-slot seat-slot--taken">
                                        <div class="seat-slot__top">نفر {{ $slotInfo['slot'] }}</div>
                                        <div class="seat-slot__avatar">{{ strtoupper(substr($reg->user?->username ?? '?', 0, 1)) }}</div>
                                        <span class="seat-slot__code">{{ $label }}</span>
                                        @if($reg->user?->cod_id)
                                            <div class="seat-slot__cod">{{ $reg->user->cod_id }}</div>
                                        @endif
                                        <div class="seat-slot__user">{{ $reg->user?->username ?? '—' }}</div>
                                        <div class="seat-slot__status">پر شده</div>
                                    </div>
                                @else
                                    <button type="button" class="seat-slot seat-pick" data-seat="{{ $seatNum }}" data-label="{{ $label }}">
                                        <div class="seat-slot__top">نفر {{ $slotInfo['slot'] }}</div>
                                        <svg class="seat-slot__icon" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                                            <ellipse cx="32" cy="54" rx="18" ry="4" fill="rgba(212,175,55,0.2)"/>
                                            <circle cx="32" cy="22" r="11" fill="#64748b"/>
                                            <path d="M14 52c2-12 10-18 18-18s16 6 18 18" fill="#475569"/>
                                        </svg>
                                        <span class="seat-slot__code">{{ $label }}</span>
                                        <div class="seat-slot__status">خالی — کلیک</div>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </form>
    </div>
</div>

<div id="seatConfirmModal" class="fixed inset-0 z-[10000] hidden items-center justify-center p-4" style="background: rgba(0,0,0,0.88);">
    <div class="bg-dark-800 border border-[#d4af37]/50 rounded-2xl p-6 max-w-sm w-full shadow-2xl text-center">
        <h2 class="text-xl font-bold text-white mb-2">تأیید جایگاه</h2>
        <p class="text-gray-300 mb-1">آیا این جایگاه را انتخاب می‌کنید؟</p>
        <p class="text-4xl font-black text-[#d4af37] my-4 font-mono" dir="ltr" id="modalSeatNum">—</p>
        <p class="text-xs text-amber-400/90 mb-5">پس از تأیید، هزینه ثبت‌نام از کیف پول کسر و ثبت‌نام نهایی می‌شود.</p>
        <div class="flex gap-3">
            <button type="button" id="modalCancelBtn"
                class="flex-1 bg-gray-600 hover:bg-gray-500 text-white rounded-lg py-3 font-bold">انصراف</button>
            <button type="button" id="modalConfirmBtn"
                class="flex-1 bg-success hover:opacity-90 text-white rounded-lg py-3 font-bold">تأیید جایگاه</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const hidden = document.getElementById('seat_number');
    const form = document.getElementById('seatForm');
    const modal = document.getElementById('seatConfirmModal');
    const modalSeatNum = document.getElementById('modalSeatNum');
    const modalConfirmBtn = document.getElementById('modalConfirmBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');
    const picks = document.querySelectorAll('.seat-pick');
    let pendingSeat = null;

    function openModal(num, label) {
        pendingSeat = num;
        hidden.value = num;
        modalSeatNum.textContent = label || num;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        pendingSeat = null;
        hidden.value = '';
        picks.forEach(function (b) {
            b.classList.remove('is-selected');
        });
    }

    picks.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const num = this.dataset.seat;
            const label = this.dataset.label;
            picks.forEach(function (b) { b.classList.remove('is-selected'); });
            this.classList.add('is-selected');
            openModal(num, label);
        });
    });

    modalCancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    modalConfirmBtn.addEventListener('click', function () {
        if (!pendingSeat) return;
        hidden.value = pendingSeat;
        modalConfirmBtn.disabled = true;
        modalCancelBtn.disabled = true;
        modalConfirmBtn.textContent = 'در حال ثبت...';

        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData,
            redirect: 'follow',
            credentials: 'same-origin',
        }).then(function (resp) {
            window.location.replace(resp.url || @json(route('home')));
        }).catch(function () {
            form.submit();
        });
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
});
</script>
@endsection
