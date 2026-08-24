@extends('layouts.app')
@section('title', 'حریم خصوصی | PlayNova')

@section('content')
<div class="max-w-3xl mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-4">حریم خصوصی</h1>
    <div class="prose prose-invert text-sm text-gray-300 leading-8 whitespace-pre-line">{{ $content }}</div>
</div>
@endsection
