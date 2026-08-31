<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Jobs\BroadcastNotificationJob;
use App\Jobs\SendUserNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationAdminController extends BaseApiController
{
    use AuthorizesAdmin;

    public function sendBroadcast(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $groupId = (string) Str::uuid();
        BroadcastNotificationJob::dispatch($validated['title'], $validated['message'], $groupId);

        return $this->success(['broadcast_group_id' => $groupId], 'اعلان کلی در صف ارسال قرار گرفت.');
    }

    public function sendPersonal(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'user_id' => 'nullable|integer|exists:users,id',
            'search' => 'nullable|string|max:120',
        ]);

        $user = $this->resolveUser($validated);

        SendUserNotificationJob::dispatch(
            $user->id,
            $validated['title'],
            $validated['message'],
            'admin',
        );

        return $this->success([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'mobile' => $user->mobile,
            ],
        ], 'اعلان شخصی در صف ارسال قرار گرفت.');
    }

    public function broadcasts(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $baseQuery = Notification::query()->where('type', 'broadcast');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $baseQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $rows = $baseQuery
            ->select([
                DB::raw('MIN(id) as id'),
                DB::raw('COALESCE(broadcast_group_id, CAST(MIN(id) AS CHAR)) as group_id'),
                'title',
                'message',
                DB::raw('MIN(created_at) as created_at'),
                DB::raw('COUNT(*) as recipient_count'),
            ])
            ->groupBy(DB::raw('COALESCE(broadcast_group_id, CAST(id AS CHAR))'), 'title', 'message')
            ->orderByDesc(DB::raw('MIN(created_at)'))
            ->paginate(25);

        $items = collect($rows->items())->map(fn ($row) => [
            'id' => (int) $row->id,
            'group_id' => (string) $row->group_id,
            'title' => $row->title,
            'message' => $row->message,
            'recipient_count' => (int) $row->recipient_count,
            'created_at' => optional($row->created_at)->toIso8601String(),
            'created_at_display' => optional($row->created_at)->format('Y/m/d H:i'),
            'type' => 'broadcast',
        ]);

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'links' => [
                'first' => $rows->url(1),
                'last' => $rows->url($rows->lastPage()),
                'prev' => $rows->previousPageUrl(),
                'next' => $rows->nextPageUrl(),
            ],
        ]);
    }

    public function personalNotifications(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = Notification::query()
            ->with('user:id,username,mobile,email')
            ->where('type', 'admin');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('username', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $notifications = $query->orderByDesc('created_at')->paginate(25);

        return $this->paginated($notifications->through(fn (Notification $notification) => $this->formatPersonal($notification)));
    }

    public function updateBroadcast(Request $request, string $groupId): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $updated = $this->broadcastQuery($groupId)->update([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'updated_at' => now(),
        ]);

        if ($updated === 0) {
            abort(404, 'اعلان یافت نشد.');
        }

        return $this->success(null, 'اعلان کلی ویرایش شد.');
    }

    public function deleteBroadcast(string $groupId): JsonResponse
    {
        $this->authorizeAdmin();

        $deleted = $this->broadcastQuery($groupId)->delete();

        if ($deleted === 0) {
            abort(404, 'اعلان یافت نشد.');
        }

        return $this->success(['deleted_count' => $deleted], "{$deleted} اعلان حذف شد.");
    }

    public function bulkDeleteBroadcasts(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'group_ids' => 'required|array|min:1',
            'group_ids.*' => 'required|string|max:64',
        ]);

        $totalDeleted = 0;

        foreach ($validated['group_ids'] as $groupId) {
            $totalDeleted += $this->broadcastQuery($groupId)->delete();
        }

        return $this->success(['deleted_count' => $totalDeleted], "{$totalDeleted} اعلان حذف شد.");
    }

    public function updatePersonal(Request $request, Notification $notification): JsonResponse
    {
        $this->authorizeAdmin();

        if ($notification->type !== 'admin') {
            abort(404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $notification->update($validated);

        return $this->success($this->formatPersonal($notification->fresh('user')), 'اعلان شخصی ویرایش شد.');
    }

    public function deletePersonal(Notification $notification): JsonResponse
    {
        $this->authorizeAdmin();

        if ($notification->type !== 'admin') {
            abort(404);
        }

        $notification->delete();

        return $this->success(null, 'اعلان شخصی حذف شد.');
    }

    public function bulkDeletePersonal(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $deleted = Notification::query()
            ->where('type', 'admin')
            ->whereIn('id', $validated['ids'])
            ->delete();

        return $this->success(['deleted_count' => $deleted], "{$deleted} اعلان حذف شد.");
    }

    protected function broadcastQuery(string $groupId)
    {
        if (Str::isUuid($groupId)) {
            return Notification::query()
                ->where('type', 'broadcast')
                ->where('broadcast_group_id', $groupId);
        }

        return Notification::query()
            ->where('type', 'broadcast')
            ->where('id', (int) $groupId);
    }

    protected function resolveUser(array $validated): User
    {
        if (! empty($validated['user_id'])) {
            return User::findOrFail($validated['user_id']);
        }

        $search = trim((string) ($validated['search'] ?? ''));

        if ($search === '') {
            abort(422, 'کاربر را انتخاب کنید یا شناسه/موبایل/نام کاربری را وارد کنید.');
        }

        $user = User::query()
            ->where('id', $search)
            ->orWhere('mobile', $search)
            ->orWhere('username', $search)
            ->orWhere('email', $search)
            ->first();

        if (! $user) {
            abort(422, 'کاربری با این مشخصات یافت نشد.');
        }

        return $user;
    }

    protected function formatPersonal(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'is_read' => (bool) $notification->is_read,
            'user' => $notification->user ? [
                'id' => $notification->user->id,
                'username' => $notification->user->username,
                'mobile' => $notification->user->mobile,
                'email' => $notification->user->email,
            ] : null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_display' => $notification->created_at?->format('Y/m/d H:i'),
        ];
    }
}
