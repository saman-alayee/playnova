@if(isset($pendingTeamInvites) && $pendingTeamInvites->isNotEmpty())
<div id="teamInvitePending" style="position:fixed;bottom:1rem;left:1rem;right:1rem;z-index:99998;max-width:520px;margin:0 auto;">
    @foreach($pendingTeamInvites as $invite)
        @php
            $t = $invite->tournament;
            $inviter = $invite->inviter;
            $startTime = $t?->start_date ? \App\Services\JalaliService::formatTime($t->start_date) : '—';
        @endphp
        <div data-team-invite-id="{{ $invite->id }}" style="background:#1e3a5f;border:1px solid #3B82F6;border-radius:12px;padding:1rem;margin-bottom:.5rem;box-shadow:0 8px 24px rgba(0,0,0,.45);">
            <p style="margin:0 0 .35rem;font-size:.8rem;color:#93c5fd;font-weight:700;">درخواست رزرو تیمی</p>
            <p style="margin:0 0 .75rem;font-size:.875rem;color:#e5e7eb;line-height:1.6;">
                <strong>{{ $inviter?->username }}</strong> ({{ $inviter?->cod_id }}) از شما برای شرکت در
                «{{ $t?->title }}» ساعت {{ $startTime }} با هزینه {{ number_format($t?->entry_fee ?? 0) }} تومان درخواست داده است.
            </p>
            <div style="display:flex;gap:.5rem;">
                <form method="POST" action="{{ route('team-invites.accept', $invite) }}" data-team-invite-action="accept" style="flex:1;">@csrf
                    <button type="submit" style="width:100%;background:#22C55E;color:#fff;border:none;border-radius:8px;padding:.45rem 0;font-weight:700;">تأیید</button>
                </form>
                <form method="POST" action="{{ route('team-invites.decline', $invite) }}" data-team-invite-action="decline" style="flex:1;">@csrf
                    <button type="submit" style="width:100%;background:#4b5563;color:#fff;border:none;border-radius:8px;padding:.45rem 0;font-weight:700;">رد</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endif

@if(isset($sentTeamInvites) && $sentTeamInvites->isNotEmpty())
<div id="teamInviteSent" style="position:fixed;bottom:{{ isset($pendingTeamInvites) && $pendingTeamInvites->isNotEmpty() ? '8rem' : '1rem' }};left:1rem;right:1rem;z-index:99997;max-width:520px;margin:0 auto;">
    @foreach($sentTeamInvites as $invite)
        <div data-team-invite-id="{{ $invite->id }}" style="background:#422006;border:1px solid #f59e0b;border-radius:12px;padding:.85rem 1rem;margin-bottom:.5rem;">
            <p style="margin:0;font-size:.8rem;color:#fcd34d;">در انتظار تأیید «{{ $invite->invitee?->cod_id }}» برای «{{ $invite->tournament?->title }}»</p>
            <form method="POST" action="{{ route('team-invites.cancel', $invite) }}" data-team-invite-action="cancel" style="margin-top:.5rem;">@csrf
                <button type="submit" style="background:transparent;border:1px solid #f59e0b;color:#fcd34d;border-radius:6px;padding:.2rem .6rem;font-size:.75rem;">لغو درخواست</button>
            </form>
        </div>
    @endforeach
</div>
@endif
