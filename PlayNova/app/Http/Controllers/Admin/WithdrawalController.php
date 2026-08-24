<?php

namespace App\Http\Controllers\Admin;

use App\Http\Traits\InvalidatesAdminDashboard;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends BaseAdminController
{
    use InvalidatesAdminDashboard;

    public function withdrawals(Request $request)
    {
        $view = $request->query('view', 'withdrawals');
        $status = $request->query('status', 'pending');

        if ($view === 'transactions') {
            $txQuery = Transaction::with('user')->orderByDesc('created_at');

            if ($request->filled('user_search')) {
                $search = trim((string) $request->user_search);
                $txQuery->whereHas('user', function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('cod_id', 'like', "%{$search}%");
                });
            }

            if ($request->filled('tx_type') && $request->tx_type !== 'all') {
                $txQuery->where('type', $request->tx_type);
            }

            $transactions = $txQuery->paginate(40)->withQueryString();

            return view('admin.withdrawals', compact('view', 'status', 'transactions'));
        }

        $query = Transaction::with('user')->where('type', 'withdraw');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $withdrawals = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        $userIds = $withdrawals->pluck('user_id')->filter()->unique()->values();
        $userTransactions = $userIds->isEmpty()
            ? collect()
            : Transaction::with('user')
                ->whereIn('user_id', $userIds)
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('user_id');

        return view('admin.withdrawals', compact('withdrawals', 'view', 'status', 'userTransactions'));
    }

    public function updateWithdrawal(Request $request, Transaction $transaction)
    {
        if ($transaction->type !== 'withdraw') {
            abort(404);
        }

        $request->validate([
            'status' => 'required|in:pending,completed,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|min:3|max:500',
        ], [
            'rejection_reason.required_if' => 'برای رد برداشت، توضیح الزامی است.',
            'rejection_reason.min' => 'توضیح رد باید حداقل ۳ کاراکتر باشد.',
        ]);

        if ($transaction->status !== 'pending' && $request->status !== $transaction->status) {
            return back()->with('error', 'این درخواست قبلاً بررسی شده است.');
        }

        if ($request->status === 'rejected' && $transaction->status === 'pending') {
            $reason = trim((string) $request->input('rejection_reason', ''));

            DB::transaction(function () use ($transaction, $reason) {
                $user = $transaction->user;
                $user->wallet = round($user->wallet + $transaction->amount, 2);
                $user->save();

                $description = $transaction->description . ' (رد شده — مبلغ بازگردانده شد)';
                if ($reason !== '') {
                    $description .= ' | دلیل: ' . $reason;
                }

                $now = now();
                $transaction->forceFill([
                    'status' => 'rejected',
                    'description' => $description,
                    'updated_at' => $now,
                ])->save();
            });

            $this->invalidateAdminDashboard();

            return back()->with('success', 'برداشت رد شد و مبلغ به کیف پول بازگردانده شد.');
        }

        if ($request->status === 'completed' && $transaction->status === 'pending') {
            $transaction->forceFill([
                'status' => 'completed',
                'updated_at' => now(),
            ])->save();

            $this->invalidateAdminDashboard();

            return back()->with('success', 'برداشت تأیید شد.');
        }

        return back()->with('success', 'وضعیت به‌روزرسانی شد.');
    }
}
