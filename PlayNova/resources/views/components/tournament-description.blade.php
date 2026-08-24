@if(filled($t->description))
    <div class="tournament-description {{ $wrapperClass ?? 'mb-3 rounded-xl border border-dark-600 bg-dark-900/50 p-3' }}">
        <p class="text-xs text-gray-500 mb-1">توضیحات مسابقه</p>
        <p class="text-xs text-gray-300 leading-relaxed whitespace-pre-line {{ $textClass ?? 'line-clamp-4' }}">{{ $t->description }}</p>
    </div>
@endif
