<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Setting;
use App\Models\Tournament;
use App\Models\User;
use App\Modules\Tournament\Services\TournamentPrizeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TournamentResultVisionService
{
    public function __construct(
        protected AvalAIService $avalai,
        protected TournamentPrizeService $prizes,
    ) {
    }

    /** @return array{system_prompt:string,user_prompt:string,seat_mode_label:string,has_saved_prompt:bool} */
    public function promptConfig(Tournament $tournament): array
    {
        $participants = $this->participants($tournament);

        return [
            'system_prompt' => $this->resolveSystemPrompt($tournament),
            'user_prompt' => $this->resolveUserPrompt($tournament, $participants),
            'seat_mode_label' => $tournament->seatModeLabel(),
            'has_saved_prompt' => filled($tournament->result_ai_system_prompt) || filled($tournament->result_ai_user_prompt),
        ];
    }

    /**
     * @return array{
     *   players: list<array{rank:int,name:?string,uid:?string,kills:?int,score:?int}>,
     *   matched: list<array<string,mixed>>,
     *   unmatched: list<array<string,mixed>>,
     *   suggested_winner_user_id: ?int,
     *   participants: list<array<string,mixed>>
     * }
     */
    public function analyzeMedia(
        Tournament $tournament,
        UploadedFile $file,
        ?string $systemPrompt = null,
        ?string $userPrompt = null,
    ): array {
        [$mime, $binary] = $this->resolveMediaBinary($file);
        $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binary);

        $participants = $this->participants($tournament);
        $system = $systemPrompt ?: $this->resolveSystemPrompt($tournament);
        $userText = $userPrompt ?: $this->resolveUserPrompt($tournament, $participants);

        $mediaPart = str_starts_with($mime, 'video/')
            ? ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'high']]
            : ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'high']];

        $raw = $this->avalai->chatWithVision([
            ['type' => 'text', 'text' => $userText],
            $mediaPart,
        ], $system);

        $players = $this->parsePlayersJson($raw);
        $matchResult = $this->matchPlayers($players, $participants);

        return [
            'players' => $players,
            'matched' => $matchResult['matched'],
            'unmatched' => $matchResult['unmatched'],
            'suggested_winner_user_id' => $matchResult['suggested_winner_user_id'],
            'participants' => $participants,
            'raw_excerpt' => mb_substr($raw, 0, 500),
        ];
    }

    public function analyzeScreenshot(Tournament $tournament, UploadedFile $image, ?string $systemPrompt = null, ?string $userPrompt = null): array
    {
        return $this->analyzeMedia($tournament, $image, $systemPrompt, $userPrompt);
    }

    public function savePrompts(Tournament $tournament, ?string $systemPrompt, ?string $userPrompt): void
    {
        $tournament->update([
            'result_ai_system_prompt' => filled($systemPrompt) ? trim($systemPrompt) : null,
            'result_ai_user_prompt' => filled($userPrompt) ? trim($userPrompt) : null,
        ]);
    }

    public function applyResult(Tournament $tournament, int $winnerUserId, array $playerStats = []): User
    {
        $isRegistered = Registration::where('tournament_id', $tournament->id)
            ->where('user_id', $winnerUserId)
            ->whereNotNull('seat_number')
            ->exists();

        if (! $isRegistered) {
            throw new RuntimeException('برنده انتخاب‌شده در این مسابقه ثبت‌نام نکرده است.');
        }

        $winner = User::findOrFail($winnerUserId);

        DB::transaction(function () use ($tournament, $winner, $winnerUserId, $playerStats) {
            foreach ($playerStats as $stat) {
                if (! isset($stat['user_id'], $stat['kills'])) {
                    continue;
                }

                $kills = max(0, (int) $stat['kills']);
                User::where('id', (int) $stat['user_id'])->update(['kills' => $kills]);
            }

            if ($tournament->status !== 'ended' || $tournament->winner_id !== $winner->id) {
                $tournament->update([
                    'status' => 'ended',
                    'winner_id' => $winner->id,
                ]);

                $winner->increment('wins');

                $loserIds = Registration::where('tournament_id', $tournament->id)
                    ->whereNotNull('seat_number')
                    ->where('user_id', '!=', $winner->id)
                    ->pluck('user_id');

                if ($loserIds->isNotEmpty()) {
                    User::whereIn('id', $loserIds)->increment('losses');
                }
            }

            $rankedEntries = $this->rankedEntriesFromStats($tournament, $playerStats);
            $this->prizes->submitPendingBatch($tournament, $winnerUserId, $rankedEntries);
        });

        return $winner->fresh();
    }

    /** @return list<array{user_id:int,rank:?int,kills:?int,team_label:?string,seat_number:?int}> */
    protected function rankedEntriesFromStats(Tournament $tournament, array $playerStats): array
    {
        if ($playerStats === []) {
            return [];
        }

        $registrations = Registration::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->get()
            ->keyBy('user_id');

        return collect($playerStats)
            ->filter(fn ($stat) => isset($stat['user_id']))
            ->map(function ($stat) use ($tournament, $registrations) {
                $userId = (int) $stat['user_id'];
                $reg = $registrations->get($userId);
                $seatNumber = $reg?->seat_number;

                return [
                    'user_id' => $userId,
                    'rank' => isset($stat['rank']) ? (int) $stat['rank'] : null,
                    'kills' => isset($stat['kills']) ? (int) $stat['kills'] : null,
                    'seat_number' => $seatNumber ? (int) $seatNumber : null,
                    'team_label' => $seatNumber ? $tournament->seatDisplayLabel((int) $seatNumber) : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveSystemPrompt(Tournament $tournament): string
    {
        if (filled($tournament->result_ai_system_prompt)) {
            return trim((string) $tournament->result_ai_system_prompt);
        }

        $base = Setting::getResultAiSystemPromptDefault();
        $modeHint = $this->seatModePromptHint($tournament);

        return $base . "\n\n" . $modeHint;
    }

    /** @param  list<array{user_id:int,username:string,cod_id:?string,seat_number:?int}>  $participants */
    protected function resolveUserPrompt(Tournament $tournament, array $participants): string
    {
        $template = filled($tournament->result_ai_user_prompt)
            ? trim((string) $tournament->result_ai_user_prompt)
            : Setting::getResultAiUserPromptTemplate();

        $participantHint = collect($participants)
            ->map(fn (array $p) => "{$p['username']} | UID: {$p['cod_id']}")
            ->implode("\n");

        return str_replace(
            ['{tournament_title}', '{seat_mode_label}', '{participants}'],
            [$tournament->title, $tournament->seatModeLabel(), $participantHint],
            $template,
        );
    }

    protected function seatModePromptHint(Tournament $tournament): string
    {
        return match ($tournament->seatMode()) {
            2 => 'Tournament type: DUO (2-player teams). Rank teams or players as shown on the scoreboard.',
            4 => 'Tournament type: SQUAD (4-player teams). Rank teams or players as shown on the scoreboard.',
            default => 'Tournament type: SOLO. Rank individual players as shown on the scoreboard.',
        };
    }

    /** @return array{0:string,1:string} */
    protected function resolveMediaBinary(UploadedFile $file): array
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));

        if (str_starts_with($mime, 'image/')) {
            $binary = file_get_contents($file->getRealPath());
            if ($binary === false) {
                throw new RuntimeException('خواندن تصویر ممکن نشد.');
            }

            return [$mime ?: 'image/jpeg', $binary];
        }

        if (str_starts_with($mime, 'video/')) {
            $frame = $this->extractVideoFrame($file);
            if ($frame !== null) {
                return ['image/jpeg', $frame];
            }

            throw new RuntimeException(
                'استخراج فریم از ویدیو ممکن نشد. از پیش‌نمایش ویدیو یک فریم انتخاب کنید یا ffmpeg را روی سرور نصب کنید.',
            );
        }

        throw new RuntimeException('فرمت فایل پشتیبانی نمی‌شود. تصویر (JPG/PNG/WebP) یا ویدیو (MP4/WebM) آپلود کنید.');
    }

    protected function extractVideoFrame(UploadedFile $file): ?string
    {
        $inputPath = $file->getRealPath();
        if (! is_string($inputPath) || ! is_readable($inputPath)) {
            return null;
        }

        $ffmpeg = $this->findFfmpegBinary();
        if ($ffmpeg === null) {
            return null;
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'pn_frame_') . '.jpg';
        $seconds = [1, 3, 5, 8];

        foreach ($seconds as $second) {
            $command = sprintf(
                '%s -hide_banner -loglevel error -ss %d -i %s -vframes 1 -q:v 2 %s 2>&1',
                escapeshellarg($ffmpeg),
                $second,
                escapeshellarg($inputPath),
                escapeshellarg($outputPath),
            );

            exec($command, $output, $exitCode);

            if ($exitCode === 0 && is_readable($outputPath) && filesize($outputPath) > 0) {
                $binary = file_get_contents($outputPath);
                @unlink($outputPath);

                return $binary !== false ? $binary : null;
            }
        }

        @unlink($outputPath);

        return null;
    }

    protected function findFfmpegBinary(): ?string
    {
        $candidates = ['ffmpeg', 'ffmpeg.exe'];

        foreach ($candidates as $candidate) {
            $command = PHP_OS_FAMILY === 'Windows'
                ? 'where ' . escapeshellarg($candidate) . ' 2>nul'
                : 'command -v ' . escapeshellarg($candidate) . ' 2>/dev/null';

            exec($command, $output, $exitCode);

            if ($exitCode === 0 && isset($output[0]) && trim($output[0]) !== '') {
                return trim($output[0]);
            }

            $output = [];
        }

        return null;
    }

    /** @return list<array{user_id:int,username:string,cod_id:?string,seat_number:?int}> */
    protected function participants(Tournament $tournament): array
    {
        return Registration::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->with('user:id,username,cod_id')
            ->get()
            ->filter(fn (Registration $reg) => $reg->user !== null)
            ->map(fn (Registration $reg) => [
                'user_id' => (int) $reg->user_id,
                'username' => (string) $reg->user->username,
                'cod_id' => $reg->user->cod_id,
                'seat_number' => $reg->seat_number,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{rank:int,name:?string,uid:?string,kills:?int,score:?int}> */
    protected function parsePlayersJson(string $raw): array
    {
        if (preg_match('/\[[\s\S]*\]/', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return collect($decoded)
                    ->map(function ($row) {
                        if (! is_array($row)) {
                            return null;
                        }

                        $rank = (int) ($row['rank'] ?? $row['position'] ?? 0);
                        if ($rank <= 0) {
                            return null;
                        }

                        $kills = $row['kills'] ?? $row['score'] ?? null;

                        return [
                            'rank' => $rank,
                            'name' => isset($row['player_name']) ? (string) $row['player_name'] : ($row['name'] ?? null),
                            'uid' => $this->normalizeUid($row['uid'] ?? $row['cod_id'] ?? $row['player_id'] ?? null),
                            'kills' => $kills !== null ? (int) $kills : null,
                            'score' => isset($row['score']) ? (int) $row['score'] : null,
                        ];
                    })
                    ->filter()
                    ->sortBy('rank')
                    ->values()
                    ->all();
            }
        }

        throw new RuntimeException('نتوانستیم جدول امتیازات را از رسانه استخراج کنیم. فایل واضح‌تری آپلود کنید یا پرامپت را تنظیم کنید.');
    }

    /**
     * @param  list<array{rank:int,name:?string,uid:?string,kills:?int,score:?int}>  $players
     * @param  list<array{user_id:int,username:string,cod_id:?string,seat_number:?int}>  $participants
     * @return array{matched:list<array<string,mixed>>,unmatched:list<array<string,mixed>>,suggested_winner_user_id:?int}
     */
    protected function matchPlayers(array $players, array $participants): array
    {
        $matched = [];
        $unmatched = [];
        $suggestedWinnerUserId = null;

        foreach ($players as $player) {
            $user = $this->findParticipant($player, $participants);

            if ($user) {
                $entry = [
                    'rank' => $player['rank'],
                    'detected_name' => $player['name'],
                    'detected_uid' => $player['uid'],
                    'kills' => $player['kills'] ?? $player['score'],
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'cod_id' => $user['cod_id'],
                    'match_method' => $user['match_method'],
                ];
                $matched[] = $entry;

                if ($player['rank'] === 1) {
                    $suggestedWinnerUserId = $user['user_id'];
                }
            } else {
                $unmatched[] = [
                    'rank' => $player['rank'],
                    'detected_name' => $player['name'],
                    'detected_uid' => $player['uid'],
                    'kills' => $player['kills'] ?? $player['score'],
                ];
            }
        }

        if ($suggestedWinnerUserId === null && $matched !== []) {
            $suggestedWinnerUserId = $matched[0]['user_id'] ?? null;
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'suggested_winner_user_id' => $suggestedWinnerUserId,
        ];
    }

    /**
     * @param  array{rank:int,name:?string,uid:?string,kills:?int,score:?int}  $player
     * @param  list<array{user_id:int,username:string,cod_id:?string,seat_number:?int}>  $participants
     * @return array{user_id:int,username:string,cod_id:?string,match_method:string}|null
     */
    protected function findParticipant(array $player, array $participants): ?array
    {
        $detectedUid = $this->normalizeUid($player['uid']);
        $detectedName = $this->normalizeName($player['name']);

        if ($detectedUid) {
            foreach ($participants as $p) {
                if ($this->normalizeUid($p['cod_id']) === $detectedUid) {
                    return array_merge($p, ['match_method' => 'uid']);
                }
            }
        }

        if ($detectedName) {
            foreach ($participants as $p) {
                if ($this->normalizeName($p['username']) === $detectedName) {
                    return array_merge($p, ['match_method' => 'username']);
                }
            }

            foreach ($participants as $p) {
                $username = $this->normalizeName($p['username']);
                if ($username && (str_contains($username, $detectedName) || str_contains($detectedName, $username))) {
                    return array_merge($p, ['match_method' => 'username_partial']);
                }
            }
        }

        return null;
    }

    protected function normalizeUid(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    protected function normalizeName(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $name = mb_strtolower(trim($value));
        $name = preg_replace('/\s+/', '', $name);

        return $name !== '' ? $name : null;
    }
}
