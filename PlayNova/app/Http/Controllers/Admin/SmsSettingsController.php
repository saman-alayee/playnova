<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use Illuminate\Http\Request;

class SmsSettingsController extends BaseAdminController
{
    public function smsSettingsForm()
    {
        return view('admin.sms-settings');
    }

    public function updateSmsSettings(Request $request)
    {
        $request->validate([
            'sms_provider' => 'required|in:test,melipayamak',
            'sms_username' => 'nullable|string|max:100',
            'sms_api_key' => 'nullable|string|max:255',
            'sms_sender' => 'nullable|string|max:20',
            'sms_patterns' => 'nullable|array',
            'sms_patterns.*.key' => 'required_with:sms_patterns|string|max:50|regex:/^[a-z0-9_\-]+$/i',
            'sms_patterns.*.title' => 'nullable|string|max:100',
            'sms_patterns.*.body_id' => 'nullable|integer|min:1',
            'sms_patterns.*.variables' => 'nullable|string|max:200',
            'sms_register_verify' => 'nullable|boolean',
        ], [
            'sms_patterns.*.key.regex' => 'کلید قالب فقط حروف انگلیسی، عدد، خط تیره و زیرخط مجاز است.',
        ]);

        $provider = $request->input('sms_provider', 'test');

        if ($provider === 'melipayamak') {
            $request->validate([
                'sms_username' => 'required|string|max:100',
                'sms_sender' => 'required|string|max:20',
            ], [
                'sms_username.required' => 'برای فعال‌سازی ارسال واقعی، نام کاربری پنل ملی‌پیامک را وارد کنید.',
                'sms_sender.required' => 'برای فعال‌سازی ارسال واقعی، خط فرستنده (from) را وارد کنید.',
            ]);

            if (! $request->filled('sms_api_key') && ! Setting::getSmsApiKey()) {
                return back()
                    ->withErrors(['sms_api_key' => 'برای فعال‌سازی ارسال واقعی، توکن REST پنل را وارد کنید.'])
                    ->withInput();
            }
        }

        Setting::set('sms_provider', $provider);
        Setting::set('sms_active', $provider === 'melipayamak');
        Setting::set('sms_register_test_mode', $provider === 'test');

        if ($request->filled('sms_username')) {
            Setting::set('sms_username', $request->sms_username);
        }
        if ($request->filled('sms_api_key')) {
            Setting::set('sms_api_key', $request->sms_api_key);
        }
        if ($request->filled('sms_sender')) {
            Setting::set('sms_sender', $request->sms_sender);
        }

        $patterns = collect($request->input('sms_patterns', []))
            ->filter(fn ($p) => is_array($p) && filled($p['key'] ?? null))
            ->values()
            ->all();

        if ($patterns !== []) {
            Setting::setSmsPatterns($patterns);
        }

        Setting::set('sms_register_verify', $request->has('sms_register_verify'));

        $message = $provider === 'melipayamak'
            ? 'ارسال واقعی ملی‌پیامک فعال شد. تنظیمات پنل ذخیره شد.'
            : 'حالت تست فعال است. تنظیمات پنل (در صورت وارد کردن) ذخیره شد.';

        return back()->with('success', $message);
    }
}
