<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        $activeSeats = $user->registrations()
            ->whereNotNull('seat_number')
            ->whereHas('tournament', function ($q) {
                $q->whereNotIn('status', ['ended', 'cancelled']);
            })
            ->with('tournament')
            ->orderByDesc('updated_at')
            ->get();

        return view('profile', compact('user', 'activeSeats'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'cod_id' => ['nullable', 'string', 'max:100', Rule::unique('users', 'cod_id')->ignore($user->id)],
            'bank_card_number' => ['nullable', 'string', 'max:24', 'regex:/^[0-9\-]*$/'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $newCodId = trim((string) ($validated['cod_id'] ?? ''));
        $currentCodId = trim((string) ($user->cod_id ?? ''));

        if ($newCodId !== $currentCodId) {
            if ($user->cod_id_changed && $currentCodId !== '') {
                return back()->withErrors([
                    'cod_id' => 'فقط یک‌بار امکان تغییر آیدی کالاف وجود دارد. برای تغییرات بیشتر تیکت ثبت کنید.',
                ])->withInput();
            }

            if ($newCodId === '' && $currentCodId !== '') {
                return back()->withErrors([
                    'cod_id' => 'امکان حذف آیدی کالاف وجود ندارد.',
                ])->withInput();
            }

            if ($currentCodId !== '' || $newCodId !== '') {
                $user->cod_id_changed = true;
            }

            $user->cod_id = $newCodId !== '' ? $newCodId : null;
        }

        $user->username = $validated['username'];
        $user->email = $validated['email'] ?? null;
        $user->mobile = $validated['mobile'] ?? null;
        $user->bank_card_number = preg_replace('/\D+/', '', (string) ($validated['bank_card_number'] ?? '')) ?: null;
        $user->bank_account_name = trim((string) ($validated['bank_account_name'] ?? '')) ?: null;

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('success', 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.');
    }
}
