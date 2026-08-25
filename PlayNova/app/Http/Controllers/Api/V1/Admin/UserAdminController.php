<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule as ValidationRule;

class UserAdminController extends BaseApiController
{
    use AuthorizesAdmin;

    public function updateCodId(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'cod_id' => ['required', 'string', 'max:100', ValidationRule::unique('users', 'cod_id')->ignore($user->id)],
        ], [
            'cod_id.required' => 'آیدی کالاف الزامی است.',
            'cod_id.unique' => 'این آیدی کالاف قبلاً ثبت شده است.',
        ]);

        $user->cod_id = trim($request->cod_id);
        $user->save();

        app(\App\Modules\Audit\Services\ActivityLogService::class)->logProfile(
            $user,
            'cod_id_changed',
            'تغییر آیدی کالاف توسط ادمین',
            ['cod_id' => $user->cod_id],
            $request->user()
        );

        return $this->success(UserResource::make($user), 'آیدی کالاف به‌روزرسانی شد.');
    }

    public function updateKills(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'kills' => 'required|integer|min:0|max:999999',
        ]);

        $user->update(['kills' => $request->kills]);

        return $this->success(UserResource::make($user->fresh()), 'تعداد کیل به‌روزرسانی شد.');
    }

    public function adjustWallet(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'action' => 'required|in:add,subtract,set',
            'amount' => 'required|numeric|min:0|max:999999999999',
            'description' => 'nullable|string|max:255',
            'allow_negative' => 'nullable|boolean',
        ]);

        $amount = (float) $request->amount;
        $note = $request->description ?: match ($request->action) {
            'add' => 'افزایش توسط ادمین',
            'subtract' => 'کسر توسط ادمین',
            default => 'تنظیم موجودی توسط ادمین',
        };
        $admin = $request->user();
        $activity = app(\App\Modules\Audit\Services\ActivityLogService::class);

        try {
            DB::transaction(function () use ($request, $user, $amount, $note, $admin, $activity) {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $before = (float) $locked->wallet;

                if ($request->action === 'set') {
                    $delta = $amount - $before;
                    if ($delta > 0) {
                        $locked->creditWallet($delta, 'admin_credit', $note . ' (' . $admin->username . ')', 'admin_set_' . uniqid());
                    } elseif ($delta < 0) {
                        $locked->debitWallet(abs($delta), 'admin_debit', $note . ' (' . $admin->username . ')', 'admin_set_' . uniqid(), (bool) $request->boolean('allow_negative', true));
                    }
                } elseif ($request->action === 'add') {
                    $locked->creditWallet($amount, 'admin_credit', $note . ' (' . $admin->username . ')', 'admin_' . uniqid());
                } else {
                    $locked->debitWallet($amount, 'admin_debit', $note . ' (' . $admin->username . ')', 'admin_' . uniqid(), (bool) $request->boolean('allow_negative'));
                }

                $activity->logWallet($locked, 'admin_adjustment', $note, [
                    'action' => $request->action,
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => (float) $locked->fresh()->wallet,
                ], $admin);
            });
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(UserResource::make($user->fresh()), 'کیف پول به‌روزرسانی شد.');
    }

    public function activityHistory(User $user): JsonResponse
    {
        $this->authorizeAdmin();

        $logs = \App\Models\ActivityLog::query()
            ->where('user_id', $user->id)
            ->with('actor:id,username')
            ->orderByDesc('created_at')
            ->paginate(50);

        $logs->getCollection()->transform(function ($log) {
            $log->created_at_display = \App\Support\IranDate::formatString($log->created_at);

            return $log;
        });

        return $this->paginated($logs);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorizeAdmin();

        if ($user->is_admin) {
            return $this->error('حذف کاربر ادمین امکان‌پذیر نیست.');
        }

        $user->delete();

        return $this->success(null, 'کاربر حذف شد.');
    }
}
