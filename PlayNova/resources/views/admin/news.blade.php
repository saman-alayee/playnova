@extends('layouts.app')
@section('title', 'مدیریت اخبار | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">مدیریت اخبار</h1>
@include('admin._nav')

<div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
    <h2 class="font-bold mb-4">افزودن خبر جدید</h2>
    <form method="POST" action="{{ route('admin.news.store') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf
        <input type="text" name="title" placeholder="عنوان خبر" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
        <textarea name="content" rows="4" placeholder="متن خبر" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"></textarea>
        <input type="file" name="image" accept="image/*" class="w-full text-sm text-gray-400">
        <button class="bg-success hover:bg-green-700 text-white rounded px-4 py-2 font-bold transition">انتشار خبر</button>  {{-- اصلاح: bg-secondary -> bg-success --}}
    </form>
</div>

<div class="space-y-3">
    @foreach($newsItems as $n)
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-4 flex justify-between items-start gap-3">
            <div>
                <h3 class="font-bold">{{ $n->title }}</h3>
                <p class="text-xs text-gray-400 mt-1">{{ Str::limit($n->content, 120) }}</p>
            </div>
            <form method="POST" action="{{ route('admin.news.delete', $n) }}">
                @csrf
                @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-300 whitespace-nowrap">حذف</button>
            </form>
        </div>
    @endforeach
</div>
<div class="mt-4">{{ $newsItems->links() }}</div>
@endsection