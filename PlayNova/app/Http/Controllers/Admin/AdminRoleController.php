<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;

class AdminRoleController extends BaseAdminController
{
    public function admins()
    {
        $admins = User::where('is_admin', true)->get();
        return view('admin.admins', compact('admins'));
    }

    public function addAdmin(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->is_admin) {
            return back()->with('admin_error', 'این کاربر قبلاً ادمین است.');
        }

        $user->is_admin = true;
        $user->save();

        return back()->with('admin_success', 'کاربر با موفقیت به عنوان ادمین اضافه شد.');
    }

    public function removeAdmin(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('admin_error', 'نمی‌توانید دسترسی خودتان را حذف کنید.');
        }

        $user->is_admin = false;
        $user->save();

        return back()->with('admin_success', 'دسترسی ادمین از کاربر حذف شد.');
    }
}
