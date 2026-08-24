@php $captcha = $captcha ?? \App\Services\CaptchaService::refresh(); @endphp
<div>
    <label class="block text-sm mb-1 text-gray-400">کد امنیتی: {{ $captcha['question'] }}</label>
    <input type="number" name="captcha" required inputmode="numeric"
        class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"
        placeholder="پاسخ را وارد کنید">
    @error('captcha')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
</div>
