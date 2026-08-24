@extends('layouts.app')

@section('title', 'تنظیمات درگاه پرداخت | پنل مدیریت')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
        <h2 class="text-2xl font-bold text-center mb-6 text-primary">تنظیمات درگاه پرداخت زیبال</h2>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-700 bg-green-900/30 px-4 py-3 text-green-200 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">{{ session('error') }}</div>
        @endif

        <div class="mb-4 rounded-lg border border-amber-700/50 bg-amber-900/20 px-4 py-3 text-sm text-amber-100 space-y-2">
            <p><strong>برای جلوگیری از خطای «درگاه‌ها پاسخگو نیستند»:</strong></p>
            <ul class="list-disc list-inside text-amber-200/90 space-y-1">
                <li>در پنل زیبال، درگاه باید <strong>تأیید و فعال</strong> باشد.</li>
                <li>دامنه <span class="font-mono" dir="ltr">playnova.ir</span> باید ثبت شده باشد.</li>
                <li>IP سرور (پایین) را در پنل زیبال → آی‌پی‌های مجاز ثبت کنید.</li>
                <li>تا قبل از فعال شدن درگاه واقعی، <strong>Sandbox</strong> را روشن بگذارید.</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('admin.payment-gateway.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="flex items-center justify-between bg-dark-900/50 p-4 rounded-lg border border-gray-700">
                <div>
                    <label class="text-gray-300 font-bold">فعال‌سازی درگاه واقعی</label>
                    <p class="text-sm text-gray-500">در حالت غیرفعال، شارژ کیف پول شبیه‌سازی می‌شود.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ \App\Models\Setting::isPaymentGatewayActive() ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-600 rounded-full peer peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
            </div>

            <div class="flex items-center justify-between bg-dark-900/50 p-4 rounded-lg border border-gray-700">
                <div>
                    <label class="text-gray-300 font-bold">محیط تست (Sandbox)</label>
                    <p class="text-sm text-gray-500">در حالت تست از مرچنت <span class="font-mono" dir="ltr">zibal</span> استفاده می‌شود.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="sandbox" value="1" class="sr-only peer" {{ \App\Models\Setting::isZibalSandbox() ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-600 rounded-full peer peer-checked:bg-amber-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
            </div>

            <div class="border-t border-gray-700 pt-4 space-y-4">
                <p class="text-sm text-gray-400">مرچنت کد را از پنل زیبال → <strong class="text-gray-300">درگاه پرداخت</strong> کپی کنید.</p>

                <div>
                    <label class="block text-gray-300 text-sm mb-1">مرچنت کد (Merchant ID)</label>
                    <input type="text" name="merchant_id" value="{{ old('merchant_id', \App\Models\Setting::getZibalMerchantCode()) }}"
                        placeholder="6a6c95ca6eca8591ace1b9cc"
                        class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary font-mono" dir="ltr">
                    <p class="text-xs text-gray-500 mt-1">در Sandbox لازم نیست — خودکار <span class="font-mono">zibal</span> استفاده می‌شود.</p>
                </div>

                <div>
                    <label class="block text-gray-300 text-sm mb-1">کلید API (اختیاری — برای IPG لازم نیست)</label>
                    <input type="text" name="api_key" value="{{ old('api_key', \App\Models\Setting::getZibalApiKey()) }}"
                        placeholder="توکن REST زیبال"
                        class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary font-mono" dir="ltr">
                </div>

                <div>
                    <label class="block text-gray-300 text-sm mb-1">آی‌پی سرور (برای ثبت در پنل زیبال)</label>
                    <input type="text" name="server_ip" value="{{ old('server_ip', \App\Models\Setting::getZibalServerIp() ?: ($detectedServerIp ?? '')) }}"
                        placeholder="185.x.x.x"
                        class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary font-mono" dir="ltr">
                    @if(!empty($detectedServerIp))
                        <p class="text-xs text-green-400/90 mt-1">IP تشخیص‌داده‌شده از سرور: <span class="font-mono" dir="ltr">{{ $detectedServerIp }}</span></p>
                    @else
                        <p class="text-xs text-gray-500 mt-1">این IP را در پنل زیبال → آی‌پی‌های مجاز ثبت کنید.</p>
                    @endif
                </div>
            </div>

            <button type="submit" class="w-full bg-success hover:opacity-90 py-3 rounded-lg text-white font-bold">ذخیره تنظیمات</button>
        </form>

        <form method="POST" action="{{ route('admin.payment-gateway.test') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full bg-secondary hover:opacity-90 py-3 rounded-lg text-white font-bold">تست اتصال به زیبال</button>
        </form>

        <div class="mt-4 text-sm text-gray-500 space-y-1">
            <p>آدرس بازگشت (Callback): <span class="font-mono text-gray-300" dir="ltr">{{ $callbackUrl ?? route('wallet.callback') }}</span></p>
            <p>مبلغ‌ها به <strong>ریال</strong> ارسال می‌شوند (تومان × ۱۰).</p>
            <p>مستندات: <a class="text-primary hover:underline" href="https://help.zibal.ir/ipg" target="_blank" rel="noopener">help.zibal.ir/ipg</a></p>
        </div>
    </div>
</div>
@endsection
