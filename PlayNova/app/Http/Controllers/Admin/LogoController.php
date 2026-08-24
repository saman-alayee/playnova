<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Services\FaviconService;
use Illuminate\Http\Request;

class LogoController extends BaseAdminController
{
    public function logoForm()
    {
        return view('admin.logo');
    }

    public function updateLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        $oldLogo = Setting::get('logo');
        if ($oldLogo && file_exists(storage_path('app/public/' . $oldLogo))) {
            unlink(storage_path('app/public/' . $oldLogo));
        }

        $path = $request->file('logo')->store('logo', 'public');
        Setting::set('logo', $path);

        $sourcePath = storage_path('app/public/' . $path);
        foreach ([
            dirname(base_path()) . '/playnova-logo.png',
            public_path('logo.png'),
        ] as $publicTarget) {
            @copy($sourcePath, $publicTarget);
        }
        FaviconService::regenerateFromFile($sourcePath);

        return back()->with('success', 'لوگو با موفقیت تغییر یافت.');
    }

    public function deleteLogo()
    {
        $logo = Setting::get('logo');
        if ($logo && file_exists(storage_path('app/public/' . $logo))) {
            unlink(storage_path('app/public/' . $logo));
        }
        Setting::set('logo', null);

        return back()->with('success', 'لوگو به حالت پیش‌فرض بازگشت.');
    }
}
