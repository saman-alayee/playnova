@extends('layouts.app')
@section('title', 'کیف پول | PlayNova')

@section('content')
@php
    $walletJalali = function ($date) {
        $date = $date->copy()->timezone('Asia/Tehran');
        $gy = (int) $date->format('Y');
        $gm = (int) $date->format('n');
        $gd = (int) $date->format('j');
        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gDaysInMonth[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd) . ' ' . $date->format('H:i');
    };

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

    $statusLabels = [
        'pending' => 'در انتظار',
        'completed' => 'موفق',
        'failed' => 'ناموفق',
        'rejected' => 'رد شده',
    ];

    $statusStyles = [
        'pending' => 'text-yellow-300 bg-yellow-500/10 border-yellow-500/30',
        'completed' => 'text-green-300 bg-green-500/10 border-green-500/30',
        'failed' => 'text-red-300 bg-red-500/10 border-red-500/30',
        'rejected' => 'text-red-300 bg-red-500/10 border-red-500/30',
    ];
@endphp
<div class="grid md:grid-cols-2 gap-6 mb-6">
    <div class="bg-dark-800 border border-success/40 rounded-xl p-6">
        <p class="text-sm text-gray-400 mb-1">موجودی فعلی</p>
        <p class="text-3xl font-black text-secondary mb-4">{{ number_format(auth()->user()->wallet) }} تومان</p>

        <h3 class="font-bold mb-2">شارژ کیف پول</h3>
        @if(auth()->user()->isKycVerified())
            <p class="text-xs text-green-400/90 mb-2">احراز هویت تأیید شده — سقف واریز برداشته شده است.</p>
            <p class="text-xs text-gray-500 mb-2">حداکثر هر واریز: ۵۰,۰۰۰,۰۰۰ تومان</p>
            @php $maxDeposit = 50000000; @endphp
        @else
            <p class="text-xs text-amber-400/90 mb-2">تا تأیید احراز هویت، حداکثر موجودی کیف پول ۱,۰۰۰,۰۰۰ تومان است.</p>
            <p class="text-xs text-gray-500 mb-2"><a href="{{ route('kyc.index') }}" class="text-secondary hover:underline">ارسال مدارک احراز هویت</a></p>
            @php $maxDeposit = max(0, auth()->user()->kycWalletCap() - (int) auth()->user()->wallet); @endphp
        @endif
        @if(!auth()->user()->isKycVerified() && $maxDeposit <= 0)
            <p class="text-sm text-red-400">سقف موجودی پر شده. برای واریز بیشتر احراز هویت را تکمیل کنید.</p>
        @else
        <form method="POST" action="{{ route('wallet.deposit') }}" class="flex gap-2">
            @csrf
            <input type="number" name="amount" min="10000" max="{{ max(10000, $maxDeposit) }}" step="1000" placeholder="مبلغ به تومان" required
                class="flex-1 bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-success">
            <button class="bg-success hover:opacity-90 text-white rounded px-4 py-2 font-bold whitespace-nowrap">شارژ</button>
        </form>
        @endif
    </div>

    <div class="bg-dark-800 border border-danger/40 rounded-xl p-6">
        <h3 class="font-bold mb-2">درخواست برداشت وجه</h3>
        @if(auth()->user()->bank_card_number)
            <p class="text-xs text-gray-400 mb-3">کارت ثبت‌شده در پروفایل:
                <span class="font-mono" dir="ltr">{{ auth()->user()->bank_card_number }}</span>
                @if(auth()->user()->bank_account_name)
                    — {{ auth()->user()->bank_account_name }}
                @endif
            </p>
            <form method="POST" action="{{ route('wallet.withdraw') }}" class="space-y-3">
                @csrf
                <input type="number" name="amount" min="50000" step="1000" placeholder="مبلغ به تومان" required
                    value="{{ old('amount') }}"
                    class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-danger">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">تأیید شماره کارت (مطابق پروفایل)</label>
                    <input type="text" name="bank_card_confirm" placeholder="6037xxxxxxxxxxxx" dir="ltr" required
                        maxlength="24" inputmode="numeric" autocomplete="off"
                        value="{{ old('bank_card_confirm') }}"
                        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-danger font-mono">
                    @error('bank_card_confirm')
                        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button class="w-full bg-danger hover:opacity-90 text-white rounded px-4 py-2 font-bold">ثبت درخواست برداشت</button>
            </form>
        @else
            <p class="text-xs text-amber-400/90 mb-2"><a href="{{ route('profile.show') }}" class="underline">شماره کارت بانکی</a> را در پروفایل ثبت کنید.</p>
        @endif
        <p class="text-xs text-gray-500 mt-2">درخواست‌های برداشت پس از بررسی ادمین پرداخت می‌شوند.</p>
    </div>
</div>

<div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h3 class="font-bold mb-4">تاریخچه تراکنش‌ها</h3>
    <div class="overflow-x-auto -mx-2 px-2">
        <table class="w-full min-w-[640px] text-sm border-collapse">
            <thead>
                <tr class="text-gray-400 border-b border-dark-600">
                    <th class="text-right py-3 px-3 font-semibold whitespace-nowrap w-[30%]">تاریخ</th>
                    <th class="text-right py-3 px-3 font-semibold whitespace-nowrap w-[22%]">نوع</th>
                    <th class="text-right py-3 px-3 font-semibold whitespace-nowrap w-[28%]">مبلغ</th>
                    <th class="text-right py-3 px-3 font-semibold whitespace-nowrap w-[20%]">وضعیت</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $t)
                    @php
                        $shownAt = method_exists($t, 'displayedAt')
                            ? $t->displayedAt()
                            : (($t->type === 'withdraw' && in_array($t->status, ['completed', 'rejected'], true)) ? $t->updated_at : $t->created_at);
                        $statusKey = $t->status ?? 'completed';
                    @endphp
                    <tr class="border-b border-dark-700/80 hover:bg-dark-700/30 align-middle">
                        <td class="py-3 px-3 whitespace-nowrap">
                            <span class="inline-block font-mono text-xs sm:text-sm text-gray-200" dir="ltr">{{ $walletJalali($shownAt) }}</span>
                        </td>
                        <td class="py-3 px-3 whitespace-nowrap text-gray-200">{{ $typeLabels[$t->type] ?? $t->type }}</td>
                        <td class="py-3 px-3 whitespace-nowrap">
                            <span class="font-semibold text-white">{{ number_format($t->amount) }}</span>
                            <span class="text-gray-400 text-xs mr-1">تومان</span>
                        </td>
                        <td class="py-3 px-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-xs font-medium {{ $statusStyles[$statusKey] ?? 'text-gray-300 bg-dark-700 border-dark-600' }}">
                                {{ $statusLabels[$statusKey] ?? $statusKey }}
                            </span>
                            @if($statusKey === 'rejected')
                                @php
                                    $rejectReason = method_exists($t, 'rejectionReason') ? $t->rejectionReason() : null;
                                @endphp
                                @if($rejectReason)
                                    <p class="text-xs text-red-300 mt-1 leading-relaxed">دلیل: {{ $rejectReason }}</p>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-gray-500">تراکنشی ثبت نشده است.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</div>
@endsection
