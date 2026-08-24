@extends('layouts.app')
@section('title', 'سوالات متداول | PlayNova')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="text-center mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-primary mb-2">سوالات متداول</h1>
        <p class="text-sm text-gray-400">یکی از دسته‌ها را انتخاب کنید تا پاسخ سوالات را ببینید.</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        @foreach($categories as $key => $cat)
            <a href="{{ route('tickets.index', ['cat' => $key]) }}#faq-answers"
                class="faq-category-link block rounded-xl border p-4 text-center transition {{ $activeKey === $key ? 'border-secondary bg-secondary/10 shadow-glowprimary' : 'border-dark-600 bg-dark-800 hover:border-secondary/50' }}">
                <div class="text-3xl mb-2">{{ $cat['icon'] }}</div>
                <div class="font-bold text-sm leading-relaxed">{{ $cat['title'] }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ count($cat['items']) }} سوال</div>
            </a>
        @endforeach
    </div>

    @if($activeCategory)
        <div id="faq-answers" class="bg-dark-800 border border-dark-600 rounded-xl p-5 md:p-6 mb-8 scroll-mt-24">
            <div class="flex items-center gap-3 mb-5 pb-4 border-b border-dark-600">
                <span class="text-2xl">{{ $activeCategory['icon'] }}</span>
                <h2 class="text-xl font-bold">{{ $activeCategory['title'] }}</h2>
            </div>

            <div class="space-y-3">
                @foreach($activeCategory['items'] as $index => $item)
                    <details class="group rounded-lg border border-dark-600 bg-dark-900/50 open:border-secondary/40 open:bg-dark-900" {{ $index === 0 ? 'open' : '' }}>
                        <summary class="cursor-pointer list-none px-4 py-3 font-semibold text-sm text-gray-100 flex items-start justify-between gap-3">
                            <span>{{ $item['q'] }}</span>
                            <span class="text-secondary text-lg leading-none shrink-0 group-open:rotate-45 transition-transform">+</span>
                        </summary>
                        <div class="px-4 pb-4 text-sm text-gray-300 leading-7 whitespace-pre-line border-t border-dark-700/80 pt-3">
                            {{ $item['a'] }}
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-dark-800 border border-dashed border-dark-600 rounded-xl p-8 text-center text-gray-500 text-sm mb-8">
            برای مشاهده پاسخ‌ها، یکی از دسته‌های بالا را انتخاب کنید.
        </div>
    @endif

    <div class="bg-gradient-to-l from-amber-900/20 to-dark-800 border border-amber-700/40 rounded-xl p-5 md:p-6 text-center">
        <h3 class="font-bold text-amber-200 mb-2">پاسخ سوال خود را پیدا نکردید؟</h3>
        <p class="text-sm text-gray-300 mb-3">با شماره ثابت پشتیبانی تماس بگیرید تا راهنمایی‌تان کنیم.</p>
        @if($supportPhone)
            <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" dir="ltr"
                class="inline-flex items-center justify-center gap-2 bg-amber-600 hover:bg-amber-500 text-white font-bold px-6 py-3 rounded-xl text-lg">
                📞 {{ $supportPhone }}
            </a>
        @else
            <p class="text-sm text-gray-400">شماره تماس به‌زودی در این بخش قرار می‌گیرد. فعلاً از <a href="{{ route('contact') }}" class="text-secondary underline">ارتباط با ما</a> استفاده کنید.</p>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function scrollToFaqAnswers() {
        var target = document.getElementById('faq-answers');
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (window.location.hash === '#faq-answers' || new URLSearchParams(window.location.search).has('cat')) {
        setTimeout(scrollToFaqAnswers, 150);
    }

    document.querySelectorAll('.faq-category-link').forEach(function (link) {
        link.addEventListener('click', function () {
            var url = new URL(this.href, window.location.origin);
            if (url.searchParams.get('cat') === new URLSearchParams(window.location.search).get('cat')) {
                setTimeout(scrollToFaqAnswers, 50);
            }
        });
    });
});
</script>
@endpush
@endsection
