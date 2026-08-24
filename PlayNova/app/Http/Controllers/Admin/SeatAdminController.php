<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;

class SeatAdminController extends BaseAdminController
{
    public function seatAdmins()
    {
        $seatAdmins = User::where('is_seat_admin', true)->get();

        return view('admin.seat-admins', compact('seatAdmins'));
    }

    public function addSeatAdmin(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->is_seat_admin) {
            return back()->with('admin_error', 'این کاربر قبلاً ادمین جایگاه است.');
        }

        $user->is_seat_admin = true;
        $user->save();

        return back()->with('admin_success', 'دسترسی مشاهده جایگاه‌ها به کاربر داده شد.');
    }

    public function removeSeatAdmin(User $user)
    {
        $user->is_seat_admin = false;
        $user->save();

        return back()->with('admin_success', 'دسترسی ادمین جایگاه حذف شد.');
    }
}
