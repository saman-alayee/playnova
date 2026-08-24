<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['registrations' => function ($query) {
            $query->whereNotNull('seat_number')
                ->whereHas('tournament', fn ($q) => $q->whereNotIn('status', ['ended', 'cancelled']))
                ->with('tournament')
                ->orderByDesc('updated_at');
        }]);

        return $this->success(new UserResource($user));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

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

        $user->username = $validated['username'];
        $user->email = $validated['email'] ?? null;
        $user->mobile = $validated['mobile'] ?? null;
        $user->bank_card_number = preg_replace('/\D+/', '', (string) ($validated['bank_card_number'] ?? '')) ?: null;
        $user->bank_account_name = trim((string) ($validated['bank_account_name'] ?? '')) ?: null;

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return $this->success(new UserResource($user), 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.');
    }
}
