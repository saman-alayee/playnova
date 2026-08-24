@extends('layouts.app')
@section('title', 'مدیریت برداشت‌ها | PlayNova')

@section('content')
@php
    $view = $view ?? 'withdrawals';
    $status = $status ?? 'pending';
    $statusLabels = [
        'pending' => 'در انتظار',
        'completed' => 'تأیید شده',
        'rejected' => 'رد شده',
        'failed' => 'ناموفق',
    ];
    $typeLabels = [
        'deposit' => 'شارژ',
        'withdraw' => 'برداشت',
        'fee' => 'ورودی مسابقه',
        'entry_fee' => 'ورودی مسابقه',
        'prize' => 'جایزه',
        'referral_bonus' => 'پاداش معرفی',
        'admin_credit' => 'واریز ادمین',
        'admin_debit' => 'کسر ادمین',
    ];
@endphp

<h1 class="text-2xl font-bold mb-4">مدیریت برداشت‌ها و تراکنش‌ها</h1>
@include('admin._nav')

<div class="flex flex-wrap gap-2 mb-4">
    <a href="{{ route('admin.withdrawals', ['view' => 'withdrawals', 'status' => $status]) }}"
        class="px-3 py-1.5 rounded text-sm {{ $view === 'withdrawals' ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-300' }}">درخواست‌های برداشت</a>
    <a href="{{ route('admin.withdrawals', ['view' => 'transactions']) }}"
        class="px-3 py-1.5 rounded text-sm {{ $view === 'transactions' ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-300' }}">همه تراکنش‌ها</a>
</div>

