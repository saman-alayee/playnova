<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Setting;
use App\Models\Tournament;
use App\Models\User;
use App\Modules\Tournament\Services\TournamentPrizeService;
use App\Services\TournamentPrizeTableParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TournamentResultVisionService
{
    public function __construct(
        protected AvalAIService $avalai,
        protected TournamentPrizeService $prizes,
        protected TournamentPrizeTableParser $prizeTableParser,
    ) {
    }

    /** @return array{system_prompt:string,user_prompt:string,seat_mode_label:string,players_per_team:int,has_saved_prompt:bool,prize_table:array<int,float>,prize_table_text:string,vision_model:string} */
    public function promptConfig(Tournament $tournament): array
    {
        $participants = $this->participants($tournament);
        $prizeTable = $this->prizes->prizeTableFor($tournament);
        $seatMode = $tournament->seatMode();

        return [
            'system_prompt' => $this->resolveSystemPrompt($tournament),
            'user_prompt' => $this->resolveUserPrompt($tournament, $participants, $prizeTable),
            'seat_mode_label' => $tournament->seatModeLabel(),
            'players_per_team' => $seatMode,
            'has_saved_prompt' => filled($tournament->result_ai_system_prompt) || filled($tournament->result_ai_user_prompt),
            'prize_table' => $prizeTable,
            'prize_table_text' => $this->prizeTableParser->formatForPrompt($prizeTable, $seatMode),
            'vision_model' => Setting::getResultAiVisionModel(),
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
        array $extraFrames = [],
    ): array {
        $images = [$this->resolveMediaBinary($file)];
        foreach ($extraFrames as $frame) {
            if ($frame instanceof UploadedFile) {
                $images[] = $this->resolveMediaBinary($frame);
            }
        }

        $participants = $this->participants($tournament);
        $prizeTable = $this->prizes->prizeTableFor($tournament);
        $system = $systemPrompt ?: $this->resolveSystemPrompt($tournament);
        $userText = $userPrompt ?: $this->resolveUserPrompt($tournament, $participants, $prizeTable);

        $contentParts = [['type' => 'text', 'text' => $userText]];
        foreach ($images as [$mime, $binary]) {
            $contentParts[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $mime . ';base64,' . base64_encode($binary),
                    'detail' => 'high',
                ],
            ];
        }

        $raw = $this->avalai->chatWithVision(
            $contentParts,
            $system,
            Setting::getResultAiVisionModel(),
        );

        $teams = $this->parseTeamsJson($raw);
        $matchResult = $this->matchTeams($teams, $participants, $tournament);

        return [
            'players' => $matchResult['players'],
            'matched' => $matchResult['matched'],
            'unmatched' => $matchResult['unmatched'],
            'suggested_winner_user_id' => $matchResult['suggested_winner_user_id'],
            'participants' => $participants,
            'prize_table' => $prizeTable,
            'prize_table_text' => $this->prizeTableParser->formatForPrompt($prizeTable, $tournament->seatMode()),
            'vision_model' => Setting::getResultAiVisionModel(),
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
                $placementRank = isset($stat['rank']) ? (int) $stat['rank'] : null;

                return [
                    'user_id' => $userId,
                    'rank' => $placementRank && $placementRank > 0 ? $placementRank : null,
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

    /** @param  list<array{user_id:int,username:string,cod_id:?string,seat_number:?int,team_number:?int}>  $participants */
    protected function resolveUserPrompt(Tournament $tournament, array $participants, ?array $prizeTable = null): string
    {
        $template = filled($tournament->result_ai_user_prompt)
            ? trim((string) $tournament->result_ai_user_prompt)
            : Setting::getResultAiUserPromptTemplate();

        $prizeTable ??= $this->prizes->prizeTableFor($tournament);
        $seatMode = $tournament->seatMode();

        $participantHint = collect($participants)
            ->map(function (array $p) {
                $team = $p['team_number'] ? 'TEAM ' . $p['team_number'] : 'TEAM ?';
                $seat = $p['seat_number'] ? 'seat ' . $p['seat_number'] : 'seat ?';
                $codId = $p['cod_id'] ?: 'unknown';

                return "{$team} | COD ID: {$codId} | {$seat}";
            })
            ->implode("\n");

        $lastPrizeRank = $prizeTable !== [] && $prizeTable !== null ? max(array_keys($prizeTable)) : 0;
        $prizeRankCount = $prizeTable !== [] && $prizeTable !== null ? count($prizeTable) : 0;

        return str_replace(
            [
                '{tournament_title}',
                '{seat_mode_label}',
                '{players_per_team}',
                '{participants}',
                '{prize_table}',
                '{last_prize_rank}',
                '{prize_rank_count}',
            ],
            [
                $tournament->title,
                $tournament->seatModeLabel(),
                (string) $seatMode,
                $participantHint,
                $this->prizeTableParser->formatForPrompt($prizeTable ?? [], $seatMode),
                (string) $lastPrizeRank,
                (string) $prizeRankCount,
            ],
            $template,
        );
    }

    protected function seatModePromptHint(Tournament $tournament): string
    {
        $base = match ($tournament->seatMode()) {
            2 => 'Tournament type: DUO (2-player teams). Return ONE JSON row per TEAM. Rank by team placement, not individual players.',
            4 => 'Tournament type: SQUAD (4-player teams). Return ONE JSON row per TEAM. Rank by team placement, not individual players.',
            default => 'Tournament type: SOLO. Return one JSON row per player.',
        };

        return $base . ' Extract every prize rank through the last configured rank — prizes are paid to all ranks, not only top 3.';
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

    /** @return list<array{user_id:int,username:string,cod_id:?string,seat_number:?int,team_number:?int}> */
    protected function participants(Tournament $tournament): array
    {
        return Registration::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->with('user:id,username,cod_id')
            ->get()
            ->filter(fn (Registration $reg) => $reg->user !== null)
            ->map(function (Registration $reg) use ($tournament) {
                $seatNumber = (int) $reg->seat_number;

                return [
                    'user_id' => (int) $reg->user_id,
                    'username' => (string) $reg->user->username,
                    'cod_id' => $reg->user->cod_id,
                    'seat_number' => $seatNumber,
                    'team_number' => $tournament->teamNumberForSeat($seatNumber),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}>
     */
    protected function parseTeamsJson(string $raw): array
    {
        if (! preg_match('/\[[\s\S]*\]/', $raw, $matches)) {
            throw new RuntimeException('نتوانستیم جدول امتیازات را از رسانه استخراج کنیم. فایل واضح‌تری آپلود کنید یا پرامپت را تنظیم کنید.');
        }

        $decoded = json_decode($matches[0], true);
        if (! is_array($decoded)) {
            throw new RuntimeException('نتوانستیم جدول امتیازات را از رسانه استخراج کنیم. فایل واضح‌تری آپلود کنید یا پرامپت را تنظیم کنید.');
        }

        $teams = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rank = (int) ($row['rank'] ?? $row['position'] ?? $row['placement'] ?? 0);
            if ($rank <= 0) {
                continue;
            }

            $teamNumber = isset($row['team_number']) ? (int) $row['team_number'] : null;
            if (! $teamNumber && isset($row['team_label'])) {
                if (preg_match('/(\d+)/', (string) $row['team_label'], $labelMatch)) {
                    $teamNumber = (int) $labelMatch[1];
                }
            }

            $names = $row['player_names'] ?? null;
            if (! is_array($names) || $names === []) {
                $single = $row['player_name'] ?? $row['name'] ?? null;
                $names = is_string($single) && $single !== '' ? [$single] : [];
            }

            $uids = $row['uids'] ?? null;
            if (! is_array($uids)) {
                $singleUid = $this->normalizeUid($row['uid'] ?? $row['cod_id'] ?? $row['player_id'] ?? null);
                $uids = array_fill(0, count($names), $singleUid);
            }

            $killsRaw = $row['kills'] ?? $row['score'] ?? null;
            $kills = [];
            if (is_array($killsRaw)) {
                foreach ($killsRaw as $kill) {
                    $kills[] = $kill !== null ? (int) $kill : null;
                }
            } elseif ($killsRaw !== null) {
                $kills = array_fill(0, max(1, count($names)), (int) $killsRaw);
            }

            $teams[] = [
                'rank' => $rank,
                'team_number' => $teamNumber,
                'team_label' => isset($row['team_label']) ? (string) $row['team_label'] : null,
                'player_names' => array_values(array_filter(array_map('strval', $names))),
                'uids' => array_values($uids),
                'kills' => $kills,
            ];
        }

        return collect($teams)
            ->sortBy('rank')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}>  $teams
     * @param  list<array{user_id:int,username:string,cod_id:?string,seat_number:?int,team_number:?int}>  $participants
     * @return array{players:list<array<string,mixed>>,matched:list<array<string,mixed>>,unmatched:list<array<string,mixed>>,suggested_winner_user_id:?int}
     */
    protected function matchTeams(array $teams, array $participants, Tournament $tournament): array
    {
        $matched = [];
        $unmatched = [];
        $players = [];
        $usedUserIds = [];
        $suggestedWinnerUserId = null;

        foreach ($teams as $team) {
            $rank = (int) $team['rank'];
            $teamNumber = $team['team_number'];
            $names = $team['player_names'];
            $uids = $team['uids'];
            $kills = $team['kills'];
            $matchedThisTeam = [];

            foreach ($names as $index => $name) {
                $player = [
                    'rank' => $rank,
                    'name' => $name,
                    'uid' => $uids[$index] ?? null,
                    'kills' => $kills[$index] ?? null,
                ];
                $players[] = $player;

                $user = $this->findParticipant($player, $participants, $usedUserIds, $teamNumber !== null);
                if ($user) {
                    $entry = [
                        'rank' => $rank,
                        'detected_name' => $name,
                        'detected_uid' => $player['uid'],
                        'kills' => $player['kills'],
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'cod_id' => $user['cod_id'],
                        'match_method' => $user['match_method'],
                        'match_score' => $user['match_score'] ?? null,
                        'team_number' => $teamNumber,
                    ];
                    $matched[] = $entry;
                    $matchedThisTeam[$user['user_id']] = $entry;

                    if ($rank === 1 && $suggestedWinnerUserId === null) {
                        $suggestedWinnerUserId = $user['user_id'];
                    }
                } else {
                    $unmatched[] = [
                        'rank' => $rank,
                        'detected_name' => $name,
                        'detected_uid' => $player['uid'],
                        'kills' => $player['kills'],
                        'team_number' => $teamNumber,
                    ];
                }
            }

            if ($teamNumber && $tournament->seatMode() > 1) {
                foreach ($participants as $participant) {
                    $userId = (int) $participant['user_id'];
                    if (isset($usedUserIds[$userId]) || (int) ($participant['team_number'] ?? 0) !== (int) $teamNumber) {
                        continue;
                    }

                    $usedUserIds[$userId] = true;
                    $entry = [
                        'rank' => $rank,
                        'detected_name' => null,
                        'detected_uid' => $participant['cod_id'],
                        'kills' => null,
                        'user_id' => $userId,
                        'username' => $participant['username'],
                        'cod_id' => $participant['cod_id'],
                        'match_method' => 'team_number',
                        'match_score' => 1.0,
                        'team_number' => $teamNumber,
                    ];
                    $matched[] = $entry;
                    $matchedThisTeam[$userId] = $entry;

                    if ($rank === 1 && $suggestedWinnerUserId === null) {
                        $suggestedWinnerUserId = $userId;
                    }
                }
            }
        }

        if ($suggestedWinnerUserId === null && $matched !== []) {
            $suggestedWinnerUserId = $matched[0]['user_id'] ?? null;
        }

        return [
            'players' => $players,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'suggested_winner_user_id' => $suggestedWinnerUserId,
        ];
    }

    /**
     * @param  array{rank:int,name:?string,uid:?string,kills:?int,score:?int}  $player
     * @param  list<array{user_id:int,username:string,cod_id:?string,seat_number:?int}>  $participants
     * @param  array<int, true>  $usedUserIds
     * @return array{user_id:int,username:string,cod_id:?string,seat_number:?int,match_method:string,match_score?:float}|null
     */
    protected function findParticipant(array $player, array $participants, array &$usedUserIds, bool $strictNameMatch = false): ?array
    {
        $minScore = $strictNameMatch ? 0.82 : 0.72;

        return PlayerNameMatcher::findBestMatch(
            is_string($player['name'] ?? null) ? $player['name'] : null,
            $player['uid'] ?? null,
            $participants,
            $usedUserIds,
            $minScore,
            allowUsernameMatch: false,
        );
    }

    protected function normalizeUid(mixed $value): ?string
    {
        return PlayerNameMatcher::normalizeUid($value);
    }

    protected function normalizeName(mixed $value): ?string
    {
        return PlayerNameMatcher::normalizeName($value);
    }
}
