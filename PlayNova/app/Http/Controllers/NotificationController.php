<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $news = News::where('is_published', true)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $notifications = collect();
        $unreadCount = 0;

        if (Auth::check()) {
            $notifications = Notification::where('user_id', Auth::id())
                ->orderByDesc('created_at')
                ->paginate(20);

            $unreadCount = Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->count();
        }

        return view('notifications', compact('notifications', 'news', 'unreadCount'));
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->update(['is_read' => true]);

        return back()->with('success', 'اعلان به عنوان خوانده‌شده علامت‌گذاری شد.');
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())->where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'همه اعلان‌ها به عنوان خوانده‌شده علامت‌گذاری شدند.');
    }

    public function delete($id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->delete();

        return back()->with('success', 'اعلان حذف شد.');
    }
}
