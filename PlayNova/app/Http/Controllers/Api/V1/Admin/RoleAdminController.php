<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleAdminController extends BaseApiController
{
    use AuthorizesAdmin;

    public function admins(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success(UserResource::collection(User::where('is_admin', true)->get())->resolve());
    }

    public function addAdmin(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        if ($user->is_admin) {
            return $this->error('این کاربر قبلاً ادمین است.');
        }

        $user->is_admin = true;
        $user->save();

        return $this->success(UserResource::make($user), 'ادمین اضافه شد.');
    }

    public function removeAdmin(User $user): JsonResponse
    {
        $this->authorizeAdmin();

        if ($user->id === auth()->id()) {
            return $this->error('نمی‌توانید دسترسی خودتان را حذف کنید.');
        }

        $user->is_admin = false;
        $user->save();

        return $this->success(null, 'دسترسی ادمین حذف شد.');
    }

    public function seatAdmins(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success(UserResource::collection(User::where('is_seat_admin', true)->get())->resolve());
    }

    public function addSeatAdmin(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        if ($user->is_seat_admin) {
            return $this->error('این کاربر قبلاً ادمین جایگاه است.');
        }

        $user->is_seat_admin = true;
        $user->save();

        return $this->success(UserResource::make($user), 'ادمین جایگاه اضافه شد.');
    }

    public function removeSeatAdmin(User $user): JsonResponse
    {
        $this->authorizeAdmin();

        $user->is_seat_admin = false;
        $user->save();

        return $this->success(null, 'دسترسی ادمین جایگاه حذف شد.');
    }
}
