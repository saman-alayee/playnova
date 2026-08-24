@extends('layouts.app')
@section('title', 'اعلانات | PlayNova')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
            <span>🔔</span>
            <span>اعلانات</span>
        </h1>
        @auth
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                    @csrf
                    <button type="submit" class="text-xs bg-primary/20 text-primary px-3 py-2 rounded-lg font-bold hover:bg-primary/30 transition">
                        علامت‌گذاری همه به‌عنوان خوانده‌شده
                    </button>
                </form>
            @endif
        @endauth
    </div>

    @auth
        <section class="mb-8">
            <h2 class="text-lg font-bold text-white mb-4">پیام‌های شما</h2>
            @if($notifications->isEmpty())
                <div class="text-center py-8 bg-dark-800/50 rounded-2xl border border-dark-600">
                    <p class="text-gray-500 text-sm">اعلان شخصی جدیدی ندارید.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($notifications as $notification)
                        <article class="rounded-2xl border p-4 {{ $notification->is_read ? 'bg-dark-800/40 border-dark-600' : 'bg-primary/10 border-primary/35' }}">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div class="min-w-0">
                                    <h3 class="font-bold text-white text-sm">{{ $notification->title }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ $notification->created_at?->format('Y/m/d H:i') }}</p>
                                </div>
                                @unless($notification->is_read)
                                    <span class="shrink-0 text-[10px] bg-primary text-white px-2 py-1 rounded-full font-bold">جدید</span>
                                @endunless
                            </div>
                            <p class="text-sm text-gray-300 leading-7 whitespace-pre-line">{{ $notification->message }}</p>
                            <div class="flex flex-wrap gap-2 mt-3">
                                @unless($notification->is_read)
                                    <form method="POST" action="{{ route('notifications.mark-read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="text-xs bg-success/20 text-success px-3 py-1.5 rounded-lg font-bold">خواندم</button>
                                    </form>
                                @endunless
                                <form method="POST" action="{{ route('notifications.delete', $notification->id) }}" onsubmit="return confirm('این اعلان حذف شود؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs bg-danger/20 text-danger px-3 py-1.5 rounded-lg font-bold">حذف</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
                @if($notifications instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div class="mt-6">
                        {{ $notifications->links() }}
                    </div>
                @endif
            @endif
        </section>
    @else
        <div class="mb-6 p-4 bg-dark-800 border border-dark-600 rounded-xl text-sm text-gray-300">
            برای مشاهده پیام‌های شخصی، <a href="{{ route('login') }}" class="text-secondary font-bold">وارد شوید</a>.
        </div>
    @endauth

    <section>
        <h2 class="text-lg font-bold text-white mb-4">اخبار و اطلاعیه‌ها</h2>
        @if($news->isEmpty())
            <div class="text-center py-8 bg-dark-800/50 rounded-2xl border border-dark-600">
                <p class="text-gray-500 text-sm">فعلاً اطلاعیه‌ای منتشر نشده است.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($news as $item)
                    <article class="bg-dark-800 border border-dark-600 rounded-2xl p-4">
                        <h3 class="font-bold text-white mb-2">{{ $item->title }}</h3>
                        <p class="text-xs text-gray-500 mb-3">{{ $item->created_at?->format('Y/m/d H:i') }}</p>
                        @if($item->image)
                            <img src="{{ asset('storage/' . $item->image) }}" alt="{{ $item->title }}" class="w-full max-h-56 object-cover rounded-xl mb-3">
                        @endif
                        <p class="text-sm text-gray-300 leading-7 whitespace-pre-line">{{ $item->content }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
