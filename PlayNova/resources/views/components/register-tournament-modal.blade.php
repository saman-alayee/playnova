@if(! $modalIsRegistered && ! ($modalPendingTeam ?? false) && $modalRegCount < $modalCapacity && $t->acceptsRegistration())
    @php $supportsTeam = $t->seatMode() >= 2; @endphp
    <div id="registerModal-{{ $t->id }}" class="register-tournament-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this) closeRegisterModal({{ $t->id }})">
        <div style="background:#1a1a2e;border:1px solid #374151;border-radius:16px;max-width:640px;width:100%;max-height:90vh;overflow-y:auto;padding:1.5rem;">
            <h2 style="font-size:1.25rem;font-weight:700;color:#8B5CF6;margin-bottom:.5rem;">{{ $t->title }}</h2>

            <div id="registerStepRules-{{ $t->id }}">
                <h3 style="font-size:1rem;font-weight:700;color:#8B5CF6;margin-bottom:.75rem;">📜 تأیید خواندن قوانین</h3>
                <div style="background:#0f0f1a;border-radius:8px;padding:1rem;max-height:240px;overflow-y:auto;border:1px solid #374151;color:#d1d5db;font-size:.875rem;line-height:1.8;white-space:pre-line;">
                    @php $rules = \App\Models\Rule::all(); @endphp
                    @forelse($rules as $index => $rule)
                        <div style="margin-bottom:.75rem;"><strong style="color:#3B82F6;">بخش {{ $index + 1 }}:</strong> <span>{{ $rule->content ?? '' }}</span></div>
                    @empty
                        <p style="color:#6b7280;">هیچ قانونی ثبت نشده است.</p>
                    @endforelse
                </div>
                <div style="margin-top:1rem;display:flex;align-items:flex-start;gap:.5rem;">
                    <input type="checkbox" id="acceptRules-{{ $t->id }}" style="margin-top:.25rem;width:1rem;height:1rem;accent-color:#8B5CF6;">
                    <label for="acceptRules-{{ $t->id }}" style="color:#d1d5db;font-size:.875rem;">قوانین و مقررات را مطالعه کرده و با تمامی موارد آن موافقم.</label>
                </div>
                <div style="margin-top:1rem;display:flex;gap:.75rem;">
                    <button type="button" id="nextRegisterStepBtn-{{ $t->id }}" disabled style="flex:1;background:#22C55E;color:#fff;border:none;border-radius:8px;padding:.5rem 0;font-weight:700;opacity:.5;cursor:not-allowed;">ادامه</button>
                    <button type="button" onclick="closeRegisterModal({{ $t->id }})" style="background:#4b5563;color:#fff;border:none;border-radius:8px;padding:.5rem 1.5rem;font-weight:700;cursor:pointer;">انصراف</button>
                </div>
            </div>

            <div id="registerStepType-{{ $t->id }}" style="display:none;">
                <h3 style="font-size:1rem;font-weight:700;color:#8B5CF6;margin-bottom:.75rem;">نوع ثبت‌نام</h3>
                <p style="color:#9ca3af;font-size:.875rem;margin-bottom:1rem;">نحوه رزرو جایگاه خود را انتخاب کنید.</p>
                <div style="display:flex;flex-direction:column;gap:.75rem;">
                    <form method="POST" action="{{ route('tournaments.register', $t->id) }}">@csrf
                        <input type="hidden" name="accept_rules" value="1">
                        <button type="submit" style="width:100%;background:#22C55E;color:#fff;border:none;border-radius:8px;padding:.75rem 0;font-weight:700;">🎯 رزرو تکی — انتخاب جایگاه</button>
                    </form>
                    @if($supportsTeam)
                        <button type="button" onclick="showTeamInviteStep({{ $t->id }})" style="width:100%;background:#3B82F6;color:#fff;border:none;border-radius:8px;padding:.75rem 0;font-weight:700;">👥 رزرو تیمی — دعوت هم‌تیمی</button>
                    @endif
                    <button type="button" onclick="backToRegisterRules({{ $t->id }})" style="width:100%;background:#4b5563;color:#fff;border:none;border-radius:8px;padding:.5rem 0;font-weight:700;">بازگشت</button>
                </div>
            </div>

            @if($supportsTeam)
            <div id="registerStepTeam-{{ $t->id }}" style="display:none;">
                <h3 style="font-size:1rem;font-weight:700;color:#8B5CF6;margin-bottom:.75rem;">رزرو تیمی</h3>
                <p style="color:#9ca3af;font-size:.875rem;margin-bottom:1rem;">آیدی کالاف هم‌تیمی خود را وارد کنید. درخواست برای او ارسال می‌شود و در صورت تأیید، هر دو در یک تیم (مثلاً 6.1 و 6.2) قرار می‌گیرید.</p>
                <form method="POST" action="{{ route('tournaments.team-invite', $t->id) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="accept_rules" value="1">
                    <input type="text" name="teammate_cod_id" required placeholder="آیدی کالاف هم‌تیمی"
                        style="width:100%;background:#0f0f1a;border:1px solid #374151;border-radius:8px;padding:.65rem .75rem;color:#fff;outline:none;">
                    <p style="font-size:.75rem;color:#fbbf24;">برای ارسال درخواست، موجودی شما باید حداقل {{ number_format($t->entry_fee) }} تومان باشد.</p>
                    <div style="display:flex;gap:.75rem;">
                        <button type="submit" style="flex:1;background:#3B82F6;color:#fff;border:none;border-radius:8px;padding:.65rem 0;font-weight:700;">ارسال درخواست</button>
                        <button type="button" onclick="backToRegisterType({{ $t->id }})" style="background:#4b5563;color:#fff;border:none;border-radius:8px;padding:.65rem 1rem;font-weight:700;">بازگشت</button>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
@endif
