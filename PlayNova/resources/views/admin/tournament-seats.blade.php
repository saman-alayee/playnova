@extends('layouts.app')
@section('title', 'جایگاه‌ها — ' . $tournament->title)

@section('content')
<h1 class="text-2xl font-bold mb-2">نقشه جایگاه‌ها</h1>
<p class="text-gray-400 text-sm mb-4">{{ $tournament->title }} — {{ $tournament->seatModeLabel() }} — {{ $tournament->registered_count }}/{{ $tournament->capacity }}</p>

@if(auth()->user()->isAdmin())
    @include('admin._nav')
@else
    <p class="mb-4"><a href="{{ route('admin.tournament-seats.index') }}" class="text-secondary text-sm hover:underline">← بازگشت به لیست مسابقات</a></p>
@endif

<style>
    .admin-seat-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; direction:ltr; }
    @media (min-width:640px){ .admin-seat-grid{ grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media (min-width:1024px){ .admin-seat-grid{ grid-template-columns:repeat(5,minmax(0,1fr)); } }
    .admin-team-card { border:1px solid rgba(212,175,55,.45); background:#111; padding:.5rem; }
    .admin-team-title { text-align:center; color:#d4af37; font-weight:800; font-size:.85rem; margin-bottom:.35rem; }
    .admin-team-slots { display:grid; gap:.35rem; direction:ltr; }
    .admin-seat { border:1px solid #374151; padding:.4rem; text-align:center; min-height:72px; font-size:.75rem; }
    .admin-seat--empty { background:#0a0a0a; color:#6b7280; }
    .admin-seat--filled { background:#1a1508; color:#e5e7eb; }
    .admin-seat__code { font-weight:800; direction:ltr; font-family:ui-monospace,monospace; }
    .admin-seat__user { color:#d4af37; margin-top:.15rem; word-break:break-word; }
    .admin-seat__cod { color:#9ca3af; font-size:.65rem; }
</style>

<div class="admin-seat-grid max-w-6xl">
    @foreach($tournament->teamsForGrid() as $teamRow)
        <div class="admin-team-card">
            <div class="admin-team-title">تیم {{ $teamRow['team'] }}</div>
            <div class="admin-team-slots" style="grid-template-columns:repeat({{ $tournament->seatMode() }},minmax(0,1fr));">
                @foreach($teamRow['slots'] as $slotInfo)
                    @php
                        $reg = $occupiedSeats->get($slotInfo['seat_number']);
                    @endphp
                    <div class="admin-seat {{ $reg ? 'admin-seat--filled' : 'admin-seat--empty' }}">
                        <div class="admin-seat__code">{{ $slotInfo['label'] }}</div>
                        @if($reg)
                            <div class="admin-seat__user">{{ $reg->user?->username ?? '—' }}</div>
                            <div class="admin-seat__cod">{{ $reg->user?->cod_id ?? '—' }}</div>
                        @else
                            <div class="mt-2">خالی</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
