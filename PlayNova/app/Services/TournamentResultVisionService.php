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
        $images = $this->resolveMediaImages($file);
        foreach ($extraFrames as $frame) {
            if ($frame instanceof UploadedFile) {
                $images[] = $this->resolveMediaBinary($frame);
            }
        }

        $participants = $this->participants($tournament);
        $prizeTable = $this->prizes->prizeTableFor($tournament);
        $system = $systemPrompt ?: $this->resolveSystemPrompt($tournament);
        $userText = $userPrompt ?: $this->resolveUserPrompt($tournament, $participants, $prizeTable);

        if (count($images) > 1) {
            $userText .= "\n\nYou receive " . count($images) . ' frames from a scrolling scoreboard recording. Merge ALL frames into one complete ranked list. Do not stop at rank 3.';
        }

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
        $teams = $this->maybeRetryMissingRanks($teams, $prizeTable, $contentParts, $system, $userText);
        $matchResult = $this->matchTeams($teams, $participants, $tournament);
        $coverage = $this->buildCoverage($teams, $prizeTable, $matchResult);

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
            'coverage' => $coverage,
            'frames_analyzed' => count($images),
        ];
    }

    /**
     * Match a ChatGPT / external JSON dump to registered players without calling a paid API.
     *
     * @return array{
     *   players: list<array{rank:int,name:?string,uid:?string,kills:?int,score:?int}>,
     *   matched: list<array<string,mixed>>,
     *   unmatched: list<array<string,mixed>>,
     *   suggested_winner_user_id: ?int,
     *   participants: list<array<string,mixed>>,
     *   prize_table: array<int,float>,
     *   prize_table_text: string,
     *   vision_model: string,
     *   raw_excerpt: string,
     *   coverage: array<string,mixed>,
     *   frames_analyzed: int
     * }
     */
    public function analyzePastedOutput(Tournament $tournament, string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new RuntimeException('خروجی چت‌جی‌پی‌تی خالی است.');
        }

        $participants = $this->participants($tournament);
        $prizeTable = $this->prizes->prizeTableFor($tournament);

        try {
            $teams = $this->parseTeamsJson($raw);
        } catch (RuntimeException) {
            throw new RuntimeException(
                'خروجی چت‌جی‌پی‌تی قابل خواندن نیست. باید یک آرایه JSON داشته باشد، مثلاً [{"rank":1,"team_label":"TEAM11","player_names":["A","B"],"kills":[3,1]}].',
            );
        }

        if ($teams === []) {
            throw new RuntimeException('در خروجی هیچ رتبه معتبری پیدا نشد. برای هر تیم فیلد rank را چک کنید.');
        }

        $matchResult = $this->matchTeams($teams, $participants, $tournament);
        $coverage = $this->buildCoverage($teams, $prizeTable, $matchResult);

        return [
            'players' => $matchResult['players'],
            'matched' => $matchResult['matched'],
            'unmatched' => $matchResult['unmatched'],
            'suggested_winner_user_id' => $matchResult['suggested_winner_user_id'],
            'participants' => $participants,
            'prize_table' => $prizeTable,
            'prize_table_text' => $this->prizeTableParser->formatForPrompt($prizeTable, $tournament->seatMode()),
            'vision_model' => 'chatgpt-manual',
            'raw_excerpt' => mb_substr($raw, 0, 500),
            'coverage' => $coverage,
            'frames_analyzed' => 0,
        ];
    }

    public static function teamNumberFromLabel(?string $label, ?int $fallback = null): ?int
    {
        if ($label !== null && $label !== '') {
            $normalized = self::normalizePersianDigits(trim($label));

            if (preg_match('/(?:team|تیم)\s*#?\s*(\d+)/iu', $normalized, $matches)) {
                return (int) $matches[1];
            }

            if (preg_match('/#(\d+)/', $normalized, $matches)) {
                return (int) $matches[1];
            }

            if (preg_match('/(\d+)/', $normalized, $matches)) {
                return (int) $matches[1];
            }
        }

        return $fallback;
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

    /** @return list<array{0:string,1:string}> */
    protected function resolveMediaImages(UploadedFile $file): array
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));

        if (str_starts_with($mime, 'image/')) {
            return [$this->resolveMediaBinary($file)];
        }

        if (str_starts_with($mime, 'video/')) {
            $frames = $this->extractVideoFrames($file, 10);
            if ($frames === []) {
                throw new RuntimeException(
                    'استخراج فریم از ویدیو ممکن نشد. از «تحلیل ویدیو» (چند فریم) استفاده کنید یا ffmpeg را روی سرور نصب کنید.',
                );
            }

            return array_map(static fn (string $binary) => ['image/jpeg', $binary], $frames);
        }

        throw new RuntimeException('فرمت فایل پشتیبانی نمی‌شود. تصویر (JPG/PNG/WebP) یا ویدیو (MP4/WebM) آپلود کنید.');
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

    /** @return list<string> */
    protected function extractVideoFrames(UploadedFile $file, int $maxFrames = 10): array
    {
        $inputPath = $file->getRealPath();
        if (! is_string($inputPath) || ! is_readable($inputPath)) {
            return [];
        }

        $ffmpeg = $this->findFfmpegBinary();
        if ($ffmpeg === null) {
            return [];
        }

        $duration = $this->probeVideoDuration($inputPath) ?? 30.0;
        $frameCount = min($maxFrames, max(4, (int) ceil($duration / 2.5)));
        $frames = [];

        for ($index = 0; $index < $frameCount; $index++) {
            $second = max(0.5, ($duration * ($index + 1)) / ($frameCount + 1));
            $binary = $this->extractVideoFrameAtSecond($ffmpeg, $inputPath, $second);
            if ($binary !== null) {
                $frames[] = $binary;
            }
        }

        if ($frames !== []) {
            return $frames;
        }

        foreach ([1, 3, 5, 8, 12, 16] as $second) {
            $binary = $this->extractVideoFrameAtSecond($ffmpeg, $inputPath, (float) $second);
            if ($binary !== null) {
                $frames[] = $binary;
            }
        }

        return $frames;
    }

    protected function extractVideoFrameAtSecond(string $ffmpeg, string $inputPath, float $second): ?string
    {
        $outputPath = tempnam(sys_get_temp_dir(), 'pn_frame_') . '.jpg';
        $command = sprintf(
            '%s -hide_banner -loglevel error -ss %.2f -i %s -vframes 1 -q:v 2 %s 2>&1',
            escapeshellarg($ffmpeg),
            $second,
            escapeshellarg($inputPath),
            escapeshellarg($outputPath),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || ! is_readable($outputPath) || filesize($outputPath) <= 0) {
            @unlink($outputPath);

            return null;
        }

        $binary = file_get_contents($outputPath);
        @unlink($outputPath);

        return $binary !== false && $binary !== '' ? $binary : null;
    }

    protected function probeVideoDuration(string $inputPath): ?float
    {
        $ffprobe = $this->findFfprobeBinary();
        if ($ffprobe === null) {
            return null;
        }

        $command = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
            escapeshellarg($ffprobe),
            escapeshellarg($inputPath),
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || ! isset($output[0])) {
            return null;
        }

        $duration = (float) trim((string) $output[0]);

        return $duration > 0 ? $duration : null;
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

        foreach ([1.0, 3.0, 5.0, 8.0] as $second) {
            $frame = $this->extractVideoFrameAtSecond($ffmpeg, $inputPath, $second);
            if ($frame !== null) {
                return $frame;
            }
        }

        return null;
    }

    protected function findFfprobeBinary(): ?string
    {
        $candidates = ['ffprobe', 'ffprobe.exe'];

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
            if (! $teamNumber) {
                $teamNumber = self::teamNumberFromLabel(
                    isset($row['team_label']) ? (string) $row['team_label'] : null,
                );
            }

            $names = $row['player_names'] ?? null;
            if (! is_array($names) || $names === []) {
                $single = $row['player_name'] ?? $row['name'] ?? null;
                $names = is_string($single) && $single !== '' ? [$single] : [];
            }

            $uids = $row['uids'] ?? null;
            if (! is_array($uids)) {
                $singleUid = $this->normalizeUid($row['uid'] ?? $row['cod_id'] ?? $row['player_id'] ?? null);
                $uids = array_fill(0, max(1, count($names)), $singleUid);
            }

            $killsRaw = $row['kills'] ?? $row['kill'] ?? $row['score'] ?? $row['scores'] ?? null;
            $kills = $this->normalizeKillsArray($killsRaw, count($names));

            $teams[] = [
                'rank' => $rank,
                'team_number' => $teamNumber,
                'team_label' => isset($row['team_label']) ? (string) $row['team_label'] : null,
                'player_names' => array_values(array_filter(array_map('strval', $names))),
                'uids' => array_values($uids),
                'kills' => $kills,
            ];
        }

        return $this->consolidateTeams($teams);
    }

    /**
     * @param  list<array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}>  $teams
     * @return list<array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}>
     */
    protected function consolidateTeams(array $teams): array
    {
        if ($teams === []) {
            return [];
        }

        $byRank = [];
        foreach ($teams as $team) {
            $rank = (int) $team['rank'];
            if (! isset($byRank[$rank])) {
                $byRank[$rank] = $team;
                continue;
            }

            $byRank[$rank] = $this->mergeTwoTeamRows($byRank[$rank], $team);
        }

        $bestRankByTeamNumber = [];
        foreach ($byRank as $rank => $team) {
            $teamNumber = $team['team_number'] ?? null;
            if ($teamNumber === null) {
                continue;
            }

            if (! isset($bestRankByTeamNumber[$teamNumber]) || $rank < $bestRankByTeamNumber[$teamNumber]) {
                if (isset($bestRankByTeamNumber[$teamNumber])) {
                    unset($byRank[$bestRankByTeamNumber[$teamNumber]]);
                }
                $bestRankByTeamNumber[$teamNumber] = $rank;
            } else {
                unset($byRank[$rank]);
            }
        }

        return collect($byRank)
            ->sortBy('rank')
            ->values()
            ->all();
    }

    /**
     * @param  array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}  $left
     * @param  array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}  $right
     * @return array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}
     */
    protected function mergeTwoTeamRows(array $left, array $right): array
    {
        $names = array_values(array_unique(array_merge($left['player_names'], $right['player_names'])));
        $count = max(count($names), count($left['uids']), count($right['uids']), count($left['kills']), count($right['kills']));

        $uids = [];
        $kills = [];
        for ($index = 0; $index < $count; $index++) {
            $uids[] = $left['uids'][$index] ?? $right['uids'][$index] ?? null;
            $leftKill = $left['kills'][$index] ?? null;
            $rightKill = $right['kills'][$index] ?? null;
            $kills[] = $leftKill ?? $rightKill;
        }

        return [
            'rank' => (int) $left['rank'],
            'team_number' => $left['team_number'] ?? $right['team_number'],
            'team_label' => $left['team_label'] ?: $right['team_label'],
            'player_names' => $names,
            'uids' => $uids,
            'kills' => $kills,
        ];
    }

    /** @return list<?int> */
    protected function normalizeKillsArray(mixed $killsRaw, int $nameCount): array
    {
        if (is_array($killsRaw)) {
            return array_map(
                static fn ($kill) => $kill !== null && $kill !== '' ? (int) $kill : null,
                $killsRaw,
            );
        }

        if ($killsRaw !== null && $killsRaw !== '') {
            return array_fill(0, max(1, $nameCount), (int) $killsRaw);
        }

        return [];
    }

    /**
     * @param  list<array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}>  $teams
     * @param  array<int,float>|null  $prizeTable
     * @param  array{matched:list<array<string,mixed>>,unmatched:list<array<string,mixed>>}  $matchResult
     * @return array{
     *   ranks_found:list<int>,
     *   missing_ranks:list<int>,
     *   expected_last_rank:int,
     *   teams_detected:int,
     *   matched_players:int,
     *   unmatched_players:int,
     *   players_with_kills:int,
     *   players_detected:int,
     *   is_complete:bool
     * }
     */
    protected function buildCoverage(array $teams, ?array $prizeTable, array $matchResult): array
    {
        $ranksFound = array_values(array_unique(array_map(static fn (array $team) => (int) $team['rank'], $teams)));
        sort($ranksFound);

        $expectedLastRank = $prizeTable !== [] && $prizeTable !== null ? (int) max(array_keys($prizeTable)) : 0;
        $missingRanks = $expectedLastRank > 0
            ? array_values(array_diff(range(1, $expectedLastRank), $ranksFound))
            : [];

        $playersDetected = 0;
        $playersWithKills = 0;
        foreach ($teams as $team) {
            $playerCount = max(count($team['player_names']), count($team['kills']));
            $playersDetected += $playerCount;
            foreach ($team['kills'] as $kill) {
                if ($kill !== null) {
                    $playersWithKills++;
                }
            }
        }

        $matchedWithKills = count(array_filter(
            $matchResult['matched'],
            static fn (array $row) => isset($row['kills']) && $row['kills'] !== null,
        ));

        return [
            'ranks_found' => $ranksFound,
            'missing_ranks' => $missingRanks,
            'expected_last_rank' => $expectedLastRank,
            'teams_detected' => count($teams),
            'matched_players' => count($matchResult['matched']),
            'unmatched_players' => count($matchResult['unmatched']),
            'players_with_kills' => max($playersWithKills, $matchedWithKills),
            'players_detected' => $playersDetected,
            'is_complete' => $missingRanks === [],
        ];
    }

    /**
     * @param  list<array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}>  $teams
     * @param  array<int,float>|null  $prizeTable
     * @param  list<array<string,mixed>>  $contentParts
     * @return list<array{rank:int,team_number:?int,team_label:?string,player_names:list<string>,uids:list<?string>,kills:list<?int>}>
     */
    protected function maybeRetryMissingRanks(
        array $teams,
        ?array $prizeTable,
        array $contentParts,
        string $system,
        string $userText,
    ): array {
        $coverage = $this->buildCoverage($teams, $prizeTable, ['matched' => [], 'unmatched' => []]);
        if ($coverage['is_complete'] || $coverage['missing_ranks'] === []) {
            return $teams;
        }

        $missing = implode(', ', $coverage['missing_ranks']);
        $retryText = $userText . "\n\nRETRY: The previous JSON missed prize ranks: {$missing}. "
            . "Return ONLY a JSON array with one row per missing rank ({$missing}). "
            . 'Include team_number, player_names, and kills (crosshair icon numbers).';

        $retryParts = $contentParts;
        $retryParts[0] = ['type' => 'text', 'text' => $retryText];

        try {
            $retryRaw = $this->avalai->chatWithVision(
                $retryParts,
                $system,
                Setting::getResultAiVisionModel(),
            );
            $retryTeams = $this->parseTeamsJson($retryRaw);

            return $this->consolidateTeams(array_merge($teams, $retryTeams));
        } catch (RuntimeException) {
            return $teams;
        }
    }

    protected static function normalizePersianDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        return str_replace($persian, range(0, 9), str_replace($arabic, range(0, 9), $value));
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
            $teamParticipants = $teamNumber && $tournament->seatMode() > 1
                ? array_values(array_filter(
                    $participants,
                    static fn (array $participant) => (int) ($participant['team_number'] ?? 0) === (int) $teamNumber,
                ))
                : [];

            foreach ($names as $index => $name) {
                $player = [
                    'rank' => $rank,
                    'name' => $name,
                    'uid' => $uids[$index] ?? null,
                    'kills' => $kills[$index] ?? null,
                ];
                $players[] = $player;

                $user = $this->findParticipant(
                    $player,
                    $participants,
                    $usedUserIds,
                    $teamNumber !== null,
                    $teamNumber,
                );
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

            if ($teamParticipants !== []) {
                $killCursor = 0;
                foreach ($teamParticipants as $participant) {
                    $userId = (int) $participant['user_id'];
                    if (isset($usedUserIds[$userId])) {
                        continue;
                    }

                    $inferredKill = null;
                    while ($killCursor < count($kills) && ($kills[$killCursor] ?? null) === null) {
                        $killCursor++;
                    }
                    if ($killCursor < count($kills)) {
                        $inferredKill = $kills[$killCursor];
                        $killCursor++;
                    }

                    $usedUserIds[$userId] = true;
                    $entry = [
                        'rank' => $rank,
                        'detected_name' => null,
                        'detected_uid' => $participant['cod_id'],
                        'kills' => $inferredKill,
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
    protected function findParticipant(
        array $player,
        array $participants,
        array &$usedUserIds,
        bool $strictNameMatch = false,
        ?int $teamNumber = null,
    ): ?array {
        $pool = $participants;
        if ($teamNumber !== null) {
            $teamPool = array_values(array_filter(
                $participants,
                static fn (array $participant) => (int) ($participant['team_number'] ?? 0) === $teamNumber,
            ));
            if ($teamPool !== []) {
                $pool = $teamPool;
            }
        }

        $minScore = $strictNameMatch ? 0.78 : 0.68;

        return PlayerNameMatcher::findBestMatch(
            is_string($player['name'] ?? null) ? $player['name'] : null,
            $player['uid'] ?? null,
            $pool,
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
