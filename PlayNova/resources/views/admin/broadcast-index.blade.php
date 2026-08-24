@extends('layouts.app')
@section('title', 'مدیریت پیام‌های همگانی | PlayNova')

@section('content')
<div class="max-w-6xl mx-auto">
    <h2 class="text-2xl font-bold mb-6 text-primary">📢 مدیریت پیام‌های همگانی</h2>
    @include('admin._nav')

    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-dark-900/50 border-b border-gray-700">
                <tr>
                    <th class="text-right py-3 px-4 text-gray-400">عنوان</th>
                    <th class="text-right py-3 px-4 text-gray-400">متن</th>
                    <th class="text-center py-3 px-4 text-gray-400">تاریخ ارسال</th>
                    <th class="text-center py-3 px-4 text-gray-400">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notifications as $notif)
                <tr class="border-b border-gray-700/50 hover:bg-gray-800/30 transition">
                    <td class="py-3 px-4 font-bold text-white">{{ $notif->title }}</td>
                    <td class="py-3 px-4 text-gray-300">{{ Str::limit($notif->message, 50) }}</td>
                    <td class="py-3 px-4 text-center text-gray-400">{{ $notif->created_at->format('Y-m-d H:i') }}</td>
                    <td class="py-3 px-4 text-center">
                        <!-- دکمه ویرایش -->
                        <a href="{{ route('admin.broadcast.edit', $notif->id) }}" class="text-secondary hover:text-secondary/80 text-sm ml-2">✏️ ویرایش</a>
                        <!-- دکمه حذف -->
                        <form method="POST" action="{{ route('admin.broadcast.delete', $notif->id) }}" class="inline" onsubmit="return confirm('آیا از حذف این پیام مطمئن هستید؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-danger hover:text-red-400 text-sm">🗑️ حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="py-8 text-center text-gray-500">هیچ پیام همگانی ارسال نشده است.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
@endsection