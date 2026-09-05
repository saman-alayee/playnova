<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule as ValidationRule;

class UserController extends BaseAdminController
{
    public function users(Request $request)
    {
        $query = User::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('cod_id', 'like', "%{$search}%");
            });
        }
        $users = $query
            ->with(['registrations' => function ($q) {
                $q->whereNotNull('seat_number')
                    ->with('tournament:id,title,status')
                    ->orderByDesc('updated_at');
            }])
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.users', compact('users'));
    }

    public function updateUserCodId(Request $request, User $user)
    {
        $request->validate([
            'cod_id' => ['required', 'string', 'max:100', ValidationRule::unique('users', 'cod_id')->ignore($user->id)],
        ], [
            'cod_id.required' => 'آیدی کالاف الزامی است.',
            'cod_id.unique' => 'این آیدی کالاف قبلاً توسط کاربر دیگری ثبت شده است.',
        ]);

        $user->cod_id = trim($request->cod_id);
        $user->save();

        return back()->with('success', 'آیدی کالاف کاربر «' . $user->username . '» به‌روزرسانی شد.');
    }

    public function updateUserKills(Request $request, User $user)
    {
        $request->validate([
            'kills' => 'required|integer|min:0|max:999999',
        ]);

        $user->update(['kills' => $request->kills]);

        return back()->with('success', 'تعداد کیل کاربر به‌روزرسانی شد.');
    }

    public function adjustUserWallet(Request $request, User $user)
    {
        $request->validate([
            'action' => 'required|in:add,subtract',
            'amount' => 'required|numeric|min:1|max:999999999999',
            'description' => 'nullable|string|max:255',
        ]);

        $amount = (float) $request->amount;
        $note = $request->description ?: ($request->action === 'add' ? 'افزایش توسط ادمین' : 'کسر توسط ادمین');

        try {
            DB::transaction(function () use ($request, $user, $amount, $note) {
                if ($request->action === 'add') {
                    $user->creditWallet($amount, 'admin_credit', $note, 'admin_' . uniqid());
                } else {
                    $user->debitWallet($amount, 'admin_debit', $note, 'admin_' . uniqid());
                }
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'کیف پول کاربر به‌روزرسانی شد.');
    }

    public function deleteUser(User $user)
    {
        if ($user->is_admin) {
            return back()->with('error', 'حذف کاربر ادمین امکان‌پذیر نیست.');
        }
        $user->delete();
        return back()->with('success', 'کاربر حذف شد.');
    }
}
