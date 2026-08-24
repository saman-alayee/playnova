<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\NewsResource;
use App\Http\Resources\V1\NotificationResource;
use App\Models\News;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $news = News::where('is_published', true)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        $paginated = $this->paginated($notifications, NotificationResource::class);
        $payload = $paginated->getData(true);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $payload['data'],
                'news' => NewsResource::collection($news),
                'unread_count' => $unreadCount,
            ],
            'meta' => $payload['meta'] ?? null,
            'links' => $payload['links'] ?? null,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->update(['is_read' => true]);

        return $this->success(new NotificationResource($notification), 'اعلان به عنوان خوانده‌شده علامت‌گذاری شد.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->success(null, 'همه اعلان‌ها به عنوان خوانده‌شده علامت‌گذاری شدند.');
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->delete();

        return $this->success(null, 'اعلان حذف شد.');
    }
}
