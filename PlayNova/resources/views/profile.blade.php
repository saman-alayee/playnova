@extends('layouts.app')
@section('title', 'پروفایل | PlayNova')

@section('content')
<div class="grid md:grid-cols-3 gap-6">
    <div class="md:col-span-1 bg-dark-800 border border-dark-600 rounded-xl p-6 text-center">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary to-secondary mx-auto mb-3 flex items-center justify-center text-2xl font-black">
            {{ strtoupper(substr($user->username, 0, 1)) }}
        </div>
        <h2 class="font-bold text-lg">{{ $user->username }}</h2>
        <p class="text-xs text-gray-400 mb-3">COD ID: {{ $user->cod_id ?? '—' }}</p>
        <div class="mt-4 bg-dark-700 rounded-lg p-3">
            <p class="text-xs text-gray-400">موجودی کیف پول</p>
            <p class="text-xl font-bold text-secondary">{{ number_format($user->wallet) }} تومان</p>
        </div>

        <!-- ========== کد دعوت (جدید) ========== -->
        <div class="mt-3 bg-dark-700 rounded-lg p-3">
            <p class="text-xs text-gray-400">کد معرف شما</p>
            <p class="font-mono text-primary font-bold">{{ $user->referral_code }}</p>
            <p class="text-xs text-gray-400 mt-2">لینک دعوت:</p>
            <div class="flex items-center gap-2 mt-1">
                <input type="text" id="referralLink" value="{{ route('register', ['ref' => $user->referral_code]) }}" 
                       class="w-full bg-dark-900 border border-dark-600 rounded px-2 py-1 text-xs text-gray-300" readonly>
                <button onclick="copyReferralLink()" class="text-xs bg-primary/20 text-primary px-2 py-1 rounded hover:bg-primary/30 transition">
                    کپی
                </button>
            </div>
            <script>
                function copyReferralLink() {
                    const input = document.getElementById('referralLink');
                    input.select();
                    document.execCommand('copy');
                    alert('✅ لینک دعوت کپی شد!');
                }
            </script>
        </div>
        <div class="mt-3 bg-dark-700 rounded-lg p-3">
            <p class="text-xs text-gray-400">پاداش معرف</p>
            <p class="text-sm text-secondary">{{ \App\Models\Setting::getReferralBonusPercent() }}% از اولین شارژ کاربر معرف</p>
        </div>

        @if(isset($activeSeats) && $activeSeats->isNotEmpty())
        <div class="mt-3 bg-dark-700 rounded-lg p-3 text-right">
            <p class="text-xs text-gray-400 mb-2">جایگاه‌های فعال شما</p>
            <ul class="space-y-2">
                @foreach($activeSeats as $reg)
                    <li class="text-sm">
                        <span class="text-white font-bold">{{ \Illuminate\Support\Str::limit($reg->tournament->title, 22) }}</span>
                        <span class="text-secondary font-mono" dir="ltr">{{ $reg->tournament->seatDisplayLabel((int) $reg->seat_number) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-[10px] text-gray-500 mt-2">پس از پایان مسابقه، جایگاه از این لیست حذف می‌شود.</p>
        </div>
        @endif
    </div>

    <div class="md:col-span-2 space-y-6">
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
            <h3 class="font-bold mb-4">ویرایش اطلاعات</h3>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="text" name="username" value="{{ old('username', $user->username) }}" placeholder="نام کاربری"
                    class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
                <input type="email" name="email" value="{{ old('email', $user->email) }}" placeholder="ایمیل"
                    class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
                <input type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}" placeholder="موبایل"
                    class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
                <input type="text" name="cod_id" value="{{ old('cod_id', $user->cod_id) }}" placeholder="آیدی کالاف"
                    class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
                    @if($user->cod_id_changed && $user->cod_id) readonly @endif>
                <p class="text-xs text-yellow-500/90">فقط یک‌بار امکان تغییر آیدی کالاف وجود دارد. در صورت نیاز به تغییرات بیشتر تیکت ثبت کنید. امکان حذف آیدی کالاف وجود ندارد.</p>
                <input type="password" name="password" placeholder="رمز عبور جدید (اختیاری)"
                    class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
                <input type="password" name="password_confirmation" placeholder="تکرار رمز عبور جدید"
                    class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
                <button class="bg-secondary hover:opacity-90 text-white rounded px-4 py-2 font-bold">ذخیره تغییرات</button>
            </form>
        </div>


        <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
            <h3 class="font-bold mb-2">اطلاعات حساب بانکی</h3>
            <p class="text-xs text-gray-500 mb-4">برای برداشت وجه و تطبیق با احراز هویت، شماره کارت و نام صاحب حساب را ثبت کنید.</p>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="username" value="{{ old('username', $user->username) }}">
                <input type="hidden" name="email" value="{{ old('email', $user->email) }}">
                <input type="hidden" name="mobile" value="{{ old('mobile', $user->mobile) }}">
                <input type="hidden" name="cod_id" value="{{ old('cod_id', $user->cod_id) }}">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">شماره کارت بانکی</label>
                    <input type="text" name="bank_card_number" value="{{ old('bank_card_number', $user->bank_card_number) }}" placeholder="6037xxxxxxxxxxxx" dir="ltr"
                        maxlength="24" inputmode="numeric"
                        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary font-mono">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">نام صاحب حساب (مطابق کارت بانکی)</label>
                    <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $user->bank_account_name) }}" placeholder="نام و نام خانوادگی"
                        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
                </div>
                <button class="bg-secondary hover:opacity-90 text-white rounded px-4 py-2 font-bold">ذخیره اطلاعات بانکی</button>
            </form>
        </div>
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
            <h3 class="font-bold mb-2">تراکنش‌ها</h3>
            <p class="text-sm text-gray-400">تاریخچه واریز و برداشت در <a href="{{ route('wallet.index') }}" class="text-secondary hover:underline">صفحه کیف پول</a> نمایش داده می‌شود.</p>
        </div>
    </div>
</div>
@endsection