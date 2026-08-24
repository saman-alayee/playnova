<button type="button"
    onclick="openGameLoginModalById('{{ route('tournaments.game-login', $t) }}')"
    class="{{ $class ?? 'w-full btn-glow-primary text-xs py-2 px-3 rounded-xl' }}">
    اطلاعات ورود
</button>
