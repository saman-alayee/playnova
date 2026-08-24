@extends('layouts.app')

@section('title', 'مدیریت ادمین‌ها | پنل مدیریت')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-primary">👑 مدیریت ادمین‌ها</h2>
            <button onclick="document.getElementById('addAdminForm').classList.toggle('hidden')" class="btn-glow-success text-sm">
                ➕ افزودن ادمین جدید
            </button>
        </div>

        <!-- فرم افزودن ادمین -->
        <div id="addAdminForm" class="hidden bg-dark-900/50 p-4 rounded-lg border border-gray-700 mb-6">
            <h3 class="text-lg font-bold text-secondary mb-3">افزودن کاربر به عنوان ادمین</h3>
            <form method="POST" action="{{ route('admin.admins.store') }}" class="flex flex-wrap gap-3">
                @csrf
                <input type="email" name="email" placeholder="ایمیل کاربر" required 
                    class="flex-1 min-w-[200px] bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary">
                <button type="submit" class="btn-glow-success px-6 py-2 text-sm">افزودن</button>
            </form>
            @if(session('admin_error'))
                <p class="text-danger text-sm mt-2">{{ session('admin_error') }}</p>
            @endif
            @if(session('admin_success'))
                <p class="text-success text-sm mt-2">{{ session('admin_success') }}</p>
            @endif
        </div>

        <!-- لیست ادمین‌ها -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-dark-900/50 border-b border-gray-700">
                    <tr>
                        <th class="text-right py-3 px-4 text-gray-400">نام</th>
                        <th class="text-right py-3 px-4 text-gray-400">ایمیل</th>
                        <th class="text-center py-3 px-4 text-gray-400">نقش</th>
                        <th class="text-center py-3 px-4 text-gray-400">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($admins as $admin)
                        <tr class="border-b border-gray-700/50 hover:bg-gray-800/30 transition">
                            <td class="py-3 px-4">{{ $admin->name ?? $admin->username ?? '-' }}</td>
                            <td class="py-3 px-4">{{ $admin->email }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-1 rounded-full text-xs bg-primary/20 text-primary">ادمین</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($admin->id !== Auth::id())
                                    <form method="POST" action="{{ route('admin.admins.remove', $admin) }}" class="inline" onsubmit="return confirm('آیا از حذف دسترسی ادمین این کاربر مطمئن هستید؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-danger hover:text-red-400 text-sm">🚫 حذف دسترسی</button>
                                    </form>
                                @else
                                    <span class="text-gray-500 text-xs">(شما)</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($admins->isEmpty())
            <div class="text-center py-8 text-gray-500 text-sm">هیچ ادمینی یافت نشد.</div>
        @endif
    </div>
</div>
@endsection