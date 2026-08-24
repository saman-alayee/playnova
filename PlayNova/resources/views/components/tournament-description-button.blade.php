@if(filled($t->description))
    <button type="button"
        onclick="openDescriptionModal({{ json_encode($t->title, JSON_UNESCAPED_UNICODE) }}, {{ json_encode($t->description, JSON_UNESCAPED_UNICODE) }})"
        class="{{ $class ?? 'flex-1 text-xs py-2 px-3 rounded-xl border border-secondary/40 text-secondary bg-secondary/10 hover:bg-secondary/20 transition font-bold' }}">
        توضیحات
    </button>
@endif
