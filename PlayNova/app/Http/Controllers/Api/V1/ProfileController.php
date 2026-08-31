<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\RegistrationResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends BaseApiController
{
    public function __construct(protected ActivityLogService $activity)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['registrations' => function ($query) {
            $query->whereNotNull('seat_number')
                ->whereHas('tournament', fn ($q) => $q->whereNotIn('status', ['ended', 'cancelled']))
                ->with('tournament')
                ->orderByDesc('updated_at');
        }]);

        return $this->success([
            'user' => new UserResource($user),
            'active_seats' => RegistrationResource::collection($user->registrations),
            'referral_bonus_percent' => Setting::getReferralBonusPercent(),
            'stats' => [
                'wins' => (int) $user->wins,
                'kills' => (int) $user->kills,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'cod_id' => [
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if (User::codIdIsTaken(is_string($value) ? $value : null, $user->id)) {
                        $fail('این آیدی کالاف قبلاً توسط کاربر دیگری ثبت شده است.');
                    }
                },
            ],
            'bank_card_number' => ['nullable', 'string', 'max:24', 'regex:/^[0-9\-]*$/'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $newCodId = User::normalizeCodIdForStorage($validated['cod_id'] ?? null);
        $currentCodId = User::normalizeCodIdForStorage($user->cod_id);
        $codIdChanged = false;
        $profileFieldsChanged = [];

        if ($newCodId !== $currentCodId) {
            $codIdChanged = true;
            if ($user->cod_id_changed && $currentCodId !== '') {
                return $this->error('فقط یک‌بار امکان تغییر آیدی کالاف وجود دارد. برای تغییرات بیشتر تیکت ثبت کنید.', 422, [
                    'cod_id' => ['فقط یک‌بار امکان تغییر آیدی کالاف وجود دارد.'],
                ]);
            }

            if ($newCodId === '' && $currentCodId !== '') {
                return $this->error('امکان حذف آیدی کالاف وجود ندارد.', 422, [
                    'cod_id' => ['امکان حذف آیدی کالاف وجود ندارد.'],
                ]);
            }

            if ($currentCodId !== '' || $newCodId !== '') {
                $user->cod_id_changed = true;
            }

            $user->cod_id = $newCodId !== '' ? $newCodId : null;
        }

        if ($user->username !== $validated['username']) {
            $profileFieldsChanged['username'] = ['from' => $user->username, 'to' => $validated['username']];
        }

        if (($user->email ?? '') !== ($validated['email'] ?? '')) {
            $profileFieldsChanged['email'] = ['from' => $user->email, 'to' => $validated['email'] ?? null];
        }

        if (($user->mobile ?? '') !== ($validated['mobile'] ?? '')) {
            $profileFieldsChanged['mobile'] = ['from' => $user->mobile, 'to' => $validated['mobile'] ?? null];
        }

        $user->username = $validated['username'];
        $user->email = $validated['email'] ?? null;
        $user->mobile = $validated['mobile'] ?? null;
        $user->bank_card_number = preg_replace('/\D+/', '', (string) ($validated['bank_card_number'] ?? '')) ?: null;
        $user->bank_account_name = trim((string) ($validated['bank_account_name'] ?? '')) ?: null;

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $profileFieldsChanged['password'] = true;
        }

        $user->save();

        if ($codIdChanged) {
            $this->activity->logProfile($user, 'cod_id_changed', 'تغییر آیدی کالاف', [
                'from' => $currentCodId ?: null,
                'to' => $newCodId ?: null,
            ]);
        }

        if ($profileFieldsChanged !== []) {
            $this->activity->logProfile($user, 'profile_updated', 'به‌روزرسانی اطلاعات پروفایل', $profileFieldsChanged);
        }

        return $this->success([
            'user' => new UserResource($user->fresh(['registrations.tournament'])),
        ], 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.');
    }
}
