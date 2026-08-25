<?php

namespace App\Http\Controllers\Admin;

use App\Http\Traits\InvalidatesAdminDashboard;
use App\Models\Tournament;
use App\Modules\Tournament\Services\TournamentListingService;
use App\Services\AvalAIService;
use App\Services\TournamentResultVisionService;
use Illuminate\Http\Request;
use RuntimeException;

class TournamentResultAiController extends BaseAdminController
{
    use InvalidatesAdminDashboard;

    public function showForm(Tournament $tournament)
    {
        return view('admin.tournament-result-ai', compact('tournament'));
    }

    public function analyze(
        Request $request,
        Tournament $tournament,
        TournamentResultVisionService $vision,
        AvalAIService $avalai,
    ) {
        if (! $avalai->isConfigured()) {
            return back()->with('error', 'سرویس هوش مصنوعی فعال نیست یا کلید API در پنل مدیریت (تنظیمات هوش مصنوعی) تنظیم نشده است.');
        }

        $request->validate([
            'screenshot' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
            'video' => 'nullable|file|mimes:mp4,webm,mov,quicktime|max:51200',
            'media' => 'nullable|file|mimes:jpeg,jpg,png,webp,mp4,webm,mov,quicktime|max:51200',
            'system_prompt' => 'nullable|string|max:10000',
            'user_prompt' => 'nullable|string|max:10000',
            'save_prompt' => 'nullable|boolean',
        ]);

        $file = $request->file('screenshot') ?? $request->file('video') ?? $request->file('media');
        if (! $file) {
            return back()->with('error', 'تصویر یا ویدیو الزامی است.');
        }

        $systemPrompt = filled($request->input('system_prompt')) ? trim((string) $request->input('system_prompt')) : null;
        $userPrompt = filled($request->input('user_prompt')) ? trim((string) $request->input('user_prompt')) : null;

        if ($request->boolean('save_prompt')) {
            $vision->savePrompts($tournament, $systemPrompt, $userPrompt);
        }

        try {
            $analysis = $vision->analyzeMedia($tournament, $file, $systemPrompt, $userPrompt);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return view('admin.tournament-result-ai', [
            'tournament' => $tournament,
            'analysis' => $analysis,
        ]);
    }

    public function apply(Request $request, Tournament $tournament, TournamentResultVisionService $vision)
    {
        $validated = $request->validate([
            'winner_user_id' => 'required|integer|exists:users,id',
            'player_stats' => 'nullable|array',
            'player_stats.*.user_id' => 'required|integer|exists:users,id',
            'player_stats.*.kills' => 'nullable|integer|min:0|max:999999',
        ]);

        try {
            $winner = $vision->applyResult(
                $tournament,
                (int) $validated['winner_user_id'],
                $validated['player_stats'] ?? []
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        TournamentListingService::forgetHomeCache();
        TournamentListingService::forgetLeaderboardCache();
        $this->invalidateAdminDashboard();

        return redirect()
            ->route('admin.tournaments')
            ->with('success', 'نتیجه مسابقه ثبت شد. برنده: ' . $winner->username);
    }
}
