@extends('layouts.app')
@section('title', $tournament->title . ' | PlayNova')

@section('content')
<div class="max-w-2xl mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <div class="flex justify-between items-start mb-4">
        <h1 class="text-2xl font-bold">{{ $tournament->title }}</h1>
        @php
            $statusLabel = ['upcoming'=>'آینده','active'=>'فعال','ended'=>'پایان یافته','cancelled'=>'لغو شده'];
            $statusColor = ['upcoming'=>'bg-secondary text-white','active'=>'bg-success text-white','ended'=>'bg-gray-600','cancelled'=>'bg-gray-800'];
        @endphp
        <span class="text-xs px-3 py-1 rounded-full font-bold {{ $statusColor[$tournament->status] }}">{{ $statusLabel[$tournament->status] }}</span>
    </div>

    <p class="text-gray-400 mb-4">{{ $tournament->description ?? 'توضیحاتی برای این مسابقه ثبت نشده است.' }}</p>

    <div class="grid grid-cols-2 gap-4 text-sm mb-6">
        <div class="bg-dark-700 rounded-lg p-3">
            <p class="text-gray-400">مبلغ ورودی</p>
            <p class="font-bold">{{ number_format($tournament->entry_fee) }} تومان</p>
        </div>
        <div class="bg-dark-700 rounded-lg p-3">
            <p class="text-gray-400">جایزه کل</p>
            <p class="font-bold text-secondary">{{ number_format($tournament->prize_pool) }} تومان</p>
        </div>
        <div class="bg-dark-700 rounded-lg p-3">
            <p class="text-gray-400">تاریخ شروع</p>
            <p class="font-bold">{{ $tournament->start_date->format('Y-m-d H:i') }}</p>
        </div>
        <div class="bg-dark-700 rounded-lg p-3">
            <p class="text-gray-400">ظرفیت باقیمانده</p>
            <p class="font-bold">{{ $tournament->remaining_capacity }} / {{ $tournament->capacity }}</p>
        </div>
        <div class="bg-dark-700 rounded-lg p-3 sm:col-span-2">
            <p class="text-gray-400">نوع چیدمان جایگاه</p>
            <p class="font-bold">{{ $tournament->seatModeLabel() }}</p>
        </div>
    </div>

    @auth
        @if($isRegistered)
            <div class="bg-green-900/30 border border-green-500 text-green-300 rounded-lg p-3 text-center text-sm mb-4">
                ✅ شما در این مسابقه ثبت‌نام کرده‌اید.
                @if(isset($registration) && $registration->seat_number)
                    — جایگاه شما: <strong>{{ $registration->seat_number }}</strong>
                    @if(auth()->user()->cod_id)
                        — آیدی کالاف: <span class="font-mono" dir="ltr">{{ auth()->user()->cod_id }}</span>
                    @endif
                @endif
            </div>
        @elseif($tournament->isFull())
            <div class="bg-gray-800 border border-gray-600 text-gray-400 rounded-lg p-3 text-center text-sm">ظرفیت این مسابقه تکمیل شده است.</div>
        @else
            <button onclick="document.getElementById('modalRules').style.display='flex'" 
                    class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold shadow-glowsuccess text-sm">
                ثبت‌نام در مسابقه
            </button>
            <div id="modalRules" 
                 style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.85); align-items: center; justify-content: center; padding: 1rem;"
                 onclick="if(event.target===this) document.getElementById('modalRules').style.display='none'">
                <div style="background: #1a1a2e; border: 1px solid #374151; border-radius: 16px; max-width: 640px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8);">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #8B5CF6; margin-bottom: 1rem;">📜 تأیید خواندن قوانین</h2>
                    <div style="background: #0f0f1a; border-radius: 8px; padding: 1rem; max-height: 240px; overflow-y: auto; border: 1px solid #374151; color: #d1d5db; font-size: 0.875rem; line-height: 1.8; white-space: pre-line;">
                        @php
                            $rules = \App\Models\Rule::all();
                        @endphp
                        @if($rules->isEmpty())
                            <p style="color: #6b7280;">هیچ قانونی ثبت نشده است.</p>
                        @else
                            @foreach($rules as $index => $rule)
                                <div style="margin-bottom: 0.75rem;">
                                    <strong style="color: #3B82F6;">بخش {{ $index + 1 }}:</strong>
                                    <span>{{ $rule->content }}</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    <div style="margin-top: 1rem; display: flex; align-items: flex-start; gap: 0.5rem;">
                        <input type="checkbox" id="acceptRules" style="margin-top: 0.25rem; width: 1rem; height: 1rem; accent-color: #8B5CF6;">
                        <label for="acceptRules" style="color: #d1d5db; font-size: 0.875rem;">
                            قوانین و مقررات را مطالعه کرده و با تمامی موارد آن موافقم.
                        </label>
                    </div>
                    <div style="margin-top: 1rem; display: flex; gap: 0.75rem;">
                        <form method="POST" action="{{ route('tournaments.register', $tournament) }}" style="flex: 1;">
                            @csrf
                            <button type="submit" id="confirmBtn" disabled
                                    style="width: 100%; background: #22C55E; color: white; border: none; border-radius: 8px; padding: 0.5rem 0; font-weight: 700; opacity: 0.5; cursor: not-allowed; transition: all 0.2s;">
                                تأیید و ثبت‌نام
                            </button>
                        </form>
                        <button onclick="document.getElementById('modalRules').style.display='none'" 
                                style="background: #4b5563; color: white; border: none; border-radius: 8px; padding: 0.5rem 1.5rem; font-weight: 700; cursor: pointer;">
                            انصراف
                        </button>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const cb = document.getElementById('acceptRules');
                    const btn = document.getElementById('confirmBtn');
                    if (cb && btn) {
                        cb.addEventListener('change', function() {
                            if (this.checked) {
                                btn.disabled = false;
                                btn.style.opacity = '1';
                                btn.style.cursor = 'pointer';
                            } else {
                                btn.disabled = true;
                                btn.style.opacity = '0.5';
                                btn.style.cursor = 'not-allowed';
                            }
                        });
                    }
                });
            </script>
        @endif
    @else
        <a href="{{ route('login') }}" class="block text-center bg-success hover:opacity-90 text-white rounded py-3 font-bold shadow-glowsuccess">برای ثبت‌نام وارد شوید</a>
    @endauth

    <div class="mt-6">
        <h3 class="font-bold mb-3 text-sm text-gray-400">نقشه جایگاه‌ها ({{ $players->whereNotNull('seat_number')->count() }} / {{ $tournament->capacity }})</h3>
        <div class="grid gap-2 mb-6" style="grid-template-columns: repeat({{ $tournament->seatMode() }}, minmax(0, 1fr));">
            @foreach($tournament->seatNumbers() as $num)
                @php $reg = ($occupiedSeats ?? collect())->get($num); @endphp
                <div class="rounded-lg border border-dark-600 bg-dark-900/50 p-2 text-center text-xs">
                    <div class="text-gray-500">#{{ $num }}</div>
                    @if($reg)
                        <div class="font-bold text-gray-200 truncate">{{ $reg->user->username }}</div>
                        <div class="text-secondary font-mono truncate" dir="ltr">{{ $reg->user->cod_id ?: '—' }}</div>
                    @else
                        <div class="text-gray-600">خالی</div>
                    @endif
                </div>
            @endforeach
        </div>

        <h3 class="font-bold mb-2 text-sm text-gray-400">بازیکنان ثبت‌نام شده ({{ $players->count() }})</h3>
        <div class="flex flex-wrap gap-2">
            @forelse($players as $reg)
                <span class="text-xs bg-dark-700 px-2 py-1 rounded">{{ $reg->user->username }}</span>
            @empty
                <span class="text-xs text-gray-500">هنوز کسی ثبت‌نام نکرده است.</span>
            @endforelse
        </div>
    </div>
</div>
@endsection