<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use Illuminate\Http\Request;

class SiteSettingsController extends BaseAdminController
{
    public function siteSettingsForm()
    {
        return view('admin.site-settings', [
            'privacyContent' => Setting::get('privacy_content', ''),
            'aboutContent' => Setting::get('about_content', ''),
            'contactEmail' => Setting::get('contact_email', ''),
            'contactPhone' => Setting::get('contact_phone', ''),
            'contactAddress' => Setting::get('contact_address', ''),
            'socialTelegram' => Setting::get('social_telegram', ''),
            'socialRubika' => Setting::get('social_rubika', ''),
            'socialInstagram' => Setting::get('social_instagram', ''),
            'resultsTelegram' => Setting::get('results_telegram', ''),
            'resultsRubika' => Setting::get('results_rubika', ''),
        ]);
    }

    public function updateSiteSettings(Request $request)
    {
        $request->validate([
            'privacy_content' => 'nullable|string',
            'about_content' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_address' => 'nullable|string|max:500',
            'social_telegram' => 'nullable|string|max:255',
            'social_rubika' => 'nullable|string|max:255',
            'social_instagram' => 'nullable|string|max:255',
            'results_telegram' => 'nullable|string|max:255',
            'results_rubika' => 'nullable|string|max:255',
        ]);

        foreach ([
            'privacy_content', 'about_content', 'contact_email', 'contact_phone', 'contact_address',
            'social_telegram', 'social_rubika', 'social_instagram',
            'results_telegram', 'results_rubika',
        ] as $key) {
            Setting::set($key, $request->input($key, ''));
        }

        return back()->with('success', 'تنظیمات سایت ذخیره شد.');
    }
}
