<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use Illuminate\Http\Request;

class ReferralSettingsController extends BaseAdminController
{
    public function referralSettingsForm()
    {
        $bonusPercent = Setting::get('referral_bonus_percent', 5);
        return view('admin.referral-settings', compact('bonusPercent'));
    }

    public function updateReferralSettings(Request $request)
    {
        $request->validate([
            'bonus_percent' => 'required|numeric|min:0|max:100',
        ]);

        Setting::set('referral_bonus_percent', $request->bonus_percent);

        return back()->with('success', 'تنظیمات دعوت با موفقیت ذخیره شد.');
    }
}
