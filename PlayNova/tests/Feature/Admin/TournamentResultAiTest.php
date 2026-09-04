<?php

namespace Tests\Feature\Admin;

use App\Models\Registration;
use App\Models\Setting;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AvalAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentResultAiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Tournament $tournament;

    protected User $player;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('avalai_api_key', 'test-key');
        Setting::set('avalai_active', '1');

        $this->admin = User::factory()->create([
            'username' => 'admin_ai',
            'mobile' => '09120000001',
            'is_admin' => true,
        ]);

        $this->player = User::factory()->create([
            'username' => 'player_ai',
            'mobile' => '09120000002',
            'cod_id' => 'MyCodId123',
        ]);

        $this->tournament = Tournament::create([
            'title' => 'AI Test Tournament',
            'game' => 'Call of Duty Mobile',
            'description' => "رتبه 1: 500000\nرتبه 2: 300000",
            'entry_fee' => 0,
            'prize_pool' => 800000,
            'prize_ranks' => [
                ['rank' => 1, 'amount' => 500000],
                ['rank' => 2, 'amount' => 300000],
            ],
            'capacity' => 100,
            'seat_mode' => 1,
            'registered_count' => 1,
            'start_date' => now()->addHour(),
            'status' => 'active',
        ]);

        Registration::create([
            'tournament_id' => $this->tournament->id,
            'user_id' => $this->player->id,
            'status' => 'confirmed',
            'seat_number' => 5,
        ]);
    }

    public function test_config_endpoint_returns_prompt_and_prize_table(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/v1/admin/tournaments/{$this->tournament->id}/result-ai/config");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'system_prompt',
                    'user_prompt',
                    'seat_mode_label',
                    'prize_table',
                    'vision_model',
                ],
            ]);

        $this->assertArrayHasKey('1', $response->json('data.prize_table'));
    }

    public function test_analyze_endpoint_returns_matched_players_kills_and_coverage(): void
    {
        Sanctum::actingAs($this->admin);

        $aiJson = json_encode([
            [
                'rank' => 1,
                'team_number' => 5,
                'team_label' => 'TEAM5',
                'player_names' => ['MyCodId123'],
                'uids' => [null],
                'kills' => [7],
            ],
            [
                'rank' => 2,
                'team_number' => 8,
                'team_label' => 'TEAM8',
                'player_names' => ['UnknownPlayer'],
                'uids' => [null],
                'kills' => [2],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->mock(AvalAIService::class, function ($mock) use ($aiJson) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('chatWithVision')->once()->andReturn($aiJson);
        });

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(
            "/api/v1/admin/tournaments/{$this->tournament->id}/result-ai/analyze",
            [
                'screenshot' => UploadedFile::fake()->image('scoreboard.jpg', 900, 600),
            ],
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suggested_winner_user_id', $this->player->id)
            ->assertJsonPath('data.matched.0.kills', 7)
            ->assertJsonPath('data.coverage.ranks_found', [1, 2])
            ->assertJsonPath('data.coverage.is_complete', true)
            ->assertJsonPath('data.frames_analyzed', 1);
    }

    public function test_analyze_retries_when_prize_ranks_are_missing(): void
    {
        Sanctum::actingAs($this->admin);

        $firstPass = json_encode([
            [
                'rank' => 1,
                'team_number' => 5,
                'player_names' => ['MyCodId123'],
                'kills' => [4],
            ],
        ]);

        $secondPass = json_encode([
            [
                'rank' => 2,
                'team_number' => 8,
                'player_names' => ['Other'],
                'kills' => [1],
            ],
        ]);

        $this->mock(AvalAIService::class, function ($mock) use ($firstPass, $secondPass) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('chatWithVision')
                ->twice()
                ->andReturn($firstPass, $secondPass);
        });

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(
            "/api/v1/admin/tournaments/{$this->tournament->id}/result-ai/analyze",
            [
                'screenshot' => UploadedFile::fake()->image('scoreboard.jpg'),
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.coverage.is_complete', true)
            ->assertJsonPath('data.coverage.missing_ranks', []);
    }

    public function test_analyze_requires_ai_configuration(): void
    {
        Sanctum::actingAs($this->admin);
        Setting::set('avalai_api_key', '');

        $this->mock(AvalAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $response = $this->postJson(
            "/api/v1/admin/tournaments/{$this->tournament->id}/result-ai/analyze",
            [
                'screenshot' => UploadedFile::fake()->image('scoreboard.jpg'),
            ],
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_apply_endpoint_records_winner_and_player_kills(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson(
            "/api/v1/admin/tournaments/{$this->tournament->id}/result-ai/apply",
            [
                'winner_user_id' => $this->player->id,
                'player_stats' => [
                    [
                        'user_id' => $this->player->id,
                        'rank' => 1,
                        'kills' => 9,
                    ],
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.winner_id', $this->player->id)
            ->assertJsonPath('data.prize_pending_approval', true);

        $this->assertSame('ended', $this->tournament->fresh()->status);
        $this->assertSame($this->player->id, $this->tournament->fresh()->winner_id);
        $this->assertSame(9, $this->player->fresh()->kills);
    }

    public function test_non_admin_cannot_access_result_ai_endpoints(): void
    {
        Sanctum::actingAs($this->player);

        $this->getJson("/api/v1/admin/tournaments/{$this->tournament->id}/result-ai/config")
            ->assertForbidden();

        $this->withHeaders(['Accept' => 'application/json'])->post(
            "/api/v1/admin/tournaments/{$this->tournament->id}/result-ai/analyze",
            ['screenshot' => UploadedFile::fake()->image('scoreboard.jpg')],
        )->assertForbidden();
    }
}
