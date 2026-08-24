@extends('layouts.app')
@section('title', 'ویرایش پیام همگانی | PlayNova')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-primary">✏️ ویرایش پیام همگانی</h1>
    @include('admin._nav')

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <form method="POST" action="{{ route('admin.broadcast.update', $notification->id) }}">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <label class="block text-gray-300 text-sm mb-1">عنوان پیام</label>
                <input type="text" name="title" value="{{ $notification->title }}" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            </div>
            <div class="mb-4">
                <label class="block text-gray-300 text-sm mb-1">متن پیام</label>
                <textarea name="message" rows="6" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">{{ $notification->message }}</textarea>
            </div>
            <button class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold">💾 ذخیره تغییرات</button>
        </form>
    </div>
</div>
@endsection