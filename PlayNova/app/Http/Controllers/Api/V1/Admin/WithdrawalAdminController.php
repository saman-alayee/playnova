<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Resources\V1\TransactionResource;
use App\Http\Traits\InvalidatesAdminDashboard;
use App\Jobs\SendUserNotificationJob;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalAdminController extends BaseApiController
{
    use AuthorizesAdmin;
    use InvalidatesAdminDashboard;

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeAdmin();

        if ($transaction->type !== 'withdraw') {
            abort(404);
        }

        $request->validate([
            'status' => 'required|in:pending,completed,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|min:3|max:500',
        ], [
            'rejection_reason.required_if' => 'برای رد برداشت، توضیح الزامی است.',
        ]);

        if ($transaction->status !== 'pending' && $request->status !== $transaction->status) {
            return $this->error('این درخواست قبلاً بررسی شده است.');
        }

        if ($request->status === 'rejected' && $transaction->status === 'pending') {
            $reason = trim((string) $request->input('rejection_reason', ''));

            DB::transaction(function () use ($transaction, $reason) {
                $lockedUser = User::query()->whereKey($transaction->user_id)->lockForUpdate()->firstOrFail();
                $lockedUser->wallet = round($lockedUser->wallet + $transaction->amount, 2);
                $lockedUser->save();

                $description = $transaction->description . ' (رد شده — مبلغ بازگردانده شد)';
                if ($reason !== '') {
                    $description .= ' | دلیل: ' . $reason;
                }

                $transaction->forceFill([
                    'status' => 'rejected',
                    'description' => $description,
                    'updated_at' => now(),
                ])->save();
            });

            $this->invalidateAdminDashboard();

            SendUserNotificationJob::dispatch(
                (int) $transaction->user_id,
                'برداشت رد شد',
                'درخواست برداشت شما رد شد و مبلغ به کیف پول بازگردانده شد.',
                'withdrawal',
            );

            return $this->success(TransactionResource::make($transaction->fresh('user')), 'برداشت رد شد و مبلغ بازگردانده شد.');
        }

        if ($request->status === 'completed' && $transaction->status === 'pending') {
            $transaction->forceFill([
                'status' => 'completed',
                'updated_at' => now(),
            ])->save();

            $this->invalidateAdminDashboard();

            SendUserNotificationJob::dispatch(
                (int) $transaction->user_id,
                'برداشت تأیید شد',
                'درخواست برداشت شما تأیید و پردازش شد.',
                'withdrawal',
            );

            return $this->success(TransactionResource::make($transaction->fresh('user')), 'برداشت تأیید شد.');
        }

        return $this->success(TransactionResource::make($transaction->fresh('user')), 'وضعیت به‌روزرسانی شد.');
    }
}