@if($view === 'transactions')
    <form method="GET" class="flex flex-wrap gap-2 mb-4 items-end">
        <input type="hidden" name="view" value="transactions">
        <div>
            <label class="block text-xs text-gray-400 mb-1">جستجوی کاربر</label>
            <input type="text" name="user_search" value="{{ request('user_search') }}" placeholder="نام کاربری، موبایل، آیدی کالاف"
                class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-sm outline-none focus:border-secondary min-w-[220px]">
        </div>
        <div>
            <label class="block text-xs text-gray-400 mb-1">نوع تراکنش</label>
            <select name="tx_type" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-sm outline-none focus:border-secondary">
                <option value="all" @selected(request('tx_type', 'all') === 'all')>همه</option>
                @foreach($typeLabels as $key => $label)
                    <option value="{{ $key }}" @selected(request('tx_type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="bg-dark-600 hover:bg-dark-500 text-white px-4 py-2 rounded text-sm">جستجو</button>
    </form>

    <div class="bg-dark-800 border border-dark-600 rounded-xl overflow-x-auto">
        <table class="w-full text-sm min-w-[800px]">
            <thead>
                <tr class="bg-dark-700 text-gray-400">
                    <th class="py-2 px-3 text-right">کاربر</th>
                    <th class="py-2 px-3 text-right">نوع</th>
                    <th class="py-2 px-3 text-right">مبلغ</th>
                    <th class="py-2 px-3 text-right">وضعیت</th>
                    <th class="py-2 px-3 text-right">توضیح</th>
                    <th class="py-2 px-3 text-right">تاریخ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr class="border-b border-dark-700">
                        <td class="py-2 px-3">{{ $tx->user->username ?? '—' }}</td>
                        <td class="py-2 px-3">{{ $typeLabels[$tx->type] ?? $tx->type }}</td>
                        <td class="py-2 px-3">{{ number_format($tx->amount) }} تومان</td>
                        <td class="py-2 px-3">{{ $statusLabels[$tx->status] ?? $tx->status }}</td>
                        <td class="py-2 px-3 text-xs text-gray-400 max-w-xs truncate" title="{{ $tx->description }}">{{ $tx->description ?: '—' }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-gray-500">تراکنشی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
@else
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(['pending' => 'در انتظار', 'all' => 'همه', 'completed' => 'تأیید شده', 'rejected' => 'رد شده'] as $key => $label)
            <a href="{{ route('admin.withdrawals', ['view' => 'withdrawals', 'status' => $key]) }}"
                class="px-3 py-1 rounded text-xs {{ $status === $key ? 'bg-primary text-white' : 'bg-dark-700 text-gray-300' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse($withdrawals as $w)
            @php
                $card = $w->user->bank_card_number ?? null;
                if (! $card && preg_match('/کارت:\s*([0-9]+)/u', (string) $w->description, $m)) {
                    $card = $m[1];
                }
                $holder = $w->user->bank_account_name ?? null;
                if (! $holder && preg_match('/صاحب حساب:\s*([^|]+)/u', (string) $w->description, $m2)) {
                    $holder = trim($m2[1]);
                }
                $rejectReason = method_exists($w, 'rejectionReason') ? $w->rejectionReason() : null;
                $userTxs = ($userTransactions ?? collect())->get($w->user_id, collect());
            @endphp
            <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-3">
                    <div><span class="text-gray-400">کاربر:</span> <strong>{{ $w->user->username ?? '—' }}</strong></div>
                    <div><span class="text-gray-400">مبلغ:</span> <strong class="text-secondary">{{ number_format($w->amount) }} تومان</strong></div>
                    <div><span class="text-gray-400">کارت:</span> <span dir="ltr" class="font-mono text-xs">{{ $card ?: '—' }}</span></div>
                    <div><span class="text-gray-400">صاحب حساب:</span> {{ $holder ?: '—' }}</div>
                    <div>
                        <span class="text-gray-400">وضعیت:</span>
                        <span class="@if($w->status === 'pending') text-yellow-300 @elseif($w->status === 'completed') text-green-300 @else text-red-300 @endif">
                            {{ $statusLabels[$w->status] ?? $w->status }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-400">تاریخ:</span>
                        @if($w->status === 'pending')
                            درخواست {{ $w->created_at->format('Y-m-d H:i') }}
                        @else
                            {{ $w->status === 'completed' ? 'تأیید' : 'رد' }} {{ $w->displayedAt()->format('Y-m-d H:i') }}
                        @endif
                    </div>
                </div>

                @if($rejectReason)
                    <p class="text-sm text-red-300 mb-3 bg-red-500/10 border border-red-500/20 rounded px-3 py-2">دلیل رد: {{ $rejectReason }}</p>
                @endif

                @if($w->status === 'pending')
                    <details class="mb-3 group">
                        <summary class="cursor-pointer text-sm text-secondary hover:underline select-none">بررسی و تصمیم‌گیری</summary>
                        <div class="mt-3 grid md:grid-cols-2 gap-4">
                            <div class="border border-green-500/30 rounded-lg p-3 bg-green-500/5">
                                <h4 class="font-bold text-green-300 text-sm mb-2">تأیید برداشت</h4>
                                <p class="text-xs text-gray-400 mb-3">پس از تأیید، مبلغ {{ number_format($w->amount) }} تومان به کاربر پرداخت‌شده ثبت می‌شود.</p>
                                <form method="POST" action="{{ route('admin.withdrawals.update', $w) }}"
                                    onsubmit="return confirm('آیا از تأیید برداشت {{ number_format($w->amount) }} تومان برای {{ $w->user->username ?? 'کاربر' }} مطمئن هستید؟')">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="completed">
                                    <label class="flex items-start gap-2 text-xs text-gray-300 mb-2">
                                        <input type="checkbox" required class="mt-0.5">
                                        <span>اطلاعات کارت را بررسی کردم و تأیید می‌کنم.</span>
                                    </label>
                                    <button type="submit" class="w-full bg-success text-white py-2 rounded text-sm font-bold">تأیید نهایی برداشت</button>
                                </form>
                            </div>
                            <div class="border border-red-500/30 rounded-lg p-3 bg-red-500/5">
                                <h4 class="font-bold text-red-300 text-sm mb-2">رد برداشت</h4>
                                <p class="text-xs text-gray-400 mb-2">مبلغ به کیف پول کاربر بازگردانده می‌شود.</p>
                                <form method="POST" action="{{ route('admin.withdrawals.update', $w) }}"
                                    onsubmit="return confirm('آیا از رد این درخواست برداشت مطمئن هستید؟')">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="rejected">
                                    <textarea name="rejection_reason" rows="3" required maxlength="500" placeholder="دلیل رد (الزامی — به کاربر نمایش داده می‌شود)"
                                        class="w-full bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs outline-none focus:border-danger resize-y mb-2"></textarea>
                                    <button type="submit" class="w-full bg-danger text-white py-2 rounded text-sm font-bold">رد نهایی برداشت</button>
                                </form>
                            </div>
                        </div>
                    </details>
                @endif

                <details>
                    <summary class="cursor-pointer text-sm text-gray-300 hover:text-white select-none">تراکنش‌های این کاربر ({{ $userTxs->count() }})</summary>
                    @if($userTxs->isEmpty())
                        <p class="text-xs text-gray-500 mt-2">تراکنشی ثبت نشده.</p>
                    @else
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full text-xs min-w-[640px]">
                                <thead>
                                    <tr class="text-gray-500 border-b border-dark-600">
                                        <th class="py-1 px-2 text-right">تاریخ</th>
                                        <th class="py-1 px-2 text-right">نوع</th>
                                        <th class="py-1 px-2 text-right">مبلغ</th>
                                        <th class="py-1 px-2 text-right">وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($userTxs->take(30) as $tx)
                                        <tr class="border-b border-dark-700/50">
                                            <td class="py-1 px-2 whitespace-nowrap">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                                            <td class="py-1 px-2">{{ $typeLabels[$tx->type] ?? $tx->type }}</td>
                                            <td class="py-1 px-2">{{ number_format($tx->amount) }}</td>
                                            <td class="py-1 px-2">{{ $statusLabels[$tx->status] ?? $tx->status }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </details>
            </div>
        @empty
            <div class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">درخواست برداشتی در این فیلتر یافت نشد.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $withdrawals->links() }}</div>
@endif
@endsection
