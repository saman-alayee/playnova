<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Traits\InvalidatesAdminDashboard;
use App\Models\Tournament;
use App\Modules\Tournament\Services\TournamentListingService;
use App\Services\AvalAIService;
use App\Services\TournamentResultVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TournamentResultController extends BaseApiController
{
    use AuthorizesAdmin;
    use InvalidatesAdminDashboard;

    public function config(Tournament $tournament, TournamentResultVisionService $vision): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success($vision->promptConfig($tournament));
    }

    public function analyze(
        Request $request,
        Tournament $tournament,
        TournamentResultVisionService $vision,
        AvalAIService $avalai,
    ): JsonResponse {
        $this->authorizeAdmin();

        if (! $avalai->isConfigured()) {
            return $this->error('سرویس هوش مصنوعی فعال نیست یا کلید API در پنل مدیریت (تنظیمات هوش مصنوعی) تنظیم نشده است.', 422);
        }

        $request->validate([
            'screenshot' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:10240',
            'video' => 'nullable|file|mimes:mp4,webm,mov,quicktime|max:51200',
            'media' => 'nullable|file|mimes:jpeg,jpg,png,webp,mp4,webm,mov,quicktime|max:51200',
            'frames' => 'nullable|array|max:8',
            'frames.*' => 'file|mimes:jpeg,jpg,png,webp|max:10240',
            'system_prompt' => 'nullable|string|max:10000',
            'user_prompt' => 'nullable|string|max:10000',
            'save_prompt' => 'nullable|boolean',
        ], [
            'screenshot.max' => 'حداکثر حجم تصویر ۱۰ مگابایت است.',
            'video.max' => 'حداکثر حجم ویدیو ۵۰ مگابایت است.',
            'media.max' => 'حداکثر حجم فایل ۵۰ مگابایت است.',
        ]);

        $file = $request->file('screenshot') ?? $request->file('video') ?? $request->file('media');

        if (! $file) {
            return $this->error('تصویر یا ویدیو نتیجه مسابقه الزامی است.', 422);
        }

        $systemPrompt = filled($request->input('system_prompt')) ? trim((string) $request->input('system_prompt')) : null;
        $userPrompt = filled($request->input('user_prompt')) ? trim((string) $request->input('user_prompt')) : null;

        if ($request->boolean('save_prompt')) {
            $vision->savePrompts($tournament, $systemPrompt, $userPrompt);
        }

        try {
            $extraFrames = array_values(array_filter($request->file('frames') ?? []));
            $result = $vision->analyzeMedia($tournament, $file, $systemPrompt, $userPrompt, $extraFrames);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'tournament_id' => $tournament->id,
            'tournament_title' => $tournament->title,
            ...$result,
        ]);
    }

    public function apply(Request $request, Tournament $tournament, TournamentResultVisionService $vision): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'winner_user_id' => 'required|integer|exists:users,id',
            'player_stats' => 'nullable|array',
            'player_stats.*.user_id' => 'required|integer|exists:users,id',
            'player_stats.*.kills' => 'nullable|integer|min:0|max:999999',
            'player_stats.*.rank' => 'nullable|integer|min:1|max:9999',
        ]);

        try {
            $winner = $vision->applyResult(
                $tournament,
                (int) $validated['winner_user_id'],
                $validated['player_stats'] ?? []
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        TournamentListingService::forgetHomeCache();
        TournamentListingService::forgetLeaderboardCache();
        $this->invalidateAdminDashboard();

        return $this->success([
            'winner_id' => $winner->id,
            'winner_username' => $winner->username,
            'tournament_status' => $tournament->fresh()->status,
            'prize_pending_approval' => true,
        ], 'نتیجه ثبت شد. جوایز در انتظار تأیید ادمین است.');
    }
}
