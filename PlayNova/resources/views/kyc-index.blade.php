@extends('layouts.app')
@section('title', 'احراز هویت | PlayNova')

@section('content')
<div class="max-w-3xl mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-2">احراز هویت (KYC)</h1>
    <p class="text-xs text-gray-400 mb-4">تصویر با AES-256 رمزنگاری و در مسیر امن ذخیره می‌شود.</p>

    @if(auth()->user()->isKycVerified())
        <div class="mb-4 p-3 rounded-lg border border-green-700 bg-green-900/20 text-green-300 text-sm">
            احراز هویت شما تأیید شده است. سقف واریز کیف پول برداشته شده است.
        </div>
    @else
        <div class="mb-4 p-3 rounded-lg border border-amber-700/60 bg-amber-900/20 text-amber-200 text-sm">
            تا قبل از تأیید احراز هویت، حداکثر موجودی کیف پول ۱,۰۰۰,۰۰۰ تومان است.
        </div>
    @endif

    @if($submission)
        <div class="mb-4 p-3 rounded-lg border border-dark-600 bg-dark-900/50 text-sm">
            <p>وضعیت: <strong>{{ ['pending'=>'در انتظار','approved'=>'تأیید شده','rejected'=>'رد شده'][$submission->status] ?? $submission->status }}</strong></p>
            @if($submission->admin_note)<p class="text-gray-400 mt-1">{{ $submission->admin_note }}</p>@endif
        </div>
    @endif

    @if(! $submission || $submission->status === 'rejected')
        <div class="mb-5 rounded-xl overflow-hidden border border-secondary/30 bg-dark-900/40">
            <img src="{{ asset('kyc-guide.png') }}" alt="راهنمای احراز هویت PlayNova" class="w-full h-auto">
        </div>

        <div class="mb-4 text-sm text-gray-300 leading-relaxed bg-dark-900/50 border border-dark-600 rounded-lg p-4 space-y-3">
            <p class="font-bold text-secondary">نکات مهم</p>
            <ul class="list-disc list-inside text-gray-400 space-y-1 mr-1">
                <li>یک تصویر واحد ارسال کنید (کارت ملی + کارت بانکی + تعهدنامه در یک عکس)</li>
                <li>از برش دادن تصویر خودداری کنید</li>
                <li>اطلاعات کارت‌ها باید خوانا باشد</li>
                <li>تصویر باید واضح و با کیفیت باشد</li>
                <li>روی کارت بانکی، <span class="text-amber-300/90">CVV2 و تاریخ انقضا را با تکه کاغذ بپوشانید</span></li>
            </ul>
        </div>

        <div class="mb-4 rounded-lg border border-secondary/30 bg-dark-900/60 p-4">
            <h2 class="text-sm font-bold text-secondary mb-3">تعهدنامه احراز هویت</h2>
            <div class="text-sm text-gray-300 leading-8 space-y-4">
                <p>اینجانب .................. با کد ملی ....................</p>
                <p>با انجام احراز هویت، صحت اطلاعات و مدارک ارائه‌شده را تأیید می‌کنم و متعهد می‌شوم از خدمات سایت مطابق قوانین و مقررات استفاده نمایم.</p>
                <p>اینجانب اقرار می‌کنم تمامی واریزهای مالی به حساب سایت با رضایت کامل، آگاهی و از حساب بانکی متعلق به خودم انجام می‌شود و مسئولیت هرگونه تخلف یا مغایرت در این خصوص بر عهده اینجانب است.</p>
                <div class="pt-2 space-y-2 text-gray-400">
                    <p>تاریخ: ..................................</p>
                    <p>امضا: ..................................</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-4">این متن را روی کاغذ بنویسید یا چاپ کنید، نام و کد ملی خود را درج کنید، امضا و تاریخ بزنید و در کنار کارت ملی و کارت بانکی در یک عکس قرار دهید.</p>
        </div>

        <form method="POST" action="{{ route('kyc.store') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm text-gray-300 font-bold mb-1">تصویر مدارک (یک فایل)</label>
                <p class="text-xs text-gray-400 mb-2 leading-relaxed">
                    کارت ملی + کارت بانکی (با پوشاندن CVV2 و تاریخ انقضا) + تعهدنامه — همه در یک عکس.
                    @if(extension_loaded('gd'))
                        تصاویر بزرگ‌تر تا ۱۰ مگابایت پذیرفته می‌شوند و سایت قبل از ذخیره آن‌ها را فشرده می‌کند.
                    @else
                        حداکثر حجم مجاز: ۲ مگابایت.
                    @endif
                </p>
                <input type="file" name="document" accept="image/jpeg,image/png,image/webp" required class="w-full text-sm bg-dark-700 border border-dark-600 rounded px-3 py-2">
                @error('document')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button class="w-full bg-success text-white rounded py-2 font-bold">ارسال مدارک</button>
        </form>
    @endif
</div>
@endsection
