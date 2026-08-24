<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\BroadcastNotificationJob;
use App\Models\Notification;
use Illuminate\Http\Request;

class BroadcastController extends BaseAdminController
{
    public function broadcastForm()
    {
        return view('admin.broadcast');
    }

    public function broadcast(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        BroadcastNotificationJob::dispatch($request->title, $request->message);

        return back()->with('success', 'پیام همگانی در صف ارسال قرار گرفت و به‌زودی برای تمام کاربران ثبت می‌شود.');
    }

    public function manageBroadcast()
    {
        $notifications = Notification::where('type', 'broadcast')->orderBy('created_at', 'desc')->paginate(25);
        return view('admin.broadcast-index', compact('notifications'));
    }

    public function editBroadcast($id)
    {
        $notification = Notification::findOrFail($id);
        return view('admin.broadcast-edit', compact('notification'));
    }

    public function updateBroadcast(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $notification = Notification::findOrFail($id);
        $notification->update([
            'title' => $request->title,
            'message' => $request->message,
        ]);

        return redirect()->route('admin.broadcast.manage')->with('success', 'پیام با موفقیت ویرایش شد.');
    }

    public function deleteBroadcast($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();
        return back()->with('success', 'پیام با موفقیت حذف شد.');
    }
}
