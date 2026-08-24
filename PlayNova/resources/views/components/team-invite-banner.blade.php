@php
    $pendingTeamInvites = $pendingTeamInvites ?? collect();
    $sentTeamInvites = $sentTeamInvites ?? collect();
    $teamInviteSignature = $pendingTeamInvites->pluck('id')->join(',') . '|' . $sentTeamInvites->pluck('id')->join(',');
@endphp
<div id="teamInviteBannersRoot" data-signature="{{ $teamInviteSignature }}">
    @include('components.team-invite-banner-content', compact('pendingTeamInvites', 'sentTeamInvites'))
</div>
<script>
(function () {
    var root = document.getElementById('teamInviteBannersRoot');
    if (!root) return;

    var bannerUrl = @json(route('team-invites.banner'));
    var lastSignature = root.dataset.signature || '';
    var pollTimer = null;

    function showTeamInviteToast(message, type) {
        if (!message) return;
        var colors = {
            success: { bg: '#14532d', border: '#22c55e', text: '#bbf7d0' },
            error: { bg: '#450a0a', border: '#ef4444', text: '#fecaca' },
            info: { bg: '#1e3a5f', border: '#3b82f6', text: '#bfdbfe' }
        };
        var c = colors[type] || colors.info;
        var el = document.createElement('div');
        el.textContent = message;
        el.style.cssText = 'position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:100000;max-width:90vw;padding:.75rem 1rem;border-radius:10px;font-size:.875rem;box-shadow:0 8px 24px rgba(0,0,0,.35);background:' + c.bg + ';border:1px solid ' + c.border + ';color:' + c.text + ';';
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 4500);
    }

    function bindTeamInviteForms() {
        root.querySelectorAll('form[data-team-invite-action]').forEach(function (form) {
            if (form.dataset.bound === '1') return;
            form.dataset.bound = '1';

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.dataset.originalText = btn.textContent;
                    btn.textContent = '...';
                }

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('[name=_token]').value
                    },
                    body: new FormData(form)
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        if (!res.ok) throw data;
                        return data;
                    });
                })
                .then(function (data) {
                    showTeamInviteToast(data.message, data.type || (data.ok ? 'success' : 'error'));
                    return refreshTeamInviteBanners(true);
                })
                .catch(function (err) {
                    showTeamInviteToast((err && err.message) ? err.message : 'عملیات ناموفق بود.', 'error');
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = btn.dataset.originalText || btn.textContent;
                    }
                });
            });
        });
    }

    function refreshTeamInviteBanners(force) {
        return fetch(bannerUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (res) {
            if (!res.ok) return null;
            return res.json();
        })
        .then(function (data) {
            if (!data) return;
            if (force || data.signature !== lastSignature) {
                root.innerHTML = data.html;
                root.dataset.signature = data.signature;
                lastSignature = data.signature;
                bindTeamInviteForms();
            }
        })
        .catch(function () {});
    }

    bindTeamInviteForms();
    pollTimer = setInterval(function () { refreshTeamInviteBanners(false); }, 12000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refreshTeamInviteBanners(true);
    });
    window.addEventListener('beforeunload', function () {
        if (pollTimer) clearInterval(pollTimer);
    });
})();
</script>
