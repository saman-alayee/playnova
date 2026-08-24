@extends('layouts.app')
@section('title', 'تنظیمات پیامک | پنل مدیریت')

@section('content')
@php
    $provider = \App\Models\Setting::getSmsProvider() ?: 'test';
    if (! in_array($provider, ['test', 'melipayamak'], true)) {
        $provider = \App\Models\Setting::isSmsActive() ? 'melipayamak' : 'test';
    }
    $meliReady = \App\Models\Setting::isMelipayamakConfigured();
    $patterns = old('sms_patterns', \App\Models\Setting::getSmsPatterns());
@endphp
<div class="max-w-3xl mx-auto" x-data="smsPatternManager(@js($patterns))">
    <h1 class="text-2xl font-bold mb-2 text-primary">تنظیمات پیامک (SMS)</h1>
    <p class="text-sm text-gray-500 mb-6">سرویس: ملی‌پیامک — <span class="text-gray-400">SendOtp</span> یا <span class="text-gray-400">BaseServiceNumber (پترن)</span></p>
    @include('admin._nav')

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-700 bg-green-900/30 px-4 py-3 text-green-300 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-6">
        <form method="POST" action="{{ route('admin.sms.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-lg border border-dark-600 bg-dark-900/40 p-4 space-y-3">
                <label for="sms_provider" class="block text-gray-200 font-bold">حالت ارسال</label>
                <select id="sms_provider" name="sms_provider" x-model="provider"
                    class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2.5 text-white outline-none focus:border-secondary">
                    <option value="test">حالت تست — کد OTP روی صفحه (بدون اتصال به پنل)</option>
                    <option value="melipayamak">ارسال واقعی — فعال‌سازی پنل ملی‌پیامک</option>
                </select>
                <p class="text-xs text-gray-500 leading-relaxed" x-show="provider === 'test'">
                    در حالت تست می‌توانید اطلاعات پنل را پایین وارد و ذخیره کنید؛ تا زمانی که «ارسال واقعی» را انتخاب نکنید، پیامکی ارسال نمی‌شود.
                </p>
                <p class="text-xs text-amber-400/90 leading-relaxed" x-show="provider === 'melipayamak'" x-cloak>
                    با انتخاب این حالت، پس از ذخیره، OTP ثبت‌نام و بازیابی رمز از طریق API ملی‌پیامک ارسال می‌شود (نیاز به تکمیل فیلدهای پنل).
                </p>
            </div>

            <div class="rounded-lg border border-dark-600 bg-dark-900/40 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="text-right flex-1">
                        <p class="text-sm text-gray-200 font-bold">تأیید موبایل بعد از ثبت‌نام</p>
                        <p class="text-xs text-gray-500 mt-1">کاربر پس از فرم ثبت‌نام به صفحه وارد کردن کد ۶ رقمی هدایت می‌شود.</p>
                    </div>
                    <input type="checkbox" name="sms_register_verify" value="1" class="h-5 w-5 shrink-0 accent-green-500"
                        {{ \App\Models\Setting::isSmsRegisterVerifyEnabled() ? 'checked' : '' }}>
                </div>
            </div>

            <div class="border-t border-dark-600 pt-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-bold text-gray-200">اتصال به پنل ملی‌پیامک</h2>
                    @if ($meliReady)
                        <span class="text-xs px-2 py-1 rounded-full bg-green-900/50 text-green-400 border border-green-800">اطلاعات پنل ذخیره شده</span>
                    @else
                        <span class="text-xs px-2 py-1 rounded-full bg-gray-800 text-gray-400 border border-gray-700">ناقص — برای ارسال واقعی پر کنید</span>
                    @endif
                </div>

                <div class="text-xs text-gray-500 bg-dark-900/60 border border-dark-600 rounded-lg p-3 leading-relaxed space-y-1">
                    <p>از پنل <a href="https://login.payamak-panel.com/" target="_blank" rel="noopener" class="text-secondary hover:underline">payamak-panel.com</a> این موارد را بردارید:</p>
                    <ul class="list-disc list-inside text-gray-400 space-y-0.5 mr-1">
                        <li><strong class="text-gray-300">username</strong> — نام کاربری پنل</li>
                        <li><strong class="text-gray-300">password</strong> — توکن REST (در API به‌جای رمز)</li>
                        <li><strong class="text-gray-300">from</strong> — خط اختصاصی (برای SendOtp)</li>
                        <li><strong class="text-gray-300">bodyId</strong> — کد قالب تأیید‌شده (برای خط خدماتی 5000…)</li>
                        <li>خط <strong class="text-gray-300">5000…</strong> فقط با کد قالب کار می‌کند</li>
                    </ul>
                </div>

                <div>
                    <label for="sms_username" class="block text-gray-300 text-sm mb-1">نام کاربری پنل (username)</label>
                    <input type="text" id="sms_username" name="sms_username" value="{{ old('sms_username', \App\Models\Setting::getSmsUsername()) }}"
                        class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white outline-none focus:border-secondary"
                        placeholder="مثال: 0912xxxxxxx یا نام کاربری پنل" dir="ltr">
                </div>

                <div>
                    <label for="sms_api_key" class="block text-gray-300 text-sm mb-1">توکن REST / API Key (password)</label>
                    <input type="password" id="sms_api_key" name="sms_api_key" value="" autocomplete="new-password"
                        class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white outline-none focus:border-secondary" dir="ltr"
                        placeholder="{{ \App\Models\Setting::getSmsApiKey() ? '•••••••• (برای تغییر، توکن جدید وارد کنید)' : 'توکن دریافتی از بخش API پنل' }}">
                    @if (\App\Models\Setting::getSmsApiKey())
                        <p class="text-xs text-green-500/80 mt-1">توکن قبلاً ذخیره شده است.</p>
                    @endif
                </div>

                <div>
                    <label for="sms_sender" class="block text-gray-300 text-sm mb-1">خط فرستنده (from)</label>
                    <input type="text" id="sms_sender" name="sms_sender" value="{{ old('sms_sender', \App\Models\Setting::getSmsSender()) }}"
                        class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white outline-none focus:border-secondary" dir="ltr"
                        placeholder="1000xxxxxxx یا 5000xxxxxxxxxx">
                </div>
            </div>

            <div class="border-t border-dark-600 pt-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-gray-200">قالب‌های پیامک (پترن)</h2>
                        <p class="text-xs text-gray-500 mt-1">هر قالب یک bodyId و متغیرهای جداگانه دارد. کلید <code class="text-secondary">register</code> و <code class="text-secondary">reset</code> در سیستم استفاده می‌شوند.</p>
                    </div>
                    <button type="button" @click="addPattern()"
                        class="text-sm px-3 py-1.5 rounded-lg border border-secondary/50 text-secondary hover:bg-secondary/10">
                        + افزودن قالب
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(pattern, index) in patterns" :key="index">
                        <div class="rounded-lg border border-dark-600 bg-dark-900/50 p-4 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs text-gray-500" x-text="'قالب #' + (index + 1)"></span>
                                <button type="button" @click="removePattern(index)" x-show="patterns.length > 1"
                                    class="text-xs text-red-400 hover:underline">حذف</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-gray-300 text-xs mb-1">عنوان (نمایشی)</label>
                                    <input type="text" :name="'sms_patterns[' + index + '][title]'" x-model="pattern.title"
                                        class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm outline-none focus:border-secondary"
                                        placeholder="مثال: ثبت‌نام">
                                </div>
                                <div>
                                    <label class="block text-gray-300 text-xs mb-1">کلید (key) — انگلیسی</label>
                                    <input type="text" :name="'sms_patterns[' + index + '][key]'" x-model="pattern.key"
                                        class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm outline-none focus:border-secondary" dir="ltr"
                                        placeholder="register">
                                </div>
                                <div>
                                    <label class="block text-gray-300 text-xs mb-1">کد قالب (bodyId)</label>
                                    <input type="number" :name="'sms_patterns[' + index + '][body_id]'" x-model="pattern.body_id"
                                        class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm outline-none focus:border-secondary" dir="ltr"
                                        placeholder="509624" min="1">
                                </div>
                                <div>
                                    <label class="block text-gray-300 text-xs mb-1">متغیرها (با ; جدا)</label>
                                    <input type="text" :name="'sms_patterns[' + index + '][variables]'" x-model="pattern.variables"
                                        class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm outline-none focus:border-secondary" dir="ltr"
                                        placeholder="code">
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">متغیر <code class="text-secondary">code</code> = کد ۶ رقمی OTP. برای قالب چندمتغیره: <span dir="ltr">code;name</span></p>
                        </div>
                    </template>
                </div>
            </div>

            <button type="submit" class="w-full bg-success hover:opacity-90 text-white rounded-lg py-3 font-bold">ذخیره تنظیمات</button>
        </form>

        <div class="border-t border-dark-600 pt-4 text-sm text-gray-500 space-y-1">
            <p>🔹 <span class="text-yellow-400/90">حالت تست</span>: کد OTP روی صفحه نمایش داده می‌شود؛ مناسب تا آماده شدن پنل.</p>
            <p>🔹 <span class="text-green-400/90">ارسال واقعی</span>: پس از پر کردن username، توکن و from، پیامک از ملی‌پیامک ارسال می‌شود.</p>
        </div>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
<script>
function smsPatternManager(initialPatterns) {
    return {
        provider: @json($provider),
        patterns: (initialPatterns || []).map(p => ({
            key: p.key || '',
            title: p.title || '',
            body_id: p.body_id || '',
            variables: p.variables || 'code',
        })),
        addPattern() {
            this.patterns.push({ key: '', title: '', body_id: '', variables: 'code' });
        },
        removePattern(index) {
            this.patterns.splice(index, 1);
        },
    };
}
</script>
@endsection
